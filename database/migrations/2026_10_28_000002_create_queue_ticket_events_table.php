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
        Schema::create('queue_ticket_events', function (Blueprint $table) {
            $table->id();
            // Tanpa foreign key cascade agar event tetap bertahan setelah reset harian / penghapusan tiket
            $table->unsignedBigInteger('ticket_id')->nullable()->index();
            $table->date('queue_date')->index();
            $table->string('nim')->nullable()->index();
            $table->unsignedInteger('number');
            $table->string('event'); // created, called, skipped, finished, undo_call
            $table->unsignedBigInteger('counter_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('queue_ticket_events');
    }
};
