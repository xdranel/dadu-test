<?php

namespace App\Constants;

class IgraciasProdi
{
    /**
     * Pemetaan ID Prodi internal iGracias dengan label namanya.
     * ID ini didapat dari URL scraping (filter_study_program).
     * Silakan update label nama sesuai dengan prodi sebenarnya jika sudah diketahui.
     */
    const MAPPING = [
        113 => 'Teknik Komputer',
        114 => 'Prodi 114',
        115 => 'Prodi 115',
        116 => 'Prodi 116',
        117 => 'Prodi 117',
        118 => 'Prodi 118',
        119 => 'Prodi 119',
        120 => 'Prodi 120',
        121 => 'Informatika',
        122 => 'Prodi 122',
        123 => 'Prodi 123',
    ];

    /**
     * Get prodi names by their IDs.
     */
    public static function getNamesByIds(array $ids): array
    {
        return array_values(array_filter(
            array_map(fn($id) => self::MAPPING[$id] ?? null, $ids)
        ));
    }
}
