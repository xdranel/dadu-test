# 111 — Jadwal Sidang TA dan Peminjaman Ruangan Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans untuk pelaksanaan langsung oleh agen utama, atau superpowers:subagent-driven-development jika metode itu dipilih. Kerjakan task sesuai dependensi; checkbox mencatat hasil nyata, bukan perkiraan.

**Goal:** Mahasiswa approved memperoleh satu jadwal ruang kelas paling awal, dapat mengonfirmasinya, dan melihat booking otomatis/alternatif dosen yang dikelola Divisi Akademik melalui SSC tanpa bentrok atau booking TA ganda.

**Architecture:** Pola mingguan ditautkan ke semester dan dihitung ketika tanggal diperiksa. Satu service ketersediaan dipakai peminjaman dosen dan TA. Konteks penjadwalan TA memuat mode otomatis/dosen, versi, dan hubungan booking; semua transisi terkait dilakukan secara utuh dan dicatat.

**Tech Stack:** PHP `^8.4.27`, Laravel `^13.0`, Pest `^4.0`, MySQL lingkungan aplikasi, Blade/Alpine/Tailwind yang sudah ada; tanpa dependency baru. Versi berasal dari `composer.json` yang dibaca, bukan asumsi versi runtime production.

**Spec:** [Rancangan110](110-rancangan-integrasi-jadwal-sidang-ta-dan-peminjaman-ruangan.md), [pertanyaan/jawaban dan alur109](109-rescan-dan-catatan-awal-jadwal-pelaksanaan-sidang-ta.md). T13 menyetujui penandaan/penautan oleh admin. Rincian implementasi di sini merupakan keputusan teknis asisten untuk ditinjau, bukan tambahan jawaban divisi.

**Status:** Rencana tertulis; seluruh checkbox belum dikerjakan. Tidak ada kode/migrasi/tes implementasi yang dijalankan pada tahap penulisan ini. Dokumen hasil implementasi dibuat terpisah setelah pekerjaan kode dimulai.

## Global Constraints

- Hanya ruang kelas Dapat Dipakai yang kosong tanpa bentrok; mahasiswa memilih tanggal saja.
- Nilai awal sementara: Senin–Jumat,08.00–16.00 WIB, istirahat12.00–13.00,120 menit; tanggal mulai–selesai per periode TA.
- Approved TA menjadi syarat; masa pendaftaran/verifikasi yang telah tutup tidak menghalangi pemesanan tanggal pelaksanaan yang sah.
- Pratinjau tidak menahan ruang; konfirmasi langsung approved. Mahasiswa tidak dapat mengubah/membatalkan setelah konfirmasi; hubungi SSC/Divisi Akademik.
- Pola `TETAP` berlaku mingguan dalam semester sumber yang sesuai. Tidak menghapus data legacy atau semester lain dengan menebak konteksnya.
- Pending/approved internal sama-sama menahan ruang. Alur dosen membatalkan pending miliknya dipertahankan, termasuk sinkronisasi bila tertaut TA.
- Penandaan mode dosen dilakukan admin sebelum proses alternatif; penautan eksplisit, bukan pencocokan tujuan bebas. Tab lama tidak dapat melewati perubahan mode/status.
- Pembatalan persetujuan TA sebelum pelaksanaan membatalkan booking terkait; riwayat dan status/warna dokumen dipertahankan.
- Satu booking aktif per pengajuan/NIM merupakan pilihan teknis rancangan; jangan diam-diam mengubahnya menjadi aturan resmi divisi.
- Tidak mengubah SSO, unit, Yudisium/stash, antrean atau ekspor TA; tidak mereset hak akses/seeder, memakai migrate:fresh, commit/push, atau deploy selama implementasi lokal. Commit tetap ditangani pengguna.
- Rescan sebelum/sesudah tiap task; pertahankan perubahan awal pengguna dan gunakan isolasi yang sesuai saat eksekusi. Jangan menyalin seluruh `/dadu` ke `/dadu-test`.
- Basis kode eksekusi adalah checkout terisolasi dari `/home/xdranel/Code/Project/php-laravel/dadu` yang sudah memuat modul teman; rescan HEAD saat eksekusi. Path relatif pada task merujuk basis kerja tersebut. Port ke `/dadu-test` dilakukan selektif pada task9.
- Semua tes database wajib memakai database khusus testing. Hasil SQLite tidak disebut bukti locking MySQL. Jika baseline gagal, laporkan dan pisahkan dari kegagalan tambahan.

