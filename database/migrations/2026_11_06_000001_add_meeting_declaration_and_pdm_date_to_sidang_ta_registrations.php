<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sidang_ta_registrations', function (Blueprint $table) {
            $table->date('pdm_submission_date')->nullable();
            $table->timestamp('declaration_accepted_at')->nullable();
            $table->unsignedSmallInteger('declaration_version')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sidang_ta_registrations', function (Blueprint $table) {
            $table->dropColumn(['pdm_submission_date', 'declaration_accepted_at', 'declaration_version']);
        });
    }
};
