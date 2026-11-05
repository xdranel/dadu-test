# 13 — D1: simpan upload draft baru tahap 2–4

Prasyarat: C2 lulus dan koreksi [12](12-rescan-c2-sebelum-upload.md) diterapkan. D1 khusus draft baru; revisi tetap ditolak sampai D2. Kita belum mengubah form HTML atau final submit. Backend masih JSON sampai E.

Semua command dijalankan pengguna dari folder asli. Asisten hanya menulis Markdown, tidak menjalankan generator, test, migrasi atau upload aplikasi.

## 1. Buat file baru

```bash
php artisan make:class Support/SidangTaDocumentRequirements
php artisan make:class Services/SidangTaDraftDocumentService
php artisan make:test SidangTaDraftUploadTest --pest
```

Jika file sudah ada, edit file itu; jangan gunakan --force. Request, controller dan SidangTaDraftService sudah ada, jadi tidak perlu generator lagi. Tidak ada migrasi baru pada D1.

## 2. Satu sumber daftar syarat

Ganti seluruh isi `app/Support/SidangTaDocumentRequirements.php`:

```php
<?php

namespace App\Support;

use App\Models\SidangTaRegistrationDraft;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class SidangTaDocumentRequirements
{
    public const CAPTURES = [
        'level_2_result_capture', 'igracias_approval_capture', 'ta_progress_capture',
        'ta_data_capture', 'online_supervision_capture', 'skpi_tak_capture',
    ];
    public const PAYMENT = [
        'graduation_payment_proof', 'bpp_payment_proof', 'ta_supervisor_sk',
        'eprt_lac_certificate', 'thesis_draft', 'transcript_khs', 'ta_course_ksm',
    ];
    public const DIPLOMA = ['diploma_statement', 'diploma_biodata_form'];
    public const PDM = ['pdm_submission_proof'];

    public static function typesForStep(int $step, ?bool $accurate = null): array
    {
        return match ($step) {
            2 => self::CAPTURES,
            3 => self::PAYMENT,
            4 => $accurate === null ? [] : ($accurate ? self::DIPLOMA : self::PDM),
            default => [],
        };
    }

    public static function scalarRules(int $step): array
    {
        return match ($step) {
            1 => ['student_phone' => 'required|string|max:30', 'parent_phone' => 'required|string|max:30'],
            3 => ['graduation_payment_date' => 'required|date|before_or_equal:today'],
            4 => ['diploma_biodata_accurate' => 'required|boolean'],
            default => [],
        };
    }

    public static function fileRules(string $type): array
    {
        return ['nullable', 'file', 'max:10240',
            in_array($type, self::CAPTURES, true) ? 'mimes:pdf,jpg,jpeg,png' : 'mimes:pdf'];
    }

    public static function complete(SidangTaRegistrationDraft $draft, int $step): bool
    {
        $payload = $draft->payload ?? [];
        if (Validator::make($payload, self::scalarRules($step))->fails()) {
            return false;
        }
        $accurate = array_key_exists('diploma_biodata_accurate', $payload)
            ? (bool) $payload['diploma_biodata_accurate'] : null;
        foreach (self::typesForStep($step, $accurate) as $type) {
            $document = $draft->documents()->where('document_type', $type)->first();
            if (!$document || !Storage::disk('local')->exists($document->file_path)) {
                return false;
            }
        }

        return true;
    }

    public static function firstIncompleteStep(SidangTaRegistrationDraft $draft): ?int
    {
        foreach (range(1, 4) as $step) {
            if (!self::complete($draft, $step)) {
                return $step;
            }
        }

        return null;
    }
}
```

Ini memeriksa kelengkapan data/file, bukan kelayakan akademik otomatis. Periode terbuka tetap diperiksa saat memilih periode dan final baru nanti; draft yang periodenya tutup tidak dihapus. `complete()` tidak membaca ulang MIME file tersimpan; upload sudah divalidasi saat diterima.

## 3. Request per tahap

Ganti seluruh `app/Http/Requests/SaveSidangTaDraftStepRequest.php`:

