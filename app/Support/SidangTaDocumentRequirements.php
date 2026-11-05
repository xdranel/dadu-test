<?php

namespace App\Support;

use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDraft;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class SidangTaDocumentRequirements
{
    public const CAPTURES = [
        'level_2_result_capture', 'igracias_approval_capture', 'ta_progress_capture',
        'ta_data_capture', 'online_supervision_capture', 'skpi_tak_capture',
    ];
    public const PAYMENT = [
        'graduation_payment_proof', 'bpp_payment_proof', 'ta_supervisor_sk',
        'eprt_lac_certificate', 'thesis_draft', 'transcript_khs', 'ta_course_ksm',
    ];
    public const DIPLOMA = ['diploma_statement', 'diploma_biodata_form'];
    public const PDM = ['pdm_submission_proof'];

    public static function typesForStep(int $step, ?bool $accurate = null): array
    {
        return match ($step) {
            2 => self::CAPTURES,
            3 => self::PAYMENT,
            4 => $accurate === null ? [] : ($accurate ? self::DIPLOMA : self::PDM),
            default => [],
        };
    }

    public static function assertKnownVersion(SidangTaRegistrationDraft $draft): void
    {
        abort_unless(in_array($draft->requirements_version, [1, 2], true), 409,
            'Versi persyaratan draft tidak dikenali. Muat ulang pengajuan.');
    }

    public static function typesForDraftStep(SidangTaRegistrationDraft $draft, int $step, ?array $payload = null): array
    {
        self::assertKnownVersion($draft);
        $payload ??= $draft->payload ?? [];
        $choice = $payload['diploma_biodata_accurate'] ?? null;
        $accurate = $choice === null ? null : (bool) $choice;
        if ($draft->requirements_version === 1) return self::typesForStep($step, $accurate);
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
        return $draft->requirements_version === 2 ? SidangTaMeetRequirements::fileRules($type) : self::fileRules($type);
    }

    // Syarat lengkap: dipakai navigasi, tinjau dan finalisasi.
    public static function scalarRules(int $step): array
    {
        return match ($step) {
            1 => ['student_phone' => 'required|string|max:30', 'parent_phone' => 'required|string|max:30'],
            3 => ['graduation_payment_date' => 'required|date|before_or_equal:today'],
            4 => ['diploma_biodata_accurate' => 'required|boolean'],
            default => [],
        };
    }

    // Syarat save: field belum ada/null boleh, tetapi nilai yang diisi tetap valid.
    public static function draftScalarRules(int $step): array
    {
        return match ($step) {
            3 => ['graduation_payment_date' => 'sometimes|nullable|date|before_or_equal:today'],
            4 => ['diploma_biodata_accurate' => 'sometimes|nullable|boolean'],
            default => self::scalarRules($step),
        };
    }

    public static function fileRules(string $type): array
    {
        return ['nullable', 'file', 'max:10240',
            in_array($type, self::CAPTURES, true) ? 'mimes:pdf,jpg,jpeg,png' : 'mimes:pdf'];
    }

    public static function progress(SidangTaRegistrationDraft $draft, int $step): array
    {
        $payload = $draft->payload ?? [];
        $labels = ['student_phone' => 'Nomor HP mahasiswa', 'parent_phone' => 'Nomor HP orang tua/wali',
            'graduation_payment_date' => 'Tanggal pembayaran wisuda',
            'diploma_biodata_accurate' => 'Pilihan kesesuaian biodata ijazah'];
        $validator = Validator::make($payload, self::scalarRules($step));
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
        foreach ($types as $type) {
            $document = $draft->documents->firstWhere('document_type', $type);
            if ($document && Storage::disk('local')->exists($document->file_path)) {
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

