# 15 — D2: draft revisi dan hapus draft secara aman

Prasyarat: D1 lulus dan [rescan 14](14-rescan-d1-sebelum-revisi.md) dibaca. Pengguna mengerjakan kode berikut di folder asli. Asisten hanya menulis Markdown; JSON backend belum menjadi form wizard HTML sampai E. Finalisasi draft tetap menunggu F.

Revisi hanya untuk owner pengajuan `needs_review`, memakai permission update dan periode asal walaupun tutup. Save draft tidak mengubah final, catatan petugas, atau dokumen final. Setelah source berubah, draft dianggap stale dan harus dibuang/dibuat ulang secara eksplisit.

## 1. Perintah membuat file

```bash
php artisan make:class Services/SidangTaDraftAccess
php artisan make:class Services/SidangTaRevisionDraftService
php artisan make:test SidangTaRevisionDraftTest --pest
```

File lainnya sudah ada: edit, jangan generate ulang/--force. Tidak ada migrasi baru; source_version, source_document_id dan lock_version sudah dibuat C1.

## 2. Pemeriksaan owner, permission dan versi source bersama

Isi `app/Services/SidangTaDraftAccess.php`:

```php
<?php

namespace App\Services;

use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDraft;
use App\Models\Student;
use App\Models\User;

class SidangTaDraftAccess
{
    public function __construct(private SidangTaStudentContext $context) {}

    public function owner(User $user, SidangTaRegistrationDraft $draft): Student
    {
        abort_unless((string) $draft->owner_user_id === (string) $user->id, 403);
        $student = $this->context->studentFor($user);
        abort_unless($draft->student_number === $student->student_number, 403);
        return $student;
    }

    public function assertCanWrite(User $user, SidangTaRegistrationDraft $draft): void
    {
        $permission = $draft->source_registration_id === null
            ? 'sidang_ta_registration_create' : 'sidang_ta_registration_update';
        abort_unless($user->can($permission), 403);
    }

    public function check(User $user, SidangTaRegistrationDraft $draft, bool $write = false): void
    {
        $this->owner($user, $draft);
        abort_unless($draft->status === SidangTaRegistrationDraft::STATUS_ACTIVE, 409);
        if ($write) {
            $this->assertCanWrite($user, $draft);
        }
        if ($draft->source_registration_id === null) {
            return;
        }

        // write=true hanya dipanggil di dalam transaksi setelah lock Student/draft.
        $query = SidangTaRegistration::whereKey($draft->source_registration_id);
        $source = ($write ? $query->lockForUpdate() : $query)->firstOrFail();
        abort_unless($source->student_number === $draft->student_number, 403);
        abort_unless($source->status === 'needs_review'
            && $source->lock_version === $draft->source_version, 409,
            'Pengajuan berubah. Buang draft revisi lama lalu muat ulang pengajuan.');

        foreach ($draft->documents()->whereNotNull('source_document_id')->get() as $document) {
            $valid = $source->documents()->whereKey($document->source_document_id)
                ->where('document_type', $document->document_type)
                ->where('file_path', $document->file_path)->exists();
            abort_unless($valid, 409, 'Referensi dokumen pengajuan sudah berubah.');
        }
    }
}
```

`owner()` terpisah agar draft stale tetap bisa dibuang. Jangan menggunakan check() yang mewajibkan source masih needs_review untuk discard. Endpoint download/resume tetap dilindungi permission read melalui route.

## 3. Start revisi dan discard

Isi `app/Services/SidangTaRevisionDraftService.php`:

```php
<?php

namespace App\Services;

use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDraft;
use App\Models\Student;
use App\Models\User;
use App\Support\SidangTaDocumentRequirements as Requirements;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SidangTaRevisionDraftService
{
    public function __construct(private SidangTaStudentContext $context,
        private SidangTaDraftAccess $access) {}

    public function start(User $user, SidangTaRegistration $registration): SidangTaRegistrationDraft
    {
        abort_unless($user->can('sidang_ta_registration_update'), 403);
        $student = $this->context->studentFor($user);
        abort_unless($registration->student_number === $student->student_number, 403);

        return DB::transaction(function () use ($user, $registration, $student) {
            Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $existing = SidangTaRegistrationDraft::where('source_registration_id', $registration->id)
                ->where('status', SidangTaRegistrationDraft::STATUS_ACTIVE)->lockForUpdate()->first();
            $source = SidangTaRegistration::whereKey($registration->id)->lockForUpdate()->firstOrFail();
            abort_unless($source->student_number === $student->student_number, 403);
            abort_unless($source->status === 'needs_review', 409, 'Pengajuan tidak sedang membutuhkan revisi.');
            if ($existing) {
                abort_unless((string) $existing->owner_user_id === (string) $user->id, 409);
                $this->access->check($user, $existing, true);
                return $existing;
            }

            $draft = SidangTaRegistrationDraft::create([
                'owner_user_id' => $user->id, 'student_number' => $student->student_number,
                'sidang_ta_period_id' => $source->sidang_ta_period_id,
                'source_registration_id' => $source->id, 'source_version' => $source->lock_version,
                'payload' => [
                    'student_phone' => $source->student_phone, 'parent_phone' => $source->parent_phone,
                    'graduation_payment_date' => $source->graduation_payment_date?->toDateString(),
                    'diploma_biodata_accurate' => $source->diploma_biodata_accurate,
                ],
            ]);
            $types = array_merge(Requirements::CAPTURES, Requirements::PAYMENT,
                Requirements::typesForStep(4, $source->diploma_biodata_accurate));
            foreach ($source->documents()->whereIn('document_type', $types)->get() as $document) {
                $draft->documents()->create([
                    'document_type' => $document->document_type, 'file_name' => $document->file_name,
                    'file_path' => $document->file_path, 'file_size' => $document->file_size,
                    'file_type' => $document->file_type, 'source_document_id' => $document->id,
                ]);
            }
            return $draft;
        });
    }

    public function discard(User $user, SidangTaRegistrationDraft $draft, int $version): void
    {
        $student = $this->access->owner($user, $draft);
        $this->access->assertCanWrite($user, $draft);
        $paths = DB::transaction(function () use ($user, $draft, $version, $student) {
            Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $locked = SidangTaRegistrationDraft::whereKey($draft->id)->lockForUpdate()->firstOrFail();
            $this->access->owner($user, $locked);
            $this->access->assertCanWrite($user, $locked);
            abort_unless($locked->status === SidangTaRegistrationDraft::STATUS_ACTIVE
                && $locked->version === $version, 409);
            // Hanya file milik draft, bukan file final yang dipinjam.
            $paths = $locked->documents()->whereNull('source_document_id')->pluck('file_path')->all();
            $locked->delete();
            return $paths;
        });
        foreach (array_unique($paths) as $path) {
            try {
                if (!Storage::disk('local')->delete($path)) {
                    Log::warning('File draft terhapus metadata tetapi perlu cleanup.', ['path' => $path]);
                }
            } catch (Throwable $exception) {
                Log::warning('Cleanup discard gagal.', ['path' => $path, 'error' => $exception->getMessage()]);
            }
        }
    }
}
```

Meskipun nama service memuat Revision, discard ini juga menangani draft baru dengan permission create. Jangan menghapus direktori draft secara rekursif: setelah finalisasi F, path di direktori itu bisa dipakai registration final. Discard menolak consumed.

## 4. Hubungkan pengecekan akses dan simpan tahap 1

Pada constructor `SidangTaDraftService`, tambahkan dependency:

```php
private SidangTaDraftAccess $access,
```

Ganti seluruh method owned menjadi:

```php
public function owned(User $user, SidangTaRegistrationDraft $draft): SidangTaRegistrationDraft
{
    $this->access->check($user, $draft);
    return $draft;
}
```

