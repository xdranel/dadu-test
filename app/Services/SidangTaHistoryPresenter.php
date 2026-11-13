<?php

namespace App\Services;

use App\Models\SidangTaRegistration;

class SidangTaHistoryPresenter
{
    public const LABELS = [
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
                'time' => $log->performed_at ? $log->performed_at->timezone('Asia/Jakarta')->translatedFormat('d F Y H:i').' WIB' : 'Waktu belum tersedia',
                'notes' => $log->notes,
            ])->all();
    }
}
