# 86 — Hasil pemisahan jadwal pendaftaran dan verifikasi/revisi Sidang TA

## 1. Hasil fitur

Kelola Periode sekarang memisahkan dua bagian:

| Bagian | Isian | Mengatur |
| --- | --- | --- |
| Pendaftaran Sidang TA | Dibuka dan Tenggat | Pendaftaran baru serta masa hidup draft awal |
| Tanggal Verifikasi Berkas | Mulai verifikasi dan Selesai verifikasi | Pengerjaan/pengiriman revisi serta masa hidup draft revisi |

Verifikasi boleh dimulai ketika pendaftaran masih terbuka, termasuk sama-sama tanggal9. Tanggal selesai harus setelah tanggal mulai verifikasi dan tidak boleh lebih awal dari tenggat pendaftaran. Jadwal tampil dalam WIB kepada mahasiswa dan petugas, dengan countdown empat kotak Hari/Jam/Menit/Detik mengikuti pola Review Yudisium.

Mahasiswa melihat jadwal dari periode pengajuan/draft miliknya juga setelah pendaftaran ditutup atau dinonaktifkan. Status aktif pendaftaran tidak menutup revisi yang jadwalnya masih berlaku. Sebelum jadwal verifikasi dimulai, rincian/draft tetap bisa dilihat, sedangkan operasi tulis revisi ditahan oleh server dan tampilan.

Pada status Perlu revisi, tombol Lihat pengajuan aktif disembunyikan. Mulai revisi dan Detail Revisi tetap tersedia. Saat Menunggu peninjauan, tombol Lihat pengajuan aktif tetap tampil.

Riwayat mahasiswa sekarang memasukkan penolakan manual maupun otomatis, mempertahankan alasan/catatan yang di-escape, dan menyembunyikan pelaku dari HTML/payload mahasiswa. Audit internal petugas tetap tersedia.

## 2. Pengajuan yang sudah ada dan transisi periode lama

Migration menambahkan dua kolom nullable pada `sidang_ta_periods`: `revision_open_at` dan `revision_close_at`. ID periode dan pengajuan tetap sama. Tidak ada pemindahan akun, reset, fresh, atau seed. Snapshot dan dokumen final pengajuan tetap disimpan.

Untuk periode lama yang jadwal verifikasinya belum lengkap, sistem **menunda penutupan otomatis revisi** sampai admin mengisinya. Revisi yang sudah diminta tetap bisa dikerjakan. Draft pendaftaran awal tetap mengikuti tenggat pendaftaran. Form pembuatan/perubahan periode sekarang mewajibkan kedua tanggal verifikasi.

Sesudah jadwal lengkap diisi:

- Pada tanggal mulai, revisi boleh dimulai/disimpan/diunggah/ditinjau/dikirim, termasuk bila pendaftaran sudah tutup.
- Setelah tanggal selesai terlewati, operasi tulis revisi ditutup. Tepat pada batas waktu masih diizinkan.
- Pengajuan berstatus Perlu revisi yang belum dikirim kembali mengikuti penolakan otomatis pada job expiry.
- Draft awal dibersihkan berdasarkan tenggat pendaftaran; draft revisi berdasarkan tenggat verifikasi. Pembersihan hanya menghapus berkas milik draft yang tidak lagi dirujuk. Dokumen final/pinjaman dan pengajuan yang sudah dikirim tetap disimpan.
- Penghapusan berjalan pada eksekusi scheduler berikutnya, biasanya per menit. Penahanan simpan/kirim diperiksa langsung oleh server.

Memperpanjang tanggal tidak memulihkan draft yang sebelumnya sudah dihapus atau pengajuan yang sudah ditolak otomatis oleh kode lama.

## 3. Langkah untuk periode aktif 9–11 November

Setelah migration dan kode baru diterapkan, buka **Kelola Periode** lalu **edit periode aktif yang sama**, bukan membuat periode pengganti.

1. Pertahankan tanggal pendaftaran 9–11 November sesuai jadwal yang sudah dipakai.
2. Isi Mulai verifikasi; tanggal9 diperbolehkan jika itu keputusan divisi.
3. Isi Selesai verifikasi sesuai jadwal divisi, misalnya tanggal20 jika memang ditetapkan demikian.
4. Simpan perubahan, lalu periksa jadwal pada halaman mahasiswa dan Review Sidang TA.

Contoh tanggal di atas adalah ilustrasi; proses implementasi tidak mengisi tanggal pada database aplikasi secara otomatis. Pendaftar yang sudah ada membaca jadwal baru melalui ID periode yang sama.

## 4. Penerapan lokal dan Docker

Kode/migration/view/tes dan panduan84–86 sudah disalin ke development asli pada branch `tugasAkhir` serta repo Docker laptop setelah pemeriksaan hash tujuan. Seluruh35 berkas hasil integrasi diverifikasi identik dengan workspace teruji. Database aplikasi belum dimigrasikan oleh proses ini; gunakan command di bawah. Tidak dilakukan commit/push/deploy atau perubahan storage produksi.

Migration baru:

```text
database/migrations/2026_11_09_000001_add_revision_window_to_sidang_ta_periods.php
```

Development lokal, jalankan pada database aplikasi development yang memang dipakai:

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
php artisan migrate
npm run build
```

Untuk menguji pembersihan otomatis secara lokal, jalankan scheduler pada terminal terpisah bila belum berjalan:

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
php artisan schedule:work
```

