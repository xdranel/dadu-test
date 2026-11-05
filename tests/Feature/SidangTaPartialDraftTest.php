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
    ($this->savePartial)(2, ['documents' => array_slice($files, 0, 2, true)])->assertOk();
    $first = $this->draft->documents()->where('document_type', Requirements::CAPTURES[0])->firstOrFail();
    ($this->savePartial)(2, ['documents' => array_slice($files, 2, null, true)])
        ->assertOk()->assertJsonCount(6, 'data.documents')->assertJsonPath('data.version', 3)
        ->assertJsonPath('data.first_incomplete_step', 3);
    expect($first->fresh()->file_path)->toBe($first->file_path);
    Storage::disk('local')->assertExists($first->file_path);
    expect(Requirements::progress($this->draft->fresh(), 2)['complete'])->toBeTrue();
});

test('payment_file_saves_without_date_but_stage_four_stays_locked', function () {
    ($this->savePartial)(2, ['documents' => ($this->filesFor)(2)])->assertOk();
    ($this->savePartial)(3, ['documents' => array_slice(($this->filesFor)(3), 0, 1, true)])
        ->assertOk()->assertJsonCount(7, 'data.documents')->assertJsonPath('data.first_incomplete_step', 3);
    $progress = Requirements::progress($this->draft->fresh(), 3);
    expect($progress['stored_documents'])->toBe(1);
    expect($progress['missing'])->toContain('Tanggal pembayaran wisuda');
    $this->get(route('sidang-ta-drafts.edit', ['draft' => $this->draft, 'step' => 4]))
        ->assertRedirect(route('sidang-ta-drafts.edit', ['draft' => $this->draft, 'step' => 3]));
});

test('null_biodata_choice_is_saved_without_becoming_pdm', function () {
    ($this->throughThree)();
    ($this->savePartial)(4, ['diploma_biodata_accurate' => null])->assertOk()
        ->assertJsonCount(13, 'data.documents')->assertJsonPath('data.first_incomplete_step', 4);
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
        ->assertJsonCount(14, 'data.documents')->assertJsonPath('data.first_incomplete_step', 4);
    $this->postJson(route('sidang-ta-drafts.review', $this->draft), [
        'version' => $this->draft->fresh()->version,
    ])->assertUnprocessable();
    ($this->savePartial)(4, ['documents' => array_slice($files, 1, null, true)])->assertOk()
        ->assertJsonCount(15, 'data.documents')->assertJsonPath('data.first_incomplete_step', null);
    $version = $this->draft->fresh()->version;
    $this->postJson(route('sidang-ta-drafts.review', $this->draft), ['version' => $version])->assertOk();
    expect($this->draft->fresh()->reviewed_version)->toBe($version);
});

test('invalid_provided_file_rejects_whole_new_batch_and_keeps_saved_progress', function () {
    ($this->savePartial)(2, ['documents' => array_slice(($this->filesFor)(2), 0, 1, true)])->assertOk();
    $before = Storage::disk('local')->allFiles();
    ($this->savePartial)(2, ['documents' => [
        Requirements::CAPTURES[1] => UploadedFile::fake()->create('valid.pdf', 100, 'application/pdf'),
        Requirements::CAPTURES[2] => UploadedFile::fake()->create('invalid.txt', 100, 'text/plain'),
    ]])->assertUnprocessable()->assertJsonValidationErrors('documents.'.Requirements::CAPTURES[2]);
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
    expect($this->draft->documents()->count())->toBe(13);
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

test('partial_revision_branch_change_never_deletes_final_files', function () {
    ($this->throughThree)();
    ($this->savePartial)(4, ['diploma_biodata_accurate' => true, 'documents' => ($this->filesFor)(4, true)])->assertOk();
    $version = $this->draft->fresh()->version;
    $this->postJson(route('sidang-ta-drafts.review', $this->draft), ['version' => $version])->assertOk();
    $id = $this->postJson(route('sidang-ta-registrations.store'), [
        'draft_id' => $this->draft->id, 'version' => $version, 'action' => 'submit',
    ])->assertOk()->json('data.id');
    $source = SidangTaRegistration::findOrFail($id);
    $source->status = 'needs_review';
    $source->lock_version++;
    $source->save();
    $paths = $source->documents()->pluck('file_path')->all();
    $revisionId = $this->postJson(route('sidang-ta-drafts.revision', $source))->assertCreated()->json('data.id');
    $this->draft = SidangTaRegistrationDraft::findOrFail($revisionId);
    ($this->savePartial)(4, ['diploma_biodata_accurate' => false])->assertOk()
        ->assertJsonCount(13, 'data.documents')->assertJsonPath('data.first_incomplete_step', 4);
    foreach ($paths as $path) Storage::disk('local')->assertExists($path);
    expect($source->fresh()->status)->toBe('needs_review');
    expect($source->documents()->count())->toBe(15);
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
    expect($crawler->filter('[data-step-progress]')->text(null, true))->toContain('1 dari 6 dokumen tersimpan.');
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
    expect($this->draft->documents()->count())->toBe(15);
});
