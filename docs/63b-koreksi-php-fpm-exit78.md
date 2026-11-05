# 63b — Perbaikan PHP-FPM exit78 dan origin502

## Penyebab yang sudah dibuktikan

Status server: MySQL healthy, scheduler/worker hidup, app Restarting(78), web unhealthy. Keempat probe HTTP memberi502. Log app memuat `failed to open error_log (/proc/self/fd/2): Permission denied (13)`, lalu FPM initialization failed. Config Laravel berhasil di-cache sebelum FPM gagal, sehingga masalah ini bukan migrasi/key/database/DNS.

Penyebab berasal dari entrypoint deployment yang ditulis asisten: ia menjalankan master FPM melalui gosu www-data, padahal konfigurasi image resmi membuka descriptor log container melalui master. Perbaikan sudah dibuat **langsung pada dua file dadu-test**, bukan hanya pada dokumen ini:

- docker/scripts/entrypoint.sh: cache config tetap sebagai www-data; master php-fpm di-exec sebagai root untuk membuka log.
- docker/php/pool.conf: user/group pool ditegaskan www-data sehingga worker yang menjalankan kode aplikasi tidak menjadi root.

CLI/scheduler/queue tetap melalui gosu www-data. Tidak memberi chmod777 atau mode privileged pada container. Source bisnis dan repo asli tidak diubah. Perilaku konfigurasi resmi merujuk [PHP Docker Official Image](https://github.com/docker-library/php).

## 1. Pada laptop development: commit dan push patch

```bash
cd /home/xdranel/Code/Project/secret/dadu-test
git diff -- docker/scripts/entrypoint.sh docker/php/pool.conf
git add docker/scripts/entrypoint.sh docker/php/pool.conf docs/63-deployment-dadu-test-docker-server.md docs/63a-koreksi-service-url-tunnel-dan-dns.md docs/63b-koreksi-php-fpm-exit78.md
git commit -m "fix(docker): retain FPM master privileges for container logging"
git push origin main
```

Kedua file deployment perlu masuk main sebelum server pull. .env.docker tidak disentuh atau di-commit. Asisten tidak menjalankan commit/push.

## 2. Pada server: ambil patch dan build target app

```bash
cd /opt/dadu-test
git pull --ff-only origin main
COMPOSE_PARALLEL_LIMIT=1 docker compose --env-file .env.docker build app
```

Cache layer ekstensi/Composer/npm yang tidak berubah masih dapat digunakan. Tidak perlu --no-cache. Image web tidak perlu di-build ulang karena source public dan konfigurasi Nginx tidak berubah pada patch63b.

## 3. Validasi FPM pada image baru sebelum recreate

```bash
docker compose --env-file .env.docker run --rm --no-deps app php-fpm -t
```

Harapan: configuration test successful; tidak ada permission denied/error_log. Ini memakai entrypoint baru dan environment/volume existing. Jika masih gagal, kirim output ini dan jangan lanjut dengan menebak permission. Pengujian FPM runtime ini belum dilakukan asisten karena Docker daemon server tidak diakses.

## 4. Recreate app dan web saja

```bash
docker compose --env-file .env.docker up -d --no-deps --force-recreate --wait --wait-timeout 120 app web
docker compose --env-file .env.docker ps
curl --max-time 10 -sS -o /dev/null -w 'APP HTTP %{http_code}\n' http://127.0.0.1:8091/login
curl --max-time 10 -sS -o /dev/null -w 'NPM HTTP %{http_code}\n' -H 'Host: dadu-test.gendhiramona.site' http://100.100.208.106:80/login
```

Web ikut di-recreate agar Nginx me-resolve ulang alamat container app yang mungkin berubah. Scheduler/worker saat ini sehat dan perilaku CLI tidak berubah; patch ini tidak membutuhkan restart keduanya atau menunggu job panjang. Keduanya bisa memakai image baru ketika deployment normal berikutnya.

Harapan: app Up, web healthy, APP200 dan NPM200. Tidak menjalankan migration, seed, reset database atau menghapus volume untuk perbaikan permission FPM ini.

Jika app masih restart:

```bash
docker compose --env-file .env.docker logs --tail 60 app web
```

Jika APP/NPM200 tetapi publik masih502, lanjutkan probe Docker→host dan published route dari63a; jangan menganggap seluruh origin sudah pulih hanya dari patch source.

## 5. Uji kembali dari laptop browser

```bash
curl --connect-timeout 10 --max-time 20 \
  --resolve dadu-test.gendhiramona.site:443:104.21.62.243 \
  -sS -o /dev/null -w 'PUBLIC HTTP %{http_code}\n' \
  https://dadu-test.gendhiramona.site/login
```

Buka juga hostname normal di browser. Jika DNS browser masih gagal meskipun curl --resolve200, periksa resolver lokal seperti63a. Kirim hasil php-fpm -t, ps, APP/NPM/PUBLIC dan log baru jika gagal. Jangan mengirim isi .env.docker/token/password.

## Bukti verifikasi patch lokal

Sebelum patch, smoke test entrypoint menggunakan stub perintah di /tmp menemukan branch FPM masih melalui gosu (FAIL), sedangkan CLI melalui gosu (PASS). Setelah patch, kedua pemeriksaan PASS: master FPM tidak melalui gosu, cache config/CLI tetap melalui gosu. Ini memeriksa control flow dengan stub, bukan klaim bahwa image Docker sudah berjalan.

Shell syntax dan git diff --check lulus. Source app/resources/routes/tests/database/config di dua clone tetap sama dengan baseline sebelum deployment. Seluruh source/deployment di-hash sebelum/sesudah tiap perubahan Markdown dan tidak berubah selama penulisan. Tidak ada SSH, build image server, restart, commit/push, atau operasi database oleh asisten. Hasil runtime menunggu langkah pengguna.
