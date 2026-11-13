<?php

use App\Models\Lecturer;
use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\Student;
use App\Models\User;
use App\Services\SidangTaRegistrationReview;
use App\Support\SidangTaReviewEligibility;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Symfony\Component\DomCrawler\Crawler;

beforeEach(function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-11-13 12:00:00', 'Asia/Jakarta'));
    Storage::fake('local');
    foreach (['sidang_ta_registration_review', 'sidang_ta_registration_read', 'sidang_ta_registration_view_program'] as $name) {
        Permission::findOrCreate($name, 'web');
    }
    $this->reviewer = User::factory()->create(['name' => 'Reviewer 107', 'status' => 'active']);
    $this->reviewer->givePermissionTo('sidang_ta_registration_review');
    $this->owner = User::factory()->create(['username' => '1070001', 'status' => 'active']);
    $this->owner->givePermissionTo('sidang_ta_registration_read');
    $this->student = Student::create(['student_number' => '1070001', 'name' => 'Mahasiswa 107',
        'email' => $this->owner->email, 'academic_year' => 2022, 'study_program' => 'Informatika', 'status' => 'Aktif']);
    $this->period = SidangTaPeriod::create(['name' => 'Verifikasi 107', 'is_active' => false,
        'open_at' => now()->subDays(4), 'close_at' => now()->subDays(2),
        'is_revision_active' => true, 'revision_open_at' => now()->subDay(), 'revision_close_at' => now()->addDays(7),
        'show_to_students' => false]);
    $this->registration = SidangTaRegistration::create([
        'sidang_ta_period_id' => $this->period->id, 'student_number' => $this->student->student_number,
        'student_name' => $this->student->name, 'study_program' => 'Informatika',
        'student_phone' => '081234567890', 'parent_phone' => '081234567891',
        'graduation_payment_date' => today(), 'diploma_biodata_accurate' => true, 'status' => 'submitted',
        'submitted_by' => $this->owner->id, 'submitted_at' => now()->subDays(3),
        'academic_snapshot' => ['student_name' => 'Nama Historis', 'ta_title' => 'Judul Historis',
            'admission' => ['eprt' => ['mode' => 'automatic']]],
    ])->fresh();
    foreach (SidangTaReviewEligibility::requiredTypes($this->registration) as $type) {
        $path = 'sidang_ta/107/'.$type.'.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 existing');
        $this->registration->documents()->create(['document_type' => $type, 'file_path' => $path,
            'file_name' => $type.'.pdf', 'file_type' => 'pdf', 'file_size' => 18, 'status' => 'verified']);
    }
    $this->actingAs($this->reviewer)->postJson(route('admin.sidang-ta-registrations.review', $this->registration), [
        'action' => 'approved', 'registration_version' => 1, 'review_notes' => 'Catatan persetujuan tetap ada',
    ])->assertRedirect();
    $this->registration = $this->registration->fresh();
    $this->cancel = fn (array $overrides = []) => $this->postJson(route('admin.sidang-ta-registrations.review', $this->registration),
        array_replace(['action' => 'cancel_approval', 'registration_version' => $this->registration->fresh()->lock_version], $overrides));
    $this->page = fn () => new Crawler($this->get(route('admin.sidang-ta-registrations.show', $this->registration))->assertOk()->getContent());
});

afterEach(function () { $this->travelBack(); });

