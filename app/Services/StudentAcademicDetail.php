<?php

namespace App\Services;

use App\Models\Student;
use App\Models\StudentAcademicProfile;
use App\Models\StudentTak;

/** Live academic data for the student detail page; never writes admission snapshots. */
class StudentAcademicDetail
{
    public function forStudent(Student $student): array
    {
        $profile = StudentAcademicProfile::where('student_number', $student->student_number)->first();
        $groups = $profile?->groups ?? [];
        $admission = app(SidangTaAdmissionFacts::class)->forStudent($student);
        $academic = app(SidangTaAcademicSnapshot::class)->forStudent($student, $admission);
        $takScore = $academic['tak_score'];
        if ($profile === null && $takScore === null && $student->activities->isNotEmpty()) {
            $takScore = $student->activities->sum('points');
        }
        $takHistory = StudentTak::where('student_number', $student->student_number)
            ->orderByDesc('test_date')->orderByDesc('id')->get()->map(function (StudentTak $row) {
                $validity = match (true) {
                    $row->test_date?->isFuture() ?? false => 'Belum berlaku',
                    $row->expiry_date === null => 'Masa berlaku belum tersedia',
                    $row->expiry_date->lt(today()) => 'Tidak berlaku',
                    default => 'Berlaku',
                };
                return ['score' => $row->total_points, 'date' => $row->test_date?->format('d/m/Y'),
                    'expires_at' => $row->expiry_date?->format('d/m/Y'), 'validity' => $validity];
            });

        // An official profile with an absent group must not inherit an old manual status.
        $rawSkpi = $profile !== null && !array_key_exists('skpi', $groups)
            ? [] : ($student->skpi?->raw_data ?? []);
        $skpiStatus = $profile !== null ? ($groups['skpi']['status'] ?? null)
            : ($rawSkpi['status_skpi'] ?? $rawSkpi['status'] ?? $rawSkpi['approval_status'] ?? null);
        $parts = [];
        foreach ([1, 2, 3] as $part) $parts[$part] = self::status($rawSkpi['skpi_status_'.$part] ?? null);

        $payments = $profile?->bpp_periods ?? [];
        $current = $profile !== null ? ($groups['bpp'] ?? null)
            : ($student->bppPayment ? ['status' => $student->bppPayment->status, 'period' => null] : null);
        if (is_array($current) && ($current['status'] ?? null) !== null) {
            $key = $current['period'] ?? '';
            if (!array_key_exists($key, $payments)) $payments[$key] = $current;
        }
        krsort($payments, SORT_STRING);
        $bppHistory = [];
        foreach ($payments as $period => $payment) {
            if (!is_array($payment)) continue;
            $bppHistory[] = ['period' => $period === '' ? 'Periode belum tersedia' : (string) $period,
                'status' => self::status($payment['status'] ?? null)];
        }

        return ['language' => $admission['eprt_facts'], 'tak_score' => $takScore, 'tak_history' => $takHistory,
            'bpp_history' => $bppHistory,
            'skpi' => ['status' => self::status($skpiStatus), 'parts' => $parts,
                'approval' => self::status($profile !== null && $skpiStatus === null ? null : ($rawSkpi['approval_status'] ?? null))],
            'sidang_approval' => self::status($academic['sidang_approval_status'])];
    }

    public static function status(mixed $value): array
    {
        $label = is_bool($value) ? ($value ? 'Ya' : 'Tidak') : (is_scalar($value) ? trim((string) $value) : '');
        $key = mb_strtolower($label);
        $class = match (true) {
            in_array($key, ['y', '1', 'true', 'ya', 'approved', 'disetujui', 'lunas', 'sudah lunas', 'lulus'], true) => 'badge-success',
            in_array($key, ['n', '0', 'false', 'tidak', 'rejected', 'ditolak', 'belum disetujui', 'belum lunas', 'belum lulus'], true) => 'badge-danger',
            in_array($key, ['pending', 'menunggu', 'diajukan', 'perlu verifikasi', 'kredit'], true) => 'badge-warning',
            default => 'bg-slate-100 text-slate-600',
        };
        return ['label' => $label === '' ? 'Belum tersedia' : $label, 'class' => $class];
    }
}
