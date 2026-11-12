# 105 — Akses baca Sidang TA berdasarkan prodi dosen

## Desain yang disepakati

Aksi Khusus `Lihat Pengajuan (Prodi Dosen)` (`sidang_ta_registration_view_program`) membuka daftar, tinjau, catatan, riwayat, preview dan unduh berkas pengajuan prodi dosen. Tidak memberi hak keputusan, catatan, pengubahan berkas, kelola periode, atau ekspor. Read lama tetap melayani konteks mahasiswa; Review tetap untuk petugas peninjau.

Email akun SSO sama dengan Email Domain dosen (dikonfirmasi pengguna). Cari satu record dosen dengan email lengkap, abaikan kapital/spasi tepi. Jangan memakai nama, kode unit, `sso_role`, atau asumsi NIP YPT sama dengan numberid SSO. Jika tidak ada/ambigu atau prodi kosong, tolak akses. Prodi dibaca ulang setiap request agar perpindahan dosen langsung berlaku.

Penulisan `Informatika` setara dengan `S1 Informatika - Kampus Surabaya`. Normalisasi dibatasi pada kapital/spasi, awalan S1 dan akhiran kampus Surabaya; `Digital Bisnis` diberi alias eksplisit `Bisnis Digital` sesuai variasi Data Dosen. Jangan memakai pencarian potongan nama. Program di kampus/jenjang lain tidak disamakan. Pembatasan diterapkan pada query, jumlah, detail, preview dan unduh; dropdown prodi diganti label tetap.

Alternatif read umum tidak dipilih karena telah digunakan akses milik mahasiswa. Review tidak dipilih untuk dosen karena memberi hak keputusan. Aksi khusus menjaga pemisahan kemampuan tanpa mengubah login SSO.

## Rencana implementasi

- [x] Rescan awal dua repo; 44 perbedaan kode dan 12 file khusus development disimpan sebagai baseline. SSO, kode unit, pekerjaan Yudisium yang di-stash dan data aplikasi tidak diubah.
- [x] Baseline 76 tes / 664 assertions lulus pada DB `dadu_testing`.
- [x] Tes gagal untuk dosen, pegawai, pindah prodi, pemalsuan filter/URL, dokumen dan penolakan POST.
- [x] Tambah resolver `SidangTaProgramAccess`; gunakan dari query/controller, tambah izin registry/routes/sidebar dan tampilan baca saja.
- [x] Sesuaikan default Pelaksana untuk instalasi baru saja. Registrasi izin lewat Edit Hak Akses bersifat menambah permission tanpa mengganti assignment.
- [x] Uji regresi, review independen, integrasikan perubahan terarah di dua folder, rescan akhir.

## Perhatian rollout

Pada role Pelaksana di IT Panel → Pengaturan Role, cabut Review Sidang TA lalu centang Aksi Khusus → Lihat Pengajuan (Prodi Dosen). Read dapat tetap dicentang. Review yang masih diberikan tetap berarti hak peninjau penuh, termasuk lintas prodi; jangan memberikannya untuk kebutuhan baca dosen. Atur akun uji dahulu sebelum akun SSO dosen digunakan.

Jangan menjalankan RolePermissionSeeder/migrate:fresh pada aplikasi yang sedang dipakai. Membuka Edit Hak Akses membuat permission baru tanpa mengubah hak yang sudah diberikan. Tidak ada perubahan skema/data pendaftaran untuk fitur ini. Pegawai dengan role sama tanpa record dosen yang cocok akan ditolak. Jika memang juga dosen dan memiliki record cocok, ia diperlakukan sebagai dosen.

Pengaturan role aktual server tidak diubah oleh pekerjaan ini. Format ekspor dikerjakan setelah fitur akses ini. JSON kehadiran masih menunggu contoh.

## Hasil pemeriksaan

Tes menggunakan DB **dadu_testing**. Paket akses/regresi lulus **119 tes / 979 assertions**: dosen dan non-dosen pada role sama; email kosong/duplikat/beraksen; prodi terkini; label pendek/panjang; filter buatan dan pencarian; pagination; detail lintas prodi; preview/unduh; draft mahasiswa tetap privat; POST keputusan ditolak tanpa perubahan data; catatan/riwayat tetap terlihat; role aktif; dan peninjau lama tetap bekerja.

Review independen menemukan perbandingan MySQL dengan kolasi accent-insensitive. Tes membuktikan kegagalan itu sebelum perbaikan. Perbandingan email yang dinormalisasi, daftar prodi DISTINCT, dan WHERE IN pembatasan prodi sekarang memakai CAST AS BINARY pada MySQL. Huruf beraksen tidak dianggap identitas/prodi yang sama. Review ulang tidak menemukan penghalang tersisa.

