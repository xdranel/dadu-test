<?php

namespace App\Console\Commands;

use App\Enums\AccessGroup;
use App\Models\Document;
use App\Models\DocumentCategory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillAccessGroupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'documents:backfill-access-group {--dry-run : Menjalankan simulasi tanpa menyimpan perubahan ke database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill access_group pada dokumen dan kategori dari visibilitas lama sesuai mapping';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info($dryRun
            ? '=== Memulai Backfill Access Group (MODE DRY-RUN) ==='
            : '=== Memulai Backfill Access Group ==='
        );

        $categorySummary = [
            'publik'     => 0,
            'dosen_staf' => 0,
            'akademik'   => 0,
        ];

        $documentSummary = [
            'publik'     => 0,
            'dosen_staf' => 0,
            'akademik'   => 0,
        ];

        // 1. Backfill Root Categories yang access_group-nya NULL
        DocumentCategory::whereNull('parent_id')
            ->whereNull('access_group')
            ->chunkById(100, function ($roots) use (&$categorySummary, $dryRun) {
                foreach ($roots as $root) {
                    $target = AccessGroup::fromLegacyVisibility($root->visibility)?->value ?? AccessGroup::PUBLIK->value;
                    $categorySummary[$target] = ($categorySummary[$target] ?? 0) + 1;

                    if (! $dryRun) {
                        $root->updateQuietly(['access_group' => $target]);
                    }
                }
            });

        // 2. Backfill Subfolder Categories yang access_group-nya NULL (mewarisi dari root)
        DocumentCategory::whereNotNull('parent_id')
            ->whereNull('access_group')
            ->chunkById(100, function ($subfolders) use (&$categorySummary, $dryRun) {
                foreach ($subfolders as $sub) {
                    $root = $sub->getRoot();
                    $target = $root->access_group instanceof AccessGroup
                        ? $root->access_group->value
                        : ($root->access_group ?? AccessGroup::fromLegacyVisibility($sub->visibility)?->value ?? AccessGroup::PUBLIK->value);

                    $categorySummary[$target] = ($categorySummary[$target] ?? 0) + 1;

                    if (! $dryRun) {
                        $sub->updateQuietly(['access_group' => $target]);
                    }
                }
            });

        // 3. Backfill Documents yang access_group-nya NULL
        Document::with('category')
            ->whereNull('access_group')
            ->chunkById(100, function ($documents) use (&$documentSummary, $dryRun) {
                foreach ($documents as $doc) {
                    if ($doc->category && $doc->category->access_group) {
                        $target = $doc->category->access_group instanceof AccessGroup
                            ? $doc->category->access_group->value
                            : $doc->category->access_group;
                    } else {
                        $target = AccessGroup::fromLegacyVisibility($doc->visibility)?->value ?? AccessGroup::PUBLIK->value;
                    }

                    $documentSummary[$target] = ($documentSummary[$target] ?? 0) + 1;

                    if (! $dryRun) {
                        $doc->updateQuietly(['access_group' => $target]);
                    }
                }
            });

        $totalCategories = array_sum($categorySummary);
        $totalDocuments = array_sum($documentSummary);

        // Tampilkan ringkasan ke konsol
        $this->newLine();
        $this->info('Ringkasan Kategori (Folder):');
        $this->table(
            ['Access Group', 'Jumlah Folder'],
            [
                ['publik', $categorySummary['publik'] ?? 0],
                ['dosen_staf', $categorySummary['dosen_staf'] ?? 0],
                ['akademik', $categorySummary['akademik'] ?? 0],
                ['TOTAL', $totalCategories],
            ]
        );

        $this->newLine();
        $this->info('Ringkasan Dokumen:');
        $this->table(
            ['Access Group', 'Jumlah Dokumen'],
            [
                ['publik', $documentSummary['publik'] ?? 0],
                ['dosen_staf', $documentSummary['dosen_staf'] ?? 0],
                ['akademik', $documentSummary['akademik'] ?? 0],
                ['TOTAL', $totalDocuments],
            ]
        );

        if ($dryRun) {
            $this->warn('[DRY-RUN] Tidak ada data yang diubah di database.');
        } else {
            // Tulis ringkasan ke Activity Log
            if (function_exists('activity')) {
                activity('documents')
                    ->withProperties([
                        'dry_run'            => false,
                        'category_summary'   => $categorySummary,
                        'document_summary'   => $documentSummary,
                        'total_categories'   => $totalCategories,
                        'total_documents'    => $totalDocuments,
                    ])
                    ->log("Backfill access_group selesai: {$totalDocuments} dokumen dan {$totalCategories} kategori berhasil dimigrasikan.");
            }

            $this->info('Perubahan berhasil disimpan dan dicatat ke activity log.');
        }

        return Command::SUCCESS;
    }
}
