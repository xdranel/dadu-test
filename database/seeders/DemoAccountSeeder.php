<?php

namespace Database\Seeders;

use App\Models\AcademicPeriod;
use App\Models\Student;
use App\Models\User;
use App\Support\DemoAccountPlan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

class DemoAccountSeeder extends Seeder
{
    /** Read-only preflight; also used before the destructive reset command. */
    public function plannedAccounts(): array
    {
        if (app()->environment('local', 'testing')) return DemoAccountPlan::local();
        if (!app()->environment('staging')) {
            throw new RuntimeException('Bootstrap demo hanya untuk APP_ENV local/testing/staging.');
        }
        $file = getenv('DADU_BLACKBOX_JSON') ?: base_path('private-import/blackbox-accounts.json');
        if (!is_file($file) || !is_readable($file)) {
            throw new RuntimeException('Roster blackbox privat belum tersedia/terbaca; jalankan persiapan fixture75.');
        }
        $payload = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($payload) || ($payload['total'] ?? null) !== 11 || !is_array($payload['data'] ?? null)) {
            throw new RuntimeException('Format roster blackbox privat tidak valid.');
        }
        return DemoAccountPlan::staging($payload['data'], getenv('DADU_TEST_PASSWORD') ?: '');
    }

    public function run(): void
    {
        $accounts = $this->plannedAccounts();
        DB::transaction(function () use ($accounts) {
            foreach ($accounts as $account) {
                $user = User::updateOrCreate(['email' => $account['email']], [
                    'username' => $account['username'], 'name' => $account['name'],
                    'password' => Hash::make($account['password']), 'status' => 'active',
                    'auth_source' => 'manual', 'must_change_password' => false, 'email_verified_at' => now(),
                ]);
                $user->syncRoles($account['roles']);
                if ($account['student'] !== null) {
                    Student::updateOrCreate(['student_number' => $account['student']['student_number']], $account['student']);
                }
            }
            // Fresh databases get the source KRS period; reruns preserve an existing active period.
            $hasActive = AcademicPeriod::where('is_active', true)->exists();
            $period = AcademicPeriod::firstOrCreate(['year' => '2026/2027', 'semester' => 1], [
                'label' => 'Ganjil 2026/2027', 'start_date' => '2026-08-01', 'end_date' => '2027-01-31',
                'is_active' => !$hasActive,
            ]);
            if (!$hasActive && !$period->is_active) $period->update(['is_active' => true]);
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->command?->info(count($accounts).' akun demo disiapkan. Password tidak dicetak.');
    }
}
