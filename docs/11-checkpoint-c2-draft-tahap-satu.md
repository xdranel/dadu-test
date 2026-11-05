# 11 — Checkpoint C2: simpan dan lanjutkan draft tahap 1

**Prasyarat:** perbaiki temuan [10](10-rescan-b-c1-koreksi-model-dokumen.md), lalu pastikan test schema/periode lulus pada MySQL Podman. Dokumen ini memberikan kode lengkap endpoint backend tahap 1 dan feature test. Form visual dipasang pada E; upload tahap 2–4 dan revisi pada D. Jangan menampilkan tombol wizard di sidebar sebelum endpoint+UI lengkap.

`phpunit.xml` lokal boleh tetap memakai Podman selama pengerjaan student, lalu dikembalikan sebelum commit/push sesuai keputusan pengguna. Semua kode di bawah disalin pengguna ke folder asli; asisten tidak menulis source.

## 1. Resolusi student dari akun

Buat `app/Services/SidangTaStudentContext.php`:

```php
<?php

namespace App\Services;

use App\Models\Student;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class SidangTaStudentContext
{
    public function studentFor(User $user): Student
    {
        if (filled($user->email)) {
            $student = Student::where('email', $user->email)->first();
            if ($student) {
                return $student;
            }
        }

        $numbers = array_values(array_filter([
            $user->username, $user->employee_id,
        ], fn ($value) => filled($value)));
        $matches = Student::whereIn('student_number', $numbers)->limit(2)->get();

        if ($matches->count() !== 1) {
            throw ValidationException::withMessages([
                'student' => 'Akun belum terhubung dengan satu data mahasiswa yang valid.',
            ]);
        }

        return $matches->first();
    }
}
```

Snapshot akademik controller lama belum dipindah pada C2; metode snapshot ditambahkan saat finalisasi agar tidak mengubah sumber data sekarang.

## 2. Request tahap 1

Buat `app/Http/Requests/SaveSidangTaDraftStepRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveSidangTaDraftStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $draft = $this->route('draft');

        if (!$user || !$user->can('sidang_ta_registration_create')) {
            return false;
        }

        return !$draft || (string) $draft->owner_user_id === (string) $user->id;
    }

    public function rules(): array
    {
        return [
            'sidang_ta_period_id' => ['required', 'integer', 'exists:sidang_ta_periods,id'],
            'student_phone' => ['required', 'string', 'max:30'],
            'parent_phone' => ['required', 'string', 'max:30'],
            'version' => [$this->route('draft') ? 'required' : 'nullable', 'integer', 'min:1'],
            'owner_user_id' => ['prohibited'], 'student_number' => ['prohibited'],
            'status' => ['prohibited'], 'payload' => ['prohibited'],
            'source_registration_id' => ['prohibited'], 'source_version' => ['prohibited'],
            'reviewed_version' => ['prohibited'], 'submitted_registration_id' => ['prohibited'],
            'documents' => ['prohibited'], 'file_path' => ['prohibited'],
        ];
    }
}
```

C2 khusus **draft baru**; source revisi sengaja ditolak. Jangan menganggap request ini sudah mendukung revisi dengan permission update. Perluas secara eksplisit pada D, bersama testnya. Kelengkapan kontak/periode tetap diperiksa server; step/owner/status tidak dapat ditentukan client.

## 3. Service draft tahap 1

Buat `app/Services/SidangTaDraftService.php`:

```php
<?php

namespace App\Services;

use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDraft;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SidangTaDraftService
{
    public function __construct(private SidangTaStudentContext $context) {}

    public function owned(User $user, SidangTaRegistrationDraft $draft): SidangTaRegistrationDraft
    {
        abort_unless((string) $draft->owner_user_id === (string) $user->id, 403);
        $student = $this->context->studentFor($user);
        abort_unless($draft->student_number === $student->student_number, 403);
        abort_unless($draft->status === SidangTaRegistrationDraft::STATUS_ACTIVE, 409);
        abort_if($draft->source_registration_id !== null, 409, 'Draft revisi belum didukung tahap ini.');

        return $draft;
    }

    private function openPeriod(int $id): SidangTaPeriod
    {
        $period = SidangTaPeriod::query()->whereKey($id)
            ->openForRegistration()->lockForUpdate()->first();

        if (!$period) {
            throw ValidationException::withMessages([
                'sidang_ta_period_id' => 'Pilih periode yang masih terbuka.',
            ]);
        }

        return $period;
    }

    public function start(User $user, array $validated): SidangTaRegistrationDraft
    {
        abort_unless($user->can('sidang_ta_registration_create'), 403);
        $student = $this->context->studentFor($user);

        return DB::transaction(function () use ($user, $validated, $student) {
            $student = Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            abort_if(SidangTaRegistration::where('student_number', $student->student_number)
                ->whereIn('status', ['submitted', 'needs_review'])->exists(),
                409, 'Anda masih memiliki pengajuan aktif.');

            $existing = SidangTaRegistrationDraft::where('student_number', $student->student_number)
                ->where('status', SidangTaRegistrationDraft::STATUS_ACTIVE)
                ->whereNull('source_registration_id')->lockForUpdate()->first();

            if ($existing) {
                abort_unless((string) $existing->owner_user_id === (string) $user->id, 409);
                return $existing; // Retry start tidak menimpa draft; ubah lewat saveStep.
            }

            $period = $this->openPeriod((int) $validated['sidang_ta_period_id']);

            return SidangTaRegistrationDraft::create([
                'owner_user_id' => $user->id,
                'student_number' => $student->student_number,
                'sidang_ta_period_id' => $period->id,
                'payload' => [
                    'student_phone' => $validated['student_phone'],
                    'parent_phone' => $validated['parent_phone'],
                ],
            ]);
        });
    }

    public function saveStep(User $user, SidangTaRegistrationDraft $draft, int $step,
        int $expectedVersion, array $validated): SidangTaRegistrationDraft
    {
        abort_unless($user->can('sidang_ta_registration_create'), 403);
        $this->owned($user, $draft);
        abort_unless($step === 1, 409, 'Penyimpanan tahap 2–4 belum tersedia.');
        $student = $this->context->studentFor($user);

        return DB::transaction(function () use ($user, $draft, $expectedVersion, $validated, $student) {
            Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $locked = SidangTaRegistrationDraft::whereKey($draft->id)->lockForUpdate()->firstOrFail();
            $this->owned($user, $locked);
            abort_unless($locked->version === $expectedVersion, 409, 'Draft berubah. Muat ulang terlebih dahulu.');
            $period = $this->openPeriod((int) $validated['sidang_ta_period_id']);

            $locked->payload = array_replace($locked->payload ?? [], [
                'student_phone' => $validated['student_phone'],
                'parent_phone' => $validated['parent_phone'],
            ]);
            $locked->sidang_ta_period_id = $period->id;
            $locked->version++;
            $locked->reviewed_version = null;
            $locked->save();

            return $locked;
        });
    }
}
```

Lock Student menserialisasi start untuk NIM yang sama. Semua mutasi draft berikutnya harus mengikuti urutan Student → draft → source/periode bila diperlukan. C2 belum membuktikan race dua proses; test HTTP di bawah hanya membuktikan retry dan konflik versi sequential. Semua file/finalisasi tetap dikerjakan D/F, bukan ditambahkan ke service sebagai stub kosong.

## 4. Controller dan route

Buat `app/Http/Controllers/SidangTaDraftController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveSidangTaDraftStepRequest;
use App\Models\SidangTaRegistrationDraft;
use App\Services\SidangTaDraftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SidangTaDraftController extends Controller
{
    public function __construct(private SidangTaDraftService $service) {}

    private function respond(SidangTaRegistrationDraft $draft, int $status = 200): JsonResponse
    {
        return response()->json(['data' => [
            'id' => $draft->id,
            'sidang_ta_period_id' => $draft->sidang_ta_period_id,
            'payload' => $draft->payload,
            'version' => $draft->version,
            'status' => $draft->status,
        ]], $status);
    }

    public function store(SaveSidangTaDraftStepRequest $request): JsonResponse
    {
        return $this->respond($this->service->start($request->user(), $request->validated()), 201);
    }

    public function edit(Request $request, SidangTaRegistrationDraft $draft): JsonResponse
    {
        return $this->respond($this->service->owned($request->user(), $draft));
    }

    public function saveStep(SaveSidangTaDraftStepRequest $request,
        SidangTaRegistrationDraft $draft, int $step): JsonResponse
    {
        return $this->respond($this->service->saveStep(
            $request->user(), $draft, $step,
            (int) $request->validated('version'), $request->validated()
        ));
    }
}
```

Tambahkan route berikut **di dalam** grup `/dashboard` yang sudah berada di auth/password.changed. FQCN dipakai agar tidak perlu import tambahan:

