<?php

namespace App\Services;

use App\Contracts\CampusDataGrabServiceInterface;
use App\Exceptions\CampusDataGrabException;
use App\Models\GraduationRegistration;
use Illuminate\Support\Facades\Log;

/**
 * Service untuk mengelola proses sync data dari sistem kampus
 * ke tabel graduation_registrations.
 */
class GraduationDataSyncService
{
    protected CampusDataGrabServiceInterface $campusService;

    public function __construct(CampusDataGrabServiceInterface $campusService)
    {
        $this->campusService = $campusService;
    }

    /**
     * Sync data mahasiswa dari sistem kampus ke registration record.
     * 
     * @param GraduationRegistration $registration
     * @param int|null $performedBy User ID yang melakukan sync
     * @return bool
     * @throws CampusDataGrabException
     */
    public function syncRegistrationData(GraduationRegistration $registration, ?int $performedBy = null): bool
    {
        try {
            // Cek availability service dulu
            if (!$this->campusService->isAvailable()) {
                throw CampusDataGrabException::serviceUnavailable();
            }

            // Ambil data dari sistem kampus
            $campusData = $this->campusService->getStudentGraduationData($registration->student_number);

            // Validasi kelengkapan data
            $this->validateRequiredFields($campusData, $registration->student_number);

            // Update registration dengan data grab
            $registration->fill([
                'student_name' => $campusData['student_name'],
                'student_email' => $campusData['student_email'],
                'study_program' => $campusData['study_program'],
                'academic_advisor_code' => $campusData['academic_advisor_code'],
                'academic_advisor_name' => $campusData['academic_advisor_name'],
                'advisor_1_code' => $campusData['advisor_1_code'],
                'advisor_1_name' => $campusData['advisor_1_name'],
                'advisor_2_code' => $campusData['advisor_2_code'] ?? null,
                'advisor_2_name' => $campusData['advisor_2_name'] ?? null,
                'examiner_1_code' => $campusData['examiner_1_code'],
                'examiner_1_name' => $campusData['examiner_1_name'],
                'examiner_2_code' => $campusData['examiner_2_code'],
                'examiner_2_name' => $campusData['examiner_2_name'],
                'examiner_3_code' => $campusData['examiner_3_code'] ?? null,
                'examiner_3_name' => $campusData['examiner_3_name'] ?? null,
                'exam_date' => $campusData['exam_date'],
                'tak_score' => $campusData['tak_score'],
                'eprt_score' => $campusData['eprt_score'],
                'ibasila_photo_approval_status' => $campusData['ibasila_photo_approval_status'],
                'thesis_title_id' => $campusData['thesis_title_id'],
                'thesis_title_en' => $campusData['thesis_title_en'] ?? null,
                'level_3_exam_status' => $campusData['level_3_exam_status'],
                'failed_courses' => $campusData['failed_courses'],
                'retaken_courses' => $campusData['retaken_courses'],
                'gpa'                    => $campusData['gpa'] ?? null,
                'passed_credits'         => $campusData['passed_credits'] ?? null,
                'remaining_credits_db'   => $campusData['remaining_credits_db'] ?? null,
                'active_semester_db'     => $campusData['active_semester_db'] ?? null,
                'payment_status'         => $campusData['payment_status'] ?? null,
                'thesis_grade_index'     => $campusData['thesis_grade_index'] ?? null,
                'has_ta2_in_krs'         => $campusData['has_ta2_in_krs'] ?? null,
                'last_synced_at' => now(),
            ]);

            $registration->save();

            // Log aksi sync
            $registration->logAction(
                action: 'data_synced',
                fromStatus: null,
                toStatus: null,
                notes: 'Data berhasil disinkronkan dari sistem kampus',
                performedBy: $performedBy
            );

            Log::info("Data graduation berhasil di-sync untuk NIM {$registration->student_number}");

            return true;

        } catch (CampusDataGrabException $e) {
            Log::error("Gagal sync data graduation: {$e->getMessage()}", [
                'student_number' => $registration->student_number,
                'error_code' => $e->getCampusErrorCode(),
                'context' => $e->getContext(),
            ]);

            // Re-throw exception agar bisa ditangani di controller
            throw $e;
        }
    }

    /** Refresh imported academic values on existing applications for this student. */
    public function syncExistingRegistrations(string $studentNumber): void
    {
        GraduationRegistration::forStudent($studentNumber)->get()->each(function (GraduationRegistration $registration) {
            try {
                $this->syncRegistrationData($registration);
            } catch (CampusDataGrabException $e) {
                Log::warning('Data yudisium tidak tersinkron setelah import JSON: ' . $e->getMessage(), [
                    'registration_id' => $registration->id,
                    'student_number' => $registration->student_number,
                ]);
            }
        });
    }

