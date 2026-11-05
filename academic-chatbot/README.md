# Chatbot Akademik RAG API

Sistem Chatbot Akademik berbasis **RAG (Retrieval-Augmented Generation)** yang dirancang untuk menjawab pertanyaan akademik seputar dokumen internal kampus (SOP, Pedoman Akademik, Minutes of Meeting) menggunakan model LLM lokal secara aman, cepat, dan akurat.

---

## Tech Stack
* **Backend Framework:** FastAPI (Python 3.11)
* **LLM Engine:** Ollama (default model: `qwen2.5:7b`)
* **Embedding Model:** Sentence-Transformers (`paraphrase-multilingual-MiniLM-L12-v2`)
* **Vector Database:** FAISS (Facebook AI Similarity Search)
* **Pencarian Hybrid:** BM25 (Keyword Search) + FAISS (Semantic Search) dengan RRF (Reciprocal Rank Fusion)
* **Ekstraksi PDF & OCR:** PyMuPDF, pdf2image, Tesseract OCR
* **Containerization:** Docker & Docker Compose

---

## Quick Start: Development (Windows)

Berikut adalah 5 langkah cepat untuk memulai development chatbot di Windows:

1. **Buat Virtual Environment & Aktifkan:**
   ```powershell
   python -m venv venv
   .\venv\Scripts\Activate.ps1
   ```

2. **Install Dependensi:**
   ```powershell
   pip install -r requirements.txt
   ```

3. **Setup Konfigurasi `.env`:**
   Salin file `.env.example` menjadi `.env` dan isi nilai `INTERNAL_API_KEY`:
   ```powershell
   copy .env.example .env
   ```

4. **Pastikan Ollama & Tesseract Siap:**
   Unduh model di Ollama local:
   ```powershell
   ollama pull qwen2.5:7b
   ```
   *Catatan: Pastikan path Tesseract OCR dan Poppler telah disesuaikan pada file [indexer.py](file:///c:/laragon/www/dadu/academic-chatbot/indexer.py).*

5. **Jalankan Server API:**
   ```powershell
   uvicorn api:app --reload
   ```

---

## Quick Start: Production (Linux via Docker)

Berikut adalah 5 langkah cepat untuk melakukan deploy di Linux server menggunakan Docker:

1. **Salin/Clone Proyek & Pindah Direktori:**
   ```bash
   git clone <repo_url> chatbot && cd chatbot
   ```

2. **Setup Environment File (`.env`):**
   ```bash
   cp .env.example .env
   # Edit file .env dan sesuaikan nilai INTERNAL_API_KEY
   nano .env
   ```

3. **Jalankan Layanan via Docker Compose:**
   ```bash
   docker compose up --build -d
   ```

4. **Inisialisasi Model Ollama:**
   ```bash
   docker exec -it ollama ollama pull qwen2.5:7b
   ```

5. **Verifikasi Layanan:**
   ```bash
   curl http://localhost:8000/health
   ```

---

## Dokumentasi API & Panduan Lengkap

Penjelasan detail mengenai skema request-response endpoint, konfigurasi environment variable lengkap, spesifikasi server minimum, pencadangan database, serta troubleshooting silakan rujuk ke berkas dokumentasi utama berikut:

**[DOKUMENTASI API & DEPLOYMENT](file:///c:/laragon/www/dadu/academic-chatbot/API_DOCUMENTATION.md)**
