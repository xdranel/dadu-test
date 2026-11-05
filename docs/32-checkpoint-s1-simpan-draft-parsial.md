# 32 — S1: simpan draft parsial, lanjut setelah lengkap

**Goal:** file valid dan isian draft tahap 2–4 dapat disimpan sebelum lengkap, sementara lanjut/tinjau/final tetap dijaga.

**Architecture:** pisahkan draftScalarRules dari scalarRules kelengkapan; simpan hanya file yang dikirim, lalu hitung progres dari data tersimpan. Save kembali ke tahap saat ini. UI memisahkan Simpan draft dan Lanjutkan.

**Tech stack:** Laravel 13, Blade/Tailwind/Alpine yang sudah ada, Pest, MySQL Podman. Tidak menambah dependency/migrasi/route.

**Spec:** [31](31-rancangan-draft-parsial-dan-review-petugas-ta.md), disetujui pengguna melalui **setuju dan lanjutkan**.

**Koreksi penerapan:** bila suite fokus berakhir 4 failed/27 passed, lihat [33](33-koreksi-empat-test-s1.md). Pastikan counter memiliki spasi sebelum `dari`, dan dua test WizardUi lama diganti, bukan dipertahankan bersama penggantinya. Target suite fokus adalah 29 test.

## Batas checkpoint

- Tahap 1 tetap memerlukan periode terbuka dan kontak valid untuk membuat draft. Setelah save awal, tetap pada tahap 1 sampai mahasiswa menekan Lanjutkan.
- Tahap 2–4 boleh belum lengkap, termasuk tanggal/pilihan biodata kosong. Nilai/file yang dikirim tetap harus valid. Satu file invalid tetap membatalkan request; dokumen yang belum dipilih tidak lagi membatalkan save.
- Field tidak dikirim mempertahankan nilai lama. Null eksplisit boleh mengosongkan scalar tahap 3/4, dan null biodata tidak diubah menjadi PDM/false.
- Owner/permission/version, perlindungan file final pinjaman, rollback, urutan tahap dan strict finalisasi tetap berlaku.
- **S2 berikutnya** menangani penghapusan tombol Unduh pada draft, kalimat bantuan read-only dan header index. Tombol Unduh masih dipertahankan pada S1 agar checkpoint/test tidak bercampur.
- Petugas, ekspor dan verifikasi dokumen belum diimplementasikan pada S1.

## Fokus review

1. Satu file tersimpan bertahan sesudah halaman ditutup, tetapi tidak membuka tahap berikutnya.
2. Beberapa save melengkapi tahap tanpa mengunggah ulang file sebelumnya.
3. Field tidak dikirim/null/false memiliki arti berbeda, terutama biodata.
4. File invalid, versi usang, cabang salah dan lompat tahap tidak menyebabkan perubahan parsial yang tidak disengaja.
5. Draft revisi tidak menghapus file final ketika cabang draft diganti sebelum lengkap.

## 1. File dan persiapan test

Edit file existing berikut:

```text
app/Support/SidangTaDocumentRequirements.php
app/Http/Requests/SaveSidangTaDraftStepRequest.php (rules saja)
app/Services/SidangTaDraftDocumentService.php
app/Http/Controllers/SidangTaDraftController.php (store, edit, saveStep)
resources/views/sidang_ta_registrations/wizard.blade.php
tests/Feature/SidangTaDraftUploadTest.php (dua blok test)
tests/Feature/SidangTaWizardUiTest.php (dua blok test)
```

