<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Menambahkan FK formal course_code → courses.course_code di tiga tabel anak:
     * - student_courses
     * - student_attendances
     * - student_grades
     *
     * ON DELETE RESTRICT : Mencegah penghapusan courses yang masih punya data mahasiswa.
     * ON UPDATE CASCADE  : Jika course_code berubah, semua tabel anak ikut diperbarui.
     *
     * CATATAN: student_grades tidak memiliki kolom 'semester' untuk relasi ke
     * academic_periods, jadi FK course_code cukup untuk referensial integrity di sana.
     *
     * PRASYARAT: Semua course_code di ketiga tabel ini harus sudah valid dan ada di
     *            tabel courses. Jalankan query validasi berikut sebelum migration:
     *
     *   SELECT DISTINCT course_code FROM student_courses
     *     WHERE course_code NOT IN (SELECT course_code FROM courses);
     */
    public function up(): void
    {
        Schema::table('student_courses', function (Blueprint $table) {
            $table->foreign('course_code')
                ->references('course_code')
                ->on('courses')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::table('student_attendances', function (Blueprint $table) {
            $table->foreign('course_code')
                ->references('course_code')
                ->on('courses')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::table('student_grades', function (Blueprint $table) {
            $table->foreign('course_code')
                ->references('course_code')
                ->on('courses')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_grades', function (Blueprint $table) {
            $table->dropForeign(['course_code']);
        });

        Schema::table('student_attendances', function (Blueprint $table) {
            $table->dropForeign(['course_code']);
        });

        Schema::table('student_courses', function (Blueprint $table) {
            $table->dropForeign(['course_code']);
        });
    }
};
