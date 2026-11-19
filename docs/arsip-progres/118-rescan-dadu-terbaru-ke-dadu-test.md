# 118 — Sinkronisasi source tugasAkhir terbaru ke dadu-test

> Arsip progres: keputusan dan perintah di sini mencerminkan tahap saat ditulis. Untuk pemakaian terkini buka [panduan](../panduan-pemakaian/README.md); hasil singkat tersedia di [kesimpulan](../kesimpulan/README.md).

## Permintaan dan keputusan

Pengguna menjalankan fetch/pull pada source `/home/xdranel/Code/Project/php-laravel/dadu`, lalu meminta scan seluruh perubahan dan penyalinan ke `/home/xdranel/Code/Project/secret/dadu-test`.

Keputusan terbaru pengguna: **Pelaksanaan Sidang TA mahasiswa dibuka kembali pada kedua folder**. Ini menggantikan penutupan117 dan jawaban sementara yang hanya membuka dadu-test.

Rescan awal: source HEAD `45bbe25`, target HEAD `daf8a99`;768 file aplikasi tracked diperiksa,629 identik dan139 file baru/berbeda. Terdapat1 view lama hanya pada target (`resources/views/documents/all.blade.php`) yang dihapus source terbaru. Source mempunyai perubahan lokal `.gitignore`, `package-lock.json`, dan `phpunit.xml`; ketiganya dipertahankan.

## Rencana kerja

1. Catat hash awal, status Git, perubahan commit source, daftar file salinan dan batas file yang dilindungi.
2. Audit terpisah kompatibilitas Docker: storage privat, batas upload, migration, seeder, konfigurasi permission dan dependency.
3. Ambil baseline tes target memakai dependency yang sama dan database khusus `dadu_testing`; jangan mengakses database aplikasi/VPS.
4. Buka booking mahasiswa pada kedua folder. Salin source aplikasi, test dan aset yang berubah ke target setelah backup, dengan pemeriksaan hash untuk menolak perubahan bersamaan. Gabungkan kebutuhan seeder Docker tanpa menghapus fitur antrean/dokumen baru.
5. Validasi syntax, route/config, tes modul yang berubah, build frontend dan kompatibilitas image Docker; bedakan kegagalan baseline/upstream dari masalah penyalinan.
6. Rescan sesudahnya, simpan manifest/hash/backup dan langkah deployment untuk VPS. Tidak melakukan commit/push/deploy maupun menjalankan backfill/migrasi pada database live.

## Batas dan perlindungan

Source aplikasi menjadi acuan untuk perubahan dokumen, antrean, SSO, dosen dan ekspor. Infrastruktur `Dockerfile`, `compose.yaml`, skrip Docker, environment, password, backup, storage dan data JSON privat target dipertahankan kecuali penyesuaian infrastruktur yang diperlukan agar fitur source baru dapat berjalan.

Tidak menyalin `.env`, `.git`, vendor/node_modules source sebagai perubahan Git, storage runtime, berkas upload, atau folder data sinkronisasi privat. `phpunit.xml` target tetap menyasar testing; konfigurasi database development source tidak disalin. Folder docs/docker source tetap tidak dibuat kembali.

Dependency composer/package/lock kedua folder identik saat scan; salinan dependency lokal yang di-ignore disiapkan hanya untuk verifikasi target. `.env.testing` target memakai key baru khusus tes, tidak memakai key/password aplikasi.

## Temuan audit awal

- Source menambah modul impor dokumen massal, access group/NIM dokumen, disk privat, catatan petugas antrean, penyesuaian SSO dan field dosen phone/COE/KK.
- Ekspor TA terbaru pada commit45bbe25 memakai **kode dosen** untuk Pembimbing1/2 dan field **KK tersendiri** pada Data Dosen. Ini menggantikan pemetaan `academic_interest` pada checkpoint116, mengikuti source terbaru yang diminta disalin.
- Volume storage Docker yang ada sudah mencakup `storage/app/private` untuk app/worker. Direktori privat perlu disiapkan dengan ownership aplikasi.
- Batas upload Docker lama12MB/file,192MB/request dan20 file tidak cocok dengan impor baru (Excel20MB, berkas15MB, ZIP512MB, hingga500 berkas). Penyesuaian PHP/Nginx perlu selaras; limit Sidang TA tetap mengikuti validasi aplikasinya sendiri.
- Seeder source menambah queue force_release/room_booking, tetapi belum membawa default akses prodi TA milik Docker. Varian target harus mempertahankan `view_program` dan batas Pelaksana untuk TA sembari menerima modul baru. Seeder tidak dijalankan pada akun live saat update.
- Migration SSO baru mengubah sebagian akun pegawai SSO dari Petugas ke Pelaksana berdasarkan kriteria pada source; akun manual demo dikecualikan. Migration lain menambah kolom dokumen/antrean/dosen. Dampak deployment dicatat, bukan dijalankan pada VPS dalam tugas penyalinan.