Suite penuh juga dijalankan. Sebanyak **42 kegagalan lama** dikonfirmasi kembali pada salinan commit awal `ecfcdd2` melalui kelas tes yang gagal, tanpa perubahan fitur ini. Kelompoknya: PasswordResetTest (3), PasswordUpdateTest (1), ExamScheduleTest (4), GraduationRegistrationTest (8), PublicDocumentTest (2), QueueHistoryAndLogTest (12), QueueTicketTest (4), SyncTest (8). Satu kegagalan tambahan riwayat pada percobaan pertama berasal dari timezone lingkungan tes terisolasi UTC; konfigurasi tes kemudian dikembalikan ke default aplikasi Asia/Jakarta dan tes riwayat lulus. **Hasil akhir suite penuh: 956 lulus, 42 gagal, 8329 assertions**. Nama 42 tes yang gagal sama persis dengan baseline. Suite penuh bukan dinyatakan hijau; perubahan ini tidak memperbaiki kegagalan lama di modul lain.

Log sesi: `/tmp/dadu105-gate-final.log`, `/tmp/dadu105-collation-red.log`, `/tmp/dadu105-baseline-failures.log`, dan `/tmp/dadu105-full-suite-final.log`.

## Verifikasi integrasi dan rescan akhir

- Development asli: paket yang sama lulus **119 tes / 980 assertions** (`/tmp/dadu105-development-gate.log`). Perbedaan assertion berasal dari konfigurasi/fitur development yang sudah ada.
- Lint PHP dan `git diff --check` lulus pada kedua repo. Kompilasi Blade lulus di worktree terisolasi (`/tmp/dadu105-blade.log`). Tidak mengubah JavaScript/dependency.
- Command copy diuji pada repo sementara untuk pemasangan pertama dan pengulangan; hasil 12 file cocok dengan repo Docker. Log `/tmp/dadu105-copy-smoke-{1,2}.log`.
- Rescan akhir: masing-masing 8 file kode berubah dan 2 file kode baru. Seluruh 44 perbedaan kode antarrepo yang sudah ada tetap dipertahankan, termasuk 12 file khusus development. Tidak mengubah Auth/LoginRequest, SsoController, model User, RoleSeeder, kode unit, `.env`, atau pekerjaan Yudisium yang di-stash.
- Total berkas fitur: 10 kode/tes + dokumen ini + patch Docker. Backup integrasi `/tmp/dadu105-integration-backup`; laporan `/tmp/dadu105-after-rescan.json`.
- Tidak menjalankan migrasi, seed, reset, impor, commit, push, atau deploy pada database/aplikasi yang sedang digunakan. Seeder hanya dijalankan oleh tes di **dadu_testing**.

## Berkas perubahan

```text
app/Services/SidangTaProgramAccess.php
app/Services/SidangTaReviewQuery.php
app/Http/Controllers/SidangTaRegistrationController.php
config/permission_modules.php
config/sidebar.php
routes/web.php
database/seeders/RolePermissionSeeder.php
resources/views/sidang_ta_registrations/admin/index.blade.php
resources/views/sidang_ta_registrations/admin/show.blade.php
tests/Feature/SidangTaProgramReadAccessTest.php
docs/patches/105-docker-akses-prodi-dosen.patch
docs/105-akses-baca-sidang-ta-prodi-dosen.md
```

## Copy development ke repo Docker laptop

File bersama `routes/web.php`, dua config permission/sidebar, dan seeder mempunyai perubahan lain yang berbeda pada development dan repo Docker. **Jangan menyalin utuh empat file tersebut.** Patch di bawah hanya memasang perubahan fitur 105 versi Docker. File lainnya disalin langsung. Command menyimpan backup dan berhenti bila patch tidak cocok; tidak melakukan git add/commit atau deploy.

