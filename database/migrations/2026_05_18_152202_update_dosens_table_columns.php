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
        Schema::table('lecturers', function (Blueprint $table) {
            // Drop old indexes first if they exist to prevent SQLite errors on dropping columns
            if (Schema::hasIndex('lecturers', 'lecturers_nip_unique')) {
                $table->dropUnique(['nip']);
            }
            if (Schema::hasIndex('lecturers', 'lecturers_nip_index')) {
                $table->dropIndex(['nip']);
            }
            if (Schema::hasIndex('lecturers', 'lecturers_homebase_index')) {
                $table->dropIndex(['homebase']);
            }
            if (Schema::hasIndex('lecturers', 'lecturers_employment_status_index')) {
                $table->dropIndex(['employment_status']);
            }

            // Drop old columns that are no longer needed or renamed
            $table->dropColumn(['nip', 'name', 'homebase', 'phone_number', 'employment_status']);
            
            // Add new columns
            $table->string('name')->nullable()->after('id');
            $table->string('active_status')->nullable()->after('name');
            $table->string('study_program')->nullable()->after('active_status');
            $table->string('nip_ypt')->nullable()->after('study_program');
            $table->string('nidn')->nullable()->after('nip_ypt');
            $table->string('nuptk')->nullable()->after('nidn');
            $table->string('employment_status')->nullable()->after('nuptk');
            $table->string('front_title')->nullable()->after('employment_status');
            $table->string('back_title')->nullable()->after('front_title');
            // 'jfa' already exists
            $table->string('academic_interest')->nullable()->after('jfa');
            // 'lecturer_code' already exists
            $table->string('email_domain')->nullable()->after('lecturer_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lecturers', function (Blueprint $table) {
            $table->dropColumn([
                'name', 'active_status', 'study_program', 'nip_ypt', 'nidn', 
                'nuptk', 'employment_status', 'front_title', 'back_title', 
                'academic_interest', 'email_domain'
            ]);

            $table->string('nip')->unique()->nullable()->index();
            $table->string('name');
            $table->string('homebase')->index();
            $table->string('phone_number')->nullable();
            $table->enum('employment_status', ['Tetap', 'LB'])->index();
        });
    }
};
