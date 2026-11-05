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
