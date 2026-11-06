# 74 — Rescan penyesuaian BPP dan batas commit

Pengguna melaporkan semua test berhasil setelah koreksi pesan konflik periode dan tiga file sudah disalin. Asisten melakukan rescan source serta unit murni, tanpa menjalankan database, deployment, atau commit.

## 1. Hasil salinan dan verifikasi

Tiga file checkpoint73 identik berdasarkan SHA-256 pada repo development asli dan dadu-test:

- `app/Support/AcademicProfileJsonNormalizer.php`.
- `tests/Unit/AcademicProfileJsonNormalizerTest.php`.
- `tests/Feature/AcademicProfileJsonImportTest.php`.

Ketiganya lulus `php -l`. Seluruh **515 file** pada enam folder source `app`, `config`, `database`, `resources`, `routes`, dan `tests` identik antara `/home/xdranel/Code/Project/php-laravel/dadu` dan `/home/xdranel/Code/Project/secret/dadu-test`.

Koreksi pesan sudah tersalin: konflik yang melibatkan wrapper financePeriod memakai pesan lama yang memuat `berbeda dengan konteks impor`; konflik sumber baris tanpa wrapper memakai pesan umum. Test regresi lama dipertahankan.

Asisten menjalankan command berikut dari dadu-test, tanpa konfigurasi/bootstrap Laravel atau database:

```bash
vendor/bin/pest --no-configuration tests/Unit/AcademicProfileJsonNormalizerTest.php tests/Unit/OfficialKrsJsonNormalizerTest.php tests/Unit/SidangTaAdmissionPolicyTest.php tests/Unit/SidangTaMeetRequirementsTest.php --colors=never
```

Hasil aktual: **72 passed,527 assertions**. File profil kini mempunyai33 deklarasi unit dan38 deklarasi feature; laporan berhasil dari pengguna dicatat terpisah karena asisten tidak menjalankan38 feature MySQL tersebut.

Normalizer source aktual membaca ulang277 baris FINANCE UPDATE:277 periode terkonfirmasi2627/1 dan0 grup skipped. Pesan konflik periode lama juga diverifikasi langsung pada normalizer. Semua pemeriksaan sumber asli dilakukan di memori, tanpa impor database atau menyalin data pribadi.

## 2. Apakah masih ada koreksi untuk checkpoint ini?

Pada pemeriksaan ini tidak ditemukan koreksi tambahan untuk pembacaan/penyimpanan periode BPP yang menjadi cakupan73. Dukungan KRS resmi70 dan periode BPP73 sudah tersedia pada source yang tersalin. Data sumber KRS dan periode BPP yang sebelumnya ditunggu juga sudah tersedia.

Namun, seluruh penyesuaian tampilan belum selesai. Source yang masih tersisa:

| Bagian | Kondisi saat rescan |
| --- | --- |
| Card BPP tahap3 | `SidangTaDraftController` masih mengambil status dari tabel lama, tidak mengenali label KREDIT, dan selalu memakai keterangan periode belum terkonfirmasi. |
| Panel kanan BPP | `wizard-academic.blade.php` masih memetakan LUNAS/belum lunas saja dan selalu mencetak periode belum terkonfirmasi. |
| Pembaca snapshot umum | `SidangTaAcademicSnapshot::forStudent` masih membaca relasi BPP lama tanpa periode resmi. |
| Bagian lain yang sudah dicatat | Card persetujuan sidang, label bahasa, serta konsistensi pembaca data kampus tetap merupakan pekerjaan lanjutan67/68/71. |

Kondisi ini tidak menjadi koreksi tambahan pada parser73 dan tidak menghalangi commit perubahan importer saat ini. Commit harus menggambarkan dukungan impor KRS/periode BPP, bukan mengklaim seluruh tampilan atau integrasi modul terkait selesai.

BPP tetap tidak menjadi blocker pengiriman TA. Perubahan informasi BPP tidak menghapus kewajiban bukti dan tanggal pembayaran wisuda.

## 3. Validasi runtime yang masih perlu dibedakan

- Laporan test pengguna diterima; asisten tidak mengklaim menjalankan full suite MySQL atau memeriksa database aplikasi.
- Uji impor dua akun demo dan penerapan migration kelas KRS pada database aplikasi tetap mengikuti70/73 jika belum dilakukan. File migration yang tersalin tidak membuktikan schema server sudah diterapkan.
- Deployment Docker, pemeriksaan hash host/container, dan uji browser dilakukan setelah source siap dipasang melalui64 bagian12. Asisten tidak mengakses atau mengubah server.

## 4. Commit message sesuai cakupan source saat ini

```text
feat: dukung impor KRS resmi dan periode BPP

- sinkronkan daftar KRS lengkap per mahasiswa dan periode secara transaksional
- pertahankan riwayat periode lain dan perluas kolom nama kelas
- baca periode_keuangan serta jaga kompatibilitas format BPP lama
- pertahankan data BPP valid saat periode konflik atau sumber gagal
- tambahkan pengujian dan JSON sintetis akun demo
- perbarui panduan deployment, pemulihan container lama, dan hasil rescan
```

Asisten hanya menulis Markdown74 dan tautan lanjutannya pada73. Source/deployment kedua repo dibandingkan dengan manifest sebelum/sesudah dan tidak diubah. Tidak ada commit, push, migration, seed, impor, build, atau restart oleh asisten.
