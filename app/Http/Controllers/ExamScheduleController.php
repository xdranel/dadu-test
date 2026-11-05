<?php

namespace App\Http\Controllers;

use App\Models\ExamSchedule;
use App\Models\Course;
use App\Models\StudentCourse;
use App\Models\AcademicPeriod;
use App\Http\Requests\StoreExamScheduleRequest;
use App\Http\Requests\UpdateExamScheduleRequest;
use Illuminate\Http\Request;
use App\Services\ExamSchedulingService;
use App\Models\Room;
use Illuminate\Support\Facades\DB;
use Rap2hpoutre\FastExcel\FastExcel;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Student;
use App\Models\Lecturer;

class ExamScheduleController extends Controller
{
    /**
     * List jadwal ujian.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $isMahasiswa = $user->hasRole('mahasiswa');

        $query = ExamSchedule::with('course')
            ->search($request->search)
            ->filterDay($request->hari)
            ->filterAssessmentType($request->jenis_assessment)
            ->latest('exam_date');

        if ($isMahasiswa) {
            $studentNumber = $user->username; // username = student_number
            $enrolledCourseCodes = StudentCourse::where('student_number', $studentNumber)
                ->pluck('course_code')
                ->toArray();

            $query->whereHas('course', fn($q) =>
                $q->whereIn('course_code', $enrolledCourseCodes)
            );
        }

        $schedules = $query->paginate(15)->withQueryString();

        return view('exam_schedules.index', [
            'schedules'    => $schedules,
            'isMahasiswa'  => $isMahasiswa,
        ]);
    }

    /**
     * Form create jadwal ujian (Admin/Dosen only).
     */
    public function create()
    {
        $activePeriod = AcademicPeriod::current();
        $activeYear = $activePeriod?->year;
        $activeSemester = $activePeriod?->semester;

        if (!StudentCourse::where('academic_year', $activeYear)->where('semester', $activeSemester)->exists()) {
            $latestKrs = StudentCourse::orderByDesc('academic_year')
                ->orderByDesc('semester')
                ->first();
            if ($latestKrs) {
                $activeYear = $latestKrs->academic_year;
                $activeSemester = $latestKrs->semester;
            }
        }

        $courses = Course::select('id', 'course_code', 'name', 'credits', 'study_program')
            ->distinct()
            ->get()
            ->map(function ($course) use ($activeYear, $activeSemester) {
                $kelas = StudentCourse::where('course_code', $course->course_code)
                    ->where('academic_year', $activeYear)
                    ->where('semester', $activeSemester)
                    ->whereNotNull('course_class')
                    ->where('course_class', '!=', '')
                    ->selectRaw('course_class, COUNT(*) as mhs_count')
                    ->groupBy('course_class')
                    ->orderBy('course_class')
                    ->get()
                    ->map(fn($k) => [
                        'course_id'    => $course->id,
                        'course_class' => $k->course_class,
                        'class'        => $k->course_class,
                        'mhs_count'    => (int) $k->mhs_count,
                        'room_count'   => (int) ceil($k->mhs_count / 30),
                    ]);

                return [
                    'id'           => $course->id,
                    'course_code'  => $course->course_code,
                    'name'         => $course->name,
                    'credits'      => $course->credits,
                    'study_program'=> $course->study_program,
                    'kelas'        => $kelas,
                    'total_mhs'    => $kelas->sum('mhs_count'),
                ];
            })->filter(fn($c) => $c['kelas']->count() > 0)->values();

        $lecturers = Lecturer::orderBy('name')->get(['lecturer_code', 'name']);

        $rooms = Room::where('status', 'Dapat Dipakai')
            ->orderBy('room_code')
            ->get();

        return view('exam_schedules.create', compact('courses', 'lecturers', 'rooms'));
    }

