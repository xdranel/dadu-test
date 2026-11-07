<?php

namespace App\Support;

use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDraft;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class SidangTaDocumentRequirements
{
    public const CAPTURES = SidangTaMeetRequirements::CAPTURES;
    public const PAYMENT = SidangTaMeetRequirements::PAYMENT;
    public const DIPLOMA = SidangTaMeetRequirements::DIPLOMA;
    public const PDM = SidangTaMeetRequirements::PDM;

    public static function typesForStep(int $step, ?bool $accurate = null, string $mode = 'automatic'): array
    {
        return SidangTaMeetRequirements::typesForStep($step, $accurate, $mode);
    }

    public static function assertKnownVersion(SidangTaRegistrationDraft $draft): void
    {
        abort_unless($draft->requirements_version === 2, 409,
            'Persyaratan pengajuan tidak sesuai. Muat ulang atau hubungi petugas.');
    }

    public static function typesForDraftStep(SidangTaRegistrationDraft $draft, int $step, ?array $payload = null): array
    {
        self::assertKnownVersion($draft);
        $payload ??= $draft->payload ?? [];
        $choice = $payload['diploma_biodata_accurate'] ?? null;
        $accurate = $choice === null ? null : (bool) $choice;
        if (SidangTaRevisionContract::isTargeted($draft)) return SidangTaRevisionContract::typesForStep($draft, $step);
        $mode = 'unknown';
        if ($step === 3) {
            $student = \App\Models\Student::where('student_number', $draft->student_number)->firstOrFail();
            $mode = app(\App\Services\SidangTaAdmissionFacts::class)->forStudent($student)['eprt']['mode'];
        }
        return SidangTaMeetRequirements::typesForStep($step, $accurate, $mode);
    }

    public static function fileRulesForDraft(SidangTaRegistrationDraft $draft, string $type): array
    {
        self::assertKnownVersion($draft);
        return self::fileRules($type);
    }

    // Syarat lengkap: dipakai navigasi, tinjau dan finalisasi.
    public static function scalarRules(int $step, ?array $payload = null): array
    {
        return match ($step) {
            1 => ['student_phone' => ['bail', 'required', 'string', 'regex:/\A[0-9]{8,15}\z/'],
                'parent_phone' => ['bail', 'required', 'string', 'regex:/\A[0-9]{8,15}\z/']],
            3 => ['graduation_payment_date' => 'required|date|before_or_equal:today'],
            4 => ['diploma_biodata_accurate' => 'required|boolean',
                'pdm_submission_date' => self::requiresPdmDate($payload)
                    ? 'required|date|before_or_equal:today' : 'nullable|date|before_or_equal:today'],
            default => [],
        };
    }

    // Syarat save: field belum ada/null boleh, tetapi nilai yang diisi tetap valid.
    public static function draftScalarRules(int $step, ?array $payload = null): array
    {
        return match ($step) {
            3 => ['graduation_payment_date' => 'sometimes|nullable|date|before_or_equal:today'],
            4 => ['diploma_biodata_accurate' => 'sometimes|nullable|boolean',
                'pdm_submission_date' => 'sometimes|nullable|date|before_or_equal:today'],
            default => self::scalarRules($step, $payload),
        };
    }

    private static function requiresPdmDate(?array $payload): bool
    {
        return in_array($payload['diploma_biodata_accurate'] ?? null, [false, 0, '0'], true);
    }

    public static function fileRules(string $type): array
    {
        return SidangTaMeetRequirements::fileRules($type);
    }

    public static function progress(SidangTaRegistrationDraft $draft, int $step): array
    {
        $payload = $draft->payload ?? [];
        $labels = ['student_phone' => 'Nomor WA Aktif Mahasiswa', 'parent_phone' => 'Nomor WA Aktif Orang Tua/Wali',
            'graduation_payment_date' => 'Tanggal pembayaran wisuda',
            'diploma_biodata_accurate' => 'Pilihan kesesuaian biodata ijazah',
            'pdm_submission_date' => 'Tanggal pengajuan PDM'];
        $validator = Validator::make($payload, self::scalarRules($step, $payload));
        $validator->passes();
        $missing = [];
        foreach (array_keys($validator->failed()) as $key) {
            $missing[] = $labels[$key] ?? $key;
        }
        $choice = $payload['diploma_biodata_accurate'] ?? null;
        $accurate = $choice === null ? null : (bool) $choice;
        $types = self::typesForDraftStep($draft, $step, $payload);
        $draft->loadMissing('documents');
        $stored = 0;
        $editable = SidangTaRevisionContract::isTargeted($draft) ? SidangTaRevisionContract::editableTypes($draft) : [];
        foreach ($types as $type) {
            $document = $draft->documents->firstWhere('document_type', $type);
            if ($document && Storage::disk('local')->exists($document->file_path)
                && !(in_array($type, $editable, true) && $document->source_document_id !== null)) {
                $stored++;
            } else {
                $missing[] = SidangTaMeetRequirements::LABELS[$type] ?? SidangTaRegistration::DOCUMENT_TYPES[$type] ?? $type;
            }
        }
        return [
            'complete' => $missing === [],
            'stored_documents' => $stored,
            'required_documents' => $step === 4 && $accurate === null ? null : count($types),
            'missing' => $missing,
        ];
    }

    public static function complete(SidangTaRegistrationDraft $draft, int $step): bool
    {
        return self::progress($draft, $step)['complete'];
    }

    public static function firstIncompleteStep(SidangTaRegistrationDraft $draft): ?int
    {
        foreach (range(1, 4) as $step) {
            if (!self::complete($draft, $step)) return $step;
        }
        return null;
    }
}
