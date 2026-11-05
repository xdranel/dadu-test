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

    private function meetContext(SidangTaRegistrationDraft $draft, Student $student): ?array
    {
        if ($draft->requirements_version !== 2) return null;
        $admission = app(\App\Services\SidangTaAdmissionFacts::class)->forStudent($student);
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
            $types[$step] = \App\Support\SidangTaMeetRequirements::typesForStep($step, $accurate, $mode);
        }
        return [
            'admission' => $admission, 'types' => $types,
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
                    ['key' => 'eprt', 'title' => 'EPRT', 'value' => $score === null ? 'Belum tersedia' : 'Nilai '.$score,
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
        if ($meetContext) $academic = array_replace($academic, $meetContext['academic']);
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
        $documentGroups = $meetContext['types'] ?? [];
        if (!$meetContext) {
            foreach ([2, 3, 4] as $step) {
                $documentGroups[$step] = SidangTaDocumentRequirements::typesForDraftStep($draft, $step);
            }
        }
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
