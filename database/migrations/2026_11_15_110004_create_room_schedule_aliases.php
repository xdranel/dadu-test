<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_schedule_aliases', function (Blueprint $t) {
            $t->id();
            $t->foreignId('room_id')->constrained()->restrictOnDelete();
            $t->string('alias_key')->unique();
            $t->string('label');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_schedule_aliases');
    }
};
