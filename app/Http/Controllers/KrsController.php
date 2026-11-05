<?php

namespace App\Http\Controllers;

use App\Models\StudentCourse;
use App\Models\Student;
use App\Services\MasterDataApiService;
use Illuminate\Http\Request;

class KrsController extends Controller
{
    public function __construct(private MasterDataApiService $masterData) {}

    public function index(Request $request)
    {
        $krs = StudentCourse::with('student')
            ->when($request->filled('search'), fn($q) =>
                $q->where('course_name', 'like', "%{$request->search}%")
                  ->orWhere('course_code', 'like', "%{$request->search}%")
                  ->orWhere('student_number', 'like', "%{$request->search}%")
            )
            ->when($request->filled('tahun_akademik'), fn($q) =>
                $q->where('academic_year', $request->tahun_akademik)
            )
            ->when($request->filled('semester'), fn($q) =>
                $q->where('semester', $request->semester)
            )
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $tahunList = StudentCourse::select('academic_year')
            ->distinct()
            ->orderBy('academic_year', 'desc')
            ->pluck('academic_year');

        return view('krs.index', compact('krs', 'tahunList'));
    }

    /**
     * Preview KRS dari cookies kampus pusat
     */
    public function previewCookies(Request $request)
    {
        $request->validate([
            'cookie_string' => ['required', 'string', 'max:5000'],
        ]);

        $url    = config('services.master_data.krs_endpoint');
        $result = $this->masterData->fetchWithCookieString($url, $request->cookie_string);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message']
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data'    => $result['data'],
            'total'   => count($result['data']),
        ]);
    }

    /**
     * Simpan data KRS dari cookies
     */
    public function importFromCookies(Request $request)
    {
        $request->validate([
            'cookie_string' => ['required', 'string', 'max:5000'],
        ]);

        $url    = config('services.master_data.krs_endpoint');
        $result = $this->masterData->fetchWithCookieString($url, $request->cookie_string);

        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }

        $synced  = 0;
        $skipped = 0;

        foreach ($result['data'] as $row) {
            if (empty($row['nim']) || empty($row['kode_matakuliah'])) continue;

            if (!Student::where('student_number', $row['nim'])->exists()) {
                $skipped++;
                continue;
            }

            StudentCourse::updateOrCreate(
                [
                    'student_number'   => $row['nim'],
                    'course_code'      => $row['kode_matakuliah'] ?? '',
                    'academic_year'    => $row['tahun_akademik']  ?? '',
                    'semester'         => $row['semester']        ?? 1,
                ],
                [
                    'course_name'      => $row['nama_matakuliah']  ?? '',
                    'course_class'     => $row['kelas_matakuliah'] ?? '',
                    'lecturer_code'    => $row['kode_dosen']       ?? null,
                    'lecturer_name'    => $row['nama_dosen']       ?? null,
                    'faculty'          => $row['fakultas']         ?? null,
                    'study_program'    => $row['program_studi']    ?? null,
                    'credits'          => $row['sks']              ?? 3,
                    'quota'            => $row['kuota']            ?? null,
                ]
            );

            $synced++;
        }

        return redirect()->route('krs.index')
            ->with('success', "Sinkronisasi KRS selesai: {$synced} data diperbarui. {$skipped} dilewati.");
    }
}