## Review Focus

1. Pratinjau lama dikonfirmasi setelah admin beralih ke mode dosen: ditolak tanpa membuat booking. Dikunci oleh tes task6/7.
2. Dosen membatalkan pending yang sudah tertaut: slot dilepas, konteks TA diperbarui, mode dosen tetap. Dikunci oleh tes task7.
3. Callback/endpoint peminjaman lama melewati otorisasi TA atau menghidupkan booking cancelled: ditolak. Dikunci oleh tes task4/7.
4. Source semester belum siap, impor baru bentrok, atau batas periode berubah: tidak menawarkan kekosongan palsu atau mengganti sebagian data. Dikunci oleh tes task3/5.
5. Dua pemesan/penautan/pembatalan bersamaan: satu hasil sah, tanpa kehilangan booking lama atau perubahan lintas-NIM. Dikunci oleh tes task6/7/9 pada MySQL.

## Konvensi interface dan data bersama

Tipe `User`, `SidangTaRegistration`, `SidangTaPeriod`, dan `RoomBookingRequest` merujuk model yang sudah ada. Nama baru di bawah dipakai konsisten oleh seluruh task.

- `SidangTaExecutionSetting`: satu per periode TA; `academic_period_id`, `start_date`, `end_date`, `allowed_weekdays` ISO1–7, `start_time`, `end_time`, `break_start_time`, `break_end_time`, `duration_minutes`, `lock_version`.
- `RoomWeeklyPattern`: periode akademik, ruang, hari ISO, jam, kode dosen/keterangan, identitas sumber/batch. `RoomScheduleSource`: periode, kelengkapan/cakupan, versi/fingerprint, referensi impor berhasil.
- `SidangTaSchedule`: satu per pengajuan, NIM, `mode` (`automatic`/`lecturer`), `state` (`unassigned`/`awaiting_lecturer`/`pending`/`confirmed`/`cancelled`), `room_booking_request_id`, `lock_version`, `was_confirmed`, `active_student_number` nullable unik untuk konteks aktif.
- `active_student_number` ditahan saat mode dosen menunggu, pending, atau confirmed; dilepas jika konteks dibatalkan. `was_confirmed` tidak dihapus hanya karena booking batal. Booking baru pengganti setelah pernah confirmed tetap tindakan petugas.
- `RoomBookingRequest` mempunyai `booking_origin` (`ordinary`/`sidang_ta_automatic`) dan FK nullable ke pengajuan TA. Booking alternatif mempertahankan user peminjam dosen/origin ordinary; keterkaitan tidak ditentukan dari teks. Satu booking tidak dipakai untuk pengajuan lain.
- Histori booking ditambah snapshot sebelum–sesudah JSON bila diperlukan; catatan teks lama tetap utuh. Penandaan/penautan dan transisi TA juga dicatat.
- Waktu interval internal dihitung pada menit lokal Asia/Jakarta; input JSON tetap dibaca tanpa mengubah nama field. Interval akhir eksklusif; tidak membuat pembulatan jam bulat.

## Task 1 — Fondasi modul dan skema kompatibel

**Files:**
- Create: `database/migrations/2026_11_15_110001_create_room_weekly_patterns.php`, `2026_11_15_110002_create_sidang_ta_scheduling_tables.php`, `2026_11_15_110003_extend_room_booking_for_sidang_ta.php` pada direktori migrasi yang sama.
- Create: `app/Models/RoomWeeklyPattern.php`, `RoomScheduleSource.php`, `SidangTaExecutionSetting.php`, `SidangTaSchedule.php` pada direktori model yang sama.
- Modify: `app/Models/Room.php`, `RoomBookingRequest.php`, `RoomBookingHistory.php`, `SidangTaRegistration.php`, `SidangTaPeriod.php`, `AcademicPeriod.php` pada direktori model yang sama.
- Create/Test: `tests/Support/TaRoomScenario.php`, `tests/Feature/SidangTaRoomSchemaCompatibilityTest.php`.

**Interfaces:** Menghasilkan model/data sesuai konvensi di atas; helper `Tests\Support\TaRoomScenario::make(): array` menghasilkan mahasiswa/petugas, approved registration, periode TA, periode akademik, pengaturan, sumber lengkap, serta kelas/lab contoh. Helper tidak menjalankan seeder global.

