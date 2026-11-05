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
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('course_code', 20)->unique();
            $table->string('name');
            $table->string('study_program', 50);
            $table->string('class', 10);
            $table->unsignedTinyInteger('semester');
            $table->unsignedTinyInteger('credits');
            $table->enum('type', ['Wajib', 'Pilihan'])->default('Wajib');
            $table->enum('status', ['Aktif', 'Tidak Aktif'])->default('Aktif');
            $table->timestamps();

            $table->index('study_program');
            $table->index('semester');
            $table->index('class');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
