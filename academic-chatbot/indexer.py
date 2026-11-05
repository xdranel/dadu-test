import hashlib
import json
import logging
from datetime import datetime
from pathlib import Path
import fitz
import pytesseract
from pdf2image import convert_from_path
from PIL import Image
from langchain_core.documents import Document
from langchain_community.document_loaders import Docx2txtLoader
from langchain_community.document_loaders import UnstructuredPowerPointLoader

from langchain_text_splitters import RecursiveCharacterTextSplitter
from langchain_huggingface import HuggingFaceEmbeddings
from langchain_community.vectorstores import FAISS

from config import get_settings, BASE_DIR

# Konfigurasi Tesseract & Poppler (Windows)
pytesseract.pytesseract.tesseract_cmd = r"C:\Users\KRISNA PUTRA\AppData\Local\Programs\Tesseract-OCR\tesseract.exe"
POPPLER_PATH = r"C:\poppler\Library\bin"

# Logger
logger = logging.getLogger(__name__)
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(name)s: %(message)s",
)

# Settings
settings = get_settings()


# Utilitas
def file_hash(path: str | Path) -> str:
    hasher = hashlib.md5()
    with open(path, "rb") as f:
        for chunk in iter(lambda: f.read(8192), b""):
            hasher.update(chunk)
    return hasher.hexdigest()

# Registry
def load_registry() -> dict:
    registry_path = settings.registry_abs_path
    if not registry_path.exists():
        logger.info("Registry belum ada, membuat registry baru.")
        return {"documents": []}

    try:
        content = registry_path.read_text(encoding="utf-8")
        return json.loads(content)
    except (json.JSONDecodeError, Exception) as e:
        logger.error(f"Gagal membaca registry: {e}. Membuat registry baru.")
        return {"documents": []}

# Menyimpan Registry
def save_registry(registry: dict) -> None:
    registry_path = settings.registry_abs_path
    registry_path.parent.mkdir(parents=True, exist_ok=True)

    registry_path.write_text(
        json.dumps(registry, indent=2, ensure_ascii=False),
        encoding="utf-8",
    )
    logger.info(f"Registry disimpan ke {registry_path}")


# Embedding Model (Singleton)
_embeddings_instance: HuggingFaceEmbeddings | None = None

# Embedding Model
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


# Loader Dokumen

def load_document(file_path: str | Path) -> list[Document]:
    path = Path(file_path)

    if not path.exists():
        raise FileNotFoundError(f"File tidak ditemukan: {path}")

    ext = path.suffix.lower()
    if ext == ".pdf":
        return load_pdf_smart(str(path))
    elif ext == ".docx":
        logger.info(f"Memuat dokumen '{path.name}' dengan Docx2txtLoader...")
        try:
            return Docx2txtLoader(str(path)).load()
        except Exception as e:
            raise RuntimeError(f"Gagal memuat dokumen DOCX '{path.name}': {e}") from e
    elif ext in {".pptx", ".ppt"}:
        logger.info(f"Memuat dokumen '{path.name}' dengan UnstructuredPowerPointLoader...")
        try:
            return UnstructuredPowerPointLoader(str(path)).load()
        except Exception as e:
            raise RuntimeError(f"Gagal memuat dokumen PPTX '{path.name}': {e}") from e
    else:
        raise ValueError(
            f"Format file '{ext}' tidak didukung. "
            f"Format yang didukung: .pdf, .docx, .pptx, .ppt"
        )

# Smart PDF Loader
def load_pdf_smart(file_path: str) -> list[Document]:

    # Layer 1: PyMuPDF
    doc = fitz.open(file_path)
    docs = []
    for page_num, page in enumerate(doc):
        text = "\n".join(
            l for l in page.get_text("text").splitlines() if l.strip()
        )
        if text.strip():
            docs.append(Document(
                page_content=text,
                metadata={
                    "source": Path(file_path).name,
                    "page": page_num + 1,
                    "total_pages": len(doc),
                    "ocr": False,
                }
            ))
    doc.close()

    if docs:
        logger.info(f"✓ {Path(file_path).name}: {len(docs)} halaman dibaca via PyMuPDF")
        return docs

    # Layer 2: OCR Tesseract
    logger.warning(f"PyMuPDF gagal mendapatkan teks, menjalankan OCR pada {Path(file_path).name}...")
    return load_pdf_with_ocr(file_path)


