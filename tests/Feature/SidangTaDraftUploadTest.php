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

test('saves_four_ta_pdfs_and_resumes_with_private_downloads', function () {
    ($this->saveUploadStep)(2, ['documents' => ($this->filesFor)(2)])
        ->assertOk()->assertJsonCount(4, 'data.documents')
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
        'diploma_biodata_accurate' => false, 'pdm_submission_date' => today()->toDateString(), 'documents' => ($this->filesFor)(4, false),
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
        'graduation_payment_proof' => UploadedFile::fake()->create('image.jpg', 100, 'image/jpeg'),
    ]])->assertUnprocessable()->assertJsonValidationErrors('documents.graduation_payment_proof');
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
    expect($this->draft->documents()->count())->toBe(4);
});

test('another_user_cannot_download_draft_document', function () {
    ($this->saveUploadStep)(2, ['documents' => ($this->filesFor)(2)])->assertOk();
    $other = User::factory()->create(['status' => 'active', 'must_change_password' => false]);
    $other->givePermissionTo('sidang_ta_registration_read');
    $this->actingAs($other)->get(route('sidang-ta-draft-documents.download',
        $this->draft->documents()->first()))->assertForbidden();
});
