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
        Schema::create('document_categories', function (Blueprint $table) {
            $table->id();
            $table->string('nama_kategori');
            $table->string('slug')->unique();
            $table->text('deskripsi')->nullable();
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->timestamps();

            $table->index('slug');
            $table->index('urutan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_categories');
    }
};
