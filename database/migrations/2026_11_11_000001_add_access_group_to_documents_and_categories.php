<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('document_categories', function (Blueprint $table) {
            $table->string('access_group')->nullable()->after('visibility');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->string('access_group')->nullable()->after('visibility');
            $table->string('owner_nim')->nullable()->after('access_group');
            $table->text('keywords')->nullable()->after('owner_nim');

            $table->index(['access_group', 'owner_nim'], 'documents_access_group_owner_nim_index');
        });

        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql') {
            Schema::table('documents', function (Blueprint $table) {
                $table->fullText(['title', 'keywords'], 'documents_title_keywords_fulltext');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        Schema::table('documents', function (Blueprint $table) use ($driver) {
            if ($driver === 'mysql') {
                $table->dropFullText('documents_title_keywords_fulltext');
            }

            $table->dropIndex('documents_access_group_owner_nim_index');
            $table->dropColumn(['access_group', 'owner_nim', 'keywords']);
        });

        Schema::table('document_categories', function (Blueprint $table) {
            $table->dropColumn('access_group');
        });
    }
};
