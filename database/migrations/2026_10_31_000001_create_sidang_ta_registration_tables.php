<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sidang_ta_periods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->dateTime('open_at');
            $table->dateTime('close_at');
            $table->boolean('is_active')->default(false);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('sidang_ta_registrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sidang_ta_period_id');
            $table->string('student_number', 20)->index();
            $table->string('student_name');
            $table->string('study_program')->nullable();
            $table->string('student_phone', 30);
            $table->string('parent_phone', 30);
            $table->date('graduation_payment_date');
            $table->boolean('diploma_biodata_accurate');
            $table->json('academic_snapshot')->nullable();
            $table->enum('status', ['submitted', 'needs_review', 'rejected', 'approved'])->default('submitted');
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();
            $table->foreign('sidang_ta_period_id', 'sidang_ta_reg_period_fk')
                ->references('id')->on('sidang_ta_periods')->restrictOnDelete();
        });

        Schema::create('sidang_ta_registration_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sidang_ta_registration_id');
            $table->string('document_type', 80);
            $table->string('file_name');
            $table->string('file_path');
            $table->unsignedInteger('file_size')->nullable();
            $table->string('file_type', 10)->nullable();
            $table->enum('status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['sidang_ta_registration_id', 'document_type'], 'sidang_ta_reg_doc_type_unique');
            $table->foreign('sidang_ta_registration_id', 'sidang_ta_docs_reg_fk')
                ->references('id')->on('sidang_ta_registrations')->cascadeOnDelete();
        });

        Schema::create('sidang_ta_registration_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sidang_ta_registration_id');
            $table->string('action');
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('performed_at')->nullable();
            $table->timestamps();
            $table->foreign('sidang_ta_registration_id', 'sidang_ta_logs_reg_fk')
                ->references('id')->on('sidang_ta_registrations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sidang_ta_registration_logs');
        Schema::dropIfExists('sidang_ta_registration_documents');
        Schema::dropIfExists('sidang_ta_registrations');
        Schema::dropIfExists('sidang_ta_periods');
    }
};