Buat satu file baru:

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
php artisan make:test SidangTaPartialDraftTest --pest
```

- [ ] Isi test lengkap bagian 8 dahulu, jalankan command fokus bagian 10 sebelum source diubah. Regresi partial-save semestinya gagal pada implementasi lama; error koneksi bukan regresi yang dimaksud.
- [ ] Terapkan source bagian 2–7 dan empat pengganti test lama bagian 9.
- [ ] Jalankan test fokus/suite/checklist. Jangan menghapus test hanya karena kontrak lama berbeda.

## 2. Requirements: aturan draft dan kelengkapan

Ganti seluruh `app/Support/SidangTaDocumentRequirements.php`:

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
        $types = self::typesForStep($step, $accurate);
        $draft->loadMissing('documents');
        $stored = 0;
        foreach ($types as $type) {
            $document = $draft->documents->firstWhere('document_type', $type);
            if ($document && Storage::disk('local')->exists($document->file_path)) {
                $stored++;
            } else {
                $missing[] = SidangTaRegistration::DOCUMENT_TYPES[$type] ?? $type;
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

Progress menghitung file yang benar-benar ada, bukan jumlah metadata saja. Bila pilihan biodata null, jumlah dokumen wajib belum diketahui dan tidak ditampilkan sebagai 0/0. Kelengkapan tetap menggunakan aturan scalar wajib.

## 3. Request: ganti method rules() saja

Pada `app/Http/Requests/SaveSidangTaDraftStepRequest.php`, pertahankan authorize(), messages() dan attributes(). Ganti hanya rules():

```php
public function rules(): array
{
    $step = (int) ($this->route('step') ?? 1);
    $rules = ['version' => [$this->route('draft') ? 'required' : 'nullable', 'integer', 'min:1']];
    foreach (['owner_user_id', 'student_number', 'status', 'payload', 'source_registration_id',
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
```

Request tetap membatasi key/tahap serta MIME/ukuran. Mengizinkan tiga key biodata pada validasi awal bukan izin mengirim cabang sembarang; service berikut wajib tetap memvalidasi cabang setelah merge.

## 4. Service dokumen lengkap

Ganti seluruh `app/Services/SidangTaDraftDocumentService.php`:

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
                                private SidangTaDraftAccess $access) {}

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
                $types = Requirements::typesForStep($step, $accurate);
                $files = $validated['documents'] ?? [];
                $fileRules = ['documents' => $types ? ['array:'.implode(',', $types)] : ['prohibited']];
                foreach ($types as $type) $fileRules["documents.{$type}"] = Requirements::fileRules($type);
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

Tidak lagi melempar error hanya karena dokumen wajib belum dipilih. Cabang yang salah tetap ditolak sebelum ada file baru ditulis. Null biodata tidak menghapus cabang lama; pilihan konkret yang disimpan mengganti cabang aktif dan membuang metadata/file khusus draft cabang lama sesudah transaksi sukses. File final pinjaman tidak dihapus.

## 5. Controller: tiga method pengganti

Pada `SidangTaDraftController`, pertahankan constructor, respond(), download, revision, destroy, review dan preview. Ganti tiga method berikut:

```php
public function store(SaveSidangTaDraftStepRequest $request): JsonResponse|RedirectResponse
{
    $draft = $this->service->start($request->user(), $request->validated());
    return $request->expectsJson() ? $this->respond($draft, 201)
        : redirect()->route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 1])
            ->with('success', 'Draft tersimpan. Anda dapat melanjutkan setelah tahap ini lengkap.');
}

public function edit(Request $request, SidangTaRegistrationDraft $draft): JsonResponse|View|RedirectResponse
{
    $this->service->owned($request->user(), $draft);
    if ($request->expectsJson()) return $this->respond($draft);
    $query = $request->validate(['step' => ['nullable', 'integer', 'between:1,4']]);
    $firstIncomplete = SidangTaDocumentRequirements::firstIncompleteStep($draft);
    $maxStep = $firstIncomplete ?? 4;
    $completedThrough = ($firstIncomplete ?? 5) - 1;
    $step = (int) ($query['step'] ?? $maxStep);
    if ($step > $maxStep) {
        return redirect()->route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => $maxStep]);
    }
    $draft->load(['period', 'documents', 'sourceRegistration']);
    $stepProgress = SidangTaDocumentRequirements::progress($draft, $step);
    $student = app(SidangTaStudentContext::class)->studentFor($request->user());
    $openPeriods = SidangTaPeriod::query()->openForRegistration()->get();
    $academic = app(SidangTaAcademicSnapshot::class)->forStudent($student);
    return view('sidang_ta_registrations.wizard', compact(
        'draft', 'student', 'step', 'maxStep', 'completedThrough', 'openPeriods', 'academic', 'stepProgress'
    ));
}

