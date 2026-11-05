<?php

namespace App\Http\Controllers;

use App\Exceptions\CampusDataGrabException;
use App\Http\Requests\ReviewGraduationRegistrationRequest;
use App\Http\Requests\StoreGraduationRegistrationRequest;
use App\Models\GraduationRegistration;
use App\Models\GraduationRegistrationDocument;
use App\Models\GraduationPeriod;
use App\Models\Student;
use App\Services\GraduationDataSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Rap2hpoutre\FastExcel\FastExcel;

class GraduationRegistrationController extends Controller
{
    protected GraduationDataSyncService $syncService;

    public function __construct(GraduationDataSyncService $syncService)
    {
        $this->syncService = $syncService;
    }

    /**
     * List the authenticated student's own submissions.
     */
    public function index(Request $request)
    {
        $studentNumber = $this->getStudentNumber();

        $registrations = GraduationRegistration::forStudent($studentNumber)
            ->latest()
            ->paginate(10);

        $activePeriod = GraduationPeriod::getActivePeriod();

        $activeRegistration = GraduationRegistration::forStudent($studentNumber)
            ->whereIn('status', [
                GraduationRegistration::STATUS_SUBMITTED,
                GraduationRegistration::STATUS_NEEDS_REVIEW,
            ])
            ->latest()
            ->first();
        $hasActiveRegistration = $activeRegistration !== null;
        $revisionNotifications = $request->user()->unreadNotifications
            ->filter(fn ($notification) => data_get($notification->data, 'type') === 'document_revision')
            ->values();
        $revisionNotifications->each(fn ($notification) => $notification->markAsRead());

        return view('graduation_registrations.index', compact(
            'registrations',
            'activePeriod',
            'hasActiveRegistration',
            'activeRegistration',
            'revisionNotifications'
        ));
    }

    /**
     * Show the create form, pre-filling data from the students table.
     * Also performs initial data sync from campus system.
     */
    public function create()
    {
        $studentNumber = $this->getStudentNumber();

        $activeRegistration = GraduationRegistration::forStudent($studentNumber)
            ->whereIn('status', [
                GraduationRegistration::STATUS_SUBMITTED,
                GraduationRegistration::STATUS_NEEDS_REVIEW,
            ])
            ->latest()
            ->first();

        if ($activeRegistration) {
            if ($activeRegistration->status === GraduationRegistration::STATUS_NEEDS_REVIEW) {
                return redirect()->route('graduation-registrations.edit', $activeRegistration)
                    ->with('info', 'Pendaftaran Anda perlu direvisi. Perbaiki pengajuan yang sama lalu kirim ulang.');
            }

            return redirect()->route('graduation-registrations.show', $activeRegistration)
                ->with('warning', 'Anda masih memiliki pengajuan aktif yang sedang di proses.');
        }

        $user = auth()->user();
        $student = Student::where('student_number', $studentNumber)
            ->orWhere('email', $user->email)
            ->first();

        $documentTypes = GraduationRegistration::DOCUMENT_TYPES;
        $activePeriod = GraduationPeriod::getActivePeriod();

        // Cek eligibility mahasiswa dari sistem kampus
        $eligibility = $this->syncService->checkEligibility($studentNumber);
        $campusData = $eligibility['data'] ?? null;

        return view('graduation_registrations.create', compact(
            'student',
            'documentTypes',
            'activePeriod',
            'eligibility',
            'campusData'
        ));
    }

