<?php

use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\User;
use App\Services\SidebarBadgeService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\DomCrawler\Crawler;

beforeEach(function () {
    $this->travelTo(now()->startOfSecond());
    foreach (['sidang_ta_registration_review', 'sidang_ta_registration_read',
                 'sidang_ta_period_read', 'sidang_ta_period_create', 'sidang_ta_period_update',
                 'sidang_ta_period_delete', 'student_read', 'lecturer_read'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->makeActor = function (array $permissions = []) {
        $id = (string) Str::uuid();
        $user = User::factory()->create(['username' => 'p1-'.$id, 'email' => $id.'@staff.test',
            'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
        if ($permissions !== []) $user->givePermissionTo($permissions);
        return $user;
    };
    $this->period = SidangTaPeriod::create(['name' => 'Periode P1', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay()]);
    $this->registration = SidangTaRegistration::create([
        'sidang_ta_period_id' => $this->period->id, 'student_number' => 'P1-PRIVATE-0001',
        'student_name' => 'Mahasiswa Rahasia P1', 'study_program' => 'Informatika',
        'student_phone' => '081234567890', 'parent_phone' => '081234567891',
        'graduation_payment_date' => today()->toDateString(), 'diploma_biodata_accurate' => true,
        'status' => 'submitted', 'submitted_at' => now(),
    ])->fresh();
    $this->periodData = ['name' => 'Periode Baru P1', 'is_active' => 1,
        'open_at' => now()->subHour()->format('Y-m-d H:i:s'),
        'close_at' => now()->addDays(2)->format('Y-m-d H:i:s')];
    $this->sidebarFor = function (string $html, string $route) {
        return (new Crawler($html))->filter('aside nav a')->reduce(
            fn (Crawler $node) => $node->attr('href') === route($route)
        );
    };
    $this->validatorConfig = function ($any) {
        config(['sidebar' => ['mahasiswa' => [], 'admin_standalone' => [], 'admin' => [
            'p1' => ['label' => 'P1', 'items' => [[
                'label' => 'P1 OR', 'route' => 'admin.sidang-ta-registrations.index',
                'permissions_any' => $any,
            ]]],
        ]]]);
    };
});

afterEach(function () {
    $this->travelBack();
});

test('p1_combined_permissions_show_one_sidebar_entry_and_manage_link', function () {
    $user = ($this->makeActor)(['sidang_ta_registration_review', 'sidang_ta_period_read']);
    $response = $this->actingAs($user)->get(route('admin.sidang-ta-registrations.index'))->assertOk();
    $html = $response->getContent();
    $crawler = new Crawler($html);
    expect(($this->sidebarFor)($html, 'admin.sidang-ta-registrations.index')->count())->toBe(1);
    expect(($this->sidebarFor)($html, 'admin.sidang-ta-periods.index')->count())->toBe(0);
    expect($crawler->filter('[data-manage-ta-periods]')->attr('href'))->toBe(route('admin.sidang-ta-periods.index'));
    expect($crawler->filter('[data-ta-review-data]')->count())->toBe(1);
    $response->assertSee('Mahasiswa Rahasia P1');
});

test('p1_reviewer_without_period_read_keeps_review_but_cannot_manage_periods', function () {
    $user = ($this->makeActor)(['sidang_ta_registration_review']);
    $html = $this->actingAs($user)->get(route('admin.sidang-ta-registrations.index'))->assertOk()->getContent();
    $crawler = new Crawler($html);
    expect($crawler->filter('[data-ta-review-data]')->count())->toBe(1);
    expect($crawler->filter('[data-manage-ta-periods]')->count())->toBe(0);
    $this->get(route('admin.sidang-ta-registrations.show', $this->registration))->assertOk();
    $this->get(route('admin.sidang-ta-periods.index'))->assertForbidden();
});

test('p1_period_reader_hub_does_not_query_or_expose_registration_data', function () {
    $user = ($this->makeActor)(['sidang_ta_period_read']);
    $queries = [];
    DB::listen(function (QueryExecuted $event) use (&$queries) {
        if (str_contains(strtolower($event->sql), 'sidang_ta_registrations')) $queries[] = $event->sql;
    });
    $response = $this->actingAs($user)->get(route('admin.sidang-ta-registrations.index', ['search' => 'P1']))
        ->assertOk()->assertViewHas('canReview', false)->assertViewHas('registrations', fn ($value) => $value === null)
        ->assertDontSee('Mahasiswa Rahasia P1')->assertDontSee('P1-PRIVATE-0001');
    expect($queries)->toBe([]);
    $crawler = new Crawler($response->getContent());
    expect($crawler->filter('[data-ta-review-data]')->count())->toBe(0);
    expect($crawler->filter('[data-ta-period-only]')->count())->toBe(1);
    expect($crawler->filter('[data-manage-ta-periods]')->count())->toBe(1);
    $this->get(route('admin.sidang-ta-registrations.show', $this->registration))->assertForbidden();
    $this->post(route('admin.sidang-ta-registrations.review', $this->registration), [
        'action' => 'approved', 'registration_version' => $this->registration->lock_version,
    ])->assertForbidden();
    expect($this->registration->fresh()->status)->toBe('submitted');
    expect($user->fresh()->can('sidang_ta_registration_review'))->toBeFalse();
});

test('p1_user_without_either_permission_has_no_hub_or_menu', function () {
    $user = ($this->makeActor)();
    $this->actingAs($user)->get(route('admin.sidang-ta-registrations.index'))->assertForbidden();
    $this->get(route('admin.sidang-ta-periods.index'))->assertForbidden();
    $html = $this->get(route('dashboard'))->assertOk()->getContent();
    expect(($this->sidebarFor)($html, 'admin.sidang-ta-registrations.index')->count())->toBe(0);
});

test('p1_period_page_keeps_review_menu_active_and_has_fixed_back_link', function () {
    $user = ($this->makeActor)(['sidang_ta_period_read']);
    $html = $this->actingAs($user)->get(route('admin.sidang-ta-periods.index'))->assertOk()->getContent();
    $crawler = new Crawler($html);
    expect(($this->sidebarFor)($html, 'admin.sidang-ta-registrations.index')->attr('aria-current'))->toBe('page');
    expect(($this->sidebarFor)($html, 'admin.sidang-ta-periods.index')->count())->toBe(0);
    expect($crawler->filter('[data-back-to-ta-review]')->attr('href'))->toBe(route('admin.sidang-ta-registrations.index'));
    $groupKey = null;
    foreach (config('sidebar.admin') as $key => $group) {
        foreach ($group['items'] ?? [] as $item) {
            if (($item['route'] ?? null) === 'admin.sidang-ta-registrations.index') $groupKey = $key;
        }
    }
    expect($groupKey)->not->toBeNull();
    expect($crawler->filter('aside nav [x-data]')->attr('x-data'))->toContain($groupKey);
});

test('p1_period_reader_sees_all_time_states_without_write_forms', function () {
    $user = ($this->makeActor)(['sidang_ta_period_read']);
    $cases = [[$this->period, 'open']];
    foreach ([['inactive', false, now()->subDay(), now()->addDay()],
                 ['upcoming', true, now()->addHour(), now()->addDay()],
                 ['closed', true, now()->subDays(2), now()->subDay()],
                 ['open', true, now(), now()->addDay()],
                 ['open', true, now()->subDay(), now()]] as [$state, $active, $open, $close]) {
        $cases[] = [SidangTaPeriod::create(['name' => 'State '.$state, 'is_active' => $active,
            'open_at' => $open, 'close_at' => $close]), $state];
    }
    $crawler = new Crawler($this->actingAs($user)->get(route('admin.sidang-ta-periods.index'))->assertOk()->getContent());
    expect($crawler->filter('form[data-ta-period-create]')->count())->toBe(0);
    expect($crawler->filter('form[data-ta-period-update]')->count())->toBe(0);
    foreach ($cases as [$period, $state]) {
        expect($crawler->filter('[data-ta-period-id="'.$period->id.'"] [data-period-state]')->attr('data-period-state'))->toBe($state);
    }
    $this->post(route('admin.sidang-ta-periods.store'), $this->periodData)->assertForbidden();
    $this->put(route('admin.sidang-ta-periods.update', $this->period), $this->periodData)->assertForbidden();
    $this->post(route('admin.sidang-ta-periods.activate', $this->period))->assertForbidden();
    $this->delete(route('admin.sidang-ta-periods.destroy', $this->period))->assertForbidden();
});

test('p1_creator_can_create_second_active_period_without_update_controls', function () {
    $user = ($this->makeActor)(['sidang_ta_period_read', 'sidang_ta_period_create']);
    $url = route('admin.sidang-ta-periods.index');
    $crawler = new Crawler($this->actingAs($user)->get($url)->assertOk()->getContent());
    expect($crawler->filter('form[data-ta-period-create]')->count())->toBe(1);
    expect($crawler->filter('form[data-ta-period-update]')->count())->toBe(0);
    $this->from($url)->post(route('admin.sidang-ta-periods.store'), $this->periodData)
        ->assertRedirect($url)->assertSessionHasNoErrors();
    expect($this->period->fresh()->is_active)->toBeTrue();
    expect(SidangTaPeriod::where('name', 'Periode Baru P1')->firstOrFail()->is_active)->toBeTrue();
    $this->put(route('admin.sidang-ta-periods.update', $this->period), $this->periodData)->assertForbidden();
});

test('p1_updater_has_correct_datetime_inputs_and_keeps_other_period_active', function () {
    $user = ($this->makeActor)(['sidang_ta_period_read', 'sidang_ta_period_update']);
    $url = route('admin.sidang-ta-periods.index');
    $crawler = new Crawler($this->actingAs($user)->get($url)->assertOk()->getContent());
    expect($crawler->filter('form[data-ta-period-create]')->count())->toBe(0);
    $input = $crawler->filter('[data-ta-period-id="'.$this->period->id.'"] input[name="open_at"]');
    expect($input->attr('value'))->toBe($this->period->open_at->format('Y-m-d\TH:i'));
    $second = SidangTaPeriod::create(['name' => 'Update P1', 'is_active' => false,
        'open_at' => now()->addHour(), 'close_at' => now()->addDay()]);
    $this->from($url)->post(route('admin.sidang-ta-periods.activate', $second))->assertRedirect($url);
    expect($this->period->fresh()->is_active)->toBeTrue();
    expect($second->fresh()->is_active)->toBeTrue();
    $this->from($url)->put(route('admin.sidang-ta-periods.update', $second), $this->periodData)
        ->assertRedirect($url)->assertSessionHasNoErrors();
    expect($this->period->fresh()->is_active)->toBeTrue();
    expect($second->fresh()->name)->toBe('Periode Baru P1');
    $this->post(route('admin.sidang-ta-periods.store'), $this->periodData)->assertForbidden();
    $this->delete(route('admin.sidang-ta-periods.destroy', $second))->assertForbidden();
});

test('p1_sidebar_or_is_fail_closed_and_preserves_legacy_permission_role_items', function () {
    $user = ($this->makeActor)(['student_read']);
    $role = Role::findOrCreate('P1Legacy', 'web');
    $user->assignRole($role);
    $this->actingAs($user);
    $groups = ['test' => ['label' => 'P1 Group', 'icon' => [], 'items' => [
        ['label' => 'P1 Public', 'route' => 'dashboard'],
        ['label' => 'P1 Permission', 'route' => 'dashboard', 'permission' => 'student_read'],
        ['label' => 'P1 Denied', 'route' => 'dashboard', 'permission' => 'lecturer_read'],
        ['label' => 'P1 Role', 'route' => 'dashboard', 'roles' => ['P1Legacy']],
        ['label' => 'P1 Empty OR', 'route' => 'dashboard', 'permissions_any' => []],
        ['label' => 'P1 Unmatched OR', 'route' => 'dashboard', 'permissions_any' => ['sidang_ta_registration_review'], 'roles' => ['P1Legacy']],
        ['label' => 'P1 Missing Route', 'route' => 'p1.route.does.not.exist'],
    ]]];
    $html = view('components.sidebar-admin', ['groups' => $groups, 'standalone' => [],
        'badgeService' => app(SidebarBadgeService::class)])->render();
    expect($html)->toContain('P1 Public')->toContain('P1 Permission')->toContain('P1 Role')
        ->not->toContain('P1 Denied')->not->toContain('P1 Empty OR')->not->toContain('P1 Unmatched OR')
        ->not->toContain('P1 Missing Route');
});

test('p1_sidebar_validator_accepts_registered_or_permissions', function () {
    ($this->validatorConfig)(['sidang_ta_registration_review', 'sidang_ta_period_read']);
    $this->artisan('sidebar:validate')->assertExitCode(0);
});

test('p1_sidebar_validator_rejects_unknown_empty_and_invalid_or_permissions', function () {
    ($this->validatorConfig)(['p1_unknown_permission']);
    $this->artisan('sidebar:validate')->expectsOutputToContain('Permission tidak terdaftar')->assertExitCode(1);
    foreach ([[], 'sidang_ta_period_read', [null], ['']] as $invalid) {
        ($this->validatorConfig)($invalid);
        $this->artisan('sidebar:validate')->expectsOutputToContain('permissions_any')->assertExitCode(1);
    }
});

test('p1_role_switch_revokes_staff_hub_access_while_student_role_is_active', function () {
    $staff = Role::findOrCreate('P1Reviewer', 'web');
    $staff->givePermissionTo('sidang_ta_registration_review');
    $student = Role::findOrCreate('mahasiswa', 'web');
    $student->givePermissionTo('sidang_ta_registration_read');
    $user = ($this->makeActor)();
    $user->assignRole([$staff, $student]);
    $this->actingAs($user)->withSession(['active_role' => 'P1Reviewer'])
        ->get(route('admin.sidang-ta-registrations.index'))->assertOk();
    $this->post(route('role.switch'), ['role' => 'mahasiswa'])->assertRedirect(route('dashboard'));
    $this->get(route('admin.sidang-ta-registrations.index'))->assertForbidden();
    $this->get(route('dashboard'))->assertOk()->assertDontSee('Review Sidang TA');
    $this->post(route('role.switch'), ['role' => 'P1Reviewer'])->assertRedirect(route('dashboard'));
    $this->get(route('admin.sidang-ta-registrations.index'))->assertOk();
});
