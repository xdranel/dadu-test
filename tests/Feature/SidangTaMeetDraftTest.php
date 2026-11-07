<?php

use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDraft;
use App\Models\Student;
use App\Models\StudentEprt;
use App\Models\User;
use App\Services\SidangTaAdmissionFacts;
use App\Support\SidangTaDocumentRequirements as Requirements;
use App\Support\SidangTaMeetRequirements as Meet;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->travelTo(now()->startOfDay());
    Storage::fake('local');
    $this->user = User::factory()->create(['username' => '1202299054', 'email' => 'meet-draft@test.local',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
    $this->student = Student::create(['student_number' => $this->user->username, 'name' => 'Meet Draft',
        'email' => $this->user->email, 'academic_year' => 2022, 'study_program' => 'Informatika', 'status' => 'Aktif']);
    foreach (['read', 'create', 'update'] as $action) {
        $permission = Permission::firstOrCreate(['name' => 'sidang_ta_registration_'.$action, 'guard_name' => 'web']);
        $this->user->givePermissionTo($permission);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($this->user);
    $this->period = SidangTaPeriod::create(['name' => 'Meet Draft', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay()]);
    $this->setEprtMode = function (string $mode) {
        StudentEprt::where('student_number', $this->student->student_number)->delete();
        if ($mode === 'unknown') return;
        $count = $mode === 'waiver' ? 3 : ($mode === 'retest' ? 2 : 1);
        foreach (range(1, $count) as $attempt) {
            StudentEprt::create(['student_number' => $this->student->student_number,
                'total_score' => $mode === 'automatic' ? 450 : 449, 'attempt_number' => $attempt,
                'test_date' => today()->subDays(4 - $attempt), 'category' => null]);
        }
    };
    $this->newMeetDraft = function (string $mode = 'automatic') {
        ($this->setEprtMode)($mode);
        $id = $this->postJson(route('sidang-ta-drafts.store'), ['sidang_ta_period_id' => $this->period->id,
            'student_phone' => '081234567890', 'parent_phone' => '081234567891'])->assertCreated()->json('data.id');
        $draft = SidangTaRegistrationDraft::findOrFail($id);
        expect($draft->requirements_version)->toBe(2);
        return $draft->fresh();
    };
    $this->uploadStep = function ($draft, int $step, array $types, array $extra = []) {
        $files = [];
        foreach ($types as $type) $files[$type] = UploadedFile::fake()->create($type.'.pdf', 100, 'application/pdf');
        return $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => $step]),
            array_replace(['version' => $draft->fresh()->version, 'documents' => $files], $extra));
    };
    $this->finishMeetDraft = function (string $mode = 'automatic', bool $accurate = true) {
        $draft = ($this->newMeetDraft)($mode);
        ($this->uploadStep)($draft, 2, Meet::CAPTURES)->assertOk();
        ($this->uploadStep)($draft, 3, Meet::typesForStep(3, $accurate, $mode),
            ['graduation_payment_date' => today()->toDateString()])->assertOk();
        ($this->uploadStep)($draft, 4, Meet::typesForStep(4, $accurate, $mode),
            ['diploma_biodata_accurate' => $accurate, 'pdm_submission_date' => $accurate ? null : today()->toDateString()])->assertOk();
        return $draft->fresh();
    };
});

afterEach(function () { $this->travelBack(); });

test('meet_v2_partial_stage_two_upload_is_saved_and_next_stage_is_locked', function () {
    $draft = ($this->newMeetDraft)();
    ($this->uploadStep)($draft, 2, ['ta_data_capture'])->assertOk();
    $progress = Requirements::progress($draft->fresh(), 2);
    expect($progress['required_documents'])->toBe(4);
    expect($progress['stored_documents'])->toBe(1);
    expect($progress['complete'])->toBeFalse();
    expect($draft->fresh()->version)->toBe(2);
    expect($draft->documents()->count())->toBe(1);
    ($this->uploadStep)($draft, 3, ['graduation_payment_proof'])->assertConflict();
    expect($draft->documents()->count())->toBe(1);
});

