<?php

use App\Models\Lecturer;
use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDraft;
use App\Models\Student;
use App\Models\User;
use App\Services\SidangTaExpiredDraftCleanup;
use App\Services\SidangTaRevisionExpiry;
use App\Support\SidangTaReviewEligibility;
use App\Support\SidangTaRevisionDeadline;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Symfony\Component\DomCrawler\Crawler;

beforeEach(function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-11-13 12:00:00', 'Asia/Jakarta'));
    Storage::fake('local');
    foreach (['sidang_ta_registration_review', 'sidang_ta_registration_read', 'sidang_ta_registration_update', 'sidang_ta_registration_view_program'] as $permission) Permission::findOrCreate($permission, 'web');
    $this->reviewer = User::factory()->create(['status' => 'active']);
    $this->reviewer->givePermissionTo('sidang_ta_registration_review');
    $this->owner = User::factory()->create(['username' => '1080001', 'status' => 'active']);
    $this->owner->givePermissionTo(['sidang_ta_registration_read', 'sidang_ta_registration_update']);
    $this->student = Student::create(['student_number' => '1080001', 'name' => 'Mahasiswa108', 'email' => $this->owner->email,
        'academic_year' => 2022, 'study_program' => 'Informatika', 'status' => 'Aktif']);
    $this->period = SidangTaPeriod::create(['name' => 'Periode108', 'open_at' => now()->subDays(10), 'close_at' => now()->subDays(5),
        'is_active' => false, 'is_revision_active' => true, 'revision_open_at' => now()->subDays(4), 'revision_close_at' => now()->subDay()]);
    $this->registration = SidangTaRegistration::create(['sidang_ta_period_id' => $this->period->id,
        'student_number' => '1080001', 'student_name' => 'Mahasiswa108', 'study_program' => 'Informatika',
        'student_phone' => '081234567890', 'parent_phone' => '081234567891', 'graduation_payment_date' => today(),
        'diploma_biodata_accurate' => true, 'status' => 'rejected', 'submitted_by' => $this->owner->id,
        'submitted_at' => now()->subDays(6), 'review_notes' => 'Perbaiki naskah', 'reviewed_by' => $this->reviewer->id,
        'academic_snapshot' => ['ta_title' => 'Judul Historis', 'admission' => ['eprt' => ['mode' => 'automatic']]],
    ])->fresh();
    foreach (SidangTaReviewEligibility::requiredTypes($this->registration) as $type) {
        $path = 'sidang_ta/108/'.$type.'.pdf'; Storage::disk('local')->put($path, '%PDF-1.4 original');
        $this->registration->documents()->create(['document_type' => $type, 'file_name' => $type.'.pdf',
            'file_path' => $path, 'file_type' => 'pdf', 'file_size' => 18,
            'status' => $type === 'thesis_draft' ? 'rejected' : 'verified', 'notes' => $type === 'thesis_draft' ? 'Perbaiki naskah' : null]);
    }
    $this->registration->logAction('rejected', 'submitted', 'rejected', 'Ditolak manual', $this->reviewer->id);
    $this->actingAs($this->reviewer);
    $this->reopen = fn (array $overrides = []) => $this->postJson(route('admin.sidang-ta-registrations.review', $this->registration),
        array_replace(['action' => 'reopen_registration', 'registration_version' => $this->registration->fresh()->lock_version], $overrides));
    $this->extendVerification = function () { $this->period->update(['revision_open_at' => now()->subHour(), 'revision_close_at' => now()->addDay(), 'is_revision_active' => true]); };
    $this->requestRevision = fn () => $this->postJson(route('admin.sidang-ta-registrations.review', $this->registration), [
        'action' => 'needs_review', 'registration_version' => $this->registration->fresh()->lock_version, 'review_notes' => 'Perbaiki naskah TA']);
});

afterEach(function () { $this->travelBack(); });