- [x] Tulis tes migrasi tambahan yang mempertahankan peminjaman ordinary pending/approved, occurrence, history dan approval TA lama; field baru default kompatibel, klasifikasi kelas belum ditentukan.
- [x] Jalankan `php artisan test tests/Feature/SidangTaRoomSchemaCompatibilityTest.php --compact` di DB testing; pastikan RED karena skema/model baru belum ada, bukan kegagalan bootstrap yang tidak terkait.
- [x] Buat migrasi tambahan/relasi/cast; gunakan tipe FK sesuai tabel asal dan constraint unik konteks. Jangan mengedit migrasi yang sudah terpasang di server.
- [x] Jalankan kembali tes; harapkan PASS dan data sebelum migrasi tetap identik pada field lama. Uji upgrade dari skema lama, bukan hanya fresh install.
- [x] Rescan dan tinjau diff task; tidak membuat commit otomatis. Pastikan source Docker yang belum mempunyai modul menerima dua migrasi dasar room milik teman sebelum migrasi baru ketika porting task9.

## Task 2 — Interval kosong dan pemeriksaan ketersediaan bersama

**Files:**
- Create: `app/Support/RoomTimeWindows.php`, `app/Services/RoomAvailabilityService.php`.
- Test: `tests/Unit/RoomTimeWindowsTest.php`, `tests/Feature/RoomAvailabilityServiceTest.php`.

**Interfaces:**
- `RoomTimeWindows::firstFit(array $busy, array $allowed, int $durationMinutes, int $notBeforeMinute = 0): ?array`: input list interval menit `start`/`end`, hasil interval pertama atau null.
- `RoomAvailabilityService::busyIntervals(int $roomId, string $date, ?int $exceptBookingId = null): array`: gabungkan pola semester yang berlaku, legacy date, dan pending/approved internal.
- `RoomAvailabilityService::forRange(string $from, string $until): array`: map tanggal/ruang ke interval terpakai untuk kalender/detail, dibatasi rentang yang diminta.
- `RoomAvailabilityService::coverage(string $from, string $until): array`: `ready`, `missing_dates`, dan `source_versions`; coverage belum diketahui tidak dinyatakan kosong.

- [x] Tulis tes pure dengan contoh berikut, ditambah waktu bertumpuk/berdampingan, celah1 jam, istirahat dan notBefore.

```php
$allowed = [['start' => 480, 'end' => 720], ['start' => 780, 'end' => 960]];
expect(RoomTimeWindows::firstFit([['start' => 480, 'end' => 570]], $allowed, 120))
    ->toBe(['start' => 570, 'end' => 690]); // 09.30–11.30
expect(RoomTimeWindows::firstFit([['start' => 480, 'end' => 660]], $allowed, 120))
    ->toBe(['start' => 780, 'end' => 900]); // 13.00–15.00
```

- [x] Tulis/jalankan RED feature: Senin9→23 November, batas semester inklusif, flag KRS aktif tidak menghapus pemakaian fisik, source belum lengkap menghasilkan unknown, legacy/date dan pola tidak dihitung ganda.
- [x] Implementasikan interval dan service; baca data per rentang secara batch, perluas pola hanya ke tanggal dalam rentang dan semester, jangan membuat record tanggal semester penuh.
- [x] Jalankan `php artisan test tests/Unit/RoomTimeWindowsTest.php tests/Feature/RoomAvailabilityServiceTest.php --compact`; harapkan PASS.
- [x] Rescan dan tinjau cakupan sumber serta hasil interval. Kontrak service ini dipakai importer pada task3; source/pattern untuk tes task2 dibuat lewat fixture task1 tanpa bergantung pada importer baru.

## Task 3 — Kontrak pola mingguan dan impor per semester

**Files:**
- Create: `app/Support/WeeklyRoomScheduleNormalizer.php`.
- Modify: `app/Services/RoomScheduleImportService.php`, `app/Http/Controllers/SyncController.php`, `resources/views/sync/index.blade.php`.
- Test: `tests/Unit/WeeklyRoomScheduleNormalizerTest.php`, `tests/Feature/WeeklyRoomScheduleImportTest.php`.

**Interfaces:**
- `WeeklyRoomScheduleNormalizer::normalize(array $payload, array $periodContext): array`: hasil `patterns`, `period_id`, `source_week`, `scope`, `fingerprint`; normalisasi tidak menulis DB. Context memuat ID/year/semester/start_date/end_date yang terdaftar.
- Pertahankan `RoomScheduleImportService::import(array $payload, User $actor, string $filename): array` dan key hasil lama `imported`, `errors`, `successful`.
- Source/pattern tersimpan melalui model task1. Metadata field periode tiap baris harus sesuai context.