```php
Route::post('sidang-ta-drafts', [\App\Http\Controllers\SidangTaDraftController::class, 'store'])
    ->middleware('permission:sidang_ta_registration_create')->name('sidang-ta-drafts.store');
Route::get('sidang-ta-drafts/{draft}/edit', [\App\Http\Controllers\SidangTaDraftController::class, 'edit'])
    ->middleware('permission:sidang_ta_registration_read')->name('sidang-ta-drafts.edit');
Route::post('sidang-ta-drafts/{draft}/steps/{step}', [\App\Http\Controllers\SidangTaDraftController::class, 'saveStep'])
    ->where('step', '[1-4]')->middleware('permission:sidang_ta_registration_create')
    ->name('sidang-ta-drafts.steps.store');
```

C2 menyediakan response JSON untuk pengujian backend. GET edit ini belum halaman wizard; saat E dipasang controller menambahkan view/redirect untuk HTML dan mempertahankan kontrak JSON untuk test. Jangan mengubah route final student lama dahulu atau mencampur form lama dengan endpoint JSON.

## 5. Kode lengkap test tahap 1

Buat `tests/Feature/SidangTaDraftTest.php`:

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
    $this->studentUser = User::factory()->create([
        'username' => '1202200001', 'email' => 'draft-test@mahasiswa.test',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false,
    ]);
    Student::create([
        'student_number' => '1202200001', 'name' => 'Draft Student',
        'email' => $this->studentUser->email, 'academic_year' => 2022,
        'study_program' => 'Informatika', 'status' => 'Aktif',
    ]);
    foreach (['create', 'read'] as $action) {
        $permission = Permission::firstOrCreate([
            'name' => "sidang_ta_registration_{$action}", 'guard_name' => 'web',
        ]);
        $this->studentUser->givePermissionTo($permission);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->period = SidangTaPeriod::create([
        'name' => 'Periode C2', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay(),
    ]);
    $this->data = [
        'sidang_ta_period_id' => $this->period->id,
        'student_phone' => '081234567890', 'parent_phone' => '081234567891',
    ];
});

test('starts_and_resumes_saved_stage_one_without_final_submission', function () {
    $before = SidangTaRegistration::count();
    $response = $this->actingAs($this->studentUser)
        ->postJson(route('sidang-ta-drafts.store'), $this->data)->assertCreated();
    $id = $response->json('data.id');

    $this->getJson(route('sidang-ta-drafts.edit', $id))->assertOk()
        ->assertJsonPath('data.payload.student_phone', '081234567890')
        ->assertJsonPath('data.sidang_ta_period_id', $this->period->id)
        ->assertJsonPath('data.version', 1);
    expect(SidangTaRegistration::count())->toBe($before);
    expect(SidangTaRegistrationDraft::findOrFail($id)->owner_user_id)
        ->toBe($this->studentUser->id);
});

test('retry_start_returns_same_draft_without_overwriting', function () {
    $first = $this->actingAs($this->studentUser)
        ->postJson(route('sidang-ta-drafts.store'), $this->data)->assertCreated();
    $second = $this->postJson(route('sidang-ta-drafts.store'), array_replace($this->data, [
        'student_phone' => '080000000000',
    ]))->assertCreated();

    expect($second->json('data.id'))->toBe($first->json('data.id'));
    $second->assertJsonPath('data.payload.student_phone', '081234567890');
    expect(SidangTaRegistrationDraft::where('owner_user_id', $this->studentUser->id)->count())->toBe(1);
});

test('rejects_closed_period_and_incomplete_contacts', function () {
    $this->period->update(['close_at' => now()->subHour()]);
    $this->actingAs($this->studentUser)
        ->postJson(route('sidang-ta-drafts.store'), $this->data)
        ->assertUnprocessable()->assertJsonValidationErrors('sidang_ta_period_id');
    $this->postJson(route('sidang-ta-drafts.store'), [])
        ->assertUnprocessable()->assertJsonValidationErrors(['student_phone', 'parent_phone']);
    expect(SidangTaRegistrationDraft::count())->toBe(0);
});

test('rejects_client_supplied_internal_metadata', function () {
    $this->actingAs($this->studentUser)
        ->postJson(route('sidang-ta-drafts.store'), array_replace($this->data, [
            'owner_user_id' => 999, 'student_number' => '999', 'status' => 'consumed',
        ]))->assertUnprocessable()
        ->assertJsonValidationErrors(['owner_user_id', 'student_number', 'status']);
    expect(SidangTaRegistrationDraft::count())->toBe(0);
});

