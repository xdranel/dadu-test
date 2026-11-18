# 117 — Menutup sementara Pelaksanaan Sidang TA untuk mahasiswa

> Status historis: keputusan terbaru pada [118](118-rescan-dadu-terbaru-ke-dadu-test.md) membuka kembali Pelaksanaan Sidang TA mahasiswa di dadu dan dadu-test. Rincian di bawah mencatat checkpoint117, bukan default terbaru.

## Permintaan dan keputusan

Pengguna meminta kartu Pelaksanaan Sidang TA disembunyikan dari mahasiswa yang sudah approved, karena fitur pencarian/booking belum siap dan masih akan diperbaiki.

Pengaturan bawaan pada `config/sidang_ta.php`: `student_booking_enabled = false`. Dengan pengaturan ini, mahasiswa tidak melihat kartu, tautan pencarian atau hasil booking pada halaman Pendaftaran Sidang TA. Panel Draft tersimpan memakai lebar penuh.

Akses langsung mahasiswa ke halaman booking, pencarian, serta konfirmasi ditutup404 sebelum validasi payload. Token pratinjau yang diperoleh sebelum penutupan juga tidak dapat dikonfirmasi melalui endpoint mahasiswa. Pendaftaran/revisi Sidang TA dan riwayat pengajuan tetap berfungsi seperti biasa.

Booking yang sudah tersimpan, pemakaian ruangan, approval TA, dokumen, dan riwayat tidak dihapus atau dibatalkan. Admin/petugas tetap mempunyai akses Kelola Pelaksanaan dan pengelolaan booking sesuai permission yang sudah ada. Fitur Peminjaman Ruangan biasa tetap menggunakan alurnya sendiri.

## Implementasi

- `config/sidang_ta.php`: pengaturan bawaan menutup fitur mahasiswa. Pengaktifan kembali dilakukan melalui perubahan konfigurasi dalam rilis setelah fitur siap.
- `app/Http/Controllers/SidangTaBookingController.php`: middleware controller memeriksa pengaturan untuk show/search/confirm sebelum FormRequest diproses. Tidak mengubah route bersama atau controller admin.
- `app/Http/Controllers/SidangTaRegistrationController.php`: query konteks booking pada index mahasiswa hanya dijalankan bila fitur diaktifkan.
- `resources/views/sidang_ta_registrations/index.blade.php`: kartu dan kolom booking hanya dirender bila fitur aktif dan ada konteks booking. Grid draft menjadi satu kolom saat fitur ditutup.
- `tests/Feature/SidangTaStudentBookingVisibilityTest.php`: pengujian kondisi default tertutup, bypassURL/payload/token lama, keutuhan booking yang sudah ada, serta akses admin.
- `tests/Feature/SidangTaBookingUiTest.php` dan `tests/Feature/SidangTaRoomBookingTest.php`: mengaktifkan konfigurasi secara eksplisit di fixture tes fitur yang masih dikembangkan. Konfigurasi aplikasi tetapfalse.

Middleware menggunakan mekanisme Laravel13 `HasMiddleware` dengan inline closure; [dokumentasi resmi](https://laravel.com/docs/13.x/controllers#controller-middleware) diperiksa melalui Context7. Pemeriksaan ini diterapkan pada controller mahasiswa agar endpoint admin dan service lifecycle tetap dapat dipakai untuk pengembangan.

## Rescan dan penyalinan

Sebelum perubahan, file controller mahasiswa, controller pendaftaran, view index, dan kedua tes lama cocok antara `dadu` dan `dadu-test`. Hash awal disimpan di `private-import/117-before-hashes.json`. Backup source sebelum penyalinan terdapat di `private-import/117-before/` pada repo Docker.

Tujuh file kode/tes sudah disamakan pada kedua folder. Markdown dan laporan117 hanya dibuat di repo `dadu-test`, mengikuti arahan pengguna. Tidak membuat ulang `dadu/docs` atau `dadu/docker`. Perubahan ekspor116 dan perubahan pengguna yang sudah ada tetap dipertahankan.

Jika perlu menyalin ulang ketujuh file dari source development ke repo Docker:

```bash
cd /home/xdranel/Code/Project/secret/dadu-test
for dadu_path in \
  config/sidang_ta.php \
  app/Http/Controllers/SidangTaBookingController.php \
  app/Http/Controllers/SidangTaRegistrationController.php \
  resources/views/sidang_ta_registrations/index.blade.php \
  tests/Feature/SidangTaStudentBookingVisibilityTest.php \
  tests/Feature/SidangTaBookingUiTest.php \
  tests/Feature/SidangTaRoomBookingTest.php
do
  cp -- "/home/xdranel/Code/Project/php-laravel/dadu/$dadu_path" "$dadu_path"
done
git diff --check
```

## Verifikasi

Tes baru sebelum implementasi:7 gagal karena kartu/endpoint masih terbuka,1 tes akses admin sudah lulus. Semua pengujian database dijalankan hanya pada `dadu_testing` lokal, bukan database aplikasi/VPS.

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 \
DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 \
DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= \
php artisan test tests/Feature/SidangTaStudentBookingVisibilityTest.php \
  tests/Feature/SidangTaBookingUiTest.php tests/Feature/SidangTaRoomBookingTest.php \
  tests/Feature/SidangTaReviewListTest.php tests/Feature/SidangTaStaffNavigationTest.php --compact
```

Hasil akhir: **54 tes lulus,323 assertions** pada lima suite di atas. Delapan skenario penutupan fitur lulus, termasuk404 untuk payload tidak lengkap dan token pratinjau lama, serta keutuhan booking/riwayat. Tes UI/pencarian fitur yang masih dikembangkan tetap berjalan melalui pengaktifan konfigurasi secara eksplisit di fixture tes.

Lint PHP dan `git diff --check` lulus. Ketujuh file kedua folder identik. Hash service dan kedua tes ekspor116 juga masih sama seperti laporan116; perubahan ekspor dipertahankan. Hash sebelum/sesudah ada pada [117-rescan-penutupan-booking-mahasiswa.json](117-rescan-penutupan-booking-mahasiswa.json).

## Deployment

Perubahan belum di-commit/push atau di-deploy ke VPS oleh sesi ini. Setelah commit/push, lakukan update image/container seperti alur yang dipakai sekarang pada panduan113. Penutupan berlaku setelah image app memuat kode baru. Tidak membutuhkan migration, fresh, seed, reset permission, atau pembatalan booking.
