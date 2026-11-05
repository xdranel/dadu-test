# 61a — Test alur persyaratan tunggal Sidang TA

Berikut isi lengkap20 file test yang ditambahkan/diperbarui. Ganti file secara utuh, jangan menambal hanya beberapa assertion. Test Activation berisi16 cases expanded; Reset berisi10 cases; lainnya menjaga fitur current dan regresi. Gunakan MySQL Podman.

### `tests/Feature/SidangTaActivationTest.php`

```php
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
```

### `tests/Feature/SidangTaDetailUiTest.php`

```php
<?php

use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\DomCrawler\Crawler;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create([
        'username' => '1202200001', 'email' => 'detail-ui@mahasiswa.test',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false,
    ]);
    $this->student = Student::create([
        'student_number' => '1202200001', 'name' => 'Nama Saat Diajukan',
        'email' => $this->user->email, 'academic_year' => 2022,
        'study_program' => 'Informatika', 'status' => 'Aktif', 'gpa' => 3.25,
    ]);
    foreach (['read', 'update', 'review'] as $action) {
        Permission::firstOrCreate(['name' => "sidang_ta_registration_{$action}", 'guard_name' => 'web']);
    }
    $this->user->givePermissionTo(['sidang_ta_registration_read', 'sidang_ta_registration_update']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->period = SidangTaPeriod::create([
        'name' => 'Periode Detail UI', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay(),
    ]);
    $this->snapshot = [
        'admission' => ['eprt' => ['mode' => 'automatic']],
        'student_name' => 'Nama Saat Diajukan', 'student_number' => '1202200001',
        'study_program' => 'Informatika Historis', 'student_email' => 'historis@mahasiswa.test',
        'semester' => 8, 'gpa' => 3.25, 'passed_credits' => 140,
        'tak_score' => 75, 'eprt_score' => 500, 'ta_title' => 'Judul TA Historis',
        'ta_title_en' => 'Historical Thesis Title', 'advisor_1' => 'Dosen Historis',
        'academic_source_updated_at' => '01/11/2026 10:00', 'bpp_status' => 'paid',
    ];
    $this->makeRegistration = function (array $overrides = []) {
        return SidangTaRegistration::create(array_replace([
            'sidang_ta_period_id' => $this->period->id, 'student_number' => '1202200001',
            'student_name' => 'Nama Saat Diajukan', 'study_program' => 'Informatika Historis',
            'student_phone' => '081234567890', 'parent_phone' => '081234567891',
            'graduation_payment_date' => today()->toDateString(), 'diploma_biodata_accurate' => true,
            'requirements_version' => 2, 'status' => 'needs_review', 'submitted_at' => now(), 'academic_snapshot' => $this->snapshot,
        ], $overrides))->fresh();
    };
    $this->actingAs($this->user);
});

test('detail_uses_saved_academic_snapshot_after_student_data_changes', function () {
    $registration = ($this->makeRegistration)();
    $this->student->update(['name' => 'Nama Terbaru Berbeda', 'gpa' => 3.99]);
    $this->student->finalProject()->create(['thesis_title_id' => 'Judul TA Terbaru Berbeda']);
    $crawler = new Crawler($this->get(route('sidang-ta-registrations.show', $registration))->assertOk()->getContent());
    $academic = $crawler->filter('[data-academic-detail]');
    expect($academic->count())->toBe(1);
    expect($academic->text(null, true))->toContain('Nama Saat Diajukan')->toContain('Judul TA Historis')
        ->toContain('Historical Thesis Title')->not->toContain('Dosen Historis')
        ->not->toContain('Nama Terbaru Berbeda')->not->toContain('Judul TA Terbaru Berbeda');
    expect($academic->filter('[data-snapshot-field="gpa"]')->text(null, true))->toBe('3,25');
    expect($academic->filter('[data-academic-group]')->count())->toBe(4);
    $stored = $registration->fresh()->academic_snapshot;
    $expected = $this->snapshot;
    ksort($stored);
    ksort($expected);
    expect($stored)->toBe($expected);
});

test('detail_preserves_zero_null_and_extra_snapshot_values_with_escaped_output', function () {
    $registration = ($this->makeRegistration)(['academic_snapshot' => [
        'admission' => ['eprt' => ['mode' => 'automatic']],
        'gpa' => 0, 'passed_credits' => 0, 'tak_score' => null,
        'ta_title' => '<script>alert(1)</script>',
        'custom_flag' => false, 'custom_meta' => ['catatan' => '<b>Tambahan</b>'],
    ]]);
    $crawler = new Crawler($this->get(route('sidang-ta-registrations.show', $registration))->assertOk()->getContent());
    $academic = $crawler->filter('[data-academic-detail]');
    expect($academic->filter('[data-snapshot-field="gpa"]')->text(null, true))->toBe('0,00');
    expect($academic->filter('[data-snapshot-field="passed_credits"]')->text(null, true))->toBe('0');
    expect($academic->filter('[data-snapshot-field="tak_score"]')->text(null, true))->toBe('Belum tersedia');
    expect($academic->filter('[data-academic-group="extra"]')->text(null, true))
        ->toContain('Custom flag')->toContain('Tidak')->toContain('<b>Tambahan</b>');
    expect($academic->filter('[data-snapshot-field="ta_title"]')->text(null, true))->toBe('<script>alert(1)</script>');
    expect($academic->filter('script, b')->count())->toBe(0);
});

test('current_registration_with_null_snapshot_fails_closed_without_live_student_values', function () {
    // Assert the normal error response independently of the local .env debug setting.
    config(['app.debug' => false]);
    $registration = ($this->makeRegistration)(['academic_snapshot' => null]);
    $response = $this->get(route('sidang-ta-registrations.show', $registration))->assertStatus(409)
        ->assertDontSee('Nama Saat Diajukan')->assertDontSee('3,25');
    expect((new Crawler($response->getContent()))->filter('[data-academic-detail]')->count())->toBe(0);
    expect($registration->fresh()->requirements_version)->toBe(2);
    expect($registration->fresh()->academic_snapshot)->toBeNull();
});

test('owner_can_see_revise_button_through_email_and_username_fallback', function () {
    $registration = ($this->makeRegistration)();
    $url = route('sidang-ta-registrations.show', $registration);
    $crawler = new Crawler($this->get($url)->assertOk()->getContent());
    expect($crawler->filter('[data-revise-registration]')->attr('href'))
        ->toBe(route('sidang-ta-registrations.edit', $registration));
    $this->user->update(['email' => 'alias-detail@mahasiswa.test']);
    $this->actingAs($this->user->fresh());
    $crawler = new Crawler($this->get($url)->assertOk()->getContent());
    expect($crawler->filter('[data-revise-registration]')->count())->toBe(1);
    $this->get(route('sidang-ta-registrations.edit', $registration))->assertOk();
});

test('owner_without_update_permission_sees_no_revise_button_and_edit_is_forbidden', function () {
    $registration = ($this->makeRegistration)();
    $this->user->revokePermissionTo('sidang_ta_registration_update');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($this->user->fresh());
    $response = $this->get(route('sidang-ta-registrations.show', $registration))->assertOk()
        ->assertDontSee(route('sidang-ta-registrations.edit', $registration), false);
    $crawler = new Crawler($response->getContent());
    expect($crawler->filter('[data-revise-registration]')->count())->toBe(0);
    $this->get(route('sidang-ta-registrations.edit', $registration))->assertForbidden();
});

test('reviewer_without_student_can_read_detail_but_has_no_student_revise_button', function () {
    $registration = ($this->makeRegistration)();
    $reviewer = User::factory()->create([
        'username' => 'reviewer-detail-28', 'email' => 'reviewer-detail@example.test', 'employee_id' => null,
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false,
    ]);
    $reviewer->givePermissionTo(['sidang_ta_registration_read', 'sidang_ta_registration_update', 'sidang_ta_registration_review']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $response = $this->actingAs($reviewer)
        ->get(route('sidang-ta-registrations.show', $registration))->assertOk()
        ->assertDontSee(route('sidang-ta-registrations.edit', $registration), false);
    $crawler = new Crawler($response->getContent());
    expect($crawler->filter('[data-academic-detail]')->count())->toBe(1);
    expect($crawler->filter('[data-revise-registration]')->count())->toBe(0);
});

test('another_student_still_cannot_read_registration_detail', function () {
    $registration = ($this->makeRegistration)();
    $other = User::factory()->create([
        'username' => '1202200002', 'email' => 'other-detail@mahasiswa.test',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false,
    ]);
    Student::create([
        'student_number' => '1202200002', 'name' => 'Mahasiswa Lain', 'email' => $other->email,
        'academic_year' => 2022, 'study_program' => 'Informatika', 'status' => 'Aktif',
    ]);
    $other->givePermissionTo(['sidang_ta_registration_read', 'sidang_ta_registration_update']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($other)->get(route('sidang-ta-registrations.show', $registration))->assertForbidden();
});

test('detail_hides_history_keeps_private_preview_and_preserves_audit', function () {
    $registration = ($this->makeRegistration)(['status' => 'submitted']);
    $path = 'sidang_ta/detail-ui/berkas.pdf';
    Storage::disk('local')->put($path, '%PDF-detail');
    $document = $registration->documents()->create([
        'document_type' => 'thesis_draft', 'file_name' => 'berkas.pdf',
        'file_path' => $path, 'file_size' => 11, 'file_type' => 'pdf',
    ]);
    $registration->logAction('submitted', null, 'submitted', 'Pendaftaran pertama.', $this->user->id);
    $registration->logAction('needs_review', 'submitted', 'needs_review', 'Periksa dokumen.', $this->user->id);
    $registration->logAction('resubmitted', 'needs_review', 'submitted', '<b>Catatan literal</b>', $this->user->id);
    $crawler = new Crawler($this->get(route('sidang-ta-registrations.show', $registration))->assertOk()->getContent());
    expect($crawler->filter('[data-registration-history]')->count())->toBe(0);
    expect($crawler->text(null, true))->not->toContain('Pendaftaran pertama.')
        ->not->toContain('Periksa dokumen.')->not->toContain('Catatan literal');
    expect($crawler->filter('[data-revise-registration]')->count())->toBe(0);
    $preview = $crawler->filter('a[data-document-preview]');
    expect($preview->attr('href'))->toBe(route('sidang-ta-registration-documents.preview', $document));
    expect($preview->attr('data-download-url'))->toBe(route('sidang-ta-registration-documents.download', $document));
    expect(preg_split('/\s+/', trim($preview->attr('class'))))->toContain('btn')->toContain('btn-secondary');
    $this->get($preview->attr('href'))->assertOk();
    $this->get($preview->attr('data-download-url'))->assertDownload('berkas.pdf');
    expect($registration->logs()->where('action', 'resubmitted')->exists())->toBeTrue();
});

test('meet_detail_uses_saved_krs_label_and_hides_retired_snapshot_fields_for_student_and_staff', function () {
    $snapshot = array_replace($this->snapshot, [
        'ta2_krs_status' => 'eligible', 'ta2_krs_label' => 'Terdaftar saat pengajuan',
        'level_3_status' => 'HIDDEN-LEVEL3', 'bpp_status' => 'HIDDEN-BPP',
        'ibasila_status' => 'HIDDEN-BASILA', 'skpi_tak' => 'HIDDEN-AVAILABILITY',
        'advisor_1' => 'HIDDEN-ADVISOR', 'examiner_1' => 'HIDDEN-EXAMINER',
        'exam_date' => 'HIDDEN-DATE', 'exam_time' => 'HIDDEN-TIME', 'exam_location' => 'HIDDEN-LOCATION',
    ]);
    $registration = ($this->makeRegistration)(['academic_snapshot' => $snapshot]);
    $this->student->update(['name' => 'Live student changed']);
    $response = $this->get(route('sidang-ta-registrations.show', $registration))->assertOk();
    $reviewer = \App\Models\User::factory()->create(['status' => 'active', 'auth_source' => 'manual',
        'must_change_password' => false]);
    $reviewer->givePermissionTo('sidang_ta_registration_review');
    $staff = $this->actingAs($reviewer)->get(route('admin.sidang-ta-registrations.show', $registration))->assertOk();
    foreach ([$response, $staff] as $page) {
        $academic = (new Crawler($page->getContent()))->filter('[data-academic-detail]');
        expect($academic->filter('[data-snapshot-field="ta2_krs_label"]')->text(null, true))->toBe('Terdaftar saat pengajuan');
        expect($academic->text(null, true))->not->toContain('HIDDEN-')->not->toContain('Live student changed');
        expect($academic->filter('[data-academic-group="lecturers"], [data-academic-group="schedule"]')->count())->toBe(0);
        expect($academic->filter('[data-snapshot-field="ta2_krs_status"]')->count())->toBe(0);
        expect($academic->text(null, true))->not->toContain('Status BPP')->not->toContain('Sidang tingkat 3');
    }
    expect($registration->fresh()->academic_snapshot)->toEqual($snapshot);
});

test('current_snapshot_without_krs_label_does_not_derive_one_from_live_data_or_raw_status', function () {
    $snapshot = array_replace($this->snapshot, ['ta2_krs_status' => 'eligible']);
    unset($snapshot['ta2_krs_label']);
    $registration = ($this->makeRegistration)(['academic_snapshot' => $snapshot]);
    $academic = (new Crawler($this->get(route('sidang-ta-registrations.show', $registration))
        ->assertOk()->getContent()))->filter('[data-academic-detail]');
    expect($academic->filter('[data-snapshot-field="ta2_krs_label"]')->text(null, true))->toBe('Belum tersedia');
    expect($academic->text(null, true))->not->toContain('eligible');
    expect($registration->fresh()->academic_snapshot)->not->toHaveKey('ta2_krs_label');
});
```

### `tests/Feature/SidangTaDocumentPreviewTest.php`

```php
<?php

use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDraft;
use App\Models\Student;
use App\Models\User;
use App\Support\SidangTaDocumentRequirements as Requirements;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\DomCrawler\Crawler;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create([
        'username' => '1202200001', 'email' => 'preview-ta@mahasiswa.test',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false,
    ]);
    $this->student = Student::create([
        'student_number' => '1202200001', 'name' => 'Preview TA Test',
        'email' => $this->user->email, 'academic_year' => 2022,
        'study_program' => 'Informatika', 'status' => 'Aktif', 'gpa' => 0,
    ]);
    foreach (['read', 'create', 'update'] as $action) {
        $this->user->givePermissionTo(Permission::firstOrCreate([
            'name' => "sidang_ta_registration_{$action}", 'guard_name' => 'web',
        ]));
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->period = SidangTaPeriod::create([
        'name' => 'Periode S2', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay(),
    ]);
    $this->data = ['sidang_ta_period_id' => $this->period->id,
        'student_phone' => '081234567890', 'parent_phone' => '081234567891'];
    $this->actingAs($this->user);
    $this->completeDraft = function () {
        $id = $this->postJson(route('sidang-ta-drafts.store'), $this->data)
            ->assertCreated()->json('data.id');
        $draft = SidangTaRegistrationDraft::findOrFail($id);
        expect($draft->requirements_version)->toBe(2);
        foreach ([2, 3, 4] as $step) {
            $data = ['version' => $draft->fresh()->version];
            if ($step === 3) $data['graduation_payment_date'] = today()->toDateString();
            if ($step === 4) $data['diploma_biodata_accurate'] = true;
            foreach (Requirements::typesForStep($step, true) as $type) {
                $data['documents'][$type] = \Illuminate\Http\UploadedFile::fake()
                    ->create($type.'.pdf', 100, 'application/pdf');
            }
            $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => $step]), $data)
                ->assertOk();
        }
        $draft = $draft->fresh();
        expect($draft->documents()->count())->toBe(7);
        return $draft;
    };

    Permission::firstOrCreate(['name' => 'sidang_ta_registration_review', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'sidang_ta_period_read', 'guard_name' => 'web']);
    $this->makeFinal = function (string $status = 'submitted') {
        $registration = SidangTaRegistration::create([
            'sidang_ta_period_id' => $this->period->id, 'student_number' => $this->student->student_number,
            'student_name' => $this->student->name, 'study_program' => $this->student->study_program,
            'student_phone' => '081234567890', 'parent_phone' => '081234567891',
            'graduation_payment_date' => today()->toDateString(), 'diploma_biodata_accurate' => true,
            'requirements_version' => 2,
            'academic_snapshot' => ['admission' => ['eprt' => ['mode' => 'automatic']]],
            'status' => $status, 'submitted_at' => now(),
        ])->fresh();
        expect($registration->requirements_version)->toBe(2);
        foreach (array_merge(Requirements::CAPTURES, Requirements::PAYMENT, Requirements::DIPLOMA) as $index => $type) {
            $path = "sidang_ta/final/{$registration->id}/{$type}.pdf";
            Storage::disk('local')->put($path, '%PDF-1.4 preview');
            $saved = $registration->documents()->create([
                'document_type' => $type, 'file_name' => $index === 0 ? 'capture.pdf' : $type.'.pdf',
                'file_path' => $path, 'file_type' => 'pdf', 'file_size' => 16,
                'status' => $status === 'needs_review' && $index === 0 ? 'rejected' : 'verified',
                'notes' => $status === 'needs_review' && $index === 0 ? 'Ganti capture.' : null,
            ]);
            if ($index === 0) $document = $saved;
        }
        return [$registration, $document];
    };
});



test('preview_owner_gets_inline_pdf_jpeg_png_for_draft_and_final', function () {
    $draft = ($this->completeDraft)();
    $draftDocument = $draft->documents()->firstOrFail();
    [, $finalDocument] = ($this->makeFinal)();
    $samples = [
        ['pdf', 'application/pdf', '%PDF-1.4 preview'],
        ['jpg', 'image/jpeg', hex2bin('ffd8ffe000104a46494600010100000100010000ffd9')],
        ['png', 'image/png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=')],
    ];
    foreach ($samples as [$extension, $mime, $bytes]) {
        foreach ([['sidang-ta-draft-documents.preview', $draftDocument],
                     ['sidang-ta-registration-documents.preview', $finalDocument]] as [$route, $document]) {
            Storage::disk('local')->put($document->file_path, $bytes);
            $document->update(['file_name' => 'berkas.'.$extension, 'file_type' => $extension]);
            $response = $this->get(route($route, $document))->assertOk()
                ->assertHeader('Content-Type', $mime)->assertHeader('X-Content-Type-Options', 'nosniff');
            expect($response->headers->get('Content-Disposition'))->toStartWith('inline;');
            expect($response->headers->get('Cache-Control'))->toContain('private')->toContain('no-store');
            expect($response->streamedContent())->toBe($bytes);
        }
    }
});

test('preview_other_student_cannot_open_draft_or_final_by_id', function () {
    $draft = ($this->completeDraft)();
    [, $finalDocument] = ($this->makeFinal)();
    $other = User::factory()->create(['username' => '1202200002', 'email' => 'other-preview@mahasiswa.test',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
    Student::create(['student_number' => '1202200002', 'name' => 'Other', 'email' => $other->email,
        'academic_year' => 2022, 'study_program' => 'Informatika', 'status' => 'Aktif']);
    $other->givePermissionTo('sidang_ta_registration_read');
    $this->actingAs($other)->get(route('sidang-ta-draft-documents.preview', $draft->documents()->first()))->assertForbidden();
    $this->get(route('sidang-ta-registration-documents.preview', $finalDocument))->assertForbidden();
});

test('preview_consumed_and_stale_revision_drafts_are_rejected', function () {
    $draft = ($this->completeDraft)();
    $document = $draft->documents()->firstOrFail();
    $draft->update(['status' => 'consumed']);
    $this->get(route('sidang-ta-draft-documents.preview', $document))->assertStatus(409);
    [$source, $finalDocument] = ($this->makeFinal)('needs_review');
    $id = $this->postJson(route('sidang-ta-drafts.revision', $source))->assertCreated()->json('data.id');
    $revision = SidangTaRegistrationDraft::findOrFail($id);
    $borrowed = $revision->documents()->firstOrFail();
    $source->lock_version++;
    $source->save();
    $this->get(route('sidang-ta-draft-documents.preview', $borrowed))->assertStatus(409);
    $this->get(route('sidang-ta-registration-documents.preview', $finalDocument))->assertOk();
});

test('preview_missing_files_return_404_and_html_content_is_not_rendered_inline', function () {
    $draft = ($this->completeDraft)();
    [, $finalDocument] = ($this->makeFinal)();
    foreach ([['sidang-ta-draft-documents.preview', $draft->documents()->firstOrFail()],
                 ['sidang-ta-registration-documents.preview', $finalDocument]] as [$route, $document]) {
        Storage::disk('local')->delete($document->file_path);
        $this->get(route($route, $document))->assertNotFound();
        Storage::disk('local')->put($document->file_path, '<html><script>alert(1)</script></html>');
        $this->get(route($route, $document))->assertStatus(415);
    }
});

test('preview_reviewer_only_can_open_final_and_download_but_not_private_drafts', function () {
    $draft = ($this->completeDraft)();
    [, $document] = ($this->makeFinal)();
    $reviewer = User::factory()->create(['username' => 'reviewer-preview', 'email' => 'reviewer-preview@test.local',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
    $reviewer->givePermissionTo('sidang_ta_registration_review');
    $this->actingAs($reviewer)->get(route('sidang-ta-registration-documents.preview', $document))->assertOk();
    $this->get(route('sidang-ta-registration-documents.download', $document))->assertDownload('capture.pdf');
    $this->get(route('sidang-ta-draft-documents.preview', $draft->documents()->first()))->assertForbidden();
    $reviewer->revokePermissionTo('sidang_ta_registration_review');
    $reviewer->givePermissionTo('sidang_ta_period_read');
    $this->get(route('sidang-ta-registration-documents.preview', $document))->assertForbidden();
    $this->get(route('sidang-ta-registration-documents.download', $document))->assertForbidden();
});

test('preview_review_page_has_no_number_badges_and_escaped_modal_triggers', function () {
    $draft = ($this->completeDraft)();
    $document = $draft->documents()->firstOrFail();
    $name = 'nama " <img src=x onerror=alert(1)>.pdf';
    $document->update(['file_name' => $name]);
    $this->postJson(route('sidang-ta-drafts.review', $draft), ['version' => $draft->version])->assertOk();
    $crawler = new Crawler($this->get(route('sidang-ta-drafts.preview', $draft))->assertOk()->getContent());
    expect($crawler->filter('[data-review-group]')->count())->toBe(3);
    expect($crawler->filter('[data-review-group] header span[aria-hidden="true"]')->count())->toBe(0);
    expect($crawler->filter('a[data-document-preview]')->count())->toBe(7);
    expect($crawler->filter('[data-document-preview-modal]')->count())->toBe(1);
    $link = $crawler->filter('a[data-document-preview]')->reduce(fn (Crawler $n) =>
        $n->attr('data-preview-url') === route('sidang-ta-draft-documents.preview', $document));
    expect($link->attr('data-file-name'))->toBe($name);
    expect($link->attr('href'))->toBe(route('sidang-ta-draft-documents.preview', $document));
    expect($link->attr('data-download-url'))->toBe(route('sidang-ta-draft-documents.download', $document));
    expect($link->attr('x-on:click.prevent'))->toBe("\$dispatch('sidang-ta-preview', \$el.dataset)");
    expect($crawler->filter('[data-review-document] img')->count())->toBe(0);
    expect($crawler->filter('[data-preview-download]')->count())->toBe(1);
    expect($crawler->filter('[data-preview-new-tab]')->attr('target'))->toBe('_blank');
    expect($crawler->filter('[data-preview-close]')->attr('type'))->toBe('button');
});

test('preview_final_detail_uses_one_modal_and_keeps_direct_download_as_secondary_data', function () {
    [$registration, $document] = ($this->makeFinal)();
    $crawler = new Crawler($this->get(route('sidang-ta-registrations.show', $registration))->assertOk()->getContent());
    expect($crawler->filter('a[data-document-preview]')->count())->toBe(7);
    $link = $crawler->filter('a[data-document-preview]')->reduce(fn (Crawler $node) =>
        $node->attr('href') === route('sidang-ta-registration-documents.preview', $document));
    expect($link->count())->toBe(1);
    expect($link->attr('href'))->toBe(route('sidang-ta-registration-documents.preview', $document));
    expect($link->attr('data-download-url'))->toBe(route('sidang-ta-registration-documents.download', $document));
    expect($crawler->filter('[data-document-preview-modal]')->count())->toBe(1);
    expect($crawler->filter('a[data-final-download]')->count())->toBe(0);
    expect($crawler->filter('[data-document-preview-modal] iframe')->getNode(0)->hasAttribute('src'))->toBeFalse();
});

test('preview_read_only_owner_can_view_without_mutating_saved_state', function () {
    $draft = ($this->completeDraft)();
    [$registration, $finalDocument] = ($this->makeFinal)();
    $this->user->revokePermissionTo('sidang_ta_registration_create');
    $this->user->revokePermissionTo('sidang_ta_registration_update');
    $version = $draft->version;
    $this->get(route('sidang-ta-draft-documents.preview', $draft->documents()->first()))->assertOk();
    $this->get(route('sidang-ta-registration-documents.preview', $finalDocument))->assertOk();
    expect($draft->fresh()->version)->toBe($version);
    expect($draft->fresh()->reviewed_version)->toBeNull();
    expect($registration->fresh()->status)->toBe('submitted');
    expect($registration->logs()->count())->toBe(0);
});

test('preview_review_modal_is_inside_layout_after_document_doctype', function () {
    $draft = ($this->completeDraft)();
    $this->postJson(route('sidang-ta-drafts.review', $draft), ['version' => $draft->version])
        ->assertOk();
    $html = $this->get(route('sidang-ta-drafts.preview', $draft))->assertOk()->getContent();
    expect(strtolower(ltrim($html)))->toStartWith('<!doctype html>');
    $crawler = new Crawler($html);
    expect($crawler->filter('body [data-document-preview-modal]')->count())->toBe(1);
});

test('preview_final_modal_is_inside_layout_after_document_doctype', function () {
    [$registration] = ($this->makeFinal)();
    $html = $this->get(route('sidang-ta-registrations.show', $registration))->assertOk()->getContent();
    expect(strtolower(ltrim($html)))->toStartWith('<!doctype html>');
    $crawler = new Crawler($html);
    expect($crawler->filter('body [data-document-preview-modal]')->count())->toBe(1);
});
```

### `tests/Feature/SidangTaDraftCardTest.php`

