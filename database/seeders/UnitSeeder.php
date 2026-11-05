<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    /**
     * Daftar unit kerja resmi sesuai UnitSeeder.
     */
    public const UNITS = [
        [
            'name'        => 'Admisi',
            'code'        => 'ADM',
            'description' => '',
        ],
        [
            'name'        => 'Akademik',
            'code'        => 'AKA',
            'description' => '',
        ],
        [
            'name'        => 'BTP',
            'code'        => 'BTP',
            'description' => '',
        ],
        [
            'name'        => 'CDC',
            'code'        => 'CDC',
            'description' => '',
        ],
        [
            'name'        => 'Endowment & Alumni',
            'code'        => 'END',
            'description' => '',
        ],
        [
            'name'        => 'Kemahasiswaan',
            'code'        => 'KEM',
            'description' => '',
        ],
        [
            'name'        => 'Keuangan',
            'code'        => 'KEU',
            'description' => '',
        ],
        [
            'name'        => 'Logistik & Asset',
            'code'        => 'LOG',
            'description' => '',
        ],
        [
            'name'        => 'SDM',
            'code'        => 'SDM',
            'description' => '',
        ],
        [
            'name'        => 'SEKPIM',
            'code'        => 'SIS',
            'description' => '',
        ],
        [
            'name'        => 'KAPRODI',
            'code'        => 'KAPRODI',
            'description' => '',
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::UNITS as $unit) {
            Unit::firstOrCreate(['code' => $unit['code']], $unit);
        }

        if ($this->command) {
            $this->command->info('UnitSeeder: Unit kerja berhasil di-seed.');
        }
    }
}
