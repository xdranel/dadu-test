<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Menghapus kolom enrolled_count dari tabel courses.
     * Kolom ini adalah derived data yang bisa basi karena tidak otomatis
     * sinkron dengan tabel student_courses.
     *
     * Sebagai gantinya, gunakan Eloquent accessor getEnrolledCountAttribute()
     * pada model Course yang menghitung langsung dari relasi enrollments().
     *
     * Sebelum menghapus, pastikan tidak ada query raw yang masih
     * menggunakan kolom ini di seluruh codebase (grep: 'enrolled_count').
     */
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('enrolled_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            // Nilai default 0 agar data lama tidak NULL saat rollback
            $table->unsignedInteger('enrolled_count')->default(0)->after('credits');
        });
    }
};
