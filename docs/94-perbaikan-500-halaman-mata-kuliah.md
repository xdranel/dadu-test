# 94 — Perbaikan 500 pada halaman Mata Kuliah

## Penyebab terkonfirmasi

Halaman yang dilaporkan adalah `/dashboard/courses`. Log server yang sudah disaring mengonfirmasi `UrlGenerationException` pada `courses.show`, parameter `course` kosong, dari view `resources/views/courses/index.blade.php`. Exception ini sama persis dengan reproduksi pada database testing.

Reproduksi lokal dengan database testing sudah membuktikan satu bug: KRS dapat memiliki kode MK yang belum tercatat pada tabel master `courses`. `CourseController` memang membentuk baris daftar dari KRS tersebut dengan `id = null`. Akan tetapi, view sebelumnya tetap membentuk URL Lihat/Edit/Hapus yang memerlukan ID master. Akibatnya seluruh halaman menjadi 500 dengan pesan:

```text
Missing required parameter for [Route: courses.show]
[URI: dashboard/courses/{course}] [Missing parameter: course].
```

Kondisi data ini menjelaskan mengapa lokal dapat dibuka sementara server gagal: kode view sama dapat berperilaku berbeda bila kelengkapan master MK berbeda. Pada mode Term datar, impor KRS menyimpan pendaftaran MK tanpa otomatis membuat data master MK; jalur format lama dapat membuat master sementara. Perbedaan mode dijelaskan pada dokumen 95. Error server sudah cocok; perbaikan tetap perlu dipasang agar runtime server menggunakannya.

## Perubahan

Pada `resources/views/courses/index.blade.php`, tombol aksi hanya dibentuk jika ID master tersedia. Baris KRS tanpa master tetap tampil dengan keterangan **Master mata kuliah belum tersedia**. Aksi pada baris dengan master tetap berfungsi dan mengikuti permission seperti sebelumnya. Perbaikan ini tidak membuat master rekaan, menghapus KRS, atau mengubah database; tidak memerlukan migrasi maupun seed ulang.

Tes regresi `tests/Feature/CourseIndexMissingMasterTest.php` mencakup:

- Daftar tetap HTTP 200 saat KRS tidak mempunyai master MK, tanpa membuat master otomatis.
- Campuran baris tanpa master dan master valid tetap dapat ditampilkan; aksi master yang valid tersedia.

## Diagnosis singkat pada Docker dadu-test

Buka ulang halaman yang gagal, lalu jalankan dari server. Hasil maksimal delapan baris, bukan keseluruhan stack:

```bash
cd /opt/dadu-test
docker compose --env-file .env.docker logs --since 5m --tail 300 app 2>&1 \
  | grep -E 'ERROR|Missing required|SQLSTATE|Exception|View:' | tail -n 8
```

Jika pesannya berbeda, telusuri exception tersebut sebelum menganggap patch ini menyelesaikan 500 server. Jangan melakukan `migrate:fresh` untuk mencoba mengatasi masalah halaman ini.

## Salin dari development ke repo dadu-test di laptop

Penyalinan hanya untuk berkas perbaikan ini; bukan menimpa seluruh folder. Jalankan Bash berikut jika perlu mengulang penyalinan setelah mengedit di development:

```bash
bash <<'DADU_COPY94'
set -euo pipefail
umask 022
SRC=/home/xdranel/Code/Project/php-laravel/dadu
DST=/home/xdranel/Code/Project/secret/dadu-test
files=(
  resources/views/courses/index.blade.php
  tests/Feature/CourseIndexMissingMasterTest.php
  docs/94-perbaikan-500-halaman-mata-kuliah.md
)
for file in "${files[@]}"; do test -f "$SRC/$file"; done
backup=$(mktemp -d /tmp/dadu94-copy.XXXXXX)
cd "$DST"
for file in "${files[@]}"; do
  if test -f "$file"; then cp --parents -- "$file" "$backup/"; fi
done
printf 'Backup: %s\n' "$backup"
cd "$SRC"
cp --parents -- "${files[@]}" "$DST/"
for file in "${files[@]}"; do cmp -- "$SRC/$file" "$DST/$file"; done
git -C "$DST" diff --check
git -C "$DST" status --short
DADU_COPY94
```

## Memasang di server

Setelah commit/push dan perbaikan tersedia pada branch deployment, gunakan [78 bagian 4 — Update Docker](78-hasil-implementasi-meeting-mahasiswa-petugas-it-panel.md#4-update-docker). Lalu buka kembali `/dashboard/courses`. Tidak perlu mengaktifkan `APP_DEBUG` pada server. Asisten tidak melakukan commit/push/deploy atau perubahan DB aplikasi.

## Pengujian

Dua tes reproduksi gagal sebelum patch karena parameter `course` kosong. Setelah patch, gate Mata Kuliah/KRS/periode lulus **23 tes, 138 assertion**. PHP lint pada kedua berkas PHP/Blade terkait berhasil. Suite lengkap MySQL `dadu_testing` dengan `APP_TIMEZONE=UTC`: **917 lulus, 43 gagal, 8.039 assertion** (92,76 detik). Seluruh 43 judul kegagalan dan lokasi test sama persis dengan suite sebelum patch (**915 lulus, 43 gagal**); tidak ada kegagalan baru. Suite penuh tetap merah karena kegagalan baseline yang sudah dicatat pada 93.

Dua tes regresi kembali lulus setelah penyalinan ke development asli (**2 tes, 12 assertion**).

Tiga berkas lingkup telah disamakan di development asli dan repo dadu-test laptop; perubahan lain dipertahankan. Backup sebelum penyalinan development: `/tmp/dadu94-integration-backup-rvp2hkju`. `git diff --check` bersih dan ketiga blok Bash pada panduan lolos `bash -n`. Penyalinan ini belum memasang patch pada server Docker.

Log sementara: `/tmp/dadu94-red.log`, `/tmp/dadu94-green.log`, `/tmp/dadu94-full.log`.

## Pengingat data berikutnya

Contoh JSON Kehadiran masih ditunggu; berkas KRS bukan contoh Kehadiran. Kebutuhan Form Yudisium tetap tercatat pada dokumen 91.
