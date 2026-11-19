# 122 — Hasil perbaikan Sidang TA dan URL publik

Rencana: [120](120-perbaikan-sidang-ta-dan-url-publik.md). Jawaban divisi dan dua jalur Pelaksanaan berikutnya: [121](121-catatan-divisi-pelaksanaan-sidang-ta.md).

## Perubahan yang sudah diterapkan

- Halaman mahasiswa: menghapus judul/deskripsi tambahan, badge jumlah periode, dan kalimat pemberitahuan yang diminta. Tombol mulai pendaftaran, status tutup, revisi dan riwayat tetap mengikuti izin/jadwal.
- Kartu Draft tersimpan hanya tampil jika ada draft yang masih relevan. Draft awal mengikuti tenggat pendaftaran; revisi mengikuti tenggat verifikasi. Revisi legacy yang belum mempunyai tanggal verifikasi tetap mengikuti aturan kompatibilitas sebelumnya.
- Saat admin memperbarui periode, draft yang sudah kedaluwarsa pada periode itu dibersihkan sebelum jadwal baru disimpan. Draft revisi berikutnya memakai pengajuan terakhir; pengajuan final, status dan berkas final dipertahankan.
- Header Review menjadi **Review Sidang TA**; teks pengantar tambahan dihapus; tombol menjadi **Export Excel**. Kartu periode memakai lebar penuh.
- Periode yang pendaftaran dan verifikasinya sudah berakhir/dinonaktifkan memakai disclosure `details/summary`: tampilan tertutup hanya nama periode dan chevron. Verifikasi yang masih aktif, termasuk legacy tanpa tanggal, tetap terbuka.
- Menghapus kalimat pengaktifan pendaftaran/revisi dari partial kartu jadwal.

Fitur menu Pelaksanaan baru, importer plotting, pencarian oleh admin, hasil lulus/gagal dan riwayat pelaksanaan belum dibuat pada tahap perbaikan ini. Kontrol Pelaksanaan lama dipindahkan bersamaan dengan tersedianya menu pengganti agar akses yang ada tidak hilang di tengah perubahan. Jawaban divisi, durasi90 menit dan dropdown ruang/slot alternatif sudah dicatat pada121.

## URL publik

Migration `2026_11_19_120000_add_public_ids_to_sidang_ta_routes.php` menambah `public_id` UUID unik pada:

1. `sidang_ta_periods`
2. `sidang_ta_registrations`
3. `sidang_ta_registration_documents`
4. `sidang_ta_registration_draft_documents`
5. `sidang_ta_form_guides`

Draft sudah memakai UUID pada primary key, sehingga tidak diubah. Jadwal/booking TA dan pengaturan Pelaksanaan memakai URL model pengajuan/periode di atas. Booking ruangan biasa dan modul aplikasi lain tidak diubah URL-nya.

Contoh tautan baru:

```text
/dashboard/sidang-ta-registrations/<uuid-publik>
/dashboard/sidang-ta-registration-documents/<uuid-publik>/preview
/dashboard/admin/sidang-ta-registrations?period=<uuid-periode>
```

ID internal, foreign key, status, snapshot dan path berkas tidak diganti. UUID lama tidak digenerasi ulang saat record diedit. Backfill berjalan per chunk dan dapat dilanjutkan bila proses terputus.

Tautan angka yang sudah terlanjur dibagikan tetap melalui pemeriksaan akses yang sama. GET yang berhasil diarahkan ke tautan UUID; pengguna yang tidak berwenang tidak memperoleh redirect ke UUID milik orang lain. Form lama yang masih terbuka tetap dapat dikirim sesuai izin/versi yang berlaku; form baru termasuk pengiriman revisi memakai UUID. Filter periode lama berbentuk angka masih diterima, sementara tautan filter/pagination/export baru memakai UUID.

Masking URL tidak menggantikan otorisasi: mahasiswa tetap hanya melihat datanya, dosen mengikuti prodi, reviewer mengikuti permission. Tidak membuka akses publik ke PDF.

## Pengujian dan tinjauan