test('approval cancellation reopens review and preserves every document and submission field', function () {
    $before = $this->registration->getRawOriginal();
    $documents = $this->registration->documents()->orderBy('id')->get()->map->getRawOriginal()->all();
    $files = collect(Storage::disk('local')->allFiles())->mapWithKeys(fn ($path) => [$path => Storage::disk('local')->get($path)])->all();
    ($this->cancel)()->assertRedirect();
    $fresh = $this->registration->fresh();
    expect($fresh->status)->toBe('submitted')->and($fresh->lock_version)->toBe(3)
        ->and($fresh->reviewed_at)->toBeNull()->and($fresh->reviewed_by)->toBeNull();
    foreach ($before as $key => $value) {
        if (!in_array($key, ['status', 'lock_version', 'reviewed_by', 'reviewed_at', 'updated_at'], true)) {
            expect($fresh->getRawOriginal($key))->toBe($value);
        }
    }
    expect($fresh->documents()->orderBy('id')->get()->map->getRawOriginal()->all())->toBe($documents);
    expect(collect(Storage::disk('local')->allFiles())->mapWithKeys(fn ($path) => [$path => Storage::disk('local')->get($path)])->all())->toBe($files);
    $this->assertDatabaseCount('sidang_ta_registration_drafts', 0);
    $this->assertDatabaseCount('sidang_ta_registrations', 1);
    $log = $fresh->logs()->latest('id')->firstOrFail();
    expect($log->action)->toBe('approval_cancelled')->and($log->from_status)->toBe('approved')
        ->and($log->to_status)->toBe('submitted')->and((int) $log->performed_by)->toBe($this->reviewer->id)
        ->and($fresh->logs()->where('action', 'approved')->count())->toBe(1);
});

test('approved UI has cancellation confirmation instead of decision controls then restores green review cards', function () {
    $page = ($this->page)();
    expect($page->filter('[data-ta-cancel-approval]:not([disabled])')->count())->toBe(1)
        ->and($page->filter('[data-ta-cancel-confirm]')->count())->toBe(1)
        ->and($page->filter('[data-ta-decision], [data-doc-review-form]')->count())->toBe(0);
    expect($page->filter('[data-ta-cancel-form] input[name="registration_version"]')->attr('value'))->toBe('2');
    ($this->cancel)()->assertRedirect();
    $page = ($this->page)();
    expect($page->filter('[data-ta-cancel-approval]')->count())->toBe(0)
        ->and($page->filter('[data-ta-decision]')->count())->toBe(1)
        ->and($page->filter('[data-document-review-state="verified"]')->count())->toBe(7)
        ->and($page->filter('[data-doc-review-form]')->count())->toBe(7);
    foreach ($page->filter('[data-document-review-state="verified"]') as $node) {
        expect($node->getAttribute('class'))->toContain('bg-emerald-50');
    }
});

test('cancellation is rejected outside configured active verification window without changing data', function (string $condition) {
    $changes = match ($condition) {
        'expired' => ['revision_close_at' => now()->subSecond()],
        'future' => ['revision_open_at' => now()->addSecond()],
        'disabled' => ['is_revision_active' => false],
        'unconfigured' => ['revision_open_at' => null, 'revision_close_at' => null],
        'missing_start' => ['revision_open_at' => null],
        'missing_end' => ['revision_close_at' => null],
    };
    $this->period->update($changes);
    $before = $this->registration->getRawOriginal();
    ($this->cancel)()->assertUnprocessable()->assertJsonValidationErrors('action');
    expect($this->registration->fresh()->getRawOriginal())->toBe($before)
        ->and($this->registration->documents()->where('status', 'verified')->count())->toBe(7)
        ->and($this->registration->logs()->count())->toBe(1);
    $page = ($this->page)();
    expect($page->filter('[data-ta-cancel-approval][disabled]')->count())->toBe(1)
        ->and($page->filter('[data-ta-cancel-blocker]')->count())->toBe(1);
})->with(['expired', 'future', 'disabled', 'unconfigured', 'missing_start', 'missing_end']);

test('verification boundaries are inclusive independently of closed registration and hidden schedule', function (string $boundary) {
    $this->travelTo($boundary === 'start' ? $this->period->revision_open_at : $this->period->revision_close_at);
    ($this->cancel)()->assertRedirect();
    expect($this->registration->fresh()->status)->toBe('submitted');
})->with(['start', 'end']);

test('a page opened earlier cannot cancel after verification expires or dates are changed', function () {
    expect(($this->page)()->filter('[data-ta-cancel-approval]:not([disabled])')->count())->toBe(1);
    $loaded = $this->registration->load('period');
    $this->period->update(['revision_close_at' => now()->subSecond()]);
    $this->expectException(\Illuminate\Validation\ValidationException::class);
    try {
        app(SidangTaRegistrationReview::class)->cancelApproval($this->reviewer, $loaded, 2);
    } finally {
        expect($this->registration->fresh()->status)->toBe('approved')
            ->and($this->registration->logs()->count())->toBe(1);
    }
});

