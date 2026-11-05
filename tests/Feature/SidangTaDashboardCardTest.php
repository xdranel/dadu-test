<?php

use App\Models\GraduationPeriod;
use App\Models\SidangTaPeriod;
use App\Models\Student;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\DomCrawler\Crawler;

beforeEach(function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-11-04 12:00:00', config('app.timezone')));
    $role = Role::firstOrCreate(['name' => 'mahasiswa', 'guard_name' => 'web']);
    $permission = Permission::firstOrCreate(['name' => 'sidang_ta_registration_read', 'guard_name' => 'web']);
    $role->syncPermissions([]);
    $this->user = User::factory()->create(['username' => '1202299057', 'email' => 'ta-dashboard@test.local',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
    $this->user->assignRole($role);
    $this->user->givePermissionTo($permission);
    Student::create(['student_number' => $this->user->username, 'name' => 'TA Dashboard',
        'email' => $this->user->email, 'academic_year' => 2022, 'study_program' => 'Informatika', 'status' => 'Aktif']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($this->user);
    $this->taPeriod = function (string $name, array $overrides = []) {
        return SidangTaPeriod::create(array_replace(['name' => $name, 'is_active' => true,
            'open_at' => now()->subDay(), 'close_at' => now()->addDays(2)], $overrides));
    };
    $this->dashboardCard = function () {
        return (new Crawler($this->get(route('dashboard'))->assertOk()->getContent()))->filter('[data-ta-dashboard-card]');
    };
});

afterEach(function () { $this->travelBack(); });

test('meet_home_card_lists_both_open_periods_with_their_dates_and_index_link', function () {
    $a = ($this->taPeriod)('Sidang TA A');
    $b = ($this->taPeriod)('Sidang TA B', ['open_at' => now()->subHours(2), 'close_at' => now()->addDays(5)]);
    $card = ($this->dashboardCard)();
    expect($card->count())->toBe(1);
    expect($card->filter('[data-ta-dashboard-period]')->count())->toBe(2);
    foreach ([$a, $b] as $period) {
        $entry = $card->filter('[data-ta-dashboard-period="'.$period->id.'"]');
        expect($entry->text(null, true))->toContain($period->name)->toContain($period->open_at->translatedFormat('d M Y H:i'));
        expect($entry->filter('[data-ta-open-at]')->attr('datetime'))->toBe($period->open_at->toIso8601String());
        expect($entry->filter('[data-ta-close-at]')->attr('datetime'))->toBe($period->close_at->toIso8601String());
    }
    expect($card->filter('a[data-ta-dashboard-link]')->attr('href'))->toBe(route('sidang-ta-registrations.index'));
});

test('meet_home_card_excludes_inactive_future_and_closed_periods_from_open_list', function () {
    $open = ($this->taPeriod)('Terbuka');
    ($this->taPeriod)('Tidak aktif', ['is_active' => false]);
    ($this->taPeriod)('Mendatang', ['open_at' => now()->addDay(), 'close_at' => now()->addDays(3)]);
    ($this->taPeriod)('Ditutup', ['open_at' => now()->subDays(4), 'close_at' => now()->subDay()]);
    $card = ($this->dashboardCard)();
    expect($card->filter('[data-ta-dashboard-period]')->count())->toBe(1);
    expect($card->filter('[data-ta-dashboard-period]')->attr('data-ta-dashboard-period'))->toBe((string) $open->id);
    expect($card->filter('[data-ta-dashboard-upcoming]')->count())->toBe(0);
});

test('meet_home_card_displays_nearest_upcoming_period_when_none_is_open', function () {
    ($this->taPeriod)('Lebih jauh', ['open_at' => now()->addDays(4), 'close_at' => now()->addDays(5)]);
    $near = ($this->taPeriod)('Paling dekat', ['open_at' => now()->addDay(), 'close_at' => now()->addDays(2)]);
    ($this->taPeriod)('Nonaktif besok', ['is_active' => false, 'open_at' => now()->addHour(), 'close_at' => now()->addDay()]);
    $card = ($this->dashboardCard)();
    expect($card->filter('[data-ta-dashboard-period]')->count())->toBe(0);
    expect($card->filter('[data-ta-dashboard-upcoming]')->attr('data-ta-dashboard-upcoming'))->toBe((string) $near->id);
    expect($card->filter('[data-ta-dashboard-upcoming]')->text(null, true))->toContain($near->name);
    expect($card->filter('a[data-ta-dashboard-link]')->attr('href'))->toBe(route('sidang-ta-registrations.index'));
});

test('meet_home_card_keeps_draft_and_history_access_when_no_period_is_available', function () {
    ($this->taPeriod)('Sudah tutup', ['open_at' => now()->subDays(4), 'close_at' => now()->subDay()]);
    $card = ($this->dashboardCard)();
    expect($card->filter('[data-ta-dashboard-empty]')->count())->toBe(1);
    expect($card->text(null, true))->toContain('Belum ada periode Sidang TA terbuka.');
    $link = $card->filter('a[data-ta-dashboard-link]');
    expect($link->attr('href'))->toBe(route('sidang-ta-registrations.index'));
    expect($link->getNode(0)->hasAttribute('disabled'))->toBeFalse();
    expect($link->getNode(0)->hasAttribute('aria-disabled'))->toBeFalse();
});

test('meet_home_card_respects_student_role_and_read_permission', function () {
    $this->user->revokePermissionTo('sidang_ta_registration_read');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($this->user->fresh());
    expect(($this->dashboardCard)()->count())->toBe(0);
    $this->user->givePermissionTo('sidang_ta_registration_read');
    $this->user->removeRole('mahasiswa');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($this->user->fresh());
    expect(($this->dashboardCard)()->count())->toBe(0);
});

test('meet_home_card_uses_sidang_periods_instead_of_the_graduation_period', function () {
    ($this->taPeriod)('Nama Periode Sidang');
    GraduationPeriod::create(['name' => 'Nama Periode Yudisium', 'is_active' => true,
        'open_at' => now()->subDays(3), 'close_at' => now()->addDays(7)]);
    $card = ($this->dashboardCard)();
    expect($card->text(null, true))->toContain('Nama Periode Sidang')->not->toContain('Nama Periode Yudisium');
});
