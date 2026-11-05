# 17 — E: wizard empat tahap dan tinjau draft

Prasyarat: D2 lulus dan [16](16-rescan-d2-sebelum-wizard.md) dibaca. Ini pengerjaan UI student; JSON C2/D tetap didukung. File dibuat/diubah pengguna di folder asli. Asisten tetap hanya docs/*.md.

**Batas checkpoint:** halaman tinjau membaca draft tersimpan dan menandai versi yang ditinjau. Tombol final Kirim dipasang F, bersamaan dengan finalizer aman. Jangan menghubungkan review baru ke store/update lama. Selesaikan E+F sebelum menjadikan wizard pengganti yang siap dipakai umum.

## 1. Buat file

```bash
php artisan make:class Services/SidangTaDraftReviewService
php artisan make:view sidang_ta_registrations.wizard
php artisan make:view sidang_ta_registrations.wizard-review
php artisan make:view sidang_ta_registrations.revision-start
php artisan make:view sidang_ta_registrations.partials.wizard-upload
php artisan make:test SidangTaWizardTest --pest
touch resources/js/sidang-ta-wizard.js
```

File existing controller/index/app.js/app.css diedit, jangan dibuat ulang dengan --force. Tidak ada migrasi baru.

## 2. Service menandai tinjau di server

Isi `app/Services/SidangTaDraftReviewService.php`:

```php
<?php

namespace App\Services;

use App\Models\SidangTaRegistrationDraft;
use App\Models\Student;
use App\Models\User;
use App\Support\SidangTaDocumentRequirements as Requirements;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SidangTaDraftReviewService
{
    public function __construct(private SidangTaDraftAccess $access) {}

    public function mark(User $user, SidangTaRegistrationDraft $draft, int $version): SidangTaRegistrationDraft
    {
        $student = $this->access->owner($user, $draft);
        return DB::transaction(function () use ($user, $draft, $student, $version) {
            Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $locked = SidangTaRegistrationDraft::whereKey($draft->id)->lockForUpdate()->firstOrFail();
            $this->access->check($user, $locked, true);
            abort_unless($locked->version === $version, 409, 'Draft berubah. Muat ulang.');
            $first = Requirements::firstIncompleteStep($locked);
            if ($first !== null) {
                throw ValidationException::withMessages(['draft' => "Lengkapi tahap {$first} dahulu."]);
            }
            $period = $locked->period()->lockForUpdate()->firstOrFail();
            if ($locked->source_registration_id === null && !$period->isOpen()) {
                throw ValidationException::withMessages(['draft' => 'Periode tutup. Pilih periode terbuka pada tahap 1.']);
            }
            $locked->reviewed_version = $locked->version;
            $locked->save(); // Version tidak dinaikkan oleh tindakan tinjau.
            return $locked;
        });
    }

    public function preview(User $user, SidangTaRegistrationDraft $draft): SidangTaRegistrationDraft
    {
        $student = $this->access->owner($user, $draft);
        return DB::transaction(function () use ($user, $draft, $student) {
            Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $locked = SidangTaRegistrationDraft::whereKey($draft->id)->lockForUpdate()->firstOrFail();
            if ($locked->source_registration_id !== null) {
                $locked->sourceRegistration()->lockForUpdate()->firstOrFail();
            }
            $this->access->check($user, $locked);
            $locked->load(['period', 'documents']);
            abort_unless($locked->reviewed_version === $locked->version
                && Requirements::firstIncompleteStep($locked) === null, 409,
                'Draft belum ditinjau atau telah berubah. Buka form dan tinjau ulang.');
            return $locked;
        });
    }
}
```

GET preview tidak menulis database. POST mark memeriksa owner/permission/syarat sebelum menyimpan reviewed_version. Mengubah tahap melalui service existing selalu mengosongkan reviewed_version.


Agar snapshot preview tidak mencampur metadata lama dan baru, pada `SidangTaDocumentRequirements::complete()` ganti baris query `$document` dalam loop dengan:

```php
$document = $draft->relationLoaded('documents')
    ? $draft->documents->firstWhere('document_type', $type)
    : $draft->documents()->where('document_type', $type)->first();
```

Pada method respond() JSON controller draft, tambahkan `$draft->loadMissing('documents');` sebelum response, dan ganti awal ekspresi documents dari `$draft->documents()->orderBy('document_type')->get()->map(...)` menjadi `$draft->documents->sortBy('document_type')->values()->map(...)`, dengan isi mapping tetap sama. Service preview memuat payload dan relasi di bawah lock; controller hanya loadMissing, bukan load ulang sesudah lock dilepas. Finalizer F tetap memeriksa versi dan file lagi saat kirim.

## 3. Controller draft: JSON tetap, browser mendapat HTML

Di SidangTaDraftController tambahkan import:

```php
use App\Models\SidangTaPeriod;
use App\Services\SidangTaStudentContext;
use App\Services\SidangTaDraftReviewService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
```

Tambahkan dependency `private SidangTaDraftReviewService $reviews` pada constructor. Method respond() JSON tidak berubah. **Ganti seluruh** method store/edit/saveStep/startRevision/destroy dengan kode berikut; pertahankan downloadDocument():

```php
public function store(SaveSidangTaDraftStepRequest $request): JsonResponse|RedirectResponse
{
    $draft = $this->service->start($request->user(), $request->validated());
    return $request->expectsJson() ? $this->respond($draft, 201)
        : redirect()->route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 2]);
}

public function edit(Request $request, SidangTaRegistrationDraft $draft): JsonResponse|View|RedirectResponse
{
    $this->service->owned($request->user(), $draft);
    if ($request->expectsJson()) {
        return $this->respond($draft);
    }
    $query = $request->validate(['step' => ['nullable', 'integer', 'between:1,4']]);
    $maxStep = SidangTaDocumentRequirements::firstIncompleteStep($draft) ?? 4;
    $step = (int) ($query['step'] ?? $maxStep);
    if ($step > $maxStep) {
        return redirect()->route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => $maxStep]);
    }
    $draft->load(['period', 'documents', 'sourceRegistration']);
    $student = app(SidangTaStudentContext::class)->studentFor($request->user());
    $openPeriods = SidangTaPeriod::query()->openForRegistration()->get();
    return view('sidang_ta_registrations.wizard', compact('draft', 'student', 'step', 'maxStep', 'openPeriods'));
}

public function saveStep(SaveSidangTaDraftStepRequest $request,
    SidangTaRegistrationDraft $draft, int $step): JsonResponse|RedirectResponse
{
    $draft = $this->service->saveStep($request->user(), $draft, $step,
        (int) $request->validated('version'), $request->validated());
    return $request->expectsJson() ? $this->respond($draft)
        : redirect()->route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => min(4, $step + 1)])
            ->with('success', 'Tahap berhasil disimpan.');
}

public function startRevision(Request $request, SidangTaRegistration $registration): JsonResponse|RedirectResponse
{
    $request->validate([
        'owner_user_id' => 'prohibited', 'student_number' => 'prohibited',
        'sidang_ta_period_id' => 'prohibited', 'payload' => 'prohibited',
        'source_registration_id' => 'prohibited', 'source_version' => 'prohibited',
        'documents' => 'prohibited',
    ]);
    $draft = $this->revisions->start($request->user(), $registration);
    return $request->expectsJson() ? $this->respond($draft, 201)
        : redirect()->route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 1]);
}

public function destroy(Request $request, SidangTaRegistrationDraft $draft)
{
    $data = $request->validate(['version' => ['required', 'integer', 'min:1']]);
    $this->revisions->discard($request->user(), $draft, (int) $data['version']);
    return $request->expectsJson() ? response()->noContent()
        : redirect()->route('sidang-ta-registrations.index')->with('success', 'Draft dihapus.');
}

public function review(Request $request, SidangTaRegistrationDraft $draft): JsonResponse|RedirectResponse
{
    $data = $request->validate(['version' => ['required', 'integer', 'min:1']]);
    $draft = $this->reviews->mark($request->user(), $draft, (int) $data['version']);
    return $request->expectsJson() ? $this->respond($draft)
        : redirect()->route('sidang-ta-drafts.preview', $draft);
}

public function preview(Request $request, SidangTaRegistrationDraft $draft): JsonResponse|View
{
    $draft = $this->reviews->preview($request->user(), $draft);
    if ($request->expectsJson()) {
        return $this->respond($draft);
    }
    $draft->loadMissing(['period', 'documents']);
    $student = app(SidangTaStudentContext::class)->studentFor($request->user());
    return view('sidang_ta_registrations.wizard-review', compact('draft', 'student'));
}
```

Tambahkan dua route di grup dashboard:

```php
Route::post('sidang-ta-drafts/{draft}/review',
    [\App\Http\Controllers\SidangTaDraftController::class, 'review'])
    ->middleware('permission:sidang_ta_registration_create|sidang_ta_registration_update')
    ->name('sidang-ta-drafts.review');
Route::get('sidang-ta-drafts/{draft}/review',
    [\App\Http\Controllers\SidangTaDraftController::class, 'preview'])
    ->middleware('permission:sidang_ta_registration_read')->name('sidang-ta-drafts.preview');
```

## 4. Entry point student dan index

Pada SidangTaRegistrationController tambahkan import Draft dan StudentContext. **Ganti method index/create/edit saja** dengan kode berikut. Store/update final legacy belum disentuh sampai F:

```php
public function index()
{
    $student = app(\App\Services\SidangTaStudentContext::class)->studentFor(auth()->user());
    $number = $student->student_number;
    $registrations = SidangTaRegistration::with('period')->where('student_number', $number)->latest()->paginate(10);
    $openPeriods = SidangTaPeriod::query()->openForRegistration()->get();
    $drafts = \App\Models\SidangTaRegistrationDraft::query()->ownedBy(auth()->user())
        ->where('student_number', $number)->where('status', 'active')
        ->with(['period', 'sourceRegistration'])->latest()->get();
    $activeRegistration = SidangTaRegistration::where('student_number', $number)
        ->whereIn('status', ['submitted', 'needs_review'])->latest()->first();
    return view('sidang_ta_registrations.index', compact('registrations', 'openPeriods', 'drafts', 'activeRegistration'));
}

public function create()
{
    $student = app(\App\Services\SidangTaStudentContext::class)->studentFor(auth()->user());
    $active = SidangTaRegistration::where('student_number', $student->student_number)
        ->whereIn('status', ['submitted', 'needs_review'])->latest()->first();
    if ($active) {
        return redirect()->route($active->status === 'needs_review'
            ? 'sidang-ta-registrations.edit' : 'sidang-ta-registrations.show', $active);
    }
    $existing = \App\Models\SidangTaRegistrationDraft::query()->ownedBy(auth()->user())
        ->where('student_number', $student->student_number)->where('status', 'active')
        ->whereNull('source_registration_id')->first();
    if ($existing) {
        return redirect()->route('sidang-ta-drafts.edit', $existing);
    }
    $openPeriods = SidangTaPeriod::query()->openForRegistration()->get();
    $draft = null;
    $step = $maxStep = 1;
    return view('sidang_ta_registrations.wizard', compact('draft', 'student', 'step', 'maxStep', 'openPeriods'));
}

public function edit(SidangTaRegistration $sidang_ta_registration)
{
    $student = app(\App\Services\SidangTaStudentContext::class)->studentFor(auth()->user());
    $registration = $sidang_ta_registration;
    abort_unless($registration->student_number === $student->student_number
        && $registration->status === 'needs_review', 403);
    return view('sidang_ta_registrations.revision-start', compact('registration'));
}
```

Tidak ada row yang dibuat oleh GET. Link revisi mengarah ke halaman konfirmasi POST start/reuse draft, sehingga reload tidak membuat draft baru.

Pada `index.blade.php`, pertahankan tabel Riwayat Pengajuan dan pagination **mulai section tabel sampai akhir**. Ganti seluruh isi `<div class="space-y-5">` sebelum section tabel itu dengan blok berikut. Hapus banner lama yang masih memakai `$period`, bukan menyisakannya berdampingan:

```blade
<section class="campus-card space-y-3 p-5">
    <h2 class="text-xl font-bold">Pendaftaran Sidang Tugas Akhir</h2>
    @if($errors->any())
        <p class="text-rose-700">{{ $errors->first() }}</p>
    @endif
    @if(session('success'))<p class="text-emerald-700">{{ session('success') }}</p>@endif
    <p>{{ $openPeriods->count() }} periode sedang terbuka.</p>
    @if($activeRegistration)
        <p>Anda memiliki pengajuan aktif.</p>
        <a class="btn btn-secondary" href="{{ route('sidang-ta-registrations.show', $activeRegistration) }}">Lihat pengajuan</a>
        @if($activeRegistration->status === 'needs_review')
            @can('sidang_ta_registration_update')
                <a class="btn btn-primary" href="{{ route('sidang-ta-registrations.edit', $activeRegistration) }}">Mulai revisi</a>
            @endcan
        @endif
    @elseif($openPeriods->isNotEmpty())
        @can('sidang_ta_registration_create')
            <a class="btn btn-primary" href="{{ route('sidang-ta-registrations.create') }}">Mulai Pendaftaran</a>
        @endcan
    @else
        <p>Pendaftaran baru belum tersedia. Draft tersimpan tetap dapat dilihat di bawah.</p>
    @endif
</section>
<section class="campus-card space-y-4 p-5">
    <h3 class="font-bold">Draft tersimpan</h3>
    @forelse($drafts as $savedDraft)
        @php
            $source = $savedDraft->sourceRegistration;
            $stale = $savedDraft->source_registration_id !== null
                && (!$source || $source->status !== 'needs_review' || $source->lock_version !== $savedDraft->source_version);
            $writePermission = $savedDraft->source_registration_id === null
                ? 'sidang_ta_registration_create' : 'sidang_ta_registration_update';
        @endphp
        <article class="rounded-xl border p-4" data-testid="draft-card">
            <p class="font-semibold">{{ $savedDraft->period?->name ?? 'Periode tidak tersedia' }}</p>
            <p class="text-sm">{{ $savedDraft->source_registration_id ? 'Draft revisi' : 'Draft pendaftaran baru' }} · Terakhir disimpan {{ $savedDraft->updated_at->format('d/m/Y H:i') }}</p>
            @if($stale)
                <p class="text-amber-800">Pengajuan asal berubah. Hapus draft lama sebelum memulai revisi kembali.</p>
            @else
                <a class="btn btn-secondary" href="{{ route('sidang-ta-drafts.edit', $savedDraft) }}">Lanjutkan draft</a>
            @endif
            @can($writePermission)
                <form class="mt-3" method="POST" action="{{ route('sidang-ta-drafts.destroy', $savedDraft) }}"
                      onsubmit="return confirm('Hapus draft ini? Data pengajuan yang sudah dikirim tetap disimpan.');">
                    @csrf @method('DELETE')
                    <input type="hidden" name="version" value="{{ $savedDraft->version }}">
                    <button class="btn btn-secondary" type="submit">Hapus draft</button>
                </form>
            @endcan
        </article>
    @empty
        <p class="text-sm text-slate-500">Belum ada draft tersimpan.</p>
    @endforelse
</section>
```

Sengaja tidak memanggil owned()/check() saat membuat kartu stale. Query sudah terfilter owner+NIM, sedangkan service discard melakukan pemeriksaan ulang server.

Isi `revision-start.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">Revisi Pendaftaran Sidang TA</x-slot>
    <section class="campus-card space-y-4 p-5">
        <h2 class="font-bold">Perbaiki pengajuan Anda</h2>
        <p>{{ $registration->review_notes ?: 'Periksa kembali data dan dokumen pengajuan.' }}</p>
        <p>Pengajuan yang sudah dikirim tetap tersimpan selama Anda mengerjakan draft revisi.</p>
        <form method="POST" action="{{ route('sidang-ta-drafts.revision', $registration) }}">
            @csrf
            <button type="submit" class="btn btn-primary">Mulai atau lanjutkan draft revisi</button>
        </form>
        <a href="{{ route('sidang-ta-registrations.index') }}" class="btn btn-secondary">Kembali</a>
    </section>
</x-app-layout>
```

## 5. Alpine minimal dan satu form untuk tahap aktif

Isi `resources/js/sidang-ta-wizard.js`:

```javascript
export default (initial = {}) => ({
    accurate: initial.accurate ?? '',
    dirty: initial.dirty ?? false,
    busy: false,
    leave(event) {
        if (!this.dirty) return;
        if (!window.confirm('Perubahan belum disimpan. Tetap tinggalkan halaman?')) {
            event.preventDefault();
            return;
        }
        this.dirty = false;
    },
});
```

Pada app.js, tambahkan import modul dan registrasinya **sebelum Alpine.start()**:

```javascript
import sidangTaWizard from './sidang-ta-wizard';
Alpine.data('sidangTaWizard', sidangTaWizard);
```

Tambahkan ke `resources/css/app.css` jika belum ada:

```css
[x-cloak] { display: none !important; }
```

Isi `partials/wizard-upload.blade.php`:

```blade
@php
    $existing = $draft?->documents?->firstWhere('document_type', $type);
    $available = $existing && \Illuminate\Support\Facades\Storage::disk('local')->exists($existing->file_path);
    $label = \App\Models\SidangTaRegistration::DOCUMENT_TYPES[$type] ?? $type;
    $capture = in_array($type, \App\Support\SidangTaDocumentRequirements::CAPTURES, true);
    $conditional = $conditional ?? null;
@endphp
<div class="rounded-xl border border-slate-200 p-4" x-data="{ fileName: '' }">
    <label class="block font-semibold" for="file-{{ $type }}">{{ $label }}</label>
    <p class="mt-1 text-xs text-slate-500">{{ \App\Models\SidangTaRegistration::DOCUMENT_DESCRIPTIONS[$type] ?? '' }}</p>
    @if($available)
        <a class="my-2 block text-sm text-emerald-700 underline" href="{{ route('sidang-ta-draft-documents.download', $existing) }}">Berkas tersimpan: {{ $existing->file_name }}</a>
    @elseif($existing)
        <p class="text-sm text-rose-700">Berkas tersimpan tidak ditemukan. Unggah kembali.</p>
    @endif
    <input id="file-{{ $type }}" x-ref="file" type="file" name="documents[{{ $type }}]"
        accept="{{ $capture ? '.pdf,.jpg,.jpeg,.png' : '.pdf' }}"
        @change="fileName = $event.target.files[0]?.name ?? ''; dirty = true"
        @if($conditional !== null)
            :disabled="accurate !== '{{ $conditional }}'"
            :required="accurate === '{{ $conditional }}' && {{ $available ? 'false' : 'true' }}"
        @else
            @required(!$available)
        @endif
        class="mt-3 block w-full text-sm">
    <p class="mt-1 text-xs text-slate-500">{{ $capture ? 'PDF/JPG/PNG' : 'PDF' }}, maksimal 10 MB per file.</p>
    <p x-show="fileName" class="text-sm text-amber-800">File pilihan baru belum disimpan: <span x-text="fileName"></span></p>
    <button x-show="fileName" type="button" class="text-sm underline"
        @click="$refs.file.value = ''; fileName = ''; dirty = true">Batalkan pilihan baru</button>
    @error("documents.{$type}")<p class="text-sm text-rose-700">{{ $message }}</p>@enderror
</div>
```

Disabled cabang tidak aktif mencegah file tersembunyi ikut dikirim. Server tetap memakai whitelist cabang. Label file baru dibedakan dari file tersimpan; membatalkan pilihan tidak menghapus file server.

Isi `wizard.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">{{ $draft?->source_registration_id ? 'Draft Revisi Sidang TA' : 'Pendaftaran Sidang TA' }}</x-slot>
    @php
        $payload = $draft?->payload ?? [];
        $accurate = (string) old('diploma_biodata_accurate', array_key_exists('diploma_biodata_accurate', $payload)
            ? ($payload['diploma_biodata_accurate'] ? '1' : '0') : '');
        $initial = ['accurate' => $accurate, 'dirty' => $errors->any()];
        $selectedPeriod = (string) old('sidang_ta_period_id', $draft?->sidang_ta_period_id ?? '');
        $periods = $openPeriods;
        if ($draft && !$periods->contains('id', $draft->sidang_ta_period_id)) {
            $periods = $periods->prepend($draft->period);
        }
        $permission = $draft?->source_registration_id ? 'sidang_ta_registration_update' : 'sidang_ta_registration_create';
        $titles = [1 => 'Data dasar & kontak', 2 => 'Verifikasi iGracias & akademik',
            3 => 'Pembayaran & dokumen', 4 => 'Biodata ijazah'];
    @endphp
    <div class="space-y-5" data-current-step="{{ $step }}" x-data="sidangTaWizard(@js($initial))"
         @beforeunload.window="if (dirty && !busy) { $event.preventDefault(); $event.returnValue = ''; }">
        @if($errors->any())
            <div role="alert" class="rounded-xl bg-rose-50 p-4 text-rose-800">
                <p class="font-bold">Periksa isian berikut:</p>
                <ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                <p>Data yang sudah tersimpan tetap tersedia. File pilihan baru yang ditolak perlu dipilih ulang.</p>
            </div>
        @endif
        @if(session('success'))<p class="text-emerald-700">{{ session('success') }}</p>@endif
        <nav aria-label="Tahap pendaftaran" class="grid grid-cols-2 gap-2 md:grid-cols-4">
            @foreach($titles as $number => $title)
                @if($draft && $number <= $maxStep)
                    <a @click="leave($event)" href="{{ route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => $number]) }}"
                       @if($step === $number) aria-current="step" @endif
                       class="rounded-xl border p-3 {{ $step === $number ? 'border-rose-600 bg-rose-50' : 'bg-white' }}">
                        Tahap {{ $number }}<span class="block text-xs">{{ $title }}</span>
                    </a>
                @else
                    <span class="rounded-xl border bg-slate-100 p-3" @if($number === $step) aria-current="step" @else aria-disabled="true" @endif>Tahap {{ $number }}<span class="block text-xs">{{ $title }}</span></span>
                @endif
            @endforeach
        </nav>
        <section class="campus-card space-y-2 p-5">
            <p class="font-bold">{{ $student->name }} · {{ $student->student_number }}</p>
            <p>{{ $student->study_program }} · {{ $student->email }}</p>
            <p class="text-sm">IPK: {{ $student->gpa ?? 'Belum tersedia' }} · SKS: {{ $student->total_credits ?? 'Belum tersedia' }}</p>
            <p class="text-sm">Judul TA: {{ $student->finalProject?->thesis_title_id ?? 'Belum tersedia' }}</p>
            <p class="text-sm">Pembimbing: {{ $student->finalProject?->advisor_1 ?? '-' }} / {{ $student->finalProject?->advisor_2 ?? '-' }}</p>
            @if($draft)<p class="text-xs">Terakhir disimpan {{ $draft->updated_at->format('d/m/Y H:i') }}.</p>@endif
            @if($draft?->sourceRegistration?->review_notes)<p class="text-amber-800">Catatan petugas: {{ $draft->sourceRegistration->review_notes }}</p>@endif
            <p class="text-sm">Tekan Simpan agar data dan file dapat dilanjutkan setelah halaman ditutup.</p>
        </section>
        <form method="POST" enctype="multipart/form-data" class="campus-card space-y-4 p-5"
              action="{{ $draft ? route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => $step]) : route('sidang-ta-drafts.store') }}"
              @input="dirty = true" @change="dirty = true" @submit="busy = true">
            @csrf
            @if($draft)<input type="hidden" name="version" value="{{ old('version', $draft->version) }}">@endif
            <h2 class="text-lg font-bold">Tahap {{ $step }} — {{ $titles[$step] }}</h2>
            @if($step === 1)
                <label class="block">Periode Sidang TA
                    @if($draft?->source_registration_id)
                        <input type="hidden" name="sidang_ta_period_id" value="{{ $draft->sidang_ta_period_id }}">
                        <span class="block font-semibold">{{ $draft->period->name }} (periode pengajuan asal)</span>
                    @else
                        <select class="campus-input" name="sidang_ta_period_id" required>
                            <option value="">Pilih periode terbuka</option>
                            @foreach($periods as $period)
                                <option value="{{ $period->id }}" @selected($selectedPeriod === (string) $period->id)>
                                    {{ $period->name }} · {{ $period->open_at->format('d/m/Y H:i') }}–{{ $period->close_at->format('d/m/Y H:i') }}{{ $period->isOpen() ? '' : ' (ditutup)' }}
                                </option>
                            @endforeach
                        </select>
                    @endif
                </label>
                <label class="block">Nomor HP mahasiswa<input class="campus-input" name="student_phone" inputmode="tel" maxlength="30" required
                    value="{{ old('student_phone', $payload['student_phone'] ?? $student->phone_number) }}"></label>
                <label class="block">Nomor HP orang tua/wali<input class="campus-input" name="parent_phone" inputmode="tel" maxlength="30" required
                    value="{{ old('parent_phone', $payload['parent_phone'] ?? $student->parent_phone_number) }}"></label>
            @elseif($step === 2 || $step === 3)
                @if($step === 3)
                    <label class="block">Tanggal pembayaran wisuda<input class="campus-input" type="date" name="graduation_payment_date" required
                        max="{{ today()->toDateString() }}" value="{{ old('graduation_payment_date', $payload['graduation_payment_date'] ?? '') }}"></label>
                @endif
                <div class="grid gap-4 md:grid-cols-2">
                    @foreach(\App\Support\SidangTaDocumentRequirements::typesForStep($step) as $type)
                        @include('sidang_ta_registrations.partials.wizard-upload', ['type' => $type, 'conditional' => null])
                    @endforeach
                </div>
            @else
                <fieldset><legend>Apakah biodata dummy ijazah sudah sesuai?</legend>
                    <label><input type="radio" name="diploma_biodata_accurate" value="1" x-model="accurate" required> Sudah sesuai</label>
                    <label class="ml-4"><input type="radio" name="diploma_biodata_accurate" value="0" x-model="accurate"> Belum sesuai, sudah mengajukan PDM</label>
                </fieldset>
                <div x-show="accurate === '1'" x-cloak class="grid gap-4 md:grid-cols-2">
                    @foreach(\App\Support\SidangTaDocumentRequirements::DIPLOMA as $type)
                        @include('sidang_ta_registrations.partials.wizard-upload', ['type' => $type, 'conditional' => '1'])
                    @endforeach
                </div>
                <div x-show="accurate === '0'" x-cloak>
                    @include('sidang_ta_registrations.partials.wizard-upload', ['type' => 'pdm_submission_proof', 'conditional' => '0'])
                </div>
            @endif
            @can($permission)
                <button type="submit" class="btn btn-primary" :disabled="busy"
                    x-text="busy ? 'Sedang menyimpan…' : '{{ $step === 4 ? 'Simpan tahap 4' : 'Simpan & Lanjutkan' }}'">Simpan</button>
            @endcan
            <p x-show="dirty" class="text-sm text-amber-800">Ada perubahan yang belum disimpan.</p>
        </form>
        @if($draft && $step === 4)
            @can($permission)
                <form method="POST" action="{{ route('sidang-ta-drafts.review', $draft) }}"
                      @submit="if (dirty) { $event.preventDefault(); } else { busy = true; }">
                    @csrf <input type="hidden" name="version" value="{{ $draft->version }}">
                    <button type="submit" class="btn btn-primary" :disabled="dirty || busy">Tinjau Sebelum Kirim</button>
                    <p class="text-sm">Simpan perubahan tahap 4 dahulu sebelum meninjau.</p>
                </form>
            @endcan
        @endif
        <a @click="leave($event)" class="btn btn-secondary" href="{{ route('sidang-ta-registrations.index') }}">Kembali ke pendaftaran</a>
    </div>
</x-app-layout>
```

Hanya input tahap aktif yang ada pada form, sehingga required tahap tersembunyi tidak menghalangi validasi. Stepper membawa query step; server membatasi akses GET dan service membatasi POST. Browser tanpa JS tidak didukung untuk cabang dinamis pada tahap 4; pastikan Vite/Alpine termuat pada pemeriksaan browser, jangan menganggap test Blade menguji JS.

Hidden version memakai old input setelah validasi gagal, sama seperti kontak. Jangan memasangkan kontak lama dengan versi DB baru, karena retry dari tab lama dapat menimpa perubahan tab lain. Jika mendapat 409, buka ulang draft dari index dan cocokkan data terbaru.

## 6. Halaman tinjau dari data tersimpan

Isi `wizard-review.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">Tinjau Pendaftaran Sidang TA</x-slot>
    @php($payload = $draft->payload)
    <div class="space-y-5">
        <section class="campus-card space-y-2 p-5">
            <h2 class="text-xl font-bold">Tinjau sebelum kirim</h2>
            <p>{{ $student->name }} · {{ $student->student_number }} · {{ $draft->period->name }}</p>
            <p>HP mahasiswa: {{ $payload['student_phone'] }} · HP orang tua/wali: {{ $payload['parent_phone'] }}</p>
            <p>Tanggal pembayaran wisuda: {{ $payload['graduation_payment_date'] }}</p>
            <p>Biodata: {{ $payload['diploma_biodata_accurate'] ? 'Sudah sesuai' : 'Belum sesuai, bukti PDM dilampirkan' }}</p>
            <p>Data di bawah berasal dari draft yang tersimpan. Pendaftaran belum dikirim.</p>
        </section>
        @foreach([2 => 'Verifikasi iGracias & akademik', 3 => 'Pembayaran & dokumen', 4 => 'Biodata ijazah'] as $number => $title)
            <section class="campus-card p-5"><h3 class="font-bold">{{ $title }}</h3>
                @foreach(\App\Support\SidangTaDocumentRequirements::typesForStep($number, $payload['diploma_biodata_accurate']) as $type)
                    @php($document = $draft->documents->firstWhere('document_type', $type))
                    <div class="flex flex-wrap justify-between gap-2 border-b py-3">
                        <div><p>{{ \App\Models\SidangTaRegistration::DOCUMENT_TYPES[$type] }}</p>
                            <p class="break-all text-sm text-slate-500">{{ $document->file_name }} · {{ number_format(($document->file_size ?? 0)/1024) }} KB</p></div>
                        <a class="underline" href="{{ route('sidang-ta-draft-documents.download', $document) }}">Unduh</a>
                    </div>
                @endforeach
            </section>
        @endforeach
        <a class="btn btn-secondary" href="{{ route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 1]) }}">Kembali ke form</a>
        <a class="btn btn-secondary" href="{{ route('sidang-ta-registrations.index') }}">Kembali ke pendaftaran</a>
    </div>
</x-app-layout>
```

Tombol Kirim Pendaftaran sengaja **belum** dipasang pada checkpoint antara ini. F akan menambah satu form final tanpa upload ulang, memeriksa ulang reviewed_version dan seluruh file, lalu mengonsumsi draft. Jangan membuat tombol yang hanya menampilkan sukses palsu atau diarahkan ke pipeline lama.

## 7. Dashboard jangan lagi mengunci satu periode

Pada DashboardController, hapus blok khusus TA yang menghitung `$activeSidangTaPeriod` dan `$mySidangTaRegistration`, serta hapus dua nama itu dari compact(). Pertahankan seluruh query yudisium/queue/lainnya.

Pada dashboard.blade.php, ganti **seluruh card Pendaftaran Sidang TA** lama (blok komentar Card: Pendaftaran Sidang TA sampai penutup card, sebelum penutup grid) dengan:

```blade
@can('sidang_ta_registration_read')
    <section class="campus-card flex flex-col gap-3 p-5">
        <h3 class="font-bold">Pendaftaran Sidang TA</h3>
        <p class="text-sm text-slate-500">Pilih periode, lanjutkan draft tersimpan, atau lihat riwayat pengajuan.</p>
        <a class="btn btn-primary" href="{{ route('sidang-ta-registrations.index') }}">Buka Pendaftaran Sidang TA</a>
    </section>
@endcan
```

Cari ulang `activeSidangTaPeriod` / `mySidangTaRegistration` pada dashboard: jangan menyisakan referensi variabel yang sudah dihapus. SidangTaPeriod::active() masih dipakai pipeline legacy store; F menggantinya. Jangan menghapus method model dahulu.

## 8. Kode test lengkap HTML dan review

Isi seluruh `tests/Feature/SidangTaWizardTest.php`:

```php
<?php

use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDraft;
use App\Models\Student;
use App\Models\User;
use App\Support\SidangTaDocumentRequirements as Requirements;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create([
        'username' => '1202200001', 'email' => 'wizard@mahasiswa.test',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false,
    ]);
    Student::create(['student_number' => '1202200001', 'name' => 'Wizard Test',
        'email' => $this->user->email, 'academic_year' => 2022, 'study_program' => 'Informatika', 'status' => 'Aktif']);
    foreach (['read', 'create', 'update'] as $action) {
        $this->user->givePermissionTo(Permission::firstOrCreate([
            'name' => "sidang_ta_registration_{$action}", 'guard_name' => 'web',
        ]));
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->period = SidangTaPeriod::create(['name' => 'Periode E', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay()]);
    $this->data = ['sidang_ta_period_id' => $this->period->id,
        'student_phone' => '081234567890', 'parent_phone' => '081234567891'];
    $this->actingAs($this->user);
    $this->completeDraft = function () {
        $id = $this->postJson(route('sidang-ta-drafts.store'), $this->data)->assertCreated()->json('data.id');
        $draft = SidangTaRegistrationDraft::findOrFail($id);
        $draft->payload = array_replace($draft->payload, [
            'graduation_payment_date' => today()->toDateString(), 'diploma_biodata_accurate' => true,
        ]);
        $draft->save();
        foreach (array_merge(Requirements::CAPTURES, Requirements::PAYMENT, Requirements::DIPLOMA) as $type) {
            $path = "sidang_ta/drafts/{$draft->id}/{$type}.pdf";
            Storage::disk('local')->put($path, '%PDF-test');
            $draft->documents()->create(['document_type' => $type, 'file_name' => $type.'.pdf',
                'file_path' => $path, 'file_size' => 10, 'file_type' => 'pdf']);
        }
        return $draft;
    };
});

test('html_create_lists_multiple_periods_without_creating_draft', function () {
    SidangTaPeriod::create(['name' => 'Periode E kedua', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay()]);
    $this->get(route('sidang-ta-registrations.create'))->assertOk()
        ->assertViewIs('sidang_ta_registrations.wizard')
        ->assertSee('Periode E kedua')->assertSee('data-current-step="1"', false);
    expect(SidangTaRegistrationDraft::count())->toBe(0);
});

test('html_save_redirects_and_future_step_is_locked', function () {
    $this->post(route('sidang-ta-drafts.store'), $this->data)->assertRedirect();
    $draft = SidangTaRegistrationDraft::where('owner_user_id', $this->user->id)->firstOrFail();
    $this->get(route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 2]))
        ->assertOk()->assertSee('data-current-step="2"', false);
    $this->get(route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 4]))
        ->assertRedirect(route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 2]));
});

test('incomplete_draft_cannot_be_marked_reviewed', function () {
    $id = $this->postJson(route('sidang-ta-drafts.store'), $this->data)->assertCreated()->json('data.id');
    $this->postJson(route('sidang-ta-drafts.review', $id), ['version' => 1])->assertUnprocessable();
    expect(SidangTaRegistrationDraft::findOrFail($id)->reviewed_version)->toBeNull();
});

test('review_marks_saved_version_and_renders_fifteen_documents', function () {
    $draft = ($this->completeDraft)();
    $this->post(route('sidang-ta-drafts.review', $draft), ['version' => 1])
        ->assertRedirect(route('sidang-ta-drafts.preview', $draft));
    $response = $this->get(route('sidang-ta-drafts.preview', $draft))->assertOk()
        ->assertViewIs('sidang_ta_registrations.wizard-review')
        ->assertViewHas('draft', fn ($viewDraft) => $viewDraft->documents->count() === 15);
    $crawler = new \Symfony\Component\DomCrawler\Crawler($response->getContent());
    expect($crawler->text(null, true))->toContain('Pendaftaran belum dikirim.');
    expect($draft->fresh()->reviewed_version)->toBe(1);
    expect(SidangTaRegistration::count())->toBe(0);
});

test('saved_change_invalidates_review', function () {
    $draft = ($this->completeDraft)();
    $this->postJson(route('sidang-ta-drafts.review', $draft), ['version' => 1])->assertOk();
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 1]),
        array_replace($this->data, ['version' => 1, 'student_phone' => '089999999999']))->assertOk();
    $this->getJson(route('sidang-ta-drafts.preview', $draft))->assertStatus(409);
    expect($draft->fresh()->reviewed_version)->toBeNull();
});

test('index_exposes_discard_for_stale_revision', function () {
    $source = SidangTaRegistration::create([
        'sidang_ta_period_id' => $this->period->id, 'student_number' => '1202200001',
        'student_name' => 'Wizard Test', 'student_phone' => '081234567890',
        'parent_phone' => '081234567891', 'graduation_payment_date' => today()->toDateString(),
        'diploma_biodata_accurate' => true, 'status' => 'needs_review',
    ])->fresh();
    $id = $this->postJson(route('sidang-ta-drafts.revision', $source))->assertCreated()->json('data.id');
    $source->lock_version++;
    $source->save();
    $response = $this->get(route('sidang-ta-registrations.index'))->assertOk()
        ->assertSee(route('sidang-ta-drafts.destroy', $id), false)
        ->assertDontSee(route('sidang-ta-drafts.edit', $id), false);
    $crawler = new \Symfony\Component\DomCrawler\Crawler($response->getContent());
    $card = $crawler->filter('[data-testid="draft-card"]');
    expect($card->count())->toBe(1);
    expect($card->text(null, true))->toContain('Pengajuan asal berubah.');
    $this->delete(route('sidang-ta-drafts.destroy', $id), ['version' => 1])
        ->assertRedirect(route('sidang-ta-registrations.index'));
    expect(SidangTaRegistrationDraft::find($id))->toBeNull();
    expect($source->fresh()->status)->toBe('needs_review');
});

test('missing_file_prevents_preview_even_after_review', function () {
    $draft = ($this->completeDraft)();
    $this->postJson(route('sidang-ta-drafts.review', $draft), ['version' => 1])->assertOk();
    Storage::disk('local')->delete($draft->documents()->first()->file_path);
    $this->getJson(route('sidang-ta-drafts.preview', $draft))->assertStatus(409);
});

test('other_user_cannot_open_html_wizard_or_review', function () {
    $draft = ($this->completeDraft)();
    $other = User::factory()->create(['status' => 'active', 'must_change_password' => false]);
    $other->givePermissionTo('sidang_ta_registration_read');
    $this->actingAs($other)->get(route('sidang-ta-drafts.edit', $draft))->assertForbidden();
    $this->get(route('sidang-ta-drafts.preview', $draft))->assertForbidden();
});

test('html_validation_preserves_stale_version_until_conflict_is_resolved', function () {
    $draft = ($this->completeDraft)();
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 1]),
        array_replace($this->data, ['version' => 1, 'student_phone' => '089999999999']))->assertOk();
    $url = route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 1]);
    $this->from($url)->post(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 1]),
        array_replace($this->data, ['version' => 1, 'student_phone' => '']))
        ->assertRedirect($url)->assertSessionHasErrors('student_phone');
    $this->get($url)->assertOk()->assertSee('name="version" value="1"', false);
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 1]),
        array_replace($this->data, ['version' => 1, 'student_phone' => '080000000000']))->assertStatus(409);
    expect($draft->fresh()->payload['student_phone'])->toBe('089999999999');
});

test('preview_returns_loaded_snapshot_before_concurrent_branch_change', function () {
    $draft = ($this->completeDraft)();
    $this->postJson(route('sidang-ta-drafts.review', $draft), ['version' => 1])->assertOk();
    $snapshot = app(\App\Services\SidangTaDraftReviewService::class)->preview($this->user, $draft);
    expect($snapshot->relationLoaded('documents'))->toBeTrue();
    expect($snapshot->relationLoaded('period'))->toBeTrue();
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 4]), [
        'version' => 1, 'diploma_biodata_accurate' => false,
        'documents' => ['pdm_submission_proof' => \Illuminate\Http\UploadedFile::fake()->create('pdm.pdf', 100, 'application/pdf')],
    ])->assertOk();
    expect($snapshot->payload['diploma_biodata_accurate'])->toBeTrue();
    expect($snapshot->documents->count())->toBe(15);
    expect($snapshot->documents->firstWhere('document_type', 'diploma_statement'))->not->toBeNull();
});

```

## 9. Jalankan test, build dan uji browser

```bash
php artisan config:clear
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing php artisan test tests/Feature/SidangTaDraftSchemaTest.php tests/Feature/SidangTaPeriodTest.php tests/Feature/SidangTaDraftTest.php tests/Feature/SidangTaDraftUploadTest.php tests/Feature/SidangTaRevisionDraftTest.php tests/Feature/SidangTaWizardTest.php
npm run build
```

Test baru berjumlah sepuluh. Gunakan koneksi lokal lengkap dari 11 bila phpunit.xml sudah dipulihkan. Jangan memakai migrate:fresh pada database aplikasi.

Setelah itu jalankan aplikasi/Vite seperti biasa dan login akun dummy dengan role Mahasiswa:

- [ ] Pilih salah satu dari dua periode terbuka, simpan kontak: pindah ke tahap 2, reload tetap tersimpan.
- [ ] Coba klik/URL tahap 4 sebelum 2–3 selesai: diarahkan kembali oleh server.
- [ ] Upload file nyata pada tiap tahap; browser Network hanya mengirim file tahap aktif. File >10 MB dan format salah mendapat pesan validasi.
- [ ] Bolak-balik radio biodata: hanya cabang aktif ikut dikirim; pilihan baru belum tersimpan diberi label.
- [ ] Tutup browser setelah save, login lagi dan lanjut dari daftar draft. Data yang belum di-save tidak dijanjikan pulih.
- [ ] Tinjau menampilkan 14/15 file tersimpan; simpan perubahan lalu tinjau ulang. Belum ada final submit pada E.
- [ ] Uji revisi dan draft stale: hapus dari index lalu mulai kembali; final lama tetap utuh.
- [ ] Uji keyboard, layar kecil, nama file panjang, asset JS termuat, error summary dan konfirmasi meninggalkan perubahan.

Fake HTTP/Blade test tidak mengeksekusi Alpine atau membuktikan transport file nyata. Bila gagal pada UI, kirim screenshot, console error dan response Network yang relevan tanpa password.

Saat hasil dipindah, sertakan seluruh Blade/JS/controller/service/test dan routes, bukan hanya PHP. Berikutnya F memasang finalizer dan tombol Kirim. Perapian detail riwayat mendalam tetap G.

Rujukan Context7: [expectsJson](https://github.com/laravel/docs/blob/13.x/requests.md), [view assertions](https://github.com/laravel/docs/blob/13.x/http-tests.md), [Alpine.data bundle](https://alpinejs.dev/globals/alpine-data). Asisten memeriksa source dan sintaks dokumen; test/build/browser belum dijalankan asisten.


Pembaruan rescan 2 November: pengguna melaporkan test dan browser E berhasil. [18](18-rescan-e-diagnosis-pilihan-periode.md) menjelaskan Periode 2 yang belum dibuka, memperkuat test dropdown, dan memperbaiki query step kosong; kode controller contoh di sini sudah diselaraskan. Prioritas visual ditunda sampai fungsi student selesai.


Pembaruan sesudah E/regresi18 lulus dan disalin: [19](19-rescan-e-siap-finalisasi.md) mencatat rescan dan bukti Tinker terbaru; lanjutkan [20 finalisasi F](20-checkpoint-f-finalisasi-draft.md) untuk tombol Kirim dan pipeline final baru, tanpa perubahan visual.
