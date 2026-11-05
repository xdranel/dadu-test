# 05 — Rencana implementasi draft dan form empat tahap Sidang TA

**Pembaruan keputusan testing:** pengguna mengonfirmasi checkpoint B lulus dan memilih MySQL Podman, tanpa SQLite. Ikuti [09](09-checkpoint-c1-struktur-draft-mysql.md) untuk konfigurasi MySQL dan kode lengkap C1. Instruksi SQLite pada catatan/scan awal di bawah bersifat historis dan tidak dipakai lagi. Contoh memakai schema dadu_testing di server Podman yang sama karena RefreshDatabase dapat mereset tabel tujuan. C dibagi menjadi C1 struktur data dan C2 service/endpoint tahap 1 agar setiap bagian punya test yang dapat dijalankan.

**Tujuan:** student memilih periode terbuka, menyimpan empat tahap beserta berkas di server, melanjutkan setelah halaman ditutup, meninjau data tersimpan, lalu mengirim satu pengajuan lengkap.

**Arsitektur:** dua tabel draft terpisah; upload per tahap; empat kelompok Blade/Alpine; finalisasi dari berkas tersimpan tanpa upload ulang. Status pengajuan final tetap `submitted`, `needs_review`, `approved`, `rejected`. Permission yang ada tetap dipakai.

**Stack:** PHP sesuai composer.json, Laravel 13, MySQL 8.4 lokal Podman, Blade, Alpine 3, Tailwind 3, Pest. Tidak menambah library UI/upload.

**Spesifikasi:** [03 peta dan rancangan](03-peta-fitur-sidang-ta-student.md), jawaban pengguna tentang draft lintas kunjungan dan pilihan periode; [04 upload](04-perbaikan-upload-sidang-ta.md).

Ini instruksi untuk pengguna yang mengerjakan kode di folder asli, bukan izin asisten mengimplementasikan aplikasi. Setelah tiap checkpoint, pindahkan sumber terbaru ke dadu-test lalu minta rescan [06](06-checklist-rescan-sidang-ta.md). Jangan mencentang pekerjaan yang belum diuji.

