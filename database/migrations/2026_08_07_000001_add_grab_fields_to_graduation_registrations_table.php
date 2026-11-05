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
        Schema::table('graduation_registrations', function (Blueprint $table) {
            // Field baru: EPRT score
            $table->decimal('eprt_score', 8, 2)->nullable()->after('tak_score');
            
            // Field baru: Status approval foto di i-Basila
            $table->enum('ibasila_photo_approval_status', ['pending', 'approved', 'rejected'])
                  ->default('pending')
                  ->after('eprt_score');
            
            // Field baru: List mata kuliah belum lulus (JSON)
            $table->json('failed_courses')->nullable()->after('ibasila_photo_approval_status');
            
            // Field baru: List mata kuliah mengulang (JSON) - untuk cumlaude
            $table->json('retaken_courses')->nullable()->after('failed_courses');
            
            // Field baru: Status sidang tingkat 3
            $table->enum('level_3_exam_status', ['not_passed', 'passed'])
                  ->nullable()
                  ->after('retaken_courses');
            
            // Field untuk menyimpan timestamp terakhir sync data dari sistem kampus
            $table->timestamp('last_synced_at')->nullable()->after('level_3_exam_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('graduation_registrations', function (Blueprint $table) {
            $table->dropColumn([
                'eprt_score',
                'ibasila_photo_approval_status',
                'failed_courses',
                'retaken_courses',
                'level_3_exam_status',
                'last_synced_at',
            ]);
        });
    }
};
