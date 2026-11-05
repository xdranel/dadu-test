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
    $this->user = User::factory()->create(['username' => '1202299056', 'email' => 'meet-final@test.local',
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
    Course::create(['course_code' => 'MEET-FINAL56', 'name' => 'TUGAS AKHIR 2', 'academic_year' => '2026/2027',
        'semester_term' => 'GANJIL', 'study_program' => 'Informatika', 'class' => 'A', 'semester' => 8, 'credits' => 4]);
    StudentCourse::create(['student_number' => $this->student->student_number, 'course_code' => 'MEET-FINAL56',
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
    $this->assertPreserved = function ($draft, array $paths) {
        expect($draft->fresh()->status)->toBe('active');
        expect($draft->documents()->count())->toBe(count($paths));
        expect(Storage::disk('local')->allFiles())->toBe($paths);
        expect(SidangTaRegistration::count())->toBe(0);
    };
});

afterEach(function () { $this->travelBack(); });

test('meet_final_automatic_submission_transfers_seven_files_and_records_a_historical_decision', function () {
    $draft = ($this->markReviewed)(($this->meetDraft)());
    $paths = Storage::disk('local')->allFiles();
    $id = ($this->submitMeet)($draft)->assertOk()->assertJsonPath('data.status', 'submitted')->json('data.id');
    $registration = SidangTaRegistration::findOrFail($id);
    expect($registration->requirements_version)->toBe(2);
    expect($registration->documents()->count())->toBe(7);
    expect($registration->documents()->where('status', 'pending')->count())->toBe(7);
    expect($draft->fresh()->status)->toBe('consumed');
    expect($draft->fresh()->submitted_registration_id)->toBe($id);
    expect($draft->documents()->count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBe($paths);
    expect($registration->logs()->count())->toBe(1);
    $snapshot = $registration->academic_snapshot;
    expect($snapshot['admission']['academic']['academic_passed'])->toBeTrue();
    expect($snapshot['admission']['eprt']['mode'])->toBe('automatic');
    expect($snapshot['admission']['checked_at'])->toBe(now()->toIso8601String());
    expect((int) $snapshot['tak_score'])->toBe(60);
    expect((int) $snapshot['passed_credits'])->toBe(120);
    expect((int) $snapshot['eprt_score'])->toBe(450);
    StudentTak::where('student_number', $this->student->student_number)->update(['total_points' => 0]);
    ($this->setMode)('waiver');
    expect($registration->fresh()->academic_snapshot)->toBe($snapshot);
    expect(Eligibility::requiredTypes($registration->fresh()))->toHaveCount(7);
    $crawler = new Crawler($this->get(route('sidang-ta-registrations.show', $registration))->assertOk()->assertDontSee('Riwayat status')->getContent());
    expect($crawler->filter('[data-snapshot-field="tak_score"]')->text(null, true))->toBe('60');
    expect($crawler->filter('[data-academic-detail]')->text(null, true))->not->toContain('academic_passed');
});

test('meet_final_waiver_submission_keeps_eight_files_and_a_readable_waiver_label', function () {
    $draft = ($this->markReviewed)(($this->meetDraft)('waiver'));
    $id = ($this->submitMeet)($draft)->assertOk()->json('data.id');
    $registration = SidangTaRegistration::findOrFail($id);
    expect($registration->documents()->count())->toBe(8);
    expect($registration->academic_snapshot['admission']['eprt']['mode'])->toBe('waiver');
    $this->get(route('sidang-ta-registrations.show', $registration))->assertOk()->assertSee('Surat Permakluman EPRT');
    expect(Eligibility::requiredTypes($registration))->toContain(Meet::WAIVER);
});

test('meet_final_pdm_submission_keeps_six_files_and_only_the_selected_biodata_branch', function () {
    $draft = ($this->markReviewed)(($this->meetDraft)('automatic', false));
    $id = ($this->submitMeet)($draft)->assertOk()->json('data.id');
    $registration = SidangTaRegistration::findOrFail($id);
    expect($registration->documents()->count())->toBe(6);
    expect($registration->documents()->whereIn('document_type', Meet::DIPLOMA)->exists())->toBeFalse();
    expect(Eligibility::groups($registration)['Biodata'])->toBe(Meet::PDM);
});

test('meet_final_rereads_each_academic_gate_after_review_and_preserves_the_draft', function (string $gate) {
    $draft = ($this->markReviewed)(($this->meetDraft)());
    $paths = Storage::disk('local')->allFiles();
    match ($gate) {
        'level_2_passed' => $this->student->sidangTingkat()->update(['tingkat_2' => false]),
        'ta2_registered' => StudentCourse::where('student_number', $this->student->student_number)->delete(),
        'skpi_approved' => $this->student->skpi()->firstOrFail()->update(['raw_data' => ['approval_status' => 'pending']]),
        'tak_score' => StudentTak::where('student_number', $this->student->student_number)->update(['total_points' => 59]),
        'passed_credits' => $this->student->courseIssues()->update(['credits_passed' => 119]),
    };
    ($this->submitMeet)($draft)->assertUnprocessable()->assertJsonValidationErrors('eligibility.'.$gate);
    ($this->assertPreserved)($draft, $paths);
    expect($draft->fresh()->reviewed_version)->toBe($draft->version);
})->with(['level_2_passed', 'ta2_registered', 'skpi_approved', 'tak_score', 'passed_credits']);

test('meet_final_missing_skpi_stays_unknown_and_cannot_be_forged_with_http_fields', function () {
    $draft = ($this->markReviewed)(($this->meetDraft)());
    $paths = Storage::disk('local')->allFiles();
    $this->student->skpi()->delete();
    $this->postJson(route('sidang-ta-registrations.store'), ['draft_id' => $draft->id,
        'version' => $draft->version, 'action' => 'submit', 'skpi_approved' => true, 'academic_passed' => true,
        'admission' => ['academic' => ['academic_passed' => true]]])
        ->assertUnprocessable()->assertJsonValidationErrors('eligibility.skpi_approved');
    ($this->assertPreserved)($draft, $paths);
});

test('meet_final_retest_and_unknown_eprt_block_only_submission', function (string $mode) {
    $draft = ($this->markReviewed)(($this->meetDraft)($mode));
    $paths = Storage::disk('local')->allFiles();
    $this->get(route('sidang-ta-drafts.preview', $draft))->assertOk();
    ($this->submitMeet)($draft)->assertUnprocessable()->assertJsonValidationErrors('eligibility.eprt');
    ($this->assertPreserved)($draft, $paths);
})->with(['retest', 'unknown']);

test('meet_final_new_waiver_requirement_after_review_requires_upload_and_another_review', function () {
    $draft = ($this->markReviewed)(($this->meetDraft)());
    $paths = Storage::disk('local')->allFiles();
    ($this->setMode)('waiver');
    ($this->submitMeet)($draft)->assertUnprocessable()->assertJsonValidationErrors('draft');
    ($this->assertPreserved)($draft, $paths);
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 3]), [
        'version' => $draft->version, 'documents' => [Meet::WAIVER => UploadedFile::fake()->create('waiver.pdf', 100, 'application/pdf')],
    ])->assertOk();
    expect($draft->fresh()->reviewed_version)->toBeNull();
    ($this->submitMeet)($draft)->assertConflict();
    $draft = ($this->markReviewed)($draft->fresh());
    $id = ($this->submitMeet)($draft)->assertOk()->json('data.id');
    expect(SidangTaRegistration::findOrFail($id)->documents()->count())->toBe(8);
});

