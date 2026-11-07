<?php

use App\Models\QueueCounter;
use App\Models\QueueState;
use App\Models\QueueTicket;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    $this->travelTo(Carbon::parse('2026-10-12 10:00:00', 'Asia/Jakarta'));
});

function createActiveAdmin(): User {
    $user = User::factory()->create(['status' => 'active']);
    $user->assignRole('admin');
    return $user;
}

// Helper PHP implementation of buildClips to verify JS specification algorithm
function phpBuildClips($n): ?array {
    $n = (int) $n;
    if ($n < 1 || $n > 999) {
        return null;
    }
    if ($n >= 1 && $n <= 11) {
        return [(string) $n];
    }
    if ($n >= 12 && $n <= 19) {
        return [(string) ($n - 10), 'belas'];
    }
    if ($n >= 20 && $n <= 99) {
        $tens = intdiv($n, 10);
        $ones = $n % 10;
        $res = [(string) $tens, 'puluh'];
        if ($ones > 0) {
            $res[] = (string) $ones;
        }
        return $res;
    }
    if ($n === 100) {
        return ['seratus'];
    }
    if ($n >= 101 && $n <= 199) {
        $sub = phpBuildClips($n - 100);
        return $sub ? array_merge(['seratus'], $sub) : ['seratus'];
    }
    if ($n >= 200 && $n <= 999) {
        $hundreds = intdiv($n, 100);
        $rem = $n % 100;
        $res = [(string) $hundreds, 'ratus'];
        if ($rem > 0) {
            $sub = phpBuildClips($rem);
            if ($sub) {
                $res = array_merge($res, $sub);
            }
        }
        return $res;
    }
    return null;
}

test('buildClips specification matches all required numbers', function () {
    expect(phpBuildClips(1))->toBe(['1']);
    expect(phpBuildClips(11))->toBe(['11']);
    expect(phpBuildClips(12))->toBe(['2', 'belas']);
    expect(phpBuildClips(19))->toBe(['9', 'belas']);
    expect(phpBuildClips(20))->toBe(['2', 'puluh']);
    expect(phpBuildClips(21))->toBe(['2', 'puluh', '1']);
    expect(phpBuildClips(99))->toBe(['9', 'puluh', '9']);
    expect(phpBuildClips(100))->toBe(['seratus']);
    expect(phpBuildClips(101))->toBe(['seratus', '1']);
    expect(phpBuildClips(115))->toBe(['seratus', '5', 'belas']);
    expect(phpBuildClips(200))->toBe(['2', 'ratus']);
    expect(phpBuildClips(999))->toBe(['9', 'ratus', '9', 'puluh', '9']);

    // Invalid or outside 1-999 returns null
    expect(phpBuildClips(0))->toBeNull();
    expect(phpBuildClips(1000))->toBeNull();
    expect(phpBuildClips(-1))->toBeNull();
});

test('migration adds call_seq column to queue_states table', function () {
    expect(Schema::hasColumn('queue_states', 'call_seq'))->toBeTrue();
});

test('next calls ticket and increments call_seq in queue_states', function () {
    $user = createActiveAdmin();

    $counter = QueueCounter::firstOrCreate(['id' => 1], ['name' => 'Loket 1']);
    $counter->update(['active_user_id' => $user->id]);

    $state = QueueState::updateOrCreate(
        ['counter_id' => $counter->id],
        ['current_id' => null, 'last_id' => null, 'call_seq' => 0]
    );

    $ticket = QueueTicket::create([
        'code' => '001',
        'nim' => '1201210001',
        'name' => 'Budi Santoso',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'device_token' => (string) Str::uuid(),
        'queue_date' => now('Asia/Jakarta')->toDateString(),
        'status' => 'waiting',
    ]);

    $response = $this->actingAs($user)
        ->withSession(['queue_counter_id' => $counter->id])
        ->post(route('admin.queue.next'));

    $response->assertRedirect();

    $state->refresh();
    $ticket->refresh();

    expect($ticket->status)->toBe('called');
    expect($ticket->counter_id)->toBe($counter->id);
    expect($state->current_id)->toBe($ticket->id);
    expect($state->call_seq)->toBe(1);
});

test('next when queue is empty does not increment call_seq', function () {
    $user = createActiveAdmin();

    $counter = QueueCounter::firstOrCreate(['id' => 1], ['name' => 'Loket 1']);
    $counter->update(['active_user_id' => $user->id]);

    $state = QueueState::updateOrCreate(
        ['counter_id' => $counter->id],
        ['current_id' => null, 'last_id' => null, 'call_seq' => 5]
    );

    $this->actingAs($user)
        ->withSession(['queue_counter_id' => $counter->id])
        ->post(route('admin.queue.next'));

    $state->refresh();
    expect($state->call_seq)->toBe(5);
});

test('back (undo) does not increment call_seq', function () {
    $user = createActiveAdmin();

    $counter = QueueCounter::firstOrCreate(['id' => 1], ['name' => 'Loket 1']);
    $counter->update(['active_user_id' => $user->id]);

    $t1 = QueueTicket::create([
        'code' => '001',
        'nim' => '1201210001',
        'name' => 'Budi',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'device_token' => (string) Str::uuid(),
        'status' => 'finished',
        'counter_id' => $counter->id,
        'queue_date' => now('Asia/Jakarta')->toDateString(),
    ]);

    $t2 = QueueTicket::create([
        'code' => '002',
        'nim' => '1201210002',
        'name' => 'Ani',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'device_token' => (string) Str::uuid(),
        'status' => 'called',
        'counter_id' => $counter->id,
        'queue_date' => now('Asia/Jakarta')->toDateString(),
    ]);

    $state = QueueState::updateOrCreate(
        ['counter_id' => $counter->id],
        ['current_id' => $t2->id, 'last_id' => $t1->id, 'call_seq' => 3]
    );

    $response = $this->actingAs($user)
        ->withSession(['queue_counter_id' => $counter->id])
        ->post(route('admin.queue.back'));

    $response->assertRedirect();

    $state->refresh();
    expect($state->call_seq)->toBe(3); // Tetap 3, tidak bertambah!
});

test('displayJson includes call_seq and number, without personal data', function () {
    $counter = QueueCounter::firstOrCreate(['id' => 1], ['name' => 'Loket 1']);

    $ticket = QueueTicket::create([
        'code' => '023',
        'nim' => '1201210023',
        'name' => 'Rahasia Pribadi',
        'study_program' => 'Informatika',
        'service_category' => 'Kemahasiswaan',
        'notes' => 'Catatan sensitif mahasiswa',
        'device_token' => (string) Str::uuid(),
        'status' => 'called',
        'counter_id' => $counter->id,
        'queue_date' => now('Asia/Jakarta')->toDateString(),
    ]);

    QueueState::updateOrCreate(
        ['counter_id' => $counter->id],
        ['current_id' => $ticket->id, 'last_id' => null, 'call_seq' => 7]
    );

    $response = $this->get(route('queue.display.json'));

    $response->assertOk();
    $json = $response->json();

    expect($json)->toHaveKey('counters');
    $first = collect($json['counters'])->firstWhere('id', $counter->id);

    expect($first)->not->toBeNull();
    expect($first['current_number'])->toBe('023');
    expect($first['number'])->toBe(23);
    expect($first['call_seq'])->toBe(7);

    // Assert NO personal data leaked in json response string
    $rawContent = $response->getContent();
    expect($rawContent)->not->toContain('1201210023');
    expect($rawContent)->not->toContain('Rahasia Pribadi');
    expect($rawContent)->not->toContain('Informatika');
    expect($rawContent)->not->toContain('Catatan sensitif');
});
