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
        Schema::table('queue_ticket_histories', function (Blueprint $table) {
            // Index komposit (queue_date, status) untuk filter rentang tanggal + status
            $table->index(['queue_date', 'status'], 'queue_ticket_histories_date_status_index');
            // Index service_category untuk filter kategori layanan
            $table->index('service_category', 'queue_ticket_histories_service_category_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('queue_ticket_histories', function (Blueprint $table) {
            $table->dropIndex('queue_ticket_histories_date_status_index');
            $table->dropIndex('queue_ticket_histories_service_category_index');
        });
    }
};
