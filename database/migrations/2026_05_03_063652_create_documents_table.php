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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kategori_dokumen_id')
                ->constrained('document_categories') // ← relasi ke document_categories
                ->cascadeOnDelete();
            $table->string('judul');
            $table->text('deskripsi')->nullable();
            $table->string('nama_file');
            $table->string('path_file');
            $table->string('tipe_file', 20);
            $table->unsignedBigInteger('ukuran_file')->default(0);
            $table->enum('visibilitas', ['Privat', 'Dosen', 'Mahasiswa', 'Publik'])->default('Privat');
            $table->unsignedInteger('jumlah_unduhan')->default(0);
            $table->timestamps();

            $table->index('visibilitas');
            $table->index('kategori_dokumen_id');
            $table->index('judul');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
