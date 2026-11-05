import hashlib
import json
import logging
from dataclasses import dataclass, field

from rank_bm25 import BM25Okapi

from langchain_community.vectorstores import FAISS
from langchain_huggingface import HuggingFaceEmbeddings
from langchain_ollama import ChatOllama
from langchain_core.documents import Document
from langchain_core.prompts import ChatPromptTemplate
from langchain_core.output_parsers import StrOutputParser

from config import get_settings, BASE_DIR

# Logger
logger = logging.getLogger(__name__)
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(name)s: %(message)s",
)

# Settings
settings = get_settings()


# Embedding Model
_embeddings_instance: HuggingFaceEmbeddings | None = None


def get_embeddings() -> HuggingFaceEmbeddings:
    global _embeddings_instance
    if _embeddings_instance is None:
        logger.info(f"Memuat embedding model '{settings.embedding_model}'...")
        _embeddings_instance = HuggingFaceEmbeddings(
            model_name=f"sentence-transformers/{settings.embedding_model}",
            model_kwargs={"device": "cpu"},
            encode_kwargs={"normalize_embeddings": True},
        )
        logger.info("Embedding model berhasil dimuat.")
    return _embeddings_instance


# Hybrid Retriever
class HybridRetriever:
    def __init__(
        self,
        alpha: float | None = None,
        top_k: int | None = None,
    ):
        self.alpha = alpha if alpha is not None else settings.hybrid_alpha
        self.top_k = top_k if top_k is not None else settings.top_k
        self.vectorstore: FAISS | None = None
        self.all_docs: list[Document] = []
        self.bm25: BM25Okapi | None = None

        self._load_vectorstore()

    def _load_vectorstore(self) -> None:
        vs_path = settings.vectorstore_abs_path
        index_file = vs_path / "index.faiss"

        if not index_file.exists():
            logger.warning(
                "Vector store belum ada. Silakan index dokumen terlebih dahulu."
            )
            return

        try:
            embeddings = get_embeddings()
            self.vectorstore = FAISS.load_local(
                str(vs_path),
                embeddings,
                allow_dangerous_deserialization=True,
            )
            logger.info("Vector store berhasil dimuat untuk retrieval.")

            self._build_bm25_index()

        except Exception as e:
            logger.error(f"Gagal memuat vector store: {e}")
            self.vectorstore = None

    def _build_bm25_index(self) -> None:
        if self.vectorstore is None:
            return

        # Ekstrak semua dokumen dari FAISS docstore
        docstore = self.vectorstore.docstore
        index_to_id = self.vectorstore.index_to_docstore_id

        self.all_docs = []
        for idx in range(len(index_to_id)):
            doc_id = index_to_id[idx]
            doc = docstore.search(doc_id)
            if hasattr(doc, "page_content"):
                self.all_docs.append(doc)

        if not self.all_docs:
            logger.warning("Tidak ada dokumen di vector store untuk BM25.")
            return

        # Tokenisasi sederhana untuk BM25 (cocok untuk Bahasa Indonesia)
        tokenized_corpus = [
            doc.page_content.lower().split() for doc in self.all_docs
        ]
        self.bm25 = BM25Okapi(tokenized_corpus)
        logger.info(f"BM25 index dibangun dengan {len(self.all_docs)} dokumen.")

    def reload(self) -> None:
        self._load_vectorstore()
        logger.info("Retriever berhasil dimuat ulang.")

    def _semantic_search(self, query: str, k: int) -> list[tuple[Document, float]]:
        if self.vectorstore is None:
            return []

        try:
            results = self.vectorstore.similarity_search_with_score(query, k=k)
            return results
        except Exception as e:
            logger.error(f"Gagal melakukan semantic search: {e}")
            return []

    def _bm25_search(self, query: str, k: int) -> list[tuple[Document, float]]:
        if self.bm25 is None or not self.all_docs:
            return []

        try:
            tokenized_query = query.lower().split()
            scores = self.bm25.get_scores(tokenized_query)

            doc_scores = list(zip(self.all_docs, scores))
            doc_scores.sort(key=lambda x: x[1], reverse=True)

            return doc_scores[:k]
        except Exception as e:
            logger.error(f"Gagal melakukan BM25 search: {e}")
            return []

    def _reciprocal_rank_fusion(
        self,
        semantic_results: list[tuple[Document, float]],
        bm25_results: list[tuple[Document, float]],
        k_constant: int = 60,
    ) -> list[Document]:
        rrf_scores: dict[int, float] = {}  # id(doc) -> skor
        doc_map: dict[int, Document] = {}  # id(doc) -> doc

        # Skor dari semantic search
        for rank, (doc, _score) in enumerate(semantic_results):
            doc_key = id(doc)
            doc_map[doc_key] = doc
            rrf_scores[doc_key] = rrf_scores.get(doc_key, 0.0) + (
                self.alpha * (1.0 / (k_constant + rank + 1))
            )

        # Skor dari BM25 search
        for rank, (doc, _score) in enumerate(bm25_results):
            matched_key = None
            for existing_key, existing_doc in doc_map.items():
                if (
                    existing_doc.page_content == doc.page_content
                    and existing_doc.metadata.get("source") == doc.metadata.get("source")
                    and existing_doc.metadata.get("chunk_index") == doc.metadata.get("chunk_index")
                ):
                    matched_key = existing_key
                    break

            if matched_key is not None:
                rrf_scores[matched_key] += (
                    (1 - self.alpha) * (1.0 / (k_constant + rank + 1))
                )
            else:
                doc_key = id(doc)
                doc_map[doc_key] = doc
                rrf_scores[doc_key] = (
                    (1 - self.alpha) * (1.0 / (k_constant + rank + 1))
                )

        # Urutkan berdasarkan skor RRF (tertinggi dulu)
        sorted_docs = sorted(
            rrf_scores.items(),
            key=lambda x: x[1],
            reverse=True,
        )

        return [doc_map[doc_key] for doc_key, _ in sorted_docs[: self.top_k]]

    def retrieve(self, query: str) -> list[Document]:
        if self.vectorstore is None:
            logger.warning("Vector store belum dimuat. Tidak bisa melakukan pencarian.")
            return []
        fetch_k = self.top_k * 3

        semantic_results = self._semantic_search(query, k=fetch_k)
        bm25_results = self._bm25_search(query, k=fetch_k)

        logger.info(
            f"Hasil pencarian — Semantic: {len(semantic_results)} dok, "
            f"BM25: {len(bm25_results)} dok"
        )

        fused_docs = self._reciprocal_rank_fusion(semantic_results, bm25_results)

        logger.info(
            f"Setelah RRF fusion: {len(fused_docs)} dokumen relevan "
            f"(alpha={self.alpha}, top_k={self.top_k})"
        )

        return fused_docs


