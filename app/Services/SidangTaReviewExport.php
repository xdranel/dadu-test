<?php

namespace App\Services;

use App\Models\Lecturer;
use App\Models\SidangTaRegistration;
use App\Models\StudentFinalProject;
use App\Support\SidangTaReviewEligibility as Eligibility;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class SidangTaReviewExport
{
    private const COLUMN_ORDER = [
        'ID Pengajuan',
        'Periode Sidang TA',
        'NIM',
        'Nama Mahasiswa',
        'Program Studi',
        'HP Mahasiswa',
        'HP Orang Tua/Wali',
        'Status Pengajuan',
        'Semester',
        'IPK',
        'SKS Lulus',
        'Sidang Tingkat 2',
        'KRS Sidang TA 2',
        'Judul TA Indonesia',
        'Judul TA Inggris',
        'Pembimbing 1',
        'Pembimbing 2',
        'KK Pembimbing 1',
        'TAK',
        'EPrT',
        'Surat Permakluman EPRT — Status',
        'Surat Permakluman EPRT — File',
        'Surat Permakluman EPRT — Catatan',
        'Status BPP',
        'Status SKPI',
        'Data TA di iGracias — Status',
        'Data TA di iGracias — Catatan',
        'Bimbingan Online yang Disetujui — Status',
        'Bimbingan Online yang Disetujui — Catatan',
        'Bukti Pembayaran Wisuda — Status',
        'Bukti Pembayaran Wisuda — Catatan',
        'Tanggal Bayar Wisuda',
        'SK Pembimbing TA Aktif — Status',
        'SK Pembimbing TA Aktif — Catatan',
        'Draft Buku TA (PDF) — Status',
        'Draft Buku TA (PDF) — File',
        'Draft Buku TA (PDF) — Catatan',
        'Biodata Ijazah',
        'Surat Pernyataan Biodata Ijazah — Status',
        'Surat Pernyataan Biodata Ijazah — File',
        'Surat Pernyataan Biodata Ijazah — Catatan',
        'Formulir Biodata Ijazah — Status',
        'Formulir Biodata Ijazah — File',
        'Formulir Biodata Ijazah — Catatan',
        'Bukti Pengajuan PDM — Status',
        'Bukti Pengajuan PDM — Catatan',
    ];

    public const SNAPSHOT_COLUMNS = [
//        'Snapshot NIM' => 'student_number',
//        'Snapshot Nama' => 'student_name',
//        'Snapshot Prodi' => 'study_program',
//        'Email Kampus' => 'student_email',
        'Semester' => 'semester',
        'IPK' => 'gpa',
        'SKS Lulus' => 'passed_credits',
        'Sidang Tingkat 2' => 'sidang_tingkat_2',
        'KRS Sidang TA 2' => 'ta2_krs_label',
        'Judul TA Indonesia' => 'ta_title',
        'Judul TA Inggris' => 'ta_title_en',
        'TAK' => 'tak_score',
        'EPrT' => 'eprt_score',
        'Status BPP' => 'bpp_status',
        'Status SKPI' => 'skpi_status',
        'Sumber Akademik Diperbarui' => 'academic_source_updated_at',
    ];

    private function text(mixed $value): string
    {
        if ($value === null || (is_string($value) && in_array(trim($value), ['', '-'], true))) return 'Belum tersedia';
        if (is_bool($value)) return $value ? 'Ya' : 'Tidak';
        if (is_scalar($value)) return (string) $value;
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)
            ?: 'Belum tersedia';
    }

    private function advisorColumns(Collection $registrations): array
    {
        $reference = static function (mixed $value): ?string {
            if (!is_scalar($value)) return null;
            $value = trim((string) $value);
            return in_array(mb_strtolower($value), ['', '-', 'belum tersedia'], true) ? null : $value;
        };
        $normalize = static fn (string $value): string => mb_strtolower(trim($value));
        $missing = $registrations->filter(fn ($r) => $reference($r->academic_snapshot['advisor_1'] ?? null) === null
            || $reference($r->academic_snapshot['advisor_2'] ?? null) === null)->pluck('student_number')->uniqueStrict();
        $projects = $missing->isEmpty() ? collect() : StudentFinalProject::whereIn('student_number', $missing)
            ->get(['student_number', 'advisor_1', 'advisor_2'])->keyBy('student_number');
        $sources = $registrations->mapWithKeys(function ($r) use ($reference, $projects) {
            $project = $projects->get($r->student_number);
            return [$r->id => [
                'advisor_1' => $reference($r->academic_snapshot['advisor_1'] ?? null) ?? $reference($project?->advisor_1),
                'advisor_2' => $reference($r->academic_snapshot['advisor_2'] ?? null) ?? $reference($project?->advisor_2),
            ]];
        });
        $references = $sources->flatten()->filter(fn ($value) => $value !== null)->map($normalize)->uniqueStrict()->values();
        $lecturers = $references->isEmpty() ? collect() : Lecturer::query()
            ->whereIn(DB::raw('LOWER(TRIM(lecturer_code))'), $references)
            ->orWhereIn(DB::raw('LOWER(TRIM(name))'), $references)
            ->get(['id', 'lecturer_code', 'name', 'kk']);
        $byCode = $lecturers->groupBy(fn ($l) => $normalize((string) $l->lecturer_code));
        $byName = $lecturers->groupBy(fn ($l) => $normalize((string) $l->name));

        return $sources->map(function ($advisors) use ($normalize, $byCode, $byName) {
            $columns = [];
            foreach (['advisor_1' => 'Pembimbing 1', 'advisor_2' => 'Pembimbing 2'] as $key => $label) {
                $value = $advisors[$key];
                $normalized = $normalize($value ?? '');
                // Codes identify a lecturer; duplicate names must not select an arbitrary expertise group.
                $matches = $byCode->get($normalized, $byName->get($normalized, collect()));
                $lecturer = $matches->count() === 1 ? $matches->first() : null;
                $columns[$label] = $this->text(filled($lecturer?->lecturer_code) ? $lecturer->lecturer_code : $value);
                if ($key === 'advisor_1') $columns['KK Pembimbing 1'] = $this->text($lecturer?->kk);
            }
            return $columns;
        })->all();
    }

    public function row(SidangTaRegistration $registration, ?array $advisors = null): array
    {
        $snapshot = is_array($registration->academic_snapshot) ? $registration->academic_snapshot : [];
        $row = [
            'ID Pengajuan' => (string) $registration->id,
            'Periode Sidang TA' => $this->text($registration->period?->name),
            'NIM' => $this->text($registration->student_number),
            'Nama Mahasiswa' => $this->text($registration->student_name),
            'Program Studi' => $this->text($registration->study_program),
            'HP Mahasiswa' => $this->text($registration->student_phone),
            'HP Orang Tua/Wali' => $this->text($registration->parent_phone),
            'Status Pengajuan' => SidangTaReviewQuery::STATUSES[$registration->status] ?? $this->text($registration->status),
            'Tanggal Bayar Wisuda' => $this->text($registration->graduation_payment_date?->format('Y-m-d')),
            'Biodata Ijazah' => $registration->diploma_biodata_accurate ? 'Sudah sesuai' : 'Belum sesuai (PDM)',
            'Dikirim' => $this->text($registration->submitted_at?->format('Y-m-d H:i:s')),
            'Ditinjau' => $this->text($registration->reviewed_at?->format('Y-m-d H:i:s')),
            'Catatan Petugas' => $this->text($registration->review_notes),
            'Versi Pengajuan' => (string) $registration->lock_version,
        ];
        foreach (self::SNAPSHOT_COLUMNS as $label => $key) $row[$label] = $this->text($snapshot[$key] ?? null);
        $row += $advisors ?? $this->advisorColumns(collect([$registration]))[$registration->id];
        $required = Eligibility::requiredTypes($registration);
        $labels = ['pending' => 'Menunggu verifikasi', 'verified' => 'Terverifikasi', 'rejected' => 'Perlu perbaikan'];
        foreach (SidangTaRegistration::DOCUMENT_TYPES as $type => $label) {
            $applicable = in_array($type, $required, true);
            $document = $registration->documents->firstWhere('document_type', $type);
            $row[$label.' — Status'] = !$applicable ? 'N/A' : (!$document ? 'Belum ada dokumen'
                : ($labels[$document->status] ?? $this->text($document->status)));
            $row[$label.' — File'] = !$applicable ? 'N/A' : (!$document ? 'Tidak ada metadata'
                : (Storage::disk('local')->exists($document->file_path) ? 'Tersedia' : 'Tidak ditemukan'));
            $row[$label.' — Catatan'] = !$applicable ? 'N/A' : $this->text($document?->notes);
        }
        return collect(self::COLUMN_ORDER)
            ->mapWithKeys(fn ($column) => [
                $column => $row[$column] ?? 'Belum tersedia',
            ])
            ->all();
    }

    private function spreadsheetRow(SidangTaRegistration $registration, array $advisors): array
    {
        $row = $this->row($registration, $advisors);
        $style = (new Style)->setFontColor('0563C1')->setFontUnderline();
        foreach (SidangTaRegistration::DOCUMENT_TYPES as $type => $label) {
            $column = $label.' — File';
            if (!isset($row[$column]) || $row[$column] !== 'Tersedia') {
                continue;
            }
            $document = $registration->documents->firstWhere('document_type', $type);
            $url = str_replace('"', '""', route('sidang-ta-registration-documents.preview', $document));
            // Only application-generated URLs become formulas; user input stays escaped text.
            $row[$column] = new FormulaCell('=HYPERLINK("'.$url.'","Lihat PDF")', $style);
        }
        return $row;
    }

    public function download(Collection $registrations): BinaryFileResponse
    {
        if ($registrations->isEmpty()) {
            throw ValidationException::withMessages(['export' => 'Tidak ada pengajuan yang cocok untuk diekspor.']);
        }
        $advisors = $this->advisorColumns($registrations);
        $rows = $registrations->map(fn (SidangTaRegistration $registration) => $this->spreadsheetRow($registration, $advisors[$registration->id]));
        $temporary = tempnam(sys_get_temp_dir(), 'dadu-ta-xlsx-');
        if ($temporary === false) throw new RuntimeException('File sementara ekspor tidak dapat dibuat.');
        $path = $temporary.'.xlsx';
        if (!@rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('File sementara XLSX tidak dapat disiapkan.');
        }
        try {
            $writer = new XlsxWriter();
            $writer->openToFile($path);

            $numericColumns = ['Semester', 'IPK', 'SKS Lulus', 'TAK', 'EPrT'];

            if ($rows->isNotEmpty()) {
                $headers = array_keys($rows->first());
                $headerCells = array_map(fn ($header) => new StringCell((string) $header, null), $headers);
                $writer->addRow(new Row($headerCells));

                foreach ($rows as $row) {
                    $cells = [];
                    foreach ($row as $column => $value) {
                        if ($value instanceof Cell) {
                            $cells[] = $value;
                        } elseif (in_array($column, $numericColumns, true) && is_numeric($value)) {
                            $numericVal = is_float($value) || is_int($value)
                                ? $value
                                : (str_contains((string) $value, '.') ? (float) $value : (int) $value);
                            $cells[] = new NumericCell($numericVal, null);
                        } else {
                            $cells[] = new StringCell((string) $value, null);
                        }
                    }
                    $writer->addRow(new Row($cells));
                }
            }

            $writer->close();

            clearstatcache(true, $path);
            return response()->download($path, 'Sidang_TA_'.now()->format('Y-m-d_H-i-s').'.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ])->setPrivate()->deleteFileAfterSend(true);
        } catch (Throwable $exception) {
            if (is_file($path)) @unlink($path);
            throw $exception;
        }
    }
}
