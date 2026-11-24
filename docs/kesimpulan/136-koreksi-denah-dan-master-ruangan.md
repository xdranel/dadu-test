# Koreksi denah dan master ruangan

- Dashboard, indikator kalender, dan filter menampilkan ruang kelas yang punya posisi pada denah. Bagian Ruangan di luar denah dihapus.
- Semua 54 ruang berlabel `KELAS` pada `DataRuanganFix.xlsx` cocok dengan denah. Gedung A lantai 1 memuat 22 ruang, Gedung A lantai 2 memuat 27 ruang, dan Gedung B lantai 1 memuat 5 ruang. `KTT A-1.04` tampil di Gedung A lantai 2.
- Impor mendukung label `AULA` yang dipakai oleh `KTT AULA` di Excel. Pilihan ruang yang bisa dipinjam tetap memerlukan label `KELAS` dan status `Dapat Dipakai`.
- Master 66 baris sudah diimpor ke MySQL lokal dan VPS `dadu-test`. Verifikasi pada kedua database menghasilkan 54 ruang terpetakan. Entri di luar Excel dipertahankan di database VPS agar relasi jadwal dan riwayat peminjaman tetap tersedia. Perubahan kode Dashboard masih menunggu deploy.

Verifikasi terarah menghasilkan 85 tes lulus dengan 580 asersi. Build aset berhasil.
