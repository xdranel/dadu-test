<?php

use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDocument;
use App\Models\SidangTaRegistrationDraft;
use App\Models\Student;
use App\Models\User;
use App\Services\SidangTaDraftFinalizer;
use App\Support\SidangTaDocumentRequirements as Requirements;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create(['username' => '1202200001',
        'email' => 'final@mahasiswa.test', 'status' => 'active',
        'auth_source' => 'manual', 'must_change_password' => false]);
    $this->student = Student::create(['student_number' => '1202200001',
        'name' => 'Final Student', 'email' => $this->user->email, 'academic_year' => 2022,
        'study_program' => 'Informatika', 'status' => 'Aktif']);
    foreach (['read', 'create', 'update', 'review'] as $action) {
        $this->user->givePermissionTo(Permission::firstOrCreate([
            'name' => "sidang_ta_registration_{$action}", 'guard_name' => 'web',
        ]));
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->period = SidangTaPeriod::create(['name' => 'Final Test', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay()]);
    $this->actingAs($this->user);
    $this->makeDraft = function (bool $accurate = true) {
        $id = $this->postJson(route('sidang-ta-drafts.store'), [
            'sidang_ta_period_id' => $this->period->id,
            'student_phone' => '081234567890', 'parent_phone' => '081234567891',
        ])->assertCreated()->json('data.id');
        $draft = SidangTaRegistrationDraft::findOrFail($id);
        $draft->payload = array_replace($draft->payload, [
            'graduation_payment_date' => today()->toDateString(), 'diploma_biodata_accurate' => $accurate,
        ]);
        $draft->save();
        foreach (array_merge(Requirements::CAPTURES, Requirements::PAYMENT,
            Requirements::typesForStep(4, $accurate)) as $type) {
            $path = "sidang_ta/drafts/{$draft->id}/{$type}.pdf";
            Storage::disk('local')->put($path, '%PDF-test');
            $draft->documents()->create(['document_type' => $type, 'file_name' => $type.'.pdf',
                'file_path' => $path, 'file_type' => 'pdf', 'file_size' => 10]);
        }
        $this->postJson(route('sidang-ta-drafts.review', $draft), ['version' => 1])->assertOk();
        return $draft->fresh();
    };
    $this->submitDraft = function ($draft, array $extra = []) {
        return $this->postJson(route('sidang-ta-registrations.store'), array_replace([
            'draft_id' => $draft->id, 'version' => $draft->version, 'action' => 'submit',
        ], $extra));
    };
    $this->makeRevision = function () {
        $draft = ($this->makeDraft)();
        $id = ($this->submitDraft)($draft)->assertOk()->json('data.id');
        $registration = SidangTaRegistration::findOrFail($id);
        $this->postJson(route('admin.sidang-ta-registrations.review', $registration), [
            'registration_version' => 1, 'action' => 'needs_review', 'review_notes' => 'Perbaiki dokumen.',
        ])->assertRedirect();
        $revisionId = $this->postJson(route('sidang-ta-drafts.revision', $registration))
            ->assertCreated()->json('data.id');
        $this->postJson(route('sidang-ta-drafts.review', $revisionId), ['version' => 1])->assertOk();
        return [$registration->fresh(), SidangTaRegistrationDraft::findOrFail($revisionId)];
    };
});

