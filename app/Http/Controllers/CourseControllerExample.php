<?php

/**
 * ============================================================================
 * CONTOH PENGGUNAAN enrolled_count di Controller
 * ============================================================================
 *
 * File ini adalah CONTOH referensi, bukan file controller aktual.
 * Adaptasikan sesuai kebutuhan controller kamu.
 *
 * Karena kolom `enrolled_count` sudah dihapus dari database, kita
 * menggunakan Eloquent untuk menghitung data secara dinamis.
 * Ada dua cara yang bisa dipilih tergantung kebutuhan performa:
 *
 * ─────────────────────────────────────────────────────────────────────────
 * CARA 1: Accessor (untuk 1 course atau sedikit course)
 * ─────────────────────────────────────────────────────────────────────────
 *
 *   $course = Course::where('course_code', 'TI-101')->first();
 *
 *   // Akses seperti property biasa — akan menghitung saat pertama diakses
 *   echo $course->enrolled_count;  // misal output: 42
 *
 *   // Dalam Blade:
 *   // {{ $course->enrolled_count }}
 *
 * ─────────────────────────────────────────────────────────────────────────
 * CARA 2: withCount() — DIREKOMENDASIKAN untuk daftar banyak course
 * ─────────────────────────────────────────────────────────────────────────
 *
 *   // withCount() menggabungkan COUNT ke query utama (1 query saja),
 *   // jauh lebih efisien daripada accessor yang N+1.
 *
 *   $courses = Course::withCount('enrollments')  // tambah kolom enrollments_count
 *       ->where('study_program', 'Teknik Informatika')
 *       ->paginate(15);
 *
 *   // Di view, akses via:
 *   // $course->enrollments_count   (dari withCount)
 *   // $course->enrolled_count      (accessor — akan fallback ke enrollments_count jika ada)
 *
 * ─────────────────────────────────────────────────────────────────────────
 * CARA 3: Query manual dengan selectRaw (untuk laporan/export)
 * ─────────────────────────────────────────────────────────────────────────
 *
 *   use Illuminate\Support\Facades\DB;
 *
 *   $courses = Course::select('courses.*')
 *       ->selectSub(
 *           DB::table('student_courses')
 *               ->selectRaw('COUNT(*)')
 *               ->whereColumn('student_courses.course_code', 'courses.course_code'),
 *           'enrolled_count'
 *       )
 *       ->orderByDesc('enrolled_count')
 *       ->get();
 *
 * ─────────────────────────────────────────────────────────────────────────
 * CONTOH CONTROLLER LENGKAP (adaptasi dari CourseController@index)
 * ─────────────────────────────────────────────────────────────────────────
 */

namespace App\Http\Controllers;

use App\Models\Course;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    /**
     * Daftar semua course dengan jumlah mahasiswa terdaftar.
     * Menggunakan withCount untuk efisiensi query (1 query, bukan N+1).
     */
    public function index(Request $request)
    {
        $courses = Course::query()
            ->with('academicPeriod')                   // eager load relasi
            ->withCount('enrollments as enrolled_count') // hitung via subquery
            ->scopeSearch($request->input('search'))
            ->scopeFilterStudyProgram($request->input('study_program'))
            ->scopeFilterStatus($request->input('status'))
            ->orderBy('name')
            ->paginate(15);

        return view('courses.index', compact('courses'));
    }

    /**
     * Detail satu course dengan data enrollments lengkap.
     */
    public function show(string $courseCode)
    {
        $course = Course::where('course_code', $courseCode)
            ->withCount('enrollments as enrolled_count')
            ->with([
                'academicPeriod',
                'coordinators',
                'teamLecturers',
                'enrollments.student',  // list mahasiswa
            ])
            ->firstOrFail();

        // Bisa akses:
        // $course->enrolled_count          → int, misal 42
        // $course->academicPeriod->label   → "Genap 2024/2025"
        // $course->coordinators            → Collection of Lecturer

        return view('courses.show', compact('course'));
    }

    /**
     * Contoh penggunaan accessor langsung (tanpa withCount).
     * Cocok untuk satu record. HINDARI dalam loop banyak data.
     */
    public function singleCourseExample(string $courseCode)
    {
        $course = Course::where('course_code', $courseCode)->firstOrFail();

        // Accessor dipanggil → menjalankan query COUNT secara lazy
        $jumlahMahasiswa = $course->enrolled_count;

        return response()->json([
            'course_code'    => $course->course_code,
            'name'           => $course->name,
            'enrolled_count' => $jumlahMahasiswa,
        ]);
    }
}
