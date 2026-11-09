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
use App\Services\SidangTaRevisionExpiry;
use App\Support\SidangTaRevisionDeadline;
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
    $this->user = User::factory()->create(['username' => '1202299060', 'email' => 'revision-deadline@test.local',
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
        'open_at' => now()->subDay(), 'close_at' => now()->addDay(),
        'revision_open_at' => now()->subDay(), 'revision_close_at' => now()->addDay()]);
    AcademicPeriod::query()->update(['is_active' => false]);
    $academic = AcademicPeriod::create(['year' => '2026/2027', 'semester' => 1, 'label' => 'Meet Final KRS',
        'start_date' => '2026-08-01', 'end_date' => '2027-01-31', 'is_active' => true]);
    Course::create(['course_code' => 'MEET-DEADLINE60', 'name' => 'TUGAS AKHIR 2', 'academic_year' => '2026/2027',
        'semester_term' => 'GANJIL', 'study_program' => 'Informatika', 'class' => 'A', 'semester' => 8, 'credits' => 4]);
    StudentCourse::create(['student_number' => $this->student->student_number, 'course_code' => 'MEET-DEADLINE60',
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

// Each test owns its fixtures. These tests deliberately use ordinary request ordering,
// not parallel processes, to exercise both outcomes of the expiry/resubmission race.
test('revision_deadline_exact_closing_instant_allows_save_but_one_second_later_blocks_it', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source);
    $this->period->update(['revision_close_at' => now()]);
    expect(SidangTaRevisionDeadline::hasExpired($source))->toBeFalse();
    ($this->replaceDocument)($draft)->assertOk();
    $this->travel(1)->seconds();
    $before = $draft->fresh()->getAttributes();
    $paths = Storage::disk('local')->allFiles();
    expect(SidangTaRevisionDeadline::hasExpired($source))->toBeTrue();
    ($this->replaceDocument)($draft)->assertUnprocessable()->assertJsonValidationErrors('draft');
    expect($draft->fresh()->getAttributes())->toBe($before);
    expect(Storage::disk('local')->allFiles())->toBe($paths);
});

test('revision_deadline_rejects_starting_a_new_revision_after_close_without_creating_a_draft', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source);
    $this->deleteJson(route('sidang-ta-drafts.destroy', $draft), ['version' => $draft->version])->assertNoContent();
    $count = SidangTaRegistrationDraft::count();
    $before = $source->fresh()->getAttributes();
    $paths = Storage::disk('local')->allFiles();
    $this->period->update(['revision_close_at' => now()->subSecond()]);
    $this->postJson(route('sidang-ta-drafts.revision', $source))->assertUnprocessable()->assertJsonValidationErrors('draft');
    expect(SidangTaRegistrationDraft::count())->toBe($count);
    expect($source->fresh()->getAttributes())->toBe($before);
    expect(Storage::disk('local')->allFiles())->toBe($paths);
});

test('revision_deadline_blocks_remaining_partial_replacement_before_expiry_job', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source, ['ta_data_capture', 'online_supervision_capture']);
    ($this->replaceDocument)($draft)->assertOk();
    $saved = $draft->documents()->orderBy('id')->get()->toArray();
    $paths = Storage::disk('local')->allFiles();
    $this->period->update(['revision_close_at' => now()->subSecond()]);
    ($this->replaceDocument)($draft, 'online_supervision_capture')->assertUnprocessable()->assertJsonValidationErrors('draft');
    expect($draft->fresh()->version)->toBe(2);
    expect($draft->documents()->orderBy('id')->get()->toArray())->toBe($saved);
    expect($source->fresh()->status)->toBe('needs_review');
    expect(Storage::disk('local')->allFiles())->toBe($paths);
});

test('revision_deadline_blocks_unchanged_step_one_save_before_expiry_job', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source);
    $before = $draft->fresh()->getAttributes();
    $paths = Storage::disk('local')->allFiles();
    $this->period->update(['revision_close_at' => now()->subSecond()]);
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 1]), [
        'version' => $draft->version, 'sidang_ta_period_id' => $this->period->id,
        'student_phone' => $draft->payload['student_phone'], 'parent_phone' => $draft->payload['parent_phone'],
    ])->assertUnprocessable()->assertJsonValidationErrors('draft');
    expect($draft->fresh()->getAttributes())->toBe($before);
    expect(Storage::disk('local')->allFiles())->toBe($paths);
});

