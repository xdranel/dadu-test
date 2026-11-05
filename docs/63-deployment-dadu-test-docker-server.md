# 63 — Deployment DADU Test pada server gendhiramona.site

**Goal:** repo xdranel/dadu-test branch main bisa diuji melalui https://dadu-test.gendhiramona.site menggunakan Docker/NPM/Tunnel yang sudah ada.

**Architecture:** cloudflared yang sudah berada di proxy-net meneruskan hostname ke NPM, NPM ke alias dadu-test-web:8080, Nginx ke PHP-FPM. MySQL8.4 terpisah, volume storage/database persisten, satu scheduler dan satu worker. Cloudflare Access tidak dipasang sesuai pilihan pengguna; login aplikasi menjadi pintu masuk. Endpoint guest register/lupa-password ditutup hanya pada Nginx hosting ini karena masalah61f; reset akun melalui admin aplikasi.

**Tech stack:** Laravel13/PHP8.4 yang wajib memenuhi composer.lock, MySQL8.4.10 yang sudah ada di cache server, Nginx, Node22 untuk build aset, Docker Compose5.1. Resource server aktual2 core/2 thread Celeron N4020, RAM3.4GiB, disk197GiB tersedia. Chatbot/LLM tidak dijalankan di paket awal.

**Spec:** hasil inventaris62 dan jawaban pengguna yang mengizinkan file Docker/YAML ditulis langsung di dadu-test untuk deadline testing. Folder original tetap read-only, source bisnis tidak diubah. Tidak ada commit/push/SSH/deploy otomatis oleh asisten.

## Rencana file dan verifikasi

- [x] Tambahkan Dockerfile multi-stage, compose.yaml, .dockerignore dan template .env.docker.example.
- [x] Tambahkan konfigurasi PHP-FPM/upload dan Nginx khusus subdomain HTTPS via proxy.
- [x] Tambahkan initializer rahasia, entrypoint, setup akun test staging dan backup script. DatabaseSeeder umum tidak dijalankan agar seed password default tidak dipublikasikan.
- [x] Validasi syntax YAML/PHP/shell serta initializer dan tidak ada perubahan source bisnis.
- [ ] Pengguna menjalankan Compose config, build image, startup dan browser pada server.
- [x] Lengkapi langkah commit/push pengguna, clone main di /opt/dadu-test, build, migrate, seed tiga akun, startup, NPM dan Cloudflare.

## Keputusan

MySQL terpisah dipilih agar data wedding tidak dipakai/mengalami perubahan. Network proxy-net existing digunakan sehingga tidak perlu tunnel/proxy baru. Public repo tidak diperlukan oleh aplikasi; clone HTTPS dipakai untuk kondisi repo yang saat ini public. Jika private, clone perlu kredensial/deploy key milik pengguna, tidak dibagikan kepada asisten. Visibility repo dan visibility web merupakan pengaturan terpisah.

Wedding boleh dihentikan sesuai izin pengguna, tetapi tidak dihapus volumenya. NPM, Portainer, portfolio dan cloudflared utama tidak dihentikan. Ada juga cloudflared service host yang aktif; tunnel pilihan dashboard harus yang connector-nya berada pada proxy-net, seperti yang dipakai portfolio, bukan connector wedding yang nanti bisa dihentikan.

## Batas publikasi

Hanya data dummy untuk pengujian. APP_DEBUG=false dan password tiga akun dibuat pada server, bukan literal password dalam repo. Endpoint reset tanpa token61f diblokir di Nginx deployment agar public testing tidak membuka jalur tersebut; source Auth dan full-suite failure61f belum diperbaiki oleh perubahan Docker ini.

## Langkah operasional

Bagian command lengkap ditambahkan setelah file Docker diverifikasi. Jangan memakai deploy.sh lama di root: script itu menarik branch develop dari workflow asal.


## A. Ringkasan file yang sudah dibuat langsung di dadu-test

