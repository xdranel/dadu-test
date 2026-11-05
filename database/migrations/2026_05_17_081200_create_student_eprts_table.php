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
        Schema::create('student_eprts', function (Blueprint $table) {
            $table->id();
            $table->string('student_number', 15);
            $table->string('student_name')->nullable();
            $table->integer('listening')->nullable();
            $table->integer('structure')->nullable();
            $table->integer('reading')->nullable();
            $table->integer('total_score')->nullable();
            $table->string('details', 150)->nullable();
            $table->timestamps();

            $table->foreign('student_number')->references('student_number')->on('students')->cascadeOnDelete();
            $table->index('student_number');
            $table->index('total_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_eprts');
    }
};