test('meet_final_qualifying_score_retires_only_the_obsolete_waiver_after_successful_submission', function () {
    $draft = ($this->markReviewed)(($this->meetDraft)('waiver'));
    $waiver = $draft->documents()->where('document_type', Meet::WAIVER)->firstOrFail();
    $keep = $draft->documents()->where('document_type', 'thesis_draft')->firstOrFail()->file_path;
    ($this->setMode)('automatic');
    $id = ($this->submitMeet)($draft)->assertOk()->json('data.id');
    $registration = SidangTaRegistration::findOrFail($id);
    expect($registration->documents()->count())->toBe(7);
    expect($registration->documents()->where('document_type', Meet::WAIVER)->exists())->toBeFalse();
    expect($registration->academic_snapshot['admission']['eprt']['mode'])->toBe('automatic');
    Storage::disk('local')->assertMissing($waiver->file_path);
    Storage::disk('local')->assertExists($keep);
});

test('meet_final_failed_check_keeps_a_previously_uploaded_waiver', function (string $mode) {
    $draft = ($this->markReviewed)(($this->meetDraft)('waiver'));
    $paths = Storage::disk('local')->allFiles();
    ($this->setMode)($mode);
    ($this->submitMeet)($draft)->assertUnprocessable()->assertJsonValidationErrors('eligibility.eprt');
    ($this->assertPreserved)($draft, $paths);
    expect($draft->documents()->where('document_type', Meet::WAIVER)->exists())->toBeTrue();
})->with(['retest', 'unknown']);