public function saveStep(SaveSidangTaDraftStepRequest $request, SidangTaRegistrationDraft $draft, int $step): JsonResponse|RedirectResponse
{
    $draft = $this->service->saveStep($request->user(), $draft, $step,
        (int) $request->validated('version'), $request->validated());
    if ($request->expectsJson()) return $this->respond($draft);
    $complete = SidangTaDocumentRequirements::complete($draft, $step);
    return redirect()->route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => $step])
        ->with('success', $complete ? 'Draft tersimpan. Tahap ini sudah lengkap.'
            : 'Draft tersimpan. Lengkapi persyaratan tahap ini sebelum melanjutkan.');
}
```

JSON lama dipertahankan, termasuk first_incomplete_step dan daftar dokumen privat. Jangan mengubah service review atau finalizer menjadi memakai draftScalarRules; kelengkapan final tetap wajib.

## 6. Wizard pengganti lengkap

Ganti seluruh `resources/views/sidang_ta_registrations/wizard.blade.php` dengan blok berikut. Targetnya view mahasiswa, bukan file dalam subfolder admin. Partial upload dan panel akademik belum diganti pada S1.

```blade
<x-app-layout>
    <x-slot
        name="header">{{ $draft?->source_registration_id ? 'Draft Revisi Sidang TA' : 'Pendaftaran Sidang TA' }}</x-slot>
    @php
        $payload = $draft?->payload ?? [];
        $rawAccurate = old('diploma_biodata_accurate', $payload['diploma_biodata_accurate'] ?? null);
        $accurate = match ($rawAccurate) {
            true, 1, '1' => '1', false, 0, '0' => '0', default => '',
        };
        $stepProgress = $stepProgress ?? ['complete' => false, 'stored_documents' => 0,
            'required_documents' => 0, 'missing' => []];
        $selectedPeriod = (string) old('sidang_ta_period_id', $draft?->sidang_ta_period_id ?? '');
        $periods = $openPeriods;
        if ($draft && !$periods->contains('id', $draft->sidang_ta_period_id)) {
            $periods = $periods->prepend($draft->period);
        }
        $selected = $periods->first(fn ($period) => (string) $period->id === $selectedPeriod);
        $windowFor = static fn ($period) => $period->open_at->format('d/m/Y H:i').' – '
            .$period->close_at->format('d/m/Y H:i').($period->isOpen() ? '' : ' (ditutup)');
        $initial = ['accurate' => $accurate, 'dirty' => $errors->any(), 'hasErrors' => $errors->any(),
            'periodHint' => $selected ? $windowFor($selected) : ''];
        $permission = $draft?->source_registration_id ? 'sidang_ta_registration_update' : 'sidang_ta_registration_create';
        $titles = [1 => 'Data dasar & kontak', 2 => 'Verifikasi iGracias & akademik',
            3 => 'Pembayaran & dokumen', 4 => 'Biodata ijazah'];
    @endphp
    <div class="mx-auto max-w-7xl space-y-5" data-current-step="{{ $step }}"
         x-data="sidangTaWizard(@js($initial))" @sidang-ta-file-changed="dirty = true"
         @pageshow.window="busy = false"
         @beforeunload.window="if (dirty && !busy) { $event.preventDefault(); $event.returnValue = ''; }">
        @if($errors->any())
            <div data-error-summary tabindex="-1" role="alert"
                 class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-rose-800">
                <p class="text-sm font-bold">Periksa isian berikut:</p>
                <ul class="mt-2 list-inside list-disc space-y-1 text-sm">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <p class="mt-2 text-xs leading-relaxed">Data yang sudah tersimpan tetap tersedia. File pilihan baru yang
                    ditolak perlu dipilih ulang.</p>
            </div>
        @endif
        @if(session('success'))
            <p role="status"
               class="rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</p>
        @endif

        <section class="campus-card overflow-hidden" aria-label="Progres pendaftaran">
            <nav aria-label="Tahap pendaftaran" data-progress-scroll class="overflow-x-auto p-4 sm:p-5">
                <ol class="flex min-w-max items-stretch gap-2">
                    @foreach($titles as $number => $title)
                        @php
                            $reachable = $number <= $maxStep;
                            $state = $number === $step ? 'current' : ($number < $step && $number <= $completedThrough
                                ? 'complete' : ($reachable ? 'available' : 'locked'));
                            $tone = match ($state) {
                                'current' => 'border-[#DA2D3B] bg-[#DA2D3B] text-white',
                                'complete' => 'border-rose-200 bg-rose-50 text-rose-800',
                                'available' => 'border-slate-200 bg-white text-slate-700',
                                default => 'border-slate-200 bg-slate-100 text-slate-400',
                            };
                        @endphp
                        <li class="flex items-center gap-2">
                            @if($draft && $reachable)
                                <a data-step="{{ $number }}" data-state="{{ $state }}" @click="leave($event)"
                                   href="{{ route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => $number]) }}"
                                   @if($number === $step) aria-current="step" @endif
                                   class="block w-40 rounded-xl border px-3 py-3 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2 {{ $tone }}">
                                    <span
                                        class="text-sm font-bold">Tahap {{ $number }}{{ $state === 'complete' ? ' ✓' : '' }}</span>
                                    <span
                                        class="mt-1 block whitespace-normal text-xs leading-relaxed">{{ $title }}</span>
                                </a>
                            @else
                                <span data-step="{{ $number }}" data-state="{{ $state }}"
                                      @if($number === $step) aria-current="step" @else aria-disabled="true" @endif
                                      class="block w-40 rounded-xl border px-3 py-3 {{ $tone }}">
                                    <span class="text-sm font-bold">Tahap {{ $number }}</span>
                                    <span
                                        class="mt-1 block whitespace-normal text-xs leading-relaxed">{{ $title }}</span>
                                </span>
                            @endif
                            @if($number < 4)
                                <span data-chevron="{{ $number }}" aria-hidden="true"
                                      class="px-1 text-xl font-bold {{ $number <= $step ? 'text-[#DA2D3B]' : 'text-slate-300' }}">&raquo;</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>
            <div data-save-status
                 class="space-y-1 border-t border-slate-100 bg-slate-50 px-4 py-3 text-xs leading-relaxed sm:px-5">
                <p class="font-medium text-slate-700">{{ $draft ? 'Terakhir disimpan '.$draft->updated_at->format('d/m/Y H:i').'.' : 'Belum ada data yang disimpan.' }}</p>
                <p class="text-slate-500">Tekan Simpan agar data dan file dapat dilanjutkan setelah halaman ditutup.</p>
                <p role="status" aria-live="polite" class="font-medium text-amber-800"
                   x-text="busy ? 'Sedang menyimpan…' : (dirty ? 'Ada perubahan yang belum disimpan.' : '')"></p>
            </div>
        </section>

        <div class="grid items-start gap-6 xl:grid-cols-12">
            <div class="min-w-0 space-y-5 xl:col-span-8">
                @if($draft?->sourceRegistration?->review_notes)
                    <section class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                        <h2 class="text-sm font-bold text-amber-900">Catatan petugas</h2>
                        <p class="mt-1 whitespace-pre-line text-sm leading-relaxed text-amber-800">{{ $draft->sourceRegistration->review_notes }}</p>
                    </section>
                @endif
                <form id="sidang-ta-step-form" method="POST" novalidate enctype="multipart/form-data"
                      class="campus-card space-y-5 p-5 sm:p-6"
                      action="{{ $draft ? route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => $step]) : route('sidang-ta-drafts.store') }}"
                      @input="dirty = true" @change="dirty = true"
                      @submit="if (busy) { $event.preventDefault(); } else { busy = true; }">
                    @csrf
                    @if($draft)
                        <input type="hidden" name="version" value="{{ old('version', $draft->version) }}">
                    @endif
                    <header class="space-y-1.5 border-b border-slate-100 pb-4">
                        <h2 class="text-lg font-bold text-slate-900">Tahap {{ $step }} — {{ $titles[$step] }}</h2>
                        <p class="text-xs text-slate-500">
                            Isian bertanda <span class="font-bold text-rose-600">*</span> wajib lengkap sebelum lanjut/kirim. Draft boleh disimpan sebelum lengkap.
                        </p>
                    </header>
                    @if($draft && !$stepProgress['complete'])
                        <section data-step-progress role="status" class="space-y-2 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                            <p class="font-semibold">Lengkapi tahap ini sebelum melanjutkan.</p>
                            @if($stepProgress['required_documents'] !== null && $stepProgress['required_documents'] > 0)
                                <p>{{ $stepProgress['stored_documents'] }} dari {{ $stepProgress['required_documents'] }} dokumen tersimpan.</p>
                            @endif
                            <p class="text-xs">Masih perlu dilengkapi:</p>
                            <ul class="list-inside list-disc space-y-1 text-xs leading-relaxed">
                                @foreach($stepProgress['missing'] as $missing)<li>{{ $missing }}</li>@endforeach
                            </ul>
                        </section>
                    @endif
                    @if($step === 1)
                        <div class="max-w-lg">
                            <label for="sidang-ta-period-id" class="mb-1.5 block text-sm font-semibold text-slate-700">
                                Periode Sidang TA <span class="text-rose-600" aria-hidden="true">*</span>
                            </label>
                            @if($draft?->source_registration_id)
                                <input type="hidden" name="sidang_ta_period_id"
                                       value="{{ $draft->sidang_ta_period_id }}">
                                <p id="sidang-ta-period-id" tabindex="-1"
                                   class="rounded-lg bg-slate-50 p-3 text-sm font-medium text-slate-800"
                                   aria-invalid="{{ $errors->has('sidang_ta_period_id') ? 'true' : 'false' }}"
                                   aria-describedby="period-window error-sidang_ta_period_id">{{ $draft->period->name }}
                                    (periode pengajuan asal)</p>
                            @else
                                <select id="sidang-ta-period-id" name="sidang_ta_period_id" required
                                        class="campus-input w-full {{ $errors->has('sidang_ta_period_id') ? '!border-rose-500' : '' }}"
                                        aria-invalid="{{ $errors->has('sidang_ta_period_id') ? 'true' : 'false' }}"
                                        aria-describedby="period-window error-sidang_ta_period_id"
                                        @change="updatePeriodHint($event.target)">
                                    <option value="">Pilih periode terbuka</option>
                                    @foreach($periods as $period)
                                        <option value="{{ $period->id }}"
                                                data-window="{{ $windowFor($period) }}" @selected($selectedPeriod === (string) $period->id)>{{ $period->name }}{{ $period->isOpen() ? '' : ' (ditutup)' }}</option>
                                    @endforeach
                                </select>
                            @endif
                            <p id="period-window" class="mt-1.5 text-xs leading-relaxed text-slate-500"
                               x-text="periodHint || 'Pilih periode yang masih terbuka.'">{{ $initial['periodHint'] ?: 'Pilih periode yang masih terbuka.' }}</p>
                            @include('sidang_ta_registrations.partials.wizard-error', ['field' => 'sidang_ta_period_id'])
                        </div>
                        <div class="grid max-w-xl gap-4 sm:grid-cols-2">
                            <div class="min-w-0">
                                <label for="student-phone" class="mb-1.5 block text-sm font-semibold text-slate-700">Nomor
                                    HP mahasiswa <span class="text-rose-600" aria-hidden="true">*</span></label>
                                <input id="student-phone"
                                       class="campus-input w-full {{ $errors->has('student_phone') ? '!border-rose-500' : '' }}"
                                       name="student_phone" inputmode="tel" maxlength="30" required
                                       placeholder="08xxxxxxxxxx"
                                       aria-invalid="{{ $errors->has('student_phone') ? 'true' : 'false' }}"
                                       aria-describedby="error-student_phone"
                                       value="{{ old('student_phone', $payload['student_phone'] ?? $student->phone_number) }}">
                                @include('sidang_ta_registrations.partials.wizard-error', ['field' => 'student_phone'])
                            </div>
                            <div class="min-w-0">
                                <label for="parent-phone" class="mb-1.5 block text-sm font-semibold text-slate-700">Nomor
                                    HP orang tua/wali <span class="text-rose-600" aria-hidden="true">*</span></label>
                                <input id="parent-phone"
                                       class="campus-input w-full {{ $errors->has('parent_phone') ? '!border-rose-500' : '' }}"
                                       name="parent_phone" inputmode="tel" maxlength="30" required
                                       placeholder="08xxxxxxxxxx"
                                       aria-invalid="{{ $errors->has('parent_phone') ? 'true' : 'false' }}"
                                       aria-describedby="error-parent_phone"
                                       value="{{ old('parent_phone', $payload['parent_phone'] ?? $student->parent_phone_number) }}">
                                @include('sidang_ta_registrations.partials.wizard-error', ['field' => 'parent_phone'])
                            </div>
                        </div>
                    @elseif($step === 2 || $step === 3)
                        @if($step === 3)
                            <div class="max-w-xs">
                                <label for="graduation-payment-date"
                                       class="mb-1.5 block text-sm font-semibold text-slate-700">Tanggal pembayaran
                                    wisuda <span class="text-rose-600" aria-hidden="true">*</span></label>
                                <input id="graduation-payment-date"
                                       class="campus-input w-full {{ $errors->has('graduation_payment_date') ? '!border-rose-500' : '' }}"
                                       type="date" name="graduation_payment_date" required
                                       max="{{ today()->toDateString() }}"
                                       aria-invalid="{{ $errors->has('graduation_payment_date') ? 'true' : 'false' }}"
                                       aria-describedby="error-graduation_payment_date"
                                       value="{{ old('graduation_payment_date', $payload['graduation_payment_date'] ?? '') }}">
                                @include('sidang_ta_registrations.partials.wizard-error', ['field' => 'graduation_payment_date'])
                            </div>
                        @endif
                        <div class="grid gap-4 sm:grid-cols-2">
                            @foreach(\App\Support\SidangTaDocumentRequirements::typesForStep($step) as $type)
                                @include('sidang_ta_registrations.partials.wizard-upload', ['type' => $type, 'conditional' => null])
                            @endforeach
                        </div>
                    @else
                        <fieldset id="diploma-biodata-group" tabindex="-1" aria-required="true"
                                  aria-invalid="{{ $errors->has('diploma_biodata_accurate') ? 'true' : 'false' }}"
                                  aria-describedby="error-diploma_biodata_accurate">
                            <legend class="text-sm font-semibold text-slate-700">Apakah biodata dummy ijazah sudah
                                sesuai? <span class="text-rose-600" aria-hidden="true">*</span></legend>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                @foreach(['1' => 'Sudah sesuai', '0' => 'Belum sesuai, sudah mengajukan PDM'] as $value => $label)
                                    <label
                                        class="flex cursor-pointer items-start gap-3 rounded-xl border p-3 text-sm leading-relaxed"
                                        :class="accurate === '{{ $value }}' ? 'border-rose-300 bg-rose-50 text-rose-900' : 'border-slate-200 text-slate-700'">
                                        <input type="radio" name="diploma_biodata_accurate" value="{{ $value }}"
                                               x-model="accurate" @checked($accurate === (string) $value) required
                                               class="mt-0.5 border-slate-300 text-rose-600 focus:ring-rose-500">
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @include('sidang_ta_registrations.partials.wizard-error', ['field' => 'diploma_biodata_accurate'])
                        </fieldset>
                        <p class="text-xs leading-relaxed text-slate-500">Menyimpan pilihan biodata yang berbeda akan mengganti lampiran cabang draft sebelumnya. Berkas pengajuan final tetap tersimpan.</p>
                        <div x-show="accurate === '1'" x-cloak class="grid gap-4 sm:grid-cols-2">
                            @foreach(\App\Support\SidangTaDocumentRequirements::DIPLOMA as $type)
                                @include('sidang_ta_registrations.partials.wizard-upload', ['type' => $type, 'conditional' => '1'])
                            @endforeach
                        </div>
                        <div x-show="accurate === '0'" x-cloak>
                            @include('sidang_ta_registrations.partials.wizard-upload', ['type' => 'pdm_submission_proof', 'conditional' => '0'])
                        </div>
                    @endif
                    <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                        <a @click="leave($event)" class="btn btn-secondary" href="{{ route('sidang-ta-registrations.index') }}">Kembali ke pendaftaran</a>
                        @can($permission)
                            <div class="flex flex-wrap items-center gap-2">
                                <button data-save-draft type="submit" class="btn btn-secondary" :disabled="busy"
                                        x-text="busy ? 'Sedang menyimpan…' : 'Simpan draft'">Simpan draft</button>
                                @if($step < 4)
                                    @if($draft && $stepProgress['complete'])
                                        <a data-next-step class="btn btn-primary" aria-disabled="false"
                                           href="{{ route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => $step + 1]) }}"
                                           :aria-disabled="dirty || busy ? 'true' : 'false'" :tabindex="dirty || busy ? -1 : 0"
                                           :class="dirty || busy ? 'pointer-events-none opacity-50' : ''"
                                           @click="if (dirty || busy) $event.preventDefault()">Lanjutkan</a>
                                    @else
                                        <button data-next-step type="button" disabled class="btn btn-primary cursor-not-allowed opacity-50">Lanjutkan</button>
                                    @endif
                                @endif
                            </div>
                        @endcan
                    </div>
                </form>
                @if($draft && $step === 4)
                    @can($permission)
                        <form data-review-draft method="POST" class="campus-card space-y-3 p-5"
                              action="{{ route('sidang-ta-drafts.review', $draft) }}"
                              @submit="if (dirty || busy || {{ $stepProgress['complete'] ? 'false' : 'true' }}) { $event.preventDefault(); } else { busy = true; }">
                            @csrf <input type="hidden" name="version" value="{{ $draft->version }}">
                            <p class="text-sm leading-relaxed text-slate-600">Draft harus tersimpan dan seluruh tahap lengkap sebelum ditinjau. Pendaftaran baru dikirim setelah konfirmasi pada halaman tinjau.</p>
                            <button data-review-submit type="submit" class="btn btn-primary"
                                    @disabled(!$stepProgress['complete'])
                                    :disabled="dirty || busy || {{ $stepProgress['complete'] ? 'false' : 'true' }}">Tinjau Sebelum Kirim</button>
                        </form>
                    @endcan
                @endif
            </div>
            @include('sidang_ta_registrations.partials.wizard-academic')
        </div>
    </div>
