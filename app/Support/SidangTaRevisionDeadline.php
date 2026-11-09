<?php

namespace App\Support;

use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use Illuminate\Validation\ValidationException;

class SidangTaRevisionDeadline
{
    public const MESSAGE = 'Batas waktu revisi telah berakhir. Revisi tidak dapat disimpan atau dikirim lagi.';
    public const NOT_STARTED_MESSAGE = 'Jadwal verifikasi berkas belum dimulai. Revisi dapat dikerjakan sesuai tanggal mulai yang ditetapkan.';

    private static function period(SidangTaRegistration $registration, bool $lockPeriod): SidangTaPeriod
    {
        abort_unless($registration->requirements_version === 2, 409,
            'Persyaratan pengajuan tidak sesuai. Muat ulang atau hubungi petugas.');
        // Reload even when the relation was loaded before the operator changed dates.
        $query = $registration->period();
        return ($lockPeriod ? $query->lockForUpdate() : $query)->firstOrFail();
    }

    public static function hasExpired(SidangTaRegistration $registration, bool $lockPeriod = false): bool
    {
        return self::period($registration, $lockPeriod)->revisionHasExpired();
    }

    public static function hasNotStarted(SidangTaRegistration $registration, bool $lockPeriod = false): bool
    {
        $period = self::period($registration, $lockPeriod);
        return $period->revisionIsConfigured() && now()->lessThan($period->revision_open_at);
    }

    // Called inside the transaction after the relevant Student/draft/registration locks.
    public static function ensureOpen(SidangTaRegistration $registration, string $field = 'draft'): void
    {
        $period = self::period($registration, true);
        if ($period->revisionHasExpired()) {
            throw ValidationException::withMessages([$field => self::MESSAGE]);
        }
        if (!$period->revisionIsOpen()) {
            throw ValidationException::withMessages([$field => self::NOT_STARTED_MESSAGE]);
        }
    }
}
