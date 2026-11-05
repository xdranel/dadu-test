# 64 — Daftar command operasional Docker DADU Test

Referensi untuk server `xdranel@100.100.208.106`, folder `/opt/dadu-test`, Compose project `dadu-test`, branch `main`, hostname `https://dadu-test.gendhiramona.site`.

**Pilih bagian sesuai kebutuhan. Dokumen ini bukan script yang seluruh command-nya dijalankan berurutan.** Deployment sudah berhasil mencapai login publik200 dan pengguna mengonfirmasi dapat login melalui browser. Instalasi pertama ada di63; perbaikan FPM ada di63b.

## 1. Masuk server dan folder aplikasi

Dari laptop:

```bash
ssh \
  -L 8181:localhost:81 \
  -L 9443:localhost:9443 \
  xdranel@100.100.208.106
```

Setelah masuk SSH:

```bash
cd /opt/dadu-test
```

Seluruh command berikut dijalankan **di server dari folder ini**, kecuali bila disebut laptop/browser. Tetap sertakan `--env-file .env.docker`. Jangan mengganti dengan environment lokal development.

| Service | Fungsi |
| --- | --- |
| app | Master PHP-FPM dan worker request aplikasi |
| web | Nginx DADU, port lokal8091 →8080 |
| db | MySQL DADU; database `dadu`, volume terpisah dari wedding |
| scheduler | Menjalankan jadwal aplikasi, termasuk expiry revisi TA |
| worker | Satu proses untuk job antrean Laravel |
| setup | Bootstrap tiga akun staging; hanya dipakai secara eksplisit |

NPM, cloudflared, Portainer dan aplikasi lain berada di luar stack DADU. Command Compose di folder ini hanya mengelola stack DADU.

## 2. Melihat status dan pemakaian resource

Status termasuk container yang berhenti:

```bash
docker compose --env-file .env.docker ps -a
```

Pemakaian CPU/RAM sesaat seluruh container server:

```bash
docker stats --no-stream
```

Validasi file Compose tanpa mencetak hasil substitusi password:

```bash
docker compose --env-file .env.docker config --quiet
```

`app` diharapkan Up, `db`/`web` healthy, scheduler/worker Up. `setup` tidak harus berjalan. Status unhealthy sendiri tidak membuat Docker otomatis restart; lihat log jika ada masalah.

## 3. Menyalakan seluruh DADU

Pilihan umum ketika image sudah dibangun dan database sudah diinisialisasi:

```bash
docker compose --env-file .env.docker up -d --wait --wait-timeout 240 db app web scheduler worker
```

`-d` membuat container tetap berjalan saat terminal SSH ditutup. `up` membuat container yang belum ada dan menerapkan perubahan konfigurasi/image bila diperlukan. Perintah ini tidak otomatis migrate atau seed.

Untuk menyalakan kembali container yang sudah ada tanpa menerapkan perubahan Compose/image:

```bash
docker compose --env-file .env.docker start
```

Sesudah server reboot, policy `unless-stopped` membantu menghidupkan container yang sebelumnya berjalan. Container yang sengaja kamu stop tetap berhenti sampai di-start/up lagi. Docker daemon juga harus aktif.

## 4. Mematikan DADU tanpa menghapus data

Hentikan seluruh stack DADU:

```bash
docker compose --env-file .env.docker stop
```

Data MySQL dan upload tetap berada di named volumes. Container masih ada. Gunakan bagian3 untuk menyalakannya kembali. DNS/Tunnel tidak perlu dihapus saat aplikasi sementara dimatikan; halaman publik bisa menunjukkan gateway error selama backend mati.

Hentikan hanya frontend/backend, sementara database dan proses background tetap hidup:

```bash
docker compose --env-file .env.docker stop web app
```

Jika ingin menghentikan proses background juga:

```bash
docker compose --env-file .env.docker stop scheduler worker
```

**Waktu tunggu worker:** konfigurasi sekarang mengizinkan graceful stop sampai14430 detik karena scraper terpanjang mempunyai timeout4 jam. Jika tidak ada job aktif, stop biasanya cepat. Hindari mematikan worker di tengah import. Opsi stop dengan batas lebih pendek berarti menerima risiko job terputus:

```bash
# Hanya ketika sengaja ingin menghentikan paksa setelah30 detik.
docker compose --env-file .env.docker stop --timeout 30 worker
```

## 5. Restart service tanpa build ulang

Restart satu service yang bermasalah:

```bash
docker compose --env-file .env.docker restart app
```

Restart webserver DADU:

```bash
docker compose --env-file .env.docker restart web
```

