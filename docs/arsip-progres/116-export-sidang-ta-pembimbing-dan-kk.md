# 116 — Kolom pembimbing dan KK pada ekspor Review Sidang TA

> Arsip progres: keputusan dan perintah di sini mencerminkan tahap saat ditulis. Untuk pemakaian terkini buka [panduan](../panduan-pemakaian/README.md); hasil singkat tersedia di [kesimpulan](../kesimpulan/README.md).

> Status historis: source hasil pull `45bbe25` pada [118](118-rescan-dadu-terbaru-ke-dadu-test.md) mengganti nilai pembimbing menjadi kode dosen dan KK memakai field `lecturers.kk` tersendiri. Catatan berikut menjelaskan implementasi checkpoint116 sebelum perubahan upstream itu.

## Permintaan dan klarifikasi

Ekspor Excel pada Form → Review Sidang TA ditambah tiga kolom, tepat sesudah kedua judul TA:

`Judul TA Indonesia → Judul TA Inggris → Pembimbing 1 → Pembimbing 2 → KK Pembimbing 1 → TAK → ...`

Pengguna mengonfirmasi bahwa KK memakai **Bidang Keilmuan** pada Data Akademik → Dosen, yaitu kolom database `lecturers.academic_interest`. Tidak menambahkan field KK atau migration baru.

## Data yang digunakan

- Pembimbing diambil dari snapshot pengajuan terlebih dahulu, mengikuti prinsip tampilan tinjau. Nilai yang belum tersedia (`null`, kosong, `-`, atau “Belum tersedia”) boleh dilengkapi dari `student_final_projects.advisor_1`/`advisor_2` berdasarkan NIM.
- Pencocokan master dosen memprioritaskan kode dosen. Jika tidak ada kode yang cocok, gunakan nama dosen yang cocok secara unik. Nama pada Excel memakai nama lengkap dari Data Dosen, termasuk gelar yang tersedia.
- KK berasal hanya dari Bidang Keilmuan dosen yang cocok dengan **Pembimbing 1**. Tidak mengambil KK pembimbing kedua atau menebak jika nama dosen berulang.
- Bila master dosen belum cocok, nilai pembimbing sumber tetap ditampilkan untuk pelacakan; KK ditulis “Belum tersedia”. Jika pembimbing memang kosong, kolomnya juga “Belum tersedia”.
- Pengambilan data TA terbaru dan master dosen dilakukan secara batch untuk seluruh hasil ekspor, sehingga tidak menambah query per mahasiswa. NIM/kode dosen dibedakan sebagai string, termasuk nol di depan dan kode `0`. Ekspor tetap mengikuti filter yang ada.
- Ekspor hanya membaca data; snapshot, judul, profil TA, dokumen, status dan riwayat pengajuan tidak ditulis ulang.

Susunan kolom selain tiga tambahan tersebut mengikuti format ekspor yang sudah digunakan. Kolom File yang tersedia tetap menjadi tautan PDF berizin; nilai nama/KK yang menyerupai formula tetap ditulis sebagai teks XLSX.

## Rescan dan perubahan file

Sebelum perubahan, service ekspor kedua folder identik: SHA-256 `40fd0fd80b5d1e29ee374f6a63476d5c946cb68984c80cf29638817b58f4fb5b`. Tes lama juga identik: `aeba24359caee3f3b1cc7aa441aa15382b9e448969b33095da20fdc3ae48a534`.

Tiga file aplikasi/tes disamakan di `dadu` dan `dadu-test`:

