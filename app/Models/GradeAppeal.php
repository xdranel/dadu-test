<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GradeAppeal extends Model
{
    const STATUS_PENDING  = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'user_id',
        'student_number',
        'student_name',
        'study_program',
        'course_id',
        'course_code',
        'course_name',
        'course_name_manual', // Field baru untuk input manual mata kuliah
        'course_class',
        'lecturer_name',
        'lecturer_code',
        'evidence_file_path',
        'evidence_file_name',
        'status',
        'admin_notes',
        'reviewed_by',
        'reviewed_at',
        'submitted_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at'  => 'datetime',
    ];

    // =========================================================================
    // Scopes
    // =========================================================================

    /**
     * Filter appeals by student number.
     */
    public function scopeForStudent($query, string $studentNumber)
    {
        return $query->where('student_number', $studentNumber);
    }

    /**
     * Filter by status.
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Search by student name, NIM, or course name.
     */
    public function scopeSearch($query, ?string $search)
    {
        return $query->when($search, fn($q, $s) =>
            $q->where(function ($inner) use ($s) {
                $inner->where('student_name', 'like', "%{$s}%")
                      ->orWhere('student_number', 'like', "%{$s}%")
                      ->orWhere('course_name', 'like', "%{$s}%");
            })
        );
    }

    // =========================================================================
    // Relations
    // =========================================================================

    /**
     * The user who submitted this appeal.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The course being appealed.
     */
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * The admin/staff who reviewed this appeal.
     */
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // =========================================================================
    // Accessors
    // =========================================================================

    /**
     * Human-readable status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING  => 'Menunggu Review',
            self::STATUS_APPROVED => 'Disetujui',
            self::STATUS_REJECTED => 'Ditolak',
            default               => ucfirst($this->status),
        };
    }

    /**
     * Badge CSS class for the status.
     */
    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING  => 'badge-warning',
            self::STATUS_APPROVED => 'badge-success',
            self::STATUS_REJECTED => 'badge-danger',
            default               => 'badge-slate',
        };
    }
}
