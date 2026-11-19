# 128 — Hapus Klasifikasi TA, pertahankan status ruang

Status: implementasi, pengujian dan penyelarasan source selesai; belum commit/push/deploy. Migration pada database aplikasi belum dijalankan. Dasar: pengguna memastikan seluruh master dari XLSX adalah ruang kelas dan meminta kolom klasifikasi dihapus.

## Perubahan dan batasnya

- Kolom Klasifikasi TA di daftar Ruangan serta input kelas/bukan kelas pada Tambah/Edit dihapus.
- Model dan request tidak lagi memakai is_classroom. Form lama yang masih terbuka boleh mengirim field itu; field tambahan diabaikan, status dan data master tetap divalidasi seperti biasa.
- Seluruh record master dianggap kelas. Pencarian TA admin/legacy, pilihan koreksi, tautan peminjaman dan impor plotting hanya memakai status **Dapat Dipakai**, jadwal semester, rentang pelaksanaan dan pemeriksaan bentrok. **Tidak Dapat Dipakai tetap dikecualikan**, sekalipun kosong.
- Tidak ada UPDATE status pada migration. Tidak mengaktifkan semua ruang, mengganti nama/kode/ID, atau mengubah alias, jadwal, booking dan riwayat.
- Format XLSX tetap memakai kolom kode/nama/gedung/kapasitas/status yang sama. Pengguna tidak perlu menambah kolom klasifikasi atau menandai 60 ruang secara manual.
- Persoalan alias iGracias yang ditemukan127 tetap terpisah. Penghapusan klasifikasi tidak menjadikan snapshot semester yang belum berhasil diimpor sebagai sumber lengkap.

## Penghapusan database pada instalasi yang sudah ter-deploy

Migration baru: `2026_11_19_128000_remove_room_ta_classification.php`.

`up()` menghapus index/kolom is_classroom saja. Migration lama110003 **tidak diedit** dan tidak di-rollback. Migration tersebut juga mencakup relasi booking TA, sehingga jangan membatalkannya hanya untuk menghapus klasifikasi.

`down()` mengembalikan kolom kompatibilitas nullable dengan default true. Nilai penandaan lama yang sudah dihapus tidak dipulihkan per ruang; seluruh master mengikuti keputusan baru sebagai kelas. Status aktif/nonaktif dan riwayat tetap sama. Backup sebelum rilis tetap menjadi sumber jika suatu saat perlu menelusuri metadata lama.

Kode baru dapat membaca skema lama yang masih mempunyai kolom ekstra, tetapi kode lama mempunyai query terhadap kolom itu. Karena itu lakukan pergantian kode/schema dalam maintenance sesuai alur rilis, supaya kode lama tidak melayani pencarian setelah kolom dihapus.

## Langkah operator

### Lokal development

Setelah kode baru tersedia, gunakan migration biasa. Tidak memakai fresh/seed.

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
php artisan down --retry=60
php artisan migrate
php artisan config:clear
php artisan view:clear
php artisan up
```

Jika migration gagal, periksa penyebab dan status schema sebelum membuka aplikasi. Perintah ini referensi operator; tidak dijalankan pada DB aplikasi selama sesi implementasi.

### Docker dadu-test/VPS

Commit/push terlebih dahulu. Gunakan [alur update lokal/Docker/VPS](../panduan-pemakaian/update-lokal-docker-vps.md): backup, maintenance, hentikan worker/scheduler, gunakan image baru, jalankan migration dari image baru, recreate app/web lalu up dan mulai ulang proses latar. Pertahankan volume serta override VPS 1 GB.

Bagian migration pada alur tersebut:

```bash
cd /opt/dadu-test
docker compose --env-file .env.docker run -T --interactive=false --rm --no-deps app \
  php artisan migrate --force