- [x] Tulis RED untuk `2627/1`, hari/tanggal cocok, unknown term/jenis ditolak, duplikat pola digabung, waktu23.30 tetap terbaca, file parsial tidak mengganti baseline, semester lain/history/internal tetap utuh.
- [x] Jalankan dua file tes dengan `php artisan test tests/Unit/WeeklyRoomScheduleNormalizerTest.php tests/Feature/WeeklyRoomScheduleImportTest.php --compact`; harapkan kegagalan karena normalizer/fitur belum ada.
- [x] Implementasikan normalizer, pemetaan periode eksplisit, kelengkapan berdasarkan kontrak metadata/filter dan seluruh baris valid; jangan menyimpulkan lengkap dari total saja atau menuntut tiap hari memiliki kelas. Simpan pola sekali, dengan batas semester pada relasinya.
- [x] Implementasikan penggantian atomik snapshot lengkap per semester, penolakan bentrok dengan pending/approved, dan source readiness memakai service task2. Gunakan koordinasi source/room sesuai urutan yang ditetapkan pada task4; jalur impor tidak mengambil lock parent TA setelah source/room.
- [x] Jalankan kedua tes sampai PASS, termasuk sambungan bentrok service task2. Rescan diff dan pastikan data field lama/semester lain tetap utuh.

## Task 4 — Integrasi peminjaman biasa dan koordinasi perubahan

**Files:**
- Create: `app/Services/RoomBookingTransitionService.php`, `app/Services/SidangTaBookingLock.php`, `app/Services/SidangTaBookingAccess.php`.
- Modify: `app/Http/Controllers/RoomBookingController.php`, `resources/views/room-bookings/dashboard.blade.php`, `review.blade.php`, `create.blade.php`, `index.blade.php` pada direktori view yang sama.
- Test: `tests/Feature/RoomBookingSharedAvailabilityTest.php`.

**Interfaces:**
- `RoomBookingTransitionService::decide(User $actor, RoomBookingRequest $booking, string $decision, ?string $note): RoomBookingRequest`.
- `RoomBookingTransitionService::cancel(User $actor, RoomBookingRequest $booking, string $reason): RoomBookingRequest`.
- `SidangTaBookingAccess::authorizeStudent(User $actor, SidangTaRegistration $registration, bool $writing = false): void`: konteks mahasiswa/NIM, permission read; mutasi awal juga memerlukan permission create TA.
- `SidangTaBookingAccess::authorizeStaff(User $actor, ?RoomBookingRequest $booking = null): void`: Review TA; link/koreksi ordinary lecturer juga hak pengelolaan peminjaman sesuai gate yang ada.
- `SidangTaBookingLock::run(int $registrationId, array $additionalRoomIds, \Closure $callback): mixed`: transaksi konteks TA dan ruang; target/versi ditinjau ulang sebelum mutasi.

- [x] Tulis RED untuk semua entry point: kalender/detail, cek ketersediaan, store, decide, cancel pemilik pending, cancel admin approved; pola mingguan harus sama. Ordinary lecturer tetap mengikuti role/permission/status sebelumnya.
- [x] Jalankan `php artisan test tests/Feature/RoomBookingSharedAvailabilityTest.php --compact`; verifikasi RED pada pola/guard yang belum ada.
- [x] Ganti pembacaan tanggalan yang relevan dengan service task2, pertahankan jam06.30–21.30 dan cakupan ruang ordinary. Source unknown diberi status/pesan tersendiri, tidak dipetakan hijau available.
- [x] Tetapkan koordinasi lock berdasarkan audit urutan lama: Student → registrasi TA berurutan → periode TA → setting/context TA → periode akademik berurutan → rooms berurutan → booking → occurrence. Path ordinary/source yang tidak menyentuh TA mulai dari periode akademik lalu rooms; jangan naik mengambil parent TA setelah mengunci booking. Jika link berubah saat transaksi dimulai, rollback/reload alur, bukan mengambil lock dalam urutan terbalik.
- [x] Tambahkan guard endpoint lama untuk booking TA dan pertahankan ordinary lecturer pending cancel. Task4 menuntaskan peminjaman ordinary/ketersediaan/guard; integrasi callback mutasi linked ditambahkan bersama service lifecycle pada task7, tanpa memanggil class yang belum ada. Jalankan tes scope task4 sampai PASS lalu rescan; regresi diulang sesudah task7.

