# Peminjaman ruangan dan sumber jadwal semester

## Kebijakan master ruang setelah perubahan128

Pengguna mengonfirmasi seluruh master XLSX merupakan ruang kelas. Kolom Klasifikasi TA dan syarat is_classroom dihapus. Status Dapat Dipakai/Tidak Dapat Dipakai tetap persis seperti semula; pencarian hanya memakai Dapat Dipakai dan tetap memeriksa bentrok. Tidak ada penandaan kelas manual.

## Diagnosis historis sebelum perubahan128

Pemeriksaan baca saja VPS 19 November 2026 menemukan60 master ruang:46 Dapat Dipakai,14 Tidak Dapat Dipakai, kolom klasifikasi saat itu masih kosong. Prasyarat klasifikasi tersebut sudah dihapus pada128; status 46 aktif/14 nonaktif tetap dipertahankan. Tidak ada sumber semester atau pola mingguan tersimpan. Satu jadwal tanggal tertentu berasal dari `Dummy Json peminjaman ruangan.txt`. Impor JSON asli sebelumnya dibatalkan karena pemetaan alias belum pasti.

Berkas asli memiliki827 baris untuk 9–15 November 2026, periode 2627/1 dan jenis TETAP. Itu memang pola mingguan, bukan hanya okupansi satu minggu. Normalizer membacanya menjadi825 pola unik karena duplikat identik digabung; pola berlaku berulang sesuai hari dalam batas semester. Master dosen membantu menampilkan kode/nama, tetapi saat ini pencarian belum memeriksa bentrok dosen, sesuai keputusan divisi.

## Urutan penyiapan oleh admin

1. **Periode akademik:** pastikan 2026/2027 Ganjil terdaftar dengan batas semester yang benar. Pada VPS periode ini sudah ada 1 Agustus 2026–31 Januari 2027; tidak perlu membuat duplikat atau mengganti tanggal Pelaksanaan menjadi tanggal 9–15 November.
2. **Master ruang:** gunakan `DataRuangan.xlsx` terbaru sebagai daftar kelas Akademik. Impor di Data Akademik → Ruangan menambahkan atau memperbarui ruang menurut Kode Ruang. Cocokkan dan selaraskan kode lama dengan kelas fisik yang sama sebelum impor agar kelas seperti `1.02` dan `KTT1.02` tidak tercatat dua kali.
3. **Status:** pertahankan status yang benar dari master. Dapat Dipakai dapat menjadi kandidat setelah lolos pemeriksaan jadwal; Tidak Dapat Dipakai dikecualikan meskipun kosong. Penghapusan klasifikasi tidak mengaktifkan ruangan nonaktif.
4. **Nama/alias:** isi alias melalui Edit Ruangan setelah memastikan ruang fisiknya sama. Master terbaru sudah cocok langsung dengan 51 dari 69 label iGracias, termasuk sejumlah ruang `B-CLS`. Enam belas label lab, `KTT AULA`, dan `KTT 1.27 B` masih belum tercocokkan. Lab berada di luar cakupan Akademik. Periksa dua label lain sebelum memetakan atau mengecualikannya. [Hasil pencocokan master terbaru](../kesimpulan/129-review-pelaksanaan-dan-ruangan.md)
5. **Impor mingguan lengkap:** buka IT Panel → Sinkronisasi → Jadwal Ruangan iGracias dan unggah file asli `rekap_igracias_2026-11-13 JADWAL PERKULIAHAN.json`. Jangan gunakan contoh satu baris atau menghapus baris/metadata agar terlihat berhasil. Snapshot harus penuh tanpa filter prodi/ruang/bentrok.
6. **Periksa hasil:** pastikan sumber semester lengkap tersedia. Angka 825 adalah jumlah pola unik pada ekspor penuh, termasuk ruang di luar cakupan Akademik. Importer saat ini masih membatalkan impor jika ada label yang belum terpetakan atau jadwal baru bentrok dengan booking aktif. Jadwal per tanggal tunggal belum memenuhi syarat sumber semester.
7. **Pelaksanaan TA:** pilih semester sumber yang sesuai, tanggal/jam pelaksanaan, hari dan aktivasi, lalu cari jadwal peserta approved. Tanggal 23–27 November boleh memakai pola referensi 9–15 November karena harinya diulang selama semester. Setiap slot TA 90 menit dan tetap diperiksa ulang sebelum Tetapkan Jadwal.

## Arti pesan pencarian setelah pembaruan128

- **Tidak ada ruangan Dapat Dipakai:** periksa status master. Jangan mengubah status ruangan yang memang tidak bisa digunakan demi mengisi hasil pencarian.
- **Sumber semester belum lengkap:** periksa periode, hasil impor mingguan dan alias. Jadwal tanggal tertentu saja belum membuktikan ketersediaan sepanjang semester.
- **Tidak ada slot 90 menit:** sumber sudah lengkap dan kelas aktif tersedia, tetapi celah kosong tidak cukup dalam jam pelaksanaan. Coba tanggal lain atau periksa jadwal kuliah/booking pending dan approved.

Kode tidak menganggap seluruh ruang kosong hanya supaya pencarian mengembalikan hasil. Perlu konfirmasi pemetaan yang belum pasti; jangan mengaktifkan semua ruang atau melewati pemeriksaan bentrok.

## Peminjaman ruangan biasa oleh admin dan dosen

Menu Peminjaman Ruangan dapat dipakai Akademik, admin operasional, dan dosen yang memiliki izin modul. Dosen manual berperan Pelaksana dapat memakai alur ini di VPS dadu-test karena lingkungan aplikasi adalah staging.

1. Berikan izin **Read, Create dan Update** modul Peminjaman Ruangan sesuai tugas akun. Read membuka Dashboard dan Daftar Peminjaman Saya. Create membuka pengajuan dan cek ketersediaan. Update mengizinkan pembatalan pengajuan sendiri yang masih pending.
2. Buka Dashboard atau Pengajuan Peminjaman Ruangan; pilih tanggal/jam, cek ruangan, pilih ruang dan isi keperluan.
3. Pengajuan biasa disimpan **pending**, termasuk bila pembuatnya admin. Penetapan TA langsung approved adalah alur berbeda.
4. Daftar Peminjaman Saya hanya menampilkan pengajuan akun tersebut. Pemilik hanya dapat membatalkan pengajuan ordinary miliknya yang masih pending.
5. Review/Persetujuan tetap memerlukan permission Review yang terpisah; tidak diberikan otomatis kepada admin yang hanya boleh meminjam. Pengajuan approved memerlukan penanganan pembatalan lewat petugas review sesuai aturan modul.

Pembaruan128 memerlukan migration baru untuk menghapus kolom/index klasifikasi yang sudah ter-deploy, **tanpa fresh/seed**. Lakukan backup dan update kode/schema melalui maintenance; lihat [hasil128](../arsip-progres/128-hasil-penghapusan-klasifikasi-ta.md). Langkah rilis: [update lokal/Docker/VPS](update-lokal-docker-vps.md). Hasil teknis: [127](../arsip-progres/127-hasil-diagnosis-dan-akses-peminjaman-ruangan.md).