```php
<?php

namespace App\Http\Requests;

use App\Support\SidangTaDocumentRequirements as Requirements;
use Illuminate\Foundation\Http\FormRequest;

class SaveSidangTaDraftStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $draft = $this->route('draft');
        return $user && $user->can('sidang_ta_registration_create')
            && (!$draft || (string) $draft->owner_user_id === (string) $user->id);
    }

    public function rules(): array
    {
        $step = (int) ($this->route('step') ?? 1);
        $rules = [
            'version' => [$this->route('draft') ? 'required' : 'nullable', 'integer', 'min:1'],
        ];
        foreach (['owner_user_id', 'student_number', 'status', 'payload',
            'source_registration_id', 'source_version', 'reviewed_version',
            'submitted_registration_id', 'file_path'] as $key) {
            $rules[$key] = ['prohibited'];
        }
        foreach (['sidang_ta_period_id', 'student_phone', 'parent_phone',
            'graduation_payment_date', 'diploma_biodata_accurate'] as $key) {
            $rules[$key] = ['prohibited'];
        }
        $rules = array_replace($rules, Requirements::scalarRules($step));
        if ($step === 1) {
            $rules['sidang_ta_period_id'] = ['required', 'integer', 'exists:sidang_ta_periods,id'];
            $rules['documents'] = ['prohibited'];
            return $rules;
        }

        $accurate = $this->has('diploma_biodata_accurate')
            ? $this->boolean('diploma_biodata_accurate') : null;
        $types = Requirements::typesForStep($step, $accurate);
        $rules['documents'] = $types ? ['nullable', 'array:'.implode(',', $types)] : ['prohibited'];
        foreach ($types as $type) {
            $rules["documents.{$type}"] = Requirements::fileRules($type);
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'documents.array' => 'Jenis dokumen tidak sesuai tahap atau cabang yang dipilih.',
            'documents.*.max' => 'Ukuran setiap file maksimal 10 MB.',
            'documents.*.mimes' => 'Format dokumen tidak sesuai persyaratan tahap ini.',
        ];
    }
}
```

Tidak semua file required pada request: service memeriksa gabungan file baru dan existing yang benar-benar ada. Unknown key dokumen tetap ditolak oleh parent array whitelist. Input scalar/file tahap lain tidak ikut disimpan.

## 4. Service upload dengan kompensasi filesystem

Ganti `app/Services/SidangTaDraftDocumentService.php`:

