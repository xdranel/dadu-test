<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ResetSidangTaTestData extends Command
{
    protected $signature = 'sidang-ta:reset-test-data {--execute : Delete all TA test registrations, drafts, documents and logs}';

    protected $description = 'Preview or explicitly reset ALL Sidang TA test submissions, preserving accounts, academic data and periods';

    /** Child-first order preserves foreign-key constraints. */
    private const TABLES = [
        'sidang_ta_registration_draft_documents',
        'sidang_ta_registration_drafts',
        'sidang_ta_registration_documents',
        'sidang_ta_registration_logs',
        'sidang_ta_registrations',
    ];

    public function handle(): int
    {
        $this->line('Database: '.DB::connection()->getDatabaseName());
        $this->warn('Stop web writes, queue workers and scheduled TA jobs before executing this maintenance reset.');
        $this->line('Preserved: users, students, academic/sync data, other modules, roles and Sidang TA periods.');

        if (! $this->option('execute')) {
            $this->info('DRY RUN: no database or file changes. Add --execute to reset ALL TA submissions.');
            $this->summary($this->snapshot(false));

            return self::SUCCESS;
        }

        try {
            $snapshot = DB::transaction(function () {
                // Match the application's Student -> Draft -> Registration lock order.
                DB::table('students')->orderBy('id')->lockForUpdate()->pluck('id');
                $snapshot = $this->snapshot(true);
                $this->summary($snapshot);
                foreach (self::TABLES as $table) {
                    // Delete the locked snapshot only; never truncate or reset sequences.
                    foreach (array_chunk($snapshot['ids'][$table], 500) as $ids) {
                        DB::table($table)->whereIn('id', $ids)->delete();
                    }
                }

                return $snapshot;
            });
        } catch (Throwable $error) {
            $this->error('Reset rolled back; no file cleanup was attempted: '.$error->getMessage());

            return self::FAILURE;
        }

        // The metadata transaction must succeed before any filesystem deletion.
        $deleted = 0;
        $skipped = 0;
        foreach ($snapshot['paths'] as $path) {
            try {
                if (! $this->ownedLocalFile($path) || $this->referenced($path)) {
                    $this->skip($path, 'unsafe, missing, non-local or still referenced');
                    $skipped++;
                    continue;
                }
                if (! Storage::disk('local')->delete($path)) {
                    $this->skip($path, 'storage refused deletion');
                    $skipped++;
                    continue;
                }
                $deleted++;
            } catch (Throwable $error) {
                $this->skip($path, $error->getMessage());
                $skipped++;
            }
        }
        $this->info("TA metadata reset complete. Files deleted: {$deleted}; skipped: {$skipped}.");

        return self::SUCCESS;
    }

    private function snapshot(bool $lock): array
    {
        $ids = [];
        $rows = [];
        foreach (['sidang_ta_registration_drafts', 'sidang_ta_registrations',
                     'sidang_ta_registration_draft_documents', 'sidang_ta_registration_documents',
                     'sidang_ta_registration_logs'] as $table) {
            $query = DB::table($table)->orderBy('id');
            $rows[$table] = ($lock ? $query->lockForUpdate() : $query)->get();
            $ids[$table] = $rows[$table]->pluck('id')->all();
        }
        $paths = $rows['sidang_ta_registration_draft_documents']->pluck('file_path')
            ->merge($rows['sidang_ta_registration_documents']->pluck('file_path'))->unique()->values()->all();

        return compact('ids', 'paths');
    }

    private function summary(array $snapshot): void
    {
        foreach (self::TABLES as $table) {
            $this->line($table.': '.count($snapshot['ids'][$table]));
        }
        $this->line('Files referenced (unique): '.count($snapshot['paths']));
    }

    private function ownedLocalFile(string $path): bool
    {
        if (config('filesystems.disks.local.driver') !== 'local'
            || ! preg_match('~\Asidang_ta/(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9_-]+(?:\.[A-Za-z0-9_-]+)*\z~D', $path)) {
            return false;
        }
        $root = realpath(Storage::disk('local')->path(''));
        $resolved = realpath(Storage::disk('local')->path($path));
        if ($root === false || $resolved === false || ! is_file($resolved)
            || ! str_starts_with($resolved, $root.DIRECTORY_SEPARATOR.'sidang_ta'.DIRECTORY_SEPARATOR)) {
            return false;
        }
        // Do not follow even internal symlinks: they can alias an unrelated module's file.
        $current = $root;
        foreach (explode('/', $path) as $part) {
            $current .= DIRECTORY_SEPARATOR.$part;
            if (is_link($current)) {
                return false;
            }
        }

        return true;
    }

    private function referenced(string $path): bool
    {
        // Raw queries include soft-deleted documents. Inspect all path columns so
        // graduation predicate_document_path and grade-appeal evidence are guarded too.
        $disk = Storage::disk('local');
        $target = realpath($disk->path($path));
        foreach (Schema::getTableListing() as $table) {
            foreach (Schema::getColumnListing($table) as $column) {
                if (! str_contains($column, 'path')) {
                    continue;
                }
                if (DB::table($table)->where($column, $path)->exists()) {
                    return true;
                }
                // A retained path may use ./ or another spelling of the same file.
                foreach (DB::table($table)->whereNotNull($column)->select($column)->cursor() as $row) {
                    $other = $row->{$column};
                    if (is_string($other) && $other !== '' && $target !== false
                        && realpath($disk->path($other)) === $target) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function skip(string $path, string $reason): void
    {
        $this->warn('File preserved: '.$path.' ('.$reason.').');
        Log::warning('Sidang TA reset preserved file', ['path' => $path, 'reason' => $reason]);
    }
}
