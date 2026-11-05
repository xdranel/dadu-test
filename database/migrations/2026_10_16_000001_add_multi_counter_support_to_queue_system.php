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
        // 1. Tangani tabel queue_counters lama (penomoran harian) -> pisahkan ke queue_number_counters
        if (Schema::hasTable('queue_counters') && Schema::hasColumn('queue_counters', 'queue_date')) {
            if (!Schema::hasTable('queue_number_counters')) {
                Schema::rename('queue_counters', 'queue_number_counters');
            } else {
                Schema::dropIfExists('queue_counters');
            }
        } elseif (!Schema::hasTable('queue_number_counters')) {
            Schema::create('queue_number_counters', function (Blueprint $table) {
                $table->id();
                $table->date('queue_date')->unique();
                $table->unsignedInteger('last_number')->default(0);
                $table->timestamps();
            });
        }

        // 2. Buat tabel queue_counters (master loket layanan)
        if (!Schema::hasTable('queue_counters')) {
            Schema::create('queue_counters', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->foreignId('active_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->unique('active_user_id');
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamps();
            });
        }

        // Seed default 2 loket jika belum ada
        if (DB::table('queue_counters')->count() === 0) {
            DB::table('queue_counters')->insert([
                ['name' => 'Loket 1', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Loket 2', 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        // 3. Tambah counter_id di queue_states (satu state per loket)
        if (Schema::hasTable('queue_states') && !Schema::hasColumn('queue_states', 'counter_id')) {
            Schema::table('queue_states', function (Blueprint $table) {
                $table->foreignId('counter_id')->nullable()->after('id')->constrained('queue_counters')->cascadeOnDelete();
            });
        }

        // 4. Tambah counter_id dan called_by di queue_tickets
        if (Schema::hasTable('queue_tickets')) {
            Schema::table('queue_tickets', function (Blueprint $table) {
                if (!Schema::hasColumn('queue_tickets', 'counter_id')) {
                    $table->foreignId('counter_id')->nullable()->after('device_token')->constrained('queue_counters')->nullOnDelete();
                    $table->index('counter_id');
                }
                if (!Schema::hasColumn('queue_tickets', 'called_by')) {
                    $table->foreignId('called_by')->nullable()->after('counter_id')->constrained('users')->nullOnDelete();
                }
            });
        }

        // 5. Tambah counter_id dan called_by di queue_ticket_histories
        if (Schema::hasTable('queue_ticket_histories')) {
            Schema::table('queue_ticket_histories', function (Blueprint $table) {
                if (!Schema::hasColumn('queue_ticket_histories', 'counter_id')) {
                    $table->unsignedBigInteger('counter_id')->nullable()->after('device_token');
                    $table->index('counter_id');
                }
                if (!Schema::hasColumn('queue_ticket_histories', 'called_by')) {
                    $table->unsignedBigInteger('called_by')->nullable()->after('counter_id');
                }
            });
        }

        // Migrasikan state singleton yang ada ke Loket 1
        $firstCounterId = DB::table('queue_counters')->orderBy('id')->value('id');
        if ($firstCounterId) {
            DB::table('queue_states')->whereNull('counter_id')->update([
                'counter_id' => $firstCounterId,
                'updated_at' => now(),
            ]);
        }

        // Pastikan setiap loket memiliki baris di queue_states
        $existingCounterIds = DB::table('queue_states')->whereNotNull('counter_id')->pluck('counter_id')->map(fn ($id) => (int) $id)->all();
        $allCounters = DB::table('queue_counters')->pluck('id');
        foreach ($allCounters as $cid) {
            if (!in_array((int)$cid, $existingCounterIds, true)) {
                DB::table('queue_states')->insert([
                    'counter_id' => $cid,
                    'current_id' => null,
                    'last_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Jadikan counter_id unik di queue_states jika belum
        try {
            Schema::table('queue_states', function (Blueprint $table) {
                $table->unique('counter_id');
            });
        } catch (\Throwable $e) {
            // Index mungkin sudah ada
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('queue_states') && Schema::hasColumn('queue_states', 'counter_id')) {
            try {
                Schema::table('queue_states', function (Blueprint $table) {
                    $table->dropUnique(['counter_id']);
                });
            } catch (\Throwable $e) {}

            try {
                Schema::table('queue_states', function (Blueprint $table) {
                    $table->dropConstrainedForeignId('counter_id');
                });
            } catch (\Throwable $e) {}
        }

        if (Schema::hasTable('queue_tickets')) {
            Schema::table('queue_tickets', function (Blueprint $table) {
                if (Schema::hasColumn('queue_tickets', 'counter_id')) {
                    try {
                        $table->dropIndex(['counter_id']);
                        $table->dropConstrainedForeignId('counter_id');
                    } catch (\Throwable $e) {}
                }
                if (Schema::hasColumn('queue_tickets', 'called_by')) {
                    try {
                        $table->dropConstrainedForeignId('called_by');
                    } catch (\Throwable $e) {}
                }
            });
        }

        if (Schema::hasTable('queue_ticket_histories')) {
            Schema::table('queue_ticket_histories', function (Blueprint $table) {
                if (Schema::hasColumn('queue_ticket_histories', 'counter_id')) {
                    try {
                        $table->dropIndex(['counter_id']);
                        $table->dropColumn('counter_id');
                    } catch (\Throwable $e) {}
                }
                if (Schema::hasColumn('queue_ticket_histories', 'called_by')) {
                    try {
                        $table->dropColumn('called_by');
                    } catch (\Throwable $e) {}
                }
            });
        }

        Schema::dropIfExists('queue_counters');
    }
};
