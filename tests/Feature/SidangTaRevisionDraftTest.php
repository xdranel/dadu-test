<?php

use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDraft;
use App\Models\Student;
use App\Models\User;
use App\Support\SidangTaDocumentRequirements as Requirements;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create([
        'username' => '1202200001', 'email' => 'revision@mahasiswa.test',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false,
    ]);
    Student::create([
        'student_number' => '1202200001', 'name' => 'Revision Test',
        'email' => $this->user->email, 'academic_year' => 2022,
        'study_program' => 'Informatika', 'status' => 'Aktif',
    ]);
    foreach (['read', 'update', 'review', 'create'] as $action) {
        Permission::firstOrCreate(['name' => "sidang_ta_registration_{$action}", 'guard_name' => 'web']);
    }
    $this->user->givePermissionTo(['sidang_ta_registration_read', 'sidang_ta_registration_update']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $period = SidangTaPeriod::create([
        'name' => 'Periode asal sudah tutup', 'is_active' => true,
        'open_at' => now()->subDays(3), 'close_at' => now()->subDay(),
    ]);
    $this->source = SidangTaRegistration::create([
        'sidang_ta_period_id' => $period->id, 'student_number' => '1202200001',
        'student_name' => 'Revision Test', 'student_phone' => '081234567890',
        'parent_phone' => '081234567891', 'graduation_payment_date' => today()->toDateString(),
        'diploma_biodata_accurate' => true, 'status' => 'needs_review',
        'review_notes' => 'Perbaiki dokumen.',
    ])->fresh(); // Muat default lock_version dari database sebelum dipakai test.
    foreach (array_merge(Requirements::CAPTURES, Requirements::PAYMENT, Requirements::DIPLOMA) as $type) {
        $path = "sidang_ta/{$this->source->id}/{$type}.pdf";
        Storage::disk('local')->put($path, '%PDF-fixture');
        $this->source->documents()->create([
            'document_type' => $type, 'file_name' => $type.'.pdf',
            'file_path' => $path, 'file_type' => 'pdf', 'status' => 'pending',
        ]);
    }
    $this->startRevision = function () {
        $id = $this->actingAs($this->user)->postJson(route('sidang-ta-drafts.revision', $this->source))
            ->assertCreated()->json('data.id');
        return SidangTaRegistrationDraft::findOrFail($id);
    };
});

test('revision_start_reuses_final_files_and_is_idempotent', function () {
    $beforeFiles = Storage::disk('local')->allFiles();
    $first = ($this->startRevision)();
    $second = ($this->startRevision)();
    expect($second->id)->toBe($first->id);
    expect($first->documents()->whereNotNull('source_document_id')->count())->toBe(15);
    expect(Storage::disk('local')->allFiles())->toBe($beforeFiles);
    expect($this->source->fresh()->status)->toBe('needs_review');
});

test('revision_can_save_contacts_after_period_closed_but_cannot_change_period', function () {
    $draft = ($this->startRevision)();
    $url = route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 1]);
    $data = ['version' => 1, 'sidang_ta_period_id' => $draft->sidang_ta_period_id,
        'student_phone' => '089999999999', 'parent_phone' => '081234567891'];
    $this->postJson($url, $data)->assertOk();
    expect($this->source->fresh()->student_phone)->toBe('081234567890');
    $other = SidangTaPeriod::create(['name' => 'Lain', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay()]);
    $this->postJson($url, array_replace($data, ['version' => 2, 'sidang_ta_period_id' => $other->id]))
        ->assertUnprocessable()->assertJsonValidationErrors('sidang_ta_period_id');
});

test('replacement_in_revision_does_not_delete_or_update_final_file', function () {
    $draft = ($this->startRevision)();
    $old = $this->source->documents()->where('document_type', 'ta_data_capture')->first();
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 2]), [
        'version' => 1, 'documents' => ['ta_data_capture' => UploadedFile::fake()->create('new.pdf', 100, 'application/pdf')],
    ])->assertOk();
    $replacement = $draft->documents()->where('document_type', 'ta_data_capture')->first();
    expect($replacement->source_document_id)->toBeNull();
    expect($replacement->file_path)->not->toBe($old->file_path);
    expect($old->fresh()->file_path)->toBe($old->file_path);
    Storage::disk('local')->assertExists($old->file_path);
    Storage::disk('local')->assertExists($replacement->file_path);
    expect($this->source->fresh()->review_notes)->toBe('Perbaiki dokumen.');
});

