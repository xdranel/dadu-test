# 107 — Batalkan Persetujuan Sidang TA

## Aturan yang disepakati

Pada detail peninjauan admin/petugas, pengajuan Disetujui menampilkan Batalkan Persetujuan. Pembatalan mengembalikan status ke Menunggu peninjauan dan memunculkan kembali Minta Revisi, Tolak, Setujui. Status tiap berkas, catatan, metadata/file, snapshot dan waktu kirim dipertahankan. Tidak membuat draft atau pendaftaran baru.

Batas waktu mengikuti **Verifikasi Berkas**, dikonfirmasi pengguna: tanggal mulai dan selesai harus lengkap, checkbox revisi aktif, dan waktu sekarang berada dalam rentang tersebut (batas awal/akhir inklusif). Jadwal pendaftaran dan Tampilkan ke mahasiswa tidak menentukan hak pembatalan. Jika tanggal belum lengkap, di luar rentang, atau revisi dinonaktifkan, tombol dinonaktifkan dan POST ditolak server.

Hanya pemilik permission Review dapat membatalkan, termasuk admin/petugas yang sudah diberi Review; dosen baca prodi dan mahasiswa tidak mendapat hak baru. Sebelum pembatalan ada modal konfirmasi. Tidak menambah permission/route baru; gunakan aksi cancel_approval pada endpoint keputusan yang sudah dilindungi Review.

Status persetujuan yang dibatalkan disimpan sebagai kejadian approval_cancelled (approved → submitted), dengan pelaku/waktu pada audit petugas. Riwayat mahasiswa menampilkan pembatalan tanpa identitas pelaku, sesuai aturan riwayat sebelumnya. Metadata keputusan aktif reviewed_by/at dikosongkan; keputusan awal tetap ada dalam riwayat. Catatan pengajuan tetap ada.

## Rencana implementasi dan verifikasi

- [x] Rescan kedua folder dan konfirmasi acuan jadwal verifikasi. Source Docker HEAD 486e430; perubahan SSO/Yudisium yang di-stash tidak disentuh.
- [x] Baseline tes review/riwayat: 31 tes / 282 assertions lulus.
- [x] RED: uji pembatalan mempertahankan berkas, warna, catatan/snapshot; UI/modal; batas waktu dan halaman stale; permission; versi/double click; lifecycle sesudah pembatalan; audit mahasiswa/petugas; pengajuan aktif lain.
- [x] GREEN: tambah helper aturan waktu dan transaksi pembatalan di SidangTaRegistrationReview; controller, partial tombol/modal dan label/tone riwayat.
- [x] Review independen dan regresi; integrasi terarah dua folder dengan backup; rescan akhir dan command copy.

Transaksi mengunci Student bila ada, lalu pengajuan NIM yang sama dalam urutan ID, kemudian periode. Pemeriksaan versi mencegah pembatalan dari halaman lama/double click; pengajuan aktif lain pada NIM yang sama menyebabkan penolakan agar tidak muncul dua pengajuan aktif. Data lama tetap dapat dikoreksi meski record master Student sudah tidak tersedia. Aturan keputusan/revisi yang sudah ada tidak diubah.

Tidak perlu perubahan skema, migrasi, seed, impor ulang, atau perubahan data massal. Hanya aksi pembatalan yang diklik petugas akan mengubah status satu pengajuan. Aplikasi/server aktif tidak diakses pada sesi ini; tes hanya menggunakan dadu_testing.

## Pemakaian admin/petugas

1. Buka Form → Review Sidang TA → Tinjau pengajuan berstatus Disetujui.
2. Selama jadwal Verifikasi Berkas lengkap, aktif dan sedang berlangsung, klik **Batalkan Persetujuan**, lalu konfirmasikan pada modal.
3. Pengajuan kembali **Menunggu peninjauan**. Berkas yang sudah terverifikasi tetap hijau; file, identitas, snapshot, catatan, waktu kirim dan ID pengajuan tidak berubah.
4. Bila satu berkas ternyata perlu diperbaiki, tandai hanya berkas tersebut sebagai perlu perbaikan, lalu gunakan Minta Revisi seperti alur sebelumnya. Berkas lain tetap terverifikasi. Jika berkas sudah benar, pengajuan juga dapat ditinjau/disetujui kembali.
5. Setelah tenggat, sebelum jadwal dimulai, saat checkbox revisi dimatikan, atau tanggal belum lengkap: pembatalan dinonaktifkan beserta alasannya. POST dari tab lama juga ditolak tanpa mengubah status.

