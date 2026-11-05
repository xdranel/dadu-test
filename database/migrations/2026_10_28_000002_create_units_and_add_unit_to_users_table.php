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
        if (!Schema::hasTable('units')) {
            Schema::create('units', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code')->nullable();
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'unit_id')) {
                $table->foreignId('unit_id')
                    ->nullable()
                    ->after('employee_id')
                    ->constrained('units')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('users', 'position')) {
                $table->string('position')
                    ->nullable()
                    ->after('unit_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'unit_id')) {
                $table->dropForeign(['unit_id']);
                $table->dropColumn('unit_id');
            }

            if (Schema::hasColumn('users', 'position')) {
                $table->dropColumn('position');
            }
        });

        Schema::dropIfExists('units');
    }
};
