<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupDummyLecturersCommand extends Command
{
    /**
     * Jalankan dengan:
     *   php artisan import:cleanup-dummy-lecturers --dry-run
     *   php artisan import:cleanup-dummy-lecturers --force
     */
    protected $signature = 'import:cleanup-dummy-lecturers
                            {--dry-run : Preview tanpa menghapus apapun}
                            {--force  : Hapus tanpa konfirmasi interaktif}';

    protected $description = 'Hapus dummy lecturers (name=null) yang hanya berasal dari koordinator, '
                           . 'dan hapus semua coordinator records dari course_lecturers';

    public function handle(): int
    {
        $this->info('');
        $this->info('=== Cleanup Dummy Coordinators dari Lecturers & Course Lecturers ===');
        $this->info('');

        // ── Kelompok 1: Dummy lecturer yang AMAN dihapus ─────────────────────
        // Kode ini hanya muncul sebagai koordinator, TIDAK sebagai team_member.
        // Tidak ada data bermakna yang akan hilang.
        $deleteFromLecturers = DB::table('lecturers')
            ->whereNull('name')
            ->whereNotIn('lecturer_code', function ($q) {
                $q->select('lecturer_code')
                  ->from('course_lecturers')
                  ->where('role', 'team_member');
            })
            ->pluck('lecturer_code');

        // ── Kelompok 2: Dummy lecturer yang HARUS dipertahankan ──────────────
        // Kode ini juga muncul sebagai team_member — rekord lecturer-nya valid,
        // hanya namanya yang belum diisi (perlu sync dari SIAKAD).
        $keepInLecturers = DB::table('lecturers')
            ->whereNull('name')
            ->whereIn('lecturer_code', function ($q) {
                $q->select('lecturer_code')
                  ->from('course_lecturers')
                  ->where('role', 'team_member');
            })
            ->pluck('lecturer_code');

        // ── Kelompok 3: Semua coordinator records di course_lecturers ─────────
        // Coordinator tidak pernah punya data di lecturers kampus dan tidak
        // ditampilkan di UI. Semua coordinator records dihapus.
        $coordinatorCount = DB::table('course_lecturers')
            ->where('role', 'coordinator')
            ->count();

        // ── Tampilkan ringkasan ───────────────────────────────────────────────
        $this->line('📊 <fg=white>RINGKASAN:</>');
        $this->line('');

        $this->line("   <fg=red>Lecturer dummy yang akan DIHAPUS</> (hanya koordinator, tidak ada team_member):");
        $this->line("   → {$deleteFromLecturers->count()} rekord dari tabel <fg=yellow>lecturers</>");
        $this->line('');

        $this->line("   <fg=green>Lecturer dummy yang DIPERTAHANKAN</> (juga jadi team_member, nama perlu diisi):");
        $this->line("   → {$keepInLecturers->count()} rekord di tabel <fg=yellow>lecturers</> (tidak disentuh)");
        $this->line('');

        $this->line("   <fg=red>Coordinator records yang akan DIHAPUS</> dari course_lecturers:");
        $this->line("   → {$coordinatorCount} rekord dari tabel <fg=yellow>course_lecturers</>");
        $this->line('');

        if ($deleteFromLecturers->isEmpty() && $coordinatorCount === 0) {
            $this->info('✅ Tidak ada yang perlu dihapus. Database sudah bersih.');
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->line('   Codes yang akan dihapus dari lecturers (sample 20 pertama):');
            foreach ($deleteFromLecturers->take(20) as $code) {
                $this->line("   → {$code}");
            }
            if ($deleteFromLecturers->count() > 20) {
                $this->line('   ... dan ' . ($deleteFromLecturers->count() - 20) . ' lainnya');
            }
            $this->info('');
            $this->info('🔍 [DRY RUN] Tidak ada yang dihapus. Jalankan tanpa --dry-run untuk eksekusi.');
            return self::SUCCESS;
        }

        if (!$this->option('force')) {
            $this->warn('⚠️  Tindakan ini TIDAK BISA dibatalkan!');
            if (!$this->confirm('Lanjutkan cleanup?')) {
                $this->info('Dibatalkan.');
                return self::SUCCESS;
            }
        }

        // ── Eksekusi ─────────────────────────────────────────────────────────
        DB::transaction(function () use ($deleteFromLecturers) {
            // 1. Hapus semua coordinator dari course_lecturers terlebih dahulu
            //    (agar FK constraint tidak menghambat hapus di lecturers)
            $deletedCoords = DB::table('course_lecturers')
                ->where('role', 'coordinator')
                ->delete();

            $this->line("   ✓ Dihapus dari course_lecturers (coordinator): {$deletedCoords} rekord");

            // 2. Hapus dummy lecturer yang tidak dipakai sebagai team_member
            if ($deleteFromLecturers->isNotEmpty()) {
                $deletedLecturers = DB::table('lecturers')
                    ->whereIn('lecturer_code', $deleteFromLecturers->toArray())
                    ->delete();

                $this->line("   ✓ Dihapus dari lecturers (dummy coordinator): {$deletedLecturers} rekord");
            }
        });

        $this->info('');
        $this->info('✅ Cleanup selesai!');
        $this->info('');
        $this->line('   Langkah selanjutnya:');
        $this->line('   → Sync data dosen dari SIAKAD untuk mengisi nama pada ' . $keepInLecturers->count() . ' dosen yang masih null');
        $this->line('   → Import JSON mata kuliah baru tidak akan membuat coordinator lagi');

        return self::SUCCESS;
    }
}
