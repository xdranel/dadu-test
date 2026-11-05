<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fix: email column was NOT NULL + UNIQUE.
     * Many students from iGracias have no email, causing duplicate '' entries.
     * Solution: make email nullable, drop unique constraint, clean existing data.
     */
    public function up(): void
    {
        // Step 1: Drop the unique index and make column nullable FIRST
        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique('students_email_unique');
            $table->string('email')->nullable()->change();
        });

        // Step 2: Now clean up existing empty-string emails → set them to NULL
        DB::table('students')
            ->where('email', '')
            ->update(['email' => null]);
    }

    public function down(): void
    {
        // Clean nulls back to empty string before re-adding constraint
        DB::table('students')
            ->whereNull('email')
            ->update(['email' => '']);

        Schema::table('students', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
            $table->unique('email');
        });
    }
};