test('revision_deadline_blocks_mark_review_without_changing_review_marker', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source);
    ($this->replaceDocument)($draft)->assertOk();
    $before = $draft->fresh()->getAttributes();
    $this->period->update(['revision_close_at' => now()->subSecond()]);
    $this->postJson(route('sidang-ta-drafts.review', $draft), ['version' => $draft->fresh()->version])
        ->assertUnprocessable()->assertJsonValidationErrors('draft');
    expect($draft->fresh()->getAttributes())->toBe($before);
    expect($source->fresh()->status)->toBe('needs_review');
});

test('revision_deadline_rechecks_finalization_after_a_successful_preview', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source);
    ($this->replaceDocument)($draft)->assertOk();
    $draft = ($this->markReviewed)($draft);
    $this->get(route('sidang-ta-drafts.preview', $draft))->assertOk();
    $registration = $source->fresh()->getAttributes();
    $draftBefore = $draft->getAttributes();
    $documents = $source->documents()->orderBy('id')->get()->toArray();
    $logs = $source->logs()->count();
    $paths = Storage::disk('local')->allFiles();
    $this->period->update(['revision_close_at' => now()->subSecond()]);
    ($this->submitRevision)($source, $draft)->assertUnprocessable()->assertJsonValidationErrors('draft');
    expect($source->fresh()->getAttributes())->toBe($registration);
    expect($draft->fresh()->getAttributes())->toBe($draftBefore);
    expect($source->documents()->orderBy('id')->get()->toArray())->toBe($documents);
    expect($source->logs()->count())->toBe($logs);
    expect(Storage::disk('local')->allFiles())->toBe($paths);
});

test('revision_expiry_rejects_once_and_preserves_snapshot_documents_draft_and_all_files', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source);
    ($this->replaceDocument)($draft)->assertOk();
    $draft = ($this->markReviewed)($draft);
    $version = $source->fresh()->lock_version;
    $snapshot = $source->fresh()->academic_snapshot;
    $draftBefore = $draft->getAttributes();
    $sourceDocuments = $source->documents()->orderBy('id')->get()->toArray();
    $draftDocuments = $draft->documents()->orderBy('id')->get()->toArray();
    expect($draft->documents()->whereNull('source_document_id')->count())->toBe(1);
    expect($draft->documents()->whereNotNull('source_document_id')->count())->toBeGreaterThan(0);
    $logs = $source->logs()->count();
    $paths = Storage::disk('local')->allFiles();
    $this->period->update(['revision_close_at' => now()->subSecond()]);
    expect(app(SidangTaRevisionExpiry::class)->run())->toBe(1);
    $rejected = $source->fresh();
    expect($rejected->status)->toBe('rejected');
    expect($rejected->lock_version)->toBe($version + 1);
    expect($rejected->reviewed_by)->toBeNull();
    expect($rejected->reviewed_at->equalTo(now()))->toBeTrue();
    expect($rejected->review_notes)->toBe(SidangTaRevisionExpiry::NOTE);
    expect(SidangTaRevisionExpiry::NOTE)->toBe('Ditolak otomatis karena revisi tidak dikirim sampai batas akhir verifikasi berkas pendaftaran sidang.');
    expect($rejected->academic_snapshot)->toBe($snapshot);
    expect($draft->fresh()->getAttributes())->toBe($draftBefore);
    expect($source->documents()->orderBy('id')->get()->toArray())->toBe($sourceDocuments);
    expect($draft->documents()->orderBy('id')->get()->toArray())->toBe($draftDocuments);
    expect($source->logs()->count())->toBe($logs + 1);
    $log = $source->logs()->orderByDesc('id')->firstOrFail();
    expect($log->action)->toBe('rejected');
    expect($log->from_status)->toBe('needs_review');
    expect($log->to_status)->toBe('rejected');
    expect($log->performed_by)->toBeNull();
    expect($log->notes)->toBe(SidangTaRevisionExpiry::NOTE);
    $after = $rejected->getAttributes();
    $this->travel(1)->minutes();
    expect(app(SidangTaRevisionExpiry::class)->run())->toBe(0);
    expect(app(SidangTaRevisionExpiry::class)->expire($source->id))->toBeFalse();
    expect($source->fresh()->getAttributes())->toBe($after);
    expect($source->logs()->count())->toBe($logs + 1);
    expect(Storage::disk('local')->allFiles())->toBe($paths);
});

