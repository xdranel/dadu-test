<?php

namespace App\Jobs;

use App\Models\SyncLog;
use App\Models\Student;
use App\Models\Course;
use App\Models\StudentCourse;
use App\Models\StudentGrade;
use App\Models\StudentAttendance;
use App\Models\StudentActivity;
use App\Services\MasterDataApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncMasterDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        private string $module,
        private string $cookieString,
        private int    $syncLogId,
    ) {}

    public function handle(MasterDataApiService $service): void
    {
        $log = SyncLog::find($this->syncLogId);

        if (!$log) return;

        $log->update([
            'status'     => 'processing',
            'started_at' => now(),
        ]);

        try {
            $setting = \App\Models\SyncSetting::where('module', $this->module)->first();
            $url = $setting ? $setting->endpoint_url : null;
            
            if (!$url) {
                // If endpoint URL is not configured in SyncSetting, fallback to config for testing
                $url = config('services.master_data.' . $this->module . '_endpoint') ?? 'https://igracias.telkomuniversity.ac.id/api/' . $this->module;
            }

            $result = $service->fetchWithCookieString($url, $this->cookieString);

            if (!$result['success']) {
                $log->update([
                    'status'        => 'failed',
                    'error_message' => $result['message'],
                    'completed_at'  => now(),
                ]);
                return;
            }

            $data      = $result['data'];
            $processed = 0;
            $skipped   = 0;

            foreach ($data as $row) {
                $result = match($this->module) {
                    'student', 'mahasiswa'    => $this->syncStudent($row),
                    'course', 'matakuliah'    => $this->syncCourse($row),
                    'krs'                     => $this->syncKrs($row),
                    'grade', 'nilai'          => $this->syncGrade($row),
                    'attendance', 'kehadiran' => $this->syncAttendance($row),
                    'activity', 'tak'         => $this->syncActivity($row),
                    default                   => false,
                };

                $result ? $processed++ : $skipped++;
            }

            $log->update([
                'status'            => 'completed',
                'total_records'     => count($data),
                'processed_records' => $processed,
                'skipped_records'   => $skipped,
                'completed_at'      => now(),
            ]);

        } catch (\Exception $e) {
            Log::error("SyncMasterDataJob [{$this->module}]: " . $e->getMessage());

            $log->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at'  => now(),
            ]);
        }
    }

    private function syncStudent(array $row): bool
    {
        $nim = $row['student_number'] ?? $row['nim'] ?? null;
        if (empty($nim)) return false;

        Student::updateOrCreate(
            ['student_number' => $nim],
            [
                'name'              => $row['name']              ?? $row['nama_mahasiswa'] ?? $row['nama'] ?? '',
                'gender'            => $row['gender']            ?? $row['jenis_kelamin']   ?? null,
                'religion'          => $row['religion']          ?? $row['agama']           ?? null,
                'faculty'           => $row['faculty']           ?? $row['fakultas']        ?? null,
                'study_program'     => $row['study_program']     ?? $row['program_studi']   ?? '',
                'academic_year'     => $row['academic_year']     ?? $row['tahun_akademik']  ?? date('Y'),
                'active_semester'   => $row['active_semester']   ?? $row['semester_aktif']  ?? 1,
                'class_name'        => $row['class_name']        ?? $row['kelas']           ?? null,
                'academic_advisor'  => $row['academic_advisor']  ?? $row['dosen_wali']      ?? null,
                'email'             => $row['email']             ?? '',
                'phone_number'      => $row['phone_number']      ?? $row['no_hp']           ?? null,
                'status'            => $this->mapStatus($row['status'] ?? 'Aktif'),
            ]
        );
        return true;
    }

    private function syncCourse(array $row): bool
    {
        $code = $row['course_code'] ?? $row['kode_mk'] ?? null;
        if (empty($code)) return false;

        Course::updateOrCreate(
            ['course_code' => strtoupper($code)],
            [
                'name'          => $row['name']          ?? $row['nama_mk']  ?? '',
                'study_program' => $row['study_program'] ?? $row['prodi']    ?? '',
                'class'         => $row['class']         ?? $row['kelas']    ?? 'A',
                'semester'      => $row['semester']      ?? 1,
                'credits'       => $row['credits']       ?? $row['sks']      ?? 3,
                'type'          => $row['type']          ?? $row['jenis']    ?? 'Wajib',
                'status'        => $row['status']        ?? 'Aktif',
            ]
        );
        return true;
    }

    private function syncKrs(array $row): bool
    {
        $nim = $row['student_number'] ?? $row['nim'] ?? null;
        $courseCode = $row['course_code'] ?? $row['kode_matakuliah'] ?? null;
        if (empty($nim) || empty($courseCode)) return false;

        if (!Student::where('student_number', $nim)->exists()) return false;

        StudentCourse::updateOrCreate(
            [
                'student_number'  => $nim,
                'course_code'     => $courseCode,
                'academic_year'   => $row['academic_year']  ?? $row['tahun_akademik'] ?? '',
                'semester'        => $row['semester']       ?? 1,
            ],
            [
                'course_name'     => $row['course_name']    ?? $row['nama_matakuliah']  ?? '',
                'course_class'    => $row['course_class']   ?? $row['kelas_matakuliah'] ?? '',
                'lecturer_code'   => $row['lecturer_code']  ?? $row['kode_dosen']       ?? null,
                'lecturer_name'   => $row['lecturer_name']  ?? $row['nama_dosen']       ?? null,
                'credits'         => $row['credits']        ?? $row['sks']              ?? 3,
            ]
        );
        return true;
    }

    private function syncGrade(array $row): bool
    {
        $nim = $row['student_number'] ?? $row['nim'] ?? null;
        $courseCode = $row['course_code'] ?? $row['kode_matakuliah'] ?? null;
        if (empty($nim) || empty($courseCode)) return false;

        StudentGrade::updateOrCreate(
            [
                'student_number'  => $nim,
                'course_code'     => $courseCode,
            ],
            [
                'course_name'     => $row['course_name']    ?? $row['nama_matakuliah'] ?? '',
                'credits'         => $row['credits']        ?? $row['sks']             ?? 3,
                'type'            => $row['type']           ?? $row['jenis']           ?? null,
                'grade'           => $row['grade']          ?? $row['nilai']           ?? null,
                'level'           => $row['level']          ?? $row['tingkat']         ?? null,
                'academic_year'   => $row['academic_year']  ?? $row['tahun_akademik']  ?? null,
            ]
        );
        return true;
    }

    private function syncAttendance(array $row): bool
    {
        $nim = $row['student_number'] ?? $row['nim'] ?? null;
        $courseCode = $row['course_code'] ?? $row['kode_matakuliah'] ?? null;
        if (empty($nim) || empty($courseCode)) return false;

        StudentAttendance::updateOrCreate(
            [
                'student_number'  => $nim,
                'course_code'     => $courseCode,
                'academic_year'   => $row['academic_year']  ?? $row['tahun_akademik'] ?? '',
                'semester'        => $row['semester']       ?? 1,
            ],
            [
                'course_name'     => $row['course_name']    ?? $row['nama_matakuliah'] ?? '',
                'class'           => $row['class']          ?? $row['kelas']           ?? null,
                'attended_count'  => $row['attended_count'] ?? $row['jumlah_hadir']    ?? 0,
                'meetings_count'  => $row['meetings_count'] ?? $row['jumlah_pertemuan']?? 0,
            ]
        );
        return true;
    }

    private function syncActivity(array $row): bool
    {
        $nim = $row['student_number'] ?? $row['nim'] ?? null;
        $activityName = $row['activity_name'] ?? $row['nama_kegiatan'] ?? null;
        if (empty($nim) || empty($activityName)) return false;

        StudentActivity::updateOrCreate(
            [
                'student_number'  => $nim,
                'activity_name'   => $activityName,
                'date'            => $row['date']           ?? $row['tanggal'] ?? null,
            ],
            [
                'activity_type'   => $row['activity_type']  ?? $row['jenis_kegiatan'] ?? null,
                'role'            => $row['role']           ?? $row['peran']          ?? null,
                'points'          => $row['points']         ?? $row['poin']           ?? 0,
                'details'         => $row['details']        ?? $row['keterangan']     ?? null,
            ]
        );
        return true;
    }

    /**
     * Map status dari iGracias ke ENUM database.
     * iGracias mengirim 'STUDENT', 'ACTIVE', dll. yang tidak sesuai
     * dengan ENUM('Aktif', 'Cuti', 'Lulus', 'Drop Out') di tabel students.
     */
    private function mapStatus(string $status): string
    {
        $map = [
            'STUDENT'  => 'Aktif',
            'ACTIVE'   => 'Aktif',
            'AKTIF'    => 'Aktif',
            'CUTI'     => 'Cuti',
            'LULUS'    => 'Lulus',
            'DROP OUT' => 'Drop Out',
            'DO'       => 'Drop Out',
            'KELUAR'   => 'Drop Out',
        ];

        $normalized = strtoupper(trim($status));

        return $map[$normalized] ?? 'Aktif';
    }

    public function failed(\Throwable $exception): void
    {
        SyncLog::find($this->syncLogId)?->update([
            'status'        => 'failed',
            'error_message' => $exception->getMessage(),
            'completed_at'  => now(),
        ]);
    }
}