    /**
     * Store a new registration (submit).
     */
    public function store(StoreGraduationRegistrationRequest $request)
    {
        $activePeriod = GraduationPeriod::getActivePeriod();
        if (!$activePeriod || !$activePeriod->isOpen()) {
            return redirect()->route('graduation-registrations.index')
                ->with('error', 'Periode pendaftaran yudisium saat ini sedang ditutup.');
        }

        $user = auth()->user();
        $studentNumber = $this->getStudentNumber();

        $activeRegistration = GraduationRegistration::forStudent($studentNumber)
            ->whereIn('status', [
                GraduationRegistration::STATUS_SUBMITTED,
                GraduationRegistration::STATUS_NEEDS_REVIEW,
            ])
            ->latest()
            ->first();

        if ($activeRegistration) {
            if ($activeRegistration->status === GraduationRegistration::STATUS_NEEDS_REVIEW) {
                return redirect()->route('graduation-registrations.edit', $activeRegistration)
                    ->with('info', 'Pendaftaran Anda perlu direvisi. Perbaiki pengajuan yang sama lalu kirim ulang.');
            }

            return redirect()->route('graduation-registrations.show', $activeRegistration)
                ->with('warning', 'Anda masih memiliki pengajuan aktif yang sedang diproses.');
        }

        // Backend Eligibility Check (Proteksi Keamanan Utama)
        $eligibility = $this->syncService->checkEligibility($studentNumber);
        if (!$eligibility['eligible']) {
            $reasonsText = implode(', ', $eligibility['reasons']);
            return redirect()->back()
                ->withInput()
                ->with('error', 'Pengajuan Yudisium ditolak oleh sistem: Anda belum memenuhi syarat minimum yudisium (' . $reasonsText . ').');
        }

        $student = Student::where('student_number', $studentNumber)
            ->orWhere('email', $user->email)
            ->first();

        // M8: Data wisuda otomatis update ke info data diri mhs (no hp mhs dan ortu)
        if ($student) {
            $student->update([
                'phone_number' => $request->student_phone,
                'parent_phone_number' => $request->parent_phone,
            ]);
        }

        $isSubmit = $request->input('action') === 'submit';

        $honorsPredicate = $request->input('honors_predicate', 'none');
        $predicateCriteria = $honorsPredicate !== 'none' ? $request->input('predicate_criteria') : null;
        $umkmPartner = ($honorsPredicate !== 'none' && $predicateCriteria === 'hki') ? $request->input('predicate_umkm_partner') : null;
        $competitionLevel = ($honorsPredicate !== 'none' && $predicateCriteria === 'lomba') ? $request->input('predicate_competition_level') : null;
        $journalIndex = ($honorsPredicate !== 'none' && $predicateCriteria === 'publikasi') ? $request->input('predicate_journal_index') : null;

        $predicateDocumentPath = null;
        $predicateDocumentName = null;

        if ($request->hasFile('predicate_document') && $request->file('predicate_document')->isValid() && $honorsPredicate !== 'none') {
            $file = $request->file('predicate_document');
            $originalName = $file->getClientOriginalName();
            $storageName = now()->format('YmdHis') . '_' . $studentNumber . '_predicate.' . $file->getClientOriginalExtension();
            $predicateDocumentPath = $file->storeAs('graduation_documents', $storageName, 'public');
            $predicateDocumentName = $originalName;
        }

        // Buat registration record terlebih dahulu
        $registration = GraduationRegistration::create([
            'student_number'            => $studentNumber ?: ($user->username ?? 'N/A'),
            'student_name'              => $student->name ?? $user->name,
            'study_program'             => $student->study_program ?? null,
            'graduation_period'         => $request->graduation_period,
            // Field manual input dari mahasiswa
            'student_phone'             => $request->student_phone,
            'parent_phone'              => $request->parent_phone,
            'personal_email'            => $request->personal_email,
            'revision_completion_date'  => $request->revision_completion_date,
            // Data akademik yang diimpor diisi hanya oleh syncRegistrationData.
            'has_title_change'          => $request->has_title_change === 'ya' || $request->has_title_change === '1' || $request->has_title_change === true,
            'sk_update_status'          => $request->has_title_change === 'ya' || $request->has_title_change === '1' ? $request->sk_update_status : null,
            'sk_update_submission_date' => $request->sk_update_status === 'not_processed_laa' ? $request->sk_update_submission_date : null,
            // TAK and EPRT are populated by syncRegistrationData from imported JSON.
            // Tanggal sidang selalu diisi ulang dari profil akademik hasil import JSON.
            'exam_date'                 => null,
            'revision_deadline'         => $request->revision_deadline,
            'biodata_correction_note'   => $request->biodata_correction_note,
            'honors_predicate'          => $honorsPredicate,
            'predicate_criteria'        => $predicateCriteria,
            'predicate_umkm_partner'    => $umkmPartner,
            'predicate_competition_level' => $competitionLevel,
            'predicate_journal_index'   => $journalIndex,
            'predicate_document_path'   => $predicateDocumentPath,
            'predicate_document_name'   => $predicateDocumentName,
            'status'                    => GraduationRegistration::STATUS_SUBMITTED,
            'submitted_by'              => $user->id,
            'submitted_at'              => now(),
        ]);

        // Sync data dari sistem kampus (grab field read-only)
        try {
            $this->syncService->syncRegistrationData($registration, $user->id);
        } catch (CampusDataGrabException $e) {
            // Jika sync gagal, tetap lanjutkan tapi beri warning
            session()->flash('warning', 'Pendaftaran berhasil diajukan, namun gagal sinkronisasi data dari sistem kampus: ' . $e->getMessage());
        }

        // Handle document uploads
        $this->handleDocumentUploads($registration, $request);

        // Log the action
        $registration->logAction(
            $isSubmit ? 'submitted' : 'created',
            null,
            $registration->status,
            null,
            $user->id
        );

        $message = 'Pendaftaran yudisium berhasil diajukan.';

        return redirect()->route('graduation-registrations.show', $registration)
            ->with('success', $message);
    }

