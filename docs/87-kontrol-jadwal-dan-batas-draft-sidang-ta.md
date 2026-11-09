# 87 — Kontrol jadwal mahasiswa dan batas waktu draft Sidang TA

## 1. Perilaku akhir

Kelola Periode memiliki tiga checkbox independen:

| Checkbox | Fungsi |
| --- | --- |
| Pendaftaran aktif | Mengizinkan pendaftaran baru dalam rentang Dibuka–Tenggat pendaftaran |
| Revisi aktif | Mengizinkan pengerjaan/pengiriman revisi dalam rentang Mulai–Selesai verifikasi |
| Tampilkan jadwal ke mahasiswa | Menampilkan kartu dua jadwal pada halaman Pendaftaran Sidang TA |

Mengaktifkan pendaftaran dan revisi tanpa mencentang tampilan **tidak menampilkan kartu jadwal**. Checkbox tampilan hanya mengatur kartu, bukan pilihan periode atau hak mendaftar. Periode pendaftaran yang aktif dan masih terbuka tetap dapat dipilih meskipun kartunya disembunyikan. Pengajuan, draft, dokumen, dan riwayat mahasiswa tetap dapat dilihat sesuai hak aksesnya.

Kartu otomatis hilang setelah **kedua jadwal berakhir**, walaupun checkbox tampilan masih dicentang. Server menyaring kartu saat halaman dibuka; halaman yang sudah terbuka juga menyembunyikannya melalui timer countdown. Tepat pada waktu selesai masih berlaku; sesudahnya ditutup. Jika verifikasi periode lama belum lengkap, kartu mengikuti akhir pendaftaran agar kartu lama tidak tertahan selamanya. Ini hanya aturan tampilan; aturan transisi akses revisi dijelaskan pada bagian2.

Jadwal mahasiswa tampil **hanya pada halaman Pendaftaran Sidang TA**, bukan form tahap1–4, tinjau, detail pengajuan, atau halaman pengerjaan revisi. Pilihan/nama periode tetap ada pada tahap1. Jadwal/countdown pada Kelola Periode dan Review Sidang TA petugas tetap tersedia dan tidak mengikuti checkbox tampilan mahasiswa.

Perubahan hide sementara untuk rekaman dari commit `2d4965e` dan `3329d05` sudah diperiksa. Referensi loop/include yang dikomentari tetap dipertahankan; kartu kosong dan petunjuk jadwal lama tidak dirender di wizard. Tes memeriksa hasil render, sehingga komentarnya tidak perlu diaktifkan kembali untuk pengujian.

## 2. Batas waktu dan keamanan draft

| Jenis | Syarat menulis/mengirim | Penghapusan draft aktif |
| --- | --- | --- |
| Pendaftaran baru | Pendaftaran aktif, sudah dibuka, belum melewati `close_at` | Sesudah tenggat pendaftaran |
| Revisi | Revisi aktif, sudah mulai verifikasi, belum melewati `revision_close_at` | Sesudah tenggat verifikasi yang lengkap |

Setelah tenggat terlewati, server langsung menolak penyimpanan, unggahan, penandaan tinjau, pengiriman, serta retry mulai/lanjutkan draft. Tombol/form juga ditahan. Ini berlaku sebelum scheduler sempat membersihkan draft. Saat mengganti periode yang masih terbuka, periode asal dan tujuan dikunci menurut ID agar urutan lock konsisten.

Penghapusan fisik/metadata berjalan pada eksekusi scheduler berikutnya, biasanya per menit, sehingga draft tidak harus lenyap persis pada detik penutupan. Pengajuan yang sudah dikirim, receipt consumed, serta dokumen final/pinjaman tetap dipertahankan. Pengajuan Perlu revisi yang melewati tenggat verifikasi mengikuti penolakan otomatis yang sudah diterapkan pada86.

Melepas checkbox Revisi aktif langsung menahan akses tulis. Menonaktifkannya tidak menunda expiry/pembersihan berdasarkan tanggal yang sudah lengkap. Petugas juga mendapat penjelasan ketika aksi Minta revisi belum tersedia.

Untuk periode lama tanpa jadwal verifikasi lengkap, aturan transisi84–86 tetap berlaku: expiry dan penghapusan otomatis revisi ditunda sampai tanggal diisi. Revisi lama tetap boleh dikerjakan **jika Revisi aktif dicentang**. Isi dua tanggal verifikasi pada periode yang sama agar tenggat revisi diberlakukan. Memperpanjang tanggal tidak memulihkan draft yang sudah dihapus atau pengajuan yang sebelumnya sudah ditolak otomatis.

## 3. Migration dan periode yang sudah dipakai

Migration tambahan:

```text
database/migrations/2026_11_09_000002_add_revision_controls_to_sidang_ta_periods.php
```

