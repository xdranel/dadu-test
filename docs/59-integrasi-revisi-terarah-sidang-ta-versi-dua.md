# 59 — Revisi terarah Sidang TA versi2 dari petugas sampai kirim ulang

> Pengguna menerapkan kode di folder asli, menguji, lalu menyalin hasil. Asisten menulis panduan lengkap dengan skill writing-plans dan memeriksa prototype di `/tmp`, bukan mengubah source project. Dasar: rancangan45 dan kontrak58.

**Tujuan:** petugas dapat meminta revisi berdasarkan dokumen yang ditandai; mahasiswa hanya mengganti berkas tersebut; hasil verified lainnya tetap tersimpan ketika revisi dikirim.

**Arsitektur:** kontrak58 dipakai pada request/service, perhitungan tahap, controller/view, dan finalizer. Guard ownership/permission serta lock Student → draft → source tetap dipertahankan. Revisi mempertahankan persyaratan EPRT dan snapshot historis pengajuan asal; lima gate akademik dibaca ulang saat dikirim.

**Teknologi:** Laravel/Pest, Blade/Alpine existing, MySQL Podman; tanpa dependency baru.

## 1. Rescan dan hasil yang akan aktif

**Koreksi setelah laporan3failed:** bagian selector yang sebelumnya hanya satu baris sudah diganti dengan seluruh test yang benar. Selector panel alasan revisi diseragamkan menjadi `data-ta-revision-blockers` pada Blade dan test. Untuk kode59 yang sudah diterapkan, gunakan bagian11 di akhir dokumen; command salin18file ada pada bagian9.

Kode Sidang TA yang diperiksa sudah sama antara folder asli dan `dadu-test`. Helper kontrak, preview gambar, serta perbaikan textarea dipertahankan.

Setelah seluruh59diterapkan:

- Petugas versi2 submitted dapat Minta Revisi setelah minimal satu dokumen rejected dan seluruh dokumen wajib lainnya verified serta filenya ada. Catatan umum masih wajib untuk keputusan Minta Revisi seperti form sebelumnya.
- Menandai dokumen Minta Perbaikan saja tetap tidak mengubah submitted. Keputusan Minta Revisi mengubahnya menjadi needs_review dan menaikkan versi/log satu kali.
- Saat needs_review, semua mutasi petugas tetap terkunci. Preview tersedia.
- Mahasiswa memulai draft revisi dari pengajuan yang sama. Seluruh berkas disalin sebagai referensi, termasuk Surat Permakluman bila berlaku.
- Hanya berkas rejected yang memiliki input upload. Card lainnya abu-abu, tanpa input/dropzone aktif. Preview contoh tetap dapat diklik.
- Periode, kontak, tanggal pembayaran, dan pilihan biodata mengikuti pengajuan asal. Percobaan perubahan melalui HTTP juga ditolak server.
- Berkas rejected yang masih meminjam file lama belum dianggap selesai. Setelah pengganti disimpan, baru tahap tersebut lengkap. Menyimpan satu dari dua berkas revisi tetap diperbolehkan, tetapi Tinjau menunggu seluruhnya.
- Kirim revisi mengubah hanya pengganti menjadi pending dan menghapus catatan dokumen itu. Berkas yang tidak direvisi tetap verified, mempertahankan ID, metadata, dan file.
- Replay pengiriman tidak menambah log atau menimpa keputusan petugas terbaru. File lama baru dibersihkan setelah transaksi finalisasi berhasil dan tidak direferensikan lagi.

**Batas fase:** deadline/penolakan otomatis belum diaktifkan pada59. Pengaturan waktu revisi masih mengikuti perilaku sebelumnya sampai checkpoint60 menambahkan expiry serta write guard. Gunakan periode yang masih sesuai untuk manual ini. Pembuatan pendaftaran baru biasa masih versi1; aktivasi defaultversi2 dilakukan setelah alur revisi dan deadline lengkap. Tidak perlu mengubah draft Krisna menjadi versi2 atau import JSON baru.

## 2. File dan urutan kerja

Kerjakan di `/home/xdranel/Code/Project/php-laravel/dadu/`.

```bash
php artisan make:test SidangTaTargetedRevisionTest --pest
```

Ini satu-satunya file baru. Semua class/partial/kontrak lain sudah ada. Tidak ada migrasi atau seeder; tidak perlu migrate:fresh.

- [ ] Isi file test baru pada bagian3, jalankan sebelum implementasi untuk melihat kegagalan jalur yang belum dibuka.
- [ ] Perbarui test existing bagian4 yang sebelumnya mengharapkan penahanan sementara atau memakai fixture metadata-only.
- [ ] Terapkan **seluruh** backend dan UI bagian5–6. Jangan membuka satu tombol saja sebelum aturan upload/finalizer ikut diterapkan.
- [ ] Jalankan seluruh verifikasi bagian7 dan manual bagian8.
- [ ] Salin18file bagian9 setelah lulus.

Fokus pengujian: request stale, ownership, penyimpanan parsial pengganti, usaha mengganti berkas verified/scalar, perubahan mode EPRT selama revisi, file hilang, discard, snapshot historis, status verified yang dipertahankan, dan retry sesudah keputusan. Semua diuji pada file baru.

## 3. Test baru lengkap —19kasus

Seluruh kode berikut masuk ke **`tests/Feature/SidangTaTargetedRevisionTest.php`**, bukan file WizardUi/MeetUi. File ini mempunyai fixture/helper sendiri dan memakai importer profil dummy existing agar lima gate terpenuhi secara realistis.

### `tests/Feature/SidangTaTargetedRevisionTest.php`

Ganti seluruh isi file berikut.

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
    $this->user = User::factory()->create(['username' => '1202299059', 'email' => 'targeted-revision@test.local',
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
    Course::create(['course_code' => 'MEET-REV59', 'name' => 'TUGAS AKHIR 2', 'academic_year' => '2026/2027',
        'semester_term' => 'GANJIL', 'study_program' => 'Informatika', 'class' => 'A', 'semester' => 8, 'credits' => 4]);
    StudentCourse::create(['student_number' => $this->student->student_number, 'course_code' => 'MEET-REV59',
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
    $this->submittedSource = function (string $mode = 'waiver', bool $accurate = true) {
        $this->actingAs($this->user);
        $draft = ($this->markReviewed)(($this->meetDraft)($mode, $accurate));
        $id = ($this->submitMeet)($draft)->assertOk()->json('data.id');
        return SidangTaRegistration::findOrFail($id);
    };
    $this->prepareRevision = function ($source, array $rejected = ['ta_data_capture']) {
        $this->reviewer = ($this->asReviewer)();
        foreach ($source->documents()->get() as $document) {
            $needsFix = in_array($document->document_type, $rejected, true);
            $this->postJson(route('admin.sidang-ta-registrations.documents.review', [
                'sidang_ta_registration' => $source, 'document' => $document,
            ]), ['registration_version' => $source->fresh()->lock_version,
                'status' => $needsFix ? 'rejected' : 'verified', 'notes' => $needsFix ? 'Perbaiki '.$document->document_type : null])->assertOk();
        }
        $this->postJson(route('admin.sidang-ta-registrations.review', $source), [
            'registration_version' => $source->fresh()->lock_version, 'action' => 'needs_review',
            'review_notes' => 'Perbaiki dokumen yang ditandai.',
        ])->assertRedirect();
        $this->actingAs($this->user);
        $id = $this->postJson(route('sidang-ta-drafts.revision', $source))->assertCreated()->json('data.id');
        return SidangTaRegistrationDraft::findOrFail($id);
    };
    $this->replaceDocument = function ($draft, string $type = 'ta_data_capture', int $step = 2, array $extra = []) {
        return $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => $step]),
            array_replace(['version' => $draft->fresh()->version,
                'documents' => [$type => UploadedFile::fake()->create('pengganti.pdf', 100, 'application/pdf')]], $extra));
    };
    $this->submitRevision = function ($source, $draft) {
        return $this->putJson(route('sidang-ta-registrations.update', $source), [
            'draft_id' => $draft->id, 'version' => $draft->fresh()->version, 'action' => 'submit',
        ]);
    };
});

afterEach(function () { $this->travelBack(); });

test('targeted_revision_request_changes_state_once_and_locks_staff_while_student_works', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source);
    expect($source->fresh()->status)->toBe('needs_review');
    expect($draft->requirements_version)->toBe(2);
    expect($draft->source_version)->toBe($source->fresh()->lock_version);
    expect($draft->documents()->count())->toBe(8);
    expect($draft->documents()->whereNotNull('source_document_id')->count())->toBe(8);
    $this->actingAs($this->reviewer);
    $doc = $source->documents()->firstOrFail();
    $version = $source->fresh()->lock_version;
    $this->postJson(route('admin.sidang-ta-registrations.documents.review', [
        'sidang_ta_registration' => $source, 'document' => $doc,
    ]), ['registration_version' => $version, 'status' => 'verified'])->assertUnprocessable();
    expect($source->fresh()->lock_version)->toBe($version);
    $crawler = new Crawler($this->get(route('admin.sidang-ta-registrations.show', $source))->assertOk()->getContent());
    expect($crawler->filter('[data-doc-review-form], [data-ta-decision]')->count())->toBe(0);
    expect($crawler->filter('a[data-document-preview]')->count())->toBe(8);
});

test('targeted_revision_borrowed_rejected_file_keeps_stage_and_review_incomplete', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source);
    $progress = \App\Support\SidangTaDocumentRequirements::progress($draft, 2);
    expect($progress['complete'])->toBeFalse();
    expect($progress['stored_documents'])->toBe(1);
    $this->postJson(route('sidang-ta-drafts.review', $draft), ['version' => $draft->version])
        ->assertUnprocessable()->assertJsonValidationErrors('draft');
    $this->get(route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 3]))
        ->assertRedirect(route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 2]));
});

test('targeted_revision_only_requested_file_can_be_saved_and_verified_file_is_preserved', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source);
    $paths = Storage::disk('local')->allFiles();
    ($this->replaceDocument)($draft, 'online_supervision_capture')->assertUnprocessable()->assertJsonValidationErrors('documents');
    expect(Storage::disk('local')->allFiles())->toBe($paths);
    ($this->replaceDocument)($draft)->assertOk();
    expect($draft->fresh()->version)->toBe(2);
    expect($draft->documents()->where('document_type', 'ta_data_capture')->firstOrFail()->source_document_id)->toBeNull();
    expect($draft->documents()->where('document_type', 'online_supervision_capture')->firstOrFail()->source_document_id)->not->toBeNull();
    expect($source->documents()->where('document_type', 'ta_data_capture')->firstOrFail()->status)->toBe('rejected');
});

test('targeted_revision_freezes_payment_date_biodata_contacts_and_period_on_server', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source, ['graduation_payment_proof', 'diploma_statement']);
    $payload = $draft->payload;
    $paths = Storage::disk('local')->allFiles();
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 1]), [
        'version' => $draft->version, 'sidang_ta_period_id' => $source->sidang_ta_period_id,
        'student_phone' => '089999999999', 'parent_phone' => $payload['parent_phone'],
    ])->assertUnprocessable()->assertJsonValidationErrors('student_phone');
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 3]), [
        'version' => $draft->version, 'graduation_payment_date' => today()->subDay()->toDateString(),
    ])->assertUnprocessable()->assertJsonValidationErrors('graduation_payment_date');
    $otherPeriod = SidangTaPeriod::create(['name' => 'Other', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay()]);
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 1]), [
        'version' => $draft->version, 'sidang_ta_period_id' => $otherPeriod->id,
        'student_phone' => $payload['student_phone'], 'parent_phone' => $payload['parent_phone'],
    ])->assertUnprocessable()->assertJsonValidationErrors('sidang_ta_period_id');
    expect($draft->fresh()->version)->toBe(1);
    expect(Storage::disk('local')->allFiles())->toBe($paths);
    ($this->replaceDocument)($draft, 'graduation_payment_proof', 3)->assertOk();
    $paths = Storage::disk('local')->allFiles();
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 4]), [
        'version' => $draft->fresh()->version, 'diploma_biodata_accurate' => false,
    ])->assertUnprocessable()->assertJsonValidationErrors('diploma_biodata_accurate');
    expect($draft->fresh()->payload)->toBe($payload);
    expect($draft->fresh()->version)->toBe(2);
    expect(Storage::disk('local')->allFiles())->toBe($paths);
});

test('targeted_revision_resubmission_changes_only_requested_file_and_keeps_verified_rows', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source);
    $snapshot = $source->fresh()->academic_snapshot;
    $original = $source->documents()->get()->keyBy('document_type');
    ($this->replaceDocument)($draft)->assertOk();
    $newPath = $draft->documents()->where('document_type', 'ta_data_capture')->firstOrFail()->file_path;
    ($this->submitRevision)($source, $draft)->assertConflict();
    $draft = ($this->markReviewed)($draft);
    ($this->submitRevision)($source, $draft)->assertOk()->assertJsonPath('data.status', 'submitted');
    expect($draft->fresh()->status)->toBe('consumed');
    expect($draft->documents()->count())->toBe(0);
    expect($source->documents()->where('status', 'pending')->count())->toBe(1);
    foreach ($source->documents()->get() as $document) {
        if ($document->document_type === 'ta_data_capture') {
            expect($document->file_path)->toBe($newPath);
            expect($document->notes)->toBeNull();
        } else {
            expect($document->status)->toBe('verified');
            expect($document->file_path)->toBe($original[$document->document_type]->file_path);
            expect($document->id)->toBe($original[$document->document_type]->id);
        }
    }
    expect($source->fresh()->academic_snapshot)->toBe($snapshot);
    Storage::disk('local')->assertMissing($original['ta_data_capture']->file_path);
    Storage::disk('local')->assertExists($original['eprt_waiver_letter']->file_path);
    $this->actingAs($this->reviewer);
    $crawler = new Crawler($this->get(route('admin.sidang-ta-registrations.index'))->assertOk()->getContent());
    $row = $crawler->filter('[data-ta-row="'.$source->id.'"]');
    expect($row->filter('[data-ta-doc="ta_data_capture"]')->attr('data-doc-state'))->toBe('pending');
    expect($row->filter('[data-ta-doc="eprt_waiver_letter"]')->attr('data-doc-state'))->toBe('verified');
});

test('targeted_revision_rechecks_five_academic_gates_without_losing_replacement', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source);
    ($this->replaceDocument)($draft)->assertOk();
    $draft = ($this->markReviewed)($draft);
    $paths = Storage::disk('local')->allFiles();
    StudentTak::where('student_number', $this->student->student_number)->update(['total_points' => 59]);
    ($this->submitRevision)($source, $draft)->assertUnprocessable()->assertJsonValidationErrors('eligibility.tak_score');
    expect($source->fresh()->status)->toBe('needs_review');
    expect($draft->fresh()->status)->toBe('active');
    expect(Storage::disk('local')->allFiles())->toBe($paths);
    StudentTak::where('student_number', $this->student->student_number)->update(['total_points' => 60]);
    ($this->submitRevision)($source, $draft)->assertOk();
});

test('targeted_revision_preserves_historical_eprt_contract_when_live_mode_changes', function (string $originalMode, string $liveMode) {
    $source = ($this->submittedSource)($originalMode);
    $draft = ($this->prepareRevision)($source);
    $snapshot = $source->fresh()->academic_snapshot;
    ($this->setMode)($liveMode);
    expect(\App\Support\SidangTaDocumentRequirements::typesForDraftStep($draft, 3))
        ->toBe(\App\Support\SidangTaMeetRequirements::typesForStep(3, true, $originalMode));
    ($this->replaceDocument)($draft)->assertOk();
    $draft = ($this->markReviewed)($draft);
    ($this->submitRevision)($source, $draft)->assertOk();
    expect($source->fresh()->academic_snapshot)->toBe($snapshot);
    expect($source->documents()->count())->toBe($originalMode === 'waiver' ? 8 : 7);
})->with([['waiver', 'automatic'], ['automatic', 'waiver'], ['waiver', 'unknown']]);

