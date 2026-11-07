# 75 — Reset demo lokal/Docker dan urutan impor

Panduan ini untuk dua lingkungan: development asli `/home/xdranel/Code/Project/php-laravel/dadu` dan Docker staging `/opt/dadu-test`. Reset database aplikasi dan impor melalui UI dijalankan pengguna; persiapan ini tidak menjalankan reset pada keduanya.

## 1. Hasil sesudah migrate:fresh --seed

| Lingkungan | Akun tetap | Akun tambahan | Total User |
| --- | --- | --- | --- |
| Lokal, APP_ENV=local | `superadmin@example.test`, `krisna@mahasiswa.test`; password keduanya `password` | Tidak dibuat | 2 |
| Docker, APP_ENV=staging | Dua email yang sama; password tetap dari `DADU_TEST_PASSWORD` server | 11 akun blackbox; password `password` | 13 |

Nama akun blackbox menggunakan nama lengkap huruf kecil. Email menggunakan nama tersebut tanpa spasi, lalu `@mahasiswa.test`. Daftar lengkap nama/email/NIM tersedia di README fixture privat. Krisna tetap memakai NIM demo `1202200001`; Krisna dalam dataset blackbox merupakan mahasiswa berbeda.

Superadmin mempunyai role Akademik. Krisna tetap mempunyai role mahasiswa dan Petugas. Sebelas akun tambahan mempunyai role mahasiswa. Akun `dummy-ta-2@mahasiswa.test` tidak dibuat oleh seeder default yang baru.

`DatabaseSeeder` sekarang memakai `DemoAccountSeeder`, bukan `UserSeeder` atau `SidangTaTesterSeeder`. Menjalankan seeder tanpa fresh tidak menghapus akun lama. Hanya reset database yang menghasilkan jumlah akun persis pada tabel. Seeder juga membuat periode akademik Ganjil 2026/2027 pada database baru; periode pendaftaran Sidang TA tetap perlu dibuat/dibuka melalui aplikasi.

Impor Mahasiswa membuat Student, bukan User login. Karena itu lokal tetap mempunyai dua akun meskipun fixture impor berisi 12 mahasiswa.

## 2. Berkas sumber dan fixture privat

Lokasi hasil persiapan di development asli:

```text
docs/fixtures/sidang-ta/data_dadu/
  README.md
  manifest.json
  blackbox-accounts.json
  sumber/                         # tujuh JSON sumber, disalin persis
  impor/
    75-01-mahasiswa-demo.json
    75-02-krs-demo-sumber.json
    75-02b-krs-demo-skenario-ta.json
    75-03-profil-akademik-demo.json
    75-04-bpp-demo.json
    75-05-bahasa-demo.json
```

Folder `sumber` menyimpan SEEDER 11 mahasiswa, MATRICS 11 mahasiswa, rekap awal, KRS, UPDT TAK & SKPI, FINANCE UPDATE, dan NILAI BAHASA UPDATE 7 November. Salinan diverifikasi dengan SHA-256 di manifest. Berkas Downloads tetap tersedia. Folder privat diabaikan Git; ekspor identitas tidak masuk commit/image Docker.

`MATRICS_BLACKBOX_11_MAHASISWA.json` menjadi acuan program studi dan skenario; SEEDER menjadi acuan identitas dan nilai profil akademik. Roster setup hanya membawa 11 identitas dan semester studi, tanpa password server. Empat ekspor umum berisi 277 mahasiswa tidak mencakup 11 NIM blackbox, sehingga tidak menggantikan profil/BPP skenario mereka.

KRS sumber untuk 11 mahasiswa berisi 83 mata kuliah dan belum mempunyai mata kuliah TA yang diterima sistem. Dua fixture KRS disediakan:

| Fixture | Isi | Tujuan |
| --- | --- | --- |
| `75-02-krs-demo-sumber.json` | 83 KRS sumber + 1 TA sintetis untuk Krisna tetap | Memeriksa perilaku KRS sumber; 11 mahasiswa masih terhalang syarat KRS TA |
| `75-02b-krs-demo-skenario-ta.json` | Seluruh isi di atas + 11 TA sintetis; total 95 | Menguji syarat akademik/bahasa/TAK/SKPI/persetujuan pada matriks |

Nama satu mahasiswa di KRS turunan diselaraskan dengan identitas SEEDER/matriks. KRS sumber dalam `sumber` tidak diubah. Profil demo dan BPP demo merupakan data skenario; periode BPP `2627/1` ditetapkan khusus pengujian dan bukan bukti pembayaran nyata. Nilai statusnya berasal dari SEEDER, bukan ekspor FINANCE UPDATE untuk cohort lain.

Untuk membuat ulang fixture, jalankan dari repo Docker di laptop; pilih folder output baru atau folder hasil yang memang hendak diperbarui:

```bash
cd /home/xdranel/Code/Project/secret/dadu-test
php docker/scripts/prepare-demo-imports.php \
  --source=/home/xdranel/Downloads/data_dadu \
  --language-source="/home/xdranel/Downloads/data_dadu/rekap_igracias_2026-11-07 NILAI BAHASA UPDATE.json" \
  --output=/tmp/dadu-demo75-private
```

Generator hanya membaca/menulis berkas; tidak membuka database. Periksa manifest sebelum mengganti fixture privat yang sudah digunakan. Opsi `--language-source` opsional agar enam sumber lama tetap didukung. Dengan opsi tersebut, manifest mencatat tujuh sumber. Sumber bahasa baru berisi 277 mahasiswa dan tidak mencakup 11 identitas blackbox; tanggal asli mereka tidak ditempelkan pada mahasiswa dummy. `75-05` menggunakan tanggal sintetis yang ditandai eksplisit, sambil mempertahankan nilai, jumlah tes, jenis, dan status skenario.

## 3. Reset development lokal

Pastikan kode seeder baru sudah ada pada repo development asli. `.env` harus menunjuk database development yang memang ingin direset, dengan `APP_ENV=local`. Jangan menyalin konfigurasi `dadu_testing` dari command unit test ke `.env` aplikasi.

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
php artisan config:clear
php artisan env
php artisan migrate:fresh --seed
```

Command fresh menghapus tabel pada database yang dikonfigurasi. Setelah selesai, login dengan dua akun pada bagian 1, password `password`. Lanjutkan impor bagian 5. Jangan menjalankan `UserSeeder`/`SidangTaTesterSeeder` tambahan karena keduanya merupakan seeder lama dan dapat menambah akun yang tidak diminta.

## 4. Reset Docker dadu-test

### 4.1. Kirim roster privat dari laptop

Kode/Compose baru harus sudah dikirim ke `/opt/dadu-test` melalui alur deployment biasa. Build mengambil berkas pada server; build tidak mengirim perubahan dari laptop. Jika belum memakai commit/pull yang memuat perubahan75, salin kelima berkas berikut **dari repo Docker di laptop**, karena skrip setup/Compose merupakan bagian repo ini:

```bash
cd /home/xdranel/Code/Project/secret/dadu-test
scp compose.yaml xdranel@100.100.208.106:/opt/dadu-test/
scp docker/scripts/seed-test-accounts.php xdranel@100.100.208.106:/opt/dadu-test/docker/scripts/
scp app/Support/DemoAccountPlan.php xdranel@100.100.208.106:/opt/dadu-test/app/Support/
scp database/seeders/DemoAccountSeeder.php database/seeders/DatabaseSeeder.php \
  xdranel@100.100.208.106:/opt/dadu-test/database/seeders/