| Pemeriksaan | Hasil |
| --- | --- |
| RED awal UUID/draft | 6 gagal sesuai fitur yang belum ada; 4 kasus lain sudah lulus. |
| RED filter periode UUID | Gagal sebelum normalisasi filter ditambahkan. |
| RED temuan tinjauan | 4 gagal: dua aturan draft, periode legacy, form URL revisi. |
| Regresi terarah sesudah perbaikan | **168 lulus, 1.753 assertion**, exit0. |
| Regresi seluruh file tenggat revisi setelah penyelarasan assertion UI lama | **32 lulus, 636 assertion**, exit0. |
| Syntax PHP, whitespace | Lulus pada semua file PHP baru/berubah; `git diff --check` bersih. |
| Build frontend terakhir | Berhasil. |
| Tinjauan independen | Tiga temuan diperbaiki; tinjauan ulang tidak menemukan masalah penting tersisa. |

Suite penuh terakhir sebelum penyelarasan terakhir assertion UI: **1.240 lulus, 46 gagal, 2 dilewati**, 10.058 assertion,270,86 detik. Perbandingan dengan118: 45 kegagalan yang sama persis tetap ada; satu kegagalan tambahan mengharapkan kartu draft kedaluwarsa tetap terlihat. Assertion lama itu sudah diselaraskan dan seluruh32 tes tenggat revisi lulus sesudahnya. Tidak ada perubahan kode produk setelah suite penuh tersebut. Suite penuh tidak diklaim hijau; nama semua kegagalan dan hasil perbandingan tersedia di [122-verifikasi-perbaikan.json](122-verifikasi-perbaikan.json).

Seluruh pengujian database memakai **`dadu_testing`**. Tidak menjalankan fresh/seed/migration pada database aplikasi lokal, production atau VPS. File environment sementara tes dibersihkan dan tidak ikut commit; key tetap privat. Log/JUnit disimpan di `private-import/120-*`.

## Rescan dan penyalinan

Sebanyak **25 file aplikasi/tes** sudah disamakan dari workspace dadu-test ke source dadu dengan backup dan pemeriksaan hash. Daftar lengkap: [122-daftar-file-perbaikan.txt](122-daftar-file-perbaikan.txt). Markdown/laporan hanya di dadu-test; source `docs/` dan `docker/` tetap tidak dibuat kembali. `.gitignore`, `package-lock.json`, `phpunit.xml` source, staging kedua Git, environment/password, data JSON privat dan konfigurasi Docker dipertahankan.

Backup target sebelum implementasi: `private-import/120-before/`. Backup source sebelum penyalinan: `private-import/120-source-before/`. Manifest source/target: `private-import/120-copy-manifest.json`.

Tidak melakukan git add, commit, push atau deployment. Perubahan siap ditinjau pada kedua repo.

## Migration dan pemakaian kode baru

**Migration diperlukan sebelum membuka aplikasi dengan kode baru**, karena URL pengajuan/berkas memerlukan UUID hasil backfill. Gunakan migration biasa untuk mempertahankan data.

Lokal development, pada folder source:

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
php artisan migrate
php artisan optimize:clear
```

Untuk Docker/VPS: commit/push dahulu, kemudian gunakan alur backup dan update pada78 dengan penyesuaian VPS1GB pada113. Image app baru harus memuat migration ini; jalankan migration memakai image baru sebelum app/web diaktifkan kembali. Bagian migration dalam alur update tersebut:

```bash
cd /opt/dadu-test
docker compose --env-file .env.docker run -T --interactive=false --rm --no-deps app \
  php artisan migrate --force
```

Tetap bangun/perbarui app dan web mengikuti alur rilis, pertahankan volume/override VPS dan pastikan `php artisan up` pada tahap akhir. **Tidak memerlukan `migrate:fresh --seed`**. Perintah di atas dicatat sebagai langkah operator; belum dijalankan pada database aplikasi/server dalam sesi ini.

Jika perlu menyalin ulang file aplikasi dari dadu-test ke source, setelah meninjau perubahan source yang mungkin baru:

```bash
cd /home/xdranel/Code/Project/secret/dadu-test
while IFS= read -r dadu_file; do
  mkdir -p -- "/home/xdranel/Code/Project/php-laravel/dadu/$(dirname -- "$dadu_file")"
  cp -p -- "$dadu_file" "/home/xdranel/Code/Project/php-laravel/dadu/$dadu_file"
done < docs/122-daftar-file-perbaikan.txt
```

Penyalinan sesi ini sudah selesai memakai pemeriksaan hash/backup; loop tersebut hanya referensi untuk salinan ulang yang memang disengaja.
