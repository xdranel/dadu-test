<?php

use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDraft;
use App\Models\Student;
use App\Models\User;
use App\Support\SidangTaDocumentRequirements as Legacy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create(['username' => '1202200052', 'email' => 'meet-version@test.local',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
    $this->student = Student::create(['student_number' => $this->user->username, 'name' => 'Version Fixture',
        'email' => $this->user->email, 'academic_year' => 2022, 'study_program' => 'Informatika', 'status' => 'Aktif']);
    foreach (['read', 'create', 'update'] as $action) {
        $permission = Permission::firstOrCreate(['name' => 'sidang_ta_registration_'.$action, 'guard_name' => 'web']);
        $this->user->givePermissionTo($permission);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($this->user);
    $this->period = SidangTaPeriod::create(['name' => 'Version', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay()]);
    $this->startData = ['sidang_ta_period_id' => $this->period->id,
        'student_phone' => '081234567890', 'parent_phone' => '081234567891'];
    // Metadata-only staged fixture: version two is NOT enabled in the live form yet.
    // It intentionally uses the current document set until the next integration checkpoint.
    $this->completeDraft = function (int $requirementsVersion = 1) {
        $draft = SidangTaRegistrationDraft::create(['owner_user_id' => $this->user->id,
            'student_number' => $this->student->student_number, 'sidang_ta_period_id' => $this->period->id,
            'requirements_version' => $requirementsVersion, 'version' => 1, 'reviewed_version' => 1,
            'payload' => ['student_phone' => '081234567890', 'parent_phone' => '081234567891',
                'graduation_payment_date' => today()->toDateString(), 'diploma_biodata_accurate' => true]]);
        foreach (array_merge(Legacy::CAPTURES, Legacy::PAYMENT, Legacy::DIPLOMA) as $type) {
            $path = 'sidang_ta/drafts/'.$draft->id.'/'.$type.'.pdf';
            Storage::disk('local')->put($path, '%PDF-1.4 metadata test');
            $draft->documents()->create(['document_type' => $type, 'file_name' => $type.'.pdf',
                'file_path' => $path, 'file_size' => 22, 'file_type' => 'pdf']);
        }
        return $draft;
    };
    $this->submitDraft = fn ($draft, array $extra = []) => $this->postJson(route('sidang-ta-registrations.store'),
        array_replace(['draft_id' => $draft->id, 'version' => $draft->version, 'action' => 'submit'], $extra));
    $this->makeSource = function (int $requirementsVersion = 2) {
        return SidangTaRegistration::create(['student_number' => $this->student->student_number,
            'student_name' => $this->student->name, 'study_program' => 'Informatika',
            'sidang_ta_period_id' => $this->period->id, 'requirements_version' => $requirementsVersion,
            'student_phone' => '081234567890', 'parent_phone' => '081234567891',
            'graduation_payment_date' => today()->toDateString(), 'diploma_biodata_accurate' => true,
            'status' => 'needs_review', 'submitted_at' => now()])->fresh();
    };
});

test('meet_requirements_version_columns_have_database_defaults_for_old_style_inserts', function () {
    foreach (['sidang_ta_registrations', 'sidang_ta_registration_drafts'] as $table) {
        expect(Schema::hasColumn($table, 'requirements_version'))->toBeTrue();
    }
    $sourceId = DB::table('sidang_ta_registrations')->insertGetId([
        'sidang_ta_period_id' => $this->period->id, 'student_number' => $this->student->student_number,
        'student_name' => 'Raw default', 'status' => 'submitted',
        'student_phone' => '081234567890', 'parent_phone' => '081234567891',
        'graduation_payment_date' => today()->toDateString(), 'diploma_biodata_accurate' => true,
    ]);
    $id = (string) Str::uuid();
    DB::table('sidang_ta_registration_drafts')->insert(['id' => $id, 'owner_user_id' => $this->user->id,
        'student_number' => $this->student->student_number, 'sidang_ta_period_id' => $this->period->id, 'payload' => '{}']);
    expect((int) DB::table('sidang_ta_registrations')->where('id', $sourceId)->value('requirements_version'))->toBe(1);
    expect((int) DB::table('sidang_ta_registration_drafts')->where('id', $id)->value('requirements_version'))->toBe(1);
});

test('meet_requirements_version_is_integer_and_defaults_to_legacy_on_unsaved_models', function () {
    expect((new SidangTaRegistration())->requirements_version)->toBe(1);
    expect((new SidangTaRegistrationDraft())->requirements_version)->toBe(1);
    $source = ($this->makeSource)(2);
    expect($source->fresh()->requirements_version)->toBe(2);
});

test('meet_new_draft_cannot_choose_its_requirements_version_in_http_input', function () {
    foreach ([1, 2, 99] as $forged) {
        $this->postJson(route('sidang-ta-drafts.store'), array_replace($this->startData,
            ['requirements_version' => $forged]))->assertUnprocessable()->assertJsonValidationErrors('requirements_version');
    }
    expect(SidangTaRegistrationDraft::count())->toBe(0);
    $id = $this->postJson(route('sidang-ta-drafts.store'), $this->startData)->assertCreated()->json('data.id');
    expect(SidangTaRegistrationDraft::findOrFail($id)->requirements_version)->toBe(1);
});

test('meet_saved_draft_cannot_change_its_requirements_version_in_http_input', function () {
    $id = $this->postJson(route('sidang-ta-drafts.store'), $this->startData)->assertCreated()->json('data.id');
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $id, 'step' => 1]),
        array_replace($this->startData, ['version' => 1, 'requirements_version' => 2]))
        ->assertUnprocessable()->assertJsonValidationErrors('requirements_version');
    $draft = SidangTaRegistrationDraft::findOrFail($id);
    expect($draft->version)->toBe(1);
    expect($draft->requirements_version)->toBe(1);
});

