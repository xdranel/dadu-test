# 78 — Hasil implementasi meeting mahasiswa, petugas, dan IT Panel

Status 7 November 2026: implementasi rancangan76, audit independen dan integrasi kode fitur selesai. Kode `a2684f5396f2d1482769d61171768e0c95829ab6` telah direview akhir; satu temuan contoh panduan kosong sudah diperbaiki dan direview ulang tanpa temuan terbuka. Agen utama telah menyalin 77 berkas fitur ke checkout shared dan development asli, memverifikasi SHA256 serta melakukan rescan kode/berkas terlindungi. Sinkronisasi tiga dokumen status akhir ini dan rescan hash akhirnya menyusul oleh agen utama. Belum ada migration/initializer pada DB aplikasi, merge/push/deploy atau uji browser/server manual. Hasil otomatis dan urutan waktunya ada pada bagian6; checklist manual bagian5 tetap belum dijalankan.

## 1. Perubahan yang tersedia

- Wizard empat tahap: kontak WA; empat PDF data TA; pembayaran/nilai bahasa; biodata dummy ijazah/PDM. Identitas sinkronisasi tetap read-only. WA mahasiswa/orang tua berupa string digit0–9 sepanjang8–15 digit, sehingga nol depan tetap tersimpan; WA dapat diperbaiki dalam revisi.
- Empat unggahan tahap2 adalah data TA iGracias, bimbingan online yang disetujui, SK TA aktif, dan draft buku TA. Unggahan mahasiswa baru dibatasi PDF maksimum10MB per berkas. Panduan gambar/PDF disimpan terpisah dari unggahan mahasiswa.
- Panel akademik menampilkan nilai/status sebenarnya, termasuk sumber BPP dan riwayat bahasa. Skor EPRT sah450 memenuhi; skor sah di bawah450 membutuhkan minimal3 tes dan surat. Jenis tes berbeda tidak dibandingkan maksimum lintas skala. Data/jenis tidak diketahui tidak dianggap lulus.
- Revisi menghitung kebutuhan surat dari EPRT terbaru dua arah. Kontak dapat diperbaiki; dokumen verified lain tetap terkunci. Cabang PDM mewajibkan tanggal pengajuan tidak melewati hari ini. Persetujuan pernyataan wajib pada server sebelum kirim awal maupun revisi; histori lama tidak diberi tanggal/persetujuan buatan.
- Riwayat mahasiswa hanya event `submitted`, `needs_review`, `approved`, `resubmitted` miliknya; audit lengkap tetap tersedia bagi petugas. Daftar petugas mempunyai countdown periode, ekspor di filter, dan pengelolaan periode dalam kartu periode aktif.
- IT Panel menyediakan **Panduan Form Sidang TA** untuk akun superadmin ber-role `Akademik`. Role lain tidak dapat CRUD meskipun diberi permission panduan. Media dibaca lewat route berizin, tanpa URL upload publik.
- Scheduler membersihkan draft aktif awal/revisi setelah `period.close_at < now()`, termasuk periode nonaktif. Consumed receipt, berkas final, dan berkas yang masih dirujuk tetap dipertahankan.

Tidak ada perubahan SSO/auth, penghubung Student saat login, seeder akun, dependency lockfile, atau JSON privat. Dockerfile dan panduan64/75 yang telah diperbaiki sebelumnya tetap pekerjaan terpisah dan tidak ditimpa fitur ini.

## 2. Berkas dan perubahan schema

Migration baru dijalankan sebagai migration biasa, tanpa fresh/refresh/seed:

1. `database/migrations/2026_11_06_000001_add_meeting_declaration_and_pdm_date_to_sidang_ta_registrations.php`: menambah tanggal PDM, waktu persetujuan, dan versi pernyataan; nullable untuk histori.
2. `database/migrations/2026_11_07_000001_create_sidang_ta_form_guides_table.php`: tabel panduan dan permission khusus; memberikan permission secara tambahan kepada role Akademik yang sudah ada.