test('meet_final_missing_file_is_reported_without_deleting_metadata_or_other_files', function () {
    $draft = ($this->markReviewed)(($this->meetDraft)('waiver'));
    $document = $draft->documents()->where('document_type', Meet::WAIVER)->firstOrFail();
    Storage::disk('local')->delete($document->file_path);
    $paths = Storage::disk('local')->allFiles();
    ($this->submitMeet)($draft)->assertUnprocessable()->assertJsonValidationErrors('documents.'.Meet::WAIVER);
    expect($draft->fresh()->status)->toBe('active');
    expect($draft->documents()->count())->toBe(8);
    expect(Storage::disk('local')->allFiles())->toBe($paths);
    expect(SidangTaRegistration::count())->toBe(0);
});

test('meet_final_unreviewed_stale_and_closed_period_submissions_preserve_every_file', function () {
    $draft = ($this->meetDraft)();
    $paths = Storage::disk('local')->allFiles();
    ($this->submitMeet)($draft)->assertConflict();
    $draft = ($this->markReviewed)($draft);
    ($this->submitMeet)($draft, $draft->version + 1)->assertConflict();
    $this->period->update(['close_at' => now()->subSecond()]);
    ($this->submitMeet)($draft)->assertUnprocessable()->assertJsonValidationErrors('draft');
    ($this->assertPreserved)($draft, $paths);
});

