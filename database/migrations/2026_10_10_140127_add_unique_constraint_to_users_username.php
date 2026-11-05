<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * SECURITY IMPROVEMENT:
     * 1. Ensure auth_source is NOT NULL (set default for existing NULL values)
     * 2. Password already nullable from initial migration (good for SSO accounts)
     */
    public function up(): void
    {
        // Step 1: Update existing NULL auth_source to 'manual' (safe default for existing users)
        DB::statement("UPDATE users SET auth_source = 'manual' WHERE auth_source IS NULL");

        // Step 2: Make auth_source NOT NULL with default
        Schema::table('users', function (Blueprint $table) {
            $table->enum('auth_source', ['sso', 'manual'])
                ->default('manual')
                ->nullable(false)
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('auth_source', ['sso', 'manual'])
                ->nullable()
                ->change();
        });
    }
};