```

Ini melengkapi pengiriman kode aplikasi KRS/BPP yang telah dilakukan sebelumnya, bukan menggantikan pengiriman seluruh aplikasi bila server belum diperbarui. JSON privat tidak ikut Git sehingga perlu dikirim terpisah. Dari laptop:

```bash
ssh xdranel@100.100.208.106 'mkdir -p /opt/dadu-test/private-import'
scp /home/xdranel/Code/Project/php-laravel/dadu/docs/fixtures/sidang-ta/data_dadu/blackbox-accounts.json \
  xdranel@100.100.208.106:/opt/dadu-test/private-import/blackbox-accounts.json
```

Di server, beri izin baca pada **roster ringkas ini saja**, karena CLI setup berjalan sebagai www-data di container. Generator lokal membuat berkas 0600; tanpa langkah ini setup dapat gagal membaca roster milik user host. Berkas ini tidak berisi password dan hanya di-mount read-only pada service setup. Ekspor sumber lengkap tidak perlu diunggah ke server.

```bash
cd /opt/dadu-test
chmod 0644 private-import/blackbox-accounts.json
```

Gunakan `.env.docker` yang sudah ada. Jangan menjalankan initializer ulang atau mengganti `DADU_TEST_PASSWORD`/APP_KEY. Jika sebelumnya mengisi `DADU_BLACKBOX_JSON_PATH`, pastikan menunjuk berkas roster yang baru; tanpa override, Compose memakai lokasi di atas.

### 4.2. Build dan periksa sebelum menghapus tabel

```bash
cd /opt/dadu-test
docker compose --env-file .env.docker config --quiet
COMPOSE_PARALLEL_LIMIT=1 docker compose --env-file .env.docker build app
COMPOSE_PARALLEL_LIMIT=1 docker compose --env-file .env.docker build web
docker compose --env-file .env.docker up -d --wait db
```

Pastikan kedua build selesai sukses. Sebelum menjalankan skrip, bandingkan versi host dan image dengan pemeriksaan berkas saja; override entrypoint ini tidak menjalankan bootstrap Laravel/seeder:

```bash
sha256sum docker/scripts/seed-test-accounts.php
docker compose --env-file .env.docker run -T --interactive=false --rm --no-deps --pull never \
  --entrypoint sha256sum setup /var/www/html/docker/scripts/seed-test-accounts.php
```

Untuk versi75 saat panduan ini diperbarui, kedua hash harus `f3a1cab01df25c8ea36ff51bef2e888889c5084f013da5250e718fa14e90931e`. Jika host berbeda, berkas server belum sesuai. Jika host benar tetapi image berbeda, periksa kegagalan build, Compose/build context atau tag image yang dipakai setup; jangan lanjut ke skrip sampai sesuai. Setelah itu:

```bash
docker compose --env-file .env.docker run -T --interactive=false --rm --no-deps setup \
  php docker/scripts/seed-test-accounts.php --check
```

Lanjutkan hanya bila keluar `Preflight OK: 13 akun staging`. Pemeriksaan ini tidak membuat akun atau mengubah database; ia memastikan roster terbaca/valid dan password dua akun tetap tersedia. Pemeriksaan di DatabaseSeeder sendiri terlambat untuk melindungi tabel dari `migrate:fresh`, sehingga preflight terpisah ini harus dilakukan **sebelum** fresh.

Jika output justru `Prepared superadmin...`, `Prepared krisna...`, `Prepared dummy-ta-2...` dan `Test accounts are ready`, image masih menjalankan skrip setup lama. Skrip lama mengabaikan argumen `--check` dan menjalankan bootstrap tiga akun, termasuk perubahan role/password/settings. Output itu tidak membuktikan fresh pernah dijalankan. Hentikan langkah reset; perbarui kelima berkas dan ulangi build serta pemeriksaan hash di atas.

### 4.3. Backup, fresh/seed, dan hidupkan aplikasi

Jalankan setelah preflight berhasil dan tidak ada penguji yang sedang memakai aplikasi. Blok ini berhenti jika ada command gagal; jangan langsung melompati command yang gagal.

```bash
bash <<'RESET_DADU'
set -euo pipefail
cd /opt/dadu-test
docker compose --env-file .env.docker exec -T --interactive=false --user www-data app php artisan down --retry=60
docker compose --env-file .env.docker stop scheduler worker
bash docker/scripts/backup.sh </dev/null
docker compose --env-file .env.docker run -T --interactive=false --rm --no-deps setup \
  php artisan migrate:fresh --seed --force
