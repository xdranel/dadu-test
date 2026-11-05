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
            // Ubah course_code, course_name, course_class menjadi nullable
            // karena sekarang menggunakan course_name_manual untuk input manual
            $table->string('course_code', 20)->nullable()->change();
            $table->string('course_name')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('grade_appeals', function (Blueprint $table) {
            // Kembalikan ke NOT NULL
            $table->string('course_code', 20)->nullable(false)->change();
            $table->string('course_name')->nullable(false)->change();
        });
    }
};
