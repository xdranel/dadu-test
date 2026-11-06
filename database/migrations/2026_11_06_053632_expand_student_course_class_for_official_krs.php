<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_courses', function (Blueprint $table) {
            $table->string('course_class', 255)->change();
        });
    }

    public function down(): void
    {
        $length = DB::getDriverName() === 'sqlite' ? 'LENGTH(course_class)' : 'CHAR_LENGTH(course_class)';
        if (DB::table('student_courses')->whereRaw($length.' > 20')->exists()) {
            throw new RuntimeException('Rollback ditolak: ada nama kelas melebihi 20 karakter.');
        }
        Schema::table('student_courses', function (Blueprint $table) {
            $table->string('course_class', 20)->change();
        });
    }
};