test('submits_fifteen_documents_without_reupload_and_consumes_metadata', function () {
    $draft = ($this->makeDraft)();
    $paths = Storage::disk('local')->allFiles();
    $id = ($this->submitDraft)($draft)->assertOk()->json('data.id');
    $registration = SidangTaRegistration::findOrFail($id);
    expect($registration->documents()->count())->toBe(15);
    expect($registration->academic_snapshot['student_name'])->toBe('Final Student');
    expect($registration->student_number)->toBe($this->student->student_number);
    expect($registration->lock_version)->toBe(1);
    expect($registration->logs()->count())->toBe(1);
    expect($draft->fresh()->status)->toBe('consumed');
    expect($draft->documents()->count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBe($paths);
});

test('submits_fourteen_documents_for_pdm_branch', function () {
    $draft = ($this->makeDraft)(false);
    $id = ($this->submitDraft)($draft)->assertOk()->json('data.id');
    $registration = SidangTaRegistration::findOrFail($id);
    expect($registration->documents()->count())->toBe(14);
    expect($registration->documents()->whereIn('document_type', Requirements::DIPLOMA)->exists())->toBeFalse();
});

test('repeated_submit_keeps_same_result_and_does_not_overwrite_review', function () {
    $draft = ($this->makeDraft)();
    $id = ($this->submitDraft)($draft)->assertOk()->json('data.id');
    $registration = SidangTaRegistration::findOrFail($id);
    $registration->documents()->update(['status' => 'verified']);
    $this->postJson(route('admin.sidang-ta-registrations.review', $registration), [
        'registration_version' => 1, 'action' => 'approved',
    ])->assertRedirect();
    ($this->submitDraft)($draft)->assertOk()->assertJsonPath('data.id', $id)
        ->assertJsonPath('data.status', 'approved');
    expect(SidangTaRegistration::count())->toBe(1);
    expect($registration->logs()->where('action', 'submitted')->count())->toBe(1);
});


test('rejects_unreviewed_stale_or_missing_file_submission', function () {
    $draft = ($this->makeDraft)();
    ($this->submitDraft)($draft, ['version' => 99])->assertStatus(409);
    $draft->reviewed_version = null;
    $draft->save();
    ($this->submitDraft)($draft)->assertStatus(409);
    $this->postJson(route('sidang-ta-drafts.review', $draft), ['version' => 1])->assertOk();
    Storage::disk('local')->delete($draft->documents()->first()->file_path);
    ($this->submitDraft)($draft)->assertUnprocessable();
    expect(SidangTaRegistration::count())->toBe(0);
    expect($draft->fresh()->status)->toBe('active');
});

test('legacy_payload_and_upload_cannot_bypass_final_request', function () {
    $this->postJson(route('sidang-ta-registrations.store'), [
        'student_phone' => '081234567890', 'parent_phone' => '081234567891',
    ])->assertUnprocessable()->assertJsonValidationErrors(['draft_id', 'action']);
    $draft = ($this->makeDraft)();
    ($this->submitDraft)($draft, ['action' => 'draft'])->assertUnprocessable();
    ($this->submitDraft)($draft, ['student_number' => '999', 'status' => 'approved'])
        ->assertUnprocessable();
    ($this->submitDraft)($draft, ['unexpected_file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])
        ->assertUnprocessable()->assertJsonValidationErrors('documents');
    expect(SidangTaRegistration::count())->toBe(0);
});

test('rechecks_period_after_review', function () {
    $draft = ($this->makeDraft)();
    $this->period->update(['close_at' => now()->subHour()]);
    ($this->submitDraft)($draft)->assertUnprocessable();
    expect($draft->fresh()->status)->toBe('active');
});

test('blocks_second_active_registration_even_for_another_period', function () {
    $draft = ($this->makeDraft)();
    $otherPeriod = SidangTaPeriod::create(['name' => 'Other', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay()]);
    SidangTaRegistration::create(['sidang_ta_period_id' => $otherPeriod->id,
        'student_number' => $this->student->student_number, 'student_name' => 'Final Student',
        'student_phone' => '081234567890', 'parent_phone' => '081234567891',
        'graduation_payment_date' => today()->toDateString(), 'diploma_biodata_accurate' => true,
        'status' => 'submitted']);
    ($this->submitDraft)($draft)->assertStatus(409);
    expect(SidangTaRegistration::count())->toBe(1);
});

test('revision_resubmits_same_registration_and_releases_obsolete_file_references', function () {
    $firstDraft = ($this->makeDraft)();
    $id = ($this->submitDraft)($firstDraft)->assertOk()->json('data.id');
    $registration = SidangTaRegistration::findOrFail($id);
    $oldPaths = $registration->documents()->whereIn('document_type', Requirements::DIPLOMA)->pluck('file_path')->all();
    $this->postJson(route('admin.sidang-ta-registrations.review', $registration), [
        'registration_version' => 1, 'action' => 'needs_review', 'review_notes' => 'Perbaiki biodata.',
    ])->assertRedirect();
    $revisionId = $this->postJson(route('sidang-ta-drafts.revision', $registration))
        ->assertCreated()->json('data.id');
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $revisionId, 'step' => 4]), [
        'version' => 1, 'diploma_biodata_accurate' => false,
        'documents' => ['pdm_submission_proof' => UploadedFile::fake()->create('pdm.pdf', 10, 'application/pdf')],
    ])->assertOk();
    $this->period->update(['close_at' => now()->subHour()]);
    $this->postJson(route('sidang-ta-drafts.review', $revisionId), ['version' => 2])->assertOk();
    $body = ['draft_id' => $revisionId, 'version' => 2, 'action' => 'submit'];
    $this->putJson(route('sidang-ta-registrations.update', $registration), $body)->assertOk()->assertJsonPath('data.id', $id);
    $this->putJson(route('sidang-ta-registrations.update', $registration), $body)->assertOk();
    expect(SidangTaRegistration::count())->toBe(1);
    expect($registration->fresh()->lock_version)->toBe(3);
    expect($registration->fresh()->review_notes)->toBeNull();
    expect($registration->documents()->count())->toBe(14);
    expect($registration->logs()->where('action', 'resubmitted')->count())->toBe(1);
    expect(SidangTaRegistrationDraft::findOrFail($revisionId)->documents()->count())->toBe(0);
    foreach ($oldPaths as $path) Storage::disk('local')->assertMissing($path);
});

