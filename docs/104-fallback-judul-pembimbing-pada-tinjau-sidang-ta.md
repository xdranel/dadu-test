# 104 — Judul dan pembimbing dari Data Akademik terbaru pada detail Sidang TA

## Perilaku yang disepakati

Perubahan hanya untuk tampilan **detail pengajuan mahasiswa** dan **tinjau pengajuan petugas**, termasuk pengajuan lama yang dibuka dari daftar/riwayat pendaftaran.

Empat field yang ditangani:

- Judul TA Bahasa Indonesia.
- Judul TA Bahasa Inggris.
- Pembimbing 1.
- Pembimbing 2.

Aturan diterapkan **per field**:

1. Jika snapshot pengajuan sudah mempunyai nilai, tampilkan nilai itu.
2. Jika field snapshot kosong, ambil nilainya dari Data Akademik terbaru untuk NIM pengajuan tersebut.
3. Jika sumber terbaru juga kosong/tidak ada, tampilkan **Belum tersedia**.

Nilai yang berasal dari sumber terbaru diberi keterangan:

> Data Akademik terbaru; belum tercatat saat pengajuan.

Yang dianggap kosong adalah field tidak ada, `null`, string kosong/spasi, `-`, atau `Belum tersedia`. Jika judul sudah tercatat tetapi pembimbing belum, hanya pembimbing yang memakai sumber terbaru.

## Contoh kasus impor setelah pendaftaran

Mahasiswa mengirim saat judul/pembimbing belum tersedia. Admin kemudian mengimpor JSON atau melengkapi Data Akademik. Setelah halaman detail/tinjau dimuat ulang, judul/pembimbing terbaru akan tampil pada field yang sebelumnya kosong.

Tidak memerlukan kirim ulang, pendaftaran ulang, atau perubahan snapshot. Jika data terbaru berubah lagi, field yang snapshot-nya masih kosong mengikuti sumber terbaru ketika halaman dibuka. Field yang sudah terisi di snapshot tetap memakai nilai historisnya.

## Lingkup dan perlindungan data

- Hanya membaca `student_final_projects` untuk NIM pada pengajuan yang sudah diotorisasi oleh controller.
- Snapshot, status, versi, waktu kirim, riwayat keputusan, dan dokumen tidak ditulis ulang saat halaman dibuka.
- Identitas, IPK/SKS/TAK/EPRT, BPP, persetujuan sidang, serta persyaratan tetap mengikuti perilaku yang sudah ada. Tidak menambahkan blokir judul/pembimbing.
- Pembimbing 1–2 ditampilkan juga pada detail mahasiswa. BPP/persetujuan iGracias tetap mengikuti lingkup tampilan petugas dari dokumen 102.
- Halaman riwayat kejadian tetap berisi timeline status. Perubahan ini ada pada detail/tinjau pengajuannya; tidak membuat kejadian riwayat baru.
- Ekspor tetap menggunakan snapshot tersimpan; perubahan ini hanya tampilan sesuai arahan pengguna.
- Form draft/revisi sudah membaca Data Akademik terbaru melalui alur sebelumnya, sehingga tidak diubah.
- Role pelaksana/read-only ditunda sampai pengguna memintanya. Tidak mengubah role, permission, route, atau SSO.

Perubahan ini memperbarui ketentuan tampilan judul/pembimbing pada dokumen 102: field kosong kini boleh memakai sumber terbaru dengan label. Nilai historis yang sudah tercatat tetap dipertahankan.

## Command diagnosis sementara

Command `sidang-ta:diagnose-snapshot` tidak diperlukan untuk menerapkan fallback tampilan ini. Sesuai arahan pengguna, tiga artefak diagnosis sementara yang belum di-add/commit sudah dihapus dari development dan repo Docker laptop:

```text
app/Console/Commands/DiagnoseSidangTaSnapshot.php
tests/Feature/SidangTaSnapshotDiagnosisTest.php
docs/104-diagnosis-judul-pembimbing-kosong-pada-snapshot-sidang-ta.md
```

Penghapusan memeriksa bahwa file belum tracked dan belum berubah dari versi asisten. Backup sesi tersimpan di `/tmp/dadu104-fallback-backup`. Dokumen 104 yang dipakai sekarang adalah dokumen hasil implementasi ini. Tidak perlu memasang/menjalankan command diagnosis pada production.

## File hasil perubahan

```text
resources/views/sidang_ta_registrations/partials/detail-academic.blade.php
tests/Feature/SidangTaDetailUiTest.php
docs/104-fallback-judul-pembimbing-pada-tinjau-sidang-ta.md
```

Kode disamakan pada `/home/xdranel/Code/Project/php-laravel/dadu` dan `/home/xdranel/Code/Project/secret/dadu-test`. Stash Yudisium tidak diterapkan kembali.

## Verifikasi

Pengujian memakai **dadu_testing**, bukan database aplikasi.

- RED: empat tes baru gagal sebelum perubahan, karena field kosong belum memakai data terbaru dan pembimbing belum tampil pada detail mahasiswa.
- GREEN: `SidangTaDetailUiTest`, `SidangTaMeetStaffReviewTest`, dan `SidangTaDocumentPreviewTest`: **52 tes lulus, 561 assertions**.
- Cakupan: impor data setelah pendaftaran, pembaruan sumber berikutnya, campuran nilai historis/terbaru per field, sumber kosong, nama/judul di-escape, akses mahasiswa lain, snapshot/status/versi tetap, review, serta preview dokumen.
- Lint PHP, pemeriksaan whitespace, dan kompilasi Blade berhasil. Tidak memerlukan build JavaScript karena tidak ada perubahan aset frontend.

Log sesi: `/tmp/dadu104-fallback-red.log`, `/tmp/dadu104-fallback-green.log`, dan `/tmp/dadu104-fallback-blade.log`.

## Command copy development → repo Docker laptop

Perubahan sudah disalin pada sesi ini. Untuk menyalin ulang tiga file hasil perubahan:

```bash
bash <<'DADU_TA_FALLBACK_104'
set -euo pipefail
umask 022
dadu_src=/home/xdranel/Code/Project/php-laravel/dadu
dadu_dst=/home/xdranel/Code/Project/secret/dadu-test
dadu_backup="/tmp/dadu104-fallback-copy-$(date +%Y%m%d-%H%M%S)"
dadu_files=(
  resources/views/sidang_ta_registrations/partials/detail-academic.blade.php
  tests/Feature/SidangTaDetailUiTest.php
  docs/104-fallback-judul-pembimbing-pada-tinjau-sidang-ta.md
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
DADU_TA_FALLBACK_104
```

Tidak perlu migrasi, seed, impor ulang, atau reset database untuk pembaruan ini. Setelah commit/push dan kode tersedia pada main repo Docker, update server tetap mengikuti **dokumen 78 bagian 4 / dokumen 64 bagian 12.2** agar image baru dan Blade terbaru terbaca. Production tidak diubah pada sesi ini.

Pengingat: contoh JSON Kehadiran masih ditunggu.
