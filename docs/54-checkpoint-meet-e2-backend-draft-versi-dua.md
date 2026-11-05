# 54 — Meet E2: backend draft versi 2, upload parsial dan kelengkapan dinamis

**Tujuan:** menghubungkan registry 51 dan fakta 50 ke validasi, penyimpanan, dan kelengkapan draft versi 2.
**Arsitektur:** satu resolver di `SidangTaDocumentRequirements` memilih persyaratan berdasarkan metadata server. Request memeriksa jenis file; service mengulangi pemeriksaan dengan payload yang sudah digabung dalam transaksi. Versi 1 tetap memakai aturan lama.
**Spesifikasi:**45,49–53. Pengguna melaporkan test53 lulus dan tersalin. Test serta tiga JSON fixture sudah dibandingkan identik antara folder asli dan workspace.

## 1. Perilaku yang selesai pada checkpoint ini

| Bagian | Versi 1 | Versi 2 |
| --- | --- | --- |
| Tahap 2 | 6 capture | Data TA dan Bimbingan Online |
| Tahap 3 | 7 dokumen | Wisuda, SK Pembimbing, Draft Buku TA; tambah Surat Permakluman pada mode waiver |
| Tahap 4 | Cabang biodata yang sudah ada | Tetap dua PDF sesuai / satu bukti PDM |
| Simpan parsial | Tetap | Boleh menyimpan satu file atau scalar belum lengkap |
| Tinjau | Memerlukan upload/scalar lengkap | Sama; lima syarat akademik dan EPRT retest/unknown belum mengunci Tinjau |
| Kirim | Jalur lama | Ditahan sementara sampai UI dan pemeriksaan final dipasang bersama |

Server `DraftService::start` **tetap membuat versi 1 pada checkpoint ini**. Test baru menyiapkan metadata versi 2 di server khusus fixture. Jangan mengubah default model, migrasi, atau data aplikasi menjadi 2 sendiri.

Ini integrasi backend draft, belum aktivasi form meet bagi mahasiswa. Form dan preview HTML versi 2 belum disesuaikan di sini. Pengiriman versi 2 ditolak dengan pesan bahwa pengiriman belum tersedia; data dan file tetap tersimpan. Checkpoint selanjutnya akan mengganti penahan sementara ini dengan pemeriksaan final yang sebenarnya sesudah tampilan siap.

Tidak ada perubahan database atau impor JSON yang diperlukan. Data persetujuan sidang iGracias tetap belum tersedia; jangan menggunakan foto BASILA sebagai pengganti.

## 2. Berkas dan perintah

Kerjakan di folder asli:

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
php artisan make:test SidangTaMeetDraftTest --pest
```

Empat file produksi existing:

```text
app/Support/SidangTaDocumentRequirements.php
app/Http/Requests/SaveSidangTaDraftStepRequest.php
app/Services/SidangTaDraftDocumentService.php
app/Services/SidangTaDraftFinalizer.php
```

Test existing yang diganti satu kasus: `tests/Feature/SidangTaRequirementsVersionTest.php`. Test baru: `tests/Feature/SidangTaMeetDraftTest.php`. Total **6 file** untuk dipindahkan setelah berhasil.

Jangan mengganti `SidangTaMeetRequirements` atau policy/facts yang sudah diuji. Model `DOCUMENT_TYPES` juga belum ditambah waiver pada tahap ini: ekspor lama mengiterasi konstanta itu dan harus diperbarui terencana bersama dukungan tampilan versi 2. Label waiver untuk checklist diambil langsung dari registry meet.

## 3. Tulis test baru dahulu

Isi seluruh `tests/Feature/SidangTaMeetDraftTest.php` dengan:

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
        // Server-only test setup. UI creation intentionally stays version one during rollout.
        $draft->update(['requirements_version' => 2]);
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

test('meet_v2_rollout_guard_blocks_submit_without_consuming_the_saved_reviewed_draft', function () {
    $draft = ($this->finishMeetDraft)();
    $this->postJson(route('sidang-ta-drafts.review', $draft), ['version' => $draft->version])->assertOk();
    $paths = Storage::disk('local')->allFiles();
    $this->postJson(route('sidang-ta-registrations.store'), ['draft_id' => $draft->id,
        'version' => $draft->version, 'action' => 'submit'])->assertUnprocessable()
        ->assertJsonPath('message', 'Pengiriman draft ini belum tersedia. Data dan berkas tetap tersimpan.');
    expect($draft->fresh()->status)->toBe('active');
    expect($draft->fresh()->reviewed_version)->toBe($draft->version);
    expect($draft->documents()->count())->toBe(7);
    expect(SidangTaRegistration::count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBe($paths);
});

test('meet_v2_consumed_replay_returns_saved_registration_before_rollout_guard', function () {
    $draft = ($this->finishMeetDraft)();
    $registration = SidangTaRegistration::create(['sidang_ta_period_id' => $this->period->id,
        'requirements_version' => 2, 'student_number' => $this->student->student_number,
        'student_name' => $this->student->name, 'student_phone' => '081234567890', 'parent_phone' => '081234567891',
        'graduation_payment_date' => today()->toDateString(), 'diploma_biodata_accurate' => true, 'status' => 'approved']);
    $draft->update(['status' => 'consumed', 'submitted_registration_id' => $registration->id]);
    $paths = Storage::disk('local')->allFiles();
    $this->postJson(route('sidang-ta-registrations.store'), ['draft_id' => $draft->id,
        'version' => $draft->version, 'action' => 'submit'])->assertOk()
        ->assertJsonPath('data.id', $registration->id)->assertJsonPath('data.status', 'approved');
    expect($registration->logs()->count())->toBe(0);
    expect($registration->fresh()->requirements_version)->toBe(2);
    expect(Storage::disk('local')->allFiles())->toBe($paths);
});

```

