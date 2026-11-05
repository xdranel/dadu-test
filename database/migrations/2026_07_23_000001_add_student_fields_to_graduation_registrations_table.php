<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('graduation_registrations', function (Blueprint $table) {
            $table->string('student_email')->nullable()->after('personal_email');
            $table->string('academic_advisor_name')->nullable()->after('student_email');
            $table->string('academic_advisor_code', 50)->nullable()->after('academic_advisor_name');
            $table->boolean('has_title_change')->default(false)->after('thesis_title_en');
            $table->string('sk_update_status', 50)->nullable()->after('has_title_change');
            $table->date('sk_update_submission_date')->nullable()->after('sk_update_status');
        });
    }

    public function down(): void
    {
        Schema::table('graduation_registrations', function (Blueprint $table) {
            $table->dropColumn([
                'student_email',
                'academic_advisor_name',
                'academic_advisor_code',
                'has_title_change',
                'sk_update_status',
                'sk_update_submission_date',
            ]);
        });
    }
};
