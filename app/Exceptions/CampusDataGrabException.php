<?php

namespace App\Exceptions;

use Exception;

class CampusDataGrabException extends Exception
{
    /**
     * Error code dari sistem kampus (jika ada).
     */
    protected ?string $campusErrorCode = null;

    /**
     * Data tambahan untuk debugging.
     */
    protected array $context = [];

    public function __construct(
        string $message = "Gagal mengambil data dari sistem kampus",
        int $code = 0,
        ?Exception $previous = null,
        ?string $campusErrorCode = null,
        array $context = []
    ) {
        parent::__construct($message, $code, $previous);
        $this->campusErrorCode = $campusErrorCode;
        $this->context = $context;
    }

    public function getCampusErrorCode(): ?string
    {
        return $this->campusErrorCode;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Factory method untuk berbagai jenis error.
     */
    public static function studentNotFound(string $studentNumber): self
    {
        return new self(
            "Data mahasiswa dengan NIM {$studentNumber} tidak ditemukan di sistem kampus.",
            404,
            null,
            'STUDENT_NOT_FOUND',
            ['student_number' => $studentNumber]
        );
    }

    public static function serviceUnavailable(): self
    {
        return new self(
            "Sistem kampus sedang tidak dapat diakses. Silakan coba beberapa saat lagi.",
            503,
            null,
            'SERVICE_UNAVAILABLE'
        );
    }

    public static function incompleteData(string $studentNumber, array $missingFields): self
    {
        return new self(
            "Data mahasiswa tidak lengkap. Field yang hilang: " . implode(', ', $missingFields),
            422,
            null,
            'INCOMPLETE_DATA',
            ['student_number' => $studentNumber, 'missing_fields' => $missingFields]
        );
    }

    public static function apiError(string $message, int $httpCode = 500): self
    {
        return new self(
            "Error dari API sistem kampus: {$message}",
            $httpCode,
            null,
            'API_ERROR'
        );
    }
}
