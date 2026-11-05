# 04 — Perbaiki upload sebelum mengubah form

Prasyarat: baca [peta fitur 03](03-peta-fitur-sidang-ta-student.md). Semua langkah di bawah dikerjakan pengguna di folder development asli. Asisten belum mengubah konfigurasi atau menjalankan upload.

## Pembaruan hasil pengguna

Pengguna mengonfirmasi upload Sidang TA berhasil setelah perubahan lokal: `post_max_size=192M`, `upload_max_filesize=12M`, `max_file_uploads=20`, `memory_limit=256M`. Nilai terbaca `max_input_time` masih `-1` walaupun file konfigurasi telah diubah menjadi `120`. Perbedaan ini belum ditelusuri sampai runtime HTTP; tidak dianggap sudah terselesaikan. Sesuai instruksi pengguna, investigasi timeout ditunda dan pengerjaan fitur dilanjutkan. Belum ada bukti bahwa seluruh variasi ukuran/cabang dalam checklist di bawah sudah diuji; keberhasilan upload adalah laporan pengguna, bukan uji ulang asisten.

## Penyebab yang sudah didukung bukti

Form `resources/views/sidang_ta_registrations/form.blade.php` mengirim satu multipart POST dengan 14 atau 15 file aktif. Masing-masing sekitar 1 MiB berarti total 14–15 MiB ditambah metadata multipart.

Pemeriksaan PHP CLI pada lingkungan scan menunjukkan:

| Pengaturan | Aktual | Dampak |
| --- | --- | --- |
| Loaded ini | `/etc/php.ini` + `/etc/php.d/*.ini` | Konfigurasi PHP host yang terbaca, bukan MySQL container. |
| `post_max_size` | `8M` | Total request 14–15 MiB melewati batas. |
| `upload_max_filesize` | `2M` | Per file masih dibatasi 2 MiB meski aplikasi mengizinkan 10 MiB. |
| `max_file_uploads` | `20` | Cukup untuk jumlah file aktif sekarang. |
| `memory_limit` | `128M` | Jangan menganggap seluruh ukuran upload selalu dimuat ke memori; tetap sediakan headroom. |
| `max_input_time` | `-1` | Nilai runtime perlu diperiksa sesuai cara server dijalankan. |

Pesan persis `The POST data is too large.` berasal dari `vendor/laravel/framework/src/Illuminate/Http/Middleware/ValidatePostSize.php:23–24`: `CONTENT_LENGTH` dibandingkan dengan `post_max_size`. Penolakan terjadi sebelum controller/form request memproses dokumen.