Riwayat approval awal tetap ada. Riwayat baru menandai **Persetujuan dibatalkan admin; menunggu peninjauan ulang**. Audit petugas memuat pelaku/waktu; riwayat mahasiswa tetap tidak memuat identitas pelaku. Ini membatalkan persetujuan keseluruhan saja, tidak menambahkan pembatalan penolakan atau permintaan revisi.

Jika mahasiswa sudah mempunyai pengajuan lain berstatus submitted/needs_review, pembatalan ditolak (409) dan keputusan lama tetap Disetujui. Tidak menghapus/menimpa pengajuan lain maupun draft. Double click/versi lama tidak menambah pembatalan kedua.

## File hasil perubahan

```text
app/Services/SidangTaRegistrationReview.php
app/Services/SidangTaHistoryPresenter.php
app/Http/Controllers/SidangTaRegistrationController.php
resources/views/sidang_ta_registrations/admin/show.blade.php
resources/views/sidang_ta_registrations/admin/partials/approval-cancellation.blade.php
resources/views/sidang_ta_registrations/history.blade.php
tests/Feature/SidangTaApprovalCancellationTest.php
docs/107-pembatalan-persetujuan-sidang-ta.md
```

## Verifikasi

- RED: **17 gagal / 5 lulus** sebelum implementasi; kegagalan mencakup aksi/tombol pembatalan, aturan waktu, riwayat, dan lifecycle.
- GREEN awal: **91 tes / 737 assertions lulus**, mencakup pembatalan, review lama, riwayat, akses dosen baca prodi, dan ekspor Excel.
- Review independen tidak menemukan masalah. Pengujian permintaan ganda dilakukan secara berurutan dengan versi lama; urutan lock dan serialisasi transaksi ditinjau pada kode, bukan simulasi multiproses.
- Lint PHP, whitespace, kompilasi Blade, dan rescan akhir lulus. Command copy diuji pada target sementara, termasuk pengulangan.

## Command copy development → repo Docker laptop

Delapan file fitur sama pada development dan repo Docker laptop. Tidak menyalin config, route, seeder, `.env`, atau file SSO. Untuk menyalin ulang:

```bash
bash <<'DADU_CANCEL_APPROVAL_107'
set -euo pipefail
umask 022
dadu_src=/home/xdranel/Code/Project/php-laravel/dadu
dadu_dst=/home/xdranel/Code/Project/secret/dadu-test
dadu_backup="/tmp/dadu107-copy-$(date +%Y%m%d-%H%M%S)"
dadu_files=(
  app/Services/SidangTaRegistrationReview.php
  app/Services/SidangTaHistoryPresenter.php
  app/Http/Controllers/SidangTaRegistrationController.php
  resources/views/sidang_ta_registrations/admin/show.blade.php
  resources/views/sidang_ta_registrations/admin/partials/approval-cancellation.blade.php
  resources/views/sidang_ta_registrations/history.blade.php
  tests/Feature/SidangTaApprovalCancellationTest.php
  docs/107-pembatalan-persetujuan-sidang-ta.md
)
for dadu_file in "${dadu_files[@]}"; do test -f "$dadu_src/$dadu_file"; done
for dadu_file in "${dadu_files[@]}"; do
  if [ -f "$dadu_dst/$dadu_file" ]; then
    mkdir -p "$dadu_backup/$(dirname "$dadu_file")"
    cp -p "$dadu_dst/$dadu_file" "$dadu_backup/$dadu_file"
  fi
  mkdir -p "$dadu_dst/$(dirname "$dadu_file")"
  cp -p "$dadu_src/$dadu_file" "$dadu_dst/$dadu_file"
done
git -C "$dadu_dst" diff --check -- "${dadu_files[@]}"
printf 'Copy selesai. Backup: %s\n' "$dadu_backup"
git -C "$dadu_dst" status --short
DADU_CANCEL_APPROVAL_107
```

