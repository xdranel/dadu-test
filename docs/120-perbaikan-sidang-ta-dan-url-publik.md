# Perbaikan Sidang TA dan URL publik — Implementation Plan

> Pelaksanaan: agen utama mengerjakan bertahap dengan skill executing-plans; verifikasi keamanan ditinjau terpisah. Pengguna sudah meminta lanjut dan memprioritaskan perbaikan sebelum fitur Pelaksanaan baru. Tidak melakukan commit/push/deploy.

**Goal:** Merapikan halaman pendaftaran/review/periode dan mengganti URL angka pada alur Sidang TA dengan UUID publik sambil mempertahankan data serta izin akses yang sudah berjalan.

**Architecture:** Tambahkan `public_id` unik pada model TA yang masih memakai ID angka di route; primary key/FK tetap. Backfill melalui migration biasa. Route model memakai UUID, dengan kompatibilitas tautan lama yang tetap melewati otorisasi. Draft sudah mempunyai UUID. Perbaikan tampilan memakai Blade/komponen yang ada.

**Tech Stack:** Laravel 13, MySQL, Blade/Alpine, Pest/PHPUnit.

**Spec:** Pesan pengguna 19 November 2026, prioritas “perbaikan yang dikerjakan terlebih dahulu”; scope masking dikonfirmasi hanya Pendaftaran/Review/Pelaksanaan Sidang TA. Fitur baru berikutnya dicatat terpisah pada121.

## Batas dan keputusan

- Kerjakan di dadu-test, lalu salin perubahan aplikasi/tes ke source dadu; docs tetap hanya di dadu-test. Source docs/docker tidak dibuat kembali.
- Pertahankan environment, akun/password, JSON privat, konfigurasi Docker dan perubahan lokal pengguna. Rescan sebelum/sesudah, backup file sebelum ditimpa.
- Tidak menjalankan migration/fresh/seed pada database aplikasi/VPS; tes hanya dadu_testing.
- UUID tidak menggantikan otorisasi pemilik, reviewer, atau dosen satu prodi. Booking biasa di modul Peminjaman Ruangan tidak dimigrasi URL-nya pada tahap ini.
- Model ber-URL angka yang tercakup: pengajuan, berkas final, berkas draft, periode, dan panduan yang ditautkan dari formulir TA. URL pengaturan Pelaksanaan/booking TA memakai model periode/pengajuan tersebut.
- Tautan angka lama tetap dapat dipakai sesuai izin yang sudah berlaku; GET yang berhasil diarahkan ke URL publik. Tautan baru dan ekspor memakai UUID. POST lama tidak kehilangan akses di tengah rilis; pemeriksaan versi dan izin tetap dijalankan.
- Periode dilipat bila pendaftaran dan verifikasi sama-sama tidak terbuka lagi (dinonaktifkan atau lewat tenggat). Periode dengan verifikasi yang masih aktif tetap mudah terlihat.
- Draft kosong/semua kedaluwarsa tidak menampilkan kartu. Kedaluwarsa mengikuti jenis draft: draft awal dari tenggat pendaftaran, revisi dari tenggat verifikasi. Verifikasi legacy tanpa tanggal tetap mengikuti kompatibilitas yang sudah ada. Saat periode diperpanjang setelah habis, bersihkan draft yang telah kedaluwarsa sebelum menyimpan jadwal baru. Pengajuan final/berkas final dipertahankan; draft revisi baru dibentuk dari state pengajuan terakhir.
- Kartu/tindakan Pelaksanaan lama dipindahkan bersamaan dengan tersedianya menu baru pada tahap fitur; akses jadwal yang sudah ada tidak dihilangkan saat baru merapikan halaman.

## Fokus tinjauan

1. UUID mahasiswa lain dan berkas prodi lain harus tetap ditolak.
2. URL angka lama tidak boleh mengarahkan pengguna tak berwenang ke UUID milik orang lain.
3. Backfill mempertahankan ID, foreign key, status, snapshot, dan path file.
4. Periode dengan verifikasi masih berjalan tetap terbuka; kartu draft tidak muncul ketika kosong/kedaluwarsa.
5. Pembukaan ulang tidak menghidupkan progres draft kedaluwarsa atau menghapus berkas final.

## Langkah

### 1. Tampilan dan draft

- [x] Perbarui Blade index mahasiswa/admin serta partial jadwal: hapus teks yang disebut pengguna; header Review Sidang TA; tombol Export Excel; kartu periode penuh.
- [x] Gunakan details/summary dengan chevron pada periode yang sudah selesai/dinonaktifkan; ringkasan tertutup hanya nama.
- [x] Saring draft kedaluwarsa di daftar; jalankan cleanup terarah sebelum pembukaan/perpanjangan periode lama. Tambahkan pemeriksaan HTTP visibilitas draft dan pembukaan ulang dari state final.

### 2. UUID dan kompatibilitas

- [x] Tulis tes kegagalan untuk URL publik, otorisasi lintas mahasiswa/prodi, tautan lama, dan backfill data lama.
- [x] Migration menambah UUID publik dan mengisi record lama secara bertahap; trait model membuat UUID baru dan menggunakannya sebagai route key.
- [x] Middleware mengarahkan GET angka yang sudah lolos otorisasi ke route UUID; perbaiki titik URL/filter yang masih memakai ID mentah.
- [x] Jalankan tes baru dan regresi pengajuan, draft, review, berkas, periode, ekspor, booking, panduan.

### 3. Verifikasi dan penyelarasan

- [x] Jalankan suite penuh; bandingkan dengan kegagalan yang sudah ada pada118, laporkan nama kegagalan tanpa mengubah aturan produk demi assertion lama.
- [x] Lint PHP, build frontend, pemeriksaan whitespace dan audit tautan.
- [x] Tinjau perubahan keamanan, perbaiki temuan, salin file aplikasi/tes ke source dengan backup/hash dan pertahankan staging pengguna.
- [x] Catat hasil akhir, daftar file, rescan dan langkah migration/update biasa pada dokumen hasil terpisah: [122](122-hasil-perbaikan-sidang-ta-dan-url-publik.md).

Referensi route key/binding Laravel 13 diperiksa melalui Context7: https://laravel.com/docs/13.x/routing#customizing-the-key
