# Peminjaman ruangan dan sumber jadwal semester

## Mengapa tiga impor belum cukup pada kasus VPS ini?

Pemeriksaan baca saja VPS 19 November 2026 menemukan60 master ruang:46 Dapat Dipakai,14 Tidak Dapat Dipakai, seluruhnya belum diklasifikasikan. Tidak ada sumber semester atau pola mingguan tersimpan. Satu jadwal tanggal tertentu berasal dari `Dummy Json peminjaman ruangan.txt`. Impor JSON asli sebelumnya dibatalkan karena pemetaan alias belum pasti.

Berkas asli memiliki827 baris untuk 9–15 November 2026, periode 2627/1 dan jenis TETAP. Itu memang pola mingguan, bukan hanya okupansi satu minggu. Normalizer membacanya menjadi825 pola unik karena duplikat identik digabung; pola berlaku berulang sesuai hari dalam batas semester. Master dosen membantu menampilkan kode/nama, tetapi saat ini pencarian belum memeriksa bentrok dosen, sesuai keputusan divisi.

## Urutan penyiapan oleh admin

1. **Periode akademik:** pastikan 2026/2027 Ganjil terdaftar dengan batas semester yang benar. Pada VPS periode ini sudah ada 1 Agustus 2026–31 Januari 2027; tidak perlu membuat duplikat atau mengganti tanggal Pelaksanaan menjadi tanggal 9–15 November.
2. **Master ruang:** impor XLSX melalui Data Akademik → Ruangan. Status aktif/inaktif terpisah dari klasifikasi kelas. Impor master tidak otomatis menyatakan seluruh ruang sebagai kelas.
3. **Klasifikasi:** edit ruang yang benar-benar kelas dan pilih Klasifikasi ruang untuk Sidang TA → Ruang kelas. Biarkan ruang nonkelas sebagai Bukan kelas, serta status inaktif tetap inaktif jika memang tidak dapat digunakan. Pencarian TA hanya memakai kelas berstatus Dapat Dipakai.
4. **Nama/alias:** pada Edit Ruangan, isi alias iGracias hanya jika sudah memastikan ruang fisiknya sama. Contoh calon: master 1.02 dengan alias KTT1.02. Label seperti B-CLS, aula dan lab perlu konfirmasi. Lihat [tabel pemetaan](../catatan/127-pemetaan-label-ruangan.md). Lengkapi master untuk ruang berbeda yang belum tercatat.
5. **Impor mingguan lengkap:** buka IT Panel → Sinkronisasi → Jadwal Ruangan iGracias dan unggah file asli `rekap_igracias_2026-11-13 JADWAL PERKULIAHAN.json`. Jangan gunakan contoh satu baris atau menghapus baris/metadata agar terlihat berhasil. Snapshot harus penuh tanpa filter prodi/ruang/bentrok.
6. **Periksa hasil:** untuk file ini hasil yang diharapkan825 pola mingguan, sumber semester lengkap. Pesan 1 jadwal tanggal tertentu tidak memenuhi syarat tersebut. Jika alias belum pasti atau jadwal baru bentrok booking aktif, impor dibatalkan dan data lama dipertahankan; selesaikan penyebabnya sebelum impor ulang.
7. **Pelaksanaan TA:** pilih semester sumber yang sesuai, tanggal/jam pelaksanaan, hari dan aktivasi, lalu cari jadwal peserta approved. Tanggal 23–27 November boleh memakai pola referensi 9–15 November karena harinya diulang selama semester. Setiap slot TA 90 menit dan tetap diperiksa ulang sebelum Tetapkan Jadwal.

## Arti pesan pencarian setelah pembaruan127

- **Klasifikasi ruang:** belum ada kelas aktif yang boleh dialokasikan; perbaiki master/klasifikasi, bukan mengubah JSON akademik mahasiswa.
- **Sumber semester belum lengkap:** periksa periode, hasil impor mingguan dan alias. Jadwal tanggal tertentu saja belum membuktikan ketersediaan sepanjang semester.
- **Tidak ada slot 90 menit:** sumber sudah lengkap dan kelas aktif tersedia, tetapi celah kosong tidak cukup dalam jam pelaksanaan. Coba tanggal lain atau periksa jadwal kuliah/booking pending dan approved.

Kode tidak menganggap seluruh ruang kosong hanya supaya pencarian mengembalikan hasil. Perlu konfirmasi pemetaan yang belum pasti; jangan mengaktifkan semua ruang atau melewati pemeriksaan bentrok.

## Peminjaman ruangan biasa oleh admin

Menu Peminjaman Ruangan sekarang dapat dipakai **Akademik/Superadmin dan admin operasional**, selain Pelaksana yang sudah diizinkan sebelumnya.

1. Untuk admin operasional, berikan permission **Read, Create dan Update** modul Peminjaman Ruangan melalui IT Panel → Pengaturan Role sesuai kebutuhan. Read membuka Dashboard/Daftar Saya, Create membuka pengajuan dan cek ketersediaan, Update mengizinkan pembatalan pengajuan sendiri yang pending. Permission existing tidak diubah otomatis lewat seeder.
2. Buka Dashboard atau Pengajuan Peminjaman Ruangan; pilih tanggal/jam, cek ruangan, pilih ruang dan isi keperluan.
3. Pengajuan biasa disimpan **pending**, termasuk bila pembuatnya admin. Penetapan TA langsung approved adalah alur berbeda.
4. Daftar Peminjaman Saya hanya menampilkan pengajuan akun tersebut. Pemilik hanya dapat membatalkan pengajuan ordinary miliknya yang masih pending.
5. Review/Persetujuan tetap memerlukan permission Review yang terpisah; tidak diberikan otomatis kepada admin yang hanya boleh meminjam. Pengajuan approved memerlukan penanganan pembatalan lewat petugas review sesuai aturan modul.

Tidak memerlukan migration baru/fresh/seed untuk pembaruan kode127. Langkah rilis: [update lokal/Docker/VPS](update-lokal-docker-vps.md). Hasil teknis: [127](../arsip-progres/127-hasil-diagnosis-dan-akses-peminjaman-ruangan.md).
