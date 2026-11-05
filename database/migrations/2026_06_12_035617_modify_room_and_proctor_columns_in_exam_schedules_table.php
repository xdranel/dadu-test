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
        Schema::table('exam_schedules', function (Blueprint $table) {
            $table->renameColumn('room', 'room_1');
            $table->renameColumn('proctor_name', 'proctor_name_1');
        });

        Schema::table('exam_schedules', function (Blueprint $table) {
            $table->string('room_2', 50)->nullable()->after('room_1');
            $table->string('proctor_name_2')->nullable()->after('proctor_name_1');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exam_schedules', function (Blueprint $table) {
            $table->dropColumn(['room_2', 'proctor_name_2']);
            $table->renameColumn('room_1', 'room');
            $table->renameColumn('proctor_name_1', 'proctor_name');
        });
    }
};