test('targeted_revision_replaces_requested_waiver_in_stage_three_with_correct_feedback', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source, [Meet::WAIVER]);
    $crawler = new Crawler($this->get(route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 3]))->assertOk()->getContent());
    expect($crawler->filter('[data-feedback-type="eprt_waiver_letter"]')->text(null, true))->toContain('Tahap 3');
    expect($crawler->filter('[data-upload-type="eprt_waiver_letter"] input[type="file"]')->count())->toBe(1);
    ($this->replaceDocument)($draft, Meet::WAIVER, 3)->assertOk();
    $draft = ($this->markReviewed)($draft);
    ($this->submitRevision)($source, $draft)->assertOk();
    expect($source->documents()->where('document_type', Meet::WAIVER)->firstOrFail()->status)->toBe('pending');
    expect($source->documents()->where('status', 'verified')->count())->toBe(7);
});

test('targeted_revision_pdm_branch_stays_locked_and_only_requested_proof_is_replaceable', function () {
    $source = ($this->submittedSource)('automatic', false);
    $draft = ($this->prepareRevision)($source, ['pdm_submission_proof']);
    ($this->replaceDocument)($draft, 'diploma_statement', 4)->assertUnprocessable()->assertJsonValidationErrors('documents');
    ($this->replaceDocument)($draft, 'pdm_submission_proof', 4)->assertOk();
    $draft = ($this->markReviewed)($draft);
    ($this->submitRevision)($source, $draft)->assertOk();
    expect($source->documents()->count())->toBe(6);
    expect($source->documents()->whereIn('document_type', Meet::DIPLOMA)->exists())->toBeFalse();
});

test('targeted_revision_stale_upload_does_not_store_file_or_overwrite_saved_replacement', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source);
    ($this->replaceDocument)($draft)->assertOk();
    $paths = Storage::disk('local')->allFiles();
    ($this->replaceDocument)($draft, 'ta_data_capture', 2, ['version' => 1])->assertConflict();
    expect(Storage::disk('local')->allFiles())->toBe($paths);
    expect($draft->fresh()->version)->toBe(2);
});

test('targeted_revision_stale_source_and_foreign_owner_cannot_mutate_saved_files', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source);
    $paths = Storage::disk('local')->allFiles();
    $source->increment('lock_version');
    ($this->replaceDocument)($draft)->assertConflict();
    $other = User::factory()->create(['status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
    $other->givePermissionTo(['sidang_ta_registration_read', 'sidang_ta_registration_create', 'sidang_ta_registration_update']);
    $this->actingAs($other);
    ($this->replaceDocument)($draft)->assertForbidden();
    expect(Storage::disk('local')->allFiles())->toBe($paths);
});

test('targeted_revision_discard_deletes_owned_replacement_but_keeps_every_final_file', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source);
    $originalPaths = $source->documents()->pluck('file_path')->all();
    ($this->replaceDocument)($draft)->assertOk();
    $owned = $draft->documents()->whereNull('source_document_id')->firstOrFail()->file_path;
    $this->deleteJson(route('sidang-ta-drafts.destroy', $draft), ['version' => $draft->fresh()->version])->assertNoContent();
    Storage::disk('local')->assertMissing($owned);
    foreach ($originalPaths as $path) Storage::disk('local')->assertExists($path);
    expect($source->fresh()->status)->toBe('needs_review');
});

test('targeted_revision_missing_replacement_keeps_source_and_other_files_unchanged', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source);
    ($this->replaceDocument)($draft)->assertOk();
    $draft = ($this->markReviewed)($draft);
    $document = $draft->documents()->where('document_type', 'ta_data_capture')->firstOrFail();
    Storage::disk('local')->delete($document->file_path);
    ($this->submitRevision)($source, $draft)->assertUnprocessable()->assertJsonValidationErrors('documents.ta_data_capture');
    expect($source->fresh()->status)->toBe('needs_review');
    expect($source->documents()->where('status', 'rejected')->count())->toBe(1);
    expect($draft->fresh()->status)->toBe('active');
});

test('targeted_revision_replay_after_later_decision_does_not_change_logs_or_files', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source);
    ($this->replaceDocument)($draft)->assertOk();
    $draft = ($this->markReviewed)($draft);
    ($this->submitRevision)($source, $draft)->assertOk();
    $source->update(['status' => 'approved']); // Controlled fixture: replay must preserve any later decision.
    $logs = $source->logs()->count();
    $paths = Storage::disk('local')->allFiles();
    StudentTak::where('student_number', $this->student->student_number)->update(['total_points' => 0]);
    ($this->submitRevision)($source, $draft)->assertOk()->assertJsonPath('data.status', 'approved');
    expect($source->logs()->count())->toBe($logs);
    expect(Storage::disk('local')->allFiles())->toBe($paths);
});

test('targeted_revision_wizard_keeps_readonly_fields_and_only_requested_upload_input', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source);
    $step1 = new Crawler($this->get(route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 1]))->assertOk()->getContent());
    expect($step1->filter('input[name="student_phone"]')->getNode(0)->hasAttribute('readonly'))->toBeTrue();
    expect($step1->filter('input[name="parent_phone"]')->getNode(0)->hasAttribute('readonly'))->toBeTrue();
    $step2 = new Crawler($this->get(route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 2]))->assertOk()->getContent());
    $locked = $step2->filter('[data-upload-type="online_supervision_capture"]');
    expect($locked->attr('data-upload-locked'))->toBe('true');
    expect($locked->filter('input[type="file"]')->count())->toBe(0);
    expect($locked->filter('[data-upload-guide]')->count())->toBe(1);
    expect($step2->filter('[data-upload-type="ta_data_capture"] input[type="file"]')->count())->toBe(1);
    ($this->replaceDocument)($draft)->assertOk();
    $step3 = new Crawler($this->get(route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 3]))->assertOk()->getContent());
    expect($step3->filter('input[name="graduation_payment_date"]')->getNode(0)->hasAttribute('readonly'))->toBeTrue();
    $step4 = new Crawler($this->get(route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 4]))->assertOk()->getContent());
    expect($step4->filter('input[type="radio"][disabled]')->count())->toBe(2);
    expect($step4->filter('input[type="hidden"][name="diploma_biodata_accurate"]')->attr('value'))->toBe('1');
});

test('targeted_revision_review_can_submit_after_replacement_but_blocks_new_academic_failure', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source);
    ($this->replaceDocument)($draft)->assertOk();
    $draft = ($this->markReviewed)($draft);
    $crawler = new Crawler($this->get(route('sidang-ta-drafts.preview', $draft))->assertOk()->getContent());
    expect($crawler->filter('[data-open-registration]')->getNode(0)->hasAttribute('disabled'))->toBeFalse();
    expect($crawler->filter('[data-confirm-registration]')->getNode(0)->hasAttribute('disabled'))->toBeFalse();
    StudentTak::where('student_number', $this->student->student_number)->update(['total_points' => 59]);
    $crawler = new Crawler($this->get(route('sidang-ta-drafts.preview', $draft))->assertOk()->getContent());
    expect($crawler->filter('[data-open-registration]')->getNode(0)->hasAttribute('disabled'))->toBeTrue();
});

test('targeted_revision_can_save_one_of_two_requested_files_without_unlocking_review', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source, ['ta_data_capture', 'online_supervision_capture']);
    ($this->replaceDocument)($draft)->assertOk();
    $progress = \App\Support\SidangTaDocumentRequirements::progress($draft->fresh(), 2);
    expect($progress['stored_documents'])->toBe(1);
    expect($progress['complete'])->toBeFalse();
    $this->postJson(route('sidang-ta-drafts.review', $draft), ['version' => $draft->fresh()->version])
        ->assertUnprocessable()->assertJsonValidationErrors('draft');
    ($this->replaceDocument)($draft, 'online_supervision_capture')->assertOk();
    $draft = ($this->markReviewed)($draft);
    ($this->submitRevision)($source, $draft)->assertOk();
    expect($source->documents()->where('status', 'pending')->count())->toBe(2);
    expect($source->documents()->where('status', 'verified')->count())->toBe(6);
});
```


Jalankan sebelum kode produksi; kegagalan pada jalur revisi yang masih ditahan merupakan hasil awal yang diharapkan:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaTargetedRevisionTest.php
```
## 4. Perbarui test existing yang kontraknya berubah

Jumlah test lama tetap. Assertion penahanan sementara diganti menjadi pemeriksaan rencana revisi yang sah. Fixture revisi versi2 pada test metadata harus mempunyai snapshot dan dokumen versi2; metadata-only tidak cukup untuk memulai revisi yang aktif.

### `tests/Feature/SidangTaRequirementsVersionTest.php`

Ganti keseluruhan test bernama `meet_revision_inherits_server_requirements_version_instead_of_request_body` dengan berikut:

```php
test('meet_revision_inherits_server_requirements_version_instead_of_request_body', function () {
    $source = ($this->makeSource)(2);
    $source->update(['academic_snapshot' => ['admission' => ['eprt' => ['mode' => 'automatic']]]]);
    foreach (\App\Support\SidangTaReviewEligibility::requiredTypes($source) as $type) {
        $path = $type === 'thesis_draft' ? 'sidang_ta/metadata-original.pdf'
            : 'sidang_ta/metadata-v2/'.$source->id.'/'.$type.'.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 metadata source');
        $source->documents()->create(['document_type' => $type, 'file_name' => $type.'.pdf',
            'file_path' => $path, 'file_type' => 'pdf',
            'status' => $type === 'ta_data_capture' ? 'rejected' : 'verified',
            'notes' => $type === 'ta_data_capture' ? 'Perbaiki capture' : null]);
    }
    $id = $this->postJson(route('sidang-ta-drafts.revision', $source), ['requirements_version' => 1])
        ->assertCreated()->json('data.id');
    $draft = SidangTaRegistrationDraft::findOrFail($id);
    expect($draft->requirements_version)->toBe(2);
    expect($draft->source_version)->toBe($source->lock_version);
    expect($source->fresh()->requirements_version)->toBe(2);
});
```

### `tests/Feature/SidangTaRequirementsVersionTest.php`

Ganti keseluruhan test bernama `meet_mismatched_revision_requirements_version_is_rejected_without_removing_source_file` dengan berikut:

```php
test('meet_mismatched_revision_requirements_version_is_rejected_without_removing_source_file', function () {
    $source = ($this->makeSource)(2);
    $source->update(['academic_snapshot' => ['admission' => ['eprt' => ['mode' => 'automatic']]]]);
    foreach (\App\Support\SidangTaReviewEligibility::requiredTypes($source) as $type) {
        $path = $type === 'thesis_draft' ? 'sidang_ta/metadata-original.pdf'
            : 'sidang_ta/metadata-v2/'.$source->id.'/'.$type.'.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 metadata source');
        $source->documents()->create(['document_type' => $type, 'file_name' => $type.'.pdf',
            'file_path' => $path, 'file_type' => 'pdf',
            'status' => $type === 'ta_data_capture' ? 'rejected' : 'verified',
            'notes' => $type === 'ta_data_capture' ? 'Perbaiki capture' : null]);
    }
    $id = $this->postJson(route('sidang-ta-drafts.revision', $source))->assertCreated()->json('data.id');
    $draft = SidangTaRegistrationDraft::findOrFail($id);
    $draft->update(['requirements_version' => 1]); // Server-side inconsistency, not an HTTP capability.
    $this->getJson(route('sidang-ta-drafts.edit', $draft))->assertConflict();
    expect($source->fresh()->requirements_version)->toBe(2);
    expect($source->documents()->count())->toBe(7);
    Storage::disk('local')->assertExists('sidang_ta/metadata-original.pdf');
});
```

### `tests/Feature/SidangTaMeetFinalizationTest.php`

Ganti keseluruhan test bernama `meet_final_staff_can_read_export_and_verify_while_revision_request_stays_held` dengan berikut:

```php
test('meet_final_staff_can_read_export_and_verify_while_revision_requires_document_feedback', function () {
    $draft = ($this->markReviewed)(($this->meetDraft)('waiver'));
    $id = ($this->submitMeet)($draft)->assertOk()->json('data.id');
    $registration = SidangTaRegistration::findOrFail($id);
    $snapshot = $registration->academic_snapshot;
    ($this->asReviewer)();
    $crawler = new Crawler($this->get(route('admin.sidang-ta-registrations.show', $registration))->assertOk()->getContent());
    expect($crawler->filter('[data-meet-review-held]')->count())->toBe(0);
    expect($crawler->filter('[data-ta-revision-blockers]')->count())->toBe(1);
    expect($crawler->filter('[data-ta-revision-blockers]')->text(null, true))->toContain('Tandai minimal satu dokumen');
    expect($crawler->filter('[data-ta-request-revision]')->getNode(0)->hasAttribute('disabled'))->toBeTrue();
    expect($crawler->filter('[data-doc-review-form]')->count())->toBe(8);
    expect($crawler->filter('form[action="'.route('admin.sidang-ta-registrations.review', $registration).'"]')->count())->toBe(1);
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
    ]), ['registration_version' => $registration->lock_version, 'status' => 'verified'])->assertOk();
    $this->postJson(route('admin.sidang-ta-registrations.review', $registration), [
        'registration_version' => $registration->fresh()->lock_version, 'action' => 'needs_review', 'review_notes' => 'Perbaiki',
    ])->assertUnprocessable()->assertJsonValidationErrors('action');
    expect($waiver->fresh()->status)->toBe('verified');
    expect($registration->fresh()->status)->toBe('submitted');
    expect($registration->fresh()->academic_snapshot)->toBe($snapshot);
    expect($registration->logs()->count())->toBe(2);
});
```

### `tests/Feature/SidangTaMeetFinalizationTest.php`

Ganti keseluruhan test bernama `meet_final_version_two_revision_stays_held_without_changing_final_or_borrowed_files` dengan berikut:

```php
test('meet_final_revision_start_rejects_an_incomplete_review_plan_without_changing_source_files', function () {
    $draft = ($this->markReviewed)(($this->meetDraft)('waiver'));
    $id = ($this->submitMeet)($draft)->assertOk()->json('data.id');
    $registration = SidangTaRegistration::findOrFail($id);
    $registration->update(['status' => 'needs_review']); // Simulate an incomplete server-side review plan.
    $snapshot = $registration->academic_snapshot;
    $paths = Storage::disk('local')->allFiles();
    $this->postJson(route('sidang-ta-drafts.revision', $registration))->assertUnprocessable()
        ->assertJsonValidationErrors('action');
    expect(SidangTaRegistrationDraft::where('source_registration_id', $id)->where('status', 'active')->count())->toBe(0);
    expect($registration->fresh()->status)->toBe('needs_review');
    expect($registration->fresh()->academic_snapshot)->toBe($snapshot);
    expect($registration->documents()->count())->toBe(8);
    expect($registration->documents()->where('status', 'pending')->count())->toBe(8);
    expect($registration->logs()->count())->toBe(1);
    expect(Storage::disk('local')->allFiles())->toBe($paths);
});
```

### `tests/Feature/SidangTaMeetStaffReviewTest.php`

Ganti keseluruhan test bernama `meet_staff_revision_request_is_still_held_without_mutating_any_saved_feedback` dengan berikut:

```php
test('meet_staff_revision_request_requires_review_of_the_remaining_documents', function () {
    $registration = ($this->makeMeetRegistration)();
    $document = $registration->documents()->where('document_type', 'ta_data_capture')->firstOrFail();
    ($this->reviewDocument)($registration, $document, ['status' => 'rejected', 'notes' => 'Perbaiki scan'])->assertOk();
    $response = ($this->decideMeet)($registration, 'needs_review')->assertUnprocessable()
        ->assertJsonValidationErrors(['documents.online_supervision_capture', 'documents.thesis_draft']);
    expect(array_key_exists('action', $response->json('errors')))->toBeFalse();
    expect($registration->fresh()->status)->toBe('submitted');
    expect($registration->fresh()->lock_version)->toBe(2);
    expect($document->fresh()->notes)->toBe('Perbaiki scan');
    expect($registration->logs()->count())->toBe(1);
});
```

