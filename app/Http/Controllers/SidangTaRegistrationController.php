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
        $schedulePeriods = SidangTaPeriod::query()->visibleToStudents()->get();
        return view('sidang_ta_registrations.index', compact('registrations', 'openPeriods', 'drafts', 'activeRegistration', 'schedulePeriods'));
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
        $admission = app(\App\Services\SidangTaAdmissionFacts::class)->forStudent($student);
        $academic = app(SidangTaAcademicSnapshot::class)->forStudent($student, $admission);
        $meetContext = ['targeted' => false, 'revision_expired' => false, 'admission' => $admission];
        $draft = null;
        $step = $maxStep = 1;
        $completedThrough = 0;
        $guides = app(\App\Services\SidangTaFormGuideService::class)->activeForSlots(array_keys(\App\Support\SidangTaFormGuideSlots::labels()));
        return view('sidang_ta_registrations.wizard', compact(
            'draft', 'student', 'step', 'maxStep', 'completedThrough', 'openPeriods', 'academic', 'meetContext', 'guides'
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
        if ($canRevise) return redirect()->route('sidang-ta-registrations.edit', $registration);
        return view('sidang_ta_registrations.show', compact('registration', 'canRevise'));
    }

    public function history(Request $request, SidangTaRegistration $sidang_ta_registration)
    {
        try {
            $student = app(SidangTaStudentContext::class)->studentFor($request->user());
        } catch (ValidationException $exception) {
            abort(403);
        }
        // This student-context endpoint never grants a reviewer bypass.
        $registration = $sidang_ta_registration;
        abort_unless($registration->student_number === $student->student_number, 403);
        $events = app(\App\Services\SidangTaHistoryPresenter::class)->forStudent($registration);
        return $request->expectsJson() ? response()->json(['data' => $events])
            : view('sidang_ta_registrations.history', compact('registration', 'events'));
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
        $registration = $sidang_ta_registration->load(['period', 'documents', 'logs.actor']);
        $needsReview = $registration->status === 'needs_review';
        $readonlyNeedsReview = $needsReview;
        $reviewable = $registration->status === 'submitted';
        $requiredTypes = \App\Support\SidangTaReviewEligibility::requiredTypes($registration);
        $approvalBlockers = \App\Support\SidangTaReviewEligibility::blockers($registration);
        $documentGroups = \App\Support\SidangTaReviewEligibility::groups($registration);
        $revisionBlockers = [];
        if ($reviewable) {
            try {
                if (\App\Support\SidangTaRevisionDeadline::hasBeenDisabled($registration)) {
                    throw ValidationException::withMessages(['action' => \App\Support\SidangTaRevisionDeadline::DISABLED_MESSAGE]);
                }
                if (\App\Support\SidangTaRevisionDeadline::hasNotStarted($registration)) {
                    throw ValidationException::withMessages(['action' => \App\Support\SidangTaRevisionDeadline::NOT_STARTED_MESSAGE]);
                }
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

    private function validatedPeriodData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'open_at' => ['required', 'date'],
            'close_at' => ['required', 'date', 'after:open_at'],
            'revision_open_at' => ['required', 'date'],
            'revision_close_at' => ['required', 'date', 'after:revision_open_at', 'after_or_equal:close_at'],
            'description' => ['nullable', 'string', 'max:2000'],
        ], [
            'revision_open_at.required' => 'Isi tanggal mulai verifikasi berkas.',
            'revision_open_at.date' => 'Tanggal mulai verifikasi berkas tidak valid.',
            'revision_close_at.required' => 'Isi tenggat verifikasi berkas.',
            'revision_close_at.date' => 'Tenggat verifikasi berkas tidak valid.',
            'revision_close_at.after' => 'Tenggat verifikasi harus setelah tanggal mulai verifikasi.',
            'revision_close_at.after_or_equal' => 'Tenggat verifikasi tidak boleh sebelum tenggat pendaftaran.',
        ]);
        foreach (['is_active', 'is_revision_active', 'show_to_students'] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }
        return $data;
    }

    public function storePeriod(Request $request)
    {
        $data = $this->validatedPeriodData($request);
        SidangTaPeriod::create($data);
        return back()->with('success', 'Periode Sidang TA berhasil dibuat.');
    }

    public function updatePeriod(Request $request, SidangTaPeriod $period)
    {
        $data = $this->validatedPeriodData($request);
        $period->update($data);
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
                (int) $request->validated('version'), $source ? (int) $source->id : null,
                $request->boolean('declaration_accepted'));
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