```

Jangan menjalankan potongan itu dengan image lama. Jangan menjalankan `docker compose down -v`, `migrate:fresh`, atau menjalankan ulang seeder role. Pada VPS 1 GB tetap ikuti alur image yang dibangun di komputer dengan resource cukup.

### Production PHP-FPM

Koordinasikan dengan pengelola deployment. Sesudah backup dan maintenance, pasang kode baru dan jalankan `php artisan migrate --force` dari release baru, muat ulang cache/proses PHP sesuai prosedur server, lalu buka maintenance setelah verifikasi. Jangan menjalankan rollback110003 atau SQL penghapusan tabel secara manual. Sesi ini tidak mengakses atau mengubah production.

## Pengujian dan perlindungan data

Tiga kasus baru teramati gagal sebelum implementasi. Sesudah perubahan, **3 tes baru lulus, 30 assertion**, termasuk native MySQL yang menyiapkan enam room dengan kombinasi status aktif/nonaktif dan flag lama true/false/null, alias, booking pending/approved, occurrence dan history. Setelah up/down/up, seluruh field selain flag tetap sama.

Regresi pertama:117 lulus, 2 skip dan1 gagal assertion urutan array snapshot (nilai sama, urutan hasil model in-memory berbeda dari hasil baca database). Tes diselaraskan untuk membandingkan snapshot database sebelum/sesudah; seluruh 12 tes settings/alias lulus sesudahnya. Suite penuh akhir: **1.287 lulus, 45 kegagalan baseline yang sama, 2 dilewati**, 10.394 assertion, 352, 75 detik. Tidak ada kegagalan tambahan dibanding127. Seluruh kasus modul terkait pada suite penuh lulus setelah koreksi snapshot tes; suite penuh tidak diklaim hijau.

Fixture umum tetap mempertahankan status keempat room uji. Skenario penolakan/race menyiapkan room uji nonaktif secara eksplisit; kode aplikasi/migration tidak mengubah status untuk memaksa pencarian berhasil. Fixture upgrade schema diurutkan dari migration terbaru128, 124, lalu110; jalur fresh dan upgrade tetap diperiksa.

Pengujian hanya MySQL dadu_testing. Tidak ada migration/reset/seed pada DB aplikasi lokal, VPS atau production; tidak commit/push/deploy.

## Rujukan

- [Rencana128](../catatan/128-rencana-penghapusan-klasifikasi-ta.md)
- [Panduan ruangan terkini](../panduan-pemakaian/peminjaman-dan-sumber-jadwal-ruangan.md)
- [Pemetaan alias yang masih perlu konfirmasi](../catatan/127-pemetaan-label-ruangan.md)

JSON kehadiran tetap menunggu contoh dan tidak berkaitan dengan penghapusan kolom ini.

## File perubahan

Daftar 26 file kode/tes/migration yang diselaraskan:

- `app/Http/Controllers/Admin/SidangTaBookingController.php`
- `app/Http/Controllers/Admin/SidangTaExecutionController.php`
- `app/Http/Requests/StoreRoomRequest.php`
- `app/Http/Requests/UpdateRoomRequest.php`
- `app/Models/Room.php`
- `app/Services/SidangTaBookingAdministration.php`
- `app/Services/SidangTaBookingService.php`
- `app/Services/SidangTaPlottingImportService.php`
- `app/Services/SidangTaStaffScheduling.php`
- `database/migrations/2026_11_19_128000_remove_room_ta_classification.php`
- `resources/views/rooms/create.blade.php`
- `resources/views/rooms/edit.blade.php`
- `resources/views/rooms/index.blade.php`
- `resources/views/sync/index.blade.php`
- `tests/Feature/RoomClassificationRemovalMysqlTest.php`
- `tests/Feature/RoomMasterEligibilityTest.php`
- `tests/Feature/RoomSchedulingDiagnosticsTest.php`
- `tests/Feature/SidangTaBookingConcurrencyTest.php`
- `tests/Feature/SidangTaBookingLifecycleTest.php`
- `tests/Feature/SidangTaExecutionDomainTest.php`
- `tests/Feature/SidangTaExecutionSettingsTest.php`
- `tests/Feature/SidangTaLecturerBookingLinkTest.php`
- `tests/Feature/SidangTaRoomMysqlUpgradeTest.php`
- `tests/Feature/SidangTaRoomSchemaCompatibilityTest.php`
- `tests/Feature/SidangTaStaffSchedulingTest.php`
- `tests/Support/TaRoomScenario.php`

Seluruh **26 file** di atas sudah disamakan ke dadu dengan backup/hash. Backup awal target: private-import/128-before/; backup source: private-import/128-source-before/; manifest: private-import/128-copy-manifest.json. Dari 756 file aplikasi/tes source, 751 cocok dan 5 adaptasi lama tetap berbeda secara sengaja (dua policy dokumen, RolePermissionSeeder, CSS dan antrean admin). View documents/all tetap khusus target; tidak ada file source yang hilang pada target.

Syntax seluruh 26 file dan whitespace di kedua repo lulus. Tidak ada lagi referensi aktif is_classroom pada app/resources; referensi tersisa memang migration historis, migration penghapusan dan tes kompatibilitas. Migration110003 tetap sama byte-nya. Environment, dependency, staging dan konfigurasi terlindungi dipertahankan; source tidak dibuatkan docs/docker.

Reproduksi XLSX asli pada schema baru: **1 tes lulus, 10 assertion**, 16, 07 detik. Hasilnya tetap **60 ruang:46 Dapat Dipakai dan14 Tidak Dapat Dipakai**, tanpa kolom klasifikasi. Impor JSON mingguan masih ditolak karena alias yang belum pasti; tidak dipaksa menjadi sumber lengkap. Sumber asli tidak ditulis ulang.

Log/JUnit/raw rescan disimpan privat pada private-import/dadu120-128-* dan private-import/128-*. Tidak membuat laporan JSON/TXT baru di docs. JS/CSS tidak berubah; build frontend tidak diulang untuk perubahan ini. Command salin ulang ada pada [panduan penyelarasan](../panduan-pemakaian/penyelarasan-file.md).
