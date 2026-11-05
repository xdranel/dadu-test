<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            if (!Schema::hasColumn('documents', 'original_file_name')) {
                $table->string('original_file_name')->nullable()->after('file_name');
            }
        });

        // Backfill: gunakan file_name (nama file asli) sebagai original_file_name untuk data lama.
        DB::table('documents')
            ->whereNull('original_file_name')
            ->update(['original_file_name' => DB::raw('file_name')]);
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            if (Schema::hasColumn('documents', 'original_file_name')) {
                $table->dropColumn('original_file_name');
            }
        });
    }
};
