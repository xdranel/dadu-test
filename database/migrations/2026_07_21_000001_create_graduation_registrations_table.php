<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('graduation_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('student_number', 20)->index();
            $table->string('student_name');
            $table->string('study_program')->nullable();

            // Period & contact
            $table->string('graduation_period');
            $table->string('student_phone', 20)->nullable();
            $table->string('parent_phone', 20)->nullable();
            $table->string('personal_email')->nullable();

            // Thesis
            $table->string('thesis_title_id', 500)->nullable();
            $table->string('thesis_title_en', 500)->nullable();

            // Advisors (manual fields — future: auto-fill from iGracias sync)
            $table->string('advisor_1_code', 20)->nullable();
            $table->string('advisor_1_name')->nullable();
            $table->string('advisor_2_code', 20)->nullable();
            $table->string('advisor_2_name')->nullable();

            // Examiners (manual fields — future: auto-fill from iGracias sync)
            $table->string('examiner_1_code', 20)->nullable();
            $table->string('examiner_1_name')->nullable();
            $table->string('examiner_2_code', 20)->nullable();
            $table->string('examiner_2_name')->nullable();
            $table->string('examiner_3_code', 20)->nullable();
            $table->string('examiner_3_name')->nullable();

            // Academic (future: auto-fill from iGracias sync)
            $table->decimal('tak_score', 8, 2)->nullable();
            $table->date('exam_date')->nullable();
            $table->date('revision_deadline')->nullable();
            $table->date('revision_completion_date')->nullable();

            // Biodata correction note
            $table->text('biodata_correction_note')->nullable();

            // Status workflow
            $table->enum('status', ['submitted', 'needs_review', 'rejected', 'approved', 'cancelled'])
                  ->default('submitted');

            // Audit
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->foreign('submitted_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('graduation_registrations');
    }
};
