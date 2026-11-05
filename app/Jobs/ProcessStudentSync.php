<?php

namespace App\Jobs;

use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessStudentSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1200;

    protected array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function handle(): void
    {
        Log::info('Memulai sinkronisasi ' . count($this->data) . ' data Mahasiswa via Queue.');

        collect($this->data)->chunk(500)->each(function ($chunk) {
            $dataToUpsert = [];
            foreach ($chunk as $row) {
                if (empty($row['nim'])) continue;

                $dataToUpsert[] = [
                    'student_number'  => $row['nim'],
                    'name'            => $row['nama_mahasiswa'] ?? '',
                    'academic_year'   => $row['tahun_akademik'] ?? date('Y'),
                    'study_program'   => $row['program_studi']  ?? '',
                    'status'          => $row['status']         ?? 'Aktif',
                    'email'           => $row['email']          ?? '',
                    'active_semester' => 1,
                ];
            }

            if (!empty($dataToUpsert)) {
                Student::upsert(
                    $dataToUpsert,
                    ['student_number'], 
                    ['name', 'academic_year', 'study_program', 'status', 'email'] // Columns to update
                );
            }
        });

        Log::info('Sinkronisasi data Mahasiswa via Queue selesai.');
    }
}
