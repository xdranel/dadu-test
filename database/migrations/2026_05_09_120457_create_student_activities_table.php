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
        Schema::create('student_activities', function (Blueprint $table) {
            $table->id();
            $table->string('student_number', 15);
            $table->string('activity_name');
            $table->string('activity_type', 100)->nullable();
            $table->string('role', 100)->nullable();
            $table->integer('points')->default(0);
            $table->date('date')->nullable();
            $table->string('details')->nullable();
            $table->timestamps();

            $table->foreign('student_number')->references('student_number')->on('students')->cascadeOnDelete();
            $table->index('student_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_activities');
    }
};