### `tests/Feature/SidangTaMeetStaffReviewTest.php`

Ganti keseluruhan test bernama `meet_staff_detail_groups_uploads_into_two_columns_with_status_colors_and_disabled_verify` dengan kode lengkap berikut:

```php
test('meet_staff_detail_groups_uploads_into_two_columns_with_status_colors_and_disabled_verify', function () {
    $registration = ($this->makeMeetRegistration)('waiver');
    $verified = $registration->documents()->where('document_type', 'ta_data_capture')->firstOrFail();
    $rejected = $registration->documents()->where('document_type', 'online_supervision_capture')->firstOrFail();
    $verified->update(['status' => 'verified']);
    $rejected->update(['status' => 'rejected', 'notes' => 'Perbaiki']);
    $crawler = ($this->reviewPage)($registration);
    expect($crawler->filter('[data-meet-document-group]')->count())->toBe(3);
    expect($crawler->filter('[data-meet-document-grid]')->count())->toBe(3);
    foreach ($crawler->filter('[data-meet-document-grid]') as $node) {
        expect($node->getAttribute('class'))->toContain('lg:grid-cols-2');
    }
    expect($crawler->filter('[data-meet-document-group="2"] [data-ta-detail-document]')->count())->toBe(2);
    expect($crawler->filter('[data-meet-document-group="3"] [data-ta-detail-document]')->count())->toBe(4);
    expect($crawler->filter('[data-meet-document-group="4"] [data-ta-detail-document]')->count())->toBe(2);
    $green = $crawler->filter('#review-document-'.$verified->id);
    $yellow = $crawler->filter('#review-document-'.$rejected->id);
    expect($green->attr('class'))->toContain('bg-emerald-50')->toContain('text-emerald-900');
    expect($yellow->attr('class'))->toContain('bg-amber-50')->toContain('text-amber-900');
    expect($green->filter('[data-doc-verify]')->getNode(0)->hasAttribute('disabled'))->toBeTrue();
    expect($green->filter('[data-doc-reject]')->getNode(0)->hasAttribute('disabled'))->toBeFalse();
    expect($yellow->filter('[data-doc-verify]')->getNode(0)->hasAttribute('disabled'))->toBeFalse();
    expect($crawler->filter('[data-ta-request-revision]')->getNode(0)->hasAttribute('disabled'))->toBeTrue();
    expect($crawler->filter('[data-ta-revision-blockers]')->count())->toBe(1);
    expect($crawler->filter('[data-ta-revision-blockers]')->text(null, true))->toContain('Selesaikan verifikasi');
    expect($crawler->filter('[data-ta-review-window]')->text(null, true))->toContain($this->period->close_at->format('d/m/Y H:i'));
    expect($crawler->filter('[data-staff-registration-history]')->getNode(0)->hasAttribute('open'))->toBeFalse();
});
```

Test lain tetap dipertahankan. Dua test RequirementsVersion tetap memeriksa pewarisan versi server dan penolakan mismatch; sekarang sumbernya mempunyai tujuh berkas valid. Test finalisasi invalid-plan memastikan revisi tidak dibuat ketika review dokumen belum lengkap.

## 5. Backend — kode lengkap

Kontrak58 sudah ada; jangan membuat salinan class baru. Service menegakkan aturan di bawah lock, bukan hanya mengandalkan input readonly atau FormRequest.

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
        abort_unless(in_array($locked->requirements_version, [1, 2], true), 409,
            'Versi persyaratan pengajuan tidak dikenali.');
        $allowed = $locked->requirements_version === 2 ? ['submitted'] : ['submitted', 'needs_review'];
        abort_unless(in_array($locked->status, $allowed, true), 422,
            'Peninjauan terkunci. Pengajuan sedang direvisi atau sudah diputuskan.');
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
            $isMeet = $locked->requirements_version === 2;
            if ($isMeet && $status === 'verified') $notes = null;
            $nextStatus = !$isMeet && $status === 'rejected' ? 'needs_review' : $locked->status;
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
            if ($locked->requirements_version === 2 && $action === 'needs_review') {
                \App\Support\SidangTaRevisionContract::assertCanRequest($locked);
            }
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

### `app/Services/SidangTaRevisionDraftService.php`

Ganti seluruh isi file berikut.

```php
<?php

namespace App\Services;

use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDraft;
use App\Models\Student;
use App\Models\User;
use App\Support\SidangTaDocumentRequirements as Requirements;
use App\Support\SidangTaRevisionContract as Revision;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SidangTaRevisionDraftService
{
    public function __construct(private SidangTaStudentContext $context,
                                private SidangTaDraftAccess $access) {}

    public function start(User $user, SidangTaRegistration $registration): SidangTaRegistrationDraft
    {
        abort_unless($user->can('sidang_ta_registration_update'), 403);
        $student = $this->context->studentFor($user);
        abort_unless($registration->student_number === $student->student_number, 403);

        return DB::transaction(function () use ($user, $registration, $student) {
            Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $existing = SidangTaRegistrationDraft::where('source_registration_id', $registration->id)
                ->where('status', SidangTaRegistrationDraft::STATUS_ACTIVE)->lockForUpdate()->first();
            $source = SidangTaRegistration::whereKey($registration->id)->lockForUpdate()->firstOrFail();
            abort_unless($source->student_number === $student->student_number, 403);
            abort_unless($source->status === 'needs_review', 409, 'Pengajuan tidak sedang membutuhkan revisi.');
            if ($source->requirements_version === 2) Revision::assertCanRequest($source);
            if ($existing) {
                abort_unless((string) $existing->owner_user_id === (string) $user->id, 409);
                $this->access->check($user, $existing, true);
                return $existing;
            }

            $draft = SidangTaRegistrationDraft::create([
                'owner_user_id' => $user->id, 'student_number' => $student->student_number,
                'sidang_ta_period_id' => $source->sidang_ta_period_id,
                'source_registration_id' => $source->id,
                'source_version' => $source->lock_version,
                'requirements_version' => $source->requirements_version,
                'payload' => [
                    'student_phone' => $source->student_phone, 'parent_phone' => $source->parent_phone,
                    'graduation_payment_date' => $source->graduation_payment_date?->toDateString(),
                    'diploma_biodata_accurate' => $source->diploma_biodata_accurate,
                ],
            ]);
            $types = $source->requirements_version === 2
                ? \App\Support\SidangTaReviewEligibility::requiredTypes($source)
                : array_merge(Requirements::CAPTURES, Requirements::PAYMENT,
                    Requirements::typesForStep(4, $source->diploma_biodata_accurate));
            foreach ($source->documents()->whereIn('document_type', $types)->get() as $document) {
                $draft->documents()->create([
                    'document_type' => $document->document_type, 'file_name' => $document->file_name,
                    'file_path' => $document->file_path, 'file_size' => $document->file_size,
                    'file_type' => $document->file_type, 'source_document_id' => $document->id,
                ]);
            }
            return $draft;
        });
    }

    public function discard(User $user, SidangTaRegistrationDraft $draft, int $version): void
    {
        $student = $this->access->owner($user, $draft);
        $this->access->assertCanWrite($user, $draft);
        $paths = DB::transaction(function () use ($user, $draft, $version, $student) {
            Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $locked = SidangTaRegistrationDraft::whereKey($draft->id)->lockForUpdate()->firstOrFail();
            $this->access->owner($user, $locked);
            $this->access->assertCanWrite($user, $locked);
            abort_unless($locked->status === SidangTaRegistrationDraft::STATUS_ACTIVE
                && $locked->version === $version, 409);
            // Hanya file milik draft, bukan file final yang dipinjam.
            $paths = $locked->documents()->whereNull('source_document_id')->pluck('file_path')->all();
            $locked->delete();
            return $paths;
        });
        foreach (array_unique($paths) as $path) {
            try {
                if (!Storage::disk('local')->delete($path)) {
                    Log::warning('File draft terhapus metadata tetapi perlu cleanup.', ['path' => $path]);
                }
            } catch (Throwable $exception) {
                Log::warning('Cleanup discard gagal.', ['path' => $path, 'error' => $exception->getMessage()]);
            }
        }
    }
}
```

### `app/Support/SidangTaDocumentRequirements.php`

Ganti seluruh isi file berikut.

```php
<?php

namespace App\Support;

use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDraft;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class SidangTaDocumentRequirements
{
    public const CAPTURES = [
        'level_2_result_capture', 'igracias_approval_capture', 'ta_progress_capture',
        'ta_data_capture', 'online_supervision_capture', 'skpi_tak_capture',
    ];
    public const PAYMENT = [
        'graduation_payment_proof', 'bpp_payment_proof', 'ta_supervisor_sk',
        'eprt_lac_certificate', 'thesis_draft', 'transcript_khs', 'ta_course_ksm',
    ];
    public const DIPLOMA = ['diploma_statement', 'diploma_biodata_form'];
    public const PDM = ['pdm_submission_proof'];

    public static function typesForStep(int $step, ?bool $accurate = null): array
    {
        return match ($step) {
            2 => self::CAPTURES,
            3 => self::PAYMENT,
            4 => $accurate === null ? [] : ($accurate ? self::DIPLOMA : self::PDM),
            default => [],
        };
    }

    public static function assertKnownVersion(SidangTaRegistrationDraft $draft): void
    {
        abort_unless(in_array($draft->requirements_version, [1, 2], true), 409,
            'Versi persyaratan draft tidak dikenali. Muat ulang pengajuan.');
    }

    public static function typesForDraftStep(SidangTaRegistrationDraft $draft, int $step, ?array $payload = null): array
    {
        self::assertKnownVersion($draft);
        $payload ??= $draft->payload ?? [];
        $choice = $payload['diploma_biodata_accurate'] ?? null;
        $accurate = $choice === null ? null : (bool) $choice;
        if ($draft->requirements_version === 1) return self::typesForStep($step, $accurate);
        if (SidangTaRevisionContract::isTargeted($draft)) return SidangTaRevisionContract::typesForStep($draft, $step);
        $mode = 'unknown';
        if ($step === 3) {
            $student = \App\Models\Student::where('student_number', $draft->student_number)->firstOrFail();
            $mode = app(\App\Services\SidangTaAdmissionFacts::class)->forStudent($student)['eprt']['mode'];
        }
        return SidangTaMeetRequirements::typesForStep($step, $accurate, $mode);
    }

    public static function fileRulesForDraft(SidangTaRegistrationDraft $draft, string $type): array
    {
        self::assertKnownVersion($draft);
        return $draft->requirements_version === 2 ? SidangTaMeetRequirements::fileRules($type) : self::fileRules($type);
    }

    // Syarat lengkap: dipakai navigasi, tinjau dan finalisasi.
    public static function scalarRules(int $step): array
    {
        return match ($step) {
            1 => ['student_phone' => 'required|string|max:30', 'parent_phone' => 'required|string|max:30'],
            3 => ['graduation_payment_date' => 'required|date|before_or_equal:today'],
            4 => ['diploma_biodata_accurate' => 'required|boolean'],
            default => [],
        };
    }

    // Syarat save: field belum ada/null boleh, tetapi nilai yang diisi tetap valid.
    public static function draftScalarRules(int $step): array
    {
        return match ($step) {
            3 => ['graduation_payment_date' => 'sometimes|nullable|date|before_or_equal:today'],
            4 => ['diploma_biodata_accurate' => 'sometimes|nullable|boolean'],
            default => self::scalarRules($step),
        };
    }

    public static function fileRules(string $type): array
    {
        return ['nullable', 'file', 'max:10240',
            in_array($type, self::CAPTURES, true) ? 'mimes:pdf,jpg,jpeg,png' : 'mimes:pdf'];
    }

    public static function progress(SidangTaRegistrationDraft $draft, int $step): array
    {
        $payload = $draft->payload ?? [];
        $labels = ['student_phone' => 'Nomor HP mahasiswa', 'parent_phone' => 'Nomor HP orang tua/wali',
            'graduation_payment_date' => 'Tanggal pembayaran wisuda',
            'diploma_biodata_accurate' => 'Pilihan kesesuaian biodata ijazah'];
        $validator = Validator::make($payload, self::scalarRules($step));
        $validator->passes();
        $missing = [];
        foreach (array_keys($validator->failed()) as $key) {
            $missing[] = $labels[$key] ?? $key;
        }
        $choice = $payload['diploma_biodata_accurate'] ?? null;
        $accurate = $choice === null ? null : (bool) $choice;
        $types = self::typesForDraftStep($draft, $step, $payload);
        $draft->loadMissing('documents');
        $stored = 0;
        $editable = SidangTaRevisionContract::isTargeted($draft) ? SidangTaRevisionContract::editableTypes($draft) : [];
        foreach ($types as $type) {
            $document = $draft->documents->firstWhere('document_type', $type);
            if ($document && Storage::disk('local')->exists($document->file_path)
                && !(in_array($type, $editable, true) && $document->source_document_id !== null)) {
                $stored++;
            } else {
                $missing[] = SidangTaMeetRequirements::LABELS[$type] ?? SidangTaRegistration::DOCUMENT_TYPES[$type] ?? $type;
            }
        }
        return [
            'complete' => $missing === [],
            'stored_documents' => $stored,
            'required_documents' => $step === 4 && $accurate === null ? null : count($types),
            'missing' => $missing,
        ];
    }

    public static function complete(SidangTaRegistrationDraft $draft, int $step): bool
    {
        return self::progress($draft, $step)['complete'];
    }

    public static function firstIncompleteStep(SidangTaRegistrationDraft $draft): ?int
    {
        foreach (range(1, 4) as $step) {
            if (!self::complete($draft, $step)) return $step;
        }
        return null;
    }
}
```

### `app/Http/Requests/SaveSidangTaDraftStepRequest.php`

Ganti seluruh isi file berikut.

