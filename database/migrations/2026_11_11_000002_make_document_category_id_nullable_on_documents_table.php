<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->unsignedBigInteger('document_category_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // down() hanya membatalkan nullable bila tidak ada baris yang bernilai NULL,
        // demi menjaga integritas data dan mencegah kegagalan rollback saat ada dokumen di root.
        if (! DB::table('documents')->whereNull('document_category_id')->exists()) {
            Schema::table('documents', function (Blueprint $table) {
                $table->unsignedBigInteger('document_category_id')->nullable(false)->change();
            });
        }
    }
};