## Task 5 — Pengaturan pelaksanaan, kelas, dan penjagaan periode

**Files:**
- Create: `app/Services/SidangTaExecutionWindowService.php`, `app/Http/Controllers/Admin/SidangTaExecutionController.php`, `app/Http/Requests/SaveSidangTaExecutionRequest.php`, `resources/views/sidang_ta_registrations/admin/execution-settings.blade.php`.
- Modify: `app/Http/Controllers/AcademicPeriodController.php`, `RoomController.php`, `app/Http/Requests/StoreRoomRequest.php`, `UpdateRoomRequest.php`, `resources/views/rooms/create.blade.php`, `edit.blade.php`, `index.blade.php` pada direktori terkait, dan `routes/web.php`.
- Test: `tests/Feature/SidangTaExecutionSettingsTest.php`.

**Interfaces:**
- `SidangTaExecutionWindowService::save(User $actor, SidangTaPeriod $period, array $values, int $version): SidangTaExecutionSetting`.
- Request values memakai field settings pada konvensi, `lock_version`; default weekday1–5,08.00–16.00, break12.00–13.00,120 menit. Route GET `admin.sidang-ta-execution.index`; PUT `admin.sidang-ta-execution.update` pada konteks `{period}`.
- `rooms.is_classroom` nullable: true kelas, false bukan kelas, null belum dipastikan. Tidak otomatis mengubah ruang unknown menjadi kelas saat impor jadwal.

- [x] Tulis/jalankan RED untuk tanggal23–27 per periode, sumber semester cocok, hari/jam/istirahat/durasi valid, stale version, izin baca/ubah, dan setting yang memotong booking aktif ditolak tanpa perubahan.
- [x] Implementasikan form/service dengan periode sumber eksplisit, validasi rentang, default, dan alasan penyimpanan gagal per input. Periode akademik yang sudah dipakai pola tidak boleh diganti tahun/term; perubahan tanggal tidak boleh memunculkan konflik atau menghilangkan cakupan booking.
- [x] Tambahkan klasifikasi yang dapat diperiksa petugas. Excel lama tetap diterima; nama KELAS dapat ditawarkan sebagai petunjuk pemetaan, keputusan disimpan eksplisit. Tidak menetapkan kapasitas minimum yang belum disepakati.
- [x] Jalankan `php artisan test tests/Feature/SidangTaExecutionSettingsTest.php tests/Feature/AcademicPeriodManagementTest.php --compact`; harapkan PASS untuk fitur dan tidak menambah kegagalan baseline.
- [x] Rescan dan pastikan tanggal pendaftaran/verifikasi, status master ruang nonaktif, serta periode KRS lama tidak ditulis ulang sebagai efek samping.

## Task 6 — Pencarian, identitas, dan konfirmasi otomatis TA

**Files:**
- Create: `app/Services/SidangTaBookingService.php`, `app/Services/SidangTaBookingIdentity.php`, `app/Http/Controllers/SidangTaBookingController.php`, `app/Http/Requests/ConfirmSidangTaBookingRequest.php`.
- Test: `tests/Feature/SidangTaRoomBookingTest.php`.

**Interfaces:**
- `SidangTaBookingIdentity::forRegistration(SidangTaRegistration $registration): array`: ID pengajuan/NIM/nama, kode/nama pembimbing/penguji; data kosong ditandai, tanpa mengubah profil.
- `SidangTaBookingService::preview(User $actor, SidangTaRegistration $registration, string $date): array`: `identity`, `candidate`, `preview_token`, `context_version`; candidate berisi tanggal, room_id/code/name, start/end, duration atau null/alasan.
- `SidangTaBookingService::confirm(User $actor, SidangTaRegistration $registration, string $previewToken): SidangTaSchedule`.
- Preview token terautentikasi mengikat user/NIM/pengajuan, versi pengajuan/context/settings/source dan kandidat. Token bukan reservasi. Simpan ulang kandidat hanya setelah revalidasi dan perhitungan ulang earliest candidate.