```php
<?php

use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDraft;
use App\Models\Student;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    \Illuminate\Support\Facades\Storage::fake('local');
    $this->user = User::factory()->create([
        'username' => '1202200001', 'email' => 'draft-card@mahasiswa.test',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false,
    ]);
    Student::create([
        'student_number' => '1202200001', 'name' => 'Draft Card Test',
        'email' => $this->user->email, 'academic_year' => 2022,
        'study_program' => 'Informatika', 'status' => 'Aktif',
    ]);
    foreach (['read', 'create', 'update'] as $action) {
        $this->user->givePermissionTo(Permission::firstOrCreate([
            'name' => "sidang_ta_registration_{$action}", 'guard_name' => 'web',
        ]));
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->period = SidangTaPeriod::create([
        'name' => 'Periode Draft Card', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay(),
    ]);
    $this->actingAs($this->user);
    $this->newDraft = function () {
        $id = $this->postJson(route('sidang-ta-drafts.store'), [
            'sidang_ta_period_id' => $this->period->id,
            'student_phone' => '081234567890', 'parent_phone' => '081234567891',
        ])->assertCreated()->json('data.id');
        return SidangTaRegistrationDraft::findOrFail($id);
    };
});

function sidangTaDraftCardXpath(string $html): DOMXPath
{
    $previous = libxml_use_internal_errors(true);
    try {
        $document = new DOMDocument();
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET);
        return new DOMXPath($document);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
}

test('draft_delete_requires_dialog_confirmation_and_keeps_cancel_non_submitting', function () {
    $draft = ($this->newDraft)();
    $response = $this->get(route('sidang-ta-registrations.index'))->assertOk();
    $xpath = sidangTaDraftCardXpath($response->getContent());
    $actions = $xpath->query('//*[@data-draft-actions]')->item(0);
    expect($actions)->not->toBeNull();
    expect($xpath->query('.//a', $actions)->length)->toBe(1);
    expect($xpath->query('.//button[@data-open-discard and @type="button"]', $actions)->length)->toBe(1);
    expect($xpath->query('.//form', $actions)->length)->toBe(0);

    $dialog = $xpath->query('//dialog[@id="discard-draft-'.$draft->id.'"]')->item(0);
    expect($dialog)->not->toBeNull();
    expect($dialog->hasAttribute('open'))->toBeFalse();
    expect($dialog->textContent)->toContain('tidak dapat dipulihkan');
    expect($xpath->query('.//button[@data-cancel-discard and @type="button" and @autofocus]', $dialog)->length)->toBe(1);
    expect($xpath->query('.//button[@data-confirm-discard and @type="submit"]', $dialog)->length)->toBe(1);
    $form = $xpath->query('.//form', $dialog)->item(0);
    expect($form->getAttribute('method'))->toBe('POST');
    expect($form->getAttribute('action'))->toBe(route('sidang-ta-drafts.destroy', $draft));
    expect($xpath->query('.//input[@name="_method" and @value="DELETE"]', $form)->length)->toBe(1);
    expect($xpath->query('.//input[@name="_token"]', $form)->length)->toBe(1);
    expect($xpath->query('.//input[@name="version"]', $form)->item(0)->getAttribute('value'))->toBe((string) $draft->version);
    expect($draft->fresh())->not->toBeNull();
});

test('read_only_owner_can_see_continue_but_not_delete_controls_or_dialog', function () {
    $draft = ($this->newDraft)();
    $this->user->revokePermissionTo('sidang_ta_registration_create');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($this->user->fresh());
    $response = $this->get(route('sidang-ta-registrations.index'))->assertOk()
        ->assertSee(route('sidang-ta-drafts.edit', $draft), false);
    $xpath = sidangTaDraftCardXpath($response->getContent());
    expect($xpath->query('//*[@data-open-discard]')->length)->toBe(0);
    expect($xpath->query('//dialog')->length)->toBe(0);
});

test('stale_revision_has_delete_confirmation_without_continue_and_preserves_final_registration', function () {
    $source = SidangTaRegistration::create([
        'sidang_ta_period_id' => $this->period->id, 'student_number' => '1202200001',
        'student_name' => 'Draft Card Test', 'student_phone' => '081234567890',
        'parent_phone' => '081234567891', 'graduation_payment_date' => today()->toDateString(),
        'diploma_biodata_accurate' => true, 'status' => 'needs_review',
        'academic_snapshot' => ['admission' => ['eprt' => ['mode' => 'automatic']]],
    ])->fresh();
    foreach (\App\Support\SidangTaMeetRequirements::allTypes(true, 'automatic') as $i => $type) {
        $path = 'sidang_ta/card/'.$source->id.'/'.$type.'.pdf';
        \Illuminate\Support\Facades\Storage::disk('local')->put($path, '%PDF-1.4 card fixture');
        $source->documents()->create(['document_type' => $type, 'file_name' => $type.'.pdf',
            'file_path' => $path, 'file_type' => 'pdf', 'status' => $i === 0 ? 'rejected' : 'verified',
            'notes' => $i === 0 ? 'Perbaiki capture.' : null]);
    }
    $id = $this->postJson(route('sidang-ta-drafts.revision', $source))
        ->assertCreated()->json('data.id');
    $draft = SidangTaRegistrationDraft::findOrFail($id);
    $source->lock_version++;
    $source->save();

    $response = $this->get(route('sidang-ta-registrations.index'))->assertOk()
        ->assertDontSee(route('sidang-ta-drafts.edit', $draft), false);
    $crawler = new \Symfony\Component\DomCrawler\Crawler($response->getContent());
    $card = $crawler->filter('[data-testid="draft-card"]');
    expect($card->count())->toBe(1);
    expect($card->text(null, true))->toContain('Pengajuan asal berubah.');

    $xpath = sidangTaDraftCardXpath($response->getContent());
    expect($xpath->query('//*[@data-open-discard]')->length)->toBe(1);
    expect($xpath->query('//dialog')->length)->toBe(1);
    $this->delete(route('sidang-ta-drafts.destroy', $draft), ['version' => $draft->version])
        ->assertRedirect(route('sidang-ta-registrations.index'));
    expect(SidangTaRegistrationDraft::find($id))->toBeNull();
    expect($source->fresh()->status)->toBe('needs_review');
});
```

### `tests/Feature/SidangTaDraftSchemaTest.php`

```php
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
```

### `tests/Feature/SidangTaDraftUploadTest.php`

```php
<?php

use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistrationDraft;
use App\Models\Student;
use App\Models\User;
use App\Services\SidangTaDraftService;
use App\Support\SidangTaDocumentRequirements as Requirements;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create([
        'username' => '1202200001', 'email' => 'upload@mahasiswa.test',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false,
    ]);
    Student::create([
        'student_number' => '1202200001', 'name' => 'Upload Test',
        'email' => $this->user->email, 'academic_year' => 2022,
        'study_program' => 'Informatika', 'status' => 'Aktif',
    ]);
    foreach (['create', 'read'] as $action) {
        $this->user->givePermissionTo(Permission::firstOrCreate([
            'name' => "sidang_ta_registration_{$action}", 'guard_name' => 'web',
        ]));
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $period = SidangTaPeriod::create([
        'name' => 'Upload Test', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay(),
    ]);
    $id = $this->actingAs($this->user)->postJson(route('sidang-ta-drafts.store'), [
        'sidang_ta_period_id' => $period->id,
        'student_phone' => '081234567890', 'parent_phone' => '081234567891',
    ])->assertCreated()->json('data.id');
    $this->draft = SidangTaRegistrationDraft::findOrFail($id);
    expect($this->draft->requirements_version)->toBe(2);
    $this->filesFor = function (int $step, ?bool $accurate = null) {
        $files = [];
        foreach (Requirements::typesForStep($step, $accurate) as $type) {
            $files[$type] = UploadedFile::fake()->create($type.'.pdf', 100, 'application/pdf');
        }
        return $files;
    };
    $this->saveUploadStep = function (int $step, array $extra = []) {
        $data = ['version' => $this->draft->fresh()->version];
        if ($step === 3) {
            $data['graduation_payment_date'] = today()->toDateString();
        }
        return $this->postJson(route('sidang-ta-drafts.steps.store', [
            'draft' => $this->draft->id, 'step' => $step,
        ]), array_replace($data, $extra));
    };
});

test('saves_two_captures_and_resumes_with_private_downloads', function () {
    ($this->saveUploadStep)(2, ['documents' => ($this->filesFor)(2)])
        ->assertOk()->assertJsonCount(2, 'data.documents')
        ->assertJsonPath('data.first_incomplete_step', 3);
    $response = $this->getJson(route('sidang-ta-drafts.edit', $this->draft))->assertOk();
    expect($response->json('data.documents.0'))->not->toHaveKey('file_path');
    foreach ($this->draft->documents()->get() as $document) {
        Storage::disk('local')->assertExists($document->file_path);
    }
    $this->get(route('sidang-ta-draft-documents.download', $this->draft->documents()->first()))
        ->assertOk()->assertHeader('content-disposition');
});

test('completes_both_biodata_branches_and_removes_obsolete_files', function () {
    ($this->saveUploadStep)(2, ['documents' => ($this->filesFor)(2)])->assertOk();
    ($this->saveUploadStep)(3, ['documents' => ($this->filesFor)(3)])->assertOk();
    ($this->saveUploadStep)(4, [
        'diploma_biodata_accurate' => true, 'documents' => ($this->filesFor)(4, true),
    ])->assertOk()->assertJsonCount(7, 'data.documents')->assertJsonPath('data.first_incomplete_step', null);
    $oldPaths = $this->draft->documents()->whereIn('document_type', Requirements::DIPLOMA)->pluck('file_path')->all();
    ($this->saveUploadStep)(4, ['diploma_biodata_accurate' => false])->assertOk()
        ->assertJsonCount(5, 'data.documents')->assertJsonPath('data.first_incomplete_step', 4);
    expect($this->draft->fresh()->payload['diploma_biodata_accurate'])->toBeFalse();
    foreach ($oldPaths as $path) Storage::disk('local')->assertMissing($path);
    ($this->saveUploadStep)(4, [
        'diploma_biodata_accurate' => false, 'documents' => ($this->filesFor)(4, false),
    ])->assertOk()->assertJsonCount(6, 'data.documents')->assertJsonPath('data.first_incomplete_step', null);
});

test('missing_file_on_disk_is_not_complete', function () {
    ($this->saveUploadStep)(2, ['documents' => ($this->filesFor)(2)])->assertOk();
    $document = $this->draft->documents()->first();
    Storage::disk('local')->delete($document->file_path);
    $this->getJson(route('sidang-ta-drafts.edit', $this->draft))
        ->assertOk()->assertJsonPath('data.first_incomplete_step', 2);
    ($this->saveUploadStep)(3, ['documents' => ($this->filesFor)(3)])->assertStatus(409);
});

test('rejects_unknown_keys_and_oversized_files', function () {
    ($this->saveUploadStep)(2, ['documents' => [
        'unknown_type' => UploadedFile::fake()->create('unknown.pdf', 100, 'application/pdf'),
    ]])->assertUnprocessable()->assertJsonValidationErrors('documents');
    ($this->saveUploadStep)(2, ['documents' => [
        'ta_data_capture' => UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf'),
    ]])->assertUnprocessable()->assertJsonValidationErrors('documents.ta_data_capture');
    expect($this->draft->documents()->count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

test('accepts_size_boundary_and_requires_pdf_in_payment_stage', function () {
    $files = ($this->filesFor)(2);
    $files['ta_data_capture'] = UploadedFile::fake()->create('limit.pdf', 10240, 'application/pdf');
    ($this->saveUploadStep)(2, ['documents' => $files])->assertOk();
    ($this->saveUploadStep)(3, ['documents' => [
        'thesis_draft' => UploadedFile::fake()->create('image.jpg', 100, 'image/jpeg'),
    ]])->assertUnprocessable()->assertJsonValidationErrors('documents.thesis_draft');
});

test('keeps_existing_files_on_partial_save_and_rejects_stale_version', function () {
    ($this->saveUploadStep)(2, ['documents' => ($this->filesFor)(2)])->assertOk();
    $before = Storage::disk('local')->allFiles();
    $version = $this->draft->fresh()->version;
    ($this->saveUploadStep)(3, ['documents' => []])->assertOk()
        ->assertJsonPath('data.first_incomplete_step', 3);
    ($this->saveUploadStep)(2, ['version' => $version, 'documents' => ($this->filesFor)(2)])
        ->assertStatus(409);
    expect(Storage::disk('local')->allFiles())->toBe($before);
    expect($this->draft->fresh()->version)->toBe($version + 1);
});

test('storage_failure_preserves_old_metadata_and_files', function () {
    ($this->saveUploadStep)(2, ['documents' => ($this->filesFor)(2)])->assertOk();
    $old = $this->draft->documents()->where('document_type', 'ta_data_capture')->first();
    $beforeFiles = Storage::disk('local')->allFiles();
    $version = $this->draft->fresh()->version;
    $temporary = UploadedFile::fake()->create('failed.pdf', 100, 'application/pdf');
    $failed = new class('failed.pdf', $temporary->tempFile) extends \Illuminate\Http\Testing\File {
        public function storeAs($path, $name = null, $options = []) { return false; }
    };
    $failed->size(100)->mimeType('application/pdf');
    $caught = false;
    try {
        app(SidangTaDraftService::class)->saveStep($this->user, $this->draft, 2, $version, [
            'documents' => [
                'ta_data_capture' => UploadedFile::fake()->create('new.pdf', 100, 'application/pdf'),
                'online_supervision_capture' => $failed,
            ],
        ]);
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Penyimpanan file draft gagal. Silakan coba lagi.');
        $caught = true;
    }
    expect($caught)->toBeTrue();
    expect($old->fresh()->file_path)->toBe($old->file_path);
    expect($this->draft->fresh()->version)->toBe($version);
    Storage::disk('local')->assertExists($old->file_path);
    expect(Storage::disk('local')->allFiles())->toBe($beforeFiles);
    expect($this->draft->documents()->count())->toBe(2);
});

test('another_user_cannot_download_draft_document', function () {
    ($this->saveUploadStep)(2, ['documents' => ($this->filesFor)(2)])->assertOk();
    $other = User::factory()->create(['status' => 'active', 'must_change_password' => false]);
    $other->givePermissionTo('sidang_ta_registration_read');
    $this->actingAs($other)->get(route('sidang-ta-draft-documents.download',
        $this->draft->documents()->first()))->assertForbidden();
});
```

### `tests/Feature/SidangTaMeetDraftTest.php`

```php
<?php

use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDraft;
use App\Models\Student;
use App\Models\StudentEprt;
use App\Models\User;
use App\Services\SidangTaAdmissionFacts;
use App\Support\SidangTaDocumentRequirements as Requirements;
use App\Support\SidangTaMeetRequirements as Meet;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->travelTo(now()->startOfDay());
    Storage::fake('local');
    $this->user = User::factory()->create(['username' => '1202299054', 'email' => 'meet-draft@test.local',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
    $this->student = Student::create(['student_number' => $this->user->username, 'name' => 'Meet Draft',
        'email' => $this->user->email, 'academic_year' => 2022, 'study_program' => 'Informatika', 'status' => 'Aktif']);
    foreach (['read', 'create', 'update'] as $action) {
        $permission = Permission::firstOrCreate(['name' => 'sidang_ta_registration_'.$action, 'guard_name' => 'web']);
        $this->user->givePermissionTo($permission);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($this->user);
    $this->period = SidangTaPeriod::create(['name' => 'Meet Draft', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay()]);
    $this->setEprtMode = function (string $mode) {
        StudentEprt::where('student_number', $this->student->student_number)->delete();
        if ($mode === 'unknown') return;
        $count = $mode === 'waiver' ? 3 : ($mode === 'retest' ? 2 : 1);
        foreach (range(1, $count) as $attempt) {
            StudentEprt::create(['student_number' => $this->student->student_number,
                'total_score' => $mode === 'automatic' ? 450 : 449, 'attempt_number' => $attempt,
                'test_date' => today()->subDays(4 - $attempt), 'category' => null]);
        }
    };
    $this->newMeetDraft = function (string $mode = 'automatic') {
        ($this->setEprtMode)($mode);
        $id = $this->postJson(route('sidang-ta-drafts.store'), ['sidang_ta_period_id' => $this->period->id,
            'student_phone' => '081234567890', 'parent_phone' => '081234567891'])->assertCreated()->json('data.id');
        $draft = SidangTaRegistrationDraft::findOrFail($id);
        expect($draft->requirements_version)->toBe(2);
        return $draft->fresh();
    };
    $this->uploadStep = function ($draft, int $step, array $types, array $extra = []) {
        $files = [];
        foreach ($types as $type) $files[$type] = UploadedFile::fake()->create($type.'.pdf', 100, 'application/pdf');
        return $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => $step]),
            array_replace(['version' => $draft->fresh()->version, 'documents' => $files], $extra));
    };
    $this->finishMeetDraft = function (string $mode = 'automatic', bool $accurate = true) {
        $draft = ($this->newMeetDraft)($mode);
        ($this->uploadStep)($draft, 2, Meet::CAPTURES)->assertOk();
        ($this->uploadStep)($draft, 3, Meet::typesForStep(3, $accurate, $mode),
            ['graduation_payment_date' => today()->toDateString()])->assertOk();
        ($this->uploadStep)($draft, 4, Meet::typesForStep(4, $accurate, $mode),
            ['diploma_biodata_accurate' => $accurate])->assertOk();
        return $draft->fresh();
    };
});

afterEach(function () { $this->travelBack(); });

test('meet_v2_partial_stage_two_upload_is_saved_and_next_stage_is_locked', function () {
    $draft = ($this->newMeetDraft)();
    ($this->uploadStep)($draft, 2, ['ta_data_capture'])->assertOk();
    $progress = Requirements::progress($draft->fresh(), 2);
    expect($progress['required_documents'])->toBe(2);
    expect($progress['stored_documents'])->toBe(1);
    expect($progress['complete'])->toBeFalse();
    expect($draft->fresh()->version)->toBe(2);
    expect($draft->documents()->count())->toBe(1);
    ($this->uploadStep)($draft, 3, ['thesis_draft'])->assertConflict();
    expect($draft->documents()->count())->toBe(1);
});

test('meet_v2_stage_two_rejects_removed_capture_without_writing_a_file', function () {
    $draft = ($this->newMeetDraft)();
    foreach (['level_2_result_capture', 'igracias_approval_capture', 'ta_progress_capture', 'skpi_tak_capture'] as $type) {
        ($this->uploadStep)($draft, 2, [$type])->assertUnprocessable()->assertJsonValidationErrors('documents');
    }
    expect($draft->fresh()->version)->toBe(1);
    expect($draft->documents()->count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

test('meet_v2_stage_three_can_save_partial_pdf_with_missing_payment_date', function () {
    $draft = ($this->newMeetDraft)();
    ($this->uploadStep)($draft, 2, Meet::CAPTURES)->assertOk();
    ($this->uploadStep)($draft, 3, ['graduation_payment_proof'])->assertOk();
    $progress = Requirements::progress($draft->fresh(), 3);
    expect($progress['required_documents'])->toBe(3);
    expect($progress['stored_documents'])->toBe(1);
    expect($progress['complete'])->toBeFalse();
    expect($draft->documents()->where('document_type', 'graduation_payment_proof')->exists())->toBeTrue();
});

test('meet_v2_waiver_mode_requires_the_fourth_stage_three_pdf', function () {
    $draft = ($this->newMeetDraft)('waiver');
    ($this->uploadStep)($draft, 2, Meet::CAPTURES)->assertOk();
    ($this->uploadStep)($draft, 3, Meet::PAYMENT, ['graduation_payment_date' => today()->toDateString()])->assertOk();
    $progress = Requirements::progress($draft->fresh(), 3);
    expect($progress['required_documents'])->toBe(4);
    expect($progress['stored_documents'])->toBe(3);
    expect($progress['complete'])->toBeFalse();
    expect($progress['missing'])->toContain('Surat Permakluman EPRT');
    ($this->uploadStep)($draft, 3, [Meet::WAIVER])->assertOk();
    expect(Requirements::progress($draft->fresh(), 3)['complete'])->toBeTrue();
});

test('meet_v2_waiver_is_pdf_only_and_removed_stage_three_uploads_are_rejected', function () {
    $draft = ($this->newMeetDraft)('waiver');
    ($this->uploadStep)($draft, 2, Meet::CAPTURES)->assertOk();
    $version = $draft->fresh()->version;
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 3]), [
        'version' => $version, 'documents' => [Meet::WAIVER => UploadedFile::fake()->create('gambar.png', 100, 'image/png')],
    ])->assertUnprocessable()->assertJsonValidationErrors('documents.'.Meet::WAIVER);
    foreach (['eprt_lac_certificate', 'bpp_payment_proof', 'transcript_khs', 'ta_course_ksm'] as $type) {
        ($this->uploadStep)($draft, 3, [$type])->assertUnprocessable()->assertJsonValidationErrors('documents');
    }
    expect($draft->fresh()->version)->toBe($version);
    expect($draft->documents()->count())->toBe(2);
});

test('meet_v2_retest_or_unknown_academics_do_not_lock_draft_or_review', function () {
    foreach (['retest', 'unknown'] as $mode) {
        $draft = ($this->finishMeetDraft)($mode);
        expect(app(SidangTaAdmissionFacts::class)->forStudent($this->student)['academic']['academic_passed'])->toBeFalse();
        expect(Requirements::firstIncompleteStep($draft))->toBeNull();
        $this->postJson(route('sidang-ta-drafts.review', $draft), ['version' => $draft->version])->assertOk();
        expect($draft->fresh()->reviewed_version)->toBe($draft->version);
        expect($draft->documents()->count())->toBe(7);
        // Discard before the next scenario; no final registration is created in this test.
        $this->deleteJson(route('sidang-ta-drafts.destroy', $draft), ['version' => $draft->version])->assertNoContent();
    }
});

test('meet_v2_pdm_branch_requires_only_one_stage_four_pdf', function () {
    $draft = ($this->finishMeetDraft)('automatic', false);
    ($this->uploadStep)($draft, 4, Meet::DIPLOMA, ['diploma_biodata_accurate' => false])
        ->assertUnprocessable()->assertJsonValidationErrors('documents');
    $progress = Requirements::progress($draft->fresh(), 4);
    expect($progress['required_documents'])->toBe(1);
    expect($progress['complete'])->toBeTrue();
    expect($draft->documents()->count())->toBe(6);
    expect($draft->documents()->whereIn('document_type', Meet::DIPLOMA)->exists())->toBeFalse();
});

test('meet_v2_new_waiver_requirement_after_review_blocks_review_again_until_uploaded', function () {
    $draft = ($this->finishMeetDraft)();
    $this->postJson(route('sidang-ta-drafts.review', $draft), ['version' => $draft->version])->assertOk();
    $paths = Storage::disk('local')->allFiles();
    ($this->setEprtMode)('waiver');
    expect(Requirements::firstIncompleteStep($draft->fresh()))->toBe(3);
    $this->postJson(route('sidang-ta-drafts.review', $draft), ['version' => $draft->version])
        ->assertUnprocessable()->assertJsonValidationErrors('draft');
    expect(Storage::disk('local')->allFiles())->toBe($paths);
    ($this->uploadStep)($draft, 3, [Meet::WAIVER])->assertOk();
    expect($draft->fresh()->reviewed_version)->toBeNull();
    expect(Requirements::firstIncompleteStep($draft->fresh()))->toBeNull();
});

test('meet_v2_uncertain_or_retest_eprt_keeps_previously_uploaded_waiver', function () {
    $draft = ($this->finishMeetDraft)('waiver');
    $path = $draft->documents()->where('document_type', Meet::WAIVER)->firstOrFail()->file_path;
    foreach (['unknown', 'retest'] as $mode) {
        ($this->setEprtMode)($mode);
        ($this->uploadStep)($draft, 3, [])->assertOk();
        Storage::disk('local')->assertExists($path);
        expect($draft->documents()->where('document_type', Meet::WAIVER)->exists())->toBeTrue();
    }
    ($this->setEprtMode)('waiver');
    expect(Requirements::progress($draft->fresh(), 3)['complete'])->toBeTrue();
    expect($draft->documents()->count())->toBe(8);
});

test('meet_v2_obsolete_waiver_is_retired_only_after_explicit_save_and_other_files_remain', function () {
    $draft = ($this->finishMeetDraft)('waiver');
    $waiver = $draft->documents()->where('document_type', Meet::WAIVER)->firstOrFail();
    $keep = $draft->documents()->where('document_type', 'thesis_draft')->firstOrFail()->file_path;
    ($this->setEprtMode)('automatic');
    Requirements::progress($draft->fresh(), 3);
    Storage::disk('local')->assertExists($waiver->file_path);
    ($this->uploadStep)($draft, 3, [])->assertOk();
    expect($draft->documents()->where('document_type', Meet::WAIVER)->exists())->toBeFalse();
    Storage::disk('local')->assertMissing($waiver->file_path);
    Storage::disk('local')->assertExists($keep);
    expect($draft->documents()->count())->toBe(7);
});

test('meet_v2_stale_upload_does_not_change_version_or_store_selected_file', function () {
    $draft = ($this->newMeetDraft)();
    ($this->uploadStep)($draft, 2, ['ta_data_capture'])->assertOk();
    $paths = Storage::disk('local')->allFiles();
    ($this->uploadStep)($draft, 2, ['online_supervision_capture'], ['version' => 1])->assertConflict();
    expect($draft->fresh()->version)->toBe(2);
    expect($draft->documents()->count())->toBe(1);
    expect(Storage::disk('local')->allFiles())->toBe($paths);
});

test('meet_v2_missing_saved_capture_keeps_the_stage_incomplete_without_losing_other_files', function () {
    $draft = ($this->finishMeetDraft)();
    $document = $draft->documents()->where('document_type', 'ta_data_capture')->firstOrFail();
    Storage::disk('local')->delete($document->file_path);
    $progress = Requirements::progress($draft->fresh(), 2);
    expect($progress['required_documents'])->toBe(2);
    expect($progress['stored_documents'])->toBe(1);
    expect($progress['complete'])->toBeFalse();
    expect(Requirements::firstIncompleteStep($draft->fresh()))->toBe(2);
    expect($draft->documents()->count())->toBe(7);
});

test('meet_v2_missing_academic_facts_block_submit_without_consuming_the_saved_reviewed_draft', function () {
    $draft = ($this->finishMeetDraft)();
    $this->postJson(route('sidang-ta-drafts.review', $draft), ['version' => $draft->version])->assertOk();
    $paths = Storage::disk('local')->allFiles();
    $this->postJson(route('sidang-ta-registrations.store'), ['draft_id' => $draft->id,
        'version' => $draft->version, 'action' => 'submit'])->assertUnprocessable()
        ->assertJsonValidationErrors(['eligibility.level_2_passed', 'eligibility.ta2_registered',
            'eligibility.skpi_approved', 'eligibility.tak_score', 'eligibility.passed_credits']);
    expect($draft->fresh()->status)->toBe('active');
    expect($draft->fresh()->reviewed_version)->toBe($draft->version);
    expect($draft->documents()->count())->toBe(7);
    expect(SidangTaRegistration::count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBe($paths);
});

test('meet_v2_current_consumed_replay_returns_saved_registration_before_fresh_admission_checks', function () {
    $draft = ($this->finishMeetDraft)();
    $registration = SidangTaRegistration::create(['sidang_ta_period_id' => $this->period->id,
        'requirements_version' => 2, 'student_number' => $this->student->student_number,
        'student_name' => $this->student->name, 'student_phone' => '081234567890', 'parent_phone' => '081234567891',
        'graduation_payment_date' => today()->toDateString(), 'diploma_biodata_accurate' => true, 'status' => 'approved']);
    $draft->update(['requirements_version' => 2, 'status' => 'consumed', 'submitted_registration_id' => $registration->id]);
    $paths = Storage::disk('local')->allFiles();
    $this->postJson(route('sidang-ta-registrations.store'), ['draft_id' => $draft->id,
        'version' => $draft->version, 'action' => 'submit'])->assertOk()
        ->assertJsonPath('data.id', $registration->id)->assertJsonPath('data.status', 'approved');
    expect($registration->logs()->count())->toBe(0);
    expect($registration->fresh()->requirements_version)->toBe(2);
    expect(Storage::disk('local')->allFiles())->toBe($paths);
});
```

