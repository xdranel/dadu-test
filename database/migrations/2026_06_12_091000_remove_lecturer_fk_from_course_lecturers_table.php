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
        Schema::table('course_lecturers', function (Blueprint $table) {
            // Drop foreign key so that we can assign courses to external lecturer codes
            // without having dummy records in the lecturers table
            $table->dropForeign(['lecturer_code']);
        });

        // Clean up empty/dummy lecturer records whose name is equal to their lecturer_code
        \Illuminate\Support\Facades\DB::table('lecturers')
            ->whereRaw('name = lecturer_code')
            ->orWhereNull('name')
            ->orWhere('name', '')
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('course_lecturers', function (Blueprint $table) {
            $table->foreign('lecturer_code')
                ->references('lecturer_code')
                ->on('lecturers')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });
    }
};