FormRequest memakai `max:10240` KiB = 10 MiB per file. Ukuran maksimum gabungan cabang 15 file adalah 150 MiB, sehingga konfigurasi server harus mampu menampung lebih besar dari itu beserta overhead. Referensi: [PHP core ini](https://www.php.net/manual/en/ini.core.php), [pitfalls upload PHP](https://www.php.net/manual/en/features.file-upload.common-pitfalls.php), dan [middleware Laravel 13](https://github.com/laravel/framework/blob/13.x/src/Illuminate/Http/Middleware/ValidatePostSize.php).

PHP CLI ini adalah bukti lokal, bukan jaminan PHP HTTP sama. Jika menjalankan `php artisan serve` dengan binary/config yang sama, temuan sangat sesuai. Bila menggunakan PHP-FPM/Apache/Herd, periksa konfigurasi runtime web tersebut. Laptop Windows teman mungkin memakai limit berbeda; belum ada bukti bahwa sistem operasi sendiri menyebabkan perbedaan waktu upload.

## Langkah 1 — Catat runtime yang benar

Di terminal folder asli:

```bash
command -v php
php --version
php --ini
php -r 'foreach (["post_max_size", "upload_max_filesize", "max_file_uploads", "memory_limit", "max_input_time"] as $k) { echo $k, "=", ini_get($k), PHP_EOL; }'
```

Catat juga cara menjalankan aplikasi, URL/port dan apakah melalui reverse proxy. Jangan mengunggah dump `phpinfo()` atau `.env` lengkap sebagai bukti karena bisa berisi informasi sensitif. Jika perlu memeriksa HTTP runtime, tampilkan hanya nilai di atas pada endpoint diagnostik lokal sementara yang dilindungi, kemudian hapus setelah selesai.

## Langkah 2 — Sesuaikan limit PHP aktif

Untuk mempertahankan batas aplikasi 10 MiB/file pada satu final request, usulan konfigurasi development:

```ini
upload_max_filesize = 12M
post_max_size = 192M
max_file_uploads = 20
memory_limit = 256M
max_input_time = 120
```

Edit file ini aktif yang ditunjukkan `php --ini`, atau file override dalam scanned ini directory. Simpan nilai lama agar bisa dipulihkan. Hak administrator mungkin diperlukan untuk mengedit `/etc/php.ini`; langkah itu dilakukan pengguna sendiri, bukan asisten.

`12M` memberi ruang agar file sedikit di atas batas aplikasi 10 MiB ditolak Laravel dengan pesan yang jelas. `192M` memberi headroom di atas 150 MiB. Ini nilai usulan lokal, bukan kewajiban menaikkan limit semua deployment. Jika aplikasi/proxy punya kebijakan lebih rendah, batas per file/total harus disepakati ulang dan ditampilkan konsisten.

**Restart PHP yang melayani HTTP.** Untuk `artisan serve`, hentikan terminal server dengan Ctrl+C dan jalankan `php artisan serve` kembali. Untuk FPM/Apache, restart/reload service yang benar sesuai instalasi. Ulangi pemeriksaan nilai setelahnya. Mengubah `.env`, MySQL Podman atau menjalankan `config:clear` tidak mengubah directive PHP.

Jangan mengandalkan `php -d post_max_size=192M artisan serve`: pada vendor yang diperiksa, ServeCommand membuat child PHP server tanpa meneruskan flag `-d` parent. Edit konfigurasi aktif dan restart merupakan langkah yang lebih mudah diperiksa.

Jika menggunakan reverse proxy, sesuaikan juga batas body/timeout pada proxy sesuai kebutuhan. Error body yang ditolak proxy berbeda jalur dari exception Laravel ini; identifikasi dari response dan log sebelum mengubah konfigurasi tambahan.

## Langkah 3 — Uji form lama dahulu

- [ ] Pilih role aktif Mahasiswa pada `krisna@mahasiswa.test` / `password`.
- [ ] Pastikan periode aktif dan dalam rentang buka. Seeder tester tidak membuat periode; gunakan pengaturan periode yang sudah ada melalui Petugas bila diperlukan, lalu kembali ke Mahasiswa.
- [ ] Isi kontak, tanggal pembayaran tidak melebihi hari ini, dan seluruh dokumen aktif.
- [ ] Uji 15 file valid sekitar 1 MiB untuk cabang sesuai, lalu pada akun/fixture terpisah uji 14 file cabang PDM. Pengajuan aktif memang memblokir pengajuan berikutnya; jangan menghapus database hanya agar test dapat diulang.
- [ ] Buktikan request masuk controller dan pengajuan/dokumen/log tercipta lengkap tanpa error POST terlalu besar. Catat status HTTP akhir/redirect, jumlah file, total ukuran dan durasi.
- [ ] Uji satu file 11 MiB pada pengajuan terpisah: PHP mengizinkan transport, Laravel harus menolak batas 10 MiB.
- [ ] Uji file bukan PDF pada input PDF-only dan berkas yang hanya diganti ekstensi; server harus menolak berdasarkan validasi file, bukan `accept` browser saja.
- [ ] Uji batas 10 MiB dan sedikit di atasnya. Fixture PDF harus benar-benar PDF, bukan file teks bernama `.pdf`.

Test HTTP framework dengan `UploadedFile::fake()` tidak membuktikan PHP SAPI menerima multipart sebesar itu. Pemeriksaan browser/HTTP nyata tetap wajib. Kalau gagal pada request invalid dan redirect, file pilihan baru tidak dapat diisi kembali oleh server/browser; UI perlu menjelaskan agar dipilih ulang.

## Langkah 4 — Ukur upload lambat tanpa menebak

Buka DevTools → Network → Preserve log. Catat ukuran total, durasi pengiriman request, waktu menunggu response/TTFB, status response, dan log PHP. Bandingkan laptop dengan file serta jumlah yang sama, tanpa network throttling. Tentukan apakah waktu habis pada transfer, penyimpanan file, atau proses server sebelum optimasi. Hindari klaim lebih dari 10 detik pasti disebabkan Windows, database, atau jaringan.

Tambahkan indikator sedang mengirim pada pekerjaan wizard berikutnya. Progress persen hanya ditampilkan jika benar-benar diukur; native POST biasa cukup menampilkan spinner dan teks, bukan persen buatan.

## Hasil checkpoint yang dikirim untuk rescan

Kirim nilai ini sesudah restart, cara menjalankan PHP, total file/ukuran, status response dan error terbaru bila ada. Tidak perlu mengirim password atau isi `.env` lengkap. Selesaikan checkpoint ini sebelum menguji wizard agar masalah konfigurasi tidak tercampur masalah UI.

Status sesi dokumentasi: penyebab batas total terdukung sumber dan PHP CLI; belum ada bukti uji upload nyata sesudah perubahan. Scan sumber sebelum/sesudah penulisan dokumen tidak menunjukkan perubahan kode oleh asisten.
