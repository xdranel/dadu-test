# 52 — Meet E1: versi persyaratan draft dan pengajuan sebelum aktivasi flow baru

**Goal:** menyimpan identitas persyaratan pada draft/pengajuan agar upload lama dan hasilmeet dapat dibedakan saatintegrasi.
**Architecture:** kolomserver requirements_version pada dua tabel, default1untuklegacy, diwariskan saatrevisi danfinalsubmit. Requesttidakbolehmenentukannya. Keamananownership, sourceversion, replay dan fileexistingtetap.
**Spec:**45,49–51. Semuaunit51dilaporkanlulusdan2filetersalinidentik. Registrybarudipakai berikutnya setelahmetadata+guard siap.

## 1. Kontrak checkpoint dan dampak

- requirements_version **berbeda** dari versiondraft/lock_versionpengajuan. Jangan memakainya sebagai optimisticlock.
- Versi1=daftaruploadlama;2=daftarhasilmeet. **Pada52 server masih membuatversi1**, karena request/checklist/UI/finalizerbelumdiwireke2. Versi2padafixturetesthanyamengujipenerusanmetadata, bukanklaimflowbaruaktif.
- Migrasimenambahkolomdefault1 ke rowexistingdaninsertlama. Tidakmenghapusfile/row, tidakmengubahacademic_snapshotatau memindahkan data.
- Draftrevisi mewarisiversisumber, bukanmengikutiversidefaultbaru. Ketidaksamaanversi draftrevisi/sumberditolak409.
- Finalizermenyalinversidraftserver, termasuk saatresubmit; consumedreplaytetapjalurawal yangtidakmenulisulang.
- Versiuploadini kelak hanya membedakandaftarfile; **bukan celahuntukmelewatilima gateakademik** pada pengirimanbaru. Aturangateakandipasangdiserveruntuksemuasubmitrelevan, replayyangsudahberhasiltetapdikecualikanlebihdahulu.

Ini tahappertamaintegrasiyangmenyentuhschema/serviceaktif. Perubahanform/requiredsetbarumenyusul. Janganubahdefaultke2sendiri. Jangan migrate:freshataumenghapusdraftuntukmelewatimigrasi.

## 2. File dan pembuatan

Delapan fileexisting:

```text
app/Models/SidangTaRegistration.php
app/Models/SidangTaRegistrationDraft.php
app/Http/Requests/SaveSidangTaDraftStepRequest.php
app/Http/Requests/StoreSidangTaRegistrationRequest.php
app/Services/SidangTaDraftService.php
app/Services/SidangTaRevisionDraftService.php
app/Services/SidangTaDraftFinalizer.php
app/Services/SidangTaDraftAccess.php
```

Ditambah2filebaru (migrasi+Featuretest), total10file. Nama migrasi mengikuti timestamp yangdihasilkanArtisan:

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
php artisan make:migration add_requirements_version_to_sidang_ta_tables
php artisan make:test SidangTaRequirementsVersionTest --pest
```

Filemigrasi `database/migrations/<timestamp>_add_requirements_version_to_sidang_ta_tables.php` adalahfileyangbarudibuatcommandtersebut. Gunakan namapersis hasilterminal saatmenyalin; janganbuatmigrasikedua dengannamasamadantimestampberbeda. Semua potongankodeberikut lengkapuntukfile/methodyangdisebut.

## 3. Test dahulu

Ganti seluruh tests/Feature/SidangTaRequirementsVersionTest.php dengan:

```php
<?php

