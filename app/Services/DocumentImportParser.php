<?php

namespace App\Services;

use OpenSpout\Reader\XLSX\Reader as XlsxReader;

class DocumentImportParser
{
    /**
     * Parse berkas XLSX atau CSV menjadi array baris data asosiatif.
     */
    public function parse(string $filePath): array
    {
        $maxRows = (int) config('documents.import_max_rows', 2000);
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($ext === 'csv' || $ext === 'txt') {
            return $this->parseCsv($filePath, $maxRows);
        }

        return $this->parseXlsx($filePath, $maxRows);
    }

    /**
     * Parse berkas CSV secara aman dengan deteksi delimiter dan penanganan BOM.
     */
    protected function parseCsv(string $filePath, int $maxRows): array
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \RuntimeException('Gagal membaca berkas CSV.');
        }

        // Hapus UTF-8 BOM jika ada
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        if (empty($lines)) {
            return [];
        }

        // Deteksi delimiter berdasarkan baris pertama
        $firstLine = $lines[0];
        $delimiter = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';

        $rows = [];
        $headers = null;
        $rowNum = 0;

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $rawCells = str_getcsv($line, $delimiter);
            $cells = array_map([$this, 'sanitizeCell'], $rawCells);

            if ($headers === null) {
                $headers = array_map('trim', $cells);
                continue;
            }

            $rowNum++;
            if ($rowNum > $maxRows) {
                throw new \OverflowException("Jumlah baris melebihi batas maksimal ({$maxRows} baris).");
            }

            $row = $this->combineRowWithHeaders($headers, $cells);
            $row['_row_number'] = $rowNum + 1; // +1 untuk memperhitungkan baris header
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Parse berkas XLSX menggunakan OpenSpout.
     */
    protected function parseXlsx(string $filePath, int $maxRows): array
    {
        $reader = new XlsxReader();
        $reader->open($filePath);

        $rows = [];
        $headers = null;
        $rowNum = 0;

        foreach ($reader->getSheetIterator() as $sheetIndex => $sheet) {
            // Hanya membaca sheet pertama (Template Import)
            foreach ($sheet->getRowIterator() as $rowEntity) {
                $rawCells = $rowEntity->toArray();
                $cells = array_map([$this, 'sanitizeCell'], $rawCells);

                if ($headers === null) {
                    $headers = array_map('trim', $cells);
                    continue;
                }

                // Cek jika seluruh baris kosong
                if ($this->isEmptyRow($cells)) {
                    continue;
                }

                $rowNum++;
                if ($rowNum > $maxRows) {
                    $reader->close();
                    throw new \OverflowException("Jumlah baris melebihi batas maksimal ({$maxRows} baris).");
                }

                $row = $this->combineRowWithHeaders($headers, $cells);
                $row['_row_number'] = $rowNum + 1;
                $rows[] = $row;
            }
            break; // Hanya sheet pertama
        }

        $reader->close();
        return $rows;
    }

    /**
     * Pasangkan header dengan nilai sel secara toleran.
     */
    protected function combineRowWithHeaders(array $headers, array $cells): array
    {
        $row = [];
        $headerKeys = config('documents.import_headers', [
            'old_name'      => 'Nama Lama',
            'document_type' => 'Jenis Dokumen',
            'role'          => 'Role Dokumen',
            'folder'        => 'Folder',
            'new_name'      => 'Nama Baru',
            'nim'           => 'NIM',
            'keywords'      => 'Keyword',
        ]);

        foreach ($headerKeys as $key => $headerLabel) {
            $idx = $this->findHeaderIndex($headers, $headerLabel);
            $val = ($idx !== false && isset($cells[$idx])) ? $cells[$idx] : '';
            $row[$key] = $val;
        }

        $parsingErrors = [];
        if (!empty($row['nim']) && preg_match('/[0-9]+(\.[0-9]+)?[eE][\+\-]?[0-9]+/i', (string) $row['nim'])) {
            $parsingErrors[] = "Kolom NIM terdeteksi menggunakan format notasi ilmiah ({$row['nim']}), kemungkinan terjadi kehilangan digit / leading zero. Mohon ubah format sel menjadi Text di spreadsheet.";
        }
        $row['_parsing_errors'] = $parsingErrors;

        return $row;
    }

    protected function findHeaderIndex(array $headers, string $label): int|false
    {
        $cleanLabel = strtolower(trim($label));
        foreach ($headers as $i => $h) {
            if (strtolower(trim((string) $h)) === $cleanLabel) {
                return $i;
            }
        }
        return false;
    }

    /**
     * Bersihkan nilai sel dan netralkan potensi formula injection (=, +, -, @).
     */
    protected function sanitizeCell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $val = trim((string) $value);

        // Netralkan CSV Formula Injection
        if (preg_match('/^[=\+\-@\t\r]/', $val)) {
            // Beri prefix kutip satu agar diperlakukan sebagai teks murni tanpa eksekusi formula
            $val = "'" . $val;
        }

        return trim($val);
    }

    protected function isEmptyRow(array $cells): bool
    {
        foreach ($cells as $c) {
            if (trim((string) $c) !== '') {
                return false;
            }
        }
        return true;
    }
}