    /**
     * Show a student's own submission detail.
     */
    public function show(GraduationRegistration $graduation_registration)
{
    $user = auth()->user();

    // Students can only view their own submissions
    if ($user->hasRole('mahasiswa') && $graduation_registration->student_number !== $this->getStudentNumber()) {
        abort(403, 'Anda tidak memiliki akses ke data ini.');
    }

    $graduation_registration->load(['documents', 'logs.performer']);
    $documentTypes = GraduationRegistration::DOCUMENT_TYPES;

    // Ambil data akademik otomatis kampus (untuk Panel Kanan)
    $eligibility = $this->syncService->checkEligibility($graduation_registration->student_number);
    $campusData  = $eligibility['data'] ?? [];

    return view('graduation_registrations.show', [
        'registration'  => $graduation_registration,
        'documentTypes' => $documentTypes,
        'eligibility'   => $eligibility,
        'campusData'    => $campusData,
    ]);
}


    /**
     * Show the edit form for a submitted/needs_review registration.
     */
    public function edit(GraduationRegistration $graduation_registration)
    {
        $user = auth()->user();
        $studentNumber = $this->getStudentNumber();

        if ($graduation_registration->student_number !== $studentNumber) {
            abort(403, 'Anda tidak memiliki akses ke data ini.');
        }

        if (!$graduation_registration->isEditable()) {
            return redirect()->route('graduation-registrations.show', $graduation_registration)
                ->with('error', 'Pendaftaran ini tidak dapat diedit karena sudah dalam status ' . $graduation_registration->status_label . '.');
        }

        $student = Student::where('student_number', $studentNumber)
            ->orWhere('email', $user->email)
            ->first();
        $documentTypes = GraduationRegistration::DOCUMENT_TYPES;
        $activePeriod = GraduationPeriod::getActivePeriod();
        $graduation_registration->load('documents');

        $eligibility = $this->syncService->checkEligibility($studentNumber);
        $campusData  = $eligibility['data'] ?? [];

        return view('graduation_registrations.edit', [
            'registration'  => $graduation_registration,
            'student'       => $student,
            'documentTypes' => $documentTypes,
            'activePeriod'  => $activePeriod,
            'eligibility'   => $eligibility,
            'campusData'    => $campusData,
        ]);
    }

