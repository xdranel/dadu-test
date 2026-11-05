import sys
import json
import shutil
from pathlib import Path

if sys.version_info < (3, 11):
    print("Dibutuhkan Python 3.11 atau lebih baru!")
    print(f"   Versi saat ini: {sys.version}")
    sys.exit(1)


# Base directory = folder academic-chatbot/
BASE_DIR = Path(__file__).resolve().parent

# Warna terminal (ANSI escape codes)
HIJAU = "\033[92m"
MERAH = "\033[91m"
KUNING = "\033[93m"
BIRU = "\033[94m"
RESET = "\033[0m"
BOLD = "\033[1m"


def cetak_header():
    print()
    print(f"{BIRU}{BOLD}{'=' * 55}")
    print("  Inisialisasi Chatbot Akademik RAG")
    print(f"{'=' * 55}{RESET}")
    print()


def cetak_status(nama: str, berhasil: bool, pesan: str = ""):
    ikon = f"{HIJAU}✅{RESET}" if berhasil else f"{MERAH}❌{RESET}"
    teks = f"  {ikon} {nama}"
    if pesan:
        teks += f" — {pesan}"
    print(teks)


def buat_folder() -> bool:
    print(f"\n{BOLD}Membuat struktur folder...{RESET}")

    folder_list = [
        BASE_DIR / "docs",
        BASE_DIR / "vectorstore",
        BASE_DIR / "data",
    ]

    semua_berhasil = True
    for folder in folder_list:
        try:
            folder.mkdir(parents=True, exist_ok=True)
            gitkeep = folder / ".gitkeep"
            if not gitkeep.exists():
                gitkeep.touch()
            cetak_status(f"Folder {folder.relative_to(BASE_DIR)}/", True, "dibuat")
        except Exception as e:
            cetak_status(f"Folder {folder.relative_to(BASE_DIR)}/", False, str(e))
            semua_berhasil = False

    # Buat file index_registry.json kosong jika belum ada
    registry_file = BASE_DIR / "data" / "index_registry.json"
    if not registry_file.exists():
        try:
            registry_file.write_text(
                json.dumps({"documents": []}, indent=2, ensure_ascii=False),
                encoding="utf-8",
            )
            cetak_status("File index_registry.json", True, "dibuat")
        except Exception as e:
            cetak_status("File index_registry.json", False, str(e))
            semua_berhasil = False

    return semua_berhasil


def cek_env_file() -> bool:
    print(f"\n{BOLD} Memeriksa konfigurasi .env...{RESET}")

    env_file = BASE_DIR / ".env"
    env_example = BASE_DIR / ".env.example"

    if not env_file.exists():
        if env_example.exists():
            shutil.copy2(env_example, env_file)
            cetak_status(
                "File .env",
                True,
                f"disalin dari .env.example — {KUNING}silakan edit nilai INTERNAL_API_KEY!{RESET}",
            )
            return True
        else:
            cetak_status("File .env", False, "tidak ditemukan dan .env.example juga tidak ada")
            return False

    env_content = env_file.read_text(encoding="utf-8")
    if "ganti-dengan-api-key-yang-aman" in env_content:
        cetak_status(
            "File .env",
            True,
            f"{KUNING}ditemukan, tapi INTERNAL_API_KEY masih default — segera ganti!{RESET}",
        )
        return True

    cetak_status("File .env", True, "sudah dikonfigurasi")
    return True


def download_embedding_model() -> bool:
    print(f"\n{BOLD}Mendownload embedding model...{RESET}")

    try:
        from sentence_transformers import SentenceTransformer
    except ImportError:
        cetak_status(
            "sentence-transformers",
            False,
            "belum terinstall — jalankan: pip install -r requirements.txt",
        )
        return False

    model_name = "paraphrase-multilingual-MiniLM-L12-v2"
    try:
        from config import get_settings
        settings = get_settings()
        model_name = settings.embedding_model
    except Exception:
        pass

    try:
        print(f"  Mengunduh model '{model_name}'... (mungkin butuh beberapa menit)")
        model = SentenceTransformer(f"sentence-transformers/{model_name}")

        test_text = "Ini adalah tes embedding untuk Bahasa Indonesia"
        embedding = model.encode([test_text])
        dimensi = embedding.shape[1]

        cetak_status(
            "Embedding model",
            True,
            f"'{model_name}' siap (dimensi: {dimensi})",
        )
        return True

    except Exception as e:
        cetak_status("Embedding model", False, f"gagal download — {e}")
        return False


