<?php

namespace App\Http\Controllers;

use App\Models\StudentCourse;
use App\Models\Student;
use Illuminate\Http\Request;
use Rap2hpoutre\FastExcel\FastExcel;

class StudentCourseController extends Controller
{
    public function index(Request $request)
    {
        $krs = StudentCourse::with('academicPeriod')->leftJoin('students', 'student_courses.student_number', '=', 'students.student_number')
            ->select(
                'student_courses.course_code',
                'student_courses.course_name',
                'student_courses.course_class',
                'student_courses.academic_year',
                'student_courses.academic_period_id',
                'student_courses.semester',
                'student_courses.credits',
                'student_courses.lecturer_code',
                'student_courses.lecturer_name',
                'students.study_program as student_study_program',
                'student_courses.study_program as krs_study_program'
            )
            ->selectRaw('MIN(student_courses.id) as id, count(student_courses.id) as jumlah_mahasiswa')
            ->when($request->search, fn($q, $s) =>
                $q->where(function($query) use ($s) {
                    $query->where('student_courses.course_name', 'like', "%{$s}%")
                          ->orWhere('student_courses.course_code', 'like', "%{$s}%")
                          ->orWhere('student_courses.student_number', 'like', "%{$s}%");
                })
            )
            ->when($request->tahun_akademik, fn($q, $t) =>
                $q->where('student_courses.academic_year', $t)
            )
            ->when($request->semester, fn($q, $s) =>
                $q->where('student_courses.semester', $s)
            )
            ->groupBy(
                'student_courses.course_code',
                'student_courses.course_name',
                'student_courses.course_class',
                'student_courses.academic_year',
                'student_courses.academic_period_id',
                'student_courses.semester',
                'student_courses.credits',
                'student_courses.lecturer_code',
                'student_courses.lecturer_name',
                'students.study_program',
                'student_courses.study_program'
            )
            ->orderByDesc('student_courses.academic_year')
            ->paginate(20)
            ->withQueryString();

        $tahunList = StudentCourse::select('academic_year')
            ->distinct()
            ->orderBy('academic_year', 'desc')
            ->pluck('academic_year');

        $courseCodes = $krs->pluck('course_code')->unique()->filter()->values();

        $teamLecturers = \App\Models\CourseLecturer::join('lecturers', 'course_lecturers.lecturer_code', '=', 'lecturers.lecturer_code')
            ->whereIn('course_code', $courseCodes)
            ->where('role', 'team_member')
            ->select('course_lecturers.course_code', 'lecturers.lecturer_code', 'lecturers.name', 'lecturers.front_title', 'lecturers.back_title')
            ->get()
            ->groupBy('course_code');

        $directLecturerCodes = $krs->pluck('lecturer_code')->unique()->filter()->values();
        $lecturersMap = \App\Models\Lecturer::whereIn('lecturer_code', $directLecturerCodes)
            ->get()
            ->keyBy('lecturer_code');

        $krs->getCollection()->transform(function ($item) use ($teamLecturers, $lecturersMap) {
            // study_program
            $spRaw = $item->student_study_program ?: $item->krs_study_program;
            $item->study_program_display = $spRaw ? trim(str_replace(' - Kampus Surabaya', '', $spRaw)) : '-';

            $item->semester_display = $item->semester ?: '-';

            // Resolve lecturers
            $list = [];
            if ($teamLecturers->has($item->course_code)) {
                foreach ($teamLecturers->get($item->course_code) as $lect) {
                    $parts = array_filter([$lect->front_title, $lect->name, $lect->back_title]);
                    $fullName = implode(' ', $parts) ?: ($lect->name ?? '');
                    
                    if (strpos($fullName, '-') === 0) {
                        $fullName = ltrim($fullName, '-');
                        $fullName = trim($fullName);
                    }

                    $list[] = [
                        'code' => $lect->lecturer_code,
                        'name' => $fullName,
                    ];
                }
            }

            if (empty($list)) {
                $fallbackCode = $item->lecturer_code;
                $fallbackName = $item->lecturer_name;
                
                if ($fallbackCode && $lecturersMap->has($fallbackCode)) {
                    $lectModel = $lecturersMap->get($fallbackCode);
                    $parts = array_filter([$lectModel->front_title, $lectModel->name, $lectModel->back_title]);
                    $fullName = implode(' ', $parts) ?: ($lectModel->name ?? '');
                    $fallbackName = $fullName;
                }

                if ($fallbackName) {
                    if (strpos($fallbackName, '-') === 0) {
                        $fallbackName = ltrim($fallbackName, '-');
                        $fallbackName = trim($fallbackName);
                    }
                }

                if ($fallbackCode || $fallbackName) {
                    $list[] = [
                        'code' => $fallbackCode ?: '-',
                        'name' => $fallbackName ?: '-',
                    ];
                }
            }

            $item->lecturers_list = $list;

            return $item;
        });

        return view('student_courses.index', compact('krs', 'tahunList'));
    }

