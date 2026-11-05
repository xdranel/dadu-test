<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\GradeAppeal;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class GradeAppealController extends Controller
{
    /**
     * List the authenticated student's own submissions.
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        if ($user->hasRole('mahasiswa')) {
            $studentNumber = $this->getStudentNumber();
            $appeals = GradeAppeal::forStudent($studentNumber)
                ->latest('submitted_at')
                ->paginate(10);
        } else {
            // Admin/Pegawai can also see all from this route if needed
            $appeals = GradeAppeal::forStudent($this->getStudentNumber())
                ->latest('submitted_at')
                ->paginate(10);
        }

        $activePeriod = \App\Models\GradeAppealPeriod::getActivePeriod();

        return view('grade_appeals.index', compact('appeals', 'activePeriod'));
    }

    /**
     * Show the create form, pre-filling data from SSO/student table.
     */
    public function create()
    {
        $user = auth()->user();
        $studentNumber = $this->getStudentNumber();
        $student = Student::where('student_number', $studentNumber)
            ->orWhere('email', $user->email)
            ->first();

        return view('grade_appeals.create', compact('student'));
    }

    /**
     * Store a new grade appeal submission.
     */
    public function store(Request $request)
    {
        $request->validate([
            'course_name_manual' => 'required|string|max:255',
            'evidence_file'      => 'required|file|mimes:pdf|max:5120', // 5MB
        ], [
            'course_name_manual.required' => 'Mata Kuliah wajib diisi.',
            'evidence_file.required'      => 'File Form Pengajuan dan Evidence wajib diunggah.',
            'evidence_file.mimes'         => 'File harus berformat PDF.',
            'evidence_file.max'           => 'Ukuran file tidak boleh melebihi 5MB.',
        ]);

        $user = auth()->user();
        $studentNumber = $this->getStudentNumber();
        $student = Student::where('student_number', $studentNumber)
            ->orWhere('email', $user->email)
            ->first();

        // Fallback: jika student tidak ditemukan via student_number, coba cari lagi via email saja
        if (!$student && $user->email) {
            $student = Student::where('email', $user->email)->first();
        }

        // Handle file upload
        $file = $request->file('evidence_file');
        $originalName = $file->getClientOriginalName();
        $storageName = now()->format('YmdHis') . '_' . $studentNumber . '_grade_appeal.' . $file->getClientOriginalExtension();
        $filePath = $file->storeAs('grade_appeal_documents', $storageName, 'public');

        $appeal = GradeAppeal::create([
            'user_id'            => $user->id,
            'student_number'     => $studentNumber ?: ($user->username ?? 'N/A'),
            'student_name'       => $student?->name ?? $user->name ?? 'N/A',
            'study_program'      => $student?->study_program ?? ($student?->major ?? 'Belum Terdaftar'), // Auto-fill dari data mahasiswa
            'course_id'          => null, // Nullable karena input manual
            'course_code'        => null, // Nullable karena input manual
            'course_name'        => null, // Nullable karena input manual
            'course_name_manual' => $request->course_name_manual, // Input manual mata kuliah
            'evidence_file_path' => $filePath,
            'evidence_file_name' => $originalName,
            'status'             => GradeAppeal::STATUS_PENDING,
            'submitted_at'       => now(),
        ]);

        return redirect()->route('grade-appeals.show', $appeal)
            ->with('success', 'Form Banding Nilai berhasil diajukan.');
    }

    /**
     * Show a student's own submission detail.
     */
    public function show(GradeAppeal $grade_appeal)
    {
        $user = auth()->user();

        // Students can only view their own submissions
        if ($user->hasRole('mahasiswa') && $grade_appeal->student_number !== $this->getStudentNumber()) {
            abort(403, 'Anda tidak memiliki akses ke data ini.');
        }

        $grade_appeal->load(['course', 'reviewer']);

        return view('grade_appeals.show', [
            'appeal' => $grade_appeal,
        ]);
    }

    // =========================================================================
    // Admin Endpoints
    // =========================================================================

    /**
     * List all grade appeals (Admin/Pegawai).
     */
    public function adminIndex(Request $request)
    {
        $appeals = GradeAppeal::query()
            ->search($request->search)
            ->when($request->status, fn($q, $s) => $q->status($s))
            ->latest('submitted_at')
            ->paginate(15);

        $activePeriod = \App\Models\GradeAppealPeriod::getActivePeriod();

        $stats = (object) [
            'total'    => GradeAppeal::count(),
            'pending'  => GradeAppeal::where('status', 'pending')->count(),
            'approved' => GradeAppeal::where('status', 'approved')->count(),
            'rejected' => GradeAppeal::where('status', 'rejected')->count(),
        ];

        return view('grade_appeals.admin_index', compact('appeals', 'activePeriod', 'stats'));
    }


    /**
     * Show a grade appeal detail (Admin/Pegawai).
     */
    public function adminShow(GradeAppeal $grade_appeal)
    {
        $grade_appeal->load(['course', 'user', 'reviewer']);

        return view('grade_appeals.admin_show', [
            'appeal' => $grade_appeal,
        ]);
    }

    /**
     * Review (approve/reject) a grade appeal.
     */
    public function review(Request $request, GradeAppeal $grade_appeal)
    {
        $request->validate([
            'status'      => 'required|in:approved,rejected',
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        $grade_appeal->update([
            'status'      => $request->status,
            'admin_notes' => $request->admin_notes,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        $statusLabel = $request->status === 'approved' ? 'disetujui' : 'ditolak';

        return redirect()->route('admin.grade-appeals.show', $grade_appeal)
            ->with('success', "Pengajuan banding nilai berhasil {$statusLabel}.");
    }

    // =========================================================================
    // API Endpoints (for AJAX)
    // =========================================================================

    // API endpoints dihapus karena tidak diperlukan lagi (mata kuliah sekarang input manual)

    // =========================================================================
    // Helpers
    // =========================================================================

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
}
