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
            $table->string('gender', 10)->nullable()->after('name');
            $table->string('religion', 30)->nullable()->after('gender');
            $table->string('profile_photo')->nullable()->after('religion');
            $table->string('faculty', 100)->nullable()->after('profile_photo');
            $table->tinyInteger('active_semester')->default(1)->after('academic_year');
            $table->string('class_name', 20)->nullable()->after('study_program');
            $table->string('academic_advisor')->nullable()->after('class_name');
            $table->string('phone_number', 20)->nullable()->after('email');

            $table->index('study_program');
            $table->index('academic_year');
            $table->index('active_semester');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn([
                'gender', 'religion', 'profile_photo', 'faculty',
                'active_semester', 'class_name', 'academic_advisor', 'phone_number'
            ]);
        });
    }
};
