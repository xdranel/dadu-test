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
        Schema::table('students', function (Blueprint $table) {
            $table->string('parent_phone_number', 20)->nullable()->after('phone_number');
            $table->string('high_school', 150)->nullable()->after('parent_phone_number');
            $table->string('passed_tpb', 30)->nullable()->after('high_school');
            $table->string('passed_tkii', 30)->nullable()->after('passed_tpb');
            $table->string('passed_tkiii', 30)->nullable()->after('passed_tkii');
            $table->string('passed_tkiv', 30)->nullable()->after('passed_tkiii');
            $table->integer('total_credits')->nullable()->after('passed_tkiv');
            $table->decimal('gpa', 4, 2)->nullable()->after('total_credits');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn([
                'parent_phone_number',
                'high_school',
                'passed_tpb',
                'passed_tkii',
                'passed_tkiii',
                'passed_tkiv',
                'total_credits',
                'gpa',
            ]);
        });
    }
};
