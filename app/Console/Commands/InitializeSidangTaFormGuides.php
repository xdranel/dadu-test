<?php

namespace App\Console\Commands;

use App\Models\SidangTaFormGuide;
use App\Services\SidangTaFormGuideService;
use Database\Seeders\SidangTaFormGuidePermissionSeeder;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class InitializeSidangTaFormGuides extends Command
{
    protected $signature = 'sidang-ta:initialize-form-guides';

    protected $description = 'Tambahkan panduan awal Sidang TA tanpa menimpa perubahan administrator';

    public function handle(SidangTaFormGuideService $service): int
    {
        (new SidangTaFormGuidePermissionSeeder)->run();
        $link = 'https://linktr.ee/laa.upps.sby';
        $defaults = [
            ['slot' => 'ta_data_capture', 'title' => 'Contoh Data TA di iGracias', 'image' => 'ta-data.png'],
            ['slot' => 'online_supervision_capture', 'title' => 'Contoh Bimbingan Online yang Disetujui', 'image' => 'bimbingan-online.png'],
            ['slot' => 'eprt_waiver_letter', 'title' => 'Template Surat Permakluman EPRT', 'url' => $link],
            ['slot' => 'pdm_submission_proof', 'title' => 'Pengajuan Perubahan Data Mahasiswa (PDM)', 'url' => $link],
        ];
        DB::transaction(function () use ($defaults, $service) {
            foreach ($defaults as $default) {
                $key = 'default:'.$default['slot'];
                if (SidangTaFormGuide::withTrashed()->where('seed_key', $key)->exists()) {
                    continue;
                }
                $file = isset($default['image']) ? new UploadedFile(public_path('assets/images/forms/sidang-ta/'.$default['image']), $default['image'], 'image/png', null, true) : null;
                $service->save(new SidangTaFormGuide, [
                    'seed_key' => $key, 'slot' => $default['slot'], 'title' => $default['title'],
                    'description' => null, 'url' => $default['url'] ?? null,
                    'link_label' => isset($default['url']) ? 'Buka template / layanan akademik' : null,
                    'sort_order' => 0, 'is_active' => true,
                ], $file);
            }
        });
        $this->info('Panduan awal Sidang TA tersedia. Perubahan administrator tetap dipertahankan.');

        return self::SUCCESS;
    }
}
