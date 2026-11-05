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
        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('module', 50);
            $table->string('status', 20)
                ->default('pending');
            $table->unsignedInteger('total_records')->default(0);
            $table->unsignedInteger('processed_records')->default(0);
            $table->unsignedInteger('skipped_records')->default(0);
            $table->text('error_message')->nullable();
            $table->string('triggered_by')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('module');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
    }
};