### `tests/Feature/SidangTaMeetFinalizationTest.php`

```php
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

test('meet_final_staff_can_read_export_and_verify_while_revision_requires_document_feedback', function () {
    $draft = ($this->markReviewed)(($this->meetDraft)('waiver'));
    $id = ($this->submitMeet)($draft)->assertOk()->json('data.id');
    $registration = SidangTaRegistration::findOrFail($id);
    $snapshot = $registration->academic_snapshot;
    ($this->asReviewer)();
    $crawler = new Crawler($this->get(route('admin.sidang-ta-registrations.show', $registration))->assertOk()->getContent());
    expect($crawler->filter('[data-meet-review-held]')->count())->toBe(0);
    expect($crawler->filter('[data-ta-revision-blockers]')->count())->toBe(1);
    expect($crawler->filter('[data-ta-revision-blockers]')->text(null, true))->toContain('Tandai minimal satu dokumen');
    expect($crawler->filter('[data-ta-request-revision]')->getNode(0)->hasAttribute('disabled'))->toBeTrue();
    expect($crawler->filter('[data-doc-review-form]')->count())->toBe(8);
    expect($crawler->filter('form[action="'.route('admin.sidang-ta-registrations.review', $registration).'"]')->count())->toBe(1);
    expect($crawler->filter('a[data-document-preview]')->count())->toBe(8);
    $waiver = $registration->documents()->where('document_type', Meet::WAIVER)->firstOrFail();
    $this->get(route('sidang-ta-registration-documents.preview', $waiver))->assertOk();
    $row = (new Crawler($this->get(route('admin.sidang-ta-registrations.index'))->assertOk()->getContent()))
        ->filter('[data-ta-row="'.$id.'"]');
    expect($row->filter('[data-ta-doc]')->count())->toBe(8);
    expect($row->filter('[data-ta-doc="eprt_waiver_letter"]')->attr('title'))->toContain('Surat Permakluman EPRT');
    expect($row->filter('[data-ta-doc="level_2_result_capture"], [data-ta-doc="transcript_khs"]')->count())->toBe(0);
    $export = app(SidangTaReviewExport::class)->row($registration->fresh()->load(['documents', 'period']));
    expect($export)->toHaveCount(57);
    expect($export['Surat Permakluman EPRT — Status'])->toBe('Menunggu verifikasi');
    expect($export['KRS Sidang TA 2'])->toBe('Terdaftar');
    expect(array_key_exists('KHS (PDF, SKS lulus minimal 120) — Status', $export))->toBeFalse();
    expect($export['Bukti Pengajuan PDM — Status'])->toBe('N/A');
    $this->postJson(route('admin.sidang-ta-registrations.documents.review', [
        'sidang_ta_registration' => $registration, 'document' => $waiver,
    ]), ['registration_version' => $registration->lock_version, 'status' => 'verified'])->assertOk();
    $this->postJson(route('admin.sidang-ta-registrations.review', $registration), [
        'registration_version' => $registration->fresh()->lock_version, 'action' => 'needs_review', 'review_notes' => 'Perbaiki',
    ])->assertUnprocessable()->assertJsonValidationErrors('action');
    expect($waiver->fresh()->status)->toBe('verified');
    expect($registration->fresh()->status)->toBe('submitted');
    expect($registration->fresh()->academic_snapshot)->toBe($snapshot);
    expect($registration->logs()->count())->toBe(2);
});

test('meet_final_unsupported_marker_one_blocks_submission_without_changing_files', function () {
    $draft = ($this->markReviewed)(($this->meetDraft)('waiver'));
    $draft->update(['requirements_version' => 1]); // Persisted unsupported data, never client input.
    $before = $draft->fresh()->getAttributes();
    $documents = $draft->documents()->orderBy('id')->get()->toArray();
    $paths = Storage::disk('local')->allFiles();
    ($this->submitMeet)($draft)->assertConflict()->assertJsonPath('message', 'Persyaratan pengajuan tidak sesuai. Muat ulang atau hubungi petugas.');
    expect($draft->fresh()->getAttributes())->toBe($before);
    expect($draft->documents()->orderBy('id')->get()->toArray())->toBe($documents);
    ($this->assertPreserved)($draft, $paths);
});

test('meet_final_revision_start_rejects_an_incomplete_review_plan_without_changing_source_files', function () {
    $draft = ($this->markReviewed)(($this->meetDraft)('waiver'));
    $id = ($this->submitMeet)($draft)->assertOk()->json('data.id');
    $registration = SidangTaRegistration::findOrFail($id);
    $registration->update(['status' => 'needs_review']); // Simulate an incomplete server-side review plan.
    $snapshot = $registration->academic_snapshot;
    $paths = Storage::disk('local')->allFiles();
    $this->postJson(route('sidang-ta-drafts.revision', $registration))->assertUnprocessable()
        ->assertJsonValidationErrors('action');
    expect(SidangTaRegistrationDraft::where('source_registration_id', $id)->where('status', 'active')->count())->toBe(0);
    expect($registration->fresh()->status)->toBe('needs_review');
    expect($registration->fresh()->academic_snapshot)->toBe($snapshot);
    expect($registration->documents()->count())->toBe(8);
    expect($registration->documents()->where('status', 'pending')->count())->toBe(8);
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
```

### `tests/Feature/SidangTaMeetStaffReviewTest.php`

```php
<?php

use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\User;
use App\Support\SidangTaAdmissionPolicy as Policy;
use App\Support\SidangTaReviewEligibility as Eligibility;
use App\Support\SidangTaMeetRequirements as Meet;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\DomCrawler\Crawler;

beforeEach(function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-11-04 12:00:00', config('app.timezone')));
    Storage::fake('local');
    $permission = Permission::firstOrCreate(['name' => 'sidang_ta_registration_review', 'guard_name' => 'web']);
    $this->reviewer = User::factory()->create(['status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
    $this->reviewer->givePermissionTo($permission);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($this->reviewer);
    $this->period = SidangTaPeriod::create(['name' => 'Meet Staff', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay()]);
    $this->sequence = 0;
    $this->makeMeetRegistration = function (string $mode = 'automatic', bool $accurate = true, array $overrides = []) {
        $this->sequence++;
        $facts = ['level_2_passed' => true, 'ta2_registered' => true, 'skpi_approved' => true,
            'tak_score' => 60, 'passed_credits' => 120];
        $registration = SidangTaRegistration::create(array_replace([
            'sidang_ta_period_id' => $this->period->id, 'requirements_version' => 2,
            'student_number' => 'M57'.str_pad((string) $this->sequence, 7, '0', STR_PAD_LEFT),
            'student_name' => 'Meet Staff Student', 'study_program' => 'Informatika',
            'student_phone' => '081234567890', 'parent_phone' => '081234567891',
            'graduation_payment_date' => today()->toDateString(), 'diploma_biodata_accurate' => $accurate,
            'status' => 'submitted', 'submitted_at' => now(),
            'academic_snapshot' => ['student_name' => 'Nama Snapshot', 'tak_score' => 60,
                'admission' => ['facts' => $facts, 'academic' => Policy::academic($facts),
                    'eprt' => Policy::eprt($mode === 'automatic' ? 450 : 449, $mode === 'automatic' ? 1 : 3)]],
        ], $overrides))->fresh();
        foreach (Eligibility::requiredTypes($registration) as $type) {
            $path = 'sidang_ta/meet57/'.$registration->id.'/'.$type.'.pdf';
            Storage::disk('local')->put($path, '%PDF-1.4 meet staff');
            $registration->documents()->create(['document_type' => $type, 'file_path' => $path,
                'file_name' => $type.'.pdf', 'file_size' => 21, 'file_type' => 'pdf', 'status' => 'pending']);
        }
        return $registration;
    };
    $this->reviewDocument = function ($registration, $document, array $overrides = []) {
        return $this->postJson(route('admin.sidang-ta-registrations.documents.review', [
            'sidang_ta_registration' => $registration, 'document' => $document,
        ]), array_replace(['registration_version' => $registration->fresh()->lock_version,
            'status' => 'verified'], $overrides));
    };
    $this->decideMeet = function ($registration, string $action, array $overrides = []) {
        return $this->postJson(route('admin.sidang-ta-registrations.review', $registration), array_replace([
            'registration_version' => $registration->fresh()->lock_version,
            'action' => $action, 'review_notes' => $action === 'approved' ? null : 'Catatan petugas',
        ], $overrides));
    };
    $this->reviewPage = function ($registration) {
        return new Crawler($this->get(route('admin.sidang-ta-registrations.show', $registration))->assertOk()->getContent());
    };
});

afterEach(function () { $this->travelBack(); });

test('meet_staff_verifies_one_file_without_approving_the_registration', function () {
    $registration = ($this->makeMeetRegistration)();
    $document = $registration->documents()->firstOrFail();
    $paths = Storage::disk('local')->allFiles();
    $snapshot = $registration->academic_snapshot;
    ($this->reviewDocument)($registration, $document)->assertOk()->assertJsonPath('data.status', 'submitted')
        ->assertJsonPath('data.lock_version', 2);
    expect($document->fresh()->status)->toBe('verified');
    expect($registration->documents()->where('status', 'pending')->count())->toBe(6);
    expect($registration->logs()->firstOrFail()->action)->toBe('document_verified');
    expect($registration->fresh()->academic_snapshot)->toBe($snapshot);
    expect(Storage::disk('local')->allFiles())->toBe($paths);
});

test('meet_staff_rejecting_a_document_does_not_start_student_revision', function () {
    $registration = ($this->makeMeetRegistration)();
    $document = $registration->documents()->firstOrFail();
    ($this->reviewDocument)($registration, $document, ['status' => 'rejected', 'notes' => '  Perbaiki PDF  '])
        ->assertOk()->assertJsonPath('data.status', 'submitted');
    expect($document->fresh()->notes)->toBe('Perbaiki PDF');
    expect($document->fresh()->status)->toBe('rejected');
    expect($registration->fresh()->status)->toBe('submitted');
    $log = $registration->logs()->firstOrFail();
    expect($log->from_status)->toBe('submitted');
    expect($log->to_status)->toBe('submitted');
});

test('meet_staff_can_reverse_both_document_states_and_verification_clears_notes', function () {
    $registration = ($this->makeMeetRegistration)('waiver');
    $document = $registration->documents()->where('document_type', Meet::WAIVER)->firstOrFail();
    ($this->reviewDocument)($registration, $document)->assertOk();
    ($this->reviewDocument)($registration, $document, ['status' => 'rejected', 'notes' => 'Perbaiki surat'])->assertOk();
    ($this->reviewDocument)($registration, $document, ['notes' => 'Tidak boleh menjadi catatan verified'])->assertOk();
    expect($document->fresh()->status)->toBe('verified');
    expect($document->fresh()->notes)->toBeNull();
    expect($registration->fresh()->status)->toBe('submitted');
    expect($registration->fresh()->lock_version)->toBe(4);
    expect($registration->logs()->count())->toBe(3);
    expect($registration->logs()->where('action', 'document_verified')->latest('id')->firstOrFail()->notes)
        ->toContain('Surat Permakluman EPRT')->not->toContain('Tidak boleh');
});

test('meet_staff_same_verification_is_noop_even_when_unused_notes_are_sent', function () {
    $registration = ($this->makeMeetRegistration)();
    $document = $registration->documents()->firstOrFail();
    ($this->reviewDocument)($registration, $document)->assertOk();
    ($this->reviewDocument)($registration, $document, ['notes' => 'Diabaikan'])->assertOk()->assertJsonPath('data.lock_version', 2);
    expect($document->fresh()->notes)->toBeNull();
    expect($registration->logs()->count())->toBe(1);
});

test('meet_staff_can_correct_rejected_notes_without_changing_registration_state', function () {
    $registration = ($this->makeMeetRegistration)();
    $document = $registration->documents()->firstOrFail();
    ($this->reviewDocument)($registration, $document, ['status' => 'rejected', 'notes' => 'Catatan A'])->assertOk();
    ($this->reviewDocument)($registration, $document, ['status' => 'rejected', 'notes' => 'Catatan A'])->assertOk();
    expect($registration->fresh()->lock_version)->toBe(2);
    ($this->reviewDocument)($registration, $document, ['status' => 'rejected', 'notes' => 'Catatan B'])->assertOk();
    expect($document->fresh()->notes)->toBe('Catatan B');
    expect($registration->fresh()->lock_version)->toBe(3);
    expect($registration->logs()->count())->toBe(2);
});

test('meet_staff_document_rejection_requires_a_nonblank_note', function () {
    $registration = ($this->makeMeetRegistration)();
    $document = $registration->documents()->firstOrFail();
    foreach ([null, '', '   ', ['invalid']] as $notes) {
        ($this->reviewDocument)($registration, $document, ['status' => 'rejected', 'notes' => $notes])
            ->assertUnprocessable()->assertJsonValidationErrors('notes');
    }
    expect($registration->fresh()->lock_version)->toBe(1);
    expect($document->fresh()->status)->toBe('pending');
    expect($registration->logs()->count())->toBe(0);
});

test('meet_staff_stale_document_and_decision_requests_do_not_overwrite_newer_review', function () {
    $registration = ($this->makeMeetRegistration)();
    $documents = $registration->documents()->get();
    ($this->reviewDocument)($registration, $documents->first())->assertOk();
    ($this->reviewDocument)($registration, $documents->get(1), ['registration_version' => 1])->assertConflict();
    ($this->decideMeet)($registration, 'rejected', ['registration_version' => 1])->assertConflict();
    expect($documents->get(1)->fresh()->status)->toBe('pending');
    expect($registration->fresh()->status)->toBe('submitted');
    expect($registration->logs()->count())->toBe(1);
});

test('meet_staff_cannot_review_a_document_from_another_registration_or_inactive_branch', function () {
    $a = ($this->makeMeetRegistration)();
    $b = ($this->makeMeetRegistration)();
    ($this->reviewDocument)($a, $b->documents()->firstOrFail())->assertNotFound();
    $extra = $a->documents()->create(['document_type' => 'pdm_submission_proof', 'file_name' => 'extra.pdf',
        'file_path' => 'sidang_ta/extra.pdf', 'file_type' => 'pdf', 'status' => 'pending']);
    ($this->reviewDocument)($a, $extra)->assertUnprocessable();
    expect($a->fresh()->lock_version)->toBe(1);
    expect($b->fresh()->lock_version)->toBe(1);
});

test('meet_staff_missing_files_prevent_verification_and_approval', function () {
    $registration = ($this->makeMeetRegistration)();
    $registration->documents()->update(['status' => 'verified']);
    $document = $registration->documents()->firstOrFail();
    Storage::disk('local')->delete($document->file_path);
    ($this->reviewDocument)($registration, $document)->assertUnprocessable()->assertJsonValidationErrors('document');
    ($this->decideMeet)($registration, 'approved')->assertUnprocessable()->assertJsonValidationErrors('documents');
    $crawler = ($this->reviewPage)($registration);
    expect($crawler->filter('#review-document-'.$document->id)->attr('data-document-review-state'))->toBe('unavailable');
    expect($crawler->filter('[data-ta-approve]')->getNode(0)->hasAttribute('disabled'))->toBeTrue();
    expect($registration->fresh()->lock_version)->toBe(1);
});

test('meet_staff_approval_requires_all_required_documents_to_be_verified', function (string $mode) {
    $registration = ($this->makeMeetRegistration)($mode);
    ($this->decideMeet)($registration, 'approved')->assertUnprocessable()->assertJsonValidationErrors('documents');
    $registration->documents()->update(['status' => 'verified']);
    ($this->decideMeet)($registration, 'approved')->assertRedirect();
    expect($registration->fresh()->status)->toBe('approved');
    expect((int) $registration->fresh()->reviewed_by)->toBe($this->reviewer->id);
    expect($registration->logs()->count())->toBe(1);
    expect($registration->logs()->firstOrFail()->action)->toBe('approved');
})->with(['automatic', 'waiver']);

test('meet_staff_rejection_keeps_files_and_requires_a_note', function () {
    $registration = ($this->makeMeetRegistration)();
    $paths = Storage::disk('local')->allFiles();
    ($this->decideMeet)($registration, 'rejected', ['review_notes' => '   '])->assertUnprocessable()->assertJsonValidationErrors('review_notes');
    ($this->decideMeet)($registration, 'rejected', ['review_notes' => '  Berkas tidak sah  '])->assertRedirect();
    expect($registration->fresh()->review_notes)->toBe('Berkas tidak sah');
    expect($registration->fresh()->status)->toBe('rejected');
    expect(Storage::disk('local')->allFiles())->toBe($paths);
});

test('meet_staff_revision_request_requires_review_of_the_remaining_documents', function () {
    $registration = ($this->makeMeetRegistration)();
    $document = $registration->documents()->where('document_type', 'ta_data_capture')->firstOrFail();
    ($this->reviewDocument)($registration, $document, ['status' => 'rejected', 'notes' => 'Perbaiki scan'])->assertOk();
    $response = ($this->decideMeet)($registration, 'needs_review')->assertUnprocessable()
        ->assertJsonValidationErrors(['documents.online_supervision_capture', 'documents.thesis_draft']);
    expect(array_key_exists('action', $response->json('errors')))->toBeFalse();
    expect($registration->fresh()->status)->toBe('submitted');
    expect($registration->fresh()->lock_version)->toBe(2);
    expect($document->fresh()->notes)->toBe('Perbaiki scan');
    expect($registration->logs()->count())->toBe(1);
});

test('meet_staff_waiting_revision_and_terminal_states_have_no_mutation_controls', function (string $status) {
    $registration = ($this->makeMeetRegistration)('automatic', true, ['status' => $status]);
    $document = $registration->documents()->firstOrFail();
    ($this->reviewDocument)($registration, $document)->assertUnprocessable();
    foreach (['approved', 'rejected', 'needs_review'] as $action) {
        ($this->decideMeet)($registration, $action)->assertUnprocessable();
    }
    $crawler = ($this->reviewPage)($registration);
    expect($crawler->filter('[data-doc-review-form], [data-ta-decision]')->count())->toBe(0);
    expect($crawler->filter('a[data-document-preview]')->count())->toBe(7);
    expect($registration->fresh()->lock_version)->toBe(1);
    expect($registration->logs()->count())->toBe(0);
})->with(['needs_review', 'approved', 'rejected']);

test('meet_staff_read_permission_alone_does_not_allow_document_or_decision_mutations', function () {
    $registration = ($this->makeMeetRegistration)();
    $reader = User::factory()->create(['status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
    $reader->givePermissionTo(Permission::firstOrCreate(['name' => 'sidang_ta_registration_read', 'guard_name' => 'web']));
    $this->actingAs($reader);
    ($this->reviewDocument)($registration, $registration->documents()->firstOrFail())->assertForbidden();
    ($this->decideMeet)($registration, 'approved')->assertForbidden();
    expect($registration->fresh()->lock_version)->toBe(1);
});

test('meet_staff_detail_groups_uploads_into_two_columns_with_status_colors_and_disabled_verify', function () {
    $registration = ($this->makeMeetRegistration)('waiver');
    $verified = $registration->documents()->where('document_type', 'ta_data_capture')->firstOrFail();
    $rejected = $registration->documents()->where('document_type', 'online_supervision_capture')->firstOrFail();
    $verified->update(['status' => 'verified']);
    $rejected->update(['status' => 'rejected', 'notes' => 'Perbaiki']);
    $crawler = ($this->reviewPage)($registration);
    expect($crawler->filter('[data-meet-document-group]')->count())->toBe(3);
    expect($crawler->filter('[data-meet-document-grid]')->count())->toBe(3);
    foreach ($crawler->filter('[data-meet-document-grid]') as $node) {
        expect($node->getAttribute('class'))->toContain('lg:grid-cols-2');
    }
    expect($crawler->filter('[data-meet-document-group="2"] [data-ta-detail-document]')->count())->toBe(2);
    expect($crawler->filter('[data-meet-document-group="3"] [data-ta-detail-document]')->count())->toBe(4);
    expect($crawler->filter('[data-meet-document-group="4"] [data-ta-detail-document]')->count())->toBe(2);
    $green = $crawler->filter('#review-document-'.$verified->id);
    $yellow = $crawler->filter('#review-document-'.$rejected->id);
    expect($green->attr('class'))->toContain('bg-emerald-50')->toContain('text-emerald-900');
    expect($yellow->attr('class'))->toContain('bg-amber-50')->toContain('text-amber-900');
    expect($green->filter('[data-doc-verify]')->getNode(0)->hasAttribute('disabled'))->toBeTrue();
    expect($green->filter('[data-doc-reject]')->getNode(0)->hasAttribute('disabled'))->toBeFalse();
    expect($yellow->filter('[data-doc-verify]')->getNode(0)->hasAttribute('disabled'))->toBeFalse();
    expect($crawler->filter('[data-ta-request-revision]')->getNode(0)->hasAttribute('disabled'))->toBeTrue();
    expect($crawler->filter('[data-ta-revision-blockers]')->count())->toBe(1);
    expect($crawler->filter('[data-ta-revision-blockers]')->text(null, true))->toContain('Selesaikan verifikasi');
    expect($crawler->filter('[data-ta-review-window]')->text(null, true))->toContain($this->period->close_at->format('d/m/Y H:i'));
    expect($crawler->filter('[data-staff-registration-history]')->getNode(0)->hasAttribute('open'))->toBeFalse();
});

test('meet_staff_pdm_and_automatic_groups_only_show_their_six_uploads', function () {
    $registration = ($this->makeMeetRegistration)('automatic', false);
    $crawler = ($this->reviewPage)($registration);
    expect($crawler->filter('[data-ta-detail-document]')->count())->toBe(6);
    expect($crawler->filter('[data-meet-document-group="3"] [data-ta-detail-document]')->count())->toBe(3);
    expect($crawler->filter('[data-meet-document-group="4"] [data-ta-detail-document]')->count())->toBe(1);
    expect($crawler->filter('[data-doc-review-form]')->count())->toBe(6);
});

test('meet_staff_missing_metadata_is_visible_and_blocks_approval', function () {
    $registration = ($this->makeMeetRegistration)();
    $document = $registration->documents()->where('document_type', 'thesis_draft')->firstOrFail();
    $document->delete();
    $crawler = ($this->reviewPage)($registration);
    expect($crawler->filter('[data-ta-missing-document="thesis_draft"]')->count())->toBe(1);
    expect($crawler->filter('[data-ta-approve]')->getNode(0)->hasAttribute('disabled'))->toBeTrue();
    ($this->decideMeet)($registration, 'approved')->assertUnprocessable()->assertJsonValidationErrors('documents');
});

test('meet_staff_html_review_keeps_the_card_anchor_and_textarea_without_padding_whitespace', function () {
    $registration = ($this->makeMeetRegistration)();
    $document = $registration->documents()->firstOrFail();
    $url = route('admin.sidang-ta-registrations.show', $registration);
    $this->post(route('admin.sidang-ta-registrations.documents.review', [
        'sidang_ta_registration' => $registration, 'document' => $document,
    ]), ['registration_version' => 1, 'status' => 'rejected', 'notes' => 'fix en iki',
        '_review_form' => 'document:'.$document->id])->assertRedirect($url.'#review-document-'.$document->id);
    $card = ($this->reviewPage)($registration)->filter('#review-document-'.$document->id);
    expect($card->filter('textarea[name="notes"]')->getNode(0)->textContent)->toBe('fix en iki');
});

test('meet_staff_escapes_file_names_and_document_feedback', function () {
    $registration = ($this->makeMeetRegistration)();
    $document = $registration->documents()->firstOrFail();
    $document->update(['file_name' => '<script>badName()</script>.pdf', 'notes' => '<script>badNote()</script>', 'status' => 'rejected']);
    $card = ($this->reviewPage)($registration)->filter('#review-document-'.$document->id);
    expect($card->filter('script')->count())->toBe(0);
    expect($card->filter('[data-document-notes]')->text(null, true))->toBe('<script>badNote()</script>');
    expect($card->filter('textarea[name="notes"]')->getNode(0)->textContent)->toBe('<script>badNote()</script>');
});

test('meet_staff_unsupported_requirements_marker_is_rejected_before_mutation', function (int $marker) {
    $registration = ($this->makeMeetRegistration)();
    $document = $registration->documents()->firstOrFail();
    $registration->update(['requirements_version' => $marker]);
    $before = $registration->fresh()->getAttributes();
    $documents = $registration->documents()->orderBy('id')->get()->toArray();
    $paths = Storage::disk('local')->allFiles();
    ($this->reviewDocument)($registration, $document)->assertConflict()->assertJsonPath('message', 'Persyaratan pengajuan tidak sesuai. Muat ulang atau hubungi petugas.');
    ($this->reviewDocument)($registration, $document, ['status' => 'rejected', 'notes' => 'Perbaiki PDF'])
        ->assertConflict()->assertJsonPath('message', 'Persyaratan pengajuan tidak sesuai. Muat ulang atau hubungi petugas.');
    foreach (['approved', 'needs_review', 'rejected'] as $action) {
        ($this->decideMeet)($registration, $action)->assertConflict()->assertJsonPath('message', 'Persyaratan pengajuan tidak sesuai. Muat ulang atau hubungi petugas.');
    }
    expect($registration->fresh()->getAttributes())->toBe($before);
    expect($registration->documents()->orderBy('id')->get()->toArray())->toBe($documents);
    expect($document->fresh()->status)->toBe('pending');
    expect($registration->fresh()->lock_version)->toBe(1);
    expect($registration->logs()->count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBe($paths);
})->with([1, 3, 99]);

test('meet_staff_failed_form_keeps_its_old_version_without_rebasing_other_cards', function () {
    $registration = ($this->makeMeetRegistration)();
    $documents = $registration->documents()->get();
    $document = $documents->first();
    $other = $documents->get(1);
    $url = route('admin.sidang-ta-registrations.show', $registration);
    $this->from($url)->post(route('admin.sidang-ta-registrations.documents.review', [
        'sidang_ta_registration' => $registration, 'document' => $document,
    ]), ['registration_version' => 1, 'status' => 'rejected', 'notes' => ['invalid'],
        '_review_form' => 'document:'.$document->id])->assertRedirect($url.'#review-document-'.$document->id)->assertSessionHasErrors('notes');
    $registration->lock_version++;
    $registration->save();
    $crawler = ($this->reviewPage)($registration);
    expect($crawler->filter('[data-doc-review-form="'.$document->id.'"] input[name="registration_version"]')->attr('value'))->toBe('1');
    expect($crawler->filter('[data-doc-review-form="'.$other->id.'"] input[name="registration_version"]')->attr('value'))->toBe('2');
    expect($crawler->filter('[data-ta-decision] input[name="registration_version"]')->attr('value'))->toBe('2');
    ($this->reviewDocument)($registration, $document, ['registration_version' => 1])->assertConflict();
    expect($registration->fresh()->lock_version)->toBe(2);
    expect($registration->logs()->count())->toBe(0);
});
```

