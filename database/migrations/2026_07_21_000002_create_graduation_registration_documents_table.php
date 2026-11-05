<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('graduation_registration_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('graduation_registration_id');
            $table->foreign('graduation_registration_id', 'grad_reg_docs_reg_id_fk')
                  ->references('id')->on('graduation_registrations')
                  ->cascadeOnDelete();
            $table->string('document_type');
            $table->string('file_name');
            $table->string('file_path');
            $table->unsignedInteger('file_size')->nullable();
            $table->string('file_type', 10)->nullable();
            $table->enum('status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('graduation_registration_documents');
    }
};