- [x] Tulis/jalankan RED: approved lama tetap bisa, submitted/needs_review/rejected tidak bisa, pendaftaran tutup tetap bisa, kelas paling awal lintasruang, lab/unknown dikecualikan, kosong dosen tidak memblokir, source/tanggal tidak valid tidak menawarkan slot.
- [x] Tambahkan RED: preview tidak membuat occurrence; konfirmasi approved, satu aktif per NIM/pengajuan; data input/token tidak dapat mengganti NIM/ruang/jam; stale mode/source/setting ditolak; request ulang tidak membuat booking ganda.
- [x] Implementasikan service dengan task2/4/5. Bekukan nilai jadwal yang dikonfirmasi dan tandai `was_confirmed`; identitas tanggal memakai pilihan/hasil saat ini, bukan tanggal historis profil. Gunakan callback room transaction yang konsisten, bukan menyalin private helper controller.
- [x] Jalankan `php artisan test tests/Feature/SidangTaRoomBookingTest.php --compact`; harapkan PASS. MySQL concurrency dilaksanakan pada task9, belum diklaim dari tes serial ini.
- [x] Rescan, periksa tidak ada mutasi `academic_snapshot`, judul/pembimbing, status verifikasi dokumen, atau pembayaran akibat booking.

## Task 7 — SSC, penautan dosen, lifecycle, dan pembatalan persetujuan

**Files:**
- Create: `app/Services/SidangTaBookingAdministration.php`, `app/Services/SidangTaBookingLifecycle.php`, `app/Http/Controllers/Admin/SidangTaBookingController.php`.
- Modify: `app/Services/SidangTaRegistrationReview.php`, `app/Services/SidangTaHistoryPresenter.php`, `RoomBookingTransitionService.php` dan controller peminjaman pada task4.
- Test: `tests/Feature/SidangTaLecturerBookingLinkTest.php`, `tests/Feature/SidangTaBookingLifecycleTest.php`.

**Interfaces:**
- `SidangTaBookingAdministration::markLecturer(User $actor, SidangTaRegistration $registration, int $version, string $reason): SidangTaSchedule`.
- `SidangTaBookingAdministration::linkLecturerBooking(User $actor, SidangTaRegistration $registration, RoomBookingRequest $booking, int $version, string $reason): SidangTaSchedule`.
- `SidangTaBookingAdministration::reschedule(User $actor, SidangTaRegistration $registration, array $slot, int $version, string $reason): SidangTaSchedule`.
- `SidangTaBookingAdministration::cancel(User $actor, SidangTaRegistration $registration, int $version, string $reason): SidangTaSchedule`.
- `SidangTaBookingLifecycle::afterRoomStatusChanged(RoomBookingRequest $lockedBooking, User $actor, string $fromStatus, ?string $note): void`: dipanggil dalam transaksi yang sudah memegang parent/room/booking sesuai urutan task4.
- `SidangTaBookingLifecycle::cancelOnApprovalWithdrawal(SidangTaRegistration $lockedRegistration, User $actor): void`: internal transaksi pembatalan persetujuan; hanya booking tertaut sebelum pelaksanaan, tanpa menghapus history.

- [x] Tulis RED mode dosen sebelum peminjaman: tab preview lama ditolak, mode menahan konteks NIM, tidak membuat occurrence. Jika automatic sudah confirmed, penandaan ditolak sampai petugas menyelesaikannya secara eksplisit.
- [x] Tulis RED penautan pending/approved single satu occurrence yang sesuai kelas/tanggal/jam/durasi TA. Tolak booking milik pengajuan lain, multioccurrence, cancelled/rejected, atau slot tidak sesuai. Link pending tidak menyetujui booking, tidak membuat occurrence kedua, dan tidak mengganti pemilik dosen.
- [x] Tulis RED pending linked kemudian approved/rejected/cancelled: state/context/history ikut berubah; dosen pemilik masih boleh cancel pending, mahasiswa tidak mendapat hak itu; mode tidak kembali automatic diam-diam. Endpoint room-review saja tidak dapat mengubah booking TA sembarang.
- [x] Tulis RED koreksi SSC: mahasiswa/lecturer-read dilarang termasuk request langsung; gagal pindah mempertahankan booking lama; berhasil pindah/cancel dicatat. Uji T10 dengan linked pending/approved: pembatalan approval atomik, status/color berkas/history utuh, peminjaman lain tidak berubah, tab persetujuan lama tidak menghidupkan cancelled.
- [x] Implementasikan interface/lifecycle dan hook pembatalan107 hanya setelah guard/lock yang lama lulus. Mutasi callback tidak mengambil parent lock setelah booking lock. Jika binding berubah saat request berlomba, rollback lalu minta reload.
- [x] Jalankan `php artisan test tests/Feature/SidangTaLecturerBookingLinkTest.php tests/Feature/SidangTaBookingLifecycleTest.php tests/Feature/SidangTaApprovalCancellationTest.php tests/Feature/SidangTaVerificationExtensionTest.php --compact`; harapkan PASS pada kasus baru dan tidak ada kegagalan tambahan terkait107/108. Rescan seluruh berkas terkait.

