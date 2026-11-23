# Tampilan Review Sidang TA, Review Pelaksanaan, dan Ruangan

- Kedua halaman review memakai satu kartu angka mengikuti Review Yudisium. Urutan filter adalah Nama atau NIM, Periode, Status, lalu Program Studi untuk admin. Hak baca dosen tetap mengikuti program studi akun. Penjelasan ikon dan pesan akses prodi dihapus dari tampilan.
- Detail pendaftaran menaruh tombol kembali sejajar dengan nama dan NIM. Informasi waktu tetap tersedia pada kartu periode. Riwayat status menggunakan tampilan timeline yang juga dipakai mahasiswa.
- File Excel, Periode, dan tombol Impor plotting sejajar pada layar lebar. Mahasiswa melihat Plotting Jadwal di Info Sidang dan dapat membuka Info Peserta, jadwal lengkap, serta riwayat status dari Detail. Kolom riwayat memakai judul Aksi. Timeline pelaksanaan mahasiswa menampilkan kejadian dan waktu dari log pelaksanaan. Pelaku dan catatan internal tersedia untuk staf.
- Daftar Ruangan dan tampilan peminjaman memakai kode untuk identitas ruang. Pilihan ruang menampilkan kode, lokasi, dan kapasitas. Form Tambah dan Edit dapat disimpan tanpa nama. Entri baru memakai kode sebagai nama internal dan edit mempertahankan nama historis untuk pencocokan.

Impor master Excel mencocokkan kode tanpa membedakan spasi dan tanda baca. Kode yang cocok diperbarui, kode baru ditambahkan, dan kode di luar file tetap tersimpan. Sel kosong mempertahankan nilai yang sudah ada.

Paginasi ruangan memakai ID sebagai pengurut kedua saat waktu pembuatan sama. Uji baca saja di VPS menemukan urutan lama hanya menjangkau 120 dari 140 entri unik dan mengulang 20 entri. Urutan baru menjangkau seluruh 140 entri, masing-masing satu kali. `DataRuanganFix.xlsx` berisi satu `KTT1.02` dan bebas kode ganda setelah normalisasi.

Verifikasi SQLite menghasilkan 185 tes lulus dengan 1.288 asersi. Satu tes pembatalan di suite terpadu gagal pada foreign key `student_courses`, dan kegagalan yang sama muncul pada checkout sebelum perubahan. Build aset berhasil.