```php
<?php

namespace App\Services;

use App\Models\SidangTaRegistrationDraft;
use App\Models\Student;
use App\Models\User;
use App\Support\SidangTaDocumentRequirements as Requirements;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class SidangTaDraftDocumentService
{
    public function __construct(private SidangTaStudentContext $context) {}

    private function clean(array $paths): void
    {
        foreach (array_unique($paths) as $path) {
            try {
                if (!Storage::disk('local')->delete($path)) {
                    Log::warning('Cleanup file draft belum berhasil.', ['path' => $path]);
                }
            } catch (Throwable $exception) {
                Log::warning('Cleanup file draft gagal.', ['path' => $path, 'error' => $exception->getMessage()]);
            }
        }
    }

    public function save(User $user, SidangTaRegistrationDraft $draft, int $step,
        int $expectedVersion, array $validated): SidangTaRegistrationDraft
    {
        abort_unless($user->can('sidang_ta_registration_create'), 403);
        abort_unless(in_array($step, [2, 3, 4], true), 422);
        abort_unless((string) $draft->owner_user_id === (string) $user->id, 403);
        $student = $this->context->studentFor($user);
        $newPaths = [];
        $retiredPaths = [];

        try {
            $result = DB::transaction(function () use ($user, $draft, $step, $expectedVersion,
                $validated, $student, &$newPaths, &$retiredPaths) {
                Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
                $locked = SidangTaRegistrationDraft::whereKey($draft->id)->lockForUpdate()->firstOrFail();
                abort_unless((string) $locked->owner_user_id === (string) $user->id
                    && $locked->student_number === $student->student_number, 403);
                abort_unless($locked->status === SidangTaRegistrationDraft::STATUS_ACTIVE
                    && $locked->source_registration_id === null, 409);
                abort_unless($locked->version === $expectedVersion, 409, 'Draft berubah. Muat ulang.');
                abort_if($locked->documents()->whereNotNull('source_document_id')->exists(), 409);

                $first = Requirements::firstIncompleteStep($locked);
                abort_if($first !== null && $step > $first, 409, 'Lengkapi tahap sebelumnya dahulu.');
                $payload = array_replace($locked->payload ?? [],
                    array_intersect_key($validated, Requirements::scalarRules($step)));
                if ($step === 4 && array_key_exists('diploma_biodata_accurate', $validated)) {
                    $payload['diploma_biodata_accurate'] = (bool) $validated['diploma_biodata_accurate'];
                }
                Validator::make($payload, Requirements::scalarRules($step))->validate();
                $accurate = $payload['diploma_biodata_accurate'] ?? null;
                $types = Requirements::typesForStep($step, $accurate);
                $files = $validated['documents'] ?? [];
                $fileRules = ['documents' => ['array:'.implode(',', $types)]];
                foreach ($types as $type) {
                    $fileRules["documents.{$type}"] = Requirements::fileRules($type);
                }
                Validator::make(['documents' => $files], $fileRules)->validate();

                foreach ($types as $type) {
                    $old = $locked->documents()->where('document_type', $type)->first();
                    $file = $files[$type] ?? null;
                    if (!$file && (!$old || !Storage::disk('local')->exists($old->file_path))) {
                        throw ValidationException::withMessages([
                            "documents.{$type}" => 'Unggah dokumen yang diwajibkan dan belum tersimpan.',
                        ]);
                    }
                    if (!$file instanceof UploadedFile) {
                        continue;
                    }
                    $directory = "sidang_ta/drafts/{$locked->id}";
                    $name = Str::uuid().'.'.$file->guessExtension();
                    $newPaths[] = "{$directory}/{$name}"; // Didaftarkan sebelum write, termasuk write parsial.
                    $path = $file->storeAs($directory, $name, 'local');
                    if (!$path) {
                        throw new RuntimeException('Penyimpanan file draft gagal. Silakan coba lagi.');
                    }
                    $locked->documents()->updateOrCreate(['document_type' => $type], [
                        'file_name' => $file->getClientOriginalName(), 'file_path' => $path,
                        'file_size' => $file->getSize(), 'file_type' => $file->guessExtension(),
                    ]);
                    if ($old) {
                        $retiredPaths[] = $old->file_path;
                    }
                }
                if ($step === 4) {
                    $obsolete = $accurate ? Requirements::PDM : Requirements::DIPLOMA;
                    foreach ($locked->documents()->whereIn('document_type', $obsolete)->get() as $old) {
                        $retiredPaths[] = $old->file_path;
                        $old->delete();
                    }
                }
                $locked->payload = $payload;
                $locked->version++;
                $locked->reviewed_version = null;
                $locked->save();

                return $locked;
            });
        } catch (Throwable $exception) {
            $this->clean($newPaths);
            throw $exception;
        }

        $this->clean($retiredPaths);
        return $result;
    }
}
```

Simpan file pengganti lebih dahulu, commit metadata baru, baru hapus file lama. Jika ada file keempat gagal sesudah tiga file berhasil, transaksi metadata dibatalkan dan file baru dibersihkan; semua file lama tetap ada. Cleanup gagal hanya dicatat untuk ditindaklanjuti, tidak mengubah response sukses menjadi seolah save gagal.

Service ini adalah entrypoint transaksi request biasa; jangan membungkusnya lagi dengan transaksi aplikasi luar yang masih bisa rollback sesudah service kembali. Test RefreshDatabase memang memakai transaksi luar untuk isolasi, tetapi bukan desain entrypoint produksi. Saat F/revisi dibangun, kompensasi file harus mengikuti batas commit finalnya sendiri.

## 5. Hubungkan dengan service/controller yang ada

Di `SidangTaDraftService`, ubah constructor menjadi:

```php
public function __construct(
    private SidangTaStudentContext $context,
    private SidangTaDraftDocumentService $documents,
) {}
```

