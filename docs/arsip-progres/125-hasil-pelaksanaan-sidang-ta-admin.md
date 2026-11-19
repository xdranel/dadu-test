# 125 — Pelaksanaan Sidang TA oleh admin

> Arsip progres: keputusan dan perintah di sini mencerminkan tahap saat ditulis. Untuk pemakaian terkini buka [panduan](../panduan-pemakaian/README.md); hasil singkat tersedia di [kesimpulan](../kesimpulan/README.md).

Status: implementasi, verifikasi, rescan dan penyelarasan source selesai; belum di-commit/push/deploy. Acuan: [121 jawaban divisi](121-catatan-divisi-pelaksanaan-sidang-ta.md), [123 rancangan](123-rancangan-pelaksanaan-sidang-ta-admin.md), [124 rencana](124-rencana-pelaksanaan-sidang-ta-admin.md). Perbaikan halaman/UUID sebelumnya ada di122.

## Klarifikasi pengguna tentang impor

Pengguna menjelaskan bahwa apa pun jalurnya, **admin tetap menekan Tetapkan Jadwal**. Ruang yang sudah diisi AKD dalam Excel menjadi pilihan awal apabila tersedia dan dapat diganti. Ruang kosong memakai rekomendasi sistem. Perbedaan istilah opsi1 pada percakapan sudah dijelaskan: implementasi mengikuti alur eksplisit pengguna, sehingga **impor saja belum memesan ruangan**.

Setelah Tetapkan Jadwal berhasil, booking langsung approved tanpa persetujuan tambahan dari petugas ruangan. Sistem memeriksa ketersediaan lagi sebelum menyimpan; admin tidak diam-diam dipindahkan ke kandidat lain jika slot terambil.

## Data dan kompatibilitas

- Metadata plotting/hasil/snapshot ditambahkan pada konteks `sidang_ta_schedules` yang sudah ada; audit pelaksanaan terpisah dari empat kejadian riwayat Pendaftaran.
- Pengaturan pencarian baru 90 menit, tanpa jeda istirahat wajib. Migration mengubah setting pencarian dan versi token; occurrence booking lama tidak dihapus/dipendekkan.
- Impor identik tidak menaikkan versi metadata; duplikat NIM dengan isi bertentangan ditolak. Booking/snapshot yang sudah disahkan tidak ditimpa oleh impor ulang. File dibatasi 5.000 baris; file lebih besar dibagi agar sesuai kapasitas server.
- Parser membaca tanggal/jam serial Excel, DateTime dan format teks yang didukung; tidak menjalankan formula. Kepemilikan/prodi untuk otorisasi berasal dari pengajuan DADU, bukan kolom file.
- URL baru memakai UUID pengajuan/periode dari120. Akun/password, data sinkronisasi privat, SSO dan alur peminjaman ruangan biasa dipertahankan.
- Hasil Lulus/Gagal baru tidak mengubah nilai TA, MK mengulang, atau approval pendaftaran secara otomatis. Data gagal lama tidak diciptakan dari dugaan MK mengulang. JSON kehadiran tetap menunggu contoh seperti catatan sebelumnya.

## Pengujian, rescan dan rilis

Tes terarah terakhir: **79 lulus, 374 assertion**, termasuk rollback/upgrade schema MySQL pada DB uji, impor tanpa booking, hak akses, konflik, snapshot, hasil/undo serta peminjaman biasa. Build frontend berhasil; syntax PHP pada 29 file lulus dan whitespace diff bersih. Review independen terakhir tidak menemukan masalah penting tersisa. Pengujian hanya pada `dadu_testing`; tidak ada migration/seed/reset pada DB aplikasi maupun deploy VPS.

Suite penuh: **1.275 lulus, 45 gagal, 2 dilewati**, 10.266 assertion, 274,38 detik (1.322 kasus). Perbandingan nama kelas/kasus dengan baseline118 menunjukkan **45 kegagalan yang sama persis, nol kegagalan tambahan**. Suite penuh belum hijau; kegagalan lama tidak disembunyikan. Nama kasus dan bukti rescan tersedia pada [125-verifikasi-pelaksanaan.json](../catatan/bukti-verifikasi-historis.md#125-verifikasi-pelaksanaan-json). Sesudah suite penuh, penyeragaman label kartu, Export Excel dan label audit diverifikasi ulang: **16 tes UI/akses lulus, 94 assertion**, 16,88 detik. Tidak ada perubahan logika produk setelah suite penuh. Dua kegagalan tambahan pada percobaan pertama (fixture rollback schema dan assertion UI pencarian mahasiswa lama) telah diperbaiki dan tidak muncul pada suite penuh terakhir.

Daftar perubahan aplikasi/tes: [125-daftar-file-pelaksanaan.txt](../catatan/bukti-verifikasi-historis.md#125-daftar-file-pelaksanaan-txt). Laporan rescan/hash: [125-verifikasi-pelaksanaan.json](../catatan/bukti-verifikasi-historis.md#125-verifikasi-pelaksanaan-json). **40 file telah disalin dan sama hash-nya di kedua folder.** Dari 750 file aplikasi/tes source yang diperiksa, 745 cocok dengan target; lima adaptasi lama dadu-test tetap berbeda secara sengaja (dua policy dokumen, RolePermissionSeeder, CSS dan antrean admin). View kompatibilitas `resources/views/documents/all.blade.php` tetap khusus target. Tidak ada file aplikasi source yang hilang di target.

Backup target awal: `private-import/124-before/`; backup source sebelum disalin: `private-import/124-source-before/`; manifest: `private-import/124-copy-manifest.json`. HEAD/index kedua repo dan file terlindungi tetap sama. `.gitignore`, `package-lock.json`, `phpunit.xml` yang sebelumnya sudah diubah pengguna pada source tetap dipertahankan. Dokumentasi hanya di dadu-test; source dadu tidak dibuatkan kembali docs/docker. Log/JUnit disimpan privat pada `private-import/dadu120-124-*`; tidak ikut commit.

Tidak melakukan git add, commit, push, deployment, atau migration pada database aplikasi. Migration baru perlu dijalankan oleh operator saat menggunakan/rilis kode ini.


## Lokasi panduan dan catatan

Alur admin/mahasiswa/dosen: [Pelaksanaan Sidang TA](../panduan-pemakaian/pelaksanaan-sidang-ta.md). Langkah rilis: [update lokal/Docker/VPS](../panduan-pemakaian/update-lokal-docker-vps.md). Penyalinan ulang: [penyelarasan file](../panduan-pemakaian/penyelarasan-file.md). Pertanyaan dan jawaban: [catatan konfirmasi](../catatan/keputusan-dan-konfirmasi.md). Kesimpulan singkat: [progres125](../kesimpulan/125-pelaksanaan-sidang-ta-admin.md).
