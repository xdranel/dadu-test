<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Menambahkan kolom auth_source ke tabel users untuk membedakan
     * akun SSO dan akun manual (Akademik, Staf SSC, Direktorat, Kaprodi).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('auth_source', ['sso', 'manual'])->nullable()->after('sso_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('auth_source');
        });
    }
};
