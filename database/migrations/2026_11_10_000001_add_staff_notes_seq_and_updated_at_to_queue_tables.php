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
                if (!Schema::hasColumn('queue_tickets', 'staff_notes_seq')) {
                    $table->unsignedBigInteger('staff_notes_seq')->nullable()->default(0)->after('staff_notes');
                }
                if (!Schema::hasColumn('queue_tickets', 'staff_notes_updated_at')) {
                    $table->timestamp('staff_notes_updated_at')->nullable()->after('staff_notes_seq');
                }
            });
        }

        if (Schema::hasTable('queue_ticket_histories')) {
            Schema::table('queue_ticket_histories', function (Blueprint $table) {
                if (!Schema::hasColumn('queue_ticket_histories', 'staff_notes_seq')) {
                    $table->unsignedBigInteger('staff_notes_seq')->nullable()->default(0)->after('staff_notes');
                }
                if (!Schema::hasColumn('queue_ticket_histories', 'staff_notes_updated_at')) {
                    $table->timestamp('staff_notes_updated_at')->nullable()->after('staff_notes_seq');
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
                if (Schema::hasColumn('queue_tickets', 'staff_notes_updated_at')) {
                    $table->dropColumn('staff_notes_updated_at');
                }
                if (Schema::hasColumn('queue_tickets', 'staff_notes_seq')) {
                    $table->dropColumn('staff_notes_seq');
                }
            });
        }

        if (Schema::hasTable('queue_ticket_histories')) {
            Schema::table('queue_ticket_histories', function (Blueprint $table) {
                if (Schema::hasColumn('queue_ticket_histories', 'staff_notes_updated_at')) {
                    $table->dropColumn('staff_notes_updated_at');
                }
                if (Schema::hasColumn('queue_ticket_histories', 'staff_notes_seq')) {
                    $table->dropColumn('staff_notes_seq');
                }
            });
        }
    }
};
