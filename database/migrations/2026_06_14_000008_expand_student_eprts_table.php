<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Memperluas tabel student_eprts untuk mendukung monitoring ePRT
     * yang lebih granular, termasuk multi-attempt dan breakdown skor per kategori.
     *
     * Kolom yang ditambahkan:
     * - academic_period_id : FK ke periode akademik ujian dilaksanakan
     * - attempt_number     : Ujian ke berapa (default 1, bisa retake)
     * - test_date          : Tanggal ujian dilaksanakan
     * - category           : Kategori skor ('listening', 'structure', 'reading')
     * - raw_score          : Skor mentah sebelum konversi ke skala standar
     * - notes              : Catatan tambahan dari admin/sistem
     *
     * Catatan: Kolom listening, structure, reading, total_score sudah ada
     * di tabel original untuk menyimpan skor yang sudah dikonversi.
     */
    public function up(): void
    {
        Schema::table('student_eprts', function (Blueprint $table) {
            // Hubungkan ke periode akademik ujian
            $table->foreignId('academic_period_id')
                ->nullable()
                ->after('details')
                ->constrained('academic_periods')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            // Attempt ke berapa (mendukung ujian ulang / retake)
            $table->tinyInteger('attempt_number')->default(1)->after('academic_period_id');

            // Tanggal ujian ePRT dilaksanakan
            $table->date('test_date')->nullable()->after('attempt_number');

            // Kategori spesifik untuk breakdown skor
            // Contoh: 'listening', 'structure', 'reading'
            $table->string('category', 50)->nullable()->after('test_date');

            // Skor mentah sebelum konversi (raw IRT score)
            $table->integer('raw_score')->nullable()->after('category');

            // Catatan dari admin/sistem (teks panjang)
            $table->text('notes')->nullable()->after('raw_score');

            $table->index('academic_period_id');
            $table->index('attempt_number');
            $table->index(['student_number', 'attempt_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_eprts', function (Blueprint $table) {
            $table->dropForeign(['academic_period_id']);
            $table->dropIndex(['academic_period_id']);
            $table->dropIndex(['attempt_number']);
            $table->dropIndex(['student_number', 'attempt_number']);

            $table->dropColumn([
                'academic_period_id',
                'attempt_number',
                'test_date',
                'category',
                'raw_score',
                'notes',
            ]);
        });
    }
};