test('revision_expiry_skips_unsupported_marker_and_other_statuses', function (int $requirementsVersion, string $status) {
    $source = ($this->submittedSource)();
    $source->update(['requirements_version' => $requirementsVersion, 'status' => $status]);
    $this->period->update(['revision_close_at' => now()->subSecond()]);
    $before = $source->fresh()->getAttributes();
    $logs = $source->logs()->count();
    expect(app(SidangTaRevisionExpiry::class)->run())->toBe(0);
    expect(app(SidangTaRevisionExpiry::class)->expire($source->id))->toBeFalse();
    expect($source->fresh()->getAttributes())->toBe($before);
    expect($source->logs()->count())->toBe($logs);
    if ($requirementsVersion === 1) {
        try {
            SidangTaRevisionDeadline::hasExpired($source);
            $this->fail('Unsupported requirements must fail closed.');
        } catch (Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception) {
            expect($exception->getStatusCode())->toBe(409);
            expect($exception->getMessage())->toBe('Persyaratan pengajuan tidak sesuai. Muat ulang atau hubungi petugas.');
        }
        expect($source->fresh()->getAttributes())->toBe($before);
        expect($source->logs()->count())->toBe($logs);
    }
})->with([[1, 'needs_review'], [2, 'submitted'], [2, 'approved'], [2, 'rejected']]);

test('revision_expiry_artisan_reports_actual_count_then_zero_on_repeat', function () {
    $source = ($this->submittedSource)();
    ($this->prepareRevision)($source);
    $this->period->update(['revision_close_at' => now()->subSecond()]);
    $this->artisan('sidang-ta:expire-revisions')->expectsOutput('Pengajuan revisi ditolak otomatis: 1.')->assertExitCode(0);
    $this->artisan('sidang-ta:expire-revisions')->expectsOutput('Pengajuan revisi ditolak otomatis: 0.')->assertExitCode(0);
    expect($source->fresh()->status)->toBe('rejected');
});

test('revision_deadline_ignores_inactive_flag_before_close_and_expiry_still_includes_it_after_close', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source);
    $this->period->update(['is_active' => false]);
    expect(SidangTaRevisionDeadline::hasExpired($source))->toBeFalse();
    ($this->replaceDocument)($draft)->assertOk();
    expect(app(SidangTaRevisionExpiry::class)->run())->toBe(0);
    $this->period->update(['revision_close_at' => now()->subSecond()]);
    expect(app(SidangTaRevisionExpiry::class)->run())->toBe(1);
    expect($source->fresh()->status)->toBe('rejected');
});

test('revision_deadline_reads_fresh_period_after_an_extension_before_expiry_runs', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source);
    $this->period->update(['revision_close_at' => now()->subSecond()]);
    $source->load('period'); // Deliberately cache the old, already closed period.
    expect(SidangTaRevisionDeadline::hasExpired($source))->toBeTrue();
    SidangTaPeriod::whereKey($this->period->id)->update(['revision_close_at' => now()->addHour()]);
    expect(SidangTaRevisionDeadline::hasExpired($source))->toBeFalse();
    expect(app(SidangTaRevisionExpiry::class)->expire($source->id))->toBeFalse();
    expect(app(SidangTaRevisionExpiry::class)->run())->toBe(0);
    ($this->replaceDocument)($draft)->assertOk();
    expect($source->fresh()->status)->toBe('needs_review');
});

test('revision_expiry_rechecks_status_when_a_previously_selected_source_has_changed', function () {
    $source = ($this->submittedSource)();
    ($this->prepareRevision)($source);
    $this->period->update(['revision_close_at' => now()->subSecond()]);
    $candidateId = $source->id;
    $source->refresh()->update(['status' => 'submitted']);
    $before = $source->fresh()->getAttributes();
    $logs = $source->logs()->count();
    expect(app(SidangTaRevisionExpiry::class)->expire($candidateId))->toBeFalse();
    expect($source->fresh()->getAttributes())->toBe($before);
    expect($source->logs()->count())->toBe($logs);
});

test('revision_submitted_before_deadline_wins_and_consumed_replay_after_close_returns_later_state', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source);
    ($this->replaceDocument)($draft)->assertOk();
    $draft = ($this->markReviewed)($draft);
    ($this->submitRevision)($source, $draft)->assertOk()->assertJsonPath('data.status', 'submitted');
    $source->update(['status' => 'approved']);
    $sourceBefore = $source->fresh()->getAttributes();
    $version = $source->fresh()->lock_version;
    $logs = $source->logs()->count();
    $paths = Storage::disk('local')->allFiles();
    $this->period->update(['revision_close_at' => now()->subSecond()]);
    expect(app(SidangTaRevisionExpiry::class)->expire($source->id))->toBeFalse();
    ($this->submitRevision)($source, $draft)->assertOk()->assertJsonPath('data.status', 'approved');
    expect($source->fresh()->getAttributes())->toBe($sourceBefore);
    expect($source->fresh()->lock_version)->toBe($version);
    expect($draft->fresh()->status)->toBe('consumed');
    expect($source->logs()->count())->toBe($logs);
    expect(Storage::disk('local')->allFiles())->toBe($paths);
});

