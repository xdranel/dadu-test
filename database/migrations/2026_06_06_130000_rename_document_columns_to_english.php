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
        Schema::table('document_categories', function (Blueprint $table) {
            $table->renameColumn('nama_kategori', 'name');
            $table->renameColumn('deskripsi', 'description');
            $table->renameColumn('urutan', 'sort_order');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['kategori_dokumen_id']);
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->renameColumn('kategori_dokumen_id', 'document_category_id');
            $table->renameColumn('judul', 'title');
            $table->renameColumn('singkatan', 'abbreviation');
            $table->renameColumn('deskripsi', 'description');
            $table->renameColumn('nama_file', 'file_name');
            $table->renameColumn('path_file', 'file_path');
            $table->renameColumn('tipe_file', 'file_type');
            $table->renameColumn('ukuran_file', 'file_size');
            $table->renameColumn('visibilitas', 'visibility');
            $table->renameColumn('jumlah_unduhan', 'download_count');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->foreign('document_category_id')
                  ->references('id')
                  ->on('document_categories')
                  ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['document_category_id']);
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->renameColumn('document_category_id', 'kategori_dokumen_id');
            $table->renameColumn('title', 'judul');
            $table->renameColumn('abbreviation', 'singkatan');
            $table->renameColumn('description', 'deskripsi');
            $table->renameColumn('file_name', 'nama_file');
            $table->renameColumn('file_path', 'path_file');
            $table->renameColumn('file_type', 'tipe_file');
            $table->renameColumn('file_size', 'ukuran_file');
            $table->renameColumn('visibility', 'visibilitas');
            $table->renameColumn('download_count', 'jumlah_unduhan');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->foreign('kategori_dokumen_id')
                  ->references('id')
                  ->on('document_categories')
                  ->cascadeOnDelete();
        });

        Schema::table('document_categories', function (Blueprint $table) {
            $table->renameColumn('name', 'nama_kategori');
            $table->renameColumn('description', 'deskripsi');
            $table->renameColumn('sort_order', 'urutan');
        });
    }
};
