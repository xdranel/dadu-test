<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_categories', function (Blueprint $table) {
            if (!Schema::hasColumn('document_categories', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('documents', function (Blueprint $table) {
            if (!Schema::hasColumn('documents', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('document_categories', function (Blueprint $table) {
            if (Schema::hasColumn('document_categories', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('documents', function (Blueprint $table) {
            if (Schema::hasColumn('documents', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