    /**
     * Simpan jadwal baru.
     */
    public function store(StoreExamScheduleRequest $request, ExamSchedulingService $schedulingService)
    {
        $validated = $request->validated();
        $hasConflicts = false;

        DB::transaction(function () use ($validated, $schedulingService, &$hasConflicts) {
            $commonFields = [
                'lecturer_code'           => $validated['lecturer_code'],
                'lecturer_code_2'         => $validated['lecturer_code_2'] ?? null,
                'assessment_type'         => $validated['assessment_type'],
                'execution_method'        => $validated['execution_method'],
                'execution_time'          => $validated['execution_time'],
                'participant_division'    => $validated['participant_division'],
                'question_type'           => $validated['question_type'],
                'answer_sheet_type'       => $validated['answer_sheet_type'],
                'needs_scratch_paper'     => $validated['needs_scratch_paper'],
                'proctor_type'            => $validated['proctor_type'],
                'exam_question_url'       => $validated['exam_question_url'] ?? null,
                'custom_answer_sheet_url' => $validated['custom_answer_sheet_url'] ?? null,
                'exam_rules'              => $validated['exam_rules'] ?? null,
            ];

            if ($validated['mode'] === 'single') {
                $rooms = $validated['rooms'] ?? [];
                if (empty($rooms)) {
                    $scheduleData = array_merge($commonFields, [
                        'course_id'      => $validated['course_id'],
                        'course_class'   => $validated['course_class'],
                        'exam_date'      => $validated['date'] ?? null,
                        'start_time'     => $validated['start_time'] ?? null,
                        'end_time'       => $validated['end_time'] ?? null,
                        'room_1'         => null,
                        'room_2'         => null,
                        'proctor_name_1' => $validated['proctor_name_1'] ?? null,
                        'proctor_name_2' => null,
                    ]);
                    $schedule = $schedulingService->processSchedule($scheduleData);
                    if ($schedule->status === 'Draft - Conflict') {
                        $hasConflicts = true;
                    }
                } else {
                    foreach ($rooms as $index => $roomCode) {
                        if (!$roomCode) continue;

                        $proctorName = ($index === 0)
                            ? ($validated['proctor_name_1'] ?? null)
                            : ($validated['proctor_name_2'] ?? null);

                        $scheduleData = array_merge($commonFields, [
                            'course_id'      => $validated['course_id'],
                            'course_class'   => $validated['course_class'],
                            'exam_date'      => $validated['date'] ?? null,
                            'start_time'     => $validated['start_time'] ?? null,
                            'end_time'       => $validated['end_time'] ?? null,
                            'room_1'         => $roomCode,
                            'room_2'         => null,
                            'proctor_name_1' => $proctorName,
                            'proctor_name_2' => null,
                        ]);
                        $schedule = $schedulingService->processSchedule($scheduleData);
                        if ($schedule->status === 'Draft - Conflict') {
                            $hasConflicts = true;
                        }
                    }
                }
            } else {
                foreach ($validated['kelas'] as $k) {
                    $rooms = $k['rooms'] ?? [];
                    if (empty($rooms)) {
                        $scheduleData = array_merge($commonFields, [
                            'course_id'      => $k['course_id'],
                            'course_class'   => $k['course_class'],
                            'exam_date'      => $k['date'] ?? null,
                            'start_time'     => $k['start_time'] ?? null,
                            'end_time'       => $k['end_time'] ?? null,
                            'room_1'         => null,
                            'room_2'         => null,
                            'proctor_name_1' => null,
                            'proctor_name_2' => null,
                        ]);
                        $schedule = $schedulingService->processSchedule($scheduleData);
                        if ($schedule->status === 'Draft - Conflict') {
                            $hasConflicts = true;
                        }
                    } else {
                        foreach ($rooms as $roomCode) {
                            if (!$roomCode) continue;
                            $scheduleData = array_merge($commonFields, [
                                'course_id'      => $k['course_id'],
                                'course_class'   => $k['course_class'],
                                'exam_date'      => $k['date'] ?? null,
                                'start_time'     => $k['start_time'] ?? null,
                                'end_time'       => $k['end_time'] ?? null,
                                'room_1'         => $roomCode,
                                'room_2'         => null,
                                'proctor_name_1' => null,
                                'proctor_name_2' => null,
                            ]);
                            $schedule = $schedulingService->processSchedule($scheduleData);
                            if ($schedule->status === 'Draft - Conflict') {
                                $hasConflicts = true;
                            }
                        }
                    }
                }
            }
        });

        if ($hasConflicts) {
            return redirect()->route('exam_schedules.index')
                ->with('success', 'Jadwal ujian berhasil ditambahkan dengan beberapa catatan bentrok.')
                ->with('warning', 'Terdapat bentrok pada jadwal yang baru ditambahkan.');
        }

        return redirect()->route('exam_schedules.index')
            ->with('success', 'Jadwal ujian berhasil ditambahkan.');
    }

    /**
     * Form edit jadwal ujian.
     */
    public function edit(ExamSchedule $exam_schedule)
    {
        $activePeriod = AcademicPeriod::current();
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

        $courses = Course::query()
            ->withCount(['studentCourses' => function ($query) use ($activeAcademicYear, $activeSemester) {
                $query->where('academic_year', $activeAcademicYear)
                      ->where('semester', $activeSemester);
            }])
            ->get();

        $prodis = Course::where('status', 'Aktif')
            ->whereNotNull('study_program')
            ->distinct()
            ->orderBy('study_program')
            ->pluck('study_program');

        $rooms = Room::where('status', 'Dapat Dipakai')
            ->orderBy('room_code')
            ->get();

        $exam_schedule->load('course');

        return view('exam_schedules.edit', [
            'schedule' => $exam_schedule,
            'prodis'   => $prodis,
            'rooms'    => $rooms,
            'courses'  => $courses,
        ]);
    }

    /**
     * Update jadwal ujian.
     */
    public function update(UpdateExamScheduleRequest $request, ExamSchedule $exam_schedule, ExamSchedulingService $service)
    {
        $schedule = $service->processSchedule($request->validated(), $exam_schedule->id);

        if ($schedule->status === 'Draft - Conflict') {
            return redirect()->route('exam_schedules.index')
                ->with('warning', 'Jadwal ujian berhasil diperbarui sebagai Draft karena terdapat konflik.')
                ->with('error_conflict', $schedule->conflict_notes);
        }

        return redirect()->route('exam_schedules.index')
            ->with('success', 'Jadwal ujian berhasil diperbarui.');
    }

    /**
     * Hapus jadwal ujian.
     */
    public function destroy(ExamSchedule $exam_schedule)
    {
        $exam_schedule->delete();

        return redirect()->route('exam_schedules.index')
            ->with('success', 'Jadwal ujian berhasil dihapus.');
    }

