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
        DB::table('students')->update(['academic_advisor' => null]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reverse migration needed since the data is removed
    }
};
