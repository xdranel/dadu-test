<?php

use App\Models\AcademicPeriod;
use App\Models\Course;
use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDraft;
use App\Models\Student;
use App\Models\StudentCourse;
use App\Models\StudentEprt;
use App\Models\User;
use App\Services\StudentAcademicProfileImportService;
use App\Support\SidangTaMeetRequirements as Meet;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\DomCrawler\Crawler;

beforeEach(function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-11-04 12:00:00', config('app.timezone')));
    Storage::fake('local');
    $this->user = User::factory()->create(['username' => '1202299061', 'email' => 'single-contract@test.local',
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
    Course::create(['course_code' => 'SINGLE-TA61', 'name' => 'TUGAS AKHIR 2', 'academic_year' => '2026/2027',
        'semester_term' => 'GANJIL', 'study_program' => 'Informatika', 'class' => 'A', 'semester' => 8, 'credits' => 4]);
    StudentCourse::create(['student_number' => $this->student->student_number, 'course_code' => 'SINGLE-TA61',
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
                'graduation_payment_date' => today()->toDateString(), 'diploma_biodata_accurate' => $accurate]]);
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
            'draft_id' => $draft->id, 'version' => $version ?? $draft->fresh()->version, 'action' => 'submit',
        ]);
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
            'draft_id' => $draft->id, 'version' => $draft->fresh()->version, 'action' => 'submit',
        ]);
    };
});

afterEach(function () { $this->travelBack(); });

test('single_new_registration_uses_current_requirements_for_ordinary_accounts_and_rejects_client_version', function () {
    foreach ([1, 2, 99] as $marker) {
        $this->postJson(route('sidang-ta-drafts.store'), ['sidang_ta_period_id' => $this->period->id,
            'student_phone' => '081234567890', 'parent_phone' => '081234567891', 'requirements_version' => $marker])
            ->assertUnprocessable()->assertJsonValidationErrors('requirements_version');
    }
    $id = $this->postJson(route('sidang-ta-drafts.store'), ['sidang_ta_period_id' => $this->period->id,
        'student_phone' => '081234567890', 'parent_phone' => '081234567891'])->assertCreated()->json('data.id');
    expect(SidangTaRegistrationDraft::findOrFail($id)->requirements_version)->toBe(2);
    expect((new SidangTaRegistration)->requirements_version)->toBe(2);
    expect((new SidangTaRegistrationDraft)->requirements_version)->toBe(2);
    $other = User::factory()->create(['username' => '1202299062', 'email' => 'ordinary-second@test.local',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
    Student::create(['student_number' => $other->username, 'name' => 'Ordinary Second', 'email' => $other->email,
        'academic_year' => 2022, 'study_program' => 'Informatika', 'status' => 'Aktif']);
    $other->givePermissionTo(['sidang_ta_registration_read', 'sidang_ta_registration_create']);
    $this->actingAs($other);
    $id = $this->postJson(route('sidang-ta-drafts.store'), ['sidang_ta_period_id' => $this->period->id,
        'student_phone' => '081234567890', 'parent_phone' => '081234567891'])->assertCreated()->json('data.id');
    expect(SidangTaRegistrationDraft::findOrFail($id)->requirements_version)->toBe(2);
    expect(SidangTaRegistrationDraft::count())->toBe(2);
});

test('single_unsupported_active_marker_cannot_read_save_review_or_submit_and_keeps_files', function (int $marker) {
    $draft = ($this->meetDraft)('automatic');
    $draft->update(['requirements_version' => $marker, 'reviewed_version' => $draft->version]);
    $before = $draft->fresh()->getAttributes();
    $paths = Storage::disk('local')->allFiles();
    $this->getJson(route('sidang-ta-drafts.edit', $draft))->assertConflict();
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 1]), [
        'version' => $draft->version, 'sidang_ta_period_id' => $this->period->id,
        'student_phone' => '081234567890', 'parent_phone' => '081234567891'])->assertConflict();
    $this->postJson(route('sidang-ta-drafts.review', $draft), ['version' => $draft->version])->assertConflict();
    ($this->submitMeet)($draft)->assertConflict();
    expect($draft->fresh()->getAttributes())->toBe($before);
    expect(Storage::disk('local')->allFiles())->toBe($paths);
    expect(SidangTaRegistration::count())->toBe(0);
})->with([1, 3]);

