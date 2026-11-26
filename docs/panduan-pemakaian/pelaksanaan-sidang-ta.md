# Pemakaian Pelaksanaan Sidang TA

Panduan saat ini untuk admin/petugas, dosen pembaca dan mahasiswa. Penjadwalan dilakukan admin; impor saja tidak memesan ruang.

## Alur admin/petugas

1. Di Data Akademik → Ruangan, periksa label `KELAS` dan `LAB` serta status Dapat Dipakai. Impor jadwal ruang iGracias melalui Sinkronisasi `room_schedule` agar okupansi semester sumber lengkap.
2. Buka Form → **Review Pelaksanaan Sidang TA**, tepat di bawah Review Sidang TA.
3. Tekan **Kelola Pelaksanaan**. Nama periode mengikuti Kelola Periode Sidang TA. Pilih semester sumber jadwal, rentang tanggal dan jam, serta hari pelaksanaan. Centang **Aktifkan pencarian jadwal periode ini**, lalu simpan. Jam masuk dan keluar tiap sidang dapat berbeda-beda sesuai jadwalnya.
4. Pilih salah satu jalur peserta. **Dari sistem:** peserta berasal dari pengajuan TA approved yang sudah ada. **Impor:** pilih periode lalu unggah XLSX dengan 16 kolom dan dua baris header seperti file pengguna. Cek jumlah diproses/dilewati serta NIM/baris/alasan. NIM tanpa pengajuan approved pada periode itu tidak dibuatkan approval baru.
5. Tekan **Tinjau** pada peserta, lalu periksa **Info Sidang**. Excel mengisi tanggal, jam mulai, jam selesai, dan kode ruang sebagai rencana. Kolom tanggal memakai `dd/mm/yyyy`, misalnya `23/11/2026`.
6. Pilih tanggal, jam mulai, dan jam selesai, lalu tekan **Cari ketersediaan**. Sistem mencari kelas yang dapat dipakai pada rentang itu dan mengutamakan Gedung A. Jadwal kuliah dan peminjaman yang bentrok menyisihkan ruang. Jika Excel mencantumkan LAB, gunakan **Cek LAB dari Excel** untuk memeriksa ruang tersebut pada tanggal dan jam yang diimpor.
7. Periksa ruang/jam yang terpilih, lalu **Tetapkan Jadwal**. Jika data/sumber/setting/slot berubah sejak pencarian, cari ulang. Booking approved yang berhasil langsung tampil pada mahasiswa dan mengurangi ketersediaan ruangan.
8. Di kartu **Plotting Jadwal**, klik **Edit Data** untuk mengoreksi tanggal, jam, ruang, atau kode penguji. Hari mengikuti tanggal. Untuk rencana, perubahannya disimpan sebagai preferensi sampai admin menetapkan jadwal. Untuk jadwal yang telah ditetapkan, perubahan tanggal, jam, atau ruang memerlukan alasan dan pengecekan bentrok sebelum disimpan.
9. Setelah mendapat keputusan penguji, pilih **Lulus** atau **Gagal** dari detail atau centang beberapa peserta yang jadwalnya sudah ditetapkan dari daftar. Gunakan **Batalkan Lulus** atau **Batalkan Gagal** di detail untuk kembali ke Belum ada hasil. Batalkan hasil terlebih dahulu sebelum mengoreksi jadwal final.
10. Filter nama/NIM, prodi, periode dan status; **Export Excel** mengikuti filter. Enam belas kolom mengikuti format plotting. Teks yang menyerupai formula tetap menjadi teks; ruangan yang sudah dibatalkan tidak dilaporkan sebagai penetapan aktif.

Penyesuaian dari catatan awal yang meminta wajib impor sebelum pengaturan: jalur **dari sistem tidak membutuhkan Excel** karena pengguna kemudian meminta dua jalur. Unggah file wajib pada tombol impor manual; sumber jadwal ruang tetap harus lengkap untuk pencarian. Tidak memaksakan unggah Excel kosong hanya untuk membuka pengaturan.

## Alur mahasiswa

1. Menu **Pelaksanaan Sidang TA** berada di bawah Pendaftaran Sidang TA. Kartu booking/cari jadwal lama di halaman Pendaftaran sudah dihilangkan.
2. **Info Sidang** mempunyai dua subkartu: Peserta TA dan Plotting Jadwal. Menampilkan periode, NIM, nama, prodi, status pengajuan, KK, judul, pembimbing 1/2, hari/tanggal/jam, ruang dan penguji 1/2. ID pengajuan tidak ditampilkan.
3. Ruang hanya ditampilkan setelah penetapan. Mahasiswa tidak dapat mencari, memilih, mengganti, membatalkan atau menetapkan hasil; endpoint POST pencarian/konfirmasi lama juga ditutup.
4. Setelah waktu pelaksanaan lewat, **Riwayat Pelaksanaan** menampilkan periode, tanggal, jam, status, dan tombol **Detail**. Hasil yang belum dicatat tampil abu-abu, Lulus hijau, Gagal merah. Pembatalan berlabel Dibatalkan.

## Dosen/pelaksana pembaca

Perilaku mengikuti Review Sidang TA: akun dosen yang cocok di Data Dosen dan memiliki aksi khusus `sidang_ta_registration_view_program` melihat mahasiswa prodi yang sesuai saja. Filter prodi disembunyikan dan tidak dapat diperluas lewat parameter URL. Tidak ada impor, ekspor atau tombol penetapan/hasil pada pembaca; server menolak mutasi. Tidak mengubah mekanisme SSO atau data unit yang dikerjakan tim lain.

## Pemilihan tanggal dan tindakan

Admin dapat memilih hari dalam rentang pelaksanaan yang diaktifkan, termasuk tanggal yang sudah lewat. Jika pelaksanaan 23–27 November, admin masih bisa mencatat sidang tanggal 23 pada 28 November selama pengaturannya aktif. Server memeriksa hari yang dicentang, batas jam, dan bentrok ruang ketika jadwal ditetapkan.

Terapkan, Reset dan Export Excel berada pada satu baris tindakan di kartu filter. Angka Total merah; Export Excel berwarna hijau dengan ikon. Unggah plotting menerima XLSX maksimal10 MB dengan dua baris header dan16 kolom. Ruang yang terisi dalam file hanya preferensi awal yang dapat diubah; pemesanan baru terjadi setelah Tetapkan Jadwal.

Keputusan dan sumber yang masih ditunggu: [catatan konfirmasi](../catatan/keputusan-dan-konfirmasi.md). Rincian implementasi: [hasil125](../arsip-progres/125-hasil-pelaksanaan-sidang-ta-admin.md).