Restart seluruh stack DADU bila memang diperlukan:

```bash
docker compose --env-file .env.docker restart
```

`restart` tidak membaca ulang nilai environment baru dari Compose dan tidak mengganti image. Perubahan source/image memakai bagian12; perubahan `.env.docker` memakai bagian13. Jangan restart Docker daemon hanya untuk memperbaiki satu service DADU karena daemon juga melayani aplikasi lain.

## 6. Menghapus container, sambil mempertahankan volume

Untuk membongkar container/network milik stack sementara:

```bash
docker compose --env-file .env.docker down
```

Tanpa `-v`, named volumes database dan upload dipertahankan. Network external `proxy-net` bukan milik stack untuk dihapus. Container dapat dibuat lagi melalui `up` pada bagian3.

**Hindari `down -v` untuk operasi biasa:** opsi itu menghapus named volumes dan dapat menghilangkan database serta upload. Menjalankan initializer lagi tidak memulihkan data tersebut.

## 7. Log dan pemeriksaan HTTP

Log100 baris terakhir:

```bash
docker compose --env-file .env.docker logs --tail 100 app web scheduler worker
```

Ikuti log secara langsung:

```bash
docker compose --env-file .env.docker logs --tail 50 -f app web
```

Tekan **Ctrl+C** untuk berhenti mengikuti log; container tetap berjalan.

Log database saat startup bermasalah:

```bash
docker compose --env-file .env.docker logs --tail 100 db
```

Cek aplikasi lokal tanpa menampilkan cookie:

```bash
curl --max-time 10 -sS -o /dev/null -w 'LOCAL HTTP %{http_code}\n' http://127.0.0.1:8091/login
```

Cek NPM dengan hostname DADU:

```bash
curl --max-time 10 -sS -o /dev/null -w 'NPM HTTP %{http_code}\n' \
  -H 'Host: dadu-test.gendhiramona.site' http://100.100.208.106:80/login
```

Cek URL publik menggunakan DNS normal:

```bash
curl --connect-timeout 10 --max-time 20 -sS -o /dev/null \
  -w 'PUBLIC HTTP %{http_code}\n' https://dadu-test.gendhiramona.site/login
```

Target login adalah200. Error502 tidak langsung berarti DNS salah; ikuti63a/63b berdasarkan hasil lokal/NPM/publik. Kirim status/error terkait, bukan isi `.env.docker`, token, password atau cookie.

## 8. Command Artisan, shell dan MySQL

Contoh command Artisan pada container app yang sedang berjalan:

```bash
docker compose --env-file .env.docker exec --user www-data app php artisan about
```

`exec` tidak menjalankan ulang entrypoint. Gunakan `--user www-data` untuk operasi Artisan rutin agar file cache/compiled tidak ditulis sebagai root. Master FPM mempunyai kebutuhan berbeda untuk membuka log; itu sudah ditangani entrypoint63b.

Shell aplikasi sebagai www-data:

```bash
docker compose --env-file .env.docker exec --user www-data app sh
```

Keluar shell dengan `exit`. PHP/Composer sudah ada dalam image; Node/npm hanya berada pada tahap build. Jangan menjalankan composer install atau npm install pada container runtime untuk mengganti source. Aset dan dependencies diperbarui melalui rebuild image.

Masuk shell MySQL:

```bash
docker compose --env-file .env.docker exec db mysql -u dadu -p dadu
```

Masukkan `DADU_DB_PASSWORD` dari file server ketika diminta. Itu password database, terpisah dari password login penguji. Jangan menuliskannya sebagai argumen `-pPASSWORD` pada command history. Keluar MySQL dengan `exit`.

## 9. Migration database

Lihat status migration:

```bash
docker compose --env-file .env.docker exec --user www-data app php artisan migrate:status
```

Jalankan migration yang belum diterapkan pada database hosting:

```bash
docker compose --env-file .env.docker exec --user www-data app php artisan migrate --force
```

Migration memakai file yang ada **dalam image**, bukan file host yang baru di-git-pull. Jika source/migration baru datang dari main, ikuti bagian12 supaya image baru dipakai. Ambil backup sebelum migration yang mengubah data/schema.

Jika app berhenti atau ingin migrate menggunakan image baru sebelum FPM dinyalakan:

```bash
docker compose --env-file .env.docker up -d --wait --wait-timeout 240 db
docker compose --env-file .env.docker run --rm --no-deps app php artisan migrate --force
```

Command `run` memakai entrypoint sehingga CLI otomatis menjadi www-data. MySQL harus sehat karena `--no-deps` tidak menyalakan dependency otomatis.

