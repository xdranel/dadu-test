# 101 — Hasil implementasi Form Yudisium

## 1. Yang diterapkan

Kode diterapkan pada folder development `/home/xdranel/Code/Project/php-laravel/dadu` dan repo Docker laptop `/home/xdranel/Code/Project/secret/dadu-test`. Belum di-commit, push, atau dipasang ke `/opt/dadu-test` maupun production. Tidak menjalankan migrasi/reset/import pada database aplikasi; seluruh pengujian DB memakai `dadu_testing`.

- Form mahasiswa tiga tahap: Data dasar; Kelengkapan dokumen; Cumlaude/Summa-Cumlaude. Identitas sinkronisasi terkunci, kedua WA mengikuti awalan08/628, email pribadi valid, serta konfirmasi judul/perubahan judul ke LAA.
- Panel akademik dan kartu persyaratan merah/hijau, riwayat EPRT, pembimbing/penguji maksimal dua, dosen wali, nilai/tanggal TA, daftar MK mengulang dan belum lulus.
- Dokumen sesuai catatan 91, tanggal pekerjaan revisi TA, angka plagiasi buku/artikel, biodata dummy ijazah dan bukti/tanggal PDM bila belum sesuai. Angka plagiasi 0–100 adalah validasi rentang input, **bukan ambang kelulusan**.
- Sementara satu kriteria predikat: HKI, lomba, jurnal, atau konferensi. Field dan lampiran bersyarat mengikuti kriterianya, termasuk publikasi belum terbit dan bukti presentasi.
- Draft terpisah dari pengajuan terkirim, tinjau dan checkbox konfirmasi, pemeriksaan ulang persyaratan ketika dikirim, versi untuk mencegah penyimpanan dari tab lama, dan pencegahan pengiriman ganda.
- Revisi mengganti dokumen yang diminta perbaikan; dokumen terverifikasi dilindungi. Koreksi biodata/PDM bersyarat diperbolehkan bila dokumen terkait diminta revisi. Draft revisi yang tertinggal versi dapat dihapus melalui halaman Detail revisi/form, lalu dibuat kembali tanpa menghapus berkas pengajuan asal.
- Admin: filter nama/NIM, periode, status, prodi, mengajukan predikat/tidak, dan kriteria; review per dokumen serta keputusan keseluruhan terpisah; koreksi angka artikel dengan alasan dan audit sebelum/sesudah. Nilai awal mahasiswa dan nilai koreksi tetap dipisahkan saat revisi dokumen lain.
- Ekspor XLSX mengikuti filter yang sama, mencakup tautan lampiran melalui endpoint yang memeriksa akses. Dokumen baru disimpan privat; dokumen public historis tetap dapat dibaca melalui jalur lama.
- Menu **IT Panel → Panduan Form Yudisium** untuk CRUD gambar/PDF/tautan berlabel, slot, urutan, dan status aktif. Hak akses CRUD dapat didelegasikan lewat Pengaturan Role seperti panduan TA.
- Kelola periode memisahkan pendaftaran dan verifikasi/revisi, dengan tiga checkbox: Pendaftaran aktif, Revisi aktif, Tampilkan jadwal ke mahasiswa. Jadwal tampil di halaman pendaftaran, tidak dimasukkan ke tahapan. Countdown memakai kotak hari/jam/menit/detik.

Tidak mengubah SSO dan alur TA. Berkas/kode lain pada branch development, termasuk pengaturan Portal Dokumen dan Antrian, dipertahankan melalui patch tambahan untuk file bersama.

## 2. Aturan sementara dan pertanyaan divisi

Daftar lengkap pertanyaan tersimpan pada [91](91-catatan-kebutuhan-form-yudisium.md) dan rancangan [99](99-rancangan-form-yudisium.md).

