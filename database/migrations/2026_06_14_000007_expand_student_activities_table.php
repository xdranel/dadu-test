<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Memperluas tabel student_activities untuk kebutuhan monitoring TAK
     * (Transkrip Aktivitas Kemahasiswaan) secara lebih detail.
     *
     * Kolom yang ditambahkan:
     * - activity_level    : Tingkat kegiatan (kampus, fakultas, nasional, internasional)
     * - activity_date     : Tanggal pelaksanaan kegiatan
     * - academic_period_id: FK ke periode akademik kegiatan berlangsung
     * - document_path     : Path file bukti dokumen yang diupload
     * - verified_at       : Timestamp verifikasi oleh admin
     * - verified_by       : NIP/kode admin yang memverifikasi
     *
     * Catatan: activity_name dan activity_type sudah ada di tabel original.
     */
    public function up(): void
    {
        Schema::table('student_activities', function (Blueprint $table) {
            // Tingkat kegiatan: 'university', 'faculty', 'national', 'international'
            $table->string('activity_level', 50)->nullable()->after('activity_type');

            // Tanggal pelaksanaan (menggantikan 'date' yang sudah ada, tapi kita tambah ini
            // sebagai alias yang lebih semantik — 'date' tetap dipertahankan untuk kompatibilitas)
            $table->date('activity_date')->nullable()->after('activity_level');

            // Hubungkan ke periode akademik
            $table->foreignId('academic_period_id')
                ->nullable()
                ->after('activity_date')
                ->constrained('academic_periods')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            // Path file bukti dokumen (PDF, gambar, dll.)
            $table->string('document_path')->nullable()->after('academic_period_id');

            // Kolom verifikasi
            $table->timestamp('verified_at')->nullable()->after('document_path');
            $table->string('verified_by', 20)->nullable()->after('verified_at');

            $table->index('activity_level');
            $table->index('activity_type');
            $table->index('academic_period_id');
            $table->index('verified_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_activities', function (Blueprint $table) {
            $table->dropForeign(['academic_period_id']);
            $table->dropIndex(['activity_level']);
            $table->dropIndex(['activity_type']);
            $table->dropIndex(['academic_period_id']);
            $table->dropIndex(['verified_at']);

            $table->dropColumn([
                'activity_level',
                'activity_date',
                'academic_period_id',
                'document_path',
                'verified_at',
                'verified_by',
            ]);
        });
    }
};
