<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_categories', function (Blueprint $table) {
            if (!Schema::hasColumn('document_categories', 'parent_id')) {
                $table->foreignId('parent_id')->nullable()->after('id')
                    ->constrained('document_categories')->nullOnDelete();
            }
            if (!Schema::hasColumn('document_categories', 'depth')) {
                $table->unsignedTinyInteger('depth')->default(0)->after('parent_id');
            }
        });

        // Separate schema call for indexes to avoid issues with column creation order
        Schema::table('document_categories', function (Blueprint $table) {
            $table->index(['parent_id', 'depth'], 'dc_parent_depth_idx');
            $table->unique(['parent_id', 'name'], 'dc_parent_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('document_categories', function (Blueprint $table) {
            // Drop indexes first
            $table->dropUnique('dc_parent_name_unique');
            $table->dropIndex('dc_parent_depth_idx');

            // Drop foreign key and columns
            if (Schema::hasColumn('document_categories', 'parent_id')) {
                $table->dropForeign(['parent_id']);
                $table->dropColumn('parent_id');
            }
            if (Schema::hasColumn('document_categories', 'depth')) {
                $table->dropColumn('depth');
            }
        });
    }
};
