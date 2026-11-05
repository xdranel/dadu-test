# Dokumentasi API — Chatbot Akademik RAG

Dokumentasi ini ditujukan bagi tim Developer, DevOps, atau Vendor untuk memahami struktur API, metode autentikasi, cara instalasi, kebutuhan server, serta pemecahan masalah (troubleshooting) untuk aplikasi Chatbot Akademik berbasis Retrieval-Augmented Generation (RAG).

---

## Deskripsi Sistem

Sistem ini adalah asisten akademik pintar berbasis AI (Chatbot) yang menggunakan metode **RAG (Retrieval-Augmented Generation)**. Sistem bekerja dengan mengekstrak teks dari berbagai dokumen akademik internal kampus (seperti Standar Operasional Prosedur/SOP, Buku Pedoman Akademik, atau Minutes of Meeting/MOM). Dokumen yang diunggah dipotong-potong menjadi bagian kecil (*chunking*), diubah menjadi representasi matematis (*vector embedding*), kemudian disimpan ke dalam database vector lokal berbasis **FAISS**.

Ketika pengguna mengajukan pertanyaan, sistem akan melakukan pencarian gabungan (*hybrid search*) yang mengombinasikan pencarian semantik (kemiripan makna kata) dan pencarian kata kunci (*BM25 keyword search*). Dokumen-dokumen paling relevan yang ditemukan akan disematkan sebagai konteks tambahan bagi model bahasa besar lokal (**Ollama LLM**) untuk menghasilkan jawaban yang akurat, faktual, dan sepenuhnya berlandaskan pada dokumen internal kampus. Hal ini secara signifikan mengurangi potensi halusinasi informasi oleh model AI.

Aplikasi ini dibangun menggunakan framework **FastAPI** yang berjalan secara asynchronous, mendukung validasi file upload (ekstensi dan *magic bytes*), pencatatan log interaksi, pembatasan jumlah pemrosesan LLM secara bersamaan (*concurrency limit*), dan manajemen cache untuk meminimalkan beban komputasi.

---

## Autentikasi

Semua endpoint API (kecuali `/health`) dilindungi menggunakan autentikasi berbasis **API Key**. 
API Key wajib dikirimkan pada setiap HTTP Request melalui header custom berikut:

- **Header Name:** `X-API-Key`
- **Header Value:** `<NILAI_API_KEY_DARI_ENV>`

> [!IMPORTANT]
> Jika API Key tidak valid, server akan merespons dengan HTTP Status Code `403 Forbidden`. Jika header `X-API-Key` tidak disertakan, server akan merespons dengan HTTP Status Code `401 Unauthorized`.

---

## Daftar Endpoint

### 1. POST `/chat`
Menerima pertanyaan dari user, mencari dokumen pendukung dari database FAISS, dan merumuskan jawaban menggunakan model LLM lokal.

* **Method:** `POST`
* **Path:** `/chat`
* **Headers:**
  ```http
  X-API-Key: ganti-dengan-api-key-yang-aman
  Content-Type: application/json
  ```
* **Request Body:**
  ```json
  {
    "question": "Bagaimana prosedur pengajuan cuti akademik bagi mahasiswa?"
  }
  ```
  *Validasi:* `question` tidak boleh kosong atau hanya berisi spasi, dengan panjang maksimal 500 karakter.
* **Response Body (Success - 200 OK):**
  ```json
  {
    "answer": "Berdasarkan pedoman akademik, prosedur pengajuan cuti akademik adalah sebagai berikut: \n1. Mahasiswa mengajukan surat permohonan ke Dekan...\n Sumber: Pedoman_Akademik_2025.pdf",
    "sources": [
      "Pedoman_Akademik_2025.pdf"
    ],
    "interaction_id": "4a1b2c3d-e4f5-6a7b-8c9d-0e1f2a3b4c5d",
    "chunks_used": 5
  }
  ```
* **Contoh cURL:**
  ```bash
  curl -X POST http://localhost:8000/chat \
    -H "X-API-Key: ganti-dengan-api-key-yang-aman" \
    -H "Content-Type: application/json" \
    -d '{"question": "Bagaimana prosedur pengajuan cuti akademik?"}'
  ```

---

