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
        Schema::create('document_import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('mode', 20); // upload, update
            $table->string('status', 30)->default('staging'); // staging, validated, processing, completed, failed, cancelled
            $table->json('summary')->nullable();
            $table->string('file_path')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('document_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('document_import_batches')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('original_name');
            $table->json('data');
            $table->string('status', 20)->default('ok'); // ok, warning, error, skipped
            $table->json('messages')->nullable();
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['batch_id', 'status']);
            $table->index(['batch_id', 'row_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_import_rows');
        Schema::dropIfExists('document_import_batches');
    }
};