## Task 8 — Tampilan mahasiswa/admin dan rute lengkap

**Files:**
- Create: `resources/views/sidang_ta_registrations/booking/index.blade.php`, `partials/booking-identity.blade.php`, `partials/booking-card.blade.php` pada direktori TA yang sesuai; `admin/booking.blade.php`.
- Modify: `app/Http/Controllers/SidangTaRegistrationController.php`, controller baru task5/6/7, `resources/views/sidang_ta_registrations/index.blade.php`, `admin/index.blade.php`, `admin/show.blade.php`, `routes/web.php`.
- Modify: `app/Http/Controllers/LecturerController.php` untuk alias header yang diperlukan menampilkan kode/nama dari Excel; jangan mengubah kebijakan seluruh importer tanpa kebutuhan.
- Test: `tests/Feature/SidangTaBookingUiTest.php`, `tests/Feature/LecturerBookingIdentityImportTest.php`.

**Interfaces:**
- GET halaman/cari dan POST konfirmasi memakai route `sidang-ta-bookings.index`, `sidang-ta-bookings.search`, `sidang-ta-bookings.confirm` dengan `{sidang_ta_registration}`. Hasil pencarian hanya satu candidate.
- Route admin `admin.sidang-ta-bookings.show`, `.mark-lecturer`, `.link-lecturer`, `.reschedule`, `.cancel`; mutasi memuat `context_version` dan alasan. ID booking dipilih eksplisit dari data yang boleh dilihat petugas, bukan input identitas mahasiswa bebas.
- Controller memakai service task5/6/7; tidak menulis status/occurrence langsung.

- [x] Tulis/jalankan RED untuk kartu di samping draft, dua kartu pengelolaan admin, periode aktif di bawah, form tanggal saja, identitas lengkap, fallback Belum tersedia, preview/confirm, mode dosen pending/confirmed/cancelled dan petunjuk SSC tanpa tombol mahasiswa Ubah/Batal.
- [x] Implementasikan tampilan dengan komponen proyek, label/error yang jelas dan responsif. Tampilkan kode sumber walau nama master belum ada; tidak mengarang penguji. Hindari memperlihatkan skema/token/lock kepada pengguna.
- [x] Tambahkan alias Excel `Kode Dosen Baru`, `Email Domain Telkom University`, `STATUS` yang diperlukan; uji sumber lama tetap terbaca. Untuk record yang sudah ada, pembaruan field identity harus eksplisit dan teruji, tanpa menimpa field lengkap dengan kosong atau membuat duplikat berdasarkan nama.
- [x] Jalankan `php artisan test tests/Feature/SidangTaBookingUiTest.php tests/Feature/LecturerBookingIdentityImportTest.php tests/Feature/SidangTaPeriodScheduleUiTest.php tests/Feature/SidangTaProgramReadAccessTest.php --compact`; harapkan PASS fitur baru dan tidak ada regresi akses prodi/jadwal lama.
- [x] Rescan dan kompilasi view pada workspace testing; pastikan tidak membersihkan cache runtime aplikasi pengguna. Build aset hanya jika diperlukan perubahan CSS/JS; dependency/lockfile tidak diubah.

## Task 9 — Verifikasi MySQL, port selektif, dan catatan hasil

**Files:**
- Test/Create: `tests/Feature/SidangTaBookingConcurrencyTest.php`, `tests/Support/TaRoomConcurrencyHarness.php`, `docs/fixtures/sidang-ta/111-room-schedule-patterns-demo.json`.
- Create: dokumen hasil112 dan daftar file aktual untuk salin; tautkan109/110/111.
- Port ke `/dadu-test` hanya dependency room dasar yang diperlukan: controller room booking, tiga model room booking, RoomScheduleImportService, empat view `room-bookings`, dua migrasi dasar room11/14 November; gabungkan patch room pada route, config permission/sidebar, SyncController/view, RoomController/LecturerController sesuai diff aktual.

