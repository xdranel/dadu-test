<?php

namespace App\Console\Commands;

use App\Services\SidangTaRevisionExpiry;
use Illuminate\Console\Command;

class ExpireSidangTaRevisions extends Command
{
    protected $signature = 'sidang-ta:expire-revisions';
    protected $description = 'Tolak otomatis revisi Sidang TA versi 2 yang melewati batas akhir periode';

    public function handle(SidangTaRevisionExpiry $expiry): int
    {
        $count = $expiry->run();
        $this->info('Pengajuan revisi ditolak otomatis: '.$count.'.');
        return self::SUCCESS;
    }
}
