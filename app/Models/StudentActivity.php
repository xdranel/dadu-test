<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentActivity extends Model
{
    protected $table = 'student_activities';

    protected $fillable = [
        'student_number',
        'activity_name',
        'activity_type',
        'activity_level',
        'activity_date',
        'academic_period_id',
        'role',
        'points',
        'date',
        'details',
        'document_path',
        'verified_at',
        'verified_by',
    ];

    protected $casts = [
        'date'          => 'date',
        'activity_date' => 'date',
        'verified_at'   => 'datetime',
        'points'        => 'integer',
    ];

    /**
     * Konstanta untuk nilai activity_level yang valid.
     */
    public const LEVELS = [
        'university'    => 'Tingkat Kampus',
        'faculty'       => 'Tingkat Fakultas',
        'national'      => 'Tingkat Nasional',
        'international' => 'Tingkat Internasional',
    ];

    /**
     * Konstanta untuk nilai activity_type yang umum.
     */
    public const TYPES = [
        'organizational' => 'Organisasi',
        'competition'    => 'Kompetisi',
        'seminar'        => 'Seminar/Webinar',
        'volunteer'      => 'Pengabdian/Volunteer',
        'achievement'    => 'Prestasi',
        'certification'  => 'Sertifikasi',
        'other'          => 'Lainnya',
    ];

    // =========================================================================
    // Scopes
    // =========================================================================

    /**
     * Filter berdasarkan tingkat kegiatan.
     */
    public function scopeByLevel($query, ?string $level)
    {
        return $query->when($level, fn($q, $l) => $q->where('activity_level', $l));
    }

    /**
     * Filter berdasarkan tipe kegiatan.
     */
    public function scopeByType($query, ?string $type)
    {
        return $query->when($type, fn($q, $t) => $q->where('activity_type', $t));
    }

    /**
     * Filter hanya kegiatan yang sudah terverifikasi.
     */
    public function scopeVerified($query)
    {
        return $query->whereNotNull('verified_at');
    }

    /**
     * Filter kegiatan yang belum terverifikasi (pending review).
     */
    public function scopePendingVerification($query)
    {
        return $query->whereNull('verified_at');
    }

    // =========================================================================
    // Accessors
    // =========================================================================

    /**
     * Apakah kegiatan ini sudah diverifikasi oleh admin.
     */
    public function getIsVerifiedAttribute(): bool
    {
        return $this->verified_at !== null;
    }

    /**
     * Label tampilan untuk activity_level.
     */
    public function getActivityLevelLabelAttribute(): string
    {
        return self::LEVELS[$this->activity_level] ?? $this->activity_level ?? '-';
    }

    /**
     * Label tampilan untuk activity_type.
     */
    public function getActivityTypeLabelAttribute(): string
    {
        return self::TYPES[$this->activity_type] ?? $this->activity_type ?? '-';
    }

    // =========================================================================
    // Relations
    // =========================================================================

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_number', 'student_number');
    }

    public function academicPeriod()
    {
        return $this->belongsTo(AcademicPeriod::class);
    }
}