test('other_user_cannot_finalize_someone_elses_draft', function () {
    $draft = ($this->makeDraft)();
    $other = User::factory()->create(['status' => 'active', 'must_change_password' => false]);
    $other->givePermissionTo('sidang_ta_registration_create');
    $this->actingAs($other);
    ($this->submitDraft)($draft)->assertForbidden();
    expect($draft->fresh()->status)->toBe('active');
});

test('database_failure_rolls_back_metadata_and_keeps_saved_files', function () {
    $draft = ($this->makeDraft)();
    $before = Storage::disk('local')->allFiles();
    $event = 'eloquent.creating: '.SidangTaRegistrationDocument::class;
    $writes = 0;
    Event::listen($event, function () use (&$writes) {
        if (++$writes === 2) throw new RuntimeException('Test DB metadata failure');
    });
    $caught = false;
    try {
        app(SidangTaDraftFinalizer::class)->submit($this->user, $draft, 1);
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Test DB metadata failure');
        $caught = true;
    } finally {
        Event::forget($event);
    }
    expect($caught)->toBeTrue();
    expect(SidangTaRegistration::count())->toBe(0);
    expect(SidangTaRegistrationDocument::count())->toBe(0);
    expect($draft->fresh()->status)->toBe('active');
    expect($draft->documents()->count())->toBe(15);
    expect(Storage::disk('local')->allFiles())->toBe($before);
});

test('html_final_submit_redirects_to_owned_detail', function () {
    $draft = ($this->makeDraft)();
    $response = $this->post(route('sidang-ta-registrations.store'), [
        'draft_id' => $draft->id, 'version' => 1, 'action' => 'submit',
    ]);
    $registration = SidangTaRegistration::where('student_number', $this->student->student_number)->firstOrFail();
    $response->assertRedirect(route('sidang-ta-registrations.show', $registration));
    $this->get(route('sidang-ta-registrations.show', $registration))->assertOk();
    $this->get(route('sidang-ta-registration-documents.download', $registration->documents()->first()))->assertOk();
});

test('revision_rollback_restores_final_and_borrowed_draft_references', function () {
    [$registration, $revision] = ($this->makeRevision)();
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $revision, 'step' => 4]), [
        'version' => 1, 'diploma_biodata_accurate' => false,
        'documents' => ['pdm_submission_proof' => UploadedFile::fake()->create('pdm.pdf', 10, 'application/pdf')],
    ])->assertOk();
    $this->postJson(route('sidang-ta-drafts.review', $revision), ['version' => 2])->assertOk();
    $beforeFiles = Storage::disk('local')->allFiles();
    $event = 'eloquent.creating: '.\App\Models\SidangTaRegistrationLog::class;
    Event::listen($event, function () { throw new RuntimeException('Test revision log failure'); });
    $caught = false;
    try {
        app(SidangTaDraftFinalizer::class)->submit($this->user, $revision->fresh(), 2, (int) $registration->id);
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Test revision log failure');
        $caught = true;
    } finally {
        Event::forget($event);
    }
    expect($caught)->toBeTrue();
    expect($registration->fresh()->status)->toBe('needs_review');
    expect($registration->fresh()->lock_version)->toBe(2);
    expect($registration->fresh()->review_notes)->toBe('Perbaiki dokumen.');
    expect($registration->fresh()->diploma_biodata_accurate)->toBeTrue();
    expect($registration->documents()->count())->toBe(15);
    expect($registration->logs()->where('action', 'resubmitted')->count())->toBe(0);
    expect($revision->fresh()->status)->toBe('active');
    expect($revision->documents()->count())->toBe(14);
    expect($revision->documents()->whereNotNull('source_document_id')->count())->toBe(13);
    expect(Storage::disk('local')->allFiles())->toBe($beforeFiles);
});

test('revision_rejects_wrong_endpoint_wrong_source_and_stale_source', function () {
    [$registration, $revision] = ($this->makeRevision)();
    $body = ['draft_id' => $revision->id, 'version' => 1, 'action' => 'submit'];
    $this->postJson(route('sidang-ta-registrations.store'), $body)->assertStatus(409);
    $other = SidangTaRegistration::create([
        'sidang_ta_period_id' => $this->period->id, 'student_number' => $this->student->student_number,
        'student_name' => 'Final Student', 'student_phone' => '081234567890',
        'parent_phone' => '081234567891', 'graduation_payment_date' => today()->toDateString(),
        'diploma_biodata_accurate' => true, 'status' => 'rejected',
    ]);
    $this->putJson(route('sidang-ta-registrations.update', $other), $body)->assertStatus(409);
    $registration->lock_version++;
    $registration->save();
    $this->putJson(route('sidang-ta-registrations.update', $registration), $body)->assertStatus(409);
    expect($registration->fresh()->status)->toBe('needs_review');
    expect($revision->fresh()->status)->toBe('active');
    expect($revision->documents()->count())->toBe(15);
    expect($other->fresh()->status)->toBe('rejected');
});