    /**
     * Detail jadwal ujian.
     */
    public function show(ExamSchedule $exam_schedule)
    {
        $exam_schedule->load(['course', 'lecturer', 'lecturer2']);

        $course = $exam_schedule->course;
        $studyProgram = '-';
        if ($course) {
            // 1. Try to get study program from student courses (where it's not null, empty, or 'umum')
            $studyProgram = StudentCourse::where('course_code', $course->course_code)
                ->whereNotNull('study_program')
                ->where('study_program', '!=', '')
                ->whereRaw("LOWER(study_program) != 'umum'")
                ->value('study_program');

            // 2. Fallback: check students taking this course
            if (!$studyProgram) {
                $firstStudentCourse = StudentCourse::where('course_code', $course->course_code)
                    ->whereHas('student', function ($query) {
                        $query->whereNotNull('study_program')
                              ->where('study_program', '!=', '')
                              ->whereRaw("LOWER(study_program) != 'umum'");
                    })
                    ->first();
                if ($firstStudentCourse) {
                    $studyProgram = $firstStudentCourse->student->study_program;
                }
            }

            if (empty($studyProgram) || strtolower($studyProgram) === 'umum') {
                $studyProgram = $course->study_program;
            }
        }

        if (strtolower($studyProgram) === 'umum') {
            $studyProgram = 'Umum';
        } elseif (empty($studyProgram)) {
            $studyProgram = '-';
        }

        return view('exam_schedules.show', [
            'schedule'     => $exam_schedule,
            'studyProgram' => $studyProgram,
        ]);
    }

    /**
     * Export PDF daftar hadir ujian.
     */
    public function exportPdf($exam_schedule)
    {
        $schedule = $exam_schedule instanceof ExamSchedule 
            ? $exam_schedule 
            : ExamSchedule::findOrFail($exam_schedule);

        $schedule->load(['course', 'lecturer', 'lecturer2', 'room', 'room2']);

        $course = $schedule->course;
        $courseCode = $course->course_code ?? null;

        $students = collect();
        if ($courseCode) {
            $students = Student::select('students.student_number', 'students.name')
                ->join('student_courses', 'students.student_number', '=', 'student_courses.student_number')
                ->where('student_courses.course_code', $courseCode)
                ->where('student_courses.course_class', $course->class)
                ->when($course->academic_period_id, function($q) use ($course) {
                    $q->where('student_courses.academic_period_id', $course->academic_period_id);
                })
                ->orderBy('students.student_number', 'asc')
                ->distinct()
                ->get();
        }

        $pdf = Pdf::loadView('exports.exam-attendance', [
            'schedule'   => $schedule,
            'students'   => $students,
            'printedAt'  => now()->locale('id')->isoFormat('DD MMMM YYYY, HH:mm'),
        ]);

        $pdf->setPaper('a4', 'portrait');

        $filename = 'Daftar_Hadir_Ujian_'
            . ($course->course_code ?? 'UNKNOWN') . '_'
            . ($course->class ?? 'X') . '_'
            . ($schedule->date ? $schedule->date->format('Ymd') : 'TBD')
            . '.pdf';

        return $pdf->download($filename);
    }


    public function getCourseData(Request $request)
    {
        $request->validate([
            'kode_mk' => 'required|string',
            'prodi'   => 'nullable|string',
            'class'   => 'nullable|string',
        ]);

        $course = Course::where('status', 'Aktif')
            ->where('course_code', $request->kode_mk)
            ->when($request->prodi, fn($q) => $q->where('study_program', $request->prodi))
            ->first();

        // Fallback: match after removing common degree prefixes from prodi (e.g. S1, D3)
        if (!$course && $request->prodi) {
            $cleanProdi = preg_replace('/^(s1)\s+/i', '', trim($request->prodi));
            $course = Course::where('status', 'Aktif')
                ->where('course_code', $request->kode_mk)
                ->where(function ($q) use ($request, $cleanProdi) {
                    $q->where('study_program', 'like', "%{$cleanProdi}%")
                      ->orWhere('study_program', 'like', "%" . trim($request->prodi) . "%");
                })
                ->first();
        }

        // Final fallback: ignore prodi filter entirely if no match is found with it
        if (!$course) {
            $course = Course::where('status', 'Aktif')
                ->where('course_code', $request->kode_mk)
                ->first();
        }

        if (!$course) {
            return response()->json([
                'success' => false,
                'message' => 'Mata kuliah dengan kode tersebut tidak ditemukan.'
            ], 404);
        }

        $activePeriod = AcademicPeriod::current();
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

        $requestedProdi = $request->prodi;

        // 1. Try to get study program from student courses (where it's not null, empty, or 'umum')
        $studyProgram = StudentCourse::where('course_code', $course->course_code)
            ->whereNotNull('study_program')
            ->where('study_program', '!=', '')
            ->whereRaw("LOWER(study_program) != 'umum'")
            ->value('study_program');

        // 2. Fallback: check students taking this course
        if (!$studyProgram) {
            $firstStudentCourse = StudentCourse::where('course_code', $course->course_code)
                ->whereHas('student', function ($query) {
                    $query->whereNotNull('study_program')
                          ->where('study_program', '!=', '')
                          ->whereRaw("LOWER(study_program) != 'umum'");
                })
                ->first();
            if ($firstStudentCourse) {
                $studyProgram = $firstStudentCourse->student->study_program;
            }
        }

        // 3. Fallback to requested prodi if the course is general or resolved prodi is empty/umum
        if (empty($studyProgram) || strtolower($studyProgram) === 'umum') {
            $studyProgram = $requestedProdi ?: $course->study_program;
        }

        // If the course is a general course (i.e. 'Umum' in course master) and the user has selected a specific prodi,
        // we should respect their selection.
        if (strtolower($course->study_program) === 'umum' && $requestedProdi) {
            $studyProgram = $requestedProdi;
        }

        if (strtolower($studyProgram) === 'umum') {
            $studyProgram = 'Umum';
        } elseif (empty($studyProgram)) {
            $studyProgram = '-';
        }

        $course->loadCount(['studentCourses' => function ($query) use ($activeAcademicYear, $activeSemester, $studyProgram, $request) {
            $query->where('academic_year', $activeAcademicYear)
                  ->where('semester', $activeSemester);

            if ($request->class) {
                $query->where('course_class', $request->class);
            }

            if ($studyProgram && strtolower($studyProgram) !== 'umum' && $studyProgram !== '-') {
                $query->where(function ($q) use ($studyProgram) {
                    $q->where('study_program', $studyProgram)
                      ->orWhereHas('student', function ($sq) use ($studyProgram) {
                          $sq->where('study_program', $studyProgram);
                      });
                });
            }
        }]);

        return response()->json([
            'success'                 => true,
            'id'                      => $course->id,
            'prodi'                   => $studyProgram,
            'nama_mk'                 => $course->name,
            'kelas'                   => $request->class ?: $course->class,
            'enrolled_students_count' => $course->student_courses_count,
        ]);
    }

