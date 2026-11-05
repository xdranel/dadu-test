<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('graduation_registration_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('graduation_registration_id');
            $table->foreign('graduation_registration_id', 'grad_reg_logs_reg_id_fk')
                  ->references('id')->on('graduation_registrations')
                  ->cascadeOnDelete();
            $table->string('action');
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('performed_by')->nullable();
            $table->timestamp('performed_at')->nullable();
            $table->timestamps();

            $table->foreign('performed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('graduation_registration_logs');
    }
};