test('verification108 closed rejected application warns to extend dates and cannot be reopened', function () {
    $page = new Crawler($this->get(route('admin.sidang-ta-registrations.show', $this->registration))->assertOk()->getContent());
    expect($page->filter('[data-ta-reopen]')->count())->toBe(1)
        ->and($page->filter('[data-ta-verification-warning]')->count())->toBe(1)
        ->and($page->filter('input[name="action"][value="reopen_registration"]')->count())->toBe(0);
    ($this->reopen)()->assertUnprocessable()->assertJsonValidationErrors('action');
    expect($this->registration->fresh()->status)->toBe('rejected');
});

test('verification108 manual and automatic rejection can reopen only after extending active verification dates', function (bool $automatic) {
    if ($automatic) {
        $this->registration->update(['reviewed_by' => null, 'review_notes' => SidangTaRevisionExpiry::NOTE]);
        $this->registration->logAction('rejected', 'needs_review', 'rejected', SidangTaRevisionExpiry::NOTE, null);
    }
    $before = $this->registration->fresh()->getRawOriginal();
    $documents = $this->registration->documents()->orderBy('id')->get()->map->getRawOriginal()->all();
    ($this->reopen)()->assertUnprocessable();
    ($this->extendVerification)();
    ($this->reopen)()->assertRedirect();
    $fresh = $this->registration->fresh();
    expect($fresh->status)->toBe('submitted');
    foreach (['student_number', 'student_name', 'academic_snapshot', 'submitted_at', 'review_notes'] as $field) expect($fresh->getRawOriginal($field))->toBe($before[$field]);
    expect($fresh->documents()->orderBy('id')->get()->map->getRawOriginal()->all())->toBe($documents);
    expect($fresh->logs()->where('action', 'rejected')->count())->toBe($automatic ? 2 : 1);
    expect($fresh->logs()->latest('id')->firstOrFail()->action)->toBe('reopened');
})->with([false, true]);

test('verification108 expired revision button only opens warning and works again after extension', function () {
    $this->registration->update(['status' => 'submitted']);
    $page = new Crawler($this->get(route('admin.sidang-ta-registrations.show', $this->registration))->assertOk()->getContent());
    expect($page->filter('[data-ta-request-revision][disabled]')->count())->toBe(1)
        ->and($page->filter('[data-ta-window-warning-trigger]')->count())->toBe(1)
        ->and($page->filter('[data-ta-verification-warning]')->count())->toBe(1)
        ->and($page->filter('button[name="action"][value="needs_review"]:not([disabled])')->count())->toBe(0);
    ($this->requestRevision)()->assertUnprocessable();
    ($this->extendVerification)();
    $page = new Crawler($this->get(route('admin.sidang-ta-registrations.show', $this->registration))->assertOk()->getContent());
    expect($page->filter('[data-ta-request-revision][type="submit"]:not([disabled])')->count())->toBe(1);
    ($this->requestRevision)()->assertRedirect();
    $this->actingAs($this->owner);
    $id = $this->postJson(route('sidang-ta-drafts.revision', $this->registration))->assertCreated()->json('data.id');
    $draft = SidangTaRegistrationDraft::findOrFail($id);
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 2]), [
        'version' => $draft->version,
        'documents' => ['thesis_draft' => \Illuminate\Http\UploadedFile::fake()->create('pengganti.pdf', 100, 'application/pdf')],
    ])->assertOk();
    $this->period->update(['revision_close_at' => now()->subSecond()]);
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 2]), [
        'version' => $draft->fresh()->version,
        'documents' => ['thesis_draft' => \Illuminate\Http\UploadedFile::fake()->create('terlambat.pdf', 100, 'application/pdf')],
    ])->assertUnprocessable()->assertJsonValidationErrors('draft');
    expect(app(SidangTaRevisionExpiry::class)->run())->toBe(1);
    app(SidangTaExpiredDraftCleanup::class)->run();
    expect(SidangTaRegistrationDraft::find($id))->toBeNull()->and($this->registration->fresh()->status)->toBe('rejected');
});

