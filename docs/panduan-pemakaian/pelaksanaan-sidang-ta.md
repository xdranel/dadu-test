# Pemakaian Pelaksanaan Sidang TA

Panduan saat ini untuk admin/petugas, dosen pembaca dan mahasiswa. Penjadwalan dilakukan admin; impor saja tidak memesan ruang.

## Alur admin/petugas

1. Seluruh master Data Akademik → Ruangan dianggap kelas; pastikan ada yang berstatus Dapat Dipakai dan semester sumber pada Pengaturan Periode Akademik sesuai. Impor jadwal ruang iGracias melalui Sinkronisasi `room_schedule` agar okupansi semester lengkap. Tidak ada lagi penandaan Klasifikasi TA setelah pembaruan128. Excel plotting peserta tidak menggantikan sumber okupansi tersebut.
2. Buka Form → **Pelaksanaan Sidang TA**, tepat di bawah Review Sidang TA. Kartu/ringkasan/filter/daftar ada pada halaman ini; kontrol pelaksanaan tidak lagi menumpuk di Review.
3. Tekan **Kelola Pelaksanaan**. Nama periode mengikuti Kelola Periode Sidang TA. Pilih semester sumber jadwal, tanggal/jam mulai–selesai, hari pelaksanaan dan aktivasi. Durasi resmi 90 menit. Ruang yang kosong saat istirahat boleh ditawarkan. Simpan; periode lewat dapat dilipat dan dibuka/diaktifkan kembali tanpa mengganti identitas periode.
4. Pilih salah satu jalur peserta. **Dari sistem:** peserta berasal dari pengajuan TA approved yang sudah ada. **Impor:** pilih periode lalu unggah XLSX dengan 16 kolom dan dua baris header seperti file pengguna. Cek jumlah diproses/dilewati serta NIM/baris/alasan. NIM tanpa pengajuan approved pada periode itu tidak dibuatkan approval baru.
5. Tekan Tinjau pada peserta, periksa **Info Sidang**. Untuk jalur impor, tanggal/jam dan ruang pilihan dari file menjadi preferensi. Tidak ada ruangan yang ditahan pada tahap impor/pratinjau.
6. Pilih tanggal lalu **Cari ketersediaan**. Dropdown menampilkan rekomendasi sebagai pilihan awal serta alternatif ruang/jam yang bebas pada tanggal sama. Semua opsi 90 menit, kelas aktif, tidak bentrok jadwal iGracias maupun peminjaman pending/approved. Ketersediaan dosen belum diperiksa sistem.
7. Periksa ruang/jam yang terpilih, lalu **Tetapkan Jadwal**. Jika data/sumber/setting/slot berubah sejak pencarian, cari ulang. Booking approved yang berhasil langsung tampil pada mahasiswa dan mengurangi ketersediaan ruangan.
8. Koreksi sebelum sidang dimulai dilakukan di detail yang sama, melalui pencarian pilihan baru atau formulir koreksi. Pembatalan membutuhkan alasan dan menyimpan riwayat. Jadwal yang sudah dimulai dipertahankan sebagai historis.
9. Setelah mendapat keputusan penguji, pilih **Lulus** atau **Gagal**. Status ini terpisah dari persetujuan pendaftaran dan nilai akademik resmi. Gunakan **Batalkan Lulus/Batalkan Gagal** untuk kembali ke Belum ada hasil. Hasil harus dibatalkan dahulu sebelum mengganti/membatalkan jadwal; pemeriksaan berlaku juga pada jalur pembatalan ruangan umum dan pembatalan approval TA.
10. Filter nama/NIM, prodi, periode dan status; **Export Excel** mengikuti filter. Enam belas kolom mengikuti format plotting. Teks yang menyerupai formula tetap menjadi teks; ruangan yang sudah dibatalkan tidak dilaporkan sebagai penetapan aktif.

Penyesuaian dari catatan awal yang meminta wajib impor sebelum pengaturan: jalur **dari sistem tidak membutuhkan Excel** karena pengguna kemudian meminta dua jalur. Unggah file wajib pada tombol impor manual; sumber jadwal ruang tetap harus lengkap untuk pencarian. Tidak memaksakan unggah Excel kosong hanya untuk membuka pengaturan.

## Alur mahasiswa

1. Menu **Pelaksanaan Sidang TA** berada di bawah Pendaftaran Sidang TA. Kartu booking/cari jadwal lama di halaman Pendaftaran sudah dihilangkan.
2. **Info Sidang** mempunyai dua subkartu: Peserta TA dan Plotting Jadwal. Menampilkan periode, NIM, nama, prodi, status pengajuan, KK, judul, pembimbing 1/2, hari/tanggal/jam, ruang dan penguji 1/2. ID pengajuan tidak ditampilkan.
3. Ruang hanya ditampilkan setelah penetapan. Mahasiswa tidak dapat mencari, memilih, mengganti, membatalkan atau menetapkan hasil; endpoint POST pencarian/konfirmasi lama juga ditutup.
4. Setelah waktu pelaksanaan lewat, Info aktif kosong. **Riwayat Pelaksanaan** tetap menampilkan periode/tanggal/jam/status dan tombol Detail dengan snapshotnya. Belum ada hasil abu-abu, Lulus hijau, Gagal merah; pembatalan diberi label Dibatalkan. Hubungi admin/petugas SSC untuk perubahan.

## Dosen/pelaksana pembaca

Perilaku mengikuti Review Sidang TA: akun dosen yang cocok di Data Dosen dan memiliki aksi khusus `sidang_ta_registration_view_program` melihat mahasiswa prodi yang sesuai saja. Filter prodi disembunyikan dan tidak dapat diperluas lewat parameter URL. Tidak ada impor, ekspor atau tombol penetapan/hasil pada pembaca; server menolak mutasi. Tidak mengubah mekanisme SSO atau data unit yang dikerjakan tim lain.

## Pemilihan tanggal dan tindakan

Pemilih tanggal pada Tetapkan jadwal sidang dibatasi tanggal mulai–selesai Pelaksanaan yang aktif; tanggal lampau tidak dapat dipilih. Jika rentang belum aktif atau sudah habis, pemilih tanggal dan tombol pencarian dinonaktifkan. Hari yang dicentang dalam pengaturan tetap diperiksa server. Contoh pelaksanaan23–27 November: tanggal22 dan28 tidak dapat dikirim dari form normal. Perubahan jadwal tidak mengubah status kelulusan akademik secara otomatis.

Terapkan, Reset dan Export Excel berada pada satu baris tindakan di kartu filter. Angka Total merah; Export Excel berwarna hijau dengan ikon. Unggah plotting menerima XLSX maksimal10 MB dengan dua baris header dan16 kolom. Ruang yang terisi dalam file hanya preferensi awal yang dapat diubah; pemesanan baru terjadi setelah Tetapkan Jadwal.

Keputusan dan sumber yang masih ditunggu: [catatan konfirmasi](../catatan/keputusan-dan-konfirmasi.md). Rincian implementasi: [hasil125](../arsip-progres/125-hasil-pelaksanaan-sidang-ta-admin.md).