Test mengakses endpoint draft asli untuk save/review. Pembuatan metadata 2 secara langsung hanya dilakukan oleh fixture; ini bukan kemampuan mahasiswa memilih versi lewat HTTP. Data akademik sengaja belum memenuhi lima syarat dalam test Tinjau, agar dibuktikan bahwa draft dan Tinjau tetap dapat digunakan.

Jalankan sebelum mengganti kode produksi. Ekspektasi awal: test perilaku baru gagal karena resolver versi 2 belum dipasang; tidak harus semua test gagal:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaMeetDraftTest.php
```

## 4. Ganti empat file produksi lengkap

### app/Support/SidangTaDocumentRequirements.php

Ganti seluruh isi file:

```php
<?php

namespace App\Support;

use App\Models\SidangTaRegistration;
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

    public static function assertKnownVersion(SidangTaRegistrationDraft $draft): void
    {
        abort_unless(in_array($draft->requirements_version, [1, 2], true), 409,
            'Versi persyaratan draft tidak dikenali. Muat ulang pengajuan.');
    }

    public static function typesForDraftStep(SidangTaRegistrationDraft $draft, int $step, ?array $payload = null): array
    {
        self::assertKnownVersion($draft);
        $payload ??= $draft->payload ?? [];
        $choice = $payload['diploma_biodata_accurate'] ?? null;
        $accurate = $choice === null ? null : (bool) $choice;
        if ($draft->requirements_version === 1) return self::typesForStep($step, $accurate);
        $mode = 'unknown';
        if ($step === 3) {
            $student = \App\Models\Student::where('student_number', $draft->student_number)->firstOrFail();
            $mode = app(\App\Services\SidangTaAdmissionFacts::class)->forStudent($student)['eprt']['mode'];
        }
        return SidangTaMeetRequirements::typesForStep($step, $accurate, $mode);
    }

    public static function fileRulesForDraft(SidangTaRegistrationDraft $draft, string $type): array
    {
        self::assertKnownVersion($draft);
        return $draft->requirements_version === 2 ? SidangTaMeetRequirements::fileRules($type) : self::fileRules($type);
    }

    // Syarat lengkap: dipakai navigasi, tinjau dan finalisasi.
    public static function scalarRules(int $step): array
    {
        return match ($step) {
            1 => ['student_phone' => 'required|string|max:30', 'parent_phone' => 'required|string|max:30'],
            3 => ['graduation_payment_date' => 'required|date|before_or_equal:today'],
            4 => ['diploma_biodata_accurate' => 'required|boolean'],
            default => [],
        };
    }

    // Syarat save: field belum ada/null boleh, tetapi nilai yang diisi tetap valid.
    public static function draftScalarRules(int $step): array
    {
        return match ($step) {
            3 => ['graduation_payment_date' => 'sometimes|nullable|date|before_or_equal:today'],
            4 => ['diploma_biodata_accurate' => 'sometimes|nullable|boolean'],
            default => self::scalarRules($step),
        };
    }

    public static function fileRules(string $type): array
    {
        return ['nullable', 'file', 'max:10240',
            in_array($type, self::CAPTURES, true) ? 'mimes:pdf,jpg,jpeg,png' : 'mimes:pdf'];
    }

    public static function progress(SidangTaRegistrationDraft $draft, int $step): array
    {
        $payload = $draft->payload ?? [];
        $labels = ['student_phone' => 'Nomor HP mahasiswa', 'parent_phone' => 'Nomor HP orang tua/wali',
            'graduation_payment_date' => 'Tanggal pembayaran wisuda',
            'diploma_biodata_accurate' => 'Pilihan kesesuaian biodata ijazah'];
        $validator = Validator::make($payload, self::scalarRules($step));
        $validator->passes();
        $missing = [];
        foreach (array_keys($validator->failed()) as $key) {
            $missing[] = $labels[$key] ?? $key;
        }
        $choice = $payload['diploma_biodata_accurate'] ?? null;
        $accurate = $choice === null ? null : (bool) $choice;
        $types = self::typesForDraftStep($draft, $step, $payload);
        $draft->loadMissing('documents');
        $stored = 0;
        foreach ($types as $type) {
            $document = $draft->documents->firstWhere('document_type', $type);
            if ($document && Storage::disk('local')->exists($document->file_path)) {
                $stored++;
            } else {
                $missing[] = SidangTaMeetRequirements::LABELS[$type] ?? SidangTaRegistration::DOCUMENT_TYPES[$type] ?? $type;
            }
        }
        return [
            'complete' => $missing === [],
            'stored_documents' => $stored,
            'required_documents' => $step === 4 && $accurate === null ? null : count($types),
            'missing' => $missing,
        ];
    }

    public static function complete(SidangTaRegistrationDraft $draft, int $step): bool
    {
        return self::progress($draft, $step)['complete'];
    }

    public static function firstIncompleteStep(SidangTaRegistrationDraft $draft): ?int
    {
        foreach (range(1, 4) as $step) {
            if (!self::complete($draft, $step)) return $step;
        }
        return null;
    }
}