    public function downloadTemplate()
    {
        $template = collect([[
            'NIM'             => '1301190001',
            'KODE MK'         => 'CSG2A3',
            'NAMA MK'         => 'Algoritma dan Pemrograman',
            'KELAS'           => 'IF-43-01',
            'PRODI'           => 'S1 Informatika',
            'TAHUN AKADEMIK'  => '2023/2024',
            'SEMESTER'        => 'Ganjil',
            'SKS'             => '3',
            'KODE DOSEN'      => 'BDI',
            'NAMA DOSEN'      => 'Budi Santoso',
        ]]);

        return (new FastExcel($template))->download(
            'template-import-krs.xlsx',
            fn($row) => $row
        );
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:2048'],
        ]);

        $imported = 0;
        $skipped  = 0;

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
                
                $nim      = $clean($row['NIM'] ?? $row['nim'] ?? $row['student_number'] ?? null);
                $kodeMk   = $clean($row['KODE MK'] ?? $row['kode_mk'] ?? $row['course_code'] ?? null);
                $namaMk   = $clean($row['NAMA MK'] ?? $row['nama_mk'] ?? $row['course_name'] ?? null);
                $kelas    = $clean($row['KELAS'] ?? $row['kelas'] ?? $row['course_class'] ?? null);
                $prodi    = $clean($row['PRODI'] ?? $row['prodi'] ?? $row['study_program'] ?? null);
                $tahun    = $clean($row['TAHUN AKADEMIK'] ?? $row['tahun_akademik'] ?? $row['academic_year'] ?? null);
                $semester = $clean($row['SEMESTER'] ?? $row['semester'] ?? null);
                $sks      = $clean($row['SKS'] ?? $row['sks'] ?? $row['credits'] ?? null);
                $kodeDos  = $clean($row['KODE DOSEN'] ?? $row['kode_dosen'] ?? $row['lecturer_code'] ?? null);
                $namaDos  = $clean($row['NAMA DOSEN'] ?? $row['nama_dosen'] ?? $row['lecturer_name'] ?? null);

                if (empty($nim) || empty($kodeMk)) return null;

                StudentCourse::updateOrCreate(
                    [
                        'student_number' => $nim,
                        'course_code'    => $kodeMk,
                        'academic_year'  => $tahun,
                    ],
                    [
                        'course_name'    => $namaMk,
                        'course_class'   => $kelas,
                        'study_program'  => $prodi,
                        'semester'       => $semester,
                        'credits'        => $sks,
                        'lecturer_code'  => $kodeDos,
                        'lecturer_name'  => $namaDos,
                    ]
                );

                $imported++;
            });
        } catch (\Exception $e) {
            return back()->with('error', 'File tidak valid: ' . $e->getMessage());
        }

        return redirect()->route('student_courses.index')
            ->with('success', "Import selesai: {$imported} data berhasil diproses.");
    }

    public function show(StudentCourse $studentCourse)
    {
        $students = StudentCourse::with('student')
            ->where('course_code', $studentCourse->course_code)
            ->where('course_class', $studentCourse->course_class)
            ->where('academic_year', $studentCourse->academic_year)
            ->where('academic_period_id', $studentCourse->academic_period_id)
            ->where('semester', $studentCourse->semester)
            ->get();

        $students->transform(function ($item) {
            $sp = $item->student->study_program ?? $item->study_program;
            $item->study_program_display = $sp ? trim(str_replace(' - Kampus Surabaya', '', $sp)) : '-';
            $item->student_name_display = $item->student->name ?? 'Data Belum Tersedia';
            return $item;
        });
            
        return view('student_courses.show', compact('studentCourse', 'students'));
    }
}
