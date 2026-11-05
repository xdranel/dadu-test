# 56 — Meet E4: kirim pengajuan awal versi 2 dan snapshot pemeriksaan

> Pelaksana: pengguna menerapkan kode di folder asli, menguji, lalu menyalin hasilnya ke `dadu-test`. Asisten menulis panduan dan melakukan pemeriksaan baca saja. Instruksi ini melanjutkan rancangan [45](45-rancangan-revisi-hasil-meet-sidang-ta.md).

**Tujuan:** draft versi 2 yang sudah ditinjau dapat dikirim jika lima syarat akademik, kondisi EPRT, isian, dan berkas memenuhi persyaratan saat pengiriman.

**Arsitektur:** finalizer mempertahankan transaksi, ownership, versi optimistis, dan replay yang sudah ada. Satu hasil pembacaan fakta menentukan gate, berkas wajib, dan snapshot. Detail, tabel petugas, dan ekspor membaca persyaratan historis yang tersimpan pada pengajuan.

**Teknologi:** Laravel/Pest, Blade/Alpine yang sudah terpasang, MySQL Podman; tidak menambah dependensi.

**Lanjutan koreksi:** gunakan [56b](56b-koreksi-letak-test-disabled-dan-krs-dummy.md) untuk pemindahan test BPP yang salah file, tampilan disabled, dan inisialisasi periode akademik dummy. Target/jumlah file pada panduan ini adalah baseline sebelum 56b.

## 1. Hasil rescan dan batas checkpoint

**Koreksi setelah laporan 10 gagal:** assertion JSON dan tiga file terkait ekspor/tombol di bawah sudah ditulis ulang. Untuk kode yang sudah diterapkan, ikuti [56a](56a-koreksi-test-tombol-bpp-dan-card-beranda.md), yang juga menambahkan permintaan Status BPP dan card beranda. Target 76 pada bagian 7 adalah baseline 56 sebelum delapan test tambahan 56a.

Tujuh file checkpoint 55 sudah identik antara folder asli dan `dadu-test`. Tidak ditemukan blocker dalam cakupan UI tersebut. Penahanan tombol kirim pada versi 2 memang masih disengaja oleh checkpoint 54–55.

Scan lanjutan menemukan resolver pengajuan final, ikon tabel, dan ekspor masih menggunakan daftar lama. Label surat permakluman juga belum masuk daftar label pengajuan final. Semuanya disesuaikan di sini supaya pengajuan versi 2 yang berhasil dikirim dapat dibaca dengan benar.

Aturan yang berlaku:

- Lima gate: lulus sidang tingkat 2, KRS TA 2, SKPI, TAK ≥60, dan SKS lulus ≥120. Gagal atau belum diketahui menahan **Kirim**, bukan penyimpanan draft atau Tinjau.
- EPRT diperiksa terpisah: automatic tanpa surat; waiver perlu PDF surat; retest/unknown menahan kirim dengan alasan masing-masing. EPRT tidak dimasukkan sebagai gate akademik keenam.
- Mengubah data setelah Tinjau harus terdeteksi saat Kirim. Nilai HTTP tambahan tidak menjadi sumber kelulusan.
- Gagal mengirim tidak mengonsumsi draft atau menghapus file. Replay draft consumed mengembalikan hasil lama tanpa mengulang pemeriksaan atau log.
- Pengajuan versi 1 tetap kompatibel. **Pembuatan draft biasa masih versi 1 pada fase integrasi ini.** Pengiriman versi 2 diuji pada akun/draft dummy yang sudah disiapkan pada 55. Jangan mengubah `DraftService::start()` atau menaikkan versi semua draft sekarang.
- Aksi petugas dan kirim revisi versi 2 masih ditahan sementara sampai checkpoint aturan revisi terarah. Preview petugas tetap tersedia. Aksi versi 1 tetap berfungsi.
- Aktivasi versi 2 untuk pendaftaran biasa, penanganan draft awal versi 1 agar tidak melewati gate meet, dan penyelesaian alur petugas/revisi masih pekerjaan lanjutan sebelum fitur meet dinyatakan selesai.

**JSON:** tidak perlu import baru bila dummy 53 sudah diimpor. Konfirmasi data resmi masih ditunggu. Approval sidang tidak disamakan dengan foto BASILA dan tidak dijadikan syarat tambahan tanpa pemetaan resmi. Nilai BPP tetap mengikuti keterbatasan data semester yang sudah dicatat pada 55.

## 2. File dan urutan pengerjaan

- [ ] Kerjakan di `/home/xdranel/Code/Project/php-laravel/dadu/`.
- [ ] Buat test baru, isi bagian 3, lalu jalankan test baru untuk melihat kegagalan sebelum implementasi.
- [ ] Perbarui tiga test lama pada bagian 4; assertion lama yang sengaja berubah mendapat pengganti.
- [ ] Terapkan kode produksi bagian 5–6.
- [ ] Jalankan pemeriksaan bagian 7, kemudian manual bagian 8.
- [ ] Salin 15 file bagian 9 setelah hasilnya lulus.

Command file baru:

```bash
php artisan make:test SidangTaMeetFinalizationTest --pest
```

Tidak ada migrasi, seeder, atau file JSON baru di checkpoint ini. Tidak perlu `migrate:fresh`.

Fokus pengujian: data akademik berubah sesudah Tinjau; EPRT berubah mode sehingga jumlah berkas berubah; file hilang meskipun metadata ada; retry pengiriman setelah periode/data berubah; pengajuan lama dan baru dibaca/ekspor bersama. Test untuk kasus-kasus tersebut ada pada bagian 3.

## 3. Test baru lengkap — tulis dahulu

### `tests/Feature/SidangTaMeetFinalizationTest.php`

Ganti isi file dengan kode lengkap berikut.