Untuk server ini, migrate rutin memakai `migrate --force` tanpa `--seed`. Jangan memakai `migrate:fresh`, `migrate:refresh` atau default DatabaseSeeder sebagai perintah update: data dapat hilang atau akun password default dibuat lagi. Rollback migration bukan langkah pemulihan otomatis; lihat migration spesifik dan backup sebelum memutuskan.

## 10. Maintenance mode

Tampilkan halaman maintenance503 sambil mempertahankan container:

```bash
docker compose --env-file .env.docker exec --user www-data app php artisan down --retry=60
```

Buka kembali aplikasi:

```bash
docker compose --env-file .env.docker exec --user www-data app php artisan up
```

Maintenance HTTP tidak otomatis menghentikan scheduler/worker. Untuk backup atau perubahan data yang memerlukan jeda tulis, stop kedua service itu juga dan tunggu upload/job aktif selesai. Jangan memakai endpoint `/up` sebagai bukti maintenance sudah mati: health route tersebut dikecualikan dari maintenance oleh Laravel pada aplikasi ini. Cek `/login` untuk respons pengguna.

## 11. Backup database, upload dan environment

Backup cepat pada stack yang sedang berjalan:

```bash
bash docker/scripts/backup.sh
```

Hasil di `backups/`:

| Ekstensi | Isi |
| --- | --- |
| .sql | Snapshot database MySQL DADU |
| -storage.tar.gz | File dalam storage/app termasuk upload |
| .env | Salinan .env.docker, mengandung key/password |

Backup database dan file bukan satu transaksi lintas sistem. Untuk hasil yang lebih konsisten saat aplikasi digunakan, pastikan request aktif selesai, masuk maintenance, stop proses background, kemudian backup:

```bash
docker compose --env-file .env.docker exec --user www-data app php artisan down --retry=60
docker compose --env-file .env.docker stop scheduler worker
bash docker/scripts/backup.sh
docker compose --env-file .env.docker exec --user www-data app php artisan up
docker compose --env-file .env.docker up -d --wait --wait-timeout 240 scheduler worker
```

Jalankan satu per satu dan pastikan backup berhasil sebelum melanjutkan perubahan schema. Backup ada di disk server yang sama; salin ke penyimpanan lain milikmu. Jangan membagikan berkas .env/SQL ke chat atau memasukkannya ke Git. Jika perlu restore, tentukan set SQL/upload/APP_KEY yang cocok; jangan menganggap rebuild dapat mengembalikan data.

## 12. Update aplikasi dari branch main

Pakai ini setelah kode yang ingin dipasang sudah di-commit/push ke repo dadu-test. Tidak menjalankan script deploy.sh lama di root repo.

Blok ini berjalan sebagai satu script dan berhenti bila ada command gagal. Build dilakukan sebelum maintenance agar proses build tidak memperpanjang waktu503. Backup dilakukan sesudah proses background dihentikan, sebelum migration. Hentikan aktivitas penguji ketika memasuki maintenance.

```bash
bash <<'DADU_UPDATE'
set -euo pipefail
cd /opt/dadu-test
compose=(docker compose --env-file .env.docker)

git status --short
git rev-parse HEAD
git pull --ff-only origin main
"${compose[@]}" config --quiet
COMPOSE_PARALLEL_LIMIT=1 "${compose[@]}" build app
COMPOSE_PARALLEL_LIMIT=1 "${compose[@]}" build web

"${compose[@]}" exec -T --user www-data app php artisan down --retry=60
"${compose[@]}" stop scheduler worker
bash docker/scripts/backup.sh
"${compose[@]}" run --rm --no-deps app php artisan migrate --force

# /up is exempt from maintenance, allowing --wait to check the new FPM/web.
"${compose[@]}" up -d --no-deps --force-recreate --wait --wait-timeout 240 app web
"${compose[@]}" exec -T --user www-data app php artisan up
"${compose[@]}" up -d --no-deps --force-recreate --wait --wait-timeout 240 scheduler worker
"${compose[@]}" ps
DADU_UPDATE
```

Sesudah selesai, cek halaman login/upload di browser. Jangan menjalankan setup akun lagi pada update normal. Recreate web membuat Nginx me-resolve ulang alamat app; recreate worker/scheduler memastikan proses panjang memakai image baru.

Jika script gagal sebelum maintenance, aplikasi lama biasanya masih berjalan. Jika gagal setelah maintenance/migration, simpan output, lihat status/log, dan periksa backup; jangan langsung menjalankan `migrate:fresh`, rollback schema, atau menghapus volume. Catat commit lama yang dicetak untuk rencana pemulihan kode yang sesuai dengan schema.

