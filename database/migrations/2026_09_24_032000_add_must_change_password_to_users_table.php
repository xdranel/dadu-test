<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Menambahkan kolom must_change_password ke tabel users untuk memaksa
     * user mengganti password saat login pertama kali setelah akun dibuat
     * atau password direset oleh admin.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false)->after('password');
        });

        // NOTE: Default FALSE untuk semua user existing agar tidak memaksa
        // ganti password secara tiba-tiba saat fitur ini di-deploy.
        // Hanya akun BARU yang dibuat manual oleh Superadmin yang akan
        // diset TRUE otomatis.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('must_change_password');
        });
    }
};