test('verification108 disabled future or incomplete windows cannot reopen even with forged POST', function (string $state) {
    ($this->extendVerification)();
    $this->period->update(match ($state) {
        'disabled' => ['is_revision_active' => false],
        'future' => ['revision_open_at' => now()->addHour()],
        'missing_start' => ['revision_open_at' => null],
        'missing_end' => ['revision_close_at' => null],
    });
    ($this->reopen)()->assertUnprocessable()->assertJsonValidationErrors('action');
    expect($this->registration->fresh()->status)->toBe('rejected');
})->with(['disabled', 'future', 'missing_start', 'missing_end']);

test('verification108 backend reloads verification dates even if earlier page offered confirmation', function () {
    ($this->extendVerification)();
    $this->get(route('admin.sidang-ta-registrations.show', $this->registration))->assertOk()->assertSee('Ya, buka kembali');
    $this->period->update(['revision_close_at' => now()->subSecond()]);
    ($this->reopen)()->assertUnprocessable();
    expect($this->registration->fresh()->status)->toBe('rejected')->and($this->registration->logs()->count())->toBe(1);
});

test('verification108 stale and repeated reopen cannot create duplicate history', function () {
    ($this->extendVerification)();
    ($this->reopen)()->assertRedirect();
    ($this->reopen)(['registration_version' => 1])->assertConflict();
    ($this->reopen)()->assertUnprocessable();
    expect($this->registration->logs()->where('action', 'reopened')->count())->toBe(1);
});

test('verification108 another active registration prevents reopening', function () {
    ($this->extendVerification)();
    $other = $this->registration->replicate(); $other->status = 'submitted'; $other->save();
    ($this->reopen)()->assertConflict();
    expect($this->registration->fresh()->status)->toBe('rejected');
});

test('verification108 student and lecturer reader cannot reopen or see reviewer controls', function () {
    ($this->extendVerification)();
    $this->actingAs($this->owner); ($this->reopen)()->assertForbidden();
    $reader = User::factory()->create(['status' => 'active']); $reader->givePermissionTo('sidang_ta_registration_view_program');
    Lecturer::create(['lecturer_code' => '108D', 'name' => 'Dosen', 'email_domain' => $reader->email, 'study_program' => 'Informatika']);
    $this->actingAs($reader); ($this->reopen)()->assertForbidden();
    $page = new Crawler($this->get(route('admin.sidang-ta-registrations.show', $this->registration))->assertOk()->getContent());
    expect($page->filter('[data-ta-reopen], [data-ta-verification-warning]')->count())->toBe(0);
});

test('verification108 warning offers period management only to period readers', function () {
    $page = new Crawler($this->get(route('admin.sidang-ta-registrations.show', $this->registration))->assertOk()->getContent());
    expect($page->filter('[data-ta-verification-warning] a[data-extend-verification]')->count())->toBe(0);
    $this->reviewer->givePermissionTo(Permission::findOrCreate('sidang_ta_period_read', 'web'));
    $page = new Crawler($this->get(route('admin.sidang-ta-registrations.show', $this->registration))->assertOk()->getContent());
    expect($page->filter('[data-ta-verification-warning] a[data-extend-verification]')->attr('href'))->toBe(route('admin.sidang-ta-periods.index'));
});

test('verification108 old stale draft is retired without deleting borrowed final file', function () {
    $draft = SidangTaRegistrationDraft::create(['owner_user_id' => $this->owner->id, 'student_number' => '1080001',
        'sidang_ta_period_id' => $this->period->id, 'source_registration_id' => $this->registration->id, 'source_version' => 1]);
    $document = $this->registration->documents()->firstOrFail();
    $draft->documents()->create(['document_type' => $document->document_type, 'file_name' => $document->file_name,
        'file_path' => $document->file_path, 'source_document_id' => $document->id]);
    ($this->extendVerification)(); ($this->reopen)()->assertRedirect();
    expect($draft->fresh())->toBeNull(); Storage::disk('local')->assertExists($document->file_path);
    ($this->requestRevision)()->assertRedirect();
    $this->actingAs($this->owner)->postJson(route('sidang-ta-drafts.revision', $this->registration))->assertCreated();
});
