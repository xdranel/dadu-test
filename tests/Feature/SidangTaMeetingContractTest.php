<?php

use App\Models\{AcademicPeriod, Course, SidangTaPeriod, SidangTaRegistration, SidangTaRegistrationDraft, Student, StudentCourse, User};
use App\Services\{SidangTaRevisionDraftService, StudentAcademicProfileImportService};
use App\Support\SidangTaDocumentRequirements as Requirements;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-11-06 12:00:00', config('app.timezone')));
    Storage::fake('local');
    $this->user = User::factory()->create(['username' => '1202299077', 'status' => 'active',
        'auth_source' => 'manual', 'must_change_password' => false]);
    $this->student = Student::create(['student_number' => $this->user->username, 'name' => 'Meeting Contract',
        'email' => $this->user->email, 'academic_year' => 2022, 'study_program' => 'Informatika', 'status' => 'Aktif']);
    foreach (['read', 'create', 'update'] as $action) {
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'sidang_ta_registration_'.$action, 'guard_name' => 'web']));
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($this->user);
    $this->period = SidangTaPeriod::create(['name' => 'Meeting Contract', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay()]);
    $this->start = fn (array $extra = []) => $this->postJson(route('sidang-ta-drafts.store'), array_replace([
        'sidang_ta_period_id' => $this->period->id, 'student_phone' => '081234567890', 'parent_phone' => '08123456789',
    ], $extra));
    $this->makeDraft = function (bool $accurate = true) {
        $id = ($this->start)()->assertCreated()->json('data.id');
        $draft = SidangTaRegistrationDraft::findOrFail($id);
        $draft->update(['payload' => array_replace($draft->payload, ['graduation_payment_date' => today()->toDateString(),
            'diploma_biodata_accurate' => $accurate])]);
        $types = ['ta_data_capture', 'online_supervision_capture', 'ta_supervisor_sk', 'thesis_draft', 'graduation_payment_proof',
            ...($accurate ? ['diploma_statement', 'diploma_biodata_form'] : ['pdm_submission_proof'])];
        foreach ($types as $type) {
            $path = 'sidang_ta/drafts/'.$draft->id.'/'.$type.'.pdf';
            Storage::disk('local')->put($path, '%PDF-1.4 contract');
            $draft->documents()->create(['document_type' => $type, 'file_path' => $path,
                'file_name' => $type.'.pdf', 'file_type' => 'pdf', 'file_size' => 16]);
        }
        return $draft->fresh();
    };
    $this->admit = function () {
        AcademicPeriod::query()->update(['is_active' => false]);
        $academic = AcademicPeriod::create(['year' => '2026/2027', 'semester' => 1, 'label' => 'Contract KRS',
            'start_date' => '2026-08-01', 'end_date' => '2027-01-31', 'is_active' => true]);
        Course::create(['course_code' => 'MEET77', 'name' => 'TUGAS AKHIR 2', 'academic_year' => '2026/2027',
            'semester_term' => 'GANJIL', 'study_program' => 'Informatika', 'class' => 'A', 'semester' => 8, 'credits' => 4]);
        StudentCourse::create(['student_number' => $this->student->student_number, 'course_code' => 'MEET77',
            'course_name' => 'TUGAS AKHIR 2', 'course_class' => 'A', 'academic_year' => '2026/2027', 'semester' => 8,
            'academic_period_id' => $academic->id]);
        $row = json_decode(file_get_contents(base_path('docs/fixtures/sidang-ta/03-profil-akademik-dummy.json')), true)['data'][0];
        $row['nim'] = $this->student->student_number;
        $row['nama'] = $this->student->name;
        expect(app(StudentAcademicProfileImportService::class)->import([$row])['errors'])->toBe([]);
    };
    $this->send = fn ($draft, array $extra = []) => $this->postJson(route('sidang-ta-registrations.store'), array_replace([
        'draft_id' => $draft->id, 'version' => $draft->fresh()->version, 'action' => 'submit',
    ], $extra));
});

afterEach(function () { $this->travelBack(); });