### 2. POST `/upload`
Mengunggah file dokumen akademik baru (.pdf, .docx, .ppt, .pptx) ke sistem untuk diproses, dipecah menjadi chunk, dan dimasukkan ke index FAISS. Jika dokumen dengan nama file yang sama sudah ada di sistem namun isinya berbeda, sistem secara otomatis akan menghapus index dokumen lama dan menimpanya dengan yang baru (update).

* **Method:** `POST`
* **Path:** `/upload`
* **Headers:**
  ```http
  X-API-Key: ganti-dengan-api-key-yang-aman
  ```
* **Request Body (Multipart/Form-Data):**
  * `file`: (Berkas biner dokumen, maks 20MB)
  * `doc_type`: `SOP` (String kategori dokumen, contoh: SOP, Pedoman, MOM, dll)
  * `uploaded_by`: `admin@univ.ac.id` (String opsional identitas pengunggah)
* **Response Body (Success - 200 OK):**
  ```json
  {
    "status": "berhasil",
    "filename": "SOP_Pengajuan_Cuti_2026.pdf",
    "doc_type": "SOP",
    "is_update": false,
    "chunks_created": 12
  }
  ```
* **Contoh cURL:**
  ```bash
  curl -X POST http://localhost:8000/upload \
    -H "X-API-Key: ganti-dengan-api-key-yang-aman" \
    -F "file=@/path/to/SOP_Pengajuan_Cuti_2026.pdf" \
    -F "doc_type=SOP" \
    -F "uploaded_by=admin@univ.ac.id"
  ```

> [!NOTE]
> File PDF scan murni akan otomatis diproses menggunakan OCR Tesseract. Proses ini membutuhkan waktu sekitar 5–30 detik per halaman. Oleh karena itu, koneksi HTTP disarankan memiliki timeout yang cukup longgar.

---

### 3. GET `/documents`
Mengembalikan daftar seluruh dokumen yang telah sukses terindeks di dalam sistem beserta metadatanya.

* **Method:** `GET`
* **Path:** `/documents`
* **Headers:**
  ```http
  X-API-Key: ganti-dengan-api-key-yang-aman
  ```
* **Response Body (Success - 200 OK):**
  ```json
  {
    "total": 2,
    "documents": [
      {
        "filename": "SOP_Pengajuan_Cuti_2026.pdf",
        "doc_type": "SOP",
        "indexed_at": "2026-07-08T10:15:30.123456",
        "chunks": 12,
        "uploaded_by": "admin@univ.ac.id"
      },
      {
        "filename": "Pedoman_Akademik_2025.pdf",
        "doc_type": "Pedoman",
        "indexed_at": "2026-07-08T09:22:15.654321",
        "chunks": 48,
        "uploaded_by": "superadmin"
      }
    ]
  }
  ```
* **Contoh cURL:**
  ```bash
  curl -X GET http://localhost:8000/documents \
    -H "X-API-Key: ganti-dengan-api-key-yang-aman"
  ```

---

### 4. DELETE `/documents/{filename}`
Menghapus dokumen tertentu dari sistem. Menghapus potongan vektor di database FAISS, file fisik dari folder penyimpanan, serta data dokumen dari registry.

* **Method:** `DELETE`
* **Path:** `/documents/SOP_Pengajuan_Cuti_2026.pdf`
* **Headers:**
  ```http
  X-API-Key: ganti-dengan-api-key-yang-aman
  ```
* **Response Body (Success - 200 OK):**
  ```json
  {
    "status": "berhasil dihapus",
    "filename": "SOP_Pengajuan_Cuti_2026.pdf"
  }
  ```
* **Contoh cURL:**
  ```bash
  curl -X DELETE http://localhost:8000/documents/SOP_Pengajuan_Cuti_2026.pdf \
    -H "X-API-Key: ganti-dengan-api-key-yang-aman"
  ```

---

### 5. GET `/health`
Memeriksa kesehatan sistem (*health check*) untuk memantau status koneksi ke model Ollama, keberadaan database vectorstore, dan jumlah total dokumen. Endpoint ini **tidak** membutuhkan API Key sehingga dapat dipantau oleh alat monitoring external (misal: Uptime Kuma, Zabbix, AWS Route53 health checks).

* **Method:** `GET`
* **Path:** `/health`
* **Headers:** Tidak ada
* **Response Body (Success - 200 OK - Semua OK):**
  ```json
  {
    "status": "ok",
    "ollama": true,
    "index_ready": true,
    "total_documents": 2
  }
  ```
