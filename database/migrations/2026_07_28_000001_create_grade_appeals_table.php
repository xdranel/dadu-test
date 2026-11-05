<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grade_appeals', function (Blueprint $table) {
            $table->id();

            // Student info (denormalized for quick access)
            $table->unsignedBigInteger('user_id');
            $table->string('student_number', 30);
            $table->string('student_name');
            $table->string('study_program', 100);

            // Course info
            $table->unsignedBigInteger('course_id');
            $table->string('course_code', 20);
            $table->string('course_name');
            $table->string('course_class', 20)->nullable();

            // Lecturer info (auto-filled)
            $table->string('lecturer_name')->nullable();
            $table->string('lecturer_code', 30)->nullable();

            // Evidence file
            $table->string('evidence_file_path');
            $table->string('evidence_file_name');

            // Status & review
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('admin_notes')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');

            // Indexes
            $table->index('student_number');
            $table->index('study_program');
            $table->index('status');
            $table->index('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_appeals');
    }
};
