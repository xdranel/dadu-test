# 125 — Pelaksanaan Sidang TA oleh admin

Status: implementasi, verifikasi, rescan dan penyelarasan source selesai; belum di-commit/push/deploy. Acuan: [121 jawaban divisi](121-catatan-divisi-pelaksanaan-sidang-ta.md), [123 rancangan](123-rancangan-pelaksanaan-sidang-ta-admin.md), [124 rencana](124-rencana-pelaksanaan-sidang-ta-admin.md). Perbaikan halaman/UUID sebelumnya ada di122.

## Klarifikasi pengguna tentang impor

Pengguna menjelaskan bahwa apa pun jalurnya, **admin tetap menekan Tetapkan Jadwal**. Ruang yang sudah diisi AKD dalam Excel menjadi pilihan awal apabila tersedia dan dapat diganti. Ruang kosong memakai rekomendasi sistem. Perbedaan istilah opsi1 pada percakapan sudah dijelaskan: implementasi mengikuti alur eksplisit pengguna, sehingga **impor saja belum memesan ruangan**.

Setelah Tetapkan Jadwal berhasil, booking langsung approved tanpa persetujuan tambahan dari petugas ruangan. Sistem memeriksa ketersediaan lagi sebelum menyimpan; admin tidak diam-diam dipindahkan ke kandidat lain jika slot terambil.

## Alur admin/petugas

1. Pastikan Data Akademik → Ruangan berisi ruang kelas aktif dan semester sumber pada Pengaturan Periode Akademik sesuai. Impor jadwal ruang iGracias melalui Sinkronisasi `room_schedule` agar okupansi semester lengkap. Excel plotting peserta tidak menggantikan sumber okupansi tersebut.
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

## Data dan kompatibilitas

- Metadata plotting/hasil/snapshot ditambahkan pada konteks `sidang_ta_schedules` yang sudah ada; audit pelaksanaan terpisah dari empat kejadian riwayat Pendaftaran.
- Pengaturan pencarian baru 90 menit, tanpa jeda istirahat wajib. Migration mengubah setting pencarian dan versi token; occurrence booking lama tidak dihapus/dipendekkan.
- Impor identik tidak menaikkan versi metadata; duplikat NIM dengan isi bertentangan ditolak. Booking/snapshot yang sudah disahkan tidak ditimpa oleh impor ulang. File dibatasi 5.000 baris; file lebih besar dibagi agar sesuai kapasitas server.
- Parser membaca tanggal/jam serial Excel, DateTime dan format teks yang didukung; tidak menjalankan formula. Kepemilikan/prodi untuk otorisasi berasal dari pengajuan DADU, bukan kolom file.
- URL baru memakai UUID pengajuan/periode dari120. Akun/password, data sinkronisasi privat, SSO dan alur peminjaman ruangan biasa dipertahankan.
- Hasil Lulus/Gagal baru tidak mengubah nilai TA, MK mengulang, atau approval pendaftaran secara otomatis. Data gagal lama tidak diciptakan dari dugaan MK mengulang. JSON kehadiran tetap menunggu contoh seperti catatan sebelumnya.

## Pertanyaan, jawaban dan contoh

