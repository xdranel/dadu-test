# 97 — Kolom Periode / SKS dan konfirmasi format KRS asli

## Arahan dan hasil tampilan

Pada 11 November 2026 pengguna mengonfirmasi bahwa semester pada sumber KRS asli adalah term Ganjil/Genap. Pada daftar **Data Akademik → KRS Mahasiswa**, kolom disederhanakan menjadi:

| Header | Contoh isi satu sel |
|---|---|
| Periode / SKS | 2026/2027 pada baris pertama; 3 SKS pada baris kedua |

Perubahan diterapkan pada `resources/views/student_courses/index.blade.php`: header tidak lagi memuat Semester Studi, baris semester studi di sel tabel dihapus, dan baris periode memakai tahun akademik KRS. Tahun atau SKS yang tidak tersedia tetap mempunyai tampilan pengganti; tidak dibuat nilai baru dari contoh 3 SKS.

Ini perubahan tampilan kolom daftar, tanpa mengubah data semester pada database, hubungan periode, maupun perilaku importer. Data semester pada profil mahasiswa tetap terpisah dari term pada JSON KRS. Tidak ada migrasi atau seed yang diperlukan.

## Validasi format B dan C

Konfirmasi pengguna: bentuk asli sumber KRS yang dipakai adalah **B dan C** pada dokumen 95. Contoh A dengan semester studi 9/13 bukan contoh kontrak ekspor KRS asli yang sekarang digunakan.

- **B — JSON datar dengan term:** untuk menambah/memperbarui MK yang kurang. MK yang cocok bisa diperbarui, sedangkan MK yang tidak disertakan tidak dihapus.
- **C — daftar resmi `krs_mata_kuliah`:** dideteksi otomatis. Daftar harus lengkap untuk mahasiswa dan masing-masing periode yang disertakan karena importer menyelaraskan daftar, termasuk menghapus MK lama pada periode yang sama yang tidak tercantum.

C boleh hanya memuat satu mahasiswa, tidak harus seluruh mahasiswa kampus. Yang harus lengkap adalah daftar MK mahasiswa tersebut pada periode yang diimpor. Deteksi otomatis mengenali struktur dan memvalidasi isi, bukan mengambil ulang data ke iGracias untuk membuktikan kelengkapannya.

## Rekomendasi untuk pilihan format lama

**Disarankan menghapus pilihan format lama dari layar impor**, karena sekarang tidak ada kontrak sumber nyata yang membutuhkan arti `semester` sebagai semester studi. Menyimpan pilihan yang tidak dibutuhkan dapat membuat angka 1 dianggap semester studi atau SKS null menjadi nilai bawaan 3 lewat jalur lama.

Alur yang disarankan jika dilanjutkan:

1. JSON datar B memakai Term secara default, dengan pratinjau sebelum penerapan.
2. JSON resmi C tetap dideteksi otomatis, dengan penjelasan bahwa daftar lengkap menggantikan daftar periode yang disertakan.
3. Pilihan semester studi tidak ditampilkan untuk operator.
4. Saat menerapkan penyederhanaan, validasi/default server juga diselaraskan. Tidak cukup hanya menghapus option HTML sementara default server masih mengarah ke mode lama.
5. Jalur backend/fixture lama diperiksa sebelum dipensiunkan; keberadaan fixture bukan bukti bahwa sumber iGracias saat ini mempunyai format semester studi.

**Status pada penyelesaian 97:** penghapusan mode lama masih rekomendasi. **Tindak lanjut yang sudah disetujui:** dokumen 98 mencatat implementasi penghapusan importer lama, deteksi B/C otomatis, penghilangan semester studi dari pratinjau, dan pemindahan kartu bantuan. Data semester internal tetap digunakan. Dokumen 95 sudah diperbarui mengikuti keadaan terbaru.

## Verifikasi

- `php -l` pada view yang berubah: berhasil.
- Pengujian yang sudah ada, `FlatTermKrsImportTest` dan `AcademicPeriodManagementTest`: **21 lulus, 126 assertion**, 12,85 detik pada MySQL `dadu_testing`, `APP_TIMEZONE=UTC`. Termasuk render daftar KRS dan pemisahan roster per periode.
- Tidak menulis test baru yang hanya mengulang perubahan label. Suite penuh tidak diulang untuk perubahan kolom ini; tidak ada perubahan logika import/database.
- Log sementara: `/tmp/dadu97-krs-tests.log`.

## Salin berkas dan memasang perubahan

Berkas lingkup: view daftar KRS, pembaruan dokumen 95, dan dokumen 97 ini. Command berikut untuk mengulang penyalinan dari development ke repo Docker pada laptop jika diperlukan:

```bash
bash <<'DADU_COPY97'
set -euo pipefail
umask 022
SRC=/home/xdranel/Code/Project/php-laravel/dadu
DST=/home/xdranel/Code/Project/secret/dadu-test
files=(
  resources/views/student_courses/index.blade.php
  docs/95-panduan-periode-akademik-dan-memilih-format-krs.md
  docs/97-hasil-penyederhanaan-kolom-krs-dan-konfirmasi-format.md
)
for file in "${files[@]}"; do test -f "$SRC/$file"; done
backup=$(mktemp -d /tmp/dadu97-copy.XXXXXX)
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
DADU_COPY97
```

Jika lokal masih memakai view yang dicache, jalankan `php artisan view:clear` dari folder development. Untuk server Docker, setelah commit/push dan kode tersedia pada branch deployment, gunakan 78 bagian 4 — Update Docker seperti sebelumnya. Tidak perlu `migrate:fresh` atau mengimpor ulang KRS hanya untuk perubahan tampilan kolom ini.

Dokumen 96 tetap menjelaskan Kelola Periode Sidang TA; tidak diubah oleh penyederhanaan kolom KRS. Contoh JSON Kehadiran masih ditunggu.
