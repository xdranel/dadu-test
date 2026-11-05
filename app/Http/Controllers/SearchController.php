<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Lecturer;
use App\Models\AcademicPeriod;
use App\Models\StudentCourse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Search courses by code or name.
     * GET /api/search/courses
     */
    public function courses(Request $request)
    {
        $q = $request->query('q');

        if (!$q || strlen($q) < 2) {
            return response()->json([]);
        }

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
            ->where(function ($query) use ($q) {
                $query->where('course_code', 'like', "%{$q}%")
                      ->orWhere('name', 'like', "%{$q}%");
            })
            ->limit(10)
            ->get()
            ->map(function ($course) use ($activeYear, $activeSemester) {
                $kelasData = StudentCourse::where('course_code', $course->course_code)
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

                if ($kelasData->isEmpty()) {
                    $kelasData = collect([[
                        'course_id'    => $course->id,
                        'course_class' => $course->class ?: 'Umum',
                        'class'        => $course->class ?: 'Umum',
                        'mhs_count'    => 0,
                        'room_count'   => 1,
                    ]]);
                }

                return [
                    'id'            => $course->id,
                    'course_code'   => $course->course_code,
                    'name'          => $course->name,
                    'credits'       => $course->credits,
                    'study_program' => $course->study_program,
                    'total_mhs'     => (int) $kelasData->sum('mhs_count'),
                    'kelas'         => $kelasData,
                ];
            })
            ->filter(fn($c) => count($c['kelas']) > 0)
            ->values();

        return response()->json($courses);
    }

    /**
     * Search lecturers by code or name.
     * GET /api/search/lecturers
     */
    public function lecturers(Request $request)
    {
        $q = $request->query('q');

        if (!$q || strlen($q) < 2) {
            return response()->json([]);
        }

        $lecturers = Lecturer::select('lecturer_code', 'name')
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->where(function ($query) use ($q) {
                $query->where('lecturer_code', 'like', "%{$q}%")
                      ->orWhere('name', 'like', "%{$q}%");
            })
            ->limit(10)
            ->get();

        return response()->json($lecturers);
    }
}