</x-app-layout>
```

## 7. Kontrak UI dan guard

Simpan tidak mengarahkan otomatis ke tahap berikutnya. Link Lanjutkan hanya dirender untuk tahap tersimpan lengkap; perubahan browser yang belum disimpan menonaktifkannya melalui dirty. Guard server tetap memeriksa prasyarat saat URL dibuka langsung. Pada tahap 4, tombol Tinjau juga memiliki disabled awal dari server dan binding dirty/busy. Field wajib memakai novalidate seperti sebelumnya, sehingga file/isian yang belum lengkap dapat disimpan tanpa popup required HTML.

Model payload null tidak diubah menjadi pilihan PDM oleh Blade. Tidak ada perubahan JavaScript global, endpoint final, route download, atau halaman petugas pada checkpoint ini.

## 8. Test baru lengkap

Isi `tests/Feature/SidangTaPartialDraftTest.php` berikut. `RefreshDatabase` sudah dipasang pada Feature melalui tests/Pest.php. File uji memakai UploadedFile fake seperti suite upload sebelumnya.

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
```

Ada 12 test baru. Dua helper menyelesaikan tahap sebelumnya lewat endpoint nyata, sehingga test partial tidak melompati guard tahap. Fixture revisi menyiapkan status needs_review pada model untuk menguji file pinjaman; ini bukan perubahan workflow petugas.