```php
<?php

use App\Models\AcademicPeriod;
use App\Models\Course;
use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDraft;
use App\Models\Student;
use App\Models\StudentCourse;
use App\Models\StudentEprt;
use App\Models\StudentTak;
use App\Models\User;
use App\Services\SidangTaAdmissionFacts;
use App\Services\StudentAcademicProfileImportService;
use App\Support\SidangTaMeetRequirements as Meet;
use App\Support\SidangTaReviewEligibility as Eligibility;
use App\Services\SidangTaReviewExport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\DomCrawler\Crawler;

beforeEach(function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-11-04 12:00:00', config('app.timezone')));
    Storage::fake('local');
    $this->user = User::factory()->create(['username' => '1202299056', 'email' => 'meet-final@test.local',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
    $this->student = Student::create(['student_number' => $this->user->username, 'name' => 'Meet Final',
        'email' => $this->user->email, 'academic_year' => 2022, 'study_program' => 'Informatika', 'status' => 'Aktif']);
    foreach (['read', 'create', 'update'] as $action) {
        $permission = Permission::firstOrCreate(['name' => 'sidang_ta_registration_'.$action, 'guard_name' => 'web']);
        $this->user->givePermissionTo($permission);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($this->user);
    $this->period = SidangTaPeriod::create(['name' => 'Meet Final', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay()]);
    AcademicPeriod::query()->update(['is_active' => false]);
    $academic = AcademicPeriod::create(['year' => '2026/2027', 'semester' => 1, 'label' => 'Meet Final KRS',
        'start_date' => '2026-08-01', 'end_date' => '2027-01-31', 'is_active' => true]);
    Course::create(['course_code' => 'MEET-FINAL56', 'name' => 'TUGAS AKHIR 2', 'academic_year' => '2026/2027',
        'semester_term' => 'GANJIL', 'study_program' => 'Informatika', 'class' => 'A', 'semester' => 8, 'credits' => 4]);
    StudentCourse::create(['student_number' => $this->student->student_number, 'course_code' => 'MEET-FINAL56',
        'course_name' => 'TUGAS AKHIR 2', 'course_class' => 'A', 'academic_year' => '2026/2027', 'semester' => 8,
        'academic_period_id' => $academic->id]);
    $this->meetDraft = function (string $mode = 'automatic', bool $accurate = true, bool $complete = true) {
        $rows = json_decode(file_get_contents(base_path('docs/fixtures/sidang-ta/03-profil-akademik-dummy.json')), true, 512, JSON_THROW_ON_ERROR)['data'];
        $row = $rows[$mode === 'waiver' ? 1 : ($mode === 'retest' ? 2 : 0)];
        $row['nim'] = $this->student->student_number;
        $row['nama'] = $this->student->name;
        if ($mode === 'unknown') $row['eprt'] = [];
        StudentEprt::where('student_number', $this->student->student_number)->delete();
        $result = app(StudentAcademicProfileImportService::class)->import([$row]);
        expect($result['errors'])->toBe([]);
        $draft = SidangTaRegistrationDraft::create(['owner_user_id' => $this->user->id,
            'student_number' => $this->student->student_number, 'sidang_ta_period_id' => $this->period->id,
            'requirements_version' => 2, 'payload' => ['student_phone' => '081234567890', 'parent_phone' => '081234567891',
                'graduation_payment_date' => today()->toDateString(), 'diploma_biodata_accurate' => $accurate]]);
        if ($complete) {
            foreach (Meet::allTypes($accurate, $mode) as $type) {
                $path = 'sidang_ta/drafts/'.$draft->id.'/'.$type.'.pdf';
                Storage::disk('local')->put($path, '%PDF-1.4 meet ui');
                $draft->documents()->create(['document_type' => $type, 'file_path' => $path,
                    'file_name' => $type.'.pdf', 'file_type' => 'pdf', 'file_size' => 16]);
            }
        }
        return $draft->fresh();
    };
    $this->markReviewed = function ($draft) {
        $this->postJson(route('sidang-ta-drafts.review', $draft), ['version' => $draft->fresh()->version])->assertOk();
        return $draft->fresh();
    };
    $this->submitMeet = function ($draft, ?int $version = null) {
        return $this->postJson(route('sidang-ta-registrations.store'), [
            'draft_id' => $draft->id, 'version' => $version ?? $draft->fresh()->version, 'action' => 'submit',
        ]);
    };
    $this->setMode = function (string $mode) {
        StudentEprt::where('student_number', $this->student->student_number)->delete();
        if ($mode === 'unknown') return;
        $count = $mode === 'waiver' ? 3 : ($mode === 'retest' ? 2 : 1);
        foreach (range(1, $count) as $attempt) {
            StudentEprt::create(['student_number' => $this->student->student_number, 'category' => null,
                'total_score' => $mode === 'automatic' ? 450 : 449, 'attempt_number' => $attempt,
                'test_date' => today()->subDays(4 - $attempt)]);
        }
    };
    $this->asReviewer = function () {
        $permission = Permission::firstOrCreate(['name' => 'sidang_ta_registration_review', 'guard_name' => 'web']);
        $reviewer = User::factory()->create(['status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
        $reviewer->givePermissionTo($permission);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($reviewer);
        return $reviewer;
    };
    $this->assertPreserved = function ($draft, array $paths) {
        expect($draft->fresh()->status)->toBe('active');
        expect($draft->documents()->count())->toBe(count($paths));
        expect(Storage::disk('local')->allFiles())->toBe($paths);
        expect(SidangTaRegistration::count())->toBe(0);
    };
});

afterEach(function () { $this->travelBack(); });

test('meet_final_automatic_submission_transfers_seven_files_and_records_a_historical_decision', function () {
    $draft = ($this->markReviewed)(($this->meetDraft)());
    $paths = Storage::disk('local')->allFiles();
    $id = ($this->submitMeet)($draft)->assertOk()->assertJsonPath('data.status', 'submitted')->json('data.id');
    $registration = SidangTaRegistration::findOrFail($id);
    expect($registration->requirements_version)->toBe(2);
    expect($registration->documents()->count())->toBe(7);
    expect($registration->documents()->where('status', 'pending')->count())->toBe(7);
    expect($draft->fresh()->status)->toBe('consumed');
    expect($draft->fresh()->submitted_registration_id)->toBe($id);
    expect($draft->documents()->count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBe($paths);
    expect($registration->logs()->count())->toBe(1);
    $snapshot = $registration->academic_snapshot;
    expect($snapshot['admission']['academic']['academic_passed'])->toBeTrue();
    expect($snapshot['admission']['eprt']['mode'])->toBe('automatic');
    expect($snapshot['admission']['checked_at'])->toBe(now()->toIso8601String());
    expect((int) $snapshot['tak_score'])->toBe(60);
    expect((int) $snapshot['passed_credits'])->toBe(120);
    expect((int) $snapshot['eprt_score'])->toBe(450);
    StudentTak::where('student_number', $this->student->student_number)->update(['total_points' => 0]);
    ($this->setMode)('waiver');
    expect($registration->fresh()->academic_snapshot)->toBe($snapshot);
    expect(Eligibility::requiredTypes($registration->fresh()))->toHaveCount(7);
    $crawler = new Crawler($this->get(route('sidang-ta-registrations.show', $registration))->assertOk()->assertDontSee('Riwayat status')->getContent());
    expect($crawler->filter('[data-snapshot-field="tak_score"]')->text(null, true))->toBe('60');
    expect($crawler->filter('[data-academic-detail]')->text(null, true))->not->toContain('academic_passed');
});

test('meet_final_waiver_submission_keeps_eight_files_and_a_readable_waiver_label', function () {
    $draft = ($this->markReviewed)(($this->meetDraft)('waiver'));
    $id = ($this->submitMeet)($draft)->assertOk()->json('data.id');
    $registration = SidangTaRegistration::findOrFail($id);
    expect($registration->documents()->count())->toBe(8);
    expect($registration->academic_snapshot['admission']['eprt']['mode'])->toBe('waiver');
    $this->get(route('sidang-ta-registrations.show', $registration))->assertOk()->assertSee('Surat Permakluman EPRT');
    expect(Eligibility::requiredTypes($registration))->toContain(Meet::WAIVER);
});

test('meet_final_pdm_submission_keeps_six_files_and_only_the_selected_biodata_branch', function () {
    $draft = ($this->markReviewed)(($this->meetDraft)('automatic', false));
    $id = ($this->submitMeet)($draft)->assertOk()->json('data.id');
    $registration = SidangTaRegistration::findOrFail($id);
    expect($registration->documents()->count())->toBe(6);
    expect($registration->documents()->whereIn('document_type', Meet::DIPLOMA)->exists())->toBeFalse();
    expect(Eligibility::groups($registration)['Biodata'])->toBe(Meet::PDM);
});

test('meet_final_rereads_each_academic_gate_after_review_and_preserves_the_draft', function (string $gate) {
    $draft = ($this->markReviewed)(($this->meetDraft)());
    $paths = Storage::disk('local')->allFiles();
    match ($gate) {
        'level_2_passed' => $this->student->sidangTingkat()->update(['tingkat_2' => false]),
        'ta2_registered' => StudentCourse::where('student_number', $this->student->student_number)->delete(),
        'skpi_approved' => $this->student->skpi()->firstOrFail()->update(['raw_data' => ['approval_status' => 'pending']]),
        'tak_score' => StudentTak::where('student_number', $this->student->student_number)->update(['total_points' => 59]),
        'passed_credits' => $this->student->courseIssues()->update(['credits_passed' => 119]),
    };
    ($this->submitMeet)($draft)->assertUnprocessable()->assertJsonValidationErrors('eligibility.'.$gate);
    ($this->assertPreserved)($draft, $paths);
    expect($draft->fresh()->reviewed_version)->toBe($draft->version);
})->with(['level_2_passed', 'ta2_registered', 'skpi_approved', 'tak_score', 'passed_credits']);

test('meet_final_missing_skpi_stays_unknown_and_cannot_be_forged_with_http_fields', function () {
    $draft = ($this->markReviewed)(($this->meetDraft)());
    $paths = Storage::disk('local')->allFiles();
    $this->student->skpi()->delete();
    $this->postJson(route('sidang-ta-registrations.store'), ['draft_id' => $draft->id,
        'version' => $draft->version, 'action' => 'submit', 'skpi_approved' => true, 'academic_passed' => true,
        'admission' => ['academic' => ['academic_passed' => true]]])
        ->assertUnprocessable()->assertJsonValidationErrors('eligibility.skpi_approved');
    ($this->assertPreserved)($draft, $paths);
});

test('meet_final_retest_and_unknown_eprt_block_only_submission', function (string $mode) {
    $draft = ($this->markReviewed)(($this->meetDraft)($mode));
    $paths = Storage::disk('local')->allFiles();
    $this->get(route('sidang-ta-drafts.preview', $draft))->assertOk();
    ($this->submitMeet)($draft)->assertUnprocessable()->assertJsonValidationErrors('eligibility.eprt');
    ($this->assertPreserved)($draft, $paths);
})->with(['retest', 'unknown']);

test('meet_final_new_waiver_requirement_after_review_requires_upload_and_another_review', function () {
    $draft = ($this->markReviewed)(($this->meetDraft)());
    $paths = Storage::disk('local')->allFiles();
    ($this->setMode)('waiver');
    ($this->submitMeet)($draft)->assertUnprocessable()->assertJsonValidationErrors('draft');
    ($this->assertPreserved)($draft, $paths);
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 3]), [
        'version' => $draft->version, 'documents' => [Meet::WAIVER => UploadedFile::fake()->create('waiver.pdf', 100, 'application/pdf')],
    ])->assertOk();
    expect($draft->fresh()->reviewed_version)->toBeNull();
    ($this->submitMeet)($draft)->assertConflict();
    $draft = ($this->markReviewed)($draft->fresh());
    $id = ($this->submitMeet)($draft)->assertOk()->json('data.id');
    expect(SidangTaRegistration::findOrFail($id)->documents()->count())->toBe(8);
});

test('meet_final_qualifying_score_retires_only_the_obsolete_waiver_after_successful_submission', function () {
    $draft = ($this->markReviewed)(($this->meetDraft)('waiver'));
    $waiver = $draft->documents()->where('document_type', Meet::WAIVER)->firstOrFail();
    $keep = $draft->documents()->where('document_type', 'thesis_draft')->firstOrFail()->file_path;
    ($this->setMode)('automatic');
    $id = ($this->submitMeet)($draft)->assertOk()->json('data.id');
    $registration = SidangTaRegistration::findOrFail($id);
    expect($registration->documents()->count())->toBe(7);
    expect($registration->documents()->where('document_type', Meet::WAIVER)->exists())->toBeFalse();
    expect($registration->academic_snapshot['admission']['eprt']['mode'])->toBe('automatic');
    Storage::disk('local')->assertMissing($waiver->file_path);
    Storage::disk('local')->assertExists($keep);
});

test('meet_final_failed_check_keeps_a_previously_uploaded_waiver', function (string $mode) {
    $draft = ($this->markReviewed)(($this->meetDraft)('waiver'));
    $paths = Storage::disk('local')->allFiles();
    ($this->setMode)($mode);
    ($this->submitMeet)($draft)->assertUnprocessable()->assertJsonValidationErrors('eligibility.eprt');
    ($this->assertPreserved)($draft, $paths);
    expect($draft->documents()->where('document_type', Meet::WAIVER)->exists())->toBeTrue();
})->with(['retest', 'unknown']);

test('meet_final_missing_file_is_reported_without_deleting_metadata_or_other_files', function () {
    $draft = ($this->markReviewed)(($this->meetDraft)('waiver'));
    $document = $draft->documents()->where('document_type', Meet::WAIVER)->firstOrFail();
    Storage::disk('local')->delete($document->file_path);
    $paths = Storage::disk('local')->allFiles();
    ($this->submitMeet)($draft)->assertUnprocessable()->assertJsonValidationErrors('documents.'.Meet::WAIVER);
    expect($draft->fresh()->status)->toBe('active');
    expect($draft->documents()->count())->toBe(8);
    expect(Storage::disk('local')->allFiles())->toBe($paths);
    expect(SidangTaRegistration::count())->toBe(0);
});

test('meet_final_unreviewed_stale_and_closed_period_submissions_preserve_every_file', function () {
    $draft = ($this->meetDraft)();
    $paths = Storage::disk('local')->allFiles();
    ($this->submitMeet)($draft)->assertConflict();
    $draft = ($this->markReviewed)($draft);
    ($this->submitMeet)($draft, $draft->version + 1)->assertConflict();
    $this->period->update(['close_at' => now()->subSecond()]);
    ($this->submitMeet)($draft)->assertUnprocessable()->assertJsonValidationErrors('draft');
    ($this->assertPreserved)($draft, $paths);
});

test('meet_final_another_user_cannot_submit_the_owners_draft', function () {
    $draft = ($this->markReviewed)(($this->meetDraft)());
    $paths = Storage::disk('local')->allFiles();
    $other = User::factory()->create(['status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
    $other->givePermissionTo('sidang_ta_registration_create');
    $this->actingAs($other);
    ($this->submitMeet)($draft)->assertForbidden();
    ($this->assertPreserved)($draft, $paths);
});

test('meet_final_consumed_replay_does_not_reread_gates_or_change_decision_and_audit', function () {
    $draft = ($this->markReviewed)(($this->meetDraft)());
    $id = ($this->submitMeet)($draft)->assertOk()->json('data.id');
    $registration = SidangTaRegistration::findOrFail($id);
    $registration->update(['status' => 'approved']);
    $snapshot = $registration->academic_snapshot;
    $paths = Storage::disk('local')->allFiles();
    $this->student->skpi()->delete();
    $this->period->update(['close_at' => now()->subSecond()]);
    $this->mock(SidangTaAdmissionFacts::class)->shouldNotReceive('forStudent');
    ($this->submitMeet)($draft)->assertOk()->assertJsonPath('data.id', $id)->assertJsonPath('data.status', 'approved');
    expect($registration->fresh()->academic_snapshot)->toBe($snapshot);
    expect($registration->logs()->count())->toBe(1);
    expect(Storage::disk('local')->allFiles())->toBe($paths);
});

test('meet_final_required_files_and_snapshot_use_the_same_final_facts_result', function () {
    $draft = ($this->markReviewed)(($this->meetDraft)('waiver'));
    $finalFacts = app(SidangTaAdmissionFacts::class)->forStudent($this->student);
    $this->mock(SidangTaAdmissionFacts::class)->shouldReceive('forStudent')->once()->andReturn($finalFacts);
    $id = ($this->submitMeet)($draft)->assertOk()->json('data.id');
    $registration = SidangTaRegistration::findOrFail($id);
    expect($registration->documents()->count())->toBe(8);
    $stored = $registration->academic_snapshot['admission']['eprt'];
    $expected = $finalFacts['eprt'];
    ksort($stored);
    ksort($expected);
    expect($stored)->toBe($expected);
});

test('meet_final_extra_legacy_document_is_rejected_without_retiring_any_file', function () {
    $draft = ($this->meetDraft)();
    $path = 'sidang_ta/drafts/'.$draft->id.'/obsolete.pdf';
    Storage::disk('local')->put($path, '%PDF-1.4 obsolete');
    $draft->documents()->create(['document_type' => 'transcript_khs', 'file_path' => $path,
        'file_name' => 'obsolete.pdf', 'file_type' => 'pdf', 'file_size' => 17]);
    $draft = ($this->markReviewed)($draft);
    $paths = Storage::disk('local')->allFiles();
    ($this->submitMeet)($draft)->assertUnprocessable()->assertJsonValidationErrors('draft');
    ($this->assertPreserved)($draft, $paths);
});

test('meet_final_staff_can_read_preview_and_export_new_files_but_mutation_is_held', function () {
    $draft = ($this->markReviewed)(($this->meetDraft)('waiver'));
    $id = ($this->submitMeet)($draft)->assertOk()->json('data.id');
    $registration = SidangTaRegistration::findOrFail($id);
    $snapshot = $registration->academic_snapshot;
    ($this->asReviewer)();
    $crawler = new Crawler($this->get(route('admin.sidang-ta-registrations.show', $registration))->assertOk()->getContent());
    expect($crawler->filter('[data-meet-review-held]')->count())->toBe(1);
    expect($crawler->filter('[data-doc-review-form]')->count())->toBe(0);
    expect($crawler->filter('form[action="'.route('admin.sidang-ta-registrations.review', $registration).'"]')->count())->toBe(0);
    expect($crawler->filter('a[data-document-preview]')->count())->toBe(8);
    $waiver = $registration->documents()->where('document_type', Meet::WAIVER)->firstOrFail();
    $this->get(route('sidang-ta-registration-documents.preview', $waiver))->assertOk();
    $row = (new Crawler($this->get(route('admin.sidang-ta-registrations.index'))->assertOk()->getContent()))
        ->filter('[data-ta-row="'.$id.'"]');
    expect($row->filter('[data-ta-doc]')->count())->toBe(8);
    expect($row->filter('[data-ta-doc="eprt_waiver_letter"]')->attr('title'))->toContain('Surat Permakluman EPRT');
    expect($row->filter('[data-ta-doc="level_2_result_capture"], [data-ta-doc="transcript_khs"]')->count())->toBe(0);
    $export = app(SidangTaReviewExport::class)->row($registration->fresh()->load(['documents', 'period']));
    expect($export)->toHaveCount(92);
    expect($export['Surat Permakluman EPRT — Status'])->toBe('Menunggu verifikasi');
    expect($export['KRS Sidang TA 2'])->toBe('Terdaftar');
    expect($export[SidangTaRegistration::DOCUMENT_TYPES['transcript_khs'].' — Status'])->toBe('N/A');
    $this->postJson(route('admin.sidang-ta-registrations.documents.review', [
        'sidang_ta_registration' => $registration, 'document' => $waiver,
    ]), ['registration_version' => $registration->lock_version, 'status' => 'verified'])->assertUnprocessable();
    $this->postJson(route('admin.sidang-ta-registrations.review', $registration), [
        'registration_version' => $registration->lock_version, 'action' => 'needs_review', 'review_notes' => 'Perbaiki',
    ])->assertUnprocessable();
    expect($waiver->fresh()->status)->toBe('pending');
    expect($registration->fresh()->status)->toBe('submitted');
    expect($registration->fresh()->academic_snapshot)->toBe($snapshot);
    expect($registration->logs()->count())->toBe(1);
});

test('meet_final_mixed_version_xlsx_has_the_same_headers_and_preserves_waiver_status', function () {
    $draft = ($this->markReviewed)(($this->meetDraft)('waiver'));
    $id = ($this->submitMeet)($draft)->assertOk()->json('data.id');
    $registration = SidangTaRegistration::findOrFail($id);
    $legacy = $registration->replicate();
    $legacy->fill(['requirements_version' => 1, 'student_number' => '1202299066',
        'student_name' => 'Legacy Export', 'academic_snapshot' => null])->save();
    ($this->asReviewer)();
    $response = $this->get(route('admin.sidang-ta-registrations.export'))->assertOk();
    $path = $response->baseResponse->getFile()->getPathname();
    try {
        $rows = (new \Rap2hpoutre\FastExcel\FastExcel)->import($path);
        expect($rows)->toHaveCount(2);
        $modern = $rows->firstWhere('ID Pengajuan', (string) $id);
        $old = $rows->firstWhere('ID Pengajuan', (string) $legacy->id);
        expect($modern)->toHaveCount(92);
        expect(array_keys($modern))->toBe(array_keys($old));
        expect($modern['Surat Permakluman EPRT — Status'])->toBe('Menunggu verifikasi');
        expect($old['Surat Permakluman EPRT — Status'])->toBe('N/A');
    } finally {
        if (is_file($path)) unlink($path);
    }
});

test('meet_final_version_two_revision_stays_held_without_changing_final_or_borrowed_files', function () {
    $draft = ($this->markReviewed)(($this->meetDraft)('waiver'));
    $id = ($this->submitMeet)($draft)->assertOk()->json('data.id');
    $registration = SidangTaRegistration::findOrFail($id);
    $registration->update(['status' => 'needs_review']); // Controlled fixture until the new staff workflow is wired.
    $snapshot = $registration->academic_snapshot;
    $paths = Storage::disk('local')->allFiles();
    $revisionId = $this->postJson(route('sidang-ta-drafts.revision', $registration))->assertCreated()->json('data.id');
    $revision = SidangTaRegistrationDraft::findOrFail($revisionId);
    $this->putJson(route('sidang-ta-registrations.update', $registration), [
        'draft_id' => $revisionId, 'version' => $revision->version, 'action' => 'submit',
    ])->assertUnprocessable()->assertJsonPath('message', 'Pengiriman revisi ini belum tersedia. Data dan berkas tetap tersimpan.');
    expect($revision->fresh()->status)->toBe('active');
    expect($registration->fresh()->status)->toBe('needs_review');
    expect($registration->fresh()->academic_snapshot)->toBe($snapshot);
    expect($registration->documents()->count())->toBe(8);
    expect($registration->logs()->count())->toBe(1);
    expect(Storage::disk('local')->allFiles())->toBe($paths);
});

test('meet_final_revalidates_required_scalar_values_even_if_review_metadata_exists', function (string $field) {
    $draft = ($this->markReviewed)(($this->meetDraft)());
    $payload = $draft->payload;
    unset($payload[$field]);
    // Simulate inconsistent persisted data, not an allowed HTTP update.
    $draft->update(['payload' => $payload]);
    $paths = Storage::disk('local')->allFiles();
    ($this->submitMeet)($draft)->assertUnprocessable()->assertJsonValidationErrors('draft');
    ($this->assertPreserved)($draft, $paths);
})->with(['student_phone', 'graduation_payment_date', 'diploma_biodata_accurate']);
```

