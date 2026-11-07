# 64 — Daftar command operasional Docker DADU Test

Referensi untuk server `xdranel@100.100.208.106`, folder `/opt/dadu-test`, Compose project `dadu-test`, branch `main`, hostname `https://dadu-test.gendhiramona.site`.

**Pilih bagian sesuai kebutuhan. Dokumen ini bukan script yang seluruh command-nya dijalankan berurutan.** Deployment sudah berhasil mencapai login publik200 dan pengguna mengonfirmasi dapat login melalui browser. Instalasi pertama ada di63; perbaikan FPM ada di63b.

**Untuk kondisi server sesudah pengiriman berkas lewat SCP:** mulai dari bagian 12.1 sekali, lalu 12.2 untuk memasang main ke container. Update rutin berikutnya memakai 12.2. Reset akun dan urutan impor lokal/Docker ada pada [75](75-reset-demo-lokal-docker-dan-urutan-impor.md).

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
| setup | Bootstrap dua akun tetap + 11 akun blackbox; hanya dipakai secara eksplisit, lihat75 |

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

Untuk server ini, migrate rutin memakai `migrate --force` tanpa `--seed`. Jangan memakai `migrate:fresh`, `migrate:refresh` atau default DatabaseSeeder sebagai perintah update rutin. Reset penuh yang sengaja diminta pengguna mempunyai panduan tersendiri di [75](75-reset-demo-lokal-docker-dan-urutan-impor.md), termasuk roster privat dan password sesuai lingkungan. Rollback migration bukan langkah pemulihan otomatis; lihat migration spesifik dan backup sebelum memutuskan.

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

Alur update: **working tree bersih → fetch/fast-forward main → build → maintenance → backup → migrate → recreate → artisan up → verifikasi**. Source PHP berada di image; pull saja memperbarui host, sedangkan container yang melayani website baru berubah setelah build dan recreate.

Pilih langkah sesuai keadaan:

| Keadaan server | Mulai dari |
| --- | --- |
| Pull ditolak karena berkas hasil SCP, termasuk file KRS/seeder yang untracked | 12.1 sekali, kemudian 12.2 |
| Working tree bersih dan ingin memasang main terbaru | 12.2 |
| Source host sudah baru tetapi kode container berbeda/hilang | 12.3 |
| Update terhenti setelah maintenance dan login 503 | 12.4 |
| Halaman sinkronisasi500 dan log menyebut SyncController.php Permission denied | 12.5 |

Update rutin tidak menjalankan seeder akun atau fresh database. Reset penuh serta urutan impor demo mempunyai panduan [75](75-reset-demo-lokal-docker-dan-urutan-impor.md).

### 12.1. Sekali: selaraskan berkas hasil SCP dengan main

Pada kasus yang dilaporkan, host masih pada `a107e00` dan main sudah memuat perubahan pada `0b3ce80`. Empat berkas tracked berubah lewat SCP dan lima berkas kode baru belum tracked. Git menolak menimpa berkas tersebut saat fast-forward. Blok update lama berhenti pada pull, **sebelum** build, `artisan down`, migration, atau recreate.

Blok berikut menyimpan salinan berkas di `$HOME/dadu-test-recovery/`, memasukkan **hanya sembilan berkas kode terkait** ke stash, lalu mengambil main. `.env.docker` dan `private-import` disalin sebagai backup privat di luar repo dan tidak dimasukkan ke stash. Aturan ignore lokal ditambahkan melalui `.git/info/exclude`, sehingga roster yang belum diabaikan oleh commit lama tidak menghalangi pemeriksaan working tree.

Jalankan di server. Aplikasi yang sedang berjalan tidak dihentikan oleh langkah penyelarasan Git ini:

