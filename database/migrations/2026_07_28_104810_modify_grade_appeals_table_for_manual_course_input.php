<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('grade_appeals', function (Blueprint $table) {
            // Drop foreign key constraint untuk course_id
            $table->dropForeign(['course_id']);
            
            // Ubah course_id menjadi nullable (tidak wajib lagi)
            $table->unsignedBigInteger('course_id')->nullable()->change();
            
            // Tambah field untuk manual input mata kuliah
            $table->string('course_name_manual')->nullable()->after('study_program');
            
            // Hapus kolom yang tidak diperlukan lagi karena mata kuliah manual
            // Kita tetap simpan course_code, course_name, course_class untuk data lama
            // tapi untuk data baru akan menggunakan course_name_manual
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('grade_appeals', function (Blueprint $table) {
            // Hapus kolom course_name_manual
            $table->dropColumn('course_name_manual');
            
            // Kembalikan course_id menjadi NOT NULL
            $table->unsignedBigInteger('course_id')->nullable(false)->change();
            
            // Tambah kembali foreign key constraint
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
        });
    }
};
