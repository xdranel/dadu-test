<?php

namespace App\Console\Commands;

use App\Models\DocumentImportBatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneDocumentImportBatchesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'documents:prune-import-batches {--days=7 : Jumlah hari kedaluwarsa}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Membersihkan batch import staging dan berkas sementaranya yang telah kedaluwarsa';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        $batches = DocumentImportBatch::whereIn('status', ['staging', 'cancelled', 'failed'])
            ->where('created_at', '<', $cutoff)
            ->get();

        $count = 0;
        foreach ($batches as $batch) {
            $dir = "imports/{$batch->id}";
            if (Storage::disk('private')->exists($dir)) {
                Storage::disk('private')->deleteDirectory($dir);
            }

            $batch->delete();
            $count++;
        }

        $this->info("Berhasil membersihkan {$count} batch import yang telah kedaluwarsa.");

        return 0;
    }
}