test('meet_final_submit_rejects_client_requirements_version_without_consuming_draft', function () {
    $draft = ($this->completeDraft)();
    ($this->submitDraft)($draft, ['requirements_version' => 2])->assertUnprocessable()
        ->assertJsonValidationErrors('requirements_version');
    expect($draft->fresh()->status)->toBe('active');
    expect(SidangTaRegistration::count())->toBe(0);
    expect($draft->documents()->count())->toBe(15);
});

test('meet_finalizer_preserves_server_requirements_metadata_and_replay_result', function () {
    $draft = ($this->completeDraft)();
    $paths = Storage::disk('local')->allFiles();
    $id = ($this->submitDraft)($draft)->assertOk()->json('data.id');
    $registration = SidangTaRegistration::findOrFail($id);
    expect($registration->requirements_version)->toBe(1);
    ($this->submitDraft)($draft)->assertOk()->assertJsonPath('data.id', $id);
    expect($registration->fresh()->requirements_version)->toBe(1);
    expect(SidangTaRegistration::count())->toBe(1);
    expect($registration->logs()->count())->toBe(1);
    expect(Storage::disk('local')->allFiles())->toBe($paths);
});

test('meet_revision_inherits_server_requirements_version_instead_of_request_body', function () {
    $source = ($this->makeSource)(2);
    $id = $this->postJson(route('sidang-ta-drafts.revision', $source), ['requirements_version' => 1])
        ->assertCreated()->json('data.id');
    $draft = SidangTaRegistrationDraft::findOrFail($id);
    expect($draft->requirements_version)->toBe(2);
    expect($draft->source_version)->toBe($source->lock_version);
    expect($source->fresh()->requirements_version)->toBe(2);
});

test('meet_mismatched_revision_requirements_version_is_rejected_without_removing_source_file', function () {
    $source = ($this->makeSource)(2);
    Storage::disk('local')->put('sidang_ta/metadata-original.pdf', '%PDF-1.4 original');
    $source->documents()->create(['document_type' => 'thesis_draft', 'file_name' => 'original.pdf',
        'file_path' => 'sidang_ta/metadata-original.pdf', 'file_type' => 'pdf']);
    $id = $this->postJson(route('sidang-ta-drafts.revision', $source))->assertCreated()->json('data.id');
    $draft = SidangTaRegistrationDraft::findOrFail($id);
    $draft->update(['requirements_version' => 1]); // Simulate server-side inconsistent data, not an HTTP capability.
    $this->getJson(route('sidang-ta-drafts.edit', $draft))->assertConflict();
    expect($source->fresh()->requirements_version)->toBe(2);
    expect($source->documents()->count())->toBe(1);
    Storage::disk('local')->assertExists('sidang_ta/metadata-original.pdf');
});
