# 114 — Perapian kartu periode pada Review Sidang TA

## Permintaan dan rescan sebelum perubahan

Kartu “Pendaftaran dan Verifikasi Berkas” dan kartu “Periode aktif” mempunyai tombol ke Kelola Periode yang sama. Pengguna meminta menghapus kartu yang berulang, menggantinya dengan kartu Periode aktif di sebelah Pelaksanaan Sidang TA, dan menumpuk daftar periode ke bawah.

Sebelum perubahan, view di `dadu` dan `dadu-test` identik (SHA-256 `5828867c887a3f653303510b4acca6834f0af0ee5aac5445a695d4ae94f2cc4f`). Basis repo Docker saat perubahan: `490f6b3`. File yang disentuh hanya `resources/views/sidang_ta_registrations/admin/index.blade.php`.

## Hasil perubahan

- Kartu Periode aktif berada di kiri; kartu Pelaksanaan Sidang TA berada di kanan pada layar sedang/besar. Di layar kecil keduanya turun ke bawah.
- Kartu Pendaftaran dan Verifikasi Berkas dihapus. Tombol Kelola Periode tersisa satu pada kartu Periode aktif.
- Jika ada beberapa periode, daftar periode selalu tersusun satu kolom ke bawah dalam kartu Periode aktif.
- Filter periode tetap berlaku; judul menjadi Periode terpilih ketika filter dipakai, sesuai perilaku sebelumnya.
- Pengguna yang tidak berhak membaca pengaturan periode tetap dapat melihat jadwal sesuai hak tinjaunya, tanpa tombol pengelolaan; kartu tersebut menggunakan lebar penuh.
- Perubahan hanya layout Blade; aturan tanggal, revisi, persetujuan, booking, dan database tidak diubah. Tidak membutuhkan migration, seed, maupun reset.

## Salinan lokal dan command copy

Satu view sudah disamakan pada folder development asli dan repo Docker. Salinan sebelum perubahan tersedia di `private-import/114-card-layout-before.blade.php` pada repo Docker (di-ignore Git).

Jika perlu menyalin ulang dari development asli ke repo Docker:

```bash
cp /home/xdranel/Code/Project/php-laravel/dadu/resources/views/sidang_ta_registrations/admin/index.blade.php \
  /home/xdranel/Code/Project/secret/dadu-test/resources/views/sidang_ta_registrations/admin/index.blade.php

sha256sum \
  /home/xdranel/Code/Project/php-laravel/dadu/resources/views/sidang_ta_registrations/admin/index.blade.php \
  /home/xdranel/Code/Project/secret/dadu-test/resources/views/sidang_ta_registrations/admin/index.blade.php
```

Untuk VPS, perubahan belum di-commit/push atau di-deploy oleh sesi ini. Setelah commit/push, update image/deployment seperti panduan113; jangan melakukan fresh/seed untuk perubahan layout ini.

## Verifikasi

Verifikasi akhir: **41 tes lulus, 325 assertion**, pada empat suite berikut: `SidangTaReviewListTest`, `SidangTaMeetingUiTest`, `SidangTaBookingUiTest`, dan `SidangTaStaffNavigationTest`. `git diff --check` bersih. Repo Docker tidak mempunyai vendor lokal, sehingga tes dijalankan dari development asli menggunakan database `dadu_testing` pada MySQL lokal port3307. Percobaan awal terhenti sebelum assertion karena container MySQL testing belum menyala; tidak menggunakan database aplikasi maupun VPS.


Command pengujian dari development asli:

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 \
DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 \
DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= \
php artisan test tests/Feature/SidangTaReviewListTest.php \
  tests/Feature/SidangTaMeetingUiTest.php tests/Feature/SidangTaBookingUiTest.php \
  tests/Feature/SidangTaStaffNavigationTest.php --compact
```

Rescan setelah perubahan memastikan hanya satu view aplikasi yang berubah pada repo Docker; skrip copy fitur112 yang sebelumnya untracked tidak disentuh. Kedua salinan view (development asli dan repo Docker) identik dengan SHA-256 `02f1cc9b0871b33a9b4ccd764a09160a056d9c4fbab8fe0e3774492c4368b6cc`.

Manifest112 tetap merekam snapshot implementasi fitur sebelumnya. Untuk perubahan114 gunakan command satu view di atas; jangan menyalin ulang snapshot lama di atas perbaikan ini.