```

### app/Http/Requests/SaveSidangTaDraftStepRequest.php

Ganti seluruh isi file:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use App\Support\SidangTaDocumentRequirements as Requirements;
use Illuminate\Foundation\Http\FormRequest;

class SaveSidangTaDraftStepRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $draft = $this->route('draft');

        $permission = $draft && $draft->source_registration_id !== null
            ? 'sidang_ta_registration_update'
            : 'sidang_ta_registration_create';

        return $user && $user->can($permission)
            && (!$draft || (string) $draft->owner_user_id === (string) $user->id);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $step = (int) ($this->route('step') ?? 1);
        $draft = $this->route('draft');
        if ($draft) Requirements::assertKnownVersion($draft);
        $rules = ['version' => [$this->route('draft')
            ? 'required'
            : 'nullable', 'integer', 'min:1']];
        foreach (['requirements_version', 'owner_user_id', 'student_number', 'status', 'payload', 'source_registration_id',
                     'source_version', 'reviewed_version', 'submitted_registration_id', 'file_path',
                     'sidang_ta_period_id', 'student_phone', 'parent_phone', 'graduation_payment_date',
                     'diploma_biodata_accurate'] as $key) {
            $rules[$key] = ['prohibited'];
        }
        $rules = array_replace($rules, Requirements::draftScalarRules($step));
        if ($step === 1) {
            $rules['sidang_ta_period_id'] = ['required', 'integer', 'exists:sidang_ta_periods,id'];
            $rules['documents'] = ['prohibited'];
            return $rules;
        }

        // Tahap 4 boleh menerima scalar yang belum ada. Cabang aktual divalidasi lagi
        // dalam service setelah merge payload dan version check di bawah lock.
        if ($draft && $draft->requirements_version === 2) {
            $types = $step === 4
                ? array_merge(\App\Support\SidangTaMeetRequirements::DIPLOMA, \App\Support\SidangTaMeetRequirements::PDM)
                : Requirements::typesForDraftStep($draft, $step);
        } else {
            $types = $step === 4 ? array_merge(Requirements::DIPLOMA, Requirements::PDM)
                : Requirements::typesForStep($step);
        }
        $rules['documents'] = ['nullable', 'array:'.implode(',', $types)];
        foreach ($types as $type) {
            $rules["documents.{$type}"] = $draft ? Requirements::fileRulesForDraft($draft, $type) : Requirements::fileRules($type);
        }
        return $rules;
    }

    public function messages(): array
    {
        return [
            'sidang_ta_period_id.required' => 'Pilih periode sidang TA.',
            'sidang_ta_period_id.integer' => 'Pilih periode sidang TA yang valid.',
            'sidang_ta_period_id.exists' => 'Periode sidang TA tidak ditemukan.',
            'student_phone.required' => 'Nomor HP mahasiswa wajib diisi.',
            'parent_phone.required' => 'Nomor HP orang tua/wali wajib diisi.',
            'student_phone.string' => 'Nomor HP mahasiswa harus berupa teks.',
            'parent_phone.string' => 'Nomor HP orang tua/wali harus berupa teks.',
            'student_phone.max' => 'Nomor HP mahasiswa maksimal 30 karakter.',
            'parent_phone.max' => 'Nomor HP orang tua/wali maksimal 30 karakter.',
            'graduation_payment_date.required' => 'Tanggal pembayaran wisuda wajib diisi.',
            'graduation_payment_date.date' => 'Tanggal pembayaran wisuda tidak valid.',
            'graduation_payment_date.before_or_equal' => 'Tanggal pembayaran wisuda tidak boleh melewati hari ini.',
            'diploma_biodata_accurate.required' => 'Pilih kesesuaian biodata dummy ijazah.',
            'diploma_biodata_accurate.boolean' => 'Pilihan kesesuaian biodata tidak valid.',
            'version.required' => 'Versi draft tidak tersedia. Buka ulang draft terbaru.',
            'version.integer' => 'Versi draft tidak valid. Buka ulang draft terbaru.',
            'version.min' => 'Versi draft tidak valid. Buka ulang draft terbaru.',
            'documents.array' => 'Jenis dokumen tidak sesuai tahap atau cabang yang dipilih.',
            'documents.*.file' => 'Pilih berkas yang valid.',
            'documents.*.uploaded' => 'Berkas gagal diunggah. Periksa ukuran berkas dan coba lagi.',
            'documents.*.max' => 'Ukuran setiap file maksimal 10 MB.',
            'documents.*.mimes' => 'Format dokumen tidak sesuai persyaratan tahap ini.',
            'prohibited' => 'Isian :attribute tidak diperbolehkan pada tahap ini.',
        ];
    }

    public function attributes(): array
    {
        $attributes = [
            'sidang_ta_period_id' => 'periode sidang TA',
            'student_phone' => 'nomor HP mahasiswa',
            'parent_phone' => 'nomor HP orang tua/wali',
            'graduation_payment_date' => 'tanggal pembayaran wisuda',
            'diploma_biodata_accurate' => 'kesesuaian biodata ijazah',
            'documents' => 'dokumen', 'version' => 'versi draft',
        ];
        foreach (array_merge(\App\Models\SidangTaRegistration::DOCUMENT_TYPES,
            \App\Support\SidangTaMeetRequirements::LABELS) as $type => $label) {
            $attributes["documents.{$type}"] = $label;
        }
        return $attributes;
    }
}

```

