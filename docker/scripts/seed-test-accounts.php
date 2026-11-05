<?php

// Explicit one-time staging setup. Never called by an image entrypoint.
use App\Models\AcademicPeriod;
use App\Models\Student;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (!$app->environment('staging')) {
    fwrite(STDERR, "This setup is restricted to APP_ENV=staging.\n");
    exit(1);
}
$password = getenv('DADU_TEST_PASSWORD') ?: '';
if (strlen($password) < 12) {
    fwrite(STDERR, "Set a test-account password of at least 12 characters.\n");
    exit(1);
}
foreach ([
    Database\Seeders\RoleSeeder::class,
    Database\Seeders\RolePermissionSeeder::class,
    Database\Seeders\UnitSeeder::class,
    Database\Seeders\SyncSettingSeeder::class,
] as $seeder) {
    $exit = Artisan::call('db:seed', ['--class' => $seeder, '--force' => true]);
    echo Artisan::output();
    if ($exit !== 0) exit($exit);
}

$accounts = [
    ['username' => 'superadmin', 'email' => 'superadmin@example.test',
        'name' => 'Super Admin Test', 'roles' => ['Akademik'], 'nim' => null],
    ['username' => '1202200001', 'email' => 'krisna@mahasiswa.test',
        'name' => 'Krisna Mahasiswa', 'roles' => ['mahasiswa', 'Petugas'], 'nim' => '1202200001'],
    ['username' => '1202299002', 'email' => 'dummy-ta-2@mahasiswa.test',
        'name' => 'Mahasiswa Dummy TA 2', 'roles' => ['mahasiswa'], 'nim' => '1202299002'],
];
DB::transaction(function () use ($accounts, $password) {
    foreach ($accounts as $account) {
        $user = User::where('email', $account['email'])->orWhere('username', $account['username'])->first();
        $user ??= new User();
        $user->forceFill([
            'username' => $account['username'], 'name' => $account['name'], 'email' => $account['email'],
            'password' => Hash::make($password), 'status' => 'active', 'auth_source' => 'manual',
            'must_change_password' => false, 'email_verified_at' => now(),
        ])->save();
        $user->syncRoles($account['roles']);
        if ($account['nim'] !== null) {
            Student::firstOrCreate(['student_number' => $account['nim']], [
                'name' => $account['name'], 'email' => $account['email'], 'academic_year' => 2022,
                'study_program' => 'Informatika', 'status' => 'Aktif',
            ]);
        }
        echo 'Prepared '.$account['email'].' ('.implode(', ', $account['roles']).").\n";
    }
});
if (in_array('--demo-academic-period', $argv, true)) {
    if (AcademicPeriod::where('is_active', true)->exists()) {
        echo "An active academic period exists; keeping it unchanged.\n";
    } else {
        AcademicPeriod::updateOrCreate(['year' => '2026/2027', 'semester' => 1], [
            'label' => 'DUMMY Ganjil 2026/2027', 'start_date' => '2026-08-01',
            'end_date' => '2027-01-31', 'is_active' => true,
        ]);
        echo "Prepared DUMMY academic period 2026/2027 GANJIL for the sample KRS import.\n";
    }
}
app(PermissionRegistrar::class)->forgetCachedPermissions();
echo "Test accounts are ready. Passwords are not printed by this command.\n";
echo "This script does not import academic JSON or create an open Sidang TA period.\n";