## Pelaksanaan dan verifikasi

### Hasil penyalinan

Selesai menulis **148 file** pada dadu-test: 143 salinan aplikasi/tes dari source, 4 penyesuaian infrastruktur Docker, dan 1 view kompatibilitas. Daftar lengkap: [118-daftar-file-salin.txt](../catatan/bukti-verifikasi-historis.md#118-daftar-file-salin-txt). Hash sebelum/sesudah, pengecualian dan hasil tes: [118-rescan-hasil.json](../catatan/bukti-verifikasi-historis.md#118-rescan-hasil-json).

Rescan akhir memeriksa 761 file tracked dalam direktori aplikasi `app`, `bootstrap`, `config`, `database`, `public`, `resources`, `routes`, dan `tests`: **756 identik, 5 berbeda dengan sengaja, tidak ada file source yang tertinggal**. Angka 768 pada scan awal juga menyertakan berkas root. Lima pengecualian akhir:

- `database/seeders/RolePermissionSeeder.php`: mempertahankan permission khusus `sidang_ta_registration_view_program` dan default Pelaksana hanya read/view_program pada TA; menerima perubahan antrean dan peminjaman ruangan dari source. Seeder tidak dijalankan terhadap akun live.
- `app/Policies/DocumentCategoryPolicy.php`, `app/Policies/DocumentPolicy.php`, `resources/css/app.css`, `resources/views/queue-tickets/admin.blade.php`: hanya membersihkan trailing whitespace/baris kosong akhir source agar `git diff --check` bersih.

Enam belas hash berkas target yang dilindungi tetap sama, termasuk compose, skrip akun demo, konfigurasi pengujian, lock dependency dan catatan pengguna. Environment, password akun, JSON privat, storage dan database aplikasi/VPS tidak diubah. Source `docs/` dan `docker/` tetap tidak dibuat kembali.

### Booking mahasiswa dibuka pada kedua folder

Empat file diperbarui pada source lalu disamakan ke target:

1. `config/sidang_ta.php`: `student_booking_enabled` menjadi `true`.
2. `tests/Feature/SidangTaStudentBookingVisibilityTest.php`: mode tertutup mengatur `false` secara eksplisit, tetap memeriksa larangan URL/payload ketika fitur ditutup.
3. `tests/Feature/SidangTaBookingUiTest.php` dan `tests/Feature/SidangTaRoomBookingTest.php`: memakai default konfigurasi aktif aplikasi.

Persyaratan persetujuan TA dan aturan booking yang sudah ada tetap berlaku. Ini menggantikan status default tertutup pada catatan117.

### Kompatibilitas dokumen dan Docker

Source menghapus `resources/views/documents/all.blade.php`, tetapi `DocumentController::flatIndex()` masih memanggilnya saat `?view=all`. View target dipertahankan agar jalur tersebut dapat dibuka. Kontrol Alpine untuk detail, rename, pindah, visibilitas dan hapus diselaraskan dengan partial baru; modal berada dalam lingkup Alpine yang sama. Perbaikan ini khusus target; view tidak dibuat kembali pada source.

| File Docker | Penyesuaian |
| --- | --- |
| `Dockerfile` | Menyiapkan `storage/app/private` milik www-data. |
| `docker/scripts/entrypoint.sh` | Menyiapkan dan memperbaiki ownership direktori privat saat container mulai. |
| `docker/php/app.ini` | `upload_max_filesize=512M`, `post_max_size=544M`, `max_file_uploads=502`. |
| `docker/nginx/default.conf` | `client_max_body_size 544m`, selaras dengan request PHP. |

Validasi unggahan masing-masing fitur tetap berlaku: limit infrastruktur mengakomodasi impor dokumen baru; batas aplikasi Sidang TA tetap 5 MB/berkas. Memory override VPS dipertahankan.

Batas origin tersebut belum menjamin ZIP 512 MB bisa diunggah lewat hostname Cloudflare. Free/Pro membatasi request sampai 100 MB, Business 200 MB; konfigurasi zona bisa lebih rendah. Periksa paket aktual saat memakai impor besar. Tidak mengubah DNS/paket Cloudflare dalam sesi ini. Referensi yang diperiksa melalui Context7: [Cloudflare 413](https://developers.cloudflare.com/support/troubleshooting/http-status-codes/4xx-client-error/error-413/) dan [batas unggahan PHP](https://www.php.net/manual/en/features.file-upload.common-pitfalls.php). Belum dilakukan uji beban ZIP 512 MB pada VPS 1 GB.

### Pengujian

Seluruh tes database dijalankan pada **`dadu_testing`**, bukan database aplikasi. Dependency dan key baru khusus tes disiapkan lokal; key kemudian disimpan privat di `private-import/118-testing.env` agar tidak ikut commit.

| Pemeriksaan | Hasil |
| --- | --- |
| Suite sebelum penyalinan | 1.105 lulus, 43 gagal, 2 dilewati. |
| Suite sesudah penyalinan | 1.213 lulus, 45 gagal, 2 dilewati. |
| Tes terarah TA/booking/ekspor, akun demo, dokumen, SSO | 88 lulus, 615 assertion. |
| Dua tes HTTP view Semua Dokumen | 2 lulus, 7 assertion; halaman 200 dan tanpa permission tetap 403. |
| Smoke JavaScript hasil render view | Lulus: selection, rename/detail, pilihan folder pindah, PUT pindah, PATCH visibilitas, modal hapus. |
| Syntax PHP file salinan non-Blade | 98 file lulus. |
| `git diff --check` | Bersih. |
| Build frontend terakhir | Berhasil. |
| Image app/web lokal | Keduanya berhasil dibangun dengan Podman. |
| Container sementara | PHP 8.4.27; `php-fpm -t` dan `nginx -t` lulus; limit upload sesuai tabel; private storage writable sebagai www-data. |

Suite penuh belum hijau. Perbandingan nama tes menunjukkan **41 kegagalan tetap dari baseline**, 2 kegagalan portal dokumen lama selesai, dan **4 kegagalan tambahan juga direproduksi langsung pada source hasil pull**:

1. `PreAssignRoleTest`: label lama “Username SSO / NIP Pegawai” tidak cocok dengan halaman terbaru.
2. `PreAssignRoleTest`: tes mengharapkan nama SSO huruf kapital, hasil terbaru memakai kapitalisasi nama biasa.
3. `PublicDocumentTest`: ekspektasi 404, tetapi middleware permission terbaru mengembalikan 403 bagi pengguna tanpa akses portal publik.
4. `QueueHistoryStaffNotesTest`: fixture memasukkan record arsip MySQL tanpa `id` wajib, mendapat SQLSTATE 1364.

Reproduksi source: 10 lulus dan 4 gagal. Daftar lengkap kegagalan tersedia di JSON hasil rescan tanpa HTML/log sensitif. Tes/perilaku upstream tersebut tidak diubah untuk menghilangkan kegagalan. Suite penuh dijalankan sebelum penyesuaian akhir view kompatibilitas dan whitespace; sesudahnya dilakukan 88 tes terarah, 2 tes HTTP, smoke JavaScript dan build terakhir. Container verifikasi tidak memakai volume/database aplikasi dan tidak membuka port publik.

### Backup dan batas pekerjaan

- Backup source sebelum pembukaan fitur: `private-import/118-backup/source/`.
- Backup file target yang ditimpa: `private-import/118-backup/target/`.
- Stage dan manifest: `private-import/118-stage/`, `private-import/118-apply-manifest.json`.
- Log/JUnit tes dan build disalin ke `private-import/118-*.log` dan `private-import/118-*.xml`.
- Tidak melakukan commit/push, perubahan VPS, reset database, seed live, migrasi/backfill data live.

## Saat akan merilis ke server

Tinjau dan commit perubahan aplikasi/infrastruktur pada repo masing-masing. Konfigurasi pembukaan booking juga perlu ikut commit source. Dependency lokal, environment tes dan backup privat tidak termasuk perubahan yang dipublikasikan.

Ada **13 migration baru yang ikut disalin**. Update server memerlukan migration biasa (`migrate --force` dalam alur update), **bukan `migrate:fresh --seed`**. Migration `2026_11_10_000001_migrate_sso_pegawai_petugas_to_pelaksana.php` memindahkan pegawai SSO kampus yang memenuhi kriterianya dari Petugas ke Pelaksana; akun manual dan mahasiswa dikecualikan. Ini perubahan data dari source terbaru; periksa kembali akses akun SSO yang terdampak setelah rilis.

Build ulang **app dan web**, karena PHP, aset dan Nginx berubah. Ikuti backup/maintenance/migration/recreate pada [78 bagian 4](78-hasil-implementasi-meeting-mahasiswa-petugas-it-panel.md), dengan penyesuaian build di luar VPS 1 GB pada [113](113-pemindahan-dadu-test-ke-vps.md). Pertahankan override resource, `.env.docker`, roster dan volume VPS. Tangani working tree server yang masih kotor sebelum pull.

Sesudah migration dan aplikasi baru siap, audit dokumen lama secara terpisah jika dibutuhkan. Berikut **hanya simulasi**; belum dijalankan pada server dalam sesi ini:

```bash
cd /opt/dadu-test
docker compose --env-file .env.docker exec -T --user www-data app \
  php artisan documents:backfill-access-group --dry-run
docker compose --env-file .env.docker exec -T --user www-data app \
  php artisan documents:migrate-storage --dry-run --limit=20
```

Tinjau hasil simulasi sebelum menentukan migrasi dokumen lama. Tidak menjalankan cleanup atau mengubah permission akun secara massal dalam pekerjaan penyalinan ini.
