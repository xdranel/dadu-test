<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    protected $fillable = [
        'student_number', 'name', 'gender', 'religion',
        'profile_photo', 'faculty', 'study_program', 'academic_year',
        'active_semester', 'status', 'class_name', 'academic_advisor',
        'email', 'phone_number',
        'parent_phone_number', 'high_school',
        'passed_tpb', 'passed_tkii', 'passed_tkiii', 'passed_tkiv',
        'total_credits', 'gpa',
    ];

    protected $hidden = ['phone_number'];

    private ?User $ssoUserCache = null;
    private bool $ssoUserResolved = false;

    /**
     * Akun user (hasil login SSO) yang terhubung ke mahasiswa ini.
     * Dicocokkan lewat NIM: users.employee_id atau users.username.
     */
    public function getSsoUserAttribute(): ?User
    {
        if (!$this->ssoUserResolved) {
            $this->ssoUserCache = $this->student_number
                ? User::where('employee_id', $this->student_number)
                    ->orWhere('username', $this->student_number)
                    ->first()
                : null;
            $this->ssoUserResolved = true;
        }

        return $this->ssoUserCache;
    }

    /** Email institusi: dari SSO ('email'), fallback ke data mahasiswa. */
    public function getInstitutionalEmailAttribute(): ?string
    {
        return $this->sso_user?->email ?: $this->email;
    }

    /** Nomor HP: dari SSO ('phone'), fallback ke data mahasiswa. */
    public function getContactPhoneAttribute(): ?string
    {
        return $this->sso_user?->phone ?: $this->phone_number;
    }

    /** URL foto profil: dari SSO ('photo') berupa URL penuh, fallback ke file upload lokal. */
    public function getPhotoUrlAttribute(): ?string
    {
        $photo = $this->sso_user?->photo ?: $this->profile_photo;

        if (!$photo) {
            return null;
        }

        return preg_match('#^https?://#i', $photo) ? $photo : \Illuminate\Support\Facades\Storage::url($photo);
    }

    public function courses()
    {
        return $this->hasMany(StudentCourse::class, 'student_number', 'student_number');
    }

    public function grades()
    {
        return $this->hasMany(StudentGrade::class, 'student_number', 'student_number');
    }

    public function attendances()
    {
        return $this->hasMany(StudentAttendance::class, 'student_number', 'student_number');
    }

    public function activities()
    {
        return $this->hasMany(StudentActivity::class, 'student_number', 'student_number');
    }

    public function eprts()
    {
        return $this->hasMany(StudentEprt::class, 'student_number', 'student_number');
    }

    // ─── Relasi Data Profil Akademik (iGracias + BASILA) ─────────────────

    public function finalProject()
    {
        return $this->hasOne(StudentFinalProject::class, 'student_number', 'student_number');
    }

    public function tak()
    {
        return $this->hasOne(StudentTak::class, 'student_number', 'student_number')
            ->where(function ($query) {
                $query->whereNull('expiry_date')
                    ->orWhereDate('expiry_date', '>=', today());
            })
            ->orderByDesc('total_points')
            ->orderByRaw("COALESCE(expiry_date, '9999-12-31') DESC");
    }

    public function skpi()
    {
        return $this->hasOne(StudentSkpi::class, 'student_number', 'student_number');
    }

    public function sidangTingkat()
    {
        return $this->hasOne(StudentSidangTingkat::class, 'student_number', 'student_number');
    }

    public function basilaApproval()
    {
        return $this->hasOne(StudentBasilaApproval::class, 'student_number', 'student_number');
    }

    public function finalProjectGrade()
    {
        return $this->hasOne(StudentFinalProjectGrade::class, 'student_number', 'student_number');
    }

    public function courseIssues()
    {
        return $this->hasOne(StudentCourseIssue::class, 'student_number', 'student_number');
    }

    public function graduationPayment()
    {
        return $this->hasOne(StudentGraduationPayment::class, 'student_number', 'student_number');
    }

    public function bppPayment()
    {
        return $this->hasOne(StudentBppPayment::class, 'student_number', 'student_number');
    }

    public function scopeSearch($query, ?string $search)
    {
        return $query->when($search, fn($q, $s) =>
            $q->where(function ($inner) use ($s) {
                $inner->where('name', 'like', "%{$s}%")
                    ->orWhere('student_number', 'like', "%{$s}%");
            })
        );
    }

    public function scopeFilterStudyProgram($query, ?string $studyProgram)
    {
        return $query->when($studyProgram, fn($q, $p) =>
            $q->where('study_program', $p)
        );
    }

    public function scopeFilterGeneration($query, ?string $generation)
    {
        return $query->when($generation, fn($q, $a) =>
            $q->where('academic_year', $a)
        );
    }

    public function getProfilePhotoUrlAttribute(): string
    {
        return $this->profile_photo
            ? asset('storage/' . $this->profile_photo)
            : asset('images/default-avatar.png');
    }

    public function getAttendancePercentageAttribute(): float
    {
        $total   = $this->attendances->sum('meetings_count');
        $hadir   = $this->attendances->sum('attended_count');
        return $total > 0 ? round(($hadir / $total) * 100, 2) : 0;
    }
}
