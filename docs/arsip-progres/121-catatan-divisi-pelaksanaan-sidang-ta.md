# 121 — Jawaban divisi dan perubahan arah Pelaksanaan Sidang TA

> Arsip progres: keputusan dan perintah di sini mencerminkan tahap saat ditulis. Untuk pemakaian terkini buka [panduan](../panduan-pemakaian/README.md); hasil singkat tersedia di [kesimpulan](../kesimpulan/README.md).

Status: catatan kebutuhan untuk tahap fitur setelah perbaikan120. Belum mengimplementasikan menu, importer, atau status hasil sidang.

## Jawaban divisi terhadap T01–T13 pada109

1. Langsung approved, boleh disesuaikan kembali.
2. Durasi resmi satu sidang: **90 menit**.
3. Periksa ruang kosong saja; bentrok pembimbing/penguji belum menjadi pemeriksaan sistem.
4. Tetap memakai jadwal sumber perkuliahan.
5. Pelaksanaan mengikuti periode.
6. Ruang kosong saat istirahat boleh ditawarkan sesuai plotting jam mulai/selesai; prodi dapat memadatkan jadwal.
7. Tidak ada jawaban tambahan.
8. Admin yang mencari/menetapkan jadwal; mahasiswa hanya melihat hasil.
9. Hanya ruang kelas yang dapat digunakan dan tersedia tanpa bentrok.
10. Booking boleh dibatalkan/disesuaikan.
11. Mahasiswa tidak mengedit.
12. Pendekatan pola mingguan per semester diperbolehkan.
13. Penjadwalan penuh oleh admin; jalur alternatif dosen pada T13 lama tidak diperlukan untuk TA baru. Peminjaman ruangan biasa tetap alur tersendiri.

Keputusan ini menggantikan asumsi sementara durasi120 menit, pengecualian istirahat wajib, dan pencarian/konfirmasi oleh mahasiswa pada109–112. Jadwal/riwayat yang sudah ada tetap perlu dipertahankan saat migrasi fitur.

## Klarifikasi lanjutan pengguna

**Pertanyaan:** Excel memuat tanggal dan jam08.00–09.30, ruang kosong. Apakah jam wajib tetap atau sistem boleh mencari jam lain?

**Jawaban pengguna:** Excel diisi dosen untuk keperluan manual/deadline tanggal20. Tetap perlu pencarian sistem, rekomendasi ruang dan dropdown yang awalnya memilih rekomendasi. Admin dapat memilih alternatif yang tersedia pada tanggal tersebut, tidak terbatas pada jam Excel. Produk akhirnya mendukung **dua jalur: impor plotting dan penjadwalan dari sistem**.

**Usulan teknis untuk tahap fitur:** Satukan hasil kedua jalur menjadi data pelaksanaan yang sama dengan sumber/audit yang jelas. Excel menjadi preferensi tanggal/jam; dropdown menampilkan pasangan ruang dan slot90 menit yang tersedia agar perubahan jam terlihat sebelum admin menetapkan. Periksa konflik kembali saat menyimpan, lalu langsung approved. Tidak memindahkan jadwal yang sudah disahkan secara diam-diam saat impor ulang.

**Scope URL:** pengguna mengonfirmasi Sidang TA dahulu: Pendaftaran, Review, dan Pelaksanaan; modul aplikasi lain di luar tahap ini.

## Excel yang diperiksa

Sumber lokal: `/home/xdranel/Downloads/excel plotting jadwal ta.xlsx`. Satu sheet, dua baris header, kelompok A–I **Peserta TA**, J–P **Plotting Jadwal**. Satu baris contoh, durasi90 menit, ruang kosong.

Kolom: Periode Sidang TA, NIM, Nama Mahasiswa, Program Studi, Status Pengajuan, Kelompok Keahlian, Judul TA, Kode Dosen Pembimbing1/2, Hari, Tanggal, Jam Mulai, Jam Selesai, Ruang (diisi AKD), Kode Penguji1/2. Tanggal/jam disimpan sebagai serial Excel; importer perlu membacanya dengan benar. Data pribadi baris contoh tidak disalin ke docs.

## Fitur berikutnya yang sudah diminta

- Menu mahasiswa Pelaksanaan di bawah Pendaftaran: Info Sidang dengan dua subkartu Peserta TA/Plotting Jadwal, tanpa ID pengajuan; ruang ditampilkan setelah ditetapkan. Mahasiswa hanya membaca.
- Riwayat periode/tanggal/jam/status/detail; belum ada keputusan hasil memakai warna abu-abu. Info aktif kosong setelah pelaksanaan lewat, snapshot tetap dapat dibaca dari riwayat.
- Menu admin Form → Pelaksanaan di bawah Review: header, kartu Kelola Pelaksanaan penuh, filter/ringkasan/export, tabel mahasiswa/prodi/periode/status/tinjau. Dosen hanya membaca sesuai scope prodi; petugas dapat menetapkan/batalkan hasil lulus/gagal dengan jejak audit.
- Pindahkan seluruh kontrol Pelaksanaan dari Review saat menu pengganti tersedia.
- Kelola Pelaksanaan mengikuti gaya Kelola Periode, mempunyai riwayat/aktivasi, input semester sumber penuh, tanggal berdampingan dengan jam, checklist hari, tombol Simpan dan Kembali.
- Impor plotting tersedia di halaman Pelaksanaan. Aturan wajib impor sebelum mengelola perlu diselaraskan dengan jalur full system yang sekarang juga diminta; jangan memblokir full system hanya karena tidak memakai file manual.

## Jawaban berdasarkan kode yang sudah ada

- Tulisan seperti “Ganjil 2026/2027 Periode I” pada Kelola Pelaksanaan berasal dari `SidangTaPeriod::name`, yaitu nama di Kelola Periode Sidang TA. Pengaturan pelaksanaan sudah ditautkan ke setiap periode melalui foreign key. Periode baru mempunyai pengaturannya sendiri; tidak perlu mengganti identitas periode lama menjadi periode selanjutnya.
- “Semester sumber jadwal” mengambil `AcademicPeriod` pada Pengaturan Periode Akademik. Semester ini menghubungkan jadwal ruangan iGracias dari Sinkronisasi (`room_schedule`), termasuk pola mingguan semester yang sudah disetujui. Excel plotting peserta berbeda dari JSON jadwal ruangan: Excel menyuplai peserta/preferensi plotting, sedangkan JSON menyuplai okupansi ruang kuliah. Mengimpor Excel saja tidak cukup membuktikan ruangan kosong.
- Perubahan hasil sidang manual memerlukan riwayat/audit; data MK mengulang belum menjadi keputusan gagal otomatis. Rekaman sidang yang belum tersedia dalam DADU tidak dibuat-buat.

## Data yang belum cukup untuk memastikan riwayat gagal

MK Tugas Akhir pada daftar mengulang saja belum membuktikan tanggal/periode dan hasil sidang terdahulu. Jangan menciptakan riwayat gagal atau mengubah status resmi dari petunjuk itu. Tampilkan data akademik sebagai petunjuk bila diperlukan; hasil resmi baru dari keputusan admin atau sumber historis yang terkonfirmasi. JSON kehadiran juga masih menunggu contoh sumber seperti catatan sebelumnya.