Dalam **saveStep saja**, ganti abort permission create paling awal dengan:

```php
$this->access->assertCanWrite($user, $draft);
```

Jangan mengganti izin create pada start() draft baru. Di transaksi saveStep tahap 1, setelah mengambil `$locked`, ganti `$this->owned($user, $locked);` dengan:

```php
$this->access->check($user, $locked, true);
```

Setelah pemeriksaan expectedVersion, ganti penentuan `$period = $this->openPeriod(...)` pada **saveStep tahap 1** dengan:

```php
if ($locked->source_registration_id !== null) {
    if ((int) $validated['sidang_ta_period_id'] !== (int) $locked->sidang_ta_period_id) {
        throw ValidationException::withMessages([
            'sidang_ta_period_id' => 'Periode revisi harus mengikuti pengajuan asal.',
        ]);
    }
    $period = $locked->period; // Boleh tertutup; bukan pilihan periode baru.
} else {
    $period = $this->openPeriod((int) $validated['sidang_ta_period_id']);
}
```

Payload kontak/version/reset reviewed_version sesudahnya tetap sama. **Jangan mengubah openPeriod pada start() baru.**

## 5. Upload revisi jangan menghapus file final

Pada constructor `SidangTaDraftDocumentService`, tambahkan `private SidangTaDraftAccess $access` setelah context. Dalam save(), ganti abort permission create awal menjadi `$this->access->assertCanWrite($user, $draft);`.

Di transaksi upload, setelah `$locked` dimuat, **ganti** blok owner/NIM/status/source/version berikut sampai guard `whereNotNull('source_document_id')` dengan:

```php
$this->access->check($user, $locked, true);
abort_unless($locked->version === $expectedVersion, 409, 'Draft berubah. Muat ulang.');
```

`check()` sekarang menjaga owner/NIM/status dan source version, sehingga guard yang melarang seluruh source_document_id tidak dipakai lagi. Pertahankan lock Student/draft dan gate tahap.

Di array metadata updateOrCreate, **tambahkan**:

```php
'source_document_id' => null,
```

Ini memutus referensi pinjaman hanya pada file yang benar-benar diganti. Di bawahnya ganti if lama yang menambahkan `$old->file_path` ke retiredPaths menjadi:

```php
if ($old && $old->source_document_id === null) {
    $retiredPaths[] = $old->file_path;
}
```

Di loop penghapusan metadata cabang obsolete tahap 4, ganti penambahan path menjadi:

```php
if ($old->source_document_id === null) {
    $retiredPaths[] = $old->file_path;
}
$old->delete();
```

Pastikan hanya satu `$old->delete()` pada loop itu. Hapus metadata draft cabang lama, **bukan** row final yang dipinjam. File final lama tidak dihapus hingga finalisasi F benar-benar sukses. Existing file lama yang dahulu menerima JPG tetap bisa dipakai tanpa upload; pengganti tahap 3 tetap PDF.

## 6. Request, controller dan routes

Ganti **method authorize saja** pada SaveSidangTaDraftStepRequest:

```php
public function authorize(): bool
{
    $user = $this->user();
    $draft = $this->route('draft');
    $permission = $draft && $draft->source_registration_id !== null
        ? 'sidang_ta_registration_update' : 'sidang_ta_registration_create';
    return $user && $user->can($permission)
        && (!$draft || (string) $draft->owner_user_id === (string) $user->id);
}
```

Rules larangan metadata internal, whitelist file dan scalar per tahap tetap dipertahankan. Source revision ditentukan **parameter route**, bukan source_registration_id pada body.

Pada SidangTaDraftController tambahkan import `App\Models\SidangTaRegistration` dan `App\Services\SidangTaRevisionDraftService`, lalu tambahkan dependency `private SidangTaRevisionDraftService $revisions` di constructor. Tambahkan method berikut:

