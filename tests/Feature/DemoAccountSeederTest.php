<?php

use App\Models\AcademicPeriod;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoAccountSeeder;
use Illuminate\Support\Facades\Hash;

function demo75FeatureRoster(): array
{
    $rows = [];
    for ($i = 1; $i <= 11; $i++) {
        $rows[] = ['nim' => sprintf('1202275%03d', $i), 'nama' => 'MAHASISWA SEED '.$i,
            'program_studi' => 'Informatika', 'semester' => '8'];
    }
    return $rows;
}

function demo75Staging(callable $callback, string $password = 'staging-seed-secret-75', bool $withFile = true): void
{
    $app = app();
    $oldEnv = $app->environment();
    $oldPassword = getenv('DADU_TEST_PASSWORD');
    $oldFile = getenv('DADU_BLACKBOX_JSON');
    $file = tempnam(sys_get_temp_dir(), 'demo75-roster-');
    file_put_contents($file, json_encode(['total' => 11, 'data' => demo75FeatureRoster()], JSON_THROW_ON_ERROR));
    $app->instance('env', 'staging');
    putenv('DADU_TEST_PASSWORD='.$password);
    putenv('DADU_BLACKBOX_JSON='.($withFile ? $file : $file.'.missing'));
    try { $callback(); }
    finally {
        $app->instance('env', $oldEnv);
        $oldPassword === false ? putenv('DADU_TEST_PASSWORD') : putenv('DADU_TEST_PASSWORD='.$oldPassword);
        $oldFile === false ? putenv('DADU_BLACKBOX_JSON') : putenv('DADU_BLACKBOX_JSON='.$oldFile);
        unlink($file);
    }
}

test('demo75_default_local_seed_produces_only_two_accounts_and_manual_logins', function () {
    $this->seed(DatabaseSeeder::class);
    expect(User::orderBy('email')->pluck('email')->all())->toBe(['krisna@mahasiswa.test', 'superadmin@example.test']);
    foreach (User::all() as $user) {
        expect(Hash::check('password', $user->password))->toBeTrue();
        expect($user->auth_source)->toBe('manual');
        expect($user->must_change_password)->toBeFalse();
        expect($user->email_verified_at)->not->toBeNull();
    }
    expect(User::where('email', 'krisna@mahasiswa.test')->firstOrFail()->hasAllRoles(['mahasiswa', 'Petugas']))->toBeTrue();
    expect(User::where('email', 'superadmin@example.test')->firstOrFail()->hasRole('Akademik'))->toBeTrue();
    expect(Student::count())->toBe(1);
    expect(AcademicPeriod::where('is_active', true)->count())->toBe(1);
    expect(AcademicPeriod::where('year', '2026/2027')->where('semester', 1)->value('is_active'))->toBeTrue();
});

test('demo75_local_account_seeder_rerun_does_not_duplicate_users_or_students', function () {
    $this->seed(DatabaseSeeder::class);
    $ids = User::orderBy('email')->pluck('id')->all();
    $this->seed(DemoAccountSeeder::class);
    expect(User::orderBy('email')->pluck('id')->all())->toBe($ids);
    expect(Student::count())->toBe(1);
});

test('demo75_staging_seed_creates_thirteen_users_with_separate_fixed_and_blackbox_passwords', function () {
    demo75Staging(function () {
        $this->seed(DatabaseSeeder::class);
        expect(User::count())->toBe(13);
        expect(Student::count())->toBe(12);
        foreach (['superadmin@example.test', 'krisna@mahasiswa.test'] as $email) {
            $user = User::where('email', $email)->firstOrFail();
            expect(Hash::check('staging-seed-secret-75', $user->password))->toBeTrue();
            expect(Hash::check('password', $user->password))->toBeFalse();
        }
        foreach (demo75FeatureRoster() as $row) {
            $user = User::where('username', $row['nim'])->firstOrFail();
            expect(Hash::check('password', $user->password))->toBeTrue();
            expect($user->auth_source)->toBe('manual');
            expect($user->must_change_password)->toBeFalse();
            expect($user->hasRole('mahasiswa'))->toBeTrue();
            expect($user->getRoleNames()->all())->toBe(['mahasiswa']);
            expect(Student::where('student_number', $row['nim'])->value('email'))->toBe($user->email);
        }
        expect(User::where('email', 'dummy-ta-2@mahasiswa.test')->exists())->toBeFalse();
    });
});

test('demo75_staging_preflight_is_read_only', function () {
    demo75Staging(function () {
        $before = User::orderBy('id')->pluck('email')->all();
        expect(app(DemoAccountSeeder::class)->plannedAccounts())->toHaveCount(13);
        expect(User::orderBy('id')->pluck('email')->all())->toBe($before);
    });
});

test('demo75_staging_refuses_missing_roster_or_weak_fixed_password_before_seeding', function () {
    foreach ([['password', true, InvalidArgumentException::class],
        ['staging-seed-secret-75', false, RuntimeException::class]] as [$password, $file, $exception]) {
        demo75Staging(function () use ($exception) {
            $before = User::orderBy('id')->pluck('email')->all();
            expect(fn () => $this->seed(DatabaseSeeder::class))->toThrow($exception);
            expect(User::orderBy('id')->pluck('email')->all())->toBe($before);
        }, $password, $file);
    }
});

test('demo75_rerunning_seeder_never_deletes_unrelated_existing_users', function () {
    $other = User::factory()->create(['email' => 'existing-demo75@example.test']);
    $this->seed(DatabaseSeeder::class);
    expect(User::whereKey($other->id)->exists())->toBeTrue();
    expect(User::count())->toBe(3);
});

test('demo75_bootstrap_preserves_a_previously_selected_active_period', function () {
    $existing = AcademicPeriod::create(['year' => '2025/2026', 'semester' => 2, 'label' => 'Existing 75',
        'start_date' => '2026-02-01', 'end_date' => '2026-07-31', 'is_active' => true]);
    $this->seed(DatabaseSeeder::class);
    expect($existing->fresh()->is_active)->toBeTrue();
    expect(AcademicPeriod::where('is_active', true)->count())->toBe(1);
    expect(AcademicPeriod::where('year', '2026/2027')->where('semester', 1)->value('is_active'))->toBeFalse();
});