```bash
bash <<'DADU_COPY_105'
set -euo pipefail
umask 022
dadu_src=/home/xdranel/Code/Project/php-laravel/dadu
dadu_dst=/home/xdranel/Code/Project/secret/dadu-test
dadu_patch="$dadu_src/docs/patches/105-docker-akses-prodi-dosen.patch"
dadu_backup="/tmp/dadu105-copy-$(date +%Y%m%d-%H%M%S)"
dadu_files=(
  app/Services/SidangTaProgramAccess.php
  app/Services/SidangTaReviewQuery.php
  app/Http/Controllers/SidangTaRegistrationController.php
  resources/views/sidang_ta_registrations/admin/index.blade.php
  resources/views/sidang_ta_registrations/admin/show.blade.php
  tests/Feature/SidangTaProgramReadAccessTest.php
  docs/patches/105-docker-akses-prodi-dosen.patch
  docs/105-akses-baca-sidang-ta-prodi-dosen.md
)
dadu_shared=(config/permission_modules.php config/sidebar.php routes/web.php database/seeders/RolePermissionSeeder.php)
for dadu_file in "${dadu_files[@]}"; do test -f "$dadu_src/$dadu_file"; done
if git -C "$dadu_dst" apply --reverse --check "$dadu_patch" 2>/dev/null; then
  dadu_apply=0
elif git -C "$dadu_dst" apply --check "$dadu_patch"; then
  dadu_apply=1
else
  printf 'Berhenti: patch tidak cocok; periksa perbedaan file bersama.\n' >&2
  exit 1
fi
for dadu_file in "${dadu_files[@]}" "${dadu_shared[@]}"; do
  if [ -f "$dadu_dst/$dadu_file" ]; then
    mkdir -p "$dadu_backup/$(dirname "$dadu_file")"
    cp -p "$dadu_dst/$dadu_file" "$dadu_backup/$dadu_file"
  fi
done
for dadu_file in "${dadu_files[@]}"; do
  mkdir -p "$dadu_dst/$(dirname "$dadu_file")"
  cp -p "$dadu_src/$dadu_file" "$dadu_dst/$dadu_file"
done
if [ "$dadu_apply" -eq 1 ]; then git -C "$dadu_dst" apply "$dadu_patch"; fi
git -C "$dadu_dst" diff --check
printf 'Copy selesai. Backup: %s\n' "$dadu_backup"
git -C "$dadu_dst" status --short
DADU_COPY_105
```

Setelah kode dicommit/push dan tersedia di main repo Docker, update `/opt/dadu-test` mengikuti **dokumen 78 bagian 4**, lalu buka Edit Hak Akses untuk memberi aksi khusus dan mencabut Review Pelaksana. Jangan memakai seed/fresh untuk memasang fitur ini. Tidak perlu impor ulang JSON. Permission ditambahkan saat Edit Hak Akses dibuka, tanpa mengubah assignment yang sudah ada. Pengguna perlu memuat ulang halaman agar perubahan role terbaca.

Production tetap ditunda sesuai instruksi pengguna. Kode SSO/penentuan unit dan stash Yudisium tidak disentuh. JSON kehadiran masih menunggu contoh.

## Nama kegagalan lama yang dikonfirmasi pada baseline dan hasil akhir


### `tests/Feature/Auth/PasswordResetTest.php`

- `reset password link can be requested`
- `reset password screen can be rendered`
- `password can be reset with valid token`

### `tests/Feature/Auth/PasswordUpdateTest.php`

- `password can be updated`

### `tests/Feature/ExamScheduleTest.php`

- `guest cannot access autocomplete search endpoints`
- `authenticated user gets empty json if autocomplete query is empty or less than 2 characters`
- `autocomplete search returns matching distinct courses`
- `autocomplete search returns matching lecturers with name validation`

### `tests/Feature/GraduationRegistrationTest.php`

- `admin can see active period countdown banner on graduation registration index`
- `countdown banner is not visible if there is no active period`
- `countdown banner shows expired status when active period is closed`
- `student can submit graduation registration with student email and conditional title change`
- `admin can delete graduation registration submission`
- `student can submit graduation registration with honors predicate cumlaude and criteria hki`
- `admin can change review status of graduation registration`
- `backend blocks submission if student is ineligible due to failed courses or tak score`

### `tests/Feature/PublicDocumentTest.php`

- `displays documents of a specific category folder when filtered`
- `displays search results across categories and allows searching within a folder`

### `tests/Feature/QueueHistoryAndLogTest.php`

- `event called and finished are logged during next call cycle`
- `event skipped is logged when officer skips ticket`
- `event undo_call is logged when officer cancels call`
- `history page lists records from both active tickets and archived histories`
- `filtering by nim searches across all months ignoring date filters`
- `month filter strictly respects month boundaries`
- `queue:export-month exports full month to storage/app/queue-exports and overwrites cleanly`
- `queue:prune-history strictly rejects deletion if export file does not exist`
- `queue:prune-history strictly rejects deletion if export file is empty 0 bytes`
- `queue:prune-history strictly rejects deletion if export row count does not match database count`
- `queue:prune-history dry-run previews deletion count without modifying database`
- `queue:prune-history successfully prunes data older than retention limit when all safeguards pass`

### `tests/Feature/QueueTicketTest.php`

- `visitor get take page displays form when queue is open`
- `ambil tiket sukses via POST /queue/take saves details and sets device_token cookie`
- `ambil tiket saat tutup ditolak dan menampilkan view closed`
- `dua request berurutan/bersamaan tidak menghasilkan kode kembar`

### `tests/Feature/SyncTest.php`

- `admin can import valid student JSON`
- `admin can import flat array Course JSON`
- `admin can import associative Course JSON`
- `admin can import valid KRS JSON when student exists`
- `admin can import flat array KRS JSON and automatically create or update student profile`
- `admin can import valid Attendance JSON when student exists`
- `sync import skips rows if student does not exist for KRS and Attendance`
- `admin can import graduation JSON payload`