use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDraft;
use App\Models\Student;
use App\Models\User;
use App\Support\SidangTaDocumentRequirements as Legacy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create(['username' => '1202200052', 'email' => 'meet-version@test.local',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
    $this->student = Student::create(['student_number' => $this->user->username, 'name' => 'Version Fixture',
        'email' => $this->user->email, 'academic_year' => 2022, 'study_program' => 'Informatika', 'status' => 'Aktif']);
    foreach (['read', 'create', 'update'] as $action) {
        $permission = Permission::firstOrCreate(['name' => 'sidang_ta_registration_'.$action, 'guard_name' => 'web']);
        $this->user->givePermissionTo($permission);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($this->user);
    $this->period = SidangTaPeriod::create(['name' => 'Version', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay()]);
    $this->startData = ['sidang_ta_period_id' => $this->period->id,
        'student_phone' => '081234567890', 'parent_phone' => '081234567891'];
    // Metadata-only staged fixture: version two is NOT enabled in the live form yet.
    // It intentionally uses the current document set until the next integration checkpoint.
    $this->completeDraft = function (int $requirementsVersion = 1) {
        $draft = SidangTaRegistrationDraft::create(['owner_user_id' => $this->user->id,
            'student_number' => $this->student->student_number, 'sidang_ta_period_id' => $this->period->id,
            'requirements_version' => $requirementsVersion, 'version' => 1, 'reviewed_version' => 1,
            'payload' => ['student_phone' => '081234567890', 'parent_phone' => '081234567891',
                'graduation_payment_date' => today()->toDateString(), 'diploma_biodata_accurate' => true]]);
        foreach (array_merge(Legacy::CAPTURES, Legacy::PAYMENT, Legacy::DIPLOMA) as $type) {
            $path = 'sidang_ta/drafts/'.$draft->id.'/'.$type.'.pdf';
            Storage::disk('local')->put($path, '%PDF-1.4 metadata test');
            $draft->documents()->create(['document_type' => $type, 'file_name' => $type.'.pdf',
                'file_path' => $path, 'file_size' => 22, 'file_type' => 'pdf']);
        }
        return $draft;
    };
    $this->submitDraft = fn ($draft, array $extra = []) => $this->postJson(route('sidang-ta-registrations.store'),
        array_replace(['draft_id' => $draft->id, 'version' => $draft->version, 'action' => 'submit'], $extra));
    $this->makeSource = function (int $requirementsVersion = 2) {
        return SidangTaRegistration::create(['student_number' => $this->student->student_number,
            'student_name' => $this->student->name, 'study_program' => 'Informatika',
            'sidang_ta_period_id' => $this->period->id, 'requirements_version' => $requirementsVersion,
            'student_phone' => '081234567890', 'parent_phone' => '081234567891',
            'graduation_payment_date' => today()->toDateString(), 'diploma_biodata_accurate' => true,
            'status' => 'needs_review', 'submitted_at' => now()])->fresh();
    };
});

test('meet_requirements_version_columns_have_database_defaults_for_old_style_inserts', function () {
    foreach (['sidang_ta_registrations', 'sidang_ta_registration_drafts'] as $table) {
        expect(Schema::hasColumn($table, 'requirements_version'))->toBeTrue();
    }
    $sourceId = DB::table('sidang_ta_registrations')->insertGetId([
        'sidang_ta_period_id' => $this->period->id, 'student_number' => $this->student->student_number,
        'student_name' => 'Raw default', 'status' => 'submitted',
        'student_phone' => '081234567890', 'parent_phone' => '081234567891',
        'graduation_payment_date' => today()->toDateString(), 'diploma_biodata_accurate' => true,
    ]);
    $id = (string) Str::uuid();
    DB::table('sidang_ta_registration_drafts')->insert(['id' => $id, 'owner_user_id' => $this->user->id,
        'student_number' => $this->student->student_number, 'sidang_ta_period_id' => $this->period->id, 'payload' => '{}']);
    expect((int) DB::table('sidang_ta_registrations')->where('id', $sourceId)->value('requirements_version'))->toBe(1);
    expect((int) DB::table('sidang_ta_registration_drafts')->where('id', $id)->value('requirements_version'))->toBe(1);
});

test('meet_requirements_version_is_integer_and_defaults_to_legacy_on_unsaved_models', function () {
    expect((new SidangTaRegistration())->requirements_version)->toBe(1);
    expect((new SidangTaRegistrationDraft())->requirements_version)->toBe(1);
    $source = ($this->makeSource)(2);
    expect($source->fresh()->requirements_version)->toBe(2);
});

test('meet_new_draft_cannot_choose_its_requirements_version_in_http_input', function () {
    foreach ([1, 2, 99] as $forged) {
        $this->postJson(route('sidang-ta-drafts.store'), array_replace($this->startData,
            ['requirements_version' => $forged]))->assertUnprocessable()->assertJsonValidationErrors('requirements_version');
    }
    expect(SidangTaRegistrationDraft::count())->toBe(0);
    $id = $this->postJson(route('sidang-ta-drafts.store'), $this->startData)->assertCreated()->json('data.id');
    expect(SidangTaRegistrationDraft::findOrFail($id)->requirements_version)->toBe(1);
});

test('meet_saved_draft_cannot_change_its_requirements_version_in_http_input', function () {
    $id = $this->postJson(route('sidang-ta-drafts.store'), $this->startData)->assertCreated()->json('data.id');
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $id, 'step' => 1]),
        array_replace($this->startData, ['version' => 1, 'requirements_version' => 2]))
        ->assertUnprocessable()->assertJsonValidationErrors('requirements_version');
    $draft = SidangTaRegistrationDraft::findOrFail($id);
    expect($draft->version)->toBe(1);
    expect($draft->requirements_version)->toBe(1);
});

