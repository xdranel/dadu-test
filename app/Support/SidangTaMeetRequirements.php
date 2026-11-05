<?php

namespace App\Support;

use InvalidArgumentException;

class SidangTaMeetRequirements
{
    public const CAPTURES = ['ta_data_capture', 'online_supervision_capture'];
    public const PAYMENT = ['graduation_payment_proof', 'ta_supervisor_sk', 'thesis_draft'];
    public const WAIVER = 'eprt_waiver_letter';
    public const DIPLOMA = ['diploma_statement', 'diploma_biodata_form'];
    public const PDM = ['pdm_submission_proof'];

    public const LABELS = [
        'ta_data_capture' => 'Capture Data TA (Menu TA/PA)',
        'online_supervision_capture' => 'Capture Bimbingan Online yang Disetujui',
        'graduation_payment_proof' => 'Bukti Pembayaran Wisuda',
        'ta_supervisor_sk' => 'SK Pembimbing TA Aktif',
        'thesis_draft' => 'Draft Buku TA (PDF)',
        'eprt_waiver_letter' => 'Surat Permakluman EPRT',
        'diploma_statement' => 'Surat Pernyataan Biodata Ijazah',
        'diploma_biodata_form' => 'Formulir Biodata Ijazah',
        'pdm_submission_proof' => 'Bukti Pengajuan PDM',
    ];

    public static function typesForStep(int $step, ?bool $accurate, string $eprtMode): array
    {
        self::assertMode($eprtMode);
        return match ($step) {
            1 => [],
            2 => self::CAPTURES,
            3 => $eprtMode === 'waiver' ? [...self::PAYMENT, self::WAIVER] : self::PAYMENT,
            4 => $accurate === null ? [] : ($accurate ? self::DIPLOMA : self::PDM),
            default => throw new InvalidArgumentException('Tahap sidang TA tidak valid.'),
        };
    }

    public static function allTypes(bool $accurate, string $eprtMode): array
    {
        return array_merge(self::typesForStep(2, $accurate, $eprtMode),
            self::typesForStep(3, $accurate, $eprtMode), self::typesForStep(4, $accurate, $eprtMode));
    }

    public static function fileRules(string $type): array
    {
        if (!array_key_exists($type, self::LABELS)) {
            throw new InvalidArgumentException('Jenis upload tidak termasuk persyaratan hasil meet.');
        }
        return ['nullable', 'file', 'max:10240', in_array($type, self::CAPTURES, true)
            ? 'mimes:pdf,jpg,jpeg,png' : 'mimes:pdf'];
    }

    private static function assertMode(string $mode): void
    {
        if (!in_array($mode, ['automatic', 'waiver', 'retest', 'unknown'], true)) {
            throw new InvalidArgumentException('Mode EPRT tidak dikenal.');
        }
    }
}

