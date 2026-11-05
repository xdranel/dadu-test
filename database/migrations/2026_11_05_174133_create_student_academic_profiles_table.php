<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_academic_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('student_number', 15)->unique();
            $table->json('groups')->nullable();
            $table->json('bpp_periods')->nullable();
            $table->timestamps();
            $table->foreign('student_number')->references('student_number')->on('students')
                ->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_academic_profiles');
    }
};
