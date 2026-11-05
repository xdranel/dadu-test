<?php

namespace App\Contracts;

/**
 * Interface untuk service yang melakukan grab/sync data mahasiswa
 * dari sistem kampus utama (iGracias, i-Basila, dll).
 * 
 * Implementasi konkret akan mengintegrasikan dengan API/sistem kampus yang sebenarnya.
 */
interface CampusDataGrabServiceInterface
{
    /**
     * Ambil data lengkap mahasiswa untuk pendaftaran yudisium.
     * 
     * @param string $studentNumber NIM mahasiswa
     * @return array Data mahasiswa dengan struktur:
     * [
     *     'student_number' => string,
     *     'student_name' => string,
     *     'student_email' => string (email kampus),
     *     'study_program' => string,
     *     'academic_advisor_code' => string,
     *     'academic_advisor_name' => string,
     *     'advisor_1_code' => string,
     *     'advisor_1_name' => string,
     *     'advisor_2_code' => string|null,
     *     'advisor_2_name' => string|null,
     *     'examiner_1_code' => string,
     *     'examiner_1_name' => string,
     *     'examiner_2_code' => string,
     *     'examiner_2_name' => string,
     *     'examiner_3_code' => string|null,
     *     'examiner_3_name' => string|null,
     *     'exam_date' => string (Y-m-d),
     *     'tak_score' => int,
     *     'eprt_score' => float,
     *     'ibasila_photo_approval_status' => string (pending|approved|rejected),
     *     'thesis_title_id' => string,
     *     'thesis_title_en' => string|null,
     *     'level_3_exam_status' => string (passed|not_passed),
     *     'failed_courses' => array [['course_code' => string, 'course_name' => string], ...],
     *     'retaken_courses' => array [['course_code' => string, 'course_name' => string, 'attempt' => int], ...],
     * ]
     * 
     * @throws \App\Exceptions\CampusDataGrabException jika data tidak ditemukan atau API error
     */
    public function getStudentGraduationData(string $studentNumber): array;

    /**
     * Check apakah service tersedia/online.
     * 
     * @return bool
     */
    public function isAvailable(): bool;

    /**
     * Ambil data mahasiswa secara parsial (hanya field tertentu).
     * Berguna untuk update data spesifik tanpa harus grab semua field.
     * 
     * @param string $studentNumber
     * @param array $fields Field yang ingin diambil
     * @return array
     */
    public function getStudentDataPartial(string $studentNumber, array $fields): array;
}