1. `app/Services/SidangTaReviewExport.php`: urutan tiga kolom, resolusi pembimbing dan KK, pemuatan batch.
2. `tests/Feature/SidangTaAdvisorExportTest.php`: tes XLSX baru untuk urutan, kode/nama, prioritas snapshot, impor belakangan, data kosong/ambigu, formula teks, PDF dan jumlah query.
3. `tests/Feature/SidangTaReviewExportTest.php`: menyelaraskan assertion yang masih mengacu format lama (57 kolom, Snapshot Nama/Prodi, label Ketersediaan) dengan format yang sedang dipakai (43 kolom sebelum perubahan,46 sesudah penambahan). Sebelum perubahan,6 tes ini memang sudah gagal dan8 lulus. Tidak mengembalikan kolom yang sebelumnya sudah dihapus dari format aplikasi.

Dokumentasi116 hanya dibuat di repo `dadu-test`, sesuai arahan pengguna; tidak membuat ulang `dadu/docs` atau `dadu/docker`. Folder JSON simulasi pada source tidak disentuh.

Backup sebelum penyalinan ke source: `private-import/116-SidangTaReviewExport-before.php` dan `private-import/116-SidangTaReviewExportTest-before.php` di repo Docker. Perubahan pengguna pada109, catatan.md dan file115 tetap dipertahankan.

## Command copy bila diperlukan

Ketiga file sudah disamakan. Jika nanti perlu menyalin ulang dari development asli:

```bash
cd /home/xdranel/Code/Project/secret/dadu-test
cp /home/xdranel/Code/Project/php-laravel/dadu/app/Services/SidangTaReviewExport.php app/Services/
cp /home/xdranel/Code/Project/php-laravel/dadu/tests/Feature/SidangTaAdvisorExportTest.php tests/Feature/
cp /home/xdranel/Code/Project/php-laravel/dadu/tests/Feature/SidangTaReviewExportTest.php tests/Feature/
git diff --check
```

## Verifikasi

Tes dijalankan dari development asli yang sudah mempunyai vendor, hanya memakai database `dadu_testing` lokal port3307. Tidak menjalankan fresh/seed, impor, atau tes pada database aplikasi/VPS. Sepuluh skenario awal terlebih dahulu gagal karena tiga kolom belum tersedia; satu regresi identifier string ditambahkan setelah pengecekan batch. Seluruh11 skenario akhirnya lulus.

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 \
DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 \
DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= \
php artisan test tests/Feature/SidangTaAdvisorExportTest.php \
  tests/Feature/SidangTaReviewExportTest.php tests/Feature/SidangTaProgramReadAccessTest.php --compact
```

Hasil akhir:

- **25 tes ekspor lulus**:11 pada `SidangTaAdvisorExportTest` dan14 pada `SidangTaReviewExportTest`. Termasuk pembacaan XLSX asli, urutan kolom, fallback pembimbing, nama ambigu, kode/NIM dengan nol di depan, teks mirip formula, tautan PDF, dan batch query maksimal2 untuk20 pengajuan.
- Regresi tambahan bersama `SidangTaProgramReadAccessTest`: **48 lulus,1 gagal,312 assertions**. Satu kegagalan adalah `fresh seed gives Pelaksana scoped viewing without TA review or period management` pada source lokal: seeder source lama belum memuat default permission prodi seperti repo Docker. Baseline ini sudah direproduksi sebelum fitur pada [112 bagian6](112-hasil-implementasi-jadwal-sidang-ta-dan-peminjaman-ruangan.md). Seeder/role tidak diubah dalam tugas ekspor ini; ini bukan klaim seluruh suite source lulus.
- Tiga file PHP lulus lint, `git diff --check` bersih, dan hash ketiga salinan aplikasi/tes kedua folder identik. Verifikasi susunan kolom memastikan format43 kolom lama dipertahankan seluruhnya, lalu hanya ditambah3 kolom yang diminta.
- Hasil rescan/hash: [116-rescan-export-pembimbing.json](../catatan/bukti-verifikasi-historis.md#116-rescan-export-pembimbing-json).

 Perubahan belum di-commit/push atau di-deploy ke VPS oleh sesi ini. Setelah commit/push, gunakan alur update Docker yang dipakai sekarang; tidak membutuhkan migration atau reset akun/database.
