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
        // Create queue_tickets table
        Schema::create('queue_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->enum('status', ['waiting', 'called', 'finished'])->default('waiting');
            $table->string('device_token');
            $table->timestamp('called_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        // Create queue_states table
        Schema::create('queue_states', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('current_id')->nullable();
            $table->unsignedBigInteger('last_id')->nullable();
            $table->timestamps();

            $table->foreign('current_id')->references('id')->on('queue_tickets')->onDelete('set null');
            $table->foreign('last_id')->references('id')->on('queue_tickets')->onDelete('set null');
        });

        // Create queue_ticket_histories table (archive)
        Schema::create('queue_ticket_histories', function (Blueprint $table) {
            $table->bigInteger('id')->primary();
            $table->string('code');
            $table->enum('status', ['waiting', 'called', 'finished']);
            $table->string('device_token');
            $table->timestamp('called_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('queue_states');
        Schema::dropIfExists('queue_tickets');
        Schema::dropIfExists('queue_ticket_histories');
    }
};
