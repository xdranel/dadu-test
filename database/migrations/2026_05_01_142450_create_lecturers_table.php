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
        Schema::create('lecturers', function (Blueprint $table) {
            $table->id();
            $table->string('nip')->unique()->nullable()->index(); 
            $table->string('lecturer_code')->unique()->index();
            $table->string('name');
            $table->string('jfa')->nullable(); 
            $table->string('homebase')->index();
            $table->string('phone_number')->nullable();
            $table->enum('employment_status', ['Tetap', 'LB'])->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lecturers');
    }
};
