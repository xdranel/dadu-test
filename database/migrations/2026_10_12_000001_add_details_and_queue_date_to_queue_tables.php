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
        // 1. Tambah kolom di tabel queue_tickets
        Schema::table('queue_tickets', function (Blueprint $table) {
            $table->string('nim')->nullable()->index()->after('code');
            $table->string('name')->nullable()->after('nim');
            $table->string('study_program')->nullable()->after('name');
            $table->string('service_category')->nullable()->after('study_program');
            $table->string('notes', 255)->nullable()->after('service_category');
            $table->date('queue_date')->nullable()->index()->after('notes');
        });

        // Isi queue_date untuk data lama jika ada
        DB::table('queue_tickets')
            ->whereNull('queue_date')
            ->update([
                'queue_date' => DB::raw('DATE(created_at)')
            ]);

        // Tambah unique index (queue_date, code) untuk mencegah kode tiket kembar
        Schema::table('queue_tickets', function (Blueprint $table) {
            $table->unique(['queue_date', 'code'], 'queue_tickets_date_code_unique');
        });

        // 2. Tambah kolom di tabel queue_ticket_histories
        Schema::table('queue_ticket_histories', function (Blueprint $table) {
            $table->string('nim')->nullable()->index()->after('code');
            $table->string('name')->nullable()->after('nim');
            $table->string('study_program')->nullable()->after('name');
            $table->string('service_category')->nullable()->after('study_program');
            $table->string('notes', 255)->nullable()->after('service_category');
            $table->date('queue_date')->nullable()->index()->after('notes');
        });

        // Ubah status di queue_ticket_histories agar bisa menampung 'unserved'
        // Untuk kompatibilitas aman di MySQL dan SQLite:
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE queue_ticket_histories MODIFY COLUMN status VARCHAR(30) NOT NULL");
        } else {
            // SQLite atau lainnya
            try {
                Schema::table('queue_ticket_histories', function (Blueprint $table) {
                    $table->string('status', 30)->change();
                });
            } catch (\Throwable $e) {
                // Ignore if in-memory sqlite doesn't require modify
            }
        }

        // Isi queue_date untuk history lama jika ada
        DB::table('queue_ticket_histories')
            ->whereNull('queue_date')
            ->update([
                'queue_date' => DB::raw('DATE(created_at)')
            ]);

        // 3. Buat tabel queue_counters untuk penguncian counter harian bebas race condition
        if (!Schema::hasTable('queue_counters')) {
            Schema::create('queue_counters', function (Blueprint $table) {
                $table->id();
                $table->date('queue_date')->unique();
                $table->unsignedInteger('last_number')->default(0);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('queue_counters');

        Schema::table('queue_tickets', function (Blueprint $table) {
            $table->dropUnique('queue_tickets_date_code_unique');
            $table->dropColumn(['nim', 'name', 'study_program', 'service_category', 'notes', 'queue_date']);
        });

        Schema::table('queue_ticket_histories', function (Blueprint $table) {
            $table->dropColumn(['nim', 'name', 'study_program', 'service_category', 'notes', 'queue_date']);
        });
    }
};
