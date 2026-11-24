# Identitas dan riwayat Peminjaman Ruangan

- Rincian Dashboard menampilkan kode dan prodi dosen untuk perkuliahan, kode dosen untuk peminjaman dosen, serta nama dan unit kerja untuk peminjam lain. Prodi berasal dari Data Dosen. Unit berasal dari penugasan Unit Kerja pada akun.
- Denah memakai warna merah dan label Penuh saat jadwal untuk hari itu lengkap dan tidak ada celah kosong minimal satu jam pada ruang yang dapat dipakai. Entri peminjaman pada rincian tetap kuning.
- Lonceng pada sidebar menghitung pengajuan aktif berstatus Pending yang dapat ditinjau pengguna. Angka diperbarui saat halaman dimuat.
- Review menampilkan satu kartu per baris, tanpa NIP dan tanpa catatan impor iGracias. Tanggal dengan jam yang sama dikelompokkan. Pembatalan membutuhkan konfirmasi dan alasan.
- Daftar Pengajuan diurutkan dari pengajuan terbaru. Peminjaman lintas bulan tetap di daftar hingga sesi terakhir selesai dalam WIB. Riwayat memakai bulan sesi terakhir dan membuka bulan berjalan secara default. Dropdown tetap menyediakan bulan arsip dari semester sebelumnya.

Verifikasi mencakup 89 tes dengan 634 asersi di SQLite, kueri riwayat baca saja di MySQL lokal, dan build aset.
