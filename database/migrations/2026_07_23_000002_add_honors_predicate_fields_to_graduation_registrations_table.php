<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('graduation_registrations', function (Blueprint $table) {
            $table->string('honors_predicate')->nullable()->after('biodata_correction_note');
            $table->string('predicate_criteria')->nullable()->after('honors_predicate');
            $table->string('predicate_umkm_partner')->nullable()->after('predicate_criteria');
            $table->string('predicate_competition_level')->nullable()->after('predicate_umkm_partner');
            $table->string('predicate_journal_index')->nullable()->after('predicate_competition_level');
            $table->string('predicate_document_path')->nullable()->after('predicate_journal_index');
            $table->string('predicate_document_name')->nullable()->after('predicate_document_path');
        });
    }

    public function down(): void
    {
        Schema::table('graduation_registrations', function (Blueprint $table) {
            $table->dropColumn([
                'honors_predicate',
                'predicate_criteria',
                'predicate_umkm_partner',
                'predicate_competition_level',
                'predicate_journal_index',
                'predicate_document_path',
                'predicate_document_name',
            ]);
        });
    }
};
