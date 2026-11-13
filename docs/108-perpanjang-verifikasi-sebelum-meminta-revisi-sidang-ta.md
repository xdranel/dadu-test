# 108 — Perpanjang verifikasi sebelum membuka kembali dan meminta revisi Sidang TA

## Perilaku akhir

**Perpanjang jadwal terlebih dahulu. Tidak ada revisi tanpa tenggat atau pengecualian waktu per mahasiswa.** Rancangan sementara tersebut sudah dibuang, termasuk migrasi dan kolomnya; tidak dipasang ke database aplikasi.

- Saat jadwal Verifikasi Berkas lewat/nonaktif/belum dimulai, Minta Revisi tetap terkunci. Tombol **Perbarui Jadwal Verifikasi** membuka popup dengan alasannya.
- Pada pengajuan Ditolak, **Buka Kembali Pengajuan** membuka popup peringatan jika jadwal belum memenuhi. Tidak ada form mutasi pada modal tersebut.
- Popup mengarahkan ke Kelola Periode untuk pemilik hak baca periode. Pengguna lain diminta menghubungi petugas pengelola. Hak edit periode tetap mengikuti permission yang sudah ada.
- Admin memperpanjang/memperbarui tanggal mulai–selesai dan mengaktifkan verifikasi, kemudian memuat ulang halaman peninjauan. Perpanjangan tanggal berlaku untuk seluruh pengajuan dalam periode itu.
- Selama jadwal lengkap, aktif dan sedang berlangsung, petugas dapat membuka kembali penolakan otomatis maupun manual, melalui konfirmasi. Status menjadi **Menunggu peninjauan**, lalu petugas dapat memakai Minta Revisi, Tolak, Setujui seperti biasa.
- Pengajuan ditolak tidak dipulihkan massal hanya karena jadwal diperpanjang. Petugas tetap memilih pengajuan yang dibuka kembali.

Berkas final, status/warna verifikasi, catatan per dokumen, identitas, snapshot dan waktu kirim dipertahankan. Draft revisi lama yang masih tersisa dihentikan agar versi lama tidak menghalangi revisi baru; file final yang dipinjam tetap aman. File milik draft yang sudah tidak direferensikan dibersihkan setelah transaksi commit. Draft yang sudah dihapus scheduler tidak dipulihkan; mahasiswa memulai revisi baru dari dokumen final, sehingga berkas terverifikasi tidak perlu diunggah ulang.

## Penegakan server dan audit

Endpoint keputusan tetap memerlukan permission Review. Pembukaan kembali memeriksa status ditolak, versi pengajuan, pengajuan aktif lain pada NIM yang sama, serta periode terkini di dalam transaksi. Tab lama/POST buatan tidak dapat melewati tenggat. Batas awal/akhir inklusif. Mahasiswa dan dosen baca prodi tidak mendapat hak mutasi.

Riwayat penolakan dipertahankan dan kejadian `reopened` ditambahkan. Riwayat mahasiswa tetap tidak menampilkan identitas pelaku. Pembatalan persetujuan107 tetap bekerja seperti sebelumnya.

Helper tenggat, scheduler auto-tolak, cleanup draft, model dan skema **tidak diubah** oleh fitur108 versi akhir. Setelah tenggat hasil perpanjangan lewat, revisi kembali terkunci, penolakan otomatis dan cleanup kembali berlaku seperti biasa.

## Verifikasi dan batas hasil

- RED: 10 gagal/4 lulus sebelum implementasi final (SQLite), karena popup dan pembukaan kembali belum tersedia.
- Paket terkait: **61 tes / 458 assertions lulus**. Meliputi 14 kasus baru, review petugas, jendela verifikasi, cleanup dan riwayat. Uji unggah berkas berhasil saat jadwal telah diperpanjang dan ditolak setelah tenggat.
- Suite penuh SQLite sebelum perubahan: **757 lulus / 267 gagal**. Sesudah: **773 lulus / 265 gagal**. Perbandingan nama tes tidak menemukan kegagalan tambahan. Dua kasus baseline lulus pada pengulangan; tidak dinyatakan sebagai perbaikan modul lain.
- Banyak kegagalan baseline berasal dari foreign key lama `student_courses` → `courses` pada SQLite dan ketidaksesuaian pengujian lama. [Daftar seluruh kegagalan dan perbandingannya](108-hasil-perbandingan-tes-sqlite.txt).
- MySQL testing port3307 tidak tersedia pada sesi ini. Instance MariaDB privat sempat dicoba, tetapi migrasi FK lama dosen/mata kuliah tidak kompatibel; instance itu telah dimatikan. Pengujian final memakai **SQLite in-memory**, bukan klaim pengujian MySQL8. Locking multiproses MySQL belum diuji ulang di lingkungan ini.
- Cache Blade worktree dibersihkan setelah rancangan sementara dibuang; hasil suite final di atas diambil sesudah pembersihan. Pemeriksaan kode dilakukan langsung; review agen independen tidak tersedia pada sesi ini.

## File fitur108