```php
<?php

namespace App\Http\Requests;

use App\Models\SidangTaRegistration;
use App\Support\SidangTaMeetRequirements;
use Illuminate\Contracts\Validation\ValidationRule;
use App\Support\SidangTaDocumentRequirements as Requirements;
use App\Support\SidangTaRevisionContract as Revision;
use Illuminate\Foundation\Http\FormRequest;

class SaveSidangTaDraftStepRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $draft = $this->route('draft');

        $permission = $draft && $draft->source_registration_id !== null
            ? 'sidang_ta_registration_update'
            : 'sidang_ta_registration_create';

        return $user && $user->can($permission)
            && (!$draft || (string) $draft->owner_user_id === (string) $user->id);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $step = (int) ($this->route('step') ?? 1);
        $draft = $this->route('draft');
        if ($draft) Requirements::assertKnownVersion($draft);
        $rules = ['version' => [$this->route('draft')
            ? 'required'
            : 'nullable', 'integer', 'min:1']];
        foreach (['requirements_version', 'owner_user_id', 'student_number', 'status', 'payload', 'source_registration_id',
                     'source_version', 'reviewed_version', 'submitted_registration_id', 'file_path',
                     'sidang_ta_period_id', 'student_phone', 'parent_phone', 'graduation_payment_date',
                     'diploma_biodata_accurate'] as $key) {
            $rules[$key] = ['prohibited'];
        }
        $rules = array_replace($rules, Requirements::draftScalarRules($step));
        if ($step === 1) {
            $rules['sidang_ta_period_id'] = ['required', 'integer', 'exists:sidang_ta_periods,id'];
            $rules['documents'] = ['prohibited'];
            return $rules;
        }

        // Tahap 4 boleh menerima scalar yang belum ada. Cabang aktual divalidasi lagi
        // dalam service setelah merge payload dan version check di bawah lock.
        if ($draft && $draft->requirements_version === 2) {
            $types = $step === 4
                ? array_merge(SidangTaMeetRequirements::DIPLOMA, SidangTaMeetRequirements::PDM)
                : Requirements::typesForDraftStep($draft, $step);
        } else {
            $types = $step === 4 ? array_merge(Requirements::DIPLOMA, Requirements::PDM)
                : Requirements::typesForStep($step);
        }
        if ($draft && Revision::isTargeted($draft)) {
            $types = array_values(array_intersect(Requirements::typesForDraftStep($draft, $step), Revision::editableTypes($draft)));
        }
        $rules['documents'] = $types ? ['nullable', 'array:'.implode(',', $types)] : ['prohibited'];
        foreach ($types as $type) {
            $rules["documents.{$type}"] = $draft ? Requirements::fileRulesForDraft($draft, $type) : Requirements::fileRules($type);
        }
        return $rules;
    }

    public function messages(): array
    {
        return [
            'sidang_ta_period_id.required' => 'Pilih periode sidang TA.',
            'sidang_ta_period_id.integer' => 'Pilih periode sidang TA yang valid.',
            'sidang_ta_period_id.exists' => 'Periode sidang TA tidak ditemukan.',
            'student_phone.required' => 'Nomor HP mahasiswa wajib diisi.',
            'parent_phone.required' => 'Nomor HP orang tua/wali wajib diisi.',
            'student_phone.string' => 'Nomor HP mahasiswa harus berupa teks.',
            'parent_phone.string' => 'Nomor HP orang tua/wali harus berupa teks.',
            'student_phone.max' => 'Nomor HP mahasiswa maksimal 30 karakter.',
            'parent_phone.max' => 'Nomor HP orang tua/wali maksimal 30 karakter.',
            'graduation_payment_date.required' => 'Tanggal pembayaran wisuda wajib diisi.',
            'graduation_payment_date.date' => 'Tanggal pembayaran wisuda tidak valid.',
            'graduation_payment_date.before_or_equal' => 'Tanggal pembayaran wisuda tidak boleh melewati hari ini.',
            'diploma_biodata_accurate.required' => 'Pilih kesesuaian biodata dummy ijazah.',
            'diploma_biodata_accurate.boolean' => 'Pilihan kesesuaian biodata tidak valid.',
            'version.required' => 'Versi draft tidak tersedia. Buka ulang draft terbaru.',
            'version.integer' => 'Versi draft tidak valid. Buka ulang draft terbaru.',
            'version.min' => 'Versi draft tidak valid. Buka ulang draft terbaru.',
            'documents.array' => 'Jenis dokumen tidak sesuai tahap atau cabang yang dipilih.',
            'documents.*.file' => 'Pilih berkas yang valid.',
            'documents.*.uploaded' => 'Berkas gagal diunggah. Periksa ukuran berkas dan coba lagi.',
            'documents.*.max' => 'Ukuran setiap file maksimal 10 MB.',
            'documents.*.mimes' => 'Format dokumen tidak sesuai persyaratan tahap ini.',
            'prohibited' => 'Isian :attribute tidak diperbolehkan pada tahap ini.',
        ];
    }

    public function attributes(): array
    {
        $attributes = [
            'sidang_ta_period_id' => 'periode sidang TA',
            'student_phone' => 'nomor HP mahasiswa',
            'parent_phone' => 'nomor HP orang tua/wali',
            'graduation_payment_date' => 'tanggal pembayaran wisuda',
            'diploma_biodata_accurate' => 'kesesuaian biodata ijazah',
            'documents' => 'dokumen', 'version' => 'versi draft',
        ];
        foreach (array_merge(SidangTaRegistration::DOCUMENT_TYPES,
            SidangTaMeetRequirements::LABELS) as $type => $label) {
            $attributes["documents.{$type}"] = $label;
        }
        return $attributes;
    }
}
```

### `app/Services/SidangTaDraftDocumentService.php`

Ganti seluruh isi file berikut.

```php
<?php

namespace App\Services;

use App\Models\SidangTaRegistrationDraft;
use App\Models\Student;
use App\Models\User;
use App\Support\SidangTaDocumentRequirements as Requirements;
use App\Support\SidangTaRevisionContract as Revision;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SidangTaDraftDocumentService
{
    public function __construct(private SidangTaStudentContext $context,
                                private SidangTaDraftAccess $access
    ) {}

    private function clean(array $paths): void
    {
        foreach (array_unique($paths) as $path) {
            try {
                if (!Storage::disk('local')->delete($path)) {
                    Log::warning('Cleanup file draft belum berhasil.', ['path' => $path]);
                }
            } catch (Throwable $exception) {
                Log::warning('Cleanup file draft gagal.', ['path' => $path, 'error' => $exception->getMessage()]);
            }
        }
    }

    public function save(User $user, SidangTaRegistrationDraft $draft, int $step,
                         int $expectedVersion, array $validated): SidangTaRegistrationDraft
    {
        $this->access->assertCanWrite($user, $draft);
        abort_unless(in_array($step, [2, 3, 4], true), 422);
        abort_unless((string) $draft->owner_user_id === (string) $user->id, 403);
        $student = $this->context->studentFor($user);
        $newPaths = [];
        $retiredPaths = [];
        try {
            $result = DB::transaction(function () use ($user, $draft, $step, $expectedVersion,
                $validated, $student, &$newPaths, &$retiredPaths) {
                Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
                $locked = SidangTaRegistrationDraft::whereKey($draft->id)->lockForUpdate()->firstOrFail();
                $this->access->check($user, $locked, true);
                abort_unless($locked->version === $expectedVersion, 409, 'Draft berubah. Muat ulang.');
                $first = Requirements::firstIncompleteStep($locked);
                abort_if($first !== null && $step > $first, 409, 'Lengkapi tahap sebelumnya dahulu.');

                $payload = array_replace($locked->payload ?? [],
                    array_intersect_key($validated, Requirements::draftScalarRules($step)));
                Validator::make($payload, Requirements::draftScalarRules($step))->validate();
                $targeted = Revision::isTargeted($locked);
                if ($targeted) Revision::assertPayloadUnchanged($locked, $payload);
                if ($step === 4 && array_key_exists('diploma_biodata_accurate', $payload)) {
                    $choice = $payload['diploma_biodata_accurate'];
                    $payload['diploma_biodata_accurate'] = $choice === null ? null : (bool) $choice;
                }
                $accurate = $payload['diploma_biodata_accurate'] ?? null;
                $types = Requirements::typesForDraftStep($locked, $step, $payload);
                $allowedTypes = $targeted ? array_values(array_intersect($types, Revision::editableTypes($locked))) : $types;
                $files = $validated['documents'] ?? [];
                $fileRules = ['documents' => $allowedTypes ? ['array:'.implode(',', $allowedTypes)] : ['prohibited']];
                foreach ($allowedTypes as $type) $fileRules["documents.{$type}"] = Requirements::fileRulesForDraft($locked, $type);
                Validator::make(['documents' => $files], $fileRules)->validate();

                foreach ($types as $type) {
                    $file = $files[$type] ?? null;
                    if (!$file instanceof UploadedFile) continue;
                    $old = $locked->documents()->where('document_type', $type)->first();
                    $directory = "sidang_ta/drafts/{$locked->id}";
                    $name = Str::uuid().'.'.$file->guessExtension();
                    $newPaths[] = "{$directory}/{$name}";
                    $path = $file->storeAs($directory, $name, 'local');
                    if (!$path) throw new RuntimeException('Penyimpanan file draft gagal. Silakan coba lagi.');
                    $locked->documents()->updateOrCreate(['document_type' => $type], [
                        'file_name' => $file->getClientOriginalName(), 'file_path' => $path,
                        'file_size' => $file->getSize(), 'file_type' => $file->guessExtension(),
                        'source_document_id' => null,
                    ]);
                    if ($old && $old->source_document_id === null) $retiredPaths[] = $old->file_path;
                }
                // Missing/uncertain EPRT data must not destroy a previously uploaded waiver.
                if (!$targeted && $step === 3 && $locked->requirements_version === 2
                    && app(\App\Services\SidangTaAdmissionFacts::class)->forStudent($student)['eprt']['mode'] === 'automatic') {
                    foreach ($locked->documents()->where('document_type', \App\Support\SidangTaMeetRequirements::WAIVER)->get() as $old) {
                        if ($old->source_document_id === null) $retiredPaths[] = $old->file_path;
                        $old->delete();
                    }
                }
                if (!$targeted && $step === 4 && $accurate !== null) {
                    $obsolete = $accurate ? Requirements::PDM : Requirements::DIPLOMA;
                    foreach ($locked->documents()->whereIn('document_type', $obsolete)->get() as $old) {
                        if ($old->source_document_id === null) $retiredPaths[] = $old->file_path;
                        $old->delete();
                    }
                }
                $locked->payload = $payload;
                $locked->version++;
                $locked->reviewed_version = null;
                $locked->save();
                return $locked->load('documents');
            });
        } catch (Throwable $exception) {
            $this->clean($newPaths);
            throw $exception;
        }
        $this->clean($retiredPaths);
        return $result;
    }
}
```

### `app/Services/SidangTaDraftService.php`

Ganti seluruh isi file berikut.

```php
<?php

namespace App\Services;

use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDraft;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Support\SidangTaRevisionContract as Revision;
use Illuminate\Validation\ValidationException;

class SidangTaDraftService
{
    public function __construct(private SidangTaStudentContext $context,
                                private SidangTaDraftDocumentService $documents,
                                private SidangTaDraftAccess $access,
    ) {}

    public function owned(User $user, SidangTaRegistrationDraft $draft): SidangTaRegistrationDraft
    {
        $this->access->check($user, $draft);
        return $draft;
    }

    private function openPeriod(int $id): SidangTaPeriod
    {
        $period = SidangTaPeriod::query()->whereKey($id)
            ->openForRegistration()->lockForUpdate()->first();

        if (!$period) {
            throw ValidationException::withMessages([
                'sidang_ta_period_id' => 'Pilih periode yang masih terbuka.',
            ]);
        }

        return $period;
    }

    public function start(User $user, array $validated): SidangTaRegistrationDraft
    {
        abort_unless($user->can('sidang_ta_registration_create'), 403);
        $student = $this->context->studentFor($user);

        return DB::transaction(function () use ($user, $validated, $student) {
            $student = Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            abort_if(SidangTaRegistration::where('student_number', $student->student_number)
                ->whereIn('status', ['submitted', 'needs_review'])->exists(),
                409, 'Anda masih memiliki pengajuan aktif.');

            $existing = SidangTaRegistrationDraft::where('student_number', $student->student_number)
                ->where('status', SidangTaRegistrationDraft::STATUS_ACTIVE)
                ->whereNull('source_registration_id')->lockForUpdate()->first();

            if ($existing) {
                abort_unless((string) $existing->owner_user_id === (string) $user->id, 409);
                return $existing; // Retry start tidak menimpa draft; ubah lewat saveStep.
            }

            $period = $this->openPeriod((int) $validated['sidang_ta_period_id']);

            return SidangTaRegistrationDraft::create([
                'owner_user_id' => $user->id,
                'student_number' => $student->student_number,
                'sidang_ta_period_id' => $period->id,
                'requirements_version' => 1,
                'payload' => [
                    'student_phone' => $validated['student_phone'],
                    'parent_phone' => $validated['parent_phone'],
                ],
            ]);
        });
    }

    public function saveStep(User $user, SidangTaRegistrationDraft $draft, int $step,
                             int $expectedVersion, array $validated): SidangTaRegistrationDraft
    {
        $this->access->assertCanWrite($user, $draft);
        $this->owned($user, $draft);
        if ($step !== 1) {
            return $this->documents->save($user, $draft, $step, $expectedVersion, $validated);
        }
        $student = $this->context->studentFor($user);

        return DB::transaction(function () use ($user, $draft, $expectedVersion, $validated, $student) {
            Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $locked = SidangTaRegistrationDraft::whereKey($draft->id)->lockForUpdate()->firstOrFail();
            $this->access->check($user, $locked, true);
            abort_unless($locked->version === $expectedVersion, 409, 'Draft berubah. Muat ulang terlebih dahulu.');
            if (Revision::isTargeted($locked)) {
                Revision::assertPayloadUnchanged($locked, array_replace($locked->payload ?? [], [
                    'student_phone' => $validated['student_phone'], 'parent_phone' => $validated['parent_phone'],
                ]));
            }
            if ($locked->source_registration_id !== null) {
                if ((int) $validated['sidang_ta_period_id'] !== (int) $locked->sidang_ta_period_id) {
                    throw ValidationException::withMessages([
                        'sidang_ta_period_id' => 'Periode revisi harus mengikuti pengajuan asal.',
                    ]);
                }
                $period = $locked->period; // Boleh tertutup; bukan pilihan periode baru.
            } else {
                $period = $this->openPeriod((int) $validated['sidang_ta_period_id']);
            }

            $locked->payload = array_replace($locked->payload ?? [], [
                'student_phone' => $validated['student_phone'],
                'parent_phone' => $validated['parent_phone'],
            ]);
            $locked->sidang_ta_period_id = $period->id;
            $locked->version++;
            $locked->reviewed_version = null;
            $locked->save();

            return $locked;
        });
    }
}
```

### `app/Services/SidangTaDraftFinalizer.php`

