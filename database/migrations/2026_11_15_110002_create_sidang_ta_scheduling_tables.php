<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sidang_ta_execution_settings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sidang_ta_period_id')->unique()->constrained()->cascadeOnDelete();
            $t->foreignId('academic_period_id')->constrained()->restrictOnDelete();
            $t->date('start_date');
            $t->date('end_date');
            $t->json('allowed_weekdays');
            $t->time('start_time')->default('08:00:00');
            $t->time('end_time')->default('16:00:00');
            $t->time('break_start_time')->nullable()->default('12:00:00');
            $t->time('break_end_time')->nullable()->default('13:00:00');
            $t->unsignedSmallInteger('duration_minutes')->default(120);
            $t->unsignedInteger('lock_version')->default(1);
            $t->timestamps();
        });
        Schema::create('sidang_ta_schedules', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sidang_ta_registration_id')->unique()->constrained()->restrictOnDelete();
            $t->string('student_number');
            $t->string('active_student_number')->nullable()->unique();
            $t->string('mode')->default('automatic');
            $t->string('state')->default('unassigned');
            $t->foreignId('room_booking_request_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $t->boolean('was_confirmed')->default(false);
            $t->unsignedInteger('lock_version')->default(1);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sidang_ta_schedules');
        Schema::dropIfExists('sidang_ta_execution_settings');
    }
};
