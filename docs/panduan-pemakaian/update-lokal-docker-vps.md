# Update aplikasi lokal, Docker dan VPS

Pakai migration biasa untuk mempertahankan data. Update tampilan126 tidak menambah migration; bila kode125 belum dipasang, migration pelaksanaan125 tetap diperlukan. Backup dan periksa working tree sebelum update.

**VPS1 GB:** jangan menjalankan blok build Docker di bawah langsung pada VPS. Gunakan alur image yang dibangun di komputer dengan RAM cukup dan pengiriman image seperti catatan [VPS113](../arsip-progres/113-pemindahan-dadu-test-ke-vps.md). Pertahankan override resource dan volume. Blok build berikut sesuai runbook server Docker/laptop dengan resource cukup.

## 3. Update lokal tanpa Docker

Jalankan dari root checkout development yang telah menerima berkas fitur dan mempunyai `.env`, vendor, serta node_modules yang sesuai. Dependency tidak berubah. Pastikan DB pada konfigurasi benar dan backup schema/data serta `storage/app` tersedia sebelum update. Command berikut adalah panduan operator, belum dijalankan pada DB aplikasi oleh asisten.

```bash
set -e
php artisan down --retry=60
php artisan config:clear
php artisan migrate
php artisan sidang-ta:initialize-form-guides
npm run build
php artisan view:clear
php artisan up
```

Untuk APP_ENV production, gunakan `php artisan migrate --force`. Jika salah satu langkah gagal, hentikan urutan dan perbaiki penyebab sebelum `artisan up`; jangan memakai fresh/seed untuk update ini. Jika memakai konfigurasi yang dicache, buat ulang `php artisan config:cache` setelah memastikan konfigurasi deployment benar. Mulai ulang proses worker/scheduler jangka panjang sesuai pengelola proses lokal agar memakai kode baru. Scheduler lokal yang belum dikelola dapat dijalankan di terminal terpisah dengan `php artisan schedule:work`; jangan menambahkan proses kedua bila scheduler sudah berjalan.

Panduan tersimpan di disk `local`, root `storage/app/sidang-ta/form-guides/`. Simpan direktori storage persisten dan sertakan dalam backup bersama database. Route media menjalankan otorisasi; panduan tidak membutuhkan `storage:link` untuk dipublikasikan.

## 4. Update Docker

Bagian ini hanya untuk repo Docker DADU Test di `/opt/dadu-test`. Rujukan operasional lengkap adalah **docs/panduan-pemakaian/64-command-operasional-docker-dadu-test.md pada repo Docker**, [panduan64 di GitHub repo Docker](https://github.com/xdranel/dadu-test/blob/main/docs/panduan-pemakaian/64-command-operasional-docker-dadu-test.md), khususnya12.1 (working tree kotor),12.2 (update),12.4 (maintenance), dan12.5 (source terbaca). File64 memang tidak ada pada checkout development asli. Gunakan versi64 yang mempertahankan pengaman Git dan perbaikan izin baca source; jangan menggantinya dengan salinan base worktree fitur.

Kode yang akan dipasang harus sudah tersedia pada main melalui proses review/integrasi pengguna. Asisten tidak melakukan push/merge/deploy. Blok berikut mengikuti64 bagian12.2 dengan tambahan initializer tepat sesudah migration, sebelum app/scheduler baru dibuka. Node/npm dijalankan pada tahap build image; tidak perlu npm pada container runtime. Umask022 dan Dockerfile dengan normalisasi izin source menjaga PHP terbaca oleh www-data.

```bash
bash <<'DADU_MEETING_UPDATE'
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
    printf 'Berhenti: working tree belum bersih; ikuti panduan64 bagian12.1.\n' >&2
    exit 1
fi
git rev-parse HEAD
git fetch origin main
git merge --ff-only origin/main
git rev-parse HEAD
"${compose[@]}" config --quiet
COMPOSE_PARALLEL_LIMIT=1 "${compose[@]}" build app
COMPOSE_PARALLEL_LIMIT=1 "${compose[@]}" build web

"${compose[@]}" exec -T --interactive=false --user www-data app php artisan down --retry=60
"${compose[@]}" stop scheduler worker
bash docker/scripts/backup.sh </dev/null
"${compose[@]}" run -T --interactive=false --rm --no-deps app php artisan migrate --force
"${compose[@]}" run -T --interactive=false --rm --no-deps app php artisan sidang-ta:initialize-form-guides
"${compose[@]}" up -d --no-deps --force-recreate --wait --wait-timeout 240 app web
"${compose[@]}" exec -T --interactive=false --user www-data app php artisan up
"${compose[@]}" up -d --no-deps --force-recreate --wait --wait-timeout 240 scheduler worker
"${compose[@]}" exec -T --interactive=false --user www-data app php artisan migrate:status
"${compose[@]}" exec -T --interactive=false --user www-data app php artisan schedule:list
"${compose[@]}" ps -a
curl --fail --max-time 10 -sS -o /dev/null -w 'LOGIN HTTP %{http_code}\n' http://127.0.0.1:8091/login
curl --fail --connect-timeout 10 --max-time 20 -sS -o /dev/null \
  -w 'PUBLIC LOGIN HTTP %{http_code}\n' https://dadu-test.gendhiramona.site/login
DADU_MEETING_UPDATE
```

Harapan: dua migration berstatus Ran; initializer sukses; app/web healthy atau running, scheduler/worker memakai image baru; login lokal/publik200. Restart saja tidak memasukkan kode/aset image baru. Jika Git/build gagal, blok belum memasuki maintenance. Jika gagal sesudah `artisan down`, periksa log dan status migration menurut64 bagian12.4; sesudah image/schema benar, jalankan `artisan up` dan hidupkan kembali scheduler/worker. Jangan membuka maintenance otomatis lewat trap saat migration gagal. `--retry=60` bukan timer pembukaan; maintenance bertahan pada storage sesudah recreate dan `/up` healthy tidak membuktikan `/login` sudah terbuka.

Compose memasang `app_storage:/var/www/html/storage`; root disk local berada dalam mount ini, sehingga media panduan bertahan saat recreate. Jangan menghapus volume. Batas PHP `post_max_size192M` dan Nginx `client_max_body_size192m` pada repo Docker sudah cukup untuk empat PDF10MB; tidak diperlukan perubahan konfigurasi ukuran untuk fitur ini. Ketahanan media pada runtime server tetap perlu checklist operator di bawah.

## Setelah memasang

Periksa `/login`, Review Sidang TA, Pelaksanaan Sidang TA dan satu akun pembaca. Migration dijalankan dari image baru sebelum membuka app/web. Restart saja tidak memasukkan file/aset baru ke image. Jangan memakai fresh/seed sebagai langkah update.

Runbook lengkap termasuk pemulihan503 dan penyelarasan working tree: [64](64-command-operasional-docker-dadu-test.md). Konfigurasi pemulihan VPS tetap tersedia di `docs/fixtures/vps-113/`.