```php
public function startRevision(Request $request, SidangTaRegistration $registration): JsonResponse
{
    $request->validate([
        'owner_user_id' => 'prohibited', 'student_number' => 'prohibited',
        'sidang_ta_period_id' => 'prohibited', 'payload' => 'prohibited',
        'source_registration_id' => 'prohibited', 'source_version' => 'prohibited',
        'documents' => 'prohibited',
    ]);
    return $this->respond($this->revisions->start($request->user(), $registration), 201);
}

public function destroy(Request $request, SidangTaRegistrationDraft $draft)
{
    $data = $request->validate(['version' => ['required', 'integer', 'min:1']]);
    $this->revisions->discard($request->user(), $draft, (int) $data['version']);
    return response()->noContent();
}
```

Tambahkan `source_registration_id` dan `source_version` pada array data respond() agar UI berikutnya dapat membedakan revisi. Jangan membuka path file atau menambah metadata client ke response/model secara otomatis.

Ganti middleware route `sidang-ta-drafts.steps.store` dari permission create menjadi:

```php
->middleware('permission:sidang_ta_registration_create|sidang_ta_registration_update')
```

Ini OR di route; FormRequest tetap memilih permission **yang sesuai draft**, sehingga update-only tidak bisa menulis draft baru dan create-only tidak bisa menulis revisi.

Tambahkan di grup dashboard yang sama:

```php
Route::post('sidang-ta-registrations/{registration}/draft',
    [\App\Http\Controllers\SidangTaDraftController::class, 'startRevision'])
    ->middleware('permission:sidang_ta_registration_update')->name('sidang-ta-drafts.revision');
Route::delete('sidang-ta-drafts/{draft}',
    [\App\Http\Controllers\SidangTaDraftController::class, 'destroy'])
    ->middleware('permission:sidang_ta_registration_create|sidang_ta_registration_update')
    ->name('sidang-ta-drafts.destroy');
```

GET resume/download tetap memakai read dan owned() yang sudah diperbarui. Form revisi HTML lama belum diarahkan ke backend ini sampai E/F; jangan mencampur jalur final lama dengan draft baru.

## 7. Guard versi keputusan petugas minimum

Tambahkan hidden berikut **di dalam form keputusan** pada `resources/views/sidang_ta_registrations/admin/show.blade.php`, sesudah CSRF:

```blade
<input type="hidden" name="registration_version" value="{{ $registration->lock_version }}">
```

Ganti **seluruh method review()** di SidangTaRegistrationController. Tambahkan import DB bila belum ada:

```php
use Illuminate\Support\Facades\DB;
```

```php
public function review(Request $request, SidangTaRegistration $sidang_ta_registration)
{
    $validated = $request->validate([
        'action' => ['required', 'in:approved,rejected,needs_review'],
        'review_notes' => ['required_if:action,rejected,needs_review', 'nullable', 'string', 'max:2000'],
        'registration_version' => ['required', 'integer', 'min:1'],
    ]);
    DB::transaction(function () use ($validated, $sidang_ta_registration) {
        $registration = SidangTaRegistration::whereKey($sidang_ta_registration->id)
            ->lockForUpdate()->firstOrFail();
        abort_unless($registration->lock_version === (int) $validated['registration_version'],
            409, 'Pengajuan telah berubah. Muat ulang sebelum meninjau.');
        abort_unless(in_array($registration->status, ['submitted', 'needs_review'], true), 422);
        $old = $registration->status;
        $registration->status = $validated['action'];
        $registration->review_notes = $validated['review_notes'] ?? null;
        $registration->reviewed_by = auth()->id();
        $registration->reviewed_at = now();
        $registration->lock_version++;
        $registration->save();
        $registration->logAction($validated['action'], $old, $validated['action'],
            $validated['review_notes'] ?? null, auth()->id());
    });
    return redirect()->back()->with('success', 'Status pendaftaran Sidang TA berhasil diperbarui.');
}
```

