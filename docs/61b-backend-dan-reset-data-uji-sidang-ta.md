# 61b — Backend, schema default, dan reset data uji

Isi lengkap18 file backend/migration/command. Pastikan jalankan dry-run reset lebih dahulu; command tidak otomatis membersihkan DB saat deploy.

### `app/Console/Commands/ResetSidangTaTestData.php`

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ResetSidangTaTestData extends Command
{
    protected $signature = 'sidang-ta:reset-test-data {--execute : Delete all TA test registrations, drafts, documents and logs}';

    protected $description = 'Preview or explicitly reset ALL Sidang TA test submissions, preserving accounts, academic data and periods';

    /** Child-first order preserves foreign-key constraints. */
    private const TABLES = [
        'sidang_ta_registration_draft_documents',
        'sidang_ta_registration_drafts',
        'sidang_ta_registration_documents',
        'sidang_ta_registration_logs',
        'sidang_ta_registrations',
    ];

    public function handle(): int
    {
        $this->line('Database: '.DB::connection()->getDatabaseName());
        $this->warn('Stop web writes, queue workers and scheduled TA jobs before executing this maintenance reset.');
        $this->line('Preserved: users, students, academic/sync data, other modules, roles and Sidang TA periods.');

        if (! $this->option('execute')) {
            $this->info('DRY RUN: no database or file changes. Add --execute to reset ALL TA submissions.');
            $this->summary($this->snapshot(false));

            return self::SUCCESS;
        }

        try {
            $snapshot = DB::transaction(function () {
                // Match the application's Student -> Draft -> Registration lock order.
                DB::table('students')->orderBy('id')->lockForUpdate()->pluck('id');
                $snapshot = $this->snapshot(true);
                $this->summary($snapshot);
                foreach (self::TABLES as $table) {
                    // Delete the locked snapshot only; never truncate or reset sequences.
                    foreach (array_chunk($snapshot['ids'][$table], 500) as $ids) {
                        DB::table($table)->whereIn('id', $ids)->delete();
                    }
                }

                return $snapshot;
            });
        } catch (Throwable $error) {
            $this->error('Reset rolled back; no file cleanup was attempted: '.$error->getMessage());

            return self::FAILURE;
        }

        // The metadata transaction must succeed before any filesystem deletion.
        $deleted = 0;
        $skipped = 0;
        foreach ($snapshot['paths'] as $path) {
            try {
                if (! $this->ownedLocalFile($path) || $this->referenced($path)) {
                    $this->skip($path, 'unsafe, missing, non-local or still referenced');
                    $skipped++;
                    continue;
                }
                if (! Storage::disk('local')->delete($path)) {
                    $this->skip($path, 'storage refused deletion');
                    $skipped++;
                    continue;
                }
                $deleted++;
            } catch (Throwable $error) {
                $this->skip($path, $error->getMessage());
                $skipped++;
            }
        }
        $this->info("TA metadata reset complete. Files deleted: {$deleted}; skipped: {$skipped}.");

        return self::SUCCESS;
    }

    private function snapshot(bool $lock): array
    {
        $ids = [];
        $rows = [];
        foreach (['sidang_ta_registration_drafts', 'sidang_ta_registrations',
            'sidang_ta_registration_draft_documents', 'sidang_ta_registration_documents',
            'sidang_ta_registration_logs'] as $table) {
            $query = DB::table($table)->orderBy('id');
            $rows[$table] = ($lock ? $query->lockForUpdate() : $query)->get();
            $ids[$table] = $rows[$table]->pluck('id')->all();
        }
        $paths = $rows['sidang_ta_registration_draft_documents']->pluck('file_path')
            ->merge($rows['sidang_ta_registration_documents']->pluck('file_path'))->unique()->values()->all();

        return compact('ids', 'paths');
    }

    private function summary(array $snapshot): void
    {
        foreach (self::TABLES as $table) {
            $this->line($table.': '.count($snapshot['ids'][$table]));
        }
        $this->line('Files referenced (unique): '.count($snapshot['paths']));
    }

    private function ownedLocalFile(string $path): bool
    {
        if (config('filesystems.disks.local.driver') !== 'local'
            || ! preg_match('~\Asidang_ta/(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9_-]+(?:\.[A-Za-z0-9_-]+)*\z~D', $path)) {
            return false;
        }
        $root = realpath(Storage::disk('local')->path(''));
        $resolved = realpath(Storage::disk('local')->path($path));
        if ($root === false || $resolved === false || ! is_file($resolved)
            || ! str_starts_with($resolved, $root.DIRECTORY_SEPARATOR.'sidang_ta'.DIRECTORY_SEPARATOR)) {
            return false;
        }
        // Do not follow even internal symlinks: they can alias an unrelated module's file.
        $current = $root;
        foreach (explode('/', $path) as $part) {
            $current .= DIRECTORY_SEPARATOR.$part;
            if (is_link($current)) {
                return false;
            }
        }

        return true;
    }

    private function referenced(string $path): bool
    {
        // Raw queries include soft-deleted documents. Inspect all path columns so
        // graduation predicate_document_path and grade-appeal evidence are guarded too.
        $disk = Storage::disk('local');
        $target = realpath($disk->path($path));
        foreach (Schema::getTableListing() as $table) {
            foreach (Schema::getColumnListing($table) as $column) {
                if (! str_contains($column, 'path')) {
                    continue;
                }
                if (DB::table($table)->where($column, $path)->exists()) {
                    return true;
                }
                // A retained path may use ./ or another spelling of the same file.
                foreach (DB::table($table)->whereNotNull($column)->select($column)->cursor() as $row) {
                    $other = $row->{$column};
                    if (is_string($other) && $other !== '' && $target !== false
                        && realpath($disk->path($other)) === $target) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function skip(string $path, string $reason): void
    {
        $this->warn('File preserved: '.$path.' ('.$reason.').');
        Log::warning('Sidang TA reset preserved file', ['path' => $path, 'reason' => $reason]);
    }
}
```

### `app/Http/Controllers/SidangTaDraftController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveSidangTaDraftStepRequest;
use App\Models\SidangTaRegistrationDraft;
use App\Models\SidangTaRegistration;
use App\Services\SidangTaAcademicSnapshot;
use App\Services\SidangTaRevisionDraftService;
use App\Services\SidangTaDraftService;
use App\Models\SidangTaPeriod;
use App\Models\Student;
use App\Services\SidangTaStudentContext;
use App\Services\SidangTaDraftReviewService;
use App\Models\SidangTaRegistrationDraftDocument;
use App\Support\SidangTaDocumentRequirements;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class SidangTaDraftController extends Controller
{
    public function __construct(private SidangTaDraftService $service,
                                private SidangTaRevisionDraftService $revisions,
                                private SidangTaDraftReviewService $reviews
    ) {}

    private function respond(SidangTaRegistrationDraft $draft, int $status = 200): JsonResponse
    {
        $draft->loadMissing('documents');
        return response()->json(['data' => [
            'id' => $draft->id,
            'sidang_ta_period_id' => $draft->sidang_ta_period_id,
            'payload' => $draft->payload,
            'version' => $draft->version,
            'status' => $draft->status,
            'first_incomplete_step' => SidangTaDocumentRequirements::firstIncompleteStep($draft),
            'documents' => $draft->documents->sortBy('document_type')->values()->map(fn ($document) => [
                'id' => $document->id, 'document_type' => $document->document_type,
                'file_name' => $document->file_name, 'file_size' => $document->file_size,
                'download_url' => route('sidang-ta-draft-documents.download', $document),
            ])->all(),
            'source_registration_id' => $draft->source_registration_id,
            'source_version' => $draft->source_version,
        ]], $status);
    }

    public function store(SaveSidangTaDraftStepRequest $request): JsonResponse|RedirectResponse
    {
        $draft = $this->service->start($request->user(), $request->validated());
        return $request->expectsJson()
            ? $this->respond($draft, 201)
            : redirect()->route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 1])
                ->with('success', 'Draft tersimpan. Anda dapat melanjutkan setelah tahap ini lengkap.');
    }

    private function meetContext(SidangTaRegistrationDraft $draft, Student $student): array
    {
        SidangTaDocumentRequirements::assertKnownVersion($draft);
        $admission = app(\App\Services\SidangTaAdmissionFacts::class)->forStudent($student);
        $targeted = \App\Support\SidangTaRevisionContract::isTargeted($draft);
        $revisionExpired = false;
        if ($targeted) {
            $source = \App\Support\SidangTaRevisionContract::source($draft);
            $revisionExpired = \App\Support\SidangTaRevisionDeadline::hasExpired($source);
            $snapshot = $source->academic_snapshot ?? [];
            $stored = $snapshot['admission'] ?? [];
            $storedMode = $stored['eprt']['mode'] ?? null;
            abort_unless(in_array($storedMode, ['automatic', 'waiver'], true), 409,
                'Persyaratan EPRT pengajuan asal belum lengkap.');
            $admission['eprt'] = ['mode' => $storedMode, 'requires_waiver' => $storedMode === 'waiver',
                'message' => $storedMode === 'waiver' ? 'Surat Permakluman mengikuti pengajuan asal.' : 'EPRT memenuhi syarat saat pengajuan asal.'];
            $admission['eprt_facts'] = ['score' => $stored['eprt_facts']['score'] ?? $snapshot['eprt_score'] ?? null,
                'confirmed_attempts' => $stored['eprt_facts']['confirmed_attempts'] ?? null];
        }
        $mode = $admission['eprt']['mode'];
        $facts = $admission['facts'];
        $level = match ($facts['level_2_passed']) { true => 'Lulus', false => 'Belum lulus', default => 'Belum tersedia' };
        $skpi = match ($facts['skpi_approved']) { true => 'Disetujui', false => 'Belum memenuhi', default => 'Belum tersedia' };
        $krs = match ($admission['krs_status']) { 'eligible' => 'Terdaftar', 'unconfirmed' => 'Belum terkonfirmasi', default => 'Belum tersedia' };
        $payment = strtolower(trim((string) ($student->bppPayment()->value('status') ?? '')));
        $payment = match ($payment) { 'paid', 'lunas' => 'Lunas', 'unpaid', 'belum lunas', 'belum_lunas' => 'Belum lunas', default => 'Belum tersedia' };
        $score = $admission['eprt_facts']['score'];
        $attempts = $admission['eprt_facts']['confirmed_attempts'];
        $payload = $draft->payload ?? [];
        $choice = $payload['diploma_biodata_accurate'] ?? null;
        $accurate = $choice === null ? null : (bool) $choice;
        $types = [];
        foreach ([2, 3, 4] as $step) {
            $types[$step] = $targeted ? \App\Support\SidangTaRevisionContract::typesForStep($draft, $step)
                : \App\Support\SidangTaMeetRequirements::typesForStep($step, $accurate, $mode);
        }
        return [
            'admission' => $admission, 'types' => $types, 'targeted' => $targeted,
            'revision_expired' => $revisionExpired,
            'editable_types' => $targeted ? \App\Support\SidangTaRevisionContract::editableTypes($draft) : [],
            'can_submit' => !$revisionExpired && ($draft->source_registration_id === null || $targeted)
                && $admission['academic']['academic_passed']
                && in_array($mode, ['automatic', 'waiver'], true),
            'academic' => ['sidang_tingkat_2' => $level, 'skpi_status' => $skpi, 'ta2_krs_label' => $krs,
                'tak_score' => $facts['tak_score'], 'passed_credits' => $facts['passed_credits'], 'eprt_score' => $score],
            'automatic' => [
                2 => [
                    ['key' => 'level_2', 'title' => 'Hasil sidang tingkat 2', 'value' => $level, 'hint' => 'Tidak memerlukan unggahan.'],
                    ['key' => 'sidang_approval', 'title' => 'Persetujuan sidang iGracias', 'value' => 'Belum tersedia', 'hint' => 'Persetujuan sidang belum tercatat.'],
                ],
                3 => [
                    ['key' => 'bpp', 'title' => 'BPP semester berjalan', 'value' => $payment,
                        'hint' => 'Periode pembayaran belum terkonfirmasi. Tidak memerlukan unggahan.'],
                    ['key' => 'eprt', 'title' => $targeted ? 'EPRT pengajuan asal' : 'EPRT', 'value' => $score === null ? 'Belum tersedia' : 'Nilai '.$score,
                        'hint' => $admission['eprt']['message'].($attempts === null ? '' : ' '.$attempts.' tes tercatat.')],
                ],
            ],
        ];
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
        $meetContext = $this->meetContext($draft, $student);
        $academic = array_replace($academic, $meetContext['academic']);
        return view('sidang_ta_registrations.wizard', compact(
            'draft', 'student', 'step', 'maxStep', 'completedThrough', 'openPeriods', 'academic', 'stepProgress', 'meetContext'
        ));
    }

    public function saveStep(SaveSidangTaDraftStepRequest $request, SidangTaRegistrationDraft $draft, int $step): JsonResponse|RedirectResponse
    {
        $draft = $this->service->saveStep($request->user(), $draft, $step,
            (int) $request->validated('version'), $request->validated());
        if ($request->expectsJson()) return $this->respond($draft);
        $complete = SidangTaDocumentRequirements::complete($draft, $step);
        return redirect()->route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => $step])
            ->with('success', $complete
                ? 'Draft tersimpan. Tahap ini sudah lengkap.'
                : 'Draft tersimpan. Lengkapi persyaratan tahap ini sebelum melanjutkan.'
            );
    }

    public function previewDocument(Request $request, SidangTaRegistrationDraftDocument $document)
    {
        $this->service->owned($request->user(), $document->draft);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($document->file_path), 404);
        $mime = $disk->mimeType($document->file_path);
        abort_unless(in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true), 415);
        return $disk->response($document->file_path, $document->file_name, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ], 'inline');
    }

    public function downloadDocument(Request $request, SidangTaRegistrationDraftDocument $document)
    {
        $this->service->owned($request->user(), $document->draft);
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->download($document->file_path, $document->file_name);
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
        $meetContext = $this->meetContext($draft, $student);
        $documentGroups = $meetContext['types'];
        // Use one resolved set in the view. A mode change after service validation must not cause null dereferences.
        foreach ($documentGroups as $types) {
            foreach ($types as $type) {
                $document = $draft->documents->firstWhere('document_type', $type);
                abort_unless($document && Storage::disk('local')->exists($document->file_path), 409,
                    'Persyaratan atau berkas berubah. Buka form dan tinjau ulang.');
            }
        }
        return view('sidang_ta_registrations.wizard-review', compact('draft', 'student', 'meetContext', 'documentGroups'));
    }
}
```

### `app/Http/Controllers/SidangTaRegistrationController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSidangTaRegistrationRequest;
use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDocument;
use App\Models\SidangTaRegistrationDraft;
use App\Services\SidangTaAcademicSnapshot;
use App\Services\SidangTaReviewExport;
use App\Services\SidangTaReviewQuery;
use App\Services\SidangTaStudentContext;
use App\Services\SidangTaDraftFinalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class SidangTaRegistrationController extends Controller
{
    public function index()
    {
        $student = app(SidangTaStudentContext::class)->studentFor(auth()->user());
        $number = $student->student_number;
        $registrations = SidangTaRegistration::with('period')->where('student_number', $number)->latest()->paginate(10);
        $openPeriods = SidangTaPeriod::query()->openForRegistration()->get();
        $drafts = SidangTaRegistrationDraft::query()->ownedBy(auth()->user())
            ->where('student_number', $number)->where('status', 'active')
            ->with(['period', 'sourceRegistration'])->latest()->get();
        $activeRegistration = SidangTaRegistration::where('student_number', $number)
            ->whereIn('status', ['submitted', 'needs_review'])->latest()->first();
        foreach ($registrations as $registration) {
            \App\Support\SidangTaReviewEligibility::requiredTypes($registration);
        }
        foreach ($drafts as $draft) {
            \App\Support\SidangTaDocumentRequirements::assertKnownVersion($draft);
        }
        return view('sidang_ta_registrations.index', compact('registrations', 'openPeriods', 'drafts', 'activeRegistration'));
    }

    public function create()
    {
        $student = app(SidangTaStudentContext::class)->studentFor(auth()->user());
        $active = SidangTaRegistration::where('student_number', $student->student_number)
            ->whereIn('status', ['submitted', 'needs_review'])->latest()->first();
        if ($active) {
            \App\Support\SidangTaReviewEligibility::requiredTypes($active);
            return redirect()->route($active->status === 'needs_review'
                ? 'sidang-ta-registrations.edit' : 'sidang-ta-registrations.show', $active);
        }
        $existing = SidangTaRegistrationDraft::query()->ownedBy(auth()->user())
            ->where('student_number', $student->student_number)->where('status', 'active')
            ->whereNull('source_registration_id')->first();
        if ($existing) {
            \App\Support\SidangTaDocumentRequirements::assertKnownVersion($existing);
            return redirect()->route('sidang-ta-drafts.edit', $existing);
        }
        $openPeriods = SidangTaPeriod::query()->openForRegistration()->get();
        $academic = app(SidangTaAcademicSnapshot::class)->forStudent($student);
        $admission = app(\App\Services\SidangTaAdmissionFacts::class)->forStudent($student);
        $facts = $admission['facts'];
        $academic = array_replace($academic, [
            'sidang_tingkat_2' => match ($facts['level_2_passed']) {
                true => 'Lulus', false => 'Belum lulus', default => 'Belum tersedia',
            },
            'skpi_status' => match ($facts['skpi_approved']) {
                true => 'Disetujui', false => 'Belum memenuhi', default => 'Belum tersedia',
            },
            'ta2_krs_label' => match ($admission['krs_status']) {
                'eligible' => 'Terdaftar', 'unconfirmed' => 'Belum terkonfirmasi', default => 'Belum tersedia',
            },
            'tak_score' => $facts['tak_score'], 'passed_credits' => $facts['passed_credits'],
            'eprt_score' => $admission['eprt_facts']['score'],
        ]);
        $meetContext = ['targeted' => false, 'revision_expired' => false];
        $draft = null;
        $step = $maxStep = 1;
        $completedThrough = 0;
        return view('sidang_ta_registrations.wizard', compact(
            'draft', 'student', 'step', 'maxStep', 'completedThrough', 'openPeriods', 'academic', 'meetContext'
        ));
    }

    public function store(StoreSidangTaRegistrationRequest $request)
    {
        return $this->finalizeDraft($request);
    }

    public function show(SidangTaRegistration $sidang_ta_registration)
    {
        $registration = $sidang_ta_registration->load(['period', 'documents']);
        $registration->unsetRelation('logs');
        $this->authorizeStudentOrReviewer($registration);
        $user = auth()->user();
        $canRevise = false;
        if ($registration->status === 'needs_review' && $user->can('sidang_ta_registration_update')) {
            try {
                $student = app(SidangTaStudentContext::class)->studentFor($user);
                $canRevise = $registration->student_number === $student->student_number;
            } catch (ValidationException $exception) {
                // Reviewer dapat membaca detail walaupun tidak terhubung dengan Student.
                $canRevise = false;
            }
        }
        return view('sidang_ta_registrations.show', compact('registration', 'canRevise'));
    }

    public function edit(SidangTaRegistration $sidang_ta_registration)
    {
        $student = app(SidangTaStudentContext::class)->studentFor(auth()->user());
        $registration = $sidang_ta_registration;
        abort_unless($registration->student_number === $student->student_number
            && $registration->status === 'needs_review', 403);
        \App\Support\SidangTaReviewEligibility::requiredTypes($registration);
        return view('sidang_ta_registrations.revision-start', compact('registration'));
    }

    public function update(StoreSidangTaRegistrationRequest $request, SidangTaRegistration $sidang_ta_registration)
    {
        return $this->finalizeDraft($request, $sidang_ta_registration);
    }

    public function adminIndex(Request $request)
    {
        $canReview = $request->user()->can('sidang_ta_registration_review');
        $periods = SidangTaPeriod::orderByDesc('open_at')->orderByDesc('id')->get();
        $registrations = null;
        $filters = [];
        $counts = null;
        $studyPrograms = collect();
        $periodCards = collect();
        if ($canReview) {
            $reviewQuery = app(SidangTaReviewQuery::class);
            $filters = $reviewQuery->filters($request);
            $counts = $reviewQuery->counts($filters);
            $studyPrograms = SidangTaRegistration::query()->whereNotNull('study_program')
                ->where('study_program', '!=', '')->distinct()->orderBy('study_program')->pluck('study_program');
            $registrations = $reviewQuery->query($filters)->with(['period', 'documents'])
                ->orderByDesc('submitted_at')->orderByDesc('id')->paginate(15)->appends($filters);
            $periodCards = isset($filters['period'])
                ? $periods->where('id', $filters['period'])->values()
                : $periods->where('is_active', true)->values();
        }
        return view('sidang_ta_registrations.admin.index', compact(
            'canReview', 'registrations', 'periods', 'filters', 'counts', 'studyPrograms', 'periodCards'
        ));
    }

    public function adminShow(SidangTaRegistration $sidang_ta_registration)
    {
        $registration = $sidang_ta_registration->load(['period', 'documents', 'logs']);
        $needsReview = $registration->status === 'needs_review';
        $readonlyNeedsReview = $needsReview;
        $reviewable = $registration->status === 'submitted';
        $requiredTypes = \App\Support\SidangTaReviewEligibility::requiredTypes($registration);
        $approvalBlockers = \App\Support\SidangTaReviewEligibility::blockers($registration);
        $documentGroups = \App\Support\SidangTaReviewEligibility::groups($registration);
        $revisionBlockers = [];
        if ($reviewable) {
            try {
                if (\App\Support\SidangTaRevisionDeadline::hasExpired($registration)) {
                    throw ValidationException::withMessages(['action' => \App\Support\SidangTaRevisionDeadline::MESSAGE]);
                }
                \App\Support\SidangTaRevisionContract::assertCanRequest($registration);
            } catch (ValidationException $exception) {
                $revisionBlockers = array_values(array_merge(...array_values($exception->errors())));
            }
        }
        return view('sidang_ta_registrations.admin.show', compact(
            'registration', 'reviewable', 'needsReview', 'readonlyNeedsReview', 'requiredTypes', 'approvalBlockers', 'documentGroups', 'revisionBlockers'
        ));
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
        $returnUrl = route('admin.sidang-ta-registrations.show', $sidang_ta_registration)
            .'#review-document-'.$document->id;
        try {
            $validated = $request->validate([
                'status' => ['required', 'in:verified,rejected'],
                'notes' => ['required_if:status,rejected', 'nullable', 'string', 'max:2000'],
                'registration_version' => ['required', 'integer', 'min:1'],
            ], ['notes.required_if' => 'Isi catatan perbaikan dokumen.']);
            $registration = app(\App\Services\SidangTaRegistrationReview::class)->document(
                $request->user(), $sidang_ta_registration, $document, (int) $validated['registration_version'],
                $validated['status'], $validated['notes'] ?? null
            );
        } catch (ValidationException $exception) {
            if (!$request->expectsJson()) $exception->redirectTo($returnUrl);
            throw $exception;
        } catch (HttpExceptionInterface $exception) {
            if ($request->expectsJson() || $exception->getStatusCode() !== 409) throw $exception;
            return redirect()->to($returnUrl)
                ->withErrors(['registration_version' => $exception->getMessage()])->withInput();
        }
        if ($request->expectsJson()) {
            return response()->json(['data' => ['id' => $registration->id,
                'status' => $registration->status, 'lock_version' => $registration->lock_version]]);
        }
        return redirect()->to($returnUrl)->with('success', 'Review dokumen berhasil disimpan.');
    }


    public function managePeriods()
    {
        $periods = SidangTaPeriod::latest()->get();
        return view('sidang_ta_registrations.admin.periods', compact('periods'));
    }

    public function storePeriod(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'open_at' => ['required', 'date'],
            'close_at' => ['required', 'date', 'after:open_at'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        SidangTaPeriod::create($data + ['is_active' => $request->boolean('is_active')]);
        return back()->with('success', 'Periode Sidang TA berhasil dibuat.');
    }

    public function updatePeriod(Request $request, SidangTaPeriod $period)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'open_at' => ['required', 'date'],
            'close_at' => ['required', 'date', 'after:open_at'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        $period->update($data + ['is_active' => $request->boolean('is_active')]);
        return back()->with('success', 'Periode Sidang TA berhasil diperbarui.');
    }

    public function setActivePeriod(SidangTaPeriod $period)
    {
        $period->update(['is_active' => true]);
        return back()->with('success', 'Periode Sidang TA berhasil diaktifkan.');
    }

    public function deletePeriod(SidangTaPeriod $period)
    {
        abort_if(
            $period->registrations()->exists() || $period->drafts()->exists(),
            409,
            'Periode yang memiliki pendaftaran atau draft tidak dapat dihapus.'
        );
        $period->delete();

        return back()->with('success', 'Periode Sidang TA berhasil dihapus.');
    }

    public function previewDocument(SidangTaRegistrationDocument $document)
    {
        $this->authorizeStudentOrReviewer($document->registration);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($document->file_path), 404);
        $mime = $disk->mimeType($document->file_path);
        abort_unless(in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true), 415);
        return $disk->response($document->file_path, $document->file_name, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ], 'inline');
    }

    public function downloadDocument(SidangTaRegistrationDocument $document)
    {
        $registration = $document->registration;
        $this->authorizeStudentOrReviewer($registration);
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);
        return Storage::disk('local')->download($document->file_path, $document->file_name);
    }

    private function authorizeStudentOrReviewer(SidangTaRegistration $registration): void
    {
        $user = auth()->user();
        if ($user->can('sidang_ta_registration_review')) {
            \App\Support\SidangTaReviewEligibility::requiredTypes($registration);
            return;
        }
        try {
            $student = app(SidangTaStudentContext::class)->studentFor($user);
        } catch (ValidationException $exception) {
            abort(403);
        }
        abort_unless($registration->student_number === $student->student_number, 403);
        \App\Support\SidangTaReviewEligibility::requiredTypes($registration);
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

    public function exportExcel(Request $request)
    {
        $query = app(SidangTaReviewQuery::class);
        $filters = $query->filters($request);
        $registrations = $query->query($filters)->with(['period', 'documents'])
            ->orderByDesc('submitted_at')->orderByDesc('id')->get();
        return app(SidangTaReviewExport::class)->download($registrations);
    }
}
```

### `app/Http/Requests/SaveSidangTaDraftStepRequest.php`

```php
<?php