# OCR Tesseract
def load_pdf_with_ocr(file_path: str) -> list[Document]:
    docs = []
    try:
        images = convert_from_path(
            file_path,
            dpi=300,
            poppler_path=POPPLER_PATH
        )
    except Exception as e:
        raise RuntimeError(
            f"Gagal mengonversi PDF ke gambar. Pastikan Poppler terinstall "
            f"di path '{POPPLER_PATH}' dengan benar. Detail error: {e}"
        ) from e

    for page_num, image in enumerate(images):
        logger.info(f"  Memproses OCR halaman {page_num+1}/{len(images)}...")
        try:
            text = pytesseract.image_to_string(image, lang="ind+eng")
        except Exception as e:
            raise RuntimeError(
                f"Gagal melakukan OCR menggunakan Tesseract. Pastikan Tesseract "
                f"terpasang di path '{pytesseract.pytesseract.tesseract_cmd}'. Detail: {e}"
            ) from e

        text = "\n".join(l for l in text.splitlines() if l.strip())

        if not text.strip():
            logger.warning(f"  Halaman {page_num+1} kosong setelah OCR")
            continue

        docs.append(Document(
            page_content=text,
            metadata={
                "source": Path(file_path).name,
                "page": page_num + 1,
                "total_pages": len(images),
                "ocr": True,
            }
        ))

    if not docs:
        raise ValueError(
            f"OCR gagal mengekstrak teks apa pun dari {Path(file_path).name}. "
            f"Pastikan dokumen memiliki kualitas tulisan yang baik."
        )

    logger.info(f"✓ OCR selesai: {len(docs)} halaman dari {Path(file_path).name}")
    return docs


# Chunking
def split_documents(documents: list, source: str, doc_type: str) -> list:
    splitter = RecursiveCharacterTextSplitter(
        chunk_size=settings.chunk_size,
        chunk_overlap=settings.chunk_overlap,
        length_function=len,
        separators=["\n\n", "\n", ". ", ", ", " ", ""],
    )

    chunks = splitter.split_documents(documents)
    timestamp = datetime.now().isoformat()

    # Tambahkan metadata ke setiap chunk
    for i, chunk in enumerate(chunks):
        chunk.metadata.update({
            "source": source,
            "doc_type": doc_type,
            "indexed_at": timestamp,
            "chunk_index": i,
        })

    logger.info(
        f"Dokumen '{source}' dipecah menjadi {len(chunks)} chunk "
        f"(ukuran: {settings.chunk_size}, overlap: {settings.chunk_overlap})."
    )
    return chunks

# FAISS Vector Store
def load_vectorstore() -> FAISS | None:
    vs_path = settings.vectorstore_abs_path
    index_file = vs_path / "index.faiss"

    if not index_file.exists():
        logger.info("Vector store belum ada, akan dibuat baru saat indexing.")
        return None

    try:
        embeddings = get_embeddings()
        vectorstore = FAISS.load_local(
            str(vs_path),
            embeddings,
            allow_dangerous_deserialization=True,
        )
        logger.info(f"Vector store berhasil dimuat dari {vs_path}.")
        return vectorstore
    except Exception as e:
        logger.error(f"Gagal memuat vector store: {e}")
        return None


def save_vectorstore(vectorstore: FAISS) -> None:
    vs_path = settings.vectorstore_abs_path
    vs_path.mkdir(parents=True, exist_ok=True)
    vectorstore.save_local(str(vs_path))
    logger.info(f"Vector store disimpan ke {vs_path}.")


def add_chunks_to_vectorstore(chunks: list) -> int:
    if not chunks:
        logger.warning("Tidak ada chunk untuk ditambahkan.")
        return 0

    embeddings = get_embeddings()
    existing_vs = load_vectorstore()

    if existing_vs is not None:
        # Tambahkan ke vector store yang sudah ada
        new_vs = FAISS.from_documents(chunks, embeddings)
        existing_vs.merge_from(new_vs)
        save_vectorstore(existing_vs)
        logger.info(f"Berhasil menambahkan {len(chunks)} chunk ke vector store yang sudah ada.")
    else:
        # Buat vector store baru
        new_vs = FAISS.from_documents(chunks, embeddings)
        save_vectorstore(new_vs)
        logger.info(f"Berhasil membuat vector store baru dengan {len(chunks)} chunk.")

    return len(chunks)


