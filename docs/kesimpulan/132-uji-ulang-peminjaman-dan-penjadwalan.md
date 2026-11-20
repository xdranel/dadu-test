# Uji ulang Peminjaman Ruangan dan Review Pelaksanaan Sidang TA

## Hasil pada VPS dadu-test

- Commit yang diuji `2458a0e`. Database memiliki 46 kelas yang dapat dipakai, 798 pola jadwal mingguan, dan satu sumber semester lengkap.
- Dashboard, Pengajuan, Daftar Peminjaman Saya, dan Review Pelaksanaan dapat dibuka oleh akun admin dan dosen simulasi sesuai izinnya. Admin juga dapat membuka Detail Pelaksanaan dan Review Peminjaman.
- Pada 23 November 2026 pukul 08.00–09.00 WIB, pencarian peminjaman menawarkan 10 kelas untuk masing-masing akun. Sebanyak 36 kelas lain tersaring karena bentrok.
- Cari Jadwal pada Review Pelaksanaan menawarkan 133 pilihan berdurasi 90 menit pada 23 November 2026. Seluruh opsi lolos pemeriksaan label, status, dan bentrok. Rekomendasi pada pengujian adalah `KTT1.09`, 08.00–09.30 WIB.
- Pengujian memakai transaksi baca saja. Jumlah transaksi peminjaman dari awal sampai akhir tetap satu.

## Perbaikan dari audit

- Setujui dan Tolak pada Review Peminjaman menyertakan `decision` saat dikirim. Form menolak kiriman kedua tanpa menonaktifkan tombol pilihan sebelum browser menyusun data. Catatan boleh dikosongkan.
- Perubahan atau penghapusan tanggal membatalkan hasil ketersediaan sebelumnya. Respons lama tidak mengubah pilihan ruang maupun rincian kalender yang sedang dibuka.
- Konfirmasi pemindahan dari Cari Jadwal mengikuti durasi 90 menit yang ditawarkan, termasuk pada periode yang masih menyimpan aturan lama 120 menit.
- Tombol keputusan dan pembatalan untuk peminjaman tertaut Sidang TA tampil sesuai izin Review Sidang TA.

Verifikasi lokal mencakup 157 tes dengan 782 asersi, empat skenario pengiriman form di Firefox, serta simulasi respons tertunda pada pencarian dan kedua kalender. Perbaikan audit tersimpan di checkout lokal untuk rilis berikutnya.