docker compose --env-file .env.docker up -d --force-recreate --wait app web
docker compose --env-file .env.docker exec -T --interactive=false --user www-data app php artisan up
docker compose --env-file .env.docker up -d --force-recreate scheduler worker
curl --max-time 10 -sS -o /dev/null -w 'LOGIN HTTP %{http_code}\n' http://127.0.0.1:8091/login
curl --max-time 15 -sS -o /dev/null -w 'PUBLIC LOGIN HTTP %{http_code}\n' https://dadu-test.gendhiramona.site/login
RESET_DADU
```

Fresh harus berjalan melalui service **setup** yang menerima password dan bind roster. Service app biasa tidak menerima keduanya. Seeder menghasilkan 13 akun, mempertahankan password dua akun tetap dari env, dan memakai `password` untuk 11 akun blackbox. Reset ini menghapus tabel aplikasi; volume storage tetap ada. Jangan memakai `down -v`.

Jika blok berhenti setelah `artisan down`, aplikasi dapat tetap 503. Setelah masalahnya ditangani, hidupkan kembali:

```bash
docker compose --env-file .env.docker exec -T --interactive=false --user www-data app php artisan up
curl --max-time 10 -sS -o /dev/null -w 'LOGIN HTTP %{http_code}\n' http://127.0.0.1:8091/login
```

Untuk bootstrap ulang akun tanpa fresh, gunakan setup biasa setelah preflight. Itu memperbarui akun/role/settings sesuai seeder dan tidak menghapus user lama. Jangan menjalankannya sebagai rutinitas setiap deployment.

## 5. Urutan impor pada kedua lingkungan

Login superadmin, buka **Sinkronisasi Data → Import JSON**, lalu pilih berkas pada folder privat `impor` di laptop. Gunakan urutan yang sama di development lokal dan website Docker:

| Urutan | Berkas | Tipe impor | Hasil yang diharapkan |
| --- | --- | --- | --- |
| 1 | `75-01-mahasiswa-demo.json` | Mahasiswa | 12 Student, program studi/email/semester studi konsisten |
| 2 | `75-02b-krs-demo-skenario-ta.json` | KRS | 12 mahasiswa, 95 KRS pada Ganjil 2026/2027 |
| 3 | `75-03-profil-akademik-demo.json` | Profil Akademik | Profil akademik, bahasa, TAK, SKPI, persetujuan dan status BPP skenario |
| 4 | `75-04-bpp-demo.json` | Profil Akademik | Status BPP yang sama dengan konteks periode `2627/1` eksplisit |
| 5 | `75-05-bahasa-demo.json` | Profil Akademik | Bahasa saja, termasuk tanggal tes dummy; BPP dan grup lain dipertahankan |

Gunakan pilihan tipe **Profil Akademik** yang tersedia di UI untuk tiga berkas terakhir; `semester` di profil adalah semester studi, sedangkan `periode_keuangan` adalah periode BPP. Jangan mengimpor SEEDER profil sebagai tipe Mahasiswa: format profil tidak membawa semua kolom master Student.

Jika ingin memeriksa data KRS sumber, gunakan `75-02-krs-demo-sumber.json` sebagai pengganti langkah 2. Akibatnya 11 mahasiswa masih belum memenuhi syarat KRS TA. Untuk kembali ke pengujian matriks, impor ulang `75-02b`. Impor KRS mengganti seluruh daftar mahasiswa pada periode yang sama; impor sumber **sesudah** skenario akan menghapus tambahan TA sintetis.

Tujuh berkas dalam `sumber` disimpan untuk referensi/audit. Ekspor umum 277 mahasiswa dan KRS penuh 4.277 mahasiswa tidak diperlukan untuk menguji 12 mahasiswa demo. Pengujian cohort umum memerlukan master Student yang cocok dan merupakan impor terpisah.

Setelah impor, buka periode pendaftaran Sidang TA melalui akun Petugas/Krisna bila ingin menguji pengajuan. Periode akademik Ganjil 2026/2027 yang dibuat seeder berbeda dari periode pendaftaran Sidang TA. Periksa hasil sinkronisasi/log setiap impor sebelum lanjut ke langkah berikutnya.

Matriks tetap menjadi acuan skenario. Sejak pembaruan79, IPK minimal 2,00 menjadi syarat pengiriman. BPP dan persetujuan iGracias tampil sebagai kartu informasi; keduanya tidak menambah syarat pengiriman baru. Tanggal dummy, pemeriksaan BPP Docker, dan langkah setelah update dijelaskan pada [panduan79](79-penyempurnaan-validasi-dan-fixture-bahasa.md).

### 5.1. Docker melewati seluruh 12 baris KRS

Gejala yang dilaporkan: lokal berhasil memakai `75-02b-krs-demo-skenario-ta.json`, tetapi Docker menampilkan `Import KRS Mahasiswa selesai: 0 data berhasil diimpor, 12 data dilewati.` Teks itu berasal dari jalur importer lama. Untuk fixture ini, kode terbaru harus memilih importer resmi dan menampilkan `Import KRS resmi selesai`.

Reproduksi pada database khusus `dadu_testing`: upload master75-01 lalu KRS75-02b melalui route aplikasi berhasil memproses 12 mahasiswa dan menyimpan 95 KRS, tanpa skip/mismatch; 1 tes lulus dengan 11 assertion. Jika baris yang sama langsung diberikan kepada importer lama, seluruh 12 baris ditolak sebelum akses database karena kode mata kuliah berada dalam `krs_mata_kuliah`, bukan pada objek mahasiswa. Ini menjelaskan pola 0/12; jangan mengubah JSON menjadi format lama atau menjalankan fresh ulang untuk mengatasinya.

Bandingkan kode host dan container aplikasi yang benar-benar melayani website. Di server:

```bash
cd /opt/dadu-test
sha256sum app/Http/Controllers/SyncController.php \
  app/Support/OfficialKrsJsonNormalizer.php app/Services/OfficialKrsJsonImportService.php
