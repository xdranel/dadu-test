<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->setDefault(2);
    }

    public function down(): void
    {
        $this->setDefault(1);
    }

    private function setDefault(int $version): void
    {
        foreach (['sidang_ta_registrations', 'sidang_ta_registration_drafts'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($version) {
                // Preserve the existing type; only future inserts get the new default.
                $table->unsignedTinyInteger('requirements_version')->default($version)->change();
            });
        }
    }
};