```text
app/Services/SidangTaRegistrationReview.php
app/Services/SidangTaHistoryPresenter.php
app/Http/Controllers/SidangTaRegistrationController.php
resources/views/sidang_ta_registrations/admin/show.blade.php
resources/views/sidang_ta_registrations/admin/partials/registration-decision.blade.php
resources/views/sidang_ta_registrations/admin/partials/registration-reopening.blade.php
resources/views/sidang_ta_registrations/admin/partials/verification-window-warning.blade.php
resources/views/sidang_ta_registrations/history.blade.php
tests/Feature/SidangTaVerificationExtensionTest.php
docs/108-perpanjang-verifikasi-sebelum-meminta-revisi-sidang-ta.md
docs/108-hasil-perbandingan-tes-sqlite.txt
```

Fitur107 belum dicommit pada repo Docker saat pekerjaan ini dimulai. Pertahankan/sertakan file107 yang sudah ada, terutama partial `approval-cancellation`, tes pembatalan dan dokumen107. Command berikut menyalin paket107+108 yang diperlukan. File SSO, seeder/permission, config, route, dan perubahan ekspor lokal tidak ikut disalin.

## Command copy development → repo Docker laptop

```bash
bash <<'DADU_VERIFY_EXTENSION_108'
set -euo pipefail
umask 022
dadu_src=/home/xdranel/Code/Project/php-laravel/dadu
dadu_dst=/home/xdranel/Code/Project/secret/dadu-test
dadu_backup="/tmp/dadu108-copy-$(date +%Y%m%d-%H%M%S)"
dadu_files=(
  app/Services/SidangTaRegistrationReview.php
  app/Services/SidangTaHistoryPresenter.php
  app/Http/Controllers/SidangTaRegistrationController.php
  resources/views/sidang_ta_registrations/admin/show.blade.php
  resources/views/sidang_ta_registrations/admin/partials/registration-decision.blade.php
  resources/views/sidang_ta_registrations/admin/partials/registration-reopening.blade.php
  resources/views/sidang_ta_registrations/admin/partials/verification-window-warning.blade.php
  resources/views/sidang_ta_registrations/history.blade.php
  tests/Feature/SidangTaVerificationExtensionTest.php
  docs/108-perpanjang-verifikasi-sebelum-meminta-revisi-sidang-ta.md
  docs/108-hasil-perbandingan-tes-sqlite.txt
  resources/views/sidang_ta_registrations/admin/partials/approval-cancellation.blade.php
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
  cp "$dadu_src/$dadu_file" "$dadu_dst/$dadu_file"
  chmod 0644 "$dadu_dst/$dadu_file"
done
git -C "$dadu_dst" diff --check -- "${dadu_files[@]}"
printf 'Copy selesai. Backup: %s\n' "$dadu_backup"
git -C "$dadu_dst" status --short
DADU_VERIFY_EXTENSION_108
```

## Update aplikasi

Untuk fitur108 versi akhir **tidak perlu migration/fresh/seed atau impor ulang**. Sesudah commit/push dan kode tersedia di main repo Docker, update server mengikuti **dokumen78 bagian4**. Sertakan perubahan107 yang belum dicommit dalam paket commit/push. Untuk lokal, muat ulang halaman setelah kode terpasang; jika ada cache view, gunakan `php artisan view:clear` dari root development.

Production dan dadu-test server tidak diakses atau dideploy pada sesi ini. Contoh JSON Kehadiran masih ditunggu.

## Integrasi dan pemeriksaan akhir

- **61 tes / 458 assertions lulus di kedua folder** pada paket terkait yang sama: popup/reopen, review petugas, jendela verifikasi, cleanup dan riwayat. Pengujian menggunakan SQLite in-memory; hasil ini tidak dinyatakan sebagai pengujian MySQL8.
- Lint PHP, whitespace, dan kompilasi Blade berhasil. Command copy diuji pada target sementara untuk pemasangan pertama dan pengulangan, termasuk dependensi107 yang belum dicommit.
- Rescan: fitur108 mengubah **6 file kode yang sudah ada + 3 file kode/tes baru**, ditambah panduan dan laporan tes. Semua file fitur108 cocok pada development dan repo Docker laptop.
- Model, migrasi, helper tenggat, scheduler, file SSO, config/route/permission/seeder, dan perubahan ekspor lokal tidak ditimpa. Perubahan107 yang masih pending tetap dipertahankan.
- Backup integrasi: `/tmp/dadu108-strict-integration-backup`; laporan rescan: `/tmp/dadu108-final-rescan.json`.
- Log: `/tmp/dadu108-final-focused.log`, `/tmp/dadu108-original-final-focused.log`, `/tmp/dadu108-full-baseline.log`, `/tmp/dadu108-full-feature-clean.log`, `/tmp/dadu108-final-failure-comparison.json`, `/tmp/dadu108-blade-final.log`, dan `/tmp/dadu108-copy-final-{1,2}.log`.

Tidak melakukan commit, push, deploy, atau perubahan database aplikasi aktif pada sesi ini. Instance MariaDB percobaan sudah dimatikan. Untuk memasang kode server gunakan alur update yang biasa, tanpa fresh/seed.