# Hapus Dokumen dari Vector Store
def remove_document_chunks(filename: str) -> bool:
    logger.info(f"Menghapus dokumen '{filename}' dari registry...")
    
    # Hapus dari registry terlebih dahulu
    registry = load_registry()
    original_len = len(registry["documents"])
    registry["documents"] = [
        doc for doc in registry["documents"]
        if doc.get("filename") != filename
    ]
    
    registry_removed = False
    # Simpan registry yang diperbarui
    if len(registry["documents"]) < original_len:
        save_registry(registry)
        logger.info(f"Dokumen '{filename}' berhasil dihapus dari registry.")
        registry_removed = True
    else:
        logger.info(f"Dokumen '{filename}' tidak ditemukan di registry.")

    # Hapus chunk dari vectorstore
    vectorstore = load_vectorstore()
    if vectorstore is None:
        logger.info("Vector store belum ada, tidak ada chunk vector yang dihapus.")
        return registry_removed

    docstore = vectorstore.docstore
    index_to_id = vectorstore.index_to_docstore_id

    # Filter: simpan hanya dokumen yang BUKAN dari file yang akan dihapus
    docs_to_keep = []
    removed_count = 0

    for idx in range(len(index_to_id)):
        doc_id = index_to_id[idx]
        doc = docstore.search(doc_id)
        if hasattr(doc, "metadata") and doc.metadata.get("source") == filename:
            removed_count += 1
        else:
            docs_to_keep.append(doc)

    if removed_count == 0:
        logger.info(f"Tidak ditemukan chunk dari file '{filename}' di vector store.")
        return registry_removed

    # Rebuild vector store tanpa dokumen yang dihapus
    embeddings = get_embeddings()
    if docs_to_keep:
        new_vs = FAISS.from_documents(docs_to_keep, embeddings)
        save_vectorstore(new_vs)
        logger.info(
            f"Berhasil menghapus {removed_count} chunk dari '{filename}'. "
            f"Sisa: {len(docs_to_keep)} chunk."
        )
    else:
        # Semua chunk dihapus, hapus file vector store
        import shutil
        vs_path = settings.vectorstore_abs_path
        if vs_path.exists():
            shutil.rmtree(vs_path)
            vs_path.mkdir(parents=True, exist_ok=True)
        logger.info(
            f"Semua chunk dari '{filename}' dihapus. Vector store dikosongkan."
        )

    return True


# Smart Index
def smart_index(
    file_path: str | Path,
    doc_type: str,
    uploaded_by: str,
) -> dict:
    path = Path(file_path)
    filename = path.name
    current_hash = file_hash(path)

    logger.info(f"Memproses dokumen '{filename}' (tipe: {doc_type})...")

    # Muat registry
    registry = load_registry()
    existing_entry = None

    for doc in registry["documents"]:
        if doc.get("filename") == filename:
            existing_entry = doc
            break

    # Kasus 1: File sudah ada dengan hash SAMA → skip
    if existing_entry and existing_entry.get("hash") == current_hash:
        logger.info(
            f"File '{filename}' sudah diindex dan tidak berubah. Dilewati."
        )
        return {
            "chunks": existing_entry.get("chunks_count", 0),
            "is_update": False,
            "skipped": True,
            "filename": filename,
            "message": f"File '{filename}' sudah diindex dan tidak berubah.",
        }

    # Kasus 2: File sudah ada tapi hash BERBEDA → hapus lalu index ulang
    is_update = False
    if existing_entry and existing_entry.get("hash") != current_hash:
        logger.info(
            f"File '{filename}' terdeteksi berubah. Menghapus index lama..."
        )
        remove_document_chunks(filename)
        is_update = True

    # Kasus 3: File baru atau update → index
    try:
        documents = load_document(path)
        chunks = split_documents(documents, filename, doc_type)
        chunks_count = add_chunks_to_vectorstore(chunks)
        
        registry = load_registry()
        new_entry = {
            "filename": filename,
            "hash": current_hash,
            "doc_type": doc_type,
            "uploaded_by": uploaded_by,
            "indexed_at": datetime.now().isoformat(),
            "chunks_count": chunks_count,
        }

        registry["documents"] = [
            doc for doc in registry["documents"]
            if doc.get("filename") != filename
        ]
        registry["documents"].append(new_entry)
        save_registry(registry)

        status = "diperbarui" if is_update else "berhasil diindex"
        message = f"File '{filename}' {status} ({chunks_count} chunk)."
        logger.info(message)

        return {
            "chunks": chunks_count,
            "is_update": is_update,
            "skipped": False,
            "filename": filename,
            "message": message,
        }

    except Exception as e:
        error_msg = f"Gagal mengindex dokumen '{filename}': {e}"
        logger.error(error_msg)
        return {
            "chunks": 0,
            "is_update": is_update,
            "skipped": False,
            "filename": filename,
            "message": error_msg,
        }