**Interfaces:** `TaRoomConcurrencyHarness::race(array $jobs): array` menjalankan koneksi/proses MySQL independen pada DB khusus dengan fixture yang committed dan dibersihkan; tidak dibungkus satu transaksi testing yang tidak terlihat proses lain. Ketiadaan MySQL membuat hasil jelas unavailable/skipped dengan alasan, bukan PASS.

- [x] Tulis RED/siapkan tes konkurensi nyata: dua mahasiswa mengincar kandidat pertama; confirm vs markLecturer; link vs cancel/decide; import vs confirm; cancel approval vs lecturer approve; perubahan setting/source vs konfirmasi. Invarian: tidak ada overlap/dua booking TA aktif, status/histori atomik dan tidak deadlock yang dibiarkan tanpa penanganan.
- [x] Jalankan unit/feature terkait sekali di lingkungan testing yang tersedia; verifikasi MySQL terpisah memakai database `dadu_testing` atau database khusus yang jelas. Jangan memakai koneksi `.env` aplikasi secara implisit. Catat command/hasil nyata pada112; perbaiki kasus gagal dalam scope sebelum memperluas tes.
- [x] Jalankan suite regresi yang relevan dan lint/build sesuai perubahan. Bandingkan baseline bila suite lama gagal. Audit seluruh query `room_schedules` dan mutasi booking agar tidak ada jalur yang tertinggal memakai tanggalan saja atau melewati guard TA.
- [x] Buat allowlist/hash diff aktual dari source hasil kerja ke kedua folder. Jangan menyalin User/SSO/queue/seeder wholesale. Pertahankan konfigurasi Docker dan tiga perubahan lokal awal. Uji command copy pada direktori percobaan beserta backup, lalu pastikan isi target yang memang disalin sama.
- [x] Tulis dokumen112: apa yang berubah, file baru/berubah, skema tambahan, hasil tes/batasnya, rescan sebelum/sesudah, contoh alur otomatis/alternatif, urutan impor/penandaan kelas/pengaturan dan command update aman. Tidak mengklaim deployment telah dilakukan.
- [x] Lakukan review hasil implementasi dan rescan final. Serahkan commit message jika diminta; pengguna tetap menangani commit/push/deployment.

## Pemeriksaan rencana sebelum eksekusi

Pemetaan kebutuhan: T01/T08→task6/8; T02/T05/T07/T09→task2/5/6; T03/T04/T12→task2/3/4; T10→task7; T11→task4/7/8; T13→task7/8/9. Kekurangan data pada P11 dijelaskan di UI/fixture dan panduan, bukan ditutup dengan data buatan.

Langkah task bergantung pada interface yang sama di bagian konvensi. Task4/7 berbagi lifecycle/lock sehingga dikerjakan berurutan oleh agen utama; review dapat dilakukan independen tanpa mengedit file yang sama. Rekomendasi eksekusi: **Native**, agen utama mengimplementasikan, memakai reviewer selektif sesuai AGENTS; tidak membuat agen baru untuk setiap perubahan kecil.

Self-review selesai pada tahap penulisan: kebutuhan T01–T13 dipetakan, signature/nama komponen diperiksa, dan urutan task diperbaiki agar service interval/ketersediaan task2 tersedia sebelum importer task3. Task4 hanya menuntaskan jalur ordinary dan guard; callback linked ditambahkan bersama lifecycle task7, tanpa memanggil class yang belum ada.

Review independen terhadap pembaruan110/111 tidak menemukan masalah penghambat. Reviewer memeriksa konsistensi pemilik dosen membatalkan pending, larangan mahasiswa, urutan parent→room→booking, dan rollback/reload jika link berubah. Review ini adalah pemeriksaan dokumen, bukan bukti pengujian kode.

Rescan setelah penulisan:620 berkas `/dadu-test` dan637 berkas `/dadu` pada cakupan kode/config/test baseline tetap sama; HEAD kedua folder tetap. Git hanya menunjukkan tiga dokumen baru serta tiga perubahan lokal awal `/dadu` yang tidak berubah. Tautan109↔110↔111, nomor tanya jawab, urutan/interface sembilan task, whitespace dan `git diff --check` diperiksa dan lulus. Seluruh checkbox masih belum dikerjakan; belum ada claim tes implementasi lulus. Rencana diserahkan untuk tinjauan sebelum eksekusi sesuai workflow writing-plans.


Pembaruan16 November2026: Task1–9 selesai. Hasil kode, review independen, perbandingan baseline dan penyalinan selektif dicatat pada112. Tidak melakukan commit/push/deploy atau migrasi database aplikasi.
