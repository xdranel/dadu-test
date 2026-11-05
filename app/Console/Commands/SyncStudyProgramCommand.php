<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncStudyProgramCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'courses:sync-study-program';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync study programs of courses based on student_courses data';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting sync of study programs...');

        $affected = DB::update("
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

        $this->info("Successfully synced {$affected} courses.");

        return self::SUCCESS;
    }
}