* **Response Body (Success - 200 OK - Terdegradasi):**
  ```json
  {
    "status": "degraded",
    "ollama": false,
    "index_ready": true,
    "total_documents": 2
  }
  ```
* **Contoh cURL:**
  ```bash
  curl -X GET http://localhost:8000/health
  ```

---

## Cara Deploy di Server Linux

Ikuti langkah-langkah di bawah ini untuk men-deploy aplikasi di lingkungan produksi (Linux Server):

### 1. Salin Proyek ke Server Linux
Salin source code proyek ini ke server target Anda, misalnya ke direktori `/app`:
```bash
git clone <url_repo_chatbot> /app
cd /app
```

### 2. Install Docker & Docker Compose
Pastikan Docker dan Docker Compose telah terinstall di sistem operasi server Anda.
Jika belum, instal menggunakan manajer paket distribusi Linux Anda (misal pada Ubuntu/Debian):
```bash
sudo apt update
sudo apt install -y docker.io docker-compose
sudo systemctl enable --now docker
```

### 3. Isi File `.env`
Salin template `.env.example` menjadi `.env` dan sesuaikan nilainya:
```bash
cp .env.example .env
nano .env
```
Isi konfigurasi penting terutama:
* `INTERNAL_API_KEY`: Generate kunci acak yang aman (misal: `python3 -c "import secrets; print(secrets.token_urlsafe(32))"`).
* `OLLAMA_BASE_URL`: Di dalam konfigurasi docker compose, gunakan alamat container Ollama: `http://ollama:11434`.

### 4. Build dan Jalankan Container Docker
Gunakan Docker Compose untuk membangun (*build*) image api dan menjalankan service di background:
```bash
docker-compose up --build -d
```
Gunakan perintah `docker-compose ps` untuk memastikan kedua container (`chatbot-api` dan `ollama`) telah berstatus `Up`.

### 5. Download Model Ollama
Unduh model LLM yang digunakan oleh asisten akademik ke dalam container Ollama dengan menggunakan script pembantu:
```bash
chmod +x scripts/init_ollama.sh
docker-compose exec -T ollama /bin/bash < scripts/init_ollama.sh
```
*Atau secara langsung lewat terminal:*
```bash
docker exec -it ollama ollama pull qwen2.5:7b
```

### 6. Verifikasi Health Check
Pastikan sistem berjalan normal dengan memanggil endpoint `/health`:
```bash
curl http://localhost:8000/health
```
Respons harus mengembalikan `"status": "ok"` dengan nilai `"ollama": true` dan `"index_ready": true`.

---

## Kebutuhan Server Minimum

| Parameter | Kebutuhan Minimum | Kebutuhan Rekomendasi | Keterangan |
|---|---|---|---|
| **Sistem Operasi** | Linux (Ubuntu Server 22.04 LTS / Debian 12 / Rocky Linux 9) | Linux (Ubuntu Server 22.04 LTS) | Direkomendasikan OS yang stabil dengan dukungan kernel Docker |
| **CPU** | 4 Cores CPU (x86_64 atau ARM64) | 8 Cores atau lebih (dengan AVX2) | Ollama running di CPU butuh instruksi AVX2 untuk pemrosesan cepat |
| **RAM** | 8 GB RAM | 16 GB RAM atau lebih | Model qwen2.5:7b memakan sekitar 4.7 GB RAM. Direkomendasikan RAM sisa untuk OS & OCR |
| **Storage** | 30 GB SSD | 100 GB SSD (NVMe lebih baik) | Digunakan untuk Image Docker, dataset model LLM (~5GB), dan file dokumen |
| **GPU** | Tidak Wajib (Bisa CPU-only) | Nvidia GPU (VRAM >= 8GB) | Jika menggunakan GPU, setup NVIDIA Container Toolkit di Docker untuk akselerasi |

---

## Semua Environment Variable

Pengaturan sistem dikontrol melalui variabel lingkungan yang tercantum pada file `.env`.