| File | Kegunaan |
| --- | --- |
| Dockerfile | PHP8.4 + Composer production, build aset Node22, target app dan web |
| compose.yaml | MySQL terpisah, PHP-FPM, Nginx, scheduler, worker, setup CLI |
| .dockerignore | Mengecualikan .env, Git, vendor/node_modules lokal, tests, docs, chatbot dan upload |
| .env.docker.example | Template tanpa key/password riil |
| docker/php/app.ini + pool.conf | Upload12M/file, POST192M,2 child PHP, opcache |
| docker/nginx/default.conf | Root public, HTTPS dari proxy, auth reset/register guest ditutup |
| docker/scripts/init-env.sh | Membuat APP_KEY/password baru sekali, mode0600; tidak menimpa existing |
| docker/scripts/entrypoint.sh | Siapkan permission volume dan jalankan PHP sebagai www-data |
| docker/scripts/seed-test-accounts.php | Tiga akun staging manual dan role; bukan DatabaseSeeder umum |
| docker/scripts/backup.sh | Dump SQL, storage/app, dan copy environment ke backups/ |
| .gitignore | Mengecualikan .env.docker dan backups/ |

Aplikasi asli di /home/xdranel/Code/Project/php-laravel/dadu tidak diubah. Source app/resources/routes/tests/database/config juga tidak berubah. File deployment ini khusus repo dadu-test; jangan disalin ke repo asli sebagai bagian kode TA.

Memory limit: MySQL640M, PHP768M, web64M, scheduler192M, worker320M. Ini batas maksimum container, bukan penggunaan idle maupun jaminan concurrency. FPM maksimal2 request PHP paralel; antrean lain menunggu. Build Node dibatasi heap512MiB. Server aslinya2 thread, bukan4; jangan menjalankan banyak build/scrape bersamaan.

## B. Commit dan push dari laptop development

Repo publik boleh digunakan untuk clone HTTPS; private tidak diperlukan secara teknis untuk hosting. Jika ingin kode hanya terlihat tim, jadikan private lalu gunakan clone SSH/deploy key read-only. Pengaturan private repo tidak membatasi pengunjung subdomain. Pemeriksaan lokal tidak menemukan .env, dump SQL, private key atau upload ter-track berdasarkan pola path; ini bukan audit seluruh history GitHub. Jangan gunakan credential yang pernah terekspos; lihat [panduan GitHub untuk data sensitif](https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/removing-sensitive-data-from-a-repository).

Rescan Git lokal menemukan source TA terbaru dan empat test/command baru masih belum di-commit. Karena itu commit source yang telah diuji dahulu, baru commit deployment. .env/phpunit.xml tidak termasuk path git add di bawah. Jika commit source sudah dibuat lebih dahulu, lewati command commit pertama yang akan menyatakan nothing to commit. File belum dikomit/push oleh asisten. Jalankan sendiri:

```bash
cd /home/xdranel/Code/Project/secret/dadu-test
git status --short
git add app resources routes tests database config docs/fixtures/sidang-ta
git diff --cached --stat
git commit -m "refactor(sidang-ta): unify current registration requirements"
git add Dockerfile compose.yaml .dockerignore .env.docker.example .gitignore docker docs/63-deployment-dadu-test-docker-server.md
git diff --cached --stat
git commit -m "build: add Docker staging deployment for DADU test"
git push origin main
```

Periksa daftar staged sebelum commit; command ini tidak bermaksud memasukkan .env/phpunit.xml/data lokal. Jika ada source TA yang belum dikomit, pastikan source yang sudah diuji juga masuk main sebelum server clone; Docker tidak mengunggah file working tree yang belum di-push.

## C. Siapkan folder server dan environment

SSH menggunakan akses yang sudah dipakai. Untuk GitHub publik:

```bash
sudo install -d -m 0755 -o "$(id -un)" -g "$(id -gn)" /opt/dadu-test
git clone --branch main --single-branch https://github.com/xdranel/dadu-test.git /opt/dadu-test
cd /opt/dadu-test
bash docker/scripts/init-env.sh
docker network inspect proxy-net --format '{{.Name}}'
docker compose --env-file .env.docker config --quiet
```

Jika folder sudah berisi clone, cukup masuk lalu git pull --ff-only origin main; jangan clone menimpa folder berisi data. .env.docker hanya disimpan di server. APP_URL default sudah https://dadu-test.gendhiramona.site. Ubah APP_URL sebelum startup jika memilih nama lain; gunakan nama sama pada NPM dan Cloudflare.