Pada `saveStep()`, setelah `$this->owned($user, $draft);`, **ganti** abort lama yang hanya membolehkan tahap 1 dengan:

```php
if ($step !== 1) {
    return $this->documents->save($user, $draft, $step, $expectedVersion, $validated);
}
```

Kode simpan kontak tahap 1 dan start C2 lainnya tetap dipakai. Tidak ada dependensi melingkar: DocumentService hanya memakai StudentContext, tidak memanggil DraftService.

Di controller `SidangTaDraftController`, tambahkan import:

```php
use App\Models\SidangTaRegistrationDraftDocument;
use App\Support\SidangTaDocumentRequirements;
use Illuminate\Support\Facades\Storage;
```

Tambahkan pada array `data` di `respond()`:

```php
'first_incomplete_step' => SidangTaDocumentRequirements::firstIncompleteStep($draft),
'documents' => $draft->documents()->orderBy('document_type')->get()->map(fn ($document) => [
    'id' => $document->id, 'document_type' => $document->document_type,
    'file_name' => $document->file_name, 'file_size' => $document->file_size,
    'download_url' => route('sidang-ta-draft-documents.download', $document),
])->all(),
```

Tambahkan method controller berikut (jangan beri return type JsonResponse karena ini file response):

```php
public function downloadDocument(Request $request, SidangTaRegistrationDraftDocument $document)
{
    $this->service->owned($request->user(), $document->draft);
    abort_unless(Storage::disk('local')->exists($document->file_path), 404);

    return Storage::disk('local')->download($document->file_path, $document->file_name);
}
```

Di grup dashboard yang sama pada routes/web.php, tambahkan:

```php
Route::get('sidang-ta-draft-documents/{document}/download',
    [\App\Http\Controllers\SidangTaDraftController::class, 'downloadDocument'])
    ->middleware('permission:sidang_ta_registration_read')
    ->name('sidang-ta-draft-documents.download');
```

Response tidak memuat path filesystem; semua unduhan melalui owner+permission. Jangan mengubah disk ke public atau menggunakan storage:link untuk membuka dokumen TA.

## 6. Sesuaikan test C2 yang sekarang sudah berubah makna

Pada `SidangTaDraftTest.php`, ganti seluruh test `cannot_skip_to_unimplemented_stage` menjadi:

```php
test('cannot_skip_required_previous_stages', function () {
    $id = $this->actingAs($this->studentUser)
        ->postJson(route('sidang-ta-drafts.store'), $this->data)->assertCreated()->json('data.id');
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $id, 'step' => 4]), [
        'version' => 1, 'diploma_biodata_accurate' => false,
        'documents' => ['pdm_submission_proof' => \Illuminate\Http\UploadedFile::fake()
            ->create('pdm.pdf', 100, 'application/pdf')],
    ])->assertStatus(409);
    expect(SidangTaRegistrationDraft::findOrFail($id)->version)->toBe(1);
});
```

Tahap 4 sekarang diimplementasikan, tetapi akses tetap ditolak karena tahap 2–3 belum tersimpan. Test ini mengirim input tahap 4 yang valid sehingga benar-benar menguji gate service, bukan gagal lebih awal pada validasi input.

## 7. Test lengkap upload

Ganti seluruh file generator `tests/Feature/SidangTaDraftUploadTest.php`:

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

test('saves_six_captures_and_resumes_with_private_downloads', function () {
    ($this->saveUploadStep)(2, ['documents' => ($this->filesFor)(2)])
        ->assertOk()->assertJsonCount(6, 'data.documents')
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
    ])->assertOk()->assertJsonCount(15, 'data.documents')
        ->assertJsonPath('data.first_incomplete_step', null);
    $oldPaths = $this->draft->documents()->whereIn('document_type', Requirements::DIPLOMA)
        ->pluck('file_path')->all();
    ($this->saveUploadStep)(4, ['diploma_biodata_accurate' => false])
        ->assertUnprocessable();
    foreach ($oldPaths as $path) {
        Storage::disk('local')->assertExists($path);
    }
    expect($this->draft->fresh()->payload['diploma_biodata_accurate'])->toBeTrue();
    ($this->saveUploadStep)(4, [
        'diploma_biodata_accurate' => false, 'documents' => ($this->filesFor)(4, false),
    ])->assertOk()->assertJsonCount(14, 'data.documents');
    foreach ($oldPaths as $path) {
        Storage::disk('local')->assertMissing($path);
    }
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

