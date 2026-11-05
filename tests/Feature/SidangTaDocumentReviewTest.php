<?php

use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\Student;
use App\Models\User;
use App\Support\SidangTaReviewEligibility as Eligibility;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->travelTo(now()->startOfSecond());
    Storage::fake('local');
    foreach (['sidang_ta_registration_review', 'sidang_ta_registration_read', 'sidang_ta_registration_create',
                 'sidang_ta_registration_update', 'sidang_ta_period_read'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->reviewer = User::factory()->create(['username' => 'p3-reviewer', 'email' => 'p3-reviewer@test.local',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
    $this->reviewer->givePermissionTo('sidang_ta_registration_review');
    $this->actingAs($this->reviewer);
    $this->period = SidangTaPeriod::create(['name' => 'P3', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay()]);
    $this->sequence = 0;
    $this->makeSource = function (array $overrides = [], string $documentStatus = 'pending') {
        $this->sequence++;
        $source = SidangTaRegistration::create(array_replace([
            'sidang_ta_period_id' => $this->period->id, 'student_number' => 'P3'.str_pad((string) $this->sequence, 6, '0', STR_PAD_LEFT),
            'student_name' => 'Student P3', 'study_program' => 'Informatika',
            'student_phone' => '081234567890', 'parent_phone' => '081234567891',
            'graduation_payment_date' => today()->toDateString(), 'diploma_biodata_accurate' => true,
            'status' => 'submitted', 'submitted_at' => now(),
        ], $overrides))->fresh();
        foreach (Eligibility::requiredTypes($source) as $type) {
            $path = "sidang_ta/p3/{$source->id}/{$type}.pdf";
            Storage::disk('local')->put($path, '%PDF-1.4 p3');
            $source->documents()->create(['document_type' => $type, 'file_name' => $type.'.pdf',
                'file_path' => $path, 'file_size' => 11, 'file_type' => 'pdf', 'status' => $documentStatus]);
        }
        return $source;
    };
    $this->reviewDoc = function ($source, $document, array $overrides = []) {
        return $this->postJson(route('admin.sidang-ta-registrations.documents.review', [
            'sidang_ta_registration' => $source, 'document' => $document,
        ]), array_replace(['registration_version' => $source->fresh()->lock_version, 'status' => 'verified'], $overrides));
    };
});

afterEach(function () { $this->travelBack(); });

test('p3_verifies_one_document_with_version_and_audit_without_approving', function () {
    $source = ($this->makeSource)();
    $document = $source->documents()->firstOrFail();
    $paths = Storage::disk('local')->allFiles();
    ($this->reviewDoc)($source, $document)->assertOk()->assertJsonPath('data.lock_version', 2)
        ->assertJsonPath('data.status', 'submitted');
    expect($document->fresh()->status)->toBe('verified');
    expect($source->documents()->where('status', 'pending')->count())->toBe(14);
    $log = $source->logs()->firstOrFail();
    expect($log->action)->toBe('document_verified');
    expect((int) $log->performed_by)->toBe($this->reviewer->id);
    expect($log->notes)->toContain(SidangTaRegistration::DOCUMENT_TYPES[$document->document_type]);
    expect(Storage::disk('local')->allFiles())->toBe($paths);
});

test('p3_rejection_requires_valid_nonblank_notes_and_status', function () {
    $source = ($this->makeSource)();
    $document = $source->documents()->firstOrFail();
    foreach ([null, '', '   ', ['bad'], str_repeat('x', 2001)] as $notes) {
        ($this->reviewDoc)($source, $document, ['status' => 'rejected', 'notes' => $notes])
            ->assertUnprocessable()->assertJsonValidationErrors('notes');
    }
    ($this->reviewDoc)($source, $document, ['status' => 'pending'])->assertUnprocessable()->assertJsonValidationErrors('status');
    expect($source->fresh()->lock_version)->toBe(1);
    expect($source->logs()->count())->toBe(0);
    expect($document->fresh()->status)->toBe('pending');
});

test('p3_rejection_moves_submission_to_revision_without_overwriting_general_notes', function () {
    $source = ($this->makeSource)(['review_notes' => 'Catatan umum lama']);
    $document = $source->documents()->firstOrFail();
    ($this->reviewDoc)($source, $document, ['status' => 'rejected', 'notes' => ' Perbaiki scan '])
        ->assertOk()->assertJsonPath('data.status', 'needs_review')->assertJsonPath('data.lock_version', 2);
    expect($document->fresh()->notes)->toBe('Perbaiki scan');
    expect($source->fresh()->review_notes)->toBe('Catatan umum lama');
    $log = $source->logs()->firstOrFail();
    expect($log->action)->toBe('document_rejected');
    expect($log->from_status)->toBe('submitted');
    expect($log->to_status)->toBe('needs_review');
});

test('p3_last_verification_never_auto_approves_or_resubmits', function () {
    foreach (['submitted', 'needs_review'] as $status) {
        $source = ($this->makeSource)(['status' => $status], 'verified');
        $document = $source->documents()->firstOrFail();
        $document->update(['status' => 'pending']);
        ($this->reviewDoc)($source, $document)->assertOk()->assertJsonPath('data.status', $status);
        expect($source->documents()->where('status', '!=', 'verified')->count())->toBe(0);
        expect($source->fresh()->status)->toBe($status);
    }
});

test('p3_stale_document_and_decision_versions_do_not_overwrite_newer_review', function () {
    $source = ($this->makeSource)();
    $document = $source->documents()->firstOrFail();
    ($this->reviewDoc)($source, $document)->assertOk();
    ($this->reviewDoc)($source, $document, ['registration_version' => 1, 'status' => 'rejected', 'notes' => 'Lama'])
        ->assertStatus(409);
    $this->postJson(route('admin.sidang-ta-registrations.review', $source), [
        'registration_version' => 1, 'action' => 'approved',
    ])->assertStatus(409);
    expect($document->fresh()->status)->toBe('verified');
    expect($source->fresh()->lock_version)->toBe(2);
    expect($source->logs()->count())->toBe(1);
});

test('p3_document_must_belong_to_registration_in_route', function () {
    $a = ($this->makeSource)();
    $b = ($this->makeSource)();
    $document = $b->documents()->firstOrFail();
    ($this->reviewDoc)($a, $document)->assertNotFound();
    expect($a->fresh()->lock_version)->toBe(1);
    expect($b->fresh()->lock_version)->toBe(1);
    expect($document->fresh()->status)->toBe('pending');
});

test('p3_inactive_biodata_branch_cannot_be_reviewed', function () {
    $source = ($this->makeSource)();
    Storage::disk('local')->put('sidang_ta/p3/extra.pdf', '%PDF-extra');
    $document = $source->documents()->create(['document_type' => 'pdm_submission_proof',
        'file_name' => 'extra.pdf', 'file_path' => 'sidang_ta/p3/extra.pdf', 'file_type' => 'pdf', 'status' => 'pending']);
    ($this->reviewDoc)($source, $document)->assertUnprocessable();
    ($this->reviewDoc)($source, $document, ['status' => 'rejected', 'notes' => 'Tidak berlaku'])->assertUnprocessable();
    expect($source->fresh()->lock_version)->toBe(1);
});

test('p3_missing_file_cannot_be_verified_but_can_be_requested_for_repair', function () {
    $source = ($this->makeSource)();
    $document = $source->documents()->firstOrFail();
    Storage::disk('local')->delete($document->file_path);
    ($this->reviewDoc)($source, $document)->assertUnprocessable()->assertJsonValidationErrors('document');
    ($this->reviewDoc)($source, $document, ['status' => 'rejected', 'notes' => 'Unggah ulang berkas'])
        ->assertOk()->assertJsonPath('data.status', 'needs_review');
});

test('p3_terminal_registration_is_read_only_for_document_and_decision', function () {
    foreach (['approved', 'rejected'] as $status) {
        $source = ($this->makeSource)(['status' => $status], 'verified');
        ($this->reviewDoc)($source, $source->documents()->first())->assertUnprocessable();
        $this->postJson(route('admin.sidang-ta-registrations.review', $source), [
            'registration_version' => 1, 'action' => 'needs_review', 'review_notes' => 'Buka lagi',
        ])->assertUnprocessable();
        expect($source->fresh()->status)->toBe($status);
        expect($source->fresh()->lock_version)->toBe(1);
        expect($source->logs()->count())->toBe(0);
    }
});

test('p3_non_review_permissions_cannot_mutate_documents_or_decisions', function () {
    $source = ($this->makeSource)();
    $document = $source->documents()->firstOrFail();
    foreach (['sidang_ta_registration_read', 'sidang_ta_registration_update', 'sidang_ta_period_read'] as $permission) {
        $user = User::factory()->create(['status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
        $user->givePermissionTo($permission);
        $this->actingAs($user);
        ($this->reviewDoc)($source, $document)->assertForbidden();
        $this->postJson(route('admin.sidang-ta-registrations.review', $source), [
            'registration_version' => 1, 'action' => 'approved',
        ])->assertForbidden();
    }
    expect($source->fresh()->lock_version)->toBe(1);
    expect($document->fresh()->status)->toBe('pending');
});

test('p3_approval_rejects_pending_rejected_missing_metadata_or_missing_file', function () {
    foreach (['pending', 'rejected', 'metadata', 'file'] as $defect) {
        $source = ($this->makeSource)([], 'verified');
        $document = $source->documents()->firstOrFail();
        if (in_array($defect, ['pending', 'rejected'], true)) $document->update(['status' => $defect]);
        if ($defect === 'metadata') $document->delete();
        if ($defect === 'file') Storage::disk('local')->delete($document->file_path);
        $this->postJson(route('admin.sidang-ta-registrations.review', $source), [
            'registration_version' => 1, 'action' => 'approved',
        ])->assertUnprocessable()->assertJsonValidationErrors('documents');
        expect($source->fresh()->status)->toBe('submitted');
        expect($source->fresh()->lock_version)->toBe(1);
        expect($source->logs()->count())->toBe(0);
    }
});

test('p3_approval_accepts_verified_fifteen_or_fourteen_and_ignores_na_metadata', function () {
    foreach ([true, false] as $accurate) {
        $source = ($this->makeSource)(['diploma_biodata_accurate' => $accurate], 'verified');
        $extra = $source->documents()->create(['document_type' => $accurate ? 'pdm_submission_proof' : 'diploma_statement',
            'file_name' => 'old.pdf', 'file_path' => 'obsolete-not-available.pdf', 'file_type' => 'pdf', 'status' => 'pending']);
        $this->postJson(route('admin.sidang-ta-registrations.review', $source), [
            'registration_version' => 1, 'action' => 'approved',
        ])->assertRedirect();
        expect($source->fresh()->status)->toBe('approved');
        expect($source->fresh()->lock_version)->toBe(2);
        expect($extra->fresh()->status)->toBe('pending');
    }
});

test('p3_identical_document_review_is_noop_but_note_change_increments_version', function () {
    $source = ($this->makeSource)([], 'verified');
    $document = $source->documents()->firstOrFail();
    ($this->reviewDoc)($source, $document)->assertOk()->assertJsonPath('data.lock_version', 1);
    expect($source->logs()->count())->toBe(0);
    ($this->reviewDoc)($source, $document, ['notes' => 'Sudah diperiksa'])->assertOk()->assertJsonPath('data.lock_version', 2);
    expect($source->logs()->count())->toBe(1);
});

test('p3_document_review_makes_existing_student_revision_stale_without_deleting_files', function () {
    $owner = User::factory()->create(['username' => '1202200001', 'email' => 'owner-p3@test.local',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
    $owner->givePermissionTo(['sidang_ta_registration_read', 'sidang_ta_registration_update']);
    Student::create(['student_number' => '1202200001', 'name' => 'Owner', 'email' => $owner->email,
        'academic_year' => 2022, 'study_program' => 'Informatika', 'status' => 'Aktif']);
    $source = ($this->makeSource)(['student_number' => '1202200001', 'status' => 'needs_review'], 'verified');
    $paths = $source->documents()->pluck('file_path')->all();
    $id = $this->actingAs($owner)->postJson(route('sidang-ta-drafts.revision', $source))->assertCreated()->json('data.id');
    $this->actingAs($this->reviewer);
    ($this->reviewDoc)($source, $source->documents()->first(), ['status' => 'rejected', 'notes' => 'Catatan baru'])->assertOk();
    $this->actingAs($owner)->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $id, 'step' => 1]), [
        'version' => 1, 'sidang_ta_period_id' => $this->period->id,
        'student_phone' => '081234567890', 'parent_phone' => '081234567891',
    ])->assertStatus(409);
    foreach ($paths as $path) Storage::disk('local')->assertExists($path);
    expect($source->documents()->count())->toBe(15);
});


test('p3_log_failure_rolls_back_document_and_registration_changes', function () {
    $source = ($this->makeSource)();
    $document = $source->documents()->firstOrFail();
    $paths = Storage::disk('local')->allFiles();
    $dispatcher = \App\Models\SidangTaRegistrationLog::getEventDispatcher();
    \App\Models\SidangTaRegistrationLog::setEventDispatcher(clone $dispatcher);
    \App\Models\SidangTaRegistrationLog::creating(function () { throw new RuntimeException('p3-log-failure'); });
    try {
        $this->withoutExceptionHandling();
        try {
            ($this->reviewDoc)($source, $document, ['status' => 'rejected', 'notes' => 'Perbaiki']);
            $this->fail('Log failure harus membatalkan transaksi.');
        } catch (RuntimeException $exception) {
            expect($exception->getMessage())->toBe('p3-log-failure');
        }
    } finally {
        \App\Models\SidangTaRegistrationLog::setEventDispatcher($dispatcher);
    }
    expect($document->fresh()->status)->toBe('pending');
    expect($document->fresh()->notes)->toBeNull();
    expect($source->fresh()->status)->toBe('submitted');
    expect($source->fresh()->lock_version)->toBe(1);
    expect($source->logs()->count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBe($paths);
});
