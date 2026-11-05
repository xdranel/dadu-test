<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fix: sks column was NOT NULL. The KRS endpoint does not provide SKS data,
     * so explicitly passing null overrides the default(3) and causes an error.
     * Also fix nama_dosen to be nullable since KRS endpoint doesn't provide it.
     */
    public function up(): void
    {
        Schema::table('student_courses', function (Blueprint $table) {
            $table->tinyInteger('credits')->nullable()->default(null)->change();
            $table->string('lecturer_name')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('student_courses', function (Blueprint $table) {
            $table->tinyInteger('credits')->nullable(false)->default(3)->change();
            $table->string('lecturer_name')->nullable(false)->change();
        });
    }
};