namespace App\Http\Requests;

use App\Models\SidangTaRegistration;
use App\Support\SidangTaMeetRequirements;
use Illuminate\Contracts\Validation\ValidationRule;
use App\Support\SidangTaDocumentRequirements as Requirements;
use App\Support\SidangTaRevisionContract as Revision;
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
        $types = $step === 4
            ? array_merge(SidangTaMeetRequirements::DIPLOMA, SidangTaMeetRequirements::PDM)
            : ($draft ? Requirements::typesForDraftStep($draft, $step) : Requirements::typesForStep($step));
        if ($draft && Revision::isTargeted($draft)) {
            $types = array_values(array_intersect(Requirements::typesForDraftStep($draft, $step), Revision::editableTypes($draft)));
        }
        $rules['documents'] = $types ? ['nullable', 'array:'.implode(',', $types)] : ['prohibited'];
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
        foreach (array_merge(SidangTaRegistration::DOCUMENT_TYPES,
            SidangTaMeetRequirements::LABELS) as $type => $label) {
            $attributes["documents.{$type}"] = $label;
        }
        return $attributes;
    }
}
```

### `app/Models/SidangTaRegistration.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SidangTaRegistration extends Model
{
    public const DOCUMENT_TYPES = \App\Support\SidangTaMeetRequirements::LABELS;

    public const DOCUMENT_DESCRIPTIONS = [
        'ta_data_capture' => 'Ambil dari menu TA/PA di iGracias.',
        'online_supervision_capture' => 'Tampilkan bimbingan online yang sudah disetujui.',
        'graduation_payment_proof' => 'Unggah bukti pembayaran wisuda dalam format PDF.',
        'ta_supervisor_sk' => 'Unggah SK pembimbing TA yang masih aktif dalam format PDF.',
        'thesis_draft' => 'PDF tanpa tanda tangan; logo dan tahun mengikuti template.',
        'eprt_waiver_letter' => 'Lengkapi Surat Permakluman EPRT lalu unggah dalam format PDF.',
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
        'requirements_version' => 2,
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

### `app/Models/SidangTaRegistrationDraft.php`

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
        'requirements_version' => 2,
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

### `app/Services/SidangTaDraftAccess.php`

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
        \App\Support\SidangTaDocumentRequirements::assertKnownVersion($draft);
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
        abort_unless($draft->requirements_version === $source->requirements_version, 409,
            'Versi persyaratan draft tidak cocok dengan pengajuan asal. Muat ulang pengajuan.');
        abort_unless($source->status === 'needs_review'
            && $source->lock_version === $draft->source_version, 409,
            'Pengajuan berubah. Buang draft revisi lama lalu muat ulang pengajuan.');

        if ($write) \App\Support\SidangTaRevisionDeadline::ensureOpen($source);

        foreach ($draft->documents()->whereNotNull('source_document_id')->get() as $document) {
            $valid = $source->documents()->whereKey($document->source_document_id)
                ->where('document_type', $document->document_type)
                ->where('file_path', $document->file_path)->exists();
            abort_unless($valid, 409, 'Referensi dokumen pengajuan sudah berubah.');
        }
    }
}
```

### `app/Services/SidangTaDraftDocumentService.php`

```php
<?php

