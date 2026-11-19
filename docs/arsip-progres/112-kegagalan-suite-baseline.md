# 112 — Kegagalan suite yang sudah ada pada commit awal

> Arsip progres: keputusan dan perintah di sini mencerminkan tahap saat ditulis. Untuk pemakaian terkini buka [panduan](../panduan-pemakaian/README.md); hasil singkat tersedia di [kesimpulan](../kesimpulan/README.md).

Perbandingan memakai commit Docker awal `57d06d7aa87fbb87b1dbb05a0a488ef2c2321dc8` pada snapshot terisolasi `/tmp/dadu111-baseline`, PHP8.5.11, MySQL8.4.13, database khusus `dadu_testing`. Sepuluh berkas tes yang gagal pada suite lengkap diuji ulang pada baseline: **49 gagal,94 lulus,687 assertions**. Nama kasus berikut berasal dari JUnit aktual; bukan asumsi berdasarkan lokasi perubahan.

## Nama kasus baseline yang gagal

### Tests.Feature.Auth.PasswordResetTest (3)

- `reset password link can be requested`
- `reset password screen can be rendered`
- `password can be reset with valid token`

### Tests.Feature.Auth.PasswordUpdateTest (1)

- `password can be updated`

### Tests.Feature.ExamScheduleTest (4)

- `guest cannot access autocomplete search endpoints`
- `authenticated user gets empty json if autocomplete query is empty or less than 2 characters`
- `autocomplete search returns matching distinct courses`
- `autocomplete search returns matching lecturers with name validation`

### Tests.Feature.GraduationRegistrationTest (8)

- `admin can see active period countdown banner on graduation registration index`
- `countdown banner is not visible if there is no active period`
- `countdown banner shows expired status when active period is closed`
- `student can submit graduation registration with student email and conditional title change`
- `admin can delete graduation registration submission`
- `student can submit graduation registration with honors predicate cumlaude and criteria hki`
- `admin can change review status of graduation registration`
- `backend blocks submission if student is ineligible due to failed courses or tak score`

### Tests.Feature.PublicDocumentTest (2)

- `it displays documents of a specific category folder when filtered`
- `it displays search results across categories and allows searching within a folder`

### Tests.Feature.QueueHistoryAndLogTest (12)

- `event called and finished are logged during next call cycle`
- `event skipped is logged when officer skips ticket`
- `event undo_call is logged when officer cancels call`
- `history page lists records from both active tickets and archived histories`
- `filtering by nim searches across all months ignoring date filters`
- `month filter strictly respects month boundaries`
- `queue:export-month exports full month to storage/app/queue-exports and overwrites cleanly`
- `queue:prune-history strictly rejects deletion if export file does not exist`
- `queue:prune-history strictly rejects deletion if export file is empty 0 bytes`
- `queue:prune-history strictly rejects deletion if export row count does not match database count`
- `queue:prune-history dry-run previews deletion count without modifying database`
- `queue:prune-history successfully prunes data older than retention limit when all safeguards pass`

### Tests.Feature.QueueTicketTest (4)

- `visitor get take page displays form when queue is open`
- `ambil tiket sukses via POST /queue/take saves details and sets device_token cookie`
- `ambil tiket saat tutup ditolak dan menampilkan view closed`
- `dua request berurutan/bersamaan tidak menghasilkan kode kembar`

### Tests.Feature.SidangTaMeetFinalizationTest (1)

- `meet_final_staff_can_read_export_and_verify_while_revision_requires_document_feedback`

### Tests.Feature.SidangTaReviewExportTest (6)

- `p2b_export_keeps_historical_snapshot_after_live_student_changes`
- `p2b_xlsx_preserves_text_ids_phones_and_literal_formula_like_content`
- `p2b_export_document_columns_distinguish_na_missing_and_unavailable`
- `p2b_uploaded_documents_are_clickable_in_existing_columns_for_both_branches with data set "dataset "biodata sesuai dan EPRT memenuhi""`
- `p2b_uploaded_documents_are_clickable_in_existing_columns_for_both_branches with data set "dataset "bukti PDM dan surat permakluman""`
- `p2b_missing_or_inapplicable_uploads_do_not_create_broken_links`

### Tests.Feature.SyncTest (8)

- `admin can import valid student JSON`
- `admin can import flat array Course JSON`
- `admin can import associative Course JSON`
- `admin can import valid KRS JSON when student exists`
- `admin can import flat array KRS JSON and automatically create or update student profile`
- `admin can import valid Attendance JSON when student exists`
- `sync import skips rows if student does not exist for KRS and Attendance`
- `admin can import graduation JSON payload`

Kegagalan ini tidak diperbaiki dalam fitur jadwal ruang/TA karena mencakup kontrak tes lama Auth, ujian, Yudisium, antrean, dokumen, ekspor TA lama dan impor teks JSON. Satu fixture baru sempat memakai `needs_revision` yang bukan enum aplikasi; telah diganti menjadi `needs_review`. Hasil suite akhir dibandingkan lagi dengan daftar ini.

## Baseline source asli diperiksa terpisah

Setelah port,58 tes integrasi terkait pada snapshot `/dadu` lulus dan1 kasus gagal: `fresh seed gives Pelaksana scoped viewing without TA review or period management` dalam `SidangTaProgramReadAccessTest.php`. Commit source awal `1c83b32d5f7fd9547666ecf300e0cba6f113e208` mereproduksi kegagalan identik pada uji tersendiri (1 gagal,2 assertions). `RolePermissionSeeder.php` source belum memuat default `sidang_ta_registration_view_program` seperti repo Docker. Tidak menimpa seeder source atau menjalankannya pada database aplikasi; perubahan hak akses operasional tetap melalui pengaturan yang sudah dipakai pengguna. Hasil terarah source sesudah port:58 lulus,1 gagal baseline,230 assertions.