| Bagian | Perilaku sekarang | Yang perlu dikonfirmasi |
|---|---|---|
| IPK | Minimal 2,00, mempertahankan angka kode lama | Angka resmi minimal IPK Yudisium |
| SKS/TAK | SKS lulus 144; TAK 60 | Sesuai catatan meeting |
| EPRT |450 sudah memenuhi; di bawah 450 setelah sedikitnya 3 tes valid memerlukan surat | Mengikuti aturanTA yang telah disepakati |
| Plagiasi | Input0–100; petugas dapat mengoreksi artikel dan menentukan keputusan dengan alasan | Batas buku/artikel dan apakah harus ada blokir otomatis |
| Predikat | Satu kriteria; rincian sesuai catatan, tanpa syarat Summa tambahan yang dikarang | Perbedaan Summa dan apakah boleh beberapa kriteria |
| Mata kuliah | MK belum lulus memblokir; MK mengulang tampil sebagai informasi | Apakah KRS TA2/MK mengulang juga perlu menjadi blocker |
| Email | Seluruh domain email pribadi yang valid | Apakah wajib Gmail |
| BPP | Status lunas yang dikenali; KREDIT/tidak dikenal tidak dianggap lunas | Arti KREDIT dan kewajiban periode pembayaran tertentu |
| Unggahan | PDF≤10 MB; khusus bukti presentasi konferensi boleh PDF/JPG/PNG≤10 MB | Pengecualian screenshot mengikuti catatan; persempit bila divisi mewajibkanPDF |

Belum ada contoh **JSON Kehadiran**. Berkas `rekap_igracias_format_krs_matakuliah.json` adalah KRS, bukan Kehadiran. Tetap perlu meminta contoh field/rincian kehadiran kepada penyedia data.

## 3. Data lama, jadwal, dan draft

Tiga migrasi baru menambahkan jadwal revisi, panduan, serta draft/versi/snapshot. Pengajuan lama menjadi versi1; pengajuan baru versi2. Relasi periode pengajuan lama diisi hanya bila nama cocok tepat dengan satu periode. Nama ambigu tidak ditebak.

Pengajuan dan berkas lama tetap dapat dibaca. Pengajuan baru melalui wizard; POST form lama diarahkan ke wizard agar tidak melewati persyaratan baru. Revisi/pemeriksaan record versi1 tetap memakai jalur kompatibilitas lama. Tidak mengonversi berkas historis generik menjadi bukti persyaratan baru.

Snapshot pengajuan versi2 tidak ditimpa impor/sinkronisasi akademik berikutnya. Pengajuan berikutnya membaca fakta terbaru; saat kirim ulang revisi, persyaratan diperiksa kembali. Koreksi admin dicatat terpisah.

- Draft awal berhenti menerima simpan/kirim setelah tenggat pendaftaran; draft revisi mengikuti tenggat verifikasi.
- Command terjadwal `graduation:cleanup-expired-drafts` berjalan setiap menit: menghapus draft aktif yang kedaluwarsa, lalu menolak pengajuan versi2 yang masih menunggu revisi melewati tenggat, dengan alasan otomatis dan riwayat.
- Final pengajuan, riwayat, dan berkas asal yang masih dirujuk tetap disimpan. Perpanjangan jadwal diperiksa ulang di dalam transaksi sebelum cleanup/penolakan.
- Periode historis tanpa jadwal revisi tetap kompatibel dan belum mempunyai tenggat revisi otomatis; isi kedua tanggal verifikasi pada Kelola periode agar memakai batas baru.
- Kartu mahasiswa membutuhkan checkbox Tampilkan jadwal. Checkbox ini hanya mengatur kartu, sedangkan izin daftar tetap mengikuti Pendaftaran aktif dan waktunya. Kartu otomatis hilang sesudah kedua jadwal berakhir.

## 4. Verifikasi

