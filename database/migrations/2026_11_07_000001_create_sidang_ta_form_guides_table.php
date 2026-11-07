<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sidang_ta_form_guides', function (Blueprint $table) {
            $table->id();
            $table->string('slot', 64)->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->string('link_label')->nullable();
            $table->string('url', 2048)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('seed_key')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        foreach (['create', 'read', 'update', 'delete'] as $action) {
            $permission = Permission::firstOrCreate(['name' => 'sidang_ta_form_guide_'.$action, 'guard_name' => 'web']);
            Role::where('name', 'Akademik')->where('guard_name', 'web')->first()?->givePermissionTo($permission);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('sidang_ta_form_guides');
        foreach (['create', 'read', 'update', 'delete'] as $action) {
            Permission::where('name', 'sidang_ta_form_guide_'.$action)->where('guard_name', 'web')->get()->each->delete();
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
