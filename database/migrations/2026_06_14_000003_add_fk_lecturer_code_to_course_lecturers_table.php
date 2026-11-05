<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Menambahkan FK constraint dari course_lecturers.lecturer_code ke
     * lecturers.lecturer_code. Migration ini mengembalikan integritas referensial
     * yang sebelumnya dihapus pada 2026_06_12_091000 karena alasan dosen eksternal.
     *
     * Sekarang masalah dosen eksternal diselesaikan dengan kolom is_external
     * di tabel lecturers (lihat migration 000002), sehingga FK bisa diterapkan
     * kembali dengan aman.
     *
     * STRATEGI PENANGANAN ORPHAN:
     * Daripada menghapus record orphan di course_lecturers, migration ini akan
     * MENGIMPORT dosen-dosen yang belum ada ke tabel lecturers sebagai rekord
     * baru dengan data minimal (is_external=false). Data lengkap bisa diisi
     * nanti via fitur sync atau edit manual di aplikasi.
     */
    public function up(): void
    {
        // ── 1. Temukan semua lecturer_code orphan ────────────────────────────
        $orphanCodes = DB::table('course_lecturers as cl')
            ->leftJoin('lecturers as l', 'cl.lecturer_code', '=', 'l.lecturer_code')
            ->whereNull('l.lecturer_code')
            ->distinct()
            ->pluck('cl.lecturer_code');

        // ── 2. Import sebagai rekord dosen baru jika ada orphan ──────────────
        if ($orphanCodes->isNotEmpty()) {
            Log::info(
                'migration_000003: Mengimport ' . $orphanCodes->count() .
                ' lecturer_code baru ke tabel lecturers (data minimal, is_external=false).',
                ['codes' => $orphanCodes->toArray()]
            );

            $now  = now();
            $rows = $orphanCodes->map(fn($code) => [
                'lecturer_code' => $code,
                // Isi name dengan kode sementara — admin bisa update nanti
                'name'          => null,
                'is_external'   => false,
                'active_status' => null,
                'created_at'    => $now,
                'updated_at'    => $now,
            ])->toArray();

            // Insert dalam batch untuk efisiensi
            foreach (array_chunk($rows, 100) as $batch) {
                DB::table('lecturers')->insertOrIgnore($batch);
            }

            Log::info(
                'migration_000003: Import selesai. ' .
                $orphanCodes->count() . ' dosen baru berhasil ditambahkan.'
            );
        }

        // ── 3. Pasang FK setelah semua orphan sudah ditangani ────────────────
        Schema::table('course_lecturers', function (Blueprint $table) {
            $table->foreign('lecturer_code')
                ->references('lecturer_code')
                ->on('lecturers')
                ->restrictOnDelete()   // Cegah hapus dosen jika masih mengajar
                ->cascadeOnUpdate();   // Jika kode dosen berubah, update otomatis
        });
    }

    /**
     * Reverse the migrations.
     *
     * Catatan: Rollback hanya menghapus FK-nya saja.
     * Rekord dosen yang diimport di up() TIDAK dihapus karena mungkin
     * sudah ada data yang berelasi atau sudah diupdate oleh admin.
     */
    public function down(): void
    {
        Schema::table('course_lecturers', function (Blueprint $table) {
            $table->dropForeign(['lecturer_code']);
        });
    }
};