### app/Services/SidangTaDraftDocumentService.php

Ganti seluruh isi file:

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
use RuntimeException;
use Throwable;

class SidangTaDraftDocumentService
{
    public function __construct(private SidangTaStudentContext $context,
                                private SidangTaDraftAccess $access
    ) {}

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
        $this->access->assertCanWrite($user, $draft);
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
                $this->access->check($user, $locked, true);
                abort_unless($locked->version === $expectedVersion, 409, 'Draft berubah. Muat ulang.');
                $first = Requirements::firstIncompleteStep($locked);
                abort_if($first !== null && $step > $first, 409, 'Lengkapi tahap sebelumnya dahulu.');

                $payload = array_replace($locked->payload ?? [],
                    array_intersect_key($validated, Requirements::draftScalarRules($step)));
                Validator::make($payload, Requirements::draftScalarRules($step))->validate();
                if ($step === 4 && array_key_exists('diploma_biodata_accurate', $payload)) {
                    $choice = $payload['diploma_biodata_accurate'];
                    $payload['diploma_biodata_accurate'] = $choice === null ? null : (bool) $choice;
                }
                $accurate = $payload['diploma_biodata_accurate'] ?? null;
                $types = Requirements::typesForDraftStep($locked, $step, $payload);
                $files = $validated['documents'] ?? [];
                $fileRules = ['documents' => $types ? ['array:'.implode(',', $types)] : ['prohibited']];
                foreach ($types as $type) $fileRules["documents.{$type}"] = Requirements::fileRulesForDraft($locked, $type);
                Validator::make(['documents' => $files], $fileRules)->validate();

