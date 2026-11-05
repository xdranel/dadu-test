<?php

namespace App\Support;

use App\Models\SidangTaRegistration;
use Illuminate\Support\Facades\Storage;

class SidangTaReviewEligibility
{
    public static function requiredTypes(SidangTaRegistration $registration): array
    {
        abort_unless($registration->requirements_version === 2, 409,
            'Persyaratan pengajuan tidak sesuai. Muat ulang atau hubungi petugas.');
        $mode = $registration->academic_snapshot['admission']['eprt']['mode'] ?? null;
        abort_unless(in_array($mode, ['automatic', 'waiver'], true), 409,
            'Snapshot persyaratan pengajuan belum lengkap.');
        return SidangTaMeetRequirements::allTypes($registration->diploma_biodata_accurate, $mode);
    }

    public static function groups(SidangTaRegistration $registration): array
    {
        $required = self::requiredTypes($registration);
        return [
            'iGracias & Akademik' => array_values(array_intersect(SidangTaMeetRequirements::CAPTURES, $required)),
            'Pembayaran & Dokumen' => array_values(array_intersect(
                array_merge(SidangTaMeetRequirements::PAYMENT, [SidangTaMeetRequirements::WAIVER]), $required)),
            'Biodata' => array_values(array_intersect(array_merge(
                SidangTaMeetRequirements::DIPLOMA, SidangTaMeetRequirements::PDM), $required)),
        ];
    }

    public static function blockers(SidangTaRegistration $registration): array
    {
        $documents = $registration->relationLoaded('documents')
            ? $registration->documents : $registration->documents()->get();
        $blockers = [];
        foreach (self::requiredTypes($registration) as $type) {
            $document = $documents->firstWhere('document_type', $type);
            $reason = !$document ? 'Belum ada dokumen'
                : (!Storage::disk('local')->exists($document->file_path) ? 'Berkas tidak ditemukan'
                    : ($document->status !== 'verified' ? 'Belum terverifikasi' : null));
            if ($reason !== null) $blockers[$type] = SidangTaRegistration::DOCUMENT_TYPES[$type].' — '.$reason;
        }
        return $blockers;
    }
}