test('meeting_contract_rejects_invalid_whatsapp_with_a_field_message', function ($field, $value) {
    ($this->start)([$field => $value])->assertUnprocessable()->assertJsonValidationErrors($field)
        ->assertJsonPath('errors.'.$field.'.0', 'Nomor WA harus diawali 08 atau 628 dan berisi 8–15 digit angka (0–9).');
    expect(SidangTaRegistrationDraft::count())->toBe(0);
})->with([['student_phone', '08abcd1234'], ['parent_phone', '+62812345678'], ['student_phone', '1234567'],
    ['parent_phone', '1234567890123456'], ['student_phone', 812345678]]);

test('meeting_contract_preserves_leading_zeroes_and_locks_identity', function () {
    $id = ($this->start)(['student_name' => 'Forged', 'email' => 'forged@example.test'])->assertCreated()->json('data.id');
    $draft = SidangTaRegistrationDraft::findOrFail($id);
    expect($draft->payload)->toEqual(['student_phone' => '081234567890', 'parent_phone' => '08123456789']);
    expect($this->student->fresh()->name)->toBe('Meeting Contract');
});

test('meeting_contract_stage_two_requires_four_pdfs_and_rejects_images', function () {
    $id = ($this->start)()->assertCreated()->json('data.id');
    $draft = SidangTaRegistrationDraft::findOrFail($id);
    $url = route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 2]);
    $this->postJson($url, ['version' => 1, 'documents' => ['ta_data_capture' => UploadedFile::fake()->image('capture.png')]])
        ->assertUnprocessable()->assertJsonValidationErrors('documents.ta_data_capture');
    $files = [];
    foreach (['ta_data_capture', 'online_supervision_capture', 'ta_supervisor_sk', 'thesis_draft'] as $type) {
        $files[$type] = UploadedFile::fake()->create($type.'.pdf', 100, 'application/pdf');
    }
    $this->postJson($url, ['version' => 1, 'documents' => $files])->assertOk();
    expect(Requirements::progress($draft->fresh(), 2))->toMatchArray(['complete' => true, 'required_documents' => 4, 'stored_documents' => 4]);
});

test('meeting_contract_pdm_date_can_be_saved_partially_but_is_required_for_completion', function () {
    $draft = ($this->makeDraft)(false);
    expect(Requirements::progress($draft, 4)['complete'])->toBeFalse();
    $url = route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 4]);
    $this->postJson($url, ['version' => 1, 'pdm_submission_date' => today()->addDay()->toDateString()])
        ->assertUnprocessable()->assertJsonValidationErrors('pdm_submission_date');
    $this->postJson($url, ['version' => 1, 'pdm_submission_date' => 'not-a-date'])
        ->assertUnprocessable()->assertJsonValidationErrors('pdm_submission_date');
    $this->postJson($url, ['version' => 1, 'pdm_submission_date' => today()->toDateString()])->assertOk();
    expect(Requirements::complete($draft->fresh(), 4))->toBeTrue();
    expect($draft->fresh()->payload['pdm_submission_date'])->toBe(today()->toDateString());
});

test('meeting_contract_requires_consent_and_persists_pdm_date_and_version_on_initial_submit', function () {
    ($this->admit)();
    $draft = ($this->makeDraft)(false);
    $draft->update(['payload' => $draft->payload + ['pdm_submission_date' => today()->subDay()->toDateString()], 'reviewed_version' => 1]);
    ($this->send)($draft)->assertUnprocessable()->assertJsonValidationErrors('declaration_accepted');
    expect($draft->fresh()->status)->toBe('active');
    $id = ($this->send)($draft, ['declaration_accepted' => true])->assertOk()->json('data.id');
    $registration = SidangTaRegistration::findOrFail($id);
    expect($registration->pdm_submission_date?->toDateString())->toBe(today()->subDay()->toDateString());
    expect($registration->declaration_accepted_at?->toDateTimeString())->toBe(now()->toDateTimeString());
    expect($registration->declaration_version)->toBe(1);
    $this->travel(1)->minutes();
    ($this->send)($draft, ['declaration_accepted' => true])->assertOk()->assertJsonPath('data.id', $id);
    expect($registration->fresh()->declaration_accepted_at->toDateTimeString())->toBe('2026-11-06 12:00:00');
});

