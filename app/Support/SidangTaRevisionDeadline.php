<?php

namespace App\Support;

use App\Models\SidangTaRegistration;
use Illuminate\Validation\ValidationException;

class SidangTaRevisionDeadline
{
    public const MESSAGE = 'Batas waktu revisi telah berakhir. Revisi tidak dapat disimpan atau dikirim lagi.';

    public static function hasExpired(SidangTaRegistration $registration, bool $lockPeriod = false): bool
    {
        abort_unless($registration->requirements_version === 2, 409,
            'Persyaratan pengajuan tidak sesuai. Muat ulang atau hubungi petugas.');

        // Selalu baca ulang periode; relasi yang sudah dimuat dapat menyimpan tanggal lama.
        $query = $registration->period();
        $period = ($lockPeriod ? $query->lockForUpdate() : $query)->firstOrFail();
        return now()->greaterThan($period->close_at);
    }

    // Panggil di dalam transaksi sesudah lock Student/draft/pengajuan yang diperlukan.
    public static function ensureOpen(SidangTaRegistration $registration, string $field = 'draft'): void
    {
        if (self::hasExpired($registration, true)) {
            throw ValidationException::withMessages([$field => self::MESSAGE]);
        }
    }
}
