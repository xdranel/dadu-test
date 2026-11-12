# 102 — Status BPP, persetujuan iGracias, dan pembimbing pada review Sidang TA

## Perubahan 12 November 2026

Pada **Review Sidang TA → Aksi → Tinjau → Peninjauan Pendaftaran Sidang TA**:

- Bagian **Status akademik** menampilkan **Status BPP** dan **Persetujuan sidang iGracias**.
- Bagian **Judul Tugas Akhir** menampilkan Bahasa Indonesia, Bahasa Inggris, lalu **Pembimbing 1** dan **Pembimbing 2**.
- Status menggunakan badge yang sudah ada: disetujui/lunas hijau, menunggu kuning, ditolak/belum lunas merah, dan data tidak tersedia abu-abu. `APPROVED` ditampilkan sebagai `Disetujui`.

Tambahan hanya diaktifkan pada halaman review petugas. Detail mahasiswa tetap mengikuti tampilan sebelumnya.

## Perlindungan data yang sedang dipakai

Nilai diambil dari `academic_snapshot` yang tersimpan pada pengajuan: `bpp_status`, `sidang_approval_status`, `advisor_1`, dan `advisor_2`. Halaman ini tidak mengambil pengganti dari profil mahasiswa terbaru dan tidak menulis ulang snapshot/pengajuan. Perubahan data sinkronisasi setelah mahasiswa mengirim pengajuan tidak mengubah ringkasan historis tersebut.

Jika snapshot pengajuan lama belum menyimpan salah satu field itu, halaman menampilkan **Belum tersedia**. Tidak menebak nilai atau mengubah data lama agar tampak lengkap.

Perubahan tidak menambah syarat/blokir BPP atau persetujuan iGracias, tidak mengubah keputusan/revisi/periode, dan tidak memerlukan migrasi, seed, atau impor ulang. Kode dan stash Yudisium tidak diterapkan kembali. Tidak ada commit, push, maupun deployment production/server yang dilakukan.

## File yang diubah

```text
resources/views/sidang_ta_registrations/admin/show.blade.php
resources/views/sidang_ta_registrations/partials/detail-academic.blade.php
tests/Feature/SidangTaDetailUiTest.php
```

Dua file Blade dan tes telah disamakan pada development asli `/home/xdranel/Code/Project/php-laravel/dadu` dan repo Docker laptop `/home/xdranel/Code/Project/secret/dadu-test`. Backup sebelum perubahan berada di `/tmp/dadu102-backup`.

## Verifikasi

Pengujian dilakukan pada database **dadu_testing**, bukan database aplikasi:

- Tes baru direproduksi gagal sebelum perubahan karena field belum ditampilkan.
- `SidangTaDetailUiTest`, `SidangTaMeetStaffReviewTest`, dan `SidangTaDocumentPreviewTest`: **48 tes lulus, 513 assertions**.
- Cakupan: snapshot historis, field lama yang kosong, nama pembimbing di-escape, halaman mahasiswa tetap, pengajuan tidak berubah saat dibuka, permission/review, serta preview dokumen.
- Hasil sesi tersimpan di `/tmp/dadu102-red.log` dan `/tmp/dadu102-green.log`.

Tidak menjalankan ulang suite penuh untuk perubahan tampilan terbatas ini. Kegagalan baseline suite penuh yang pernah dicatat sebelumnya tidak dinyatakan sudah terselesaikan.

## Command copy development → repo Docker laptop

Kode sudah disalin pada sesi ini. Untuk menyalin ulang perubahan102, jalankan blok berikut. Daftarnya hanya memuat tiga file di atas dan dokumen102; tidak mengambil file Yudisium dari stash.

```bash
bash <<'DADU_REVIEW_TA_102'
set -euo pipefail
umask 022
dadu_src=/home/xdranel/Code/Project/php-laravel/dadu
dadu_dst=/home/xdranel/Code/Project/secret/dadu-test
dadu_backup="/tmp/dadu102-copy-$(date +%Y%m%d-%H%M%S)"
dadu_files=(
  resources/views/sidang_ta_registrations/admin/show.blade.php
  resources/views/sidang_ta_registrations/partials/detail-academic.blade.php
  tests/Feature/SidangTaDetailUiTest.php
  docs/102-status-bpp-igracias-dan-pembimbing-review-sidang-ta.md
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
DADU_REVIEW_TA_102
```

Setelah perubahan102 di-commit/push dan tersedia pada main repo Docker, pembaruan server tetap mengikuti **dokumen78 bagian4 / dokumen64 bagian12.2**. Tidak perlu `migrate:fresh --seed` untuk perubahan ini. Update Docker membangun image baru dan membuat ulang app/web sehingga Blade terbaru terbaca.
