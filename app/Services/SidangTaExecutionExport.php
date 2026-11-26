<?php

namespace App\Services;

use App\Models\SidangTaRegistration;
use Closure;
use DateTimeImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
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

        return $this->downloadWorkbook('Pelaksanaan_Sidang_TA_'.now()->format('Y-m-d_H-i-s').'.xlsx', function (Writer $writer) use ($registrations) {
            $this->writeHeaders($writer);
            foreach ($registrations as $registration) {
                /** @var SidangTaRegistration $registration */
                // Export assigned values only; an imported preference is not a booking.
                $dto = app(SidangTaExecutionInfoService::class)->forRegistration($registration, true);
                $assigned = $dto['is_confirmed'] && ! in_array($dto['scheduling_state'], ['cancelled', 'rejected'], true);
                $date = $assigned && $dto['date'] ? DateTimeImmutable::createFromFormat('!Y-m-d', $dto['date']) : null;
                $writer->addRow($this->textRow([
                    $dto['period'], $dto['student_number'], $dto['student_name'], $dto['study_program'],
                    SidangTaReviewQuery::STATUSES[$dto['registration_status']] ?? $dto['registration_status'],
                    $dto['expertise_group'], $dto['title'], $dto['advisor_1_code'], $dto['advisor_2_code'],
                    $assigned ? $dto['weekday'] : null, $date ? $date->format('d/m/Y') : null,
                    $assigned ? $dto['start_time'] : null, $assigned ? $dto['end_time'] : null,
                    $assigned ? $dto['room'] : null, $dto['examiner_1_code'], $dto['examiner_2_code'], $dto['ta_type'] ?? 'Individu',
                ]));
            }
        });
    }

    public function downloadTemplate(): BinaryFileResponse
    {
        $options = new Options;
        $options->mergeCells(0, 1, 8, 1);
        $options->mergeCells(9, 1, 16, 1);
        foreach ([26, 16, 26, 24, 19, 22, 40, 19, 19, 12, 16, 14, 14, 21, 17, 17, 16] as $column => $width) {
            $options->setColumnWidth($width, $column + 1);
        }

        return $this->downloadWorkbook('Template_Plotting_Sidang_TA.xlsx', function (Writer $writer) {
            $writer->getCurrentSheet()->setName('Plotting Jadwal');
            $headerStyle = (new Style)->setFontBold()->setFontColor('FFFFFF')->setBackgroundColor('334155')->setShouldWrapText();
            $this->writeHeaders($writer, $headerStyle);
            $writer->addRow($this->textRow([
                'Contoh Periode Sidang TA', '0000000000', 'Mahasiswa Contoh', 'S1 Informatika',
                'Disetujui', 'Contoh Kelompok Keahlian', 'Judul Tugas Akhir Contoh', 'PB1', 'PB2',
                'Senin', '23/11/2026', '08:00', '09:30', '', 'PJ1', 'PJ2', 'Individu',
            ]));
        }, $options);
    }

    private function writeHeaders(Writer $writer, ?Style $style = null): void
    {
        $groups = array_replace(array_fill(0, count(SidangTaPlottingImportService::HEADERS), ''), SidangTaPlottingImportService::GROUP_HEADERS);
        $writer->addRow($this->textRow($groups, $style));
        $headers = $this->textRow(SidangTaPlottingImportService::HEADERS, $style);
        if ($style) {
            $headers->setHeight(34);
        }
        $writer->addRow($headers);
    }

    private function textRow(array $values, ?Style $style = null): Row
    {
        return new Row(array_map(fn ($value) => new StringCell((string) ($value ?? ''), null), $values), $style);
    }

    private function downloadWorkbook(string $filename, Closure $write, ?Options $options = null): BinaryFileResponse
    {
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
            $writer = new Writer($options);
            $writer->openToFile($path);
            $write($writer);
            $writer->close();
            clearstatcache(true, $path);

            return response()->download($path, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'])->setPrivate()->deleteFileAfterSend(true);
        } catch (Throwable $e) {
            @unlink($path);
            throw $e;
        }
    }
}
