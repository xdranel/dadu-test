<?php

namespace App\Services;

use App\Models\SidangTaRegistration;
use Carbon\CarbonInterface;

class SidangTaHistoryPresenter
{
    public const LABELS = [
        'schedule_updated' => 'Jadwal pelaksanaan sidang diperbarui',
        'submitted' => 'Pengajuan pendaftaran sidang dikirim',
        'needs_review' => 'Pengajuan diminta revisi oleh admin',
        'approved' => 'Pengajuan disetujui admin',
        'approval_cancelled' => 'Persetujuan dibatalkan admin; menunggu peninjauan ulang',
        'rejected' => 'Pengajuan ditolak',
        'reopened' => 'Pengajuan dibuka kembali oleh admin',
        'resubmitted' => 'Pengajuan revisi dikirim',
    ];

    public function forStudent(SidangTaRegistration $registration): array
    {
        return $registration->logs()->whereIn('action', array_keys(self::LABELS))
            ->reorder()->orderByDesc('performed_at')->orderByDesc('id')->get()
            ->map(fn ($log) => [
                'action' => $log->action, 'label' => self::LABELS[$log->action],
                'time' => $this->timeLabel($log->performed_at),
                'notes' => $log->notes,
            ])->all();
    }

    public function forExecution(SidangTaRegistration $registration, bool $staff = false): array
    {
        $registration->loadMissing($staff ? 'roomSchedule.executionLogs.actor' : 'roomSchedule.executionLogs');

        return $registration->roomSchedule?->executionLogs->map(function ($log) use ($staff) {
            $action = $log->action;
            $label = match ($action) {
                'plotting_imported' => $staff ? 'Impor plotting' : 'Rencana jadwal disiapkan',
                'scheduled', 'confirmed' => 'Penetapan jadwal',
                'rescheduled' => 'Perubahan jadwal',
                'cancelled' => 'Pembatalan jadwal',
                'result_updated' => 'Perubahan hasil',
                default => 'Pembaruan pelaksanaan',
            };
            if ($action === 'result_updated') {
                $results = SidangTaExecutionInfoService::RESULTS;
                $label .= ' · '.($staff ? ($results[$log->from_status] ?? '—').' → ' : '').($results[$log->to_status] ?? '—');
                $action = in_array($log->to_status, ['passed', 'failed'], true) ? $log->to_status : $action;
            }

            return [
                'action' => $action,
                'label' => $label,
                'time' => $this->timeLabel($log->performed_at),
                'notes' => $staff ? $log->notes : null,
                'actor' => $staff ? $log->actor?->name ?? 'Petugas' : null,
            ];
        })->all() ?? [];
    }

    private function timeLabel(?CarbonInterface $time): string
    {
        return $time ? $time->copy()->timezone('Asia/Jakarta')->translatedFormat('d F Y H:i').' WIB' : 'Waktu belum tersedia';
    }
}
