<?php

use App\Support\DemoAccountPlan as Plan;

function demo75Roster(): array
{
    $rows = [];
    for ($i = 1; $i <= 11; $i++) {
        $rows[] = ['nim' => sprintf('1202275%03d', $i), 'nama' => 'MAHASISWA DEMO '.$i,
            'program_studi' => 'Informatika', 'semester' => '8'];
    }
    return $rows;
}

test('demo75_local_has_only_two_fixed_manual_accounts_with_requested_password', function () {
    $accounts = Plan::local();
    expect(array_column($accounts, 'email'))->toBe(['superadmin@example.test', 'krisna@mahasiswa.test']);
    expect(array_column($accounts, 'password'))->toBe(['password', 'password']);
    expect($accounts[1]['roles'])->toBe(['mahasiswa', 'Petugas']);
    expect($accounts[1]['student']['student_number'])->toBe('1202200001');
});

test('demo75_staging_preserves_the_supplied_fixed_account_secret_and_adds_eleven_testers', function () {
    $accounts = Plan::staging(demo75Roster(), 'staging-test-secret-75');
    expect($accounts)->toHaveCount(13);
    expect($accounts[0]['password'])->toBe('staging-test-secret-75');
    expect($accounts[1]['password'])->toBe('staging-test-secret-75');
    foreach (array_slice($accounts, 2) as $account) {
        expect($account['password'])->toBe('password');
        expect($account['roles'])->toBe(['mahasiswa']);
        expect($account['email'])->toStartWith('mahasiswademo')->toEndWith('@mahasiswa.test');
    }
});

test('demo75_email_uses_the_complete_lowercase_name_without_whitespace', function () {
    expect(Plan::emailForName(' MAHASISWA   DEMO SATU '))->toBe('mahasiswademosatu@mahasiswa.test');
    $accounts = Plan::blackbox(demo75Roster());
    expect($accounts[0]['name'])->toBe('mahasiswa demo 1');
    expect($accounts[0]['student']['name'])->toBe('MAHASISWA DEMO 1');
});

test('demo75_rejects_a_missing_or_short_staging_secret', function () {
    foreach (['', 'password', '12345678901'] as $secret) {
        expect(fn () => Plan::staging(demo75Roster(), $secret))->toThrow(InvalidArgumentException::class);
    }
});

test('demo75_requires_exactly_eleven_blackbox_students', function () {
    expect(fn () => Plan::blackbox([]))->toThrow(InvalidArgumentException::class);
    expect(fn () => Plan::blackbox(array_slice(demo75Roster(), 0, 10)))->toThrow(InvalidArgumentException::class);
});

test('demo75_rejects_duplicate_nims_and_normalized_email_collisions', function () {
    $rows = demo75Roster();
    $rows[1]['nim'] = $rows[0]['nim'];
    expect(fn () => Plan::blackbox($rows))->toThrow(InvalidArgumentException::class);
    $rows = demo75Roster();
    $rows[1]['nama'] = ' mahasiswa  demo 1 ';
    expect(fn () => Plan::blackbox($rows))->toThrow(InvalidArgumentException::class);
});

test('demo75_reserves_the_fixed_krisna_identity_and_email', function () {
    $rows = demo75Roster();
    $rows[0]['nim'] = '1202200001';
    expect(fn () => Plan::blackbox($rows))->toThrow(InvalidArgumentException::class);
    $rows = demo75Roster();
    $rows[0]['nama'] = 'KRISNA';
    expect(fn () => Plan::blackbox($rows))->toThrow(InvalidArgumentException::class);
});

test('demo75_requires_valid_names_programs_and_explicit_study_semesters', function () {
    foreach ([['nim' => 'bad'], ['nama' => ''], ['program_studi' => ''], ['semester' => null],
        ['semester' => '0'], ['semester' => '128'], ['semester' => true]] as $patch) {
        $rows = demo75Roster(); $rows[0] = array_replace($rows[0], $patch);
        expect(fn () => Plan::blackbox($rows))->toThrow(InvalidArgumentException::class);
    }
});

test('demo75_uses_the_existing_nim_entry_year_rule_without_using_the_current_date', function () {
    $rows = demo75Roster();
    $rows[0]['nim'] = '103072400079';
    $rows[1]['nim'] = '1101230018';
    $accounts = Plan::blackbox($rows);
    expect($accounts[0]['student']['academic_year'])->toBe(2024);
    expect($accounts[1]['student']['academic_year'])->toBe(2023);
});