test('single_unsupported_consumed_marker_never_replays_or_overwrites_the_saved_decision', function (int $draftMarker, int $registrationMarker) {
    $draft = ($this->markReviewed)(($this->meetDraft)('automatic'));
    $id = ($this->submitMeet)($draft)->assertOk()->json('data.id');
    $registration = SidangTaRegistration::findOrFail($id);
    $registration->forceFill(['status' => 'approved', 'requirements_version' => $registrationMarker])->save();
    $draft->refresh()->update(['requirements_version' => $draftMarker]);
    $before = $registration->fresh()->getAttributes();
    $logs = $registration->logs()->count();
    $paths = Storage::disk('local')->allFiles();
    ($this->submitMeet)($draft)->assertConflict();
    expect($registration->fresh()->getAttributes())->toBe($before);
    expect($registration->logs()->count())->toBe($logs);
    expect(Storage::disk('local')->allFiles())->toBe($paths);
})->with([[1, 2], [2, 1], [3, 2], [2, 3]]);

test('single_finalization_database_failure_rolls_back_metadata_without_removing_any_draft_file', function () {
    $draft = ($this->markReviewed)(($this->meetDraft)('automatic'));
    $before = $draft->getAttributes();
    $documents = $draft->documents()->orderBy('id')->get()->toArray();
    $paths = Storage::disk('local')->allFiles();
    $event = 'eloquent.creating: '.\App\Models\SidangTaRegistrationDocument::class;
    $writes = 0;
    \Illuminate\Support\Facades\Event::listen($event, function () use (&$writes) {
        if (++$writes === 2) throw new RuntimeException('single finalization rollback');
    });
    try {
        expect(fn () => app(\App\Services\SidangTaDraftFinalizer::class)->submit($this->user, $draft, $draft->version))
            ->toThrow(RuntimeException::class, 'single finalization rollback');
    } finally { \Illuminate\Support\Facades\Event::forget($event); }
    expect(SidangTaRegistration::count())->toBe(0);
    expect(\App\Models\SidangTaRegistrationDocument::count())->toBe(0);
    expect($draft->fresh()->getAttributes())->toBe($before);
    expect($draft->documents()->orderBy('id')->get()->toArray())->toBe($documents);
    expect(Storage::disk('local')->allFiles())->toBe($paths);
});

test('single_document_review_log_failure_rolls_back_document_and_registration', function () {
    $registration = ($this->submittedSource)('automatic');
    $reviewer = ($this->asReviewer)();
    $document = $registration->documents()->firstOrFail();
    $before = $registration->getAttributes();
    $docBefore = $document->getAttributes();
    $event = 'eloquent.creating: '.\App\Models\SidangTaRegistrationLog::class;
    \Illuminate\Support\Facades\Event::listen($event, function () { throw new RuntimeException('single review rollback'); });
    try {
        expect(fn () => app(\App\Services\SidangTaRegistrationReview::class)->document($reviewer,
            $registration, $document, $registration->lock_version, 'verified', null))
            ->toThrow(RuntimeException::class, 'single review rollback');
    } finally { \Illuminate\Support\Facades\Event::forget($event); }
    expect($registration->fresh()->getAttributes())->toBe($before);
    expect($document->fresh()->getAttributes())->toBe($docBefore);
    expect($registration->logs()->count())->toBe(1);
    Storage::disk('local')->assertExists($document->file_path);
});

test('single_revision_owner_with_create_only_cannot_start_save_or_discard_a_revision', function () {
    $registration = ($this->submittedSource)('automatic');
    $draft = ($this->prepareRevision)($registration);
    $before = $draft->getAttributes();
    $paths = Storage::disk('local')->allFiles();
    $this->user->revokePermissionTo('sidang_ta_registration_update');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($this->user->fresh());
    $this->postJson(route('sidang-ta-drafts.revision', $registration))->assertForbidden();
    ($this->replaceDocument)($draft)->assertForbidden();
    $this->deleteJson(route('sidang-ta-drafts.destroy', $draft), ['version' => $draft->version])->assertForbidden();
    expect($draft->fresh()->getAttributes())->toBe($before);
    expect(Storage::disk('local')->allFiles())->toBe($paths);
});

test('single_staff_history_stays_collapsed_and_escaped_including_an_empty_history', function (bool $empty) {
    $registration = ($this->submittedSource)('automatic');
    if ($empty) $registration->logs()->delete();
    else $registration->logAction('document_rejected', 'submitted', 'submitted', '<script>history</script>', $this->user->id);
    ($this->asReviewer)();
    $crawler = new Crawler($this->get(route('admin.sidang-ta-registrations.show', $registration))->assertOk()->getContent());
    $history = $crawler->filter('details[data-staff-registration-history]');
    expect($history->count())->toBe(1);
    expect($history->getNode(0)->hasAttribute('open'))->toBeFalse();
    expect($history->filter('script')->count())->toBe(0);
    expect($history->text(null, true))->toContain($empty ? 'Belum ada riwayat status.' : '<script>history</script>');
})->with([true, false]);

