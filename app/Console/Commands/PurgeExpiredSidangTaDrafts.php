<?php

namespace App\Console\Commands;

use App\Services\SidangTaExpiredDraftCleanup;
use Illuminate\Console\Command;

class PurgeExpiredSidangTaDrafts extends Command
{
    protected $signature = 'sidang-ta:purge-expired-drafts {--dry-run : Tampilkan jumlah tanpa menghapus draft atau berkas}';
    protected $description = 'Hapus draft aktif Sidang TA setelah batas akhir periode';

    public function handle(SidangTaExpiredDraftCleanup $cleanup): int
    {
        $summary = $cleanup->run((bool) $this->option('dry-run'));
        $this->info('Draft kedaluwarsa: '.$summary['eligible_drafts'].'. Draft dihapus: '.$summary['deleted_drafts'].'.');
        return self::SUCCESS;
    }
}
