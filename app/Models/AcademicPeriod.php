<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPeriod extends Model
{
    protected $fillable = [
        'year',
        'semester',
        'label',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected $casts = [
        'semester'   => 'integer',
        'start_date' => 'date',
        'end_date'   => 'date',
        'is_active'  => 'boolean',
    ];

    // =========================================================================
    // Scopes
    // =========================================================================

    /**
     * Periode yang sedang aktif.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Urutkan periode dari yang terbaru.
     */
    public function scopeLatestPeriod($query)
    {
        return $query->orderByDesc('year')->orderByDesc('semester');
    }

    // =========================================================================
    // Accessors
    // =========================================================================

    /**
     * Label semester dalam Bahasa Indonesia.
     * Contoh: "Ganjil" untuk semester 1, "Genap" untuk semester 2.
     */
    public function getSemesterLabelAttribute(): string
    {
        return match ($this->semester) {
            1 => 'Ganjil',
            2 => 'Genap',
            default => "Semester {$this->semester}",
        };
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Ambil periode akademik yang sedang aktif.
     * Mengembalikan null jika tidak ada periode aktif.
     */
    public static function current(): ?self
    {
        return static::where('is_active', true)->first();
    }

    // =========================================================================
    // Relations
    // =========================================================================

    public function courses()
    {
        return $this->hasMany(Course::class);
    }

    public function studentCourses()
    {
        return $this->hasMany(StudentCourse::class);
    }

    public function studentActivities()
    {
        return $this->hasMany(StudentActivity::class);
    }

    public function studentEprts()
    {
        return $this->hasMany(StudentEprt::class);
    }
}