test('single_stale_and_consumed_revision_drafts_cannot_be_discarded_or_delete_final_files', function () {
    $source = ($this->submittedSource)('automatic');
    $oldRejectedPath = $source->documents()->where('document_type', 'ta_data_capture')->value('file_path');
    $finalPaths = $source->documents()->where('document_type', '!=', 'ta_data_capture')->pluck('file_path')->all();
    $draft = ($this->prepareRevision)($source);
    $this->deleteJson(route('sidang-ta-drafts.destroy', $draft), ['version' => $draft->version + 1])->assertConflict();
    expect($draft->fresh()->status)->toBe('active');
    expect($source->fresh()->status)->toBe('needs_review');
    ($this->replaceDocument)($draft)->assertOk();
    $ownedPath = $draft->documents()->whereNull('source_document_id')->value('file_path');
    $draft = ($this->markReviewed)($draft);
    $this->putJson(route('sidang-ta-registrations.update', $source), [
        'draft_id' => $draft->id, 'version' => $draft->version, 'action' => 'submit',
    ])->assertOk();
    $consumed = $draft->fresh();
    expect($consumed->status)->toBe('consumed');
    $this->deleteJson(route('sidang-ta-drafts.destroy', $consumed), ['version' => $consumed->version])->assertConflict();
    Storage::disk('local')->assertExists($ownedPath);
    Storage::disk('local')->assertMissing($oldRejectedPath);
    foreach ($finalPaths as $path) Storage::disk('local')->assertExists($path);
    expect($source->fresh()->status)->toBe('submitted');
});

test('single_revision_draft_rejects_initial_submit_and_wrong_source_without_mutation', function () {
    $source = ($this->submittedSource)('automatic');
    $draft = ($this->prepareRevision)($source);
    ($this->replaceDocument)($draft)->assertOk();
    $draft = ($this->markReviewed)($draft);
    $before = [$draft->fresh()->getAttributes(), $draft->documents()->orderBy('id')->get()->toArray(),
        Storage::disk('local')->allFiles(), $source->fresh()->getAttributes()];
    $this->postJson(route('sidang-ta-registrations.store'), [
        'draft_id' => $draft->id, 'version' => $draft->version, 'action' => 'submit',
    ])->assertConflict();
    $wrongSource = $source->replicate();
    $wrongSource->status = 'needs_review';
    $wrongSource->lock_version = 1;
    $wrongSource->save();
    $this->putJson(route('sidang-ta-registrations.update', $wrongSource), [
        'draft_id' => $draft->id, 'version' => $draft->version, 'action' => 'submit',
    ])->assertConflict();
    expect([$draft->fresh()->getAttributes(), $draft->documents()->orderBy('id')->get()->toArray(),
        Storage::disk('local')->allFiles(), $source->fresh()->getAttributes()])->toBe($before);
});

test('single_saved_and_final_requests_cannot_replace_the_server_contract_or_upload_at_finalization', function () {
    $id = $this->postJson(route('sidang-ta-drafts.store'), ['sidang_ta_period_id' => $this->period->id,
        'student_phone' => '081234567890', 'parent_phone' => '081234567891'])->assertCreated()->json('data.id');
    $draft = SidangTaRegistrationDraft::findOrFail($id);
    foreach ([1, 99] as $marker) {
        $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 1]), [
            'version' => $draft->version, 'sidang_ta_period_id' => $this->period->id,
            'student_phone' => '081234567890', 'parent_phone' => '081234567891', 'requirements_version' => $marker])
            ->assertUnprocessable()->assertJsonValidationErrors('requirements_version');
        ($this->submitMeet)($draft)->assertConflict();
        $this->postJson(route('sidang-ta-registrations.store'), ['draft_id' => $draft->id,
            'version' => $draft->version, 'action' => 'submit', 'requirements_version' => $marker])
            ->assertUnprocessable()->assertJsonValidationErrors('requirements_version');
    }
    $this->postJson(route('sidang-ta-registrations.store'), ['draft_id' => $draft->id,
        'version' => $draft->version, 'action' => 'submit', 'unexpected_file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])
        ->assertUnprocessable()->assertJsonValidationErrors('documents');
    expect($draft->fresh()->version)->toBe(1);
    expect($draft->documents()->count())->toBe(0);
    expect(SidangTaRegistration::count())->toBe(0);
});