def verifikasi_ollama() -> bool:
    print(f"\n{BOLD}Memverifikasi koneksi Ollama...{RESET}")

    ollama_url = "http://localhost:11434"
    ollama_model = "qwen2.5:7b"
    try:
        from config import get_settings
        settings = get_settings()
        ollama_url = settings.ollama_base_url
        ollama_model = settings.ollama_model
    except Exception:
        pass

    try:
        import httpx
        response = httpx.get(f"{ollama_url}/api/tags", timeout=10.0)
        response.raise_for_status()
    except ImportError:
        cetak_status(
            "Koneksi Ollama",
            False,
            "httpx belum terinstall — jalankan: pip install -r requirements.txt",
        )
        return False
    except Exception:
        cetak_status(
            "Koneksi Ollama",
            False,
            f"tidak bisa terhubung ke {ollama_url} — pastikan Ollama sudah berjalan",
        )
        return False

    cetak_status("Server Ollama", True, f"terhubung di {ollama_url}")

    try:
        data = response.json()
        model_tersedia = [m.get("name", "") for m in data.get("models", [])]
        model_ditemukan = any(
            ollama_model in nama or ollama_model.split(":")[0] in nama
            for nama in model_tersedia
        )

        if model_ditemukan:
            cetak_status("Model LLM", True, f"'{ollama_model}' sudah tersedia")
        else:
            cetak_status(
                "Model LLM",
                False,
                f"'{ollama_model}' belum di-pull — jalankan: ollama pull {ollama_model}",
            )
            if model_tersedia:
                print(f"     Model yang tersedia: {', '.join(model_tersedia)}")
            return False

    except Exception as e:
        cetak_status("Model LLM", False, f"gagal cek model — {e}")
        return False

    return True


def verifikasi_faiss() -> bool:
    print(f"\n{BOLD}Memverifikasi FAISS...{RESET}")

    try:
        import faiss

        dimensi = 384
        index = faiss.IndexFlatL2(dimensi)
        cetak_status(
            "FAISS",
            True,
            f"vector database siap (dimensi tes: {dimensi})",
        )
        return True

    except ImportError:
        cetak_status(
            "FAISS",
            False,
            "belum terinstall — jalankan: pip install faiss-cpu",
        )
        return False
    except Exception as e:
        cetak_status("FAISS", False, f"error — {e}")
        return False


def cetak_ringkasan(hasil: dict[str, bool]):
    print(f"\n{BOLD}{'=' * 55}")
    print("  Ringkasan Status Inisialisasi")
    print(f"{'=' * 55}{RESET}\n")

    total = len(hasil)
    berhasil = sum(1 for v in hasil.values() if v)

    for nama, status in hasil.items():
        cetak_status(nama, status)

    print(f"\n  Hasil: {berhasil}/{total} komponen siap")

    if berhasil == total:
        print(f"\n  {HIJAU}{BOLD}Semua komponen siap! Project bisa dijalankan.{RESET}")
        print(f"  Jalankan server: {BIRU}uvicorn main:app --reload --port 8000{RESET}")
    else:
        print(f"\n  {KUNING}{BOLD}Beberapa komponen belum siap. Perbaiki error di atas.{RESET}")

    print()


def main():
    cetak_header()

    hasil: dict[str, bool] = {}

    # 1. Buat struktur folder
    hasil["Struktur folder"] = buat_folder()

    # 2. Cek file .env
    hasil["Konfigurasi .env"] = cek_env_file()

    # 3. Download embedding model
    hasil["Embedding model"] = download_embedding_model()

    # 4. Verifikasi FAISS
    hasil["FAISS vector DB"] = verifikasi_faiss()

    # 5. Verifikasi Ollama
    hasil["Koneksi Ollama"] = verifikasi_ollama()

    # Tampilkan ringkasan
    cetak_ringkasan(hasil)


if __name__ == "__main__":
    main()