- Gate seluruh Yudisium: **96 tes lulus,701 assertions**, mencakup85 tes baru dan11 tes kompatibilitas. Termasuk empat kriteria/lima cabang prestasi, persyaratan berubah, dokumen privat, kepemilikan, versi usang, rollback upload, koreksi artikel, dokumen terverifikasi, cleanup/expiry, periode dan panduan.
- Review independen menemukan dua masalah penting: nilai awal artikel tertimpa saat revisi berkas lain, dan tidak ada pemulihan draft revisi usang di UI. Keduanya direproduksi lewat tes gagal, diperbaiki, dan masuk gate lulus. Tidak ada temuan Critical atau Minor tertunda.
- JavaScript: **24/24 lulus**. Build Vite dan kompilasi Blade berhasil; lint43 filePHP tanpa error; pemeriksaan whitespace lingkup perubahan bersih.
- Suite penuh: **1015 lulus,35 gagal,8772 assertions**. Sebelum pekerjaan ini:922 lulus,43 gagal. Seluruh header kegagalan yang tersisa sudah ada pada baseline, di Auth, ExamSchedule, PublicDocument, Queue, SidangTaHistory, dan Sync. Tes lama Yudisium disesuaikan dengan permission, data mahasiswa, checklist, dan alur wizard yang berlaku. Jadi suite penuh **belum hijau**.
- Uji ulang pada folder development asli setelah integrasi: **96/96 lulus,701 assertions**,19,13 detik. Pengujian browser visual dan produksi belum dijalankan.

Log kerja sesi: `/tmp/yud101-yudisium-final.log`, `/tmp/yud101-full-final.log`, `/tmp/yud101-js-final.log`, `/tmp/yud101-build-final.log`, `/tmp/yud101-original-final.log`. Log ini bukan berkas deployment.

## 5. Langkah lokal development

Setelah meninjau perubahan, jalankan di folder development yang `.env`-nya memang menunjuk database lokal yang dimaksud:

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
php artisan migrate
php artisan optimize:clear
php artisan permission:cache-reset
npm run build
php artisan schedule:list
```

Tidak perlu `migrate:fresh --seed`. Pengujian asisten tidak memigrasikan DB aplikasi, sehingga langkah `migrate` tetap perlu dijalankan oleh pengguna.

Buka Review Yudisium → Kelola periode. Lengkapi jadwal pendaftaran dan jadwal verifikasi pada periode yang dipakai. Atur tiga checkbox sesuai kebutuhan. Akun Akademik memperoleh permission panduan baru melalui migrasi; delegasikan CRUD panduan untuk Administrator/petugas melalui IT Panel → Pengaturan Role bila dibutuhkan.

Isi panduan pada menu baru, lalu coba mahasiswa dengan persyaratan lengkap: draft → tiga tahap → tinjau → checkbox → kirim; admin tandai dokumen → minta revisi → mahasiswa unggah perbaikan → tinjau/kirim ulang → keputusan akhir. Pengajuan dengan data akademik kurang akan tetap terhalang sesuai kartu persyaratan. Sumber fakta menggunakan hasil impor yang sudah ada; tidak ada urutan impor baru khusus untuk wizard ini.

Untuk menjalankan scheduler lokal pada terminal tersendiri:

```bash
php artisan schedule:work
```

Scheduler harus berjalan agar pembersihan/status kedaluwarsa otomatis diterapkan. Pemeriksaan jadwal di server saat simpan/kirim tetap bekerja walaupun scheduler belum berjalan. Command cleanup manual tersedia, tetapi benar-benar menghapus draft kedaluwarsa dan memutus revisi yang sudah lewat; gunakan setelah memeriksa jadwal:

```bash
php artisan graduation:cleanup-expired-drafts
```

## 6. Update Docker dadu-test

Setelah commit/push dan perubahan tersedia di main repo Docker, **tetap gunakan dokumen 78 bagian4, Update Docker**, yang merujuk dokumen 64 bagian12.2. Prosedurnya mencakup backup, migrasi, build/recreate container, dan `artisan up`; jangan menjalankan fresh/seed untuk pembaruan fitur ini.

Tiga migrasi 11 November harus terbaca di dalam image app baru. Sesudah update, pemeriksaan berikut dapat dijalankan:

```bash
cd /opt/dadu-test
docker compose --env-file .env.docker exec -T --interactive=false --user www-data app php artisan migrate:status
docker compose --env-file .env.docker exec -T --interactive=false --user www-data app php artisan permission:cache-reset
docker compose --env-file .env.docker exec -T --interactive=false --user www-data app php artisan schedule:list
docker compose --env-file .env.docker ps
```

Pastikan scheduler hidup; lengkapi jadwal/panduan/perizinan melalui aplikasi seperti langkah lokal. Tidak perlu mengimpor ulang mahasiswa hanya untuk membuat tabel draft/panduan. Jika source host sudah baru tetapi container masih memakai kode lama, ikuti pemeriksaan/perbaikan image pada64, bukan reset database.

Production tetap ditunda sesuai arahan pengguna. Tidak ada command/perubahan production yang dijalankan.

## 7. Rescan dan command copy development → repo Docker laptop

Integrasi sesi ini sudah menyalin 61 file kode/tes ke kedua folder. Daftar file yang bisa disalin langsung ada di `docs/101-yudisium-files.txt`; empat file bersama (`routes/web.php`, `routes/console.php`, `config/sidebar.php`, `config/permission_modules.php`) memakai `docs/101-yudisium-shared.patch` agar perubahan branch lain tetap ada.

Backup sebelum integrasi: `/tmp/dadu101-integration-backup-20261111-163745`. Source `.env`, storage, vendor, node_modules, dan `.git` tidak disalin. Aset dibangun melalui langkah lokal/buildDocker.

Blok berikut dapat dipakai untuk menyalin ulang file fitur yang tercatat dari development ke repo Docker laptop. Jalankan dengan bash; ini bukan command untuk `/opt/dadu-test`:

```bash
bash <<'DADU_YUDISIUM_COPY'
set -euo pipefail
umask 022
dadu_src=/home/xdranel/Code/Project/php-laravel/dadu
dadu_dst=/home/xdranel/Code/Project/secret/dadu-test
dadu_patch="$dadu_src/docs/101-yudisium-shared.patch"
dadu_manifest="$dadu_src/docs/101-yudisium-files.txt"
dadu_backup="/tmp/dadu101-copy-$(date +%Y%m%d-%H%M%S)"

