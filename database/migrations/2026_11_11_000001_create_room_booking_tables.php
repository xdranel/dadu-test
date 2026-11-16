<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->date('usage_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('description')->nullable();
            $table->string('source')->default('csv');
            $table->timestamps();
            $table->index(['room_id', 'usage_date']);
        });
        Schema::create('room_import_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('filename');
            $table->unsignedInteger('rows_imported')->default(0);
            $table->json('errors')->nullable();
            $table->timestamps();
        });
        Schema::create('room_booking_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->enum('type', ['single', 'recurring']);
            $table->date('start_date');
            $table->date('end_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('purpose');
            $table->unsignedInteger('participants');
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->text('admin_note')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['room_id', 'status']);
        });
        Schema::create('room_booking_occurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('room_booking_requests')->cascadeOnDelete();
            $table->date('usage_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();
            $table->unique(['request_id', 'usage_date']);
            $table->index('usage_date');
        });
        Schema::create('room_booking_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('room_booking_requests')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_booking_histories');
        Schema::dropIfExists('room_booking_occurrences');
        Schema::dropIfExists('room_booking_requests');
        Schema::dropIfExists('room_import_logs');
        Schema::dropIfExists('room_schedules');
    }
};