test('saves_contacts_and_rejects_stale_version', function () {
    $id = $this->actingAs($this->studentUser)
        ->postJson(route('sidang-ta-drafts.store'), $this->data)->assertCreated()->json('data.id');
    $url = route('sidang-ta-drafts.steps.store', ['draft' => $id, 'step' => 1]);
    $data = array_replace($this->data, ['version' => 1, 'student_phone' => '089999999999']);
    SidangTaRegistrationDraft::whereKey($id)->update(['reviewed_version' => 1]);

    $this->postJson($url, $data)->assertOk()->assertJsonPath('data.version', 2);
    $this->postJson($url, array_replace($data, ['student_phone' => '080000000000']))
        ->assertStatus(409);
    $draft = SidangTaRegistrationDraft::findOrFail($id);
    expect($draft->payload['student_phone'])->toBe('089999999999');
    expect($draft->reviewed_version)->toBeNull();
});

test('other_user_cannot_read_or_save_draft_even_with_permissions', function () {
    $id = $this->actingAs($this->studentUser)
        ->postJson(route('sidang-ta-drafts.store'), $this->data)->assertCreated()->json('data.id');
    $other = User::factory()->create(['status' => 'active', 'must_change_password' => false]);
    $other->givePermissionTo(['sidang_ta_registration_read', 'sidang_ta_registration_create']);

    $this->actingAs($other)->getJson(route('sidang-ta-drafts.edit', $id))->assertForbidden();
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $id, 'step' => 1]),
        array_replace($this->data, ['version' => 1]))->assertForbidden();
});

test('cannot_skip_to_unimplemented_stage', function () {
    $id = $this->actingAs($this->studentUser)
        ->postJson(route('sidang-ta-drafts.store'), $this->data)->assertCreated()->json('data.id');
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $id, 'step' => 4]),
        array_replace($this->data, ['version' => 1]))->assertStatus(409);
    expect(SidangTaRegistrationDraft::findOrFail($id)->version)->toBe(1);
});

test('unlinked_user_cannot_start_draft', function () {
    $other = User::factory()->create(['status' => 'active', 'must_change_password' => false]);
    $other->givePermissionTo('sidang_ta_registration_create');
    $this->actingAs($other)->postJson(route('sidang-ta-drafts.store'), $this->data)
        ->assertUnprocessable()->assertJsonValidationErrors('student');
});
```

C2 belum menguji ulang login sungguhan atau berkas yang belum ada. Persistence di sini dibuktikan melalui request GET terpisah yang membaca row server. Uji tutup browser dan file tersimpan dilakukan setelah UI/upload tersedia.

## 6. Jalankan di MySQL dan kirim hasil

Selama phpunit.xml masih memakai konfigurasi lokal 09:

```bash
php artisan config:clear
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing php artisan test tests/Feature/SidangTaDraftSchemaTest.php tests/Feature/SidangTaPeriodTest.php tests/Feature/SidangTaDraftTest.php
```

Hasil yang diharapkan: 9 test schema sesudah koreksi 10, 9 test periode, 8 test C2. Jangan memindah konfigurasi database lokal ke server.

Setelah phpunit.xml dipulihkan nanti, koneksi lokal yang sama tetap bisa dipakai melalui environment lengkap:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaDraftTest.php
```

Sesuaikan password lokal; jangan salin kredensial produksi. Untuk semua suite, ulangi command yang sama dengan path test yang dibutuhkan.

Setelah test lulus, beri tahu agar rescan source asli dapat dilakukan sebelum atau sesudah disalin ke dadu-test sesuai kebutuhanmu. Berikutnya D menambahkan simpan upload tahap 2–4, revisi dan cleanup aman; E memasang wizard HTML. Jangan menambahkan tombol submit final draft sebelum F.

Referensi diperiksa melalui Context7: [Laravel HTTP tests](https://github.com/laravel/docs/blob/13.x/http-tests.md), [row locking](https://github.com/laravel/docs/blob/13.x/queries.md#pessimistic-locking). Asisten hanya memeriksa sintaks dokumen, bukan menjalankan kode aplikasi atau mengklaim test sudah lulus.


Pembaruan setelah pengguna menyelesaikan C2 dan menyalin source: baca [12 rescan C2](12-rescan-c2-sebelum-upload.md) untuk typo dan relasi sisa, lalu [13 D1](13-checkpoint-d1-upload-draft.md) untuk upload dan command generator. Request pada 13 menggantikan request C2; test skip tahap pada dokumen ini juga harus diganti sesuai 13.
