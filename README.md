# DADU 1.0 — Dashboard Akademik Terpadu

[![Laravel](https://img.shields.io/badge/Laravel-11%2F12%2F13-FF2D20?style=flat&logo=laravel&logoColor=white)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D8.4.27-777BB4?style=flat&logo=php&logoColor=white)](https://php.net)
[![FastAPI](https://img.shields.io/badge/FastAPI-0.109+-009688?style=flat&logo=fastapi&logoColor=white)](https://fastapi.tiangolo.com)
[![TailwindCSS](https://img.shields.io/badge/TailwindCSS-3.x-38B2AC?style=flat&logo=tailwind-css&logoColor=white)](https://tailwindcss.com)
[![Vite](https://img.shields.io/badge/Vite-6.x-646CFF?style=flat&logo=vite&logoColor=white)](https://vitejs.dev)
[![Ollama](https://img.shields.io/badge/Ollama-Qwen2.5%3A7B-000000?style=flat&logo=ollama&logoColor=white)](https://ollama.ai)

**DADU 1.0 (Dashboard Akademik Terpadu)** adalah platform sistem informasi akademik kampus terintegrasi yang dirancang untuk mengelola operasional akademik, penjadwalan ujian, pendaftaran & verifikasi yudisium/wisuda, sanggah nilai, manajemen dokumen publik/internal, sistem antrean layanan, sinkronisasi data iGracias/iBasila, serta asisten cerdas berbasis AI RAG (Retrieval-Augmented Generation).

---

## 📑 Daftar Isi
1. [Arsitektur Sistem & Tech Stack](#-arsitektur-sistem--tech-stack)
2. [Fitur & Modul Utama](#-fitur--modul-utama)
3. [Prasyarat Sistem (Prerequisites)](#-prasyarat-sistem-prerequisites)
4. [Panduan Instalasi & Menjalankan Proyek](#-panduan-instalasi--menjalankan-proyek)
   - [Langkah 1: Setup Backend Laravel](#langkah-1-setup-backend-laravel)
   - [Langkah 2: Konfigurasi Environment (.env) & Gotchas](#langkah-2-konfigurasi-environment-env--gotchas)
   - [Langkah 3: Database & Seeding](#langkah-3-database--seeding)
   - [Langkah 4: Setup Frontend (Vite & Tailwind)](#langkah-4-setup-frontend-vite--tailwind)
   - [Langkah 5: Menjalankan Server](#langkah-5-menjalankan-server)
5. [Kredensial Akun Pengujian (Seeded Accounts)](#-kredensial-akun-pengujian-seeded-accounts)
6. [Menjalankan Layanan Chatbot AI (FastAPI + Ollama)](#-menjalankan-layanan-chatbot-ai-fastapi--ollama)
7. [Daftar Branch & Status Repositori](#-daftar-branch--status-repositori)
8. [Standar Penamaan Branch (Branch Naming Convention)](#-standar-penamaan-branch-branch-naming-convention)
9. [Panduan Commit & Workflow Git](#-panduan-commit--workflow-git)
10. [Perintah Artisan & Testing Cheatsheet](#-perintah-artisan--testing-cheatsheet)
11. [Troubleshooting & FAQ](#-troubleshooting--faq)

---

## 🏗 Arsitektur Sistem & Tech Stack

Aplikasi ini menggunakan arsitektur **Hybrid Monolith + Microservice**:

```
dadu/
├── app/                  # Laravel Core (Controllers, Models, Services, Middleware)
├── database/             # Migrations, Seeders, & SQLite DB
├── resources/            # Blade Templates, JS, Tailwind CSS
├── routes/               # Route definitions (web.php, console.php)
└── academic-chatbot/     # Microservice Python FastAPI (RAG + Ollama + FAISS)
```

| Komponen | Teknologi | Keterangan |
| :--- | :--- | :--- |
| **Backend Utama** | PHP ^8.4.27, Laravel 13 | REST Controller, Blade View Engine, Eloquent ORM |
| **Frontend** | Tailwind CSS 3, Vite 6, Alpine.js | Responsive UI panel, dynamic interactive components |
| **Role & Permission**| Spatie `laravel-permission` | Granular CRUD permission & dynamic role checking |
| **Database** | SQLite (Default Dev) / MySQL 8.0+ | Database agnostic via Eloquent Migrations |
| **Microservice AI** | Python 3.11+, FastAPI, Uvicorn | RAG Assistant, FAISS vector index, BM25 hybrid search |
| **LLM & Embeddings** | Ollama (`qwen2.5:7b`), Sentence-Transformers | Local offline LLM & multilingual semantic embedding |
| **Export & Reporting**| Barryvdh DomPDF, Fast-Excel | PDF & Excel generation untuk sidang yudisium & jadwal |

---

## 🚀 Fitur & Modul Utama

- **Autentikasi Hybrid (SSO + Akun Lokal):** Dukungan Single Sign On (SSO) Telkom University via `SatuService` dengan fallback otomatis ke akun database lokal dan proteksi Google reCAPTCHA.
- **Sistem Peran (RBAC):** Pemisahan hak akses berjenjang: `Akademik (Superadmin)`, `admin`, `Staf SSC`, `Direktorat`, `Kaprodi`, `dosen`, dan `mahasiswa`.
- **Modul Yudisium & Kelulusan:** Form pendaftaran wisuda, upload berkas persyaratan predikat, checklist verifikasi 2-panel bagi admin, serta export bahan sidang yudisium ke format Excel.
- **Modul Penjadwalan Ujian (Exam Schedule):** Pengelolaan jadwal ujian semester, alokasi ruang, penugasan pengawas (proctor), dan deteksi bentrok jadwal.
- **Modul Sanggah Nilai (Grade Appeal):** Pengajuan sanggah nilai mahasiswa dalam periode aktif beserta alur tinjauan dosen/akademik.
- **Direktori Dokumen & Portal Publik:** Kategori folder bertingkat (hierarchical), visibilitas dokumen (publik vs internal), download logging, dan soft-delete.
- **Antrian Layanan Akademik:** Sistem tiket antrean mahasiswa pada Student Service Center (SSC).
- **Sinkronisasi Data Eksternal (iGracias & iBasila):** Integrasi dan scraper data mahasiswa, perwalian, KRS, dan nilai ePRT.
- **Academic AI Chatbot:** Asisten tanya-jawab dokumen pedoman dan SOP akademik secara natural berbasis Retrieval-Augmented Generation (RAG).

---

## 💻 Prasyarat Sistem (Prerequisites)

Pastikan lingkungan lokal Anda telah terpasang:
- **PHP** `>= 8.4.27` (Disarankan PHP `8.4.27+`) (Extension yang wajib aktif: `pdo`, `pdo_sqlite` / `pdo_mysql`, `mbstring`, `openssl`, `curl`, `fileinfo`, `gd`, `zip`, `dom`)
- **Composer** `>= 2.x`
- **Node.js** `>= 20.x` / `>= 22.x (LTS)` & **NPM** `>= 10.x`
- **Git**
- *(Opsional untuk Chatbot AI)*: **Python 3.11+** & **Ollama**

---

## 🛠 Panduan Instalasi & Menjalankan Proyek

### Langkah 1: Setup Backend Laravel

1. Buka terminal dan masuk ke direktori proyek:
   ```bash
   cd dadu
   ```

2. Gandakan berkas konfigurasi lingkungan:
   ```bash
   cp .env.example .env
   ```

3. Pasang seluruh dependensi PHP via Composer:
   ```bash
   composer install
   ```

4. Buat kunci enkripsi aplikasi:
   ```bash
   php artisan key:generate
   ```

---

### Langkah 2: Konfigurasi Environment (`.env`) & Gotchas

Buka berkas [`.env`](.env) dan perhatikan pengaturan berikut:

#### 1. Konfigurasi Database (Pilih salah satu)
* **Pilihan A: SQLite (Paling direkomendasikan & tercepat untuk Development Lokal):**
  ```env
  DB_CONNECTION=sqlite
  ```
  Pastikan berkas database SQLite tersedia:
  ```bash
  touch database/database.sqlite
  ```

* **Pilihan B: MySQL / MariaDB (Untuk Staging / Production):**
  ```env
  DB_CONNECTION=mysql
  DB_HOST=127.0.0.1
  DB_PORT=3306
  DB_DATABASE=dadu
  DB_USERNAME=root
  DB_PASSWORD=
  ```

#### 2. ⚠️ PENTING: Pengaturan reCAPTCHA pada Mode Development Lokal
Secara default, proteksi reCAPTCHA pada form login aktif (`AUTH_WITH_CAPTCHA=true`). Jika Anda **tidak** mengisi Google reCAPTCHA Key asli untuk localhost, verifikasi login akan gagal.
Tambahkan baris berikut di `.env` lokal Anda untuk menonaktifkannya:
```env
AUTH_WITH_CAPTCHA=false
```

#### 3. Konfigurasi Integrasi Chatbot API (Opsional)
Jika Anda juga menjalankan microservice `academic-chatbot`:
```env
CHATBOT_API_URL=http://localhost:8001
CHATBOT_API_KEY=ganti-dengan-api-key-yang-aman
```
*(Catatan: Gunakan port `8001` untuk chatbot agar tidak bentrok dengan Laravel yang berjalan di port `8000`).*

---

### Langkah 3: Database & Seeding

Jalankan migrasi tabel dan seeding data master (roles, permission, setting, dan user dummy):
```bash
php artisan migrate --seed
```

Buat symbolic link untuk direktori penyimpanan berkas publik (foto profil & upload dokumen):
```bash
php artisan storage:link
```

---

### Langkah 4: Setup Frontend (Vite & Tailwind)

Pasang dependensi Node.js dan bangun aset frontend:
```bash
npm install
```

---

### Langkah 5: Menjalankan Server

Buka **2 jendela terminal terpisah**:

* **Terminal 1 — Frontend Asset Compiler (Hot Reload):**
  ```bash
  npm run dev
  ```

* **Terminal 2 — Server Aplikasi Laravel:**
  ```bash
  php artisan serve
  ```

Buka peramban Anda di: **`http://127.0.0.1:8000`**

---

## 🔑 Kredensial Akun Pengujian (Seeded Accounts)

Setelah menjalankan `php artisan migrate --seed`, akun berikut siap digunakan untuk pengujian berbagai role:

| Role | Username / Email | Password | Tipe Akun | Hak Akses Utama |
| :--- | :--- | :--- | :--- | :--- |
| **Super Admin** | `superadmin` / `superadmin@example.test` | `password` | Manual | Akses penuh (Akademik & Role Management) |
| **Admin Operasional** | `admin` / `admin@example.test` | `password` | Manual | Verifikasi yudisium, kelola jadwal, dokumen |
| **Staf SSC** | `staf_ssc` / `staf_ssc@example.test` | `password` | Manual | Antrean layanan & operasional SSC |
| **Direktorat** | `direktorat` / `direktorat@example.test`| `password` | Manual | Monitoring & pelaporan eksekutif |
| **Kaprodi** | `kaprodi` / `kaprodi@example.test` | `password` | Manual | Verifikasi akademik program studi |
| **Mahasiswa (SSO)** | `1202200001` | `password` | Mock SSO | Portal yudisium, sanggah nilai, KRS |
| **Dosen (SSO)** | `krisna_dosen` | `password` | Mock SSO | Jadwal mengajar, tinjau sanggah nilai |

---

## 🤖 Menjalankan Layanan Chatbot AI (FastAPI + Ollama)

Jika fitur Chatbot Asisten Akademik diperlukan:

1. Masuk ke direktori chatbot:
   ```bash
   cd academic-chatbot
   ```

2. Buat dan aktifkan Virtual Environment Python:
   ```bash
   python3 -m venv venv
   source venv/bin/activate       # macOS / Linux
   # .\venv\Scripts\Activate.ps1   # Windows PowerShell
   ```

3. Pasang pustaka Python:
   ```bash
   pip install -r requirements.txt
   ```

4. Konfigurasi `.env` pada folder `academic-chatbot`:
   ```bash
   cp .env.example .env
   ```
   *Pastikan nilai `INTERNAL_API_KEY` sama persis dengan `CHATBOT_API_KEY` di `.env` Laravel.*

5. Pastikan Ollama berjalan dan unduh model LLM:
   ```bash
   ollama pull qwen2.5:7b
   ```

6. Jalankan API Chatbot di port `8001`:
   ```bash
   uvicorn api:app --reload --port 8001
   ```

---

## 🌿 Daftar Branch & Status Repositori

Repositori ini memiliki konfigurasi branch berikut di Git:

| Nama Branch | Status | Deskripsi |
| :--- | :--- | :--- |
| **`main`** | **Production** | Branch stabil utama yang siap deploy ke server produksi. |
| **`develop`** | **Staging** | Branch integrasi utama tempat penggabungan fitur-fitur baru. |
| **`formYudisium`** | Fitur | Pengembangan alur pendaftaran, upload syarat, dan validasi form yudisium. |
| **`portal-informasi`**| Fitur | Refactor navbar dan perbaikan UI portal direktori dokumen publik. |
| **`chatbotai`** | Fitur | Integrasi antarmuka dan backend RAG Academic Chatbot. |
| **`dashboard`** | Fitur | Penyelesaian modul dashboard visual dan metrik antrean. |
| **`refactor/login-sso`**| Refactor | Peningkatan arsitektur SSO hybrid dan sistem autentikasi. |

---

## 🏷 Standar Penamaan Branch (Branch Naming Convention)

Untuk menjaga kerapian repositori dan mempermudah pelacakan pekerjaan tim, gunakan format standar:
```
<kategori>/<deskripsi-singkat-kebab-case>
```

### 1. Menambah Fitur Baru (`feature/` atau `feat/`)
Gunakan saat mengembangkan fitur, modul, atau halaman baru:
- `feature/export-excel-yudisium`
- `feature/notifikasi-sanggah-nilai`
- `feat/filter-jadwal-ruangan`

### 2. Memperbaiki Bug / Error (`fix/` atau `bugfix/`)
Gunakan saat mengatasi issue, error tampilan, atau bug logika:
- `fix/sso-session-timeout`
- `fix/upload-dokumen-validation`
- `bugfix/sidebar-minimize-glitch`

### 3. Mengubah / Memperbarui Fitur Tanpa Mengubah Logika Bisnis (`refactor/`)
Gunakan saat merestrukturisasi kode, optimasi query, atau merapikan UI:
- `refactor/direktori-dokumen`
- `refactor/sidebar-config`
- `refactor/academic-period-service`

### 4. Menghapus Fitur / Pembersihan Kode (`remove/` atau `chore/`)
Gunakan saat membuang fitur usang atau membersihkan dependensi lama:
- `remove/legacy-auth-system`
- `chore/remove-unused-observers`
- `chore/cleanup-deprecated-assets`

### 5. Dokumentasi (`docs/`)
Gunakan saat memperbarui panduan, README, atau dokumentasi API:
- `docs/update-installation-guide`
- `docs/api-contracts`

### 6. Perbaikan Kritis di Server Produksi (`hotfix/`)
Dibuat langsung dari `main` jika terjadi kendala kritis di produksi:
- `hotfix/critical-login-500`

---

## 🔀 Panduan Commit & Workflow Git

### Workflow Rekomendasi
1. Selalu tarik perubahan terbaru dari `develop` sebelum membuat branch baru:
   ```bash
   git checkout develop
   git pull origin develop
   ```
2. Buat branch baru sesuai konvensi:
   ```bash
   git checkout -b feature/nama-fitur-anda
   ```
3. Lakukan commit teratur menggunakan format [Conventional Commits](https://www.conventionalcommits.org/):
   - `feat: tambah ekspor rekap nilai mahasiswa ke excel`
   - `fix: perbaiki validasi upload berkas yudisium format pdf`
   - `refactor: optimasi relasi eloquent pada modul jadwal ujian`
   - `docs: perbarui panduan setup database sqlite pada readme`
4. Push ke remote dan ajukan Pull Request (PR) ke branch `develop` (bukan langsung ke `main`).

---

## ⚡ Perintah Artisan & Testing Cheatsheet

```bash
# Menjalankan seluruh Unit & Feature Test (Pest / PHPUnit)
php artisan test

# Menjalankan test tertentu
php artisan test tests/Feature/GraduationRegistrationTest.php

# Merapikan style kode PHP otomatis (Laravel Pint)
./vendor/bin/pint

# Reset database & jalankan ulang seluruh seeder
php artisan migrate:fresh --seed

# Membersihkan seluruh cache sistem (Config, Route, View)
php artisan optimize:clear

# Menampilkan daftar rute aktif beserta middleware
php artisan route:list
```

---

## ❓ Troubleshooting & FAQ

#### Q1: Muncul error `Verifikasi reCAPTCHA gagal` saat mencoba login di localhost.
> **Solusi:** Di environment lokal, pastikan baris `AUTH_WITH_CAPTCHA=false` sudah ditambahkan di berkas `.env` Anda. Setelah itu jalankan `php artisan config:clear`.

#### Q2: Halaman menampilkan `403 This action is unauthorized` setelah login.
> **Solusi:** Pengguna Anda belum memiliki role atau permission yang dibutuhkan. Pastikan Anda telah menjalankan seeder permissions:
> ```bash
> php artisan db:seed --class=RolePermissionSeeder
> ```

#### Q3: Gambar atau dokumen yang diunggah tidak tampil / 404 Not Found.
> **Solusi:** Symbolic link direktori storage belum dibuat. Jalankan perintah:
> ```bash
> php artisan storage:link
> ```

#### Q4: Terjadi bentrok port 8000 antara Laravel dan FastAPI Chatbot.
> **Solusi:** Jalankan salah satu di port berbeda, misalnya Laravel di port `8000` (`php artisan serve`) dan FastAPI di port `8001` (`uvicorn api:app --reload --port 8001`), lalu sesuaikan `CHATBOT_API_URL=http://localhost:8001` di `.env` Laravel.

#### Q5: Database SQLite `Could not find database file`.
> **Solusi:** Buat berkas database secara manual melalui terminal:
> ```bash
> touch database/database.sqlite
> php artisan migrate --seed
> ```

#### Q6: Muncul error Composer platform requirement (`require a PHP version ">= 8.4.27"`) saat deploy di VPS.
> **Solusi:** Versi PHP CLI di VPS masih versi lama (< 8.4.27). Lakukan upgrade paket PHP ke 8.4.27 via PPA `ondrej/php`, set alternatif default CLI via `update-alternatives --set php /usr/bin/php8.4`, dan sesuaikan konfigurasi fastcgi Nginx/Apache ke `php8.4-fpm`. Lihat panduan lengkap upgrade VPS di dokumentasi.

---

## 👨‍💻 Kontribusi & Lisensi

Proyek ini dikembangkan dan dikelola oleh tim pengembang **CELOE (Center of e-Learning and Open Education) Telkom University**. Dilisensikan di bawah lisensi internal institusi.