Pemeriksaan kandidat penghapusan tanpa menghapus draft:

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
php artisan sidang-ta:purge-expired-drafts --dry-run
```

Docker dadu-test: setelah commit/push, gunakan [panduan78 bagian4 — Update Docker](78-hasil-implementasi-meeting-mahasiswa-petugas-it-panel.md#4-update-docker). Alur tersebut sudah menjalankan migration biasa melalui image baru sebelum app/scheduler/worker baru digunakan, serta build app/web untuk membawa aset countdown.

Produksi tetap mengikuti keputusan pengguna untuk ditunda dan koordinasi lead dev. Saat fitur ini akan dipasang di produksi, terapkan migration tambahan, versi kode/aset yang sesuai, lalu isi jadwal pada periode yang sudah dipakai pendaftar. Pemeriksaan storage produksi dari83 merupakan pekerjaan terpisah yang masih ditunda.

## 5. Verifikasi

- RED awal mereproduksi revisi yang masih mengikuti tenggat pendaftaran, validasi jadwal yang belum ada, timeline penolakan/pelaku, dan tampilan countdown/jadwal.
- Regresi seluruh Sidang TA: **457 tes lulus, 4.879 assertion**.
- JavaScript: **22 tes lulus**; numeric countdown, pergantian jadwal terpilih, dan transisi waktu diuji.
- Build Vite berhasil. Review independen tidak menemukan masalah material.

Suite PHP lengkap: **835 lulus, 42 gagal, 7.359 assertion**. Tidak ada signature kegagalan baru dibanding baseline83; satu kegagalan lama pada tes riwayat mahasiswa sudah terselesaikan oleh pembaruan kontrak/fixture waktu. Kegagalan yang masih tersisa: `Auth/PasswordResetTest` (3), `Auth/PasswordUpdateTest` (1), `ExamScheduleTest` (4), `GraduationRegistrationTest` (8), `PublicDocumentTest` (2), `QueueHistoryAndLogTest` (12), `QueueTicketTest` (4), dan `SyncTest` (8). Kegagalan tersebut belum diperbaiki pada fitur jadwal ini.

Tests memakai database `dadu_testing`, terpisah dari database aplikasi. Terdapat pengujian tanggal mulai yang berimpit dengan pendaftaran, batas waktu tepat, perpanjangan setelah seleksi kandidat, legacy tanpa jadwal, pendaftaran tertutup/revisi terbuka, inactive period, dokumen final/pinjaman, serta pengajuan lama yang identitas/snapshot-nya tetap sama setelah jadwal diisi.

Lint19 berkas PHP non-Blade yang berubah dan syntax tiga blok Bash pada panduan ini lulus. Seluruh partial Blade juga terkompilasi. Penyesuaian tes lama mengikuti kontrak baru: deadline revisi terpisah, tombol aktif disembunyikan saat needs_review, serta alasan penolakan dan actor timeline sesuai permintaan.

Pengujian UI memakai render Blade/DOM dan helper JavaScript; belum dilakukan pemeriksaan browser interaktif atau build/runtime image Docker dari proses ini.

## 6. Berkas perubahan

- `app/Http/Controllers/SidangTaDraftController.php`
- `app/Http/Controllers/SidangTaRegistrationController.php`
- `app/Models/SidangTaPeriod.php`
- `app/Services/SidangTaExpiredDraftCleanup.php`
- `app/Services/SidangTaHistoryPresenter.php`
- `app/Services/SidangTaRevisionExpiry.php`
- `app/Support/SidangTaRevisionDeadline.php`
- `database/migrations/2026_11_09_000001_add_revision_window_to_sidang_ta_periods.php`
- `resources/js/sidang-ta-wizard.js`
- `resources/views/sidang_ta_registrations/admin/index.blade.php`
- `resources/views/sidang_ta_registrations/admin/periods.blade.php`
- `resources/views/sidang_ta_registrations/admin/show.blade.php`
- `resources/views/sidang_ta_registrations/history.blade.php`
- `resources/views/sidang_ta_registrations/index.blade.php`
- `resources/views/sidang_ta_registrations/partials/period-countdown.blade.php`
- `resources/views/sidang_ta_registrations/partials/period-schedule.blade.php`
- `resources/views/sidang_ta_registrations/revision-start.blade.php`
- `resources/views/sidang_ta_registrations/show.blade.php`
- `resources/views/sidang_ta_registrations/wizard-review.blade.php`
- `resources/views/sidang_ta_registrations/wizard.blade.php`
- `tests/Feature/SidangTaDetailUiTest.php`
- `tests/Feature/SidangTaExpiredDraftCleanupTest.php`
- `tests/Feature/SidangTaHistoryTest.php`
- `tests/Feature/SidangTaMeetingUiTest.php`
- `tests/Feature/SidangTaPeriodScheduleUiTest.php`
- `tests/Feature/SidangTaPeriodTest.php`
- `tests/Feature/SidangTaReviewListTest.php`
- `tests/Feature/SidangTaRevisionDeadlineTest.php`
- `tests/Feature/SidangTaRevisionWindowTest.php`
- `tests/Feature/SidangTaStaffNavigationTest.php`
- `tests/Feature/SidangTaStudentPolishTest.php`
- `tests/Js/sidang-ta-wizard.test.mjs`
- `docs/84-rancangan-jadwal-pendaftaran-dan-revisi-sidang-ta.md`
- `docs/85-rencana-implementasi-jadwal-verifikasi-sidang-ta.md`
- `docs/86-hasil-jadwal-verifikasi-dan-revisi-sidang-ta.md`

Referensi sintaks/operasi dicocokkan melalui Context7 dengan [Laravel13 migrations](https://github.com/laravel/docs/blob/13.x/migrations.md) dan [Laravel13 scheduler](https://github.com/laravel/docs/blob/13.x/scheduling.md). Rancangan dan rencana pelaksanaan tersedia pada [84](84-rancangan-jadwal-pendaftaran-dan-revisi-sidang-ta.md) dan [85](85-rencana-implementasi-jadwal-verifikasi-sidang-ta.md).
