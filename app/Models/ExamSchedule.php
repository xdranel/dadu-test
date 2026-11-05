<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamSchedule extends Model
{
    protected $table = 'exam_schedules';

    protected $fillable = [
        'course_id',
        'course_class',
        'lecturer_code',
        'lecturer_code_2',
        'exam_date',
        'start_time',
        'end_time',
        'room_1',
        'room_2',
        'proctor_name_1',
        'proctor_name_2',
        'assessment_type',
        'execution_method',
        'execution_time',
        'participant_division',
        'question_type',
        'answer_sheet_type',
        'needs_scratch_paper',
        'proctor_type',
        'exam_question_url',
        'custom_answer_sheet_url',
        'exam_rules',
        'status',
        'conflict_notes',
    ];

    protected function casts(): array
    {
        return [
            'exam_date' => 'date',
        ];
    }

    /**
     * Get date attribute alias for exam_date.
     */
    public function getDateAttribute()
    {
        return $this->exam_date;
    }

    /**
     * Relation to Course.
     */
    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    /**
     * Relation to Lecturer 1.
     */
    public function lecturer()
    {
        return $this->belongsTo(Lecturer::class, 'lecturer_code', 'lecturer_code');
    }

    /**
     * Relation to Lecturer 2.
     */
    public function lecturer2()
    {
        return $this->belongsTo(Lecturer::class, 'lecturer_code_2', 'lecturer_code');
    }

    /**
     * Relation to Room 1.
     */
    public function room()
    {
        return $this->belongsTo(Room::class, 'room_1', 'room_code');
    }

    /**
     * Relation to Room 2.
     */
    public function room2()
    {
        return $this->belongsTo(Room::class, 'room_2', 'room_code');
    }

    /**
     * Scope: search by course name or code.
     */
    public function scopeSearch($query, ?string $search)
    {
        return $query->when($search, fn($q, $s) =>
            $q->whereHas('course', fn($inner) =>
                $inner->where('name', 'like', "%{$s}%")
                      ->orWhere('course_code', 'like', "%{$s}%")
            )
        );
    }

    /**
     * Scope: filter by day of exam_date (Senin, Selasa, etc.).
     */
    public function scopeFilterDay($query, ?string $day)
    {
        if (!$day) return $query;

        $dayMap = [
            'Senin'  => 1, // Monday
            'Selasa' => 2,
            'Rabu'   => 3,
            'Kamis'  => 4,
            'Jumat'  => 5,
            'Sabtu'  => 6,
        ];

        $dayNumber = $dayMap[$day] ?? null;

        if ($dayNumber === null) return $query;

        return $query->whereRaw('DAYOFWEEK(exam_date) = ?', [$dayNumber + 1]);
        // MySQL DAYOFWEEK: 1=Sunday, 2=Monday, ..., 7=Saturday
    }

    /**
     * Scope: filter by assessment type.
     */
    public function scopeFilterAssessmentType($query, ?string $assessmentType)
    {
        return $query->when($assessmentType, fn($q, $type) => $q->where('assessment_type', $type));
    }

    /**
     * Check if the exam schedule is complete.
     */
    public function isComplete(): bool
    {
        if ($this->assessment_type === 'TIDAK ADA ASSESSMEN BERSAMA') {
            return true;
        }

        $hasCore = !empty($this->exam_date) &&
                   !empty($this->start_time) &&
                   !empty($this->end_time) &&
                   !empty($this->room_1) &&
                   !empty($this->proctor_name_1);

        if (!$hasCore) {
            return false;
        }

        if ($this->participant_division === 'DIBAGI 2 (JIKA PESERTA KELAS > 30)') {
            return !empty($this->room_2) && !empty($this->proctor_name_2);
        }

        return true;
    }
}