| Pertanyaan | Keputusan dan contoh pemakaian |
| --- | --- |
| Jika Excel sudah memuat ruang, apakah impor langsung memesan? | Tidak. Sesuai penjelasan terakhir pengguna, admin tetap menetapkan lewat tombol. Contoh Excel 23 November 08.00–09.30 ruang K-01: setelah impor belum ada booking. Pencarian memilih K-01/jam tersebut sebagai rekomendasi jika tersedia; admin dapat memilih K-02 pada 10.00–11.30 lalu menekan Tetapkan Jadwal. |
| Jika kolom ruang kosong? | Tanggal/jam menjadi preferensi; admin mencari ruang dan memeriksa alternatif. Excel bukan bukti bahwa ruangan tersedia. |
| Jika tidak memakai Excel? | Buka peserta approved dari sistem, pilih tanggal dan cari ketersediaan. Pengaturan tidak dipaksa mengunggah Excel karena ada jalur sistem. |
| Apakah perlu persetujuan kedua dari petugas ruangan? | Tidak setelah Tetapkan Jadwal berhasil: booking langsung approved. Untuk peminjaman dosen lama yang masih pending, izin review ruangan tetap diperlukan sebelum disahkan. Peminjaman lama yang sudah ditolak/dibatalkan tetap tersimpan; penetapan berikutnya membuat booking pengganti. |
| Dari mana nama periode dan semester sumber? | Nama mengikuti Kelola Periode Sidang TA, misalnya Ganjil 2026/2027 Periode I. Semester sumber berasal dari Pengaturan Periode Akademik yang dipakai JSON jadwal ruangan `room_schedule`. Periode TA berikutnya mempunyai pengaturan sendiri; periode lama tidak dipakai ulang sebagai identitas periode baru. |
| Apakah mahasiswa/dosen mengatur jadwal? | Mahasiswa hanya membaca hasil; perubahan melalui admin/petugas SSC. Dosen dengan aksi khusus hanya membaca pengajuan prodi yang sesuai. Peminjaman ruangan biasa tetap mengikuti modul tersendiri. |
| Apakah MK TA mengulang membuktikan gagal sidang sebelumnya? | Belum. Riwayat resmi perlu data tanggal, periode dan keputusan yang terkonfirmasi. Status Lulus/Gagal pada fitur ini ditetapkan manual oleh admin dan bisa dibatalkan kembali. |

Hal yang masih perlu dikonfirmasi divisi: sumber riwayat sidang sebelum DADU dipakai (termasuk tanggal/periode dan bukti hasil), aturan hubungan keputusan pelaksanaan dengan nilai akademik resmi, dan apakah pemeriksaan bentrok pembimbing/penguji akan ditambahkan pada tahap berikutnya. Untuk tahap ini jawaban divisi tetap pemeriksaan ruang kosong saja. **Contoh JSON kehadiran masih belum tersedia dan tetap ditunggu**; Excel plotting bukan penggantinya.

## Pengujian, rescan dan rilis

Tes terarah terakhir: **79 lulus, 374 assertion**, termasuk rollback/upgrade schema MySQL pada DB uji, impor tanpa booking, hak akses, konflik, snapshot, hasil/undo serta peminjaman biasa. Build frontend berhasil; syntax PHP pada 29 file lulus dan whitespace diff bersih. Review independen terakhir tidak menemukan masalah penting tersisa. Pengujian hanya pada `dadu_testing`; tidak ada migration/seed/reset pada DB aplikasi maupun deploy VPS.

Suite penuh: **1.275 lulus, 45 gagal, 2 dilewati**, 10.266 assertion, 274,38 detik (1.322 kasus). Perbandingan nama kelas/kasus dengan baseline118 menunjukkan **45 kegagalan yang sama persis, nol kegagalan tambahan**. Suite penuh belum hijau; kegagalan lama tidak disembunyikan. Nama kasus dan bukti rescan tersedia pada [125-verifikasi-pelaksanaan.json](125-verifikasi-pelaksanaan.json). Sesudah suite penuh, penyeragaman label kartu, Export Excel dan label audit diverifikasi ulang: **16 tes UI/akses lulus, 94 assertion**, 16,88 detik. Tidak ada perubahan logika produk setelah suite penuh. Dua kegagalan tambahan pada percobaan pertama (fixture rollback schema dan assertion UI pencarian mahasiswa lama) telah diperbaiki dan tidak muncul pada suite penuh terakhir.