Route review existing sudah dilindungi permission review; pertahankan. Ini diperlukan agar source_version draft bermakna. Halaman review yang dibuka sebelum perubahan wajib dimuat ulang. Finalizer F juga wajib menaikkan lock_version saat resubmit.

## 8. Test lengkap D2

Isi seluruh `tests/Feature/SidangTaRevisionDraftTest.php`:

```php
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

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create([
        'username' => '1202200001', 'email' => 'revision@mahasiswa.test',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false,
    ]);
    Student::create([
        'student_number' => '1202200001', 'name' => 'Revision Test',
        'email' => $this->user->email, 'academic_year' => 2022,
        'study_program' => 'Informatika', 'status' => 'Aktif',
    ]);
    foreach (['read', 'update', 'review', 'create'] as $action) {
        Permission::firstOrCreate(['name' => "sidang_ta_registration_{$action}", 'guard_name' => 'web']);
    }
    $this->user->givePermissionTo(['sidang_ta_registration_read', 'sidang_ta_registration_update']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $period = SidangTaPeriod::create([
        'name' => 'Periode asal sudah tutup', 'is_active' => true,
        'open_at' => now()->subDays(3), 'close_at' => now()->subDay(),
    ]);
    $this->source = SidangTaRegistration::create([
        'sidang_ta_period_id' => $period->id, 'student_number' => '1202200001',
        'student_name' => 'Revision Test', 'student_phone' => '081234567890',
        'parent_phone' => '081234567891', 'graduation_payment_date' => today()->toDateString(),
        'diploma_biodata_accurate' => true, 'status' => 'needs_review',
        'review_notes' => 'Perbaiki dokumen.',
    ])->fresh(); // Muat default lock_version dari database sebelum dipakai test.
    foreach (array_merge(Requirements::CAPTURES, Requirements::PAYMENT, Requirements::DIPLOMA) as $type) {
        $path = "sidang_ta/{$this->source->id}/{$type}.pdf";
        Storage::disk('local')->put($path, '%PDF-fixture');
        $this->source->documents()->create([
            'document_type' => $type, 'file_name' => $type.'.pdf',
            'file_path' => $path, 'file_type' => 'pdf', 'status' => 'pending',
        ]);
    }
    $this->startRevision = function () {
        $id = $this->actingAs($this->user)->postJson(route('sidang-ta-drafts.revision', $this->source))
            ->assertCreated()->json('data.id');
        return SidangTaRegistrationDraft::findOrFail($id);
    };
});

test('revision_start_reuses_final_files_and_is_idempotent', function () {
    $beforeFiles = Storage::disk('local')->allFiles();
    $first = ($this->startRevision)();
    $second = ($this->startRevision)();
    expect($second->id)->toBe($first->id);
    expect($first->documents()->whereNotNull('source_document_id')->count())->toBe(15);
    expect(Storage::disk('local')->allFiles())->toBe($beforeFiles);
    expect($this->source->fresh()->status)->toBe('needs_review');
});

test('revision_can_save_contacts_after_period_closed_but_cannot_change_period', function () {
    $draft = ($this->startRevision)();
    $url = route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 1]);
    $data = ['version' => 1, 'sidang_ta_period_id' => $draft->sidang_ta_period_id,
        'student_phone' => '089999999999', 'parent_phone' => '081234567891'];
    $this->postJson($url, $data)->assertOk();
    expect($this->source->fresh()->student_phone)->toBe('081234567890');
    $other = SidangTaPeriod::create(['name' => 'Lain', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay()]);
    $this->postJson($url, array_replace($data, ['version' => 2, 'sidang_ta_period_id' => $other->id]))
        ->assertUnprocessable()->assertJsonValidationErrors('sidang_ta_period_id');
});

test('replacement_in_revision_does_not_delete_or_update_final_file', function () {
    $draft = ($this->startRevision)();
    $old = $this->source->documents()->where('document_type', 'ta_data_capture')->first();
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 2]), [
        'version' => 1, 'documents' => ['ta_data_capture' => UploadedFile::fake()->create('new.pdf', 100, 'application/pdf')],
    ])->assertOk();
    $replacement = $draft->documents()->where('document_type', 'ta_data_capture')->first();
    expect($replacement->source_document_id)->toBeNull();
    expect($replacement->file_path)->not->toBe($old->file_path);
    expect($old->fresh()->file_path)->toBe($old->file_path);
    Storage::disk('local')->assertExists($old->file_path);
    Storage::disk('local')->assertExists($replacement->file_path);
    expect($this->source->fresh()->review_notes)->toBe('Perbaiki dokumen.');
});

test('changing_revision_biodata_branch_preserves_final_biodata_files', function () {
    $draft = ($this->startRevision)();
    $paths = $this->source->documents()->whereIn('document_type', Requirements::DIPLOMA)->pluck('file_path')->all();
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 4]), [
        'version' => 1, 'diploma_biodata_accurate' => false,
        'documents' => ['pdm_submission_proof' => UploadedFile::fake()->create('pdm.pdf', 100, 'application/pdf')],
    ])->assertOk()->assertJsonCount(14, 'data.documents');
    foreach ($paths as $path) {
        Storage::disk('local')->assertExists($path);
    }
    expect($this->source->fresh()->diploma_biodata_accurate)->toBeTrue();
    expect($this->source->documents()->count())->toBe(15);
});

test('discard_removes_only_draft_owned_files', function () {
    $draft = ($this->startRevision)();
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 2]), [
        'version' => 1, 'documents' => ['ta_data_capture' => UploadedFile::fake()->create('new.pdf', 100, 'application/pdf')],
    ])->assertOk();
    $newPath = $draft->documents()->whereNull('source_document_id')->first()->file_path;
    $this->deleteJson(route('sidang-ta-drafts.destroy', $draft), ['version' => 2])->assertNoContent();
    $this->assertDatabaseMissing('sidang_ta_registration_drafts', ['id' => $draft->id]);
    Storage::disk('local')->assertMissing($newPath);
    foreach ($this->source->documents()->get() as $document) {
        Storage::disk('local')->assertExists($document->file_path);
    }
});

test('changed_source_blocks_resume_and_save_but_still_allows_discard', function () {
    $draft = ($this->startRevision)();
    $this->source->lock_version++;
    $this->source->save();
    $this->getJson(route('sidang-ta-drafts.edit', $draft))->assertStatus(409);
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 2]),
        ['version' => 1])->assertStatus(409);
    $this->deleteJson(route('sidang-ta-drafts.destroy', $draft), ['version' => 1])->assertNoContent();
});

test('wrong_owner_or_create_only_user_cannot_start_revision', function () {
    $other = User::factory()->create(['status' => 'active', 'must_change_password' => false]);
    $other->givePermissionTo('sidang_ta_registration_create');
    $this->actingAs($other)->postJson(route('sidang-ta-drafts.revision', $this->source))->assertForbidden();
    $other->givePermissionTo('sidang_ta_registration_update');
    Student::create(['student_number' => '1202200002', 'name' => 'Other',
        'email' => $other->email, 'academic_year' => 2022, 'study_program' => 'Informatika', 'status' => 'Aktif']);
    $this->actingAs($other)->postJson(route('sidang-ta-drafts.revision', $this->source))->assertForbidden();
});

test('review_increments_version_and_rejects_stale_review', function () {
    $reviewer = User::factory()->create(['status' => 'active', 'must_change_password' => false]);
    $reviewer->givePermissionTo('sidang_ta_registration_review');
    $this->actingAs($reviewer)->postJson(route('admin.sidang-ta-registrations.review', $this->source), [
        'registration_version' => 1, 'action' => 'needs_review', 'review_notes' => 'Catatan baru',
    ])->assertRedirect();
    expect($this->source->fresh()->lock_version)->toBe(2);
    $this->postJson(route('admin.sidang-ta-registrations.review', $this->source), [
        'registration_version' => 1, 'action' => 'approved',
    ])->assertStatus(409);
    expect($this->source->fresh()->status)->toBe('needs_review');
    expect($this->source->logs()->count())->toBe(1);
});

test('revision_owner_with_create_only_cannot_save_or_discard', function () {
    $draft = ($this->startRevision)();
    $this->user->revokePermissionTo('sidang_ta_registration_update');
    $this->user->givePermissionTo('sidang_ta_registration_create');
    $this->user->unsetRelation('permissions');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($this->user)->postJson(route('sidang-ta-drafts.steps.store',
        ['draft' => $draft, 'step' => 2]), ['version' => 1])->assertForbidden();
    $this->deleteJson(route('sidang-ta-drafts.destroy', $draft), ['version' => 1])
        ->assertForbidden();
    expect($draft->fresh()->version)->toBe(1);
});

test('discard_rejects_wrong_version_and_consumed_draft_without_deleting_files', function () {
    $draft = ($this->startRevision)();
    $before = Storage::disk('local')->allFiles();
    $this->deleteJson(route('sidang-ta-drafts.destroy', $draft), ['version' => 2])
        ->assertStatus(409);
    expect($draft->fresh()->documents()->count())->toBe(15);
    // Simulasikan state consumed; finalizer sebenarnya baru dipasang pada F.
    $draft->status = SidangTaRegistrationDraft::STATUS_CONSUMED;
    $draft->save();
    $this->deleteJson(route('sidang-ta-drafts.destroy', $draft), ['version' => 1])
        ->assertStatus(409);
    expect($draft->fresh()->status)->toBe(SidangTaRegistrationDraft::STATUS_CONSUMED);
    expect(Storage::disk('local')->allFiles())->toBe($before);
});

```

