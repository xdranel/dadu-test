<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Migrasi sekali-jalan: rapikan `title` dokumen lama yang masih berupa
     * nama file mentah (mengandung dash/underscore, tanpa spasi) menjadi
     * teks yang mudah dibaca. Hanya menyentuh baris yang terdeteksi mentah.
     */
    public function up(): void
    {
        DB::table('documents')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                $title = (string) $row->title;

                $looksRaw = $title !== ''
                    && !str_contains($title, ' ')
                    && (str_contains($title, '-') || str_contains($title, '_'));

                if (!$looksRaw) {
                    continue;
                }

                $humanized = Str::of($title)
                    ->replace(['-', '_'], ' ')
                    ->squish()
                    ->title()
                    ->value();

                DB::table('documents')->where('id', $row->id)->update(['title' => $humanized]);
            }
        });
    }

    public function down(): void
    {
        // Tidak reversibel: judul asli tidak disimpan.
    }
};
