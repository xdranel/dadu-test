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
        Schema::table('student_courses', function (Blueprint $table) {
            $table->dropForeign(['student_number']);
            $table->foreign('student_number')
                ->references('student_number')
                ->on('students')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::table('student_attendances', function (Blueprint $table) {
            $table->dropForeign(['student_number']);
            $table->foreign('student_number')
                ->references('student_number')
                ->on('students')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::table('student_grades', function (Blueprint $table) {
            $table->dropForeign(['student_number']);
            $table->foreign('student_number')
                ->references('student_number')
                ->on('students')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::table('student_activities', function (Blueprint $table) {
            $table->dropForeign(['student_number']);
            $table->foreign('student_number')
                ->references('student_number')
                ->on('students')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::table('student_eprts', function (Blueprint $table) {
            $table->dropForeign(['student_number']);
            $table->foreign('student_number')
                ->references('student_number')
                ->on('students')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_courses', function (Blueprint $table) {
            $table->dropForeign(['student_number']);
            $table->foreign('student_number')
                ->references('student_number')
                ->on('students')
                ->cascadeOnDelete();
        });

        Schema::table('student_attendances', function (Blueprint $table) {
            $table->dropForeign(['student_number']);
            $table->foreign('student_number')
                ->references('student_number')
                ->on('students')
                ->cascadeOnDelete();
        });

        Schema::table('student_grades', function (Blueprint $table) {
            $table->dropForeign(['student_number']);
            $table->foreign('student_number')
                ->references('student_number')
                ->on('students')
                ->cascadeOnDelete();
        });

        Schema::table('student_activities', function (Blueprint $table) {
            $table->dropForeign(['student_number']);
            $table->foreign('student_number')
                ->references('student_number')
                ->on('students')
                ->cascadeOnDelete();
        });

        Schema::table('student_eprts', function (Blueprint $table) {
            $table->dropForeign(['student_number']);
            $table->foreign('student_number')
                ->references('student_number')
                ->on('students')
                ->cascadeOnDelete();
        });
    }
};