Setelah commit/push dan kode tersedia pada main repo Docker, update `/opt/dadu-test` tetap mengikuti **dokumen78 bagian4**. Tidak perlu migrate:fresh, seed, migrasi skema baru, atau impor ulang untuk pembaruan ini. Pastikan periode pengajuan terkait mempunyai tanggal mulai/selesai Verifikasi Berkas serta checkbox revisi aktif. Aplikasi lama dan data approved tidak diubah massal saat memasang kode; pembatalan hanya terjadi bila reviewer menjalankan aksinya.

Production tidak diakses/deploy pada sesi ini. Kode SSO, hak akses yang sudah diberikan, ekspor106, dan stash Yudisium dipertahankan. Contoh JSON Kehadiran masih ditunggu.

## Hasil akhir dan kondisi development terbaru

- Repo Docker/worktree fitur: **91 tes terkait / 737 assertions lulus**.
- Suite penuh pada basis repo Docker HEAD486e430 + fitur ini: **982 lulus, 42 gagal / 8592 assertions**. Seluruh 42 nama kegagalan sama persis dengan baseline yang sudah dicatat pada dokumen105/106. Tidak menambah kegagalan suite penuh; kegagalan lama tidak diperbaiki oleh fitur ini.
- Development asli setelah penggabungan terbaru: **90 lulus, 1 gagal / 730 assertions** pada paket yang sama. Seluruh tes pembatalan lulus. Satu kegagalan ada pada `tests/Feature/SidangTaProgramReadAccessTest.php`, tes `fresh seed gives Pelaksana scoped viewing without TA review or period management`, baris200: default RolePermissionSeeder tidak lagi memberikan permission `sidang_ta_registration_view_program`.
- Kegagalan seeder tersebut direproduksi pada salinan terisolasi commit development **d51ed98**, tanpa perubahan pembatalan, dengan tes yang sama: **1 gagal / 2 assertions**. Commit itu menggabungkan branch peminjamanRuangan. Karena perubahan seeder berasal dari penggabungan lain, tidak ditimpa oleh fitur107. Assignment role pada database aplikasi tidak diubah. Default seeder Pelaksana pada development perlu ditinjau tersendiri jika akan dipakai untuk fresh/seed; tidak menjalankan seed ulang untuk memasang fitur ini.
- Penggabungan lain masuk saat pekerjaan berjalan: **8 file kode lama berubah dan 10 file kode baru** di development. Pemeriksaan hash pertama menghentikan penyalinan sebelum perubahan apa pun. Integrasi kemudian dibatasi ke delapan file Sidang TA yang tidak bertabrakan; seluruh perubahan penggabungan tersebut dipertahankan.
- Rescan sesudah integrasi: fitur hanya mengubah **5 file kode lama + 2 file kode/tes baru**, ditambah dokumen107, pada tiap folder. File `.gitignore`, `package-lock.json`, `phpunit.xml`, dan semua perubahan di luar daftar fitur dipertahankan. Tidak menerapkan stash Yudisium.
- Modal menggunakan komponen `x-modal` yang sudah ada; tidak menambah JavaScript/dependency. Kompilasi Blade berhasil. Source kedua folder untuk kedelapan file fitur telah dicocokkan.

Log sesi: `/tmp/dadu107-baseline.log`, `/tmp/dadu107-red.log`, `/tmp/dadu107-green1.log`, `/tmp/dadu107-full-suite.log`, `/tmp/dadu107-verified-failures.json`, `/tmp/dadu107-development-gate.log`, `/tmp/dadu107-development-seeder-baseline.log`, `/tmp/dadu107-blade.log`.

Laporan rescan: `/tmp/dadu107-after-rescan.json`; snapshot sebelum integrasi: `/tmp/dadu107-pre-integration-hashes.json`; backup berkas: `/tmp/dadu107-integration-backup`. Command copy diuji melalui `/tmp/dadu107-copy-smoke-{1,2}.log`.

Tidak menjalankan migrasi/seed/reset/impor pada database aplikasi aktif, tidak commit/push/deploy, dan tidak mengakses production/dadu-test server. Seluruh aksi uji status dan seeder berlangsung hanya pada **dadu_testing**.
