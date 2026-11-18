<?php

use App\Models\QueueCounter;
use App\Models\QueueTicket;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    Carbon::setTestNow(Carbon::parse('2026-10-12 10:00:00', 'Asia/Jakarta'));
});

afterEach(function () {
    Carbon::setTestNow();
});

test('queue dashboard renders list-waiting list-called and list-finished with locked height and scrollable classes', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $counter = QueueCounter::firstOrCreate(['id' => 1], ['name' => 'Loket 1']);

    $today = now('Asia/Jakarta')->toDateString();

    // Seed tickets across states
    QueueTicket::create([
        'code' => 'A001',
        'status' => 'waiting',
        'device_token' => 'token-1',
        'queue_date' => $today,
        'created_at' => now(),
    ]);

    QueueTicket::create([
        'code' => 'A002',
        'status' => 'called',
        'device_token' => 'token-2',
        'queue_date' => $today,
        'counter_id' => $counter->id,
        'called_at' => now(),
        'created_at' => now(),
    ]);

    for ($i = 3; $i <= 7; $i++) {
        QueueTicket::create([
            'code' => sprintf('A%03d', $i),
            'status' => 'finished',
            'device_token' => 'token-' . $i,
            'queue_date' => $today,
            'counter_id' => $counter->id,
            'finished_at' => now(),
            'created_at' => now(),
        ]);
    }

    $response = $this->actingAs($admin)->get(route('admin.queue.index'));
    $response->assertOk();

    $content = $response->getContent();

    // 1. Grid wrapper must have items-stretch
    expect($content)->toContain('grid grid-cols-1 md:grid-cols-3 gap-6 items-stretch');

    // 2. Outer column cards must have locked height classes and queue-col-card
    expect($content)->toContain('queue-col-card');
    expect($content)->toContain('md:h-[calc(100dvh-16rem)]');
    expect($content)->toContain('md:min-h-[420px]');
    expect($content)->toContain('md:max-h-[680px]');

    // 3. Three scrollable containers must exist
    expect($content)->toContain('id="list-waiting"');
    expect($content)->toContain('id="list-called"');
    expect($content)->toContain('id="list-finished"');

    // 4. Pola A: Wrapper perantara relative flex-1 min-h-0 dan List absolute inset-0 overflow-y-auto min-h-0
    expect($content)->toContain('class="relative flex-1 min-h-0"');
    expect($content)->toMatch('/id="list-waiting"[^>]*class="[^"]*absolute inset-0[^"]*overflow-y-auto[^"]*"/');
    expect($content)->toMatch('/id="list-waiting"[^>]*class="[^"]*min-h-0[^"]*"/');
    expect($content)->toMatch('/id="list-called"[^>]*class="[^"]*absolute inset-0[^"]*overflow-y-auto[^"]*"/');
    expect($content)->toMatch('/id="list-called"[^>]*class="[^"]*min-h-0[^"]*"/');
    expect($content)->toMatch('/id="list-finished"[^>]*class="[^"]*absolute inset-0[^"]*overflow-y-auto[^"]*"/');
    expect($content)->toMatch('/id="list-finished"[^>]*class="[^"]*min-h-0[^"]*"/');

    // 5. Accessibility attributes (tabindex="0", role="region", aria-label)
    expect($content)->toContain('aria-label="Daftar Antrian Menunggu"');
    expect($content)->toContain('aria-label="Daftar Antrian Sedang Dipanggil"');
    expect($content)->toContain('aria-label="Daftar Antrian Selesai Hari Ini"');
});