    /**
     * Update a submitted/needs_review registration.
     */
    public function update(StoreGraduationRegistrationRequest $request, GraduationRegistration $graduation_registration)
    {
        $user = auth()->user();

        if ($graduation_registration->student_number !== $this->getStudentNumber()) {
            abort(403, 'Anda tidak memiliki akses ke data ini.');
        }

        if (!$graduation_registration->isEditable()) {
            return redirect()->route('graduation-registrations.show', $graduation_registration)
                ->with('error', 'Pendaftaran ini tidak dapat diedit.');
        }

        $isSubmit = $request->input('action') === 'submit';
        $activePeriod = GraduationPeriod::getActivePeriod();
        if ($isSubmit && (!$activePeriod || !$activePeriod->isOpen())) {
            return redirect()->back()->with('error', 'Periode pendaftaran yudisium saat ini sedang ditutup.');
        }

        // Backend Eligibility Check saat update/resubmit
        if ($isSubmit) {
            $eligibility = $this->syncService->checkEligibility($graduation_registration->student_number);
            if (!$eligibility['eligible']) {
                $reasonsText = implode(', ', $eligibility['reasons']);
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'Pembaruan Pendaftaran Yudisium ditolak: Anda belum memenuhi syarat minimum yudisium (' . $reasonsText . ').');
            }
        }

        // M8: Update phone number pada tabel student
        $student = Student::where('student_number', $graduation_registration->student_number)->first();
        if ($student) {
            $student->update([
                'phone_number' => $request->student_phone,
                'parent_phone_number' => $request->parent_phone,
            ]);
        }

        $oldStatus = $graduation_registration->status;

        $honorsPredicate = $request->input('honors_predicate', 'none');
        $predicateCriteria = $honorsPredicate !== 'none' ? $request->input('predicate_criteria') : null;
        $umkmPartner = ($honorsPredicate !== 'none' && $predicateCriteria === 'hki') ? $request->input('predicate_umkm_partner') : null;
        $competitionLevel = ($honorsPredicate !== 'none' && $predicateCriteria === 'lomba') ? $request->input('predicate_competition_level') : null;
        $journalIndex = ($honorsPredicate !== 'none' && $predicateCriteria === 'publikasi') ? $request->input('predicate_journal_index') : null;

        $predicateDocumentPath = $graduation_registration->predicate_document_path;
        $predicateDocumentName = $graduation_registration->predicate_document_name;

        if ($request->hasFile('predicate_document') && $request->file('predicate_document')->isValid() && $honorsPredicate !== 'none') {
            if ($predicateDocumentPath && Storage::disk('public')->exists($predicateDocumentPath)) {
                Storage::disk('public')->delete($predicateDocumentPath);
            }
            $file = $request->file('predicate_document');
            $originalName = $file->getClientOriginalName();
            $storageName = now()->format('YmdHis') . '_' . $graduation_registration->student_number . '_predicate.' . $file->getClientOriginalExtension();
            $predicateDocumentPath = $file->storeAs('graduation_documents', $storageName, 'public');
            $predicateDocumentName = $originalName;
        }

        $graduation_registration->update([
            'graduation_period'         => $request->graduation_period,
            'student_phone'             => $request->student_phone,
            'parent_phone'              => $request->parent_phone,
            'personal_email'            => $request->personal_email,
            'has_title_change'          => $request->has_title_change === 'ya' || $request->has_title_change === '1' || $request->has_title_change === true,
            'sk_update_status'          => $request->has_title_change === 'ya' || $request->has_title_change === '1' ? $request->sk_update_status : null,
            'sk_update_submission_date' => $request->sk_update_status === 'not_processed_laa' ? $request->sk_update_submission_date : null,
            'revision_deadline'         => $request->revision_deadline,
            'revision_completion_date'   => $request->revision_completion_date,
            'biodata_correction_note'   => $request->biodata_correction_note,
            'honors_predicate'          => $honorsPredicate,
            'predicate_criteria'        => $predicateCriteria,
            'predicate_umkm_partner'    => $umkmPartner,
            'predicate_competition_level' => $competitionLevel,
            'predicate_journal_index'   => $journalIndex,
            'predicate_document_path'   => $predicateDocumentPath,
            'predicate_document_name'   => $predicateDocumentName,
            'status'                    => GraduationRegistration::STATUS_SUBMITTED,
            'submitted_by'              => $user->id,
            'submitted_at'              => $graduation_registration->submitted_at ?? now(),
            'review_notes'              => null,
        ]);

