<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sidang_ta_periods', function (Blueprint $table) {
            $table->dateTime('revision_open_at')->nullable();
            $table->dateTime('revision_close_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sidang_ta_periods', function (Blueprint $table) {
            $table->dropColumn(['revision_open_at', 'revision_close_at']);
        });
    }
};