### `tests/Feature/SidangTaPartialDraftTest.php`

```php
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
use Symfony\Component\DomCrawler\Crawler;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create([
        'username' => '1202200001', 'email' => 'partial@mahasiswa.test',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false,
    ]);
    Student::create(['student_number' => '1202200001', 'name' => 'Partial Test',
        'email' => $this->user->email, 'academic_year' => 2022,
        'study_program' => 'Informatika', 'status' => 'Aktif']);
    foreach (['create', 'read', 'update'] as $action) {
        $this->user->givePermissionTo(Permission::firstOrCreate([
            'name' => "sidang_ta_registration_{$action}", 'guard_name' => 'web',
        ]));
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->period = SidangTaPeriod::create(['name' => 'Partial Period', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay()]);
    $this->data = ['sidang_ta_period_id' => $this->period->id,
        'student_phone' => '081234567890', 'parent_phone' => '081234567891'];
    $id = $this->actingAs($this->user)->postJson(route('sidang-ta-drafts.store'), $this->data)
        ->assertCreated()->json('data.id');
    $this->draft = SidangTaRegistrationDraft::findOrFail($id);
    expect($this->draft->requirements_version)->toBe(2);
    $this->filesFor = function (int $step, ?bool $accurate = null) {
        $files = [];
        foreach (Requirements::typesForStep($step, $accurate) as $type) {
            $files[$type] = UploadedFile::fake()->create($type.'.pdf', 100, 'application/pdf');
        }
        return $files;
    };
    $this->savePartial = function (int $step, array $extra = []) {
        return $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $this->draft, 'step' => $step]),
            array_replace(['version' => $this->draft->fresh()->version], $extra));
    };
    $this->throughThree = function () {
        ($this->savePartial)(2, ['documents' => ($this->filesFor)(2)])->assertOk();
        ($this->savePartial)(3, ['graduation_payment_date' => today()->toDateString(),
            'documents' => ($this->filesFor)(3)])->assertOk();
    };
});

test('partial_capture_is_persisted_and_resumable_without_unlocking_next_step', function () {
    ($this->savePartial)(2, ['documents' => array_slice(($this->filesFor)(2), 0, 1, true)])
        ->assertOk()->assertJsonCount(1, 'data.documents')->assertJsonPath('data.version', 2)
        ->assertJsonPath('data.first_incomplete_step', 2);
    $document = $this->draft->documents()->firstOrFail();
    Storage::disk('local')->assertExists($document->file_path);
    $this->getJson(route('sidang-ta-drafts.edit', $this->draft))->assertOk()
        ->assertJsonCount(1, 'data.documents')->assertJsonPath('data.first_incomplete_step', 2);
    $this->get(route('sidang-ta-drafts.edit', ['draft' => $this->draft, 'step' => 3]))
        ->assertRedirect(route('sidang-ta-drafts.edit', ['draft' => $this->draft, 'step' => 2]));
});

test('separate_capture_saves_complete_stage_without_reuploading_previous_files', function () {
    $files = ($this->filesFor)(2);
    ($this->savePartial)(2, ['documents' => array_slice($files, 0, 1, true)])->assertOk();
    $first = $this->draft->documents()->where('document_type', Requirements::CAPTURES[0])->firstOrFail();
    ($this->savePartial)(2, ['documents' => array_slice($files, 1, null, true)])
        ->assertOk()->assertJsonCount(2, 'data.documents')->assertJsonPath('data.version', 3)
        ->assertJsonPath('data.first_incomplete_step', 3);
    expect($first->fresh()->file_path)->toBe($first->file_path);
    Storage::disk('local')->assertExists($first->file_path);
    expect(Requirements::progress($this->draft->fresh(), 2)['complete'])->toBeTrue();
});

test('payment_file_saves_without_date_but_stage_four_stays_locked', function () {
    ($this->savePartial)(2, ['documents' => ($this->filesFor)(2)])->assertOk();
    ($this->savePartial)(3, ['documents' => array_slice(($this->filesFor)(3), 0, 1, true)])
        ->assertOk()->assertJsonCount(3, 'data.documents')->assertJsonPath('data.first_incomplete_step', 3);
    $progress = Requirements::progress($this->draft->fresh(), 3);
    expect($progress['stored_documents'])->toBe(1);
    expect($progress['missing'])->toContain('Tanggal pembayaran wisuda');
    $this->get(route('sidang-ta-drafts.edit', ['draft' => $this->draft, 'step' => 4]))
        ->assertRedirect(route('sidang-ta-drafts.edit', ['draft' => $this->draft, 'step' => 3]));
});

test('null_biodata_choice_is_saved_without_becoming_pdm', function () {
    ($this->throughThree)();
    ($this->savePartial)(4, ['diploma_biodata_accurate' => null])->assertOk()
        ->assertJsonCount(5, 'data.documents')->assertJsonPath('data.first_incomplete_step', 4);
    expect($this->draft->fresh()->payload['diploma_biodata_accurate'])->toBeNull();
    $crawler = new Crawler($this->get(route('sidang-ta-drafts.edit', ['draft' => $this->draft, 'step' => 4]))
        ->assertOk()->getContent());
    expect($crawler->filter('input[name="diploma_biodata_accurate"][checked]')->count())->toBe(0);
    expect($crawler->filter('input[type="file"][disabled]')->count())->toBe(3);
    expect($crawler->filter('[data-review-submit]')->getNode(0)->hasAttribute('disabled'))->toBeTrue();
});

test('one_diploma_file_saves_and_second_file_makes_review_available', function () {
    ($this->throughThree)();
    $files = ($this->filesFor)(4, true);
    ($this->savePartial)(4, ['diploma_biodata_accurate' => true,
        'documents' => array_slice($files, 0, 1, true)])->assertOk()
        ->assertJsonCount(6, 'data.documents')->assertJsonPath('data.first_incomplete_step', 4);
    $this->postJson(route('sidang-ta-drafts.review', $this->draft), [
        'version' => $this->draft->fresh()->version,
    ])->assertUnprocessable();
    ($this->savePartial)(4, ['documents' => array_slice($files, 1, null, true)])->assertOk()
        ->assertJsonCount(7, 'data.documents')->assertJsonPath('data.first_incomplete_step', null);
    $version = $this->draft->fresh()->version;
    $this->postJson(route('sidang-ta-drafts.review', $this->draft), ['version' => $version])->assertOk();
    expect($this->draft->fresh()->reviewed_version)->toBe($version);
});

test('invalid_provided_file_rejects_whole_new_batch_and_keeps_saved_progress', function () {
    ($this->savePartial)(2, ['documents' => array_slice(($this->filesFor)(2), 0, 1, true)])->assertOk();
    $before = Storage::disk('local')->allFiles();
    ($this->savePartial)(2, ['documents' => [
        Requirements::CAPTURES[0] => UploadedFile::fake()->create('valid.pdf', 100, 'application/pdf'),
        Requirements::CAPTURES[1] => UploadedFile::fake()->create('invalid.txt', 100, 'text/plain'),
    ]])->assertUnprocessable()->assertJsonValidationErrors('documents.'.Requirements::CAPTURES[1]);
    expect($this->draft->fresh()->version)->toBe(2);
    expect($this->draft->documents()->count())->toBe(1);
    expect(Storage::disk('local')->allFiles())->toBe($before);
});

test('biodata_files_require_known_active_branch_even_for_partial_save', function () {
    ($this->throughThree)();
    $version = $this->draft->fresh()->version;
    ($this->savePartial)(4, ['documents' => ($this->filesFor)(4, false)])
        ->assertUnprocessable()->assertJsonValidationErrors('documents');
    ($this->savePartial)(4, ['diploma_biodata_accurate' => false,
        'documents' => array_slice(($this->filesFor)(4, true), 0, 1, true)])
        ->assertUnprocessable()->assertJsonValidationErrors('documents');
    expect($this->draft->fresh()->version)->toBe($version);
    expect($this->draft->documents()->count())->toBe(5);
});

test('partial_draft_cannot_bypass_review_or_final_submission', function () {
    ($this->savePartial)(2, ['documents' => array_slice(($this->filesFor)(2), 0, 1, true)])->assertOk();
    $version = $this->draft->fresh()->version;
    $this->postJson(route('sidang-ta-drafts.review', $this->draft), ['version' => $version])
        ->assertUnprocessable();
    $this->postJson(route('sidang-ta-registrations.store'), [
        'draft_id' => $this->draft->id, 'version' => $version, 'action' => 'submit',
    ])->assertStatus(409);
    expect(SidangTaRegistration::count())->toBe(0);
    expect($this->draft->fresh()->status)->toBe('active');
    expect($this->draft->documents()->count())->toBe(1);
});

test('stale_partial_save_does_not_add_files_or_change_version', function () {
    ($this->savePartial)(2, ['documents' => array_slice(($this->filesFor)(2), 0, 1, true)])->assertOk();
    $before = Storage::disk('local')->allFiles();
    ($this->savePartial)(2, ['version' => 1, 'documents' => [
        Requirements::CAPTURES[1] => UploadedFile::fake()->create('stale.pdf', 100, 'application/pdf'),
    ]])->assertStatus(409);
    expect($this->draft->fresh()->version)->toBe(2);
    expect(Storage::disk('local')->allFiles())->toBe($before);
});

test('partial_revision_rejects_branch_change_and_preserves_borrowed_final_files', function () {
    // This case needs a final source, rather than the empty new-registration draft created by the shared setup.
    $this->deleteJson(route('sidang-ta-drafts.destroy', $this->draft), [
        'version' => $this->draft->version,
    ])->assertNoContent();
    expect(SidangTaRegistrationDraft::find($this->draft->id))->toBeNull();
    $source = SidangTaRegistration::create([
        'sidang_ta_period_id' => $this->period->id, 'student_number' => '1202200001',
        'student_name' => 'Partial Test', 'study_program' => 'Informatika',
        'requirements_version' => 2,
        'academic_snapshot' => ['admission' => ['eprt' => ['mode' => 'automatic']]],
        'student_phone' => '081234567890', 'parent_phone' => '081234567891',
        'graduation_payment_date' => today()->toDateString(), 'diploma_biodata_accurate' => true,
        'status' => 'needs_review', 'submitted_at' => now()->subHour(),
        'review_notes' => 'Perbaiki surat pernyataan biodata.',
    ]);
    $source->forceFill(['lock_version' => 2])->save();
    foreach (array_merge(Requirements::CAPTURES, Requirements::PAYMENT, Requirements::DIPLOMA) as $type) {
        $path = "sidang_ta/final/{$source->id}/{$type}.pdf";
        Storage::disk('local')->put($path, '%PDF-1.4 final');
        $source->documents()->create([
            'document_type' => $type, 'file_name' => $type.'.pdf', 'file_path' => $path,
            'file_type' => 'pdf', 'file_size' => 14,
            'status' => $type === 'diploma_statement' ? 'rejected' : 'verified',
            'notes' => $type === 'diploma_statement' ? 'Unggah surat yang terbaca.' : null,
        ]);
    }
    $source = $source->fresh();
    expect($source->requirements_version)->toBe(2);
    expect($source->lock_version)->toBe(2);
    $sourceBefore = $source->getAttributes();
    $sourceDocumentsBefore = $source->documents()->orderBy('id')->get()->toArray();
    $paths = $source->documents()->pluck('file_path')->all();
    $revisionId = $this->postJson(route('sidang-ta-drafts.revision', $source))
        ->assertCreated()->assertJsonCount(7, 'data.documents')
        ->assertJsonPath('data.first_incomplete_step', 4)->json('data.id');
    $this->draft = SidangTaRegistrationDraft::findOrFail($revisionId);
    expect($this->draft->requirements_version)->toBe(2);
    $borrowedBefore = $this->draft->documents()->orderBy('id')->get()->toArray();
    expect($this->draft->documents()->whereNotNull('source_document_id')->count())->toBe(7);
    $version = $this->draft->version;
    ($this->savePartial)(4, ['diploma_biodata_accurate' => false])->assertUnprocessable()
        ->assertJsonValidationErrors('diploma_biodata_accurate');
    $this->getJson(route('sidang-ta-drafts.edit', $this->draft))->assertOk()
        ->assertJsonCount(7, 'data.documents')->assertJsonPath('data.first_incomplete_step', 4);
    expect($this->draft->fresh()->version)->toBe($version);
    expect($this->draft->fresh()->payload['diploma_biodata_accurate'])->toBeTrue();
    expect($this->draft->documents()->orderBy('id')->get()->toArray())->toBe($borrowedBefore);
    expect($source->fresh()->getAttributes())->toBe($sourceBefore);
    expect($source->documents()->orderBy('id')->get()->toArray())->toBe($sourceDocumentsBefore);
    foreach ($paths as $path) Storage::disk('local')->assertExists($path);
});

test('html_save_stays_on_current_step_and_continue_requires_saved_complete_stage', function () {
    $this->post(route('sidang-ta-drafts.store'), $this->data)
        ->assertRedirect(route('sidang-ta-drafts.edit', ['draft' => $this->draft, 'step' => 1]));
    $files = ($this->filesFor)(2);
    $url = route('sidang-ta-drafts.steps.store', ['draft' => $this->draft, 'step' => 2]);
    $edit = route('sidang-ta-drafts.edit', ['draft' => $this->draft, 'step' => 2]);
    $this->post($url, ['version' => 1, 'documents' => array_slice($files, 0, 1, true)])
        ->assertRedirect($edit)->assertSessionHasNoErrors();
    $crawler = new Crawler($this->get($edit)->assertOk()->getContent());
    expect($crawler->filter('[data-step-progress]')->text(null, true))->toContain('1 dari 2 dokumen tersimpan.');
    expect($crawler->filter('button[data-next-step][disabled]')->count())->toBe(1);
    expect($crawler->filter('a[data-next-step]')->count())->toBe(0);
    $this->post($url, ['version' => 2, 'documents' => array_slice($files, 1, null, true)])
        ->assertRedirect($edit)->assertSessionHasNoErrors();
    $crawler = new Crawler($this->get($edit)->assertOk()->getContent());
    expect($crawler->filter('a[data-next-step]')->attr('href'))
        ->toBe(route('sidang-ta-drafts.edit', ['draft' => $this->draft, 'step' => 3]));
    expect($crawler->filter('[data-step-progress]')->count())->toBe(0);
});

test('omitted_scalar_preserves_value_and_explicit_null_clears_without_false_coercion', function () {
    ($this->throughThree)();
    ($this->savePartial)(4, ['diploma_biodata_accurate' => true, 'documents' => ($this->filesFor)(4, true)])->assertOk();
    $version = $this->draft->fresh()->version;
    $this->postJson(route('sidang-ta-drafts.review', $this->draft), ['version' => $version])->assertOk();
    ($this->savePartial)(3)->assertOk();
    expect($this->draft->fresh()->payload['graduation_payment_date'])->toBe(today()->toDateString());
    expect($this->draft->fresh()->reviewed_version)->toBeNull();
    ($this->savePartial)(4)->assertOk();
    expect($this->draft->fresh()->payload['diploma_biodata_accurate'])->toBeTrue();
    ($this->savePartial)(3, ['graduation_payment_date' => null])->assertOk()
        ->assertJsonPath('data.first_incomplete_step', 3);
    expect($this->draft->fresh()->payload['graduation_payment_date'])->toBeNull();
    ($this->savePartial)(3, ['graduation_payment_date' => today()->toDateString()])->assertOk();
    ($this->savePartial)(4, ['diploma_biodata_accurate' => null])->assertOk()
        ->assertJsonPath('data.first_incomplete_step', 4);
    expect($this->draft->fresh()->payload['diploma_biodata_accurate'])->toBeNull();
    expect($this->draft->documents()->count())->toBe(7);
});
```

### `tests/Feature/SidangTaResetTestDataTest.php`

```php
<?php

use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDraft;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create();
    $this->student = Student::create(['student_number' => '1202200099', 'name' => 'Reset fixture',
        'academic_year' => 2022, 'study_program' => 'Informatika', 'status' => 'Aktif']);
    $this->period = SidangTaPeriod::create(['name' => 'Keep period', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay()]);
    // Reset explicitly accepts old dummy submissions as well as future version two.
    $this->registration = SidangTaRegistration::create(['sidang_ta_period_id' => $this->period->id,
        'student_number' => $this->student->student_number, 'student_name' => 'Reset fixture',
        'student_phone' => '08123456789', 'parent_phone' => '08123456788',
        'graduation_payment_date' => today(), 'diploma_biodata_accurate' => true,
        'requirements_version' => 1]);
    $this->document = $this->registration->documents()->create(['document_type' => 'thesis_draft',
        'file_name' => 'original.pdf', 'file_path' => 'sidang_ta/original.pdf']);
    $this->registration->logAction('submitted', null, 'submitted', 'Dummy', $this->user->id);
    $this->draft = SidangTaRegistrationDraft::create(['owner_user_id' => $this->user->id,
        'student_number' => $this->student->student_number, 'sidang_ta_period_id' => $this->period->id,
        'source_registration_id' => $this->registration->id, 'requirements_version' => 1,
        'payload' => []]);
    $this->draft->documents()->create(['document_type' => 'thesis_draft',
        'source_document_id' => $this->document->id, 'file_name' => 'original.pdf',
        'file_path' => $this->document->file_path]);
    Storage::disk('local')->put('sidang_ta/original.pdf', 'dummy');
    Storage::disk('local')->put('sidang_ta/unreferenced.pdf', 'keep orphan');
    $this->nonTaSnapshot = function () {
        $rows = [];
        foreach (Schema::getTableListing() as $table) {
            if (str_starts_with(Str::afterLast($table, '.'), 'sidang_ta_registration')) continue;
            $rows[$table] = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
        }
        return $rows;
    };
});

test('TA reset defaults to dry run and prints resolved database plus all counts without mutation', function () {
    $before = ($this->nonTaSnapshot)();
    $this->artisan('sidang-ta:reset-test-data')
        ->expectsOutput('Database: '.DB::connection()->getDatabaseName())
        ->expectsOutput('sidang_ta_registration_draft_documents: 1')
        ->expectsOutput('sidang_ta_registration_drafts: 1')
        ->expectsOutput('sidang_ta_registration_documents: 1')
        ->expectsOutput('sidang_ta_registration_logs: 1')
        ->expectsOutput('sidang_ta_registrations: 1')
        ->expectsOutput('Files referenced (unique): 1')->assertSuccessful();
    expect($this->registration->fresh())->not->toBeNull();
    expect($this->draft->fresh())->not->toBeNull();
    expect(($this->nonTaSnapshot)())->toBe($before);
    Storage::disk('local')->assertExists(['sidang_ta/original.pdf', 'sidang_ta/unreferenced.pdf']);
});

test('explicit TA reset clears only child-first TA data and owned files while preserving academic data and all other tables', function () {
    DB::table('student_taks')->insert(['student_number' => $this->student->student_number, 'total_points' => 80]);
    DB::table('student_skpis')->insert(['student_number' => $this->student->student_number, 'raw_data' => '{"ready":true}']);
    $before = ($this->nonTaSnapshot)();
    $this->artisan('sidang-ta:reset-test-data --execute')->assertSuccessful();
    foreach (['sidang_ta_registrations', 'sidang_ta_registration_drafts',
        'sidang_ta_registration_documents', 'sidang_ta_registration_draft_documents',
        'sidang_ta_registration_logs'] as $table) {
        $this->assertDatabaseCount($table, 0);
    }
    expect(($this->nonTaSnapshot)())->toBe($before);
    Storage::disk('local')->assertMissing('sidang_ta/original.pdf');
    Storage::disk('local')->assertExists('sidang_ta/unreferenced.pdf');
});

test('TA reset preserves a file shared with another module including soft deleted public documents', function () {
    $category = \App\Models\DocumentCategory::create(['name' => 'Shared', 'slug' => 'reset-shared']);
    $public = \App\Models\Document::withoutEvents(fn () => \App\Models\Document::create([
        'document_category_id' => $category->id, 'title' => 'Shared file', 'file_name' => 'original.pdf',
        'file_path' => 'sidang_ta/original.pdf', 'file_type' => 'pdf', 'file_size' => 5,
        'visibility' => 'publik']));
    // Keep the shared reference visible to raw queries even after soft deletion.
    DB::table('documents')->where('id', $public->id)->update(['deleted_at' => now()]);
    $this->artisan('sidang-ta:reset-test-data --execute')->assertSuccessful();
    Storage::disk('local')->assertExists('sidang_ta/original.pdf');
    expect(DB::table('documents')->where('id', $public->id)->exists())->toBeTrue();
});

test('TA reset preserves unsafe paths and only deletes files from removed metadata', function () {
    foreach (['documents/other.pdf', 'sidang_ta/../outside.pdf', 'sidang_ta//alias.pdf',
        'sidang_ta/./alias.pdf', 'sidang_ta\\backslash.pdf', '/sidang_ta/absolute.pdf'] as $index => $path) {
        $this->draft->documents()->create(['document_type' => 'unsafe_'.$index,
            'file_name' => 'unsafe.pdf', 'file_path' => $path]);
    }
    Storage::disk('local')->put('documents/other.pdf', 'other module');
    Storage::disk('local')->put('outside.pdf', 'outside');
    Storage::disk('local')->put('sidang_ta/alias.pdf', 'alias');
    $this->artisan('sidang-ta:reset-test-data --execute')->assertSuccessful();
    Storage::disk('local')->assertExists(['documents/other.pdf', 'outside.pdf',
        'sidang_ta/alias.pdf', 'sidang_ta/unreferenced.pdf']);
});

test('TA reset preserves files referenced by graduation document and predicate fields', function () {
    $graduation = DB::table('graduation_registrations')->insertGetId([
        'student_number' => $this->student->student_number, 'student_name' => 'Keep graduation',
        'graduation_period' => '2026', 'predicate_document_path' => 'sidang_ta/predicate.pdf']);
    DB::table('graduation_registration_documents')->insert([
        'graduation_registration_id' => $graduation, 'document_type' => 'thesis_draft',
        'file_name' => 'original.pdf', 'file_path' => 'sidang_ta/original.pdf']);
    $this->draft->documents()->create(['document_type' => 'predicate_fixture',
        'file_name' => 'predicate.pdf', 'file_path' => 'sidang_ta/predicate.pdf']);
    Storage::disk('local')->put('sidang_ta/predicate.pdf', 'graduation predicate');
    $this->artisan('sidang-ta:reset-test-data --execute')->assertSuccessful();
    Storage::disk('local')->assertExists(['sidang_ta/original.pdf', 'sidang_ta/predicate.pdf']);
    expect(DB::table('graduation_registrations')->where('id', $graduation)->exists())->toBeTrue();
});

test('TA reset preserves symlinks even if their targets are inside the TA folder', function () {
    $disk = Storage::disk('local');
    $link = $disk->path('sidang_ta/alias.pdf');
    symlink($disk->path('sidang_ta/unreferenced.pdf'), $link);
    $this->document->update(['file_path' => 'sidang_ta/alias.pdf']);
    $this->draft->documents()->update(['file_path' => 'sidang_ta/alias.pdf']);
    $this->artisan('sidang-ta:reset-test-data --execute')->assertSuccessful();
    expect(is_link($link))->toBeTrue();
    $disk->assertExists('sidang_ta/unreferenced.pdf');
});

test('TA reset preserves a graduation reference that spells the same file with a dot segment', function () {
    DB::table('graduation_registrations')->insert([
        'student_number' => $this->student->student_number, 'student_name' => 'Keep alias',
        'graduation_period' => '2026', 'predicate_document_path' => 'sidang_ta/./original.pdf']);
    $this->artisan('sidang-ta:reset-test-data --execute')->assertSuccessful();
    Storage::disk('local')->assertExists('sidang_ta/original.pdf');
});

test('TA reset rolls back every metadata deletion and preserves files if a database delete fails', function () {
    $failOnce = true;
    DB::listen(function (QueryExecuted $event) use (&$failOnce) {
        if ($failOnce && preg_match('/^delete from ["`]?sidang_ta_registrations["`]? /i', $event->sql)) {
            $failOnce = false;
            throw new RuntimeException('Injected delete failure');
        }
    });
    $this->artisan('sidang-ta:reset-test-data --execute')->assertFailed();
    expect($failOnce)->toBeFalse();
    expect($this->registration->fresh())->not->toBeNull();
    expect($this->draft->fresh())->not->toBeNull();
    expect($this->registration->documents()->count())->toBe(1);
    expect($this->registration->logs()->count())->toBe(1);
    expect($this->draft->documents()->count())->toBe(1);
    Storage::disk('local')->assertExists('sidang_ta/original.pdf');
});

test('TA reset is idempotent and subsequent executions report zero records and zero files', function () {
    $this->artisan('sidang-ta:reset-test-data --execute')->assertSuccessful();
    $this->artisan('sidang-ta:reset-test-data --execute')
        ->expectsOutput('sidang_ta_registration_draft_documents: 0')
        ->expectsOutput('sidang_ta_registration_drafts: 0')
        ->expectsOutput('sidang_ta_registration_documents: 0')
        ->expectsOutput('sidang_ta_registration_logs: 0')
        ->expectsOutput('sidang_ta_registrations: 0')
        ->expectsOutput('Files referenced (unique): 0')->assertSuccessful();
    Storage::disk('local')->assertExists('sidang_ta/unreferenced.pdf');
});