# Prompt Template Bahasa Indonesia

SYSTEM_PROMPT = """\
Kamu adalah asisten akademik yang membantu mahasiswa dan pegawai \
menjawab pertanyaan seputar dokumen akademik kampus.

ATURAN KETAT:
1. Jawab HANYA berdasarkan konteks dokumen yang diberikan di bawah.
2. Jika informasi yang ditanyakan TIDAK ADA dalam konteks, jawab: \
"Maaf, informasi tersebut tidak ditemukan di dokumen yang tersedia."
3. JANGAN mengarang atau menambahkan informasi di luar konteks.
4. Gunakan bahasa Indonesia yang ramah, jelas, dan profesional.
5. WAJIB gunakan format cetak tebal (**bold**) secara konsisten untuk:
   - Nama dokumen, formulir, atau surat resmi: **Surat Keterangan Lulus (SKL)**
   - Jumlah SKS, IPK, nilai: **minimal 144 SKS**, **IPK ≥ 2,00**
   - Batas waktu dan tenggat: **paling lambat H-3 sebelum penutupan**
   - Nama prosedur atau tahapan: **yudisium**, **sidang tugas akhir**
   - Persyaratan krusial: **ditandatangani basah oleh Pembimbing 1 dan 2**
   - Nominal biaya atau angka penting lainnya
   Contoh BENAR: "Mahasiswa harus menyelesaikan **minimal 144 SKS** dengan **IPK minimal 2,00** untuk mendaftar **yudisium**."
   Contoh SALAH: "Mahasiswa harus menyelesaikan minimal 144 SKS dengan IPK minimal 2,00 untuk mendaftar yudisium."
6. JANGAN sertakan baris "Sumber: [nama file]" di akhir jawaban. \
Sistem UI sudah menampilkan rujukan dokumen secara otomatis.
"""

HUMAN_PROMPT = """\
{history_section}Konteks dokumen:
{context}

Pertanyaan saat ini: {question}
"""

prompt_template = ChatPromptTemplate.from_messages([
    ("system", SYSTEM_PROMPT),
    ("human", HUMAN_PROMPT),
])


# Format riwayat percakapan menjadi teks untuk prompt LLM
def format_history(history: list) -> str:
    """Format riwayat percakapan menjadi teks yang dapat dibaca LLM."""
    if not history:
        return ""
    hasil = ""
    for item in history:
        prefix = "User" if item.get("role") == "user" else "Asisten"
        hasil += f"{prefix}: {item.get('content', '')}\n"
    return hasil.strip()


# Cache In-Memory

_answer_cache: dict[str, dict] = {}


def _question_hash(question: str, history: list = None) -> str:
    """Buat hash unik untuk pertanyaan + konteks riwayat terakhir."""
    normalized = question.strip().lower()
    # Sertakan 2 pesan terakhir di cache key agar tidak terlalu spesifik
    if history:
        history_suffix = json.dumps(history[-2:], ensure_ascii=False)
    else:
        history_suffix = ""
    return hashlib.md5((normalized + history_suffix).encode("utf-8")).hexdigest()


