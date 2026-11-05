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
        Schema::create('student_grades', function (Blueprint $table) {
            $table->id();
            $table->string('student_number', 15);
            $table->string('course_code', 20);
            $table->string('course_name');
            $table->tinyInteger('credits')->default(3);
            $table->string('type', 50)->nullable();
            $table->string('grade', 5)->nullable();
            $table->tinyInteger('level')->nullable();
            $table->string('academic_year', 10)->nullable();
            $table->timestamps();

            $table->foreign('student_number')->references('student_number')->on('students')->cascadeOnDelete();
            $table->index('student_number');
            $table->index(['student_number', 'level']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_grades');
    }
};