namespace App\Services;

use App\Models\SidangTaRegistrationDraft;
use App\Models\Student;
use App\Models\User;
use App\Support\SidangTaDocumentRequirements as Requirements;
use App\Support\SidangTaRevisionContract as Revision;
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
                $targeted = Revision::isTargeted($locked);
                if ($targeted) Revision::assertPayloadUnchanged($locked, $payload);
                if ($step === 4 && array_key_exists('diploma_biodata_accurate', $payload)) {
                    $choice = $payload['diploma_biodata_accurate'];
                    $payload['diploma_biodata_accurate'] = $choice === null ? null : (bool) $choice;
                }
                $accurate = $payload['diploma_biodata_accurate'] ?? null;
                $types = Requirements::typesForDraftStep($locked, $step, $payload);
                $allowedTypes = $targeted ? array_values(array_intersect($types, Revision::editableTypes($locked))) : $types;
                $files = $validated['documents'] ?? [];
                $fileRules = ['documents' => $allowedTypes ? ['array:'.implode(',', $allowedTypes)] : ['prohibited']];
                foreach ($allowedTypes as $type) $fileRules["documents.{$type}"] = Requirements::fileRulesForDraft($locked, $type);
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
                if (!$targeted && $step === 3
                    && app(\App\Services\SidangTaAdmissionFacts::class)->forStudent($student)['eprt']['mode'] === 'automatic') {
                    foreach ($locked->documents()->where('document_type', \App\Support\SidangTaMeetRequirements::WAIVER)->get() as $old) {
                        if ($old->source_document_id === null) $retiredPaths[] = $old->file_path;
                        $old->delete();
                    }
                }
                if (!$targeted && $step === 4 && $accurate !== null) {
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

### `app/Services/SidangTaDraftFinalizer.php`

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
use App\Support\SidangTaRevisionContract as Revision;
use App\Support\SidangTaMeetRequirements as Meet;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class SidangTaDraftFinalizer
{
    public function __construct(private SidangTaDraftAccess $access,
                                private SidangTaAcademicSnapshot $snapshots,
                                private SidangTaAdmissionFacts $facts) {}

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

            Requirements::assertKnownVersion($locked);

            // Harus sebelum check(active), source needs_review, periode dan kelengkapan.
            if ($locked->status === SidangTaRegistrationDraft::STATUS_CONSUMED) {
                $existing = $locked->submittedRegistration()->firstOrFail();
                abort_unless($existing->requirements_version === 2, 409,
                    'Persyaratan pengajuan tidak sesuai. Muat ulang atau hubungi petugas.');
                abort_unless($existing->student_number === $student->student_number, 403);
                return $existing; // Tidak mengubah keputusan reviewer atau menulis log ulang.
            }

            $this->access->check($user, $locked, true);
            $targeted = Revision::isTargeted($locked);
            abort_unless($locked->reviewed_version === $locked->version, 409, 'Tinjau draft terlebih dahulu.');
            if ($targeted) Revision::assertTransferReady($locked);
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

            $payload = $locked->payload ?? [];
            // One fresh decision supplies gates, required files and the stored snapshot.
            $admission = $this->facts->forStudent($student);
            $errors = [];
            foreach ($admission['academic']['blockers'] as $key => $message) {
                $errors['eligibility.'.$key] = $message;
            }
            if (!$targeted && !in_array($admission['eprt']['mode'], ['automatic', 'waiver'], true)) {
                $errors['eligibility.eprt'] = $admission['eprt']['message'];
            }
            if ($errors !== []) throw ValidationException::withMessages($errors);
            $rules = [];
            foreach (range(1, 4) as $step) $rules = array_merge($rules, Requirements::scalarRules($step));
            if (Validator::make($payload, $rules)->fails()) {
                throw ValidationException::withMessages(['draft' => 'Data belum lengkap. Buka form dan lengkapi kembali.']);
            }
            $types = $targeted ? \App\Support\SidangTaReviewEligibility::requiredTypes($source)
                : Meet::allTypes((bool) $payload['diploma_biodata_accurate'], $admission['eprt']['mode']);
            $allDocuments = $locked->documents()->lockForUpdate()->get();
            // A formerly necessary waiver may become obsolete after a qualifying test.
            // Only an explicit successful submission retires it failed submissions keep it.
            $documents = !$targeted && $admission['eprt']['mode'] === 'automatic'
                ? $allDocuments->reject(fn ($document) => $document->document_type === Meet::WAIVER)->values()
                : $allDocuments;
            $actual = $documents->pluck('document_type')->all();
            $expected = $types;
            sort($actual); sort($expected);
            if ($actual !== $expected) {
                throw ValidationException::withMessages(['draft' => 'Daftar berkas tidak sesuai persyaratan aktif. Buka form dan tinjau ulang.']);
            }
            foreach ($documents as $document) {
                if (!Storage::disk('local')->exists($document->file_path)) {
                    throw ValidationException::withMessages(['documents.'.$document->document_type =>
                        'Berkas tidak ditemukan. Unggah ulang dan tinjau kembali.']);
                }
            }
            $snapshot = $targeted ? $source->academic_snapshot : $this->snapshots->forStudent($student);
            if (!$targeted) {
                $snapshot = array_replace($snapshot, [
                    'passed_credits' => $admission['facts']['passed_credits'],
                    'tak_score' => $admission['facts']['tak_score'],
                    'eprt_score' => $admission['eprt_facts']['score'],
                    'sidang_tingkat_2' => 'Lulus', 'skpi_status' => 'Disetujui',
                    'ta2_krs_status' => 'eligible', 'ta2_krs_label' => 'Terdaftar',
                    'admission' => $admission + ['checked_at' => now()->toIso8601String()],
                ]);
            }

            $registration = $source ?? new SidangTaRegistration();
            $oldStatus = $source?->status;
            $oldPaths = $source ? $source->documents()->pluck('file_path')->all() : [];
            $registration->fill([
                'sidang_ta_period_id' => $period->id,
                'student_number' => $student->student_number,
                'student_name' => $targeted ? $source->student_name : $student->name,
                'study_program' => $targeted ? $source->study_program : $student->study_program,
                'student_phone' => $payload['student_phone'], 'parent_phone' => $payload['parent_phone'],
                'graduation_payment_date' => $payload['graduation_payment_date'],
                'diploma_biodata_accurate' => $payload['diploma_biodata_accurate'],
                'academic_snapshot' => $snapshot,
                'requirements_version' => 2,
                'status' => 'submitted', 'submitted_at' => now(),
                'reviewed_by' => null, 'reviewed_at' => null, 'review_notes' => null,
            ]);
            if (!$source) $registration->submitted_by = $user->id;
            $registration->lock_version = $source ? $source->lock_version + 1 : 1;
            $registration->save();

            foreach ($documents as $document) {
                // Borrowed verified rows keep their status, notes, metadata and file.
                if ($targeted && $document->source_document_id !== null) continue;
                $registration->documents()->updateOrCreate(['document_type' => $document->document_type], [
                    'file_name' => $document->file_name, 'file_path' => $document->file_path,
                    'file_size' => $document->file_size, 'file_type' => $document->file_type,
                    'status' => 'pending', 'notes' => null,
                ]);
            }
            // Lepaskan FK source_document_id sebelum menghapus row final obsolete.
            $locked->documents()->delete();
            $registration->documents()->whereNotIn('document_type', $types)->delete();
            $retired = array_values(array_diff(array_merge($oldPaths, $allDocuments->pluck('file_path')->all()),
                $documents->pluck('file_path')->all()));
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

### `app/Services/SidangTaDraftService.php`

```php
<?php

namespace App\Services;

use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDraft;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Support\SidangTaRevisionContract as Revision;
use App\Support\SidangTaDocumentRequirements as Requirements;
use Illuminate\Validation\ValidationException;

class SidangTaDraftService
{
    public function __construct(private SidangTaStudentContext $context,
                                private SidangTaDraftDocumentService $documents,
                                private SidangTaDraftAccess $access,
    ) {}

    public function owned(User $user, SidangTaRegistrationDraft $draft): SidangTaRegistrationDraft
    {
        Requirements::assertKnownVersion($draft);
        $this->access->check($user, $draft);
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
                Requirements::assertKnownVersion($existing);
                abort_unless((string) $existing->owner_user_id === (string) $user->id, 409);
                return $existing; // Retry start tidak menimpa draft; ubah lewat saveStep.
            }

            $period = $this->openPeriod((int) $validated['sidang_ta_period_id']);

            return SidangTaRegistrationDraft::create([
                'owner_user_id' => $user->id,
                'student_number' => $student->student_number,
                'sidang_ta_period_id' => $period->id,
                'requirements_version' => 2,
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
        $this->access->assertCanWrite($user, $draft);
        $this->owned($user, $draft);
        if ($step !== 1) {
            return $this->documents->save($user, $draft, $step, $expectedVersion, $validated);
        }
        $student = $this->context->studentFor($user);

        return DB::transaction(function () use ($user, $draft, $expectedVersion, $validated, $student) {
            Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $locked = SidangTaRegistrationDraft::whereKey($draft->id)->lockForUpdate()->firstOrFail();
            Requirements::assertKnownVersion($locked);
            $this->access->check($user, $locked, true);
            abort_unless($locked->version === $expectedVersion, 409, 'Draft berubah. Muat ulang terlebih dahulu.');
            if (Revision::isTargeted($locked)) {
                Revision::assertPayloadUnchanged($locked, array_replace($locked->payload ?? [], [
                    'student_phone' => $validated['student_phone'], 'parent_phone' => $validated['parent_phone'],
                ]));
            }
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

### `app/Services/SidangTaRegistrationReview.php`

```php
<?php

namespace App\Services;

use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDocument;
use App\Models\User;
use App\Support\SidangTaReviewEligibility as Eligibility;
use App\Support\SidangTaRevisionContract;
use App\Support\SidangTaRevisionDeadline;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SidangTaRegistrationReview
{
    private function locked(SidangTaRegistration $registration, int $version): SidangTaRegistration
    {
        $locked = SidangTaRegistration::whereKey($registration->id)->lockForUpdate()->firstOrFail();
        abort_unless($locked->lock_version === $version, 409, 'Pengajuan berubah. Muat ulang sebelum meninjau.');
        abort_unless($locked->requirements_version === 2, 409,
            'Persyaratan pengajuan tidak sesuai. Muat ulang atau hubungi petugas.');
        abort_unless($locked->status === 'submitted', 422,
            'Peninjauan terkunci. Pengajuan sedang direvisi atau sudah diputuskan.');
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
            if ($status === 'verified') $notes = null;
            $nextStatus = $locked->status;
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
            if ($action === 'needs_review') {
                SidangTaRevisionDeadline::ensureOpen($locked, 'action');
                SidangTaRevisionContract::assertCanRequest($locked);
            }
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

### `app/Services/SidangTaReviewExport.php`

```php
<?php

namespace App\Services;

use App\Models\SidangTaRegistration;
use App\Support\SidangTaReviewEligibility as Eligibility;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Rap2hpoutre\FastExcel\FastExcel;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class SidangTaReviewExport
{
    public const SNAPSHOT_COLUMNS = [
        'Snapshot NIM' => 'student_number',
        'Snapshot Nama' => 'student_name',
        'Snapshot Prodi' => 'study_program',
        'Email Kampus' => 'student_email',
        'Semester' => 'semester',
        'IPK' => 'gpa',
        'SKS Lulus' => 'passed_credits',
        'Sidang Tingkat 2' => 'sidang_tingkat_2',
        'KRS Sidang TA 2' => 'ta2_krs_label',
        'Judul TA Indonesia' => 'ta_title',
        'Judul TA Inggris' => 'ta_title_en',
        'TAK' => 'tak_score',
        'EPrT' => 'eprt_score',
        'Status BPP' => 'bpp_status',
        'Status SKPI' => 'skpi_status',
        'Sumber Akademik Diperbarui' => 'academic_source_updated_at',
    ];

    private function text(mixed $value): string
    {
        if ($value === null || (is_string($value) && in_array(trim($value), ['', '-'], true))) return 'Belum tersedia';
        if (is_bool($value)) return $value ? 'Ya' : 'Tidak';
        if (is_scalar($value)) return (string) $value;
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)
            ?: 'Belum tersedia';
    }

    public function row(SidangTaRegistration $registration): array
    {
        $snapshot = is_array($registration->academic_snapshot) ? $registration->academic_snapshot : [];
        $row = [
            'ID Pengajuan' => (string) $registration->id,
            'Periode Sidang TA' => $this->text($registration->period?->name),
            'NIM' => $this->text($registration->student_number),
            'Nama Mahasiswa' => $this->text($registration->student_name),
            'Program Studi' => $this->text($registration->study_program),
            'HP Mahasiswa' => $this->text($registration->student_phone),
            'HP Orang Tua/Wali' => $this->text($registration->parent_phone),
            'Tanggal Bayar Wisuda' => $this->text($registration->graduation_payment_date?->format('Y-m-d')),
            'Biodata Ijazah' => $registration->diploma_biodata_accurate ? 'Sudah sesuai' : 'Belum sesuai (PDM)',
            'Status Pengajuan' => SidangTaReviewQuery::STATUSES[$registration->status] ?? $this->text($registration->status),
            'Dikirim' => $this->text($registration->submitted_at?->format('Y-m-d H:i:s')),
            'Ditinjau' => $this->text($registration->reviewed_at?->format('Y-m-d H:i:s')),
            'Catatan Petugas' => $this->text($registration->review_notes),
            'Versi Pengajuan' => (string) $registration->lock_version,
        ];
        foreach (self::SNAPSHOT_COLUMNS as $label => $key) $row[$label] = $this->text($snapshot[$key] ?? null);
        $required = Eligibility::requiredTypes($registration);
        $labels = ['pending' => 'Menunggu verifikasi', 'verified' => 'Terverifikasi', 'rejected' => 'Perlu perbaikan'];
        foreach (SidangTaRegistration::DOCUMENT_TYPES as $type => $label) {
            $applicable = in_array($type, $required, true);
            $document = $registration->documents->firstWhere('document_type', $type);
            $row[$label.' — Status'] = !$applicable ? 'N/A' : (!$document ? 'Belum ada dokumen'
                : ($labels[$document->status] ?? $this->text($document->status)));
            $row[$label.' — Ketersediaan'] = !$applicable ? 'N/A' : (!$document ? 'Tidak ada metadata'
                : (Storage::disk('local')->exists($document->file_path) ? 'Tersedia' : 'Tidak ditemukan'));
            $row[$label.' — Catatan'] = !$applicable ? 'N/A' : $this->text($document?->notes);
        }
        return $row;
    }

    public function download(Collection $registrations): BinaryFileResponse
    {
        if ($registrations->isEmpty()) {
            throw ValidationException::withMessages(['export' => 'Tidak ada pengajuan yang cocok untuk diekspor.']);
        }
        $rows = $registrations->map(fn (SidangTaRegistration $registration) => $this->row($registration));
        $temporary = tempnam(sys_get_temp_dir(), 'dadu-ta-xlsx-');
        if ($temporary === false) throw new RuntimeException('File sementara ekspor tidak dapat dibuat.');
        $path = $temporary.'.xlsx';
        if (!@rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('File sementara XLSX tidak dapat disiapkan.');
        }
        try {
            (new FastExcel($rows))->stringValues()->setColumnFormat([
                'Semester' => 'number', 'IPK' => 'number', 'SKS Lulus' => 'number',
                'TAK' => 'number', 'EPrT' => 'number',
            ])->escapeFormulas()->export($path);
            clearstatcache(true, $path);
            return response()->download($path, 'Sidang_TA_'.now()->format('Y-m-d_H-i-s').'.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ])->setPrivate()->deleteFileAfterSend(true);
        } catch (Throwable $exception) {
            if (is_file($path)) @unlink($path);
            throw $exception;
        }
    }
}
```

### `app/Services/SidangTaRevisionDraftService.php`

```php
<?php

namespace App\Services;

use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDraft;
use App\Models\Student;
use App\Models\User;
use App\Support\SidangTaDocumentRequirements as Requirements;
use App\Support\SidangTaReviewEligibility as Eligibility;
use App\Support\SidangTaRevisionContract as Revision;
use App\Support\SidangTaRevisionDeadline;
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
            abort_unless($source->requirements_version === 2, 409,
                'Persyaratan pengajuan tidak sesuai. Muat ulang atau hubungi petugas.');
            abort_unless($source->status === 'needs_review', 409, 'Pengajuan tidak sedang membutuhkan revisi.');
            SidangTaRevisionDeadline::ensureOpen($source);
            Revision::assertCanRequest($source);
            if ($existing) {
                Requirements::assertKnownVersion($existing);
                abort_unless((string) $existing->owner_user_id === (string) $user->id, 409);
                $this->access->check($user, $existing, true);
                return $existing;
            }

            $draft = SidangTaRegistrationDraft::create([
                'owner_user_id' => $user->id, 'student_number' => $student->student_number,
                'sidang_ta_period_id' => $source->sidang_ta_period_id,
                'source_registration_id' => $source->id,
                'source_version' => $source->lock_version,
                'requirements_version' => 2,
                'payload' => [
                    'student_phone' => $source->student_phone, 'parent_phone' => $source->parent_phone,
                    'graduation_payment_date' => $source->graduation_payment_date?->toDateString(),
                    'diploma_biodata_accurate' => $source->diploma_biodata_accurate,
                ],
            ]);
            $types = Eligibility::requiredTypes($source);
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
            Requirements::assertKnownVersion($locked);
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

### `app/Support/SidangTaDocumentRequirements.php`

```php
<?php

namespace App\Support;

use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDraft;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class SidangTaDocumentRequirements
{
    public const CAPTURES = SidangTaMeetRequirements::CAPTURES;
    public const PAYMENT = SidangTaMeetRequirements::PAYMENT;
    public const DIPLOMA = SidangTaMeetRequirements::DIPLOMA;
    public const PDM = SidangTaMeetRequirements::PDM;

    public static function typesForStep(int $step, ?bool $accurate = null, string $mode = 'automatic'): array
    {
        return SidangTaMeetRequirements::typesForStep($step, $accurate, $mode);
    }

    public static function assertKnownVersion(SidangTaRegistrationDraft $draft): void
    {
        abort_unless($draft->requirements_version === 2, 409,
            'Persyaratan pengajuan tidak sesuai. Muat ulang atau hubungi petugas.');
    }

    public static function typesForDraftStep(SidangTaRegistrationDraft $draft, int $step, ?array $payload = null): array
    {
        self::assertKnownVersion($draft);
        $payload ??= $draft->payload ?? [];
        $choice = $payload['diploma_biodata_accurate'] ?? null;
        $accurate = $choice === null ? null : (bool) $choice;
        if (SidangTaRevisionContract::isTargeted($draft)) return SidangTaRevisionContract::typesForStep($draft, $step);
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
        return self::fileRules($type);
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
        return SidangTaMeetRequirements::fileRules($type);
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
        $editable = SidangTaRevisionContract::isTargeted($draft) ? SidangTaRevisionContract::editableTypes($draft) : [];
        foreach ($types as $type) {
            $document = $draft->documents->firstWhere('document_type', $type);
            if ($document && Storage::disk('local')->exists($document->file_path)
                && !(in_array($type, $editable, true) && $document->source_document_id !== null)) {
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

### `app/Support/SidangTaReviewEligibility.php`

```php
<?php

namespace App\Support;

use App\Models\SidangTaRegistration;
use Illuminate\Support\Facades\Storage;

class SidangTaReviewEligibility
{
    public static function requiredTypes(SidangTaRegistration $registration): array
    {
        abort_unless($registration->requirements_version === 2, 409,
            'Persyaratan pengajuan tidak sesuai. Muat ulang atau hubungi petugas.');
        $mode = $registration->academic_snapshot['admission']['eprt']['mode'] ?? null;
        abort_unless(in_array($mode, ['automatic', 'waiver'], true), 409,
            'Snapshot persyaratan pengajuan belum lengkap.');
        return SidangTaMeetRequirements::allTypes($registration->diploma_biodata_accurate, $mode);
    }

    public static function groups(SidangTaRegistration $registration): array
    {
        $required = self::requiredTypes($registration);
        return [
            'iGracias & Akademik' => array_values(array_intersect(SidangTaMeetRequirements::CAPTURES, $required)),
            'Pembayaran & Dokumen' => array_values(array_intersect(
                array_merge(SidangTaMeetRequirements::PAYMENT, [SidangTaMeetRequirements::WAIVER]), $required)),
            'Biodata' => array_values(array_intersect(array_merge(
                SidangTaMeetRequirements::DIPLOMA, SidangTaMeetRequirements::PDM), $required)),
        ];
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

### `app/Support/SidangTaRevisionContract.php`

```php
<?php

namespace App\Support;

use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDraft;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SidangTaRevisionContract
{
    public static function isTargeted(SidangTaRegistrationDraft $draft): bool
    {
        SidangTaDocumentRequirements::assertKnownVersion($draft);
        return $draft->source_registration_id !== null;
    }

    public static function source(SidangTaRegistrationDraft $draft): SidangTaRegistration
    {
        abort_unless(self::isTargeted($draft), 409, 'Draft bukan revisi terarah.');
        $source = SidangTaRegistration::whereKey($draft->source_registration_id)->firstOrFail();
        abort_unless($source->requirements_version === 2, 409,
            'Persyaratan pengajuan tidak sesuai. Muat ulang atau hubungi petugas.');
        abort_unless($source->requirements_version === $draft->requirements_version
            && $source->status === 'needs_review'
            && $source->student_number === $draft->student_number
            && $source->lock_version === $draft->source_version, 409,
            'Pengajuan asal berubah. Muat ulang revisi.');
        return $source;
    }

    public static function typesForStep(SidangTaRegistrationDraft $draft, int $step): array
    {
        $groups = array_values(SidangTaReviewEligibility::groups(self::source($draft)));
        return in_array($step, [2, 3, 4], true) ? $groups[$step - 2] : [];
    }

    public static function editableTypes(SidangTaRegistrationDraft $draft): array
    {
        $source = self::source($draft);
        return $source->documents()->whereIn('document_type', SidangTaReviewEligibility::requiredTypes($source))
            ->where('status', 'rejected')->pluck('document_type')->all();
    }

    public static function assertPayloadUnchanged(SidangTaRegistrationDraft $draft, array $mergedPayload): void
    {
        $source = self::source($draft);
        $expected = ['student_phone' => $source->student_phone, 'parent_phone' => $source->parent_phone,
            'graduation_payment_date' => $source->graduation_payment_date?->format('Y-m-d'),
            'diploma_biodata_accurate' => $source->diploma_biodata_accurate];
        $errors = [];
        foreach ($expected as $key => $value) {
            $actual = $mergedPayload[$key] ?? null;
            // Accept the validated boolean equivalents sent by HTML forms.
            if ($key === 'diploma_biodata_accurate' && in_array($actual, [true, false, 0, 1, '0', '1'], true)) {
                $actual = (bool) $actual;
            }
            if ($actual !== $value) $errors[$key] = 'Isian ini mengikuti pengajuan asal dan tidak dapat diubah pada revisi.';
        }
        if ($errors !== []) throw ValidationException::withMessages($errors);
    }

    public static function replacementRequired(SidangTaRegistrationDraft $draft, $document): bool
    {
        if (!self::isTargeted($draft)) return false;
        return in_array($document->document_type, self::editableTypes($draft), true)
            && $document->source_document_id !== null;
    }

    public static function assertCanRequest(SidangTaRegistration $source): void
    {
        $documents = $source->documents()->get();
        $errors = [];
        $rejected = 0;
        foreach (SidangTaReviewEligibility::requiredTypes($source) as $type) {
            $document = $documents->firstWhere('document_type', $type);
            if (!$document) {
                $errors['documents.'.$type] = 'Metadata dokumen belum tersedia.';
            } elseif ($document->status === 'rejected') {
                $rejected++;
            } elseif ($document->status !== 'verified' || !Storage::disk('local')->exists($document->file_path)) {
                $errors['documents.'.$type] = 'Selesaikan verifikasi atau tandai dokumen untuk diperbaiki terlebih dahulu.';
            }
        }
        if ($rejected === 0) $errors['action'] = 'Tandai minimal satu dokumen untuk diperbaiki sebelum meminta revisi.';
        if ($errors !== []) throw ValidationException::withMessages($errors);
    }

    public static function assertTransferReady(SidangTaRegistrationDraft $draft): void
    {
        $source = self::source($draft);
        self::assertCanRequest($source);
        self::assertPayloadUnchanged($draft, $draft->payload ?? []);
        $required = SidangTaReviewEligibility::requiredTypes($source);
        $sourceDocuments = $source->documents()->get()->keyBy('document_type');
        $documents = $draft->documents()->get();
        $actual = $documents->pluck('document_type')->all();
        $expected = $required;
        sort($actual); sort($expected);
        if ($actual !== $expected) throw ValidationException::withMessages(['draft' => 'Daftar dokumen revisi tidak sesuai pengajuan asal.']);
        $errors = [];
        foreach ($documents as $document) {
            $original = $sourceDocuments->get($document->document_type);
            if (!Storage::disk('local')->exists($document->file_path)) {
                $errors['documents.'.$document->document_type] = 'Berkas tidak ditemukan. Muat ulang revisi.';
            } elseif ($original->status === 'rejected') {
                if ($document->source_document_id !== null || $document->file_path === $original->file_path) {
                    $errors['documents.'.$document->document_type] = 'Unggah berkas pengganti untuk dokumen yang diminta diperbaiki.';
                }
            } elseif ($original->status !== 'verified'
                || (int) $document->source_document_id !== (int) $original->id
                || $document->file_path !== $original->file_path) {
                $errors['documents.'.$document->document_type] = 'Dokumen yang tidak direvisi harus tetap mengikuti berkas terverifikasi.';
            }
        }
        if ($errors !== []) throw ValidationException::withMessages($errors);
    }
}
```

### `app/Support/SidangTaRevisionDeadline.php`

```php
<?php

namespace App\Support;

use App\Models\SidangTaRegistration;
use Illuminate\Validation\ValidationException;

class SidangTaRevisionDeadline
{
    public const MESSAGE = 'Batas waktu revisi telah berakhir. Revisi tidak dapat disimpan atau dikirim lagi.';

    public static function hasExpired(SidangTaRegistration $registration, bool $lockPeriod = false): bool
    {
        abort_unless($registration->requirements_version === 2, 409,
            'Persyaratan pengajuan tidak sesuai. Muat ulang atau hubungi petugas.');

        // Selalu baca ulang periode; relasi yang sudah dimuat dapat menyimpan tanggal lama.
        $query = $registration->period();
        $period = ($lockPeriod ? $query->lockForUpdate() : $query)->firstOrFail();
        return now()->greaterThan($period->close_at);
    }

    // Panggil di dalam transaksi sesudah lock Student/draft/pengajuan yang diperlukan.
    public static function ensureOpen(SidangTaRegistration $registration, string $field = 'draft'): void
    {
        if (self::hasExpired($registration, true)) {
            throw ValidationException::withMessages([$field => self::MESSAGE]);
        }
    }
}
```

### `database/migrations/2026_11_04_231319_set_sidang_ta_requirements_default_to_two.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->setDefault(2);
    }

    public function down(): void
    {
        $this->setDefault(1);
    }

    private function setDefault(int $version): void
    {
        foreach (['sidang_ta_registrations', 'sidang_ta_registration_drafts'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($version) {
                // Preserve the existing type; only future inserts get the new default.
                $table->unsignedTinyInteger('requirements_version')->default($version)->change();
            });
        }
    }
};
```