Ganti seluruh isi file berikut.

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
use App\Support\SidangTaRevisionContract as Revision;
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
            $targeted = Revision::isTargeted($locked);
            abort_unless($locked->reviewed_version === $locked->version, 409, 'Tinjau draft terlebih dahulu.');
            if ($targeted) Revision::assertTransferReady($locked);
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
                if (!$targeted && !in_array($admission['eprt']['mode'], ['automatic', 'waiver'], true)) {
                    $errors['eligibility.eprt'] = $admission['eprt']['message'];
                }
                if ($errors !== []) throw ValidationException::withMessages($errors);
                $rules = [];
                foreach (range(1, 4) as $step) $rules = array_merge($rules, Requirements::scalarRules($step));
                if (Validator::make($payload, $rules)->fails()) {
                    throw ValidationException::withMessages(['draft' => 'Data belum lengkap. Buka form dan lengkapi kembali.']);
                }
                $types = $targeted ? \App\Support\SidangTaReviewEligibility::requiredTypes($source)
                    : Meet::allTypes((bool) $payload['diploma_biodata_accurate'], $admission['eprt']['mode']);
            } else {
                $types = array_merge(Requirements::CAPTURES, Requirements::PAYMENT,
                    Requirements::typesForStep(4, $payload['diploma_biodata_accurate']));
            }
            $allDocuments = $locked->documents()->lockForUpdate()->get();
            // A formerly necessary waiver may become obsolete after a qualifying test.
            // Only an explicit successful submission retires it failed submissions keep it.
            $documents = !$targeted && $isMeet && $admission['eprt']['mode'] === 'automatic'
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
            $snapshot = $targeted ? $source->academic_snapshot : $this->snapshots->forStudent($student);
            if ($isMeet && !$targeted) {
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
                'student_number' => $student->student_number,
                'student_name' => $targeted ? $source->student_name : $student->name,
                'study_program' => $targeted ? $source->study_program : $student->study_program,
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
                // Borrowed verified rows keep their status, notes, metadata and file.
                if ($targeted && $document->source_document_id !== null) continue;
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

Perubahan mode EPRT tidak memindahkan kontrak dokumen revisi. Source waiver tetap mempunyai surat, sekalipun nilai terkini kemudian memenuhi450; source automatic tidak tiba-tiba meminta upload surat pada card yang terkunci. Hal ini mengikuti keputusan kontrak58: revisi memperbaiki dokumen pengajuan asal. Pengajuan baru tetap memakai EPRT terbaru. Lima gate akademik tetap diperiksa ulang saat mengirim revisi.

Snapshot, nama, dan prodi yang direkam saat pengajuan awal tetap dipertahankan. Waktu/status pengiriman dan log revisi diperbarui, tanpa mereset row dokumen verified yang dipinjam.

Referensi API: [transaksi Laravel13](https://laravel.com/docs/13.x/database#database-transactions) dan [pessimistic locking](https://laravel.com/docs/13.x/queries#pessimistic-locking), diperiksa melalui Context7. Cleanup berkas tetap dilakukan sesudah transaksi berhasil.

## 6. Controller dan tampilan

### `app/Http/Controllers/SidangTaDraftController.php`

Ganti **method `meetContext()` saja** dengan kode lengkap berikut. Method lainnya tetap ada.

```php
    private function meetContext(SidangTaRegistrationDraft $draft, Student $student): ?array
    {
        if ($draft->requirements_version !== 2) return null;
        $admission = app(\App\Services\SidangTaAdmissionFacts::class)->forStudent($student);
        $targeted = \App\Support\SidangTaRevisionContract::isTargeted($draft);
        if ($targeted) {
            $source = \App\Support\SidangTaRevisionContract::source($draft);
            $snapshot = $source->academic_snapshot ?? [];
            $stored = $snapshot['admission'] ?? [];
            $storedMode = $stored['eprt']['mode'] ?? null;
            abort_unless(in_array($storedMode, ['automatic', 'waiver'], true), 409,
                'Persyaratan EPRT pengajuan asal belum lengkap.');
            $admission['eprt'] = ['mode' => $storedMode, 'requires_waiver' => $storedMode === 'waiver',
                'message' => $storedMode === 'waiver' ? 'Surat Permakluman mengikuti pengajuan asal.' : 'EPRT memenuhi syarat saat pengajuan asal.'];
            $admission['eprt_facts'] = ['score' => $stored['eprt_facts']['score'] ?? $snapshot['eprt_score'] ?? null,
                'confirmed_attempts' => $stored['eprt_facts']['confirmed_attempts'] ?? null];
        }
        $mode = $admission['eprt']['mode'];
        $facts = $admission['facts'];
        $level = match ($facts['level_2_passed']) { true => 'Lulus', false => 'Belum lulus', default => 'Belum tersedia' };
        $skpi = match ($facts['skpi_approved']) { true => 'Disetujui', false => 'Belum memenuhi', default => 'Belum tersedia' };
        $krs = match ($admission['krs_status']) { 'eligible' => 'Terdaftar', 'unconfirmed' => 'Belum terkonfirmasi', default => 'Belum tersedia' };
        $payment = strtolower(trim((string) ($student->bppPayment()->value('status') ?? '')));
        $payment = match ($payment) { 'paid', 'lunas' => 'Lunas', 'unpaid', 'belum lunas', 'belum_lunas' => 'Belum lunas', default => 'Belum tersedia' };
        $score = $admission['eprt_facts']['score'];
        $attempts = $admission['eprt_facts']['confirmed_attempts'];
        $payload = $draft->payload ?? [];
        $choice = $payload['diploma_biodata_accurate'] ?? null;
        $accurate = $choice === null ? null : (bool) $choice;
        $types = [];
        foreach ([2, 3, 4] as $step) {
            $types[$step] = $targeted ? \App\Support\SidangTaRevisionContract::typesForStep($draft, $step)
                : \App\Support\SidangTaMeetRequirements::typesForStep($step, $accurate, $mode);
        }
        return [
            'admission' => $admission, 'types' => $types, 'targeted' => $targeted,
            'editable_types' => $targeted ? \App\Support\SidangTaRevisionContract::editableTypes($draft) : [],
            'can_submit' => ($draft->source_registration_id === null || $targeted)
                && $admission['academic']['academic_passed']
                && in_array($mode, ['automatic', 'waiver'], true),
            'academic' => ['sidang_tingkat_2' => $level, 'skpi_status' => $skpi, 'ta2_krs_label' => $krs,
                'tak_score' => $facts['tak_score'], 'passed_credits' => $facts['passed_credits'], 'eprt_score' => $score],
            'automatic' => [
                2 => [
                    ['key' => 'level_2', 'title' => 'Hasil sidang tingkat 2', 'value' => $level, 'hint' => 'Tidak memerlukan unggahan.'],
                    ['key' => 'sidang_approval', 'title' => 'Persetujuan sidang iGracias', 'value' => 'Belum tersedia', 'hint' => 'Persetujuan sidang belum tercatat.'],
                ],
                3 => [
                    ['key' => 'bpp', 'title' => 'BPP semester berjalan', 'value' => $payment,
                        'hint' => 'Periode pembayaran belum terkonfirmasi. Tidak memerlukan unggahan.'],
                    ['key' => 'eprt', 'title' => $targeted ? 'EPRT pengajuan asal' : 'EPRT', 'value' => $score === null ? 'Belum tersedia' : 'Nilai '.$score,
                        'hint' => $admission['eprt']['message'].($attempts === null ? '' : ' '.$attempts.' tes tercatat.')],
                ],
            ],
        ];
    }
```

### `app/Http/Controllers/SidangTaRegistrationController.php`

Ganti **method `adminShow()` saja**. Method review/preview/download lain tetap dipertahankan.

```php
    public function adminShow(SidangTaRegistration $sidang_ta_registration)
    {
        $registration = $sidang_ta_registration->load(['period', 'documents', 'logs']);
        $reviewable = match ($registration->requirements_version) {
            1 => in_array($registration->status, ['submitted', 'needs_review'], true),
            2 => $registration->status === 'submitted',
            default => false,
        };
        $requiredTypes = \App\Support\SidangTaReviewEligibility::requiredTypes($registration);
        $approvalBlockers = \App\Support\SidangTaReviewEligibility::blockers($registration);
        $documentGroups = \App\Support\SidangTaReviewEligibility::groups($registration);
        $revisionBlockers = [];
        if ($registration->requirements_version === 2 && $reviewable) {
            try {
                \App\Support\SidangTaRevisionContract::assertCanRequest($registration);
            } catch (ValidationException $exception) {
                $revisionBlockers = array_values(array_merge(...array_values($exception->errors())));
            }
        }
        return view('sidang_ta_registrations.admin.show', compact(
            'registration', 'reviewable', 'requiredTypes', 'approvalBlockers', 'documentGroups', 'revisionBlockers'
        ));
    }
```

### `resources/views/sidang_ta_registrations/wizard.blade.php`

Ganti seluruh isi file berikut.

```blade
<x-app-layout>
    <x-slot name="header">
        {{ $draft?->source_registration_id ? 'Draft Revisi Sidang TA' : 'Pendaftaran Sidang TA' }}
    </x-slot>
    @php
        $payload = $draft?->payload ?? [];
        $isMeet = $draft?->requirements_version === 2;
        $meetContext = $meetContext ?? null;
        $targetedRevision = (bool) ($meetContext['targeted'] ?? false);
        $rawAccurate = $targetedRevision ? ($payload['diploma_biodata_accurate'] ?? null)
            : old('diploma_biodata_accurate', $payload['diploma_biodata_accurate'] ?? null);
        $accurate = match ($rawAccurate) {
            true, 1, '1' => '1', false, 0, '0' => '0', default => '',
        };
        $stepProgress = $stepProgress ?? ['complete' => false, 'stored_documents' => 0,
            'required_documents' => 0, 'missing' => []];
        $selectedPeriod = $targetedRevision ? (string) $draft->sidang_ta_period_id
            : (string) old('sidang_ta_period_id', $draft?->sidang_ta_period_id ?? '');
        $periods = $openPeriods;
        if ($draft && !$periods->contains('id', $draft->sidang_ta_period_id)) {
            $periods = $periods->prepend($draft->period);
        }
        $selected = $periods->first(fn ($period) => (string) $period->id === $selectedPeriod);
        $windowFor = static fn ($period) => $period->open_at->format('d/m/Y H:i').' – '
            .$period->close_at->format('d/m/Y H:i').($period->isOpen() ? '' : ' (ditutup)');
        $initial = ['accurate' => $accurate, 'dirty' => $errors->any(), 'hasErrors' => $errors->any(),
            'periodHint' => $selected ? $windowFor($selected) : ''];
        $reviewReady = $draft && $completedThrough === 4;
        $permission = $draft?->source_registration_id ? 'sidang_ta_registration_update' : 'sidang_ta_registration_create';
        $titles = [1 => 'Data dasar & kontak', 2 => 'Verifikasi iGracias & akademik',
            3 => 'Pembayaran & dokumen', 4 => 'Biodata ijazah'];
    @endphp
    <div class="mx-auto max-w-7xl space-y-5" data-current-step="{{ $step }}"
         x-data="sidangTaWizard(@js($initial))" @sidang-ta-file-changed="dirty = true"
         @pageshow.window="busy = false"
         @beforeunload.window="if (dirty && !busy) { $event.preventDefault(); $event.returnValue = ''; }">
        @if($errors->any())
            <div data-error-summary tabindex="-1" role="alert"
                 class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-rose-800">
                <p class="text-sm font-bold">
                    Periksa isian berikut:
                </p>
                <ul class="mt-2 list-inside list-disc space-y-1 text-sm">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <p class="mt-2 text-xs leading-relaxed">
                    Data yang sudah tersimpan tetap tersedia. File pilihan baru yang ditolak perlu dipilih ulang.
                </p>
            </div>
        @endif
        @if(session('success'))
            <p role="status" class="rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('success') }}
            </p>
        @endif

        <section class="campus-card overflow-hidden" aria-label="Progres pendaftaran">
            <nav aria-label="Tahap pendaftaran" data-progress-scroll class="overflow-x-auto p-4 sm:p-5">
                <ol class="flex min-w-max items-stretch gap-2">
                    @foreach($titles as $number => $title)
                        @php
                            $reachable = $number <= $maxStep;
                            $state = $number === $step ? 'current' : ($number < $step && $number <= $completedThrough
                                ? 'complete' : ($reachable ? 'available' : 'locked'));
                            $tone = match ($state) {
                                'current' => 'border-[#DA2D3B] bg-[#DA2D3B] text-white',
                                'complete' => 'border-rose-200 bg-rose-50 text-rose-800',
                                'available' => 'border-slate-200 bg-white text-slate-700',
                                default => 'border-slate-200 bg-slate-100 text-slate-400',
                            };
                        @endphp
                        <li class="flex items-center gap-2">
                            @if($draft && $reachable)
                                <a data-step="{{ $number }}" data-state="{{ $state }}" @click="leave($event)"
                                   href="{{ route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => $number]) }}"
                                   @if($number === $step) aria-current="step" @endif
                                   class="block w-40 rounded-xl border px-3 py-3 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2 {{ $tone }}">
                                    <span class="text-sm font-bold">
                                        Tahap {{ $number }}{{ $state === 'complete' ? ' ✓' : '' }}
                                    </span>
                                    <span class="mt-1 block whitespace-normal text-xs leading-relaxed">
                                        {{ $title }}
                                    </span>
                                </a>
                            @else
                                <span data-step="{{ $number }}" data-state="{{ $state }}"
                                      @if($number === $step) aria-current="step" @else aria-disabled="true" @endif
                                      class="block w-40 rounded-xl border px-3 py-3 {{ $tone }}">
                                    <span class="text-sm font-bold">
                                        Tahap {{ $number }}
                                    </span>
                                    <span class="mt-1 block whitespace-normal text-xs leading-relaxed">
                                        {{ $title }}
                                    </span>
                                </span>
                            @endif
                            @if($number < 4)
                                <span data-chevron="{{ $number }}" aria-hidden="true"
                                      class="px-1 text-xl font-bold {{ $number <= $step ? 'text-[#DA2D3B]' : 'text-slate-300' }}">&raquo;</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>
            <div data-save-status
                 class="space-y-1 border-t border-slate-100 bg-slate-50 px-4 py-3 text-xs leading-relaxed sm:px-5">
                <p class="font-medium text-slate-700">
                    {{ $draft ? 'Terakhir disimpan '.$draft->updated_at->format('d/m/Y H:i').'.' : 'Belum ada data yang disimpan.' }}
                </p>
                <p class="text-slate-500">
                    Tekan Simpan agar data dan file dapat dilanjutkan setelah halaman ditutup.
                </p>
                <p role="status" aria-live="polite" class="font-medium text-amber-800"
                   x-text="busy ? 'Sedang menyimpan…' : (dirty ? 'Ada perubahan yang belum disimpan.' : '')"></p>
            </div>
        </section>

        <div class="grid items-start gap-6 xl:grid-cols-12">
            <div class="min-w-0 space-y-5 xl:col-span-8">
                @if($draft?->sourceRegistration?->review_notes)
                    <section class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                        <h2 class="text-sm font-bold text-amber-900">
                            Catatan petugas
                        </h2>
                        <p class="mt-1 whitespace-pre-line text-sm leading-relaxed text-amber-800">
                            {{ $draft->sourceRegistration->review_notes }}
                        </p>
                    </section>
                @endif
                @if($draft?->sourceRegistration)
                    @include('sidang_ta_registrations.partials.revision-feedback', ['registration' => $draft->sourceRegistration])
                @endif

                <form id="sidang-ta-step-form" method="POST" novalidate enctype="multipart/form-data"
                      class="campus-card space-y-5 p-5 sm:p-6"
                      action="{{ $draft ? route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => $step]) : route('sidang-ta-drafts.store') }}"
                      @input="dirty = true" @change="dirty = true"
                      @submit="if (busy) { $event.preventDefault(); } else { busy = true; }">
                    @csrf
                    @if($draft)
                        <input type="hidden" name="version" value="{{ old('version', $draft->version) }}">
                    @endif
                    <header class="space-y-1.5 border-b border-slate-100 pb-4">
                        <h2 class="text-lg font-bold text-slate-900">Tahap {{ $step }} — {{ $titles[$step] }}</h2>
                        <p class="text-xs text-slate-500">
                            @if($isMeet)
                                Tanda * menunjukkan unggahan wajib. Draft boleh disimpan sebelum lengkap.
                            @else
                                Isian bertanda <span class="font-bold text-rose-600">*</span> wajib lengkap sebelum
                                lanjut/kirim. Draft boleh disimpan sebelum lengkap.
                            @endif
                        </p>
                    </header>
                    @if($draft && !$stepProgress['complete'])
                        <section data-step-progress role="status"
                                 class="space-y-2 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                            <p class="font-semibold">
                                Lengkapi tahap ini sebelum melanjutkan.
                            </p>
                            @if($stepProgress['required_documents'] !== null && $stepProgress['required_documents'] > 0)
                                <p>
                                    {{ $stepProgress['stored_documents'] }}
                                    dari {{ $stepProgress['required_documents'] }} dokumen tersimpan.
                                </p>
                            @endif
                            <p class="text-xs">
                                Masih perlu dilengkapi:
                            </p>
                            <ul class="list-inside list-disc space-y-1 text-xs leading-relaxed">
                                @foreach($stepProgress['missing'] as $missing)
                                    <li>{{ $missing }}</li>
                                @endforeach
                            </ul>
                        </section>
                    @endif
                    @if($step === 1)
                        <div class="max-w-lg">
                            <label for="sidang-ta-period-id" class="mb-1.5 block text-sm font-semibold text-slate-700">
                                Periode Sidang TA @unless($isMeet)<span class="text-rose-600" aria-hidden="true">*</span>@endunless
                            </label>
                            @if($draft?->source_registration_id)
                                <input type="hidden" name="sidang_ta_period_id"
                                       value="{{ $draft->sidang_ta_period_id }}">
                                <p id="sidang-ta-period-id" tabindex="-1"
                                   class="rounded-lg bg-slate-50 p-3 text-sm font-medium text-slate-800"
                                   aria-invalid="{{ $errors->has('sidang_ta_period_id') ? 'true' : 'false' }}"
                                   aria-describedby="period-window error-sidang_ta_period_id">
                                    {{ $draft->period->name }}(periode pengajuan asal)
                                </p>
                            @else
                                <select id="sidang-ta-period-id" name="sidang_ta_period_id" required
                                        class="campus-input w-full {{ $errors->has('sidang_ta_period_id') ? '!border-rose-500' : '' }}"
                                        aria-invalid="{{ $errors->has('sidang_ta_period_id') ? 'true' : 'false' }}"
                                        aria-describedby="period-window error-sidang_ta_period_id"
                                        @change="updatePeriodHint($event.target)">
                                    <option value="">Pilih periode terbuka</option>
                                    @foreach($periods as $period)
                                        <option value="{{ $period->id }}"
                                                data-window="{{ $windowFor($period) }}" @selected($selectedPeriod === (string) $period->id)>
                                            {{ $period->name }}{{ $period->isOpen() ? '' : ' (ditutup)' }}
                                        </option>
                                    @endforeach
                                </select>
                            @endif
                            <p id="period-window" class="mt-1.5 text-xs leading-relaxed text-slate-500"
                               x-text="periodHint || 'Pilih periode yang masih terbuka.'">{{ $initial['periodHint'] ?: 'Pilih periode yang masih terbuka.' }}</p>
                            @include('sidang_ta_registrations.partials.wizard-error', ['field' => 'sidang_ta_period_id'])
                        </div>
                        <div class="grid max-w-xl gap-4 sm:grid-cols-2">
                            <div class="min-w-0">
                                <label for="student-phone" class="mb-1.5 block text-sm font-semibold text-slate-700">
                                    Nomor HP mahasiswa @unless($isMeet)<span class="text-rose-600" aria-hidden="true">*</span>@endunless
                                </label>
                                <input id="student-phone"
                                       class="campus-input w-full {{ $errors->has('student_phone') ? '!border-rose-500' : '' }}"
                                       name="student_phone" @readonly($targetedRevision) inputmode="tel" maxlength="30" required
                                       placeholder="08xxxxxxxxxx"
                                       aria-invalid="{{ $errors->has('student_phone') ? 'true' : 'false' }}"
                                       aria-describedby="error-student_phone"
                                       value="{{ $targetedRevision ? ($payload['student_phone'] ?? '') : old('student_phone', $payload['student_phone'] ?? $student->phone_number) }}">
                                @include('sidang_ta_registrations.partials.wizard-error', ['field' => 'student_phone'])
                            </div>
                            <div class="min-w-0">
                                <label for="parent-phone" class="mb-1.5 block text-sm font-semibold text-slate-700">
                                    Nomor HP orang tua/wali @unless($isMeet)<span class="text-rose-600" aria-hidden="true">*</span>@endunless
                                </label>
                                <input id="parent-phone"
                                       class="campus-input w-full {{ $errors->has('parent_phone') ? '!border-rose-500' : '' }}"
                                       name="parent_phone" @readonly($targetedRevision) inputmode="tel" maxlength="30" required
                                       placeholder="08xxxxxxxxxx"
                                       aria-invalid="{{ $errors->has('parent_phone') ? 'true' : 'false' }}"
                                       aria-describedby="error-parent_phone"
                                       value="{{ $targetedRevision ? ($payload['parent_phone'] ?? '') : old('parent_phone', $payload['parent_phone'] ?? $student->parent_phone_number) }}">
                                @include('sidang_ta_registrations.partials.wizard-error', ['field' => 'parent_phone'])
                            </div>
                        </div>
                    @elseif($step === 2 || $step === 3)
                        @if($step === 3)
                            <div class="max-w-xs">
                                <label for="graduation-payment-date"
                                       class="mb-1.5 block text-sm font-semibold text-slate-700">
                                    Tanggal pembayaran wisuda @unless($isMeet)<span class="text-rose-600" aria-hidden="true">*</span>@endunless
                                </label>
                                <input id="graduation-payment-date"
                                       class="campus-input w-full {{ $errors->has('graduation_payment_date') ? '!border-rose-500' : '' }}"
                                       type="date" name="graduation_payment_date" @readonly($targetedRevision) required
                                       max="{{ today()->toDateString() }}"
                                       aria-invalid="{{ $errors->has('graduation_payment_date') ? 'true' : 'false' }}"
                                       aria-describedby="error-graduation_payment_date"
                                       value="{{ $targetedRevision ? ($payload['graduation_payment_date'] ?? '') : old('graduation_payment_date', $payload['graduation_payment_date'] ?? '') }}">
                                @include('sidang_ta_registrations.partials.wizard-error', ['field' => 'graduation_payment_date'])
                            </div>
                        @endif
                        @if($isMeet)
                            @include('sidang_ta_registrations.partials.meet-automatic-cards', ['items' => $meetContext['automatic'][$step] ?? []])
                        @endif
                        <div class="grid gap-4 sm:grid-cols-2">
                            @foreach(($isMeet ? $meetContext['types'][$step] : \App\Support\SidangTaDocumentRequirements::typesForStep($step)) as $type)
                                @include('sidang_ta_registrations.partials.wizard-upload', ['type' => $type, 'conditional' => null])
                            @endforeach
                        </div>
                    @else
                        @if($targetedRevision)
                            <input type="hidden" name="diploma_biodata_accurate" value="{{ $accurate }}">
                        @endif
                        <fieldset id="diploma-biodata-group" tabindex="-1" aria-required="true"
                                  aria-invalid="{{ $errors->has('diploma_biodata_accurate') ? 'true' : 'false' }}"
                                  aria-describedby="error-diploma_biodata_accurate">
                            <legend class="text-sm font-semibold text-slate-700">
                                Apakah biodata dummy ijazah sudah sesuai? @unless($isMeet)<span class="text-rose-600" aria-hidden="true">*</span>@endunless
                            </legend>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                @foreach(['1' => 'Sudah sesuai', '0' => 'Belum sesuai, sudah mengajukan PDM'] as $value => $label)
                                    <label
                                        class="flex cursor-pointer items-start gap-3 rounded-xl border p-3 text-sm leading-relaxed"
                                        :class="accurate === '{{ $value }}' ? 'border-rose-300 bg-rose-50 text-rose-900' : 'border-slate-200 text-slate-700'">
                                        <input type="radio" name="diploma_biodata_accurate" @disabled($targetedRevision) value="{{ $value }}"
                                               x-model="accurate" @checked($accurate === (string) $value) required
                                               class="mt-0.5 border-slate-300 text-rose-600 focus:ring-rose-500">
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @include('sidang_ta_registrations.partials.wizard-error', ['field' => 'diploma_biodata_accurate'])
                        </fieldset>
                        <p class="text-xs leading-relaxed text-slate-500">
                            Menyimpan pilihan biodata yang berbeda akan mengganti lampiran cabang draft sebelumnya.
                            Berkas pengajuan final tetap tersimpan.
                        </p>
                        <div x-show="accurate === '1'" x-cloak class="grid gap-4 sm:grid-cols-2">
                            @foreach(\App\Support\SidangTaDocumentRequirements::DIPLOMA as $type)
                                @include('sidang_ta_registrations.partials.wizard-upload', ['type' => $type, 'conditional' => '1'])
                            @endforeach
                        </div>
                        <div x-show="accurate === '0'" x-cloak>
                            @include('sidang_ta_registrations.partials.wizard-upload', ['type' => 'pdm_submission_proof', 'conditional' => '0'])
                        </div>
                    @endif
                    <div
                        class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                        <a @click="leave($event)" class="btn btn-secondary"
                           href="{{ route('sidang-ta-registrations.index') }}">
                            Kembali ke pendaftaran
                        </a>
                        @can($permission)
                            <div class="flex flex-wrap items-center gap-2">
                                <button data-save-draft type="submit" class="btn btn-secondary" :disabled="busy"
                                        x-text="busy ? 'Sedang menyimpan…' : 'Simpan draft'">
                                    Simpan draft
                                </button>
                                @if($step < 4)
                                    @if($draft && $stepProgress['complete'])
                                        <a data-next-step class="btn btn-primary" aria-disabled="false"
                                           href="{{ route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => $step + 1]) }}"
                                           :aria-disabled="dirty || busy ? 'true' : 'false'"
                                           :tabindex="dirty || busy ? -1 : 0"
                                           :class="dirty || busy ? 'pointer-events-none opacity-50' : ''"
                                           @click="if (dirty || busy) $event.preventDefault()">
                                            Lanjutkan
                                        </a>
                                    @else
                                        <button data-next-step type="button" disabled
                                                class="btn btn-primary cursor-not-allowed opacity-50">
                                            Lanjutkan
                                        </button>
                                    @endif
                                @endif
                            </div>
                        @endcan
                    </div>
                </form>
                @if($draft && $step === 4)
                    @can($permission)
                        <form data-review-draft method="POST" class="campus-card space-y-3 p-5"
                              action="{{ route('sidang-ta-drafts.review', $draft) }}"
                              x-on:submit="if (dirty || busy || {{ $reviewReady ? 'false' : 'true' }}) { $event.preventDefault(); } else { busy = true; }">
                            @csrf <input type="hidden" name="version" value="{{ $draft->version }}">
                            <p class="text-sm leading-relaxed text-slate-600">
                                Draft harus tersimpan dan seluruh tahap lengkap sebelum ditinjau. Pendaftaran baru
                                dikirim setelah konfirmasi pada halaman tinjau.
                            </p>
                            <p id="review-readiness" class="text-xs text-slate-500">Lengkapi seluruh dokumen dan isian,
                                lalu tekan Simpan draft untuk mengaktifkan tinjauan.</p>
                            <button data-review-submit type="submit" aria-describedby="review-readiness"
                                    aria-disabled="{{ $reviewReady ? 'false' : 'true' }}"
                                    :aria-disabled="dirty || busy || {{ $reviewReady ? 'false' : 'true' }} ? 'true' : 'false'"
                                    class="btn btn-primary disabled:cursor-not-allowed disabled:opacity-50 disabled:shadow-none"
                                    @disabled(!$reviewReady)
                                    :disabled="dirty || busy || {{ $reviewReady ? 'false' : 'true' }}">
                                Tinjau Sebelum Kirim
                            </button>
                        </form>
                    @endcan
                @endif
            </div>
            @include('sidang_ta_registrations.partials.wizard-academic')
        </div>
    </div>
    @include('sidang_ta_registrations.partials.document-preview-modal')
</x-app-layout>
```

### `resources/views/sidang_ta_registrations/partials/wizard-upload.blade.php`

Ganti seluruh isi file berikut.

```blade
@php
    $existing = $draft?->documents?->firstWhere('document_type', $type);
    $available = $existing && \Illuminate\Support\Facades\Storage::disk('local')->exists($existing->file_path);
    $isMeetUpload = $draft?->requirements_version === 2;
    $lockedUpload = (bool) ($meetContext['targeted'] ?? false)
        && !in_array($type, $meetContext['editable_types'] ?? [], true);
    $replacementNeeded = (bool) ($meetContext['targeted'] ?? false) && !$lockedUpload
        && $existing && $existing->source_document_id !== null;
    $label = ($isMeetUpload ? \App\Support\SidangTaMeetRequirements::LABELS[$type] ?? null : null)
        ?? \App\Models\SidangTaRegistration::DOCUMENT_TYPES[$type] ?? $type;
    $capture = in_array($type, $isMeetUpload ? \App\Support\SidangTaMeetRequirements::CAPTURES
        : \App\Support\SidangTaDocumentRequirements::CAPTURES, true);
    $guide = $isMeetUpload ? match ($type) {
        'ta_data_capture' => 'assets/images/forms/sidang-ta/ta-data.png',
        'online_supervision_capture' => 'assets/images/forms/sidang-ta/bimbingan-online.png',
        default => null,
    } : null;
    $guideAvailable = $guide && is_file(public_path($guide));
    $conditional = $conditional ?? null;
    $errorKey = "documents.{$type}";
    $uploadInitial = ['serverError' => $errors->has($errorKey)];
@endphp
<div data-upload-type="{{ $type }}" data-upload-locked="{{ $lockedUpload ? 'true' : 'false' }}"
     class="min-w-0 rounded-xl border border-slate-200 p-4 {{ $lockedUpload ? 'bg-slate-100 text-slate-500' : 'bg-white' }}"
     x-data="sidangTaUpload(@js($uploadInitial))">
    <label class="block text-sm font-semibold leading-relaxed text-slate-800" for="file-{{ $type }}">
        {{ $label }} @unless($lockedUpload)<span class="text-rose-600" aria-hidden="true">*</span>@endunless
    </label>
    <p id="help-{{ $type }}" class="mt-1.5 text-xs leading-relaxed text-slate-500">
        @unless($guideAvailable)
            {{ \App\Models\SidangTaRegistration::DOCUMENT_DESCRIPTIONS[$type] ?? '' }}
        @endunless
        {{ $capture ? 'PDF/JPG/JPEG/PNG' : 'PDF' }}, maksimal 10 MB per file.
    </p>
    @if($guideAvailable)
        <button data-upload-guide data-document-preview type="button"
                class="mt-3 block w-full rounded-lg text-left focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2"
                data-preview-url="{{ asset($guide) }}" data-download-url=""
                data-file-name="Contoh {{ $label }}" data-file-type="png"
                aria-label="Lihat contoh {{ $label }}" aria-haspopup="dialog"
                x-on:click="$dispatch('sidang-ta-preview', $el.dataset)">
            <img loading="lazy" src="{{ asset($guide) }}" alt="Panduan {{ $label }}"
                 class="w-full rounded-lg border border-slate-200 object-contain">
            <span class="mt-1 block text-xs font-medium text-[#DA2D3B]">Lihat contoh</span>
        </button>

    @endif
    @if($isMeetUpload && $type === \App\Support\SidangTaMeetRequirements::WAIVER)
        <p class="mt-2 text-xs leading-relaxed text-slate-600">
            Unduh template, lengkapi, lalu unggah kembali dalam format PDF.
            <a data-waiver-template href="https://telkomuniversityofficial-my.sharepoint.com/:w:/g/personal/akademiksby_telkomuniversity_ac_id/EYC5y40Ky_1PhVWh4_LswJoBtC8yKT6PuTEAwUTDiFY-2A?e=qRenjo"
               target="_blank" rel="noopener" class="font-semibold text-[#DA2D3B] underline">Link Surat Permakluman</a>
        </p>
    @endif
    @if($available)
        <div class="mt-3 space-y-2 rounded-lg border border-emerald-200 bg-emerald-50 p-3">
            <p class="text-xs font-semibold text-emerald-800">Berkas tersimpan</p>
            <p class="break-words text-sm text-slate-800">{{ $existing->file_name }}</p>
            <p class="text-xs text-slate-500">
                {{ number_format(($existing->file_size ?? 0) / 1024) }} KB · {{ $lockedUpload ? 'Berkas ini tetap digunakan.' : 'Pilih berkas baru hanya untuk mengganti.' }}
            </p>
        </div>
    @elseif($existing)
        <p class="mt-3 text-sm text-rose-700">
            Berkas tersimpan tidak ditemukan. Unggah kembali.
        </p>
    @endif
    @if($replacementNeeded)
        <p data-replacement-needed class="mt-3 rounded-lg bg-amber-50 p-3 text-xs font-semibold text-amber-900">
            Unggah berkas pengganti sesuai catatan petugas. Berkas pengajuan sebelumnya belum dihitung sebagai pengganti.
        </p>
    @endif
    @if($lockedUpload)
        <p data-upload-preserved class="mt-3 rounded-lg bg-slate-200/60 p-3 text-xs text-slate-600">
            Dokumen terverifikasi tetap digunakan. Pengunggahan hanya tersedia untuk berkas yang diminta diperbaiki.
        </p>
    @else
    <div
        class="relative mt-3 rounded-xl border-2 border-dashed p-4 transition-colors focus-within:ring-2 focus-within:ring-rose-500 focus-within:ring-offset-2"
        :class="hasError() ? 'border-rose-500 bg-rose-50' : (dragDepth > 0 ? 'border-rose-500 bg-rose-50' : 'border-slate-300 bg-slate-50 hover:border-rose-400')"
        @dragenter.prevent.stop="enter()" @dragover.prevent.stop
        @dragleave.prevent.stop="leaveDrop()" @drop.prevent.stop="drop($event)">
        <input id="file-{{ $type }}" x-ref="file" type="file" name="documents[{{ $type }}]"
               accept="{{ $capture ? '.pdf,.jpg,.jpeg,.png' : '.pdf' }}"
               class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
               aria-describedby="help-{{ $type }} error-documents-{{ $type }}"
               aria-invalid="{{ $errors->has($errorKey) ? 'true' : 'false' }}"
               :aria-invalid="hasError() ? 'true' : 'false'"
               @change="picked()"
               @if($conditional !== null)
                   @disabled($accurate !== $conditional)
                   :disabled="accurate !== '{{ $conditional }}'"
               :required="accurate === '{{ $conditional }}' && {{ $available && !$replacementNeeded ? 'false' : 'true' }}"
        @else
            @required(!$available || $replacementNeeded)
            @endif>
        <div class="pointer-events-none py-3 text-center">
            <svg aria-hidden="true" class="mx-auto h-7 w-7 text-slate-400" fill="none" stroke="currentColor"
                 viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                      d="M12 16V4m0 0L7 9m5-5 5 5M4 16v3a1 1 0 001 1h14a1 1 0 001-1v-3"/>
            </svg>
            <p class="mt-2 text-sm font-medium text-slate-700">
                Klik untuk memilih atau seret file ke sini
            </p>
            <p class="mt-1 text-xs text-slate-500">
                {{ $capture ? 'PDF/JPG/PNG' : 'PDF' }} · Maksimal 10 MB · Satu berkas
            </p>
        </div>
    </div>
    <div x-show="fileName" x-cloak class="mt-3 space-y-2 rounded-lg border border-amber-200 bg-amber-50 p-3">
        <p class="text-xs font-semibold text-amber-800">
            Pilihan baru · Belum disimpan
        </p>
        <p class="break-words text-sm text-slate-800" x-text="fileName"></p>
        <p class="text-xs text-slate-500" x-text="fileSize"></p>
        <button type="button" class="btn btn-secondary text-xs" @click="clear()">
            Batalkan pilihan baru
        </button>
    </div>
    <p id="error-documents-{{ $type }}" role="status" aria-live="polite"
       class="mt-1.5 text-xs leading-relaxed text-rose-700" x-show="hasError()">
        <span x-show="localError" x-text="localError"></span>
        <span x-show="!localError && !selectionChanged">{{ $errors->first($errorKey) }}</span>
    </p>
    @endif
</div>
```

### `resources/views/sidang_ta_registrations/wizard-review.blade.php`

Ganti seluruh isi file berikut.

```blade
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
                    {{ $isRevision ? 'Penuhi persyaratan akademik sebelum mengirim revisi. Data dan berkas tetap tersimpan.'
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
                                x-text="sending ? 'Sedang mengirim…' : '{{ $confirmLabel }}'">{{ $confirmLabel }}
                        </button>
                    </form>
                </dialog>
            @endcan
        </section>
    </div>
    @include('sidang_ta_registrations.partials.document-preview-modal')
</x-app-layout>
```

### `resources/views/sidang_ta_registrations/partials/revision-feedback.blade.php`

Ganti seluruh isi file berikut.

```blade
@php
    $sourceDocuments = $registration->relationLoaded('documents') ? $registration->documents : $registration->documents()->get();
    $feedback = [];
    foreach(\App\Support\SidangTaReviewEligibility::requiredTypes($registration) as $type) {
        $sourceDocument = $sourceDocuments->firstWhere('document_type', $type);
        $missingFile = $sourceDocument && !\Illuminate\Support\Facades\Storage::disk('local')->exists($sourceDocument->file_path);
        if (!$sourceDocument || $missingFile || $sourceDocument->status === 'rejected' || filled($sourceDocument->notes)) {
            $feedback[$type] = ['document' => $sourceDocument, 'missing_file' => $missingFile];
        }
    }
@endphp
@if($feedback !== [])
    <section data-review-feedback class="space-y-3 rounded-xl border border-amber-200 bg-amber-50 p-4">
        <h2 class="text-sm font-bold text-amber-900">
            Catatan dokumen pengajuan sebelumnya
        </h2>
        <p class="text-xs leading-relaxed text-amber-800">
            Gunakan catatan ini untuk memperbaiki berkas. Berkas yang diganti akan ditinjau kembali setelah revisi dikirim.
        </p>
        <ul class="space-y-3">
            @foreach($feedback as $type => $item)
                @php
                    $sourceDocument = $item['document'];
                    $label = !$sourceDocument ? 'Belum ada dokumen' : ($item['missing_file'] ? 'Berkas tidak ditemukan' : (['pending' => 'Menunggu verifikasi', 'verified' => 'Terverifikasi', 'rejected' => 'Perlu perbaikan'][$sourceDocument->status] ?? $sourceDocument->status));
                    $stepNumber = 4;
                    foreach (array_values(\App\Support\SidangTaReviewEligibility::groups($registration)) as $index => $groupTypes) {
                        if (in_array($type, $groupTypes, true)) $stepNumber = $index + 2;
                    }
                @endphp
                <li data-feedback-type="{{ $type }}" class="border-t border-amber-200 pt-3 text-xs text-amber-900">
                    <p class="font-semibold">
                        {{ \App\Models\SidangTaRegistration::DOCUMENT_TYPES[$type] }} · Tahap {{ $stepNumber }}
                    </p>
                    <p class="mt-1">{{ $label }}</p>
                    @if(filled($sourceDocument?->notes))
                        <p class="mt-1 whitespace-pre-line break-words">
                            {{ $sourceDocument->notes }}
                        </p>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>
@endif
```

### `resources/views/sidang_ta_registrations/admin/partials/registration-decision.blade.php`

Ganti seluruh isi file berikut.

```blade
@php
    $revisionBlockers = $revisionBlockers ?? ($registration->requirements_version === 2
        ? ['Data kelengkapan revisi belum tersedia. Muat ulang halaman.'] : []);
    $oldForm = old('_review_form') === 'decision';
    $rawVersion = $oldForm ? old('registration_version') : $registration->lock_version;
    $versionValue = is_scalar($rawVersion) ? (string) $rawVersion : '';
    $rawNotes = $oldForm ? old('review_notes') : $registration->review_notes;
    $notesValue = is_string($rawNotes) ? $rawNotes : '';
@endphp
<form data-ta-decision method="POST" novalidate
      action="{{ route('admin.sidang-ta-registrations.review', $registration) }}" class="campus-card space-y-4 p-5">
    @csrf
    <input type="hidden" name="_review_form" value="decision">
    <input type="hidden" name="registration_version" value="{{ $versionValue }}">
    <h3 class="font-bold text-slate-800">
        Keputusan petugas
    </h3>
    @if($registration->requirements_version === 2 && $revisionBlockers !== [])
        <div data-ta-revision-blockers role="status" class="rounded-xl bg-amber-50 p-3 text-xs text-amber-900">
            <p class="font-semibold">Selesaikan pemeriksaan dokumen sebelum meminta revisi.</p>
            <ul class="mt-2 list-inside list-disc space-y-1">
                @foreach($revisionBlockers as $message)<li>{{ $message }}</li>@endforeach
            </ul>
        </div>
    @endif
    @if($approvalBlockers !== [])
        <div data-ta-approval-blockers
             class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900">
            <p class="font-semibold">
                Setujui tersedia setelah seluruh dokumen wajib terverifikasi dan berkasnya tersedia.
            </p>
            <ul class="mt-2 list-inside list-disc space-y-1">
                @foreach($approvalBlockers as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    @if($errors->has('registration_version'))
        <a data-ta-reload href="{{ route('admin.sidang-ta-registrations.show', $registration) }}" class="btn btn-secondary">
            Muat ulang pengajuan
        </a>
    @endif
    <label class="campus-label">Catatan untuk mahasiswa (wajib untuk revisi atau penolakan)
        <textarea name="review_notes" maxlength="2000" class="campus-input min-h-24">{{ $notesValue }}</textarea>
    </label>
    @if($oldForm && $errors->has('review_notes'))
        <p class="text-xs text-rose-700">
            {{ $errors->first('review_notes') }}
        </p>
    @endif
    <div class="flex flex-wrap justify-end gap-2">
        <button data-ta-request-revision type="submit"
                class="btn btn-secondary disabled:cursor-not-allowed disabled:opacity-50"
                name="action" value="needs_review" @disabled($registration->requirements_version === 2 && $revisionBlockers !== [])>
            Minta Revisi
        </button>
        <button type="submit" class="btn btn-secondary" name="action" value="rejected">
            Tolak
        </button>
        <button data-ta-approve type="submit" class="btn btn-primary disabled:cursor-not-allowed disabled:opacity-50"
                name="action" value="approved" @disabled($approvalBlockers !== [])>
            Setujui
        </button>
    </div>
</form>
```

Catatan penerapan UI:

- Kontak/tanggal memakai readonly agar nilai original tetap terkirim. Radio biodata disabled disertai satu hidden value agar form mengirim pilihan pengajuan asal.
- Card upload yang terkunci tidak memiliki file input atau handler drop aktif. Button contoh tetap di luar blok upload, sehingga preview58 tetap bekerja.
- Pesan berkas rejected yang masih dipinjam menandakan bahwa pengganti wajib disimpan. Review/submit tidak dihitung lengkap hanya karena file lama tersedia.
- Feedback Surat Permakluman sekarang diklasifikasikan tahap3, bukan tahap4.
- Alasan Minta Revisi yang belum tersedia ditampilkan melalui `revisionBlockers`, bukan pemberitahuan penahanan fase57.
- Tombol Kirim pada Tinjau revisi mengikuti lima gate terbaru; EPRT yang ditampilkan pada revisi diberi konteks pengajuan asal. Kedua tombol tetap memakai kondisi disabled yang sama.

## 7. Verifikasi sesudah seluruh kode diterapkan

### A. Test baru dan kontrak

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaTargetedRevisionTest.php tests/Feature/SidangTaRevisionContractTest.php
```
Target27passed:19baru+8kontrak.

### B. Integrasi dan test existing yang diperbarui

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaTargetedRevisionTest.php tests/Feature/SidangTaRevisionContractTest.php tests/Feature/SidangTaMeetStaffReviewTest.php tests/Feature/SidangTaMeetFinalizationTest.php tests/Feature/SidangTaRequirementsVersionTest.php
```
Target **86 passed**:19+8+24+27+8.

### C. Regresi versi1, upload, wizard, dan preview

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaRevisionDraftTest.php tests/Feature/SidangTaFinalizationTest.php tests/Feature/SidangTaDocumentReviewTest.php tests/Feature/SidangTaReviewFeedbackTest.php tests/Feature/SidangTaDraftUploadTest.php tests/Feature/SidangTaMeetDraftTest.php tests/Feature/SidangTaMeetUiTest.php tests/Feature/SidangTaWizardUiTest.php tests/Feature/SidangTaDocumentPreviewTest.php
```
### D. Seluruh Sidang TA dan Node

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTa*Test.php tests/Unit/SidangTaAdmissionPolicyTest.php tests/Unit/SidangTaMeetRequirementsTest.php
```
Jika baseline58berjumlah330, cakupan yang sama menjadi **349 passed**. Ada19kasus baru dari17deklarasi, dengan dua tambahan dataset perubahan mode EPRT. Tidak ada pengurangan test lama.

```bash
node tests/Js/sidang-ta-entry.test.mjs
node tests/Js/sidang-ta-wizard.test.mjs
node tests/Js/sidang-ta-document-preview.test.mjs
php artisan view:clear
```

Node tetap13test. Jalankan server/Vite seperti biasa. Bila menggunakan asset build, jalankan `npm run build` untuk class readonly/gray yang ditambahkan. Tidak perlu import JSON atau perubahan PHPUnit.

## 8. Manual memakai pengajuan dummy002 yang sama

### A. Petugas mempersiapkan dan meminta revisi

1. Login akun petugas yang biasa digunakan, misalnya Krisna dengan role Petugas. Buka pengajuan dummy002 versi2 yang masih submitted dari checkpoint sebelumnya.
2. Verifikasi seluruh dokumen kecuali satu yang akan diuji, misalnya Capture Data TA. Jika sebelumnya sudah verified, tekan Minta Perbaikan pada dokumen itu dan isi alasan yang jelas.
3. Selesaikan verifikasi tujuh dokumen lainnya. Jika masih ada pending atau file hilang, Minta Revisi disabled dan daftar alasan terlihat.
4. Setelah tepat satu rejected dan lainnya verified, isi catatan umum pada keputusan dan tekan Minta Revisi. Status menjadi needs_review, seluruh form mutasi petugas hilang, preview tetap dapat dibuka.

### B. Mahasiswa memperbaiki hanya berkas yang diminta

1. Login **dummy-ta-2@mahasiswa.test**, bukan Krisna role Mahasiswa. Versi melekat pada pengajuan, jadi gunakan pengajuan dummy002 yang sama. Untuk membuka kedua sisi bersamaan, gunakan profil browser terpisah; satu sesi login dipakai bersama antartab.
2. Detail menampilkan tombol Perbaiki Pengajuan. Masuk lalu Mulai atau lanjutkan draft revisi.
3. Card yang diminta diperbaiki memiliki upload aktif. Card lain abu-abu dan tidak menerima klik pilih file/drag-drop. Contoh gambar pada card tetap bisa dibuka di modal.
4. Kontak, tanggal, periode, serta pilihan biodata mengikuti sumber dan tidak bisa diubah. Catatan dokumen sumber tetap terlihat, termasuk setelah file baru dipilih/disimpan.
5. Sebelum menyimpan pengganti, tahap tersebut belum lengkap dan Tinjau belum aktif. Simpan file pengganti; tahap menjadi lengkap. Jika dua berkas diminta, menyimpan satu berkas tetap berhasil tetapi review belum terbuka sampai keduanya lengkap.
6. Tinjau sebelum kirim. Delapan dokumen pengajuan tetap tersedia pada source waiver; berkas yang diganti memakai pilihan baru, yang lain memakai berkas original. Semua lima gate harus terpenuhi untuk mengaktifkan Kirim.
7. Buka modal, coba Periksa kembali, lalu kirim revisi. Pengajuan kembali submitted; tidak membuat pengajuan final terpisah. Draft revisi menjadi consumed.

### C. Petugas meninjau hasil kirim ulang

1. Buka Review Sidang TA lagi. Ikon file yang diganti menjadi pending; ikon tujuh berkas lainnya tetap hijau.
2. Pada detail, warna card dan catatan sesuai: pengganti pending dengan catatan kosong; berkas lain tetap verified. Snapshot akademik tetap historis.
3. Preview file pengganti dan satu file lain. Verifikasi pengganti; Setujui aktif sesudah seluruh dokumen terverifikasi.
4. Jika akan memakai dummy002 untuk checkpoint berikutnya, pertahankan status submitted sampai test deadline selesai. Keputusan final boleh diuji pada dummy lain; jangan memaksa status approved/rejected kembali melalui database.

### D. Pemeriksaan tambahan

- Buka dua tab draft milik dummy002 sebelum menyimpan. Simpan pada tabA; request dari tabB yang masih memakai versi lama ditolak tanpa menimpa file tabA.
- Untuk uji discard tambahan, mulailah dari permintaan revisi yang sah pada dummy lain. Buang draft setelah memilih/menyimpan pengganti; file pengajuan final tetap dapat dibuka. Skenario ini juga tersedia pada unit/Feature test.
- Krisna yang memiliki draft versi1 tetap memakai flow legacy. Itu digunakan untuk regresi sampai fase aktivasi defaultversi2, bukan bug akun.

## 9. Pemindahan setelah lulus

Salin18file dari folder asli ke `dadu-test` dengan path sama:

- `app/Services/SidangTaRegistrationReview.php`
- `app/Services/SidangTaRevisionDraftService.php`
- `app/Support/SidangTaDocumentRequirements.php`
- `app/Http/Requests/SaveSidangTaDraftStepRequest.php`
- `app/Services/SidangTaDraftDocumentService.php`
- `app/Services/SidangTaDraftService.php`
- `app/Services/SidangTaDraftFinalizer.php`
- `app/Http/Controllers/SidangTaDraftController.php`
- `app/Http/Controllers/SidangTaRegistrationController.php`
- `resources/views/sidang_ta_registrations/wizard.blade.php`
- `resources/views/sidang_ta_registrations/partials/wizard-upload.blade.php`
- `resources/views/sidang_ta_registrations/wizard-review.blade.php`
- `resources/views/sidang_ta_registrations/partials/revision-feedback.blade.php`
- `resources/views/sidang_ta_registrations/admin/partials/registration-decision.blade.php`
- `tests/Feature/SidangTaTargetedRevisionTest.php`
- `tests/Feature/SidangTaRequirementsVersionTest.php`
- `tests/Feature/SidangTaMeetFinalizationTest.php`
- `tests/Feature/SidangTaMeetStaffReviewTest.php`

### Command CLI penyalinan18file

Jalankan **setelah test dan manual59 lulus**, dari terminal mana pun. Script memakai Bash sehingga dapat ditempel dari terminal Zsh. Script memeriksa keberadaan seluruh file sumber, menyalin18path yang disebut di atas, lalu membandingkan isinya dengan `cmp`.

```bash
bash <<'BASH'
set -euo pipefail
sidang_ta_source='/home/xdranel/Code/Project/php-laravel/dadu'
sidang_ta_target='/home/xdranel/Code/Project/secret/dadu-test'
sidang_ta_files=(
    'app/Services/SidangTaRegistrationReview.php'
    'app/Services/SidangTaRevisionDraftService.php'
    'app/Support/SidangTaDocumentRequirements.php'
    'app/Http/Requests/SaveSidangTaDraftStepRequest.php'
    'app/Services/SidangTaDraftDocumentService.php'
    'app/Services/SidangTaDraftService.php'
    'app/Services/SidangTaDraftFinalizer.php'
    'app/Http/Controllers/SidangTaDraftController.php'
    'app/Http/Controllers/SidangTaRegistrationController.php'
    'resources/views/sidang_ta_registrations/wizard.blade.php'
    'resources/views/sidang_ta_registrations/partials/wizard-upload.blade.php'
    'resources/views/sidang_ta_registrations/wizard-review.blade.php'
    'resources/views/sidang_ta_registrations/partials/revision-feedback.blade.php'
    'resources/views/sidang_ta_registrations/admin/partials/registration-decision.blade.php'
    'tests/Feature/SidangTaTargetedRevisionTest.php'
    'tests/Feature/SidangTaRequirementsVersionTest.php'
    'tests/Feature/SidangTaMeetFinalizationTest.php'
    'tests/Feature/SidangTaMeetStaffReviewTest.php'
)

test -d "$sidang_ta_source"
test -d "$sidang_ta_target"
for sidang_ta_file in "${sidang_ta_files[@]}"; do
    if [[ ! -f "$sidang_ta_source/$sidang_ta_file" ]]; then
        printf 'File sumber tidak ditemukan: %s\n' "$sidang_ta_file" >&2
        exit 1
    fi
done

for sidang_ta_file in "${sidang_ta_files[@]}"; do
    mkdir -p -- "$sidang_ta_target/$(dirname -- "$sidang_ta_file")"
    cp -- "$sidang_ta_source/$sidang_ta_file" "$sidang_ta_target/$sidang_ta_file"
done

for sidang_ta_file in "${sidang_ta_files[@]}"; do
    if ! cmp -s -- "$sidang_ta_source/$sidang_ta_file" "$sidang_ta_target/$sidang_ta_file"; then
        printf 'Hasil salinan berbeda: %s\n' "$sidang_ta_file" >&2
        exit 1
    fi
done
printf '%s file berhasil disalin dan identik.\n' "${#sidang_ta_files[@]}"
BASH
```

Hasil yang diharapkan: `18 file berhasil disalin dan identik.` Command menyalin file; file sumber tetap tersedia di folder asli. Tidak menjalankan Git commit/push, migrasi, atau perubahan konfigurasi. Asisten menguji script pada direktori fixture di `/tmp`, bukan menjalankannya pada kedua project.

Helper `SidangTaRevisionContract.php` dari58tetap dipakai tanpa perubahan. Tidak ada file yang perlu dihapus. Jangan menyalin `.env`, cache, atau mengubah pengaturan testing sementara untuk checkpoint ini.

## 10. Pemeriksaan dan batas hasil asisten

Source Sidang TA antarfolder dibandingkan sebelum pengerjaan; tidak ada perbedaan yang ditemukan. Proposal dikerjakan di `/tmp`. Tiga belas file PHP lengkap lolos lint; lima Blade dikompilasi dalam memori. Probe render140assertion wizard/review plus22beranda/panel lulus dengan helper/storage/context stub. Review fixture, urutan tahap, transaksi, dan19kasus baru sudah dilakukan. Pemeriksaan awal tersebut belum menangkap ketidaksesuaian test/selector pada panduan; koreksi setelah laporan pengguna beserta pemeriksaan terarah dicatat pada bagian11.

Feature MySQL, build, dan browser tidak dijalankan asisten; target86/349 harus dibuktikan oleh pengujian pengguna. Hash app/resources/routes/tests/database/migrations/config pada kedua folder diperiksa sebelum/sesudah perubahan docs. Source aplikasi tetap tidak diubah asisten.

Berikutnya60: deadline revisi, penolakan otomatis/idempotent, dan write guard setelah tenggat. Setelah itu aktivasi defaultversi2 untuk pendaftaran baru serta penanganan draft awal versi1 agar tidak melewati gate meet, lalu audit penutupan/commit. Fitur meet belum dinyatakan selesai pada59.


## 11. Koreksi tiga test gagal setelah penerapan59

### Hasil rescan sebelum koreksi

Backend dan manual pengguna sudah menjalankan alur revisi. Kegagalan yang dilaporkan sesuai tiga ketidaksesuaian berikut:

1. Test staff yang masih bernama `meet_staff_revision_request_is_still_held_without_mutating_any_saved_feedback` mengharapkan error `action`, sesuai penahanan fase57. Pada59 sudah ada satu rejected, sehingga penolakan terjadi karena dokumen lainnya belum verified: error berada pada `documents.*`.
2. Test layout masih mencari `data-meet-revision-held`, padahal penahanan sementara sudah diganti dengan alasan rencana review yang belum lengkap.
3. View pada panduan awal memakai `data-meet-revision-blockers`, sementara satu test memakai `data-ta-revision-blockers`. Ini ketidakkonsistenan panduan. Selector yang dipakai sekarang adalah **`data-ta-revision-blockers`** pada seluruh test dan view59.

Instruksi `selector_only_in_...` sebelumnya keliru: itu label internal patch, bukan nama test sungguhan. Kode satu baris tersebut tidak dapat menggantikan seluruh test. Bagian4 sudah diperbaiki; di bawah ini tiga test dituliskan lengkap agar koreksi dapat diterapkan langsung.

### A. View keputusan petugas — satu penggantian atribut

Pada **`resources/views/sidang_ta_registrations/admin/partials/registration-decision.blade.php`**, cari:

```blade
<div data-meet-revision-blockers role="status" class="rounded-xl bg-amber-50 p-3 text-xs text-amber-900">
```

Ganti dengan:

```blade
<div data-ta-revision-blockers role="status" class="rounded-xl bg-amber-50 p-3 text-xs text-amber-900">
```

Kode lengkap view pada bagian6 juga sudah memakai atribut yang benar. Tidak perlu mengubah controller, service, atau aturan revisi untuk koreksi selector ini.

### B. `tests/Feature/SidangTaMeetStaffReviewTest.php`

Ganti test `meet_staff_revision_request_is_still_held_without_mutating_any_saved_feedback` dengan kode berikut. Jika sudah memakai nama `meet_staff_revision_request_requires_review_of_the_remaining_documents`, ganti test bernama baru itu. Pertahankan hanya satu deklarasi untuk kasus ini.

```php
test('meet_staff_revision_request_requires_review_of_the_remaining_documents', function () {
    $registration = ($this->makeMeetRegistration)();
    $document = $registration->documents()->where('document_type', 'ta_data_capture')->firstOrFail();
    ($this->reviewDocument)($registration, $document, ['status' => 'rejected', 'notes' => 'Perbaiki scan'])->assertOk();
    $response = ($this->decideMeet)($registration, 'needs_review')->assertUnprocessable()
        ->assertJsonValidationErrors(['documents.online_supervision_capture', 'documents.thesis_draft']);
    expect(array_key_exists('action', $response->json('errors')))->toBeFalse();
    expect($registration->fresh()->status)->toBe('submitted');
    expect($registration->fresh()->lock_version)->toBe(2);
    expect($document->fresh()->notes)->toBe('Perbaiki scan');
    expect($registration->logs()->count())->toBe(1);
});
```

### C. `tests/Feature/SidangTaMeetStaffReviewTest.php`

Ganti seluruh test `meet_staff_detail_groups_uploads_into_two_columns_with_status_colors_and_disabled_verify` dengan kode lengkap berikut.

```php
test('meet_staff_detail_groups_uploads_into_two_columns_with_status_colors_and_disabled_verify', function () {
    $registration = ($this->makeMeetRegistration)('waiver');
    $verified = $registration->documents()->where('document_type', 'ta_data_capture')->firstOrFail();
    $rejected = $registration->documents()->where('document_type', 'online_supervision_capture')->firstOrFail();
    $verified->update(['status' => 'verified']);
    $rejected->update(['status' => 'rejected', 'notes' => 'Perbaiki']);
    $crawler = ($this->reviewPage)($registration);
    expect($crawler->filter('[data-meet-document-group]')->count())->toBe(3);
    expect($crawler->filter('[data-meet-document-grid]')->count())->toBe(3);
    foreach ($crawler->filter('[data-meet-document-grid]') as $node) {
        expect($node->getAttribute('class'))->toContain('lg:grid-cols-2');
    }
    expect($crawler->filter('[data-meet-document-group="2"] [data-ta-detail-document]')->count())->toBe(2);
    expect($crawler->filter('[data-meet-document-group="3"] [data-ta-detail-document]')->count())->toBe(4);
    expect($crawler->filter('[data-meet-document-group="4"] [data-ta-detail-document]')->count())->toBe(2);
    $green = $crawler->filter('#review-document-'.$verified->id);
    $yellow = $crawler->filter('#review-document-'.$rejected->id);
    expect($green->attr('class'))->toContain('bg-emerald-50')->toContain('text-emerald-900');
    expect($yellow->attr('class'))->toContain('bg-amber-50')->toContain('text-amber-900');
    expect($green->filter('[data-doc-verify]')->getNode(0)->hasAttribute('disabled'))->toBeTrue();
    expect($green->filter('[data-doc-reject]')->getNode(0)->hasAttribute('disabled'))->toBeFalse();
    expect($yellow->filter('[data-doc-verify]')->getNode(0)->hasAttribute('disabled'))->toBeFalse();
    expect($crawler->filter('[data-ta-request-revision]')->getNode(0)->hasAttribute('disabled'))->toBeTrue();
    expect($crawler->filter('[data-ta-revision-blockers]')->count())->toBe(1);
    expect($crawler->filter('[data-ta-revision-blockers]')->text(null, true))->toContain('Selesaikan verifikasi');
    expect($crawler->filter('[data-ta-review-window]')->text(null, true))->toContain($this->period->close_at->format('d/m/Y H:i'));
    expect($crawler->filter('[data-staff-registration-history]')->getNode(0)->hasAttribute('open'))->toBeFalse();
});
```

### D. `tests/Feature/SidangTaMeetFinalizationTest.php`

Ganti seluruh test `meet_final_staff_can_read_export_and_verify_while_revision_requires_document_feedback` dengan kode lengkap berikut.

```php
test('meet_final_staff_can_read_export_and_verify_while_revision_requires_document_feedback', function () {
    $draft = ($this->markReviewed)(($this->meetDraft)('waiver'));
    $id = ($this->submitMeet)($draft)->assertOk()->json('data.id');
    $registration = SidangTaRegistration::findOrFail($id);
    $snapshot = $registration->academic_snapshot;
    ($this->asReviewer)();
    $crawler = new Crawler($this->get(route('admin.sidang-ta-registrations.show', $registration))->assertOk()->getContent());
    expect($crawler->filter('[data-meet-review-held]')->count())->toBe(0);
    expect($crawler->filter('[data-ta-revision-blockers]')->count())->toBe(1);
    expect($crawler->filter('[data-ta-revision-blockers]')->text(null, true))->toContain('Tandai minimal satu dokumen');
    expect($crawler->filter('[data-ta-request-revision]')->getNode(0)->hasAttribute('disabled'))->toBeTrue();
    expect($crawler->filter('[data-doc-review-form]')->count())->toBe(8);
    expect($crawler->filter('form[action="'.route('admin.sidang-ta-registrations.review', $registration).'"]')->count())->toBe(1);
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
    ]), ['registration_version' => $registration->lock_version, 'status' => 'verified'])->assertOk();
    $this->postJson(route('admin.sidang-ta-registrations.review', $registration), [
        'registration_version' => $registration->fresh()->lock_version, 'action' => 'needs_review', 'review_notes' => 'Perbaiki',
    ])->assertUnprocessable()->assertJsonValidationErrors('action');
    expect($waiver->fresh()->status)->toBe('verified');
    expect($registration->fresh()->status)->toBe('submitted');
    expect($registration->fresh()->academic_snapshot)->toBe($snapshot);
    expect($registration->logs()->count())->toBe(2);
});
```

### E. Jalankan ulang setelah tiga file di atas diperbaiki

Dari folder asli, bersihkan cache view:

```bash
php artisan view:clear
```

Targeted tiga kasus:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaMeetStaffReviewTest.php tests/Feature/SidangTaMeetFinalizationTest.php --filter='meet_staff_revision_request_requires_review_of_the_remaining_documents|meet_staff_detail_groups_uploads_into_two_columns_with_status_colors_and_disabled_verify|meet_final_staff_can_read_export_and_verify_while_revision_requires_document_feedback'
```
Target3passed. Selanjutnya ulangi fokus yang Anda jalankan:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaTargetedRevisionTest.php tests/Feature/SidangTaRevisionContractTest.php tests/Feature/SidangTaMeetStaffReviewTest.php tests/Feature/SidangTaMeetFinalizationTest.php tests/Feature/SidangTaRequirementsVersionTest.php
```
Target tetap **86 passed**. Tidak ada penambahan/pengurangan test, hanya perbaikan assertion dan selector. Lanjutkan command seluruh Sidang TA pada bagian7D (target349 bila baseline330), kemudian jalankan command salin18file pada bagian9. Hasil manual yang sudah berhasil tidak perlu diulang seluruhnya untuk perubahan atribut ini; cek panel alasan Minta Revisi tetap tampil ketika review belum lengkap.

### F. Bukti pemeriksaan koreksi oleh asisten

Dua file test lengkap lolos PHP lint. Probe tanpa DB memakai kontrak asli dengan pembacaan dokumen yang dimock: setelah ada rejected, error dokumen lainnya tersedia dan tidak ada error action; ketika belum ada rejected, error action memang muncul. Probe render Blade aktual memeriksa selector baru, keadaan tombol, dan fallback yang tetap terkunci. Total17pemeriksaan lulus.

Script penyalinan diuji pada18file fixture di `/tmp`; seluruh file hasil salinan identik. Skenario sumber hilang berhenti sebelum ada file tujuan yang disalin. Script belum dijalankan terhadap project asli/workspace oleh asisten.

Feature MySQL belum dijalankan asisten; hasil86/349 perlu dibuktikan oleh pengujian Anda. Rescan hash sebelum/sesudah perubahan docs memastikan source aplikasi kedua folder tidak berubah oleh asisten. Koreksi ini tetap memakai daftar18file yang sama; tahap deadline60 menunggu checkpoint59 kembali lulus.
