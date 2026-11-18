<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lecturers', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->after('email_domain');
            $table->string('coe', 150)->nullable()->after('phone');
            $table->string('kk', 150)->nullable()->after('coe');
        });
    }

    public function down(): void
    {
        Schema::table('lecturers', function (Blueprint $table) {
            $table->dropColumn(['phone', 'coe', 'kk']);
        });
    }
};

