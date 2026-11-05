<?php

namespace App\Services;

use App\Contracts\CampusDataGrabServiceInterface;
use App\Exceptions\CampusDataGrabException;
use Illuminate\Support\Facades\Log;

/*
 * Service ini mengembalikan data dummy untuk development/testing.
 */
class MockCampusDataGrabService implements CampusDataGrabServiceInterface
{
    /**
     * Simulasi data mahasiswa dummy.
     */
    protected array $mockData = [
        '1301190001' => [
            'student_number' => '1301190001',
            'student_name' => 'Ahmad Fauzi',
            'student_email' => '1301190001@student.telkomuniversity.ac.id',
            'study_program' => 'S1 Teknik Informatika',
            'academic_advisor_code' => 'DOS001',
            'academic_advisor_name' => 'Dr. Budi Santoso, S.T., M.T.',
            'advisor_1_code' => 'DOS002',
            'advisor_1_name' => 'Dr. Siti Aminah, S.Kom., M.Kom.',
            'advisor_2_code' => 'DOS003',
            'advisor_2_name' => 'Dr. Andi Wijaya, S.T., M.T.',
            'examiner_1_code' => 'DOS004',
            'examiner_1_name' => 'Prof. Dr. Hendra Kusuma, S.Kom., M.Kom.',
            'examiner_2_code' => 'DOS005',
            'examiner_2_name' => 'Dr. Rini Susanti, S.T., M.T.',
            'examiner_3_code' => 'DOS006',
            'examiner_3_name' => 'Dr. Dian Pratama, S.Kom., M.Kom.',
            'exam_date' => '2026-07-15',
            'tak_score' => 85,
            'eprt_score' => 500,
            'gpa' => 3.77,
            'ipk' => 3.77,
            'passed_credits' => 146,
            'sks_lulus' => 146,
            'remaining_credits' => 0,
            'sks_belum_lulus' => 0,
            'ibasila_photo_approval_status' => 'approved',
            'payment_status' => 'LUNAS',
            'has_ta2_in_krs' => true,
            'thesis_title_id' => 'Sistem Informasi Manajemen Berbasis Web untuk Pendaftaran Yudisium',
            'thesis_title_en' => 'Web-Based Management Information System for Graduation Registration',
            'level_3_exam_status' => 'passed',
            'failed_courses' => [],
            'retaken_courses' => [
                ['course_code' => 'FIG1A3', 'course_name' => 'Fisika Dasar', 'attempt' => 2],
            ],
        ],
        '1301199999' => [
            'student_number' => '1301199999',
            'student_name' => 'Budi Ineligible',
            'student_email' => '1301199999@student.telkomuniversity.ac.id',
            'study_program' => 'S1 Teknik Informatika',
            'academic_advisor_code' => 'DOS001',
            'academic_advisor_name' => 'Dr. Budi Santoso, S.T., M.T.',
            'advisor_1_code' => 'DOS002',
            'advisor_1_name' => 'Dr. Siti Aminah, S.Kom., M.Kom.',
            'advisor_2_code' => null,
            'advisor_2_name' => null,
            'examiner_1_code' => 'DOS004',
            'examiner_1_name' => 'Prof. Dr. Hendra Kusuma, S.Kom., M.Kom.',
            'examiner_2_code' => 'DOS005',
            'examiner_2_name' => 'Dr. Rini Susanti, S.T., M.T.',
            'examiner_3_code' => null,
            'examiner_3_name' => null,
            'exam_date' => '2026-07-15',
            'tak_score' => 45,
            'eprt_score' => 380,
            'gpa' => 1.85,
            'passed_credits' => 120,
            'ibasila_photo_approval_status' => 'pending',
            'thesis_title_id' => 'Sistem Belum Siap',
            'thesis_title_en' => 'System Not Ready',
            'level_3_exam_status' => 'failed',
            'failed_courses' => [
                ['course_code' => 'IFG2A3', 'course_name' => 'Algoritma Pemrograman'],
            ],
            'retaken_courses' => [],
        ],
    ];

    /**
     * {@inheritdoc}
     */
    public function getStudentGraduationData(string $studentNumber): array
    {
        Log::info("MockCampusDataGrabService: Mengambil data untuk NIM {$studentNumber}");

        usleep(500000); // 0.5 detik

        if (!isset($this->mockData[$studentNumber])) {
            $student = \App\Models\Student::where('student_number', $studentNumber)->first();
            return [
                'student_number' => $studentNumber,
                'student_name' => $student->name ?? 'Mahasiswa Yudisium',
                'student_email' => $student->email ?? ($studentNumber . '@student.telkomuniversity.ac.id'),
                'study_program' => $student->study_program ?? 'S1 Informatika',
                'academic_advisor_code' => 'DOS001',
                'academic_advisor_name' => 'Dosen Wali, S.T., M.T.',
                'advisor_1_code' => 'DOS002',
                'advisor_1_name' => 'Dosen Pembimbing 1, S.T., M.T.',
                'advisor_2_code' => null,
                'advisor_2_name' => null,
                'examiner_1_code' => 'DOS004',
                'examiner_1_name' => 'Dosen Penguji 1, S.T., M.T.',
                'examiner_2_code' => 'DOS005',
                'examiner_2_name' => 'Dosen Penguji 2, S.T., M.T.',
                'examiner_3_code' => null,
                'examiner_3_name' => null,
                'exam_date' => date('Y-m-d'),
                'tak_score' => 85,
                'eprt_score' => 500,
                'gpa' => 3.77,
                'ipk' => 3.77,
                'passed_credits' => 146,
                'sks_lulus' => 146,
                'remaining_credits' => 0,
                'sks_belum_lulus' => 0,
                'ibasila_photo_approval_status' => 'approved',
                'thesis_title_id' => 'Sistem Informasi Manajemen Berbasis Web untuk Pendaftaran Yudisium',
                'thesis_title_en' => 'Web-Based Management Information System for Graduation Registration',
                'level_3_exam_status' => 'passed',
                'failed_courses' => [],
                'retaken_courses' => [],
            ];
        }

        return $this->mockData[$studentNumber];
    }

    /**
     * {@inheritdoc}
     */
    public function isAvailable(): bool
    {
        // Mock service selalu tersedia
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getStudentDataPartial(string $studentNumber, array $fields): array
    {
        $fullData = $this->getStudentGraduationData($studentNumber);
        
        return array_intersect_key($fullData, array_flip($fields));
    }
}