        try {
            $this->syncService->syncRegistrationData($graduation_registration, $user->id);
        } catch (CampusDataGrabException $e) {
            session()->flash('warning', 'Pendaftaran diperbarui, namun data akademik hasil impor belum dapat disinkronkan: ' . $e->getMessage());
        }

        // Handle document uploads
        $this->handleDocumentUploads($graduation_registration, $request);

        // Log the action
        $action = $isSubmit
            ? ($oldStatus === GraduationRegistration::STATUS_NEEDS_REVIEW ? 'resubmitted' : 'submitted')
            : 'updated';

        $graduation_registration->logAction(
            $action,
            $oldStatus,
            $graduation_registration->status,
            null,
            $user->id
        );

        $message = $isSubmit
            ? 'Pendaftaran yudisium berhasil diajukan.'
            : 'Draft pendaftaran yudisium berhasil diperbarui.';

        return redirect()->route('graduation-registrations.show', $graduation_registration)
            ->with('success', $message);
    }

    //  ADMIN / STAFF-FACING ACTIONS

    /**
     * List all submissions with filters (admin/staff view).
     */
    public function adminIndex(Request $request)
    {
        // 1. Base query
        $registrationsQuery = GraduationRegistration::query()
            ->with('documents')
            ->search($request->search)
            ->filterStatus($request->status)
            ->filterPeriod($request->period)
            ->filterStudyProgram($request->study_program);

        $registrations = (clone $registrationsQuery)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        // 2. Dynamic Stats Cards matching selected period
        $statsQuery = GraduationRegistration::query()
            ->filterPeriod($request->period);

        $stats = $statsQuery->selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN status = 'needs_review' THEN 1 ELSE 0 END) as needs_review
        ")->first();

        // 3. Dynamic Status Filter List in Indonesian (filtered by period if selected)
        $periodStatusQuery = GraduationRegistration::query()
            ->filterPeriod($request->period);
        $availableStatuses = $periodStatusQuery->distinct()->pluck('status')->toArray();

        $statusMap = [
            GraduationRegistration::STATUS_SUBMITTED    => 'Menunggu Review',
            GraduationRegistration::STATUS_APPROVED     => 'Disetujui',
            GraduationRegistration::STATUS_REJECTED     => 'Ditolak',
            GraduationRegistration::STATUS_NEEDS_REVIEW => 'Revisi',
            GraduationRegistration::STATUS_CANCELLED    => 'Dibatalkan',
        ];

        $statuses = [];
        foreach ($statusMap as $key => $label) {
            if (empty($availableStatuses) || in_array($key, $availableStatuses)) {
                $statuses[$key] = $label;
            }
        }
        if (empty($statuses)) {
            $statuses = $statusMap;
        }

        // 4. Gather period list & study programs list
        $dbPeriods = GraduationRegistration::distinct()->pluck('graduation_period')->filter()->toArray();
        $modelPeriods = GraduationPeriod::pluck('name')->toArray();
        $periods = collect(array_unique(array_merge($dbPeriods, $modelPeriods)))->sort()->values();

        $studyPrograms = GraduationRegistration::distinct()->pluck('study_program')->filter()->sort()->values();

        // 5. Period management data
        $allPeriods = GraduationPeriod::orderByDesc('created_at')->get();
        $activePeriod = GraduationPeriod::getActivePeriod();

        return view('admin.graduation_registrations.index', compact(
            'registrations',
            'periods',
            'studyPrograms',
            'statuses',
            'stats',
            'allPeriods',
            'activePeriod'
        ));
    }

    /**
     * Show a single submission detail (admin/staff view).
     */
    public function adminShow(GraduationRegistration $graduation_registration)
    {
        $graduation_registration->load(['documents', 'logs.performer', 'submitter', 'reviewer']);
        $documentTypes = GraduationRegistration::DOCUMENT_TYPES;

        return view('admin.graduation_registrations.show', [
            'registration'  => $graduation_registration,
            'documentTypes' => $documentTypes,
        ]);
    }

    /**
     * Process a review action (approve, reject, or request revision).
     */
    public function review(ReviewGraduationRegistrationRequest $request, GraduationRegistration $graduation_registration)
    {
        if (!$graduation_registration->isReviewable()) {
            return redirect()->route('admin.graduation-registrations.show', $graduation_registration)
                ->with('error', 'Status pengajuan ini tidak dapat diubah.');
        }

        $user = auth()->user();
        $oldStatus = $graduation_registration->status;

        $inputStatus = $request->input('status') ?: $request->input('action');

        $newStatus = match ($inputStatus) {
            'approve', 'approved'     => GraduationRegistration::STATUS_APPROVED,
            'reject', 'rejected'      => GraduationRegistration::STATUS_REJECTED,
            'needs_review', 'revisi'  => GraduationRegistration::STATUS_NEEDS_REVIEW,
            default                   => GraduationRegistration::STATUS_APPROVED,
        };

        $freeOfSanction = $request->boolean('is_free_of_sanction');
        $cumlaudeVerified = $request->boolean('cumlaude_verified_by_admin');
        if ($newStatus === GraduationRegistration::STATUS_APPROVED
            && $graduation_registration->documents()->where('status', '!=', 'verified')->exists()) {
            return back()->withInput()->with('error', 'Semua dokumen yang telah diunggah harus berstatus sesuai/terverifikasi sebelum pendaftaran dapat disetujui.');
        }

        if ($newStatus === GraduationRegistration::STATUS_APPROVED
            && (!$freeOfSanction || (($graduation_registration->honors_predicate ?? 'none') !== 'none' && !$cumlaudeVerified))) {
            return back()->withInput()->with('error', 'Checklist sanksi dan verifikasi predikat wajib diselesaikan sebelum menyetujui pendaftaran.');
        }

        $actionLabel = match ($newStatus) {
            GraduationRegistration::STATUS_APPROVED     => 'approved',
            GraduationRegistration::STATUS_REJECTED     => 'rejected',
            GraduationRegistration::STATUS_NEEDS_REVIEW => 'revision_requested',
            default                                     => 'updated',
        };

        $graduation_registration->update([
            'status'       => $newStatus,
            'reviewed_by'  => $user->id,
            'reviewed_at'  => now(),
            'review_notes' => $request->review_notes,
            'is_free_of_sanction' => $freeOfSanction,
            'cumlaude_verified_by_admin' => $cumlaudeVerified,
        ]);

        $graduation_registration->logAction(
            $actionLabel,
            $oldStatus,
            $newStatus,
            $request->review_notes,
            $user->id
        );

        $messages = [
            GraduationRegistration::STATUS_APPROVED     => 'Pendaftaran yudisium berhasil disetujui.',
            GraduationRegistration::STATUS_REJECTED     => 'Pendaftaran yudisium berhasil ditolak.',
            GraduationRegistration::STATUS_NEEDS_REVIEW => 'Permintaan revisi berhasil dikirim ke mahasiswa.',
        ];

        return redirect()->route('admin.graduation-registrations.show', $graduation_registration)
            ->with('success', $messages[$newStatus] ?? 'Status pendaftaran yudisium berhasil diperbarui.');
    }

    // PRIVATE HELPERS
    /**
     * Handle uploading documents from the request.
     */
    private function handleDocumentUploads(GraduationRegistration $registration, Request $request): void
    {
        if (!$request->hasFile('documents')) {
            return;
        }

        foreach ($request->file('documents') as $type => $file) {
            if (!$file || !$file->isValid()) {
                continue;
            }

            // Delete old document of same type if exists
            $existing = $registration->documents()->where('document_type', $type)->first();
            if ($existing) {
                if (Storage::disk('public')->exists($existing->file_path)) {
                    Storage::disk('public')->delete($existing->file_path);
                }
                $existing->delete();
            }

            // Store the new file
            $originalName = $file->getClientOriginalName();
            $storageName = now()->format('YmdHis') . '_' . $registration->id . '_' . $type . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('graduation_documents', $storageName, 'public');

            $registration->documents()->create([
                'document_type' => $type,
                'file_name'     => $originalName,
                'file_path'     => $path,
                'file_size'     => $file->getSize(),
                'file_type'     => strtolower($file->getClientOriginalExtension()),
                'status'        => 'pending',
                'uploaded_at'   => now(),
            ]);
        }
    }

    /**
     * Delete a graduation registration submission (admin action).
     */
    public function adminDestroy(GraduationRegistration $graduation_registration)
    {
        // Delete associated files from storage
        foreach ($graduation_registration->documents as $doc) {
            if (Storage::disk('public')->exists($doc->file_path)) {
                Storage::disk('public')->delete($doc->file_path);
            }
            $doc->delete();
        }

        $graduation_registration->delete();

        return back()->with('success', 'Data pengajuan pendaftaran yudisium berhasil dihapus.');
    }

    /**
     * Resolve student_number (NIM) for the current user safely.
     */
    private function getStudentNumber(): string
    {
        $user = auth()->user();
        if (!$user) return '';

        if (!empty($user->employee_id)) {
            return (string) $user->employee_id;
        }

        if (!empty($user->username)) {
            $student = Student::where('student_number', $user->username)->first();
            if ($student) {
                return (string) $student->student_number;
            }
        }

        if (!empty($user->email)) {
            $student = Student::where('email', $user->email)->first();
            if ($student) {
                return (string) $student->student_number;
            }
        }

        return (string) ($user->username ?? '');
    }

    /**
     * Sync data dari sistem kampus secara manual (AJAX endpoint).
     */
    public function syncCampusData(GraduationRegistration $graduation_registration)
    {
        $user = auth()->user();

        // Cek authorization
        if ($user->hasRole('mahasiswa') && $graduation_registration->student_number !== $this->getStudentNumber()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            $this->syncService->syncRegistrationData($graduation_registration, $user->id);

            return response()->json([
                'success' => true,
                'message' => 'Data berhasil disinkronkan dari sistem kampus',
                'data' => [
                    'last_synced_at' => $graduation_registration->last_synced_at?->format('d/m/Y H:i'),
                    'student_name' => $graduation_registration->student_name,
                    'tak_score' => $graduation_registration->tak_score,
                    'eprt_score' => $graduation_registration->eprt_score,
                    'exam_date' => $graduation_registration->exam_date?->format('d/m/Y'),
                    'level_3_exam_status' => $graduation_registration->level_3_exam_status_label,
                    'ibasila_photo_approval_status' => $graduation_registration->ibasila_photo_approval_status_label,
                ]
            ]);
        } catch (CampusDataGrabException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => $e->getCampusErrorCode(),
            ], 422);
        }
    }
    /**
 * Export data bahan sidang yudisium ke format Excel (.xlsx).
 */
