<?php

use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDraft;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->owner = User::factory()->create([
        'status' => 'active', 'auth_source' => 'manual',
        'must_change_password' => false,
    ]);
    $this->period = SidangTaPeriod::create([
        'name' => 'Periode test draft', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay(),
    ]);
    $this->makeDraft = function (array $overrides = []) {
        return SidangTaRegistrationDraft::create(array_replace([
            'owner_user_id' => $this->owner->id,
            'student_number' => '1202200001',
            'sidang_ta_period_id' => $this->period->id,
            'payload' => ['student_phone' => '081234567890'],
        ], $overrides));
    };
});

test('draft_schema_exists_without_changing_final_statuses', function () {
    expect(Schema::hasTable('sidang_ta_registration_drafts'))->toBeTrue();
    expect(Schema::hasTable('sidang_ta_registration_draft_documents'))->toBeTrue();
    expect(Schema::hasColumn('sidang_ta_registrations', 'lock_version'))->toBeTrue();
});

test('draft_persists_uuid_payload_and_owner_relations', function () {
    $draft = ($this->makeDraft)();
    $loaded = SidangTaRegistrationDraft::findOrFail($draft->id);

    expect(Str::isUuid($loaded->id))->toBeTrue();
    expect($loaded->payload)->toBe(['student_phone' => '081234567890']);
    expect($loaded->version)->toBe(1);
    expect($loaded->requirements_version)->toBe(2);
    expect($loaded->status)->toBe('active');
    expect($loaded->reviewed_version)->toBeNull();
    expect($loaded->owner->is($this->owner))->toBeTrue();
    expect($loaded->period->is($this->period))->toBeTrue();
    expect($this->period->drafts()->whereKey($draft->id)->exists())->toBeTrue();
});

test('draft_query_filters_by_owner', function () {
    $own = ($this->makeDraft)();
    $other = User::factory()->create(['status' => 'active']);
    ($this->makeDraft)(['owner_user_id' => $other->id]);

    expect(SidangTaRegistrationDraft::query()->ownedBy($this->owner)->pluck('id')->all())
        ->toBe([$own->id]);
});

test('draft_does_not_create_final_submission', function () {
    $before = SidangTaRegistration::count();
    ($this->makeDraft)();

    expect(SidangTaRegistration::count())->toBe($before);
});

test('draft_documents_have_unique_types_per_draft', function () {
    $draft = ($this->makeDraft)();
    $metadata = [
        'document_type' => 'ta_data_capture', 'file_name' => 'capture.pdf',
        'file_path' => "sidang_ta/drafts/{$draft->id}/capture.pdf",
        'file_size' => 100, 'file_type' => 'pdf',
    ];

    $first = $draft->documents()->create($metadata);
    expect($first->getTable())->toBe('sidang_ta_registration_draft_documents');
    expect($first->exists)->toBeTrue();

    $duplicateRejected = false;
    try {
        $draft->documents()->create($metadata);
    } catch (QueryException $exception) {
        // 1062 adalah duplicate entry MySQL, bukan unknown column / table missing.
        expect((int) $exception->errorInfo[1])->toBe(1062);
        $duplicateRejected = true;
    }

    expect($duplicateRejected)->toBeTrue();
    expect($draft->documents()->count())->toBe(1);
});

test('same_document_type_is_allowed_for_different_drafts', function () {
    $first = ($this->makeDraft)();
    $second = ($this->makeDraft)(['student_number' => '1202200002']);
    foreach ([$first, $second] as $draft) {
        $document = $draft->documents()->create([
            'document_type' => 'ta_data_capture', 'file_name' => 'capture.pdf',
            'file_path' => "sidang_ta/drafts/{$draft->id}/capture.pdf",
        ]);
        expect($document->draft->is($draft))->toBeTrue();
    }
    expect($first->documents()->count())->toBe(1);
    expect($second->documents()->count())->toBe(1);
});

test('deleting_draft_cascades_only_its_document_metadata', function () {
    $draft = ($this->makeDraft)();
    $document = $draft->documents()->create([
        'document_type' => 'ta_data_capture', 'file_name' => 'capture.pdf',
        'file_path' => "sidang_ta/drafts/{$draft->id}/capture.pdf",
    ]);
    $draft->delete();

    $this->assertDatabaseMissing('sidang_ta_registration_draft_documents', [
        'id' => $document->id,
    ]);
    expect($this->period->fresh())->not->toBeNull();
    expect($this->owner->fresh())->not->toBeNull();
});

test('period_with_draft_cannot_be_deleted_through_controller', function () {
    ($this->makeDraft)();
    $permission = Permission::firstOrCreate([
        'name' => 'sidang_ta_period_delete', 'guard_name' => 'web',
    ]);
    $this->owner->givePermissionTo($permission);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($this->owner)
        ->delete(route('admin.sidang-ta-periods.destroy', $this->period))
        ->assertStatus(409);

    $this->assertDatabaseHas('sidang_ta_periods', ['id' => $this->period->id]);
});

test('final_document_model_keeps_registration_relation', function () {
    $registration = SidangTaRegistration::create([
        'sidang_ta_period_id' => $this->period->id,
        'student_number' => '1202200001', 'student_name' => 'Student Test',
        'student_phone' => '081234567890', 'parent_phone' => '081234567891',
        'graduation_payment_date' => today()->toDateString(),
        'diploma_biodata_accurate' => true, 'status' => 'submitted',
    ]);
    $document = $registration->documents()->create([
        'document_type' => 'ta_data_capture', 'file_name' => 'final.pdf',
        'file_path' => 'sidang_ta/test/final.pdf', 'status' => 'pending',
    ]);

    expect($document->getTable())->toBe('sidang_ta_registration_documents');
    expect($document->fresh()->registration->is($registration))->toBeTrue();
    expect($registration->fresh()->lock_version)->toBe(1);
    expect($registration->fresh()->requirements_version)->toBe(2);
});

test('requirements_marker_defaults_to_two_in_database_without_model_attributes', function () {
    $draftId = (string) Str::uuid();
    DB::table('sidang_ta_registration_drafts')->insert([
        'id' => $draftId, 'owner_user_id' => $this->owner->id,
        'student_number' => '1202200003', 'sidang_ta_period_id' => $this->period->id,
        'payload' => '{}',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $registrationId = DB::table('sidang_ta_registrations')->insertGetId([
        'sidang_ta_period_id' => $this->period->id,
        'student_number' => '1202200004', 'student_name' => 'Database Default',
        'student_phone' => '081234567890', 'parent_phone' => '081234567891',
        'graduation_payment_date' => today()->toDateString(),
        'diploma_biodata_accurate' => true, 'status' => 'submitted',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    expect((int) DB::table('sidang_ta_registration_drafts')->where('id', $draftId)->value('requirements_version'))->toBe(2);
    expect((int) DB::table('sidang_ta_registrations')->where('id', $registrationId)->value('requirements_version'))->toBe(2);
});
