<?php

namespace Database\Seeders;

use App\Models\AcademicPeriod;
use Illuminate\Database\Seeder;

class AcademicPeriodSeeder extends Seeder
{
    /**
     * Seed 2 periode akademik contoh.
     *
     * Jalankan dengan:
     *   php artisan db:seed --class=AcademicPeriodSeeder
     */
    public function run(): void
    {
        $periods = [
            [
                'year'       => '2024/2025',
                'semester'   => 1,
                'label'      => 'Ganjil 2024/2025',
                'start_date' => '2024-09-01',
                'end_date'   => '2025-02-28',
                'is_active'  => false,
            ],
            [
                'year'       => '2024/2025',
                'semester'   => 2,
                'label'      => 'Genap 2024/2025',
                'start_date' => '2025-03-01',
                'end_date'   => '2025-08-31',
                'is_active'  => true,  // Periode yang sedang berjalan
            ],
        ];

        foreach ($periods as $period) {
            AcademicPeriod::updateOrCreate(
                // Kunci unik: kombinasi year + semester (sesuai constraint DB)
                ['year' => $period['year'], 'semester' => $period['semester']],
                $period
            );
        }

        $this->command->info('✅ AcademicPeriodSeeder: ' . count($periods) . ' periode akademik berhasil di-seed.');
    }
}