test -f "$dadu_manifest"
test -f "$dadu_patch"
mapfile -t dadu_files < "$dadu_manifest"
dadu_files+=(
  docs/91-catatan-kebutuhan-form-yudisium.md
  docs/99-rancangan-form-yudisium.md
  docs/100-rencana-implementasi-yudisium.md
  docs/101-hasil-implementasi-form-yudisium.md
  docs/101-yudisium-files.txt
  docs/101-yudisium-shared.patch
)
for dadu_file in "${dadu_files[@]}"; do test -f "$dadu_src/$dadu_file"; done

# Periksa patch sebelum menulis file apa pun.
dadu_apply_patch=0
if git -C "$dadu_dst" apply --reverse --check "$dadu_patch" 2>/dev/null; then
  printf 'Route/config Yudisium sudah terpasang.\n'
elif git -C "$dadu_dst" apply --check "$dadu_patch"; then
  dadu_apply_patch=1
else
  printf 'Berhenti: patch file bersama perlu ditinjau; belum ada file disalin.\n' >&2
  exit 1
fi

mkdir -p "$dadu_backup"
for dadu_file in "${dadu_files[@]}" routes/web.php routes/console.php config/sidebar.php config/permission_modules.php; do
  if [ -f "$dadu_dst/$dadu_file" ]; then
    mkdir -p "$dadu_backup/$(dirname "$dadu_file")"
    cp -p "$dadu_dst/$dadu_file" "$dadu_backup/$dadu_file"
  fi
done
for dadu_file in "${dadu_files[@]}"; do
  mkdir -p "$dadu_dst/$(dirname "$dadu_file")"
  cp -p "$dadu_src/$dadu_file" "$dadu_dst/$dadu_file"
done
if [ "$dadu_apply_patch" -eq 1 ]; then git -C "$dadu_dst" apply "$dadu_patch"; fi
printf 'Selesai. Backup: %s\n' "$dadu_backup"
git -C "$dadu_dst" diff --check
git -C "$dadu_dst" status --short
DADU_YUDISIUM_COPY
```

Daftar ini terbatas pada fitur 101. Perubahan baru di luar daftar perlu di-rescan sebelum dimasukkan; jangan menyalin seluruh folder development ke repo Docker karena terdapat perbedaan branch yang memang dipertahankan.
