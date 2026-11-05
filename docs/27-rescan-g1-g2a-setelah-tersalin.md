# 27 — Rescan student setelah G1 dan G2a tersalin

Tanggal: 2 November 2026. Pengguna mengonfirmasi **90 test PHP lulus**, seluruh checklist manual berhasil, dan semua berkas sudah dipindahkan ke dadu-test.

**Hasil:** tidak ditemukan blocker baru pada alur Sidang TA student yang diperiksa. Implementasi wizard, upload, card draft, tinjau dan konfirmasi kirim sudah tersalin. Bagian yang belum selesai dari daftar visual awal adalah **G2b: detail pengajuan**, terutama data akademik dan visibilitas tombol Perbaiki Pengajuan.

## 1. Perbandingan berkas

Perbandingan SHA-256 terhadap kedua folder mencakup `app`, `resources`, `routes`, `database/migrations`, `tests`, `config` dan `bootstrap` di luar cache. Tidak ditemukan berkas berbeda atau belum tersalin pada direktori tersebut. Ini termasuk source serta test JavaScript baru dan koreksi test whitespace pada 25/26.

Berkas root yang dibandingkan juga sama: composer.json/lock, package.json/lock, tailwind.config.js, vite.config.js, postcss.config.js dan artisan. `phpunit.xml` masih berbeda sesuai keputusan pengguna: asli memakai MySQL `dadu_testing`, dadu-test memakai konfigurasi awal SQLite. Ini bukan kekurangan pemindahan fitur; pengujian tetap dapat memakai command env MySQL Podman eksplisit dan pengguna memulihkan konfigurasi sementara sebelum commit/push.

Tidak membandingkan isi `.env`, runtime database, vendor/node_modules secara menyeluruh, atau aset hasil build. Kecocokan source tidak menjamin konfigurasi HTTP/database kedua folder identik.

## 2. Verifikasi yang dijalankan asisten

| Pemeriksaan | Hasil |
| --- | --- |
| Lint PHP pada class Sidang TA di aplikasi dan file Feature terkait | 29 berkas, tanpa kesalahan sintaks |
| Kompilasi Blade di memori lalu lint PHP hasilnya | 13 view Sidang TA, tanpa kesalahan; resolusi component tag dimatikan dan tidak menulis cache |
| Syntax check app.js dan sidang-ta-wizard.js | Lulus |
| Test entry point dari berkas aktual dadu-test | 1 test lulus |
| Test factory wizard/upload dari berkas aktual dadu-test | 8 test lulus |
| Jumlah definisi Feature Sidang TA pada source | 90 |
| Suite PHP dan checklist browser | Lulus berdasarkan laporan pengguna; tidak dijalankan ulang asisten |

Tidak menjalankan Laravel bootstrap/test, migrasi, database, aplikasi, Vite atau npm build. Kelulusan syntax/Node tidak digunakan untuk mengklaim suite PHP atau UI browser telah diverifikasi ulang oleh asisten.

## 3. Hasil pembacaan alur aktual

| Area | Yang diperiksa dan hasil |
| --- | --- |
| Periode | Dropdown tetap memakai periode terbuka jamak; pilihan periode tidak kembali ke first() tunggal. Revisi mempertahankan periode pengajuan asal. |
| Draft baru/resume | Owner dan identitas mahasiswa diperiksa; simpan per tahap tetap server-side. Batas akses tahap menggunakan kelengkapan tersimpan. Membuka tahap 1 tanpa draft sudah diberi warna aktif tanpa membuat draft kosong. |
| Wizard | Footer simpan berada di card tahapan, panel akademik terpisah dan tersedia pada seluruh tahap. Form save memakai novalidate dan error field; old version dipertahankan saat validasi gagal. |
| Upload | app.js kini mengimpor dan mendaftarkan kedua provider sebelum Alpine.start. Drop memasang file ke input; nama pilihan baru dan status Belum disimpan tidak disamakan dengan berkas tersimpan. Batal pilihan tidak menghapus file server. |
| Cabang biodata | Input cabang tidak aktif memiliki binding disabled dan atribut awal yang sesuai; persyaratan file tetap mengikuti cabang aktif. |
| Simpan/penggantian file | Version dan prasyarat diperiksa dalam transaksi. File baru didaftarkan untuk cleanup jika gagal; penghapusan file lama terjadi setelah commit. File final yang dipinjam revisi tidak diperlakukan sebagai file khusus draft. |
| Card draft | Lebar terbatas, kedua aksi satu baris, Hapus membuka dialog dan bukan submit langsung. Form DELETE tetap memiliki CSRF/version dan pemeriksaan permission; stale revision tetap dapat dibuang oleh pemilik yang berhak. |
| Tinjau | Preview memuat dokumen dan periode yang konsisten; status review mengikuti versi draft. Card ringkasan dan kelompok dokumen sudah baru. Tindakan unduh memakai tombol/link privat, tidak lagi link underline. |
| Konfirmasi kirim | Tombol pembuka di luar form dan type button. Form final berada di dialog; tombol Periksa kembali tidak submit. Payload tetap draft_id/version/action dengan PUT untuk revisi. Sending guard dan pemulihan pageshow ada. |
| Finalisasi | Lock Student/draft/source/period, pengecekan review version dan periode baru, daftar dokumen tepat, serta guard pengajuan aktif tetap ada. Tidak menerima upload langsung dari client final. |
| Retry/revisi | Replay consumed dilakukan sebelum gate active/source needs_review/periode; tidak menulis ulang keputusan atau log. Revisi memakai ID sumber dan source_version; metadata draft document dilepas sebelum dokumen final obsolete dihapus. |
| Akses dokumen | Download draft memeriksa pemilik/akses draft dan keberadaan file; download final memeriksa pemilik atau permission reviewer. Tidak menemukan pemindahan file privat menjadi URL publik. |
| Test terbaru | Test entry provider, warning stale dengan normalisasi DOM, kontrak form modal, PDM, revisi dan read-only sudah ikut disalin. Tidak ditemukan penghapusan assertion hanya untuk membuat test lulus pada koreksi 25. |