test('revision_expiry_wins_then_stale_draft_discard_removes_only_owned_replacement', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source);
    $originalPaths = $source->documents()->pluck('file_path')->all();
    ($this->replaceDocument)($draft)->assertOk();
    $owned = $draft->documents()->whereNull('source_document_id')->firstOrFail()->file_path;
    $draft = ($this->markReviewed)($draft);
    $this->period->update(['revision_close_at' => now()->subSecond()]);
    expect(app(SidangTaRevisionExpiry::class)->expire($source->id))->toBeTrue();
    ($this->submitRevision)($source, $draft)->assertConflict();
    Storage::disk('local')->assertExists($owned);
    $logs = $source->logs()->count();
    $version = $source->fresh()->lock_version;
    $index = new Crawler($this->get(route('sidang-ta-registrations.index'))->assertOk()->getContent());
    $card = $index->filter('[data-testid="draft-card"]')->reduce(fn (Crawler $node) => $node->filter('#discard-draft-'.$draft->id)->count() === 1);
    expect($card->count())->toBe(1);
    expect($card->filter('a[href="'.route('sidang-ta-drafts.edit', $draft).'"]')->count())->toBe(0);
    expect($card->filter('[data-open-discard]')->count())->toBe(1);
    $this->deleteJson(route('sidang-ta-drafts.destroy', $draft), ['version' => $draft->fresh()->version])->assertNoContent();
    Storage::disk('local')->assertMissing($owned);
    foreach ($originalPaths as $path) Storage::disk('local')->assertExists($path);
    expect($source->fresh()->status)->toBe('rejected');
    expect($source->fresh()->lock_version)->toBe($version);
    expect($source->logs()->count())->toBe($logs);
});

test('revision_deadline_staff_cannot_request_revision_after_close', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source);
    $this->deleteJson(route('sidang-ta-drafts.destroy', $draft), ['version' => $draft->version])->assertNoContent();
    $source->refresh()->update(['status' => 'submitted']);
    $this->period->update(['revision_close_at' => now()->subSecond()]);
    $this->actingAs($this->reviewer);
    $before = $source->fresh()->getAttributes();
    $logs = $source->logs()->count();
    $crawler = new Crawler($this->get(route('admin.sidang-ta-registrations.show', $source))->assertOk()->getContent());
    expect($crawler->filter('[data-ta-revision-blockers]')->count())->toBe(1);
    expect($crawler->filter('[data-ta-request-revision]')->getNode(0)->hasAttribute('disabled'))->toBeTrue();
    $this->postJson(route('admin.sidang-ta-registrations.review', $source), [
        'registration_version' => $source->fresh()->lock_version, 'action' => 'needs_review',
        'review_notes' => 'Perbaiki dokumen yang ditandai.',
    ])->assertUnprocessable()->assertJsonValidationErrors('action');
    expect($source->fresh()->getAttributes())->toBe($before);
    expect($source->logs()->count())->toBe($logs);
});

test('revision_deadline_expired_wizard_keeps_preview_and_disables_save_and_review_before_job', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source);
    ($this->replaceDocument)($draft)->assertOk();
    $this->period->update(['revision_close_at' => now()->subSecond()]);
    $response = $this->get(route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 4]))->assertOk();
    $response->assertViewHas('meetContext', fn ($context) => $context['revision_expired'] === true && $context['can_submit'] === false);
    $crawler = new Crawler($response->getContent());
    expect($crawler->filter('[data-revision-deadline-expired][role="alert"]')->count())->toBe(1);
    expect($crawler->filter('[data-save-draft]')->getNode(0)->hasAttribute('disabled'))->toBeTrue();
    expect($crawler->filter('[data-review-submit]')->getNode(0)->hasAttribute('disabled'))->toBeTrue();
    $document = $draft->documents()->whereNotNull('source_document_id')->firstOrFail();
    $this->get(route('sidang-ta-draft-documents.preview', $document))->assertOk();
    expect($source->fresh()->status)->toBe('needs_review');
});

