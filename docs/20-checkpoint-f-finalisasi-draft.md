# 20 — F: kirim draft menjadi pengajuan final

Prasyarat: E dan koreksi18 lulus, [rescan19](19-rescan-e-siap-finalisasi.md) dibaca. Pengguna mengerjakan kode di folder asli; asisten hanya menulis Markdown. Tidak ada perubahan desain di sini.

Final request hanya `draft_id`, `version`, `action=submit` serta CSRF/method spoof. Identitas, periode, kontak, snapshot dan dokumen diambil dari server. Store/update lama diganti, bukan dipertahankan sebagai jalur alternatif.

## 1. Buat file

```bash
php artisan make:class Services/SidangTaAcademicSnapshot
php artisan make:class Services/SidangTaDraftFinalizer
php artisan make:test SidangTaFinalizationTest --pest
```

Request/controller/Blade sudah ada: edit langsung tanpa --force. Tidak ada migrasi baru. Tetap gunakan MySQL Podman; database aplikasi runtime pengguna adalah dadu, sedangkan contoh test memakai dadu_testing.

## 2. Snapshot akademik server

Isi `app/Services/SidangTaAcademicSnapshot.php`. Ini memindahkan isi snapshot controller lama dengan identitas Student yang sudah diverifikasi, bukan menambahkan syarat otomatis IPK/TAK/EPrT baru:

```php
<?php

namespace App\Services;

use App\Models\Student;
use App\Models\StudentTak;
use App\Models\StudentEprt;

class SidangTaAcademicSnapshot
{
    public function forStudent(Student $student): array
    {
        $number = $student->student_number;
        $project = $student->finalProject;
        $tak = $student->tak ?? StudentTak::where('student_number', $number)->latest('test_date')->first();
        $eprt = $student->eprts()->orderByDesc('test_date')->first()
            ?? StudentEprt::where('student_number', $number)->orderByDesc('test_date')->first();
        $skpi = $student->skpi?->raw_data ?? [];
        $status = $skpi['status'] ?? $skpi['approval_status'] ?? $skpi['is_approved'] ?? 'Belum tersedia';
        if ($status === true) $status = 'Disetujui';

        return [
            'student_number' => $number, 'student_name' => $student->name,
            'study_program' => $student->study_program ?? '-', 'semester' => $student->active_semester ?? '-',
            'gpa' => $student->gpa, 'passed_credits' => $student->total_credits,
            'sidang_tingkat_2' => $student->sidangTingkat?->tingkat_2 ? 'Lulus' : 'Belum lulus / belum tersedia',
            'ta_title' => $project?->thesis_title_id ?? '-', 'ta_title_en' => $project?->thesis_title_en ?? '-',
            'advisor_1' => $project?->advisor_1 ?? '-', 'advisor_2' => $project?->advisor_2 ?? '-',
            'examiner_1' => $project?->examiner_1 ?? '-', 'examiner_2' => $project?->examiner_2 ?? '-',
            'examiner_3' => $project?->examiner_3 ?? '-',
            'exam_date' => $project?->exam_date?->format('d/m/Y') ?? '-',
            'exam_time' => $project?->exam_time ?? '-', 'exam_location' => $project?->exam_location ?? '-',
            'tak_score' => $tak?->total_points, 'eprt_score' => $eprt?->total_score,
            'bpp_status' => $student->bppPayment?->status ?? 'Belum tersedia',
            'skpi_status' => is_scalar($status) ? (string) $status : 'Tersedia',
            'skpi_tak' => $student->tak ? 'TAK tersedia' : 'TAK belum tersedia',
            'level_3_status' => $student->sidangTingkat?->tingkat_3 ? 'Lulus' : 'Belum lulus / belum tersedia',
            'ibasila_status' => $student->basilaApproval?->status ?? 'Belum tersedia',
            'student_email' => $student->email,
            'academic_source_updated_at' => $student->updated_at?->format('d/m/Y H:i'),
        ];
    }
}
```

Fallback TAK/EPrT mengikuti perilaku lama, termasuk data kosong; perubahan kebijakan skor berlaku/expired dibahas terpisah bila diminta. Timezone mengikuti runtime yang sekarang, bukan konversi otomatis tanggal existing.

