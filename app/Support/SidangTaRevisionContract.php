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
        SidangTaDocumentRequirements::assertKnownVersion($draft);
        return $draft->source_registration_id !== null;
    }

    public static function source(SidangTaRegistrationDraft $draft): SidangTaRegistration
    {
        abort_unless(self::isTargeted($draft), 409, 'Draft bukan revisi terarah.');
        $source = SidangTaRegistration::whereKey($draft->source_registration_id)->firstOrFail();
        abort_unless($source->requirements_version === 2, 409,
            'Persyaratan pengajuan tidak sesuai. Muat ulang atau hubungi petugas.');
        abort_unless($source->requirements_version === $draft->requirements_version
            && $source->status === 'needs_review'
            && $source->student_number === $draft->student_number
            && $source->lock_version === $draft->source_version, 409,
            'Pengajuan asal berubah. Muat ulang revisi.');
        return $source;
    }

    public static function effectiveMode(SidangTaRegistrationDraft $draft, ?array $admission = null): string
    {
        self::source($draft);
        $admission ??= app(\App\Services\SidangTaAdmissionFacts::class)->forStudent(
            \App\Models\Student::where('student_number', $draft->student_number)->firstOrFail());
        return $admission['eprt']['mode'];
    }

    public static function effectiveTypes(SidangTaRegistrationDraft $draft, ?array $admission = null): array
    {
        $source = self::source($draft);
        return SidangTaMeetRequirements::allTypes($source->diploma_biodata_accurate, self::effectiveMode($draft, $admission));
    }

    // Public wrapper retained for request, wizard and progress callers.
    public static function typesForStep(SidangTaRegistrationDraft $draft, int $step, ?array $admission = null): array
    {
        $types = self::effectiveTypes($draft, $admission);
        $group = match ($step) {
            2 => SidangTaMeetRequirements::CAPTURES,
            3 => [...SidangTaMeetRequirements::PAYMENT, SidangTaMeetRequirements::WAIVER],
            4 => [...SidangTaMeetRequirements::DIPLOMA, ...SidangTaMeetRequirements::PDM],
            default => [],
        };
        return array_values(array_intersect($group, $types));
    }

    public static function editableTypes(SidangTaRegistrationDraft $draft, ?array $admission = null): array
    {
        $source = self::source($draft);
        $required = self::effectiveTypes($draft, $admission);
        $editable = $source->documents()->whereIn('document_type', $required)
            ->where('status', 'rejected')->pluck('document_type')->all();
        if (in_array(SidangTaMeetRequirements::WAIVER, $required, true)
            && !$source->documents()->where('document_type', SidangTaMeetRequirements::WAIVER)->exists()) {
            $editable[] = SidangTaMeetRequirements::WAIVER;
        }
        return $editable;
    }

    public static function assertPayloadUnchanged(SidangTaRegistrationDraft $draft, array $mergedPayload): void
    {
        $source = self::source($draft);
        $expected = ['graduation_payment_date' => $source->graduation_payment_date?->format('Y-m-d'),
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

    public static function assertTransferReady(SidangTaRegistrationDraft $draft, ?array $admission = null): void
    {
        $source = self::source($draft);
        self::assertCanRequest($source);
        self::assertPayloadUnchanged($draft, $draft->payload ?? []);
        $required = self::effectiveTypes($draft, $admission);
        $sourceDocuments = $source->documents()->get()->keyBy('document_type');
        $documents = $draft->documents()->get();
        if (!in_array(SidangTaMeetRequirements::WAIVER, $required, true)) {
            $documents = $documents->reject(fn ($document) => $document->document_type === SidangTaMeetRequirements::WAIVER);
        }
        $actual = $documents->pluck('document_type')->all();
        $expected = $required;
        sort($actual); sort($expected);
        if ($actual !== $expected) throw ValidationException::withMessages(['draft' => 'Daftar dokumen revisi tidak sesuai pengajuan asal.']);
        $errors = [];
        foreach ($documents as $document) {
            $original = $sourceDocuments->get($document->document_type);
            if (!Storage::disk('local')->exists($document->file_path)) {
                $errors['documents.'.$document->document_type] = 'Berkas tidak ditemukan. Muat ulang revisi.';
            } elseif ($original === null) {
                if ($document->document_type !== SidangTaMeetRequirements::WAIVER || $document->source_document_id !== null) {
                    $errors['documents.'.$document->document_type] = 'Dokumen tambahan tidak sesuai persyaratan aktif.';
                }
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
