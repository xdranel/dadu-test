<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('queue_states') && !Schema::hasColumn('queue_states', 'call_seq')) {
            Schema::table('queue_states', function (Blueprint $table) {
                $table->unsignedBigInteger('call_seq')->default(0)->after('last_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('queue_states') && Schema::hasColumn('queue_states', 'call_seq')) {
            Schema::table('queue_states', function (Blueprint $table) {
                $table->dropColumn('call_seq');
            });
        }
    }
};
