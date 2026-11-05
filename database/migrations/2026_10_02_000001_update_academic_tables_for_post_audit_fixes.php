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
        // 1. student_taks: Add plain index first so FK is satisfied, then DROP UNIQUE, add test_date & expiry_date
        Schema::table('student_taks', function (Blueprint $table) {
            $table->index('student_number');
            $table->dropUnique(['student_number']);
            $table->date('test_date')->nullable()->after('total_points');
            $table->date('expiry_date')->nullable()->after('test_date');
        });

        // 2. student_final_projects: tambah thesis_title_id & thesis_title_en
        Schema::table('student_final_projects', function (Blueprint $table) {
            $table->text('thesis_title_id')->nullable()->after('student_number');
            $table->text('thesis_title_en')->nullable()->after('thesis_title_id');
        });

        // 3. student_basila_approvals: tambah status & rejection_reason
        Schema::table('student_basila_approvals', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->after('photo_approval');
            $table->text('rejection_reason')->nullable()->after('status');
        });

        // 4. graduation_registrations: tambah is_free_of_sanction & cumlaude_verified_by_admin
        Schema::table('graduation_registrations', function (Blueprint $table) {
            $table->boolean('is_free_of_sanction')->nullable()->after('status');
            $table->boolean('cumlaude_verified_by_admin')->nullable()->after('is_free_of_sanction');
        });

        // 5. student_graduation_payments: Add plain index first, then DROP UNIQUE, add composite UNIQUE (student_number, payment_type)
        Schema::table('student_graduation_payments', function (Blueprint $table) {
            $table->index('student_number');
            $table->dropUnique(['student_number']);
            $table->enum('payment_type', ['wisuda'])->default('wisuda')->after('student_number');
            $table->unique(['student_number', 'payment_type']);
            $table->decimal('amount', 12, 2)->nullable()->after('status');
            $table->timestamp('paid_at')->nullable()->after('amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_graduation_payments', function (Blueprint $table) {
            $table->dropUnique(['student_number', 'payment_type']);
            $table->dropColumn(['payment_type', 'amount', 'paid_at']);
            $table->unique('student_number');
            $table->dropIndex(['student_number']);
        });

        Schema::table('graduation_registrations', function (Blueprint $table) {
            $table->dropColumn(['is_free_of_sanction', 'cumlaude_verified_by_admin']);
        });

        Schema::table('student_basila_approvals', function (Blueprint $table) {
            $table->dropColumn(['status', 'rejection_reason']);
        });

        Schema::table('student_final_projects', function (Blueprint $table) {
            $table->dropColumn(['thesis_title_id', 'thesis_title_en']);
        });

        Schema::table('student_taks', function (Blueprint $table) {
            $table->unique('student_number');
            $table->dropIndex(['student_number']);
            $table->dropColumn(['test_date', 'expiry_date']);
        });
    }
};
