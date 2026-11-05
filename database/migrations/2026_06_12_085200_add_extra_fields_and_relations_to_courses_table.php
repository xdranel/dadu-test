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
        Schema::table('courses', function (Blueprint $table) {
            $table->string('course_id', 50)->nullable()->after('id');
            $table->unsignedInteger('enrolled_count')->default(0)->after('credits');
            $table->string('status_dosen', 100)->nullable()->after('enrolled_count');
            $table->string('semester_term', 20)->nullable()->after('semester');
            $table->string('academic_year', 20)->nullable()->after('semester_term');
            $table->string('learning_method', 50)->nullable()->after('status_dosen');
        });

        Schema::create('course_lecturers', function (Blueprint $table) {
            $table->id();
            $table->string('course_code', 20);
            $table->string('lecturer_code', 20);
            $table->string('role', 50); // 'coordinator' or 'team_member'
            $table->timestamps();

            $table->foreign('course_code')
                ->references('course_code')
                ->on('courses')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('lecturer_code')
                ->references('lecturer_code')
                ->on('lecturers')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->unique(['course_code', 'lecturer_code', 'role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_lecturers');

        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn([
                'course_id',
                'enrolled_count',
                'status_dosen',
                'semester_term',
                'academic_year',
                'learning_method',
            ]);
        });
    }
};