| Nama Variable | Deskripsi | Contoh Nilai | Sifat |
|---|---|---|---|
| `OLLAMA_BASE_URL` | URL tempat server Ollama berjalan. | `http://ollama:11434` (Docker) atau `http://localhost:11434` | Wajib |
| `OLLAMA_MODEL` | Model LLM yang di-load dan digunakan oleh Ollama. | `qwen2.5:7b` | Wajib |
| `EMBEDDING_MODEL` | Model HuggingFace untuk menghitung vector embedding. | `paraphrase-multilingual-MiniLM-L12-v2` | Opsional |
| `CHUNK_SIZE` | Jumlah karakter maksimal per potongan teks dokumen. | `800` | Opsional (default `800`) |
| `CHUNK_OVERLAP` | Jumlah overlap karakter antar potongan dokumen. | `150` | Opsional (default `150`) |
| `TOP_K` | Jumlah potongan teks teratas yang diambil saat retrieval. | `5` | Opsional (default `5`) |
| `HYBRID_ALPHA` | Bobot pencarian hybrid (1.0 = full semantic, 0.0 = full keyword). | `0.6` | Opsional (default `0.6`) |
| `INTERNAL_API_KEY` | API Key yang dicocokkan dengan header request `X-API-Key`. | `ganti-dengan-api-key-yang-aman` | **Sangat Wajib** |
| `MAX_UPLOAD_SIZE_MB`| Batas ukuran maksimal file yang boleh diupload (MB). | `20` | Opsional (default `20`) |
| `MAX_CONCURRENT_LLM`| Jumlah request chat ke LLM yang boleh diproses bersamaan. | `5` | Opsional (default `5`) |
| `RATE_LIMIT_PER_MINUTE` | Batas request rate-limiting (opsional jika dipasang reverse proxy). | `10` | Opsional |
| `CORS_ORIGINS` | Daftar asal domain asal (origins) yang diizinkan untuk CORS (comma-separated).| `http://localhost:3000,http://localhost:8000` | Opsional |

---

## Troubleshooting

### 1. Masalah: Kecepatan Respon `/chat` Sangat Lambat (Di atas 30 detik)
* **Penyebab:**
  * Komputasi LLM berjalan sepenuhnya di CPU yang kekurangan core atau tidak mendukung instruksi hardware AVX2.
  * Model yang di-load terlalu besar untuk memori RAM fisik server sehingga terjadi disk swapping.
* **Solusi:**
  * Pastikan RAM server minimal 8GB (direkomendasikan 16GB).
  * Jalankan `htop` di server untuk memonitor utilisasi RAM dan CPU saat bertanya.
  * Jika server memiliki GPU Nvidia, konfigurasi Docker Compose agar menggunakan runtime GPU Nvidia (`deploy.resources.reservations.devices`).

### 2. Masalah: Error Saat Upload Dokumen PDF: "Gagal mengonversi PDF ke gambar..."
* **Penyebab:**
  * Program `poppler-utils` tidak terinstal atau tidak dapat diakses di dalam environment Linux.
* **Solusi:**
  * Pastikan Dockerfile sukses menginstall paket `poppler-utils` menggunakan perintah `apt-get install poppler-utils`.
  * Di server host Linux (jika dijalankan tanpa docker), jalankan: `sudo apt install poppler-utils`.

### 3. Masalah: Error "Gagal melakukan OCR menggunakan Tesseract..."
* **Penyebab:**
  * Engine OCR Tesseract tidak terinstall di server, atau package bahasa Indonesia (`tesseract-ocr-ind`) belum didownload.
* **Solusi:**
  * Pastikan path pemanggilan binary Tesseract pada file `indexer.py` disesuaikan untuk server Linux (biasanya default berada di `/usr/bin/tesseract` atau terdeteksi otomatis jika telah terdaftar di system PATH).
  * Jika berjalan di docker, pastikan baris `tesseract-ocr` dan `tesseract-ocr-ind` tercantum di Dockerfile.

### 4. Masalah: Error `413 Request Entity Too Large` saat upload file
* **Penyebab:**
  * File yang diunggah melebihi batas ukuran `max_upload_size_mb` di file `.env`, atau server reverse proxy (seperti Nginx) di depan container membatasi request body.
* **Solusi:**
  * Sesuaikan parameter `MAX_UPLOAD_SIZE_MB` di file `.env`.
  * Jika menggunakan Nginx sebagai reverse proxy, tambahkan direktif `client_max_body_size 20M;` di dalam blok konfigurasi server Nginx Anda.
