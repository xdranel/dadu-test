<?php

namespace App\Support;

use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDraft;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SidangTaRevisionContract
{
    public static function isTargeted(SidangTaRegistrationDraft $draft): bool
    {
        return $draft->requirements_version === 2 && $draft->source_registration_id !== null;
    }

    public static function source(SidangTaRegistrationDraft $draft): SidangTaRegistration
    {
        abort_unless(self::isTargeted($draft), 409, 'Draft bukan revisi terarah.');
        $source = SidangTaRegistration::whereKey($draft->source_registration_id)->firstOrFail();
        abort_unless($source->requirements_version === 2
            && $source->status === 'needs_review'
            && $source->student_number === $draft->student_number
            && $source->lock_version === $draft->source_version, 409,
            'Pengajuan asal berubah. Muat ulang revisi.');
        return $source;
    }

    public static function typesForStep(SidangTaRegistrationDraft $draft, int $step): array
    {
        $groups = array_values(SidangTaReviewEligibility::groups(self::source($draft)));
        return in_array($step, [2, 3, 4], true) ? $groups[$step - 2] : [];
    }

    public static function editableTypes(SidangTaRegistrationDraft $draft): array
    {
        $source = self::source($draft);
        return $source->documents()->whereIn('document_type', SidangTaReviewEligibility::requiredTypes($source))
            ->where('status', 'rejected')->pluck('document_type')->all();
    }

    public static function assertPayloadUnchanged(SidangTaRegistrationDraft $draft, array $mergedPayload): void
    {
        $source = self::source($draft);
        $expected = ['student_phone' => $source->student_phone, 'parent_phone' => $source->parent_phone,
            'graduation_payment_date' => $source->graduation_payment_date?->format('Y-m-d'),
            'diploma_biodata_accurate' => $source->diploma_biodata_accurate];
        $errors = [];
        foreach ($expected as $key => $value) {
            $actual = $mergedPayload[$key] ?? null;
            // Accept the validated boolean equivalents sent by HTML forms.
            if ($key === 'diploma_biodata_accurate' && in_array($actual, [true, false, 0, 1, '0', '1'], true)) {
                $actual = (bool) $actual;
            }
            if ($actual !== $value) $errors[$key] = 'Isian ini mengikuti pengajuan asal dan tidak dapat diubah pada revisi.';
        }
        if ($errors !== []) throw ValidationException::withMessages($errors);
    }

    public static function replacementRequired(SidangTaRegistrationDraft $draft, $document): bool
    {
        if (!self::isTargeted($draft)) return false;
        return in_array($document->document_type, self::editableTypes($draft), true)
            && $document->source_document_id !== null;
    }

    public static function assertCanRequest(SidangTaRegistration $source): void
    {
        $documents = $source->documents()->get();
        $errors = [];
        $rejected = 0;
        foreach (SidangTaReviewEligibility::requiredTypes($source) as $type) {
            $document = $documents->firstWhere('document_type', $type);
            if (!$document) {
                $errors['documents.'.$type] = 'Metadata dokumen belum tersedia.';
            } elseif ($document->status === 'rejected') {
                $rejected++;
            } elseif ($document->status !== 'verified' || !Storage::disk('local')->exists($document->file_path)) {
                $errors['documents.'.$type] = 'Selesaikan verifikasi atau tandai dokumen untuk diperbaiki terlebih dahulu.';
            }
        }
        if ($rejected === 0) $errors['action'] = 'Tandai minimal satu dokumen untuk diperbaiki sebelum meminta revisi.';
        if ($errors !== []) throw ValidationException::withMessages($errors);
    }

    public static function assertTransferReady(SidangTaRegistrationDraft $draft): void
    {
        $source = self::source($draft);
        self::assertCanRequest($source);
        self::assertPayloadUnchanged($draft, $draft->payload ?? []);
        $required = SidangTaReviewEligibility::requiredTypes($source);
        $sourceDocuments = $source->documents()->get()->keyBy('document_type');
        $documents = $draft->documents()->get();
        $actual = $documents->pluck('document_type')->all();
        $expected = $required;
        sort($actual); sort($expected);
        if ($actual !== $expected) throw ValidationException::withMessages(['draft' => 'Daftar dokumen revisi tidak sesuai pengajuan asal.']);
        $errors = [];
        foreach ($documents as $document) {
            $original = $sourceDocuments->get($document->document_type);
            if (!Storage::disk('local')->exists($document->file_path)) {
                $errors['documents.'.$document->document_type] = 'Berkas tidak ditemukan. Muat ulang revisi.';
            } elseif ($original->status === 'rejected') {
                if ($document->source_document_id !== null || $document->file_path === $original->file_path) {
                    $errors['documents.'.$document->document_type] = 'Unggah berkas pengganti untuk dokumen yang diminta diperbaiki.';
                }
            } elseif ($original->status !== 'verified'
                || (int) $document->source_document_id !== (int) $original->id
                || $document->file_path !== $original->file_path) {
                $errors['documents.'.$document->document_type] = 'Dokumen yang tidak direvisi harus tetap mengikuti berkas terverifikasi.';
            }
        }
        if ($errors !== []) throw ValidationException::withMessages($errors);
    }
}
