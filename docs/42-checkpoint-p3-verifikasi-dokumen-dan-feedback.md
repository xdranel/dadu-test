# 42 — P3: verifikasi dokumen, persetujuan akhir dan feedback mahasiswa

**Goal:** petugas memverifikasi tiap dokumen; permintaan perbaikan terlihat oleh mahasiswa dan persetujuan hanya dapat dilakukan setelah semua dokumen wajib tersedia serta verified.

**Architecture:** service review mengunci Registration lalu Document(s), memeriksa lock_version sebelum kelayakan. Satu helper eligibility dipakai server dan tombol Setujui. Feedback revisi membaca dokumen final sumber tanpa kolom baru pada draft.

**Tech stack:** Laravel13, MySQL Podman, Blade/Tailwind, Pest. Status/notes dokumen dan log/version sudah tersedia; tidak ada migrasi/dependency/permission baru.

**Spec:**31 bagian7 telah disetujui. P2b dan koreksi41 dilaporkan lulus unit/manual dan tersalin; rescan source terkait kedua folder identik dengan162 definisi Feature. Header setPrivate dan tombol hijau terpasang.

**Koreksi penerapan:** fixture feedback tahap2 diperbaiki pada [43](43-koreksi-fixture-feedback-p3-dan-rescan.md). Jika detail mengalami500, lihat [44](44-koreksi-error500-detail-sidang-ta.md): source terbaru kehilangan @empty pada loop dokumen. Gunakan section lengkap pada44; tidak perlu reset database.