    /**
     * Validasi field wajib dari data grab.
     * 
     * @param array $data
     * @param string $studentNumber
     * @throws CampusDataGrabException
     */
    protected function validateRequiredFields(array $data, string $studentNumber): void
    {
        $requiredFields = [
            'student_name',
            'study_program',
        ];

        $missingFields = [];

        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                $missingFields[] = $field;
            }
        }

        if (!empty($missingFields)) {
            throw CampusDataGrabException::incompleteData($studentNumber, $missingFields);
        }
    }

    /**
     * Cek apakah mahasiswa eligible untuk mendaftar yudisium
     * berdasarkan data dari sistem kampus.
     * 
     * @param string $studentNumber
     * @return array ['eligible' => bool, 'reasons' => array]
     */
    public function checkEligibility(string $studentNumber): array
    {
        try {
            $campusData = $this->campusService->getStudentGraduationData($studentNumber);

            $eligible = true;
            $reasons = [];

            // Fallback TAK & EPRT dari database jika data grab bernilai null
            $takScore = $campusData['tak_score'] ?? null;
            if ($takScore === null) {
                $topTakRecord = \App\Models\StudentTak::where('student_number', $studentNumber)
                    ->where(function ($q) {
                        $q->whereNull('expiry_date')
                          ->orWhere('expiry_date', '>=', today());
                    })
                    ->orderByDesc('total_points')
                    ->orderByRaw("COALESCE(expiry_date, '2099-12-31') DESC")
                    ->first();

                if ($topTakRecord) {
                    $takScore = $topTakRecord->total_points;
                }
                if ($takScore !== null) {
                    $campusData['tak_score'] = $takScore;
                }
            }

            $eprtScore = $campusData['eprt_score'] ?? null;
            if ($eprtScore === null) {
                $eprtScore = \App\Models\StudentEprt::where('student_number', $studentNumber)->max('total_score') ?: null;
                if ($eprtScore !== null) {
                    $campusData['eprt_score'] = $eprtScore;
                }
            }

            // 1. Cek status sidang tingkat 3
            if (($campusData['level_3_exam_status'] ?? null) !== 'passed') {
                $eligible = false;
                $reasons[] = 'Belum lulus sidang tingkat 3';
            }

            // 2. Cek ada mata kuliah yang belum lulus
            if (!empty($campusData['failed_courses'])) {
                $eligible = false;
                $reasons[] = 'Masih ada mata kuliah yang belum lulus (' . count($campusData['failed_courses']) . ' MK)';
            }

            if (!empty($campusData['retaken_courses'])) {
                $eligible = false;
                $reasons[] = 'Masih ada mata kuliah yang sedang/mengulang (' . count($campusData['retaken_courses']) . ' MK)';
            }

            // 3. Cek approval foto i-Basila
            if (($campusData['ibasila_photo_approval_status'] ?? null) !== 'approved') {
                $eligible = false;
                $reasons[] = 'Foto di i-Basila belum disetujui';
            }

            // 4. Cek skor TAK minimal (minimal: 60)
            if (($campusData['tak_score'] ?? null) === null || (float)$campusData['tak_score'] < 60) {
                $eligible = false;
                $reasons[] = ($campusData['tak_score'] ?? null) === null
                    ? 'Data TAK belum tersedia atau sudah kedaluwarsa'
                    : 'Skor TAK belum memenuhi minimal (60)';
            }

            // 5. Cek skor EPRT minimal (minimal: 450)
            if (($campusData['eprt_score'] ?? null) === null || (float)$campusData['eprt_score'] < 450) {
                $eligible = false;
                $reasons[] = ($campusData['eprt_score'] ?? null) === null
                    ? 'Data EPRT belum tersedia'
                    : 'Skor EPRT belum memenuhi minimal (450)';
            }

            // 6. Cek IPK minimal (minimal: 2.00)
            $gpa = $campusData['gpa'] ?? $campusData['ipk'] ?? null;
            if ($gpa !== null && (float)$gpa < 2.00) {
                $eligible = false;
                $reasons[] = 'IPK belum memenuhi standar kelulusan minimal (2.00)';
            }

            // 7. Cek Status Pembayaran Wisuda / BPP
            $paymentStatus = strtolower(trim((string) ($campusData['payment_status'] ?? '')));
            if (!in_array($paymentStatus, ['lunas', 'paid', 'free', 'bebas'], true)) {
                $eligible = false;
                $reasons[] = 'Status pembayaran wisuda belum lunas' . ($paymentStatus !== '' ? ' (' . strtoupper($paymentStatus) . ')' : '');
            }

            // 8. SKPI must be approved in the imported academic profile.
            if (strtolower((string) ($campusData['skpi_approval_status'] ?? '')) !== 'approved') {
                $eligible = false;
                $reasons[] = 'Approval SKPI belum disetujui atau belum tersedia';
            }

            // 9. KRS TA2 must be linked to the active academic period.
            $hasTa2InCampusData = $campusData['has_ta2_in_krs'] ?? null;
            if ($hasTa2InCampusData !== true) {
                $eligible = false;
                $reasons[] = match ($campusData['ta2_krs_status'] ?? null) {
                    'no_active_period' => 'Periode akademik aktif belum ditentukan oleh admin',
                    'period_unlinked' => 'KRS TA2 ditemukan, tetapi belum tertaut ke periode akademik aktif. Impor ulang KRS dengan tahun akademik yang sesuai.',
                    'other_period' => 'KRS TA2 ditemukan, tetapi tercatat pada periode akademik yang berbeda',
                    default => 'Belum ditemukan KRS TA2 untuk periode akademik aktif',
                };
            }

            return [
                'eligible' => $eligible,
                'reasons' => $reasons,
                'data' => $campusData,
            ];

        } catch (CampusDataGrabException $e) {
            return [
                'eligible' => false,
                'reasons' => ['Gagal mengambil data dari sistem kampus: ' . $e->getMessage()],
                'data' => null,
            ];
        }
    }
}
