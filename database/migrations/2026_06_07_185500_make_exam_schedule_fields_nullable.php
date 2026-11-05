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
        Schema::table('exam_schedules', function (Blueprint $table) {
            $table->date('exam_date')->nullable()->change();
            $table->time('start_time')->nullable()->change();
            $table->time('end_time')->nullable()->change();
            $table->string('room', 50)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exam_schedules', function (Blueprint $table) {
            $table->date('exam_date')->nullable(false)->change();
            $table->time('start_time')->nullable(false)->change();
            $table->time('end_time')->nullable(false)->change();
            $table->string('room', 50)->nullable(false)->change();
        });
    }
};
