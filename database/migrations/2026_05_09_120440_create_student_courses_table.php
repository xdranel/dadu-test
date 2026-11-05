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
        Schema::create('student_courses', function (Blueprint $table) {
            $table->id();
            $table->string('student_number', 15);
            $table->string('course_code', 20);
            $table->string('course_name');
            $table->string('course_class', 20);
            $table->string('lecturer_code', 20)->nullable();
            $table->string('lecturer_name')->nullable();
            $table->string('faculty', 100)->nullable();
            $table->string('study_program')->nullable();
            $table->string('academic_year', 10);
            $table->tinyInteger('semester');
            $table->tinyInteger('credits')->default(3);
            $table->tinyInteger('quota')->nullable();
            $table->timestamps();

            $table->foreign('student_number')->references('student_number')->on('students')->cascadeOnDelete();
            $table->index('student_number');
            $table->index('course_code');
            $table->index('academic_year');
            $table->index(['student_number', 'academic_year', 'semester']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_courses');
    }
};