## 9. Empat test lama yang kebijakannya berubah

Jangan mengganti seluruh dua file test. Ganti empat blok bernama berikut; fixture/helper dan semua test lain dipertahankan. Nama test yang diperbarui juga boleh diganti seperti contoh agar mencerminkan kontrak baru.

### Dua blok pada SidangTaDraftUploadTest.php

```php
test('completes_both_biodata_branches_and_removes_obsolete_files', function () {
    ($this->saveUploadStep)(2, ['documents' => ($this->filesFor)(2)])->assertOk();
    ($this->saveUploadStep)(3, ['documents' => ($this->filesFor)(3)])->assertOk();
    ($this->saveUploadStep)(4, [
        'diploma_biodata_accurate' => true, 'documents' => ($this->filesFor)(4, true),
    ])->assertOk()->assertJsonCount(15, 'data.documents')->assertJsonPath('data.first_incomplete_step', null);
    $oldPaths = $this->draft->documents()->whereIn('document_type', Requirements::DIPLOMA)->pluck('file_path')->all();
    ($this->saveUploadStep)(4, ['diploma_biodata_accurate' => false])->assertOk()
        ->assertJsonCount(13, 'data.documents')->assertJsonPath('data.first_incomplete_step', 4);
    expect($this->draft->fresh()->payload['diploma_biodata_accurate'])->toBeFalse();
    foreach ($oldPaths as $path) Storage::disk('local')->assertMissing($path);
    ($this->saveUploadStep)(4, [
        'diploma_biodata_accurate' => false, 'documents' => ($this->filesFor)(4, false),
    ])->assertOk()->assertJsonCount(14, 'data.documents')->assertJsonPath('data.first_incomplete_step', null);
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
```