docker compose --env-file .env.docker exec -T --interactive=false --user www-data app \
  sha256sum app/Http/Controllers/SyncController.php \
  app/Support/OfficialKrsJsonNormalizer.php app/Services/OfficialKrsJsonImportService.php
```

Hash versi yang diverifikasi untuk panduan ini:

| Berkas | SHA-256 |
| --- | --- |
| SyncController.php | `9f54cc8540b39211190b7ba3e09827b4c0079f88e3b46529d059e101e72c3aca` |
| OfficialKrsJsonNormalizer.php | `4d12392772a92b8512345785342e0cba56be7c5408a79960d8b56293b28abcd0` |
| OfficialKrsJsonImportService.php | `26dcca239abbe3c095cc44c176b6ba8e0047915b37800bedfde72851bb6a182e` |

Jika image baru sudah memuat kode yang sesuai tetapi container app berbeda, container belum diganti dengan image hasil build. Jika host pun belum sesuai, kirim kode terlebih dahulu. Untuk memastikan seluruh dukungan KRS tersedia, dari laptop:

```bash
cd /home/xdranel/Code/Project/secret/dadu-test
scp app/Http/Controllers/SyncController.php \
  xdranel@100.100.208.106:/opt/dadu-test/app/Http/Controllers/
scp app/Support/OfficialKrsJsonNormalizer.php \
  xdranel@100.100.208.106:/opt/dadu-test/app/Support/
scp app/Services/OfficialKrsJsonImportService.php \
  xdranel@100.100.208.106:/opt/dadu-test/app/Services/