    /**
     * Check real-time room availability.
     * GET /exam-schedules/check-availability
     */
    public function checkAvailability(Request $request)
    {
        $request->validate([
            'exam_date'       => 'nullable|date',
            'start_time'      => 'nullable|date_format:H:i',
            'end_time'        => 'nullable|date_format:H:i|after:start_time',
            'room_1'          => 'nullable|string',
            'room_2'          => 'nullable|string',
            'course_id'       => 'nullable|exists:courses,id',
            'lecturer_code'   => 'nullable|string|max:20',
            'lecturer_code_2' => 'nullable|string|max:20',
            'exclude_id'      => 'nullable|integer',
            'assessment_type' => 'nullable|string',
        ]);

        $assessmentType = $request->assessment_type;
        $examDate = $request->exam_date;
        $startTime = $request->start_time;
        $endTime = $request->end_time;
        $room1 = $request->room_1;
        $room2 = $request->room_2;
        $lecturerCode = $request->lecturer_code;
        $lecturerCode2 = $request->lecturer_code_2;
        $courseId = $request->course_id;
        $excludeId = $request->exclude_id;

        $response = [
            'available'          => true,
            'message'            => 'Ruangan tersedia dan tidak ada konflik jadwal.',
            'room_available'     => true,
            'room_message'       => '',
            'lecturer_available' => true,
            'lecturer_message'   => '',
            'student_available'  => true,
            'student_message'    => '',
        ];

        if ($assessmentType === 'TIDAK ADA ASSESSMEN BERSAMA' || !$examDate || !$startTime || !$endTime) {
            return response()->json($response);
        }

        // 1. Check room conflict
        $rooms = array_filter([$room1, $room2]);
        if (!empty($rooms)) {
            $conflictRoom = ExamSchedule::where('status', 'Approved')
                ->whereDate('exam_date', $examDate)
                ->where(function ($q) use ($rooms) {
                    $q->whereIn('room_1', $rooms)
                      ->orWhereIn('room_2', $rooms);
                })
                ->where('start_time', '<', $endTime)
                ->where('end_time', '>', $startTime)
                ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
                ->with('course')
                ->first();

            if ($conflictRoom) {
                $confRoomCode = in_array($conflictRoom->room_1, $rooms) ? $conflictRoom->room_1 : $conflictRoom->room_2;
                $response['available'] = false;
                $response['room_available'] = false;
                $response['room_message'] = sprintf(
                    'Ruangan %s sudah digunakan oleh mata kuliah %s (%s) pada jam %s - %s.',
                    $confRoomCode,
                    $conflictRoom->course->name ?? '-',
                    $conflictRoom->course->course_code ?? '-',
                    \Carbon\Carbon::parse($conflictRoom->start_time)->format('H:i'),
                    \Carbon\Carbon::parse($conflictRoom->end_time)->format('H:i')
                );
                $response['message'] = $response['room_message'];
            } else {
                $response['room_message'] = '';
            }
        }

        // 2. Check lecturer conflict
        $newLecturers = array_filter([$lecturerCode, $lecturerCode2]);
        if (!empty($newLecturers)) {
            $conflictLecturer = ExamSchedule::where('status', 'Approved')
                ->whereDate('exam_date', $examDate)
                ->where('start_time', '<', $endTime)
                ->where('end_time', '>', $startTime)
                ->where(function ($q) use ($newLecturers) {
                    $q->whereIn('lecturer_code', $newLecturers)
                      ->orWhereIn('lecturer_code_2', $newLecturers);
                })
                ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
                ->with('course')
                ->first();

            if ($conflictLecturer) {
                $conflictLecturersList = array_intersect(
                    $newLecturers,
                    array_filter([$conflictLecturer->lecturer_code, $conflictLecturer->lecturer_code_2])
                );
                $msg = sprintf(
                    'Dosen %s memiliki jadwal mengajar/mengawas ujian matakuliah %s (%s) pada jam %s - %s.',
                    implode(', ', $conflictLecturersList),
                    $conflictLecturer->course->name ?? '-',
                    $conflictLecturer->course->course_code ?? '-',
                    \Carbon\Carbon::parse($conflictLecturer->start_time)->format('H:i'),
                    \Carbon\Carbon::parse($conflictLecturer->end_time)->format('H:i')
                );
                $response['available'] = false;
                $response['lecturer_available'] = false;
                $response['lecturer_message'] = $msg;
                if ($response['message'] === 'Ruangan tersedia dan tidak ada konflik jadwal.') {
                    $response['message'] = $msg;
                }
            }
        }

        // 3. Check student-level course overlaps
        if ($courseId) {
            $course = Course::find($courseId);
            if ($course) {
                $courseCode = $course->course_code;

                $studentOverlap = ExamSchedule::where('status', 'Approved')
                    ->whereDate('exam_date', $examDate)
                    ->where('start_time', '<', $endTime)
                    ->where('end_time', '>', $startTime)
                    ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
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
                    ->first();

                if ($studentOverlap) {
                    $overlappingStudentsCount = DB::table('student_courses as sc1')
                        ->join('student_courses as sc2', 'sc1.student_number', '=', 'sc2.student_number')
                        ->where('sc1.course_code', $studentOverlap->course->course_code)
                        ->where('sc2.course_code', $courseCode)
                        ->count();

                    $msg = sprintf(
                        'Tabrakan jadwal mahasiswa! Terdapat %d mahasiswa yang mengambil mata kuliah ini dan juga %s (%s) yang dijadwalkan pada jam %s - %s.',
                        $overlappingStudentsCount,
                        $studentOverlap->course->name ?? '-',
                        $studentOverlap->course->course_code ?? '-',
                        \Carbon\Carbon::parse($studentOverlap->start_time)->format('H:i'),
                        \Carbon\Carbon::parse($studentOverlap->end_time)->format('H:i')
                    );
                    $response['available'] = false;
                    $response['student_available'] = false;
                    $response['student_message'] = $msg;
                    if ($response['message'] === 'Ruangan tersedia dan tidak ada konflik jadwal.') {
                        $response['message'] = $msg;
                    }
                }
            }
        }

        return response()->json($response);
    }

