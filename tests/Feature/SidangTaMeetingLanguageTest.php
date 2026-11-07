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


test('meeting77_revision_waiver_to_automatic_uses_live_requirements_and_retires_only_on_submit', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source);
    $snapshot = $source->fresh()->academic_snapshot;
    $waiver = $source->documents()->where('document_type', Meet::WAIVER)->firstOrFail();
    $verified = $source->documents()->where('document_type', 'thesis_draft')->firstOrFail();
    ($this->setMode)('automatic');
    expect(\App\Support\SidangTaDocumentRequirements::typesForDraftStep($draft, 3))->toBe(['graduation_payment_proof']);
    ($this->replaceDocument)($draft)->assertOk();
    expect($source->fresh()->academic_snapshot)->toBe($snapshot);
    Storage::disk('local')->assertExists($waiver->file_path);
    $draft = ($this->markReviewed)($draft);
    ($this->submitRevision)($source, $draft)->assertOk();
    expect($source->fresh()->academic_snapshot['admission']['eprt']['mode'])->toBe('automatic');
    expect($source->documents()->where('document_type', Meet::WAIVER)->exists())->toBeFalse();
    Storage::disk('local')->assertMissing($waiver->file_path);
    expect($verified->fresh()->status)->toBe('verified');
    expect($verified->fresh()->file_path)->toBe($verified->file_path);
});

test('meeting77_revision_automatic_to_waiver_opens_only_the_new_waiver_upload', function () {
    $source = ($this->submittedSource)('automatic');
    $draft = ($this->prepareRevision)($source);
    $snapshot = $source->fresh()->academic_snapshot;
    ($this->setMode)('waiver');
    expect(\App\Support\SidangTaRevisionContract::editableTypes($draft))->toContain(Meet::WAIVER);
    ($this->replaceDocument)($draft)->assertOk();
    ($this->replaceDocument)($draft, 'thesis_draft', 2)->assertUnprocessable();
    $this->postJson(route('sidang-ta-drafts.review', $draft), ['version' => $draft->fresh()->version])->assertUnprocessable();
    ($this->replaceDocument)($draft, Meet::WAIVER, 3)->assertOk();
    expect($source->fresh()->academic_snapshot)->toBe($snapshot);
    $draft = ($this->markReviewed)($draft);
    ($this->submitRevision)($source, $draft)->assertOk();
    expect($source->fresh()->academic_snapshot['admission']['eprt']['mode'])->toBe('waiver');
    expect($source->documents()->where('document_type', Meet::WAIVER)->firstOrFail()->status)->toBe('pending');
    expect($source->documents()->where('status', 'verified')->count())->toBe(6);
});

test('meeting77_revision_unknown_or_retest_live_facts_block_finalization_and_keep_source', function ($mode) {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source);
    ($this->replaceDocument)($draft)->assertOk();
    $draft = ($this->markReviewed)($draft);
    $snapshot = $source->fresh()->academic_snapshot;
    $paths = Storage::disk('local')->allFiles();
    ($this->setMode)($mode);
    ($this->submitRevision)($source, $draft)->assertUnprocessable()->assertJsonValidationErrors('eligibility.eprt');
    expect($source->fresh()->academic_snapshot)->toBe($snapshot);
    expect($source->fresh()->status)->toBe('needs_review');
    expect(Storage::disk('local')->allFiles())->toBe($paths);
})->with(['unknown', 'retest']);

test('meeting77_rejected_waiver_still_requires_a_replacement_while_waiver_is_active', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source, [Meet::WAIVER]);
    $this->postJson(route('sidang-ta-drafts.review', $draft), ['version' => $draft->fresh()->version])->assertUnprocessable();
    ($this->replaceDocument)($draft, Meet::WAIVER, 3)->assertOk();
    $draft = ($this->markReviewed)($draft);
    ($this->submitRevision)($source, $draft)->assertOk();
    expect($source->documents()->where('document_type', Meet::WAIVER)->firstOrFail()->status)->toBe('pending');
});

test('meeting77_retired_source_waiver_survives_outer_transaction_rollback', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source);
    ($this->replaceDocument)($draft)->assertOk();
    $draft = ($this->markReviewed)($draft);
    $waiver = $source->documents()->where('document_type', Meet::WAIVER)->firstOrFail();
    ($this->setMode)('automatic');
    \Illuminate\Support\Facades\DB::beginTransaction();
    try {
        app(\App\Services\SidangTaDraftFinalizer::class)->submit($this->user, $draft, $draft->version, $source->id, true);
        Storage::disk('local')->assertExists($waiver->file_path);
    } finally {
        \Illuminate\Support\Facades\DB::rollBack();
    }
    expect($source->fresh()->status)->toBe('needs_review');
    expect($waiver->fresh())->not->toBeNull();
    Storage::disk('local')->assertExists($waiver->file_path);
});


test('meeting77_revision_can_return_to_waiver_after_saving_an_automatic_stage_without_losing_verified_file', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source);
    $waiver = $source->documents()->where('document_type', Meet::WAIVER)->firstOrFail();
    ($this->replaceDocument)($draft)->assertOk();
    ($this->setMode)('automatic');
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 3]),
        ['version' => $draft->fresh()->version])->assertOk();
    ($this->setMode)('waiver');
    $draft = ($this->markReviewed)($draft);
    ($this->submitRevision)($source, $draft)->assertOk();
    expect($waiver->fresh()->status)->toBe('verified');
    expect($waiver->fresh()->file_path)->toBe($waiver->file_path);
});


test('meeting77_legacy_eprt_source_still_identifies_its_compatible_scale_without_official_type', function () {
    StudentEprt::create(['student_number' => $this->student->student_number, 'total_score' => 600]);
    $admission = app(SidangTaAdmissionFacts::class)->forStudent($this->student);
    expect($admission['eprt']['mode'])->toBe('automatic');
    expect($admission['eprt_facts']['type'])->toBeNull();
    expect($admission['eprt_facts']['scale_identified'])->toBeTrue();
    expect($admission['eprt_facts']['score'])->toBe(600);
});