Daftar perubahan aplikasi/tes: [125-daftar-file-pelaksanaan.txt](125-daftar-file-pelaksanaan.txt). Laporan rescan/hash: [125-verifikasi-pelaksanaan.json](125-verifikasi-pelaksanaan.json). **40 file telah disalin dan sama hash-nya di kedua folder.** Dari 750 file aplikasi/tes source yang diperiksa, 745 cocok dengan target; lima adaptasi lama dadu-test tetap berbeda secara sengaja (dua policy dokumen, RolePermissionSeeder, CSS dan antrean admin). View kompatibilitas `resources/views/documents/all.blade.php` tetap khusus target. Tidak ada file aplikasi source yang hilang di target.

Backup target awal: `private-import/124-before/`; backup source sebelum disalin: `private-import/124-source-before/`; manifest: `private-import/124-copy-manifest.json`. HEAD/index kedua repo dan file terlindungi tetap sama. `.gitignore`, `package-lock.json`, `phpunit.xml` yang sebelumnya sudah diubah pengguna pada source tetap dipertahankan. Dokumentasi hanya di dadu-test; source dadu tidak dibuatkan kembali docs/docker. Log/JUnit disimpan privat pada `private-import/dadu120-124-*`; tidak ikut commit.

Tidak melakukan git add, commit, push, deployment, atau migration pada database aplikasi. Migration baru perlu dijalankan oleh operator saat menggunakan/rilis kode ini.

## Migration dan pemakaian kode baru

**Jalankan migration biasa sebelum menggunakan halaman baru.** Migration `2026_11_19_124000_extend_sidang_ta_execution_admin.php` menambah metadata/snapshot/hasil/audit serta aktivasi pelaksanaan. Setting pencarian lama disesuaikan ke 90 menit tanpa memendekkan occurrence yang sudah disahkan. Tetap lakukan backup sebelum rilis seperti alur yang sudah digunakan.

Lokal development, sesudah source diperbarui:

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
php artisan migrate
php artisan optimize:clear
```

Docker/server: commit/push dahulu lalu gunakan alur backup dan **78 bagian 4 — Update Docker**, dengan penyesuaian pengiriman image untuk VPS 1 GB pada [113](113-pemindahan-dadu-test-ke-vps.md). App/image baru harus memuat migration di atas; jalankan migration dari image baru sebelum mengaktifkan app/web kembali. Bagian migration dalam alur tersebut:

```bash
cd /opt/dadu-test
docker compose --env-file .env.docker run -T --interactive=false --rm --no-deps app \
  php artisan migrate --force
```

Bangun/perbarui app dan web sesuai alur rilis; pertahankan volume dan override resource VPS, lalu pastikan `php artisan up` pada tahap akhir. Tidak membutuhkan `migrate:fresh --seed`. Perintah rilis ini referensi operator, belum dijalankan pada aplikasi/server pada sesi ini.

Setelah rilis, buka Form → Pelaksanaan Sidang TA → Kelola Pelaksanaan, periksa semester sumber dan jadwal; kemudian uji satu peserta demo melalui impor/pencarian sampai penetapan, pembacaan mahasiswa, dan pembatalan hasil/jadwal sebelum digunakan pada peserta riil. Booking yang sudah ada tetap memakai waktu tersimpan.


## Penyalinan ulang dari source ke dadu-test

Penyalinan sesi ini memakai backup/hash sebelum dan sesudah; status akhirnya dicatat di laporan rescan. Untuk penyelarasan berikutnya, setelah meninjau perubahan source, gunakan daftar 40 file fitur ini saja:

```bash
cd /home/xdranel/Code/Project/secret/dadu-test
while IFS= read -r dadu_file; do
  mkdir -p -- "$(dirname -- "$dadu_file")"
  cp -p -- "/home/xdranel/Code/Project/php-laravel/dadu/$dadu_file" "$dadu_file"
done < docs/125-daftar-file-pelaksanaan.txt
```

Jangan memakai penyalinan seluruh folder untuk menimpa `.env`, konfigurasi Docker, fixture privat atau adaptasi lain dadu-test. Dokumen125 ini dan laporan/daftar file tetap hanya di dadu-test; source dadu tidak dibuatkan kembali folder docs/docker.