Migration menambahkan `is_revision_active` dan `show_to_students`; tidak mengubah migration000001 yang mungkin sudah diterapkan. Nilai awal pada periode lama: **Revisi aktif = ya** untuk menjaga akses revisi yang sudah tersedia, **Tampilkan jadwal ke mahasiswa = tidak** agar publikasi kartu menjadi pilihan eksplisit. Status aktif pendaftaran tetap memakai nilai sebelumnya. Periode baru mengikuti ketiga checkbox pada form; checkbox yang tidak dicentang disimpan sebagai tidak aktif.

ID periode/pengajuan yang sudah ada tidak berubah. Sesudah migration, edit **periode aktif yang sama** pada Kelola Periode, pertahankan tanggal pendaftaran9–11 November sesuai keputusan divisi, isi tanggal verifikasi sebenarnya, lalu centang pendaftaran/revisi/tampilan sesuai kebutuhan. Tidak perlu membuat periode pengganti, migrate:fresh, atau seed ulang. Proses implementasi tidak mengisi tanggal maupun mengubah checkbox pada database aplikasi.

## 4. Penerapan lokal dan Docker

Kode dan dokumen ini disalin ke development asli `dadu` serta repo laptop `dadu-test` setelah pemeriksaan hash. Perubahan lain pada `.gitignore`, `package-lock.json`, `phpunit.xml`, konfigurasi lingkungan, dan fixture pribadi tidak ditimpa. Server Docker/produksi tidak dideploy oleh proses ini.

Development lokal, jalankan pada database aplikasi development yang memang digunakan:

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
php artisan migrate
npm run build
```

Database aplikasi **belum dimigrasikan oleh asisten**. Kode baru memerlukan kolom migration000002, jadi jalankan migration sebelum memakai halaman jadwal. Build development asli telah diperiksa setelah penyalinan; command build di atas juga disediakan untuk penerapan ulang.

Untuk pembersihan otomatis di development, scheduler harus berjalan. Pada terminal terpisah jika belum ada scheduler:

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
php artisan schedule:work
```

Periksa kandidat pembersihan tanpa menghapus:

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
php artisan sidang-ta:purge-expired-drafts --dry-run
```

Docker dadu-test: setelah commit/push dan perubahan tersedia pada main, gunakan [panduan78 bagian4 — Update Docker](78-hasil-implementasi-meeting-mahasiswa-petugas-it-panel.md#4-update-docker). Alur itu melakukan backup, migration biasa dari image baru, recreate app/web, `artisan up`, lalu menghidupkan scheduler/worker. Sesudahnya pastikan migration000002 berstatus Ran dan atur checkbox pada periode yang sudah dipakai. Update hide untuk rekaman yang sebelumnya dipasang di server belum membawa migration/fitur87 ini.

Produksi tetap ditunda sesuai instruksi pengguna dan koordinasi lead dev.

## 5. Berkas dan command copy ulang

Berkas yang berubah pada87:

- `app/Services/SidangTaDraftAccess.php`
- `app/Services/SidangTaDraftService.php`
- `app/Http/Controllers/SidangTaDraftController.php`
- `resources/views/sidang_ta_registrations/wizard.blade.php`
- `resources/views/sidang_ta_registrations/wizard-review.blade.php`
- `resources/views/sidang_ta_registrations/revision-start.blade.php`
- `resources/views/sidang_ta_registrations/show.blade.php`
- `tests/Feature/SidangTaPeriodScheduleUiTest.php`
- `tests/Feature/SidangTaRevisionDeadlineTest.php`
- `app/Models/SidangTaPeriod.php`
- `app/Http/Controllers/SidangTaRegistrationController.php`
- `app/Support/SidangTaRevisionDeadline.php`
- `resources/views/sidang_ta_registrations/partials/period-schedule.blade.php`
- `resources/views/sidang_ta_registrations/admin/periods.blade.php`
- `resources/views/sidang_ta_registrations/index.blade.php`
- `database/migrations/2026_11_09_000002_add_revision_controls_to_sidang_ta_periods.php`
- `resources/views/sidang_ta_registrations/partials/period-countdown.blade.php`
- `docs/87-kontrol-jadwal-dan-batas-draft-sidang-ta.md`

Salinan kedua repo laptop sudah disamakan pada proses ini. Jika perlu copy ulang dari development asli ke repo Docker laptop, blok berikut hanya menyalin daftar berkas87, membuat backup tujuan terlebih dahulu, dan tidak menghapus berkas lain. Periksa perubahan tujuan sebelum menjalankannya bila ada pekerjaan baru sesudah87.

```bash
bash <<'DADU_COPY_87'
set -euo pipefail
source_dir=/home/xdranel/Code/Project/php-laravel/dadu
target_dir=/home/xdranel/Code/Project/secret/dadu-test
files=(
  app/Services/SidangTaDraftAccess.php
  app/Services/SidangTaDraftService.php
  app/Http/Controllers/SidangTaDraftController.php
  resources/views/sidang_ta_registrations/wizard.blade.php
  resources/views/sidang_ta_registrations/wizard-review.blade.php
  resources/views/sidang_ta_registrations/revision-start.blade.php
  resources/views/sidang_ta_registrations/show.blade.php
  tests/Feature/SidangTaPeriodScheduleUiTest.php
  tests/Feature/SidangTaRevisionDeadlineTest.php
  app/Models/SidangTaPeriod.php
  app/Http/Controllers/SidangTaRegistrationController.php
  app/Support/SidangTaRevisionDeadline.php
  resources/views/sidang_ta_registrations/partials/period-schedule.blade.php
  resources/views/sidang_ta_registrations/admin/periods.blade.php
  resources/views/sidang_ta_registrations/index.blade.php
  database/migrations/2026_11_09_000002_add_revision_controls_to_sidang_ta_periods.php
  resources/views/sidang_ta_registrations/partials/period-countdown.blade.php
  docs/87-kontrol-jadwal-dan-batas-draft-sidang-ta.md
)
for relative in "${files[@]}"; do
    test -f "$source_dir/$relative"
