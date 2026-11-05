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
        Schema::create('student_attendances', function (Blueprint $table) {
            $table->id();
            $table->string('student_number', 15);
            $table->string('course_code', 20);
            $table->string('course_name');
            $table->string('class', 20)->nullable();
            $table->tinyInteger('attended_count')->default(0);
            $table->tinyInteger('meetings_count')->default(0);
            $table->string('academic_year', 10);
            $table->tinyInteger('semester');
            $table->timestamps();

            $table->foreign('student_number')->references('student_number')->on('students')->cascadeOnDelete();
            $table->index('student_number');
            $table->index(['student_number', 'academic_year', 'semester']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_attendances');
    }
};
