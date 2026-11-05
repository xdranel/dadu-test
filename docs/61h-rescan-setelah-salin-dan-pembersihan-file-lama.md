# 61h — Rescan setelah48 file disalin

Pengguna melaporkan manual test lokal TA lulus dan48 file sudah disalin. Rescan langsung membandingkan byte tiap file dari manifest61: seluruh48 file ada dan identik, tidak ada yang hilang atau berbeda.

Perbandingan seluruh source app/resources/routes/tests/database/config menyisakan tepat tujuh path tambahan di dadu-test: lima test alur v1 dan dua view lama. Ketujuhnya sudah tidak ada di folder asli. Jangan mempertahankan kelima test itu saat menjalankan wildcard SidangTa karena asumsi v1-nya memang sudah dipensiunkan.

## 1. Hapus tujuh file lama hanya di dadu-test

```bash
cd /home/xdranel/Code/Project/secret/dadu-test
rm -- tests/Feature/SidangTaFinalizationTest.php tests/Feature/SidangTaRequirementsVersionTest.php tests/Feature/SidangTaRevisionDraftTest.php tests/Feature/SidangTaDocumentReviewTest.php tests/Feature/SidangTaReviewFeedbackTest.php resources/views/sidang_ta_registrations/form.blade.php resources/views/sidang_ta_registrations/partials/upload.blade.php
```

Tidak perlu menjalankan generator, menulis ulang48 file, atau mengulang reset data aplikasi. Asisten tidak menjalankan penghapusan source karena aturan pengguna membatasi penulisan asisten ke docs.

## 2. Uji hasil salin setelah pembersihan

Dari terminal dadu-test, jalankan perintah berikut saja. Semua koneksi database dinyatakan eksplisit ke schema testing Podman, sehingga tidak perlu menyalin phpunit.xml atau .env dari folder asli. Simpan output agar bisa dibaca langsung oleh asisten:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTa*Test.php tests/Unit/SidangTaAdmissionPolicyTest.php tests/Unit/SidangTaMeetRequirementsTest.php 2>&1 | tee /tmp/dadu-sidang-ta-61-after-copy.log
```

Perbandingan source belum menggantikan hasil test di clone tujuan. Hasil sebelum salin yang sudah terbukti adalah347 passed/3987 assertions dan Node13 passed; manual test lulus berdasarkan laporan pengguna. Setelah run ini selesai, beri tahu asisten agar log hasil salin dibaca. Jangan menyebut full suite proyek lulus: PasswordResetTest masih mempunyai temuan Auth61f.

## 3. Sisa pekerjaan

- Jika test pasca-salin lulus, rescan terakhir memastikan dua clone benar-benar sama dan tidak ada file lama tertinggal.
- JSON resmi SKPI, approval iGracias, riwayat tes EPRT, TAK masih menunggu konfirmasi; data dummy tidak menjadi bukti kontrak resmi.
- Persoalan lupa password61f tetap terbuka, termasuk reset tanpa bukti pemilik akun; belum ada perubahan perilaku Auth atau keputusan implementasinya.
- Tidak ada commit/push/deploy otomatis, tidak ada reset ulang database aplikasi pada61h.

Hash source kedua folder diverifikasi sebelum/sesudah setiap penulisan Markdown pada tahap ini. Tidak ada source aplikasi/test yang diubah asisten dan tidak ada test database yang dijalankan asisten.


## Pembaruan setelah pembersihan/test pengguna

Tujuh file lama sudah terhapus dan seluruh503 file source kedua clone identik. Test pasca-salin menghasilkan346 lulus/1 gagal karena negative snapshot test bergantung pada APP_DEBUG lokal. Ikuti [61i](61i-koreksi-test-snapshot-null-dan-debug.md): perbaiki satu test di source, salin satu file itu, lalu jalankan ulang log pasca-salin. Jangan mengulang penghapusan atau reset yang sudah selesai.
