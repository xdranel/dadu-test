<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GraduationRegistration extends Model
{
    // Status constants
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_NEEDS_REVIEW = 'needs_review';
    const STATUS_REJECTED = 'rejected';
    const STATUS_APPROVED = 'approved';
    const STATUS_CANCELLED = 'cancelled';

    const STATUSES = [
        self::STATUS_SUBMITTED,
        self::STATUS_NEEDS_REVIEW,
        self::STATUS_REJECTED,
        self::STATUS_APPROVED,
        self::STATUS_CANCELLED,
    ];

    // All supported document types and their Indonesian labels
    const DOCUMENT_TYPES = [
        'approval_sheet'              => 'Lembar Pengesahan',
        'final_thesis_book_full'      => 'Buku TA Full (lengkap)',
        'ithenticate_book_result'     => 'Hasil i-Thenticate Buku TA',
        'ithenticate_journal_result'  => 'Hasil i-Thenticate Jurnal',
        'library_clearance_letter'    => 'SBKP (Surat Bebas Pustaka)',
        'openlib_clearance_letter'    => 'Surat Bebas Openlib',
        'financial_clearance_letter'  => 'Surat Bebas Keuangan',
        'diploma_biodata_statement'   => 'Scan Pernyataan Dummy Ijazah',
        'revision_sheet_scan'         => 'Scan Lembar Revisi',
    ];

    protected $fillable = [
        'student_number', 'student_name', 'study_program',
        'graduation_period',
        'student_phone', 'parent_phone', 'personal_email', 'student_email',
        'academic_advisor_name', 'academic_advisor_code',
        'thesis_title_id', 'thesis_title_en',
        'has_title_change', 'sk_update_status', 'sk_update_submission_date',
        'advisor_1_code', 'advisor_1_name',
        'advisor_2_code', 'advisor_2_name',
        'examiner_1_code', 'examiner_1_name',
        'examiner_2_code', 'examiner_2_name',
        'examiner_3_code', 'examiner_3_name',
        'tak_score', 'eprt_score', 'exam_date',
        'ibasila_photo_approval_status',
        'failed_courses', 'retaken_courses',
        'level_3_exam_status',
        'last_synced_at',
        'gpa', 'passed_credits', 'remaining_credits_db', 'active_semester_db',
        'payment_status', 'thesis_grade_index', 'json_imported_at',
        'revision_deadline', 'revision_completion_date',
        'biodata_correction_note',
        'honors_predicate',
        'predicate_criteria',
        'predicate_umkm_partner',
        'predicate_competition_level',
        'predicate_journal_index',
        'predicate_document_path',
        'predicate_document_name',
        'status',
        'is_free_of_sanction', 'cumlaude_verified_by_admin',
        'submitted_by', 'reviewed_by',
        'submitted_at', 'reviewed_at', 'review_notes',
    ];

    protected $casts = [
        'tak_score'                 => 'integer',
        'eprt_score'                => 'decimal:2',
        'gpa'                       => 'decimal:2',
        'passed_credits'            => 'integer',
        'remaining_credits_db'      => 'integer',
        'active_semester_db'        => 'integer',
        'has_title_change'          => 'boolean',
        'is_free_of_sanction'       => 'boolean',
        'cumlaude_verified_by_admin' => 'boolean',
        'sk_update_submission_date' => 'date',
        'exam_date'                 => 'date',
        'revision_deadline'         => 'date',
        'revision_completion_date'  => 'date',
        'failed_courses'            => 'array',
        'retaken_courses'           => 'array',
        'submitted_at'              => 'datetime',
        'reviewed_at'               => 'datetime',
        'last_synced_at'            => 'datetime',
        'json_imported_at'          => 'datetime',
    ];

    // --- Relationships ---

    /**
     * The student record associated with this registration.
     * Soft reference via student_number (no FK on existing students table).
     */
    public function student()
    {
        return $this->belongsTo(Student::class, 'student_number', 'student_number');
    }

    public function documents()
    {
        return $this->hasMany(GraduationRegistrationDocument::class);
    }

    public function logs()
    {
        return $this->hasMany(GraduationRegistrationLog::class)->orderByDesc('performed_at');
    }

    public function getStatusLogsAttribute()
    {
        return $this->logs;
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // --- Scopes ---

    public function scopeForStudent($query, ?string $studentNumber = null)
    {
        return $query->where('student_number', $studentNumber ?? '');
    }

    public function scopeFilterStatus($query, ?string $status)
    {
        return $query->when($status, fn($q, $s) => $q->where('status', $s));
    }

    public function scopeFilterPeriod($query, ?string $period)
    {
        return $query->when($period, fn($q, $p) => $q->where('graduation_period', $p));
    }

    public function scopeFilterStudyProgram($query, ?string $studyProgram)
    {
        return $query->when($studyProgram, fn($q, $p) => $q->where('study_program', $p));
    }

    public function scopeSearch($query, ?string $search)
    {
        return $query->when($search, fn($q, $s) =>
            $q->where(function ($inner) use ($s) {
                $inner->where('student_name', 'like', "%{$s}%")
                      ->orWhere('student_number', 'like', "%{$s}%");
            })
        );
    }

    // --- Accessors ---

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_SUBMITTED    => 'Diajukan',
            self::STATUS_NEEDS_REVIEW => 'Perlu Revisi',
            self::STATUS_REJECTED     => 'Ditolak',
            self::STATUS_APPROVED     => 'Disetujui',
            self::STATUS_CANCELLED    => 'Dibatalkan',
            default                   => ucfirst($this->status),
        };
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_SUBMITTED    => 'badge-info',
            self::STATUS_NEEDS_REVIEW => 'badge-warning',
            self::STATUS_REJECTED     => 'badge-danger',
            self::STATUS_APPROVED     => 'badge-success',
            self::STATUS_CANCELLED    => 'badge-danger',
            default                   => 'badge-info',
        };
    }

    public function getHonorsPredicateLabelAttribute(): string
    {
        return match ($this->honors_predicate) {
            'cumlaude'       => 'Ya, akan mengajukan CUMLAUDE',
            'summa_cumlaude' => 'Ya, akan mengajukan SUMMA CUMLAUDE',
            'none'           => 'Tidak',
            default          => $this->honors_predicate ?: 'Tidak',
        };
    }

    public function getPredicateCriteriaLabelAttribute(): string
    {
        return match ($this->predicate_criteria) {
            'hki font-medium' => 'Hak Cipta',
            'hki'       => 'Hak Cipta',
            'lomba'     => 'Lomba',
            'publikasi' => 'Publikasi Ilmiah',
            default     => $this->predicate_criteria ?: '-',
        };
    }

    public function getPredicateCompetitionLevelLabelAttribute(): string
    {
        return match ($this->predicate_competition_level) {
            'juara_nasional_1'     => 'Juara Nasional Peringkat 1',
            'juara_nasional_2'     => 'Juara Nasional Peringkat 2',
            'juara_nasional_3'     => 'Juara Nasional Peringkat 3',
            'juara_internasional_1' => 'Juara Internasional Peringkat 1',
            'juara_internasional_2' => 'Juara Internasional Peringkat 2',
            'juara_internasional_3' => 'Juara Internasional Peringkat 3',
            default                => $this->predicate_competition_level ?: '-',
        };
    }

    public function getPredicateJournalIndexLabelAttribute(): string
    {
        return match ($this->predicate_journal_index) {
            's2'                 => 'S2',
            's1'                 => 'S1',
            'scopus_conference' => 'Conference International Terindeks Scopus',
            'q4'                 => 'Q4',
            'q3'                 => 'Q3',
            'q2'                 => 'Q2',
            'q1'                 => 'Q1',
            default              => $this->predicate_journal_index ?: '-',
        };
    }

    // --- Helpers ---

    public function isEditable(): bool
    {
        // Rejected applications are terminal; students start a new application.
        return $this->status === self::STATUS_NEEDS_REVIEW;
    }

    public function isReviewable(): bool
    {
        return in_array($this->status, [self::STATUS_SUBMITTED, self::STATUS_NEEDS_REVIEW]);
    }

    /**
     * Get the document for a specific type, if uploaded.
     */
    public function getDocumentByType(string $type): ?GraduationRegistrationDocument
    {
        return $this->documents->firstWhere('document_type', $type);
    }

    /**
     * Log a status change action.
     */
    public function logAction(string $action, ?string $fromStatus, ?string $toStatus, ?string $notes = null, ?int $performedBy = null): GraduationRegistrationLog
    {
        return $this->logs()->create([
            'action'       => $action,
            'from_status'  => $fromStatus,
            'to_status'    => $toStatus,
            'notes'        => $notes,
            'performed_by' => $performedBy,
            'performed_at' => now(),
        ]);
    }

    // --- Accessors for new grab fields ---

    public function getIbasilaPhotoApprovalStatusLabelAttribute(): string
    {
        return match ($this->ibasila_photo_approval_status) {
            'pending'  => 'Menunggu',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            default    => ucfirst($this->ibasila_photo_approval_status ?? 'pending'),
        };
    }

    public function getIbasilaPhotoApprovalStatusBadgeAttribute(): string
    {
        return match ($this->ibasila_photo_approval_status) {
            'pending'  => 'badge-warning',
            'approved' => 'badge-success',
            'rejected' => 'badge-danger',
            default    => 'badge-info',
        };
    }

    public function getLevel3ExamStatusLabelAttribute(): string
    {
        return match ($this->level_3_exam_status) {
            'passed'     => 'Lulus',
            'not_passed' => 'Belum Lulus',
            default      => $this->level_3_exam_status ?: '-',
        };
    }

    public function getLevel3ExamStatusBadgeAttribute(): string
    {
        return match ($this->level_3_exam_status) {
            'passed'     => 'badge-success',
            'not_passed' => 'badge-danger',
            default      => 'badge-secondary',
        };
    }

    /**
     * Check if data needs to be synced from campus system.
     */
    public function needsSync(): bool
    {
        // Data perlu sync jika belum pernah sync atau sudah lebih dari 24 jam
        return !$this->last_synced_at || $this->last_synced_at->lt(now()->subDay());
    }
}
