<?php

namespace App\Jobs;

use App\Models\Course;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessCourseSync implements ShouldQueue
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
        Log::info('Memulai sinkronisasi ' . count($this->data) . ' data Matakuliah via Queue.');

        collect($this->data)->chunk(500)->each(function ($chunk) {
            $dataToUpsert = [];
            foreach ($chunk as $row) {
                if (empty($row['kode_mk'])) continue;

                $dataToUpsert[] = [
                    'course_code'   => strtoupper($row['kode_mk']),
                    'name'          => $row['nama_mk']  ?? '',
                    'study_program' => $row['prodi']    ?? '',
                    'class'         => $row['kelas']    ?? 'A',
                    'semester'      => $row['semester'] ?? 1,
                    'credits'       => $row['sks']      ?? 3,
                    'type'          => $row['jenis']    ?? 'Wajib',
                    'status'        => $row['status']   ?? 'Aktif',
                ];
            }

            if (!empty($dataToUpsert)) {
                Course::upsert(
                    $dataToUpsert,
                    ['course_code'], // Unique key
                    ['name', 'study_program', 'class', 'semester', 'credits', 'type', 'status'] // Columns to update
                );
            }
        });

        Log::info('Sinkronisasi data Matakuliah via Queue selesai.');
    }
}