## 3. Finalizer: satu transaksi, replay aman, tanpa upload ulang

Isi `app/Services/SidangTaDraftFinalizer.php`:

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

Semua berkas aktif yang sudah disimpan tetap di path privat semula. Tidak ada copy/move ulang yang bisa gagal di tengah finalisasi. Header consumed dipertahankan untuk replay, tetapi metadata draft-document dihapus dalam transaksi. Dokumen resubmit di-reset pending; file final lama baru dibersihkan sesudah metadata final baru berhasil.

Jika database gagal pada dokumen/log berikutnya, seluruh metadata/status/header kembali lewat rollback, dan file tidak disentuh. Jangan membungkus entrypoint ini dengan transaksi aplikasi luar yang masih dapat rollback setelah service kembali; sama seperti batas service D1/D2. Test RefreshDatabase memakai transaksi luar hanya untuk isolasi test.

## 4. Ganti request final lama

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
            ? 'sidang_ta_registration_update' : 'sidang_ta_registration_create';
        return $this->user()?->can($permission) ?? false;
    }

    public function rules(): array
    {
        $rules = [
            'draft_id' => ['required', 'uuid', 'exists:sidang_ta_registration_drafts,id'],
            'version' => ['required', 'integer', 'min:1'],
            'action' => ['required', 'in:submit'],
        ];
        foreach (['student_number', 'student_name', 'study_program', 'owner_user_id',
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

Tidak ada required/nullable yang bergantung action client lagi. Action kosong/salah ditolak; file di key mana pun ditolak. Internal field asing yang tidak digunakan tidak boleh di-mass-assign; finalizer hanya membaca whitelist server di atas.

## 5. Ganti store/update dan hapus pipeline yang tidak dipakai

Pada SidangTaRegistrationController tambahkan import:

```php
use App\Services\SidangTaDraftFinalizer;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
```

Ganti seluruh method store/update dan tambahkan helper final berikut:

```php
public function store(StoreSidangTaRegistrationRequest $request)
{
    return $this->finalizeDraft($request);
}

public function update(StoreSidangTaRegistrationRequest $request, SidangTaRegistration $sidang_ta_registration)
{
    return $this->finalizeDraft($request, $sidang_ta_registration);
}

private function finalizeDraft(StoreSidangTaRegistrationRequest $request, ?SidangTaRegistration $source = null)
{
    try {
        $draft = SidangTaRegistrationDraft::findOrFail($request->validated('draft_id'));
        $registration = app(SidangTaDraftFinalizer::class)->submit($request->user(), $draft,
            (int) $request->validated('version'), $source ? (int) $source->id : null);
    } catch (ValidationException $exception) {
        if ($request->expectsJson()) throw $exception;
        return redirect()->route('sidang-ta-registrations.index')->withErrors($exception->errors());
    } catch (HttpExceptionInterface $exception) {
        if ($request->expectsJson() || $exception->getStatusCode() !== 409) throw $exception;
        return redirect()->route('sidang-ta-registrations.index')
            ->withErrors(['draft' => $exception->getMessage() ?: 'Pengajuan berubah. Buka draft terbaru.']);
    }

    $url = route('sidang-ta-registrations.show', $registration);
    return $request->expectsJson()
        ? response()->json(['data' => ['id' => $registration->id, 'status' => $registration->status, 'url' => $url]])
        : redirect()->to($url)->with('success', 'Pendaftaran berhasil diproses.');
}
```

Hapus **seluruh method private** saveDocuments(), studentNumber(), student(), academicSnapshot() yang lama setelah store/update diganti. Snapshot sudah berada pada class baru. Hapus import Student/StudentTak/StudentEprt yang tidak lagi dipakai. Pertahankan Storage untuk download, DB untuk review, dan StudentContext untuk index/create/edit.

Ganti seluruh authorizeStudentOrReviewer() agar identitas final sama dengan resolusi draft, tanpa mengandalkan nama role:

```php
private function authorizeStudentOrReviewer(SidangTaRegistration $registration): void
{
    $user = auth()->user();
    if ($user->can('sidang_ta_registration_review')) return;
    try {
        $student = app(SidangTaStudentContext::class)->studentFor($user);
    } catch (ValidationException $exception) {
        abort(403);
    }
    abort_unless($registration->student_number === $student->student_number, 403);
}
```

Route store/update existing beserta permission-nya tetap dipakai. Jangan menambah route final paralel atau membiarkan adapter lama menulis file mentah. Review petugas D2 tetap memakai lock_version, tidak diubah menjadi desain baru.

## 6. Tambahkan tombol kirim di halaman tinjau

Pada wizard-review.blade.php, sebelum link Kembali ke form, tambahkan:

```blade
@php($permission = $draft->source_registration_id ? 'sidang_ta_registration_update' : 'sidang_ta_registration_create')
@if($errors->any())
    <div role="alert" class="rounded-xl bg-rose-50 p-4 text-rose-800">
        @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
    </div>
@endif
@can($permission)
    <form method="POST" x-data="{ sending: false }"
          @submit="if (sending) { $event.preventDefault(); } else { sending = true; }"
          action="{{ $draft->source_registration_id ? route('sidang-ta-registrations.update', $draft->source_registration_id) : route('sidang-ta-registrations.store') }}">
        @csrf
        @if($draft->source_registration_id) @method('PUT') @endif
        <input type="hidden" name="draft_id" value="{{ $draft->id }}">
        <input type="hidden" name="version" value="{{ $draft->version }}">
        <input type="hidden" name="action" value="submit">
        <button type="submit" class="btn btn-primary" :disabled="sending"
            x-text="sending ? 'Sedang mengirim…' : 'Kirim Pendaftaran'">Kirim Pendaftaran</button>
    </form>
@endcan
```

Tidak ada enctype multipart atau input file pada form final. Disable tombol hanya membantu UX; transaksi/idempotensi server tetap pengaman utama. Pesan “Pendaftaran belum dikirim” tetap benar pada preview sebelum submit.

## 7. Test lengkap finalisasi

Isi seluruh `tests/Feature/SidangTaFinalizationTest.php`:

```php
<?php

use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDocument;
use App\Models\SidangTaRegistrationDraft;
use App\Models\Student;
use App\Models\User;
use App\Services\SidangTaDraftFinalizer;
use App\Support\SidangTaDocumentRequirements as Requirements;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create(['username' => '1202200001',
        'email' => 'final@mahasiswa.test', 'status' => 'active',
        'auth_source' => 'manual', 'must_change_password' => false]);
    $this->student = Student::create(['student_number' => '1202200001',
        'name' => 'Final Student', 'email' => $this->user->email, 'academic_year' => 2022,
        'study_program' => 'Informatika', 'status' => 'Aktif']);
    foreach (['read', 'create', 'update', 'review'] as $action) {
        $this->user->givePermissionTo(Permission::firstOrCreate([
            'name' => "sidang_ta_registration_{$action}", 'guard_name' => 'web',
        ]));
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->period = SidangTaPeriod::create(['name' => 'Final Test', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay()]);
    $this->actingAs($this->user);
    $this->makeDraft = function (bool $accurate = true) {
        $id = $this->postJson(route('sidang-ta-drafts.store'), [
            'sidang_ta_period_id' => $this->period->id,
            'student_phone' => '081234567890', 'parent_phone' => '081234567891',
        ])->assertCreated()->json('data.id');
        $draft = SidangTaRegistrationDraft::findOrFail($id);
        $draft->payload = array_replace($draft->payload, [
            'graduation_payment_date' => today()->toDateString(), 'diploma_biodata_accurate' => $accurate,
        ]);
        $draft->save();
        foreach (array_merge(Requirements::CAPTURES, Requirements::PAYMENT,
            Requirements::typesForStep(4, $accurate)) as $type) {
            $path = "sidang_ta/drafts/{$draft->id}/{$type}.pdf";
            Storage::disk('local')->put($path, '%PDF-test');
            $draft->documents()->create(['document_type' => $type, 'file_name' => $type.'.pdf',
                'file_path' => $path, 'file_type' => 'pdf', 'file_size' => 10]);
        }
        $this->postJson(route('sidang-ta-drafts.review', $draft), ['version' => 1])->assertOk();
        return $draft->fresh();
    };
    $this->submitDraft = function ($draft, array $extra = []) {
        return $this->postJson(route('sidang-ta-registrations.store'), array_replace([
            'draft_id' => $draft->id, 'version' => $draft->version, 'action' => 'submit',
        ], $extra));
    };
    $this->makeRevision = function () {
        $draft = ($this->makeDraft)();
        $id = ($this->submitDraft)($draft)->assertOk()->json('data.id');
        $registration = SidangTaRegistration::findOrFail($id);
        $this->postJson(route('admin.sidang-ta-registrations.review', $registration), [
            'registration_version' => 1, 'action' => 'needs_review', 'review_notes' => 'Perbaiki dokumen.',
        ])->assertRedirect();
        $revisionId = $this->postJson(route('sidang-ta-drafts.revision', $registration))
            ->assertCreated()->json('data.id');
        $this->postJson(route('sidang-ta-drafts.review', $revisionId), ['version' => 1])->assertOk();
        return [$registration->fresh(), SidangTaRegistrationDraft::findOrFail($revisionId)];
    };
});

test('submits_fifteen_documents_without_reupload_and_consumes_metadata', function () {
    $draft = ($this->makeDraft)();
    $paths = Storage::disk('local')->allFiles();
    $id = ($this->submitDraft)($draft)->assertOk()->json('data.id');
    $registration = SidangTaRegistration::findOrFail($id);
    expect($registration->documents()->count())->toBe(15);
    expect($registration->academic_snapshot['student_name'])->toBe('Final Student');
    expect($registration->student_number)->toBe($this->student->student_number);
    expect($registration->lock_version)->toBe(1);
    expect($registration->logs()->count())->toBe(1);
    expect($draft->fresh()->status)->toBe('consumed');
    expect($draft->documents()->count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBe($paths);
});

test('submits_fourteen_documents_for_pdm_branch', function () {
    $draft = ($this->makeDraft)(false);
    $id = ($this->submitDraft)($draft)->assertOk()->json('data.id');
    $registration = SidangTaRegistration::findOrFail($id);
    expect($registration->documents()->count())->toBe(14);
    expect($registration->documents()->whereIn('document_type', Requirements::DIPLOMA)->exists())->toBeFalse();
});

test('repeated_submit_keeps_same_result_and_does_not_overwrite_review', function () {
    $draft = ($this->makeDraft)();
    $id = ($this->submitDraft)($draft)->assertOk()->json('data.id');
    $registration = SidangTaRegistration::findOrFail($id);
    $this->postJson(route('admin.sidang-ta-registrations.review', $registration), [
        'registration_version' => 1, 'action' => 'approved',
    ])->assertRedirect();
    ($this->submitDraft)($draft)->assertOk()->assertJsonPath('data.id', $id)
        ->assertJsonPath('data.status', 'approved');
    expect(SidangTaRegistration::count())->toBe(1);
    expect($registration->logs()->where('action', 'submitted')->count())->toBe(1);
});

test('rejects_unreviewed_stale_or_missing_file_submission', function () {
    $draft = ($this->makeDraft)();
    ($this->submitDraft)($draft, ['version' => 99])->assertStatus(409);
    $draft->reviewed_version = null;
    $draft->save();
    ($this->submitDraft)($draft)->assertStatus(409);
    $this->postJson(route('sidang-ta-drafts.review', $draft), ['version' => 1])->assertOk();
    Storage::disk('local')->delete($draft->documents()->first()->file_path);
    ($this->submitDraft)($draft)->assertUnprocessable();
    expect(SidangTaRegistration::count())->toBe(0);
    expect($draft->fresh()->status)->toBe('active');
});

test('legacy_payload_and_upload_cannot_bypass_final_request', function () {
    $this->postJson(route('sidang-ta-registrations.store'), [
        'student_phone' => '081234567890', 'parent_phone' => '081234567891',
    ])->assertUnprocessable()->assertJsonValidationErrors(['draft_id', 'action']);
    $draft = ($this->makeDraft)();
    ($this->submitDraft)($draft, ['action' => 'draft'])->assertUnprocessable();
    ($this->submitDraft)($draft, ['student_number' => '999', 'status' => 'approved'])
        ->assertUnprocessable();
    ($this->submitDraft)($draft, ['unexpected_file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])
        ->assertUnprocessable()->assertJsonValidationErrors('documents');
    expect(SidangTaRegistration::count())->toBe(0);
});

test('rechecks_period_after_review', function () {
    $draft = ($this->makeDraft)();
    $this->period->update(['close_at' => now()->subHour()]);
    ($this->submitDraft)($draft)->assertUnprocessable();
    expect($draft->fresh()->status)->toBe('active');
});

test('blocks_second_active_registration_even_for_another_period', function () {
    $draft = ($this->makeDraft)();
    $otherPeriod = SidangTaPeriod::create(['name' => 'Other', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay()]);
    SidangTaRegistration::create(['sidang_ta_period_id' => $otherPeriod->id,
        'student_number' => $this->student->student_number, 'student_name' => 'Final Student',
        'student_phone' => '081234567890', 'parent_phone' => '081234567891',
        'graduation_payment_date' => today()->toDateString(), 'diploma_biodata_accurate' => true,
        'status' => 'submitted']);
    ($this->submitDraft)($draft)->assertStatus(409);
    expect(SidangTaRegistration::count())->toBe(1);
});

test('revision_resubmits_same_registration_and_releases_obsolete_file_references', function () {
    $firstDraft = ($this->makeDraft)();
    $id = ($this->submitDraft)($firstDraft)->assertOk()->json('data.id');
    $registration = SidangTaRegistration::findOrFail($id);
    $oldPaths = $registration->documents()->whereIn('document_type', Requirements::DIPLOMA)->pluck('file_path')->all();
    $this->postJson(route('admin.sidang-ta-registrations.review', $registration), [
        'registration_version' => 1, 'action' => 'needs_review', 'review_notes' => 'Perbaiki biodata.',
    ])->assertRedirect();
    $revisionId = $this->postJson(route('sidang-ta-drafts.revision', $registration))
        ->assertCreated()->json('data.id');
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $revisionId, 'step' => 4]), [
        'version' => 1, 'diploma_biodata_accurate' => false,
        'documents' => ['pdm_submission_proof' => UploadedFile::fake()->create('pdm.pdf', 10, 'application/pdf')],
    ])->assertOk();
    $this->period->update(['close_at' => now()->subHour()]);
    $this->postJson(route('sidang-ta-drafts.review', $revisionId), ['version' => 2])->assertOk();
    $body = ['draft_id' => $revisionId, 'version' => 2, 'action' => 'submit'];
    $this->putJson(route('sidang-ta-registrations.update', $registration), $body)->assertOk()->assertJsonPath('data.id', $id);
    $this->putJson(route('sidang-ta-registrations.update', $registration), $body)->assertOk();
    expect(SidangTaRegistration::count())->toBe(1);
    expect($registration->fresh()->lock_version)->toBe(3);
    expect($registration->fresh()->review_notes)->toBeNull();
    expect($registration->documents()->count())->toBe(14);
    expect($registration->logs()->where('action', 'resubmitted')->count())->toBe(1);
    expect(SidangTaRegistrationDraft::findOrFail($revisionId)->documents()->count())->toBe(0);
    foreach ($oldPaths as $path) Storage::disk('local')->assertMissing($path);
});

test('other_user_cannot_finalize_someone_elses_draft', function () {
    $draft = ($this->makeDraft)();
    $other = User::factory()->create(['status' => 'active', 'must_change_password' => false]);
    $other->givePermissionTo('sidang_ta_registration_create');
    $this->actingAs($other);
    ($this->submitDraft)($draft)->assertForbidden();
    expect($draft->fresh()->status)->toBe('active');
});

test('database_failure_rolls_back_metadata_and_keeps_saved_files', function () {
    $draft = ($this->makeDraft)();
    $before = Storage::disk('local')->allFiles();
    $event = 'eloquent.creating: '.SidangTaRegistrationDocument::class;
    $writes = 0;
    Event::listen($event, function () use (&$writes) {
        if (++$writes === 2) throw new RuntimeException('Test DB metadata failure');
    });
    $caught = false;
    try {
        app(SidangTaDraftFinalizer::class)->submit($this->user, $draft, 1);
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Test DB metadata failure');
        $caught = true;
    } finally {
        Event::forget($event);
    }
    expect($caught)->toBeTrue();
    expect(SidangTaRegistration::count())->toBe(0);
    expect(SidangTaRegistrationDocument::count())->toBe(0);
    expect($draft->fresh()->status)->toBe('active');
    expect($draft->documents()->count())->toBe(15);
    expect(Storage::disk('local')->allFiles())->toBe($before);
});

test('html_final_submit_redirects_to_owned_detail', function () {
    $draft = ($this->makeDraft)();
    $response = $this->post(route('sidang-ta-registrations.store'), [
        'draft_id' => $draft->id, 'version' => 1, 'action' => 'submit',
    ]);
    $registration = SidangTaRegistration::where('student_number', $this->student->student_number)->firstOrFail();
    $response->assertRedirect(route('sidang-ta-registrations.show', $registration));
    $this->get(route('sidang-ta-registrations.show', $registration))->assertOk();
    $this->get(route('sidang-ta-registration-documents.download', $registration->documents()->first()))->assertOk();
});

test('revision_rollback_restores_final_and_borrowed_draft_references', function () {
    [$registration, $revision] = ($this->makeRevision)();
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $revision, 'step' => 4]), [
        'version' => 1, 'diploma_biodata_accurate' => false,
        'documents' => ['pdm_submission_proof' => UploadedFile::fake()->create('pdm.pdf', 10, 'application/pdf')],
    ])->assertOk();
    $this->postJson(route('sidang-ta-drafts.review', $revision), ['version' => 2])->assertOk();
    $beforeFiles = Storage::disk('local')->allFiles();
    $event = 'eloquent.creating: '.\App\Models\SidangTaRegistrationLog::class;
    Event::listen($event, function () { throw new RuntimeException('Test revision log failure'); });
    $caught = false;
    try {
        app(SidangTaDraftFinalizer::class)->submit($this->user, $revision->fresh(), 2, (int) $registration->id);
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Test revision log failure');
        $caught = true;
    } finally {
        Event::forget($event);
    }
    expect($caught)->toBeTrue();
    expect($registration->fresh()->status)->toBe('needs_review');
    expect($registration->fresh()->lock_version)->toBe(2);
    expect($registration->fresh()->review_notes)->toBe('Perbaiki dokumen.');
    expect($registration->fresh()->diploma_biodata_accurate)->toBeTrue();
    expect($registration->documents()->count())->toBe(15);
    expect($registration->logs()->where('action', 'resubmitted')->count())->toBe(0);
    expect($revision->fresh()->status)->toBe('active');
    expect($revision->documents()->count())->toBe(14);
    expect($revision->documents()->whereNotNull('source_document_id')->count())->toBe(13);
    expect(Storage::disk('local')->allFiles())->toBe($beforeFiles);
});

test('revision_rejects_wrong_endpoint_wrong_source_and_stale_source', function () {
    [$registration, $revision] = ($this->makeRevision)();
    $body = ['draft_id' => $revision->id, 'version' => 1, 'action' => 'submit'];
    $this->postJson(route('sidang-ta-registrations.store'), $body)->assertStatus(409);
    $other = SidangTaRegistration::create([
        'sidang_ta_period_id' => $this->period->id, 'student_number' => $this->student->student_number,
        'student_name' => 'Final Student', 'student_phone' => '081234567890',
        'parent_phone' => '081234567891', 'graduation_payment_date' => today()->toDateString(),
        'diploma_biodata_accurate' => true, 'status' => 'rejected',
    ]);
    $this->putJson(route('sidang-ta-registrations.update', $other), $body)->assertStatus(409);
    $registration->lock_version++;
    $registration->save();
    $this->putJson(route('sidang-ta-registrations.update', $registration), $body)->assertStatus(409);
    expect($registration->fresh()->status)->toBe('needs_review');
    expect($revision->fresh()->status)->toBe('active');
    expect($revision->documents()->count())->toBe(15);
    expect($other->fresh()->status)->toBe('rejected');
});

```

Ada tiga belas test finalisasi. Regresi fixture memakai file tersimpan sederhana untuk isolasi finalizer; validasi MIME upload nyata sudah berada di D1 dan uji browser. Event failure dipasang hanya pada model dokumen final dan dilepas di finally; bukan mematikan seluruh event/auth.

## 8. Jalankan semua test dan build

phpunit.xml workspace sudah kembali ke konfigurasi awal; untuk memastikan MySQL Podman tetap dipakai, gunakan seluruh parameter DB lokal secara eksplisit. Sesuaikan password lokal bila berbeda:

```bash
php artisan config:clear
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaDraftSchemaTest.php tests/Feature/SidangTaPeriodTest.php tests/Feature/SidangTaDraftTest.php tests/Feature/SidangTaDraftUploadTest.php tests/Feature/SidangTaRevisionDraftTest.php tests/Feature/SidangTaWizardTest.php tests/Feature/SidangTaFinalizationTest.php
npm run build
```

Jangan gunakan database dadu/dadu_test aplikasi untuk RefreshDatabase ini. Command generator/perubahan request tidak memerlukan migrate baru. Test lama snapshot/owner/period harus tetap lulus; jangan memperbaiki dengan menonaktifkan permission atau mengurangi assertion.

## 9. Verifikasi browser dan konkurensi

- [ ] Lengkapi empat tahap, tinjau, lalu Kirim: Network final request tidak membawa file; hasil menuju detail dan satu pengajuan submitted tercatat.
- [ ] Klik ganda/retry request yang sama: tetap satu pengajuan dan satu log submitted; bila petugas sudah memutuskan, retry tidak mengubah keputusan.
- [ ] Kirim dua tab dengan draft/version sama hampir bersamaan; periksa database testing/local yang dipakai uji bahwa jumlah registration dan log submit tetap satu. Test sequential di atas tidak membuktikan row lock pada dua koneksi.
- [ ] Ubah draft dari tab lain lalu kirim versi lama: ditolak, tidak menimpa versi terbaru.
- [ ] Tutup periode sesudah tinjau untuk pengajuan baru: ditolak; draft dan file tetap bisa dipulihkan/dipilih periode baru.
- [ ] Minta revisi, ganti salah satu file/cabang, tinjau dan kirim: registration ID tetap, version final bertambah, file lama hanya dihapus sesudah sukses dan yang dipakai ulang tetap ada.
- [ ] Catatan/perubahan keputusan petugas setelah draft dimulai membuat resubmit ditolak; discard stale tetap bisa dari index.
- [ ] Riwayat tidak menampilkan draft consumed sebagai draft aktif. Detail masih sederhana; visual ditunda sesuai arahan pengguna.

Untuk pemeriksaan data setelah dua tab, jalankan SQL baca di database pengujian yang benar (ganti NIM sesuai fixture uji browser):

```sql
SELECT id, student_number, status, lock_version FROM sidang_ta_registrations
WHERE student_number = '1202200001' ORDER BY id;
SELECT sidang_ta_registration_id, action, COUNT(*) AS jumlah
FROM sidang_ta_registration_logs
WHERE action IN ('submitted', 'resubmitted')
GROUP BY sidang_ta_registration_id, action;
```

Resubmitted dapat lebih dari satu jika memang ada beberapa siklus revisi; untuk uji retry satu siklus seharusnya hanya menambah satu log. Jangan menghapus histori agar hitungan terlihat benar.

## 10. Berkas untuk rescan dan batas selesai

Kirim dua service baru, request final, controller final, Blade review, dan test finalisasi. Tidak perlu memindahkan `.env` atau kredensial server. phpunit.xml lokal dipulihkan pengguna sebelum commit/push; asisten tidak melakukan commit.

Cari ulang di controller TA: `saveDocuments`, `$request->file`, `academicSnapshot`, `studentNumber`, `SidangTaPeriod::active`. Setelah penggantian, method/path legacy tersebut tidak dipakai final store/update. Jangan menghapus helper/module lain di luar TA.

Sesudah F lulus, fungsi student dapat diaudit menyeluruh sebelum perapian detail/visual. Desain petugas penuh tetap menunggu pengguna. Belum ada test/build/browser yang dijalankan asisten pada sesi penulisan ini.

Referensi Context7: [transaksi dan lock](https://github.com/laravel/docs/blob/13.x/database.md#database-transactions), [validasi Laravel 13](https://github.com/laravel/docs/blob/13.x/validation.md), [fake storage testing](https://github.com/laravel/docs/blob/13.x/filesystem.md).