    public function downloadTemplate()
    {
        $template = collect([[
            'Program Studi' => 'S1 Informatika',
            'Kode MK' => 'CSG2A3',
            'Nama MK' => 'Algoritma dan Pemrograman',
            'Kelas' => 'IF-43-01',
            'Jumlah Mahasiswa' => '35',
            'Kode Dosen 1' => 'BDI',
            'Kode Dosen 2' => '',
            'Jenis Assesment' => 'MENGERJAKAN SOAL UJIAN',
            'Metode Pelaksanaan' => 'DIKERJAKAN ONSITE DI KAMPUS',
            'Waktu Pelaksanaan' => 'SESUAI JADWAL KELAS PERKULIAHAN',
            'Pembagian Peserta Ujian' => 'SEMUA PESERTA DIGABUNG DALAM 1 RUANGAN',
            'Soal Ujian' => 'PERLU PENGGANDAAN FISIK (HARDCOPY)',
            'Lembar Jawaban' => 'MENGGUNAKAN LEMBAR JAWABAN STANDAR AKADEMIK',
            'Kebutuhan Kertas Buram' => 'TIDAK PERLU',
            'Pengawal' => 'DOSEN PENGAMPU',
            'Jadwal Ujian Serentak se-TUNC' => '22/06/2026',
            'URL Soal Ujian' => 'https://drive.google.com/example-soal',
            'URL Lembar Jawaban Custom' => '',
            'Aturan Ujian' => 'Open Book, boleh bawa kalkulator',
        ]]);

        return (new FastExcel($template))->download(
            'template-import-jadwal-ujian.xlsx',
            fn($row) => $row
        );
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:2048'],
        ]);

        $imported = 0;
        $skipped  = [];

        try {
            (new FastExcel)->import($request->file('file'), function ($row) use (&$imported, &$skipped) {
                $clean = function($val) {
                    if (is_string($val)) {
                        $val = preg_replace('/^\p{Z}+|\p{Z}+$/u', '', $val);
                        $val = str_replace("\xC2\xA0", '', $val);
                        $val = trim($val);
                    }
                    return $val === '' ? null : $val;
                };

                $prodi = null;
                $kodeMk = null;
                $namaMk = null;
                $kelas = null;
                $kodeDosen1 = null;
                $kodeDosen2 = null;
                $jenisAssessmentRaw = null;
                $metodePelaksanaanRaw = null;
                $waktuPelaksanaanRaw = null;
                $pembagianPesertaRaw = null;
                $soalUjianRaw = null;
                $lembarJawabanRaw = null;
                $kertasBuramRaw = null;
                $pengawalRaw = null;
                $jadwalSerentakRaw = null;
                $urlSoal = null;
                $urlLembarCustom = null;
                $aturanUjian = null;

                foreach ($row as $key => $value) {
                    $cleanKey = str_replace([' ', '-', '_', '/'], '', strtolower(trim($key)));
                    $cleanVal = $clean($value);
                    
                    if (str_contains($cleanKey, 'url') && str_contains($cleanKey, 'soal')) {
                        $urlSoal = $cleanVal;
                    } elseif (str_contains($cleanKey, 'url') && (str_contains($cleanKey, 'lembar') || str_contains($cleanKey, 'custom'))) {
                        $urlLembarCustom = $cleanVal;
                    } elseif (str_contains($cleanKey, 'aturan')) {
                        $aturanUjian = $cleanVal;
                    } elseif (str_contains($cleanKey, 'program')) {
                        $prodi = $cleanVal;
                    } elseif (str_contains($cleanKey, 'kodemk') || (str_contains($cleanKey, 'kode') && str_contains($cleanKey, 'mk'))) {
                        $kodeMk = $cleanVal;
                    } elseif (str_contains($cleanKey, 'namamk') || (str_contains($cleanKey, 'nama') && str_contains($cleanKey, 'mk'))) {
                        $namaMk = $cleanVal;
                    } elseif (str_contains($cleanKey, 'kelas')) {
                        $kelas = $cleanVal;
                    } elseif (str_contains($cleanKey, 'dosen')) {
                        if (str_contains($cleanKey, '2') || str_contains($cleanKey, 'two')) {
                            $kodeDosen2 = $cleanVal;
                        } else {
                            $kodeDosen1 = $cleanVal;
                        }
                    } elseif (str_contains($cleanKey, 'assessment') || str_contains($cleanKey, 'assesment')) {
                        $jenisAssessmentRaw = $cleanVal;
                    } elseif (str_contains($cleanKey, 'metode')) {
                        $metodePelaksanaanRaw = $cleanVal;
                    } elseif (str_contains($cleanKey, 'waktu')) {
                        $waktuPelaksanaanRaw = $cleanVal;
                    } elseif (str_contains($cleanKey, 'pembagian') || str_contains($cleanKey, 'peserta')) {
                        $pembagianPesertaRaw = $cleanVal;
                    } elseif (str_contains($cleanKey, 'soal') && !str_contains($cleanKey, 'url')) {
                        $soalUjianRaw = $cleanVal;
                    } elseif (str_contains($cleanKey, 'lembar') && !str_contains($cleanKey, 'custom') && !str_contains($cleanKey, 'url')) {
                        $lembarJawabanRaw = $cleanVal;
                    } elseif (str_contains($cleanKey, 'kertas') || str_contains($cleanKey, 'buram')) {
                        $kertasBuramRaw = $cleanVal;
                    } elseif (str_contains($cleanKey, 'pengawal') || str_contains($cleanKey, 'pengawas')) {
                        $pengawalRaw = $cleanVal;
                    } elseif (
                        str_contains($cleanKey, 'serentak') ||
                        str_contains($cleanKey, 'tunc') ||
                        str_contains($cleanKey, 'tanggal') ||
                        str_contains($cleanKey, 'tgl') ||
                        (str_contains($cleanKey, 'jadwal') && str_contains($cleanKey, 'ujian'))
                    ) {
                        $jadwalSerentakRaw = $cleanVal;
                    }
                }

                if (empty($kodeMk)) {
                    $skipped[] = "Baris " . ($imported + count($skipped) + 2) . ": Kode MK tidak diisi.";
                    return null;
                }

                $course = Course::where('status', 'Aktif')
                    ->where('course_code', $kodeMk)
                    ->when($prodi, fn($q) => $q->where('study_program', $prodi))
                    ->first();

                // Fallback: match after removing common degree prefixes from prodi (e.g. S1, D3)
                if (!$course && $prodi) {
                    $cleanProdi = preg_replace('/^(s1|d3|d4|s2)\s+/i', '', trim($prodi));
                    $course = Course::where('status', 'Aktif')
                        ->where('course_code', $kodeMk)
                        ->where(function ($q) use ($prodi, $cleanProdi) {
                            $q->where('study_program', 'like', "%{$cleanProdi}%")
                              ->orWhere('study_program', 'like', "%" . trim($prodi) . "%");
                        })
                        ->first();
                }

                // Final fallback: ignore prodi filter entirely if no match is found with it
                if (!$course) {
                    $course = Course::where('status', 'Aktif')
                        ->where('course_code', $kodeMk)
                        ->first();
                }

                if (!$course) {
                    $skipped[] = "Baris " . ($imported + count($skipped) + 2) . ": Mata kuliah dengan kode '{$kodeMk}' tidak ditemukan atau tidak aktif.";
                    return null;
                }

                $normalizeOption = function ($val, array $mappings, $default = 'TIDAK ADA ASSESSMEN BERSAMA') {
                    if (empty($val)) return $default;
                    $valClean = strtolower(trim($val));
                    foreach ($mappings as $pattern => $value) {
                        if (str_contains($valClean, $pattern)) {
                            return $value;
                        }
                    }
                    return $default;
                };

                $assessmentType = $normalizeOption($jenisAssessmentRaw, [
                    'tidak ada' => 'TIDAK ADA ASSESSMEN BERSAMA',
                    'lisan' => 'UJIAN LISAN / PRESENTASI / PROJECT BASED',
                    'presentasi' => 'UJIAN LISAN / PRESENTASI / PROJECT BASED',
                    'project' => 'UJIAN LISAN / PRESENTASI / PROJECT BASED',
                    'soal' => 'MENGERJAKAN SOAL UJIAN',
                    'ujian' => 'MENGERJAKAN SOAL UJIAN',
                ], 'TIDAK ADA ASSESSMEN BERSAMA');

                $executionMethod = $normalizeOption($metodePelaksanaanRaw, [
                    'tidak ada' => 'TIDAK ADA ASSESSMEN BERSAMA',
                    'onsite' => 'DIKERJAKAN ONSITE DI KAMPUS',
                    'di kampus' => 'DIKERJAKAN ONSITE DI KAMPUS',
                    'kampus' => 'DIKERJAKAN ONSITE DI KAMPUS',
                    'take home' => 'TAKE HOME ATAU DIKERJAKAN FROM ANYWHERE',
                    'anywhere' => 'TAKE HOME ATAU DIKERJAKAN FROM ANYWHERE',
                    'online' => 'TAKE HOME ATAU DIKERJAKAN FROM ANYWHERE',
                ], 'TIDAK ADA ASSESSMEN BERSAMA');

                $executionTime = $normalizeOption($waktuPelaksanaanRaw, [
                    'tidak ada' => 'TIDAK ADA ASSESSMEN BERSAMA',
                    'sesuai jadwal' => 'SESUAI JADWAL KELAS PERKULIAHAN',
                    'jadwal kelas' => 'SESUAI JADWAL KELAS PERKULIAHAN',
                    'se-tus' => 'JADWAL SERENTAK se-TUS per MK Prodi',
                    'se-tunc' => 'JADWAL SERENTAK se-TUNC',
                ], 'TIDAK ADA ASSESSMEN BERSAMA');

                $participantDivision = $normalizeOption($pembagianPesertaRaw, [
                    'tidak ada' => 'TIDAK ADA ASSESSMEN BERSAMA',
                    'dibagi 2' => 'DIBAGI 2 (JIKA PESERTA KELAS > 30)',
                    'bagi 2' => 'DIBAGI 2 (JIKA PESERTA KELAS > 30)',
                    'digabung' => 'SEMUA PESERTA DIGABUNG DALAM 1 RUANGAN',
                    '1 ruangan' => 'SEMUA PESERTA DIGABUNG DALAM 1 RUANGAN',
                    'gabung' => 'SEMUA PESERTA DIGABUNG DALAM 1 RUANGAN',
                ], 'TIDAK ADA ASSESSMEN BERSAMA');

                $questionType = $normalizeOption($soalUjianRaw, [
                    'tidak ada' => 'TIDAK ADA ASSESSMEN BERSAMA',
                    'hardcopy' => 'PERLU PENGGANDAAN FISIK (HARDCOPY)',
                    'penggandaan' => 'PERLU PENGGANDAAN FISIK (HARDCOPY)',
                    'lms' => 'SOAL TERSEDIA DI LMS',
                ], 'TIDAK ADA ASSESSMEN BERSAMA');

                $answerSheetType = $normalizeOption($lembarJawabanRaw, [
                    'tidak ada' => 'TIDAK ADA ASSESSMEN BERSAMA',
                    'standar' => 'MENGGUNAKAN LEMBAR JAWABAN STANDAR AKADEMIK',
                    'akademik' => 'MENGGUNAKAN LEMBAR JAWABAN STANDAR AKADEMIK',
                    'custom' => 'LEMBAR JAWABAN CUSTOM (PERLU PENGGANDAAN)',
                    'tidak perlu' => 'TIDAK PERLU LEMBAR JAWABAN',
                ], 'TIDAK ADA ASSESSMEN BERSAMA');

                $needsScratchPaper = $normalizeOption($kertasBuramRaw, [
                    'perlu' => 'PERLU',
                    'ya' => 'PERLU',
                    'yes' => 'PERLU',
                    'tidak' => 'TIDAK PERLU',
                ], 'TIDAK PERLU');

                $proctorType = $normalizeOption($pengawalRaw, [
                    'tidak ada' => 'TIDAK ADA ASSESSMEN BERSAMA',
                    'dosen pengampu' => 'DOSEN PENGAMPU',
                    'asprak' => 'ASPRAK / ASLAB / ASDOS (PRODI SETOR NAMA)',
                    'aslab' => 'ASPRAK / ASLAB / ASDOS (PRODI SETOR NAMA)',
                    'asdos' => 'ASPRAK / ASLAB / ASDOS (PRODI SETOR NAMA)',
                    'tlh' => 'TLH / STUDENT STAFF UNIT (DICARIKAN AKADEMIK)',
                    'student staff' => 'TLH / STUDENT STAFF UNIT (DICARIKAN AKADEMIK)',
                    'tidak perlu' => 'TIDAK PERLU PENGAWAS',
                ], 'TIDAK ADA ASSESSMEN BERSAMA');

                // Parsing Date from Jadwal Ujian Serentak se-TUNC
                $examDateStr = $jadwalSerentakRaw;
                $examDate = null;
                if (!empty($examDateStr)) {
                    if ($examDateStr instanceof \DateTimeInterface) {
                        $examDate = $examDateStr->format('Y-m-d');
                    } else {
                        $monthsIndo = [
                            'januari' => 'january', 'februari' => 'february', 'maret' => 'march', 'april' => 'april',
                            'mei' => 'may', 'juni' => 'june', 'juli' => 'july', 'agustus' => 'august',
                            'september' => 'september', 'oktober' => 'october', 'november' => 'november', 'desember' => 'december',
                            'jan' => 'january', 'feb' => 'february', 'mar' => 'march', 'apr' => 'april',
                            'jun' => 'june', 'jul' => 'july', 'agu' => 'august', 'sep' => 'september',
                            'okt' => 'october', 'nov' => 'november', 'des' => 'december'
                        ];

                        $tempStr = strtolower(trim((string)$examDateStr));
                        
                        if (preg_match('/^\d{1,2}[\/\-.]\d{1,2}[\/\-.](\d{2}|\d{4})$/', $tempStr)) {
                            $parts = preg_split('/[\/\-.]+/', $tempStr);
                            if (count($parts) === 3) {
                                $year = $parts[2];
                                if (strlen($year) === 2) {
                                    $year = '20' . $year;
                                }
                                $tempStr = sprintf('%04d-%02d-%02d', $year, $parts[1], $parts[0]);
                            }
                        } else {
                            foreach ($monthsIndo as $indo => $eng) {
                                $tempStr = str_replace($indo, $eng, $tempStr);
                            }
                        }

                        try {
                            $parsedDate = \Carbon\Carbon::parse($tempStr);
                            if ($parsedDate && $parsedDate->year > 2000 && $parsedDate->year < 2100) {
                                $examDate = $parsedDate->format('Y-m-d');
                            }
                        } catch (\Exception $e) {
                            // Date parsing failed
                        }
                    }
                }

                 $data = [
                    'course_id' => $course->id,
                    'lecturer_code' => $kodeDosen1,
                    'lecturer_code_2' => $kodeDosen2,
                    'exam_date' => $examDate,
                    'start_time' => null,
                    'end_time' => null,
                    'room_1' => null,
                    'room_2' => null,
                    'proctor_name_1' => null,
                    'proctor_name_2' => null,
                    'assessment_type' => $assessmentType,
                    'execution_method' => $executionMethod,
                    'execution_time' => $executionTime,
                    'participant_division' => $participantDivision,
                    'question_type' => $questionType,
                    'answer_sheet_type' => $answerSheetType,
                    'needs_scratch_paper' => $needsScratchPaper,
                    'proctor_type' => $proctorType,
                    'exam_question_url' => $urlSoal,
                    'custom_answer_sheet_url' => $urlLembarCustom,
                    'exam_rules' => $aturanUjian,
                ];

                $validator = \Illuminate\Support\Facades\Validator::make($data, [
                    'course_id'              => ['required', 'exists:courses,id'],
                    'lecturer_code'          => ['required', 'string', 'max:20'],
                    'lecturer_code_2'        => ['nullable', 'string', 'max:20'],
                    'exam_date'              => ['nullable', 'date'],
                    'assessment_type'        => ['required', 'string', 'in:TIDAK ADA ASSESSMEN BERSAMA,MENGERJAKAN SOAL UJIAN,UJIAN LISAN / PRESENTASI / PROJECT BASED'],
                    'execution_method'       => ['required', 'string', 'in:TIDAK ADA ASSESSMEN BERSAMA,DIKERJAKAN ONSITE DI KAMPUS,TAKE HOME ATAU DIKERJAKAN FROM ANYWHERE'],
                    'execution_time'         => ['required', 'string', 'in:TIDAK ADA ASSESSMEN BERSAMA,SESUAI JADWAL KELAS PERKULIAHAN,JADWAL SERENTAK se-TUS per MK Prodi,JADWAL SERENTAK se-TUNC'],
                    'participant_division'   => ['required', 'string', 'in:TIDAK ADA ASSESSMEN BERSAMA,DIBAGI 2 (JIKA PESERTA KELAS > 30),SEMUA PESERTA DIGABUNG DALAM 1 RUANGAN'],
                    'question_type'          => ['required', 'string', 'in:TIDAK ADA ASSESSMEN BERSAMA,PERLU PENGGANDAAN FISIK (HARDCOPY),SOAL TERSEDIA DI LMS'],
                    'answer_sheet_type'      => ['required', 'string', 'in:TIDAK ADA ASSESSMEN BERSAMA,MENGGUNAKAN LEMBAR JAWABAN STANDAR AKADEMIK,LEMBAR JAWABAN CUSTOM (PERLU PENGGANDAAN),TIDAK PERLU LEMBAR JAWABAN'],
                    'needs_scratch_paper'    => ['required', 'string', 'in:PERLU,TIDAK PERLU'],
                    'proctor_type'           => ['required', 'string', 'in:TIDAK ADA ASSESSMEN BERSAMA,DOSEN PENGAMPU,ASPRAK / ASLAB / ASDOS (PRODI SETOR NAMA),TLH / STUDENT STAFF UNIT (DICARIKAN AKADEMIK),TIDAK PERLU PENGAWAS'],
                    'exam_question_url'      => ['nullable', 'url', 'max:2048'],
                    'custom_answer_sheet_url'=> ['nullable', 'url', 'max:2048'],
                    'exam_rules'             => ['nullable', 'string', 'max:1000'],
                ]);

                if ($validator->fails()) {
                    $errorsList = implode(', ', $validator->errors()->all());
                    $skipped[] = "Baris " . ($imported + count($skipped) + 2) . ": Validasi gagal - " . $errorsList;
                    return null;
                }

                $service = app(ExamSchedulingService::class);
                $existing = ExamSchedule::where('course_id', $course->id)->first();
                $excludeId = $existing ? $existing->id : null;
                $service->processSchedule($data, $excludeId);

                $imported++;
            });
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal memproses file Excel: ' . $e->getMessage());
        }

        $msg = "Import selesai: {$imported} jadwal ujian berhasil diimport/diperbarui.";
        if (count($skipped) > 0) {
            $msg .= " " . count($skipped) . " baris dilewati.";
            return redirect()->route('exam_schedules.index')
                ->with('success', $msg)
                ->with('warning', 'Beberapa baris dilewati saat import:')
                ->with('error_conflict', implode("\n", $skipped));
        }

        return redirect()->route('exam_schedules.index')->with('success', $msg);
    }
}