Initializer menghasilkan key/password mandiri, bukan password lokal yang sebelumnya dibagikan. Menjalankan initializer lagi tidak mengganti key/database password. Jangan menghapus/regenerasi .env.docker pada deployment berikutnya karena itu dapat memutus DB dan data terenkripsi/session.

## D. Opsional: hentikan wedding yang sudah tidak dipakai

Pengguna mengizinkan wedding dimatikan. Untuk mengurangi beban sebelum build, jalankan hanya tiga nama container yang terlihat pada report:

```bash
docker stop wedding-app-1 wedding-cloudflared-1 wedding-mysql-1
```

Ini tidak menghapus volume atau data wedding. Jangan hentikan container cloudflared (tanpa prefix wedding), NPM, portfolio, atau Portainer. Jangan memakai docker system prune/volume prune untuk langkah ini. Jangan memakai tunnel wedding-staging-tunnel untuk DADU jika connector wedding dihentikan.

## E. Build dan bootstrap aplikasi — urutan satu per satu

Pastikan terminal server berada di /opt/dadu-test. Build pertama bisa cukup lama pada Celeron; Composer check-platform-reqs akan menghentikan build jika PHP image yang didapat tidak memenuhi composer.lock. Jangan menambahkan --ignore-platform-reqs.

```bash
COMPOSE_PARALLEL_LIMIT=1 docker compose --env-file .env.docker build app
COMPOSE_PARALLEL_LIMIT=1 docker compose --env-file .env.docker build web
docker compose --env-file .env.docker up -d --wait --wait-timeout 240 db
docker compose --env-file .env.docker run --rm --no-deps app php artisan migrate --force
docker compose --env-file .env.docker run --rm --no-deps setup php docker/scripts/seed-test-accounts.php --demo-academic-period
docker compose --env-file .env.docker up -d --wait --wait-timeout 240 app web scheduler worker
docker compose --env-file .env.docker ps
docker compose --env-file .env.docker exec web nginx -t
curl -I http://127.0.0.1:8091/up
curl -I http://127.0.0.1:8091/login
curl -I http://127.0.0.1:8091/forgot-password
curl -I http://127.0.0.1:8091/index.php/forgot-password/reset
```

Harapan: db healthy, app/scheduler/worker running, web healthy; /up dan /login200, dua URL lupa-password404. Jika salah satu command gagal, berhenti pada command itu dan kirim output error lengkap; jangan menjalankan migrate:fresh atau menghapus volume.

Setup membuat role/permission/unit/settings dan tiga akun pada APP_ENV=staging. Flag --demo-academic-period menyiapkan AcademicPeriod DUMMY GANJIL2026/2027 bila belum ada periode aktif, untuk menghubungkan KRS dummy. Tidak mengubah periode aktif yang sudah ada. Ini bukan SidangTaPeriod dan bukan impor data resmi. Hilangkan flag jika tidak menguji fixture KRS tersebut.

Jalankan setup hanya untuk bootstrap pertama atau ketika sengaja mereset password/role ketiga akun: rerun akan menyetel ulang password/roles dan settings sinkronisasi. Update rutin cukup migrate; jangan seed ulang otomatis.

| Akun | Role |
| --- | --- |
| superadmin@example.test | Akademik/superadmin |
| krisna@mahasiswa.test | Petugas dan mahasiswa, bisa switch role |
| dummy-ta-2@mahasiswa.test | mahasiswa, NIM1202299002 |

Lihat password bersama ketiga akun langsung di terminal server:

```bash
grep '^DADU_TEST_PASSWORD=' .env.docker
```

Bagikan kepada penguji melalui jalur internalmu; jangan kirim nilai tersebut dalam log diagnostik ke chat. Password hosting tidak memakai literal password dari seed default repo. Database masih berisi data dummy; jangan impor backup data akademik riil untuk demo publik ini.

## F. Tambahkan Proxy Host di Nginx Proxy Manager

Buka http://localhost:8181 dari laptop setelah SSH forward aktif. Pilih Hosts → Proxy Hosts → Add Proxy Host:

| Field | Nilai |
| --- | --- |
| Domain Names | dadu-test.gendhiramona.site |
| Scheme | http |
| Forward Hostname/IP | dadu-test-web |
| Forward Port |8080 |
| Access List | Public |
| Cache Assets | off untuk awal pengujian |
| SSL Certificate | None untuk jalur internal Tunnel HTTP ini |
| Force SSL | off pada NPM untuk host ini |

HTTPS publik akan dilayani Cloudflare; SSL/setting domain lain tidak perlu diubah. Nginx aplikasi mengirim informasi HTTPS ke PHP untuk URL/form/cookie secure, meskipun jalur NPM→container memakai HTTP.

Pada tab Advanced tambahkan:

```nginx
client_max_body_size 192m;
proxy_read_timeout 180s;
proxy_send_timeout 180s;
```

Simpan dan pastikan host Online. Jangan membuat rule cache keseluruhan halaman login atau pengajuan.

## G. Tambahkan hostname pada Tunnel yang sudah ada

Screenshot menunjukkan wedding-staging-tunnel(1 replica) dan xdranel-laptop-tunnel(2 replicas). Report juga menunjukkan connector Docker pada proxy-net serta service cloudflared host aktif. Karena asal kedua replica belum dipastikan seluruhnya Docker, alias container sebagai Service origin dapat gagal pada connector host. Gunakan alamat host bersama yang sudah diketahui, dengan uji koneksi di bawah:

```bash
curl -I -H 'Host: dadu-test.gendhiramona.site' http://100.100.208.106:80/login
docker exec nginx-proxy-manager-app-1 curl -I -H 'Host: dadu-test.gendhiramona.site' http://100.100.208.106:80/login
```

Kedua command harus mendapat200 dari login DADU setelah Proxy Host dibuat. Test kedua memeriksa jalur dari network Docker ke alamat host. Jika curl tidak tersedia di NPM atau alamat itu tidak bisa dijangkau, kirim error; jangan menebak origin lain. Ini memvalidasi rancangan alamat bersama sebelum hostname dipublikasikan.

Di Cloudflare Zero Trust → Networks/Connectors → Tunnels, buka **xdranel-laptop-tunnel**, bagian Published application routes/Public Hostnames, tambahkan:

| Field | Nilai |
| --- | --- |
| Subdomain | dadu-test |
| Domain | gendhiramona.site |
| Path | kosong |
| Service Type | HTTP |
| URL |100.100.208.106:80 |
| HTTP Host Header (additional settings) | dadu-test.gendhiramona.site |

Jangan memasukkan URL publik wedding/login sebagai Service origin. Tidak perlu Tunnel baru, token baru, atau cloudflared container tambahan. CNAME DNS biasanya dibuat otomatis oleh dashboard Tunnel; jangan menambahkan A dan CNAME bersamaan pada nama dadu-test yang sama. DNS CNAME dan Tunnel yang kamu ingat itu dapat menjadi bagian jalur yang sama.

Buka https://dadu-test.gendhiramona.site/login dari browser. Cloudflare Access tidak dikonfigurasi pada paket ini. Kata Public pada Access List NPM bukan tombol login aplikasi; pengguna tetap harus masuk DADU.

## H. Uji browser yang diperlukan

1. Login ketiga akun dan cek switch role Krisna. Periksa CSS/gambar tampil, tidak ada asset HTTP atau419 saat login.
2. Superadmin/Petugas membuat periode Sidang TA terbuka melalui Review Sidang TA → Kelola Periode.
3. Jika ingin demo submit sukses pada dummy002, impor tiga JSON dummy yang sudah ada di docs/fixtures/sidang-ta melalui IT Panel sesuai53. AcademicPeriod dummy harus dibuat sebelum impor KRS supaya academic_period_id terisi. File fixture ada di clone laptop/server, bukan dalam image karena docs dikecualikan dari build context.
4. Krisna tidak otomatis memiliki semua facts JSON. Tanpa data sinkronisasi yang memenuhi, draft/tinjau tetap boleh tetapi final submit akan terblokir. Ini perilaku eligibility, bukan deployment gagal. Dummy002 dari fixture memakai cabang EPRT waiver sehingga perlu Surat Permakluman PDF saat testing.
5. Uji upload1–10MB, simpan/tutup/buka draft, preview privat, kirim, review file/revisi dan expiry. Belum perlu JSON resmi yang masih menunggu.
6. Pastikan /forgot-password dan /index.php/forgot-password/reset404 pada hostname publik. Reset password penguji dilakukan dari menu admin, bukan guest. Full suite Auth61f di source tidak dianggap sudah diperbaiki oleh blok proxy ini.

