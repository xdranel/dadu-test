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
        Schema::create('sync_settings', function (Blueprint $table) {
            $table->id();
            $table->string('module', 50)->unique();  // mahasiswa, matakuliah, dll
            $table->string('label');                  // label tampil di UI
            $table->string('endpoint_url')->nullable(); // URL lengkap
            $table->boolean('is_active')->default(true);
            $table->string('icon', 10)->nullable();
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_settings');
    }
};