                foreach ($types as $type) {
                    $file = $files[$type] ?? null;
                    if (!$file instanceof UploadedFile) continue;
                    $old = $locked->documents()->where('document_type', $type)->first();
                    $directory = "sidang_ta/drafts/{$locked->id}";
                    $name = Str::uuid().'.'.$file->guessExtension();
                    $newPaths[] = "{$directory}/{$name}";
                    $path = $file->storeAs($directory, $name, 'local');
                    if (!$path) throw new RuntimeException('Penyimpanan file draft gagal. Silakan coba lagi.');
                    $locked->documents()->updateOrCreate(['document_type' => $type], [
                        'file_name' => $file->getClientOriginalName(), 'file_path' => $path,
                        'file_size' => $file->getSize(), 'file_type' => $file->guessExtension(),
                        'source_document_id' => null,
                    ]);
                    if ($old && $old->source_document_id === null) $retiredPaths[] = $old->file_path;
                }
                // Missing/uncertain EPRT data must not destroy a previously uploaded waiver.
                if ($step === 3 && $locked->requirements_version === 2
                    && app(\App\Services\SidangTaAdmissionFacts::class)->forStudent($student)['eprt']['mode'] === 'automatic') {
                    foreach ($locked->documents()->where('document_type', \App\Support\SidangTaMeetRequirements::WAIVER)->get() as $old) {
                        if ($old->source_document_id === null) $retiredPaths[] = $old->file_path;
                        $old->delete();
                    }
                }
                if ($step === 4 && $accurate !== null) {
                    $obsolete = $accurate ? Requirements::PDM : Requirements::DIPLOMA;
                    foreach ($locked->documents()->whereIn('document_type', $obsolete)->get() as $old) {
                        if ($old->source_document_id === null) $retiredPaths[] = $old->file_path;
                        $old->delete();
                    }
                }
                $locked->payload = $payload;
                $locked->version++;
                $locked->reviewed_version = null;
                $locked->save();
                return $locked->load('documents');
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

### app/Services/SidangTaDraftFinalizer.php

Ganti seluruh isi file:

```php
<?php

namespace App\Services;

use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDocument;
use App\Models\SidangTaRegistrationDraft;
use App\Models\SidangTaRegistrationDraftDocument;
use App\Models\Student;
use App\Models\User;
use App\Support\SidangTaDocumentRequirements as Requirements;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class SidangTaDraftFinalizer
{
    public function __construct(private SidangTaDraftAccess $access,
                                private SidangTaAcademicSnapshot $snapshots) {}

    public function submit(User $user, SidangTaRegistrationDraft $draft, int $version,
                           ?int $expectedSourceId = null): SidangTaRegistration
    {
        $student = $this->access->owner($user, $draft);
        $this->access->assertCanWrite($user, $draft);
        $retired = [];

        $registration = DB::transaction(function () use ($user, $draft, $version,
            $expectedSourceId, $student, &$retired) {
            $student = Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $locked = SidangTaRegistrationDraft::whereKey($draft->id)->lockForUpdate()->firstOrFail();
            $this->access->owner($user, $locked);
            $this->access->assertCanWrite($user, $locked);
            $sourceId = $locked->source_registration_id === null ? null : (int) $locked->source_registration_id;
            abort_unless($sourceId === $expectedSourceId, 409, 'Draft tidak cocok dengan jenis pengajuan ini.');
            abort_unless($locked->version === $version, 409, 'Draft berubah. Muat ulang sebelum mengirim.');

            // Harus sebelum check(active), source needs_review, periode dan kelengkapan.
            if ($locked->status === SidangTaRegistrationDraft::STATUS_CONSUMED) {
                $existing = $locked->submittedRegistration()->firstOrFail();
                abort_unless($existing->student_number === $student->student_number, 403);
                return $existing; // Tidak mengubah keputusan reviewer atau menulis log ulang.
            }

            $this->access->check($user, $locked, true);
            // Temporary rollout guard; remove only when the meet UI and final eligibility are wired.
            abort_unless($locked->requirements_version === 1, 422,
                'Pengiriman draft ini belum tersedia. Data dan berkas tetap tersimpan.');
            abort_unless($locked->reviewed_version === $locked->version, 409, 'Tinjau draft terlebih dahulu.');
            if (Requirements::firstIncompleteStep($locked) !== null) {
                throw ValidationException::withMessages(['draft' => 'Data atau berkas belum lengkap. Buka form dan lengkapi kembali.']);
            }
            $source = $sourceId === null ? null
                : $locked->sourceRegistration()->lockForUpdate()->firstOrFail();
            $period = $locked->period()->lockForUpdate()->firstOrFail();
            if (!$source && !$period->isOpen()) {
                throw ValidationException::withMessages(['draft' => 'Periode telah ditutup. Pilih periode terbuka dan tinjau ulang.']);
            }
            if ($source) {
                abort_unless((int) $source->sidang_ta_period_id === (int) $locked->sidang_ta_period_id, 409);
            }
            $otherActive = SidangTaRegistration::where('student_number', $student->student_number)
                ->whereIn('status', ['submitted', 'needs_review']);
            if ($source) $otherActive->where('id', '!=', $source->id);
            abort_if($otherActive->exists(), 409, 'Anda masih memiliki pengajuan aktif lain.');

            $payload = $locked->payload;
            $types = array_merge(Requirements::CAPTURES, Requirements::PAYMENT,
                Requirements::typesForStep(4, $payload['diploma_biodata_accurate']));
            $documents = $locked->documents()->get();
            $actual = $documents->pluck('document_type')->all();
            $expected = $types;
            sort($actual); sort($expected);
            if ($actual !== $expected) {
                throw ValidationException::withMessages(['draft' => 'Daftar berkas tidak sesuai persyaratan aktif.']);
            }

            $registration = $source ?? new SidangTaRegistration();
            $oldStatus = $source?->status;
            $oldPaths = $source ? $source->documents()->pluck('file_path')->all() : [];
            $registration->fill([
                'sidang_ta_period_id' => $period->id,
                'student_number' => $student->student_number, 'student_name' => $student->name,
                'study_program' => $student->study_program,
                'student_phone' => $payload['student_phone'], 'parent_phone' => $payload['parent_phone'],
                'graduation_payment_date' => $payload['graduation_payment_date'],
                'diploma_biodata_accurate' => $payload['diploma_biodata_accurate'],
                'academic_snapshot' => $this->snapshots->forStudent($student),
                'requirements_version' => $locked->requirements_version,
                'status' => 'submitted', 'submitted_at' => now(),
                'reviewed_by' => null, 'reviewed_at' => null, 'review_notes' => null,
            ]);
            if (!$source) $registration->submitted_by = $user->id;
            $registration->lock_version = $source ? $source->lock_version + 1 : 1;
            $registration->save();

            foreach ($documents as $document) {
                $registration->documents()->updateOrCreate(['document_type' => $document->document_type], [
                    'file_name' => $document->file_name, 'file_path' => $document->file_path,
                    'file_size' => $document->file_size, 'file_type' => $document->file_type,
                    'status' => 'pending', 'notes' => null,
                ]);
            }
            // Lepaskan FK source_document_id sebelum menghapus row final obsolete.
            $locked->documents()->delete();
            $registration->documents()->whereNotIn('document_type', $types)->delete();
            $retired = array_values(array_diff($oldPaths, $documents->pluck('file_path')->all()));
            $registration->logAction($source ? 'resubmitted' : 'submitted', $oldStatus,
                'submitted', $source ? 'Mahasiswa mengirim revisi.' : 'Pendaftaran dikirim mahasiswa.', $user->id);
            $locked->status = SidangTaRegistrationDraft::STATUS_CONSUMED;
            $locked->submitted_registration_id = $registration->id;
            $locked->save(); // Version draft tetap untuk identitas replay.

            return $registration;
        });

        // Hanya sesudah transaksi final sukses. Jika DB gagal, tidak ada file yang dihapus.
        foreach (array_unique($retired) as $path) {
            try {
                if (SidangTaRegistrationDocument::where('file_path', $path)->exists()
                    || SidangTaRegistrationDraftDocument::where('file_path', $path)->exists()) {
                    continue;
                }
                if (!Storage::disk('local')->delete($path)) {
                    Log::warning('Cleanup finalisasi perlu diulang.', ['path' => $path]);
                }
            } catch (Throwable $exception) {
                Log::warning('Cleanup finalisasi gagal.', ['path' => $path, 'error' => $exception->getMessage()]);
            }
        }
        return $registration;
    }
}

```

## 5. Penjelasan titik penting

- `typesForDraftStep` membaca mode EPRT melalui adapter 50 saat memilih persyaratan tahap 3 versi 2. Nilai mode tidak diterima dari HTTP. Tipe file tahap 4 diperiksa lagi setelah pilihan biodata digabung dengan payload tersimpan.
- Upload lama yang dihapus dari flow meet ditolak untuk versi 2, sebelum file ditulis. Versi 1 tetap menerimanya sesuai aturan lama.
- Scalar wajib tetap diperiksa untuk melanjutkan tahap/Tinjau. Tanggal pembayaran boleh belum diisi ketika Simpan parsial, tetapi tahap 3 belum dianggap lengkap.
- Perubahan EPRT setelah Tinjau diperiksa ulang saat menghitung kelengkapan. Jika kini membutuhkan waiver yang belum ada, tahap 3 kembali belum lengkap. Setelah upload, versi draft naik dan penanda reviewed dihapus seperti perubahan biasa.
- Membaca kelengkapan tidak menghapus file. Waiver yang sudah diunggah tetap disimpan saat data menjadi unknown/retest. Hanya pada **Simpan tahap 3** dengan mode automatic, metadata waiver dilepas dan file milik draft dibersihkan setelah transaksi berhasil. File pinjaman dari pengajuan final tidak dihapus oleh cleanup draft.
- Penahan Kirim versi 2 berada **setelah consumed replay**, sehingga pengiriman ulang yang sudah berhasil tidak rusak. Penahan ini tidak menyatakan bahwa lima syarat akademik telah diuji pada finalisasi; pemeriksaan final masih checkpoint berikutnya.
- Backend revisi terarah belum diaktifkan pada versi 2. Sumber versi 2 nyata belum dibuat oleh UI. Jangan memakai fixture metadata sebagai pengajuan asli, atau mencoba mengaktifkan sebagian flow lewat perubahan database manual.

## 6. Sesuaikan satu test metadata lama

Test checkpoint52 pernah memakai versi2 dengan15dokumen lama hanya untuk menguji pengiriman metadata. Itu fixture sementara yang sudah diberi batas pada52. Sekarang versi2 tidak boleh dikirim lewat jalur final lama.

Pada `tests/Feature/SidangTaRequirementsVersionTest.php`, ganti hanya test `meet_finalizer_preserves_server_requirements_metadata_and_replay_result` dengan berikut. Fixture menggunakan versi1 untuk memeriksa metadata dan replay legacy. Test lain tetap, termasuk pewarisan versi sumber serta guard mismatch revisi. Test replay versi2 yang sudah consumed ada pada file test baru di bagian3.

```php
test('meet_finalizer_preserves_server_requirements_metadata_and_replay_result', function () {
    $draft = ($this->completeDraft)();
    $paths = Storage::disk('local')->allFiles();
    $id = ($this->submitDraft)($draft)->assertOk()->json('data.id');
    $registration = SidangTaRegistration::findOrFail($id);
    expect($registration->requirements_version)->toBe(1);
    ($this->submitDraft)($draft)->assertOk()->assertJsonPath('data.id', $id);
    expect($registration->fresh()->requirements_version)->toBe(1);
    expect(SidangTaRegistration::count())->toBe(1);
    expect($registration->logs()->count())->toBe(1);
    expect(Storage::disk('local')->allFiles())->toBe($paths);
});

```

Jangan mengubah assertion penolakan forged requirements_version. Jangan mengendurkan test file source/pinjam atau versi stale. Pada integrasi final versi2 nanti, fixture pengiriman akan memakai persyaratan meet dan fakta akademik lengkap.

## 7. Verifikasi sesudah implementasi

```bash
php -l app/Support/SidangTaDocumentRequirements.php
php -l app/Http/Requests/SaveSidangTaDraftStepRequest.php
php -l app/Services/SidangTaDraftDocumentService.php
php -l app/Services/SidangTaDraftFinalizer.php
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaMeetDraftTest.php
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaMeetDraftTest.php tests/Feature/SidangTaRequirementsVersionTest.php tests/Feature/SidangTaFinalizationTest.php tests/Feature/SidangTaRevisionDraftTest.php
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaDummyImportTest.php tests/Feature/SidangTaAdmissionFactsTest.php tests/Unit/SidangTaAdmissionPolicyTest.php tests/Unit/SidangTaMeetRequirementsTest.php
```

Target **14 test baru**. Fokus empat file kedua: **45 passed** (14+8+13+10). Command dummy/facts/policy/registry tetap36. Feature TA bertambah14 dari baseline53 (227 jika sebelumnya213); Unit TA21 dan Node12 tidak bertambah. Jika baseline aktual berbeda, laporkan hitungan aktual; jangan menambah duplikat test.

Jalankan juga suite TA yang biasa digunakan setelah fokus lulus. Tidak perlu `migrate`, `migrate:fresh`, atau seed pada checkpoint54. Jangan mengubah phpunit.xml/DB ke SQLite.

## 8. Manual dan pemindahan

Manual yang relevan saat ini adalah regresi **versi1**: mulai pendaftaran biasa, Simpan parsial, pindah tahap setelah lengkap, Tinjau dan Kirim, buka detail/preview, serta gunakan draft revisi. Semuanya tetap berjalan. Jangan menguji tampilan meet dengan mengubah metadata lewat Tinker; UI versi2 belum dipasang.

Perilaku baru versi2 diuji lewat14testHTTP di atas. Sesudah fokus dan regresi berhasil, pindahkan6file pada bagian2, termasuk test existing yang diperbarui. Minta rescan sebelum melanjutkan tampilan dan final submit.

Tahap berikutnya: form/tinjau versi2 dengan kartu otomatis dan upload yang tepat; lalu pemeriksaan final lima syarat+EPRT, snapshot keputusan, aktivasi versi2 dari server, dan pembaruan alur petugas/revisi terarah. Pengajuan lama serta file privat tetap harus dapat dibuka.


## 9. Pemeriksaan panduan

Keenam blok PHP final lulus pemeriksaan sintaks. Probe resolver/progress/rules dengan vendor aktual lulus21pengecekan; query fakta, storage dan loadMissing diganti stub sehingga tidak mengakses database. Ini memeriksa pemilihan tipe, jumlah, label, file hilang, dan aturan request, bukan menjalankan Feature test.

Review independen tidak menemukan blocker pada fixture HTTP, validasi di dalam transaksi, perlindungan file, dan urutan replay/penahan Kirim. Finalizer mempertahankan access/source guard sebelum penahan sementara. Test PDM juga mencoba upload dua berkas cabang sesuai ketika pilihan sebenarnya belum sesuai, dan harus ditolak.

Pada scan terakhir file test baru sudah muncul di folder asli dan cocok dengan14test pada panduan jika whitespace diabaikan. Berkas produksi yang dipindai masih baseline sebelum penerapan54. Asisten tidak mengubah source aplikasi, menjalankan migrasi, atau menjalankan FeatureDB; hasil test pengguna tetap diperlukan.
