# Plotting dan hasil Pelaksanaan Sidang TA

- Admin dapat menentukan lama sidang dari jam mulai dan selesai dalam rentang pelaksanaan yang aktif. Pencarian menggunakan interval tersebut secara utuh dan memeriksa bentrok jadwal kelas serta peminjaman ruangan. Gedung A mendapat prioritas untuk ruang kelas.
- XLSX menerima tanggal `dd/mm/yyyy` dan mempertahankan jam mulai dan selesai dari file. LAB hanya dapat dipilih melalui preferensi ruang dari Excel pada interval asalnya. Impor menyimpan rencana hingga admin menetapkan jadwal.
- Daftar menampilkan kode ruangan di kolom Ruangan. Aksi Lulus/Gagal massal tersedia untuk peserta dengan penetapan ruang, tanggal, dan jam. Pembatalan hasil dilakukan dari detail peserta.
- Kartu Plotting Jadwal memiliki Edit Data untuk tanggal, jam, ruang, dan dua kode penguji. Hari dihitung dari tanggal. Koreksi booking memeriksa bentrok dan dicatat di riwayat. Admin juga dapat mengoreksi jadwal lampau dalam rentang periode yang masih diaktifkan, sesudah membatalkan hasil yang sudah tercatat jika ada.

Kode aplikasi dan tes diselaraskan di `dadu` dan `dadu-test`. Build aset berhasil. Sebanyak 153 tes terkait jadwal, hasil, impor, dan peminjaman ruang lulus masing-masing pada SQLite dan MySQL. Suite lengkap MySQL mencatat 1.326 lulus, 46 gagal, dan 2 dilewati. Salah satu kegagalan ada pada tes ekspor pendaftaran Sidang TA yang mengharapkan 57 kolom dan mendapat 46 kolom.

[Panduan pemakaian Pelaksanaan Sidang TA](../panduan-pemakaian/pelaksanaan-sidang-ta.md)