Akun/test data setup hosting ini tidak mengubah atau menyalin DB lokal; MySQL container DADU mempunyai volume terpisah dari wedding. Tidak ada LLM/chatbot pada paket awal. Endpoint chatbot opsional dapat dikonfigurasi nanti; bagian itu tidak dinyatakan lulus pada demo TA ini.

## I. Operasional dan pembaruan berikutnya

Scheduler container hidup terus bersama Compose; tidak perlu menjalankan schedule:work lagi dari terminal SSH. Task harian antrean08:00 Jakarta juga ikut aktif. Worker satu proses memakai retry_after14700 di atas timeout scraper terpanjang14400. Job scraping4 jam dapat membuat graceful stop menunggu lama; jangan mulai scraper besar saat demo/deploy pada server2 thread. JSON dummy kecil dapat diimpor dari UI.

Lihat status/log (hindari membagikan nilai rahasia):

```bash
cd /opt/dadu-test
docker compose --env-file .env.docker ps
docker compose --env-file .env.docker logs --tail 100 app web scheduler worker
docker stats --no-stream
```

Backup setelah bootstrap dan sebelum update data/schema:

```bash
bash docker/scripts/backup.sh
```

backups/ menyimpan SQL, storage/app dan file environment; folder di-ignore Git/Docker. Untuk backup DB+upload yang konsisten, gunakan maintenance ketika aplikasi sedang dipakai lalu up kembali. Backup lokal perlu disalin ke penyimpanan lain milikmu untuk pemulihan jika disk server rusak.

Update source main berikutnya:

```bash
cd /opt/dadu-test
git rev-parse HEAD
git pull --ff-only origin main
COMPOSE_PARALLEL_LIMIT=1 docker compose --env-file .env.docker build app
COMPOSE_PARALLEL_LIMIT=1 docker compose --env-file .env.docker build web
docker compose --env-file .env.docker exec app php artisan down --retry=60
docker compose --env-file .env.docker stop scheduler worker
bash docker/scripts/backup.sh
docker compose --env-file .env.docker run --rm --no-deps app php artisan migrate --force
docker compose --env-file .env.docker up -d --force-recreate app web scheduler worker
docker compose --env-file .env.docker exec app php artisan up
docker compose --env-file .env.docker up -d --wait --wait-timeout 240 app web scheduler worker
```

Tidak menjalankan setup lagi agar password/roles tidak di-reset. File .env.docker/volume tidak dihapus saat rebuild. Jangan menggunakan down -v atau migrate:fresh untuk update. Catat commit sebelum pull; pemulihan kode menggunakan commit tersebut dan rebuild, sedangkan pemulihan schema/data membutuhkan backup bila migration tidak kompatibel. Jika update gagal sesudah migrate, jangan menebak rollback otomatis; simpan log dan backup untuk langkah pemulihan spesifik.

## J. Batas verifikasi asisten

- Bash/sh syntax pada entrypoint, initializer dan backup lulus.
- PHP lint setup script lulus.
- YAML parse/anchor dan pemeriksaan network/port/volume/env/health dependency lulus.
- Initializer diuji pada salinan /tmp: menghasilkan key/password mandiri, permission0600, rerun tidak mengganti key. Tidak ada rahasia yang dicetak.
- Source bisnis kedua clone tetap identik dengan baseline sebelum deployment; source asli tidak ditulis.
- Docker daemon/Compose CLI tidak tersedia lokal (hanya Podman); validasi Compose config, build image, migration/seed, koneksi MySQL, Nginx -t/runtime dan browser menunggu server pengguna. Jangan menyatakan image/build atau hosting sudah berhasil hanya dari lint.

Referensi: [Docker startup order](https://docs.docker.com/compose/how-tos/startup-order/), [external networks](https://docs.docker.com/reference/compose-file/networks/#external), [Laravel deployment](https://laravel.com/docs/13.x/deployment), [Cloudflare published applications](https://developers.cloudflare.com/tunnel/concepts/routing/).