Blok kedua menggantikan nama lama `keeps_existing_files_on_missing_requirements_and_stale_version`. Sukses save parsial tetap menambah version walaupun belum ada file tahap berikutnya.

### Dua blok pada SidangTaWizardUiTest.php

```php
test('g1_invalid_biodata_error_is_attached_to_radio_group', function () {
    $draft = ($this->completeDraft)();
    $url = route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 4]);
    $this->from($url)->post(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 4]), [
        'version' => 1, 'diploma_biodata_accurate' => 'invalid',
    ])->assertRedirect($url)->assertSessionHasErrors('diploma_biodata_accurate');
    $response = $this->get($url)->assertOk();
    $xpath = sidangTaUiXpath($response->getContent());
    expect($xpath->query('//fieldset[@id="diploma-biodata-group" and @aria-invalid="true"]')->length)->toBe(1);
    expect($xpath->query('//*[@id="error-diploma_biodata_accurate"]')->item(0)->textContent)
        ->toContain('Pilihan kesesuaian biodata tidak valid.');
    expect($draft->fresh()->version)->toBe(1);
    expect($draft->documents()->count())->toBe(15);
});

test('g1_missing_saved_file_keeps_step_incomplete_and_preserves_other_saved_files', function () {
    $draft = ($this->completeDraft)();
    $document = $draft->documents()->where('document_type', Requirements::CAPTURES[0])->firstOrFail();
    $other = $draft->documents()->where('document_type', Requirements::CAPTURES[1])->firstOrFail();
    Storage::disk('local')->delete($document->file_path);
    $url = route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 2]);
    $this->from($url)->post(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 2]), [
        'version' => 1,
    ])->assertRedirect($url)->assertSessionHasNoErrors();
    $response = $this->get($url)->assertOk();
    $crawler = new \Symfony\Component\DomCrawler\Crawler($response->getContent());
    expect($crawler->filter('[data-step-progress]')->text(null, true))->toContain('5 dari 6 dokumen tersimpan.')
        ->toContain(\App\Models\SidangTaRegistration::DOCUMENT_TYPES[$document->document_type]);
    expect($crawler->filter('button[data-next-step][disabled]')->count())->toBe(1);
    $response->assertSee(route('sidang-ta-draft-documents.download', $other), false)
        ->assertDontSee(route('sidang-ta-draft-documents.download', $document), false);
    expect(Storage::disk('local')->exists($other->file_path))->toBeTrue();
    expect($draft->fresh()->version)->toBe(2);
    expect($draft->documents()->count())->toBe(15);
});
```