test('keeps_existing_files_on_missing_requirements_and_stale_version', function () {
    ($this->saveUploadStep)(2, ['documents' => ($this->filesFor)(2)])->assertOk();
    $before = Storage::disk('local')->allFiles();
    $version = $this->draft->fresh()->version;
    ($this->saveUploadStep)(3, ['documents' => []])->assertUnprocessable();
    ($this->saveUploadStep)(2, [
        'version' => $version - 1, 'documents' => ($this->filesFor)(2),
    ])->assertStatus(409);
    expect(Storage::disk('local')->allFiles())->toBe($before);
    expect($this->draft->fresh()->version)->toBe($version);
});

test('storage_failure_preserves_old_metadata_and_files', function () {
    ($this->saveUploadStep)(2, ['documents' => ($this->filesFor)(2)])->assertOk();
    $old = $this->draft->documents()->where('document_type', 'level_2_result_capture')->first();
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
                'level_2_result_capture' => UploadedFile::fake()->create('new.pdf', 100, 'application/pdf'),
                'igracias_approval_capture' => $failed,
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
    expect($this->draft->documents()->count())->toBe(6);
});

test('another_user_cannot_download_draft_document', function () {
    ($this->saveUploadStep)(2, ['documents' => ($this->filesFor)(2)])->assertOk();
    $other = User::factory()->create(['status' => 'active', 'must_change_password' => false]);
    $other->givePermissionTo('sidang_ta_registration_read');
    $this->actingAs($other)->get(route('sidang-ta-draft-documents.download',
        $this->draft->documents()->first()))->assertForbidden();
});
```

## 8. Jalankan dan checkpoint

```bash
php artisan config:clear
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing php artisan test tests/Feature/SidangTaDraftSchemaTest.php tests/Feature/SidangTaPeriodTest.php tests/Feature/SidangTaDraftTest.php tests/Feature/SidangTaDraftUploadTest.php
```

Dengan tambahan dua regresi 12, test C2 menjadi 10; upload D1 berjumlah 8; schema/periode masing-masing 9. Jika phpunit.xml sudah dipulihkan, tambahkan DB_USERNAME/DB_PASSWORD dan parameter lokal lengkap seperti contoh 11. Jangan commit konfigurasi password lokal yang sementara.

Hasil yang dikirim untuk rescan: dua class baru, request/service/controller/routes yang diubah, test C2 yang disesuaikan dan test upload baru. Asisten akan membaca ulang langsung folder asli atau hasil salinan ke dadu-test.

Belum disertakan: revisi final, discard draft, submit final, penandaan review, atau UI wizard. Itu D2/E/F berikutnya, bukan fitur yang dianggap selesai saat D1 lulus. Fake file test bukan bukti transport PHP menerima file asli; uji browser dan MIME file nyata tetap dilakukan setelah UI terpasang.

Rujukan Context7: [validasi Laravel 13](https://github.com/laravel/docs/blob/13.x/validation.md), [filesystem testing](https://github.com/laravel/docs/blob/13.x/filesystem.md), [HTTP file tests](https://github.com/laravel/docs/blob/13.x/http-tests.md). Dokumen diperiksa sintaks dan direview terhadap source; suite aplikasi belum dijalankan asisten.


Pembaruan sesudah pengguna melaporkan D1 lulus: rescan tersedia pada [14](14-rescan-d1-sebelum-revisi.md), kemudian terapkan [15 D2](15-checkpoint-d2-draft-revisi.md). Guard source pada service/request D1 sengaja diubah melalui 15; jangan menyalin ulang versi D1 di atas setelah mengerjakan D2. File upload test masih perlu ikut disalin ke dadu-test.
