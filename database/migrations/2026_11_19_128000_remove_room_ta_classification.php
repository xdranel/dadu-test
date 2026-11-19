<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('rooms', 'is_classroom')) {
            Schema::table('rooms', function (Blueprint $table) {
                $table->dropIndex(['is_classroom']);
                $table->dropColumn('is_classroom');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('rooms', 'is_classroom')) {
            // Compatibility with the previous application: all master rooms are classes.
            // Never change room.status or touch existing bookings/history.
            Schema::table('rooms', function (Blueprint $table) {
                $table->boolean('is_classroom')->nullable()->default(true)->index();
            });
        }
    }
};
