<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Hanya berjalan pada driver non-MySQL (misal: SQLite in-memory saat testing).
     * No-op penuh pada database MySQL (production).
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql') {
            return;
        }

        Schema::table('documents', function (Blueprint $table) {
            $table->string('visibility')->default('publik')->change();
        });

        Schema::table('document_categories', function (Blueprint $table) {
            $table->string('visibility')->default('publik')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql') {
            return;
        }

        // Pada driver non-MySQL (SQLite), membiarkan kolom bertipe string agar tidak
        // memicu kegagalan rollback bila terdapat data dengan visibilitas 'private'.
    }
};
