<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Menambahkan kolom academic_period_id ke tabel courses dan student_courses
     * sebagai FK ke tabel master academic_periods.
     *
     * Kolom ini nullable untuk kompatibilitas dengan data lama yang belum
     * dikaitkan ke periode akademik yang terdefinisi.
     * Ke depannya, data baru sebaiknya selalu diisi.
     */
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->foreignId('academic_period_id')
                ->nullable()
                ->after('academic_year')
                ->constrained('academic_periods')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->index('academic_period_id');
        });

        Schema::table('student_courses', function (Blueprint $table) {
            $table->foreignId('academic_period_id')
                ->nullable()
                ->after('semester')
                ->constrained('academic_periods')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->index('academic_period_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_courses', function (Blueprint $table) {
            $table->dropForeign(['academic_period_id']);
            $table->dropIndex(['academic_period_id']);
            $table->dropColumn('academic_period_id');
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->dropForeign(['academic_period_id']);
            $table->dropIndex(['academic_period_id']);
            $table->dropColumn('academic_period_id');
        });
    }
};
