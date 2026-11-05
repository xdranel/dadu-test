<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * PERHATIAN: Migrasi ini mengubah kolom enum `visibility` pada tabel `documents`
     * dari ['Privat','Dosen','Mahasiswa','Publik'] menjadi ['Pegawai','Publik'].
     * Nilai lama selain 'Publik' dipetakan ke 'Pegawai'.
     * BACKUP DATABASE TERLEBIH DAHULU sebelum menjalankan migrasi ini di produksi.
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        // Petakan nilai lama ke skema baru terlebih dulu (aman untuk semua driver).
        DB::table('documents')
            ->whereNotIn('visibility', ['Pegawai', 'Publik'])
            ->update(['visibility' => 'Pegawai']);

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `documents` MODIFY `visibility` ENUM('Pegawai','Publik') NOT NULL DEFAULT 'Publik'");
        }
        // SQLite: kolom disimpan sebagai teks, tidak perlu ALTER enum.
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `documents` MODIFY `visibility` ENUM('Privat','Dosen','Mahasiswa','Publik') NOT NULL DEFAULT 'Privat'");
        }

        DB::table('documents')
            ->where('visibility', 'Pegawai')
            ->update(['visibility' => 'Privat']);
    }
};
