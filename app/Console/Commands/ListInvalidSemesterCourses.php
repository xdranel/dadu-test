<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Course;

class ListInvalidSemesterCourses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'courses:list-invalid-semester';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List courses with invalid or unrealistic semester numbers';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Finding courses with invalid semesters...');

        // Note: The Course model accessor getSemesterAttribute() will automatically
        // return null if the underlying database value is invalid (not 1-14).
        // To find courses that have invalid semesters in the database,
        // we can query the raw attribute value or just check Course::all()
        // and check if getRawOriginal('semester') is invalid.
        $courses = Course::all()->filter(function ($course) {
            $rawSem = $course->getRawOriginal('semester');
            return !is_numeric($rawSem) || $rawSem < 1 || $rawSem > 14;
        });

        if ($courses->isEmpty()) {
            $this->info('No courses with invalid semesters found. Great!');
            return self::SUCCESS;
        }

        $headers = ['ID', 'Course Code', 'Name', 'Raw Semester', 'Credits', 'Status'];
        $data = $courses->map(function ($c) {
            return [
                $c->id,
                $c->course_code,
                $c->name,
                $c->getRawOriginal('semester') ?? 'NULL / Empty',
                $c->credits,
                $c->status,
            ];
        })->toArray();

        $this->table($headers, $data);
        $this->warn("Found {$courses->count()} courses with invalid semesters.");

        return self::SUCCESS;
    }
}
