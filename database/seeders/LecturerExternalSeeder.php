<?php

namespace Database\Seeders;

use App\Models\Lecturer;
use Illuminate\Database\Seeder;

class LecturerExternalSeeder extends Seeder
{
    /**
     * Seed contoh 1 dosen internal dan 1 dosen eksternal.
     *
     * Jalankan dengan:
     *   php artisan db:seed --class=LecturerExternalSeeder
     *
     * Catatan: Seeder ini hanya sebagai contoh/referensi.
     * Untuk lingkungan produksi, data dosen biasanya di-import
     * dari sistem SIAKAD atau portal dosen melalui mekanisme sync.
     */
    public function run(): void
    {
        // ─── Dosen Internal ────────────────────────────────────────────────
        Lecturer::updateOrCreate(
            ['lecturer_code' => 'INT001'],
            [
                'name'              => 'Dr. Budi Santoso',
                'front_title'       => 'Dr.',
                'back_title'        => 'M.Kom.',
                'nidn'              => '0011223344',
                'nip_ypt'           => 'YPT.2010.001',
                'employment_status' => 'Tetap',
                'active_status'     => 'Aktif',
                'study_program'     => 'Teknik Informatika',
                'jfa'               => 'Lektor Kepala',
                'academic_interest' => 'Kecerdasan Buatan, Machine Learning',
                'is_external'       => false,   // Dosen kampus sendiri
                'institution'       => null,     // Tidak perlu diisi untuk dosen internal
            ]
        );

        // ─── Dosen Eksternal (Luar Kampus / Praktisi) ──────────────────────
        Lecturer::updateOrCreate(
            ['lecturer_code' => 'EXT001'],
            [
                'name'              => 'Ir. Siti Rahma',
                'front_title'       => 'Ir.',
                'back_title'        => 'M.T.',
                'nidn'              => null,     // Dosen luar mungkin tidak punya NIDN di sini
                'nip_ypt'           => null,
                'employment_status' => 'LB',    // Luar Biasa
                'active_status'     => 'Aktif',
                'study_program'     => null,
                'jfa'               => null,
                'academic_interest' => 'Rekayasa Perangkat Lunak, DevOps',
                'is_external'       => true,    // Tandai sebagai dosen luar kampus
                'institution'       => 'PT. Teknologi Nusantara',  // Asal institusi
            ]
        );

        $this->command->info('✅ LecturerExternalSeeder: 2 dosen (1 internal, 1 eksternal) berhasil di-seed.');
        $this->command->line('   → INT001: Dr. Budi Santoso (Internal)');
        $this->command->line('   → EXT001: Ir. Siti Rahma (Eksternal - PT. Teknologi Nusantara)');
    }
}