test('TA database defaults apply version two to raw future inserts without rewriting old dummy metadata', function () {
    $id = DB::table('sidang_ta_registrations')->insertGetId(['sidang_ta_period_id' => $this->period->id,
        'student_number' => $this->student->student_number, 'student_name' => 'Future insert',
        'student_phone' => '08123456789', 'parent_phone' => '08123456788',
        'graduation_payment_date' => today()->toDateString(), 'diploma_biodata_accurate' => true]);
    $draftId = (string) Str::uuid();
    DB::table('sidang_ta_registration_drafts')->insert(['id' => $draftId, 'owner_user_id' => $this->user->id,
        'student_number' => $this->student->student_number, 'sidang_ta_period_id' => $this->period->id,
        'payload' => '{}']);
    expect((int) DB::table('sidang_ta_registrations')->where('id', $id)->value('requirements_version'))->toBe(2);
    expect((int) DB::table('sidang_ta_registration_drafts')->where('id', $draftId)->value('requirements_version'))->toBe(2);
    expect($this->registration->fresh()->requirements_version)->toBe(1);
    expect($this->draft->fresh()->requirements_version)->toBe(1);
    expect((new SidangTaRegistration())->requirements_version)->toBe(2);
    expect((new SidangTaRegistrationDraft())->requirements_version)->toBe(2);
});
```

### `tests/Feature/SidangTaReviewExportTest.php`

```php
<?php

use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\Student;
use App\Models\User;
use App\Services\SidangTaReviewExport;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Rap2hpoutre\FastExcel\FastExcel;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

