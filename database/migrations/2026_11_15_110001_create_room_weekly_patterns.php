<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_schedule_sources', function (Blueprint $t) {
            $t->id();
            $t->foreignId('academic_period_id')->unique()->constrained()->restrictOnDelete();
            $t->boolean('is_complete')->default(false);
            $t->string('scope')->default('semester');
            $t->string('fingerprint', 64);
            $t->unsignedInteger('version')->default(1);
            $t->json('metadata')->nullable();
            $t->timestamps();
        });
        Schema::create('room_weekly_patterns', function (Blueprint $t) {
            $t->id();
            $t->foreignId('academic_period_id')->constrained()->restrictOnDelete();
            $t->foreignId('room_id')->constrained()->restrictOnDelete();
            $t->unsignedTinyInteger('weekday');
            $t->time('start_time');
            $t->time('end_time');
            $t->string('lecturer_code', 50)->nullable();
            $t->string('description')->nullable();
            $t->string('source_room_label')->nullable();
            $t->string('source_identity', 64);
            $t->date('reference_date');
            $t->timestamps();
            $t->unique(['academic_period_id', 'source_identity']);
            $t->index(['room_id', 'weekday']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_weekly_patterns');
        Schema::dropIfExists('room_schedule_sources');
    }
};