scp database/migrations/2026_11_06_053632_expand_student_course_class_for_official_krs.php \
  xdranel@100.100.208.106:/opt/dadu-test/database/migrations/
```

Kelima berkas setup pada bagian4.1 sebelumnya hanya memperbarui akun dan Compose. Kode aplikasi KRS di atas juga harus ada pada build context server. Update ini memakai migration biasa; akun dan data impor yang telah berhasil dipertahankan. Di server, saat penguji tidak aktif:

```bash
bash <<'UPDATE_KRS75'
set -euo pipefail
cd /opt/dadu-test
compose=(docker compose --env-file .env.docker)
"${compose[@]}" config --quiet
COMPOSE_PARALLEL_LIMIT=1 "${compose[@]}" build app
"${compose[@]}" exec -T --interactive=false --user www-data app php artisan down --retry=60
"${compose[@]}" stop scheduler worker
bash docker/scripts/backup.sh </dev/null
"${compose[@]}" run -T --interactive=false --rm --no-deps app php artisan migrate --force
"${compose[@]}" up -d --no-deps --force-recreate --wait --wait-timeout 240 app web
"${compose[@]}" exec -T --interactive=false --user www-data app php artisan up
"${compose[@]}" up -d --no-deps --force-recreate --wait --wait-timeout 240 scheduler worker
curl --max-time 10 -sS -o /dev/null -w 'LOGIN HTTP %{http_code}\n' http://127.0.0.1:8091/login
UPDATE_KRS75
```

Kode yang berubah hanya PHP/migration, sehingga build app cukup; web tetap direcreate agar alamat upstream app diperbarui. Migration dijalankan melalui one-off app dari image baru; perintah ini tidak menjalankan seeder akun. Jika blok gagal setelah masuk maintenance, ikuti pemulihan `artisan up` pada bagian4.3 setelah penyebab gagal ditangani.

Ulangi pemeriksaan hash host/container, lalu impor ulang **75-02b** dengan tipe KRS. Hasil yang dituju: `12 mahasiswa diproses, 95 entri mata kuliah disimpan, 0 dilewati, 0 NIM-nama mismatch`. Jika pesan sudah resmi tetapi masih ada skip, ambil catatan pada log sinkronisasi terbaru untuk memeriksa sebab data/periode/penyimpanan. Jangan mengulang fresh sebagai langkah diagnosis.

## 6. Cakupan verifikasi

Rencana akun dan seeder diuji memakai data sintetis pada database khusus `dadu_testing`, termasuk jumlah 2/13 akun, hash password, role, pemanggilan ulang, preflight, serta pelestarian periode aktif. Generator memeriksa identitas antar SEEDER/matriks dan SHA-256 salinan enam sumber. Reset database development/server dan impor UI tetap dijalankan pengguna sesuai langkah di atas.

Hasil akhir verifikasi persiapan:

- 16 tes baru rencana akun/seeder lulus. Tes SSO juga lulus dengan persiapan identitas SSO tersendiri.
- Seluruh suite: 645 lulus, 30 gagal. Kegagalan pada reset/update password, autocomplete jadwal, wisuda, dokumen publik, antrean, dan tes sinkronisasi lama juga terulang ketika memakai DatabaseSeeder lama. Kegagalan tersebut belum diperbaiki dalam pekerjaan reset demo ini.
- Kedua fixture KRS diterima normalizer: 84/95 mata kuliah. Profil/BPP masing-masing 12 baris diterima tanpa grup dilewati. Enam salinan sumber identik dengan Downloads.
- Syntax delapan berkas PHP dan seluruh blok Bash di panduan valid. YAML Compose diperiksa, termasuk bind roster khusus setup. Build/runtime Docker di server belum dijalankan dari laptop ini.

Untuk mengulang tes khusus akun pada database pengujian MySQL yang sudah tersedia:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 \
DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 \
DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= \
php artisan test tests/Unit/DemoAccountPlanTest.php tests/Feature/DemoAccountSeederTest.php
```
