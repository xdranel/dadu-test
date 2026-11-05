import asyncio
import json
import logging
import os
import re
import uuid
from datetime import datetime
from pathlib import Path

import httpx
from fastapi import (
    FastAPI,
    File,
    Form,
    HTTPException,
    Request,
    Security,
    UploadFile,
)
from fastapi.middleware.cors import CORSMiddleware
from fastapi.security import APIKeyHeader
from pydantic import BaseModel, Field

from config import get_settings, BASE_DIR
from indexer import load_registry, smart_index, remove_document_chunks
from retriever import ask, clear_cache

# Logger
logger = logging.getLogger(__name__)
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(name)s: %(message)s",
)

settings = get_settings()

ALLOWED_EXTENSIONS: dict[str, str] = {
    ".pdf": "application/pdf",
    ".docx": "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
    ".pptx": "application/vnd.openxmlformats-officedocument.presentationml.presentation",
    ".ppt": "application/vnd.ms-powerpoint",
}

# validasi isi file
MAGIC_BYTES: dict[str, bytes] = {
    ".pdf": b"%PDF",
    ".docx": b"PK\x03\x04",
    ".pptx": b"PK\x03\x04",
    ".ppt": b"\xd0\xcf\x11\xe0",
}

INTERACTIONS_LOG_PATH = BASE_DIR / "data" / "interactions.jsonl"

# Concurrency Limiter
_llm_semaphore = asyncio.Semaphore(settings.max_concurrent_llm)

# Model request untuk endpoint /chat
class ChatRequest(BaseModel):
    question: str = Field(
        ...,
        min_length=1,
        max_length=500,
        description="Pertanyaan yang ingin diajukan (maks 500 karakter).",
    )
    history: list[dict] = Field(
        default=[],
        description="Riwayat percakapan sebelumnya (opsional, maks 10 item).",
    )

# Model response untuk endpoint /chat
class ChatResponse(BaseModel):
    answer: str
    sources: list[str]
    interaction_id: str
    chunks_used: int

# Model response untuk endpoint /upload
class UploadResponse(BaseModel):
    status: str
    filename: str
    doc_type: str
    is_update: bool
    chunks_created: int

# Model info dokumen individual
class DocumentInfo(BaseModel):
    filename: str
    doc_type: str
    indexed_at: str
    chunks: int
    uploaded_by: str

# Model response untuk endpoint GET /documents
class DocumentsListResponse(BaseModel):
    total: int
    documents: list[DocumentInfo]

# Model response untuk endpoint DELETE /documents/{filename}
class DeleteResponse(BaseModel):
    status: str
    filename: str

# Model response untuk endpoint /health
class HealthResponse(BaseModel):
    status: str
    ollama: bool
    index_ready: bool
    total_documents: int

# Utilitas
def sanitize_filename(filename: str) -> str:
    filename = Path(filename).name

    name, ext = os.path.splitext(filename)
    name = re.sub(r"[^\w\s\-]", "", name, flags=re.UNICODE)
    name = re.sub(r"\s+", "_", name.strip())
    if not name:
        name = f"dokumen_{uuid.uuid4().hex[:8]}"

    return f"{name}{ext.lower()}"

def validate_file_extension(filename: str) -> str | None:
    ext = Path(filename).suffix.lower()
    if ext in ALLOWED_EXTENSIONS:
        return ext
    return None

def validate_magic_bytes(header: bytes, extension: str) -> bool:
    expected = MAGIC_BYTES.get(extension)
    if expected is None:
        return False
    return header[: len(expected)] == expected


def log_interaction(
    interaction_id: str,
    question: str,
    answer: str,
    sources: list[str],
    chunks_used: int,
) -> None:
    entry = {
        "id": interaction_id,
        "timestamp": datetime.now().isoformat(),
        "question": question,
        "answer": answer,
        "sources": sources,
        "chunks_used": chunks_used,
    }

    try:
        INTERACTIONS_LOG_PATH.parent.mkdir(parents=True, exist_ok=True)
        with open(INTERACTIONS_LOG_PATH, "a", encoding="utf-8") as f:
            f.write(json.dumps(entry, ensure_ascii=False) + "\n")
        logger.info(f"Interaksi '{interaction_id}' berhasil dicatat.")
    except Exception as e:
        logger.error(f"Gagal mencatat interaksi: {e}")

