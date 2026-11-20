# Peminjaman ruangan dan sumber jadwal semester

## Label ruang

Master di Data Akademik → Ruangan menyimpan label **KELAS**, **LAB**, atau kosong. Pencarian Sidang TA dan pengajuan Peminjaman Ruangan menawarkan ruang **KELAS** berstatus **Dapat Dipakai** setelah memeriksa jadwal kuliah dan peminjaman. Isi label yang belum ditentukan lewat Edit Ruangan setelah diverifikasi Akademik.

## Diagnosis historis sebelum perubahan128

Pemeriksaan baca saja VPS 19 November 2026 menemukan60 master ruang:46 Dapat Dipakai,14 Tidak Dapat Dipakai, kolom klasifikasi saat itu masih kosong. Prasyarat klasifikasi tersebut sudah dihapus pada128; status 46 aktif/14 nonaktif tetap dipertahankan. Tidak ada sumber semester atau pola mingguan tersimpan. Satu jadwal tanggal tertentu berasal dari `Dummy Json peminjaman ruangan.txt`. Impor JSON asli sebelumnya dibatalkan karena pemetaan alias belum pasti.

Berkas asli memiliki827 baris untuk 9–15 November 2026, periode 2627/1 dan jenis TETAP. Itu memang pola mingguan, bukan hanya okupansi satu minggu. Normalizer membacanya menjadi825 pola unik karena duplikat identik digabung; pola berlaku berulang sesuai hari dalam batas semester. Master dosen membantu menampilkan kode/nama, tetapi saat ini pencarian belum memeriksa bentrok dosen, sesuai keputusan divisi.

## Urutan penyiapan oleh admin

1. **Periode akademik:** pastikan 2026/2027 Ganjil terdaftar dengan batas semester yang benar. Pada VPS periode ini sudah ada 1 Agustus 2026–31 Januari 2027; tidak perlu membuat duplikat atau mengganti tanggal Pelaksanaan menjadi tanggal 9–15 November.
2. **Master ruang:** setelah migrasi kolom Label, impor `DataRuangan.xlsx` terbaru melalui Data Akademik → Ruangan. Setiap baris berkode disimpan. Nama, lokasi, kapasitas, dan label yang kosong dapat dilengkapi lewat Edit Ruangan. Impor ulang dengan sel kosong mempertahankan isian yang sudah ada. Impor mencocokkan kode tanpa membedakan spasi dan tanda baca.
3. **Status:** pertahankan status yang benar dari master. Dapat Dipakai dapat menjadi kandidat setelah lolos pemeriksaan jadwal; Tidak Dapat Dipakai dikecualikan meskipun kosong. Penghapusan klasifikasi tidak mengaktifkan ruangan nonaktif.
4. **Kode ruang:** gunakan kode sesuai iGracias, termasuk `KTT 1.27 B`. Label `KELAS` dan `LAB` menentukan jenis ruang. Koreksi identitas melalui Kode Ruang atau Nama Ruangan di master. Bila kode lama dan baru tercatat untuk satu kelas, gunakan kode terbaru. Kode lama tanpa label dan tanpa pemakaian mendatang tidak menghalangi impor.
5. **Impor mingguan lengkap:** buka IT Panel → Sinkronisasi → Jadwal Ruangan iGracias dan unggah file asli `rekap_igracias_2026-11-13 JADWAL PERKULIAHAN.json`. Jangan gunakan contoh satu baris atau menghapus baris/metadata agar terlihat berhasil. Snapshot harus penuh tanpa filter prodi/ruang/bentrok.
6. **Periksa hasil:** uji pada master dan JSON terbaru menghasilkan 798 pola tersimpan dan 27 pola dari lima lab di luar master yang dilewati. Ruang asing di luar daftar pengecualian dan bentrok dengan booking aktif tetap membatalkan impor. Jadwal per tanggal tunggal belum memenuhi syarat sumber semester. [Hasil uji impor](../kesimpulan/131-label-ruangan-dan-import-jadwal.md)
7. **Pelaksanaan TA:** pilih semester sumber yang sesuai, tanggal/jam pelaksanaan, hari dan aktivasi, lalu cari jadwal peserta approved. Tanggal 23–27 November boleh memakai pola referensi 9–15 November karena harinya diulang selama semester. Setiap slot TA 90 menit dan tetap diperiksa ulang sebelum Tetapkan Jadwal.

## Arti pesan pencarian setelah pembaruan128

- **Tidak ada ruang KELAS dan Dapat Dipakai:** periksa label dan status di master.
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