Pembacaan ini berfokus pada modul Sidang TA beserta hubungan yang dipakainya, bukan audit semua modul aplikasi. Tidak ditemukan kebutuhan mengubah source pada rescan ini.

## 4. Pekerjaan yang memang masih tersisa

### G2b — detail pengajuan

`resources/views/sidang_ta_registrations/show.blade.php` masih merupakan tampilan lama. Ini sudah ditunda pada rancangan 22/26, sehingga bukan file G2a yang gagal tersalin.

1. **Data akademik saat pengajuan:** masih loop key mentah dengan penggantian underscore. Perlu kelompok identitas, ringkasan IPK/SKS/TAK/EPrT, status akademik, judul ID/EN, pembimbing/penguji, jadwal dan waktu sumber. Nilai wajib tetap dari `academic_snapshot` tersimpan; jangan menggambarnya ulang dari Student terbaru.
2. **Perbaiki Pengajuan:** tombol masih hanya dibatasi status `needs_review`. Saat G2b, batasi tampilannya dengan kepemilikan melalui pemetaan StudentContext yang sama dan permission update. Backend sudah memeriksa akses sehingga ini bukan bypass, tetapi pembaca yang tidak berhak masih dapat melihat tautan yang berakhir 403.
3. **Label riwayat status:** tampilan masih memakai str_replace/ucfirst pada action. Label bahasa Indonesia dan susunan informasi dapat dirapikan bersama detail, tanpa mengubah log tersimpan.

G2b belum memiliki kode panduan pengganti pada dokumen ini. Panduan berikutnya tetap harus menyediakan lokasi edit, command file baru bila ada, test lengkap untuk snapshot historis/fallback/kepemilikan, serta checklist browser.

### Cleanup nonblokir sesudah tampilan stabil

`form.blade.php` dan `partials/upload.blade.php` lama masih ada. Pencarian pemanggil menemukan partial hanya dipakai form lama; tidak ditemukan controller/route aktif yang memakai form tersebut. `reportValidity()` tersisa di form legacy itu, sedangkan wizard aktif sudah memakai novalidate. Jangan mengaktifkan kembali form legacy untuk mengambil tampilannya. Cleanup boleh dibahas terpisah setelah G2b, tanpa mengubah modul Yudisium atau flow petugas.

## 5. Langkah berikutnya

Tidak ada koreksi kode wajib atau test baru dari rescan ini; tidak perlu mengulang suite hanya karena dokumen audit ditambahkan. Tahap G1 dan G2a tercatat lulus menurut pengguna dan sudah tersinkron. Lanjut berikutnya ke G2b detail pengajuan sesuai daftar visual awal. Workflow petugas penuh tetap menunggu penjelasan pengguna; tidak dimulai oleh audit ini.

Asisten hanya menulis dokumen ini dan memperbarui catatan pendamping [07](07-catatan-pendamping-sidang-ta.md). Hash source diperiksa kembali sesudah penulisan untuk memastikan tidak ada perubahan aplikasi oleh asisten.