Kedua blok menggantikan nama lama `g1_biodata_error_is_attached_to_radio_group` dan `g1_missing_saved_file_shows_its_inline_error_and_preserves_other_saved_files`. Test biodata tetap menguji error inline, sekarang dengan nilai yang benar-benar invalid. Omitted choice pada draft yang sudah lengkap harus mempertahankan nilai lama, bukan memunculkan required error.

Test tanggal masa depan, unknown key/ukuran/MIME, storage failure, owner, source/version, pengajuan final dan endpoint download tetap seperti sebelumnya. Total definisi PHP TA setelah menambah 12 dan mengganti empat blok tanpa menduplikasi adalah **110**.

## 10. Command verifikasi

```bash
php artisan config:clear
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaPartialDraftTest.php tests/Feature/SidangTaDraftUploadTest.php tests/Feature/SidangTaWizardUiTest.php
```

Setelah fokus lulus:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaDraftSchemaTest.php tests/Feature/SidangTaPeriodTest.php tests/Feature/SidangTaDraftTest.php tests/Feature/SidangTaDraftUploadTest.php tests/Feature/SidangTaRevisionDraftTest.php tests/Feature/SidangTaWizardTest.php tests/Feature/SidangTaFinalizationTest.php tests/Feature/SidangTaWizardUiTest.php tests/Feature/SidangTaDraftCardTest.php tests/Feature/SidangTaReviewUiTest.php tests/Feature/SidangTaDetailUiTest.php tests/Feature/SidangTaPartialDraftTest.php
node tests/Js/sidang-ta-entry.test.mjs
node tests/Js/sidang-ta-wizard.test.mjs
npm run build
```

Target: **110 definisi test PHP TA**, **9 test Node**, build berhasil. Gunakan MySQL Podman schema dadu_testing, bukan database aplikasi. Tidak memerlukan phpunit.xml diubah permanen atau menjalankan migrate baru.

## 11. Checklist browser S1

- [ ] Start pendaftaran valid: save kembali tahap 1 dan membuat draft. Tombol Lanjutkan baru aktif sesudah data tersimpan; membuka form awal saja belum membuat draft.
- [ ] Tahap 2 pilih satu capture, tekan Simpan draft: nama masuk Berkas tersimpan, tetap tahap 2, tampil 1 dari 6 dan daftar kekurangan. Lanjutkan disabled. Tutup/buka halaman: file tetap ada.
- [ ] Tambahkan file berikutnya pada beberapa save. File sebelumnya tidak perlu dipilih ulang. Setelah enam lengkap dan tersimpan, Lanjutkan aktif menuju tahap 3.
- [ ] Saat semua file tersimpan lengkap, pilih pengganti/ubah isian tanpa save: Lanjutkan menjadi disabled sampai save. Tombol tahap dan konfirmasi leave tetap menjaga perubahan belum tersimpan.
- [ ] Tahap 3: simpan satu PDF tanpa tanggal. File tersimpan, tanggal masih belum lengkap dan tahap 4 terkunci. Lengkapi tanggal serta sisa file, save, baru lanjut.
- [ ] Tahap 4: Simpan draft tanpa pilihan tidak otomatis memilih PDM. Pilih Sesuai, simpan satu dari dua PDF: belum dapat Tinjau. Tambah PDF kedua dan save: baru dapat Tinjau.
- [ ] Ganti cabang ke PDM dan save tanpa file: pilihan tersimpan tetapi belum dapat tinjau. Lampiran cabang draft sebelumnya diganti sesuai peringatan; pada revisi, dokumen final tetap aman. Tambahkan PDM dan save sebelum tinjau.
- [ ] File invalid/terlalu besar atau tanggal masa depan tetap menghasilkan error inline dan tidak merusak progres yang telah tersimpan. Pilihan baru yang ditolak perlu dipilih ulang.
- [ ] Uji versi lama dua tab: stale ditolak dan tidak mengganti file/tab terbaru. Direct URL tahap depan tetap dikembalikan ke tahap yang belum lengkap.
- [ ] Tinjau/Kirim final tidak dapat dilakukan saat belum lengkap. Sesudah lengkap, alur konfirmasi final dan revisi sebelumnya tetap bekerja.

## 12. Berkas untuk rescan dan tahap berikutnya

Setelah test/browser berhasil, salin seluruh file pada bagian 1 beserta test baru ke dadu-test. Tidak memindahkan .env, phpunit.xml sementara, database, cache/build atau dependencies. Minta rescan S1 sebelum S2.

Kebutuhan menghapus tombol Unduh draft, kalimat bantuan dan mengganti header index tetap dicatat sebagai S2, lalu P1–P3 untuk petugas mengikuti [31](31-rancangan-draft-parsial-dan-review-petugas-ta.md). Jangan menganggap kemampuan verifikasi dokumen petugas sudah tersedia pada S1 ini.

## 13. Hasil pemeriksaan panduan oleh asisten

- Source kedua folder dipindai sebelum dan sesudah penulisan panduan; file aplikasi tidak diubah.
- Tujuh blok PHP lulus pemeriksaan sintaks. Blade dikompilasi di memori tanpa component tags lalu hasil PHP lulus pemeriksaan sintaks; ini belum menguji render komponen aplikasi.
- Ekspresi Alpine pada wizard lulus pemeriksaan sintaks JavaScript. Probe di memori dengan relation/storage stub memeriksa progress kosong/lengkap/file hilang, pilihan null dibanding false, serta aturan draft dibanding aturan lengkap; tidak memakai koneksi database.
- Sembilan test Node existing lulus. Review independen statis tidak menemukan blocker pada snippet produksi dan test pengganti/baru.
- Suite Feature PHP, build, dan checklist browser pada bagian 10–11 **belum dijalankan untuk implementasi S1**. Jalankan setelah menerapkan kode pada project asli, sebelum menyatakan checkpoint lulus atau menyalin ke dadu-test.

Referensi Context7: [Laravel 13 validation](https://github.com/laravel/docs/blob/13.x/validation.md), khusus nullable/sometimes dan whitelist key array.
