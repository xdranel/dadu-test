# 103 — Batas upload Sidang TA 5 MB dan berkas lama

## Keputusan dan perubahan

Pada 12 November 2026 pengguna memilih **5 MB per berkas** untuk unggahan mahasiswa pada pendaftaran dan revisi Sidang TA.

Batas baru diterapkan pada validasi backend, pemilih/drag-and-drop browser, petunjuk form, dan pesan error. Format tetap PDF. Ukuran tepat 5 MB diterima; di atas 5 MB ditolak. Batas backend memakai 5120 KB, selaras dengan browser 5×1024×1024 byte.

Nilai utama berada pada `SidangTaMeetRequirements::MAX_UPLOAD_MB`; batas KB diturunkan dari konstanta tersebut. Blade meneruskan batas ke komponen browser agar petunjuk dan validasinya sama.

## Penanganan berkas lama

- Berkas yang sudah tersimpan tidak dihapus, diperkecil, atau ditandai tidak valid karena ukurannya melebihi5 MB.
- Draft pendaftaran yang sudah memiliki berkas 8 MB tetap bisa dilanjutkan, ditinjau, dan dikirim.
- Berkas final terverifikasi tetap dipakai pada revisi, walaupun ukurannya di atas 5 MB.
- Berkas pengganti revisi yang sudah tersimpan sebelum perubahan juga tetap dapat ditinjau dan dikirim.
- Jika memilih berkas baru untuk mengganti/mengunggah, batas 5 MB berlaku. Unggahan yang ditolak tidak mengganti metadata, menaikkan versi draft, atau menghapus file lama.
- Kartu berkas lama di atas 5 MB menjelaskan bahwa batas baru berlaku untuk unggahan baru/pengganti. Catatan kewajiban revisi tetap berlaku jika petugas meminta dokumen tersebut diperbaiki.

Pemeriksaan tinjau/finalisasi tetap memastikan dokumen ada, lengkap, dan sesuai kontrak pengajuan. Tidak menambahkan pemeriksaan ulang ukuran berkas lama. Perubahan tidak mengubah persyaratan akademik, periode, keputusan, atau hak akses.

Batas ini khusus unggahan mahasiswa Sidang TA. Panduan form admin dan Yudisium mempunyai jalur terpisah; stash Yudisium tidak diterapkan kembali. Batas global PHP/Docker tidak diturunkan agar unggahan lain dan pengiriman beberapa berkas sekaligus tetap berjalan.

## File yang diubah

```text
app/Support/SidangTaMeetRequirements.php
app/Http/Requests/SaveSidangTaDraftStepRequest.php
resources/views/sidang_ta_registrations/partials/wizard-upload.blade.php
resources/js/sidang-ta-wizard.js
tests/Unit/SidangTaMeetRequirementsTest.php
tests/Feature/SidangTaDraftUploadTest.php
tests/Feature/SidangTaMeetFinalizationTest.php
tests/Feature/SidangTaTargetedRevisionTest.php
tests/Js/sidang-ta-wizard.test.mjs
```

File disamakan pada development asli `/home/xdranel/Code/Project/php-laravel/dadu` dan repo Docker laptop `/home/xdranel/Code/Project/secret/dadu-test`. Backup awal: `/tmp/dadu103-upload-backup`.

## Verifikasi

Tes menggunakan database **dadu_testing**, bukan database aplikasi.

- RED sebelum perubahan: backend masih menerima unggahan di atas 5 MB; browser masih memakai 10 MB; petunjuk lama belum sesuai.
- Gate `SidangTaMeetRequirementsTest`, `SidangTaDraftUploadTest`, `SidangTaMeetFinalizationTest`, `SidangTaTargetedRevisionTest`, dan `SidangTaWizardUiTest`: **77 tes lulus, 1113 assertions**.
- Satu tes tambahan untuk pengganti revisi 8 MB yang sudah tersimpan: **1 tes lulus, 20 assertions**. Total dua eksekusi: **78 tes terkait lulus, 1133 assertions**.
- JavaScript: **24/24 lulus**, termasuk tepat 5 MB, lebih satu byte, drag-and-drop, dan batas dari server.
- Build Vite, kompilasi Blade, dan lint PHP berhasil. Tidak ada perubahan DB aplikasi, migrasi, seed, commit, push, atau deployment server.

Log sesi: `/tmp/dadu103-red.log`, `/tmp/dadu103-green-final.log`, `/tmp/dadu103-saved-revision.log`, `/tmp/dadu103-js-green.log`, `/tmp/dadu103-build.log`, dan `/tmp/dadu103-blade.log`.

## Copy development → repo Docker laptop

Kode sudah disalin dalam sesi ini. Blok berikut menyalin ulang hanya file 103 beserta catatannya, dengan backup:

```bash
bash <<'DADU_UPLOAD_LIMIT_103'
set -euo pipefail
umask 022
dadu_src=/home/xdranel/Code/Project/php-laravel/dadu
dadu_dst=/home/xdranel/Code/Project/secret/dadu-test
dadu_backup="/tmp/dadu103-copy-$(date +%Y%m%d-%H%M%S)"
dadu_files=(
  app/Support/SidangTaMeetRequirements.php
  app/Http/Requests/SaveSidangTaDraftStepRequest.php
  resources/views/sidang_ta_registrations/partials/wizard-upload.blade.php
  resources/js/sidang-ta-wizard.js
  tests/Unit/SidangTaMeetRequirementsTest.php
  tests/Feature/SidangTaDraftUploadTest.php
  tests/Feature/SidangTaMeetFinalizationTest.php
  tests/Feature/SidangTaTargetedRevisionTest.php
  tests/Js/sidang-ta-wizard.test.mjs
  docs/103-batas-upload-sidang-ta-5mb-dan-kompatibilitas-berkas-lama.md
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
printf 'Copy selesai. Backup: %s\n' "$dadu_backup"
git -C "$dadu_dst" diff --check -- "${dadu_files[@]}"
git -C "$dadu_dst" status --short
DADU_UPLOAD_LIMIT_103
```

## Memasang pembaruan

Lokal development sudah dibangun asetnya pada sesi ini. Tidak perlu menjalankan migrasi/reset/seed untuk perubahan 103.

Setelah commit/push dan perubahan tersedia pada main repo Docker, gunakan **dokumen 78 bagian 4 / dokumen 64 bagian 12.2** seperti sebelumnya. Build dan recreate app/web diperlukan agar backend dan bundle JavaScript baru sama-sama terbaca. Halaman yang sudah terbuka sebelum update bisa masih menunjukkan petunjuk lama sampai dimuat ulang; backend tetap menolak unggahan baru di atas 5 MB setelah kode baru terpasang.

Production/server belum diperbarui oleh asisten. Pengingat terpisah: contoh JSON Kehadiran masih ditunggu.
