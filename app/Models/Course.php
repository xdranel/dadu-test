<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    protected $fillable = [
        'course_id',
        'course_code',
        'name',
        'study_program',
        'class',
        'semester',
        'semester_term',
        'academic_year',
        'academic_period_id',
        'credits',
        'status_dosen',
        'learning_method',
        'type',
        'status',
    ];

    protected $casts = [
        'credits'   => 'integer',
        'semester'  => 'integer',
    ];

    // =========================================================================
    // Scopes
    // =========================================================================

    public function scopeSearch($query, ?string $search)
    {
        return $query->when($search, fn($q, $s) =>
            $q->where(function ($inner) use ($s) {
                $inner->where('name', 'like', "%{$s}%")
                      ->orWhere('course_code', 'like', "%{$s}%");
            })
        );
    }

    public function scopeFilterStudyProgram($query, ?string $studyProgram)
    {
        return $query->when($studyProgram, fn($q, $p) => $q->where('study_program', $p));
    }

    public function scopeFilterSemester($query, ?string $semester)
    {
        return $query->when($semester, fn($q, $s) => $q->where('semester', $s));
    }

    public function scopeFilterStatus($query, ?string $status)
    {
        return $query->when($status, fn($q, $s) => $q->where('status', $s));
    }

    public function scopeFilterAcademicPeriod($query, ?int $academicPeriodId)
    {
        return $query->when($academicPeriodId, fn($q, $id) => $q->where('academic_period_id', $id));
    }

    // =========================================================================
    // Accessor: enrolled_count dinamis (menggantikan kolom yang dihapus)
    // =========================================================================

    /**
     * Menghitung jumlah mahasiswa terdaftar secara dinamis dari relasi enrollments.
     *
     * Gunakan withCount('enrollments') di query untuk performa terbaik:
     *   Course::withCount('enrollments')->get()
     *   // lalu akses: $course->enrollments_count
     *
     * Atau untuk satu instance:
     *   $course->enrolled_count  // memanggil accessor ini
     *
     * @return int
     */
    public function getEnrolledCountAttribute(): int
    {
        // Gunakan cache relasi jika sudah di-load dengan withCount()
        if (array_key_exists('enrollments_count', $this->attributes)) {
            return (int) $this->attributes['enrollments_count'];
        }

        return $this->enrollments()->count();
    }

    /**
     * Validasi semester agar hanya mengembalikan nilai yang valid (1-14).
     *
     * @param mixed $value
     * @return int|null
     */
    public function getSemesterAttribute($value): ?int
    {
        if (is_numeric($value) && $value >= 1 && $value <= 14) {
            return (int) $value;
        }

        return null;
    }

    // =========================================================================
    // Relations
    // =========================================================================

    /**
     * Periode akademik yang mengampu course ini.
     */
    public function academicPeriod()
    {
        return $this->belongsTo(AcademicPeriod::class);
    }

    /**
     * Jadwal ujian yang terkait dengan course ini.
     */
    public function examSchedules()
    {
        return $this->hasMany(ExamSchedule::class, 'course_id');
    }

    /**
     * Enrollments mahasiswa (KRS) di course ini.
     */
    public function enrollments()
    {
        return $this->hasMany(StudentCourse::class, 'course_code', 'course_code');
    }

    public function studentCourses()
    {
        return $this->hasMany(StudentCourse::class, 'course_code', 'course_code');
    }

    /**
     * Mahasiswa yang terdaftar di course ini (many-to-many via student_courses).
     */
    public function students()
    {
        return $this->belongsToMany(
            Student::class,
            'student_courses',
            'course_code',
            'student_number',
            'course_code',
            'student_number'
        );
    }

    /**
     * Dosen koordinator course ini.
     */
    public function coordinators()
    {
        return $this->belongsToMany(
                Lecturer::class,
                'course_lecturers',
                'course_code',
                'lecturer_code',
                'course_code',
                'lecturer_code'
            )
            ->wherePivot('role', 'coordinator')
            ->withTimestamps();
    }

    /**
     * Dosen tim pengajar course ini.
     */
    public function teamLecturers()
    {
        return $this->belongsToMany(
                Lecturer::class,
                'course_lecturers',
                'course_code',
                'lecturer_code',
                'course_code',
                'lecturer_code'
            )
            ->wherePivot('role', 'team_member')
            ->withTimestamps();
    }

    /**
     * Semua dosen yang terlibat (koordinator + tim).
     */
    public function lecturers()
    {
        return $this->belongsToMany(
                Lecturer::class,
                'course_lecturers',
                'course_code',
                'lecturer_code',
                'course_code',
                'lecturer_code'
            )
            ->withPivot('role')
            ->withTimestamps();
    }
}
