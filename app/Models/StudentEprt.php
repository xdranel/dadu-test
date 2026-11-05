<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentEprt extends Model
{
    protected $fillable = [
        'student_number',
        'student_name',
        'listening',
        'structure',
        'reading',
        'total_score',
        'details',
        'academic_period_id',
        'attempt_number',
        'test_date',
        'category',
        'raw_score',
        'notes',
    ];

    protected $casts = [
        'listening'          => 'integer',
        'structure'          => 'integer',
        'reading'            => 'integer',
        'total_score'        => 'integer',
        'raw_score'          => 'integer',
        'attempt_number'     => 'integer',
        'test_date'          => 'date',
    ];

    /**
     * Kategori skor ePRT yang valid.
     */
    public const CATEGORIES = [
        'listening'  => 'Listening Comprehension',
        'structure'  => 'Structure & Written Expression',
        'reading'    => 'Reading Comprehension',
    ];

    // =========================================================================
    // Scopes
    // =========================================================================

    /**
     * Filter berdasarkan nomor attempt (ujian ke berapa).
     */
    public function scopeByAttempt($query, int $attempt)
    {
        return $query->where('attempt_number', $attempt);
    }

    /**
     * Filter berdasarkan kategori skor.
     */
    public function scopeByCategory($query, ?string $category)
    {
        return $query->when($category, fn($q, $c) => $q->where('category', $c));
    }

    /**
     * Urutkan berdasarkan attempt terbaru.
     */
    public function scopeLatestAttempt($query)
    {
        return $query->orderByDesc('attempt_number')->orderByDesc('test_date');
    }

    /**
     * Hanya tampilkan attempt pertama (ujian awal).
     */
    public function scopeFirstAttempt($query)
    {
        return $query->where('attempt_number', 1);
    }

    // =========================================================================
    // Accessors
    // =========================================================================

    /**
     * Tingkat kemahiran berdasarkan total_score.
     * Menggunakan standar konversi TOEFL ITP.
     */
    public function getProficiencyLevelAttribute(): string
    {
        return match (true) {
            $this->total_score >= 550 => 'Advanced',
            $this->total_score >= 450 => 'Intermediate',
            $this->total_score >= 350 => 'Elementary',
            $this->total_score > 0   => 'Beginner',
            default                  => 'Belum Diuji',
        };
    }

    /**
     * Label kategori yang lebih readable.
     */
    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category ?? '-';
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