test('meet_v2_stage_two_rejects_removed_capture_without_writing_a_file', function () {
    $draft = ($this->newMeetDraft)();
    foreach (['level_2_result_capture', 'igracias_approval_capture', 'ta_progress_capture', 'skpi_tak_capture'] as $type) {
        ($this->uploadStep)($draft, 2, [$type])->assertUnprocessable()->assertJsonValidationErrors('documents');
    }
    expect($draft->fresh()->version)->toBe(1);
    expect($draft->documents()->count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

test('meet_v2_stage_three_can_save_partial_pdf_with_missing_payment_date', function () {
    $draft = ($this->newMeetDraft)();
    ($this->uploadStep)($draft, 2, Meet::CAPTURES)->assertOk();
    ($this->uploadStep)($draft, 3, ['graduation_payment_proof'])->assertOk();
    $progress = Requirements::progress($draft->fresh(), 3);
    expect($progress['required_documents'])->toBe(1);
    expect($progress['stored_documents'])->toBe(1);
    expect($progress['complete'])->toBeFalse();
    expect($draft->documents()->where('document_type', 'graduation_payment_proof')->exists())->toBeTrue();
});

test('meet_v2_waiver_mode_requires_the_fourth_stage_three_pdf', function () {
    $draft = ($this->newMeetDraft)('waiver');
    ($this->uploadStep)($draft, 2, Meet::CAPTURES)->assertOk();
    ($this->uploadStep)($draft, 3, Meet::PAYMENT, ['graduation_payment_date' => today()->toDateString()])->assertOk();
    $progress = Requirements::progress($draft->fresh(), 3);
    expect($progress['required_documents'])->toBe(2);
    expect($progress['stored_documents'])->toBe(1);
    expect($progress['complete'])->toBeFalse();
    expect($progress['missing'])->toContain('Surat Permakluman EPRT');
    ($this->uploadStep)($draft, 3, [Meet::WAIVER])->assertOk();
    expect(Requirements::progress($draft->fresh(), 3)['complete'])->toBeTrue();
});

test('meet_v2_waiver_is_pdf_only_and_removed_stage_three_uploads_are_rejected', function () {
    $draft = ($this->newMeetDraft)('waiver');
    ($this->uploadStep)($draft, 2, Meet::CAPTURES)->assertOk();
    $version = $draft->fresh()->version;
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 3]), [
        'version' => $version, 'documents' => [Meet::WAIVER => UploadedFile::fake()->create('gambar.png', 100, 'image/png')],
    ])->assertUnprocessable()->assertJsonValidationErrors('documents.'.Meet::WAIVER);
    foreach (['eprt_lac_certificate', 'bpp_payment_proof', 'transcript_khs', 'ta_course_ksm'] as $type) {
        ($this->uploadStep)($draft, 3, [$type])->assertUnprocessable()->assertJsonValidationErrors('documents');
    }
    expect($draft->fresh()->version)->toBe($version);
    expect($draft->documents()->count())->toBe(4);
});

test('meet_v2_retest_or_unknown_academics_do_not_lock_draft_or_review', function () {
    foreach (['retest', 'unknown'] as $mode) {
        $draft = ($this->finishMeetDraft)($mode);
        expect(app(SidangTaAdmissionFacts::class)->forStudent($this->student)['academic']['academic_passed'])->toBeFalse();
        expect(Requirements::firstIncompleteStep($draft))->toBeNull();
        $this->postJson(route('sidang-ta-drafts.review', $draft), ['version' => $draft->version])->assertOk();
        expect($draft->fresh()->reviewed_version)->toBe($draft->version);
        expect($draft->documents()->count())->toBe(7);
        // Discard before the next scenario; no final registration is created in this test.
        $this->deleteJson(route('sidang-ta-drafts.destroy', $draft), ['version' => $draft->version])->assertNoContent();
    }
});

test('meet_v2_pdm_branch_requires_only_one_stage_four_pdf', function () {
    $draft = ($this->finishMeetDraft)('automatic', false);
    ($this->uploadStep)($draft, 4, Meet::DIPLOMA, ['diploma_biodata_accurate' => false])
        ->assertUnprocessable()->assertJsonValidationErrors('documents');
    $progress = Requirements::progress($draft->fresh(), 4);
    expect($progress['required_documents'])->toBe(1);
    expect($progress['complete'])->toBeTrue();
    expect($draft->documents()->count())->toBe(6);
    expect($draft->documents()->whereIn('document_type', Meet::DIPLOMA)->exists())->toBeFalse();
});

test('meet_v2_new_waiver_requirement_after_review_blocks_review_again_until_uploaded', function () {
    $draft = ($this->finishMeetDraft)();
    $this->postJson(route('sidang-ta-drafts.review', $draft), ['version' => $draft->version])->assertOk();
    $paths = Storage::disk('local')->allFiles();
    ($this->setEprtMode)('waiver');
    expect(Requirements::firstIncompleteStep($draft->fresh()))->toBe(3);
    $this->postJson(route('sidang-ta-drafts.review', $draft), ['version' => $draft->version])
        ->assertUnprocessable()->assertJsonValidationErrors('draft');
    expect(Storage::disk('local')->allFiles())->toBe($paths);
    ($this->uploadStep)($draft, 3, [Meet::WAIVER])->assertOk();
    expect($draft->fresh()->reviewed_version)->toBeNull();
    expect(Requirements::firstIncompleteStep($draft->fresh()))->toBeNull();
});