test('meeting_contract_revision_allows_contact_and_missing_historical_pdm_date_but_freezes_branch_and_period', function () {
    ($this->admit)();
    $draft = ($this->makeDraft)(false);
    $draft->update(['payload' => $draft->payload + ['pdm_submission_date' => today()->subDay()->toDateString()], 'reviewed_version' => 1]);
    $id = ($this->send)($draft, ['declaration_accepted' => true])->assertOk()->json('data.id');
    $source = SidangTaRegistration::findOrFail($id);
    $source->update(['status' => 'needs_review', 'pdm_submission_date' => null]);
    $source->documents()->update(['status' => 'verified']);
    $source->documents()->where('document_type', 'ta_data_capture')->update(['status' => 'rejected']);
    $revision = app(SidangTaRevisionDraftService::class)->start($this->user, $source);
    expect(Requirements::complete($revision, 4))->toBeFalse();
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $revision, 'step' => 1]), [
        'version' => 1, 'sidang_ta_period_id' => $this->period->id, 'student_phone' => '08987654321', 'parent_phone' => '08123456789',
    ])->assertOk();
    $revision = $revision->fresh();
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $revision, 'step' => 2]), [
        'version' => $revision->version, 'documents' => ['ta_data_capture' => UploadedFile::fake()->create('replacement.pdf', 100, 'application/pdf')],
    ])->assertOk();
    $url = route('sidang-ta-drafts.steps.store', ['draft' => $revision, 'step' => 4]);
    $this->postJson($url, ['version' => $revision->fresh()->version, 'diploma_biodata_accurate' => true])
        ->assertUnprocessable()->assertJsonValidationErrors('diploma_biodata_accurate');
    $this->postJson($url, ['version' => $revision->fresh()->version, 'pdm_submission_date' => today()->toDateString()])->assertOk();
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $revision, 'step' => 1]), [
        'version' => $revision->fresh()->version, 'sidang_ta_period_id' => 99999, 'student_phone' => '08987654321', 'parent_phone' => '08123456789',
    ])->assertUnprocessable()->assertJsonValidationErrors('sidang_ta_period_id');
    $revision->refresh()->update(['reviewed_version' => $revision->version]);
    $submit = ['draft_id' => $revision->id, 'version' => $revision->version, 'action' => 'submit'];
    $this->putJson(route('sidang-ta-registrations.update', $source), $submit)->assertUnprocessable()->assertJsonValidationErrors('declaration_accepted');
    $this->travel(1)->minutes();
    $this->putJson(route('sidang-ta-registrations.update', $source), $submit + ['declaration_accepted' => true])->assertOk();
    expect($source->fresh()->student_phone)->toBe('08987654321');
    expect($source->fresh()->student_name)->toBe('Meeting Contract');
    expect($source->fresh()->pdm_submission_date->toDateString())->toBe(today()->toDateString());
    expect($source->fresh()->declaration_accepted_at->toDateTimeString())->toBe(now()->toDateTimeString());
});

test('meeting_contract_finalizer_rechecks_pdm_date_with_a_field_error_after_review', function () {
    ($this->admit)();
    $draft = ($this->makeDraft)(false);
    $draft->update(['reviewed_version' => 1]);
    ($this->send)($draft, ['declaration_accepted' => true])->assertUnprocessable()->assertJsonValidationErrors('pdm_submission_date');
    expect($draft->fresh()->status)->toBe('active');
    expect(SidangTaRegistration::count())->toBe(0);
});

test('meeting_contract_accurate_branch_does_not_require_a_pdm_date', function () {
    ($this->admit)();
    $draft = ($this->makeDraft)(true);
    $draft->update(['reviewed_version' => 1]);
    $id = ($this->send)($draft, ['declaration_accepted' => true])->assertOk()->json('data.id');
    expect(SidangTaRegistration::findOrFail($id)->pdm_submission_date)->toBeNull();
});

test('meeting_contract_requires_each_whatsapp_contact_to_start', function ($field) {
    ($this->start)([$field => ''])->assertUnprocessable()->assertJsonValidationErrors($field);
    expect(SidangTaRegistrationDraft::count())->toBe(0);
})->with(['student_phone', 'parent_phone']);
