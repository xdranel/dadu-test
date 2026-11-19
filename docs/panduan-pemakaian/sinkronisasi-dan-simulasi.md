# Sinkronisasi data dan simulasi lokal/Docker/VPS

Panduan ini untuk impor pada lingkungan yang sudah ada. Jangan melakukan reset database untuk sekadar memperbarui data akademik. Akun khusus dosen yang dibuat manual pada VPS tidak otomatis dibuat ulang oleh reset seeder.

Login superadmin, buka **Sinkronisasi Data → Import JSON**, lalu pilih berkas pada folder privat `impor` di laptop. Gunakan urutan yang sama di development lokal dan website Docker:

| Urutan | Berkas | Tipe impor | Hasil yang diharapkan |
| --- | --- | --- | --- |
| 1 | `75-01-mahasiswa-demo.json` | Mahasiswa | 12 Student, program studi/email/semester studi konsisten |
| 2 | `75-02b-krs-demo-skenario-ta.json` | KRS | 12 mahasiswa, 95 KRS pada Ganjil 2026/2027 |
| 3 | `75-03-profil-akademik-demo.json` | Profil Akademik | Profil akademik, bahasa, TAK, SKPI, persetujuan dan status BPP skenario |
| 4 | `75-04-bpp-demo.json` | Profil Akademik | Status BPP yang sama dengan konteks periode `2627/1` eksplisit |
| 5 | `75-05-bahasa-demo.json` | Profil Akademik | Bahasa saja, termasuk tanggal tes dummy; BPP dan grup lain dipertahankan |

Gunakan pilihan tipe **Profil Akademik** yang tersedia di UI untuk tiga berkas terakhir; `semester` di profil adalah semester studi, sedangkan `periode_keuangan` adalah periode BPP. Jangan mengimpor SEEDER profil sebagai tipe Mahasiswa: format profil tidak membawa semua kolom master Student.

Jika ingin memeriksa data KRS sumber, gunakan `75-02-krs-demo-sumber.json` sebagai pengganti langkah 2. Akibatnya 11 mahasiswa masih belum memenuhi syarat KRS TA. Untuk kembali ke pengujian matriks, impor ulang `75-02b`. Impor KRS mengganti seluruh daftar mahasiswa pada periode yang sama; impor sumber **sesudah** skenario akan menghapus tambahan TA sintetis.

Tujuh berkas dalam `sumber` disimpan untuk referensi/audit. Ekspor umum 277 mahasiswa dan KRS penuh 4.277 mahasiswa tidak diperlukan untuk menguji 12 mahasiswa demo. Pengujian cohort umum memerlukan master Student yang cocok dan merupakan impor terpisah.

Setelah impor, buka periode pendaftaran Sidang TA melalui akun Petugas/Krisna bila ingin menguji pengajuan. Periode akademik Ganjil 2026/2027 yang dibuat seeder berbeda dari periode pendaftaran Sidang TA. Periksa hasil sinkronisasi/log setiap impor sebelum lanjut ke langkah berikutnya.

Matriks tetap menjadi acuan skenario. Sejak pembaruan79, IPK minimal 2,00 menjadi syarat pengiriman. BPP dan persetujuan iGracias tampil sebagai kartu informasi; keduanya tidak menambah syarat pengiriman baru. Tanggal dummy, pemeriksaan BPP Docker, dan langkah setelah update dijelaskan pada [panduan79](../arsip-progres/79-penyempurnaan-validasi-dan-fixture-bahasa.md).

## Jika KRS dilewati pada Docker

Pastikan profil mahasiswa dan periode tahun+term sudah tersedia. Periksa keterangan impor berdasarkan NIM/baris. Untuk format C, runtime harus memakai importer resmi; jika lokal berhasil tetapi server tidak, bandingkan SHA-256 file host dengan file di container app. Keduanya harus sama untuk versi yang sedang dirilis; jangan memakai hash snapshot lama sebagai nilai yang harus selalu cocok.

Jika berbeda, perbarui image dan recreate container mengikuti [panduan update lokal/Docker/VPS](update-lokal-docker-vps.md). Pada VPS1 GB gunakan penyesuaian image/resource yang sudah ditetapkan. Jangan menghapus database atau mengubah JSON menjadi format lama untuk menyelesaikan kode container yang tertinggal. Diagnosis historis lengkap tetap pada [arsip75](../arsip-progres/75-reset-demo-lokal-docker-dan-urutan-impor.md).

## Fixture dan data privat

Fixture sintetis aktif berada di [fixture Sidang TA](../fixtures/sidang-ta/README.md). Sumber/data simulasi VPS privat tetap di `docs/fixtures/sidang-ta/data_dadu/` dan tidak ikut commit. Password tidak ditulis di dokumentasi publik. Jika pindah dari VPS ke Docker laptop, gunakan backup/konfigurasi dan catatan [113](../arsip-progres/113-pemindahan-dadu-test-ke-vps.md) serta [115](../arsip-progres/115-akun-dosen-pelaksana-dan-json-simulasi-vps.md).

KRS sekarang hanya B/C dan dideteksi otomatis; petunjuk fixture format semester studi lama di arsip tidak menjadi format unggahan sekarang. Lihat [panduan KRS](95-panduan-periode-akademik-dan-memilih-format-krs.md) dan [hasil98](../arsip-progres/98-penyederhanaan-impor-krs-b-c.md).

Contoh **JSON kehadiran tetap ditunggu**. Jangan menganggap XLSX plotting sidang atau JSON KRS sebagai sumber kehadiran.
