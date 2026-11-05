<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Menambahkan kolom is_external dan institution ke tabel lecturers
     * untuk mendukung penanganan dosen luar kampus secara proper.
     * Dengan adanya kolom ini, semua dosen (internal maupun eksternal)
     * bisa terdaftar di tabel yang sama dengan validasi FK yang benar.
     */
    public function up(): void
    {
        Schema::table('lecturers', function (Blueprint $table) {
            // Menandai apakah dosen berasal dari luar kampus
            $table->boolean('is_external')->default(false)->after('email_domain');

            // Asal institusi dosen luar (null jika dosen internal)
            $table->string('institution')->nullable()->after('is_external');

            $table->index('is_external');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lecturers', function (Blueprint $table) {
            $table->dropIndex(['is_external']);
            $table->dropColumn(['is_external', 'institution']);
        });
    }
};
