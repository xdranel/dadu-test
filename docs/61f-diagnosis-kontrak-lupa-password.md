# 61f — Diagnosis perbedaan kontrak lupa password pada full suite

## Hasil rescan

`reset password link can be requested` hanya terdapat di `tests/Feature/Auth/PasswordResetTest.php`. Test itu tidak dipanggil oleh command yang hanya memilih `EmailVerificationTest.php` dan `AuthenticationTest.php`. Jadi hasil ini kemungkinan berasal dari full suite berikutnya; cocokkan dengan header output terminal sebelum menyimpulkan dua test sebelumnya gagal.

UserFactory di folder asli sudah mempunyai status=active, auth_source=manual, must_change_password=false sesuai61e. Koreksi AuthenticationTest61d juga sudah diterapkan.

## Penyebab kegagalan notification

Test lama memakai alur tautan email: `POST /forgot-password` lalu `Notification::assertSentTo(..., ResetPassword::class)`. Route POST tersebut tidak terdaftar; pada path itu hanya tersedia GET halaman awal. Request POST ke path itu seharusnya ditolak method405, sehingga tidak ada kode pengirim email yang berjalan. Baris89 pada assertion PHPUnit/Notification fake adalah tempat kegagalan dilaporkan, bukan penyebab bisnisnya.

Route saat ini di routes/web.php:

| Method/path | Perilaku |
| --- | --- |
| GET /forgot-password | Halaman input username |
| POST /forgot-password/verify | Menyimpan username dalam session, tanpa bukti pemilik akun |
| GET /forgot-password/reset | Menampilkan form password baru jika session username ada |
| POST /forgot-password/reset | Mengganti password untuk akun manual aktif selain Akademik |

`PasswordResetLinkController::store()` yang mengirim tautan token masih ada, tetapi route menuju method itu dikomentari di routes/auth.php. Route reset token GET/POST `/reset-password` masih tersedia melalui NewPasswordController.

## Temuan yang harus diperbaiki sebelum menyesuaikan kontrak test

ForgotPasswordController::submitStep1 tidak memverifikasi pemilik username; submitStep2 hanya memerlukan session username dan password baru. Pemeriksaan manual/active/bukanAkademik membatasi jenis akun, tetapi tidak membuktikan bahwa pemohon pemilik akun. Ini memungkinkan penggantian password akun manual aktif milik orang lain yang username-nya diketahui. Throttle dan audit log tidak menggantikan pembuktian kepemilikan.

Jangan membuat test lama menjadi hijau dengan menghapus assertion notification atau menulis test sukses mengikuti reset berbasis username saja. Memperbaiki ini memerlukan keputusan tentang perilaku produk Auth, di luar perubahan persyaratan Sidang TA.

Rekomendasi: gunakan alur token email dari komponen Laravel yang sudah tersedia, lalu tutup seluruh jalur guest yang masih bisa mengubah password tanpa token. Penyesuaian harus meliputi route, UI, pengiriman token, reset/validasi token, pembatasan akun manual aktif sesuai kebijakan yang disetujui, invalidasi sesi lama, dan test penolakan tanpa token/token salah/kedaluwarsa/pakai ulang. Menambahkan route email saja belum menutup route reset username yang lama.

Alternatif lingkup: catat Auth sebagai pekerjaan terpisah, pastikan suite Sidang TA serta Node diketahui hasilnya, lalu lanjutkan audit TA. Jika memilih lingkup ini, full suite tetap dinyatakan gagal, bukan di-skip atau dianggap lulus. Perlu persetujuan arah pengerjaan sebelum kode perubahan alur Auth disusun.

## Langkah saat ini

1. Pastikan output dua file EmailVerificationTest dan AuthenticationTest memang lulus atau kirim kegagalan yang benar dari dua file itu.
2. Konfirmasi arah: memperbaiki Auth dengan reset email bertoken, atau menunda Auth sebagai pekerjaan terpisah dan kembali ke TA.
3. Belum ada file aplikasi/test yang perlu diganti berdasarkan61f; ini diagnosis source, bukan patch untuk membuat assertion hijau.
4. Jangan lanjut reset database aplikasi/salin akhir sambil menganggap full suite telah lulus. Keputusan lingkup dan hasil test harus dicatat secara eksplisit.

## Pemeriksaan dokumen

Source di kedua clone di-hash sebelum dan sesudah penulisan dokumen ini dan tidak berubah. Asisten tidak menjalankan endpoint reset, tidak menghubungi gateway/email, dan tidak menjalankan test database. Diagnosis didasarkan pada route/controller/test aktual; pesan assertion lengkap tetap diperlukan jika hasil aktual berbeda dari jalur yang dijelaskan.