test('meet_final_submit_rejects_client_requirements_version_without_consuming_draft', function () {
    $draft = ($this->completeDraft)();
    ($this->submitDraft)($draft, ['requirements_version' => 2])->assertUnprocessable()
        ->assertJsonValidationErrors('requirements_version');
    expect($draft->fresh()->status)->toBe('active');
    expect(SidangTaRegistration::count())->toBe(0);
    expect($draft->documents()->count())->toBe(15);
});

test('meet_finalizer_preserves_server_requirements_metadata_and_replay_result', function () {
    $draft = ($this->completeDraft)(2);
    $paths = Storage::disk('local')->allFiles();
    $id = ($this->submitDraft)($draft)->assertOk()->json('data.id');
    $registration = SidangTaRegistration::findOrFail($id);
    expect($registration->requirements_version)->toBe(2);
    ($this->submitDraft)($draft)->assertOk()->assertJsonPath('data.id', $id);
    expect($registration->fresh()->requirements_version)->toBe(2);
    expect(SidangTaRegistration::count())->toBe(1);
    expect($registration->logs()->count())->toBe(1);
    expect(Storage::disk('local')->allFiles())->toBe($paths);
});

test('meet_revision_inherits_server_requirements_version_instead_of_request_body', function () {
    $source = ($this->makeSource)(2);
    $id = $this->postJson(route('sidang-ta-drafts.revision', $source), ['requirements_version' => 1])
        ->assertCreated()->json('data.id');
    $draft = SidangTaRegistrationDraft::findOrFail($id);
    expect($draft->requirements_version)->toBe(2);
    expect($draft->source_version)->toBe($source->lock_version);
    expect($source->fresh()->requirements_version)->toBe(2);
});

test('meet_mismatched_revision_requirements_version_is_rejected_without_removing_source_file', function () {
    $source = ($this->makeSource)(2);
    Storage::disk('local')->put('sidang_ta/metadata-original.pdf', '%PDF-1.4 original');
    $source->documents()->create(['document_type' => 'thesis_draft', 'file_name' => 'original.pdf',
        'file_path' => 'sidang_ta/metadata-original.pdf', 'file_type' => 'pdf']);
    $id = $this->postJson(route('sidang-ta-drafts.revision', $source))->assertCreated()->json('data.id');
    $draft = SidangTaRegistrationDraft::findOrFail($id);
    $draft->update(['requirements_version' => 1]); // Simulate server-side inconsistent data, not an HTTP capability.
    $this->getJson(route('sidang-ta-drafts.edit', $draft))->assertConflict();
    expect($source->fresh()->requirements_version)->toBe(2);
    expect($source->documents()->count())->toBe(1);
    Storage::disk('local')->assertExists('sidang_ta/metadata-original.pdf');
});

