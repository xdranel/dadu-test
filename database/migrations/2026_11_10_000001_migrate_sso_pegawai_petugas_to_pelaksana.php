<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use App\Models\User;

return new class extends Migration
{
    /**
     * Migrasikan pegawai SSO yang sebelumnya landing/diberikan role Petugas ke Pelaksana.
     *
     * Kriteria yang dimigrasi:
     * - auth_source = 'sso'
     * - email berdomain @telkomuniversity.ac.id (bukan domain mahasiswa @student.)
     * - memiliki sso_role 'Petugas' atau role 'Petugas'
     *
     * Pengecualian (TIDAK dimigrasi):
     * - Petugas yang didaftarkan manual oleh admin (auth_source = 'manual')
     * - Akun mahasiswa (memiliki role mahasiswa, domain @student, atau terdaftar di tabel students)
     */
    public function up(): void
    {
        $pelaksanaRole = Role::firstOrCreate(['name' => 'Pelaksana']);

        // Ambil nomor induk mahasiswa untuk proteksi ekstra agar tidak memigrasi akun mahasiswa
        $studentNims = [];
        if (DB::getSchemaBuilder()->hasTable('students')) {
            $studentNims = DB::table('students')->pluck('student_number')->filter()->all();
        }

        $users = User::where('auth_source', 'sso')
            ->where(function ($q) {
                $q->where('sso_role', 'Petugas')
                  ->orWhereHas('roles', fn ($r) => $r->where('name', 'Petugas'));
            })
            ->where('email', 'like', '%@telkomuniversity.ac.id')
            ->where('email', 'not like', '%@student.%')
            ->get();

        foreach ($users as $user) {
            // Abaikan jika akun adalah mahasiswa
            if ($user->hasRole('mahasiswa') || in_array($user->username, $studentNims, true)) {
                continue;
            }

            // Ganti role Petugas menjadi Pelaksana
            if ($user->hasRole('Petugas')) {
                $user->removeRole('Petugas');
            }
            if (! $user->hasRole('Pelaksana')) {
                $user->assignRole('Pelaksana');
            }

            // Perbarui sso_role jika sebelumnya 'Petugas'
            if ($user->sso_role === 'Petugas') {
                $user->update(['sso_role' => 'Pelaksana']);
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $users = User::where('auth_source', 'sso')
            ->where('sso_role', 'Pelaksana')
            ->where('email', 'like', '%@telkomuniversity.ac.id')
            ->where('email', 'not like', '%@student.%')
            ->get();

        foreach ($users as $user) {
            if ($user->hasRole('mahasiswa')) {
                continue;
            }

            if ($user->hasRole('Pelaksana')) {
                $user->removeRole('Pelaksana');
            }
            if (! $user->hasRole('Petugas')) {
                $user->assignRole('Petugas');
            }

            $user->update(['sso_role' => 'Petugas']);
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