test('revision_deadline_expired_review_keeps_documents_readable_and_send_buttons_disabled_before_job', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source);
    ($this->replaceDocument)($draft)->assertOk();
    $draft = ($this->markReviewed)($draft);
    $this->period->update(['revision_close_at' => now()->subSecond()]);
    $response = $this->get(route('sidang-ta-drafts.preview', $draft))->assertOk();
    $response->assertViewHas('meetContext', fn ($context) => $context['revision_expired'] === true && $context['can_submit'] === false);
    $crawler = new Crawler($response->getContent());
    expect($crawler->filter('[data-revision-deadline-expired][role="alert"]')->count())->toBe(1);
    expect($crawler->filter('a[data-document-preview]')->count())->toBe(8);
    foreach (['data-open-registration', 'data-confirm-registration'] as $selector) {
        expect($crawler->filter('['.$selector.']')->getNode(0)->hasAttribute('disabled'))->toBeTrue();
    }
    $document = $draft->documents()->whereNull('source_document_id')->firstOrFail();
    $this->get(route('sidang-ta-draft-documents.preview', $document))->assertOk();
    expect($source->fresh()->status)->toBe('needs_review');
});

test('revision_expiry_also_rejects_overdue_source_when_student_never_keeps_a_revision_draft', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source);
    $this->deleteJson(route('sidang-ta-drafts.destroy', $draft), ['version' => $draft->version])->assertNoContent();
    expect(SidangTaRegistrationDraft::where('source_registration_id', $source->id)->where('status', 'active')->exists())->toBeFalse();
    $documents = $source->documents()->orderBy('id')->get()->toArray();
    $paths = Storage::disk('local')->allFiles();
    $this->period->update(['revision_close_at' => now()->subSecond()]);
    expect(app(SidangTaRevisionExpiry::class)->run())->toBe(1);
    expect($source->fresh()->status)->toBe('rejected');
    expect($source->documents()->orderBy('id')->get()->toArray())->toBe($documents);
    expect(Storage::disk('local')->allFiles())->toBe($paths);
});

test('revision_expiry_does_not_reject_at_exact_deadline_but_rejects_one_second_after', function () {
    $source = ($this->submittedSource)();
    ($this->prepareRevision)($source);
    $this->period->update(['revision_close_at' => now()]);
    $before = $source->fresh()->getAttributes();
    expect(app(SidangTaRevisionExpiry::class)->expire($source->id))->toBeFalse();
    expect(app(SidangTaRevisionExpiry::class)->run())->toBe(0);
    expect($source->fresh()->getAttributes())->toBe($before);
    $this->travel(1)->seconds();
    expect(app(SidangTaRevisionExpiry::class)->run())->toBe(1);
    expect($source->fresh()->status)->toBe('rejected');
});

test('revision_deadline_expired_step_two_keeps_requested_document_preview_without_upload_input', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source);
    $this->period->update(['revision_close_at' => now()->subSecond()]);
    $response = $this->get(route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 2]))->assertOk();
    $crawler = new Crawler($response->getContent());
    expect($crawler->filter('[data-revision-deadline-expired][role="alert"]')->count())->toBe(1);
    $requested = $crawler->filter('[data-upload-type="ta_data_capture"]');
    expect($requested->count())->toBe(1);
    expect($requested->filter('input[type="file"]')->count())->toBe(0);
    $document = $draft->documents()->where('document_type', 'ta_data_capture')->firstOrFail();
    expect($requested->text(null, true))->toContain($document->file_name);
    $this->get(route('sidang-ta-draft-documents.preview', $document))->assertOk();
    expect($crawler->filter('[data-save-draft]')->getNode(0)->hasAttribute('disabled'))->toBeTrue();
    expect($draft->fresh()->version)->toBe(1);
    expect($source->fresh()->status)->toBe('needs_review');
});


test('revision_window85_full_revision_submit_works_after_registration_close', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source);
    $this->period->update(['close_at' => now()->subSecond(), 'revision_close_at' => now()->addDay()]);
    ($this->replaceDocument)($draft)->assertOk();
    $draft = ($this->markReviewed)($draft);
    ($this->submitRevision)($source, $draft)->assertOk();
    expect($source->fresh()->status)->toBe('submitted');
    expect($draft->fresh()->status)->toBe('consumed');
});

test('revision_window85_before_start_blocks_HTTP_save_review_and_submit_without_mutations', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source);
    $this->period->update(['revision_open_at' => now()->addHour(), 'revision_close_at' => now()->addDay()]);
    $before = $draft->fresh()->getAttributes();
    $files = Storage::disk('local')->allFiles();
    ($this->replaceDocument)($draft)->assertUnprocessable()->assertJsonValidationErrors('draft');
    $this->postJson(route('sidang-ta-drafts.review', $draft), ['version' => $draft->version])->assertUnprocessable()->assertJsonValidationErrors('draft');
    ($this->submitRevision)($source, $draft)->assertUnprocessable();
    expect($draft->fresh()->getAttributes())->toBe($before);
    expect(Storage::disk('local')->allFiles())->toBe($files);
    expect($source->fresh()->status)->toBe('needs_review');
});