public function exportExcel(Request $request)
{
    // 1. Query data pendaftaran yudisium (bisa difilter berdasarkan status / pencarian / periode)
    $query = GraduationRegistration::query()->with([
        'documents',
        'student.bppPayment',
        'student.skpi',
        'student.courseIssues',
        'student.sidangTingkat',
        'student.basilaApproval',
    ]);
    if ($request->filled('status')) {
        $query->where('status', $request->status);
    }
    if ($request->filled('period')) {
        $query->where('graduation_period', $request->period);
    }
    if ($request->filled('search')) {
        $search = $request->search;
        $query->where(function ($q) use ($search) {
            $q->where('student_name', 'like', "%{$search}%")
              ->orWhere('student_number', 'like', "%{$search}%");
        });
    }
    $registrations = $query->latest()->get();
    // 2. Mapping data untuk kolom Excel Bahan Sidang Yudisium
    $exportData = $registrations->map(function ($reg, $index) {
        $student = $reg->student;
        $courseIssues = $student?->courseIssues;
        $skpiData = $student?->skpi?->raw_data ?? [];
        $skpiStatus = $skpiData['status'] ?? $skpiData['approval_status'] ?? $skpiData['is_approved'] ?? '-';
        if ($skpiStatus === true || in_array(strtolower((string) $skpiStatus), ['true', '1', 'approved', 'disetujui', 'lulus'], true)) {
            $skpiStatus = 'Disetujui';
        }
        $formatCourses = static function (?array $courses): string {
            return collect($courses ?? [])->map(function ($course) {
                if (is_string($course)) {
                    return $course;
                }
                return ($course['course_code'] ?? $course['kode'] ?? '-')
                    . ' - ' . ($course['course_name'] ?? $course['nama'] ?? '-');
            })->implode('; ');
        };
        $documentReview = $reg->documents->map(
            fn ($document) => $document->document_type . ': ' . $document->status
        )->implode('; ');

        return [
            'No'                         => $index + 1,
            'Periode Yudisium'           => $reg->graduation_period ?? '-',
            'NIM'                        => $reg->student_number ?? '-',
            'Nama Mahasiswa'             => $reg->student_name ?? '-',
            'Program Studi'              => $reg->study_program ?? '-',
            'No HP Mahasiswa'            => $reg->student_phone ?? '-',
            'No HP Orang Tua'            => $reg->parent_phone ?? '-',
            'Email Pribadi (Gmail)'      => $reg->personal_email ?? '-',
            'Email Kampus'               => $reg->student_email ?? '-',
            'Dosen Wali'                 => $reg->academic_advisor_name ?? '-',
            'Judul TA (Bahasa Indonesia)'=> $reg->thesis_title_id ?? '-',
            'Judul TA (Bahasa Inggris)'  => $reg->thesis_title_en ?? '-',
            'Ada Perubahan Judul?'       => ucfirst($reg->has_title_change ?? 'tidak'),
            'Dosen Pembimbing 1'         => $reg->advisor_1_name ?? '-',
            'Dosen Pembimbing 2'         => $reg->advisor_2_name ?? '-',
            'Dosen Penguji 1'            => $reg->examiner_1_name ?? '-',
            'Dosen Penguji 2'            => $reg->examiner_2_name ?? '-',
            'Dosen Penguji 3'            => $reg->examiner_3_name ?? '-',
            'Skor TAK'                   => $reg->tak_score ?? 0,
            'Nilai EPRT'                 => $reg->eprt_score ?? 0,
            'Tanggal Sidang TA'          => $reg->exam_date?->format('d/m/Y') ?? '-',
            'Status Sidang Tingkat 3'    => $student?->sidangTingkat?->tingkat_3 ? 'Lulus' : 'Belum Lulus',
            'Approval BASILA'            => $student?->basilaApproval?->status ?? '-',
            'Approval SKPI'              => $skpiStatus,
            'IPK'                         => $reg->gpa ?? '-',
            'SKS Lulus'                   => $reg->passed_credits ?? '-',
            'SKS Belum Lulus'             => $reg->remaining_credits_db ?? '-',
            'Semester Aktif / Lama Studi' => $reg->active_semester_db ?? '-',
            'Status Pembayaran Wisuda'    => $reg->payment_status ?? '-',
            'Status Pembayaran BPP'       => $student?->bppPayment?->status ?? '-',
            'MK Belum Lulus'              => $formatCourses($courseIssues?->failed_courses),
            'MK Mengulang'                => $formatCourses($courseIssues?->retaking_courses),
            'Status Berkas'               => $documentReview ?: 'Belum ada berkas',
            'Pengajuan Cumlaude'         => $reg->honors_predicate !== 'none' ? strtoupper($reg->honors_predicate) : 'Tidak',
            'Kriteria Predikat'          => $reg->predicate_criteria ?? '-',
            'Status Pendaftaran'         => ucfirst(str_replace('_', ' ', $reg->status)),
            'Tanggal Pengajuan'          => $reg->submitted_at ? $reg->submitted_at->format('d/m/Y H:i') : '-',
            'Catatan Reviewer'           => $reg->review_notes ?? '-',
        ];
    });
    // 3. Generate dan download file Excel
    $filename = 'Bahan_Sidang_Yudisium_' . date('Y-m-d_H-i') . '.xlsx';
    
    return (new FastExcel($exportData))->download($filename);
}

}