**Koreksi posisi setelah aksi:** [44 bagian6](44-koreksi-error500-detail-sidang-ta.md#6-tetap-di-kartu-dokumen-setelah-minta-perbaikanverifikasi) memperbarui reviewDocument, article admin dan satu assertion test, serta menambah2test. Terapkan setelah42; target terkini46fokus/185PHP/12Node, menggantikan44/183 sebelumnya.

## 1. Kontrak dan batas

- Permission review saja untuk menilai dokumen/keputusan; read/update mahasiswa dan period_read bukan izin review.
- Dokumen wajib cabang aktif dapat verified/rejected. Rejected membutuhkan catatan; verified membutuhkan file aktual tersedia. Dokumen N/A dan dokumen milik pengajuan lain ditolak.
- Satu dokumen rejected mengubah submitted menjadi needs_review; notes umum tidak ditimpa catatan dokumen. Verifikasi terakhir tidak otomatis menyetujui atau mengubah needs_review menjadi submitted.
- Setujui hanya jika semua15/14dokumen wajib mempunyai metadata, file aktual, dan status verified. N/A tidak dihitung. Approved/rejected terminal read-only.
- Setiap perubahan status/catatan dokumen menaikkan lock_version dan menulis satu log. Request stale409. Pengiriman ulang nilai identik dengan versi saat ini tanpa perubahan tidak menambah versi/log.
- Urutan lock reviewer **Registration → Document(s)**, tanpa mengambil lock Student/Draft sesudahnya. Jalur mahasiswa Student→Draft→SourceRegistration tetap. Finalizer sudah mereset semua dokumen kiriman revisi ke pending/notes=null; tidak diubah.
- Feedback detail mahasiswa memuat status/catatan. Revision-start dan wizard menampilkan masalah/catatan dokumen pengajuan asal, termasuk setelah file draft diganti. Source_version guard tetap membuat draft stale jika review berubah di tengah revisi.
- Tidak menghapus file/final, membuka ulang keputusan terminal, menambah bulk review, atau mengirim notifikasi eksternal.

## 2. File dan urutan

Edit existing:

```text
routes/web.php
app/Http/Controllers/SidangTaRegistrationController.php (adminShow, review, tambah reviewDocument)
resources/views/sidang_ta_registrations/admin/show.blade.php
resources/views/sidang_ta_registrations/show.blade.php
resources/views/sidang_ta_registrations/wizard.blade.php
resources/views/sidang_ta_registrations/revision-start.blade.php
tests/Feature/SidangTaFinalizationTest.php (satu fixture test)
```

Buat baru:

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
php artisan make:class Support/SidangTaReviewEligibility
php artisan make:class Services/SidangTaRegistrationReview
php artisan make:view sidang_ta_registrations.admin.partials.document-review-form
php artisan make:view sidang_ta_registrations.admin.partials.registration-decision
php artisan make:view sidang_ta_registrations.partials.document-review-status
php artisan make:view sidang_ta_registrations.partials.revision-feedback
php artisan make:test SidangTaDocumentReviewTest --pest
php artisan make:test SidangTaReviewFeedbackTest --pest
```

- [ ] Isi21test baru bagian8–9 dahulu. Jalankan fokus bagian10 sebelum source diubah; perilaku review/eligibility baru harus gagal.
- [ ] Terapkan bagian3–7 dan perbarui satu fixture test lama bagian7. Jangan menambah ulang blok lama.
- [ ] Jalankan fokus/suite/manual termasuk dua tab dan revisi, lalu salin15file untuk rescan.

## 3. Eligibility lengkap

Isi app/Support/SidangTaReviewEligibility.php:

```php
<?php

namespace App\Support;

use App\Models\SidangTaRegistration;
use Illuminate\Support\Facades\Storage;

class SidangTaReviewEligibility
{
    public static function requiredTypes(SidangTaRegistration $registration): array
    {
        return array_merge(SidangTaDocumentRequirements::CAPTURES, SidangTaDocumentRequirements::PAYMENT,
            SidangTaDocumentRequirements::typesForStep(4, $registration->diploma_biodata_accurate));
    }

    public static function blockers(SidangTaRegistration $registration): array
    {
        $documents = $registration->relationLoaded('documents')
            ? $registration->documents : $registration->documents()->get();
        $blockers = [];
        foreach (self::requiredTypes($registration) as $type) {
            $document = $documents->firstWhere('document_type', $type);
            $reason = !$document ? 'Belum ada dokumen'
                : (!Storage::disk('local')->exists($document->file_path) ? 'Berkas tidak ditemukan'
                    : ($document->status !== 'verified' ? 'Belum terverifikasi' : null));
            if ($reason !== null) $blockers[$type] = SidangTaRegistration::DOCUMENT_TYPES[$type].' — '.$reason;
        }
        return $blockers;
    }
}
```

## 4. Service review lengkap

Isi app/Services/SidangTaRegistrationReview.php:

```php
<?php

namespace App\Services;

use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDocument;
use App\Models\User;
use App\Support\SidangTaReviewEligibility as Eligibility;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SidangTaRegistrationReview
{
    private function locked(SidangTaRegistration $registration, int $version): SidangTaRegistration
    {
        $locked = SidangTaRegistration::whereKey($registration->id)->lockForUpdate()->firstOrFail();
        abort_unless($locked->lock_version === $version, 409, 'Pengajuan berubah. Muat ulang sebelum meninjau.');
        abort_unless(in_array($locked->status, ['submitted', 'needs_review'], true), 422,
            'Pengajuan sudah diputuskan dan tidak dapat ditinjau kembali.');
        return $locked;
    }

    public function document(User $user, SidangTaRegistration $registration, SidangTaRegistrationDocument $document,
                             int $version, string $status, ?string $notes): SidangTaRegistration
    {
        abort_unless($user->can('sidang_ta_registration_review'), 403);
        abort_unless(in_array($status, ['verified', 'rejected'], true), 422);
        $notes = trim($notes ?? '');
        $notes = $notes === '' ? null : $notes;
        if ($status === 'rejected' && $notes === null) {
            throw ValidationException::withMessages(['notes' => 'Isi catatan perbaikan dokumen.']);
        }
        return DB::transaction(function () use ($user, $registration, $document, $version, $status, $notes) {
            $locked = $this->locked($registration, $version);
            $doc = $locked->documents()->whereKey($document->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($doc->document_type, Eligibility::requiredTypes($locked), true), 422,
                'Dokumen ini tidak berlaku untuk cabang biodata pengajuan.');
            if ($status === 'verified' && !Storage::disk('local')->exists($doc->file_path)) {
                throw ValidationException::withMessages(['document' => 'Berkas tidak ditemukan. Minta mahasiswa mengunggah ulang.']);
            }
            $oldRegistrationStatus = $locked->status;
            $nextStatus = $status === 'rejected' ? 'needs_review' : $locked->status;
            if ($doc->status === $status && $doc->notes === $notes && $locked->status === $nextStatus) return $locked;
            $labels = ['pending' => 'Menunggu verifikasi', 'verified' => 'Terverifikasi', 'rejected' => 'Perlu perbaikan'];
            $oldDocumentStatus = $labels[$doc->status] ?? $doc->status;
            $doc->status = $status;
            $doc->notes = $notes;
            $doc->save();
            $locked->status = $nextStatus;
            $locked->reviewed_by = $user->id;
            $locked->reviewed_at = now();
            $locked->lock_version++;
            $locked->save();
            $logNotes = SidangTaRegistration::DOCUMENT_TYPES[$doc->document_type].': '.$oldDocumentStatus.' → '.$labels[$status];
            if ($notes !== null) $logNotes .= '. Catatan: '.$notes;
            $locked->logAction($status === 'verified' ? 'document_verified' : 'document_rejected',
                $oldRegistrationStatus, $nextStatus, $logNotes, $user->id);
            return $locked;
        }, 3);
    }

    public function decide(User $user, SidangTaRegistration $registration, int $version,
                           string $action, ?string $notes): SidangTaRegistration
    {
        abort_unless($user->can('sidang_ta_registration_review'), 403);
        abort_unless(in_array($action, ['approved', 'needs_review', 'rejected'], true), 422);
        $notes = trim($notes ?? '');
        $notes = $notes === '' ? null : $notes;
        if (in_array($action, ['needs_review', 'rejected'], true) && $notes === null) {
            throw ValidationException::withMessages(['review_notes' => 'Isi catatan revisi atau penolakan.']);
        }
        return DB::transaction(function () use ($user, $registration, $version, $action, $notes) {
            $locked = $this->locked($registration, $version);
            if ($action === 'approved') {
                $locked->setRelation('documents', $locked->documents()->lockForUpdate()->get());
                $blockers = Eligibility::blockers($locked);
                if ($blockers !== []) {
                    throw ValidationException::withMessages(['documents' => 'Persetujuan belum dapat dilakukan: '.implode('; ', $blockers)]);
                }
            }
            $old = $locked->status;
            $locked->status = $action;
            $locked->review_notes = $notes;
            $locked->reviewed_by = $user->id;
            $locked->reviewed_at = now();
            $locked->lock_version++;
            $locked->save();
            $locked->logAction($action, $old, $action, $notes, $user->id);
            return $locked;
        }, 3);
    }
}
```

Tidak ada lock Student/Draft pada reviewer, tidak ada perubahan file. Version diperiksa sebelum eligibility sehingga test stale approval tetap409. Transaksi yang gagal tidak menulis status/version/log parsial.

## 5. Route dan controller

Tambahkan di grup admin TA yang sama, tetap dilindungi permission review:

```php
Route::post('sidang-ta-registrations/{sidang_ta_registration}/documents/{document}/review',
    [SidangTaRegistrationController::class, 'reviewDocument'])
    ->middleware('permission:sidang_ta_registration_review')
    ->name('sidang-ta-registrations.documents.review');
```

Ganti adminShow dan review, lalu tambahkan reviewDocument pada SidangTaRegistrationController. Request/model/HttpExceptionInterface sudah di-import; class baru memakai nama lengkap:

```php
public function adminShow(SidangTaRegistration $sidang_ta_registration)
{
    $registration = $sidang_ta_registration->load(['period', 'documents', 'logs']);
    $reviewable = in_array($registration->status, ['submitted', 'needs_review'], true);
    $requiredTypes = \App\Support\SidangTaReviewEligibility::requiredTypes($registration);
    $approvalBlockers = \App\Support\SidangTaReviewEligibility::blockers($registration);
    return view('sidang_ta_registrations.admin.show', compact('registration', 'reviewable', 'requiredTypes', 'approvalBlockers'));
}

public function review(Request $request, SidangTaRegistration $sidang_ta_registration)
{
    $validated = $request->validate([
        'action' => ['required', 'in:approved,rejected,needs_review'],
        'review_notes' => ['required_if:action,rejected,needs_review', 'nullable', 'string', 'max:2000'],
        'registration_version' => ['required', 'integer', 'min:1'],
    ]);
    try {
        app(\App\Services\SidangTaRegistrationReview::class)->decide($request->user(), $sidang_ta_registration,
            (int) $validated['registration_version'], $validated['action'], $validated['review_notes'] ?? null);
    } catch (HttpExceptionInterface $exception) {
        if ($request->expectsJson() || $exception->getStatusCode() !== 409) throw $exception;
        return redirect()->route('admin.sidang-ta-registrations.show', $sidang_ta_registration)
            ->withErrors(['registration_version' => $exception->getMessage()])->withInput();
    }
    return redirect()->back()->with('success', 'Status pendaftaran Sidang TA berhasil diperbarui.');
}

public function reviewDocument(Request $request, SidangTaRegistration $sidang_ta_registration,
                               SidangTaRegistrationDocument $document)
{
    $validated = $request->validate([
        'status' => ['required', 'in:verified,rejected'],
        'notes' => ['required_if:status,rejected', 'nullable', 'string', 'max:2000'],
        'registration_version' => ['required', 'integer', 'min:1'],
    ], ['notes.required_if' => 'Isi catatan perbaikan dokumen.']);
    try {
        $registration = app(\App\Services\SidangTaRegistrationReview::class)->document(
            $request->user(), $sidang_ta_registration, $document, (int) $validated['registration_version'],
            $validated['status'], $validated['notes'] ?? null
        );
    } catch (HttpExceptionInterface $exception) {
        if ($request->expectsJson() || $exception->getStatusCode() !== 409) throw $exception;
        return redirect()->route('admin.sidang-ta-registrations.show', $sidang_ta_registration)
            ->withErrors(['registration_version' => $exception->getMessage()])->withInput();
    }
    if ($request->expectsJson()) {
        return response()->json(['data' => ['id' => $registration->id,
            'status' => $registration->status, 'lock_version' => $registration->lock_version]]);
    }
    return redirect()->route('admin.sidang-ta-registrations.show', $registration)
        ->with('success', 'Review dokumen berhasil disimpan.');
}
```

JSON keputusan umum tetap redirect seperti kontrak existing. JSON review dokumen baru200 dengan status/version; stale409. HTML stale diarahkan ke detail dengan pesan dan input lama; pengguna perlu Muat ulang untuk memakai versi baru. Hidden version form yang gagal tidak diam-diam diperbarui oleh GET redirect.

## 6. UI petugas

### Partial review dokumen lengkap

Isi resources/views/sidang_ta_registrations/admin/partials/document-review-form.blade.php:

```blade
@if($reviewable && in_array($document->document_type, $requiredTypes, true))
    @php
        $oldForm = old('_review_form') === ('document:'.$document->id);
        $rawNotes = $oldForm ? old('notes') : $document->notes;
        $notesValue = is_string($rawNotes) ? $rawNotes : '';
        $rawVersion = $oldForm ? old('registration_version') : $registration->lock_version;
        $versionValue = is_scalar($rawVersion) ? (string) $rawVersion : '';
    @endphp
    <form data-doc-review-form="{{ $document->id }}" method="POST" novalidate
          action="{{ route('admin.sidang-ta-registrations.documents.review', ['sidang_ta_registration' => $registration, 'document' => $document]) }}"
          class="w-full space-y-3 border-t border-slate-100 pt-3">
        @csrf
        <input type="hidden" name="_review_form" value="document:{{ $document->id }}">
        <input type="hidden" name="registration_version" value="{{ $versionValue }}">
        <label class="block text-xs font-semibold text-slate-600" for="document-notes-{{ $document->id }}">Catatan dokumen (wajib jika meminta perbaikan)</label>
        <textarea id="document-notes-{{ $document->id }}" name="notes" maxlength="2000" class="campus-input min-h-20">{{ $notesValue }}</textarea>
        @if($oldForm && $errors->has('notes'))<p role="alert" class="text-xs text-rose-700">{{ $errors->first('notes') }}</p>@endif
        <div class="flex flex-wrap justify-end gap-2">
            <button type="submit" name="status" value="rejected" class="btn btn-secondary">Minta perbaikan</button>
            <button data-doc-verify type="submit" name="status" value="verified" @disabled(!$available) class="btn btn-success disabled:cursor-not-allowed disabled:opacity-50">Verifikasi</button>
        </div>
    </form>
@endif
```

### Partial keputusan lengkap

Isi resources/views/sidang_ta_registrations/admin/partials/registration-decision.blade.php:

```blade
@php
    $oldForm = old('_review_form') === 'decision';
    $rawVersion = $oldForm ? old('registration_version') : $registration->lock_version;
    $versionValue = is_scalar($rawVersion) ? (string) $rawVersion : '';
    $rawNotes = $oldForm ? old('review_notes') : $registration->review_notes;
    $notesValue = is_string($rawNotes) ? $rawNotes : '';
@endphp
<form data-ta-decision method="POST" novalidate action="{{ route('admin.sidang-ta-registrations.review', $registration) }}" class="campus-card space-y-4 p-5">
    @csrf
    <input type="hidden" name="_review_form" value="decision">
    <input type="hidden" name="registration_version" value="{{ $versionValue }}">
    <h3 class="font-bold text-slate-800">Keputusan petugas</h3>
    @if($approvalBlockers !== [])
        <div data-ta-approval-blockers class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900">
            <p class="font-semibold">Setujui tersedia setelah seluruh dokumen wajib terverifikasi dan berkasnya tersedia.</p>
            <ul class="mt-2 list-inside list-disc space-y-1">@foreach($approvalBlockers as $message)<li>{{ $message }}</li>@endforeach</ul>
        </div>
    @endif
    @if($errors->has('registration_version'))
        <a data-ta-reload href="{{ route('admin.sidang-ta-registrations.show', $registration) }}" class="btn btn-secondary">Muat ulang pengajuan</a>
    @endif
    <label class="campus-label">Catatan untuk mahasiswa (wajib untuk revisi atau penolakan)<textarea name="review_notes" maxlength="2000" class="campus-input min-h-24">{{ $notesValue }}</textarea></label>
    @if($oldForm && $errors->has('review_notes'))<p class="text-xs text-rose-700">{{ $errors->first('review_notes') }}</p>@endif
    <div class="flex flex-wrap justify-end gap-2">
        <button type="submit" class="btn btn-secondary" name="action" value="needs_review">Minta Revisi</button>
        <button type="submit" class="btn btn-secondary" name="action" value="rejected">Tolak</button>
        <button data-ta-approve type="submit" class="btn btn-primary disabled:cursor-not-allowed disabled:opacity-50" name="action" value="approved" @disabled($approvalBlockers !== [])>Setujui</button>
    </div>
</form>
```

### Pasang pada admin/show

Pada resources/views/sidang_ta_registrations/admin/show.blade.php:

1. Di dalam tiap article `data-ta-detail-document`, setelah kondisi link preview/file hilang (`@endif`) dan sebelum `</article>`, tambahkan:

```blade
@include('sidang_ta_registrations.admin.partials.document-review-form')
```

Parent sudah menyediakan available pada loop dokumen dari39. Jangan menaruh include di luar foreach atau sebelum x-app-layout.

2. Ganti seluruh form lama `data-ta-decision` dengan:

```blade
@include('sidang_ta_registrations.admin.partials.registration-decision')
```

Pertahankan parent kondisi submitted/needs_review yang sudah ada; jangan membuat form bersarang. Terminal tidak menampilkan form keputusan ataupun review dokumen.

3. Tambahkan dua item pada array actionLabels di blok @php awal:

```php
'document_verified' => 'Dokumen diverifikasi',
'document_rejected' => 'Dokumen diminta perbaikan',
```

Ini fragment array, bukan statement PHP mandiri. Snapshot, preview modal, notes/history dan back link lain tetap.

## 7. Feedback mahasiswa dan fixture existing

### Partial status dokumen

Isi resources/views/sidang_ta_registrations/partials/document-review-status.blade.php:

```blade
@php
    $reviewLabel = ['pending' => 'Menunggu verifikasi', 'verified' => 'Terverifikasi', 'rejected' => 'Perlu perbaikan'][$document->status] ?? $document->status;
@endphp
<div data-student-document-state="{{ $document->id }}" class="mt-2 space-y-1 text-xs">
    <p class="font-medium {{ $document->status === 'rejected' ? 'text-rose-700' : 'text-slate-600' }}">{{ $reviewLabel }}</p>
    @if(filled($document->notes))<p class="whitespace-pre-line break-words text-rose-700">{{ $document->notes }}</p>@endif
</div>
```

Pada student show.blade.php, di loop dokumen setelah paragraf nama/ukuran file dan sebelum penutup div metadata, tambahkan include partial ini. Tombol Lihat berkas tetap ada:

```blade
@include('sidang_ta_registrations.partials.document-review-status')
```

Pada function actionLabel match di blok @php awal student show, tambahkan dua case yang sama seperti label admin (tetap memakai operator => dan koma). Jangan mengganti function statusLabel atau view admin dengan student.

### Partial feedback revisi lengkap

Isi resources/views/sidang_ta_registrations/partials/revision-feedback.blade.php:

```blade
@php
    $sourceDocuments = $registration->relationLoaded('documents') ? $registration->documents : $registration->documents()->get();
    $feedback = [];
    foreach(\App\Support\SidangTaReviewEligibility::requiredTypes($registration) as $type) {
        $sourceDocument = $sourceDocuments->firstWhere('document_type', $type);
        $missingFile = $sourceDocument && !\Illuminate\Support\Facades\Storage::disk('local')->exists($sourceDocument->file_path);
        if (!$sourceDocument || $missingFile || $sourceDocument->status === 'rejected' || filled($sourceDocument->notes)) {
            $feedback[$type] = ['document' => $sourceDocument, 'missing_file' => $missingFile];
        }
    }
@endphp
@if($feedback !== [])
    <section data-review-feedback class="space-y-3 rounded-xl border border-amber-200 bg-amber-50 p-4">
        <h2 class="text-sm font-bold text-amber-900">Catatan dokumen pengajuan sebelumnya</h2>
        <p class="text-xs leading-relaxed text-amber-800">Gunakan catatan ini untuk memperbaiki berkas. Berkas yang diganti akan ditinjau kembali setelah revisi dikirim.</p>
        <ul class="space-y-3">
            @foreach($feedback as $type => $item)
                @php
                    $sourceDocument = $item['document'];
                    $label = !$sourceDocument ? 'Belum ada dokumen' : ($item['missing_file'] ? 'Berkas tidak ditemukan' : (['pending' => 'Menunggu verifikasi', 'verified' => 'Terverifikasi', 'rejected' => 'Perlu perbaikan'][$sourceDocument->status] ?? $sourceDocument->status));
                    $stepNumber = in_array($type, \App\Support\SidangTaDocumentRequirements::CAPTURES, true) ? 2 : (in_array($type, \App\Support\SidangTaDocumentRequirements::PAYMENT, true) ? 3 : 4);
                @endphp
                <li data-feedback-type="{{ $type }}" class="border-t border-amber-200 pt-3 text-xs text-amber-900">
                    <p class="font-semibold">{{ \App\Models\SidangTaRegistration::DOCUMENT_TYPES[$type] }} · Tahap {{ $stepNumber }}</p>
                    <p class="mt-1">{{ $label }}</p>
                    @if(filled($sourceDocument?->notes))<p class="mt-1 whitespace-pre-line break-words">{{ $sourceDocument->notes }}</p>@endif
                </li>
            @endforeach
        </ul>
    </section>
@endif
```

Pada revision-start.blade.php, pasang setelah paragraf catatan umum dan sebelum form POST mulai revisi:

```blade
@include('sidang_ta_registrations.partials.revision-feedback')
```

Pada wizard.blade.php, setelah card catatan umum sourceRegistration yang sudah ada dan sebelum form sidang-ta-step-form, pasang kondisi **terpisah** berikut. Catatan per dokumen tetap tampil walaupun catatan umum kosong:

```blade
@if($draft?->sourceRegistration)
    @include('sidang_ta_registrations.partials.revision-feedback', ['registration' => $draft->sourceRegistration])
@endif
```

### Satu test existing yang perlu diganti

Pada tests/Feature/SidangTaFinalizationTest.php, ganti blok `repeated_submit_keeps_same_result_and_does_not_overwrite_review` dengan berikut. Bulk update verified hanya setup fixture untuk test replay, bukan alur produksi:

```php
test('repeated_submit_keeps_same_result_and_does_not_overwrite_review', function () {
    $draft = ($this->makeDraft)();
    $id = ($this->submitDraft)($draft)->assertOk()->json('data.id');
    $registration = SidangTaRegistration::findOrFail($id);
    $registration->documents()->update(['status' => 'verified']);
    $this->postJson(route('admin.sidang-ta-registrations.review', $registration), [
        'registration_version' => 1, 'action' => 'approved',
    ])->assertRedirect();
    ($this->submitDraft)($draft)->assertOk()->assertJsonPath('data.id', $id)
        ->assertJsonPath('data.status', 'approved');
    expect(SidangTaRegistration::count())->toBe(1);
    expect($registration->logs()->where('action', 'submitted')->count())->toBe(1);
});
```

Test RevisionDraft `review_increments_version_and_rejects_stale_review` tetap tidak diubah: stale approval tetap409 sebelum pemeriksaan kelengkapan. Test existing lain tidak perlu diganti.

## 8. Test review dokumen lengkap

Isi tests/Feature/SidangTaDocumentReviewTest.php:

```php
<?php

use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\Student;
use App\Models\User;
use App\Support\SidangTaReviewEligibility as Eligibility;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->travelTo(now()->startOfSecond());
    Storage::fake('local');
    foreach (['sidang_ta_registration_review', 'sidang_ta_registration_read', 'sidang_ta_registration_create',
        'sidang_ta_registration_update', 'sidang_ta_period_read'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->reviewer = User::factory()->create(['username' => 'p3-reviewer', 'email' => 'p3-reviewer@test.local',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
    $this->reviewer->givePermissionTo('sidang_ta_registration_review');
    $this->actingAs($this->reviewer);
    $this->period = SidangTaPeriod::create(['name' => 'P3', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay()]);
    $this->sequence = 0;
    $this->makeSource = function (array $overrides = [], string $documentStatus = 'pending') {
        $this->sequence++;
        $source = SidangTaRegistration::create(array_replace([
            'sidang_ta_period_id' => $this->period->id, 'student_number' => 'P3'.str_pad((string) $this->sequence, 6, '0', STR_PAD_LEFT),
            'student_name' => 'Student P3', 'study_program' => 'Informatika',
            'student_phone' => '081234567890', 'parent_phone' => '081234567891',
            'graduation_payment_date' => today()->toDateString(), 'diploma_biodata_accurate' => true,
            'status' => 'submitted', 'submitted_at' => now(),
        ], $overrides))->fresh();
        foreach (Eligibility::requiredTypes($source) as $type) {
            $path = "sidang_ta/p3/{$source->id}/{$type}.pdf";
            Storage::disk('local')->put($path, '%PDF-1.4 p3');
            $source->documents()->create(['document_type' => $type, 'file_name' => $type.'.pdf',
                'file_path' => $path, 'file_size' => 11, 'file_type' => 'pdf', 'status' => $documentStatus]);
        }
        return $source;
    };
    $this->reviewDoc = function ($source, $document, array $overrides = []) {
        return $this->postJson(route('admin.sidang-ta-registrations.documents.review', [
            'sidang_ta_registration' => $source, 'document' => $document,
        ]), array_replace(['registration_version' => $source->fresh()->lock_version, 'status' => 'verified'], $overrides));
    };
});

afterEach(function () { $this->travelBack(); });

test('p3_verifies_one_document_with_version_and_audit_without_approving', function () {
    $source = ($this->makeSource)();
    $document = $source->documents()->firstOrFail();
    $paths = Storage::disk('local')->allFiles();
    ($this->reviewDoc)($source, $document)->assertOk()->assertJsonPath('data.lock_version', 2)
        ->assertJsonPath('data.status', 'submitted');
    expect($document->fresh()->status)->toBe('verified');
    expect($source->documents()->where('status', 'pending')->count())->toBe(14);
    $log = $source->logs()->firstOrFail();
    expect($log->action)->toBe('document_verified');
    expect((int) $log->performed_by)->toBe($this->reviewer->id);
    expect($log->notes)->toContain(SidangTaRegistration::DOCUMENT_TYPES[$document->document_type]);
    expect(Storage::disk('local')->allFiles())->toBe($paths);
});

test('p3_rejection_requires_valid_nonblank_notes_and_status', function () {
    $source = ($this->makeSource)();
    $document = $source->documents()->firstOrFail();
    foreach ([null, '', '   ', ['bad'], str_repeat('x', 2001)] as $notes) {
        ($this->reviewDoc)($source, $document, ['status' => 'rejected', 'notes' => $notes])
            ->assertUnprocessable()->assertJsonValidationErrors('notes');
    }
    ($this->reviewDoc)($source, $document, ['status' => 'pending'])->assertUnprocessable()->assertJsonValidationErrors('status');
    expect($source->fresh()->lock_version)->toBe(1);
    expect($source->logs()->count())->toBe(0);
    expect($document->fresh()->status)->toBe('pending');
});

test('p3_rejection_moves_submission_to_revision_without_overwriting_general_notes', function () {
    $source = ($this->makeSource)(['review_notes' => 'Catatan umum lama']);
    $document = $source->documents()->firstOrFail();
    ($this->reviewDoc)($source, $document, ['status' => 'rejected', 'notes' => ' Perbaiki scan '])
        ->assertOk()->assertJsonPath('data.status', 'needs_review')->assertJsonPath('data.lock_version', 2);
    expect($document->fresh()->notes)->toBe('Perbaiki scan');
    expect($source->fresh()->review_notes)->toBe('Catatan umum lama');
    $log = $source->logs()->firstOrFail();
    expect($log->action)->toBe('document_rejected');
    expect($log->from_status)->toBe('submitted');
    expect($log->to_status)->toBe('needs_review');
});

test('p3_last_verification_never_auto_approves_or_resubmits', function () {
    foreach (['submitted', 'needs_review'] as $status) {
        $source = ($this->makeSource)(['status' => $status], 'verified');
        $document = $source->documents()->firstOrFail();
        $document->update(['status' => 'pending']);
        ($this->reviewDoc)($source, $document)->assertOk()->assertJsonPath('data.status', $status);
        expect($source->documents()->where('status', '!=', 'verified')->count())->toBe(0);
        expect($source->fresh()->status)->toBe($status);
    }
});

test('p3_stale_document_and_decision_versions_do_not_overwrite_newer_review', function () {
    $source = ($this->makeSource)();
    $document = $source->documents()->firstOrFail();
    ($this->reviewDoc)($source, $document)->assertOk();
    ($this->reviewDoc)($source, $document, ['registration_version' => 1, 'status' => 'rejected', 'notes' => 'Lama'])
        ->assertStatus(409);
    $this->postJson(route('admin.sidang-ta-registrations.review', $source), [
        'registration_version' => 1, 'action' => 'approved',
    ])->assertStatus(409);
    expect($document->fresh()->status)->toBe('verified');
    expect($source->fresh()->lock_version)->toBe(2);
    expect($source->logs()->count())->toBe(1);
});

test('p3_document_must_belong_to_registration_in_route', function () {
    $a = ($this->makeSource)();
    $b = ($this->makeSource)();
    $document = $b->documents()->firstOrFail();
    ($this->reviewDoc)($a, $document)->assertNotFound();
    expect($a->fresh()->lock_version)->toBe(1);
    expect($b->fresh()->lock_version)->toBe(1);
    expect($document->fresh()->status)->toBe('pending');
});

test('p3_inactive_biodata_branch_cannot_be_reviewed', function () {
    $source = ($this->makeSource)();
    Storage::disk('local')->put('sidang_ta/p3/extra.pdf', '%PDF-extra');
    $document = $source->documents()->create(['document_type' => 'pdm_submission_proof',
        'file_name' => 'extra.pdf', 'file_path' => 'sidang_ta/p3/extra.pdf', 'file_type' => 'pdf', 'status' => 'pending']);
    ($this->reviewDoc)($source, $document)->assertUnprocessable();
    ($this->reviewDoc)($source, $document, ['status' => 'rejected', 'notes' => 'Tidak berlaku'])->assertUnprocessable();
    expect($source->fresh()->lock_version)->toBe(1);
});

test('p3_missing_file_cannot_be_verified_but_can_be_requested_for_repair', function () {
    $source = ($this->makeSource)();
    $document = $source->documents()->firstOrFail();
    Storage::disk('local')->delete($document->file_path);
    ($this->reviewDoc)($source, $document)->assertUnprocessable()->assertJsonValidationErrors('document');
    ($this->reviewDoc)($source, $document, ['status' => 'rejected', 'notes' => 'Unggah ulang berkas'])
        ->assertOk()->assertJsonPath('data.status', 'needs_review');
});

test('p3_terminal_registration_is_read_only_for_document_and_decision', function () {
    foreach (['approved', 'rejected'] as $status) {
        $source = ($this->makeSource)(['status' => $status], 'verified');
        ($this->reviewDoc)($source, $source->documents()->first())->assertUnprocessable();
        $this->postJson(route('admin.sidang-ta-registrations.review', $source), [
            'registration_version' => 1, 'action' => 'needs_review', 'review_notes' => 'Buka lagi',
        ])->assertUnprocessable();
        expect($source->fresh()->status)->toBe($status);
        expect($source->fresh()->lock_version)->toBe(1);
        expect($source->logs()->count())->toBe(0);
    }
});

test('p3_non_review_permissions_cannot_mutate_documents_or_decisions', function () {
    $source = ($this->makeSource)();
    $document = $source->documents()->firstOrFail();
    foreach (['sidang_ta_registration_read', 'sidang_ta_registration_update', 'sidang_ta_period_read'] as $permission) {
        $user = User::factory()->create(['status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
        $user->givePermissionTo($permission);
        $this->actingAs($user);
        ($this->reviewDoc)($source, $document)->assertForbidden();
        $this->postJson(route('admin.sidang-ta-registrations.review', $source), [
            'registration_version' => 1, 'action' => 'approved',
        ])->assertForbidden();
    }
    expect($source->fresh()->lock_version)->toBe(1);
    expect($document->fresh()->status)->toBe('pending');
});

test('p3_approval_rejects_pending_rejected_missing_metadata_or_missing_file', function () {
    foreach (['pending', 'rejected', 'metadata', 'file'] as $defect) {
        $source = ($this->makeSource)([], 'verified');
        $document = $source->documents()->firstOrFail();
        if (in_array($defect, ['pending', 'rejected'], true)) $document->update(['status' => $defect]);
        if ($defect === 'metadata') $document->delete();
        if ($defect === 'file') Storage::disk('local')->delete($document->file_path);
        $this->postJson(route('admin.sidang-ta-registrations.review', $source), [
            'registration_version' => 1, 'action' => 'approved',
        ])->assertUnprocessable()->assertJsonValidationErrors('documents');
        expect($source->fresh()->status)->toBe('submitted');
        expect($source->fresh()->lock_version)->toBe(1);
        expect($source->logs()->count())->toBe(0);
    }
});

test('p3_approval_accepts_verified_fifteen_or_fourteen_and_ignores_na_metadata', function () {
    foreach ([true, false] as $accurate) {
        $source = ($this->makeSource)(['diploma_biodata_accurate' => $accurate], 'verified');
        $extra = $source->documents()->create(['document_type' => $accurate ? 'pdm_submission_proof' : 'diploma_statement',
            'file_name' => 'old.pdf', 'file_path' => 'obsolete-not-available.pdf', 'file_type' => 'pdf', 'status' => 'pending']);
        $this->postJson(route('admin.sidang-ta-registrations.review', $source), [
            'registration_version' => 1, 'action' => 'approved',
        ])->assertRedirect();
        expect($source->fresh()->status)->toBe('approved');
        expect($source->fresh()->lock_version)->toBe(2);
        expect($extra->fresh()->status)->toBe('pending');
    }
});

test('p3_identical_document_review_is_noop_but_note_change_increments_version', function () {
    $source = ($this->makeSource)([], 'verified');
    $document = $source->documents()->firstOrFail();
    ($this->reviewDoc)($source, $document)->assertOk()->assertJsonPath('data.lock_version', 1);
    expect($source->logs()->count())->toBe(0);
    ($this->reviewDoc)($source, $document, ['notes' => 'Sudah diperiksa'])->assertOk()->assertJsonPath('data.lock_version', 2);
    expect($source->logs()->count())->toBe(1);
});

test('p3_document_review_makes_existing_student_revision_stale_without_deleting_files', function () {
    $owner = User::factory()->create(['username' => '1202200001', 'email' => 'owner-p3@test.local',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
    $owner->givePermissionTo(['sidang_ta_registration_read', 'sidang_ta_registration_update']);
    Student::create(['student_number' => '1202200001', 'name' => 'Owner', 'email' => $owner->email,
        'academic_year' => 2022, 'study_program' => 'Informatika', 'status' => 'Aktif']);
    $source = ($this->makeSource)(['student_number' => '1202200001', 'status' => 'needs_review'], 'verified');
    $paths = $source->documents()->pluck('file_path')->all();
    $id = $this->actingAs($owner)->postJson(route('sidang-ta-drafts.revision', $source))->assertCreated()->json('data.id');
    $this->actingAs($this->reviewer);
    ($this->reviewDoc)($source, $source->documents()->first(), ['status' => 'rejected', 'notes' => 'Catatan baru'])->assertOk();
    $this->actingAs($owner)->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $id, 'step' => 1]), [
        'version' => 1, 'sidang_ta_period_id' => $this->period->id,
        'student_phone' => '081234567890', 'parent_phone' => '081234567891',
    ])->assertStatus(409);
    foreach ($paths as $path) Storage::disk('local')->assertExists($path);
    expect($source->documents()->count())->toBe(15);
});


test('p3_log_failure_rolls_back_document_and_registration_changes', function () {
    $source = ($this->makeSource)();
    $document = $source->documents()->firstOrFail();
    $paths = Storage::disk('local')->allFiles();
    $dispatcher = \App\Models\SidangTaRegistrationLog::getEventDispatcher();
    \App\Models\SidangTaRegistrationLog::setEventDispatcher(clone $dispatcher);
    \App\Models\SidangTaRegistrationLog::creating(function () { throw new RuntimeException('p3-log-failure'); });
    try {
        $this->withoutExceptionHandling();
        try {
            ($this->reviewDoc)($source, $document, ['status' => 'rejected', 'notes' => 'Perbaiki']);
            $this->fail('Log failure harus membatalkan transaksi.');
        } catch (RuntimeException $exception) {
            expect($exception->getMessage())->toBe('p3-log-failure');
        }
    } finally {
        \App\Models\SidangTaRegistrationLog::setEventDispatcher($dispatcher);
    }
    expect($document->fresh()->status)->toBe('pending');
    expect($document->fresh()->notes)->toBeNull();
    expect($source->fresh()->status)->toBe('submitted');
    expect($source->fresh()->lock_version)->toBe(1);
    expect($source->logs()->count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBe($paths);
});

```

## 9. Test feedback dan UI lengkap

**Koreksi test:** dua test feedback yang khusus tahap 2 sudah diperbaiki agar memilih `level_2_result_capture` secara eksplisit. Jika sudah menerapkan versi awal, ikuti [43](43-koreksi-fixture-feedback-p3-dan-rescan.md); kode aplikasi tidak perlu diubah untuk kedua kegagalan ini.


Isi tests/Feature/SidangTaReviewFeedbackTest.php:

```php
<?php

use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\Student;
use App\Models\User;
use App\Support\SidangTaReviewEligibility as Eligibility;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Symfony\Component\DomCrawler\Crawler;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->travelTo(now()->startOfSecond());
    Storage::fake('local');
    foreach (['sidang_ta_registration_review', 'sidang_ta_registration_read', 'sidang_ta_registration_create',
        'sidang_ta_registration_update', 'sidang_ta_period_read'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->reviewer = User::factory()->create(['username' => 'p3-feedback-reviewer', 'email' => 'p3-feedback-reviewer@test.local',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
    $this->reviewer->givePermissionTo('sidang_ta_registration_review');
    $this->actingAs($this->reviewer);
    $this->owner = User::factory()->create(['username' => '1202200001', 'email' => 'owner-feedback@test.local',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
    $this->owner->givePermissionTo(['sidang_ta_registration_read', 'sidang_ta_registration_update', 'sidang_ta_registration_create']);
    Student::create(['student_number' => '1202200001', 'name' => 'Owner Feedback', 'email' => $this->owner->email,
        'academic_year' => 2022, 'study_program' => 'Informatika', 'status' => 'Aktif', 'gpa' => 0]);

    $this->period = SidangTaPeriod::create(['name' => 'P3', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay()]);
    $this->sequence = 0;
    $this->makeSource = function (array $overrides = [], string $documentStatus = 'pending') {
        $this->sequence++;
        $source = SidangTaRegistration::create(array_replace([
            'sidang_ta_period_id' => $this->period->id, 'student_number' => $this->owner->username,
            'student_name' => 'Student P3', 'study_program' => 'Informatika',
            'student_phone' => '081234567890', 'parent_phone' => '081234567891',
            'graduation_payment_date' => today()->toDateString(), 'diploma_biodata_accurate' => true,
            'status' => 'submitted', 'submitted_at' => now(),
        ], $overrides))->fresh();
        foreach (Eligibility::requiredTypes($source) as $type) {
            $path = "sidang_ta/p3/{$source->id}/{$type}.pdf";
            Storage::disk('local')->put($path, '%PDF-1.4 p3');
            $source->documents()->create(['document_type' => $type, 'file_name' => $type.'.pdf',
                'file_path' => $path, 'file_size' => 11, 'file_type' => 'pdf', 'status' => $documentStatus]);
        }
        return $source;
    };
    $this->reviewDoc = function ($source, $document, array $overrides = []) {
        return $this->postJson(route('admin.sidang-ta-registrations.documents.review', [
            'sidang_ta_registration' => $source, 'document' => $document,
        ]), array_replace(['registration_version' => $source->fresh()->lock_version, 'status' => 'verified'], $overrides));
    };
});

afterEach(function () { $this->travelBack(); });


test('p3_student_detail_shows_escaped_document_feedback_and_indonesian_log', function () {
    $source = ($this->makeSource)();
    $document = $source->documents()->firstOrFail();
    ($this->reviewDoc)($source, $document, ['status' => 'rejected', 'notes' => '<b>Perbaiki scan</b>'])->assertOk();
    $listing = $this->get(route('admin.sidang-ta-registrations.index'))->assertOk();
    expect($listing->viewData('counts')['needs_review'])->toBe(1);
    $crawler = new Crawler($listing->getContent());
    expect($crawler->filter('[data-ta-row="'.$source->id.'"] [data-ta-doc="'.$document->document_type.'"]')->attr('data-doc-state'))->toBe('rejected');
    $response = $this->actingAs($this->owner)->get(route('sidang-ta-registrations.show', $source))->assertOk();
    $crawler = new Crawler($response->getContent());
    $status = $crawler->filter('[data-student-document-state="'.$document->id.'"]');
    expect($status->text(null, true))->toContain('Perlu perbaikan')->toContain('<b>Perbaiki scan</b>');
    expect($status->filter('b')->count())->toBe(0);
    expect($crawler->filter('[data-registration-history]')->text(null, true))->toContain('Dokumen diminta perbaikan');
    expect($crawler->filter('[data-doc-review-form]')->count())->toBe(0);
});

test('p3_revision_start_shows_source_document_feedback_without_general_note', function () {
    $source = ($this->makeSource)(['status' => 'needs_review'], 'verified');
    $document = $source->documents()
        ->where('document_type', 'level_2_result_capture')->firstOrFail();
    $document->update(['status' => 'rejected', 'notes' => 'Perbaiki PDF']);
    $crawler = new Crawler($this->actingAs($this->owner)->get(route('sidang-ta-registrations.edit', $source))->assertOk()->getContent());
    expect($crawler->filter('[data-review-feedback]')->count())->toBe(1);
    expect($crawler->filter('[data-feedback-type="'.$document->document_type.'"]')->text(null, true))
        ->toContain('Perbaiki PDF')->toContain('Tahap 2');
    expect($source->fresh()->review_notes)->toBeNull();
});

test('p3_revision_wizard_keeps_source_feedback_after_new_draft_file_selection_is_saved', function () {
    $source = ($this->makeSource)(['status' => 'needs_review'], 'verified');
    $document = $source->documents()
        ->where('document_type', 'level_2_result_capture')->firstOrFail();
    $document->update(['status' => 'rejected', 'notes' => 'Catatan dokumen asal']);
    $id = $this->actingAs($this->owner)->postJson(route('sidang-ta-drafts.revision', $source))->assertCreated()->json('data.id');
    $url = route('sidang-ta-drafts.edit', ['draft' => $id, 'step' => 2]);
    $this->get($url)->assertOk()->assertSee('Catatan dokumen asal');
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $id, 'step' => 2]), [
        'version' => 1, 'documents' => [$document->document_type => UploadedFile::fake()->create('baru.pdf', 100, 'application/pdf')],
    ])->assertOk()->assertJsonPath('data.version', 2);
    $saved = \App\Models\SidangTaRegistrationDraftDocument::query()
        ->where('draft_id', $id)->where('document_type', $document->document_type)->firstOrFail();
    expect($saved->file_name)->toBe('baru.pdf');
    expect($saved->source_document_id)->toBeNull();
    expect($saved->file_path)->not->toBe($document->file_path);
    Storage::disk('local')->assertExists($saved->file_path);
    $crawler = new Crawler($this->get($url)->assertOk()->getContent());
    expect($crawler->filter('[data-review-feedback]')->text(null, true))->toContain('Catatan dokumen asal')
        ->toContain('Berkas yang diganti akan ditinjau kembali');
    expect($document->fresh()->notes)->toBe('Catatan dokumen asal');
    Storage::disk('local')->assertExists($document->file_path);
});

test('p3_resubmission_resets_all_review_statuses_and_notes_to_pending', function () {
    $source = ($this->makeSource)(['status' => 'needs_review'], 'verified');
    $document = $source->documents()->firstOrFail();
    $document->update(['status' => 'rejected', 'notes' => 'Catatan sebelum revisi']);
    $id = $this->actingAs($this->owner)->postJson(route('sidang-ta-drafts.revision', $source))->assertCreated()->json('data.id');
    $this->postJson(route('sidang-ta-drafts.review', $id), ['version' => 1])->assertOk();
    $this->putJson(route('sidang-ta-registrations.update', $source), [
        'draft_id' => $id, 'version' => 1, 'action' => 'submit',
    ])->assertOk()->assertJsonPath('data.status', 'submitted');
    expect($source->documents()->where('status', '!=', 'pending')->exists())->toBeFalse();
    expect($source->documents()->whereNotNull('notes')->exists())->toBeFalse();
    $crawler = new Crawler($this->get(route('sidang-ta-registrations.show', $source))->assertOk()->getContent());
    expect($crawler->filter('[data-student-document-state="'.$document->id.'"]')->text(null, true))->toContain('Menunggu verifikasi')
        ->not->toContain('Catatan sebelum revisi');
});

test('p3_admin_controls_follow_eligibility_and_terminal_status', function () {
    $source = ($this->makeSource)();
    $source->documents()->create(['document_type' => 'pdm_submission_proof', 'file_name' => 'old.pdf',
        'file_path' => 'not-active-branch.pdf', 'file_type' => 'pdf', 'status' => 'pending']);
    $url = route('admin.sidang-ta-registrations.show', $source);
    $crawler = new Crawler($this->get($url)->assertOk()->getContent());
    expect($crawler->filter('[data-doc-review-form]')->count())->toBe(15);
    expect($crawler->filter('[data-ta-approve]')->getNode(0)->hasAttribute('disabled'))->toBeTrue();
    expect($crawler->filter('[data-ta-approval-blockers]')->count())->toBe(1);
    $source->documents()->whereIn('document_type', Eligibility::requiredTypes($source))->update(['status' => 'verified']);
    $crawler = new Crawler($this->get($url)->assertOk()->getContent());
    expect($crawler->filter('[data-ta-approve]')->getNode(0)->hasAttribute('disabled'))->toBeFalse();
    $source->update(['status' => 'approved']);
    $crawler = new Crawler($this->get($url)->assertOk()->getContent());
    expect($crawler->filter('[data-doc-review-form]')->count())->toBe(0);
    expect($crawler->filter('[data-ta-decision]')->count())->toBe(0);
});

test('p3_validation_keeps_only_failed_form_old_version_without_rebasing_other_forms', function () {
    $source = ($this->makeSource)();
    $documents = $source->documents()->get();
    $document = $documents->first();
    $other = $documents->get(1);
    $url = route('admin.sidang-ta-registrations.show', $source);
    $this->from($url)->post(route('admin.sidang-ta-registrations.documents.review', [
        'sidang_ta_registration' => $source, 'document' => $document,
    ]), ['registration_version' => 1, 'status' => 'rejected', 'notes' => ['invalid'],
        '_review_form' => 'document:'.$document->id])->assertRedirect($url)->assertSessionHasErrors('notes');
    $source->lock_version++;
    $source->save();
    $crawler = new Crawler($this->get($url)->assertOk()->getContent());
    expect($crawler->filter('[data-doc-review-form="'.$document->id.'"] input[name="registration_version"]')->attr('value'))->toBe('1');
    expect($crawler->filter('[data-doc-review-form="'.$other->id.'"] input[name="registration_version"]')->attr('value'))->toBe('2');
    expect($crawler->filter('[data-ta-decision] input[name="registration_version"]')->attr('value'))->toBe('2');
    ($this->reviewDoc)($source, $document, ['registration_version' => 1])->assertStatus(409);
    expect($source->fresh()->lock_version)->toBe(2);
});

```

## 10. Command test, browser dan konkurensi

```bash
php artisan route:clear
php artisan view:clear
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaDocumentReviewTest.php tests/Feature/SidangTaReviewFeedbackTest.php tests/Feature/SidangTaFinalizationTest.php tests/Feature/SidangTaRevisionDraftTest.php
```

Target fokus **44 passed** =15DocumentReview+6Feedback+13Finalization+10RevisionDraft. Tidak menambah ulang test replay lama.

Suite lengkap:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaDraftSchemaTest.php tests/Feature/SidangTaPeriodTest.php tests/Feature/SidangTaDraftTest.php tests/Feature/SidangTaDraftUploadTest.php tests/Feature/SidangTaRevisionDraftTest.php tests/Feature/SidangTaWizardTest.php tests/Feature/SidangTaFinalizationTest.php tests/Feature/SidangTaWizardUiTest.php tests/Feature/SidangTaDraftCardTest.php tests/Feature/SidangTaReviewUiTest.php tests/Feature/SidangTaDetailUiTest.php tests/Feature/SidangTaPartialDraftTest.php tests/Feature/SidangTaStudentPolishTest.php tests/Feature/SidangTaDocumentPreviewTest.php tests/Feature/SidangTaStaffNavigationTest.php tests/Feature/SidangTaReviewListTest.php tests/Feature/SidangTaReviewExportTest.php tests/Feature/SidangTaDocumentReviewTest.php tests/Feature/SidangTaReviewFeedbackTest.php
node tests/Js/sidang-ta-entry.test.mjs
node tests/Js/sidang-ta-wizard.test.mjs
node tests/Js/sidang-ta-document-preview.test.mjs
npm run build
```

Target **183 PHP, 12 Node**, build berhasil. Test kegagalan log sengaja melempar exception dan memverifikasi rollback; dispatcher diklon/dipulihkan sehingga tidak meninggalkan listener kegagalan pada test berikutnya. Tidak menjalankan test terhadap database aplikasi dadu; tetap dadu_testing Podman.

Checklist manual:

- [ ] Pada role petugas, buka detail submitted. Tiap dokumen cabang aktif mempunyai catatan, Minta perbaikan dan Verifikasi; preview/modal/unduh tetap bekerja. Cabang N/A tidak mendapat form review.
- [ ] Verifikasi satu dokumen: status/ikon berubah, versi naik, riwayat mencatat Dokumen diverifikasi, pengajuan tetap Menunggu. Dokumen lain tidak berubah.
- [ ] Minta perbaikan tanpa catatan ditolak inline. Isi catatan: dokumen Perlu perbaikan dan pengajuan Perlu revisi. Catatan umum tetap terpisah dari catatan dokumen.
- [ ] Login mahasiswa: detail menunjukkan status dan catatan, aman sebagai teks. Mulai revisi: catatan dokumen tampil walaupun catatan umum kosong. Pada wizard, setelah mengganti file draft, catatan asal tetap tersedia sampai revisi dikirim.
- [ ] Kirim ulang revisi lengkap: seluruh dokumen menjadi Menunggu verifikasi dan catatan review dokumen dikosongkan; riwayat lama tetap ada. File final tetap aman selama draft dikerjakan.
- [ ] Setujui disabled bila ada pending/rejected/missing/unavailable. Setelah semua15 atau14dokumen wajib verified dan file tersedia, tombol aktif. Verifikasi terakhir tidak otomatis menyetujui. Setujui secara eksplisit lalu halaman menjadi read-only.
- [ ] Uji cabang PDM: dua dokumen ijazah N/A tidak menghalangi. Uji Minta Revisi/Tolak umum dengan catatan. Approved/rejected tidak dapat ditinjau ulang.
- [ ] Review-only tanpa read mahasiswa tetap dapat menilai; read/update mahasiswa atau period-only tidak mendapat akses endpoint review.
- [ ] Daftar/statistik/ikon dan ekspor Excel mencerminkan status/catatan terbaru; tombol ekspor tetap hijau, filter/period/sidebar tetap bekerja.

Dua tab reviewer:

1. Buka pengajuan yang sama pada tabA dan tabB sebelum melakukan perubahan.
2. TabA verifikasi satu dokumen dan tunggu halaman selesai dimuat.
3. TabB kirim review dokumen atau keputusan dari halaman lama. Harus ditolak karena versi berubah; perubahan tabA tetap tersimpan, tidak ada log review kedua dari request lama.
4. Pada tabB klik Muat ulang pengajuan, baru lakukan tindakan lagi memakai versi terbaru. Form yang gagal validasi tidak otomatis mengganti versi lamanya.

Reviewer saat mahasiswa mengerjakan revisi:

1. Pengajuan needs_review, mahasiswa mulai draft revisi dan simpan satu tahap.
2. Petugas mengubah status/catatan salah satu dokumen sumber.
3. Mahasiswa menyimpan dari draft lama: ditolak409; file final dan file draft tidak terhapus akibat konflik.
4. Kembali ke daftar mahasiswa, hapus draft revisi stale lewat konfirmasi lalu mulai ulang dari pengajuan terbaru. Jangan mematikan source_version guard agar draft lama bisa disimpan.

Pengujian versi stale otomatis di atas memeriksa urutan aksi yang berkonflik. Klaim eksekusi paralel/lock di database tetap membutuhkan manual dua sesi; asisten tidak menjalankannya.

## 11. Rescan dan penutup petugas

Sesudah test/manual berhasil, salin15file pada bagian2 ke dadu-test. Tidak memindahkan .env/phpunit.xml lokal, database, cache/build/dependencies. Minta rescan seluruh fitur petugas dan student sebelum commit/push. P3 menyelesaikan cakupan fitur yang disetujui, tetapi belum dinyatakan selesai sampai implementasi/test/salinan lulus rescan.

Asisten akan memberi commit message petugas sesuai diff final, sebagaimana pada penutupan student. Tidak melakukan git add/commit/push sendiri dan tidak membuka tahap baru di luar scope ini.

## 12. Pemeriksaan panduan oleh asisten

Delapan blok PHP lulus sintaks (method/fragment array diperiksa dalam konteks yang sesuai); sembilan blok Blade lulus kompilasi di memori tanpa component tags dan hasil PHP lulus sintaks. Ada15test backend baru,6test feedback baru dan1blok existing pengganti; target bertambah21, bukan22.

Probe eligibility dengan model/relation dan storage stub memeriksa15/14dokumen, pending/rejected, metadata/file hilang. Probe render partial memverifikasi feedback escaped, input notes array ditangani aman, hanya form gagal mempertahankan versi1 sementara form lain/keputusan memakai2, kontrol terminal tidak tampil dan tombol Setujui disabled/aktif mengikuti helper. Semua probe lulus tanpa bootstrap aplikasi atau DB. Dua belas test Node existing dijalankan ulang dan lulus.

Review independen backend/UI/fixture tidak menemukan blocker. Lock Registration→Document(s) tidak menambah edge balik ke Student/Draft, stale diperiksa sebelum eligibility, reset pending finalizer tetap, dan hanya satu fixture existing perlu disesuaikan. Test rollback log ditambahkan untuk membuktikan transaksi secara aktual saat suite dijalankan pengguna; asisten belum menjalankan test DB ini.

Source kedua folder dipindai sebelum/sesudah setiap penulisan dan tidak diubah. Feature183/build/manual P3 belum dijalankan pengguna; hasil162 P2b tidak menjadi bukti P3 lulus. Asisten hanya menulis docs, tidak menjalankan aplikasi/DB/suite Feature/build atau commit/push.

Referensi Context7: [Laravel13 transactions](https://github.com/laravel/docs/blob/13.x/database.md), [pessimistic locking](https://github.com/laravel/docs/blob/13.x/queries.md). Semua mutation berada dalam transaksi; retry deadlock3 memakai API existing.
