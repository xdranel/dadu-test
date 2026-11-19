<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const TABLES = [
        'sidang_ta_periods', 'sidang_ta_registrations', 'sidang_ta_registration_documents',
        'sidang_ta_registration_draft_documents', 'sidang_ta_form_guides',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            if (! Schema::hasColumn($name, 'public_id')) {
                Schema::table($name, function (Blueprint $table) {
                    $table->uuid('public_id')->nullable()->unique();
                });
            }
            // Query builder includes old/soft-deleted rows without changing timestamps or files.
            DB::table($name)->whereNull('public_id')->select('id')->orderBy('id')
                ->chunkById(200, function ($rows) use ($name) {
                    foreach ($rows as $row) {
                        DB::table($name)->where('id', $row->id)->whereNull('public_id')
                            ->update(['public_id' => (string) Str::uuid()]);
                    }
                });
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::TABLES) as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropUnique(['public_id']);
                $table->dropColumn('public_id');
            });
        }
    }
};
