<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('students')->update(['email' => null]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reverse migration needed
    }
};
