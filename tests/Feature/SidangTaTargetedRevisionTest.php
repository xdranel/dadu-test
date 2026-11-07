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
                'graduation_payment_date' => today()->toDateString(), 'diploma_biodata_accurate' => $accurate,
                'pdm_submission_date' => $accurate ? null : today()->toDateString()]]);
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
            'draft_id' => $draft->id, 'version' => $version ?? $draft->fresh()->version, 'action' => 'submit', 'declaration_accepted' => true,
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
            'draft_id' => $draft->id, 'version' => $draft->fresh()->version, 'action' => 'submit', 'declaration_accepted' => true,
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
    expect($progress['stored_documents'])->toBe(3);
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

test('targeted_revision_allows_contact_correction_but_freezes_payment_date_biodata_and_period_on_server', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source, ['graduation_payment_proof', 'diploma_statement']);
    $payload = $draft->payload;
    $paths = Storage::disk('local')->allFiles();
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 1]), [
        'version' => $draft->version, 'sidang_ta_period_id' => $source->sidang_ta_period_id,
        'student_phone' => '089999999999', 'parent_phone' => $payload['parent_phone'],
    ])->assertOk();
    $draft->refresh();
    $payload = $draft->payload;
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 3]), [
        'version' => $draft->version, 'graduation_payment_date' => today()->subDay()->toDateString(),
    ])->assertUnprocessable()->assertJsonValidationErrors('graduation_payment_date');
    $otherPeriod = SidangTaPeriod::create(['name' => 'Other', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay()]);
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 1]), [
        'version' => $draft->version, 'sidang_ta_period_id' => $otherPeriod->id,
        'student_phone' => $payload['student_phone'], 'parent_phone' => $payload['parent_phone'],
    ])->assertUnprocessable()->assertJsonValidationErrors('sidang_ta_period_id');
    expect($draft->fresh()->version)->toBe(2);
    expect(Storage::disk('local')->allFiles())->toBe($paths);
    ($this->replaceDocument)($draft, 'graduation_payment_proof', 3)->assertOk();
    $paths = Storage::disk('local')->allFiles();
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 4]), [
        'version' => $draft->fresh()->version, 'diploma_biodata_accurate' => false,
    ])->assertUnprocessable()->assertJsonValidationErrors('diploma_biodata_accurate');
    expect($draft->fresh()->payload)->toBe($payload);
    expect($draft->fresh()->version)->toBe(3);
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

test('targeted_revision_uses_live_eprt_contract_and_snapshots_only_successful_resubmission', function (string $originalMode, string $liveMode) {
    $source = ($this->submittedSource)($originalMode);
    $draft = ($this->prepareRevision)($source);
    $snapshot = $source->fresh()->academic_snapshot;
    ($this->setMode)($liveMode);
    expect(\App\Support\SidangTaDocumentRequirements::typesForDraftStep($draft, 3))
        ->toBe($liveMode === 'waiver' ? ['graduation_payment_proof', 'eprt_waiver_letter'] : ['graduation_payment_proof']);
    ($this->replaceDocument)($draft)->assertOk();
    if ($liveMode === 'waiver') ($this->replaceDocument)($draft, Meet::WAIVER, 3)->assertOk();
    expect($source->fresh()->academic_snapshot)->toBe($snapshot);
    $draft = ($this->markReviewed)($draft);
    if ($liveMode === 'unknown') {
        ($this->submitRevision)($source, $draft)->assertUnprocessable()->assertJsonValidationErrors('eligibility.eprt');
        expect($source->fresh()->academic_snapshot)->toBe($snapshot);
    } else {
        ($this->submitRevision)($source, $draft)->assertOk();
        expect($source->fresh()->academic_snapshot['admission']['eprt']['mode'])->toBe($liveMode);
        expect($source->documents()->count())->toBe($liveMode === 'waiver' ? 8 : 7);
    }
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

test('targeted_revision_wizard_allows_WA_edits_and_preserves_locked_data_and_documents', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source);
    \App\Models\SidangTaFormGuide::create(['slot'=>'online_supervision_capture', 'title'=>'Panduan bimbingan', 'is_active'=>true]);
    $step1 = new Crawler($this->get(route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 1]))->assertOk()->getContent());
    expect($step1->filter('input[name="student_phone"]')->getNode(0)->hasAttribute('readonly'))->toBeFalse();
    expect($step1->filter('input[name="parent_phone"]')->getNode(0)->hasAttribute('readonly'))->toBeFalse();
    $step2 = new Crawler($this->get(route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 2]))->assertOk()->getContent());
    $locked = $step2->filter('[data-upload-type="online_supervision_capture"]');
    expect($locked->attr('data-upload-locked'))->toBe('true');
    expect($locked->filter('input[type="file"]')->count())->toBe(0);
    expect($locked->filter('[data-form-guide="online_supervision_capture"]')->count())->toBe(1);
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
    expect($crawler->filter('[data-confirm-registration]')->getNode(0)->hasAttribute('disabled'))->toBeTrue();
    expect($crawler->filter('[data-confirm-registration]')->attr(':disabled'))->toBe('!canConfirm');
    StudentTak::where('student_number', $this->student->student_number)->update(['total_points' => 59]);
    $crawler = new Crawler($this->get(route('sidang-ta-drafts.preview', $draft))->assertOk()->getContent());
    expect($crawler->filter('[data-open-registration]')->getNode(0)->hasAttribute('disabled'))->toBeTrue();
});

test('targeted_revision_can_save_one_of_two_requested_files_without_unlocking_review', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source, ['ta_data_capture', 'online_supervision_capture']);
    ($this->replaceDocument)($draft)->assertOk();
    $progress = \App\Support\SidangTaDocumentRequirements::progress($draft->fresh(), 2);
    expect($progress['stored_documents'])->toBe(3);
    expect($progress['complete'])->toBeFalse();
    $this->postJson(route('sidang-ta-drafts.review', $draft), ['version' => $draft->fresh()->version])
        ->assertUnprocessable()->assertJsonValidationErrors('draft');
    ($this->replaceDocument)($draft, 'online_supervision_capture')->assertOk();
    $draft = ($this->markReviewed)($draft);
    ($this->submitRevision)($source, $draft)->assertOk();
    expect($source->documents()->where('status', 'pending')->count())->toBe(2);
    expect($source->documents()->where('status', 'verified')->count())->toBe(6);
});
