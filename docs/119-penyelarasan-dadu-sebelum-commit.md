# 119 — Rescan dan penyelarasan dadu ke dadu-test sebelum commit

## Permintaan dan rencana

Pengguna meminta rescan source `dadu/` lalu menyalin perubahan terbaru ke `dadu-test/` untuk ditinjau sebelum commit/push. Source saat scan: `9bf3a00`; target: `a3e5133`. Tidak melakukan commit, push, deployment, migration, atau perubahan database aplikasi.

Rencana: periksa hash/status Git kedua folder; bedakan pembaruan aplikasi dari konfigurasi khusus Docker; backup dan salin file aplikasi yang tertinggal; jalankan pemeriksaan syntax, tes peminjaman ruangan/booking TA, build frontend; rescan dan catat hasil.

Scan awal menemukan 778 file tracked source yang identik dan 17 yang berbeda. Dari 17 perbedaan, 7 merupakan pembaruan aplikasi/tes yang perlu disalin; 5 merupakan konfigurasi root yang dipertahankan pada target, dan 5 lainnya merupakan pengecualian yang sudah dijelaskan pada [118](118-rescan-dadu-terbaru-ke-dadu-test.md).

## Cakupan

- Menyalin controller dan service peminjaman ruangan, empat Blade peminjaman ruangan, serta tes baru `RoomBookingFeatureCompleteTest.php`.
- Mempertahankan `.env.example`, `.gitignore`, `README.md`, `deploy.sh`, dan `phpunit.xml` khusus target.
- Mempertahankan default permission TA khusus Docker dan perapian whitespace pada empat file dari checkpoint118.
- Pelaksanaan Sidang TA mahasiswa tetap terbuka pada kedua folder.
- Tidak mengubah source, environment/password, JSON privat, storage, override Docker, maupun staging Git yang sudah dibuat pengguna. Source `docs/` dan `docker/` tetap tidak dibuat kembali.

## Hasil

Tujuh file sudah disalin dari source dan hash hasilnya cocok:

1. `app/Http/Controllers/RoomBookingController.php`
2. `app/Services/RoomBookingTransitionService.php`
3. `resources/views/room-bookings/create.blade.php`
4. `resources/views/room-bookings/dashboard.blade.php`
5. `resources/views/room-bookings/index.blade.php`
6. `resources/views/room-bookings/review.blade.php`
7. `tests/Feature/RoomBookingFeatureCompleteTest.php`

Perubahan upstream mencakup validasi tanggal peminjaman terhadap hari ini/periode akademik, pemeriksaan status ruang saat approval, redirect dashboard petugas, pencocokan data dosen, filter/pagination, serta dukungan akun manual Pelaksana pada local/testing. Batas local/testing dari source tetap dipertahankan. Tidak ada migration baru pada tujuh file pembaruan ini.

Rescan akhir: **795 file source diperiksa, 785 identik, 10 perbedaan sengaja dipertahankan**. Semua pembaruan aplikasi yang perlu disalin sudah tersalin. Daftar file beserta hash dan pengecualian tersedia di [119-rescan-hasil.json](119-rescan-hasil.json).

Sebanyak **981 file tracked target di luar daftar salinan tetap memiliki hash yang sama**. Hash index Git sebelum/sesudah sama; tidak menjalankan `git add` atau mengubah staging pengguna. Source tidak ditulis, dan tiga perubahan lokal source yang sudah ada (`.gitignore`, `package-lock.json`, `phpunit.xml`) tetap dipertahankan.

Backup enam file lama target: `private-import/119-backup/`. File tes ketujuh sebelumnya belum ada. Manifest privat: `private-import/119-copy-manifest.json`; inventory awal: `private-import/119-inventory-before.json`.

## Verifikasi

| Pemeriksaan | Hasil |
| --- | --- |
| Syntax PHP controller, service dan tes baru | Ketiganya lulus. |
| `git diff --check` dan `git diff --cached --check` | Bersih. |
| `npm run build` | Berhasil. |
| Tes peminjaman ruangan dan booking TA | 76 tes, 301 assertion, 0 failure/error, 0 skipped; exit 0. Ada 76 warning environment, dijelaskan di bawah. |
| Rescan hash salinan, file lain dan staging | Cocok; konfigurasi booking mahasiswa tetap aktif di kedua folder. |

Tes menggunakan database khusus **`dadu_testing`** dan key khusus testing dari penyimpanan privat checkpoint118. Delapan berkas tes yang dijalankan: `RoomBookingFeatureCompleteTest`, `RoomBookingSharedAvailabilityTest`, `SidangTaRoomBookingTest`, `SidangTaBookingUiTest`, `SidangTaBookingLifecycleTest`, `SidangTaBookingConcurrencyTest`, `SidangTaLecturerBookingLinkTest`, dan `SidangTaStudentBookingVisibilityTest`.

Peringatan sudah diperiksa dengan satu tes ulang memakai `--display-warnings`: Dotenv mencoba membaca `.env` lokal dadu-test yang memang tidak tersedia, pada `vendor/vlucas/phpdotenv/src/Store/File/Reader.php:73`. Environment pengujian diberikan melalui proses; assertion tes selesai tanpa kegagalan. Tidak menyalin `.env` source atau membuat file environment untuk ikut commit. Log pengujian, probe warning, JUnit dan build tersimpan di `private-import/119-*`.

Suite penuh tidak diulang untuk penyalinan terbatas ini. Laporan kegagalan suite penuh dari checkpoint118 tetap menjadi catatan terpisah; hasil 76 tes ini tidak menyatakan seluruh suite sudah hijau.

## Sebelum commit/push

File baru/berubah dari sesi ini belum di-stage. Tinjau tujuh file di atas dan kedua dokumen119, lalu stage bersama perubahan Docker/bootstrap yang sebelumnya sudah pengguna siapkan. Tidak ada perubahan akun, password, data sinkronisasi, atau VPS dalam sesi ini.
