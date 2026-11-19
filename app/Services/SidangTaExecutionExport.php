<?php

namespace App\Services;

use App\Models\SidangTaRegistration;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class SidangTaExecutionExport
{
    public function download(Collection $registrations): BinaryFileResponse
    {
        if ($registrations->isEmpty()) {
            throw ValidationException::withMessages(['export' => 'Tidak ada peserta yang cocok untuk diekspor.']);
        }
        $temporary = tempnam(sys_get_temp_dir(), 'dadu-execution-');
        if ($temporary === false) {
            throw new RuntimeException('File ekspor tidak dapat dibuat.');
        }
        $path = $temporary.'.xlsx';
        if (! rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('File XLSX tidak dapat disiapkan.');
        }
        try {
            $writer = new Writer;
            $writer->openToFile($path);
            $add = fn (array $values) => $writer->addRow(new Row(array_map(fn ($value) => new StringCell((string) ($value ?? ''), null), $values)));
            $add(['Peserta TA', '', '', '', '', '', '', '', '', 'Plotting Jadwal', '', '', '', '', '', '']);
            $add(SidangTaPlottingImportService::HEADERS);
            foreach ($registrations as $registration) {
                /** @var SidangTaRegistration $registration */
                // Export assigned values only; an imported preference is not a booking.
                $dto = app(SidangTaExecutionInfoService::class)->forRegistration($registration, true);
                $assigned = $dto['is_confirmed'] && ! in_array($dto['scheduling_state'], ['cancelled', 'rejected'], true);
                $add([$dto['period'], $dto['student_number'], $dto['student_name'], $dto['study_program'], SidangTaReviewQuery::STATUSES[$dto['registration_status']] ?? $dto['registration_status'], $dto['expertise_group'], $dto['title'], $dto['advisor_1_code'], $dto['advisor_2_code'], $assigned ? $dto['weekday'] : null, $assigned ? $dto['date'] : null, $assigned ? $dto['start_time'] : null, $assigned ? $dto['end_time'] : null, $assigned ? $dto['room'] : null, $dto['examiner_1_code'], $dto['examiner_2_code']]);
            }
            $writer->close();
            clearstatcache(true, $path);

            return response()->download($path, 'Pelaksanaan_Sidang_TA_'.now()->format('Y-m-d_H-i-s').'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'])->setPrivate()->deleteFileAfterSend(true);
        } catch (Throwable $e) {
            @unlink($path);
            throw $e;
        }
    }
}
