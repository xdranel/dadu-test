# 123 — Pelaksanaan Sidang TA dikelola admin

> Arsip progres: keputusan dan perintah di sini mencerminkan tahap saat ditulis. Untuk pemakaian terkini buka [panduan](../panduan-pemakaian/README.md); hasil singkat tersedia di [kesimpulan](../kesimpulan/README.md).

Dasar yang sudah diminta dilanjutkan: [jawaban divisi121](121-catatan-divisi-pelaksanaan-sidang-ta.md). Perbaikan120–122 sudah selesai. Rancangan teknis ini memisahkan fitur Pelaksanaan dari pemeriksaan berkas, mempertahankan booking/database lama, dan menyediakan dua jalur yang memakai validasi ruangan yang sama.

## Pendekatan

Gunakan konteks `SidangTaSchedule` yang sudah ada sebagai sumber hasil penjadwalan dan riwayat. Menyimpan plotting dalam sistem kedua yang terpisah akan membuat peminjaman ruangan dan kartu mahasiswa mudah berbeda; karena itu impor dan pencarian sistem bermuara pada konteks/booking yang sama. Impor bukan pengganti JSON jadwal ruang iGracias: sumber okupansi semester harus tetap lengkap.

Keputusan pengguna: full admin, mahasiswa hanya membaca; durasi90 menit; kelas yang tersedia saja; pemeriksaan dosen tidak termasuk; waktu istirahat boleh dipakai; booking langsung disahkan dan dapat dikoreksi admin; dosen hanya melihat prodi sendiri; dua jalur impor dan sistem.

## Alur

1. Admin membuka Form → Pelaksanaan Sidang TA. Daftar berisi pengajuan approved dan konteks pelaksanaan historis; filter nama/NIM, prodi, periode dan status. Dosen mengikuti scope prodi yang sama dengan Review, tanpa tindakan tulis/ekspor.
2. Admin mengelola rentang pelaksanaan per periode. Semester sumber jadwal penuh satu baris; tanggal mulai berdampingan dengan jam mulai, tanggal/jam selesai serupa. Durasi resmi90 menit; tidak ada istirahat wajib yang menghilangkan slot. Periode selesai dapat dilipat dan diaktifkan kembali tanpa mengubah identitasnya.
3. Jalur impor membaca dua baris header dan16 kolom Excel. Baris tanpa ruang menyimpan peserta dan preferensi tanggal/jam. Klarifikasi terakhir pengguna: ruang terisi menjadi pilihan awal yang dapat diganti; booking selalu menunggu tombol Tetapkan Jadwal oleh admin. Impor saja tidak menahan ruang. Tidak menimpa booking yang sudah disahkan melalui impor ulang.
4. Jalur sistem mengambil peserta approved dari DADU, admin memilih tanggal lalu mencari. Sistem mengurutkan rekomendasi dan memberi dropdown pasangan ruang/slot90 menit yang benar-benar tersedia pada tanggal itu; pilihan awal rekomendasi. Preferensi Excel diprioritaskan bila tersedia. Pilihan alternatif boleh memakai jam lain pada tanggal yang sama.
5. Saat ditetapkan, ruang dicek lagi di dalam transaksi/lock bersama peminjaman ruangan. Token terikat admin, pengajuan, versi konteks/pengaturan/sumber, kandidat dan kedaluwarsa. Konflik/stale token ditolak; tidak mengganti slot diam-diam.
6. Admin boleh mengubah/membatalkan booking mengikuti mekanisme koreksi yang sudah melindungi riwayat. Peminjaman ruangan biasa tetap alurnya sendiri; endpoint dosen alternatif TA lama tidak lagi menjadi alur baru.
7. Admin menandai Lulus/Gagal dan dapat membatalkan penilaian kembali menjadi Belum ada hasil. Ini terpisah dari approval pendaftaran. Semua keputusan/koreksi dicatat dalam audit. MK mengulang tidak otomatis dijadikan kegagalan historis.
8. Mahasiswa membuka menu Pelaksanaan di bawah Pendaftaran: Info Sidang terdiri Peserta TA dan Plotting Jadwal; ruang hanya tampil bila sudah ditetapkan. Info aktif hilang setelah jadwal sidang lewat; Riwayat tetap menyimpan detail. Belum ada hasil berwarna abu-abu; Lulus hijau, Gagal merah. Tidak ada tombol cari, pilih, ubah, atau batalkan.

## Data dan izin

Tambahkan metadata peserta/preferensi/snapshot/hasil pada `sidang_ta_schedules`, aktivasi pada setting, dan audit pelaksanaan terpisah dari riwayat Pendaftaran. Booking/approval lama tidak dihapus dan tidak dipendekkan otomatis; konfigurasi pencarian baru mengikuti90 menit. Gunakan UUID pengajuan/periode untuk semua route baru.

Penulisan/hasil/import/export khusus reviewer TA, pengaturan khusus permission periode. Dosen pembaca menggunakan `sidang_ta_registration_view_program` dan scope dosen terbaru. Mahasiswa hanya miliknya. Baris Excel NIM/periode yang belum mempunyai pengajuan approved dilewati dengan alasan NIM/baris, tanpa membuat approval atau mahasiswa baru.

Riwayat yang sudah mempunyai booking nyata dapat ditampilkan; data gagal masa lalu yang belum ada tidak dibuat-buat. Snapshot info dibekukan saat penetapan untuk detail riwayat. CSV/XLSX tidak mengeksekusi formula dari data pengguna.

## Verifikasi

Tes membuktikan mahasiswa tidak dapat menulis lewat URL lama/baru, dosen tidak memperluas prodi atau mengubah hasil, slot90 menit/kelas/istirahat, alternatif dan konflik/stale, impor duplikat/serial tanggal/ruang kosong/booking lama, hasil+undo, snapshot sesudah waktu lewat, serta integrasi pembatalan approval dan ketersediaan ruang. Hanya DB testing; tidak deploy/migrate live/commit/push.