Setelah migration, jalankan `sidang-ta:initialize-form-guides`. Command menyiapkan dua gambar asli data TA/bimbingan dan dua tautan template EPRT/PDM `https://linktr.ee/laa.upps.sby`. Tidak membuat akun/role dan tidak mereset permission lain. Pengulangan tidak menimpa edit administrator, konten nonaktif, pemindahan slot, atau default yang sengaja dihapus. Slot contoh dummy ijazah/pernyataan biodata tetap kosong sampai administrator mengisinya dan menampilkan `Contoh belum diatur.`; tidak dibuat contoh/link rekaan.

Berkas produksi mencakup controller/request draft, final, riwayat dan panduan; model/snapshot; service persyaratan, fakta bahasa, revisi/finalisasi, panduan dan cleanup; registry/support; permission/sidebar; route web/scheduler; Blade wizard/tinjau/detail/riwayat/petugas/panduan; serta `resources/js/{app,sidang-ta-wizard}.js`. Daftar nama lengkap dapat diperiksa secara opsional dari histori Git pada repo Docker/feature yang memiliki base `0b3ce80`. Command berikut bukan langkah wajib update lokal; checkout development asli dapat tidak memiliki commit tersebut:

```bash
git diff --name-only 0b3ce80 HEAD
git diff --check 0b3ce80 HEAD
```

Integrasi berkas fitur yang telah direview sudah dilakukan oleh agen utama dengan backup dan pemeriksaan hash. Checkout development asli tetap tidak membawa Dockerfile, compose.yaml, atau docs64; berkas deployment tersebut tidak ditambahkan. Perubahan lokal lain dan berkas terlindungi dipertahankan; penutupan sinkronisasi dokumen dijelaskan pada bagian8.

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