beforeEach(function () {
    $this->travelTo(now()->startOfSecond());
    Storage::fake('local');
    $this->temporaryExports = [];
    foreach (['sidang_ta_registration_review', 'sidang_ta_registration_read', 'sidang_ta_period_read'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->reviewer = User::factory()->create(['username' => 'export-reviewer', 'email' => 'export-reviewer@test.local',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
    $this->reviewer->givePermissionTo('sidang_ta_registration_review');
    $this->actingAs($this->reviewer);
    $this->a = SidangTaPeriod::create(['name' => 'Export A', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay()]);
    $this->b = SidangTaPeriod::create(['name' => 'Export B', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDays(2)]);
    $this->sequence = 0;
    $this->makeRegistration = function (array $overrides = []) {
        $this->sequence++;
        $overrides['academic_snapshot'] = array_replace_recursive(
            ['student_name' => 'Nama Historis', 'gpa' => 3.25, 'passed_credits' => 140,
                'admission' => ['eprt' => ['mode' => 'automatic']]], $overrides['academic_snapshot'] ?? []
        );
        return SidangTaRegistration::create(array_replace([
            'sidang_ta_period_id' => $this->a->id, 'student_number' => '0000'.str_pad((string) $this->sequence, 6, '0', STR_PAD_LEFT),
            'student_name' => 'Export '.$this->sequence, 'study_program' => 'Informatika',
            'student_phone' => '081234567890', 'parent_phone' => '081234567891',
            'graduation_payment_date' => today()->toDateString(), 'diploma_biodata_accurate' => true,
            'status' => 'submitted', 'submitted_at' => now(),
            'academic_snapshot' => ['student_name' => 'Nama Historis', 'gpa' => 3.25, 'passed_credits' => 140],
        ], $overrides))->fresh();
    };
    $this->attach = function ($registration, string $type, string $status = 'pending', bool $exists = true, ?string $notes = null) {
        $path = "sidang_ta/export-test/{$registration->id}/{$type}.pdf";
        if ($exists) Storage::disk('local')->put($path, '%PDF-export');
        return $registration->documents()->create(['document_type' => $type, 'file_name' => $type.'.pdf',
            'file_path' => $path, 'file_type' => 'pdf', 'file_size' => 11, 'status' => $status, 'notes' => $notes]);
    };
    $this->exportRows = function (array $filters = []) {
        $response = $this->get(route('admin.sidang-ta-registrations.export', $filters))->assertOk()
            ->assertDownload('Sidang_TA_'.now()->format('Y-m-d_H-i-s').'.xlsx');
        expect($response->baseResponse)->toBeInstanceOf(BinaryFileResponse::class);
        $path = $response->baseResponse->getFile()->getPathname();
        $this->temporaryExports[] = $path;
        return [$response, (new FastExcel)->import($path), $path];
    };
});

afterEach(function () {
    foreach ($this->temporaryExports as $path) if (is_file($path)) @unlink($path);
    $this->travelBack();
});

test('p2b_exports_all_filtered_rows_beyond_page_with_same_filter_counts', function () {
    $ids = [];
    foreach (range(1, 20) as $i) $ids[] = ($this->makeRegistration)(['student_name' => 'Target '.$i])->id;
    ($this->makeRegistration)(['student_name' => 'Target periode lain', 'sidang_ta_period_id' => $this->b->id]);
    ($this->makeRegistration)(['student_name' => 'Target prodi lain', 'study_program' => 'Sistem Informasi']);
    ($this->makeRegistration)(['student_name' => 'Target status lain', 'status' => 'approved']);
    $filters = ['search' => 'Target', 'period' => $this->a->id, 'status' => 'submitted', 'study_program' => 'Informatika'];
    $listing = $this->get(route('admin.sidang-ta-registrations.index', $filters + ['page' => 2]))->assertOk();
    expect($listing->viewData('counts')['total'])->toBe(20);
    [, $rows] = ($this->exportRows)($filters + ['page' => 2]);
    expect($rows)->toHaveCount(20);
    expect($rows->pluck('ID Pengajuan')->all())->toBe(array_map('strval', array_reverse($ids)));
    expect($rows->pluck('Status Pengajuan')->unique()->all())->toBe(['Menunggu peninjauan']);
});

test('p2b_export_keeps_historical_snapshot_after_live_student_changes', function () {
    $student = Student::create(['student_number' => '00123456', 'name' => 'Nama Awal', 'email' => 'snapshot-export@test.local',
        'academic_year' => 2022, 'study_program' => 'Informatika', 'status' => 'Aktif', 'gpa' => 3.25]);
    ($this->makeRegistration)(['student_number' => $student->student_number, 'student_name' => 'Nama Pengajuan',
        'academic_snapshot' => ['student_name' => 'Nama Snapshot', 'study_program' => 'Prodi Snapshot',
            'gpa' => 0, 'passed_credits' => 0, 'tak_score' => null, 'bpp_status' => false]]);
    $student->update(['name' => 'Nama Terbaru', 'study_program' => 'Prodi Terbaru', 'gpa' => 4]);
    [, $rows] = ($this->exportRows)();
    $row = $rows->first();
    expect($row['Nama Mahasiswa'])->toBe('Nama Pengajuan');
    expect($row['Snapshot Nama'])->toBe('Nama Snapshot');
    expect($row['Snapshot Prodi'])->toBe('Prodi Snapshot');
    expect($row['IPK'])->toBe(0);
    expect($row['SKS Lulus'])->toBe(0);
    expect($row['TAK'])->toBe('Belum tersedia');
    expect($row['Status BPP'])->toBe('Tidak');
    expect(array_values($row))->not->toContain('Nama Terbaru')->not->toContain('Prodi Terbaru');
});

test('p2b_xlsx_preserves_text_ids_phones_and_literal_formula_like_content', function () {
    $registration = ($this->makeRegistration)(['student_number' => '001234567890123456', 'student_name' => '=2+2',
        'student_phone' => '001234567890', 'parent_phone' => '000987654321',
        'review_notes' => '=HYPERLINK("https://example.invalid","x")',
        'academic_snapshot' => ['gpa' => '=1+1', 'ta_title' => '=SUM(1,2)']]);
    ($this->attach)($registration, 'thesis_draft', 'rejected', true, '=SUM(2,3)');
    [, $rows, $path] = ($this->exportRows)();
    $row = $rows->first();
    expect($row)->toHaveCount(57);
    expect($row['NIM'])->toBe('001234567890123456');
    expect($row['HP Mahasiswa'])->toBe('001234567890');
    expect($row['HP Orang Tua/Wali'])->toBe('000987654321');
    $zip = new ZipArchive;
    expect($zip->open($path))->toBeTrue();
    try { $xml = $zip->getFromName('xl/worksheets/sheet1.xml'); } finally { $zip->close(); }
    $document = new DOMDocument;
    expect($document->loadXML($xml, LIBXML_NONET))->toBeTrue();
    $xpath = new DOMXPath($document);
    expect($xpath->query('//*[local-name()="f"]')->length)->toBe(0);
    // Reader OpenSpout dapat menafsirkan teks berawalan '=' sebagai FormulaCell.
    // Periksa literal langsung dari inlineStr pada XLSX, bukan computed value reader.
    $textCell = function (string $column) use ($xpath): string {
        foreach ($xpath->query('//*[local-name()="row" and @r="1"]/*[local-name()="c"]') as $header) {
            if ($xpath->evaluate('string(.//*[local-name()="t"])', $header) === $column) {
                $address = preg_replace('/\d+$/', '2', $header->getAttribute('r'));
                return $xpath->evaluate('string(//*[local-name()="c" and @r="'.$address.'"]/*[local-name()="is"]/*[local-name()="t"])');
            }
        }
        throw new RuntimeException('Kolom tidak ditemukan: '.$column);
    };
    expect($textCell('Nama Mahasiswa'))->toBe('=2+2');
    expect($textCell('Catatan Petugas'))->toBe('=HYPERLINK("https://example.invalid","x")');
    expect($textCell('IPK'))->toBe('=1+1');
    expect($textCell('Judul TA Indonesia'))->toBe('=SUM(1,2)');
    expect($textCell(SidangTaRegistration::DOCUMENT_TYPES['thesis_draft'].' — Catatan'))->toBe('=SUM(2,3)');
    foreach (['C2', 'F2', 'G2'] as $cell) {
        $node = $xpath->query('//*[local-name()="c" and @r="'.$cell.'"]')->item(0);
        expect(in_array($node->getAttribute('t'), ['inlineStr', 's'], true))->toBeTrue();
    }
});

test('p2b_export_document_columns_distinguish_na_missing_and_unavailable', function () {
    $accurate = ($this->makeRegistration)();
    $pdm = ($this->makeRegistration)(['diploma_biodata_accurate' => false]);
    ($this->attach)($accurate, 'thesis_draft', 'verified', false);
    ($this->attach)($accurate, 'pdm_submission_proof', 'verified', true, 'Cabang lama');
    ($this->attach)($pdm, 'pdm_submission_proof', 'rejected', true, 'Perbaiki PDM');
    [, $rows] = ($this->exportRows)();
    $a = $rows->firstWhere('ID Pengajuan', (string) $accurate->id);
    $b = $rows->firstWhere('ID Pengajuan', (string) $pdm->id);
    $thesis = SidangTaRegistration::DOCUMENT_TYPES['thesis_draft'];
    $capture = SidangTaRegistration::DOCUMENT_TYPES['ta_data_capture'];
    $pdmLabel = SidangTaRegistration::DOCUMENT_TYPES['pdm_submission_proof'];
    expect($a[$thesis.' — Status'])->toBe('Terverifikasi');
    expect($a[$thesis.' — Ketersediaan'])->toBe('Tidak ditemukan');
    expect($a[$capture.' — Status'])->toBe('Belum ada dokumen');
    expect($a[$capture.' — Ketersediaan'])->toBe('Tidak ada metadata');
    foreach (['Status', 'Ketersediaan', 'Catatan'] as $column) expect($a[$pdmLabel.' — '.$column])->toBe('N/A');
    expect($b[$pdmLabel.' — Status'])->toBe('Perlu perbaikan');
    expect($b[$pdmLabel.' — Catatan'])->toBe('Perbaiki PDM');
    foreach (['diploma_statement', 'diploma_biodata_form'] as $type) {
        expect($b[SidangTaRegistration::DOCUMENT_TYPES[$type].' — Status'])->toBe('N/A');
    }
});

test('p2b_empty_export_is_disabled_in_ui_and_direct_request_is_rejected', function () {
    $crawler = new Crawler($this->get(route('admin.sidang-ta-registrations.index'))->assertOk()->getContent());
    expect($crawler->filter('[data-ta-export]')->count())->toBe(0);
    expect($crawler->filter('[data-ta-export-empty][disabled]')->count())->toBe(1);
    $this->getJson(route('admin.sidang-ta-registrations.export'))->assertUnprocessable()->assertJsonValidationErrors('export');
});

test('p2b_period_only_and_student_read_without_review_cannot_export', function () {
    ($this->makeRegistration)();
    $queries = [];
    DB::listen(function (QueryExecuted $event) use (&$queries) {
        if (str_contains(strtolower($event->sql), 'sidang_ta_registrations')) $queries[] = $event->sql;
    });
    foreach (['sidang_ta_period_read', 'sidang_ta_registration_read'] as $permission) {
        $user = User::factory()->create(['status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
        $user->givePermissionTo($permission);
        $this->actingAs($user)->get(route('admin.sidang-ta-registrations.export'))->assertForbidden();
        if ($permission === 'sidang_ta_period_read') {
            $crawler = new Crawler($this->get(route('admin.sidang-ta-registrations.index'))->assertOk()->getContent());
            expect($crawler->filter('[data-ta-export], [data-ta-export-empty]')->count())->toBe(0);
        }
    }
    expect($queries)->toBe([]);
});

test('p2b_export_uses_same_invalid_filter_validation_as_listing', function () {
    foreach ([['status' => 'bad'], ['period' => 999999], ['search' => ['bad']],
                 ['study_program' => str_repeat('x', 256)], ['page' => 0]] as $filters) {
        $this->getJson(route('admin.sidang-ta-registrations.export', $filters))->assertUnprocessable();
    }
});

test('p2b_export_link_keeps_applied_filters_without_current_page', function () {
    foreach (range(1, 16) as $i) ($this->makeRegistration)(['student_name' => 'Tautan '.$i]);
    $filters = ['search' => 'Tautan', 'period' => $this->a->id, 'status' => 'submitted', 'study_program' => 'Informatika'];
    $crawler = new Crawler($this->get(route('admin.sidang-ta-registrations.index', $filters + ['page' => 2]))->assertOk()->getContent());
    $href = $crawler->filter('[data-ta-export]')->attr('href');
    expect($href)->toBe(route('admin.sidang-ta-registrations.export', $filters));
    parse_str(parse_url($href, PHP_URL_QUERY), $query);
    expect(array_key_exists('page', $query))->toBeFalse();
});

test('p2b_export_omits_private_paths_urls_and_unknown_snapshot_fields', function () {
    $registration = ($this->makeRegistration)(['academic_snapshot' => [
        'file_path' => 'private-secret-snapshot-path', 'gpa' => null,
    ]]);
    $document = ($this->attach)($registration, 'thesis_draft');
    [, $rows] = ($this->exportRows)();
    $row = $rows->first();
    expect($row['IPK'])->toBe('Belum tersedia');
    expect(implode('|', array_keys($row)))->not->toContain('file_path')->not->toContain('download_url');
    expect(implode('|', array_values($row)))->not->toContain($document->file_path)
        ->not->toContain('private-secret-snapshot-path')->not->toContain('/storage/');
});

test('p2b_private_attachment_is_deleted_after_send_without_mutating_registration', function () {
    $registration = ($this->makeRegistration)();
    [$response, , $path] = ($this->exportRows)();
    $response->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    expect($response->headers->get('Cache-Control'))->toContain('private')->toContain('no-store');
    expect($response->headers->hasCacheControlDirective('public'))->toBeFalse();
    expect($response->baseResponse->shouldDeleteFileAfterSend())->toBeTrue();
    ob_start();
    try { $response->baseResponse->sendContent(); $bytes = ob_get_contents(); } finally { ob_end_clean(); }
    expect($bytes)->toStartWith('PK');
    expect(is_file($path))->toBeFalse();
    expect($registration->fresh()->status)->toBe('submitted');
    expect($registration->fresh()->lock_version)->toBe(1);
    expect($registration->logs()->count())->toBe(0);
});
```

### `tests/Feature/SidangTaReviewListTest.php`

```php
<?php

use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\User;
use App\Support\SidangTaDocumentRequirements as Requirements;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\DomCrawler\Crawler;

beforeEach(function () {
    $this->travelTo(now()->startOfSecond());
    Storage::fake('local');
    foreach (['sidang_ta_registration_review', 'sidang_ta_period_read'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->reviewer = User::factory()->create(['username' => 'p2a-reviewer', 'email' => 'p2a-reviewer@test.local',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
    $this->reviewer->givePermissionTo('sidang_ta_registration_review');
    $this->actingAs($this->reviewer);
    $this->a = SidangTaPeriod::create(['name' => 'P2a A', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay()]);
    $this->b = SidangTaPeriod::create(['name' => 'P2a B', 'is_active' => true,
        'open_at' => now()->addHour(), 'close_at' => now()->addDays(2)]);
    $this->sequence = 0;
    $this->makeRegistration = function (array $overrides = []) {
        $this->sequence++;
        $overrides['academic_snapshot'] = array_replace_recursive(
            ['admission' => ['eprt' => ['mode' => 'automatic']]], $overrides['academic_snapshot'] ?? []
        );
        return SidangTaRegistration::create(array_replace([
            'sidang_ta_period_id' => $this->a->id, 'student_number' => 'P2A'.str_pad((string) $this->sequence, 6, '0', STR_PAD_LEFT),
            'student_name' => 'Mahasiswa '.$this->sequence, 'study_program' => 'Informatika',
            'student_phone' => '081234567890', 'parent_phone' => '081234567891',
            'graduation_payment_date' => today()->toDateString(), 'diploma_biodata_accurate' => true,
            'status' => 'submitted', 'submitted_at' => now(),
        ], $overrides))->fresh();
    };
    $this->attach = function ($registration, string $type, string $status = 'pending', bool $exists = true) {
        $path = "sidang_ta/p2a/{$registration->id}/{$type}.pdf";
        if ($exists) Storage::disk('local')->put($path, '%PDF-1.4 preview');
        return $registration->documents()->create(['document_type' => $type, 'file_name' => $type.'.pdf',
            'file_path' => $path, 'file_type' => 'pdf', 'file_size' => 16, 'status' => $status]);
    };
    $this->listing = function (array $filters = []) {
        return $this->get(route('admin.sidang-ta-registrations.index', $filters))->assertOk();
    };
});

afterEach(function () { $this->travelBack(); });

test('p2a_all_status_cards_match_full_unfiltered_result', function () {
    foreach (['submitted', 'needs_review', 'approved', 'rejected'] as $status) {
        ($this->makeRegistration)(['status' => $status]);
    }
    $response = ($this->listing)();
    $response->assertViewHas('counts', ['total' => 4, 'submitted' => 1, 'needs_review' => 1, 'approved' => 1, 'rejected' => 1]);
    $crawler = new Crawler($response->getContent());
    expect($crawler->filter('[data-ta-stat]')->count())->toBe(5);
    expect($crawler->filter('[data-ta-stat="total"]')->text())->toBe('4');
    expect($crawler->filter('[data-ta-row]')->count())->toBe(4);
});

test('p2a_combined_filters_group_name_nim_search_without_leaking_other_matches', function () {
    $target = ($this->makeRegistration)(['student_name' => 'Target Cocok', 'status' => 'approved']);
    ($this->makeRegistration)(['student_number' => 'Target-B', 'sidang_ta_period_id' => $this->b->id, 'status' => 'approved']);
    ($this->makeRegistration)(['student_number' => 'Target-Prodi', 'study_program' => 'Sistem Informasi', 'status' => 'approved']);
    ($this->makeRegistration)(['student_name' => 'Target Pending']);
    $response = ($this->listing)(['search' => 'Target', 'period' => $this->a->id, 'status' => 'approved', 'study_program' => 'Informatika']);
    $response->assertViewHas('registrations', fn ($rows) => $rows->total() === 1 && $rows->first()->id === $target->id)
        ->assertViewHas('counts', ['total' => 1, 'submitted' => 0, 'needs_review' => 0, 'approved' => 1, 'rejected' => 0]);
});

test('p2a_nim_search_and_zero_are_preserved', function () {
    $target = ($this->makeRegistration)(['student_number' => '123450', 'student_name' => 'Nama khusus']);
    ($this->makeRegistration)(['student_number' => '777777', 'student_name' => 'Nama lain']);
    ($this->listing)(['search' => '12345'])->assertViewHas('registrations', fn ($rows) => $rows->total() === 1 && $rows->first()->id === $target->id);
    ($this->listing)(['search' => '0'])->assertViewHas('filters', fn ($filters) => $filters['search'] === '0')
        ->assertViewHas('counts', fn ($counts) => $counts['total'] === 1);
});

test('p2a_invalid_filter_values_return_validation_errors', function () {
    foreach ([[['period' => 999999], 'period'], [['status' => 'unknown'], 'status'],
                 [['search' => ['bad']], 'search'], [['study_program' => ['bad']], 'study_program'],
                 [['search' => str_repeat('x', 101)], 'search'], [['page' => 0], 'page']] as [$filters, $field]) {
        $this->getJson(route('admin.sidang-ta-registrations.index', $filters))
            ->assertUnprocessable()->assertJsonValidationErrors($field);
    }
});

test('p2a_pagination_keeps_all_filters_and_counts_all_matching_rows', function () {
    foreach (range(1, 20) as $i) ($this->makeRegistration)(['student_name' => 'Pagination '.$i]);
    ($this->makeRegistration)(['student_name' => 'Pagination lain', 'study_program' => 'Sistem Informasi']);
    $filters = ['search' => 'Pagination', 'period' => $this->a->id, 'status' => 'submitted', 'study_program' => 'Informatika'];
    $response = ($this->listing)($filters)->assertViewHas('counts', fn ($counts) => $counts['total'] === 20)
        ->assertViewHas('registrations', fn ($rows) => $rows->count() === 15 && $rows->total() === 20);
    $rows = $response->viewData('registrations');
    parse_str(parse_url($rows->nextPageUrl(), PHP_URL_QUERY), $query);
    foreach ($filters as $key => $value) expect((string) $query[$key])->toBe((string) $value);
    expect((string) $query['page'])->toBe('2');
    ($this->listing)($filters + ['page' => 2])->assertViewHas('registrations', fn ($page) => $page->count() === 5)
        ->assertViewHas('counts', fn ($counts) => $counts['total'] === 20);
    $crawler = new Crawler($response->getContent());
    expect($crawler->filter('[data-ta-reset]')->attr('href'))->toBe(route('admin.sidang-ta-registrations.index'));
    $crawler = new Crawler(($this->listing)($filters + ['page' => 999])->getContent());
    expect($crawler->filter('[data-ta-empty]')->text(null, true))->toContain('Halaman ini tidak berisi pengajuan.');
    expect($crawler->filter('[data-ta-first-page]')->attr('href'))->toBe(route('admin.sidang-ta-registrations.index', $filters));
});

test('p2a_banner_lists_all_active_periods_and_selected_inactive_period', function () {
    $inactive = SidangTaPeriod::create(['name' => 'Nonaktif', 'is_active' => false,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay()]);
    $closed = SidangTaPeriod::create(['name' => 'Aktif tutup', 'is_active' => true,
        'open_at' => now()->subDays(2), 'close_at' => now()->subDay()]);
    $crawler = new Crawler(($this->listing)()->getContent());
    expect($crawler->filter('[data-ta-period-banner]')->count())->toBe(3);
    expect($crawler->filter('[data-ta-period-banner="'.$this->a->id.'"]')->text(null, true))->toContain('Sedang terbuka');
    expect($crawler->filter('[data-ta-period-banner="'.$this->b->id.'"]')->text(null, true))->toContain('Belum dibuka');
    expect($crawler->filter('[data-ta-period-banner="'.$closed->id.'"]')->text(null, true))->toContain('Ditutup');
    $crawler = new Crawler(($this->listing)(['period' => $inactive->id])->getContent());
    expect($crawler->filter('[data-ta-period-banner]')->count())->toBe(1);
    expect($crawler->filter('[data-ta-period-banner="'.$inactive->id.'"]')->text(null, true))->toContain('Tidak aktif');
});

test('p2a_no_active_period_and_empty_vs_filtered_empty_are_distinct', function () {
    SidangTaPeriod::query()->update(['is_active' => false]);
    $crawler = new Crawler(($this->listing)()->getContent());
    expect($crawler->filter('[data-ta-no-period]')->count())->toBe(1);
    expect($crawler->filter('[data-ta-empty]')->text(null, true))->toBe('Belum ada pengajuan Sidang TA.');
    $crawler = new Crawler(($this->listing)(['search' => 'Tidak Ada'])->getContent());
    expect($crawler->filter('[data-ta-empty]')->text(null, true))->toBe('Tidak ada pengajuan yang cocok dengan filter.');
});

test('p2a_document_matrix_shows_only_current_branch_and_distinguishes_all_review_states', function () {
    $accurate = ($this->makeRegistration)();
    $pdm = ($this->makeRegistration)(['diploma_biodata_accurate' => false]);
    ($this->attach)($accurate, Requirements::CAPTURES[0], 'verified');
    ($this->attach)($accurate, Requirements::CAPTURES[1], 'rejected');
    ($this->attach)($accurate, Requirements::PAYMENT[0]);
    ($this->attach)($accurate, Requirements::PAYMENT[1], 'verified', false);
    ($this->attach)($accurate, Requirements::PDM[0], 'verified');
    ($this->attach)($pdm, Requirements::PDM[0], 'verified');
    ($this->attach)($pdm, Requirements::DIPLOMA[0], 'verified');
    $crawler = new Crawler(($this->listing)()->getContent());
    $row = $crawler->filter('[data-ta-row="'.$accurate->id.'"]');
    expect($row->filter('[data-ta-doc]')->count())->toBe(7);
    foreach ([Requirements::CAPTURES[0] => 'verified', Requirements::CAPTURES[1] => 'rejected',
                 Requirements::PAYMENT[0] => 'pending', Requirements::PAYMENT[1] => 'unavailable',
                 Requirements::PAYMENT[2] => 'missing'] as $type => $state) {
        expect($row->filter('[data-ta-doc="'.$type.'"]')->attr('data-doc-state'))->toBe($state);
    }
    expect($row->filter('a[data-ta-doc="'.Requirements::PAYMENT[1].'"]')->count())->toBe(0);
    expect($row->filter('[data-ta-doc="'.Requirements::PDM[0].'"]')->count())->toBe(0);
    $row = $crawler->filter('[data-ta-row="'.$pdm->id.'"]');
    expect($row->filter('[data-ta-doc]')->count())->toBe(6);
    expect($row->filter('[data-ta-doc="'.Requirements::DIPLOMA[0].'"], [data-ta-doc="'.Requirements::DIPLOMA[1].'"]')->count())->toBe(0);
    expect($row->filter('a[data-ta-doc="'.Requirements::PDM[0].'"]')->count())->toBe(1);
});

test('p2a_approved_registration_does_not_turn_pending_document_green', function () {
    $registration = ($this->makeRegistration)(['status' => 'approved']);
    ($this->attach)($registration, Requirements::CAPTURES[0]);
    $crawler = new Crawler(($this->listing)()->getContent());
    expect($crawler->filter('[data-ta-row="'.$registration->id.'"] [data-ta-doc="'.Requirements::CAPTURES[0].'"]')->attr('data-doc-state'))->toBe('pending');
});

test('p2a_reviewer_without_student_read_uses_private_modal_links_and_detail', function () {
    $registration = ($this->makeRegistration)();
    $document = ($this->attach)($registration, Requirements::CAPTURES[0]);
    $document->update(['file_name' => 'nama " <img src=x>.pdf']);
    $crawler = new Crawler(($this->listing)()->getContent());
    $link = $crawler->filter('a[data-ta-doc="'.Requirements::CAPTURES[0].'"]');
    expect($link->attr('href'))->toBe(route('sidang-ta-registration-documents.preview', $document));
    expect($link->attr('data-file-name'))->toBe('nama " <img src=x>.pdf');
    expect($crawler->filter('[data-ta-row] img')->count())->toBe(0);
    expect($crawler->filter('[data-document-preview-modal]')->count())->toBe(1);
    $this->get($link->attr('href'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->get(route('admin.sidang-ta-registrations.show', $registration))->assertOk();
    expect($this->reviewer->can('sidang_ta_registration_read'))->toBeFalse();
});

test('p2a_period_only_still_has_no_registration_stats_program_query_or_preview', function () {
    ($this->makeRegistration)(['student_name' => 'Rahasia P2a']);
    $user = User::factory()->create(['username' => 'period-p2a', 'email' => 'period-p2a@test.local',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
    $user->givePermissionTo('sidang_ta_period_read');
    $queries = [];
    DB::listen(function (QueryExecuted $event) use (&$queries) {
        if (str_contains(strtolower($event->sql), 'sidang_ta_registrations')) $queries[] = $event->sql;
    });
    $response = $this->actingAs($user)->get(route('admin.sidang-ta-registrations.index', ['status' => 'invalid']))
        ->assertOk()->assertViewHas('registrations', fn ($value) => $value === null)
        ->assertViewHas('counts', fn ($value) => $value === null)
        ->assertViewHas('studyPrograms', fn ($value) => $value->isEmpty())
        ->assertDontSee('Rahasia P2a')->assertDontSee('Ringkasan hasil filter');
    expect($queries)->toBe([]);
    $crawler = new Crawler($response->getContent());
    expect($crawler->filter('[data-document-preview-modal]')->count())->toBe(0);
    expect($crawler->filter('[data-ta-period-only]')->count())->toBe(1);
});

test('p2a_detail_uses_historical_snapshot_and_handles_missing_file_safely', function () {
    $registration = ($this->makeRegistration)(['academic_snapshot' => ['student_name' => 'Nama Historis', 'gpa' => 0,
        'custom_field' => '<b>literal</b>']]);
    $document = ($this->attach)($registration, Requirements::CAPTURES[0], 'rejected', false);
    $document->update(['notes' => '<img src=x> perbaiki']);
    $html = $this->get(route('admin.sidang-ta-registrations.show', $registration))->assertOk()->getContent();
    expect(strtolower(ltrim($html)))->toStartWith('<!doctype html>');
    $crawler = new Crawler($html);
    expect($crawler->filter('[data-academic-detail]')->count())->toBe(1);
    expect($crawler->filter('[data-academic-detail]')->text(null, true))->toContain('Nama Historis')->toContain('0,00');
    $card = $crawler->filter('[data-ta-detail-document="'.$document->id.'"]');
    expect($card->text(null, true))->toContain('Berkas tidak ditemukan.')->toContain('<img src=x> perbaiki');
    expect($card->filter('img')->count())->toBe(0);
    expect($card->filter('a[data-document-preview]')->count())->toBe(0);
    expect($crawler->filter('body [data-document-preview-modal]')->count())->toBe(1);
    expect($crawler->filter('[data-ta-decision] input[name="registration_version"]')->attr('value'))->toBe((string) $registration->lock_version);
});

test('p2a_list_eager_loads_all_page_documents_in_one_query', function () {
    foreach (range(1, 15) as $i) {
        $registration = ($this->makeRegistration)();
        ($this->attach)($registration, Requirements::CAPTURES[0]);
    }
    $queries = [];
    DB::listen(function (QueryExecuted $event) use (&$queries) {
        if (str_contains(strtolower($event->sql), 'sidang_ta_registration_documents')) $queries[] = $event->sql;
    });
    ($this->listing)()->assertViewHas('registrations', fn ($rows) => $rows->every(fn ($row) => $row->relationLoaded('documents')));
    expect($queries)->toHaveCount(1);
});

test('p2a_unknown_program_filter_remains_visible_and_escaped_with_zero_results', function () {
    ($this->makeRegistration)(['study_program' => 'Prodi Historis']);
    $value = '<b>Prodi tidak tersedia</b>';
    $response = ($this->listing)(['study_program' => $value])->assertViewHas('counts', fn ($counts) => $counts['total'] === 0);
    $crawler = new Crawler($response->getContent());
    expect($crawler->filter('select[name="study_program"] option[selected]')->attr('value'))->toBe($value);
    expect($crawler->filter('select[name="study_program"] b')->count())->toBe(0);
    expect($crawler->filter('select[name="study_program"]')->text(null, true))->toContain('Prodi Historis');
});
```

### `tests/Feature/SidangTaReviewUiTest.php`

```php
<?php

use App\Models\AcademicPeriod;
use App\Models\Course;
use App\Models\StudentCourse;
use App\Services\StudentAcademicProfileImportService;
use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDraft;
use App\Models\Student;
use App\Models\User;
use App\Support\SidangTaDocumentRequirements as Requirements;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\DomCrawler\Crawler;

beforeEach(function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-11-04 12:00:00', config('app.timezone')));
    Storage::fake('local');
    $this->user = User::factory()->create([
        'username' => '1202200001', 'email' => 'review-ui@mahasiswa.test',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false,
    ]);
    $this->student = Student::create([
        'student_number' => '1202200001', 'name' => 'Review UI Test',
        'email' => $this->user->email, 'academic_year' => 2022,
        'study_program' => 'Informatika', 'status' => 'Aktif',
    ]);
    foreach (['read', 'create', 'update'] as $action) {
        $this->user->givePermissionTo(Permission::firstOrCreate([
            'name' => "sidang_ta_registration_{$action}", 'guard_name' => 'web',
        ]));
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->period = SidangTaPeriod::create([
        'name' => 'Periode Review UI', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay(),
    ]);
    $this->actingAs($this->user);
    AcademicPeriod::query()->update(['is_active' => false]);
    $academic = AcademicPeriod::create(['year' => '2026/2027', 'semester' => 1, 'label' => 'Meet 61 KRS',
        'start_date' => '2026-08-01', 'end_date' => '2027-01-31', 'is_active' => true]);
    Course::create(['course_code' => 'MEET61-FINAL', 'name' => 'TUGAS AKHIR 2', 'academic_year' => '2026/2027',
        'semester_term' => 'GANJIL', 'study_program' => 'Informatika', 'class' => 'A', 'semester' => 8, 'credits' => 4]);
    StudentCourse::create(['student_number' => $this->student->student_number, 'course_code' => 'MEET61-FINAL',
        'course_name' => 'TUGAS AKHIR 2', 'course_class' => 'A', 'academic_year' => '2026/2027', 'semester' => 8,
        'academic_period_id' => $academic->id]);
    $row = json_decode(file_get_contents(base_path('docs/fixtures/sidang-ta/03-profil-akademik-dummy.json')),
        true, 512, JSON_THROW_ON_ERROR)['data'][0];
    $row['nim'] = $this->student->student_number;
    $row['nama'] = $this->student->name;
    $result = app(StudentAcademicProfileImportService::class)->import([$row]);
    expect($result['errors'])->toBe([]);
    $this->revisionSource = function () {
        $source = SidangTaRegistration::create([
            'sidang_ta_period_id' => $this->period->id,
            'student_number' => $this->student->student_number, 'student_name' => $this->student->name,
            'study_program' => $this->student->study_program, 'requirements_version' => 2,
            'academic_snapshot' => ['admission' => ['eprt' => ['mode' => 'automatic']]],
            'student_phone' => '081234567890', 'parent_phone' => '081234567891',
            'graduation_payment_date' => today()->toDateString(), 'diploma_biodata_accurate' => true,
            'status' => 'needs_review', 'submitted_by' => $this->user->id,
            'submitted_at' => now()->subHour(), 'reviewed_by' => $this->user->id,
            'reviewed_at' => now(), 'review_notes' => 'Perbaiki dokumen.',
        ]);
        $source->forceFill(['lock_version' => 2])->save();
        expect($source->fresh()->lock_version)->toBe(2);
        foreach (array_merge(Requirements::CAPTURES, Requirements::PAYMENT, Requirements::DIPLOMA) as $index => $type) {
            $path = 'sidang_ta/registrations/'.$source->id.'/'.$type.'.pdf';
            Storage::disk('local')->put($path, '%PDF-1.4 source final');
            $source->documents()->create(['document_type' => $type, 'file_name' => $type.'.pdf',
                'file_path' => $path, 'file_type' => 'pdf', 'file_size' => 25, 'status' => $index === 0 ? 'rejected' : 'verified',
                'notes' => $index === 0 ? 'Ganti capture.' : null]);
        }
        $source->logAction('submitted', null, 'submitted', 'Pendaftaran awal.', $this->user->id);
        $source->logAction('needs_review', 'submitted', 'needs_review', 'Perbaiki dokumen.', $this->user->id);
        return $source->fresh();
    };
    $this->reviewedDraft = function (bool $accurate = true) {
        $id = $this->postJson(route('sidang-ta-drafts.store'), [
            'sidang_ta_period_id' => $this->period->id,
            'student_phone' => '081234567890', 'parent_phone' => '081234567891',
        ])->assertCreated()->json('data.id');
        $draft = SidangTaRegistrationDraft::findOrFail($id);
        expect($draft->requirements_version)->toBe(2);
        foreach ([2, 3, 4] as $step) {
            $data = ['version' => $draft->fresh()->version];
            if ($step === 3) $data['graduation_payment_date'] = today()->toDateString();
            if ($step === 4) $data['diploma_biodata_accurate'] = $accurate;
            foreach (Requirements::typesForStep($step, $accurate) as $type) {
                $data['documents'][$type] = \Illuminate\Http\UploadedFile::fake()
                    ->create($type.'.pdf', 100, 'application/pdf');
            }
            $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => $step]), $data)->assertOk();
        }
        $draft = $draft->fresh();
        expect($draft->documents()->count())->toBe($accurate ? 7 : 6);
        $this->postJson(route('sidang-ta-drafts.review', $draft), ['version' => $draft->version])->assertOk();
        return $draft->fresh();
    };
});

afterEach(function () { $this->travelBack(); });

test('review_summary_and_download_buttons_do_not_submit_registration', function () {
    $draft = ($this->reviewedDraft)();
    $response = $this->get(route('sidang-ta-drafts.preview', $draft))->assertOk();
    $crawler = new Crawler($response->getContent());
    $summary = $crawler->filter('[data-review-summary]');
    expect($summary->count())->toBe(1);
    expect($summary->text(null, true))->toContain('Review UI Test')->toContain('1202200001')
        ->toContain('Periode Review UI')->toContain('081234567890')->toContain('081234567891')
        ->toContain('7 dokumen')->toContain('Belum dikirim');
    expect($crawler->filter('[data-review-group]')->count())->toBe(3);
    $links = $crawler->filter('a[data-document-preview]');
    expect($links->count())->toBe(7);
    $actual = $links->each(function (Crawler $node) {
        $classes = preg_split('/\s+/', trim($node->attr('class')));
        expect($classes)->toContain('btn')->toContain('btn-secondary')->not->toContain('underline');
        return $node->attr('href');
    });
    $expected = $draft->documents->map(fn ($document) => route('sidang-ta-draft-documents.preview', $document))->all();
    sort($actual);
    sort($expected);
    expect($actual)->toBe($expected);
    expect(SidangTaRegistration::count())->toBe(0);
    expect($draft->fresh()->status)->toBe('active');
    expect($crawler->filter('[data-open-registration]')->getNode(0)->hasAttribute('disabled'))->toBeFalse();
    expect($crawler->filter('[data-confirm-registration]')->getNode(0)->hasAttribute('disabled'))->toBeFalse();
});

test('only_confirmation_form_submits_saved_draft_with_current_contract', function () {
    $draft = ($this->reviewedDraft)();
    $crawler = new Crawler($this->get(route('sidang-ta-drafts.preview', $draft))->assertOk()->getContent());
    $open = $crawler->filter('[data-open-registration]');
    expect($open->count())->toBe(1);
    expect($open->attr('type'))->toBe('button');
    expect($open->getNode(0)->hasAttribute('disabled'))->toBeFalse();
    $openNode = $open->getNode(0);
    $xpath = new \DOMXPath($openNode->ownerDocument);
    expect($xpath->query('ancestor::form', $openNode)->length)->toBe(0);
    $dialog = $crawler->filter('dialog#confirm-send-registration');
    expect($dialog->count())->toBe(1);
    expect($dialog->getNode(0)->hasAttribute('open'))->toBeFalse();
    expect($dialog->text(null, true))->toContain('Periksa kembali data dan seluruh dokumen.');
    expect($dialog->filter('[data-cancel-registration]')->attr('type'))->toBe('button');
    expect($dialog->filter('[data-cancel-registration]')->getNode(0)->hasAttribute('autofocus'))->toBeTrue();
    expect($dialog->filter('[data-confirm-registration]')->attr('type'))->toBe('submit');
    expect($dialog->filter('[data-confirm-registration]')->getNode(0)->hasAttribute('disabled'))->toBeFalse();
    $form = $dialog->filter('form[data-final-registration-form]');
    expect($crawler->filter('form[data-final-registration-form]')->count())->toBe(1);
    expect($form->attr('method'))->toBe('POST');
    expect($form->attr('action'))->toBe(route('sidang-ta-registrations.store'));
    expect($form->filter('input[type="file"]')->count())->toBe(0);
    $fields = $form->filter('input[name]')->each(fn (Crawler $node) => [
        'name' => $node->attr('name'), 'value' => $node->attr('value'),
    ]);
    $body = array_column($fields, 'value', 'name');
    $keys = array_keys($body);
    sort($keys);
    expect($keys)->toBe(['_token', 'action', 'draft_id', 'version']);
    expect($body['draft_id'])->toBe($draft->id);
    expect($body['version'])->toBe((string) $draft->version);
    expect($body['action'])->toBe('submit');
    expect(SidangTaRegistration::count())->toBe(0);

    $response = $this->post($form->attr('action'), $body);
    $registration = SidangTaRegistration::firstOrFail();
    $response->assertRedirect(route('sidang-ta-registrations.show', $registration));
    expect(SidangTaRegistration::count())->toBe(1);
    expect($registration->status)->toBe('submitted');
    expect($registration->documents()->count())->toBe(7);
    expect($draft->fresh()->status)->toBe('consumed');
});

test('pdm_review_displays_only_its_six_required_documents', function () {
    $draft = ($this->reviewedDraft)(false);
    $crawler = new Crawler($this->get(route('sidang-ta-drafts.preview', $draft))->assertOk()->getContent());
    expect($crawler->filter('[data-review-summary]')->text(null, true))
        ->toContain('6 dokumen')->toContain('Belum sesuai, bukti PDM dilampirkan');
    expect($crawler->filter('a[data-document-preview]')->count())->toBe(6);
    expect($crawler->filter('[data-review-document="pdm_submission_proof"]')->count())->toBe(1);
    expect($crawler->filter('[data-review-document="diploma_statement"]')->count())->toBe(0);
    expect($crawler->filter('[data-review-document="diploma_biodata_form"]')->count())->toBe(0);
});

test('revision_confirmation_form_keeps_original_registration_and_put_method', function () {
    $source = ($this->revisionSource)();
    $revisionId = $this->postJson(route('sidang-ta-drafts.revision', $source))
        ->assertCreated()->json('data.id');
    $revision = SidangTaRegistrationDraft::findOrFail($revisionId);
    expect($revision->requirements_version)->toBe(2);
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $revision, 'step' => 2]), [
        'version' => $revision->version,
        'documents' => ['ta_data_capture' => \Illuminate\Http\UploadedFile::fake()
            ->create('replacement.pdf', 100, 'application/pdf')],
    ])->assertOk();
    $revision = $revision->fresh();
    $this->postJson(route('sidang-ta-drafts.review', $revision), ['version' => $revision->version])->assertOk();
    $crawler = new Crawler($this->get(route('sidang-ta-drafts.preview', $revision))->assertOk()->getContent());
    $form = $crawler->filter('#confirm-send-registration form[data-final-registration-form]');
    expect($form->attr('action'))->toBe(route('sidang-ta-registrations.update', $source));
    expect($form->filter('input[name="_method"]')->attr('value'))->toBe('PUT');
    expect($form->filter('input[name="draft_id"]')->attr('value'))->toBe($revision->id);
    expect($form->filter('input[name="version"]')->attr('value'))->toBe((string) $revision->version);
    expect($form->filter('[data-confirm-registration]')->text(null, true))->toBe('Ya, kirim revisi');
    expect(SidangTaRegistration::count())->toBe(1);
    expect($source->fresh()->status)->toBe('needs_review');
});

test('read_only_owner_keeps_review_downloads_without_send_controls', function () {
    $draft = ($this->reviewedDraft)();
    $this->user->revokePermissionTo('sidang_ta_registration_create');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($this->user->fresh());
    $crawler = new Crawler($this->get(route('sidang-ta-drafts.preview', $draft))->assertOk()->getContent());
    expect($crawler->filter('a[data-document-preview]')->count())->toBe(7);
    expect($crawler->filter('[data-open-registration]')->count())->toBe(0);
    expect($crawler->filter('#confirm-send-registration')->count())->toBe(0);
    expect($crawler->filter('form[data-final-registration-form]')->count())->toBe(0);
    expect(SidangTaRegistration::count())->toBe(0);
});


test('unknown_eprt_allows_seven_document_review_but_blocks_send', function () {
    \App\Models\StudentEprt::where('student_number', $this->student->student_number)->delete();
    expect(app(\App\Services\SidangTaAdmissionFacts::class)->forStudent($this->student)['eprt']['mode'])
        ->toBe('unknown');
    $draft = ($this->reviewedDraft)();
    $crawler = new Crawler($this->get(route('sidang-ta-drafts.preview', $draft))->assertOk()->getContent());
    expect($draft->requirements_version)->toBe(2);
    expect($crawler->filter('a[data-document-preview]')->count())->toBe(7);
    expect($crawler->filter('[data-review-group="3"] [data-review-document]')->count())->toBe(3);
    expect($crawler->filter('[data-review-document="eprt_waiver_letter"]')->count())->toBe(0);
    expect($crawler->filter('[data-open-registration]')->getNode(0)->hasAttribute('disabled'))->toBeTrue();
    expect($crawler->filter('[data-confirm-registration]')->getNode(0)->hasAttribute('disabled'))->toBeTrue();
    $this->postJson(route('sidang-ta-registrations.store'), [
        'action' => 'submit', 'draft_id' => $draft->id, 'version' => $draft->version,
    ])->assertUnprocessable();
    expect(SidangTaRegistration::count())->toBe(0);
    expect($draft->fresh()->status)->toBe('active');
});
```

### `tests/Feature/SidangTaRevisionContractTest.php`

```php
<?php

use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDraft;
use App\Models\User;
use App\Support\SidangTaReviewEligibility as Eligibility;
use App\Support\SidangTaRevisionContract as Contract;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Storage::fake('local');
    $this->owner = User::factory()->create();
    $period = SidangTaPeriod::create(['name' => 'Contract', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay()]);
    $this->source = SidangTaRegistration::create(['requirements_version' => 2,
        'sidang_ta_period_id' => $period->id, 'student_number' => '1202299058', 'student_name' => 'Contract',
        'student_phone' => '081234567890', 'parent_phone' => '081234567891',
        'graduation_payment_date' => today()->toDateString(), 'diploma_biodata_accurate' => true,
        'academic_snapshot' => ['admission' => ['eprt' => ['mode' => 'waiver']]], 'status' => 'needs_review'])->fresh();
    $this->payload = ['student_phone' => $this->source->student_phone, 'parent_phone' => $this->source->parent_phone,
        'graduation_payment_date' => today()->toDateString(), 'diploma_biodata_accurate' => true];
    $this->draft = SidangTaRegistrationDraft::create(['owner_user_id' => $this->owner->id,
        'student_number' => $this->source->student_number, 'sidang_ta_period_id' => $period->id,
        'requirements_version' => 2, 'source_registration_id' => $this->source->id,
        'source_version' => $this->source->lock_version, 'payload' => $this->payload])->fresh();
    foreach (Eligibility::requiredTypes($this->source) as $type) {
        $path = 'sidang_ta/contract/'.$type.'.pdf';
        Storage::disk('local')->put($path, '%PDF-contract');
        $doc = $this->source->documents()->create(['document_type' => $type, 'file_name' => $type.'.pdf',
            'file_path' => $path, 'file_size' => 13, 'file_type' => 'pdf',
            'status' => $type === 'ta_data_capture' ? 'rejected' : 'verified',
            'notes' => $type === 'ta_data_capture' ? 'Perbaiki' : null]);
        $this->draft->documents()->create(['document_type' => $type, 'file_name' => $doc->file_name,
            'file_path' => $path, 'file_size' => 13, 'file_type' => 'pdf', 'source_document_id' => $doc->id]);
    }
    $this->replaceRequested = function () {
        $path = 'sidang_ta/contract/replacement.pdf';
        Storage::disk('local')->put($path, '%PDF-new');
        $document = $this->draft->documents()->where('document_type', 'ta_data_capture')->firstOrFail();
        $document->update(['file_path' => $path, 'source_document_id' => null]);
        return $document;
    };
});

test('revision_contract_freezes_document_groups_to_the_stored_waiver_mode', function () {
    expect(Contract::isTargeted($this->draft))->toBeTrue();
    expect(Contract::typesForStep($this->draft, 2))->toBe(['ta_data_capture', 'online_supervision_capture']);
    expect(Contract::typesForStep($this->draft, 3))->toContain('eprt_waiver_letter')->toHaveCount(4);
    expect(Contract::editableTypes($this->draft))->toBe(['ta_data_capture']);
});

test('revision_contract_requires_rejected_documents_and_all_other_files_verified', function () {
    Contract::assertCanRequest($this->source);
    $doc = $this->source->documents()->where('document_type', 'thesis_draft')->firstOrFail();
    $doc->update(['status' => 'pending']);
    expect(fn () => Contract::assertCanRequest($this->source))->toThrow(ValidationException::class);
});

test('revision_contract_cannot_request_revision_without_any_rejected_document', function () {
    $this->source->documents()->update(['status' => 'verified', 'notes' => null]);
    expect(fn () => Contract::assertCanRequest($this->source))->toThrow(ValidationException::class);
});

test('revision_contract_borrowed_rejected_file_does_not_count_as_a_replacement', function () {
    $doc = $this->draft->documents()->where('document_type', 'ta_data_capture')->firstOrFail();
    expect(Contract::replacementRequired($this->draft, $doc))->toBeTrue();
    expect(fn () => Contract::assertTransferReady($this->draft))->toThrow(ValidationException::class);
    $replacement = ($this->replaceRequested)();
    expect(Contract::replacementRequired($this->draft, $replacement))->toBeFalse();
    Contract::assertTransferReady($this->draft);
});

test('revision_contract_frozen_payload_accepts_equivalent_html_boolean_but_rejects_changes', function () {
    Contract::assertPayloadUnchanged($this->draft, array_replace($this->payload, ['diploma_biodata_accurate' => '1']));
    foreach (['student_phone' => '089999999999', 'parent_phone' => '088888888888',
                 'graduation_payment_date' => today()->subDay()->toDateString(), 'diploma_biodata_accurate' => false] as $key => $value) {
        expect(fn () => Contract::assertPayloadUnchanged($this->draft, array_replace($this->payload, [$key => $value])))
            ->toThrow(ValidationException::class);
    }
});

test('revision_contract_verified_file_must_keep_its_source_reference', function () {
    ($this->replaceRequested)();
    $doc = $this->draft->documents()->where('document_type', 'thesis_draft')->firstOrFail();
    $doc->update(['source_document_id' => null]);
    expect(fn () => Contract::assertTransferReady($this->draft))->toThrow(ValidationException::class);
});

test('revision_contract_missing_replacement_or_extra_document_blocks_transfer', function () {
    $replacement = ($this->replaceRequested)();
    Storage::disk('local')->delete($replacement->file_path);
    expect(fn () => Contract::assertTransferReady($this->draft))->toThrow(ValidationException::class);
    Storage::disk('local')->put($replacement->file_path, '%PDF-new');
    $this->draft->documents()->create(['document_type' => 'transcript_khs', 'file_name' => 'extra.pdf',
        'file_path' => 'extra.pdf', 'file_type' => 'pdf']);
    expect(fn () => Contract::assertTransferReady($this->draft))->toThrow(ValidationException::class);
});

test('revision_contract_rejects_stale_source_version_instead_of_using_cached_relation', function () {
    $this->draft->load('sourceRegistration');
    $this->source->lock_version++;
    $this->source->save();
    try {
        Contract::source($this->draft);
        $this->fail('A stale source must be rejected.');
    } catch (Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception) {
        expect($exception->getStatusCode())->toBe(409);
    }
});

test('revision_contract_unsupported_marker_is_rejected_before_targeted_classification', function (int $marker) {
    $this->draft->update(['requirements_version' => $marker]);
    $before = $this->draft->fresh()->getAttributes();
    $paths = Storage::disk('local')->allFiles();
    try {
        Contract::isTargeted($this->draft);
        $this->fail('Unsupported requirements must fail closed.');
    } catch (Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception) {
        expect($exception->getStatusCode())->toBe(409);
        expect($exception->getMessage())->toBe('Persyaratan pengajuan tidak sesuai. Muat ulang atau hubungi petugas.');
    }
    expect($this->draft->fresh()->getAttributes())->toBe($before);
    expect(Storage::disk('local')->allFiles())->toBe($paths);
})->with([1, 3]);
```

### `tests/Feature/SidangTaRevisionDeadlineTest.php`

```php
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
        'open_at' => now()->subDay(), 'close_at' => now()->addDay()]);
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

// Each test owns its fixtures. These tests deliberately use ordinary request ordering,
// not parallel processes, to exercise both outcomes of the expiry/resubmission race.
test('revision_deadline_exact_closing_instant_allows_save_but_one_second_later_blocks_it', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source);
    $this->period->update(['close_at' => now()]);
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
    $this->period->update(['close_at' => now()->subSecond()]);
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
    $this->period->update(['close_at' => now()->subSecond()]);
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
    $this->period->update(['close_at' => now()->subSecond()]);
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
    $this->period->update(['close_at' => now()->subSecond()]);
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
    $this->period->update(['close_at' => now()->subSecond()]);
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
    $this->period->update(['close_at' => now()->subSecond()]);
    expect(app(SidangTaRevisionExpiry::class)->run())->toBe(1);
    $rejected = $source->fresh();
    expect($rejected->status)->toBe('rejected');
    expect($rejected->lock_version)->toBe($version + 1);
    expect($rejected->reviewed_by)->toBeNull();
    expect($rejected->reviewed_at->equalTo(now()))->toBeTrue();
    expect($rejected->review_notes)->toBe(SidangTaRevisionExpiry::NOTE);
    expect(SidangTaRevisionExpiry::NOTE)->toBe('Ditolak otomatis karena revisi tidak dikirim sampai batas akhir periode Sidang TA.');
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
    $this->period->update(['close_at' => now()->subSecond()]);
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
    $this->period->update(['close_at' => now()->subSecond()]);
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
    $this->period->update(['close_at' => now()->subSecond()]);
    expect(app(SidangTaRevisionExpiry::class)->run())->toBe(1);
    expect($source->fresh()->status)->toBe('rejected');
});

test('revision_deadline_reads_fresh_period_after_an_extension_before_expiry_runs', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source);
    $this->period->update(['close_at' => now()->subSecond()]);
    $source->load('period'); // Deliberately cache the old, already closed period.
    expect(SidangTaRevisionDeadline::hasExpired($source))->toBeTrue();
    SidangTaPeriod::whereKey($this->period->id)->update(['close_at' => now()->addHour()]);
    expect(SidangTaRevisionDeadline::hasExpired($source))->toBeFalse();
    expect(app(SidangTaRevisionExpiry::class)->expire($source->id))->toBeFalse();
    expect(app(SidangTaRevisionExpiry::class)->run())->toBe(0);
    ($this->replaceDocument)($draft)->assertOk();
    expect($source->fresh()->status)->toBe('needs_review');
});

