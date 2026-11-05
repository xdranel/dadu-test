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
        Schema::table('sidang_ta_registrations', function (Blueprint $table) {
            $table->unsignedTinyInteger('requirements_version')->default(1);
        });
        Schema::table('sidang_ta_registration_drafts', function (Blueprint $table) {
            $table->unsignedTinyInteger('requirements_version')->default(1);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sidang_ta_registration_drafts', function (Blueprint $table) {
            $table->dropColumn('requirements_version');
        });
        Schema::table('sidang_ta_registrations', function (Blueprint $table) {
            $table->dropColumn('requirements_version');
        });
    }
};