Bagian ini hanya untuk repo Docker DADU Test di `/opt/dadu-test`. Rujukan operasional lengkap adalah **docs/64-command-operasional-docker-dadu-test.md pada repo Docker**, [panduan64 di GitHub repo Docker](https://github.com/xdranel/dadu-test/blob/main/docs/64-command-operasional-docker-dadu-test.md), khususnya12.1 (working tree kotor),12.2 (update),12.4 (maintenance), dan12.5 (source terbaca). File64 memang tidak ada pada checkout development asli. Gunakan versi64 yang mempertahankan pengaman Git dan perbaikan izin baca source; jangan menggantinya dengan salinan base worktree fitur.

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

## 5. Checklist manual sesudah update

Semua kotak berikut belum diverifikasi secara interaktif oleh asisten. Gunakan akun yang sudah tersedia; tidak perlu reset akun atau impor ulang untuk memasang fitur.

### Mahasiswa

- [ ] Dashboard dan sidebar menuju daftar Sidang TA. Periksa empat tahap, identitas terkunci, nilai/status akademik nyata dan tidak berulang. Akun hanya-baca dapat membuka detail tanpa kontrol kirim/revisi.
- [ ] Isi WA dengan nol depan; coba kosong, huruf, tanda baca dan panjang salah. Pesan Indonesia muncul pada field/summary; request tidak sah tidak dapat dikirim.
- [ ] Tahap2 menerima empat PDF maksimal10MB; gambar/kelebihan ukuran ditolak lewat pemilih dan drag/drop. Preview PDF/gambar panduan, nama file, batal, modal tutup, dan konfirmasi meninggalkan perubahan bekerja pada browser yang dipakai.
- [ ] Coba EPRT449/450/451 dengan data sah yang memang tersedia; surat hanya untuk skor rendah dengan minimal3 tes. Data hilang/kedaluwarsa/jenis tak dikenal tidak tampil lulus; riwayat menampilkan sumber/tanggal sebenarnya.
- [ ] Cabang biodata belum sesuai mewajibkan bukti PDM dan tanggal <=hari ini. Tinjau memuat semua dokumen, tanpa ringkasan akademik ganda. Modal meminta consent; server menolak kirim tanpa consent.
- [ ] Revisi langsung ke tinjau, menampilkan yang perlu diperbaiki; WA dapat diubah, berkas verified lain terkunci. Dengan data uji terkontrol, perubahan EPRT dua arah menambah/menghapus kebutuhan surat tanpa mengubah audit final lama. Riwayat hanya empat event pengajuan/keputusan milik sendiri.

### Petugas

- [ ] Daftar hanya menampilkan judul pada header; ekspor mengikuti filter dan berada di dalam filter; kelola periode berada dalam kartu periode aktif. Countdown dan badge tetap konsisten ketika melewati waktu buka/tutup; tanpa periode tidak ada deadline rekaan.
- [ ] Detail/review mengelompokkan dokumen menurut empat tahap, PDF/gambar historis masih terbaca, riwayat menampilkan audit lengkap termasuk pemeriksaan dokumen. Petugas biasa tidak dapat CRUD panduan.

### Superadmin / Akademik

- [ ] Menu IT Panel **Panduan Form Sidang TA** tersedia untuk role Akademik. Buat/edit urutan, slot, judul, deskripsi, gambar/PDF10MB dan tautan HTTP/HTTPS; URL javascript ditolak.
- [ ] Panduan aktif tampil pada slot mahasiswa yang tepat; nonaktif/hapus tidak tampil. Penggantian panduan tidak menyentuh berkas mahasiswa. Cek default yang diedit/dihapus tetap demikian sesudah initializer diulang secara sengaja.
- [ ] Dalam jadwal pemeliharaan yang disetujui, cek media tetap dapat dibuka setelah recreate app/web; media tetap memerlukan login/permission. Periksa log scheduler dan kecocokan kode host/container sesuai64, termasuk izin source sebagai www-data.

### Cleanup dan batas pemulihan

`sidang-ta:purge-expired-drafts` dijadwalkan setiap menit bersama scheduler yang sudah ada. Operator dapat memeriksa jumlah secara sengaja memakai `php artisan sidang-ta:purge-expired-drafts --dry-run` pada lingkungan yang benar; tanpa opsi ini command menghapus draft eligible. Asisten hanya mengujinya pada dadu_testing dan tidak menjalankannya pada DB aplikasi. Jangan menjalankan `schedule:run` hanya untuk memeriksa fitur ini karena jadwal lain juga dapat berjalan.

Penghapusan file dilakukan sesudah commit metadata draft. Jika storage menolak penghapusan atau proses berhenti setelah commit, berkas orphan dapat tertinggal. Log kegagalan mencatat path; metadata draft sudah hilang sehingga mengulang purge tidak menemukan path itu lagi. Operator perlu memulihkan akses storage lalu memeriksa path terhadap semua referensi dokumen final/draft sebelum membersihkan orphan secara manual. Untuk crash tanpa log perlu inventaris storage dan referensi DB dari backup/keadaan aktual. Tidak ada retry queue atau rekonsiliasi orphan otomatis pada fitur ini.

## 6. Hasil otomatis dan batas verifikasi

- `php -l` pada seluruh **71 berkas PHP/Blade berubah** sejak base `0b3ce80`: exit0, tanpa kesalahan syntax.
- `node --test tests/Js/*.test.mjs` memakai runner default: **18 passed,0 failed**, exit0; Nodev26.13.0. Tidak memakai opsi isolasi tambahan.
- `npm run build`: exit0, Vitev6.4.4,60 modules, build1.51s; dependency tidak diperbarui.
- Suite PHP penuh sebelum perbaikan teks panduan terakhir pada MySQL `dadu_testing`: **741 passed,30 failed,6543 assertions**,88.43s, exit1. PHP8.5.11; Laravel13.38.0. Seluruh30 identitas kegagalan sama persis dengan baseline sebelum fitur645pass30fail; tidak ada kegagalan baru atau baseline yang hilang. Suite keseluruhan tetap merah karena kegagalan lama, bukan diklaim hijau.
- Run awal fase ini739pass32fail6524assertions menemukan dua test petugas dengan ekspektasi pengelompokan lama. Reproduksi fokus2fail10assertions; koreksi ekspektasi tahap dan pemeriksaan ID dokumen SK/thesis menghasilkan suite petugas **26pass237assertions**. Tidak mengubah perilaku produksi untuk meluluskan test lama.
- Perbaikan terakhir menampilkan `Contoh belum diatur.` saat slot tanpa panduan aktif. Reproduksi RED: 2 gagal/1 lulus; gate guide/UI sesudah perbaikan: **20 lulus, 174 assertion**, exit0,11.78s. Review ulang scoped menutup temuan tanpa temuan baru pada `a2684f5396f2d1482769d61171768e0c95829ab6`. Suite PHP penuh, Node dan build tidak diulang setelah perbaikan teks ini; hasil sebelumnya di atas tetap bukti pada waktunya. Log gate: `/tmp/meeting77-final-fix-green.log`.
- Tiga blok Bash pada dokumen ini lolos `bash -n`; pemeriksaan hanya syntax, tidak menjalankan deployment. Penutupan dokumen status akhir tidak mengubah blok command dan tidak mengulang test/build.

Kegagalan lama menurut identitas lengkap (tidak diperbaiki dalam fitur):

```text
Tests\Feature\Auth\PasswordResetTest :: reset password link can be requested
Tests\Feature\Auth\PasswordResetTest :: reset password screen can be rendered
Tests\Feature\Auth\PasswordResetTest :: password can be reset with valid token
Tests\Feature\Auth\PasswordUpdateTest :: password can be updated
Tests\Feature\ExamScheduleTest :: guest cannot access autocomplete search endpoints
Tests\Feature\ExamScheduleTest :: authenticated user gets empty json if autocomplete query is empty or less than 2 characters
Tests\Feature\ExamScheduleTest :: autocomplete search returns matching distinct courses
Tests\Feature\ExamScheduleTest :: autocomplete search returns matching lecturers with name validation
Tests\Feature\GraduationRegistrationTest :: admin can see active period countdown banner on graduation registration index
Tests\Feature\GraduationRegistrationTest :: countdown banner is not visible if there is no active period
Tests\Feature\GraduationRegistrationTest :: countdown banner shows expired status when active period is closed
Tests\Feature\GraduationRegistrationTest :: student can submit graduation registration with student email and conditional title change
Tests\Feature\GraduationRegistrationTest :: admin can delete graduation registration submission
Tests\Feature\GraduationRegistrationTest :: student can submit graduation registration with honors predicate cumlaude and criteria hki
Tests\Feature\GraduationRegistrationTest :: admin can change review status of graduation registration
Tests\Feature\GraduationRegistrationTest :: backend blocks submission if student is ineligible due to failed courses or tak score
Tests\Feature\PublicDocumentTest :: displays documents of a specific category folder when filtered
Tests\Feature\PublicDocumentTest :: displays search results across categories and allows searching within a folder
Tests\Feature\QueueTicketTest :: visitor get take page displays form when queue is open
Tests\Feature\QueueTicketTest :: ambil tiket sukses via POST /queue/take saves details and sets device_token cookie
Tests\Feature\QueueTicketTest :: ambil tiket saat tutup ditolak dan menampilkan view closed
Tests\Feature\QueueTicketTest :: dua request berurutan/bersamaan tidak menghasilkan kode kembar
Tests\Feature\SyncTest :: admin can import valid student JSON
Tests\Feature\SyncTest :: admin can import flat array Course JSON
Tests\Feature\SyncTest :: admin can import associative Course JSON
Tests\Feature\SyncTest :: admin can import valid KRS JSON when student exists
Tests\Feature\SyncTest :: admin can import flat array KRS JSON and automatically create or update student profile
Tests\Feature\SyncTest :: admin can import valid Attendance JSON when student exists
Tests\Feature\SyncTest :: sync import skips rows if student does not exist for KRS and Attendance
Tests\Feature\SyncTest :: admin can import graduation JSON payload
```

Log sesi development: `/tmp/meeting77-task5-lint-final.log`, `/tmp/meeting77-task5-node.log`, `/tmp/meeting77-task5-build.log`, `/tmp/meeting77-task5-staff-red.log`, `/tmp/meeting77-task5-staff-green.log`, `/tmp/meeting77-task5-full-final.log`; perbandingan nama lengkap `/tmp/meeting77-task5-baseline-comparison.json` dan `/tmp/meeting77-task5-final-failures.json`. File/tmp ini bukti sesi lokal, bukan berkas runtime/deploy dan dapat hilang setelah pembersihan host.

Test database berjalan serial hanya pada `dadu_testing`; test cleanup memakai fixture dan `Storage::fake` terisolasi. Cleanup mencakup batas waktu tepat, periode diperpanjang, status consumed berubah setelah seleksi, shared/borrowed/final files, dry-run/idempotensi dan rollback transaksi. Ini pengujian deterministik recheck/lock order, bukan bukti dua proses database bersamaan; tidak ada klaim stress/race simultan nyata.

Validasi UI menggunakan Blade/DOM, helper JavaScript dan build aset. Belum ada browser interaktif, SSH/server, build image Docker penuh, migration/initializer pada DB aplikasi atau pemeriksaan runtime mount server. Tidak memasang framework browser baru. Review independen akhir beserta review ulang perbaikan teks selesai tanpa temuan terbuka; integrasi berkas ke kedua checkout dan rescan kode/hash telah dilakukan agen utama. Penutupan sinkronisasi tiga dokumen status akhir masih mengikuti proses agen utama, sebagaimana bagian8.

Rujukan command dicocokkan dengan kode repo, panduan64 yang telah diperbaiki, dan dokumentasi terkini melalui Context7: [Laravel13 migrations](https://github.com/laravel/docs/blob/13.x/migrations.md), [Laravel13 deployment](https://github.com/laravel/docs/blob/13.x/deployment.md), [Docker Compose up](https://docs.docker.com/reference/cli/docker/compose/up/). Ini rujukan sintaks/pola operasi, bukan bukti command deployment telah dijalankan.


## 7. Manifest berkas fitur untuk review/integrasi

Daftar ini mencakup produksi, test, dan dokumen fitur; tidak mencakup Dockerfile/compose/panduan64/75. Artefak build mengikuti proses build pada masing-masing lingkungan.

```text
app/Console/Commands/InitializeSidangTaFormGuides.php
app/Console/Commands/PurgeExpiredSidangTaDrafts.php
app/Http/Controllers/Admin/SidangTaFormGuideController.php
app/Http/Controllers/SidangTaDraftController.php
app/Http/Controllers/SidangTaRegistrationController.php
app/Http/Requests/SaveSidangTaDraftStepRequest.php
app/Http/Requests/SidangTaFormGuideRequest.php
app/Http/Requests/StoreSidangTaRegistrationRequest.php
app/Models/SidangTaFormGuide.php
app/Models/SidangTaRegistration.php
app/Models/SidangTaRegistrationLog.php
app/Services/SidangTaAcademicSnapshot.php
app/Services/SidangTaAdmissionFacts.php
app/Services/SidangTaDraftDocumentService.php
app/Services/SidangTaDraftFinalizer.php
app/Services/SidangTaExpiredDraftCleanup.php
app/Services/SidangTaFormGuideService.php
app/Services/SidangTaHistoryPresenter.php
app/Services/SidangTaRevisionDraftService.php
app/Support/SidangTaDocumentRequirements.php
app/Support/SidangTaFormGuideSlots.php
app/Support/SidangTaLanguageHistory.php
app/Support/SidangTaMeetRequirements.php
app/Support/SidangTaRevisionContract.php
config/permission_modules.php
config/sidebar.php
database/migrations/2026_11_06_000001_add_meeting_declaration_and_pdm_date_to_sidang_ta_registrations.php
database/migrations/2026_11_07_000001_create_sidang_ta_form_guides_table.php
database/seeders/SidangTaFormGuidePermissionSeeder.php
docs/76-rancangan-meeting-mahasiswa-petugas-it-panel.md
docs/77-rencana-implementasi-meeting-mahasiswa-petugas-it-panel.md
docs/78-hasil-implementasi-meeting-mahasiswa-petugas-it-panel.md
resources/js/app.js
resources/js/sidang-ta-wizard.js
resources/views/sidang_ta_guides/form.blade.php
resources/views/sidang_ta_guides/index.blade.php
resources/views/sidang_ta_guides/partials/guides.blade.php
resources/views/sidang_ta_registrations/admin/index.blade.php
resources/views/sidang_ta_registrations/admin/show.blade.php
resources/views/sidang_ta_registrations/history.blade.php
resources/views/sidang_ta_registrations/index.blade.php
resources/views/sidang_ta_registrations/partials/detail-academic.blade.php
resources/views/sidang_ta_registrations/partials/language-history.blade.php
resources/views/sidang_ta_registrations/partials/meet-admission-summary.blade.php
resources/views/sidang_ta_registrations/partials/wizard-academic.blade.php
resources/views/sidang_ta_registrations/partials/wizard-upload.blade.php
resources/views/sidang_ta_registrations/revision-start.blade.php
resources/views/sidang_ta_registrations/show.blade.php
resources/views/sidang_ta_registrations/wizard-review.blade.php
resources/views/sidang_ta_registrations/wizard.blade.php
routes/console.php
routes/web.php
tests/Feature/SidangTaActivationTest.php
tests/Feature/SidangTaDetailUiTest.php
tests/Feature/SidangTaDraftUploadTest.php
tests/Feature/SidangTaExpiredDraftCleanupTest.php
tests/Feature/SidangTaFormGuideTest.php
tests/Feature/SidangTaHistoryTest.php
tests/Feature/SidangTaMeetDraftTest.php
tests/Feature/SidangTaMeetFinalizationTest.php
tests/Feature/SidangTaMeetStaffReviewTest.php
tests/Feature/SidangTaMeetUiTest.php
tests/Feature/SidangTaMeetingContractTest.php
tests/Feature/SidangTaMeetingLanguageTest.php
tests/Feature/SidangTaMeetingUiTest.php
tests/Feature/SidangTaOfficialAdmissionTest.php
tests/Feature/SidangTaPartialDraftTest.php
tests/Feature/SidangTaReviewListTest.php
tests/Feature/SidangTaReviewUiTest.php
tests/Feature/SidangTaRevisionContractTest.php
tests/Feature/SidangTaRevisionDeadlineTest.php
tests/Feature/SidangTaStudentPolishTest.php
tests/Feature/SidangTaTargetedRevisionTest.php
tests/Feature/SidangTaWizardUiTest.php
tests/Js/sidang-ta-wizard.test.mjs
tests/Unit/SidangTaLanguageHistoryTest.php
tests/Unit/SidangTaMeetRequirementsTest.php
```


## 8. Serah terima kode dan langkah operator

Pada 7 November 2026, kode yang direview adalah `a2684f5396f2d1482769d61171768e0c95829ab6` pada branch lokal `codex/meeting76-ta`. Audit seluruh branch serta review ulang perbaikan contoh kosong selesai tanpa temuan terbuka. Agen utama telah menyalin 77 berkas fitur ke kedua tujuan berikut dan memverifikasi SHA256 seluruh salinan:

| Tujuan | Checkout | Backup sebelum penyalinan |
| --- | --- | --- |
| Shared / Docker | `/home/xdranel/Code/Project/secret/dadu-test` | `/tmp/dadu77-integration-backup/20261107-083804/shared` |
| Development asli | `/home/xdranel/Code/Project/php-laravel/dadu` | `/tmp/dadu77-integration-backup/20261107-083820/original` |

Rescan kode sesudah penyalinan cocok dengan sumber yang direview. Hash sebelum/sesudah berkas terlindungi tetap sama: Dockerfile/panduan64/75 pada repo yang memilikinya, Auth/SSO, User/Student dan seeder akun. Dokumen status akhir76/77/78 sedang ditutup lewat commit dokumentasi terpisah; agen utama akan mereview diff tiga dokumen, menyinkronkannya secara terbatas ke kedua checkout dan mengulang rescan hash akhir. Integrasi kode di atas tidak berarti branch tujuan telah di-merge, di-push atau di-deploy.

Langkah berikutnya bagi operator adalah backup, migration biasa → `sidang-ta:initialize-form-guides` → build aset pada lokal sesuai bagian3. Untuk Docker, build image baru lebih dahulu agar migration dan initializer memakai kode baru, kemudian recreate app/web/scheduler/worker sesuai bagian4. Setelah itu jalankan checklist manual mahasiswa, petugas dan Akademik pada bagian5. Tidak ada fresh/seed, migration/initializer DB aplikasi, deployment atau pemeriksaan browser/server yang dijalankan untuk penutupan dokumen ini.
