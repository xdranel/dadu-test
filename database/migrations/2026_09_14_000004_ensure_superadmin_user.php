<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Memastikan user superadmin@example.test ada dan memiliki role 'Akademik' (Superadmin).
     */
    public function up(): void
    {
        // 1. Pastikan role Akademik ada
        $akademikRole = Role::firstOrCreate([
            'name'       => 'Akademik',
            'guard_name' => 'web',
        ]);

        // 2. Pastikan role admin ada
        Role::firstOrCreate([
            'name'       => 'admin',
            'guard_name' => 'web',
        ]);

        // 3. Pastikan user superadmin@example.test ada dan memiliki role Akademik
        $user = User::where('email', 'superadmin@example.test')->first();
        if (!$user) {
            $user = User::create([
                'username'    => 'superadmin',
                'name'        => 'Super Admin',
                'email'       => 'superadmin@example.test',
                'password'    => Hash::make('password'),
                'status'      => 'active',
                'auth_source' => 'manual',
            ]);
        } else {
            $user->update([
                'auth_source' => 'manual',
            ]);
        }

        if (!$user->hasRole('Akademik')) {
            $user->assignRole($akademikRole);
        }

        // Clear Spatie cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Tidak menghapus user atau role pada rollback demi keamanan data
    }
};
