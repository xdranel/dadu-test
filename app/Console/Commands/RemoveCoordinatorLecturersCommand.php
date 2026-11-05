<?php

namespace App\Console\Commands;

use App\Models\CourseLecturer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RemoveCoordinatorLecturersCommand extends Command
{
    /**
     * Nama dan signature command.
     *
     * Jalankan dengan:
     *   php artisan import:remove-coordinator-lecturers
     *   php artisan import:remove-coordinator-lecturers --dry-run   (preview tanpa hapus)
     *   php artisan import:remove-coordinator-lecturers --force      (tanpa konfirmasi)
     */
    protected $signature = 'import:remove-coordinator-lecturers
                            {--dry-run : Preview record yang akan dihapus tanpa benar-benar menghapus}
                            {--force  : Hapus tanpa meminta konfirmasi}';

    protected $description = 'Hapus semua record di course_lecturers dengan role=coordinator yang lecturer_code-nya tidak ada di tabel lecturers (orphaned coordinators dari import lama)';

    public function handle(): int
    {
        $this->info('');
        $this->info('=== Remove Orphaned Coordinator Lecturers ===');
        $this->info('');

        // ── Cari semua orphan coordinator ────────────────────────────────────
        $orphans = DB::table('course_lecturers as cl')
            ->select(
                'cl.id',
                'cl.course_code',
                'cl.lecturer_code',
                'cl.role',
                DB::raw("COALESCE(c.name, '(course tidak dikenal)') as course_name")
            )
            ->leftJoin('lecturers as l', 'cl.lecturer_code', '=', 'l.lecturer_code')
            ->leftJoin('courses as c', 'cl.course_code', '=', 'c.course_code')
            ->where('cl.role', 'coordinator')
            ->whereNull('l.lecturer_code')
            ->orderBy('cl.lecturer_code')
            ->orderBy('cl.course_code')
            ->get();

        if ($orphans->isEmpty()) {
            $this->info('✅ Tidak ada orphaned coordinator ditemukan. Database sudah bersih.');
            return self::SUCCESS;
        }

        // ── Tampilkan ringkasan ───────────────────────────────────────────────
        $distinctCodes  = $orphans->pluck('lecturer_code')->unique()->count();
        $distinctCourses = $orphans->pluck('course_code')->unique()->count();

        $this->warn("⚠️  Ditemukan {$orphans->count()} record orphaned coordinator:");
        $this->line("   - {$distinctCodes} lecturer_code unik");
        $this->line("   - {$distinctCourses} course unik");
        $this->info('');

        // ── Tampilkan tabel preview ───────────────────────────────────────────
        $this->table(
            ['ID', 'Lecturer Code', 'Course Code', 'Course Name'],
            $orphans->take(30)->map(fn($r) => [
                $r->id,
                $r->lecturer_code,
                $r->course_code,
                substr($r->course_name, 0, 45),
            ])->toArray()
        );

        if ($orphans->count() > 30) {
            $this->line('   ... dan ' . ($orphans->count() - 30) . ' record lainnya (tidak ditampilkan).');
        }

        $this->info('');

        // ── Dry run: hanya preview, tidak hapus ──────────────────────────────
        if ($this->option('dry-run')) {
            $this->info('🔍 [DRY RUN] Tidak ada yang dihapus. Jalankan tanpa --dry-run untuk benar-benar menghapus.');
            return self::SUCCESS;
        }

        // ── Konfirmasi sebelum hapus ──────────────────────────────────────────
        if (!$this->option('force')) {
            if (!$this->confirm("Hapus {$orphans->count()} record ini dari course_lecturers?")) {
                $this->info('Dibatalkan. Tidak ada yang dihapus.');
                return self::SUCCESS;
            }
        }

        // ── Eksekusi penghapusan ──────────────────────────────────────────────
        $ids     = $orphans->pluck('id')->toArray();
        $deleted = DB::table('course_lecturers')->whereIn('id', $ids)->delete();

        $this->info('');
        $this->info("✅ Berhasil menghapus {$deleted} record orphaned coordinator dari course_lecturers.");
        $this->info('   Import mata kuliah berikutnya tidak akan membuat coordinator baru.');
        $this->info('   Dosen pengampu (team_member) tetap aman dan tidak terpengaruh.');

        return self::SUCCESS;
    }
}