## 13. Mengubah environment atau konfigurasi deployment

Edit file server:

```bash
nano .env.docker
```

Validasi, lalu terapkan ulang environment container:

```bash
docker compose --env-file .env.docker config --quiet
docker compose --env-file .env.docker up -d --no-deps --force-recreate --wait --wait-timeout 240 app web scheduler worker
```

Ini sesuai untuk variable yang memang direferensikan Compose, misalnya APP_URL atau endpoint chatbot. APP_ENV/APP_DEBUG/timezone dan banyak nilai lain ditetapkan pada `compose.yaml`; menambahkan baris sembarang ke .env.docker tidak otomatis memasukkannya ke PHP. Jika mengubah file PHP/Nginx/Dockerfile yang di-COPY ke image, lakukan rebuild melalui bagian12.

Restart saja tidak menerapkan environment baru. Entry point FPM membuat config cache saat app baru dimulai. Jangan meregenerasi APP_KEY atau password DB pada update biasa. MySQL environment hanya menginisialisasi user/password ketika volume database pertama dibuat; mengganti DADU_DB_PASSWORD di file tidak mengubah password yang sudah tersimpan dalam MySQL.

## 14. Cache dan route Laravel

Bersihkan cache optimasi pada app yang berjalan:

```bash
docker compose --env-file .env.docker exec --user www-data app php artisan optimize:clear
```

Buat kembali cache konfigurasi app:

```bash
docker compose --env-file .env.docker exec --user www-data app php artisan config:cache
```

Bersihkan hanya compiled Blade atau cache aplikasi jika diperlukan:

```bash
docker compose --env-file .env.docker exec --user www-data app php artisan view:clear
docker compose --env-file .env.docker exec --user www-data app php artisan cache:clear
```

Lihat route TA:

```bash
docker compose --env-file .env.docker exec --user www-data app php artisan route:list --path=sidang-ta
```

Cache:clear memengaruhi cache aplikasi bersama, termasuk permission/rate limit; pakai ketika ada kebutuhan. Jangan memakai cache:clear sebagai cara menyelesaikan migration atau koneksi DB yang gagal. Runtime image tidak membawa dependency dev seperti Tinker/Pest, sehingga command testing/tinker development belum tentu tersedia di sini.

## 15. Scheduler dan expiry revisi TA

Lihat jadwal:

```bash
docker compose --env-file .env.docker exec --user www-data app php artisan schedule:list
```

Hentikan atau hidupkan scheduler:

```bash
docker compose --env-file .env.docker stop scheduler
docker compose --env-file .env.docker up -d scheduler
```

Pilih salah satu sesuai tujuan. Tidak perlu menjalankan schedule:work manual dari terminal SSH karena service scheduler sudah menanganinya.

Jalankan pengecekan expiry TA sekali secara sengaja:

```bash
docker compose --env-file .env.docker exec --user www-data app php artisan sidang-ta:expire-revisions
```

Command tersebut dapat menolak pengajuan revisi yang sudah melewati deadline sesuai policy. Untuk menjalankan semua jadwal yang jatuh tempo secara manual:

```bash
docker compose --env-file .env.docker exec --user www-data app php artisan schedule:run
```

Schedule:run mencakup task antrean harian08:00 Asia/Jakarta yang mengarsipkan dan membersihkan tiket; pilih command expiry khusus jika hanya ingin mengecek TA.

## 16. Worker dan job antrean

Hentikan/hidupkan worker:

```bash
docker compose --env-file .env.docker stop worker
docker compose --env-file .env.docker up -d worker
```

Minta worker keluar secara graceful setelah job aktif selesai:

```bash
docker compose --env-file .env.docker exec --user www-data app php artisan queue:restart
```

Docker restart policy menghidupkannya lagi. Queue:restart tidak mengganti image container. Jika image baru sudah dibangun, gunakan recreate worker:

```bash
docker compose --env-file .env.docker up -d --no-deps --force-recreate worker
```

Lihat job gagal:

```bash
docker compose --env-file .env.docker exec --user www-data app php artisan queue:failed
```

Setelah penyebabnya diperbaiki, ulangi job tertentu dengan ID/UUID dari daftar (ganti UUID_JOB dengan nilai sebenarnya):

```bash
docker compose --env-file .env.docker exec --user www-data app php artisan queue:retry UUID_JOB
```

Mengulang job bisa mengulangi efek import, jadi jangan menjalankan retry all tanpa menilai job-nya. Worker juga memang keluar secara berkala sesudah max-time3600 dan job aktif selesai, lalu dihidupkan lagi oleh policy Docker. Lihat bagian4 untuk waktu tunggu job scraper4 jam.

