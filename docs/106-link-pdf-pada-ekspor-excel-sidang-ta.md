# 106 — Tautan berkas pada ekspor Excel Sidang TA

Ekspor pada Form → Review Sidang TA mempertahankan 57 kolom, urutan, filter, data snapshot, status, dan catatan. Pada kolom Ketersediaan setiap jenis dokumen, berkas tersedia menjadi tautan `Lihat PDF` menuju preview berkas yang sama di aplikasi. Berkas tidak ada, tidak ditemukan, atau tidak berlaku tetap memakai keterangannya tanpa tautan.

Tautan menggunakan route preview yang telah dilindungi login dan otorisasi. Berkas tetap privat, tanpa menyalin ke public storage atau membuat akses tanpa login. Tidak mengubah hak ekspor; dosen baca prodi tetap mengikuti fitur105. Tautan tidak memakai masa kedaluwarsa signed URL.

Implementasi terarah: gunakan FormulaCell HYPERLINK yang dibuat aplikasi hanya untuk berkas tersedia, dengan label tetap dan URL route, serta gaya biru bergaris bawah. Data lain tetap di-escape sebagai teks agar input yang menyerupai formula tidak dijalankan. Tidak mengubah library/dependency.

Verifikasi: tes XLSX nyata untuk kedua cabang biodata/EPRT dan sembilan jenis unggahan; kasus metadata/berkas tidak ada; jumlah kolom; formula input tetap literal; URL menuju dokumen yang tepat dan akses privat. Baseline ekspor: 10 tes / 96 assertions lulus. RED: empat kasus gagal sebelum perubahan karena belum ada hyperlink. GREEN: paket ekspor, daftar, pembaca prodi dosen, dan preview dokumen lulus **62 tes / 542 assertions**. Suite penuh diperiksa terpisah untuk kegagalan baseline.

## Berkas perubahan

```text
app/Services/SidangTaReviewExport.php
tests/Feature/SidangTaReviewExportTest.php
docs/106-link-pdf-pada-ekspor-excel-sidang-ta.md
```

Seluruh perubahan terpusat pada ekspor. Tidak mengubah route, policy, login SSO, role/permission, periode, snapshot, status, maupun berkas mahasiswa. Helper `row()` tetap menghasilkan data status biasa untuk pemanggil internal; tautan hanya dipasang saat membuat XLSX.

Tautan ditampilkan biru bergaris bawah pada Excel dan membuka PDF melalui browser. Penerima laporan harus login dengan akun yang memang diizinkan melihat pengajuan tersebut. Tidak ada berkas PDF yang disematkan di dalam Excel. Ekspor baru akan memuat tautan; file Excel yang sudah diunduh sebelumnya tidak berubah.

## Copy development → repo Docker laptop

Ketiga file telah disamakan pada folder development dan repo Docker laptop. Command ini untuk menyalin ulang:

```bash
bash <<'DADU_EXCEL_PDF_106'
set -euo pipefail
umask 022
dadu_src=/home/xdranel/Code/Project/php-laravel/dadu
dadu_dst=/home/xdranel/Code/Project/secret/dadu-test
dadu_backup="/tmp/dadu106-copy-$(date +%Y%m%d-%H%M%S)"
dadu_files=(
  app/Services/SidangTaReviewExport.php
  tests/Feature/SidangTaReviewExportTest.php
  docs/106-link-pdf-pada-ekspor-excel-sidang-ta.md
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
DADU_EXCEL_PDF_106
```

Setelah commit/push dan kode tersedia pada main repo Docker, update `/opt/dadu-test` tetap mengikuti **dokumen78 bagian4**. Tidak perlu migrasi/seed/impor ulang untuk fitur ini. Setelah update, unduh ulang Excel dari Review Sidang TA untuk memperoleh tautannya. Tidak melakukan commit, push, atau deploy pada sesi ini.

Pengingat: contoh JSON kehadiran masih menunggu data sumber.

## Hasil verifikasi akhir

- Ekspor dari development asli: **14 tes / 175 assertions lulus**.
- Paket terkait ekspor/daftar/prodi/preview: **62 tes / 542 assertions lulus**.
- Suite penuh: **960 lulus, 42 gagal / 8408 assertions**. Seluruh 42 nama kegagalan sama persis dengan baseline yang sudah diverifikasi pada dokumen105; tidak ada kegagalan tambahan dari fitur ini. Daftar kegagalan lama ada pada [dokumen105](105-akses-baca-sidang-ta-prodi-dosen.md#nama-kegagalan-lama-yang-dikonfirmasi-pada-baseline-dan-hasil-akhir).
- Lint PHP dan whitespace lulus pada kedua folder. Sintaks command copy serta copy pertama/pengulangan berhasil diuji pada target sementara.
- Rescan awal/akhir: hanya dua file kode/tes di atas berubah; 44 perbedaan antarrepo yang sudah ada dan 12 file khusus development tetap dipertahankan. Tidak mengubah JavaScript/Blade/dependency sehingga tidak memerlukan perubahan aset.
- Backup integrasi: `/tmp/dadu106-integration-backup`. Laporan rescan: `/tmp/dadu106-after-rescan.json`.
- Log: `/tmp/dadu106-baseline.log`, `/tmp/dadu106-red.log`, `/tmp/dadu106-green.log`, `/tmp/dadu106-development-export.log`, `/tmp/dadu106-full-suite.log`, dan `/tmp/dadu106-verified-failures.json`.

Pengujian hanya memakai **dadu_testing**, bukan database aplikasi aktif. Berkas sumber dan catatan telah disalin, sedangkan commit/push/update Docker dilakukan pengguna seperti alur sebelumnya.
