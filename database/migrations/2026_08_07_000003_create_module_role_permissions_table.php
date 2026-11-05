<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Membuat tabel untuk menyimpan konfigurasi permission per module yang bisa diatur oleh super admin.
     */
    public function up(): void
    {
        Schema::create('module_role_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('module_name'); // e.g., 'graduation_registration'
            $table->string('role_name'); // e.g., 'mahasiswa', 'dosen', 'pegawai', 'super_admin'
            $table->boolean('can_create')->default(false);
            $table->boolean('can_read')->default(false);
            $table->boolean('can_update')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->text('additional_rules')->nullable(); // JSON untuk rule tambahan
            $table->timestamps();

            // Composite unique index
            $table->unique(['module_name', 'role_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('module_role_permissions');
    }
};
