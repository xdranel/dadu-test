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
  sumber/                         # enam JSON sumber, disalin persis
  impor/
    75-01-mahasiswa-demo.json
    75-02-krs-demo-sumber.json
    75-02b-krs-demo-skenario-ta.json
    75-03-profil-akademik-demo.json
    75-04-bpp-demo.json
```

Folder `sumber` menyimpan SEEDER 11 mahasiswa, MATRICS 11 mahasiswa, rekap awal, KRS, UPDT TAK & SKPI, dan FINANCE UPDATE. Salinan diverifikasi dengan SHA-256 di manifest. Berkas Downloads tetap tersedia. Folder privat diabaikan Git; ekspor identitas tidak masuk commit/image Docker.

`MATRICS_BLACKBOX_11_MAHASISWA.json` menjadi acuan program studi dan skenario; SEEDER menjadi acuan identitas dan nilai profil akademik. Roster setup hanya membawa 11 identitas dan semester studi, tanpa password server. Tiga ekspor umum berisi 277 mahasiswa tidak mencakup 11 NIM blackbox, sehingga tidak menggantikan profil/BPP skenario mereka.

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
  --output=/tmp/dadu-demo75-private
```

Generator hanya membaca/menulis berkas; tidak membuka database. Periksa manifest sebelum mengganti fixture privat yang sudah digunakan.

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

Kode/Compose baru harus sudah dikirim ke `/opt/dadu-test` melalui alur deployment biasa. JSON privat tidak ikut Git sehingga perlu dikirim terpisah. Dari laptop:

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
docker compose --env-file .env.docker run -T --interactive=false --rm --no-deps setup \
  php docker/scripts/seed-test-accounts.php --check
```

Lanjutkan hanya bila keluar `Preflight OK: 13 akun staging`. Pemeriksaan ini tidak membuat akun atau mengubah database; ia memastikan roster terbaca/valid dan password dua akun tetap tersedia. Pemeriksaan di DatabaseSeeder sendiri terlambat untuk melindungi tabel dari `migrate:fresh`, sehingga preflight terpisah ini harus dilakukan **sebelum** fresh.

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

Gunakan pilihan tipe **Profil Akademik** yang tersedia di UI untuk dua berkas terakhir; `semester` di profil adalah semester studi, sedangkan `periode_keuangan` adalah periode BPP. Jangan mengimpor SEEDER profil sebagai tipe Mahasiswa: format profil tidak membawa semua kolom master Student.

Jika ingin memeriksa data KRS sumber, gunakan `75-02-krs-demo-sumber.json` sebagai pengganti langkah 2. Akibatnya 11 mahasiswa masih belum memenuhi syarat KRS TA. Untuk kembali ke pengujian matriks, impor ulang `75-02b`. Impor KRS mengganti seluruh daftar mahasiswa pada periode yang sama; impor sumber **sesudah** skenario akan menghapus tambahan TA sintetis.

Enam berkas dalam `sumber` disimpan untuk referensi/audit. Ekspor umum 277 mahasiswa dan KRS penuh 4.277 mahasiswa tidak diperlukan untuk menguji 12 mahasiswa demo. Pengujian cohort umum memerlukan master Student yang cocok dan merupakan impor terpisah.

Setelah impor, buka periode pendaftaran Sidang TA melalui akun Petugas/Krisna bila ingin menguji pengajuan. Periode akademik Ganjil 2026/2027 yang dibuat seeder berbeda dari periode pendaftaran Sidang TA. Periksa hasil sinkronisasi/log setiap impor sebelum lanjut ke langkah berikutnya.

Matriks adalah acuan skenario, bukan pernyataan bahwa semua label UI sudah disesuaikan. Tampilan BPP, label bahasa EPRT/ECCT, dan kartu persetujuan yang sebelumnya tercatat masih perlu ditinjau terpisah. BPP tidak menjadi penghalang utama kelayakan Sidang TA.

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