test('changing_revision_biodata_branch_preserves_final_biodata_files', function () {
    $draft = ($this->startRevision)();
    $paths = $this->source->documents()->whereIn('document_type', Requirements::DIPLOMA)->pluck('file_path')->all();
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 4]), [
        'version' => 1, 'diploma_biodata_accurate' => false,
        'documents' => ['pdm_submission_proof' => UploadedFile::fake()->create('pdm.pdf', 100, 'application/pdf')],
    ])->assertOk()->assertJsonCount(14, 'data.documents');
    foreach ($paths as $path) {
        Storage::disk('local')->assertExists($path);
    }
    expect($this->source->fresh()->diploma_biodata_accurate)->toBeTrue();
    expect($this->source->documents()->count())->toBe(15);
});

test('discard_removes_only_draft_owned_files', function () {
    $draft = ($this->startRevision)();
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 2]), [
        'version' => 1, 'documents' => ['ta_data_capture' => UploadedFile::fake()->create('new.pdf', 100, 'application/pdf')],
    ])->assertOk();
    $newPath = $draft->documents()->whereNull('source_document_id')->first()->file_path;
    $this->deleteJson(route('sidang-ta-drafts.destroy', $draft), ['version' => 2])->assertNoContent();
    $this->assertDatabaseMissing('sidang_ta_registration_drafts', ['id' => $draft->id]);
    Storage::disk('local')->assertMissing($newPath);
    foreach ($this->source->documents()->get() as $document) {
        Storage::disk('local')->assertExists($document->file_path);
    }
});

test('changed_source_blocks_resume_and_save_but_still_allows_discard', function () {
    $draft = ($this->startRevision)();
    $this->source->lock_version++;
    $this->source->save();
    $this->getJson(route('sidang-ta-drafts.edit', $draft))->assertStatus(409);
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 2]),
        ['version' => 1])->assertStatus(409);
    $this->deleteJson(route('sidang-ta-drafts.destroy', $draft), ['version' => 1])->assertNoContent();
});

test('wrong_owner_or_create_only_user_cannot_start_revision', function () {
    $other = User::factory()->create(['status' => 'active', 'must_change_password' => false]);
    $other->givePermissionTo('sidang_ta_registration_create');
    $this->actingAs($other)->postJson(route('sidang-ta-drafts.revision', $this->source))->assertForbidden();
    $other->givePermissionTo('sidang_ta_registration_update');
    Student::create(['student_number' => '1202200002', 'name' => 'Other',
        'email' => $other->email, 'academic_year' => 2022, 'study_program' => 'Informatika', 'status' => 'Aktif']);
    $this->actingAs($other)->postJson(route('sidang-ta-drafts.revision', $this->source))->assertForbidden();
});

test('review_increments_version_and_rejects_stale_review', function () {
    $reviewer = User::factory()->create(['status' => 'active', 'must_change_password' => false]);
    $reviewer->givePermissionTo('sidang_ta_registration_review');
    $this->actingAs($reviewer)->postJson(route('admin.sidang-ta-registrations.review', $this->source), [
        'registration_version' => 1, 'action' => 'needs_review', 'review_notes' => 'Catatan baru',
    ])->assertRedirect();
    expect($this->source->fresh()->lock_version)->toBe(2);
    $this->postJson(route('admin.sidang-ta-registrations.review', $this->source), [
        'registration_version' => 1, 'action' => 'approved',
    ])->assertStatus(409);
    expect($this->source->fresh()->status)->toBe('needs_review');
    expect($this->source->logs()->count())->toBe(1);
});

test('revision_owner_with_create_only_cannot_save_or_discard', function () {
    $draft = ($this->startRevision)();
    $this->user->revokePermissionTo('sidang_ta_registration_update');
    $this->user->givePermissionTo('sidang_ta_registration_create');
    $this->user->unsetRelation('permissions');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($this->user)->postJson(route('sidang-ta-drafts.steps.store',
        ['draft' => $draft, 'step' => 2]), ['version' => 1])->assertForbidden();
    $this->deleteJson(route('sidang-ta-drafts.destroy', $draft), ['version' => 1])
        ->assertForbidden();
    expect($draft->fresh()->version)->toBe(1);
});

test('discard_rejects_wrong_version_and_consumed_draft_without_deleting_files', function () {
    $draft = ($this->startRevision)();
    $before = Storage::disk('local')->allFiles();
    $this->deleteJson(route('sidang-ta-drafts.destroy', $draft), ['version' => 2])
        ->assertStatus(409);
    expect($draft->fresh()->documents()->count())->toBe(15);
    // Simulasikan state consumed; finalizer sebenarnya baru dipasang pada F.
    $draft->status = SidangTaRegistrationDraft::STATUS_CONSUMED;
    $draft->save();
    $this->deleteJson(route('sidang-ta-drafts.destroy', $draft), ['version' => 1])
        ->assertStatus(409);
    expect($draft->fresh()->status)->toBe(SidangTaRegistrationDraft::STATUS_CONSUMED);
    expect(Storage::disk('local')->allFiles())->toBe($before);
});
