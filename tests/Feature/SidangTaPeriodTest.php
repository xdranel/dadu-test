<?php

use App\Models\SidangTaPeriod;
use App\Models\User;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;


function sidangTaPeriodTestData(array $overrides = []): array
{
    return array_replace([
        'name' => 'Periode Sidang TA Test',
        'open_at' => now()->subDay()->format('Y-m-d H:i:s'),
        'close_at' => now()->addDay()->format('Y-m-d H:i:s'),
        'description' => 'Fixture pengujian, bukan data aplikasi.',
        'is_active' => true,
    ], $overrides);
}

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-01 12:00:00', config('app.timezone')));

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $role = Role::firstOrCreate([
        'name' => 'Sidang TA Test Operator',
        'guard_name' => 'web',
    ]);

    foreach (['read', 'create', 'update'] as $action) {
        $permission = Permission::firstOrCreate([
            'name' => "sidang_ta_period_{$action}",
            'guard_name' => 'web',
        ]);
        $role->givePermissionTo($permission);
    }

    $this->operator = User::factory()->create([
        'status' => 'active',
        'auth_source' => 'manual',
        'must_change_password' => false,
    ]);
    $this->operator->assignRole($role);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

afterEach(function () {
    $this->travelBack();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

test('lists_only_open_periods', function () {
    $open = SidangTaPeriod::create(sidangTaPeriodTestData());

    SidangTaPeriod::create(sidangTaPeriodTestData([
        'name' => 'Belum dibuka',
        'open_at' => now()->addDay(),
        'close_at' => now()->addDays(2),
    ]));
    SidangTaPeriod::create(sidangTaPeriodTestData([
        'name' => 'Sudah ditutup',
        'open_at' => now()->subDays(2),
        'close_at' => now()->subDay(),
    ]));
    SidangTaPeriod::create(sidangTaPeriodTestData([
        'name' => 'Nonaktif',
        'is_active' => false,
    ]));

    expect(SidangTaPeriod::query()->openForRegistration()->pluck('id')->all())
        ->toBe([$open->id]);
});

test('includes_exact_open_and_close_boundaries', function () {
    $opensNow = SidangTaPeriod::create(sidangTaPeriodTestData([
        'name' => 'Dibuka tepat sekarang',
        'open_at' => now(),
    ]));
    $closesNow = SidangTaPeriod::create(sidangTaPeriodTestData([
        'name' => 'Ditutup tepat sekarang',
        'close_at' => now(),
    ]));

    // closesNow memiliki open_at lebih awal, sehingga berada di urutan pertama.
    expect(SidangTaPeriod::query()->openForRegistration()->pluck('id')->all())
        ->toBe([$closesNow->id, $opensNow->id]);
    expect($opensNow->isOpen())->toBeTrue();
    expect($closesNow->isOpen())->toBeTrue();
});

test('orders_open_periods_by_open_date_then_id', function () {
    $later = SidangTaPeriod::create(sidangTaPeriodTestData([
        'open_at' => now()->subHour(),
    ]));
    $earlier = SidangTaPeriod::create(sidangTaPeriodTestData([
        'open_at' => now()->subHours(2),
    ]));
    $sameTime = SidangTaPeriod::create(sidangTaPeriodTestData([
        'open_at' => now()->subHours(2),
    ]));

    expect(SidangTaPeriod::query()->openForRegistration()->pluck('id')->all())
        ->toBe([$earlier->id, $sameTime->id, $later->id]);
});

test('allows_two_open_periods', function () {
    $url = route('admin.sidang-ta-periods.index');

    foreach (['Periode A', 'Periode B'] as $name) {
        $this->actingAs($this->operator)
            ->from($url)
            ->post(route('admin.sidang-ta-periods.store'), sidangTaPeriodTestData([
                'name' => $name,
            ]))
            ->assertRedirect($url)
            ->assertSessionHasNoErrors();
    }

    foreach (['Periode A', 'Periode B'] as $name) {
        $this->assertDatabaseHas('sidang_ta_periods', [
            'name' => $name,
            'is_active' => true,
        ]);
    }
    expect(SidangTaPeriod::query()->openForRegistration()->count())->toBe(2);
});

test('updating_period_keeps_other_period_active', function () {
    $first = SidangTaPeriod::create(sidangTaPeriodTestData(['name' => 'A']));
    $second = SidangTaPeriod::create(sidangTaPeriodTestData(['name' => 'B']));
    $url = route('admin.sidang-ta-periods.index');

    $this->actingAs($this->operator)->from($url)
        ->put(route('admin.sidang-ta-periods.update', $second), sidangTaPeriodTestData([
            'name' => 'B diperbarui',
        ]))
        ->assertRedirect($url)
        ->assertSessionHasNoErrors();

    expect($first->fresh()->is_active)->toBeTrue();
    expect($second->fresh()->is_active)->toBeTrue();
    expect($second->fresh()->name)->toBe('B diperbarui');
});

test('activating_period_keeps_other_period_active', function () {
    $first = SidangTaPeriod::create(sidangTaPeriodTestData(['name' => 'A']));
    $second = SidangTaPeriod::create(sidangTaPeriodTestData([
        'name' => 'B', 'is_active' => false,
    ]));
    $url = route('admin.sidang-ta-periods.index');

    $this->actingAs($this->operator)->from($url)
        ->post(route('admin.sidang-ta-periods.activate', $second))
        ->assertRedirect($url)
        ->assertSessionHasNoErrors();

    expect($first->fresh()->is_active)->toBeTrue();
    expect($second->fresh()->is_active)->toBeTrue();
});

test('deactivating_period_only_changes_target', function () {
    $first = SidangTaPeriod::create(sidangTaPeriodTestData(['name' => 'A']));
    $second = SidangTaPeriod::create(sidangTaPeriodTestData(['name' => 'B']));
    $data = sidangTaPeriodTestData(['name' => 'B']);
    unset($data['is_active']); // Checkbox tidak dicentang tidak dikirim browser.
    $url = route('admin.sidang-ta-periods.index');

    $this->actingAs($this->operator)->from($url)
        ->put(route('admin.sidang-ta-periods.update', $second), $data)
        ->assertRedirect($url)
        ->assertSessionHasNoErrors();

    expect($first->fresh()->is_active)->toBeTrue();
    expect($second->fresh()->is_active)->toBeFalse();
    expect(SidangTaPeriod::query()->openForRegistration()->pluck('id')->all())
        ->toBe([$first->id]);
});

test('rejects_period_changes_without_permission', function () {
    $user = User::factory()->create([
        'status' => 'active',
        'auth_source' => 'manual',
        'must_change_password' => false,
    ]);
    $period = SidangTaPeriod::create(sidangTaPeriodTestData());

    $this->actingAs($user)
        ->post(route('admin.sidang-ta-periods.store'), sidangTaPeriodTestData([
            'name' => 'Tidak boleh dibuat',
        ]))->assertForbidden();

    $this->put(route('admin.sidang-ta-periods.update', $period), sidangTaPeriodTestData([
        'name' => 'Tidak boleh berubah',
    ]))->assertForbidden();

    $this->post(route('admin.sidang-ta-periods.activate', $period))->assertForbidden();

    expect($period->fresh()->name)->toBe('Periode Sidang TA Test');
    expect($period->fresh()->is_active)->toBeTrue();
    $this->assertDatabaseMissing('sidang_ta_periods', ['name' => 'Tidak boleh dibuat']);
});

test('rejects_period_end_before_start', function () {
    $url = route('admin.sidang-ta-periods.index');

    $this->actingAs($this->operator)->from($url)
        ->post(route('admin.sidang-ta-periods.store'), sidangTaPeriodTestData([
            'name' => 'Tanggal salah',
            'close_at' => now()->subDays(2)->format('Y-m-d H:i:s'),
        ]))
        ->assertRedirect($url)
        ->assertSessionHasErrors('close_at');

    $this->assertDatabaseMissing('sidang_ta_periods', ['name' => 'Tanggal salah']);
});
