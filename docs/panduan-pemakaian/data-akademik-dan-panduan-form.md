# Data Akademik dan Panduan Form Sidang TA

## Koreksi data akademik

Buka Data Akademik → Mahasiswa → View → Data Yudisium & Tugas Akhir, lalu gunakan Edit Data sesuai permission akun. Koreksi menyimpan data ke database, bukan menulis ulang berkas JSON sumber. Impor JSON berikutnya **boleh memperbarui kembali field yang sama**, sesuai keputusan pengguna.

Tab Rencana Studi & Nilai memakai periode akademik yang disimpan pada master. Daftar KRS aktif bergantung pada tautan tahun+term dan periode yang menjadi acuan aktif. Impor berhasil tidak otomatis mengubah periode aktif. Histori nilai dan seluruh mata kuliah tetap mempunyai fungsi tersendiri; section History Nilai (KRS Aktif) yang sempat ada telah dihapus.

Riwayat EPRT/TAK/BPP dan status iGracias membaca data tersimpan dari sinkronisasi. Selesaikan pesan impor berdasar NIM/baris jika data belum tersedia. Kehadiran belum disesuaikan ke sumber baru karena contoh JSON belum diberikan.

## Panduan Form Sidang TA

Buka IT Panel → Panduan Form Sidang TA menggunakan akun yang mempunyai permission terkait. Tambah/edit contoh gambar, PDF atau tautan beserta slot formnya. Label tautan dapat menutupi URL panjang. Urutan mengatur posisi beberapa panduan dalam slot yang sama; bukan nomor tahap mahasiswa. Item nonaktif tidak ditampilkan pada form.

Untuk unggahan pada server PHP-FPM, izin tulis storage harus sesuai user proses; kasus production tidak dapat dipastikan hanya dari Docker lokal. Koordinasikan pemeriksaan production dengan pengelola server sesuai [83](../arsip-progres/83-perbaikan-izin-panduan-dan-storage-produksi.md).

Sumber koreksi akademik: [93](../arsip-progres/93-hasil-perbaikan-krs-dan-edit-data-akademik.md); detail sinkronisasi: [90](../arsip-progres/90-hasil-detail-mahasiswa-dan-catatan-sinkronisasi.md).
