<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sidang_ta_execution_settings', function (Blueprint $table) {
            $table->boolean('is_active')->default(true);
        });
        // Settings affect future searches only; historical occurrences keep their original times.
        DB::table('sidang_ta_execution_settings')->update(['duration_minutes' => 90,
            'break_start_time' => null, 'break_end_time' => null,
            'lock_version' => DB::raw('lock_version + 1')]);
        Schema::table('sidang_ta_schedules', function (Blueprint $table) {
            $table->json('participant_data')->nullable();
            $table->json('details_snapshot')->nullable();
            $table->string('planning_source', 20)->default('system');
            $table->date('preferred_date')->nullable();
            $table->time('preferred_start_time')->nullable();
            $table->time('preferred_end_time')->nullable();
            $table->foreignId('preferred_room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->string('result_status', 20)->default('pending')->index();
            $table->text('result_notes')->nullable();
            $table->foreignId('result_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('result_at')->nullable();
        });
        Schema::create('sidang_ta_execution_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sidang_ta_schedule_id')->constrained('sidang_ta_schedules')->cascadeOnDelete();
            $table->string('action', 40);
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40)->nullable();
            $table->text('notes')->nullable();
            $table->json('data')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('performed_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sidang_ta_execution_logs');
        Schema::table('sidang_ta_schedules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('preferred_room_id');
            $table->dropConstrainedForeignId('result_by');
            $table->dropIndex(['result_status']);
            $table->dropColumn(['participant_data', 'details_snapshot', 'planning_source', 'preferred_date',
                'preferred_start_time', 'preferred_end_time', 'result_status', 'result_notes', 'result_at']);
        });
        Schema::table('sidang_ta_execution_settings', fn (Blueprint $table) => $table->dropColumn('is_active'));
    }
};