Jalankan sebelum kode produksi. Kegagalan pada pengiriman versi 2 atau tombol yang masih ditahan merupakan hasil yang diharapkan pada langkah ini.

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaMeetFinalizationTest.php
```

## 4. Perbarui test lama yang perilakunya berubah

### `tests/Feature/SidangTaMeetDraftTest.php`

Ganti keseluruhan test `meet_v2_rollout_guard_blocks_submit_without_consuming_the_saved_reviewed_draft` dengan berikut. Fixture ini tidak mempunyai lima fakta akademik lengkap, sehingga sekarang ditolak karena eligibility. Test replay setelahnya tetap dipertahankan.

```php
test('meet_v2_missing_academic_facts_block_submit_without_consuming_the_saved_reviewed_draft', function () {
    $draft = ($this->finishMeetDraft)();
    $this->postJson(route('sidang-ta-drafts.review', $draft), ['version' => $draft->version])->assertOk();
    $paths = Storage::disk('local')->allFiles();
    $this->postJson(route('sidang-ta-registrations.store'), ['draft_id' => $draft->id,
        'version' => $draft->version, 'action' => 'submit'])->assertUnprocessable()
        ->assertJsonValidationErrors(['eligibility.level_2_passed', 'eligibility.ta2_registered',
            'eligibility.skpi_approved', 'eligibility.tak_score', 'eligibility.passed_credits']);
    expect($draft->fresh()->status)->toBe('active');
    expect($draft->fresh()->reviewed_version)->toBe($draft->version);
    expect($draft->documents()->count())->toBe(7);
    expect(SidangTaRegistration::count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBe($paths);
});
```

### `tests/Feature/SidangTaMeetUiTest.php`

Ganti empat test di bawah berdasarkan namanya; test lain tetap ada. Test waiver yang sebelumnya bernama `meet_ui_waiver_review_has_eight_previews_and_held_send_buttons` diganti oleh test `...enabled_send_buttons`.

```php
test('meet_ui_retest_and_unknown_can_open_review_with_five_academic_checks', function () {
    foreach (['retest', 'unknown'] as $mode) {
        $draft = ($this->meetDraft)($mode);
        $crawler = new Crawler(($this->reviewPage)($draft)->getContent());
        expect($crawler->filter('[data-admission-gate]')->count())->toBe(5);
        expect($crawler->filter('[data-eprt-mode="'.$mode.'"]')->count())->toBe(1);
        foreach (['[data-open-registration]', '[data-confirm-registration]'] as $selector) {
            expect($crawler->filter($selector)->getNode(0)->hasAttribute('disabled'))->toBeTrue();
            expect($crawler->filter($selector)->attr(':disabled'))->toBe('sending || true');
        }
        expect($crawler->filter('[data-review-document]')->count())->toBe(7);
        $this->deleteJson(route('sidang-ta-drafts.destroy', $draft), ['version' => $draft->version])->assertNoContent();
    }
});
```

```php
test('meet_ui_waiver_review_has_eight_previews_and_enabled_send_buttons', function () {
    $draft = ($this->meetDraft)('waiver');
    $crawler = new Crawler(($this->reviewPage)($draft)->getContent());
    expect($crawler->filter('[data-review-document]')->count())->toBe(8);
    expect($crawler->filter('[data-review-document="eprt_waiver_letter"]')->text(null, true))->toContain('Surat Permakluman EPRT');
    expect($crawler->filter('[data-review-document] a[data-document-preview]')->count())->toBe(8);
    expect($crawler->filter('[data-review-summary]')->text(null, true))->toContain('8 dokumen');
    expect($crawler->filter('[data-meet-send-held]')->count())->toBe(0);
    foreach (['[data-open-registration]', '[data-confirm-registration]'] as $selector) {
        $button = $crawler->filter($selector);
        expect($button->getNode(0)->hasAttribute('disabled'))->toBeFalse();
        expect($button->attr(':disabled'))->toBe('sending || false');
    }
    expect($crawler->filter('form[data-final-registration-form] input[type="file"]')->count())->toBe(0);
    expect(SidangTaRegistration::count())->toBe(0);
});
```

```php
test('meet_ui_failed_tak_is_shown_without_blocking_the_review_page', function () {
    $draft = ($this->meetDraft)();
    StudentTak::where('student_number', $this->student->student_number)->update(['total_points' => 59]);
    $crawler = new Crawler(($this->reviewPage)($draft)->getContent());
    $gate = $crawler->filter('[data-admission-gate="tak_score"]');
    expect($gate->attr('data-gate-state'))->toBe('failed');
    expect($gate->text(null, true))->toContain('TAK minimal 60')->toContain('Belum memenuhi');
    expect($crawler->filter('[data-academic-blockers]')->text(null, true))->toContain('TAK minimal 60');
    expect($crawler->filter('[data-open-registration]')->getNode(0)->hasAttribute('disabled'))->toBeTrue();
    expect($crawler->filter('[data-confirm-registration]')->attr(':disabled'))->toBe('sending || true');
    expect($draft->fresh()->status)->toBe('active');
});
```

```php
test('meet_ui_read_only_owner_can_preview_without_send_controls', function () {
    $draft = ($this->meetDraft)();
    $this->postJson(route('sidang-ta-drafts.review', $draft), ['version' => $draft->version])->assertOk();
    $this->user->revokePermissionTo(['sidang_ta_registration_create', 'sidang_ta_registration_update']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($this->user->fresh());
    $crawler = new Crawler($this->get(route('sidang-ta-drafts.preview', $draft))->assertOk()->getContent());
    expect($crawler->filter('a[data-document-preview]')->count())->toBe(7);
    expect($crawler->filter('[data-open-registration], #confirm-send-registration')->count())->toBe(0);
});
```

Test read-only dicantumkan kembali sebagai pengaman: permission tetap menentukan apakah kontrol kirim ditampilkan.

### `tests/Feature/SidangTaReviewExportTest.php`

Cari blok berikut:

```php
expect($row)->toHaveCount(88);
```

Ganti dengan:

```php
expect($row)->toHaveCount(92);
```

Jumlah kolom menjadi **92**: 88 kolom lama + tiga kolom Surat Permakluman (status/ketersediaan/catatan) + satu kolom KRS. Urutan header sama untuk semua versi; kolom surat pada versi 1 bernilai `N/A`. Pemeriksaan formula dan privasi unduhan tetap dipertahankan.

## 5. Finalizer dan resolver pengajuan final

### `app/Services/SidangTaDraftFinalizer.php`

Ganti isi file dengan kode lengkap berikut.

```php
<?php

namespace App\Services;

use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDocument;
use App\Models\SidangTaRegistrationDraft;
use App\Models\SidangTaRegistrationDraftDocument;
use App\Models\Student;
use App\Models\User;
use App\Support\SidangTaDocumentRequirements as Requirements;
use App\Support\SidangTaMeetRequirements as Meet;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class SidangTaDraftFinalizer
{
    public function __construct(private SidangTaDraftAccess $access,
                                private SidangTaAcademicSnapshot $snapshots,
                                private SidangTaAdmissionFacts $facts) {}

    public function submit(User $user, SidangTaRegistrationDraft $draft, int $version,
                           ?int $expectedSourceId = null): SidangTaRegistration
    {
        $student = $this->access->owner($user, $draft);
        $this->access->assertCanWrite($user, $draft);
        $retired = [];

        $registration = DB::transaction(function () use ($user, $draft, $version,
            $expectedSourceId, $student, &$retired) {
            $student = Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $locked = SidangTaRegistrationDraft::whereKey($draft->id)->lockForUpdate()->firstOrFail();
            $this->access->owner($user, $locked);
            $this->access->assertCanWrite($user, $locked);
            $sourceId = $locked->source_registration_id === null ? null : (int) $locked->source_registration_id;
            abort_unless($sourceId === $expectedSourceId, 409, 'Draft tidak cocok dengan jenis pengajuan ini.');
            abort_unless($locked->version === $version, 409, 'Draft berubah. Muat ulang sebelum mengirim.');

            // Harus sebelum check(active), source needs_review, periode dan kelengkapan.
            if ($locked->status === SidangTaRegistrationDraft::STATUS_CONSUMED) {
                $existing = $locked->submittedRegistration()->firstOrFail();
                abort_unless($existing->student_number === $student->student_number, 403);
                return $existing; // Tidak mengubah keputusan reviewer atau menulis log ulang.
            }

            $this->access->check($user, $locked, true);
            Requirements::assertKnownVersion($locked);
            $isMeet = $locked->requirements_version === 2;
            abort_if($isMeet && $sourceId !== null, 422,
                'Pengiriman revisi ini belum tersedia. Data dan berkas tetap tersimpan.');
            abort_unless($locked->reviewed_version === $locked->version, 409, 'Tinjau draft terlebih dahulu.');
            if (!$isMeet && Requirements::firstIncompleteStep($locked) !== null) {
                throw ValidationException::withMessages(['draft' => 'Data atau berkas belum lengkap. Buka form dan lengkapi kembali.']);
            }
            $source = $sourceId === null ? null
                : $locked->sourceRegistration()->lockForUpdate()->firstOrFail();
            $period = $locked->period()->lockForUpdate()->firstOrFail();
            if (!$source && !$period->isOpen()) {
                throw ValidationException::withMessages(['draft' => 'Periode telah ditutup. Pilih periode terbuka dan tinjau ulang.']);
            }
            if ($source) {
                abort_unless((int) $source->sidang_ta_period_id === (int) $locked->sidang_ta_period_id, 409);
            }
            $otherActive = SidangTaRegistration::where('student_number', $student->student_number)
                ->whereIn('status', ['submitted', 'needs_review']);
            if ($source) $otherActive->where('id', '!=', $source->id);
            abort_if($otherActive->exists(), 409, 'Anda masih memiliki pengajuan aktif lain.');

            $payload = $locked->payload ?? [];
            $admission = null;
            if ($isMeet) {
                // One fresh decision supplies gates, required files and the stored snapshot.
                $admission = $this->facts->forStudent($student);
                $errors = [];
                foreach ($admission['academic']['blockers'] as $key => $message) {
                    $errors['eligibility.'.$key] = $message;
                }
                if (!in_array($admission['eprt']['mode'], ['automatic', 'waiver'], true)) {
                    $errors['eligibility.eprt'] = $admission['eprt']['message'];
                }
                if ($errors !== []) throw ValidationException::withMessages($errors);
                $rules = [];
                foreach (range(1, 4) as $step) $rules = array_merge($rules, Requirements::scalarRules($step));
                if (Validator::make($payload, $rules)->fails()) {
                    throw ValidationException::withMessages(['draft' => 'Data belum lengkap. Buka form dan lengkapi kembali.']);
                }
                $types = Meet::allTypes((bool) $payload['diploma_biodata_accurate'], $admission['eprt']['mode']);
            } else {
                $types = array_merge(Requirements::CAPTURES, Requirements::PAYMENT,
                    Requirements::typesForStep(4, $payload['diploma_biodata_accurate']));
            }
            $allDocuments = $locked->documents()->lockForUpdate()->get();
            // A formerly necessary waiver may become obsolete after a qualifying test.
            // Only an explicit successful submission retires it; failed submissions keep it.
            $documents = $isMeet && $admission['eprt']['mode'] === 'automatic'
                ? $allDocuments->reject(fn ($document) => $document->document_type === Meet::WAIVER)->values()
                : $allDocuments;
            $actual = $documents->pluck('document_type')->all();
            $expected = $types;
            sort($actual); sort($expected);
            if ($actual !== $expected) {
                throw ValidationException::withMessages(['draft' => 'Daftar berkas tidak sesuai persyaratan aktif. Buka form dan tinjau ulang.']);
            }
            foreach ($documents as $document) {
                if (!Storage::disk('local')->exists($document->file_path)) {
                    throw ValidationException::withMessages(['documents.'.$document->document_type =>
                        'Berkas tidak ditemukan. Unggah ulang dan tinjau kembali.']);
                }
            }
            $snapshot = $this->snapshots->forStudent($student);
            if ($isMeet) {
                $snapshot = array_replace($snapshot, [
                    'passed_credits' => $admission['facts']['passed_credits'],
                    'tak_score' => $admission['facts']['tak_score'],
                    'eprt_score' => $admission['eprt_facts']['score'],
                    'sidang_tingkat_2' => 'Lulus', 'skpi_status' => 'Disetujui',
                    'ta2_krs_status' => 'eligible', 'ta2_krs_label' => 'Terdaftar',
                    'admission' => $admission + ['checked_at' => now()->toIso8601String()],
                ]);
            }

            $registration = $source ?? new SidangTaRegistration();
            $oldStatus = $source?->status;
            $oldPaths = $source ? $source->documents()->pluck('file_path')->all() : [];
            $registration->fill([
                'sidang_ta_period_id' => $period->id,
                'student_number' => $student->student_number, 'student_name' => $student->name,
                'study_program' => $student->study_program,
                'student_phone' => $payload['student_phone'], 'parent_phone' => $payload['parent_phone'],
                'graduation_payment_date' => $payload['graduation_payment_date'],
                'diploma_biodata_accurate' => $payload['diploma_biodata_accurate'],
                'academic_snapshot' => $snapshot,
                'requirements_version' => $locked->requirements_version,
                'status' => 'submitted', 'submitted_at' => now(),
                'reviewed_by' => null, 'reviewed_at' => null, 'review_notes' => null,
            ]);
            if (!$source) $registration->submitted_by = $user->id;
            $registration->lock_version = $source ? $source->lock_version + 1 : 1;
            $registration->save();

            foreach ($documents as $document) {
                $registration->documents()->updateOrCreate(['document_type' => $document->document_type], [
                    'file_name' => $document->file_name, 'file_path' => $document->file_path,
                    'file_size' => $document->file_size, 'file_type' => $document->file_type,
                    'status' => 'pending', 'notes' => null,
                ]);
            }
            // Lepaskan FK source_document_id sebelum menghapus row final obsolete.
            $locked->documents()->delete();
            $registration->documents()->whereNotIn('document_type', $types)->delete();
            $retired = array_values(array_diff(array_merge($oldPaths, $allDocuments->pluck('file_path')->all()),
                $documents->pluck('file_path')->all()));
            $registration->logAction($source ? 'resubmitted' : 'submitted', $oldStatus,
                'submitted', $source ? 'Mahasiswa mengirim revisi.' : 'Pendaftaran dikirim mahasiswa.', $user->id);
            $locked->status = SidangTaRegistrationDraft::STATUS_CONSUMED;
            $locked->submitted_registration_id = $registration->id;
            $locked->save(); // Version draft tetap untuk identitas replay.

            return $registration;
        });

        // Hanya sesudah transaksi final sukses. Jika DB gagal, tidak ada file yang dihapus.
        foreach (array_unique($retired) as $path) {
            try {
                if (SidangTaRegistrationDocument::where('file_path', $path)->exists()
                    || SidangTaRegistrationDraftDocument::where('file_path', $path)->exists()) {
                    continue;
                }
                if (!Storage::disk('local')->delete($path)) {
                    Log::warning('Cleanup finalisasi perlu diulang.', ['path' => $path]);
                }
            } catch (Throwable $exception) {
                Log::warning('Cleanup finalisasi gagal.', ['path' => $path, 'error' => $exception->getMessage()]);
            }
        }
        return $registration;
    }
}
```

### `app/Support/SidangTaReviewEligibility.php`

Ganti isi file dengan kode lengkap berikut.

```php
<?php

namespace App\Support;

use App\Models\SidangTaRegistration;
use Illuminate\Support\Facades\Storage;

class SidangTaReviewEligibility
{
    public static function requiredTypes(SidangTaRegistration $registration): array
    {
        if ($registration->requirements_version === 2) {
            $mode = $registration->academic_snapshot['admission']['eprt']['mode'] ?? null;
            abort_unless(in_array($mode, ['automatic', 'waiver'], true), 409,
                'Snapshot persyaratan pengajuan belum lengkap.');
            return SidangTaMeetRequirements::allTypes($registration->diploma_biodata_accurate, $mode);
        }
        abort_unless($registration->requirements_version === 1, 409, 'Versi persyaratan pengajuan tidak dikenali.');
        return array_merge(SidangTaDocumentRequirements::CAPTURES, SidangTaDocumentRequirements::PAYMENT,
            SidangTaDocumentRequirements::typesForStep(4, $registration->diploma_biodata_accurate));
    }

    public static function groups(SidangTaRegistration $registration): array
    {
        if ($registration->requirements_version === 2) {
            $required = self::requiredTypes($registration);
            return [
                'iGracias & Akademik' => array_values(array_intersect(SidangTaMeetRequirements::CAPTURES, $required)),
                'Pembayaran & Dokumen' => array_values(array_intersect(
                    array_merge(SidangTaMeetRequirements::PAYMENT, [SidangTaMeetRequirements::WAIVER]), $required)),
                'Biodata' => array_values(array_intersect(array_merge(
                    SidangTaMeetRequirements::DIPLOMA, SidangTaMeetRequirements::PDM), $required)),
            ];
        }
        self::requiredTypes($registration); // Validate the version before selecting legacy groups.
        return ['iGracias & Akademik' => SidangTaDocumentRequirements::CAPTURES,
            'Pembayaran & Dokumen' => SidangTaDocumentRequirements::PAYMENT,
            'Biodata' => array_merge(SidangTaDocumentRequirements::DIPLOMA, SidangTaDocumentRequirements::PDM)];
    }

    public static function blockers(SidangTaRegistration $registration): array
    {
        $documents = $registration->relationLoaded('documents')
            ? $registration->documents : $registration->documents()->get();
        $blockers = [];
        foreach (self::requiredTypes($registration) as $type) {
            $document = $documents->firstWhere('document_type', $type);
            $reason = !$document ? 'Belum ada dokumen'
                : (!Storage::disk('local')->exists($document->file_path) ? 'Berkas tidak ditemukan'
                    : ($document->status !== 'verified' ? 'Belum terverifikasi' : null));
            if ($reason !== null) $blockers[$type] = SidangTaRegistration::DOCUMENT_TYPES[$type].' — '.$reason;
        }
        return $blockers;
    }
}
```

Finalizer versi 2 tidak menggunakan `firstIncompleteStep()` sebagai satu-satunya pemeriksaan akhir: helper itu membaca mode EPRT sendiri. Pada finalisasi, scalar, daftar dokumen, dan keberadaan berkas diperiksa terhadap **satu hasil fakta final**. Saat waiver berubah menjadi automatic, hanya surat yang sudah tidak diperlukan boleh dilepas setelah transaksi berhasil; file lain tidak ikut dihapus.

Transaksi mempertahankan lock Student → draft → pengajuan asal/periode/dokumen. Pemeriksaan ini membaca ulang data pada transaksi pengiriman; sinkronisasi serentak lintas tabel belum dijamin mempunyai satu versi atomik karena importer belum memakai protokol lock/revisi data akademik bersama. Test perubahan data di sini dilakukan sesudah Tinjau dan sebelum request Kirim. Jangan mengklaim serialisasi seluruh jalur import dari checkpoint ini.

Referensi API yang diperiksa melalui Context7: [transaksi Laravel](https://laravel.com/docs/13.x/database#database-transactions) dan [pessimistic locking](https://laravel.com/docs/13.x/queries#pessimistic-locking). Exception membatalkan transaksi; cleanup berkas tetap dilakukan setelah transaksi finalisasi berhasil.

## 6. Integrasi label, tampilan, ekspor, dan tombol

### `app/Models/SidangTaRegistration.php`

Cari blok berikut:

```php
        'pdm_submission_proof' => 'Bukti Pengajuan PDM',
```

Ganti dengan:

```php
        'pdm_submission_proof' => 'Bukti Pengajuan PDM',
        'eprt_waiver_letter' => 'Surat Permakluman EPRT',
```

### `app/Services/SidangTaReviewExport.php`

Ganti seluruh isi file berikut.

```php
<?php

namespace App\Services;

use App\Models\SidangTaRegistration;
use App\Support\SidangTaReviewEligibility as Eligibility;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Rap2hpoutre\FastExcel\FastExcel;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class SidangTaReviewExport
{
    public const SNAPSHOT_COLUMNS = [
        'Snapshot NIM' => 'student_number',
        'Snapshot Nama' => 'student_name',
        'Snapshot Prodi' => 'study_program',
        'Email Kampus' => 'student_email',
        'Semester' => 'semester',
        'IPK' => 'gpa',
        'SKS Lulus' => 'passed_credits',
        'Sidang Tingkat 2' => 'sidang_tingkat_2',
        'KRS Sidang TA 2' => 'ta2_krs_label',
        'Judul TA Indonesia' => 'ta_title',
        'Judul TA Inggris' => 'ta_title_en',
        'Pembimbing 1' => 'advisor_1',
        'Pembimbing 2' => 'advisor_2',
        'Penguji 1' => 'examiner_1',
        'Penguji 2' => 'examiner_2',
        'Penguji 3' => 'examiner_3',
        'Tanggal Sidang' => 'exam_date',
        'Waktu Sidang' => 'exam_time',
        'Lokasi Sidang' => 'exam_location',
        'TAK' => 'tak_score',
        'EPrT' => 'eprt_score',
        'Status BPP' => 'bpp_status',
        'Status SKPI' => 'skpi_status',
        'Status TAK' => 'skpi_tak',
        'Sidang Tingkat 3' => 'level_3_status',
        'Status i-Basila' => 'ibasila_status',
        'Sumber Akademik Diperbarui' => 'academic_source_updated_at',
    ];

    private function text(mixed $value): string
    {
        if ($value === null || (is_string($value) && in_array(trim($value), ['', '-'], true))) return 'Belum tersedia';
        if (is_bool($value)) return $value ? 'Ya' : 'Tidak';
        if (is_scalar($value)) return (string) $value;
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)
            ?: 'Belum tersedia';
    }

    public function row(SidangTaRegistration $registration): array
    {
        $snapshot = is_array($registration->academic_snapshot) ? $registration->academic_snapshot : [];
        $row = [
            'ID Pengajuan' => (string) $registration->id,
            'Periode Sidang TA' => $this->text($registration->period?->name),
            'NIM' => $this->text($registration->student_number),
            'Nama Mahasiswa' => $this->text($registration->student_name),
            'Program Studi' => $this->text($registration->study_program),
            'HP Mahasiswa' => $this->text($registration->student_phone),
            'HP Orang Tua/Wali' => $this->text($registration->parent_phone),
            'Tanggal Bayar Wisuda' => $this->text($registration->graduation_payment_date?->format('Y-m-d')),
            'Biodata Ijazah' => $registration->diploma_biodata_accurate ? 'Sudah sesuai' : 'Belum sesuai (PDM)',
            'Status Pengajuan' => SidangTaReviewQuery::STATUSES[$registration->status] ?? $this->text($registration->status),
            'Dikirim' => $this->text($registration->submitted_at?->format('Y-m-d H:i:s')),
            'Ditinjau' => $this->text($registration->reviewed_at?->format('Y-m-d H:i:s')),
            'Catatan Petugas' => $this->text($registration->review_notes),
            'Versi Pengajuan' => (string) $registration->lock_version,
        ];
        foreach (self::SNAPSHOT_COLUMNS as $label => $key) $row[$label] = $this->text($snapshot[$key] ?? null);
        $required = Eligibility::requiredTypes($registration);
        $labels = ['pending' => 'Menunggu verifikasi', 'verified' => 'Terverifikasi', 'rejected' => 'Perlu perbaikan'];
        foreach (SidangTaRegistration::DOCUMENT_TYPES as $type => $label) {
            $applicable = in_array($type, $required, true);
            $document = $registration->documents->firstWhere('document_type', $type);
            $row[$label.' — Status'] = !$applicable ? 'N/A' : (!$document ? 'Belum ada dokumen'
                : ($labels[$document->status] ?? $this->text($document->status)));
            $row[$label.' — Ketersediaan'] = !$applicable ? 'N/A' : (!$document ? 'Tidak ada metadata'
                : (Storage::disk('local')->exists($document->file_path) ? 'Tersedia' : 'Tidak ditemukan'));
            $row[$label.' — Catatan'] = !$applicable ? 'N/A' : $this->text($document?->notes);
        }
        return $row;
    }

    public function download(Collection $registrations): BinaryFileResponse
    {
        if ($registrations->isEmpty()) {
            throw ValidationException::withMessages(['export' => 'Tidak ada pengajuan yang cocok untuk diekspor.']);
        }
        $rows = $registrations->map(fn (SidangTaRegistration $registration) => $this->row($registration));
        $temporary = tempnam(sys_get_temp_dir(), 'dadu-ta-xlsx-');
        if ($temporary === false) throw new RuntimeException('File sementara ekspor tidak dapat dibuat.');
        $path = $temporary.'.xlsx';
        if (!@rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('File sementara XLSX tidak dapat disiapkan.');
        }
        try {
            (new FastExcel($rows))->stringValues()->setColumnFormat([
                'Semester' => 'number', 'IPK' => 'number', 'SKS Lulus' => 'number',
                'TAK' => 'number', 'EPrT' => 'number',
            ])->escapeFormulas()->export($path);
            clearstatcache(true, $path);
            return response()->download($path, 'Sidang_TA_'.now()->format('Y-m-d_H-i-s').'.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ])->setPrivate()->deleteFileAfterSend(true);
        } catch (Throwable $exception) {
            if (is_file($path)) @unlink($path);
            throw $exception;
        }
    }
}
```

### `app/Services/SidangTaRegistrationReview.php`

Ganti seluruh isi file berikut.

```php
<?php

namespace App\Services;

use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDocument;
use App\Models\User;
use App\Support\SidangTaReviewEligibility as Eligibility;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SidangTaRegistrationReview
{
    private function locked(SidangTaRegistration $registration, int $version): SidangTaRegistration
    {
        $locked = SidangTaRegistration::whereKey($registration->id)->lockForUpdate()->firstOrFail();
        abort_unless($locked->lock_version === $version, 409, 'Pengajuan berubah. Muat ulang sebelum meninjau.');
        abort_unless(in_array($locked->status, ['submitted', 'needs_review'], true), 422,
            'Pengajuan sudah diputuskan dan tidak dapat ditinjau kembali.');
        abort_unless($locked->requirements_version === 1, 422,
            'Peninjauan pengajuan ini belum dibuka. Berkas tetap dapat dilihat.');
        return $locked;
    }

    public function document(User $user, SidangTaRegistration $registration, SidangTaRegistrationDocument $document,
                             int $version, string $status, ?string $notes): SidangTaRegistration
    {
        abort_unless($user->can('sidang_ta_registration_review'), 403);
        abort_unless(in_array($status, ['verified', 'rejected'], true), 422);
        $notes = trim($notes ?? '');
        $notes = $notes === '' ? null : $notes;
        if ($status === 'rejected' && $notes === null) {
            throw ValidationException::withMessages(['notes' => 'Isi catatan perbaikan dokumen.']);
        }
        return DB::transaction(function () use ($user, $registration, $document, $version, $status, $notes) {
            $locked = $this->locked($registration, $version);
            $doc = $locked->documents()->whereKey($document->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($doc->document_type, Eligibility::requiredTypes($locked), true), 422,
                'Dokumen ini tidak berlaku untuk cabang biodata pengajuan.');
            if ($status === 'verified' && !Storage::disk('local')->exists($doc->file_path)) {
                throw ValidationException::withMessages(['document' => 'Berkas tidak ditemukan. Minta mahasiswa mengunggah ulang.']);
            }
            $oldRegistrationStatus = $locked->status;
            $nextStatus = $status === 'rejected' ? 'needs_review' : $locked->status;
            if ($doc->status === $status && $doc->notes === $notes && $locked->status === $nextStatus) return $locked;
            $labels = ['pending' => 'Menunggu verifikasi', 'verified' => 'Terverifikasi', 'rejected' => 'Perlu perbaikan'];
            $oldDocumentStatus = $labels[$doc->status] ?? $doc->status;
            $doc->status = $status;
            $doc->notes = $notes;
            $doc->save();
            $locked->status = $nextStatus;
            $locked->reviewed_by = $user->id;
            $locked->reviewed_at = now();
            $locked->lock_version++;
            $locked->save();
            $logNotes = SidangTaRegistration::DOCUMENT_TYPES[$doc->document_type].': '.$oldDocumentStatus.' → '.$labels[$status];
            if ($notes !== null) $logNotes .= '. Catatan: '.$notes;
            $locked->logAction($status === 'verified' ? 'document_verified' : 'document_rejected',
                $oldRegistrationStatus, $nextStatus, $logNotes, $user->id);
            return $locked;
        }, 3);
    }

    public function decide(User $user, SidangTaRegistration $registration, int $version,
                           string $action, ?string $notes): SidangTaRegistration
    {
        abort_unless($user->can('sidang_ta_registration_review'), 403);
        abort_unless(in_array($action, ['approved', 'needs_review', 'rejected'], true), 422);
        $notes = trim($notes ?? '');
        $notes = $notes === '' ? null : $notes;
        if (in_array($action, ['needs_review', 'rejected'], true) && $notes === null) {
            throw ValidationException::withMessages(['review_notes' => 'Isi catatan revisi atau penolakan.']);
        }
        return DB::transaction(function () use ($user, $registration, $version, $action, $notes) {
            $locked = $this->locked($registration, $version);
            if ($action === 'approved') {
                $locked->setRelation('documents', $locked->documents()->lockForUpdate()->get());
                $blockers = Eligibility::blockers($locked);
                if ($blockers !== []) {
                    throw ValidationException::withMessages(['documents' => 'Persetujuan belum dapat dilakukan: '.implode('; ', $blockers)]);
                }
            }
            $old = $locked->status;
            $locked->status = $action;
            $locked->review_notes = $notes;
            $locked->reviewed_by = $user->id;
            $locked->reviewed_at = now();
            $locked->lock_version++;
            $locked->save();
            $locked->logAction($action, $old, $action, $notes, $user->id);
            return $locked;
        }, 3);
    }
}
```

### `app/Http/Controllers/SidangTaRegistrationController.php`

Cari blok berikut:

```php
        $reviewable = in_array($registration->status, ['submitted', 'needs_review'], true);
```

Ganti dengan:

```php
        $reviewable = $registration->requirements_version === 1
            && in_array($registration->status, ['submitted', 'needs_review'], true);
```

### `resources/views/sidang_ta_registrations/admin/show.blade.php`

Cari blok berikut:

```php
        @include('sidang_ta_registrations.partials.detail-academic')
```

Ganti dengan:

```php
        @if($registration->requirements_version === 2)
            <p data-meet-review-held role="status" class="rounded-xl bg-amber-50 p-4 text-sm text-amber-900">
                Peninjauan pengajuan ini belum dibuka. Berkas tetap dapat dilihat.
            </p>
        @endif
        @include('sidang_ta_registrations.partials.detail-academic')
```

Cari blok berikut:

```php
        @if(in_array($registration->status, ['submitted', 'needs_review'], true))
```

Ganti dengan:

```php
        @if($reviewable)
```

### `resources/views/sidang_ta_registrations/admin/index.blade.php`

Cari blok berikut:

```php
                                    $requiredTypes = array_merge(\App\Support\SidangTaDocumentRequirements::CAPTURES, \App\Support\SidangTaDocumentRequirements::PAYMENT, \App\Support\SidangTaDocumentRequirements::typesForStep(4, $registration->diploma_biodata_accurate));
```

Ganti dengan:

```php
                                    $requiredTypes = \App\Support\SidangTaReviewEligibility::requiredTypes($registration);
                                    $rowGroups = \App\Support\SidangTaReviewEligibility::groups($registration);
```

Cari blok berikut:

```php
                                    @foreach($groups as $types)
```

Ganti dengan:

```php
                                    @foreach($rowGroups as $types)
```

### `resources/views/sidang_ta_registrations/partials/detail-academic.blade.php`

Cari blok berikut:

```php
['academic_source_updated_at', 'ta2_krs_status']
```

Ganti dengan:

```php
['academic_source_updated_at', 'ta2_krs_status', 'admission']
```

### `app/Http/Controllers/SidangTaDraftController.php`

Cari blok berikut:

```php
            'admission' => $admission, 'types' => $types,
```

Ganti dengan:

```php
            'admission' => $admission, 'types' => $types,
            'can_submit' => $draft->source_registration_id === null
                && $admission['academic']['academic_passed']
                && in_array($mode, ['automatic', 'waiver'], true),
```

### `resources/views/sidang_ta_registrations/wizard-review.blade.php`

Ganti seluruh isi file berikut.

```php
<x-app-layout>
    <x-slot name="header">Tinjau Pendaftaran Sidang TA</x-slot>
    @php
        $payload = $draft->payload;
        $isMeet = $draft->requirements_version === 2;
        $meetContext = $meetContext ?? null;
        $sendBlocked = $isMeet && !($meetContext['can_submit'] ?? false);
        $accurate = (bool) $payload['diploma_biodata_accurate'];
        $isRevision = $draft->source_registration_id !== null;
        $permission = $isRevision ? 'sidang_ta_registration_update' : 'sidang_ta_registration_create';
        $groups = [2 => 'Verifikasi iGracias & akademik', 3 => 'Pembayaran & dokumen', 4 => 'Biodata ijazah'];
        $documentCount = array_sum(array_map('count', $documentGroups));
        $confirmLabel = $isRevision ? 'Ya, kirim revisi' : 'Ya, kirim pendaftaran';
    @endphp
    <div class="mx-auto max-w-6xl space-y-5">
        @if($errors->any())
            <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
                <p class="font-bold">Pendaftaran belum dapat dikirim.</p>
                <ul class="mt-2 list-inside list-disc space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section data-review-summary class="campus-card overflow-hidden">
            <header class="border-b border-rose-100 bg-rose-50/60 p-5 sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-[#DA2D3B]">Pemeriksaan
                            akhir{{ $isRevision ? ' · Revisi' : '' }}</p>
                        <h2 class="mt-1 text-xl font-bold text-slate-900">Tinjau sebelum kirim</h2>
                        <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-600">Periksa kembali data dan
                            seluruh berkas sebelum mengirim pendaftaran kepada petugas.</p>
                    </div>
                    <span
                        class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800">Belum dikirim</span>
                </div>
            </header>
            <div class="space-y-5 p-5 sm:p-6">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="min-w-0 rounded-xl bg-slate-50 p-4">
                        <p class="text-xs font-semibold text-slate-500">Mahasiswa</p>
                        <p class="mt-1 break-words text-base font-bold text-slate-900">{{ $student->name }}</p>
                        <p class="mt-1 break-words text-sm text-slate-600">{{ $student->student_number }}
                            · {{ $student->study_program ?: 'Program studi belum tersedia' }}</p>
                    </div>
                    <div class="min-w-0 rounded-xl bg-slate-50 p-4">
                        <p class="text-xs font-semibold text-slate-500">Periode Sidang TA</p>
                        <p class="mt-1 break-words text-base font-bold text-slate-900">{{ $draft->period->name }}</p>
                        <p class="mt-1 text-sm text-slate-600">{{ $documentCount }} dokumen dalam
                            tinjauan{{ $isRevision ? ' · Periode pengajuan asal' : '' }}</p>
                    </div>
                </div>
                <dl class="grid gap-x-6 gap-y-5 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs text-slate-500">
                            Nomor HP mahasiswa
                        </dt>
                        <dd class="mt-1 break-words text-sm font-semibold text-slate-800">
                            {{ $payload['student_phone'] }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">
                            Nomor HP orang tua/wali
                        </dt>
                        <dd class="mt-1 break-words text-sm font-semibold text-slate-800">
                            {{ $payload['parent_phone'] }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">
                            Tanggal pembayaran wisuda
                        </dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-800">
                            {{ \Illuminate\Support\Carbon::parse($payload['graduation_payment_date'])->translatedFormat('d M Y') }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">
                            Biodata dummy ijazah
                        </dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-800">
                            {{ $accurate ? 'Sudah sesuai' : 'Belum sesuai, bukti PDM dilampirkan' }}
                        </dd>
                    </div>
                </dl>
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
                    <p class="text-xs leading-relaxed text-slate-500">
                        Data di bawah berasal dari draft yang tersimpan. Pendaftaran belum dikirim.
                    </p>
                    <a class="text-sm font-semibold text-[#DA2D3B] hover:underline"
                       href="{{ route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 1]) }}">
                        Ubah data kontak
                    </a>
                </div>
            </div>
        </section>

        @if($isMeet)
            @include('sidang_ta_registrations.partials.meet-admission-summary', ['admission' => $meetContext['admission']])
        @endif

        @foreach($groups as $number => $title)
            @php
                $types = $documentGroups[$number];
            @endphp
            <section data-review-group="{{ $number }}" class="campus-card overflow-hidden">
                <header
                    class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4 sm:px-6">
                    <div class="flex items-center gap-3">
                        <div>
                            <h3 class="text-base font-bold text-slate-800">
                                {{ $title }}
                            </h3>
                            <p class="mt-0.5 text-xs text-slate-500">
                                Tahap {{ $number }} · {{ count($types) }} berkas tersimpan
                            </p>
                        </div>
                    </div>
                    <a class="text-sm font-semibold text-[#DA2D3B] hover:underline"
                       href="{{ route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => $number]) }}">Ubah
                        tahap {{ $number }}</a>
                </header>
                @if($isMeet && isset($meetContext['automatic'][$number]))
                    <div class="px-5 pt-4 sm:px-6">
                        @include('sidang_ta_registrations.partials.meet-automatic-cards', ['items' => $meetContext['automatic'][$number]])
                    </div>
                @endif
                <div class="divide-y divide-slate-100 px-5 sm:px-6">
                    @foreach($types as $type)
                        @php
                            $document = $draft->documents->firstWhere('document_type', $type);
                            $documentLabel = ($isMeet ? \App\Support\SidangTaMeetRequirements::LABELS[$type] ?? null : null)
                                ?? \App\Models\SidangTaRegistration::DOCUMENT_TYPES[$type] ?? $type;
                        @endphp
                        <div data-review-document="{{ $type }}"
                             class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between sm:gap-5">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold leading-relaxed text-slate-800">{{ $documentLabel }}</p>
                                <p class="mt-1 break-words text-xs leading-relaxed text-slate-500">{{ $document->file_name }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ number_format(($document->file_size ?? 0) / 1024) }}
                                    KB</p>
                            </div>
                            <a data-document-preview
                               class="btn btn-secondary shrink-0 self-start whitespace-nowrap !text-xs no-underline sm:self-auto"
                               href="{{ route('sidang-ta-draft-documents.preview', $document) }}"
                               data-preview-url="{{ route('sidang-ta-draft-documents.preview', $document) }}"
                               data-download-url="{{ route('sidang-ta-draft-documents.download', $document) }}"
                               data-file-name="{{ $document->file_name }}" data-file-type="{{ $document->file_type }}"
                               target="_blank" rel="noopener" aria-haspopup="dialog"
                               aria-label="Lihat {{ $documentLabel }}"
                               x-on:click.prevent="$dispatch('sidang-ta-preview', $el.dataset)">
                                Lihat berkas
                            </a>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach

        <section data-review-actions class="campus-card space-y-4 p-5 sm:p-6"
                 x-data="{ sending: false }" @pageshow.window="sending = false">
            <div>
                <h2 class="text-base font-bold text-slate-800">
                    Sudah memeriksa semua data?
                </h2>
                <p class="mt-1 text-sm leading-relaxed text-slate-600">
                    Pastikan periode, kontak, pembayaran, biodata, dan setiap berkas sudah benar. Anda masih dapat
                    kembali ke form untuk memperbaikinya.
                </p>
            </div>
            @if($sendBlocked)
                <p id="registration-send-readiness" data-meet-send-held role="status" class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900">
                    {{ $isRevision ? 'Pengiriman revisi ini belum tersedia. Data dan berkas tetap tersimpan.'
                        : 'Penuhi persyaratan akademik dan EPRT sebelum mengirim. Draft dan berkas tetap tersimpan.' }}
                </p>
            @endif
            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-wrap gap-2">
                    <a class="btn btn-secondary"
                       href="{{ route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 1]) }}">
                        Kembali ke form
                    </a>
                    <a class="btn btn-secondary" href="{{ route('sidang-ta-registrations.index') }}">
                        Kembali ke pendaftaran
                    </a>
                </div>
                @can($permission)
                    <button data-open-registration type="button"
                            class="btn btn-primary disabled:cursor-not-allowed disabled:opacity-50 disabled:shadow-none"
                            aria-disabled="{{ $sendBlocked ? 'true' : 'false' }}"
                            :aria-disabled="sending || {{ $sendBlocked ? 'true' : 'false' }} ? 'true' : 'false'"
                            @if($sendBlocked) aria-describedby="registration-send-readiness" @endif
                            @disabled($sendBlocked) :disabled="sending || {{ $sendBlocked ? 'true' : 'false' }}"
                            aria-haspopup="dialog" aria-controls="confirm-send-registration"
                            onclick="document.getElementById('confirm-send-registration').showModal()">Kirim Pendaftaran
                    </button>
                @endcan
            </div>
            @can($permission)
                <dialog id="confirm-send-registration" aria-labelledby="confirm-send-title"
                        aria-describedby="confirm-send-description"
                        @cancel="if (sending) $event.preventDefault()"
                        class="m-auto w-11/12 max-w-lg rounded-2xl border border-slate-200 bg-white p-5 text-slate-800 shadow-xl backdrop:bg-slate-900/50 sm:p-6">
                    <h2 id="confirm-send-title" class="text-lg font-bold">
                        {{ $isRevision ? 'Kirim revisi pendaftaran?' : 'Kirim pendaftaran sekarang?' }}
                    </h2>
                    <div id="confirm-send-description" class="mt-3 space-y-3 text-sm leading-relaxed text-slate-600">
                        <p>Periksa kembali data dan seluruh dokumen. Setelah dikirim, pengajuan akan ditinjau oleh
                            petugas.</p>
                        <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-amber-900">
                            <p class="font-semibold">Pastikan kembali:</p>
                            <ul class="mt-2 list-inside list-disc space-y-1">
                                <li>Periode Sidang TA dan nomor kontak sudah benar.</li>
                                <li>Tanggal pembayaran wisuda serta pilihan biodata sudah sesuai.</li>
                                <li>Seluruh berkas lengkap, benar, dan dapat dibaca.</li>
                            </ul>
                        </div>
                        <p>Jika masih ragu, pilih Periksa kembali. Jika sudah yakin, lanjutkan pengiriman. Perubahan
                            setelah pengiriman mengikuti proses revisi yang tersedia.</p>
                    </div>
                    <form data-final-registration-form method="POST"
                          class="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"
                          action="{{ $isRevision ? route('sidang-ta-registrations.update', $draft->source_registration_id) : route('sidang-ta-registrations.store') }}"
                          @submit="if (sending || {{ $sendBlocked ? 'true' : 'false' }}) { $event.preventDefault(); } else { sending = true; }">
                        @csrf
                        @if($isRevision)
                            @method('PUT')
                        @endif
                        <input type="hidden" name="draft_id" value="{{ $draft->id }}">
                        <input type="hidden" name="version" value="{{ $draft->version }}">
                        <input type="hidden" name="action" value="submit">
                        <button data-cancel-registration type="button" autofocus class="btn btn-secondary"
                                :disabled="sending"
                                onclick="this.closest('dialog').close()">Periksa kembali
                        </button>
                        <button data-confirm-registration type="submit"
                                class="btn btn-primary disabled:cursor-not-allowed disabled:opacity-50 disabled:shadow-none"
                                aria-disabled="{{ $sendBlocked ? 'true' : 'false' }}"
                                :aria-disabled="sending || {{ $sendBlocked ? 'true' : 'false' }} ? 'true' : 'false'"
                                @if($sendBlocked) aria-describedby="registration-send-readiness" @endif
                                @disabled($sendBlocked) :disabled="sending || {{ $sendBlocked ? 'true' : 'false' }}"
                                x-text="sending ? 'Sedang mengirim…' : '{{ $confirmLabel }}'">{{ $confirmLabel }}</button>
                    </form>
                </dialog>
            @endcan
        </section>
    </div>
    @include('sidang_ta_registrations.partials.document-preview-modal')
</x-app-layout>
```

Kedua tombol dan guard submit memakai `$sendBlocked`, bukan `$isMeet`. Modal tetap dipertahankan.

## 7. Jalankan test sesudah kode

### A. Test baru dan integrasi utama

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaMeetFinalizationTest.php tests/Feature/SidangTaMeetDraftTest.php tests/Feature/SidangTaMeetUiTest.php tests/Feature/SidangTaFinalizationTest.php tests/Feature/SidangTaReviewExportTest.php
```
Target: **76 passed** (27 baru +14 draft meet +12 UI meet +13 finalisasi lama +10 ekspor), tanpa test yang dihapus untuk menyembunyikan kegagalan.

### B. Area pengajuan/review lama yang tersentuh resolver dan tombol

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaRequirementsVersionTest.php tests/Feature/SidangTaRevisionDraftTest.php tests/Feature/SidangTaReviewListTest.php tests/Feature/SidangTaDocumentReviewTest.php tests/Feature/SidangTaReviewFeedbackTest.php tests/Feature/SidangTaDetailUiTest.php tests/Feature/SidangTaReviewUiTest.php
```
### C. Seluruh test Sidang TA dan JavaScript

Perintah berikut menyeleksi file test Sidang TA saja, termasuk Unit policy/registry. Tetap memakai database testing Podman.

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTa*Test.php tests/Unit/SidangTaAdmissionPolicyTest.php tests/Unit/SidangTaMeetRequirementsTest.php
```
```bash
node tests/Js/sidang-ta-entry.test.mjs
node tests/Js/sidang-ta-wizard.test.mjs
node tests/Js/sidang-ta-document-preview.test.mjs
```
Test baru berjumlah **27 kasus** dari 19 deklarasi test dengan dataset. Jika baseline Feature TA sebelumnya 239, sesudah ini menjadi 266. Unit policy/registry tetap 21, Node tetap 12; jumlah total terminal dapat berbeda bila baseline Anda mencakup test lain.

Setelah perubahan Blade, bila server masih menampilkan cache lama:

```bash
php artisan view:clear
```

Jalankan aplikasi/Vite seperti biasa. Tidak ada perubahan sumber JavaScript, tetapi `npm run build` boleh dipakai sebagai pemeriksaan build sebelum pemindahan.

## 8. Manual pada draft dummy versi 2 yang sudah berhasil dibuka

### A. Siapkan tinjauan, jangan kirim dahulu

1. Login akun `dummy-ta-2@mahasiswa.test` dengan password dummy yang dibuat pada 55.
2. Lanjutkan **draft versi 2 yang sama**. Jangan menjalankan promosi metadata pada draft nyata atau mengulang persiapan akun/draft bila sudah benar.
3. Lengkapi dua upload tahap 2, empat PDF tahap 3 (termasuk Surat Permakluman) dan tanggal pembayaran, lalu dua PDF tahap 4 pada cabang biodata sudah sesuai.
4. Tinjau sebelum kirim: ada delapan berkas, lima gate terpenuhi, mode EPRT surat permakluman, kedua tombol kirim aktif bagi pemilik dengan izin create. Data persetujuan sidang yang belum tersedia tetap tidak dipalsukan.
5. Klik Kirim Pendaftaran untuk membuka modal, tetapi **jangan tekan Ya dulu**. Biarkan halaman ini terbuka untuk test perubahan data di bawah.

Jika lima gate belum terpenuhi pada dummy, periksa hasil import dummy 53 dan periode akademik aktif yang benar. Jangan mengisi nilai lulus secara sembarang pada mahasiswa nyata.

### B. Ubah TAK dummy setelah halaman Tinjau terbuka

Ini hanya untuk data dummy yang Anda izinkan. Jalankan pada aplikasi lokal dengan database `dadu`, bukan `dadu_testing` atau server. Nilai normal dummy 002 adalah TAK 60.

```bash
php artisan tinker
```

Setiap assignment di bawah merupakan perintah lengkap. Tidak ada baris yang dimulai `->`.

```php
if (app()->environment('production') || Illuminate\Support\Facades\DB::connection()->getDatabaseName() !== 'dadu') { throw new RuntimeException('Gunakan aplikasi lokal dengan database dadu.'); }
$targetNim = '1202299002';
$dummy = App\Models\Student::where('student_number', $targetNim)->where('email', 'dummy-ta-2@mahasiswa.test')->firstOrFail();
$takRows = App\Models\StudentTak::where('student_number', $dummy->student_number)->get();
if ($takRows->count() !== 1 || (float) $takRows->first()->total_points !== 60.0) { throw new RuntimeException('Skenario ini memerlukan satu data TAK dummy bernilai 60.'); }
$tak = $takRows->first();
$tak->update(['total_points' => 59]);
dump($tak->fresh()->only(['student_number', 'total_points']));
```

Kembali ke modal pada halaman lama, tekan **Ya, kirim pendaftaran**. Harus ditolak dengan alasan TAK; kembali ke halaman pendaftaran, draft masih bisa dilanjutkan dan delapan berkas tetap tersimpan. Belum ada pengajuan final baru.

Lanjutkan draft dan Tinjau kembali tanpa memulihkan TAK dahulu: gate TAK belum memenuhi; tombol kirim disabled. Langkah ini membuktikan tampilan terbaru, sedangkan pengiriman dari modal lama tadi membuktikan pemeriksaan server.

Pulihkan nilai canonical dummy. Bila Tinker sebelumnya ditutup, buka lagi dan jalankan blok lengkap berikut:

```php
if (app()->environment('production') || Illuminate\Support\Facades\DB::connection()->getDatabaseName() !== 'dadu') { throw new RuntimeException('Gunakan aplikasi lokal dengan database dadu.'); }
$dummy = App\Models\Student::where('student_number', '1202299002')->where('email', 'dummy-ta-2@mahasiswa.test')->firstOrFail();
App\Models\StudentTak::where('student_number', $dummy->student_number)->update(['total_points' => 60]);
```

### C. Kirim sesudah nilai dipulihkan

1. Tinjau ulang: gate TAK terpenuhi dan tombol kirim aktif kembali.
2. Buka modal, pilih Periksa kembali untuk memastikan tidak mengirim. Buka lagi dan pilih Ya.
3. Pengajuan menjadi submitted; detail menampilkan delapan berkas dengan label Surat Permakluman yang benar. Snapshot TAK 60, SKS 120, EPRT 449, KRS Terdaftar. Riwayat status tidak muncul pada sisi mahasiswa.
4. Draft sudah consumed dan tidak ditawarkan sebagai draft aktif lagi. Preview file masih bekerja setelah pengiriman.

Jangan mengubah pengajuan menjadi approved/needs_review lewat Tinker untuk melanjutkan manual; test replay dan penahanan revisi sudah mencakupnya di database testing.

### D. Baca sebagai petugas

1. Login akun petugas yang biasa dipakai, buka Review Sidang TA.
2. Baris pengajuan dummy 002 hanya memiliki delapan ikon upload yang berlaku; tidak ada ikon capture yang sudah digantikan data otomatis/KHS/KSM.
3. Buka Tinjau: delapan preview dapat dibuka, label surat terbaca, snapshot akademik sesuai saat pengiriman. Riwayat status tersedia dan tertutup secara default.
4. Ada pemberitahuan peninjauan belum dibuka untuk pengajuan ini. Tidak ada form verifikasi atau keputusan versi 2 pada checkpoint ini. Ini penahanan sementara, bukan bug permission.
5. Ekspor Excel tetap hijau; hasil memuat KRS dan Surat Permakluman, pengajuan versi 1 tetap terbaca. Kolom surat pada pengajuan versi 1 adalah N/A.
6. Buka salah satu pengajuan versi 1 yang biasa diuji: UI/keputusan lama masih tersedia sesuai status dan izin. Jangan mengubah keputusan pengajuan nyata hanya untuk test ini.

Variasi automatic, PDM, unknown/retest, file hilang, replay, dan pembatasan user lain sudah menjadi test otomatis. Manual tambahan dapat memakai akun dummy lain melalui prosedur 55; jangan reset pengajuan yang sudah dikirim agar skenario bisa dipaksa ulang.

## 9. Pemindahan setelah lulus

Salin **15 file** dari folder asli ke `dadu-test`, dengan path yang sama:

- `app/Services/SidangTaDraftFinalizer.php`
- `app/Support/SidangTaReviewEligibility.php`
- `app/Models/SidangTaRegistration.php`
- `app/Services/SidangTaReviewExport.php`
- `app/Services/SidangTaRegistrationReview.php`
- `app/Http/Controllers/SidangTaRegistrationController.php`
- `resources/views/sidang_ta_registrations/admin/show.blade.php`
- `resources/views/sidang_ta_registrations/admin/index.blade.php`
- `resources/views/sidang_ta_registrations/partials/detail-academic.blade.php`
- `app/Http/Controllers/SidangTaDraftController.php`
- `resources/views/sidang_ta_registrations/wizard-review.blade.php`
- `tests/Feature/SidangTaMeetDraftTest.php`
- `tests/Feature/SidangTaMeetUiTest.php`
- `tests/Feature/SidangTaReviewExportTest.php`
- `tests/Feature/SidangTaMeetFinalizationTest.php`

Tidak ada file produksi yang perlu dihapus. Tidak perlu menyalin `.env`, hasil build, cache, atau mengubah `phpunit.xml` untuk checkpoint ini. Setting testing Podman sementara tetap mengikuti keputusan Anda sampai fase meet selesai.

Tahap berikutnya: aturan verifikasi dan revisi terarah versi 2, pembatasan upload revisi di server/UI, mempertahankan verified pada berkas yang tidak direvisi, layout dua kolom/status card petugas, dan deadline revisi. Setelah jalur baru lengkap baru dilakukan aktivasi pendaftaran versi 2 serta rescan penutupan/commit. **Belum waktunya menyatakan seluruh student/petugas hasil meet selesai.**

## 10. Pemeriksaan panduan oleh asisten

Usulan kode diperiksa di `/tmp`, bukan dipasang ke source aplikasi. PHP lint pada sebelas file PHP lengkap lulus; empat Blade yang berubah dikompilasi dalam memori. Probe render 64 assertion mencakup versi 1/2, empat tahap, dan Tinjau. Probe resolver/kelompok/ekspor 42 assertion memeriksa enam kombinasi versi/mode/biodata dan 92 kolom tanpa DB. Pemeriksaan independen tidak menemukan blocker; tambahan test scalar finalisasi dimasukkan setelah review.

Ini bukan laporan bahwa Feature test atau manual Anda sudah dijalankan oleh asisten. Jalankan bagian 7–8 setelah menerapkan kode. Hasil rescan source sebelum/sesudah penulisan panduan dicatat pada 07.
