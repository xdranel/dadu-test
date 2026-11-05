<?php

use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDraft;
use App\Models\Student;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->studentUser = User::factory()->create([
        'username' => '1202200001', 'email' => 'draft-test@mahasiswa.test',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false,
    ]);
    Student::create([
        'student_number' => '1202200001', 'name' => 'Draft Student',
        'email' => $this->studentUser->email, 'academic_year' => 2022,
        'study_program' => 'Informatika', 'status' => 'Aktif',
    ]);
    foreach (['create', 'read'] as $action) {
        $permission = Permission::firstOrCreate([
            'name' => "sidang_ta_registration_{$action}", 'guard_name' => 'web',
        ]);
        $this->studentUser->givePermissionTo($permission);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->period = SidangTaPeriod::create([
        'name' => 'Periode C2', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay(),
    ]);
    $this->data = [
        'sidang_ta_period_id' => $this->period->id,
        'student_phone' => '081234567890', 'parent_phone' => '081234567891',
    ];
});

test('starts_and_resumes_saved_stage_one_without_final_submission', function () {
    $before = SidangTaRegistration::count();
    $response = $this->actingAs($this->studentUser)
        ->postJson(route('sidang-ta-drafts.store'), $this->data)->assertCreated();
    $id = $response->json('data.id');

    $this->getJson(route('sidang-ta-drafts.edit', $id))->assertOk()
        ->assertJsonPath('data.payload.student_phone', '081234567890')
        ->assertJsonPath('data.sidang_ta_period_id', $this->period->id)
        ->assertJsonPath('data.version', 1);
    expect(SidangTaRegistration::count())->toBe($before);
    expect(SidangTaRegistrationDraft::findOrFail($id)->owner_user_id)
        ->toBe($this->studentUser->id);
});

test('retry_start_returns_same_draft_without_overwriting', function () {
    $first = $this->actingAs($this->studentUser)
        ->postJson(route('sidang-ta-drafts.store'), $this->data)->assertCreated();
    $second = $this->postJson(route('sidang-ta-drafts.store'), array_replace($this->data, [
        'student_phone' => '080000000000',
    ]))->assertCreated();

    expect($second->json('data.id'))->toBe($first->json('data.id'));
    $second->assertJsonPath('data.payload.student_phone', '081234567890');
    expect(SidangTaRegistrationDraft::where('owner_user_id', $this->studentUser->id)->count())->toBe(1);
});

test('rejects_closed_period_and_incomplete_contacts', function () {
    $this->period->update(['close_at' => now()->subHour()]);
    $this->actingAs($this->studentUser)
        ->postJson(route('sidang-ta-drafts.store'), $this->data)
        ->assertUnprocessable()->assertJsonValidationErrors('sidang_ta_period_id');
    $this->postJson(route('sidang-ta-drafts.store'), [])
        ->assertUnprocessable()->assertJsonValidationErrors(['student_phone', 'parent_phone']);
    expect(SidangTaRegistrationDraft::count())->toBe(0);
});

test('rejects_client_supplied_internal_metadata', function () {
    $this->actingAs($this->studentUser)
        ->postJson(route('sidang-ta-drafts.store'), array_replace($this->data, [
            'owner_user_id' => 999, 'student_number' => '999', 'status' => 'consumed',
        ]))->assertUnprocessable()
        ->assertJsonValidationErrors(['owner_user_id', 'student_number', 'status']);
    expect(SidangTaRegistrationDraft::count())->toBe(0);
});

test('saves_contacts_and_rejects_stale_version', function () {
    $id = $this->actingAs($this->studentUser)
        ->postJson(route('sidang-ta-drafts.store'), $this->data)->assertCreated()->json('data.id');
    $url = route('sidang-ta-drafts.steps.store', ['draft' => $id, 'step' => 1]);
    $data = array_replace($this->data, ['version' => 1, 'student_phone' => '089999999999']);
    SidangTaRegistrationDraft::whereKey($id)->update(['reviewed_version' => 1]);

    $this->postJson($url, $data)->assertOk()->assertJsonPath('data.version', 2);
    $this->postJson($url, array_replace($data, ['student_phone' => '080000000000']))
        ->assertStatus(409);
    $draft = SidangTaRegistrationDraft::findOrFail($id);
    expect($draft->payload['student_phone'])->toBe('089999999999');
    expect($draft->reviewed_version)->toBeNull();
});

test('other_user_cannot_read_or_save_draft_even_with_permissions', function () {
    $id = $this->actingAs($this->studentUser)
        ->postJson(route('sidang-ta-drafts.store'), $this->data)->assertCreated()->json('data.id');
    $other = User::factory()->create(['status' => 'active', 'must_change_password' => false]);
    $other->givePermissionTo(['sidang_ta_registration_read', 'sidang_ta_registration_create']);

    $this->actingAs($other)->getJson(route('sidang-ta-drafts.edit', $id))->assertForbidden();
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $id, 'step' => 1]),
        array_replace($this->data, ['version' => 1]))->assertForbidden();
});

test('cannot_skip_required_previous_stages', function () {
    $id = $this->actingAs($this->studentUser)
        ->postJson(route('sidang-ta-drafts.store'), $this->data)->assertCreated()->json('data.id');
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $id, 'step' => 4]), [
        'version' => 1, 'diploma_biodata_accurate' => false,
        'documents' => ['pdm_submission_proof' => \Illuminate\Http\UploadedFile::fake()
            ->create('pdm.pdf', 100, 'application/pdf')],
    ])->assertStatus(409);
    expect(SidangTaRegistrationDraft::findOrFail($id)->version)->toBe(1);
});

test('unlinked_user_cannot_start_draft', function () {
    $other = User::factory()->create(['status' => 'active', 'must_change_password' => false]);
    $other->givePermissionTo('sidang_ta_registration_create');
    $this->actingAs($other)->postJson(route('sidang-ta-drafts.store'), $this->data)
        ->assertUnprocessable()->assertJsonValidationErrors('student');
});

test('rejects_source_registration_id_on_new_draft_request', function () {
    $this->actingAs($this->studentUser)
        ->postJson(route('sidang-ta-drafts.store'), array_replace($this->data, [
            'source_registration_id' => 123,
        ]))->assertUnprocessable()->assertJsonValidationErrors('source_registration_id');
    expect(SidangTaRegistrationDraft::count())->toBe(0);
});

test('read_only_owner_cannot_start_or_save_draft', function () {
    $id = $this->actingAs($this->studentUser)
        ->postJson(route('sidang-ta-drafts.store'), $this->data)->assertCreated()->json('data.id');
    $this->studentUser->revokePermissionTo('sidang_ta_registration_create');
    $this->studentUser->unsetRelation('permissions');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($this->studentUser)
        ->postJson(route('sidang-ta-drafts.store'), $this->data)->assertForbidden();
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $id, 'step' => 1]),
        array_replace($this->data, ['version' => 1]))->assertForbidden();
    expect(SidangTaRegistrationDraft::findOrFail($id)->version)->toBe(1);
});
