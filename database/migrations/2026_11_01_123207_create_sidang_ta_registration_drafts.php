<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sidang_ta_registrations', function (Blueprint $table) {
            $table->unsignedInteger('lock_version')->default(1);
        });

        Schema::create('sidang_ta_registration_drafts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('owner_user_id');
            $table->string('student_number', 20)->index('sta_draft_student_idx');
            $table->unsignedBigInteger('sidang_ta_period_id');
            $table->unsignedBigInteger('source_registration_id')->nullable();
            $table->unsignedInteger('source_version')->nullable();
            $table->json('payload');
            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('reviewed_version')->nullable();
            $table->string('status', 20)->default('active');
            $table->unsignedBigInteger('submitted_registration_id')->nullable();
            $table->timestamps();

            $table->foreign('owner_user_id', 'sta_draft_owner_fk')
                ->references('id')->on('users')->restrictOnDelete();
            $table->foreign('sidang_ta_period_id', 'sta_draft_period_fk')
                ->references('id')->on('sidang_ta_periods')->restrictOnDelete();
            $table->foreign('source_registration_id', 'sta_draft_source_fk')
                ->references('id')->on('sidang_ta_registrations')->restrictOnDelete();
            $table->foreign('submitted_registration_id', 'sta_draft_submitted_fk')
                ->references('id')->on('sidang_ta_registrations')->restrictOnDelete();
        });

        Schema::create('sidang_ta_registration_draft_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('draft_id');
            $table->string('document_type', 80);
            $table->string('file_name');
            $table->string('file_path');
            $table->unsignedInteger('file_size')->nullable();
            $table->string('file_type', 10)->nullable();
            $table->unsignedBigInteger('source_document_id')->nullable();
            $table->timestamps();

            $table->unique(['draft_id', 'document_type'], 'sta_draft_doc_type_uq');
            $table->foreign('draft_id', 'sta_draft_doc_draft_fk')
                ->references('id')->on('sidang_ta_registration_drafts')->cascadeOnDelete();
            $table->foreign('source_document_id', 'sta_draft_doc_source_fk')
                ->references('id')->on('sidang_ta_registration_documents')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sidang_ta_registration_draft_documents');
        Schema::dropIfExists('sidang_ta_registration_drafts');
        Schema::table('sidang_ta_registrations', function (Blueprint $table) {
            $table->dropColumn('lock_version');
        });
    }
};
