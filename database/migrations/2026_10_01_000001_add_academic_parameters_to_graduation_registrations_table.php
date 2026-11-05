<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambahkan kolom parameter akademik baru ke tabel graduation_registrations.
     * Kolom-kolom ini diisi dari JSON Import iGracias + BASILA.
     */
    public function up(): void
    {
        Schema::table('graduation_registrations', function (Blueprint $table) {
            $table->decimal('gpa', 4, 2)->nullable()->after('last_synced_at')
                ->comment('IPK kumulatif mahasiswa dari import JSON iGracias');

            $table->smallInteger('passed_credits')->nullable()->unsigned()->after('gpa')
                ->comment('Jumlah SKS yang sudah lulus dari import JSON iGracias');

            $table->smallInteger('remaining_credits_db')->nullable()->unsigned()->after('passed_credits')
                ->comment('Jumlah SKS belum lulus dari import JSON iGracias');

            $table->tinyInteger('active_semester_db')->nullable()->unsigned()->after('remaining_credits_db')
                ->comment('Semester aktif mahasiswa dari import JSON iGracias');

            $table->string('payment_status', 50)->nullable()->after('active_semester_db')
                ->comment('Status keuangan/pembayaran wisuda dari import JSON');

            $table->string('thesis_grade_index', 5)->nullable()->after('payment_status')
                ->comment('Indeks nilai tugas akhir dari import JSON (A, B+, dll)');

            $table->timestamp('json_imported_at')->nullable()->after('thesis_grade_index')
                ->comment('Waktu terakhir data di-import dari JSON iGracias+BASILA');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('graduation_registrations', function (Blueprint $table) {
            $table->dropColumn([
                'gpa',
                'passed_credits',
                'remaining_credits_db',
                'active_semester_db',
                'payment_status',
                'thesis_grade_index',
                'json_imported_at',
            ]);
        });
    }
};
