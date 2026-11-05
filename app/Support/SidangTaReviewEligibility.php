<?php

namespace App\Support;

use App\Models\SidangTaRegistration;
use Illuminate\Support\Facades\Storage;

class SidangTaReviewEligibility
{
    public static function requiredTypes(SidangTaRegistration $registration): array
    {
        return array_merge(SidangTaDocumentRequirements::CAPTURES, SidangTaDocumentRequirements::PAYMENT,
            SidangTaDocumentRequirements::typesForStep(4, $registration->diploma_biodata_accurate));
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
