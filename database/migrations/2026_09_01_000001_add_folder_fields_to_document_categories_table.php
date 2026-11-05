<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_categories', function (Blueprint $table) {
            if (!Schema::hasColumn('document_categories', 'is_pinned')) {
                $table->boolean('is_pinned')->default(false)->after('sort_order');
            }
            if (!Schema::hasColumn('document_categories', 'manual_order')) {
                $table->unsignedInteger('manual_order')->nullable()->after('is_pinned');
            }
            if (!Schema::hasColumn('document_categories', 'show_when_empty')) {
                $table->boolean('show_when_empty')->default(false)->after('manual_order');
            }
        });

        // Backfill: kategori lama dengan sort_order = 1 dianggap tersemat (pinned).
        DB::table('document_categories')->where('sort_order', 1)->update(['is_pinned' => true]);
    }

    public function down(): void
    {
        Schema::table('document_categories', function (Blueprint $table) {
            foreach (['is_pinned', 'manual_order', 'show_when_empty'] as $col) {
                if (Schema::hasColumn('document_categories', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