# Keamanan: API Key Authentication
api_key_header = APIKeyHeader(name="X-API-Key", auto_error=False)

async def verify_api_key(
    api_key: str | None = Security(api_key_header),
) -> str:

    if api_key is None:
        raise HTTPException(
            status_code=401,
            detail="Header X-API-Key tidak ditemukan. Autentikasi diperlukan.",
        )
    if api_key != settings.internal_api_key:
        raise HTTPException(
            status_code=403,
            detail="API key tidak valid. Akses ditolak.",
        )
    return api_key

# Inisialisasi FastAPI
app = FastAPI(
    title="Chatbot Akademik RAG API",
    description=(
        "API untuk chatbot akademik berbasis Retrieval-Augmented Generation. "
        "Mendukung upload dokumen, pencarian hybrid, dan tanya-jawab "
        "menggunakan LLM lokal (Ollama)."
    ),
    version="1.0.0",
)

# CORS Middleware
cors_origins_env = os.getenv("CORS_ORIGINS", "http://localhost:3000,http://localhost:8000")
cors_origins = [origin.strip() for origin in cors_origins_env.split(",") if origin.strip()]

app.add_middleware(
    CORSMiddleware,
    allow_origins=cors_origins,
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

logger.info(f"CORS dikonfigurasi untuk origins: {cors_origins}")

# Startup Event
@app.on_event("startup")
async def startup_event():
    logger.info("=" * 60)
    logger.info("Memulai Chatbot Akademik RAG API...")
    logger.info("=" * 60)

    try:
        registry = load_registry()
        total_docs = len(registry.get("documents", []))
        logger.info(f"Jumlah dokumen di registry: {total_docs}")
    except Exception as e:
        logger.error(f"Gagal membaca registry: {e}")

    # Cek status vectorstore
    vs_index = settings.vectorstore_abs_path / "index.faiss"
    if vs_index.exists():
        logger.info(f"Vector store ditemukan di: {settings.vectorstore_abs_path}")
    else:
        logger.warning("Vector store belum ada. Silakan upload dan index dokumen.")

    # Cek koneksi Ollama
    try:
        async with httpx.AsyncClient(timeout=5.0) as client:
            resp = await client.get(f"{settings.ollama_base_url}/api/tags")
            if resp.status_code == 200:
                logger.info(f"Ollama terhubung di {settings.ollama_base_url}")
            else:
                logger.warning(
                    f"Ollama merespons dengan status {resp.status_code}"
                )
    except Exception as e:
        logger.warning(f"Tidak dapat terhubung ke Ollama: {e}")

    settings.docs_abs_path.mkdir(parents=True, exist_ok=True)
    (BASE_DIR / "data").mkdir(parents=True, exist_ok=True)

    logger.info("=" * 60)
    logger.info("API siap menerima request.")
    logger.info("=" * 60)


# Endpoint 1: POST /chat
@app.post(
    "/chat",
    response_model=ChatResponse,
    summary="Tanya jawab dengan chatbot",
    description="Mengirimkan pertanyaan dan mendapatkan jawaban dari chatbot akademik.",
)
async def chat_endpoint(
    request: ChatRequest,
    _api_key: str = Security(verify_api_key),
) -> ChatResponse:
    question = request.question.strip()
    if not question:
        raise HTTPException(
            status_code=422,
            detail="Pertanyaan tidak boleh kosong atau hanya berisi spasi.",
        )

    # Validasi dan sanitasi riwayat percakapan
    validated_history: list[dict] = []
    for item in request.history[:10]:  # Maksimal 10 item
        if (
            isinstance(item, dict)
            and isinstance(item.get("role"), str)
            and isinstance(item.get("content"), str)
            and item["role"] in ("user", "assistant")
        ):
            validated_history.append({
                "role": item["role"],
                "content": item["content"],
            })

    logger.info(
        f"Pertanyaan diterima: '{question[:80]}...' "
        f"— riwayat: {len(validated_history)} pesan"
    )

    acquired = _llm_semaphore._value > 0
    if not acquired and _llm_semaphore._value == 0:
        logger.warning("Semua slot LLM sedang terpakai, menunggu giliran...")

    try:
        async with _llm_semaphore:
            result = await asyncio.wait_for(
                asyncio.to_thread(ask, question, validated_history),
                timeout=60.0,
            )
    except asyncio.TimeoutError:
        logger.error(f"Timeout saat memproses pertanyaan: '{question[:50]}...'")
        raise HTTPException(
            status_code=504,
            detail=(
                "Waktu pemrosesan melebihi batas 60 detik. "
                "Model mungkin sedang sibuk, silakan coba lagi."
            ),
        )
    except Exception as e:
        logger.error(f"Gagal memproses pertanyaan: {e}")
        raise HTTPException(
            status_code=500,
            detail=f"Terjadi kesalahan saat memproses pertanyaan: {e}",
        )

    # Generate interaction ID
    interaction_id = str(uuid.uuid4())

    # Catat interaksi ke file log
    log_interaction(
        interaction_id=interaction_id,
        question=question,
        answer=result.get("answer", ""),
        sources=result.get("sources", []),
        chunks_used=result.get("chunks_used", 0),
    )

    logger.info(
        f"Pertanyaan dijawab (ID: {interaction_id}, "
        f"chunks: {result.get('chunks_used', 0)}, "
        f"cache: {result.get('from_cache', False)})"
    )

    return ChatResponse(
        answer=result.get("answer", ""),
        sources=result.get("sources", []),
        interaction_id=interaction_id,
        chunks_used=result.get("chunks_used", 0),
    )


# Endpoint 2: POST /upload

@app.post(
    "/upload",
    response_model=UploadResponse,
    summary="Upload dokumen akademik",
    description=(
        "Mengupload dokumen (PDF, DOCX, PPTX) untuk diindex ke dalam "
        "vector store. PDF scan akan diproses dengan OCR."
    ),
)
async def upload_endpoint(
    file: UploadFile = File(..., description="File dokumen yang akan diupload"),
    doc_type: str = Form(..., description="Tipe dokumen (SOP, Pedoman, MOM, dll)"),
    uploaded_by: str = Form("anonim", description="Email/ID pegawai yang mengupload"),
    _api_key: str = Security(verify_api_key),
) -> UploadResponse:
    original_filename = file.filename or "tanpa_nama"
    logger.info(
        f"Upload diterima: '{original_filename}' "
        f"(tipe: {doc_type}, oleh: {uploaded_by})"
    )

    # Validasi ekstensi file
    extension = validate_file_extension(original_filename)
    if extension is None:
        ekstensi_diizinkan = ", ".join(ALLOWED_EXTENSIONS.keys())
        raise HTTPException(
            status_code=400,
            detail=(
                f"Tipe file tidak didukung. "
                f"Format yang diizinkan: {ekstensi_diizinkan}"
            ),
        )

    # Baca konten file
    try:
        content = await file.read()
    except Exception as e:
        logger.error(f"Gagal membaca file upload: {e}")
        raise HTTPException(
            status_code=400,
            detail=f"Gagal membaca file yang diupload: {e}",
        )
    finally:
        await file.close()

    # Validasi ukuran file
    file_size = len(content)
    if file_size > settings.max_upload_size_bytes:
        ukuran_mb = file_size / (1024 * 1024)
        raise HTTPException(
            status_code=413,
            detail=(
                f"Ukuran file ({ukuran_mb:.1f} MB) melebihi batas maksimal "
                f"{settings.max_upload_size_mb} MB."
            ),
        )

    if file_size == 0:
        raise HTTPException(
            status_code=400,
            detail="File yang diupload kosong (0 bytes).",
        )

    # Validasi magic bytes
    if not validate_magic_bytes(content, extension):
        raise HTTPException(
            status_code=400,
            detail=(
                f"Isi file tidak sesuai dengan format {extension}. "
                f"File mungkin rusak atau bukan file {extension} yang valid."
            ),
        )

    # Sanitasi nama file
    safe_filename = sanitize_filename(original_filename)
    save_path = settings.docs_abs_path / safe_filename

    # Simpan file ke disk
    try:
        settings.docs_abs_path.mkdir(parents=True, exist_ok=True)
        save_path.write_bytes(content)
        logger.info(f"File disimpan ke: {save_path}")
    except Exception as e:
        logger.error(f"Gagal menyimpan file ke disk: {e}")
        raise HTTPException(
            status_code=500,
            detail=f"Gagal menyimpan file ke server: {e}",
        )

    try:
        logger.info(f"Memulai indexing '{safe_filename}' (bisa memakan waktu lama untuk PDF scan)...")
        result = await asyncio.to_thread(
            smart_index,
            str(save_path),
            doc_type,
            uploaded_by,
        )
    except Exception as e:
        logger.error(f"Indexing gagal, menghapus file '{safe_filename}': {e}")
        try:
            if save_path.exists():
                save_path.unlink()
                logger.info(f"Rollback: file '{safe_filename}' berhasil dihapus.")
        except Exception as cleanup_err:
            logger.error(f"Gagal menghapus file saat rollback: {cleanup_err}")

        raise HTTPException(
            status_code=500,
            detail=f"Gagal mengindex dokumen: {e}",
        )

    # Cek apakah indexing berhasil (bukan error internal dari smart_index)
    chunks_created = result.get("chunks", 0)
    is_skipped = result.get("skipped", False)

    if chunks_created == 0 and not is_skipped:
        logger.warning(f"Indexing menghasilkan 0 chunk, melakukan rollback...")
        try:
            if save_path.exists():
                save_path.unlink()
                logger.info(f"Rollback: file '{safe_filename}' dihapus (0 chunk).")
        except Exception as cleanup_err:
            logger.error(f"Gagal menghapus file saat rollback: {cleanup_err}")

        raise HTTPException(
            status_code=500,
            detail=result.get("message", "Gagal mengindex dokumen, tidak ada chunk yang dihasilkan."),
        )

    # Bersihkan cache retriever karena ada dokumen baru
    if not is_skipped:
        try:
            await asyncio.to_thread(clear_cache)
            logger.info("Cache retriever dibersihkan setelah upload.")
        except Exception as e:
            logger.warning(f"Gagal membersihkan cache: {e}")

    logger.info(
        f"Upload selesai: '{safe_filename}' "
        f"({chunks_created} chunk, update: {result.get('is_update', False)})"
    )

    return UploadResponse(
        status="berhasil" if not is_skipped else "dilewati (file tidak berubah)",
        filename=safe_filename,
        doc_type=doc_type,
        is_update=result.get("is_update", False),
        chunks_created=chunks_created,
    )


# Endpoint 3: GET /documents

@app.get(
    "/documents",
    response_model=DocumentsListResponse,
    summary="Daftar semua dokumen",
    description="Menampilkan daftar semua dokumen yang sudah diindex.",
)
async def list_documents_endpoint(
    _api_key: str = Security(verify_api_key),
) -> DocumentsListResponse:
    """
    Endpoint untuk mendapatkan daftar semua dokumen di registry.
    """
    try:
        registry = await asyncio.to_thread(load_registry)
    except Exception as e:
        logger.error(f"Gagal membaca registry: {e}")
        raise HTTPException(
            status_code=500,
            detail=f"Gagal membaca registry dokumen: {e}",
        )

    raw_docs = registry.get("documents", [])
    documents = [
        DocumentInfo(
            filename=doc.get("filename", "tidak diketahui"),
            doc_type=doc.get("doc_type", "tidak diketahui"),
            indexed_at=doc.get("indexed_at", "tidak diketahui"),
            chunks=doc.get("chunks_count", 0),
            uploaded_by=doc.get("uploaded_by", "tidak diketahui"),
        )
        for doc in raw_docs
    ]

    logger.info(f"Daftar dokumen diminta: {len(documents)} dokumen ditemukan.")

    return DocumentsListResponse(
        total=len(documents),
        documents=documents,
    )


# Endpoint 4: DELETE /documents/{filename}

@app.delete(
    "/documents/{filename}",
    response_model=DeleteResponse,
    summary="Hapus dokumen",
    description="Menghapus dokumen dari index, registry, dan file fisik.",
)
async def delete_document_endpoint(
    filename: str,
    _api_key: str = Security(verify_api_key),
) -> DeleteResponse:

    # Sanitasi filename untuk mencegah path traversal
    safe_filename = Path(filename).name
    if not safe_filename or safe_filename in (".", ".."):
        raise HTTPException(
            status_code=400,
            detail="Nama file tidak valid.",
        )

    logger.info(f"Permintaan hapus dokumen: '{safe_filename}'")

    try:
        registry = await asyncio.to_thread(load_registry)
    except Exception as e:
        logger.error(f"Gagal membaca registry: {e}")
        raise HTTPException(
            status_code=500,
            detail=f"Gagal membaca registry: {e}",
        )

    doc_exists = any(
        doc.get("filename") == safe_filename
        for doc in registry.get("documents", [])
    )

    if not doc_exists:
        raise HTTPException(
            status_code=404,
            detail=f"Dokumen '{safe_filename}' tidak ditemukan di registry.",
        )

    # Hapus vector dari FAISS dan dari registry
    try:
        removed = await asyncio.to_thread(remove_document_chunks, safe_filename)
        if removed:
            logger.info(f"Chunk vector '{safe_filename}' berhasil dihapus dari FAISS.")
        else:
            logger.warning(f"Tidak ada chunk yang dihapus untuk '{safe_filename}'.")
    except Exception as e:
        logger.error(f"Gagal menghapus chunk dari vectorstore: {e}")
        raise HTTPException(
            status_code=500,
            detail=f"Gagal menghapus dokumen dari vectorstore: {e}",
        )

    # Hapus file fisik dari docs/
    file_path = settings.docs_abs_path / safe_filename
    if file_path.exists():
        try:
            file_path.unlink()
            logger.info(f"File fisik '{safe_filename}' berhasil dihapus.")
        except Exception as e:
            logger.warning(
                f"Gagal menghapus file fisik '{safe_filename}': {e}. "
                f"File mungkin masih ada di disk."
            )
    else:
        logger.info(f"File fisik '{safe_filename}' tidak ditemukan di disk (mungkin sudah dihapus).")

    # Bersihkan cache retriever
    try:
        await asyncio.to_thread(clear_cache)
        logger.info("Cache retriever dibersihkan setelah penghapusan dokumen.")
    except Exception as e:
        logger.warning(f"Gagal membersihkan cache: {e}")

    logger.info(f"Dokumen '{safe_filename}' berhasil dihapus sepenuhnya.")

    return DeleteResponse(
        status="berhasil dihapus",
        filename=safe_filename,
    )

# Endpoint 5: GET /health

@app.get(
    "/health",
    response_model=HealthResponse,
    summary="Health check",
    description="Memeriksa status kesehatan sistem: Ollama, vectorstore, dan dokumen.",
)
async def health_check_endpoint() -> HealthResponse:
    # Cek koneksi Ollama
    ollama_ok = False
    try:
        async with httpx.AsyncClient(timeout=5.0) as client:
            resp = await client.get(f"{settings.ollama_base_url}/api/tags")
            ollama_ok = resp.status_code == 200
    except Exception:
        ollama_ok = False

    # Cek status vectorstore
    index_file = settings.vectorstore_abs_path / "index.faiss"
    index_ready = index_file.exists()

    # Hitung jumlah dokumen
    total_documents = 0
    try:
        registry = load_registry()
        total_documents = len(registry.get("documents", []))
    except Exception:
        pass

    # Tentukan status keseluruhan
    if ollama_ok and index_ready:
        status = "ok"
    else:
        status = "degraded"

    logger.info(
        f"Health check — Status: {status}, "
        f"Ollama: {'✅' if ollama_ok else '❌'}, "
        f"Index: {'✅' if index_ready else '❌'}, "
        f"Dokumen: {total_documents}"
    )

    return HealthResponse(
        status=status,
        ollama=ollama_ok,
        index_ready=index_ready,
        total_documents=total_documents,
    )