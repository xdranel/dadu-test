<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lecturer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'active_status',
        'study_program',
        'nip_ypt',
        'nidn',
        'nuptk',
        'employment_status',
        'front_title',
        'back_title',
        'jfa',
        'academic_interest',
        'lecturer_code',
        'email_domain',
        'is_external',
        'institution',
    ];

    protected $casts = [
        'is_external' => 'boolean',
    ];

    // =========================================================================
    // Scopes
    // =========================================================================

    /**
     * Filter hanya dosen internal kampus.
     */
    public function scopeInternal($query)
    {
        return $query->where('is_external', false);
    }

    /**
     * Filter hanya dosen eksternal / luar kampus.
     */
    public function scopeExternal($query)
    {
        return $query->where('is_external', true);
    }

    /**
     * Urutkan dosen: data lengkap (nama + NIP) di atas, tidak lengkap di bawah.
     * Dalam setiap grup, urutkan berdasarkan nama A-Z.
     *
     * Contoh penggunaan:
     *   Lecturer::completeFirst()->paginate(20);
     */
    public function scopeCompleteFirst($query)
    {
        return $query->orderByRaw("
            CASE
                WHEN (name IS NOT NULL AND name != '')
                 AND (nip_ypt IS NOT NULL AND nip_ypt != '')
                THEN 0
                ELSE 1
            END ASC
        ")->orderBy('name', 'asc');
    }

    /**
     * Filter hanya dosen dengan data tidak lengkap
     * (nama atau NIP kosong/null).
     *
     * Berguna untuk halaman admin "data yang perlu dilengkapi".
     *
     * Contoh penggunaan:
     *   Lecturer::incomplete()->count();
     *   Lecturer::incomplete()->get();
     */
    public function scopeIncomplete($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('name')
              ->orWhere('name', '')
              ->orWhereNull('nip_ypt')
              ->orWhere('nip_ypt', '');
        });
    }

    // =========================================================================
    // Accessors
    // =========================================================================

    /**
     * Nama lengkap dengan gelar depan dan belakang.
     */
    public function getFullNameAttribute(): string
    {
        $parts = array_filter([
            $this->front_title,
            $this->name,
            $this->back_title,
        ]);

        return implode(' ', $parts) ?: ($this->name ?? '');
    }

    // =========================================================================
    // Relations
    // =========================================================================

    /**
     * Course yang diajar dosen ini (sebagai koordinator atau anggota tim).
     */
    public function courses()
    {
        return $this->belongsToMany(
                Course::class,
                'course_lecturers',
                'lecturer_code',
                'course_code',
                'lecturer_code',
                'course_code'
            )
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Jadwal ujian di mana dosen ini sebagai pengawas utama.
     */
    public function examSchedules()
    {
        return $this->hasMany(ExamSchedule::class, 'lecturer_code', 'lecturer_code');
    }
}
