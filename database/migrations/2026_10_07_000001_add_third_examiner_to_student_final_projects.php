<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_final_projects', function (Blueprint $table) {
            $table->string('examiner_3')->nullable()->after('examiner_2');
        });
    }

    public function down(): void
    {
        Schema::table('student_final_projects', function (Blueprint $table) {
            $table->dropColumn('examiner_3');
        });
    }
};