done
backup_dir=$(mktemp -d /tmp/dadu-copy87.XXXXXX)
for relative in "${files[@]}"; do
    if [ -f "$target_dir/$relative" ]; then
        mkdir -p "$backup_dir/$(dirname "$relative")"
        cp -p "$target_dir/$relative" "$backup_dir/$relative"
    fi
    mkdir -p "$target_dir/$(dirname "$relative")"
    cp "$source_dir/$relative" "$target_dir/$relative"
    cmp "$source_dir/$relative" "$target_dir/$relative"
done
printf 'Backup: %s\n' "$backup_dir"
git -C "$target_dir" status --short
git -C "$target_dir" diff --stat
DADU_COPY_87
```

## 6. Verifikasi

- RED mereproduksi unggahan draft awal setelah tenggat, jadwal yang masih muncul di form, checkbox yang belum tersimpan/berpengaruh, kartu kedaluwarsa, dan revisi nonaktif yang masih menerima unggahan.
- Dua berkas tes jadwal/deadline: **52 tes lulus, 754 assertion**. Mencakup delapan kombinasi checkbox, pilihan periode yang independen dari tampilan, pengajuan tetap terlihat saat kartu disembunyikan, batas akhir tepat, penguncian semua operasi tulis, reaktivasi revisi, serta expiry/pembersihan saat revisi nonaktif.
- Suite PHP lengkap: **856 lulus, 42 gagal, 7.577 assertion**. Seluruh42 signature kegagalan identik dengan baseline sebelum perubahan checkbox; **tidak ada kegagalan baru**. Kegagalan lama berada pada `Auth/PasswordResetTest` (3), `Auth/PasswordUpdateTest` (1), `ExamScheduleTest` (4), `GraduationRegistrationTest` (8), `PublicDocumentTest` (2), `QueueHistoryAndLogTest` (12), `QueueTicketTest` (4), dan `SyncTest` (8); belum diperbaiki dalam pekerjaan ini.
- Seluruh **22 tes JavaScript lulus**; build Vite berhasil, sembilan berkas PHP non-Blade lulus lint, dan seluruh Blade terkompilasi.
- Review independen menemukan pesan kartu revisi yang salah menyebut pendaftaran; sudah diperbaiki dan diuji untuk jadwal lengkap maupun legacy. Review akhir tidak menemukan masalah material.

Tests menggunakan `dadu_testing`, terpisah dari database aplikasi. Verifikasi UI melalui render Blade/DOM dan tes JavaScript; belum dilakukan pemeriksaan browser interaktif atau pengujian runtime image di server. Tidak dilakukan fresh/seed, impor data, commit/push, maupun perubahan produksi.

## 7. Rescan akhir kedua repo laptop

Sebanyak18 berkas hasil integrasi (kode/tes, migration baru, dan panduan87) identik dengan workspace teruji. Rescan seluruh folder `app`, `config`, `database`, `resources`, `routes`, dan `tests` tidak menemukan berkas berbeda atau tertinggal antara development asli dan repo Docker laptop.

Fixture tidak diubah oleh87. Dua fixture lama `72-01-bpp-periode-akun-demo.json` dan `72-02-bpp-lunas-dummy002.json` hanya ada di repo Docker laptop; `docs/fixtures/sidang-ta/README.md` memang berbeda sesuai lingkungan. Perbedaan tersebut dipertahankan. Build frontend berhasil pada workspace pengujian dan development asli; salinan ini tidak mengubah database aplikasi.
