# 80 — Posisi tombol dan peringatan pada tinjau Sidang TA

## 1. Pembetulan

Menggunakan cara1 dari permintaan pengguna. Ketiga tombol dipindah ke posisi tepat setelah penjelasan “Pastikan periode, kontak, pembayaran, biodata, dan setiap berkas sudah benar. Anda masih dapat kembali ke form untuk memperbaikinya.” Kedua peringatan tampil setelah baris tombol.

Urutan dalam kartu:

1. Judul “Sudah memeriksa semua data?” dan penjelasan.
2. Kembali ke form, Kembali ke pendaftaran, Kirim Pendaftaran.
3. Peringatan kuning “Penuhi persyaratan akademik dan EPRT sebelum mengirim. Draft dan berkas tetap tersimpan.” bila pengiriman tertahan.
4. Daftar merah “Persyaratan berikut belum terpenuhi:” bila ada alasan yang perlu ditampilkan.

Tombol kirim tetap berada di sisi kanan pada desktop. Susunan responsif dalam baris tombol mengikuti tampilan sebelumnya. Isi pesan, status disabled, pengaman periode/revisi, serta checkbox dalam modal tetap mengikuti aturan yang sudah ada. Peringatan revisi yang kedaluwarsa juga mengikuti posisi baru ini.

## 2. Catatan perubahan dan lokasi hasil

Baseline repo Docker laptop: `af39acf`.

| Berkas | Perubahan |
| --- | --- |
| `resources/views/sidang_ta_registrations/wizard-review.blade.php` | Memindahkan dua blok peringatan ke bawah baris tombol dalam kartu tinjau |
| `tests/Feature/SidangTaFollowup79Test.php` | Menambahkan regresi urutan tampilan dan memastikan pengiriman yang tertahan tetap disabled |

Kedua berkas sudah diperbaiki pada development asli `/home/xdranel/Code/Project/php-laravel/dadu` setelah diuji di worktree terisolasi. Sesuai permintaan serah terima melalui `.md`, kode kedua berkas belum disalin otomatis ke repo Docker laptop `/home/xdranel/Code/Project/secret/dadu-test`. Panduan80 ini tersedia di folder `docs/` repo Docker dan development asli.

## 3. Command copy ke folder dadu-test

Jalankan di laptop setelah memeriksa hasil development asli. Blok ini menyalin tepat dua berkas yang berubah dan membandingkan hasilnya dengan sumber.

```bash
bash <<'COPY_DADU80'
set -euo pipefail
source_dir=/home/xdranel/Code/Project/php-laravel/dadu
target_dir=/home/xdranel/Code/Project/secret/dadu-test
files=(
  resources/views/sidang_ta_registrations/wizard-review.blade.php
  tests/Feature/SidangTaFollowup79Test.php
)
for relative_file in "${files[@]}"; do
  install -m 0644 -- "$source_dir/$relative_file" "$target_dir/$relative_file"
  cmp -- "$source_dir/$relative_file" "$target_dir/$relative_file"
done
git -C "$target_dir" diff --check
git -C "$target_dir" status --short
COPY_DADU80
```

Setelah copy, review diff lalu commit/push seperti biasa. Untuk menerapkan di Docker server, gunakan [panduan78 bagian4 — Update Docker](78-hasil-implementasi-meeting-mahasiswa-petugas-it-panel.md#4-update-docker). Pembetulan ini tidak membutuhkan migration baru, seed, atau impor ulang JSON.

## 4. Tes dan rescan

Tes regresi baru dijalankan sebelum pembetulan dan gagal sesuai masalah: baris tombol berada pada urutan keempat, padahal harus kedua. Setelah perubahan, seluruh `SidangTaFollowup79Test` lulus: **17 tes, 115 assertion**.

Suite lengkap (termasuk seluruh unit test) pada `dadu_testing`: **765 lulus, 30 gagal, 6.929 assertion**. Semua kegagalan berada pada feature test lama, dengan signature kelas/lokasi yang sama seperti hasil79; tidak ada kegagalan baru. Berkas yang masih gagal: `Auth/PasswordResetTest` (3), `Auth/PasswordUpdateTest` (1), `ExamScheduleTest` (4), `GraduationRegistrationTest` (8), `PublicDocumentTest` (2), `QueueTicketTest` (4), dan `SyncTest` (8). Daftar nama tes lama tersedia pada [panduan78 bagian6](78-hasil-implementasi-meeting-mahasiswa-petugas-it-panel.md#6-hasil-otomatis-dan-batas-verifikasi).

Rescan membandingkan template sebelum/sesudah: kedua blok peringatan sama persis, dan setelah perpindahan blok diabaikan seluruh sisa template identik. Class responsif, ID/ARIA, izin tombol, form/modal, serta pengaman pengiriman tetap sama. Syntax PHP tes dan `git diff --check` lulus. Verifikasi posisi memakai hasil render DOM; belum dilakukan pemeriksaan browser interaktif.

Untuk mengulang tes terkait pada database khusus pengujian yang sudah tersedia:

```bash
cd /home/xdranel/Code/Project/secret/dadu-test
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 \
DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 \
DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= \
php artisan test tests/Feature/SidangTaFollowup79Test.php --compact
```

## 5. Pemeriksaan setelah update

Buka tinjau dengan persyaratan belum lengkap. Ketiga tombol harus langsung terlihat di bawah penjelasan; peringatan kuning dan daftar merah berada di bawahnya. Tombol kirim tetap disabled. Dengan persyaratan terpenuhi, tombol kirim membuka modal dan konfirmasi tetap menunggu checkbox.