**Petunjuk kode test:** kode lengkap checkpoint B ada pada bagian 6 [dokumen 08](08-checkpoint-b-periode-sidang-ta.md#6-periksa-checkpoint-ini), termasuk fixture, sembilan test dan perintah menjalankan seluruh file. Tabel/nama test C–G di bawah adalah kontrak rencana, bukan file test siap salin atau perintah yang wajib dijalankan sekarang. Pada panduan rinci masing-masing checkpoint berikutnya, sediakan kode test lengkap sebelum meminta pengguna menjalankannya. Jangan menyuruh pengguna membuat test dari judul saja.

## Batasan global dan keputusan awal

- Seluruh field/key dokumen mengikuti tabel dokumen 03; jangan mengubah NIM, student_name, status, submitted_by atau snapshot dari input client.
- Empat tahap + mode tinjau. Tombol tahap berikutnya hanya aktif setelah tahap sebelumnya tervalidasi dan tersimpan server.
- Simpan dilakukan eksplisit melalui **Simpan & Lanjutkan** / **Simpan perubahan**. Teks UI menjelaskan pilihan file belum diunggah sampai simpan sukses. Refresh/tutup hanya mempertahankan data yang sudah tersimpan.
- Draft tidak ditampilkan pada daftar review petugas atau Riwayat Pengajuan final; sediakan kartu **Lanjutkan draft** terpisah di halaman student.
- Draft baru paling banyak satu aktif per NIM; revisi paling banyak satu draft aktif untuk registration yang sama. Pertahankan aturan lama satu pengajuan `submitted/needs_review` per NIM lintas periode. Perubahan kebijakan setelah approved/rejected tidak ditambahkan diam-diam.
- Dokumen tahap 2: PDF/JPG/JPEG/PNG; seluruh tahap 3 dan 4: PDF untuk upload baru, mengikuti gambar. Maksimum aplikasi 10 MiB per file. Existing file revisi yang dahulu sah tidak dipaksa konversi.
- Draft tidak otomatis kedaluwarsa pada versi pertama; periode tetap diperiksa saat final submit baru. Sediakan hapus draft eksplisit dan cleanup yang aman, bukan hapus diam-diam berdasarkan umur.
- Revisi tetap terkait periode pengajuan asal, tidak bisa pindah periode, dan boleh dikirim setelah periode ditutup sesuai perilaku lama sampai kebijakan petugas dikonfirmasi.

## File yang dibuat/diubah

| Tindakan | Path | Tanggung jawab |
| --- | --- | --- |
| Buat | `database/migrations/2026_11_01_000003_create_sidang_ta_registration_drafts.php` | Dua tabel draft + lock_version pada final; pilih timestamp berikutnya jika nama sudah dipakai saat implementasi. |
| Buat | `app/Models/SidangTaRegistrationDraft.php` | Relasi draft, payload, versi, status consumed. |
| Buat | `app/Models/SidangTaRegistrationDraftDocument.php` | Berkas privat milik draft; relasi source document saat revisi. |
| Buat | `app/Support/SidangTaDocumentRequirements.php` | Whitelist, pengelompokan, format dan conditional requirement bersama. |
| Buat | `app/Services/SidangTaStudentContext.php` | Resolusi Student/NIM dan snapshot dari perilaku controller sekarang. |
| Buat | `app/Services/SidangTaDraftService.php` | Simpan tahap, cek kelengkapan, tinjau, finalisasi, discard, kompensasi storage. |
| Buat | `app/Http/Controllers/SidangTaDraftController.php` | Endpoint draft yang tipis, auth dan response. |
| Buat | `app/Http/Requests/SaveSidangTaDraftStepRequest.php` | Validasi input per tahap dan ownership sebelum validasi existing files. |
| Ubah | `app/Http/Requests/StoreSidangTaRegistrationRequest.php` | Validasi final draft ID/version/action, tidak menerima upload final mentah. |
| Ubah | `app/Http/Controllers/SidangTaRegistrationController.php` | Create/edit/resume dan delegasi store/update ke finalizer; periode jamak. |
| Ubah | `app/Models/SidangTaPeriod.php`, `routes/web.php` | Query periode terbuka dan route draft dengan izin. |
| Ubah | `resources/views/sidang_ta_registrations/form.blade.php` | Form tahap dan penunjuk data tersimpan. |
| Ubah/buat | `resources/views/sidang_ta_registrations/partials/upload.blade.php`, `partials/stepper.blade.php`, `partials/review.blade.php`, `review.blade.php` | Upload existing/draft, navigasi dan ringkasan tinjau. |
| Buat | `resources/js/sidang-ta-wizard.js` | Interaksi tahap, validasi lokal, status simpan tanpa mengulang aturan domain. |
| Ubah | `resources/js/app.js` | Daftarkan Alpine component sebelum `Alpine.start()`. |
| Ubah | `resources/views/sidang_ta_registrations/{index,show}.blade.php` | Resume draft, periode terbuka, detail terkelompok. |
| Ubah | `app/Http/Controllers/DashboardController.php`, `resources/views/dashboard.blade.php` | Card TA tidak lagi bergantung `active()->first()`; link lanjut draft/pilih periode. |
| Ubah minimal | `resources/views/sidang_ta_registrations/admin/periods.blade.php` | Hapus teks hanya satu periode aktif; tidak mendesain ulang alur review petugas. |
| Ubah minimal | `resources/views/sidang_ta_registrations/admin/show.blade.php`, `app/Models/SidangTaRegistration.php` | Hidden registration_version pada review dan lock_version server; cegah keputusan dari halaman review lama menimpa revisi baru. |
| Buat | `tests/Feature/SidangTaDraftTest.php`, `SidangTaRegistrationTest.php`, `SidangTaPeriodTest.php` | Kontrak draft, final/revisi/izin dan periode jamak. |

Nama baru di tabel ini adalah usulan implementasi, belum ada di repositori saat scan. Gunakan satu service untuk alur baru dan revisi; jangan menyalin pipeline upload terpisah di setiap controller.

## Checkpoint A — Lindungi baseline dan selesaikan upload

- [ ] Kerjakan [04](04-perbaikan-upload-sidang-ta.md) dan catat hasil browser nyata. Database Podman tidak mengatur limit PHP host.
- [ ] Catat commit/diff sebelum coding dan kondisi pengajuan test. Jangan menjalankan `migrate:fresh` pada database development yang ingin dipertahankan.
- [ ] Siapkan database test terpisah. `phpunit.xml` memakai SQLite memory dan `tests/Pest.php` menerapkan RefreshDatabase; pastikan tidak tertimpa environment shell/config cache. Jangan menjalankan suite bila koneksi test menunjuk database development.
- [ ] Buat fixture test mandiri: role+permission relevan, user aktif dengan `must_change_password=false`, Student terkait, periode dan fake storage. Jangan bergantung akun manual/SSO eksternal atau seeder semua user.

**Selesai bila:** upload 14/15 x sekitar 1 MiB form lama bisa diukur dan test runtime aman. Kirim hasil untuk rescan sebelum checkpoint berikutnya.

## Checkpoint B — Periode yang dapat dipilih

Panduan rinci tahap ini tersedia di [08 — checkpoint B](08-checkpoint-b-periode-sidang-ta.md). Upload telah dilaporkan berhasil oleh pengguna; persiapan database/fixture test di checkpoint A tetap perlu dipastikan. B adalah perubahan antara: jangan menguji/menggunakan submit student dengan beberapa periode sebelum jalur pemilihan dan finalisasi C–F selesai, karena store lama masih memilih periode pertama.

**Interface:** tambahkan `SidangTaPeriod::scopeOpenForRegistration(Builder $query): Builder`; kondisi `is_active=true`, `open_at <= now()`, `close_at >= now()`, urutan `open_at`, lalu `id`. Tetap gunakan timezone aplikasi; jangan memaksakan label WIB bila konfigurasi berbeda.

- [ ] Tulis dahulu test `lists_only_open_periods` dan `allows_two_open_periods`; kontrol waktu test agar batas buka/tutup deterministik. Test ID pilihan baru dikerjakan C/D, test final setelah tutup dikerjakan F.
- [ ] Hapus operasi yang mematikan seluruh periode lain dari `storePeriod`, `updatePeriod`, `setActivePeriod`. Aktivasi mengubah periode target saja; nonaktifkan lewat form edit yang sudah ada. Update teks admin: beberapa periode bisa aktif, tetapi rentang tanggal tetap menentukan keterbukaan.
- [ ] `index/create` memakai koleksi periode terbuka, bukan `SidangTaPeriod::active()` tunggal. Tahap 1 memiliki select `sidang_ta_period_id`; default kosong bila lebih dari satu, boleh terpilih otomatis bila hanya satu. Tampilkan nama dan tanggal.
- [ ] Tetapkan kontrak untuk C/D/F: validasi ID pilihan baru tidak cukup `exists`; server memeriksa flag dan rentang waktu. Revisi selalu memakai periode asal meskipun tertutup, menolak ID pengganti dan tidak mengharuskan asal terbuka saat save tahap 1 maupun final.
- [ ] Implementasikan perubahan/resume periode draft saat model draft tersedia di C/D: pilihan baru harus terbuka, menaikkan version dan membatalkan tinjau; pilihan lama yang tutup tetap terlihat dengan label tutup. Tahap lain yang masih valid boleh dipertahankan. Ini bukan syarat kelulusan checkpoint B sendiri.
- [ ] Periksa semua pemanggilan `SidangTaPeriod::active()` di controller/dashboard; ganti tampilan student ke jumlah/daftar terbuka agar tidak memilih periode acak. Jangan mengubah periode yudisium.
- [ ] Saat C selesai membuat tabel draft, tambahkan guard `deletePeriod()` jika ada draft yang mengacu, selain registrasi final; tampilkan pesan 409 yang jelas. Jangan query tabel draft sebelum migrasinya tersedia.

**Selesai bila:** query opsi memuat dua periode tanpa saling mematikan; seluruh sembilan test fondasi pada dokumen 08 lulus. Pilihan tahap 1 yang terintegrasi dengan penyimpanan draft dikerjakan C–E, bukan syarat B fondasi. Gunakan perintah seluruh file dengan koneksi testing pada 08, bukan filter dua test lama. Test tambahan periode terkait draft/submit baru ditambahkan C–F ketika endpoint-nya tersedia.

## Checkpoint C — Struktur draft dan kontrak akses

Panduan rinci: [09 fondasi](09-checkpoint-c1-struktur-draft-mysql.md), [10 koreksi rescan asli](10-rescan-b-c1-koreksi-model-dokumen.md), [11 backend tahap 1](11-checkpoint-c2-draft-tahap-satu.md). C2 pada 11 khusus draft baru; kontrak revisi pada rencana besar di bawah dipasang D, bukan diklaim sudah ada pada C2. phpunit.xml lokal dipulihkan sebelum commit/push sesuai keputusan pengguna.

Skema yang disarankan:

| Tabel | Kolom penting |
| --- | --- |
| `sidang_ta_registration_drafts` | `id` UUID PK; `owner_user_id` FK users restrict delete; `student_number` varchar(20) index; `sidang_ta_period_id` FK periods restrict delete; `source_registration_id` nullable FK final restrict delete; `source_version` nullable unsigned integer; `payload` JSON; `version` unsigned integer default 1; `reviewed_version` nullable unsigned integer; `status` varchar(20) default `active`; `submitted_registration_id` nullable FK final restrict delete; timestamps. |
| `sidang_ta_registration_draft_documents` | id; `draft_id` UUID FK draft cascade delete; `document_type` varchar(80); file_name/path/size/type; `source_document_id` nullable FK final document restrict delete; timestamps; unique (`draft_id`,`document_type`) dengan nama indeks pendek. |

`payload` hanya menyimpan field kontak, tanggal pembayaran, pilihan biodata. Period ID disimpan kolom sendiri; snapshot akademik final dibuat ulang oleh server saat submit. `status` draft hanya `active/consumed`; jangan menerima nilainya dari request. Pemilik draft menggunakan user ID dan NIM dari resolusi Student server, bukan hidden input.

Pada tabel final `sidang_ta_registrations`, tambahkan `lock_version` unsigned integer default 1 melalui migrasi baru yang sama. `source_version` disalin dari lock_version final saat start revisi di bawah lock, bukan dari client. Final revisi dan setiap keputusan review menaikkan lock_version. Tambahkan ke cast model, tetapi jangan mass-assign nilainya dari request. `down()` menghapus tabel draft lebih dulu, kemudian kolom lock_version.

**Kontrak helper bersama:**

- `SidangTaStudentContext::studentFor(User $user): Student` — pindahkan resolusi email/username dari controller; gagal dengan pesan akun belum terhubung bila Student/NIM tidak valid. Jangan membuat NIM kosong/fallback palsu. Dashboard TA memakai resolver yang sama dengan penanganan empty-state.
- `SidangTaStudentContext::snapshot(Student $student): array` — pertahankan seluruh isi snapshot sekarang; hindari mengganti sumber akademik pada refactor ini.
- `SidangTaDocumentRequirements::typesForStep(int $step, ?bool $accurate): array` dan `rulesForType(string $type): array` — satu sumber aturan untuk request, service, UI dan test. Langkah 4 tanpa jawaban tidak boleh dianggap cabang false.
- `SidangTaDraftService::start(User $user, array $validated, ?SidangTaRegistration $source = null): SidangTaRegistrationDraft`.
- `saveStep(User $user, SidangTaRegistrationDraft $draft, int $step, int $expectedVersion, array $validated): SidangTaRegistrationDraft`.
- `firstIncompleteStep(SidangTaRegistrationDraft $draft): ?int` — hitung ulang dari payload + jenis file yang berlaku + keberadaan file fisik, bukan angka step kiriman client.
- `markReviewed(User $user, SidangTaRegistrationDraft $draft, int $expectedVersion): SidangTaRegistrationDraft`.
- `submit(User $user, SidangTaRegistrationDraft $draft, int $expectedVersion): SidangTaRegistration`.
- `discard(User $user, SidangTaRegistrationDraft $draft): void` — hanya active dan owned; tidak menghapus dokumen final yang dipinjam revisi.

Route baru seluruhnya di grup auth/password.changed/dashboard yang ada:

| Method dan path setelah `/dashboard` | Nama route | Aksi |
| --- | --- | --- |
| POST `sidang-ta-drafts` | `sidang-ta-drafts.store` | Validasi+simpan tahap 1; source_registration_id hanya untuk revisi milik sendiri. |
| GET `sidang-ta-drafts/{draft}/edit` | `sidang-ta-drafts.edit` | Resume tahap valid berikutnya atau tahap lama yang boleh dibuka. |
| POST `sidang-ta-drafts/{draft}/steps/{step}` | `sidang-ta-drafts.steps.store` | Save tahap 1–4, multipart hanya file tahap aktif. |
| POST `sidang-ta-drafts/{draft}/review` | `sidang-ta-drafts.review` | Validasi seluruh tahap dan tandai reviewed_version server, lalu redirect preview. |
| GET `sidang-ta-drafts/{draft}/review` | `sidang-ta-drafts.preview` | Tampil review hanya jika reviewed_version sama dengan version sekarang. |
| GET `sidang-ta-draft-documents/{document}/download` | `sidang-ta-draft-documents.download` | Owner aktif saja; file tetap privat. |
| DELETE `sidang-ta-drafts/{draft}` | `sidang-ta-drafts.destroy` | Discard draft dengan konfirmasi eksplisit di UI. |

`registration.store` tetap endpoint final baru dan `registration.update` endpoint final revisi, tetapi inputnya berubah menjadi `draft_id`, `version`, `action=submit`. Controller memuat draft sendiri dan memanggil finalizer. `update` memastikan source_registration_id sama dengan parameter route. Hapus jalur lama yang langsung menyimpan semua `$request->file()`; jangan menyisakan bypass draft/kelengkapan lewat endpoint lama.

- [ ] Test init draft tidak membuat registration/log submitted; reload/login ulang menemukan draft+berkas yang sama; input NIM/user/status/file_path buatan tidak dipercaya.
- [ ] Terapkan izin `sidang_ta_registration_read` untuk resume/download; untuk write/init/review/discard wajib `create` bila draft baru atau `update` bila revisi. Pemeriksaan dinamis dilakukan authorize/policy sebelum membaca existing document; jangan membolehkan keduanya tanpa melihat source.
- [ ] Ownership wajib untuk setiap endpoint, termasuk UUID document. Permission review tidak otomatis membuka draft mahasiswa lain; draft belum merupakan antrean petugas.
- [ ] Inisialisasi idempotent: transaksi mengunci row Student yang sama dengan `lockForUpdate`, cari active draft/source atau pengajuan aktif, baru insert. Dua akun terkait NIM sama tidak boleh mengambil draft private milik user lain; kembalikan konflik jelas.
- [ ] Migrasi baru melalui `php artisan migrate` di database development yang dipilih pengguna. Jangan edit migrasi lama untuk memaksa fresh install.

**Selesai bila:** `php artisan test --filter=SidangTaDraftTest` mencakup persistensi, izin dan ownership.

## Checkpoint D — Save per tahap, revisi dan keamanan file

- [ ] `SaveSidangTaDraftStepRequest` mengizinkan `step` 1–4 dari route; `version` wajib integer; validasi prasyarat server sebelum menerima tahap yang lebih jauh. Tahap 1 mengirim kontak/periode untuk draft baru; revisi memakai periode asal tanpa syarat masih terbuka dan menolak perpindahan periode. Tahap 2 enam capture; tahap 3 tanggal+7 PDF; tahap 4 boolean+PDF cabang terpilih.
- [ ] Whitelist `documents` menggunakan `array:` dengan key tahap yang berlaku. Unknown key, array menggantikan satu file, input path dan file tahap lain ditolak. Ambil hanya validated UploadedFile, jangan iterasi seluruh `$request->file('documents')`. Request yang sama sekali tidak mengirim file boleh memakai existing file sah untuk tahap tersebut.
- [ ] Server menghitung persyaratan dari **data tersimpan yang digabung perubahan tervalidasi**. Existing row saja tidak cukup: file harus ada, source revisi harus milik registration asal, dan cabang harus berlaku. Format existing final file sah lama dihormati; upload pengganti mengikuti aturan baru.
- [ ] Save file baru ke `sidang_ta/drafts/{uuid}/` pada disk local/private menggunakan nama generated. Periksa hasil false/exception karena `config/filesystems.php` memakai `throw=false`; jangan memasukkan path kosong ke DB.
- [ ] Kumpulkan path baru dan path tergantikan. Selesaikan perubahan metadata/payload/version dalam transaksi. Pada gagal DB/storage hapus hanya file baru yang dibuat request ini; path lama tetap utuh. Hapus file draft lama hanya sesudah DB commit sukses; bila penghapusan gagal, log cleanup yang dapat diulang.
- [ ] Kunci Student lalu draft dengan urutan sama pada semua mutasi; periksa expectedVersion. Dua tab menyimpan versi sama: satu berhasil, lainnya 409 tanpa overwrite. Jangan menahan lock DB selama transfer HTTP; PHP menerima upload sebelum service dijalankan.
- [ ] Save sukses menaikkan version dan mengosongkan reviewed_version, termasuk perubahan cabang/periode. Mengubah field pada browser membatalkan tampilan tinjau dan memberi penanda belum tersimpan.
- [ ] Saat ganti biodata 1→0 atau 0→1: validasi file cabang baru, baru commit penggantian/penghapusan metadata cabang lama. Jika save gagal, draft server lama tetap utuh. File cabang tidak aktif di browser disabled/clear agar tidak ikut multipart. Jangan hapus file final lama saat merevisi.
- [ ] Revisi: `edit()` hanya owner status needs_review. Saat start draft, kunci source, isi payload dan source_version dari final dan buat draft-document yang mereferensikan final via `source_document_id`; jangan menyalin/menghapus file final tanpa perlu. Selama draft disunting, final belum berubah.
- [ ] Jika petugas mengubah final saat revisi berjalan, finalizer mengecek status needs_review dan source_version==lock_version di bawah lock registration; konflik 409 meminta muat ulang, bukan menimpa keputusan baru. Jangan mengandalkan updated_at resolusi detik sebagai versi.
- [ ] Discard draft menghapus hanya file yang dimiliki draft; existing final yang dipinjam tidak boleh terhapus. Jangan menghapus root folder yang memuat file final yang sudah direferensikan.

**Selesai bila:** test `rejects_unknown_document_key`, `keeps_old_file_when_replacement_fails`, `rejects_stale_draft_version`, `persists_files_after_logout`, `switches_biodata_branch_safely`, `rejects_skipped_step` lulus.

## Checkpoint E — Empat tahap dan review dari data tersimpan

- [ ] Pertahankan header/panel data akademik yang relevan dan pola visual aplikasi. Stepper menampilkan Tahap 1–4, judul ringkas, kondisi terkunci/tersimpan/aktif; mobile tetap terbaca tanpa menyembunyikan tombol.
- [ ] GET create menampilkan tahap 1 dan pilihan periode, tanpa membuat row melalui GET. Setelah POST tahap 1 sukses, redirect ke edit draft. GET edit final revisi menampilkan data awal atau redirect draft yang sudah ada, tanpa mengubah final pada GET.
- [ ] Pilih desain paling sederhana: satu halaman menampilkan tahap yang sedang dibuka dengan **form hanya untuk tahap tersebut**, native POST multipart dan redirect setelah save. Data/file tahap sebelumnya ada di server, sehingga tidak perlu mempertahankan input file semua tahap dalam DOM.
- [ ] Alpine dipakai untuk nama/ukuran file, radio kondisional, penanda perubahan dan status sedang menyimpan. Daftarkan factory `sidangTaWizard(initial)` dari `resources/js/sidang-ta-wizard.js` sebelum `Alpine.start()`; initial state di-encode aman melalui `@js`, jangan interpolasi string mentah ke atribut JS.
- [ ] Tombol stepper hanya membuka tahap <= tahap pertama yang belum lengkap; server menghitung ulang batas pada GET maupun POST. Klik locked/URL buatan tidak boleh membuka tahap 4 sebelum 1–3 lengkap. Kembali tidak perlu submit jika tidak ada perubahan; perubahan belum disimpan diberi konfirmasi sebelum navigasi.
- [ ] Terapkan validasi browser pada form tahap yang aktif saja. Hindari seluruh `reportValidity()` form lama untuk tahap lain yang tersembunyi. `x-show` tidak menonaktifkan input; cabang biodata tidak aktif harus disabled dan tidak required. Gunakan label/id unik dan pesan error per field.
- [ ] Revisi tampilkan **Berkas tersimpan** dengan link download berizin dan **File pengganti belum disimpan** secara terpisah. Tombol hapus pilihan baru tidak menghapus file server. Input wajib hanya jika belum ada file tersimpan yang masih tersedia.
- [ ] Setelah tahap 4 tersimpan, tombol Tinjau melakukan POST markReviewed, lalu GET review menampilkan payload dan daftar file **dari server**, bukan nama file browser. Tampilkan periode, kontak, tanggal wisuda, pilihan biodata, 6 capture, 7 dokumen tahap 3 dan 1/2 dokumen cabang beserta ukuran dan tautan.
- [ ] Form final hanya draft_id/version/action, CSRF dan PUT spoof untuk revisi. Tombol Kirim Pendaftaran ada di halaman review. Server tidak mempercayai centang completed atau reviewed_version dari client.
- [ ] Saat saving/submitting tampilkan spinner+teks, cegah klik berulang dan Enter ganda. Jangan disabled draft_id/version/_token atau file input sebelum browser membuat payload; cukup tombol submit. Native error page tidak membutuhkan promise reset; halaman hasil/redirect harus tetap bisa dipakai.
- [ ] Error validasi membuka tahap yang relevan dan mempertahankan semua yang sudah tersimpan; file baru yang gagal harus dipilih ulang. 413 memberi pesan total upload melebihi batas, berbeda dari 422 validasi field; jangan menambahkan exception handler yang berasumsi old input/file masih tersedia setelah PHP menolak body.
- [ ] Resume sesudah tutup browser/login ulang menampilkan tanggal simpan terakhir, tahap berikutnya dan semua file tersimpan; tampilkan warning untuk perubahan yang belum disimpan. Jangan menyebut autosave.

**Selesai bila:** uji browser empat tahap, dua cabang, revisi, tutup-buka dan mobile lulus; `npm run build` berhasil. UI tidak lagi mengirim seluruh 14/15 file dalam satu request; tahap terberat 7 file (70 MiB pada batas aplikasi), final tanpa file. Konfigurasi 04 tetap aman untuk masa transisi form lama.

## Checkpoint F — Finalisasi yang lengkap, atomik dan dapat diulang

- [ ] Final request wajib `draft_id` UUID, `version` integer, `action` in:submit; tanpa action/bernilai lain ditolak. Jangan memilih required/nullable seluruh pengajuan berdasarkan flag action. Tolak file baru pada endpoint final agar sumber data hanya draft tersimpan.
- [ ] Di transaksi dengan urutan lock Student → draft → source registration bila revisi, cek owner dan permission sesuai source terlebih dahulu. **Branch consumed diperiksa sebelum syarat active/kelengkapan/source needs_review**: jika expectedVersion cocok dengan versi draft saat sukses, kembalikan submitted_registration_id tanpa perubahan. Finalisasi tidak menaikkan version draft, sehingga replay memakai version yang sama; save terhadap consumed ditolak.
- [ ] Untuk branch active, cek expectedVersion, reviewed_version==version, semua tahap lengkap, file fisik tersedia, dan periode terbuka hanya untuk pengajuan baru. Unknown payload internal harus tidak ikut mass assignment.
- [ ] Untuk final baru, cek lagi tidak ada submitted/needs_review milik NIM itu sambil memegang lock Student. Buat registration lengkap + semua metadata dokumen + snapshot akademik server + satu log submitted, lalu set draft consumed dan submitted_registration_id. Jangan membuat registration sebelum validasi final selesai.
- [ ] Untuk revisi, cek owner/source/source_version/status; update field final dan metadata pengganti; naikkan lock_version; reset reviewer fields sesuai perilaku lama; status submitted dan satu log resubmitted. Source period tetap, pilihan periode client ditolak bila berbeda.
- [ ] Berkas draft baru dapat dipakai langsung oleh final dengan path privat yang sama; lokasi fisik tidak harus dipindah. Setelah commit final, path tersebut menjadi milik registration. Download final tetap melalui route berizin; jangan membuka folder `/storage/sidang_ta` publik.
- [ ] Setelah membaca metadata draft dan menentukan metadata final tujuan, hapus row draft-document milik draft yang dikonsumsi **di dalam transaksi**, sebelum menghapus row dokumen final usang yang direferensikan source_document_id. Ini melepaskan FK restrict; jangan menghapus file fisiknya pada langkah metadata tersebut. Retain header draft consumed untuk replay, bukan row draft-document yang terus meminjam final lama.
- [ ] Hapus file final tergantikan/tidak berlaku hanya sesudah commit dan hanya bila tidak direferensikan metadata final/draft aktif lain. Jika transaksi gagal, metadata draft-document ikut rollback, registration lama utuh, draft dapat dicoba lagi; file draft yang tersimpan tetap ada.
- [ ] Pertahankan draft consumed sebagai bukti idempotensi; jangan hapus folder/file-nya. Request final ulang pada draft consumed milik sendiri mengembalikan registration yang sama tanpa log/row baru dan tanpa mengganti keputusan petugas terbaru. Permission tetap diperiksa; tidak bergantung source masih needs_review untuk replay yang sudah sukses.
- [ ] `show/download` final mempertahankan owner-or-review permission. `adminIndex/adminShow/review` tetap membaca final saja; tidak menambahkan fitur workflow petugas.
- [ ] Penyesuaian minimum review petugas untuk kompatibilitas revisi: halaman `admin/show.blade.php` mengirim hidden `registration_version` dari lock_version; `review()` memvalidasi integer tersebut, memakai transaksi+lock row, membandingkan versi/status, menaikkan lock_version dan menulis log dalam transaksi. Halaman review lama harus mendapat 409 setelah revisi baru terkirim, bukan menimpa submission baru. Ini perlindungan data, bukan desain baru workflow petugas.

**Selesai bila:** final tanpa lengkap atau belum ditinjau ditolak; final valid sekali membuat satu registration dan satu log; retry dua kali tidak menduplikasi; storage/DB failure tidak merusak final/revisi lama. Test race perlu MySQL terpisah dan dua koneksi/proses; SQLite memory tidak membuktikan row locking.

## Checkpoint G — Rapikan riwayat dan detail setelah form berfungsi

- [ ] Halaman index memisahkan kartu draft (periode/tahap/terakhir disimpan/lanjut/hapus) dari Riwayat Pengajuan. Kondisi tanpa periode terbuka tidak menghilangkan resume draft atau revisi.
- [ ] Perbaiki urutan empty-state: pengajuan aktif dan revisi tetap terlihat walau periode lain belum dibuka. Pilih periode pada form, bukan pada data pengajuan final yang sudah terkirim.
- [ ] `show.blade.php` menggunakan ringkasan status/periode/tanggal, catatan petugas, empat kelompok sesuai form, bagian akademik dan timeline status. Hindari mencetak semua key snapshot dengan `str_replace`.
- [ ] Buat mapping label eksplisit: NIM/Nama/Prodi/Semester/Email, Judul TA ID/EN, Pembimbing/Penguji, IPK/SKS/TAK/EPrT/BPP/SKPI/iBasila, jadwal. Baca snapshot tersimpan; jangan menggantinya dengan data akademik live untuk riwayat.
- [ ] Urutkan dokumen menurut requirement grup, tampilkan nama/ukuran/status/catatan yang memang tersimpan, link Unduh, dan empty-state untuk data lama tidak lengkap. Jangan menampilkan dokumen cabang yang sudah tidak berlaku sebagai syarat aktif.
- [ ] Tombol Perbaiki Pengajuan hanya bila owner + permission update + status needs_review. Reviewer yang mengakses detail tidak diberi tombol student yang kemudian selalu 403.
- [ ] Uji snapshot null/field hilang/nilai 0, nama file panjang, catatan panjang, tanpa logs, pagination dan layar kecil. Render text dengan escaping Blade, bukan HTML mentah dari file name/catatan.

**Selesai bila:** [checklist 06](06-checklist-rescan-sidang-ta.md) lulus dan rescan sumber sudah dilakukan sesudah hasil dipindah. Alur petugas lengkap baru direncanakan saat pengguna menjelaskannya.

## Rujukan teknis yang dipakai

- [Laravel 13 validation](https://github.com/laravel/docs/blob/13.x/validation.md): array whitelist dan validasi kondisional; diambil melalui Context7.
- [Laravel database transactions](https://github.com/laravel/docs/blob/13.x/database.md#database-transactions): transaksi DB dan rollback; diambil melalui Context7. Filesystem tetap memerlukan cleanup tersendiri.
- [Alpine templating](https://alpinejs.dev/essentials/templating): `x-show` mempertahankan node tersembunyi, `x-if` menghapus/membuat node; diambil melalui Context7. Karena draft server, setiap tahap dapat dirender ulang tanpa mempertahankan FileList tahap lain.

Rencana ini belum dijalankan. Pemeriksaan sebelum/sesudah penulisan hanya membaca sumber; source kedua folder tetap sama dengan baseline scan.
