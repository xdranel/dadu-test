<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('graduation_periods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->dateTime('open_at');
            $table->dateTime('close_at');
            $table->boolean('is_active')->default(false);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('graduation_periods');
    }
};
