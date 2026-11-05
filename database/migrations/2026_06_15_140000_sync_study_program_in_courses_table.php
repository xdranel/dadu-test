<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::update("
            UPDATE courses
            SET study_program = (
                SELECT study_program 
                FROM student_courses 
                WHERE student_courses.course_code = courses.course_code
                  AND study_program IS NOT NULL
                LIMIT 1
            )
            WHERE (LOWER(study_program) = 'umum' OR study_program IS NULL)
              AND EXISTS (
                  SELECT 1 
                  FROM student_courses 
                  WHERE student_courses.course_code = courses.course_code 
                    AND student_courses.study_program IS NOT NULL
              )
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No down migration needed since we are only syncing data
    }
};