test('meet_final_another_user_cannot_submit_the_owners_draft', function () {
    $draft = ($this->markReviewed)(($this->meetDraft)());
    $paths = Storage::disk('local')->allFiles();
    $other = User::factory()->create(['status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
    $other->givePermissionTo('sidang_ta_registration_create');
    $this->actingAs($other);
    ($this->submitMeet)($draft)->assertForbidden();
    ($this->assertPreserved)($draft, $paths);
});

test('meet_final_consumed_replay_does_not_reread_gates_or_change_decision_and_audit', function () {
    $draft = ($this->markReviewed)(($this->meetDraft)());
    $id = ($this->submitMeet)($draft)->assertOk()->json('data.id');
    $registration = SidangTaRegistration::findOrFail($id);
    $registration->update(['status' => 'approved']);
    $snapshot = $registration->academic_snapshot;
    $paths = Storage::disk('local')->allFiles();
    $this->student->skpi()->delete();
    $this->period->update(['close_at' => now()->subSecond()]);
    $this->mock(SidangTaAdmissionFacts::class)->shouldNotReceive('forStudent');
    ($this->submitMeet)($draft)->assertOk()->assertJsonPath('data.id', $id)->assertJsonPath('data.status', 'approved');
    expect($registration->fresh()->academic_snapshot)->toBe($snapshot);
    expect($registration->logs()->count())->toBe(1);
    expect(Storage::disk('local')->allFiles())->toBe($paths);
});

test('meet_final_required_files_and_snapshot_use_the_same_final_facts_result', function () {
    $draft = ($this->markReviewed)(($this->meetDraft)('waiver'));
    $finalFacts = app(SidangTaAdmissionFacts::class)->forStudent($this->student);
    $this->mock(SidangTaAdmissionFacts::class)->shouldReceive('forStudent')->once()->andReturn($finalFacts);
    $id = ($this->submitMeet)($draft)->assertOk()->json('data.id');
    $registration = SidangTaRegistration::findOrFail($id);
    expect($registration->documents()->count())->toBe(8);
    $stored = $registration->academic_snapshot['admission']['eprt'];
    $expected = $finalFacts['eprt'];
    ksort($stored);
    ksort($expected);
    expect($stored)->toBe($expected);
});


test('meet_final_extra_legacy_document_is_rejected_without_retiring_any_file', function () {
    $draft = ($this->meetDraft)();
    $path = 'sidang_ta/drafts/'.$draft->id.'/obsolete.pdf';
    Storage::disk('local')->put($path, '%PDF-1.4 obsolete');
    $draft->documents()->create(['document_type' => 'transcript_khs', 'file_path' => $path,
        'file_name' => 'obsolete.pdf', 'file_type' => 'pdf', 'file_size' => 17]);
    $draft = ($this->markReviewed)($draft);
    $paths = Storage::disk('local')->allFiles();
    ($this->submitMeet)($draft)->assertUnprocessable()->assertJsonValidationErrors('draft');
    ($this->assertPreserved)($draft, $paths);
});

test('meet_final_staff_can_read_preview_and_export_new_files_but_mutation_is_held', function () {
    $draft = ($this->markReviewed)(($this->meetDraft)('waiver'));
    $id = ($this->submitMeet)($draft)->assertOk()->json('data.id');
    $registration = SidangTaRegistration::findOrFail($id);
    $snapshot = $registration->academic_snapshot;
    ($this->asReviewer)();
    $crawler = new Crawler($this->get(route('admin.sidang-ta-registrations.show', $registration))->assertOk()->getContent());
    expect($crawler->filter('[data-meet-review-held]')->count())->toBe(1);
    expect($crawler->filter('[data-doc-review-form]')->count())->toBe(0);
    expect($crawler->filter('form[action="'.route('admin.sidang-ta-registrations.review', $registration).'"]')->count())->toBe(0);
    expect($crawler->filter('a[data-document-preview]')->count())->toBe(8);
    $waiver = $registration->documents()->where('document_type', Meet::WAIVER)->firstOrFail();
    $this->get(route('sidang-ta-registration-documents.preview', $waiver))->assertOk();
    $row = (new Crawler($this->get(route('admin.sidang-ta-registrations.index'))->assertOk()->getContent()))
        ->filter('[data-ta-row="'.$id.'"]');
    expect($row->filter('[data-ta-doc]')->count())->toBe(8);
    expect($row->filter('[data-ta-doc="eprt_waiver_letter"]')->attr('title'))->toContain('Surat Permakluman EPRT');
    expect($row->filter('[data-ta-doc="level_2_result_capture"], [data-ta-doc="transcript_khs"]')->count())->toBe(0);
    $export = app(SidangTaReviewExport::class)->row($registration->fresh()->load(['documents', 'period']));
    expect($export)->toHaveCount(92);
    expect($export['Surat Permakluman EPRT — Status'])->toBe('Menunggu verifikasi');
    expect($export['KRS Sidang TA 2'])->toBe('Terdaftar');
    expect($export[SidangTaRegistration::DOCUMENT_TYPES['transcript_khs'].' — Status'])->toBe('N/A');
    $this->postJson(route('admin.sidang-ta-registrations.documents.review', [
        'sidang_ta_registration' => $registration, 'document' => $waiver,
    ]), ['registration_version' => $registration->lock_version, 'status' => 'verified'])->assertUnprocessable();
    $this->postJson(route('admin.sidang-ta-registrations.review', $registration), [
        'registration_version' => $registration->lock_version, 'action' => 'needs_review', 'review_notes' => 'Perbaiki',
    ])->assertUnprocessable();
    expect($waiver->fresh()->status)->toBe('pending');
    expect($registration->fresh()->status)->toBe('submitted');
    expect($registration->fresh()->academic_snapshot)->toBe($snapshot);
    expect($registration->logs()->count())->toBe(1);
});

test('meet_final_mixed_version_xlsx_has_the_same_headers_and_preserves_waiver_status', function () {
    $draft = ($this->markReviewed)(($this->meetDraft)('waiver'));
    $id = ($this->submitMeet)($draft)->assertOk()->json('data.id');
    $registration = SidangTaRegistration::findOrFail($id);
    $legacy = $registration->replicate();
    $legacy->fill(['requirements_version' => 1, 'student_number' => '1202299066',
        'student_name' => 'Legacy Export', 'academic_snapshot' => null])->save();
    ($this->asReviewer)();
    $response = $this->get(route('admin.sidang-ta-registrations.export'))->assertOk();
    $path = $response->baseResponse->getFile()->getPathname();
    try {
        $rows = (new \Rap2hpoutre\FastExcel\FastExcel)->import($path);
        expect($rows)->toHaveCount(2);
        $modern = $rows->firstWhere('ID Pengajuan', (string) $id);
        $old = $rows->firstWhere('ID Pengajuan', (string) $legacy->id);
        expect($modern)->toHaveCount(92);
        expect(array_keys($modern))->toBe(array_keys($old));
        expect($modern['Surat Permakluman EPRT — Status'])->toBe('Menunggu verifikasi');
        expect($old['Surat Permakluman EPRT — Status'])->toBe('N/A');
    } finally {
        if (is_file($path)) unlink($path);
    }
});

test('meet_final_version_two_revision_stays_held_without_changing_final_or_borrowed_files', function () {
    $draft = ($this->markReviewed)(($this->meetDraft)('waiver'));
    $id = ($this->submitMeet)($draft)->assertOk()->json('data.id');
    $registration = SidangTaRegistration::findOrFail($id);
    $registration->update(['status' => 'needs_review']); // Controlled fixture until the new staff workflow is wired.
    $snapshot = $registration->academic_snapshot;
    $paths = Storage::disk('local')->allFiles();
    $revisionId = $this->postJson(route('sidang-ta-drafts.revision', $registration))->assertCreated()->json('data.id');
    $revision = SidangTaRegistrationDraft::findOrFail($revisionId);
    $this->putJson(route('sidang-ta-registrations.update', $registration), [
        'draft_id' => $revisionId, 'version' => $revision->version, 'action' => 'submit',
    ])->assertUnprocessable()->assertJsonPath('message', 'Pengiriman revisi ini belum tersedia. Data dan berkas tetap tersimpan.');
    expect($revision->fresh()->status)->toBe('active');
    expect($registration->fresh()->status)->toBe('needs_review');
    expect($registration->fresh()->academic_snapshot)->toBe($snapshot);
    expect($registration->documents()->count())->toBe(8);
    expect($registration->logs()->count())->toBe(1);
    expect(Storage::disk('local')->allFiles())->toBe($paths);
});

test('meet_final_revalidates_required_scalar_values_even_if_review_metadata_exists', function (string $field) {
    $draft = ($this->markReviewed)(($this->meetDraft)());
    $payload = $draft->payload;
    unset($payload[$field]);
    // Simulate inconsistent persisted data, not an allowed HTTP update.
    $draft->update(['payload' => $payload]);
    $paths = Storage::disk('local')->allFiles();
    ($this->submitMeet)($draft)->assertUnprocessable()->assertJsonValidationErrors('draft');
    ($this->assertPreserved)($draft, $paths);
})->with(['student_phone', 'graduation_payment_date', 'diploma_biodata_accurate']);