```bash
bash <<'DADU_ALIGN'
set -euo pipefail
umask 022
cd /opt/dadu-test

if [ "$(git branch --show-current)" != main ]; then
    printf 'Berhenti: branch server harus main.\n' >&2
    exit 1
fi
test -f .env.docker
git fetch origin main
if ! git merge-base --is-ancestor HEAD origin/main; then
    printf 'Berhenti: histori server bercabang dari main; tinjau commit lokal dahulu.\n' >&2
    exit 1
fi

task_recovery_dir="$HOME/dadu-test-recovery/$(date -u +%Y%m%dT%H%M%SZ)-$$"
mkdir -m 0700 -p "$task_recovery_dir"
mkdir -p "$task_recovery_dir/source"
printf 'Backup berkas: %s\n' "$task_recovery_dir"
git rev-parse HEAD > "$task_recovery_dir/head-before.txt"
git status --short > "$task_recovery_dir/status-before.txt"
cp -p -- .env.docker "$task_recovery_dir/.env.docker"
if [ -d private-import ]; then
    cp -a -- private-import "$task_recovery_dir/private-import"
fi

task_exclude_file="$(git rev-parse --git-path info/exclude)"
printf '\n# Runtime privat DADU server\n/private-import/\n/.env.docker*\n/backups/\n' >> "$task_exclude_file"

task_scp_files=(
    app/Http/Controllers/SyncController.php
    compose.yaml
    database/seeders/DatabaseSeeder.php
    docker/scripts/seed-test-accounts.php
    app/Services/OfficialKrsJsonImportService.php
    app/Support/DemoAccountPlan.php
    app/Support/OfficialKrsJsonNormalizer.php
    database/migrations/2026_11_06_053632_expand_student_course_class_for_official_krs.php
    database/seeders/DemoAccountSeeder.php
)
for task_file in "${task_scp_files[@]}"; do
    if [ -f "$task_file" ]; then
        mkdir -p "$task_recovery_dir/source/$(dirname -- "$task_file")"
        cp -p -- "$task_file" "$task_recovery_dir/source/$task_file"
    fi
done
git diff --binary HEAD -- "${task_scp_files[@]}" > "$task_recovery_dir/tracked-changes.patch"

if [ -n "$(git status --porcelain -- "${task_scp_files[@]}")" ]; then
    git -c user.name="DADU server backup" -c user.email="dadu-server@localhost" \
      stash push --include-untracked -m "dadu-scp-before-main-$(date -u +%Y%m%dT%H%M%SZ)" -- "${task_scp_files[@]}"
    git rev-parse refs/stash > "$task_recovery_dir/stash-oid.txt"
fi

if [ -n "$(git status --porcelain)" ]; then
    git status --short
    printf 'Berhenti: ada perubahan lain di luar sembilan berkas SCP. Backup tercatat di atas; tinjau dahulu.\n' >&2
    exit 1
fi
git merge --ff-only origin/main
cmp -- .env.docker "$task_recovery_dir/.env.docker"
if [ -d "$task_recovery_dir/private-import" ]; then
    diff -qr -- "$task_recovery_dir/private-import" private-import
fi
git status --short
git log -1 --oneline
printf 'Penyelarasan selesai. Backup: %s\nLanjutkan bagian 12.2.\n' "$task_recovery_dir"
DADU_ALIGN
```

Harapan: fast-forward berhasil, `git status --short` kosong, env/roster tetap sama, dan lokasi backup tercetak. Backup ini menyimpan berkas, bukan isi database; backup database/upload dilakukan pada 12.2. Folder backup dibuat0700 secara khusus; checkout Git memakai umask022 agar source baru tetap terbaca oleh www-data. Jangan memakai umask077 untuk seluruh blok checkout.

Identitas `DADU server backup` hanya dipakai pada command stash tersebut agar tetap dapat berjalan bila server belum mempunyai konfigurasi nama/email Git. Konfigurasi Git pengguna tidak diubah.

**Biarkan stash tersimpan.** Main sudah membawa implementasi KRS/seeder yang tadi dikirim lewat SCP; `git stash pop` dapat memasukkan kode lama kembali atau menimbulkan konflik. Untuk melihat daftar dan ringkasan simpanan:

```bash
git stash list
git stash show --include-untracked --stat 'stash@{0}'
```

Gunakan entri berlabel `dadu-scp-before-main-...` yang sesuai, karena `stash@{0}` berarti stash terbaru. OID tepatnya juga tersimpan di `stash-oid.txt` pada folder backup. Salinan source dapat dibandingkan dengan main bila ada perubahan server yang ingin dipertahankan. Jangan menghapus stash/backup sebelum perbandingan selesai. Tidak memakai `git reset --hard`, `git clean`, stash seluruh repo, atau commit berkas privat untuk menyelesaikan kasus ini.

Jika langkah penyelarasan berhenti karena perubahan lain atau histori bercabang, simpan outputnya. Jangan lanjut build dari working tree yang belum jelas. Sembilan berkas yang sudah masuk stash tetap dapat ditemukan dari OID dan salinan backup; langkah ini tidak menjalankan maintenance atau mengubah database.

### 12.2. Update rutin setelah working tree bersih

Kode yang ingin dipasang harus sudah di-commit/push ke main. Sesudah penyelarasan 12.1, jalankan blok ini untuk memasang kode main ke container. Pada update berikutnya, cukup mulai dari blok ini selama working tree bersih. Jangan menjalankan script deploy.sh lama di root repo.

Blok berhenti sebelum fetch/build jika ditemukan perubahan lokal atau file untracked yang belum diabaikan. Build dilakukan sebelum maintenance. `--interactive=false` menjaga command container agar tidak membaca sisa heredoc; `-T` saja hanya menonaktifkan TTY. Backup memakai stdin kosong untuk alasan yang sama. Referensi: [Compose exec](https://docs.docker.com/reference/cli/docker/compose/exec/) dan [Compose run](https://docs.docker.com/reference/cli/docker/compose/run/).

```bash
bash <<'DADU_UPDATE'
set -euo pipefail
umask 022
cd /opt/dadu-test
compose=(docker compose --env-file .env.docker)

if [ "$(git branch --show-current)" != main ]; then
    printf 'Berhenti: branch server harus main.\n' >&2
    exit 1
fi
if [ -n "$(git status --porcelain)" ]; then
    git status --short
    printf 'Berhenti: working tree belum bersih. Untuk berkas SCP yang dilaporkan, ikuti64 bagian 12.1.\n' >&2
    exit 1
fi
printf 'Commit sebelum update: '
git rev-parse HEAD
git fetch origin main
git merge --ff-only origin/main
printf 'Commit yang akan dipasang: '
git rev-parse HEAD
"${compose[@]}" config --quiet
COMPOSE_PARALLEL_LIMIT=1 "${compose[@]}" build app
COMPOSE_PARALLEL_LIMIT=1 "${compose[@]}" build web

"${compose[@]}" exec -T --interactive=false --user www-data app php artisan down --retry=60
"${compose[@]}" stop scheduler worker
bash docker/scripts/backup.sh </dev/null
"${compose[@]}" run -T --interactive=false --rm --no-deps app php artisan migrate --force

# Route /up dikecualikan dari maintenance; --wait dapat memeriksa web.
"${compose[@]}" up -d --no-deps --force-recreate --wait --wait-timeout 240 app web
"${compose[@]}" exec -T --interactive=false --user www-data app php artisan up
"${compose[@]}" up -d --no-deps --force-recreate --wait --wait-timeout 240 scheduler worker
"${compose[@]}" ps -a
curl --fail --max-time 10 -sS -o /dev/null -w 'LOGIN HTTP %{http_code}\n' http://127.0.0.1:8091/login
curl --fail --connect-timeout 10 --max-time 20 -sS -o /dev/null \
  -w 'PUBLIC LOGIN HTTP %{http_code}\n' https://dadu-test.gendhiramona.site/login
DADU_UPDATE
```

Harapan: migration berhasil, app/web siap, scheduler/worker berjalan, login lokal/publik 200. Recreate web membuat Nginx memakai alamat app yang baru; recreate worker/scheduler memasang image baru pada proses background. Akun, roster privat, password env, serta data impor yang sudah tersimpan tidak direset. Seed akun tidak menjadi bagian update rutin.

Jika blok gagal pada Git/build, maintenance belum dijalankan dan container lama belum diganti oleh blok ini. Jika gagal setelah `artisan down`, ikuti 12.4. Jangan memasang trap yang selalu menjalankan `artisan up` saat gagal karena image/schema mungkin belum siap.

### 12.3. Verifikasi source host dan kode container

Login 200 membuktikan endpoint merespons; untuk memastikan kode KRS/profil terbaru terpasang, bandingkan hash berdasarkan nama file:

```bash
cd /opt/dadu-test
sha256sum \
  app/Http/Controllers/SyncController.php \
  app/Support/OfficialKrsJsonNormalizer.php \
  app/Services/OfficialKrsJsonImportService.php \
  app/Services/AcademicProfileJsonImportService.php
docker compose --env-file .env.docker exec -T --interactive=false --user www-data app \
  sha256sum \
  app/Http/Controllers/SyncController.php \
  app/Support/OfficialKrsJsonNormalizer.php \
  app/Services/OfficialKrsJsonImportService.php \
  app/Services/AcademicProfileJsonImportService.php
```

Hash mengikuti commit yang dipasang; cocokkan host/container, bukan hash historis dari chat. File yang hilang di host berarti source belum lengkap. Host baru tetapi container berbeda/hilang berarti image belum sesuai atau container belum direcreate. Untuk source main yang sudah bersih, ulangi 12.2 dan pastikan build/recreate sukses. Restart atau `artisan up` saja tidak memasukkan source baru ke image.

Insiden KRS telah direproduksi: fixture 75-02b dikirim ke jalur lama menghasilkan 0 berhasil/12 dilewati; upload melalui kode resmi berhasil 12 mahasiswa/95 KRS. Pengguna kemudian mengonfirmasi Docker dapat mengimpor sesudah source dikirim, build/migration dijalankan, dan container direcreate. Pemulihan tidak membutuhkan fresh/seed ulang. Panduan khusus KRS dan pilihan fixture ada pada [75 bagian5.1](75-reset-demo-lokal-docker-dan-urutan-impor.md#51-docker-melewati-seluruh-12-baris-krs).

### 12.4. Pemulihan 503 setelah maintenance

`artisan down --retry=60` mengisi header Retry-After; 60 bukan timer untuk membuka aplikasi otomatis. Maintenance tersimpan pada volume storage dan bertahan sesudah recreate. Route `/up` dapat 200 saat `/login` 503, sehingga healthy saja belum membuktikan aplikasi terbuka.

Periksa status, log error terakhir, dan migration:

```bash
cd /opt/dadu-test
docker compose --env-file .env.docker ps -a
docker compose --env-file .env.docker exec -T --interactive=false --user www-data app php artisan migrate:status
docker compose --env-file .env.docker logs --tail 100 app web
```

Sesudah penyebab kegagalan ditangani, migration selesai, dan app/web memakai image yang sesuai, hidupkan kembali akses pengguna dan proses background:

```bash
docker compose --env-file .env.docker exec -T --interactive=false --user www-data app php artisan up
docker compose --env-file .env.docker up -d --no-deps --force-recreate --wait --wait-timeout 240 scheduler worker
curl --fail --max-time 10 -sS -o /dev/null -w 'LOGIN HTTP %{http_code}\n' http://127.0.0.1:8091/login
curl --fail --connect-timeout 10 --max-time 20 -sS -o /dev/null \
  -w 'PUBLIC LOGIN HTTP %{http_code}\n' https://dadu-test.gendhiramona.site/login
```

Target: `Application is now live.` dan login lokal/publik 200. `artisan up` tidak menghidupkan container worker/scheduler yang dihentikan, sehingga langkah up kedua tetap diperlukan. Pengguna sebelumnya telah memulihkan503 dengan `artisan up` dan mendapatkan login200. Bila migration masih gagal/pending, tangani error sebelum membuka maintenance; jangan menjalankan fresh atau rollback schema sebagai pemulihan otomatis.


### 12.5. Sinkronisasi500: source PHP tidak terbaca oleh www-data

Log server membuktikan `include(/var/www/html/app/Http/Controllers/SyncController.php): Failed to open stream: Permission denied`. Error `Cannot modify header information` yang menyertainya merupakan error lanjutan. Pada pengujian aplikasi lokal, halaman sinkronisasi dapat dibuka setelah seluruh fixture demo diimpor;19 assertion lulus.

Penyebab berhasil direproduksi pada script penyelarasan versi awal panduan ini: `umask 077` untuk backup berlaku sampai operasi stash/checkout Git, sehingga source baru menjadi0600. Docker COPY mempertahankan mode file dari build context. Di image, file milik root dengan mode0600 tidak terbaca oleh worker FPM www-data. Pemeriksaan isi/hash saja tidak menggantikan pemeriksaan izin baca pengguna aplikasi.

Panduan sekarang memakai umask022 untuk checkout dan membuat **folder backup**0700 secara khusus. Dockerfile juga menormalkan izin baca/traverse hanya pada direktori source aplikasi dan konfigurasi image. Berkas env, roster privat dan volume storage tidak masuk normalisasi source tersebut. Referensi: [Dockerfile COPY](https://docs.docker.com/reference/dockerfile/#copy).

Untuk memeriksa mode source pada container saat ini:

```bash
cd /opt/dadu-test
docker compose --env-file .env.docker exec -T --interactive=false --user 0 app \
  stat -c '%a %U:%G %n' app/Http/Controllers/SyncController.php
```

**Pemulihan sementara pada container app yang sedang berjalan:** perbaiki izin source sebagai root, lalu buktikan autoload controller berhasil sebagai www-data. Ini tidak menyentuh isi kode, database, env, atau roster privat:

```bash
bash <<'DADU_FIX_CODE_READ'
set -euo pipefail
cd /opt/dadu-test
compose=(docker compose --env-file .env.docker)
"${compose[@]}" exec -T --interactive=false --user 0 app sh -eu -c '
chmod -R a+rX app config database public resources routes
chmod a+rx bootstrap
chmod a+r bootstrap/app.php bootstrap/providers.php artisan composer.json composer.lock
'
"${compose[@]}" exec -T --interactive=false --user www-data app php -r '
require "vendor/autoload.php";
if (!class_exists("App\\Http\\Controllers\\SyncController")) { exit(1); }
echo "SyncController terbaca oleh www-data.\n";
'
"${compose[@]}" restart app web
curl --fail --max-time 10 -sS -o /dev/null -w 'LOGIN HTTP %{http_code}\n' http://127.0.0.1:8091/login
DADU_FIX_CODE_READ
```

Sesudah pesan `SyncController terbaca oleh www-data.` dan login200, buka ulang halaman sinkronisasi dalam sesi login browser. Script hanya memperbaiki container app saat ini; recreate dari image lama dapat mengembalikan izin yang salah. Worker/scheduler juga harus memakai image yang telah diperbaiki agar source baru dapat dibaca.

**Perbaikan permanen:** commit/push Dockerfile dan panduan64 yang telah diperbaiki dari laptop. Sesudah perubahan itu tersedia di main, gunakan12.2 untuk fetch/build/migration/recreate. Tidak perlu mengubah source PHP atau mengulang fresh/seed. Dockerfile yang baru memperbaiki mode source saat build meskipun berkas host lama masih0600; konfigurasi PHP-FPM/Nginx yang di-COPY terpisah juga dibuat0644.

Verifikasi pengembangan: uji simulasi Git sekarang memastikan hasil checkout dapat dibaca pengguna lain, dan uji instruksi awal RUN pada source stage Dockerfile memastikan source0600/direktori0700 menjadi terbaca/terlintasi, script executable tetap executable, serta fixture privat/env tetap0600. Uji izin ini menjalankan command build yang relevan pada direktori sementara; build Docker penuh dan pemulihan server tetap harus dijalankan pada lingkungan Docker pengguna.

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

**Bootstrap ulang akun demo** mengubah password/role sesuai kebijakan75 dan menyetel ulang settings sinkronisasi. Siapkan roster privat serta izin baca menurut [75 bagian4](75-reset-demo-lokal-docker-dan-urutan-impor.md#4-reset-docker-dadu-test), lalu periksa dahulu:

```bash
docker compose --env-file .env.docker run -T --interactive=false --rm --no-deps setup php docker/scripts/seed-test-accounts.php --check
docker compose --env-file .env.docker run -T --interactive=false --rm --no-deps setup
```

Jalankan command kedua hanya jika preflight berhasil dan memang ingin memperbarui akun. Dua akun tetap adalah superadmin@example.test dan krisna@mahasiswa.test, memakai DADU_TEST_PASSWORD yang sudah ada. Sebelas akun dari roster memakai email nama lengkap huruf kecil tanpa spasi dan password `password`. Dummy002 tidak dibuat lagi. Setup tidak menghapus akun lama, tidak mengimpor JSON, dan tidak membuat periode sidang TA terbuka. Periode akademik Ganjil 2026/2027 dibuat bila belum tersedia; periode aktif yang sudah ada dipertahankan. Untuk jumlah akun persis13 sesudah reset penuh, gunakan prosedur fresh/seed pada75; bukan command rutin ini.

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
| Menjalankan kode baru | Working tree bersih → fetch/fast-forward → build → migrate → recreate sesuai bagian12 |
| Menerapkan environment baru | recreate, bukan restart saja |
| Menghentikan akses HTTP dengan halaman503 | artisan down |
| Mengembalikan akses setelah maintenance | artisan up |
| Menjalankan Artisan pada app aktif | exec --user www-data app php artisan … |
| Menjalankan CLI pada image baru sebelum app hidup | run --rm --no-deps app php artisan … |
| Melihat log terus-menerus | logs -f; Ctrl+C hanya menghentikan tampilan log |

Dokumen memakai service name Compose, bukan ID container yang berubah setiap recreate. Tidak ada kebutuhan mengulang konfigurasi Cloudflare/NPM setiap stop/start selama hostname, network proxy-net, alias dadu-test-web dan port8080 tetap sama.

## Referensi dan pemeriksaan dokumen

- [Git stash: pathspec dan include-untracked](https://git-scm.com/docs/git-stash)
- [Git merge: fast-forward only](https://git-scm.com/docs/git-merge)
- [Docker Compose CLI](https://docs.docker.com/reference/cli/docker/compose/)
- [Compose restart](https://docs.docker.com/reference/cli/docker/compose/restart/)
- [Compose down](https://docs.docker.com/reference/cli/docker/compose/down/)
- [Laravel deployment](https://laravel.com/docs/13.x/deployment)
- [Laravel migrations](https://laravel.com/docs/13.x/migrations)
- [Laravel queues](https://laravel.com/docs/13.x/queues)

Perintah dicocokkan dengan compose.yaml dan script backup/entrypoint saat ini. Blok Bash diperiksa syntax. Penyelarasan Git diperiksa pada repo simulasi di /tmp, termasuk file tracked/untracked hasil SCP, pelestarian env/roster, dan pengaman working tree. Asisten tidak menjalankan SSH, build/restart Docker, migration, backup database, seed, reset data, atau commit/push repo aplikasi untuk memperbarui dokumen ini.
