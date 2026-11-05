<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Membuat tabel master academic_periods sebagai sumber kebenaran tunggal
     * untuk semua referensi tahun akademik dan semester di seluruh sistem.
     * Ini menggantikan penggunaan string bebas 'academic_year' dan 'semester'
     * yang tersebar di beberapa tabel.
     */
    public function up(): void
    {
        Schema::create('academic_periods', function (Blueprint $table) {
            $table->id();

            // Contoh: "2024/2025"
            $table->string('year', 10);

            // 1 = Ganjil, 2 = Genap
            $table->tinyInteger('semester');

            // Label tampilan: "Ganjil 2024/2025"
            $table->string('label', 50);

            $table->date('start_date');
            $table->date('end_date');

            // Hanya satu periode yang boleh aktif dalam satu waktu
            $table->boolean('is_active')->default(false);

            $table->timestamps();

            // Mencegah duplikasi periode akademik yang sama
            $table->unique(['year', 'semester']);

            $table->index('is_active');
            $table->index('year');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('academic_periods');
    }
};
