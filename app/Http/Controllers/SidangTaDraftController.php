<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveSidangTaDraftStepRequest;
use App\Models\SidangTaRegistrationDraft;
use App\Models\SidangTaRegistration;
use App\Services\SidangTaAcademicSnapshot;
use App\Services\SidangTaAdmissionFacts;
use App\Services\SidangTaRevisionDraftService;
use App\Services\SidangTaDraftService;
use App\Models\SidangTaPeriod;
use App\Models\Student;
use App\Services\SidangTaStudentContext;
use App\Services\SidangTaDraftReviewService;
use App\Models\SidangTaRegistrationDraftDocument;
use App\Support\SidangTaDocumentRequirements;
use App\Support\SidangTaMeetRequirements;
use App\Support\SidangTaRevisionContract;
use App\Support\SidangTaRevisionDeadline;
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
        $admission = app(SidangTaAdmissionFacts::class)->forStudent($student);
        $targeted = SidangTaRevisionContract::isTargeted($draft);
        $revisionExpired = false;
        $revisionNotStarted = false;
        if ($targeted) {
            $source = SidangTaRevisionContract::source($draft);
            $revisionExpired = SidangTaRevisionDeadline::hasExpired($source);
            $revisionNotStarted = SidangTaRevisionDeadline::hasNotStarted($source);
        }
        $mode = $admission['eprt']['mode'];
        $payload = $draft->payload ?? [];
        $choice = $payload['diploma_biodata_accurate'] ?? null;
        $accurate = $choice === null ? null : (bool) $choice;
        $types = [];
        foreach ([2, 3, 4] as $step) {
            $types[$step] = $targeted ? SidangTaRevisionContract::typesForStep($draft, $step, $admission)
                : SidangTaMeetRequirements::typesForStep($step, $accurate, $mode);
        }
        return [
            'admission' => $admission, 'types' => $types, 'targeted' => $targeted,
            'revision_expired' => $revisionExpired, 'revision_not_started' => $revisionNotStarted,
            'editable_types' => $targeted ? SidangTaRevisionContract::editableTypes($draft, $admission) : [],
            'can_submit' => !$revisionExpired && !$revisionNotStarted && ($draft->source_registration_id === null || $targeted)
                && $admission['academic']['academic_passed']
                && in_array($mode, ['automatic', 'waiver'], true),
            'waiver_ready' => $this->waiverReady($draft, $targeted, $admission),
        ];
    }

    private function waiverReady(SidangTaRegistrationDraft $draft, bool $targeted, array $admission): bool
    {
        if ($admission['eprt']['mode'] !== 'waiver') return false;
        $document = $draft->documents->firstWhere('document_type', SidangTaMeetRequirements::WAIVER);
        return $document && Storage::disk('local')->exists($document->file_path)
            && !($targeted && $document->source_document_id !== null
                && in_array(SidangTaMeetRequirements::WAIVER, SidangTaRevisionContract::editableTypes($draft, $admission), true));
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
        $meetContext = $this->meetContext($draft, $student);
        $academic = app(SidangTaAcademicSnapshot::class)->forStudent($student, $meetContext['admission']);
        $guides = app(\App\Services\SidangTaFormGuideService::class)->activeForSlots(array_keys(\App\Support\SidangTaFormGuideSlots::labels()));
        return view('sidang_ta_registrations.wizard', compact(
            'draft', 'student', 'step', 'maxStep', 'completedThrough', 'openPeriods', 'academic', 'stepProgress', 'meetContext', 'guides'
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
        $academic = app(SidangTaAcademicSnapshot::class)->forStudent($student, $meetContext['admission']);
        return view('sidang_ta_registrations.wizard-review', compact('draft', 'student', 'meetContext', 'documentGroups', 'academic'));
    }
}