```

Jalankan sebelumproduksidiubah, expectedFAILkolom/metadatabelumada:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaRequirementsVersionTest.php
```

Fixture serverversion2 dengan15legacyfiles di sini sengaja **hanya untuk testtransportmetadata** saatflowaktifmasihlegacy. Saatwiring2aktif checkpointberikutnya fixtureiniakan diganti dengandataakademikvalid dan7/8filebaru; janganmenggunakannyasebagai contohdataproduksi versi2.

## 4. Migrasi lengkap

Isi filemigrasihasilgenerate bagian2:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sidang_ta_registrations', function (Blueprint $table) {
            $table->unsignedTinyInteger('requirements_version')->default(1);
        });
        Schema::table('sidang_ta_registration_drafts', function (Blueprint $table) {
            $table->unsignedTinyInteger('requirements_version')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('sidang_ta_registration_drafts', function (Blueprint $table) {
            $table->dropColumn('requirements_version');
        });
        Schema::table('sidang_ta_registrations', function (Blueprint $table) {
            $table->dropColumn('requirements_version');
        });
    }
};

```

Down hanya disediakan untuk reversibilitas struktur, janganjalankanrollback padaDBaplikasiyangakanmemakaiversi2 karenaidentitasversinyaakanhilang. TidakmenambahJSONdefaultataudependencymigrasi.

## 5. Dua model lengkap

Ganti seluruh `app/Models/SidangTaRegistration.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SidangTaRegistration extends Model
{
    public const DOCUMENT_TYPES = [
        'level_2_result_capture' => 'Capture Hasil Sidang Tingkat 2',
        'igracias_approval_capture' => 'Capture Persetujuan Sidang di iGracias',
        'ta_progress_capture' => 'Capture Progress Status TA',
        'ta_data_capture' => 'Capture Data TA (Menu TA/PA)',
        'online_supervision_capture' => 'Capture Bimbingan Online yang Disetujui',
        'skpi_tak_capture' => 'Capture Status SKPI & TAK',
        'graduation_payment_proof' => 'Bukti Pembayaran Wisuda',
        'bpp_payment_proof' => 'Bukti Bayar BPP Semester Berjalan',
        'ta_supervisor_sk' => 'SK Pembimbing TA Aktif',
        'eprt_lac_certificate' => 'Sertifikat EPrT LAC (minimal 450)',
        'thesis_draft' => 'Draft Buku TA (PDF)',
        'transcript_khs' => 'KHS (PDF, SKS lulus minimal 120)',
        'ta_course_ksm' => 'KSM yang mengambil Mata Kuliah Tugas Akhir',
        'diploma_statement' => 'Surat Pernyataan Biodata Ijazah',
        'diploma_biodata_form' => 'Formulir Biodata Ijazah',
        'pdm_submission_proof' => 'Bukti Pengajuan PDM',
    ];

    public const DOCUMENT_DESCRIPTIONS = [
        'level_2_result_capture' => 'Pastikan status lulus terlihat; flow mensyaratkan IPK minimal 2,00 dan SKS lulus minimal 120.',
        'igracias_approval_capture' => 'Capture harus memperlihatkan persetujuan sidang di iGracias.',
        'ta_progress_capture' => 'Pastikan status TA 1–5 dan tanggal terlihat pada capture.',
        'ta_data_capture' => 'Ambil dari menu TA/PA di iGracias.',
        'online_supervision_capture' => 'Tampilkan bimbingan online yang sudah disetujui.',
        'skpi_tak_capture' => 'Pastikan status SKPI dan TAK terlihat.',
        'bpp_payment_proof' => 'Untuk BPP semester berjalan dengan status sudah lunas.',
        'eprt_lac_certificate' => 'Nilai minimal 450; unggah PDF.',
        'thesis_draft' => 'PDF tanpa tanda tangan; logo dan tahun mengikuti template.',
        'transcript_khs' => 'PDF dengan SKS lulus minimal 120.',
        'ta_course_ksm' => 'KSM perlu memperlihatkan pengambilan mata kuliah Tugas Akhir.',
        'diploma_statement' => 'Dokumen bermaterai fisik dan ditandatangani basah; unggah PDF.',
        'diploma_biodata_form' => 'Formulir biodata dummy ijazah; unggah PDF.',
        'pdm_submission_proof' => 'Unggah PDF bukti pengajuan Perubahan Data Mahasiswa.',
    ];

    protected $fillable = [
        'requirements_version',
        'sidang_ta_period_id', 'student_number', 'student_name', 'study_program',
        'student_phone', 'parent_phone', 'graduation_payment_date',
        'diploma_biodata_accurate', 'academic_snapshot', 'status',
        'submitted_by', 'reviewed_by', 'submitted_at', 'reviewed_at', 'review_notes',
    ];

    protected $attributes = [
        'requirements_version' => 1,
    ];

    protected $casts = [
        'requirements_version' => 'integer',
        'academic_snapshot' => 'array',
        'graduation_payment_date' => 'date',
        'diploma_biodata_accurate' => 'boolean',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'lock_version' => 'integer',
    ];

    public function period()
    {
        return $this->belongsTo(SidangTaPeriod::class, 'sidang_ta_period_id');
    }

    public function documents()
    {
        return $this->hasMany(SidangTaRegistrationDocument::class, 'sidang_ta_registration_id');
    }

    public function logs()
    {
        return $this->hasMany(SidangTaRegistrationLog::class, 'sidang_ta_registration_id')->latest('performed_at');
    }

    public function logAction(string $action, ?string $from, ?string $to, ?string $notes, ?int $userId): void
    {
        $this->logs()->create([
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'notes' => $notes,
            'performed_by' => $userId,
            'performed_at' => now(),
        ]);
    }
}

