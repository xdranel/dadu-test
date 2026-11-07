<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        // 1. Modifikasi kolom status dan tambah skipped_at, skipped_by di queue_tickets
        if (Schema::hasTable('queue_tickets')) {
            if ($driver === 'mysql') {
                DB::statement("ALTER TABLE queue_tickets MODIFY COLUMN status VARCHAR(30) NOT NULL DEFAULT 'waiting'");
            } else {
                try {
                    Schema::table('queue_tickets', function (Blueprint $table) {
                        $table->string('status', 30)->default('waiting')->change();
                    });
                } catch (\Throwable $e) {
                    // Ignore if in-memory sqlite doesn't require change
                }
            }

            Schema::table('queue_tickets', function (Blueprint $table) {
                if (!Schema::hasColumn('queue_tickets', 'skipped_at')) {
                    $table->timestamp('skipped_at')->nullable()->after('finished_at');
                }
                if (!Schema::hasColumn('queue_tickets', 'skipped_by')) {
                    $table->foreignId('skipped_by')->nullable()->after('skipped_at')->constrained('users')->nullOnDelete();
                }
            });
        }

        // 2. Tambah skipped_at, skipped_by di queue_ticket_histories
        if (Schema::hasTable('queue_ticket_histories')) {
            if ($driver === 'mysql') {
                DB::statement("ALTER TABLE queue_ticket_histories MODIFY COLUMN status VARCHAR(30) NOT NULL");
            }

            Schema::table('queue_ticket_histories', function (Blueprint $table) {
                if (!Schema::hasColumn('queue_ticket_histories', 'skipped_at')) {
                    $table->timestamp('skipped_at')->nullable()->after('finished_at');
                }
                if (!Schema::hasColumn('queue_ticket_histories', 'skipped_by')) {
                    $table->unsignedBigInteger('skipped_by')->nullable()->after('skipped_at');
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
                if (Schema::hasColumn('queue_tickets', 'skipped_by')) {
                    $table->dropForeign(['skipped_by']);
                    $table->dropColumn('skipped_by');
                }
                if (Schema::hasColumn('queue_tickets', 'skipped_at')) {
                    $table->dropColumn('skipped_at');
                }
            });
        }

        if (Schema::hasTable('queue_ticket_histories')) {
            Schema::table('queue_ticket_histories', function (Blueprint $table) {
                if (Schema::hasColumn('queue_ticket_histories', 'skipped_by')) {
                    $table->dropColumn('skipped_by');
                }
                if (Schema::hasColumn('queue_ticket_histories', 'skipped_at')) {
                    $table->dropColumn('skipped_at');
                }
            });
        }
    }
};
