<?php

namespace App\Services;

use ZipArchive;

class DocumentImportZipExtractor
{
    /**
     * Ekstraksi arsip ZIP secara aman dengan proteksi zip-slip, zip-bomb, dan entri tersembunyi.
     */
    public function extract(string $zipFilePath, string $destinationDir): array
    {
        $zip = new ZipArchive();
        if ($zip->open($zipFilePath) !== true) {
            throw new \RuntimeException('Gagal membuka berkas ZIP.');
        }

        if (!is_dir($destinationDir)) {
            mkdir($destinationDir, 0755, true);
        }

        $realDest = realpath($destinationDir);
        if ($realDest === false) {
            $zip->close();
            throw new \RuntimeException('Direktori tujuan ekstraksi tidak valid.');
        }

        $maxFiles = (int) config('documents.import_max_files', 500);
        $maxTotalBytes = 524288000; // 500 MB

        $totalFiles = 0;
        $totalBytes = 0;
        $extractedFiles = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (!$stat) continue;

            $entryName = $stat['name'];

            // Lewati direktori
            if (str_ends_with($entryName, '/') || str_ends_with($entryName, '\\')) {
                continue;
            }

            // Lewati entri tersembunyi / metadata macOS
            $baseName = basename($entryName);
            if (str_starts_with($baseName, '.') || str_contains($entryName, '__MACOSX')) {
                continue;
            }

            // Proteksi Zip Bomb
            $totalFiles++;
            if ($totalFiles > $maxFiles) {
                $zip->close();
                throw new \OverflowException("Jumlah berkas di dalam ZIP melebihi batas maksimal ({$maxFiles} berkas).");
            }

            $totalBytes += $stat['size'];
            if ($totalBytes > $maxTotalBytes) {
                $zip->close();
                throw new \OverflowException('Total ukuran berkas terkompresi melebihi batas 500 MB.');
            }

            // Proteksi Zip Slip
            // Sanitasi path tujuan
            $targetPath = $destinationDir . DIRECTORY_SEPARATOR . $baseName;

            // Pastikan tidak ada path traversal
            if (str_contains($entryName, '..') || str_starts_with($entryName, '/') || str_starts_with($entryName, '\\')) {
                $zip->close();
                throw new \SecurityException("Entri berbahaya terdeteksi di dalam ZIP: {$entryName}");
            }

            // Ekstrak berkas
            $stream = $zip->getStream($entryName);
            if (!$stream) {
                continue;
            }

            $outFp = fopen($targetPath, 'wb');
            stream_copy_to_stream($stream, $outFp);
            fclose($outFp);
            fclose($stream);

            $extractedFiles[$baseName] = $targetPath;
        }

        $zip->close();

        return $extractedFiles;
    }
}