## 17. Akun penguji dan pembersihan TA — hanya bila sengaja

Melihat password hosting untuk dibagikan ke penguji melalui jalur internal:

```bash
grep '^DADU_TEST_PASSWORD=' .env.docker
```

**Bootstrap ulang tiga akun** mengubah password/role sesuai file env dan menyetel ulang settings sinkronisasi:

```bash
docker compose --env-file .env.docker run --rm --no-deps setup
```

Gunakan ketika memang ingin bootstrap/reset ketiga akun, bukan tiap kali menyalakan aplikasi. Akunnya superadmin@example.test, krisna@mahasiswa.test dan dummy-ta-2@mahasiswa.test. Setup tidak mengimpor JSON atau membuat periode sidang TA terbuka. Flag demo periode akademik ada pada63 untuk bootstrap awal.

**Pratinjau pembersihan data TA** (belum menghapus):

```bash
docker compose --env-file .env.docker exec --user www-data app php artisan sidang-ta:reset-test-data
```

Hanya jika sengaja ingin menghapus seluruh draft/pengajuan/upload TA uji, setelah backup dan membaca database/lingkup pratinjau, gunakan:

```bash
docker compose --env-file .env.docker exec --user www-data app php artisan sidang-ta:reset-test-data --execute
```

Hentikan aktivitas penguji/scheduler/worker ketika melakukan pembersihan. Akun, sinkronisasi akademik dan periode dipertahankan oleh command TA ini. Penghapusan data bukan bagian rutinitas update atau stop/start.

## 18. Validasi konfigurasi PHP-FPM dan Nginx

Pemeriksaan FPM melalui entrypoint yang sudah dibetulkan:

```bash
docker compose --env-file .env.docker run --rm --no-deps app php-fpm -t
```

Untuk FPM master, jangan menambahkan --user www-data; pengaturan master/pool mengikuti63b. Ini berbeda dari command Artisan rutin.

Cek syntax Nginx DADU:

```bash
docker compose --env-file .env.docker exec web nginx -t
```

Jika melakukan reload konfigurasi yang memang sudah ada di container:

```bash
docker compose --env-file .env.docker exec web nginx -s reload
```

File konfigurasi Nginx/PHP tidak di-bind dari host. Perubahan file di Git/host membutuhkan rebuild/recreate, bukan hanya reload. Jangan melakukan chmod777 atau mode privileged untuk mengatasi error permission; gunakan log dan konfigurasi63b yang sudah dibuktikan bekerja.

## 19. Ringkasan perbedaan penting

| Kebutuhan | Pilihan |
| --- | --- |
| Menyalakan DADU | up -d --wait |
| Mematikan sementara, container tetap ada | stop |
| Melepas container, volume tetap ada | down tanpa -v |
| Mengulang proses dengan konfigurasi/image container yang sama | restart |
| Menjalankan kode baru | pull → build → migrate → recreate sesuai bagian12 |
| Menerapkan environment baru | recreate, bukan restart saja |
| Menghentikan akses HTTP dengan halaman503 | artisan down |
| Mengembalikan akses setelah maintenance | artisan up |
| Menjalankan Artisan pada app aktif | exec --user www-data app php artisan … |
| Menjalankan CLI pada image baru sebelum app hidup | run --rm --no-deps app php artisan … |
| Melihat log terus-menerus | logs -f; Ctrl+C hanya menghentikan tampilan log |

Dokumen memakai service name Compose, bukan ID container yang berubah setiap recreate. Tidak ada kebutuhan mengulang konfigurasi Cloudflare/NPM setiap stop/start selama hostname, network proxy-net, alias dadu-test-web dan port8080 tetap sama.

## Referensi dan pemeriksaan dokumen

- [Docker Compose CLI](https://docs.docker.com/reference/cli/docker/compose/)
- [Compose restart](https://docs.docker.com/reference/cli/docker/compose/restart/)
- [Compose down](https://docs.docker.com/reference/cli/docker/compose/down/)
- [Laravel deployment](https://laravel.com/docs/13.x/deployment)
- [Laravel migrations](https://laravel.com/docs/13.x/migrations)
- [Laravel queues](https://laravel.com/docs/13.x/queues)

Perintah dicocokkan dengan compose.yaml dan script backup/entrypoint saat ini. Blok Bash diperiksa syntax tanpa dieksekusi; source di kedua clone dan file deployment di-hash sebelum/sesudah setiap penulisan Markdown. Asisten tidak melakukan SSH, restart, migration, backup, seed, reset data atau operasi Git commit/push untuk membuat panduan ini.
