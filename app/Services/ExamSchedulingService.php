<?php

namespace App\Services;

use App\Models\ExamSchedule;
use App\Models\Course;
use App\Models\StudentCourse;
use App\Models\Room;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ExamSchedulingService
{
    /**
     * Process and validate an exam schedule input.
     *
     * @param array $data
     * @param int|null $excludeId
     * @return ExamSchedule
     */
    public function processSchedule(array $data, ?int $excludeId = null): ExamSchedule
    {
        return DB::transaction(function () use ($data, $excludeId) {
            if (($data['assessment_type'] ?? '') === 'TIDAK ADA ASSESSMEN BERSAMA' || empty($data['exam_date']) || empty($data['start_time']) || empty($data['end_time'])) {
                $data['status'] = 'Approved';
                $data['conflict_notes'] = null;
                
                if ($excludeId) {
                    $schedule = ExamSchedule::findOrFail($excludeId);
                    $schedule->update($data);
                } else {
                    $schedule = ExamSchedule::create($data);
                }
                return $schedule;
            }

            ExamSchedule::where('status', 'Approved')
                ->whereDate('exam_date', $data['exam_date'])
                ->lockForUpdate()
                ->get();

            $conflicts = [];

            // Retrieve Course details
            $course = Course::findOrFail($data['course_id']);
            $courseCode = $course->course_code;

            $activePeriod = \App\Models\AcademicPeriod::current();
            $activeAcademicYear = $activePeriod?->year;
            $activeSemester = $activePeriod?->semester;

            if (!StudentCourse::where('academic_year', $activeAcademicYear)->where('semester', $activeSemester)->exists()) {
                $latestKrs = StudentCourse::orderByDesc('academic_year')
                    ->orderByDesc('semester')
                    ->first();
                if ($latestKrs) {
                    $activeAcademicYear = $latestKrs->academic_year;
                    $activeSemester = $latestKrs->semester;
                }
            }

            // Validate Room Capacity
            $enrolledStudentsCount = StudentCourse::where('course_code', $courseCode)
                ->where('academic_year', $activeAcademicYear)
                ->where('semester', $activeSemester)
                ->when(!empty($data['course_class']), fn($q) => $q->where('course_class', $data['course_class']))
                ->count();
            $room1 = !empty($data['room_1']) ? Room::where('room_code', $data['room_1'])->first() : null;
            $room2 = !empty($data['room_2']) ? Room::where('room_code', $data['room_2'])->first() : null;

            if ($room1) {
                $totalCapacity = $room1->capacity + ($room2 ? $room2->capacity : 0);
                if ($enrolledStudentsCount > $totalCapacity) {
                    $conflicts[] = sprintf(
                        "Kapasitas ruangan %s%s tidak mencukupi (%d mahasiswa terdaftar, total kapasitas %d).",
                        $room1->room_code,
                        $room2 ? " dan " . $room2->room_code : "",
                        $enrolledStudentsCount,
                        $totalCapacity
                    );
                }
            }

            $newLecturers = array_filter([$data['lecturer_code'], $data['lecturer_code_2'] ?? null]);

            // Validate Room & Lecturer Conflict
            $roomOrLecturerConflict = ExamSchedule::where('status', 'Approved')
                ->whereDate('exam_date', $data['exam_date'])
                ->where('start_time', '<', $data['end_time'])
                ->where('end_time', '>', $data['start_time'])
                ->where(function ($query) use ($data, $newLecturers) {
                    $query->where(function($q) use ($data) {
                        $rooms = array_filter([$data['room_1'] ?? null, $data['room_2'] ?? null]);
                        if (!empty($rooms)) {
                            $q->whereIn('room_1', $rooms)
                              ->orWhereIn('room_2', $rooms);
                        } else {
                            $q->whereRaw('0 = 1');
                        }
                    });
                    if (!empty($newLecturers)) {
                        $query->orWhere(function($q) use ($newLecturers) {
                            $q->whereIn('lecturer_code', $newLecturers)
                              ->orWhereIn('lecturer_code_2', $newLecturers);
                        });
                    }
                })
                ->when($excludeId, function ($query) use ($excludeId) {
                    $query->where('id', '!=', $excludeId);
                })
                ->with('course')
                ->get();

            foreach ($roomOrLecturerConflict as $conflict) {
                $conflictType = '';
                
                $conflictRooms = array_filter([$conflict->room_1, $conflict->room_2]);
                $inputRooms = array_filter([$data['room_1'] ?? null, $data['room_2'] ?? null]);
                $hasRoomConflict = !empty(array_intersect($inputRooms, $conflictRooms));

                $hasLecturerConflict = false;
                $conflictLecturers = array_filter([$conflict->lecturer_code, $conflict->lecturer_code_2]);
                if (array_intersect($newLecturers, $conflictLecturers)) {
                    $hasLecturerConflict = true;
                }

                if ($hasRoomConflict && $hasLecturerConflict) {
                    $conflictType = 'Ruangan dan Dosen';
                } elseif ($hasRoomConflict) {
                    $conflictType = 'Ruangan';
                } else {
                    $conflictType = 'Dosen';
                }

                $conflicts[] = sprintf(
                    "Bentrok %s dengan jadwal %s (%s) pada jam %s - %s.",
                    $conflictType,
                    $conflict->course->name ?? '-',
                    $conflict->course->course_code ?? '-',
                    Carbon::parse($conflict->start_time)->format('H:i'),
                    Carbon::parse($conflict->end_time)->format('H:i')
                );
            }

            // Validate Overlapping Courses (Course-Level Validation using exists query)
            $overlappingSchedules = ExamSchedule::where('status', 'Approved')
                ->whereDate('exam_date', $data['exam_date'])
                ->where('start_time', '<', $data['end_time'])
                ->where('end_time', '>', $data['start_time'])
                ->when($excludeId, function ($query) use ($excludeId) {
                    $query->where('id', '!=', $excludeId);
                })
                ->whereHas('course', function ($query) use ($courseCode) {
                    $query->where('course_code', '!=', $courseCode)
                          ->whereExists(function ($sub) use ($courseCode) {
                              $sub->select(DB::raw(1))
                                  ->from('student_courses as sc1')
                                  ->join('student_courses as sc2', 'sc1.student_number', '=', 'sc2.student_number')
                                  ->whereColumn('sc1.course_code', 'courses.course_code')
                                  ->where('sc2.course_code', $courseCode);
                          });
                })
                ->with('course')
                ->get();

            foreach ($overlappingSchedules as $conflict) {
                $conflicts[] = sprintf(
                    "Bentrok mata kuliah beririsan dengan %s (%s) pada jam %s - %s karena terdapat mahasiswa yang mengambil kedua mata kuliah tersebut.",
                    $conflict->course->name ?? '-',
                    $conflict->course->course_code ?? '-',
                    Carbon::parse($conflict->start_time)->format('H:i'),
                    Carbon::parse($conflict->end_time)->format('H:i')
                );
            }

            // Determine status and notes
            if (!empty($conflicts)) {
                $data['status'] = 'Draft - Conflict';
                $data['conflict_notes'] = implode("\n", $conflicts);
            } else {
                $data['status'] = 'Approved';
                $data['conflict_notes'] = null;
            }

            // Save or Update schedule
            if ($excludeId) {
                $schedule = ExamSchedule::findOrFail($excludeId);
                $schedule->update($data);
            } else {
                $schedule = ExamSchedule::create($data);
            }

            return $schedule;
        });
    }
}