test('meet_v2_uncertain_or_retest_eprt_keeps_previously_uploaded_waiver', function () {
    $draft = ($this->finishMeetDraft)('waiver');
    $path = $draft->documents()->where('document_type', Meet::WAIVER)->firstOrFail()->file_path;
    foreach (['unknown', 'retest'] as $mode) {
        ($this->setEprtMode)($mode);
        ($this->uploadStep)($draft, 3, [])->assertOk();
        Storage::disk('local')->assertExists($path);
        expect($draft->documents()->where('document_type', Meet::WAIVER)->exists())->toBeTrue();
    }
    ($this->setEprtMode)('waiver');
    expect(Requirements::progress($draft->fresh(), 3)['complete'])->toBeTrue();
    expect($draft->documents()->count())->toBe(8);
});

test('meet_v2_obsolete_waiver_is_retired_only_after_explicit_save_and_other_files_remain', function () {
    $draft = ($this->finishMeetDraft)('waiver');
    $waiver = $draft->documents()->where('document_type', Meet::WAIVER)->firstOrFail();
    $keep = $draft->documents()->where('document_type', 'thesis_draft')->firstOrFail()->file_path;
    ($this->setEprtMode)('automatic');
    Requirements::progress($draft->fresh(), 3);
    Storage::disk('local')->assertExists($waiver->file_path);
    ($this->uploadStep)($draft, 3, [])->assertOk();
    expect($draft->documents()->where('document_type', Meet::WAIVER)->exists())->toBeFalse();
    Storage::disk('local')->assertMissing($waiver->file_path);
    Storage::disk('local')->assertExists($keep);
    expect($draft->documents()->count())->toBe(7);
});

test('meet_v2_stale_upload_does_not_change_version_or_store_selected_file', function () {
    $draft = ($this->newMeetDraft)();
    ($this->uploadStep)($draft, 2, ['ta_data_capture'])->assertOk();
    $paths = Storage::disk('local')->allFiles();
    ($this->uploadStep)($draft, 2, ['online_supervision_capture'], ['version' => 1])->assertConflict();
    expect($draft->fresh()->version)->toBe(2);
    expect($draft->documents()->count())->toBe(1);
    expect(Storage::disk('local')->allFiles())->toBe($paths);
});

test('meet_v2_missing_saved_capture_keeps_the_stage_incomplete_without_losing_other_files', function () {
    $draft = ($this->finishMeetDraft)();
    $document = $draft->documents()->where('document_type', 'ta_data_capture')->firstOrFail();
    Storage::disk('local')->delete($document->file_path);
    $progress = Requirements::progress($draft->fresh(), 2);
    expect($progress['required_documents'])->toBe(4);
    expect($progress['stored_documents'])->toBe(3);
    expect($progress['complete'])->toBeFalse();
    expect(Requirements::firstIncompleteStep($draft->fresh()))->toBe(2);
    expect($draft->documents()->count())->toBe(7);
});

test('meet_v2_missing_academic_facts_block_submit_without_consuming_the_saved_reviewed_draft', function () {
    $draft = ($this->finishMeetDraft)();
    $this->postJson(route('sidang-ta-drafts.review', $draft), ['version' => $draft->version])->assertOk();
    $paths = Storage::disk('local')->allFiles();
    $this->postJson(route('sidang-ta-registrations.store'), ['draft_id' => $draft->id,
        'version' => $draft->version, 'action' => 'submit', 'declaration_accepted' => true])->assertUnprocessable()
        ->assertJsonValidationErrors(['eligibility.level_2_passed', 'eligibility.ta2_registered',
            'eligibility.skpi_approved', 'eligibility.tak_score', 'eligibility.passed_credits']);
    expect($draft->fresh()->status)->toBe('active');
    expect($draft->fresh()->reviewed_version)->toBe($draft->version);
    expect($draft->documents()->count())->toBe(7);
    expect(SidangTaRegistration::count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBe($paths);
});

test('meet_v2_current_consumed_replay_returns_saved_registration_before_fresh_admission_checks', function () {
    $draft = ($this->finishMeetDraft)();
    $registration = SidangTaRegistration::create(['sidang_ta_period_id' => $this->period->id,
        'requirements_version' => 2, 'student_number' => $this->student->student_number,
        'student_name' => $this->student->name, 'student_phone' => '081234567890', 'parent_phone' => '081234567891',
        'graduation_payment_date' => today()->toDateString(), 'diploma_biodata_accurate' => true, 'status' => 'approved']);
    $draft->update(['requirements_version' => 2, 'status' => 'consumed', 'submitted_registration_id' => $registration->id]);
    $paths = Storage::disk('local')->allFiles();
    $this->postJson(route('sidang-ta-registrations.store'), ['draft_id' => $draft->id,
        'version' => $draft->version, 'action' => 'submit', 'declaration_accepted' => true])->assertOk()
        ->assertJsonPath('data.id', $registration->id)->assertJsonPath('data.status', 'approved');
    expect($registration->logs()->count())->toBe(0);
    expect($registration->fresh()->requirements_version)->toBe(2);
    expect(Storage::disk('local')->allFiles())->toBe($paths);
});
