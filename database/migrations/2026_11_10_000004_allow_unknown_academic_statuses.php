<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('student_sidang_tingkats', function (Blueprint $table) {
            foreach (range(1, 4) as $level) $table->boolean('tingkat_'.$level)->nullable()->default(false)->change();
        });
        Schema::table('student_basila_approvals', function (Blueprint $table) {
            $table->boolean('photo_approval')->nullable()->default(false)->change();
            $table->enum('status', ['pending', 'approved', 'rejected'])->nullable()->default('pending')->change();
        });
    }

    public function down(): void
    {
        $unknown = DB::table('student_sidang_tingkats')->where(function ($query) {
            foreach (range(1, 4) as $level) $query->orWhereNull('tingkat_'.$level);
        })->exists() || DB::table('student_basila_approvals')->whereNull('photo_approval')->orWhereNull('status')->exists();
        if ($unknown) throw new RuntimeException('Rollback dihentikan: masih ada status akademik belum tersedia. Tentukan statusnya secara eksplisit sebelum mengembalikan kolom non-nullable.');
        Schema::table('student_sidang_tingkats', function (Blueprint $table) {
            foreach (range(1, 4) as $level) $table->boolean('tingkat_'.$level)->nullable(false)->default(false)->change();
        });
        Schema::table('student_basila_approvals', function (Blueprint $table) {
            $table->boolean('photo_approval')->nullable(false)->default(false)->change();
            $table->enum('status', ['pending', 'approved', 'rejected'])->nullable(false)->default('pending')->change();
        });
    }
};