test('revision_expiry_rechecks_status_when_a_previously_selected_source_has_changed', function () {
    $source = ($this->submittedSource)();
    ($this->prepareRevision)($source);
    $this->period->update(['close_at' => now()->subSecond()]);
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
    $this->period->update(['close_at' => now()->subSecond()]);
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
    $this->period->update(['close_at' => now()->subSecond()]);
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
    $this->period->update(['close_at' => now()->subSecond()]);
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
    $this->period->update(['close_at' => now()->subSecond()]);
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
    $this->period->update(['close_at' => now()->subSecond()]);
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
    $this->period->update(['close_at' => now()->subSecond()]);
    expect(app(SidangTaRevisionExpiry::class)->run())->toBe(1);
    expect($source->fresh()->status)->toBe('rejected');
    expect($source->documents()->orderBy('id')->get()->toArray())->toBe($documents);
    expect(Storage::disk('local')->allFiles())->toBe($paths);
});

test('revision_expiry_does_not_reject_at_exact_deadline_but_rejects_one_second_after', function () {
    $source = ($this->submittedSource)();
    ($this->prepareRevision)($source);
    $this->period->update(['close_at' => now()]);
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
    $this->period->update(['close_at' => now()->subSecond()]);
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
```

### `tests/Feature/SidangTaStaffNavigationTest.php`

```php
<?php

use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\User;
use App\Services\SidebarBadgeService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\DomCrawler\Crawler;

beforeEach(function () {
    $this->travelTo(now()->startOfSecond());
    foreach (['sidang_ta_registration_review', 'sidang_ta_registration_read',
                 'sidang_ta_period_read', 'sidang_ta_period_create', 'sidang_ta_period_update',
                 'sidang_ta_period_delete', 'student_read', 'lecturer_read'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->makeActor = function (array $permissions = []) {
        $id = (string) Str::uuid();
        $user = User::factory()->create(['username' => 'p1-'.$id, 'email' => $id.'@staff.test',
            'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
        if ($permissions !== []) $user->givePermissionTo($permissions);
        return $user;
    };
    $this->period = SidangTaPeriod::create(['name' => 'Periode P1', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay()]);
    $this->registration = SidangTaRegistration::create([
        'sidang_ta_period_id' => $this->period->id, 'student_number' => 'P1-PRIVATE-0001',
        'student_name' => 'Mahasiswa Rahasia P1', 'study_program' => 'Informatika',
        'student_phone' => '081234567890', 'parent_phone' => '081234567891',
        'graduation_payment_date' => today()->toDateString(), 'diploma_biodata_accurate' => true,
        'status' => 'submitted', 'submitted_at' => now(),
        'academic_snapshot' => ['admission' => ['eprt' => ['mode' => 'automatic']]],
    ])->fresh();
    $this->periodData = ['name' => 'Periode Baru P1', 'is_active' => 1,
        'open_at' => now()->subHour()->format('Y-m-d H:i:s'),
        'close_at' => now()->addDays(2)->format('Y-m-d H:i:s')];
    $this->sidebarFor = function (string $html, string $route) {
        return (new Crawler($html))->filter('aside nav a')->reduce(
            fn (Crawler $node) => $node->attr('href') === route($route)
        );
    };
    $this->validatorConfig = function ($any) {
        config(['sidebar' => ['mahasiswa' => [], 'admin_standalone' => [], 'admin' => [
            'p1' => ['label' => 'P1', 'items' => [[
                'label' => 'P1 OR', 'route' => 'admin.sidang-ta-registrations.index',
                'permissions_any' => $any,
            ]]],
        ]]]);
    };
});

afterEach(function () {
    $this->travelBack();
});

test('p1_combined_permissions_show_one_sidebar_entry_and_manage_link', function () {
    $user = ($this->makeActor)(['sidang_ta_registration_review', 'sidang_ta_period_read']);
    $response = $this->actingAs($user)->get(route('admin.sidang-ta-registrations.index'))->assertOk();
    $html = $response->getContent();
    $crawler = new Crawler($html);
    expect(($this->sidebarFor)($html, 'admin.sidang-ta-registrations.index')->count())->toBe(1);
    expect(($this->sidebarFor)($html, 'admin.sidang-ta-periods.index')->count())->toBe(0);
    expect($crawler->filter('[data-manage-ta-periods]')->attr('href'))->toBe(route('admin.sidang-ta-periods.index'));
    expect($crawler->filter('[data-ta-review-data]')->count())->toBe(1);
    $response->assertSee('Mahasiswa Rahasia P1');
});

test('p1_reviewer_without_period_read_keeps_review_but_cannot_manage_periods', function () {
    $user = ($this->makeActor)(['sidang_ta_registration_review']);
    $html = $this->actingAs($user)->get(route('admin.sidang-ta-registrations.index'))->assertOk()->getContent();
    $crawler = new Crawler($html);
    expect($crawler->filter('[data-ta-review-data]')->count())->toBe(1);
    expect($crawler->filter('[data-manage-ta-periods]')->count())->toBe(0);
    $this->get(route('admin.sidang-ta-registrations.show', $this->registration))->assertOk();
    $this->get(route('admin.sidang-ta-periods.index'))->assertForbidden();
});

test('p1_period_reader_hub_does_not_query_or_expose_registration_data', function () {
    $user = ($this->makeActor)(['sidang_ta_period_read']);
    $queries = [];
    DB::listen(function (QueryExecuted $event) use (&$queries) {
        if (str_contains(strtolower($event->sql), 'sidang_ta_registrations')) $queries[] = $event->sql;
    });
    $response = $this->actingAs($user)->get(route('admin.sidang-ta-registrations.index', ['search' => 'P1']))
        ->assertOk()->assertViewHas('canReview', false)->assertViewHas('registrations', fn ($value) => $value === null)
        ->assertDontSee('Mahasiswa Rahasia P1')->assertDontSee('P1-PRIVATE-0001');
    expect($queries)->toBe([]);
    $crawler = new Crawler($response->getContent());
    expect($crawler->filter('[data-ta-review-data]')->count())->toBe(0);
    expect($crawler->filter('[data-ta-period-only]')->count())->toBe(1);
    expect($crawler->filter('[data-manage-ta-periods]')->count())->toBe(1);
    $this->get(route('admin.sidang-ta-registrations.show', $this->registration))->assertForbidden();
    $this->post(route('admin.sidang-ta-registrations.review', $this->registration), [
        'action' => 'approved', 'registration_version' => $this->registration->lock_version,
    ])->assertForbidden();
    expect($this->registration->fresh()->status)->toBe('submitted');
    expect($user->fresh()->can('sidang_ta_registration_review'))->toBeFalse();
});

test('p1_user_without_either_permission_has_no_hub_or_menu', function () {
    $user = ($this->makeActor)();
    $this->actingAs($user)->get(route('admin.sidang-ta-registrations.index'))->assertForbidden();
    $this->get(route('admin.sidang-ta-periods.index'))->assertForbidden();
    $html = $this->get(route('dashboard'))->assertOk()->getContent();
    expect(($this->sidebarFor)($html, 'admin.sidang-ta-registrations.index')->count())->toBe(0);
});

test('p1_period_page_keeps_review_menu_active_and_has_fixed_back_link', function () {
    $user = ($this->makeActor)(['sidang_ta_period_read']);
    $html = $this->actingAs($user)->get(route('admin.sidang-ta-periods.index'))->assertOk()->getContent();
    $crawler = new Crawler($html);
    expect(($this->sidebarFor)($html, 'admin.sidang-ta-registrations.index')->attr('aria-current'))->toBe('page');
    expect(($this->sidebarFor)($html, 'admin.sidang-ta-periods.index')->count())->toBe(0);
    expect($crawler->filter('[data-back-to-ta-review]')->attr('href'))->toBe(route('admin.sidang-ta-registrations.index'));
    $groupKey = null;
    foreach (config('sidebar.admin') as $key => $group) {
        foreach ($group['items'] ?? [] as $item) {
            if (($item['route'] ?? null) === 'admin.sidang-ta-registrations.index') $groupKey = $key;
        }
    }
    expect($groupKey)->not->toBeNull();
    expect($crawler->filter('aside nav [x-data]')->attr('x-data'))->toContain($groupKey);
});

test('p1_period_reader_sees_all_time_states_without_write_forms', function () {
    $user = ($this->makeActor)(['sidang_ta_period_read']);
    $cases = [[$this->period, 'open']];
    foreach ([['inactive', false, now()->subDay(), now()->addDay()],
                 ['upcoming', true, now()->addHour(), now()->addDay()],
                 ['closed', true, now()->subDays(2), now()->subDay()],
                 ['open', true, now(), now()->addDay()],
                 ['open', true, now()->subDay(), now()]] as [$state, $active, $open, $close]) {
        $cases[] = [SidangTaPeriod::create(['name' => 'State '.$state, 'is_active' => $active,
            'open_at' => $open, 'close_at' => $close]), $state];
    }
    $crawler = new Crawler($this->actingAs($user)->get(route('admin.sidang-ta-periods.index'))->assertOk()->getContent());
    expect($crawler->filter('form[data-ta-period-create]')->count())->toBe(0);
    expect($crawler->filter('form[data-ta-period-update]')->count())->toBe(0);
    foreach ($cases as [$period, $state]) {
        expect($crawler->filter('[data-ta-period-id="'.$period->id.'"] [data-period-state]')->attr('data-period-state'))->toBe($state);
    }
    $this->post(route('admin.sidang-ta-periods.store'), $this->periodData)->assertForbidden();
    $this->put(route('admin.sidang-ta-periods.update', $this->period), $this->periodData)->assertForbidden();
    $this->post(route('admin.sidang-ta-periods.activate', $this->period))->assertForbidden();
    $this->delete(route('admin.sidang-ta-periods.destroy', $this->period))->assertForbidden();
});

test('p1_creator_can_create_second_active_period_without_update_controls', function () {
    $user = ($this->makeActor)(['sidang_ta_period_read', 'sidang_ta_period_create']);
    $url = route('admin.sidang-ta-periods.index');
    $crawler = new Crawler($this->actingAs($user)->get($url)->assertOk()->getContent());
    expect($crawler->filter('form[data-ta-period-create]')->count())->toBe(1);
    expect($crawler->filter('form[data-ta-period-update]')->count())->toBe(0);
    $this->from($url)->post(route('admin.sidang-ta-periods.store'), $this->periodData)
        ->assertRedirect($url)->assertSessionHasNoErrors();
    expect($this->period->fresh()->is_active)->toBeTrue();
    expect(SidangTaPeriod::where('name', 'Periode Baru P1')->firstOrFail()->is_active)->toBeTrue();
    $this->put(route('admin.sidang-ta-periods.update', $this->period), $this->periodData)->assertForbidden();
});

test('p1_updater_has_correct_datetime_inputs_and_keeps_other_period_active', function () {
    $user = ($this->makeActor)(['sidang_ta_period_read', 'sidang_ta_period_update']);
    $url = route('admin.sidang-ta-periods.index');
    $crawler = new Crawler($this->actingAs($user)->get($url)->assertOk()->getContent());
    expect($crawler->filter('form[data-ta-period-create]')->count())->toBe(0);
    $input = $crawler->filter('[data-ta-period-id="'.$this->period->id.'"] input[name="open_at"]');
    expect($input->attr('value'))->toBe($this->period->open_at->format('Y-m-d\TH:i'));
    $second = SidangTaPeriod::create(['name' => 'Update P1', 'is_active' => false,
        'open_at' => now()->addHour(), 'close_at' => now()->addDay()]);
    $this->from($url)->post(route('admin.sidang-ta-periods.activate', $second))->assertRedirect($url);
    expect($this->period->fresh()->is_active)->toBeTrue();
    expect($second->fresh()->is_active)->toBeTrue();
    $this->from($url)->put(route('admin.sidang-ta-periods.update', $second), $this->periodData)
        ->assertRedirect($url)->assertSessionHasNoErrors();
    expect($this->period->fresh()->is_active)->toBeTrue();
    expect($second->fresh()->name)->toBe('Periode Baru P1');
    $this->post(route('admin.sidang-ta-periods.store'), $this->periodData)->assertForbidden();
    $this->delete(route('admin.sidang-ta-periods.destroy', $second))->assertForbidden();
});

test('p1_sidebar_or_is_fail_closed_and_preserves_legacy_permission_role_items', function () {
    $user = ($this->makeActor)(['student_read']);
    $role = Role::findOrCreate('P1Legacy', 'web');
    $user->assignRole($role);
    $this->actingAs($user);
    $groups = ['test' => ['label' => 'P1 Group', 'icon' => [], 'items' => [
        ['label' => 'P1 Public', 'route' => 'dashboard'],
        ['label' => 'P1 Permission', 'route' => 'dashboard', 'permission' => 'student_read'],
        ['label' => 'P1 Denied', 'route' => 'dashboard', 'permission' => 'lecturer_read'],
        ['label' => 'P1 Role', 'route' => 'dashboard', 'roles' => ['P1Legacy']],
        ['label' => 'P1 Empty OR', 'route' => 'dashboard', 'permissions_any' => []],
        ['label' => 'P1 Unmatched OR', 'route' => 'dashboard', 'permissions_any' => ['sidang_ta_registration_review'], 'roles' => ['P1Legacy']],
        ['label' => 'P1 Missing Route', 'route' => 'p1.route.does.not.exist'],
    ]]];
    $html = view('components.sidebar-admin', ['groups' => $groups, 'standalone' => [],
        'badgeService' => app(SidebarBadgeService::class)])->render();
    expect($html)->toContain('P1 Public')->toContain('P1 Permission')->toContain('P1 Role')
        ->not->toContain('P1 Denied')->not->toContain('P1 Empty OR')->not->toContain('P1 Unmatched OR')
        ->not->toContain('P1 Missing Route');
});

test('p1_sidebar_validator_accepts_registered_or_permissions', function () {
    ($this->validatorConfig)(['sidang_ta_registration_review', 'sidang_ta_period_read']);
    $this->artisan('sidebar:validate')->assertExitCode(0);
});

test('p1_sidebar_validator_rejects_unknown_empty_and_invalid_or_permissions', function () {
    ($this->validatorConfig)(['p1_unknown_permission']);
    $this->artisan('sidebar:validate')->expectsOutputToContain('Permission tidak terdaftar')->assertExitCode(1);
    foreach ([[], 'sidang_ta_period_read', [null], ['']] as $invalid) {
        ($this->validatorConfig)($invalid);
        $this->artisan('sidebar:validate')->expectsOutputToContain('permissions_any')->assertExitCode(1);
    }
});

test('p1_role_switch_revokes_staff_hub_access_while_student_role_is_active', function () {
    $staff = Role::findOrCreate('P1Reviewer', 'web');
    $staff->givePermissionTo('sidang_ta_registration_review');
    $student = Role::findOrCreate('mahasiswa', 'web');
    $student->givePermissionTo('sidang_ta_registration_read');
    $user = ($this->makeActor)();
    $user->assignRole([$staff, $student]);
    $this->actingAs($user)->withSession(['active_role' => 'P1Reviewer'])
        ->get(route('admin.sidang-ta-registrations.index'))->assertOk();
    $this->post(route('role.switch'), ['role' => 'mahasiswa'])->assertRedirect(route('dashboard'));
    $this->get(route('admin.sidang-ta-registrations.index'))->assertForbidden();
    $this->get(route('dashboard'))->assertOk()->assertDontSee('Review Sidang TA');
    $this->post(route('role.switch'), ['role' => 'P1Reviewer'])->assertRedirect(route('dashboard'));
    $this->get(route('admin.sidang-ta-registrations.index'))->assertOk();
});
```

### `tests/Feature/SidangTaStudentPolishTest.php`

```php
<?php

use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDraft;
use App\Models\Student;
use App\Models\User;
use App\Support\SidangTaDocumentRequirements as Requirements;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Symfony\Component\DomCrawler\Crawler;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create([
        'username' => '1202200001', 'email' => 'student-polish@mahasiswa.test',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false,
    ]);
    $this->student = Student::create([
        'student_number' => '1202200001', 'name' => 'Student Polish Test',
        'email' => $this->user->email, 'academic_year' => 2022,
        'study_program' => 'Informatika', 'status' => 'Aktif', 'gpa' => 0,
    ]);
    foreach (['read', 'create', 'update'] as $action) {
        $this->user->givePermissionTo(Permission::firstOrCreate([
            'name' => "sidang_ta_registration_{$action}", 'guard_name' => 'web',
        ]));
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->period = SidangTaPeriod::create([
        'name' => 'Periode S2', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay(),
    ]);
    $this->data = ['sidang_ta_period_id' => $this->period->id,
        'student_phone' => '081234567890', 'parent_phone' => '081234567891'];
    $this->actingAs($this->user);
    $this->completeDraft = function () {
        $id = $this->postJson(route('sidang-ta-drafts.store'), $this->data)
            ->assertCreated()->json('data.id');
        $draft = SidangTaRegistrationDraft::findOrFail($id);
        expect($draft->requirements_version)->toBe(2);
        foreach ([2, 3, 4] as $step) {
            $data = ['version' => $draft->fresh()->version];
            if ($step === 3) $data['graduation_payment_date'] = today()->toDateString();
            if ($step === 4) $data['diploma_biodata_accurate'] = true;
            foreach (Requirements::typesForStep($step, true) as $type) {
                $data['documents'][$type] = \Illuminate\Http\UploadedFile::fake()
                    ->create($type.'.pdf', 100, 'application/pdf');
            }
            $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => $step]), $data)
                ->assertOk();
        }
        $draft = $draft->fresh();
        expect($draft->documents()->count())->toBe(7);
        return $draft;
    };
});


test('s2_diploma_review_is_disabled_until_both_saved_files_exist', function () {
    $draft = ($this->completeDraft)();
    $missing = $draft->documents()->where('document_type', 'diploma_biodata_form')->firstOrFail();
    Storage::disk('local')->delete($missing->file_path);
    $url = route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 4]);
    $crawler = new Crawler($this->get($url)->assertOk()->getContent());
    $button = $crawler->filter('[data-review-submit]')->getNode(0);
    expect($button->hasAttribute('disabled'))->toBeTrue();
    expect($button->getAttribute('aria-disabled'))->toBe('true');
    expect($button->getAttribute('class'))->toContain('disabled:opacity-50')
        ->toContain('disabled:cursor-not-allowed');
    expect($crawler->filter('[data-step-progress]')->text(null, true))->toContain('1 dari 2 dokumen tersimpan.');
    $this->postJson(route('sidang-ta-drafts.review', $draft), ['version' => $draft->version])
        ->assertUnprocessable();
    Storage::disk('local')->put($missing->file_path, '%PDF-ui');
    $crawler = new Crawler($this->get($url)->assertOk()->getContent());
    $button = $crawler->filter('[data-review-submit]')->getNode(0);
    expect($button->hasAttribute('disabled'))->toBeFalse();
    expect($button->getAttribute('aria-disabled'))->toBe('false');
    expect($button->getAttribute(':disabled'))->toContain('dirty || busy');
    expect($crawler->filter('[data-review-draft]')->attr('x-on:submit'))->toContain('dirty || busy');
});

test('s2_pdm_review_requires_saved_pdm_after_switching_branch', function () {
    $draft = ($this->completeDraft)();
    $save = route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 4]);
    $this->postJson($save, ['version' => $draft->version, 'diploma_biodata_accurate' => false])
        ->assertOk()->assertJsonPath('data.first_incomplete_step', 4);
    $url = route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 4]);
    $crawler = new Crawler($this->get($url)->assertOk()->getContent());
    expect($crawler->filter('[data-review-submit]')->getNode(0)->hasAttribute('disabled'))->toBeTrue();
    expect($crawler->filter('[data-step-progress]')->text(null, true))->toContain('0 dari 1 dokumen tersimpan.');
    $this->postJson($save, ['version' => $draft->fresh()->version, 'documents' => [
        'pdm_submission_proof' => UploadedFile::fake()->create('pdm.pdf', 100, 'application/pdf'),
    ]])->assertOk()->assertJsonPath('data.first_incomplete_step', null);
    $crawler = new Crawler($this->get($url)->assertOk()->getContent());
    expect($crawler->filter('[data-review-submit]')->getNode(0)->hasAttribute('disabled'))->toBeFalse();
});

test('s2_upload_steps_hide_downloads_and_removed_hint_but_preview_keeps_downloads', function () {
    $draft = ($this->completeDraft)();
    $documents = $draft->documents()->get();
    foreach ([2, 3, 4] as $step) {
        $response = $this->get(route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => $step]))->assertOk();
        $crawler = new Crawler($response->getContent());
        $response->assertSee('Berkas tersimpan')->assertDontSee('Unduh berkas')
            ->assertDontSee('Informasi akademik bersifat baca saja.');
        foreach ($documents as $document) {
            $response->assertDontSee(route('sidang-ta-draft-documents.download', $document), false);
        }
        foreach (Requirements::typesForStep($step, true) as $type) {
            expect($crawler->filter('[data-upload-type="'.$type.'"]')->text(null, true))
                ->toContain($documents->firstWhere('document_type', $type)->file_name);
        }
    }
    $this->postJson(route('sidang-ta-drafts.review', $draft), ['version' => $draft->version])->assertOk();
    $response = $this->get(route('sidang-ta-drafts.preview', $draft))->assertOk();
    foreach ($documents as $document) {
        $response->assertSee(route('sidang-ta-draft-documents.download', $document), false);
    }
    $this->get(route('sidang-ta-draft-documents.download', $documents->first()))->assertOk();
});

test('s2_index_keeps_draft_and_history_when_multiple_periods_close', function () {
    $draft = ($this->completeDraft)();
    $second = SidangTaPeriod::create(['name' => 'Periode S2 kedua', 'is_active' => true,
        'open_at' => now()->subHour(), 'close_at' => now()->addDay()]);
    $url = route('sidang-ta-registrations.index');
    $crawler = new Crawler($this->get($url)->assertOk()->getContent());
    $header = $crawler->filter('[data-registration-header]');
    $headerNode = $header->getNode(0);
    $xpath = new \DOMXPath($headerNode->ownerDocument);
    expect($xpath->query(
        'ancestor::section[contains(concat(" ", normalize-space(@class), " "), " campus-card ")]',
        $headerNode
    )->length)->toBe(0);
    expect($header->count())->toBe(1);
    expect($header->attr('class'))->not->toContain('campus-card');
    expect($header->filter('[data-open-period-count]')->text(null, true))->toBe('2 periode terbuka');
    expect($header->filter('[data-registration-start]')->attr('href'))
        ->toBe(route('sidang-ta-registrations.create'));
    $this->period->update(['close_at' => now()->subMinute()]);
    $second->update(['close_at' => now()->subMinute()]);
    $response = $this->get($url)->assertOk()->assertSee('Riwayat Pengajuan');
    $crawler = new Crawler($response->getContent());
    expect($crawler->filter('[data-registration-start]')->count())->toBe(0);
    expect($crawler->filter('[data-registration-closed][disabled]')->count())->toBe(1);
    expect($crawler->filter('[data-open-period-count]')->text(null, true))->toBe('0 periode terbuka');
    expect($crawler->filter('[data-testid="drafts-panel"]')->count())->toBe(1);
    $response->assertSee(route('sidang-ta-drafts.edit', $draft), false);
});

test('s2_index_active_registration_hides_start_and_respects_revision_permission', function () {
    $registration = SidangTaRegistration::create([
        'sidang_ta_period_id' => $this->period->id, 'student_number' => $this->student->student_number,
        'student_name' => $this->student->name, 'study_program' => $this->student->study_program,
        'student_phone' => '081234567890', 'parent_phone' => '081234567891',
        'graduation_payment_date' => today()->toDateString(), 'diploma_biodata_accurate' => true,
        'requirements_version' => 2,
        'academic_snapshot' => ['admission' => ['eprt' => ['mode' => 'automatic']]],
        'status' => 'needs_review', 'submitted_at' => now(),
    ]);
    $url = route('sidang-ta-registrations.index');
    $crawler = new Crawler($this->get($url)->assertOk()->getContent());
    $header = $crawler->filter('[data-registration-header]');
    expect($header->filter('[data-registration-start]')->count())->toBe(0);
    expect($header->filter('[data-registration-active]')->attr('href'))
        ->toBe(route('sidang-ta-registrations.show', $registration));
    expect($header->filter('[data-registration-revise]')->attr('href'))
        ->toBe(route('sidang-ta-registrations.edit', $registration));
    $this->user->revokePermissionTo('sidang_ta_registration_update');
    $crawler = new Crawler($this->get($url)->assertOk()->getContent());
    expect($crawler->filter('[data-registration-revise]')->count())->toBe(0);
    $registration->update(['status' => 'submitted']);
    $this->user->givePermissionTo('sidang_ta_registration_update');
    $crawler = new Crawler($this->get($url)->assertOk()->getContent());
    expect($crawler->filter('[data-registration-revise]')->count())->toBe(0);
});

test('s2_read_only_owner_keeps_draft_access_without_create_or_delete_controls', function () {
    $draft = ($this->completeDraft)();
    $this->user->revokePermissionTo('sidang_ta_registration_create');
    $this->user->revokePermissionTo('sidang_ta_registration_update');
    $response = $this->get(route('sidang-ta-registrations.index'))->assertOk();
    $crawler = new Crawler($response->getContent());
    expect($crawler->filter('[data-registration-start]')->count())->toBe(0);
    expect($crawler->filter('[data-registration-readonly]')->count())->toBe(1);
    expect($crawler->filter('[data-testid="drafts-panel"]')->count())->toBe(1);
    expect($crawler->filter('[data-open-discard]')->count())->toBe(0);
    $response->assertSee(route('sidang-ta-drafts.edit', $draft), false);
});
```

### `tests/Feature/SidangTaWizardTest.php`

```php
<?php

