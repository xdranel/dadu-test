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
        Schema::create('exam_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')
                  ->constrained('courses')
                  ->cascadeOnDelete();
            $table->string('lecturer_code', 20);
            $table->date('exam_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('room', 50);
            $table->string('proctor_name')->nullable();
            $table->string('assessment_type');
            $table->string('execution_method');
            $table->string('execution_time');
            $table->string('participant_division');
            $table->string('question_type');
            $table->string('answer_sheet_type');
            $table->string('needs_scratch_paper', 20);
            $table->string('proctor_type');
            $table->text('exam_question_url')->nullable();
            $table->text('custom_answer_sheet_url')->nullable();
            $table->text('exam_rules')->nullable();
            $table->timestamps();

            $table->index('course_id');
            $table->index('exam_date');
            $table->index('lecturer_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_schedules');
    }
};
