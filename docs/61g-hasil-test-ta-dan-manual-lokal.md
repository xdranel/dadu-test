# 61g — Hasil test TA dan urutan uji manual lokal

## Bukti hasil

Log pengguna `/tmp/dadu-sidang-ta-61.log` dibaca langsung oleh asisten: **347 passed,3987 assertions,36.03s**. Ini mencakup27 kelompok Feature Sidang TA serta2 kelompok Unit policy/requirements, termasuk Activation, ResetTestData, finalisasi, targeted revision, deadline, preview, UI dan ekspor. Tidak ada failure pada run tersebut.

Tiga test Node dijalankan asisten sesudah memastikan file test tidak menulis source: entry1, wizard8, preview4, total **13 passed,0 failed**, seluruh command exit0. Tidak ada keperluan mengulang Node sebelum ada perubahan JavaScript atau masalah baru.

Hasil Auth EmailVerification+Authentication sebelumnya dilaporkan pengguna12 passed. **Full suite proyek belum lulus**: PasswordResetTest masih meminta notification pada POST route yang tidak terdaftar, dan alur reset pengganti mempunyai masalah pembuktian kepemilikan akun sesuai61f. Belum ada keputusan mengubah perilaku produk Auth. Jangan mencatat hasil scoped TA sebagai bukti seluruh aplikasi lulus.

## Hasil rescan source

- 46 path paket61 ada lengkap di folder asli; selisih yang terdeteksi hanya formatting serta import alias pada SidangTaDraftController yang setara dengan fully qualified names.
- Migration aktual yang dipertahankan:2026_11_04_231319_set_sidang_ta_requirements_default_to_two.php.
- Tujuh file lama sudah tidak ada di folder asli; dadu-test masih menunggu penyalinan.
- AuthenticationTest61d dan UserFactory61e sudah diterapkan; keduanya perlu ikut salin akhir.
- JSON resmi SKPI, approval iGracias, riwayat tes EPRT dan TAK masih menunggu konfirmasi; tidak ada perubahan adapter baru pada61g.

## Langkah berikutnya — pengujian manual TA lokal

Pengujian TA lokal dapat dilanjutkan berdasarkan hasil347+13 di atas, sambil mempertahankan status gagal full suite sebagai pekerjaan Auth terpisah. Ini melanjutkan pengujian fitur, bukan menutup audit seluruh aplikasi. Tidak ada perubahan kode fitur baru pada61g.

**1. Buka terminal folder development asli.** Jika schedule:work sedang berjalan, hentikan sementara dengan Ctrl+C selama pembersihan data uji. Pastikan database aplikasi pada .env adalah database lokal yang memang hanya untuk testing (sebelumnya dadu), berbeda dari dadu_testing untuk unit test.

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
php artisan config:clear
php artisan migrate --force
php artisan sidang-ta:reset-test-data
```

Command terakhir hanya pratinjau. Baca nama database, tabel dan file yang ditampilkan. Data TA lama pada database aplikasi lokal seluruhnya merupakan data uji yang sebelumnya disepakati boleh dibersihkan. Migration mengubah default untuk insert baru, bukan mengubah row versi1 menjadi2.

**2. Jika database/lingkup pratinjau sudah sesuai, jalankan reset eksplisit.**

```bash
php artisan sidang-ta:reset-test-data --execute
php artisan optimize:clear
```

Asisten tidak menjalankan command ini. Reset tidak membuat periode, memperbarui tanggal periode, mengisi data akademik, atau menjadikan facts unknown lulus. Akun, periode TA, sinkronisasi/profil dan data modul lain tetap dipertahankan. Jika reset melaporkan kegagalan, berhenti dan kirim outputnya sebelum mengulang.

**3. Jalankan aplikasi/Vite seperti biasanya dan uji student.**

1. Pastikan ada periode TA terbuka menurut waktu server. Login Krisna atau dummy002, mulai draft baru. Semua akun harus melihat alur terkini dengan2 upload pada tahap2,3 PDF dasar tahap3, waiver hanya bila mode EPRT mensyaratkan, dan cabang biodata/PDM tahap4.
2. Simpan satu file saja, tutup halaman, buka draft kembali: file tersimpan, tahap berikutnya belum terbuka sampai kelengkapan tahap terpenuhi.
3. Uji drag/drop dan picker; nama file langsung terlihat. Contoh gambar dan berkas dapat dipreview dalam dialog.
4. Tinjau draft lengkap. Bila facts akademik/EPRT belum memenuhi atau belum terkonfirmasi, kirim harus nonaktif dengan alasan yang terlihat. Jangan mengubah data akun biasa menjadi dummy-lulus hanya untuk membuat tombol aktif.
5. Pada akun dengan fixture dummy yang memenuhi sesuai53, uji modal konfirmasi kirim dan pengajuan masuk ke daftar petugas. Ini pengujian memakai data dummy, bukan bukti data resmi JSON telah tersedia.

**4. Uji petugas dan revisi pada pengajuan dummy yang berhasil dikirim.**

1. Verifikasi satu dokumen: card hijau, tombol Verifikasi nonaktif. Minta perbaikan pada dokumen lain: card kuning, catatan tersimpan tanpa padding whitespace.
2. Koreksi keputusan sebelum Minta Revisi; verified menghapus catatan perbaikan. Setelah Minta Revisi, mutasi petugas dikunci dan preview tetap tersedia.
3. Student hanya dapat mengganti upload yang diminta. Setelah kirim revisi, berkas unchanged tetap verified, target pengganti pending, snapshot/riwayat tampil sesuai peran.
4. Uji expiry memakai pengajuan dummy/periode khusus pengujian, jangan mengubah semua periode. schedule:work boleh dijalankan selama pengujian deadline lalu dihentikan Ctrl+C setelah selesai.

**5. Sesudah manual test TA lulus, salin48 file menggunakan bagian copy pada61.** Manifest di61 sekarang mencakup46 file paket TA +AuthenticationTest61d +UserFactory61e. Hapus tujuh path retire dari dadu-test sesuai command61, kemudian jalankan test TA kembali di dadu-test memakai koneksi eksplisit MySQL Podman yang sama. Jangan menyalin .env atau phpunit.xml dari folder asli.

Belum menyatakan full suite proyek lulus atau memberi commit/push final. Hasil pasca-salin dan keputusan lingkup Auth masih perlu dicatat. Reset/deploy instance publik tidak dilakukan otomatis saat menyiapkan manual test lokal ini.

## Batas verifikasi

Bukti347 PHP berasal dari log test yang dijalankan pengguna.13 Node berasal dari command yang dijalankan asisten. Asisten tidak menjalankan database aplikasi, migration, reset, browser, atau menyalin source. Hash source kedua folder diperiksa sebelum/sesudah setiap perubahan Markdown, termasuk sesudah menjalankan Node, dan tetap sama.


## Pembaruan sesudah laporan pengguna

Manual TA lokal dilaporkan lulus dan48 file telah disalin. Rescan membuktikan48 file identik; tujuh file lama masih tertinggal hanya di dadu-test. Ikuti [61h](61h-rescan-setelah-salin-dan-pembersihan-file-lama.md) untuk pembersihan dan test pasca-salin. Tidak perlu mengulang reset/manual test yang sudah lulus hanya karena catatan lama menyebutnya belum dikerjakan.
