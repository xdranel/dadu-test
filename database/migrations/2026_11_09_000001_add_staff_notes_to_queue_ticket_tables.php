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
        if (Schema::hasTable('queue_tickets')) {
            Schema::table('queue_tickets', function (Blueprint $table) {
                if (!Schema::hasColumn('queue_tickets', 'staff_notes')) {
                    $table->text('staff_notes')->nullable()->after('notes');
                }
            });
        }

        if (Schema::hasTable('queue_ticket_histories')) {
            Schema::table('queue_ticket_histories', function (Blueprint $table) {
                if (!Schema::hasColumn('queue_ticket_histories', 'staff_notes')) {
                    $table->text('staff_notes')->nullable()->after('notes');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('queue_tickets')) {
            Schema::table('queue_tickets', function (Blueprint $table) {
                if (Schema::hasColumn('queue_tickets', 'staff_notes')) {
                    $table->dropColumn('staff_notes');
                }
            });
        }

        if (Schema::hasTable('queue_ticket_histories')) {
            Schema::table('queue_ticket_histories', function (Blueprint $table) {
                if (Schema::hasColumn('queue_ticket_histories', 'staff_notes')) {
                    $table->dropColumn('staff_notes');
                }
            });
        }
    }
};
