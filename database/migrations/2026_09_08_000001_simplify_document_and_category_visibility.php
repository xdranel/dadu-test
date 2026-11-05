<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Simplifikasi visibilitas dokumen & kategori menjadi 2 tingkat:
     * - 'publik' (Publik dan Mahasiswa)
     * - 'internal' (Pegawai dan Dosen)
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            // Ubah sementara ke VARCHAR agar aman dari constraint case-insensitive ENUM MySQL
            DB::statement("ALTER TABLE `documents` MODIFY `visibility` VARCHAR(50) NOT NULL DEFAULT 'publik'");
        }

        // Petakan nilai lama ke skema baru
        DB::table('documents')
            ->whereIn('visibility', ['Publik', 'publik', 'Mahasiswa', 'mahasiswa'])
            ->update(['visibility' => 'publik']);

        DB::table('documents')
            ->whereIn('visibility', ['Pegawai', 'pegawai', 'Privat', 'privat', 'Dosen', 'dosen', 'internal'])
            ->update(['visibility' => 'internal']);

        DB::table('documents')
            ->whereNotIn('visibility', ['publik', 'internal'])
            ->update(['visibility' => 'internal']);

        if ($driver === 'mysql') {
            // Kunci enum hanya ke 2 nilai final
            DB::statement("ALTER TABLE `documents` MODIFY `visibility` ENUM('publik','internal') NOT NULL DEFAULT 'publik'");
        }

        // Tambahkan kolom visibility pada tabel document_categories jika belum ada
        Schema::table('document_categories', function (Blueprint $table) {
            if (!Schema::hasColumn('document_categories', 'visibility')) {
                $table->enum('visibility', ['publik', 'internal'])
                    ->default('publik')
                    ->after('description');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `documents` MODIFY `visibility` VARCHAR(50) NOT NULL DEFAULT 'Publik'");
        }

        DB::table('documents')
            ->where('visibility', 'publik')
            ->update(['visibility' => 'Publik']);

        DB::table('documents')
            ->where('visibility', 'internal')
            ->update(['visibility' => 'Pegawai']);

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `documents` MODIFY `visibility` ENUM('Pegawai','Publik') NOT NULL DEFAULT 'Publik'");
        }

        Schema::table('document_categories', function (Blueprint $table) {
            if (Schema::hasColumn('document_categories', 'visibility')) {
                $table->dropColumn('visibility');
            }
        });
    }
};
