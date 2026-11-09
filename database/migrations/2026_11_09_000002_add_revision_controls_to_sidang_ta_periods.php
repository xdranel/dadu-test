<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sidang_ta_periods', function (Blueprint $table) {
            // Preserve existing revision access; publishing schedules is an explicit opt-in.
            $table->boolean('is_revision_active')->default(true);
            $table->boolean('show_to_students')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('sidang_ta_periods', function (Blueprint $table) {
            $table->dropColumn(['is_revision_active', 'show_to_students']);
        });
    }
};
