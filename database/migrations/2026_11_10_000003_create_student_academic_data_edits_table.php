<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('student_academic_data_edits', function (Blueprint $table) {
            $table->id();
            $table->string('student_number', 15);
            $table->foreign('student_number')->references('student_number')->on('students')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('before_data');
            $table->json('after_data');
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['student_number', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_academic_data_edits');
    }
};
