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
        // Rename permission names from exam_schedule_* to jadwal_*
        $actions = ['create', 'read', 'update', 'delete'];
        foreach ($actions as $action) {
            DB::table('permissions')
                ->where('name', "exam_schedule_{$action}")
                ->update(['name' => "jadwal_{$action}"]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $actions = ['create', 'read', 'update', 'delete'];
        foreach ($actions as $action) {
            DB::table('permissions')
                ->where('name', "jadwal_{$action}")
                ->update(['name' => "exam_schedule_{$action}"]);
        }
    }
};