```

Ganti seluruh `app/Models/SidangTaRegistrationDraft.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SidangTaRegistrationDraft extends Model
{
    use HasUuids;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_CONSUMED = 'consumed';

    protected $fillable = [
        'requirements_version',
        'owner_user_id', 'student_number', 'sidang_ta_period_id',
        'source_registration_id', 'source_version', 'payload', 'version',
        'reviewed_version', 'status', 'submitted_registration_id',
    ];

    protected $attributes = [
        'requirements_version' => 1,
        'payload' => '{}', 'version' => 1, 'status' => self::STATUS_ACTIVE,
    ];

    protected $casts = [
        'requirements_version' => 'integer',
        'payload' => 'array', 'version' => 'integer',
        'source_version' => 'integer', 'reviewed_version' => 'integer',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(SidangTaPeriod::class, 'sidang_ta_period_id');
    }

    public function sourceRegistration(): BelongsTo
    {
        return $this->belongsTo(SidangTaRegistration::class, 'source_registration_id');
    }

    public function submittedRegistration(): BelongsTo
    {
        return $this->belongsTo(SidangTaRegistration::class, 'submitted_registration_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SidangTaRegistrationDraftDocument::class, 'draft_id');
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('owner_user_id', $user->getKey());
    }
}

```

fillable ditambah agarserviceyangmengirimarrayserverdapatmenyimpanmetadata. HTTPtidakmemakaiinputmassassignment; keduarequestberikutmelarang fieldini. Defaultattributes1memastikanmodelbaruyangbelumdisimpanjugatidakmenghasilkannull.

## 6. Dua request lengkap

Ganti seluruh `app/Http/Requests/SaveSidangTaDraftStepRequest.php`:

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
        $types = $step === 4 ? array_merge(Requirements::DIPLOMA, Requirements::PDM)
            : Requirements::typesForStep($step);
        $rules['documents'] = ['nullable', 'array:'.implode(',', $types)];
        foreach ($types as $type) $rules["documents.{$type}"] = Requirements::fileRules($type);
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
        foreach (\App\Models\SidangTaRegistration::DOCUMENT_TYPES as $type => $label) {
            $attributes["documents.{$type}"] = $label;
        }
        return $attributes;
    }
}

```

Ganti seluruh `app/Http/Requests/StoreSidangTaRegistrationRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSidangTaRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permission = $this->route('sidang_ta_registration')
            ? 'sidang_ta_registration_update'
            : 'sidang_ta_registration_create';
        return $this->user()?->can($permission) ?? false;
    }

    public function rules(): array
    {
        $rules = [
            'draft_id' => ['required', 'uuid', 'exists:sidang_ta_registration_drafts,id'],
            'version' => ['required', 'integer', 'min:1'],
            'action' => ['required', 'in:submit'],
        ];
        foreach (['requirements_version', 'student_number', 'student_name', 'study_program', 'owner_user_id',
                     'sidang_ta_period_id', 'student_phone', 'parent_phone', 'graduation_payment_date',
                     'diploma_biodata_accurate', 'academic_snapshot', 'status', 'submitted_by',
                     'reviewed_by', 'reviewed_at', 'review_notes', 'reviewed_version',
                     'source_registration_id', 'source_version', 'documents', 'file_path'] as $key) {
            $rules[$key] = ['prohibited'];
        }
        return $rules;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->allFiles() !== []) {
                $validator->errors()->add('documents', 'Final submit memakai berkas tersimpan, tanpa upload baru.');
            }
        });
    }
}

```

## 7. Empat perubahan kecil service

### Draft baru: versi dipilih server

Pada app/Services/SidangTaDraftService.php, di methodstart dan arraySidangTaRegistrationDraft::create yangmemuatowner/student/period, tambahkan tepat setelahsidang_ta_period_id:

```php
'requirements_version' => 1,
```

Tidak menambahkan ke payload. Janganmengambil `$validated['requirements_version']` atau inputrequest. Retryexistingdrafttetapmengembalikandraftlamatanpamenimpaversinya.

### Revisi: salin versi sumber

Pada app/Services/SidangTaRevisionDraftService.php, di methodstart dan arraySidangTaRegistrationDraft::create, setelahsource_registration_id/source_version, tambahkan:

```php
'requirements_version' => $source->requirements_version,
```

Sourceyangdipakaiadalahrowterkunciyangdibacaulang, bukanmodelrouteyangbisalama. BodyrequestpadastartRevisiontidakdipakaiuntukmetadata. existingdraftbranch tetapmenjalankanaccesscheck.

### Submit: rekam versi draft

Pada app/Services/SidangTaDraftFinalizer.php, di array `$registration->fill([...])`, setelahacademic_snapshot, tambahkan:

```php
'requirements_version' => $locked->requirements_version,
```

Tidakubahurutanconsumedreplay,student/draft/source/periodlock ataucleanupfile. Kolomini tidakmasukkeacademic_snapshot agarconsumerdapatmembedakanversipersyaratandarisnapshotdataakademik.

### Guard versi revisi

Pada app/Services/SidangTaDraftAccess.php, methodcheck, setelahsourcerowdibacadandiperiksaNIMnya:

```php
abort_unless($source->student_number === $draft->student_number, 403);
```

Tambahkan tepatberikutnya, sebelumstatus/source_versionguardexisting:

```php
abort_unless($draft->requirements_version === $source->requirements_version, 409,
    'Versi persyaratan draft tidak cocok dengan pengajuan asal. Muat ulang pengajuan.');
```

Jangantimparequirements_versiondraftsecaraotomatisuntukmelewati409. Perbedaanmetadata harusditangani dengandiscard/resumeyangsudahada, bukanmenghapusfilesumber.

## 8. Migrasi dan test

Setelahsemuakodediatasditerapkan, jalankanmigrasidiDBaplikasilokalyangbiasadipakai (.env, bukan dadu_testing):

```bash
php artisan migrate
php artisan migrate:status
```

Periksa outputhanyamigrasibaru/yangmemangpending. Tidakmemakaifresh/seed. DBtesting akanmenjalankanmigrasisendirimelaluiRefreshDatabase padaFeaturetests.

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaRequirementsVersionTest.php
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaDraftSchemaTest.php tests/Feature/SidangTaFinalizationTest.php tests/Feature/SidangTaRevisionDraftTest.php tests/Feature/SidangTaRequirementsVersionTest.php
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Unit/SidangTaMeetRequirementsTest.php tests/Unit/SidangTaAdmissionPolicyTest.php tests/Feature/SidangTaAdmissionFactsTest.php
```

Targettestbaru **8passed**. FeatureTAbertambah8 daribaseline50 (210jika202sebelumnya); Unitbaru51tidakbertambahlagi (21UnitTA), Node12tetap. Commandpolicy/facts/registrytetap33. Hitunghasilaktualjikabaselinelain, janganmenambahduplikattestuntukmengejarangka.

## 9. Verifikasi manual dan pemindahan

1. Setelahmigrate,bukadraftlamadanpreviewfile: tetapdapatdibuka,tetappakaiformlamauntukcheckpointini. Draftbelumaktifflowmeet2.
2. Buka detailpengajuanlama sebagai student/petugas: snapshot/preview/riwayatcollapse tetap. TidakadafieldversionsettingdiUI.
3. Buatdraftbaru lewatUI normal, simpanparsial; tidakdimintaisi requirements_version. Untukrevisi,source/owner/versionguardsdanfileborrowedtetap.
4. OpsionalTinkerread-only untukmemeriksametadata, bukanharusimportJSON:

```bash
php artisan tinker
```

```php
App\Models\SidangTaRegistrationDraft::latest()->first()?->only(['id', 'requirements_version', 'version', 'source_version']);
App\Models\SidangTaRegistration::latest()->first()?->only(['id', 'requirements_version', 'lock_version']);
```

Semuarowaplikasinormalmasihversion1saat52. Janganmengeditlangsungmenjadi2. Setelahtest/manualberhasil,salin **10file** (8existing+2baru) ke dadu-test. Saatmemindahkanmigrasi gunakanfilenameaslihasilmigrationgenerate. Janganikutmemindahdatabase,.env,phpunitsettinglokalvendor/cache.

Checkpointberikutnya aktivasiresolverversi+requiredsetEPRTbaru padawizard/save/checklist/submitsertapembaruanfixtureHTTPyangsesuai. Sesudahitu targetedrevision+reviewerlock/deadline+grid/ikonekspor. Tidakmenganggapmetadata52sudahmengaktifkanflowmeet.

ReferensiAPIContext7: [Laravel13 migrations](https://github.com/laravel/docs/blob/13.x/migrations.md). Asistenmenulispanduan,tidakmenjalankanmigrasi/DBFeaturetests atauubahsourceaplikasi.


## 10. Pemeriksaan panduan oleh asisten

Seluruh12blokPHP lulus php-l sesuai konteksfragmentarray/method. Probedenganvendoractualtanpabootstrapapp/DBmemastikanmodeldefaultint1,fillable+castint2,kompilasiSQLMySQLmigrationup/downuntuk2table,danaturanprohibitedkeduaFormRequestmenolak1/2/99tapimembolehkanomission. Ini bukan pelaksanaan migrasipadaDBpengguna.

Reviewindependenfixture/service/skematidakmenemukanblocker: rawinsertmengisikolomwajib,sourcefreshmemuatlock_versiondefault,fixture15filemetadatajelasbatasnya,ownerversionguarddanreplaytetap. Feature8/migrasimanualbelumdijalankanasisten. Sourceaplikasiduafoldertidakdiubah;hanyadocs.