test('double click and stale versions cannot cancel twice or overwrite another reviewer', function () {
    ($this->cancel)(['registration_version' => 1])->assertConflict();
    ($this->cancel)(['registration_version' => 2])->assertRedirect();
    ($this->cancel)(['registration_version' => 2])->assertConflict();
    ($this->cancel)()->assertUnprocessable();
    expect($this->registration->fresh()->lock_version)->toBe(3)
        ->and($this->registration->logs()->where('action', 'approval_cancelled')->count())->toBe(1);
});

test('only approved registrations can have approval cancelled', function (string $status) {
    $this->registration->update(['status' => $status]);
    ($this->cancel)()->assertUnprocessable();
    expect($this->registration->fresh()->status)->toBe($status)
        ->and($this->registration->logs()->count())->toBe(1);
    expect(($this->page)()->filter('[data-ta-cancel-approval]')->count())->toBe(0);
})->with(['submitted', 'needs_review', 'rejected']);

test('student and program reader cannot cancel even through a forged POST', function (string $type) {
    if ($type === 'student') $user = $this->owner;
    else {
        $user = User::factory()->create(['status' => 'active', 'email' => 'lecturer107@campus.test']);
        $user->givePermissionTo('sidang_ta_registration_view_program');
        Lecturer::create(['lecturer_code' => '107D', 'name' => 'Dosen', 'email_domain' => $user->email, 'study_program' => 'Informatika']);
        $html = $this->actingAs($user)->get(route('admin.sidang-ta-registrations.show', $this->registration))->assertOk()->getContent();
        expect((new Crawler($html))->filter('[data-ta-cancel-approval], [data-ta-cancel-form]')->count())->toBe(0);
    }
    $this->actingAs($user);
    ($this->cancel)()->assertForbidden();
    expect($this->registration->fresh()->status)->toBe('approved');
})->with(['student', 'program_reader']);

test('after cancellation only the marked document needs correction and other verifications remain', function () {
    ($this->cancel)()->assertRedirect();
    $document = $this->registration->documents()->where('document_type', 'thesis_draft')->firstOrFail();
    $this->postJson(route('admin.sidang-ta-registrations.documents.review', [$this->registration, $document]), [
        'status' => 'rejected', 'notes' => 'Perbaiki naskah', 'registration_version' => 3])->assertOk();
    $this->postJson(route('admin.sidang-ta-registrations.review', $this->registration), [
        'action' => 'needs_review', 'review_notes' => 'Perbaiki draft buku TA', 'registration_version' => 4])->assertRedirect();
    expect($this->registration->fresh()->status)->toBe('needs_review')
        ->and($this->registration->documents()->where('status', 'verified')->count())->toBe(6)
        ->and($document->fresh()->status)->toBe('rejected');
});

test('cancellation audit appears to students without actor identity and does not erase approval history', function () {
    ($this->cancel)()->assertRedirect();
    $response = $this->actingAs($this->owner)->getJson(route('sidang-ta-registrations.history', $this->registration))->assertOk();
    expect(array_column($response->json('data'), 'action'))->toBe(['approval_cancelled', 'approved'])
        ->and($response->json('data.0.label'))->toBe('Persetujuan dibatalkan admin; menunggu peninjauan ulang');
    foreach ($response->json('data') as $event) expect($event)->not->toHaveKey('actor');
    $html = $this->get(route('sidang-ta-registrations.history', $this->registration))->assertOk()->getContent();
    expect((new Crawler($html))->filter('[data-student-timeline]')->text())->not->toContain($this->reviewer->name);
});

test('cancellation cannot create a second active registration for the same student', function (string $status) {
    $other = $this->registration->replicate();
    $other->status = $status;
    $other->save();
    ($this->cancel)()->assertConflict();
    expect($this->registration->fresh()->status)->toBe('approved')->and($this->registration->logs()->count())->toBe(1);
})->with(['submitted', 'needs_review']);

test('historical approved registration can be reopened without a current Student master record', function () {
    $this->student->delete();
    ($this->cancel)()->assertRedirect();
    expect($this->registration->fresh()->status)->toBe('submitted');
});