Fixture PDF final hanya metadata/file sederhana untuk meniru existing; file upload pengganti memakai UploadedFile fake untuk validasi. Sepuluh test ini tidak membuktikan perlombaan dua koneksi MySQL, hanya kontrak ownership, versi dan pemeliharaan file. Race finalisasi diuji pada F.

## 9. Jalankan suite

```bash
php artisan config:clear
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing php artisan test tests/Feature/SidangTaDraftSchemaTest.php tests/Feature/SidangTaPeriodTest.php tests/Feature/SidangTaDraftTest.php tests/Feature/SidangTaDraftUploadTest.php tests/Feature/SidangTaRevisionDraftTest.php
```

Gunakan username/password phpunit.xml lokal selama belum dipulihkan, atau seluruh DB_* environment pada contoh 11. Seluruh D1/C2 harus tetap lulus, bukan hanya sepuluh test D2. Salin upload test D1 yang tertinggal ke dadu-test agar suite lengkap.

Checkpoint ini selesai setelah test lulus dan rescan memastikan file final tidak disentuh selama save/discard draft revisi. Berikutnya E membangun wizard HTML dan halaman tinjau; F mengubah pipeline final store/update menjadi finalisasi draft. Jalur lama store/update belum dipakai untuk mengirim draft, dan akan diganti pada F agar tidak tersisa bypass.

Rujukan Context7: [Laravel row locking](https://github.com/laravel/docs/blob/13.x/queries.md#pessimistic-locking), [FormRequest authorization](https://github.com/laravel/docs/blob/13.x/validation.md#authorizing-form-requests). Asisten memeriksa sintaks dokumen dan source, tidak menjalankan test aplikasi atau mengubah kode.


Pembaruan sesudah pengguna melaporkan seluruh D2 lulus: source sudah direscan pada [16](16-rescan-d2-sebelum-wizard.md). Lanjutkan [17 wizard dan tinjau](17-checkpoint-e-wizard-student.md). Upload/revisi test kini sudah tersalin; catatan file tertinggal sebelumnya tidak lagi berlaku.
