<?php

use App\Models\{SidangTaPeriod, SidangTaRegistration, SidangTaRegistrationDraft, Student, User};
use App\Services\SidangTaExpiredDraftCleanup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-11-06 12:00:00', config('app.timezone')));
    Storage::fake('local');
    $this->owner = User::factory()->create();
    Student::create(['student_number' => '1202299078', 'name' => 'Cleanup', 'email' => $this->owner->email,
        'academic_year' => 2022, 'study_program' => 'Informatika', 'status' => 'Aktif']);
    $this->period = SidangTaPeriod::create(['name' => 'Cleanup', 'is_active' => false,
        'open_at' => now()->subDay(), 'close_at' => now()->subSecond(),
        'revision_open_at' => now()->subDay(), 'revision_close_at' => now()->subSecond()]);
    $this->draft = function (array $extra = [], string $path = 'owned.pdf') {
        $draft = SidangTaRegistrationDraft::create(array_replace(['owner_user_id' => $this->owner->id,
            'student_number' => '1202299078', 'sidang_ta_period_id' => $this->period->id, 'payload' => []], $extra));
        Storage::disk('local')->put($path, '%PDF');
        $draft->documents()->create(['document_type' => 'ta_data_capture', 'file_path' => $path, 'file_name' => 'owned.pdf']);
        return $draft;
    };
});

afterEach(function () { $this->travelBack(); });

test('expired_cleanup_preserves_exact_boundary_and_future_or_inactive_period_until_after_close', function () {
    $draft = ($this->draft)();
    foreach ([now(), now()->addDay()] as $close) {
        $this->period->update(['close_at' => $close]);
        expect(app(SidangTaExpiredDraftCleanup::class)->run()['deleted_drafts'])->toBe(0);
        expect($draft->fresh())->not->toBeNull();
        Storage::disk('local')->assertExists('owned.pdf');
    }
    $this->period->update(['close_at' => now()->subSecond()]);
    expect(app(SidangTaExpiredDraftCleanup::class)->run()['deleted_drafts'])->toBe(1);
    expect($draft->fresh())->toBeNull();
    Storage::disk('local')->assertMissing('owned.pdf');
});

test('expired_cleanup_deletes_initial_and_revision_orphans_but_preserves_final_borrowed_shared_and_consumed_files', function () {
    $source = SidangTaRegistration::create(['sidang_ta_period_id' => $this->period->id, 'student_number' => '1202299078',
        'student_name' => 'Cleanup', 'student_phone' => '081234567890', 'parent_phone' => '081234567891',
        'graduation_payment_date' => today(), 'diploma_biodata_accurate' => true, 'status' => 'needs_review']);
    $final = $source->documents()->create(['document_type' => 'thesis_draft', 'file_name' => 'final.pdf', 'file_path' => 'final.pdf', 'status' => 'verified']);
    Storage::disk('local')->put('final.pdf', '%PDF final');
    $initial = ($this->draft)([], 'initial.pdf');
    $revision = ($this->draft)(['source_registration_id' => $source->id, 'source_version' => 1], 'revision.pdf');
    $revision->documents()->create(['document_type' => 'thesis_draft', 'file_name' => 'final.pdf', 'file_path' => 'final.pdf', 'source_document_id' => $final->id]);
    $revision->documents()->create(['document_type' => 'graduation_payment_proof', 'file_name' => 'shared.pdf', 'file_path' => 'shared.pdf']);
    $future = SidangTaPeriod::create(['name' => 'Future', 'is_active' => false, 'open_at' => now()->addDay(), 'close_at' => now()->addDays(2)]);
    $kept = ($this->draft)(['sidang_ta_period_id' => $future->id], 'shared.pdf');
    $receipt = ($this->draft)(['status' => 'consumed', 'submitted_registration_id' => $source->id], 'receipt.pdf');
    // A reference without source_document_id still protects final-owned paths.
    $initial->documents()->create(['document_type' => 'thesis_draft', 'file_name' => 'final.pdf', 'file_path' => 'final.pdf']);
    expect(app(SidangTaExpiredDraftCleanup::class)->run(true))->toMatchArray(['eligible_drafts' => 2, 'deleted_drafts' => 0]);
    expect($initial->fresh())->not->toBeNull();
    $result = app(SidangTaExpiredDraftCleanup::class)->run();
    expect($result)->toMatchArray(['eligible_drafts' => 2, 'deleted_drafts' => 2]);
    expect($initial->fresh())->toBeNull();
    expect($revision->fresh())->toBeNull();
    expect($receipt->fresh()->status)->toBe('consumed');
    expect($kept->fresh())->not->toBeNull();
    expect($source->fresh()->status)->toBe('needs_review');
    Storage::disk('local')->assertMissing(['initial.pdf', 'revision.pdf']);
    Storage::disk('local')->assertExists(['final.pdf', 'shared.pdf', 'receipt.pdf']);
    expect(app(SidangTaExpiredDraftCleanup::class)->run()['deleted_drafts'])->toBe(0);
});

test('expired_cleanup_command_dry_run_is_read_only_and_repeat_is_idempotent', function () {
    $draft = ($this->draft)();
    $this->artisan('sidang-ta:purge-expired-drafts --dry-run')->assertSuccessful();
    expect($draft->fresh())->not->toBeNull();
    $this->artisan('sidang-ta:purge-expired-drafts')->assertSuccessful();
    expect($draft->fresh())->toBeNull();
    $this->artisan('sidang-ta:purge-expired-drafts')->assertSuccessful();
});

test('expired_cleanup_rechecks_consumed_status_after_selection', function () {
    $draft = ($this->draft)();
    $event = 'eloquent.retrieved: '.Student::class;
    \Illuminate\Support\Facades\Event::listen($event, function () use ($draft) {
        DB::table('sidang_ta_registration_drafts')->where('id', $draft->id)->update(['status' => 'consumed']);
    });
    try {
        expect(app(SidangTaExpiredDraftCleanup::class)->run()['deleted_drafts'])->toBe(0);
        expect($draft->fresh()->status)->toBe('consumed');
        Storage::disk('local')->assertExists('owned.pdf');
    } finally {
        \Illuminate\Support\Facades\Event::forget($event);
    }
});

test('expired_cleanup_rechecks_period_extension_after_selection', function () {
    $draft = ($this->draft)();
    $event = 'eloquent.retrieved: '.Student::class;
    \Illuminate\Support\Facades\Event::listen($event, function () {
        DB::table('sidang_ta_periods')->where('id', $this->period->id)->update(['close_at' => now()->addDay()]);
    });
    try {
        expect(app(SidangTaExpiredDraftCleanup::class)->run()['deleted_drafts'])->toBe(0);
        expect($draft->fresh())->not->toBeNull();
        Storage::disk('local')->assertExists('owned.pdf');
    } finally {
        \Illuminate\Support\Facades\Event::forget($event);
    }
});

test('expired_cleanup_defers_files_until_the_owning_transaction_commits_and_preserves_them_on_rollback', function () {
    $draft = ($this->draft)();
    DB::beginTransaction();
    try {
        expect(app(SidangTaExpiredDraftCleanup::class)->run()['deleted_drafts'])->toBe(1);
        expect($draft->fresh())->toBeNull();
        Storage::disk('local')->assertExists('owned.pdf');
    } finally {
        DB::rollBack();
    }
    expect($draft->fresh())->not->toBeNull();
    Storage::disk('local')->assertExists('owned.pdf');
});
