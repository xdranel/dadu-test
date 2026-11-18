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
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `documents` MODIFY `visibility` ENUM('publik','internal','private') NOT NULL DEFAULT 'publik'");
            DB::statement("ALTER TABLE `document_categories` MODIFY `visibility` ENUM('publik','internal','private') NOT NULL DEFAULT 'publik'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::table('documents')->where('visibility', 'private')->update(['visibility' => 'internal']);
            DB::table('document_categories')->where('visibility', 'private')->update(['visibility' => 'internal']);
            DB::statement("ALTER TABLE `documents` MODIFY `visibility` ENUM('publik','internal') NOT NULL DEFAULT 'publik'");
            DB::statement("ALTER TABLE `document_categories` MODIFY `visibility` ENUM('publik','internal') NOT NULL DEFAULT 'publik'");
        }
    }
};