def clear_cache() -> None:
    global _answer_cache
    jumlah = len(_answer_cache)
    _answer_cache.clear()
    logger.info(f"Cache jawaban dikosongkan ({jumlah} entri dihapus).")


# LLM (Ollama)

_llm_instance: ChatOllama | None = None


def get_llm() -> ChatOllama:
    global _llm_instance
    if _llm_instance is None:
        logger.info(
            f"Menghubungkan ke Ollama ({settings.ollama_base_url}) "
            f"dengan model '{settings.ollama_model}'..."
        )
        _llm_instance = ChatOllama(
            base_url=settings.ollama_base_url,
            model=settings.ollama_model,
            temperature=0.3,  # Rendah agar jawaban lebih faktual
        )
        logger.info("Koneksi ke Ollama berhasil.")
    return _llm_instance


# Fungsi Utama: ask()

_retriever_instance: HybridRetriever | None = None

# Singleton retriever 
def get_retriever() -> HybridRetriever:
    global _retriever_instance
    if _retriever_instance is None:
        _retriever_instance = HybridRetriever()
    return _retriever_instance

#reload retriever
def reload_retriever() -> None:
    global _retriever_instance
    _retriever_instance = None
    clear_cache()
    logger.info("Retriever akan dimuat ulang pada pencarian berikutnya.")

# Fungsi Utama: ask()
def ask(question: str, history: list = None) -> dict:
    """Fungsi utama tanya-jawab dengan dukungan riwayat percakapan."""
    if history is None:
        history = []
    question = question.strip()
    if not question:
        return {
            "answer": "Silakan masukkan pertanyaan Anda.",
            "sources": [],
            "chunks_used": 0,
            "from_cache": False,
        }

    # Cek cache (dengan konteks riwayat)
    q_hash = _question_hash(question, history)
    if q_hash in _answer_cache:
        logger.info(f"Jawaban ditemukan di cache untuk: '{question[:50]}...'")
        cached = _answer_cache[q_hash]
        cached["from_cache"] = True
        return cached

    # Cari dokumen relevan
    retriever = get_retriever()
    relevant_docs = retriever.retrieve(question)

    if not relevant_docs:
        answer_no_docs = (
            "Maaf, informasi tersebut tidak ditemukan di dokumen yang tersedia. "
            "Pastikan dokumen terkait sudah diupload ke sistem."
        )
        result = {
            "answer": answer_no_docs,
            "sources": [],
            "chunks_used": 0,
            "from_cache": False,
        }
        return result

    # Bangun konteks
    context_parts = []
    sources = set()

    for doc in relevant_docs:
        source_name = doc.metadata.get("source", "Tidak diketahui")
        doc_type = doc.metadata.get("doc_type", "")
        sources.add(source_name)

        header = f"[Sumber: {source_name}"
        if doc_type:
            header += f" | Tipe: {doc_type}"
        header += "]"

        context_parts.append(f"{header}\n{doc.page_content}")

    context = "\n\n---\n\n".join(context_parts)
    sources_list = sorted(sources)

    logger.info(
        f"Konteks dibangun dari {len(relevant_docs)} chunk, "
        f"sumber: {', '.join(sources_list)}"
    )

    # Bangun section riwayat percakapan untuk prompt
    history_section = ""
    if history:
        formatted = format_history(history)
        history_section = (
            f"RIWAYAT PERCAKAPAN SEBELUMNYA:\n"
            f"{formatted}\n"
            f"─────────────────────────────\n\n"
        )
        logger.info(f"Riwayat percakapan disertakan: {len(history)} pesan")

    # Generate jawaban via LLM
    try:
        llm = get_llm()
        chain = prompt_template | llm | StrOutputParser()

        answer = chain.invoke({
            "context": context,
            "question": question,
            "history_section": history_section,
        })

        logger.info("Jawaban berhasil digenerate oleh LLM.")

    except Exception as e:
        error_msg = (
            f"Maaf, terjadi kesalahan saat memproses pertanyaan Anda. "
            f"Silakan coba beberapa saat lagi. (Error: {e})"
        )
        logger.error(f"Gagal generate jawaban: {e}")
        return {
            "answer": error_msg,
            "sources": sources_list,
            "chunks_used": len(relevant_docs),
            "from_cache": False,
        }

    # Simpan ke cache dan kembalikan
    result = {
        "answer": answer,
        "sources": sources_list,
        "chunks_used": len(relevant_docs),
        "from_cache": False,
    }

    _answer_cache[q_hash] = result.copy()
    logger.info(
        f"Jawaban disimpan ke cache (total cache: {len(_answer_cache)} entri)."
    )

    return result