use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDraft;
use App\Models\Student;
use App\Models\User;
use App\Support\SidangTaDocumentRequirements as Requirements;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create([
        'username' => '1202200001', 'email' => 'wizard@mahasiswa.test',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false,
    ]);
    Student::create(['student_number' => '1202200001', 'name' => 'Wizard Test',
        'email' => $this->user->email, 'academic_year' => 2022, 'study_program' => 'Informatika', 'status' => 'Aktif']);
    foreach (['read', 'create', 'update'] as $action) {
        $this->user->givePermissionTo(Permission::firstOrCreate([
            'name' => "sidang_ta_registration_{$action}", 'guard_name' => 'web',
        ]));
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->period = SidangTaPeriod::create(['name' => 'Periode E', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay()]);
    $this->data = ['sidang_ta_period_id' => $this->period->id,
        'student_phone' => '081234567890', 'parent_phone' => '081234567891'];
    $this->actingAs($this->user);
    $this->completeDraft = function () {
        $id = $this->postJson(route('sidang-ta-drafts.store'), $this->data)->assertCreated()->json('data.id');
        $draft = SidangTaRegistrationDraft::findOrFail($id);
        expect($draft->requirements_version)->toBe(2);
        foreach ([2, 3, 4] as $step) {
            $data = ['version' => $draft->fresh()->version];
            if ($step === 3) $data['graduation_payment_date'] = today()->toDateString();
            if ($step === 4) $data['diploma_biodata_accurate'] = true;
            foreach (Requirements::typesForStep($step, true) as $type) {
                $data['documents'][$type] = \Illuminate\Http\UploadedFile::fake()
                    ->create($type.'.pdf', 100, 'application/pdf');
            }
            $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => $step]), $data)
                ->assertOk();
        }
        $draft = $draft->fresh();
        expect($draft->documents()->count())->toBe(7);
        return $draft;
    };
});

function sidangTaWizardPeriodIds(string $html): array
{
    $previous = libxml_use_internal_errors(true);
    try {
        $dom = new DOMDocument();
        $dom->loadHTML($html, LIBXML_NONET);
        $options = (new DOMXPath($dom))->query(
            '//select[@name="sidang_ta_period_id"]/option[@value!=""]'
        );
        $ids = [];
        foreach ($options as $option) {
            $ids[] = (int) $option->getAttribute('value');
        }
        sort($ids);
        return $ids;
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
}

test('html_create_lists_multiple_periods_without_creating_draft', function () {
    SidangTaPeriod::create(['name' => 'Periode E kedua', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay()]);
    $this->get(route('sidang-ta-registrations.create'))->assertOk()
        ->assertViewIs('sidang_ta_registrations.wizard')
        ->assertSee('Periode E kedua')->assertSee('data-current-step="1"', false);
    expect(SidangTaRegistrationDraft::count())->toBe(0);
});

test('html_save_redirects_and_future_step_is_locked', function () {
    $this->post(route('sidang-ta-drafts.store'), $this->data)->assertRedirect();
    $draft = SidangTaRegistrationDraft::where('owner_user_id', $this->user->id)->firstOrFail();
    $this->get(route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 2]))
        ->assertOk()->assertSee('data-current-step="2"', false);
    $this->get(route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 4]))
        ->assertRedirect(route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 2]));
});

test('incomplete_draft_cannot_be_marked_reviewed', function () {
    $id = $this->postJson(route('sidang-ta-drafts.store'), $this->data)->assertCreated()->json('data.id');
    $this->postJson(route('sidang-ta-drafts.review', $id), ['version' => 1])->assertUnprocessable();
    expect(SidangTaRegistrationDraft::findOrFail($id)->reviewed_version)->toBeNull();
});

test('review_marks_saved_version_and_renders_seven_documents', function () {
    $draft = ($this->completeDraft)();
    $this->post(route('sidang-ta-drafts.review', $draft), ['version' => $draft->version])
        ->assertRedirect(route('sidang-ta-drafts.preview', $draft));
    $response = $this->get(route('sidang-ta-drafts.preview', $draft))->assertOk()
        ->assertViewIs('sidang_ta_registrations.wizard-review')
        ->assertViewHas('draft', fn ($viewDraft) => $viewDraft->documents->count() === 7);
    $crawler = new \Symfony\Component\DomCrawler\Crawler($response->getContent());
    expect($crawler->text(null, true))->toContain('Pendaftaran belum dikirim.');
    expect($draft->fresh()->reviewed_version)->toBe($draft->version);
    expect(SidangTaRegistration::count())->toBe(0);
});

test('saved_change_invalidates_review', function () {
    $draft = ($this->completeDraft)();
    $this->postJson(route('sidang-ta-drafts.review', $draft), ['version' => $draft->version])->assertOk();
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 1]),
        array_replace($this->data, ['version' => $draft->version, 'student_phone' => '089999999999']))->assertOk();
    $this->getJson(route('sidang-ta-drafts.preview', $draft))->assertStatus(409);
    expect($draft->fresh()->reviewed_version)->toBeNull();
});

test('index_exposes_discard_for_stale_revision', function () {
    $source = SidangTaRegistration::create([
        'sidang_ta_period_id' => $this->period->id, 'student_number' => '1202200001',
        'student_name' => 'Wizard Test', 'student_phone' => '081234567890',
        'parent_phone' => '081234567891', 'graduation_payment_date' => today()->toDateString(),
        'diploma_biodata_accurate' => true, 'requirements_version' => 2, 'academic_snapshot' => ['admission' => ['eprt' => ['mode' => 'automatic']]],
        'status' => 'needs_review',
    ])->fresh();
    expect($source->requirements_version)->toBe(2);
    foreach (array_merge(Requirements::CAPTURES, Requirements::PAYMENT, Requirements::DIPLOMA) as $index => $type) {
        $path = "sidang_ta/final/{$source->id}/{$type}.pdf";
        Storage::disk('local')->put($path, '%PDF-1.4 source');
        $source->documents()->create(['document_type' => $type, 'file_name' => $type.'.pdf',
            'file_path' => $path, 'file_size' => 20, 'file_type' => 'pdf',
            'status' => $index === 0 ? 'rejected' : 'verified',
            'notes' => $index === 0 ? 'Unggah capture yang terbaca.' : null]);
    }
    $id = $this->postJson(route('sidang-ta-drafts.revision', $source))
        ->assertCreated()->json('data.id');
    $source->lock_version++;
    $source->save();

    $response = $this->get(route('sidang-ta-registrations.index'))->assertOk()
        ->assertSee(route('sidang-ta-drafts.destroy', $id), false)
        ->assertDontSee(route('sidang-ta-drafts.edit', $id), false);
    $crawler = new \Symfony\Component\DomCrawler\Crawler($response->getContent());
    $card = $crawler->filter('[data-testid="draft-card"]');
    expect($card->count())->toBe(1);
    expect($card->text(null, true))->toContain('Pengajuan asal berubah.');

    $this->delete(route('sidang-ta-drafts.destroy', $id), ['version' => 1])
        ->assertRedirect(route('sidang-ta-registrations.index'));
    expect(SidangTaRegistrationDraft::find($id))->toBeNull();
    expect($source->fresh()->status)->toBe('needs_review');
});


test('missing_file_prevents_preview_even_after_review', function () {
    $draft = ($this->completeDraft)();
    $this->postJson(route('sidang-ta-drafts.review', $draft), ['version' => $draft->version])->assertOk();
    Storage::disk('local')->delete($draft->documents()->first()->file_path);
    $this->getJson(route('sidang-ta-drafts.preview', $draft))->assertStatus(409);
});

test('other_user_cannot_open_html_wizard_or_review', function () {
    $draft = ($this->completeDraft)();
    $other = User::factory()->create(['status' => 'active', 'must_change_password' => false]);
    $other->givePermissionTo('sidang_ta_registration_read');
    $this->actingAs($other)->get(route('sidang-ta-drafts.edit', $draft))->assertForbidden();
    $this->get(route('sidang-ta-drafts.preview', $draft))->assertForbidden();
});

test('html_validation_preserves_stale_version_until_conflict_is_resolved', function () {
    $draft = ($this->completeDraft)();
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 1]),
        array_replace($this->data, ['version' => $draft->version, 'student_phone' => '089999999999']))->assertOk();
    $url = route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 1]);
    $this->from($url)->post(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 1]),
        array_replace($this->data, ['version' => $draft->version, 'student_phone' => '']))
        ->assertRedirect($url)->assertSessionHasErrors('student_phone');
    $this->get($url)->assertOk()->assertSee('name="version" value="'.$draft->version.'"', false);
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 1]),
        array_replace($this->data, ['version' => $draft->version, 'student_phone' => '080000000000']))->assertStatus(409);
    expect($draft->fresh()->payload['student_phone'])->toBe('089999999999');
});

test('preview_returns_loaded_snapshot_before_concurrent_branch_change', function () {
    $draft = ($this->completeDraft)();
    $this->postJson(route('sidang-ta-drafts.review', $draft), ['version' => $draft->version])->assertOk();
    $snapshot = app(\App\Services\SidangTaDraftReviewService::class)->preview($this->user, $draft);
    expect($snapshot->relationLoaded('documents'))->toBeTrue();
    expect($snapshot->relationLoaded('period'))->toBeTrue();
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 4]), [
        'version' => $draft->version, 'diploma_biodata_accurate' => false,
        'documents' => ['pdm_submission_proof' => \Illuminate\Http\UploadedFile::fake()->create('pdm.pdf', 100, 'application/pdf')],
    ])->assertOk();
    expect($snapshot->payload['diploma_biodata_accurate'])->toBeTrue();
    expect($snapshot->documents->count())->toBe(7);
    expect($snapshot->documents->firstWhere('document_type', 'diploma_statement'))->not->toBeNull();
});

test('future_active_period_appears_only_after_opening_time', function () {
    try {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-02 12:00:00', config('app.timezone')));
        $this->period->update([
            'open_at' => '2026-10-31 23:59:00',
            'close_at' => '2026-11-05 23:59:00',
        ]);
        $future = SidangTaPeriod::create([
            'name' => 'Periode 2 masa depan', 'is_active' => true,
            'open_at' => '2026-11-06 23:59:00', 'close_at' => '2026-11-30 23:56:00',
        ]);
        $response = $this->get(route('sidang-ta-registrations.create'))->assertOk();
        expect(sidangTaWizardPeriodIds($response->getContent()))->toBe([$this->period->id]);

        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-07 12:00:00', config('app.timezone')));
        $response = $this->get(route('sidang-ta-registrations.create'))->assertOk();
        expect(sidangTaWizardPeriodIds($response->getContent()))->toBe([$future->id]);
    } finally {
        $this->travelBack();
    }
});

test('new_registration_select_contains_both_overlapping_open_period_ids', function () {
    $other = SidangTaPeriod::create([
        'name' => 'Periode terbuka kedua', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay(),
    ]);
    $response = $this->get(route('sidang-ta-registrations.create'))->assertOk();
    $expected = [$this->period->id, $other->id];
    sort($expected);
    expect(sidangTaWizardPeriodIds($response->getContent()))->toBe($expected);
});

test('resumed_new_draft_select_keeps_all_currently_open_period_ids', function () {
    $other = SidangTaPeriod::create([
        'name' => 'Periode terbuka kedua', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay(),
    ]);
    $id = $this->postJson(route('sidang-ta-drafts.store'), $this->data)
        ->assertCreated()->json('data.id');
    $response = $this->get(route('sidang-ta-drafts.edit', ['draft' => $id, 'step' => 1]))->assertOk();
    $expected = [$this->period->id, $other->id];
    sort($expected);
    expect(sidangTaWizardPeriodIds($response->getContent()))->toBe($expected);
});

test('empty_step_query_uses_first_incomplete_step', function () {
    $id = $this->postJson(route('sidang-ta-drafts.store'), $this->data)
        ->assertCreated()->json('data.id');
    $this->get(route('sidang-ta-drafts.edit', $id).'?step=')->assertOk()
        ->assertViewIs('sidang_ta_registrations.wizard')
        ->assertViewHas('step', 2)
        ->assertSee('data-current-step="2"', false);
});
```

### `tests/Feature/SidangTaWizardUiTest.php`

```php
<?php

use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDraft;
use App\Models\Student;
use App\Models\User;
use App\Support\SidangTaDocumentRequirements as Requirements;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create([
        'username' => '1202200001', 'email' => 'wizard-ui@mahasiswa.test',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false,
    ]);
    $this->student = Student::create([
        'student_number' => '1202200001', 'name' => 'Wizard UI Test',
        'email' => $this->user->email, 'academic_year' => 2022,
        'study_program' => 'Informatika', 'status' => 'Aktif', 'gpa' => 0,
    ]);
    foreach (['read', 'create', 'update'] as $action) {
        $this->user->givePermissionTo(Permission::firstOrCreate([
            'name' => "sidang_ta_registration_{$action}", 'guard_name' => 'web',
        ]));
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->period = SidangTaPeriod::create([
        'name' => 'Periode G1', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay(),
    ]);
    $this->data = ['sidang_ta_period_id' => $this->period->id,
        'student_phone' => '081234567890', 'parent_phone' => '081234567891'];
    $this->actingAs($this->user);
    $this->completeDraft = function () {
        $id = $this->postJson(route('sidang-ta-drafts.store'), $this->data)
            ->assertCreated()->json('data.id');
        $draft = SidangTaRegistrationDraft::findOrFail($id);
        expect($draft->requirements_version)->toBe(2);
        foreach ([2, 3, 4] as $step) {
            $data = ['version' => $draft->fresh()->version];
            if ($step === 3) $data['graduation_payment_date'] = today()->toDateString();
            if ($step === 4) $data['diploma_biodata_accurate'] = true;
            foreach (Requirements::typesForStep($step, true) as $type) {
                $data['documents'][$type] = \Illuminate\Http\UploadedFile::fake()
                    ->create($type.'.pdf', 100, 'application/pdf');
            }
            $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => $step]), $data)
                ->assertOk();
        }
        $draft = $draft->fresh();
        expect($draft->documents()->count())->toBe(7);
        return $draft;
    };
});

function sidangTaUiXpath(string $html): DOMXPath
{
    $previous = libxml_use_internal_errors(true);
    try {
        $document = new DOMDocument();
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET);
        return new DOMXPath($document);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
}

test('g1_fresh_step_one_is_active_without_creating_draft_or_unlocking_future_steps', function () {
    $response = $this->get(route('sidang-ta-registrations.create'))->assertOk();
    $xpath = sidangTaUiXpath($response->getContent());
    $active = $xpath->query('//*[@data-step="1" and @aria-current="step"]')->item(0);
    expect($active)->not->toBeNull();
    expect($active->getAttribute('class'))->toContain('bg-[#DA2D3B]');
    expect($xpath->query('//*[@aria-current="step"]')->length)->toBe(1);
    expect($xpath->query('//a[@data-step="2" or @data-step="3" or @data-step="4"]')->length)->toBe(0);
    expect($xpath->query('//*[@data-step="2" and @aria-disabled="true"]')->length)->toBe(1);
    expect($xpath->query('//*[@data-chevron="1"]')->item(0)->getAttribute('class'))->toContain('text-[#DA2D3B]');
    expect($xpath->query('//section[@aria-label="Progres pendaftaran"]//*[@data-save-status]')->length)->toBe(1);
    $response->assertSee('Belum ada data yang disimpan.');
    expect(SidangTaRegistrationDraft::count())->toBe(0);
});

test('g1_step_one_errors_are_inline_and_preserve_valid_contact_input', function () {
    $url = route('sidang-ta-registrations.create');
    $this->from($url)->post(route('sidang-ta-drafts.store'), [
        'sidang_ta_period_id' => '', 'student_phone' => '', 'parent_phone' => '089123456789',
    ])->assertRedirect($url)->assertSessionHasErrors(['sidang_ta_period_id', 'student_phone']);
    $response = $this->get($url)->assertOk();
    $xpath = sidangTaUiXpath($response->getContent());
    expect($xpath->query('//form[@id="sidang-ta-step-form" and @novalidate]')->length)->toBe(1);
    expect($xpath->query('//*[@id="error-sidang_ta_period_id"]')->length)->toBe(1);
    expect($xpath->query('//*[@id="error-student_phone"]')->item(0)->textContent)->toContain('Nomor HP mahasiswa wajib diisi.');
    expect($xpath->query('//input[@name="student_phone" and @aria-invalid="true"]')->length)->toBe(1);
    expect($xpath->query('//input[@name="parent_phone"]')->item(0)->getAttribute('value'))->toBe('089123456789');
    expect(SidangTaRegistrationDraft::count())->toBe(0);
});

test('g1_academic_panel_is_present_on_all_four_steps_and_keeps_zero_gpa', function () {
    $this->student->finalProject()->create([
        'thesis_title_id' => 'Judul akademik untuk G1',
        'thesis_title_en' => 'Academic title for G1', 'advisor_1' => 'Dosen Pembimbing G1',
    ]);
    $draft = ($this->completeDraft)();
    foreach ([1, 2, 3, 4] as $step) {
        $response = $this->get(route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => $step]))
            ->assertOk()->assertSee('Judul akademik untuk G1')->assertSee('Academic title for G1')
            ->assertDontSee('Dosen Pembimbing G1')->assertSee('0,00')
            ->assertSee('KRS Sidang TA 2')->assertDontSee('Pembimbing &amp; Penguji', false)
            ->assertDontSee('Jadwal sidang TA')->assertDontSee('Sidang tingkat 3')
            ->assertSee('Status BPP')->assertDontSee('Status i-Basila')->assertDontSee('Ketersediaan TAK');
        $xpath = sidangTaUiXpath($response->getContent());
        expect($xpath->query('//aside[@data-academic-panel]')->length)->toBe(1);
        expect($xpath->query('//aside[@data-academic-panel]//*[@data-save-status]')->length)->toBe(0);
    }
    expect(SidangTaRegistration::count())->toBe(0);
});


test('g1_saved_draft_still_cannot_navigate_to_unfinished_future_step', function () {
    $id = $this->postJson(route('sidang-ta-drafts.store'), $this->data)->assertCreated()->json('data.id');
    $response = $this->get(route('sidang-ta-drafts.edit', ['draft' => $id, 'step' => 2]))->assertOk();
    $xpath = sidangTaUiXpath($response->getContent());
    expect($xpath->query('//a[@data-step="1"]')->length)->toBe(1);
    expect($xpath->query('//*[@data-step="2" and @aria-current="step"]')->length)->toBe(1);
    expect($xpath->query('//a[@data-step="3" or @data-step="4"]')->length)->toBe(0);
    $this->get(route('sidang-ta-drafts.edit', ['draft' => $id, 'step' => 4]))
        ->assertRedirect(route('sidang-ta-drafts.edit', ['draft' => $id, 'step' => 2]));
});

test('g1_payment_date_error_is_inline_and_does_not_change_saved_documents', function () {
    $draft = ($this->completeDraft)();
    $url = route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 3]);
    $this->from($url)->post(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 3]), [
        'version' => $draft->version, 'graduation_payment_date' => today()->addDay()->toDateString(),
    ])->assertRedirect($url)->assertSessionHasErrors('graduation_payment_date');
    $response = $this->get($url)->assertOk();
    $xpath = sidangTaUiXpath($response->getContent());
    expect($xpath->query('//*[@id="error-graduation_payment_date"]')->item(0)->textContent)
        ->toContain('Tanggal pembayaran wisuda tidak boleh melewati hari ini.');
    expect($draft->fresh()->version)->toBe($draft->version);
    expect($draft->documents()->count())->toBe(7);
});

test('g1_inactive_biodata_upload_is_initially_disabled_and_has_dynamic_binding', function () {
    $draft = ($this->completeDraft)();
    $response = $this->get(route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 4]))->assertOk();
    $xpath = sidangTaUiXpath($response->getContent());
    $inactive = $xpath->query('//input[@id="file-pdm_submission_proof"]')->item(0);
    expect($inactive->hasAttribute('disabled'))->toBeTrue();
    expect($inactive->getAttribute(':disabled'))->toBe("accurate !== '0'");
    expect($xpath->query('//input[@id="file-diploma_statement" and @disabled]')->length)->toBe(0);
});

test('g1_non_field_errors_remain_visible_in_summary', function () {
    $draft = ($this->completeDraft)();
    $errors = (new ViewErrorBag())->put('default', new MessageBag([
        'version' => ['Versi draft tidak valid. Buka ulang draft terbaru.'],
        'documents' => ['Jenis dokumen tidak sesuai tahap atau cabang yang dipilih.'],
    ]));
    $response = $this->withSession(['errors' => $errors])
        ->get(route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 2]))->assertOk();
    $xpath = sidangTaUiXpath($response->getContent());
    $summary = $xpath->query('//*[@data-error-summary]')->item(0);
    expect($summary->textContent)->toContain('Versi draft tidak valid.')
        ->toContain('Jenis dokumen tidak sesuai tahap');
});

test('g1_invalid_biodata_error_is_attached_to_radio_group', function () {
    $draft = ($this->completeDraft)();
    $url = route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 4]);
    $this->from($url)->post(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 4]), [
        'version' => $draft->version, 'diploma_biodata_accurate' => 'invalid',
    ])->assertRedirect($url)->assertSessionHasErrors('diploma_biodata_accurate');
    $response = $this->get($url)->assertOk();
    $xpath = sidangTaUiXpath($response->getContent());
    expect($xpath->query('//fieldset[@id="diploma-biodata-group" and @aria-invalid="true"]')->length)->toBe(1);
    expect($xpath->query('//*[@id="error-diploma_biodata_accurate"]')->item(0)->textContent)
        ->toContain('Pilihan kesesuaian biodata tidak valid.');
    expect($draft->fresh()->version)->toBe($draft->version);
    expect($draft->documents()->count())->toBe(7);
});

test('g1_missing_saved_file_keeps_step_incomplete_and_preserves_other_saved_files', function () {
    $draft = ($this->completeDraft)();
    $document = $draft->documents()->where('document_type', Requirements::CAPTURES[0])->firstOrFail();
    $other = $draft->documents()->where('document_type', Requirements::CAPTURES[1])->firstOrFail();
    Storage::disk('local')->delete($document->file_path);
    $url = route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 2]);
    $this->from($url)->post(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 2]), [
        'version' => $draft->version,
    ])->assertRedirect($url)->assertSessionHasNoErrors();
    $response = $this->get($url)->assertOk();
    $crawler = new \Symfony\Component\DomCrawler\Crawler($response->getContent());
    expect($crawler->filter('[data-step-progress]')->text(null, true))->toContain('1 dari 2 dokumen tersimpan.')
        ->toContain(\App\Models\SidangTaRegistration::DOCUMENT_TYPES[$document->document_type]);
    expect($crawler->filter('button[data-next-step][disabled]')->count())->toBe(1);
    $response->assertDontSee(route('sidang-ta-draft-documents.download', $other), false)
        ->assertDontSee(route('sidang-ta-draft-documents.download', $document), false)
        ->assertSee($other->file_name);
    expect(Storage::disk('local')->exists($other->file_path))->toBeTrue();
    expect($draft->fresh()->version)->toBe($draft->version + 1);
    expect($draft->documents()->count())->toBe(7);
});

test('meet_krs_requires_one_active_period_and_explicit_ta2_name', function () {
    \App\Models\AcademicPeriod::query()->update(['is_active' => false]);
    $service = app(\App\Services\SidangTaAcademicSnapshot::class);
    expect($service->ta2KrsStatus($this->student))->toBe('unknown');
    $period = \App\Models\AcademicPeriod::create(['year' => '2026/2027', 'semester' => 1,
        'label' => 'Meet KRS', 'start_date' => '2026-08-01', 'end_date' => '2027-01-31', 'is_active' => true]);
    $course = \App\Models\Course::create(['course_code' => 'MEET-TA2', 'name' => 'TUGAS AKHIR 2',
        'academic_year' => '2026/2027', 'semester_term' => 'GANJIL', 'study_program' => 'Informatika',
        'class' => 'A', 'semester' => 8, 'credits' => 4, 'type' => 'Wajib', 'status' => 'Aktif']);
    $row = \App\Models\StudentCourse::create(['student_number' => $this->student->student_number,
        'course_code' => $course->course_code, 'course_name' => 'TUGAS AKHIR', 'course_class' => 'A',
        'academic_year' => '2026/2027', 'semester' => 8, 'academic_period_id' => $period->id]);
    foreach (['TUGAS AKHIR', 'TUGAS AKHIR 1', 'TUGAS AKHIR 20', 'PROPOSAL TUGAS AKHIR'] as $name) {
        $row->update(['course_name' => $name]);
        expect($service->ta2KrsStatus($this->student))->toBe('unconfirmed');
    }
    $row->update(['course_name' => '  tugas akhir  2  ']);
    expect($service->ta2KrsStatus($this->student))->toBe('eligible');
    $snapshot = $service->forStudent($this->student->fresh());
    expect($snapshot['ta2_krs_status'])->toBe('eligible');
    expect($snapshot['ta2_krs_label'])->toBe('Terdaftar');
    $row->update(['academic_period_id' => null]);
    expect($service->ta2KrsStatus($this->student))->toBe('unknown');
    $row->update(['academic_period_id' => $period->id]);
    $period->update(['is_active' => false]);
    expect($service->ta2KrsStatus($this->student))->toBe('unknown');
});
```

## File yang dipensiunkan setelah replacement dan suite lulus

Hapus tepat tujuh file berikut di kedua clone setelah seluruh file pengganti diterapkan dan targeted tests yang baru lulus, tetapi sebelum menjalankan wildcard full suite yang sebaliknya akan memanggil test khusus versi lama. Ini menghapus test/view khusus perilaku lama yang tidak lagi menjadi fitur, bukan assertion valid yang ditutup-tutupi.

- `tests/Feature/SidangTaFinalizationTest.php`
- `tests/Feature/SidangTaRequirementsVersionTest.php`
- `tests/Feature/SidangTaRevisionDraftTest.php`
- `tests/Feature/SidangTaDocumentReviewTest.php`
- `tests/Feature/SidangTaReviewFeedbackTest.php`
- `resources/views/sidang_ta_registrations/form.blade.php`
- `resources/views/sidang_ta_registrations/partials/upload.blade.php`

Coverage pengganti mencakup active/consumed marker selain2, create/replay/finalisasi dan rollback, endpoint/source revision, permission/deadline/discard, document feedback/log, current snapshot/export, serta partial save dan preview. `SidangTaPartialDraftTest` tetap dipertahankan.
