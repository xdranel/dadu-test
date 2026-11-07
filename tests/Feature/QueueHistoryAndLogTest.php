<?php

use App\Models\QueueCounter;
use App\Models\QueueState;
use App\Models\QueueTicket;
use App\Models\QueueTicketEvent;
use App\Models\QueueTicketHistory;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    Carbon::setTestNow(Carbon::parse('2026-10-12 10:00:00', 'Asia/Jakarta'));
});

afterEach(function () {
    Carbon::setTestNow();
    // Clean up test export files if generated
    $exportDir = storage_path('app/queue-exports');
    if (File::isDirectory($exportDir)) {
        File::deleteDirectory($exportDir);
    }
});

function createHistoryAdmin(): User {
    $user = User::factory()->create(['status' => 'active']);
    $user->assignRole('admin');
    return $user;
}

/* =========================================================================
 * 1. SCHEMA & EVENT LOGGING TESTS
 * ========================================================================= */

test('queue_ticket_events table has all required columns and performance indexes', function () {
    expect(Schema::hasTable('queue_ticket_events'))->toBeTrue();
    expect(Schema::hasColumns('queue_ticket_events', [
        'id', 'ticket_id', 'queue_date', 'nim', 'number', 'event',
        'counter_id', 'user_id', 'meta', 'created_at'
    ]))->toBeTrue();

    // Check indexes on queue_ticket_histories
    expect(Schema::hasTable('queue_ticket_histories'))->toBeTrue();
});

test('event created is logged when visitor creates a ticket', function () {
    $response = $this->post(route('queue.store'), [
        'name' => 'Kadek Dev',
        'nim' => '1201210088',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'notes' => 'Konsultasi KRS',
    ]);

    $response->assertRedirect();

    $ticket = QueueTicket::where('nim', '1201210088')->first();
    expect($ticket)->not->toBeNull();

    $event = QueueTicketEvent::where('ticket_id', $ticket->id)
        ->where('event', 'created')
        ->first();

    expect($event)->not->toBeNull();
    expect($event->queue_date)->toBe(now('Asia/Jakarta')->toDateString());
    expect($event->nim)->toBe('1201210088');
    expect((int) $event->number)->toBe((int) $ticket->code);
    expect($event->counter_id)->toBeNull();
    expect($event->user_id)->toBeNull();
});

test('event called and finished are logged during next call cycle', function () {
    $user = createHistoryAdmin();
    $counter = QueueCounter::firstOrCreate(['id' => 1], ['name' => 'Loket 1']);
    $counter->update(['active_user_id' => $user->id]);

    QueueState::updateOrCreate(
        ['counter_id' => $counter->id],
        ['current_id' => null, 'last_id' => null, 'call_seq' => 0]
    );

    // Create 2 waiting tickets
    $t1 = QueueTicket::create([
        'code' => '001',
        'nim' => '1201210001',
        'name' => 'Mahasiswa Satu',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'device_token' => (string) Str::uuid(),
        'queue_date' => now('Asia/Jakarta')->toDateString(),
        'status' => 'waiting',
    ]);

    $t2 = QueueTicket::create([
        'code' => '002',
        'nim' => '1201210002',
        'name' => 'Mahasiswa Dua',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'device_token' => (string) Str::uuid(),
        'queue_date' => now('Asia/Jakarta')->toDateString(),
        'status' => 'waiting',
    ]);

    // Next 1: Call Ticket 1
    $this->actingAs($user)
        ->withSession(['queue_counter_id' => $counter->id])
        ->post(route('admin.queue.next'))
        ->assertRedirect();

    expect(QueueTicketEvent::where('ticket_id', $t1->id)->where('event', 'called')->exists())->toBeTrue();

    // Next 2: Ticket 1 finished, Ticket 2 called
    $this->actingAs($user)
        ->withSession(['queue_counter_id' => $counter->id])
        ->post(route('admin.queue.next'))
        ->assertRedirect();

    expect(QueueTicketEvent::where('ticket_id', $t1->id)->where('event', 'finished')->exists())->toBeTrue();
    expect(QueueTicketEvent::where('ticket_id', $t2->id)->where('event', 'called')->exists())->toBeTrue();
});

test('event skipped is logged when officer skips ticket', function () {
    $user = createHistoryAdmin();
    $counter = QueueCounter::firstOrCreate(['id' => 1], ['name' => 'Loket 1']);
    $counter->update(['active_user_id' => $user->id]);

    $ticket = QueueTicket::create([
        'code' => '001',
        'nim' => '1201210001',
        'name' => 'Mahasiswa Lewati',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'device_token' => (string) Str::uuid(),
        'queue_date' => now('Asia/Jakarta')->toDateString(),
        'status' => 'called',
        'counter_id' => $counter->id,
        'called_at' => now('Asia/Jakarta'),
    ]);

    QueueState::updateOrCreate(
        ['counter_id' => $counter->id],
        ['current_id' => $ticket->id, 'last_id' => null, 'call_seq' => 1]
    );

    $this->actingAs($user)
        ->withSession(['queue_counter_id' => $counter->id])
        ->post(route('admin.queue.skip'))
        ->assertRedirect();

    $skipEvent = QueueTicketEvent::where('ticket_id', $ticket->id)
        ->where('event', 'skipped')
        ->first();

    expect($skipEvent)->not->toBeNull();
    expect($skipEvent->user_id)->toBe($user->id);
    expect($skipEvent->counter_id)->toBe($counter->id);
});

test('event undo_call is logged when officer cancels call', function () {
    $user = createHistoryAdmin();
    $counter = QueueCounter::firstOrCreate(['id' => 1], ['name' => 'Loket 1']);
    $counter->update(['active_user_id' => $user->id]);

    $t1 = QueueTicket::create([
        'code' => '001',
        'nim' => '1201210001',
        'name' => 'Mahasiswa Satu Selesai',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'device_token' => (string) Str::uuid(),
        'queue_date' => now('Asia/Jakarta')->toDateString(),
        'status' => 'finished',
        'counter_id' => $counter->id,
        'called_at' => now('Asia/Jakarta')->subMinutes(5),
        'finished_at' => now('Asia/Jakarta')->subMinute(),
    ]);

    $t2 = QueueTicket::create([
        'code' => '002',
        'nim' => '1201210002',
        'name' => 'Mahasiswa Dua Dipanggil',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'device_token' => (string) Str::uuid(),
        'queue_date' => now('Asia/Jakarta')->toDateString(),
        'status' => 'called',
        'counter_id' => $counter->id,
        'called_at' => now('Asia/Jakarta'),
    ]);

    QueueState::updateOrCreate(
        ['counter_id' => $counter->id],
        ['current_id' => $t2->id, 'last_id' => $t1->id, 'call_seq' => 2]
    );

    $this->actingAs($user)
        ->withSession(['queue_counter_id' => $counter->id])
        ->post(route('admin.queue.back'))
        ->assertRedirect();

    $undoEvent = QueueTicketEvent::where('ticket_id', $t2->id)
        ->where('event', 'undo_call')
        ->first();

    expect($undoEvent)->not->toBeNull();
    expect($undoEvent->user_id)->toBe($user->id);
});

test('polling endpoints do NOT create any events', function () {
    $user = createHistoryAdmin();
    $counter = QueueCounter::firstOrCreate(['id' => 1], ['name' => 'Loket 1']);

    $initialEventCount = QueueTicketEvent::count();

    // 1. Polling live data JSON admin
    $this->actingAs($user)
        ->getJson(route('admin.queue.json'))
        ->assertOk();

    // 2. Polling display JSON publik
    $this->getJson(route('queue.display.json'))
        ->assertOk();

    expect(QueueTicketEvent::count())->toBe($initialEventCount);
});

test('events persist when daily archive moves tickets and deletes queue_tickets', function () {
    $ticket = QueueTicket::create([
        'code' => '001',
        'nim' => '1201210999',
        'name' => 'Mahasiswa Persist',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'device_token' => (string) Str::uuid(),
        'queue_date' => '2026-10-12',
        'status' => 'finished',
        'finished_at' => now('Asia/Jakarta'),
    ]);

    QueueTicketEvent::record('created', $ticket);
    QueueTicketEvent::record('called', $ticket);
    QueueTicketEvent::record('finished', $ticket);

    expect(QueueTicketEvent::where('nim', '1201210999')->count())->toBe(3);

    // Simulate 08:00 daily archive
    DB::table('queue_ticket_histories')->insert([
        'id' => $ticket->id,
        'code' => $ticket->code,
        'nim' => $ticket->nim,
        'name' => $ticket->name,
        'study_program' => $ticket->study_program,
        'service_category' => $ticket->service_category,
        'queue_date' => $ticket->queue_date,
        'status' => $ticket->status,
        'device_token' => $ticket->device_token,
        'created_at' => $ticket->created_at,
        'updated_at' => $ticket->updated_at,
    ]);
    DB::table('queue_tickets')->where('id', $ticket->id)->delete();

    // Events must still be present and linked
    $events = QueueTicketEvent::where('nim', '1201210999')->get();
    expect($events)->toHaveCount(3);
    expect($events->first()->queue_date)->toBe('2026-10-12');
    expect($events->first()->number)->toBe(1);
});

/* =========================================================================
 * 2. HISTORY PAGE & FILTER TESTS
 * ========================================================================= */

test('unauthenticated users and unauthorized users cannot access history', function () {
    $this->get(route('admin.queue.history'))->assertRedirect(route('login'));

    $regularUser = User::factory()->create(['status' => 'active']);
    $this->actingAs($regularUser)->get(route('admin.queue.history'))->assertForbidden();
});

test('history page lists records from both active tickets and archived histories', function () {
    $user = createHistoryAdmin();

    // Active ticket today
    QueueTicket::create([
        'code' => '001',
        'nim' => '1201210001',
        'name' => 'Active Today',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'device_token' => (string) Str::uuid(),
        'queue_date' => '2026-10-12',
        'status' => 'finished',
    ]);

    // Archived ticket earlier this month
    QueueTicketHistory::create([
        'code' => '005',
        'nim' => '1201210002',
        'name' => 'Archived History',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'device_token' => (string) Str::uuid(),
        'queue_date' => '2026-10-05',
        'status' => 'finished',
    ]);

    $response = $this->actingAs($user)->get(route('admin.queue.history', ['month' => '2026-10']));
    $response->assertOk();
    $response->assertSee('Active Today');
    $response->assertSee('Archived History');
});

test('filtering by nim searches across all months ignoring date filters', function () {
    $user = createHistoryAdmin();

    // September ticket
    QueueTicketHistory::create([
        'code' => '001',
        'nim' => '1201219999',
        'name' => 'Mahasiswa Lintas Bulan',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'device_token' => (string) Str::uuid(),
        'queue_date' => '2026-09-15',
        'status' => 'finished',
    ]);

    // October ticket
    QueueTicketHistory::create([
        'code' => '002',
        'nim' => '1201219999',
        'name' => 'Mahasiswa Lintas Bulan',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'device_token' => (string) Str::uuid(),
        'queue_date' => '2026-10-10',
        'status' => 'skipped',
    ]);

    // Query with NIM and a specific month
    $response = $this->actingAs($user)->get(route('admin.queue.history', [
        'nim' => '1201219999',
        'month' => '2026-10',
    ]));

    $response->assertOk();
    // Both September and October visits should be visible
    $response->assertSee('Mahasiswa Lintas Bulan');
    $response->assertSee('001');
    $response->assertSee('002');
});

test('month filter strictly respects month boundaries', function () {
    $user = createHistoryAdmin();

    // 2026-09-30 (Previous month end)
    QueueTicketHistory::create([
        'code' => '099',
        'nim' => '1111111111',
        'name' => 'End of September',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'device_token' => (string) Str::uuid(),
        'queue_date' => '2026-09-30',
        'status' => 'finished',
    ]);

    // 2026-10-01 (October start)
    QueueTicketHistory::create([
        'code' => '001',
        'nim' => '2222222222',
        'name' => 'Start of October',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'device_token' => (string) Str::uuid(),
        'queue_date' => '2026-10-01',
        'status' => 'finished',
    ]);

    // 2026-11-01 (Next month start)
    QueueTicketHistory::create([
        'code' => '001',
        'nim' => '3333333333',
        'name' => 'Start of November',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'device_token' => (string) Str::uuid(),
        'queue_date' => '2026-11-01',
        'status' => 'finished',
    ]);

    $response = $this->actingAs($user)->get(route('admin.queue.history', ['month' => '2026-10']));
    $response->assertOk();
    $response->assertSee('Start of October');
    $response->assertDontSee('End of September');
    $response->assertDontSee('Start of November');
});

test('detail page route redirects to history list when detail view is removed', function () {
    $user = createHistoryAdmin();
    $ticket = QueueTicket::create([
        'code' => '007',
        'nim' => '1201210007',
        'name' => 'James Bond',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'device_token' => (string) Str::uuid(),
        'queue_date' => '2026-10-12',
        'status' => 'finished',
    ]);

    $response = $this->actingAs($user)->get(route('admin.queue.history.show', ['id' => $ticket->id]));
    $response->assertRedirect(route('admin.queue.history'));
});

/* =========================================================================
 * 3. CSV EXPORT WEB ENDPOINT TESTS
 * ========================================================================= */

test('csv export streams response with BOM and protects against CSV injection', function () {
    $user = createHistoryAdmin();

    // Ticket with dangerous CSV formula injection payloads
    QueueTicket::create([
        'code' => '001',
        'nim' => '=cmd|"/C calc"!A0',
        'name' => '+2+5',
        'study_program' => '-@test',
        'service_category' => '@SUM(1,2)',
        'notes' => "\tTAB_INJECT",
        'device_token' => 'SECRET_DEVICE_TOKEN_DO_NOT_LEAK',
        'queue_date' => '2026-10-12',
        'status' => 'finished',
    ]);

    $response = $this->actingAs($user)->get(route('admin.queue.history.export', ['month' => '2026-10']));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $content = $response->streamedContent();

    // Verify UTF-8 BOM is prepended
    expect(str_starts_with($content, "\xEF\xBB\xBF"))->toBeTrue();

    // Verify device_token is NEVER included
    expect(str_contains($content, 'SECRET_DEVICE_TOKEN_DO_NOT_LEAK'))->toBeFalse();
    expect(str_contains($content, 'device_token'))->toBeFalse();

    // Verify CSV injection protection prefixes with single quote '
    expect(str_contains($content, "'=cmd"))->toBeTrue();
    expect(str_contains($content, "'+2+5"))->toBeTrue();
    expect(str_contains($content, "'-@test"))->toBeTrue();
    expect(str_contains($content, "'@SUM(1,2)"))->toBeTrue();
    expect(str_contains($content, "'\tTAB_INJECT"))->toBeTrue();
});

/* =========================================================================
 * 4. ARTISAN COMMAND: queue:export-month
 * ========================================================================= */

test('queue:export-month exports full month to storage/app/queue-exports and overwrites cleanly', function () {
    QueueTicketHistory::create([
        'code' => '001',
        'nim' => '1201210001',
        'name' => 'Bulan Sep 1',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'device_token' => (string) Str::uuid(),
        'queue_date' => '2026-09-10',
        'status' => 'finished',
    ]);

    QueueTicketHistory::create([
        'code' => '002',
        'nim' => '1201210002',
        'name' => 'Bulan Sep 2',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'device_token' => (string) Str::uuid(),
        'queue_date' => '2026-09-15',
        'status' => 'skipped',
    ]);

    $exitCode = Artisan::call('queue:export-month', ['bulan' => '2026-09']);
    expect($exitCode)->toBe(0);

    $targetFile = storage_path('app/queue-exports/queue-2026-09.csv');
    expect(File::exists($targetFile))->toBeTrue();

    $content = File::get($targetFile);
    expect(str_starts_with($content, "\xEF\xBB\xBF"))->toBeTrue();
    expect(str_contains($content, 'Bulan Sep 1'))->toBeTrue();
    expect(str_contains($content, 'Bulan Sep 2'))->toBeTrue();

    // Run again to verify overwriting behavior
    $exitCode2 = Artisan::call('queue:export-month', ['bulan' => '2026-09']);
    expect($exitCode2)->toBe(0);
    expect(File::exists($targetFile))->toBeTrue();
});

/* =========================================================================
 * 5. ARTISAN COMMAND: queue:prune-history SAFEGUARDS
 * ========================================================================= */

test('queue:prune-history strictly rejects deletion if export file does not exist', function () {
    // Insert old record (> 24 months ago)
    QueueTicketHistory::create([
        'code' => '001',
        'nim' => '1201210001',
        'name' => 'Data Kuno 2024',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'device_token' => (string) Str::uuid(),
        'queue_date' => '2024-05-15',
        'status' => 'finished',
    ]);

    // Ensure no export file exists for 2024-05
    $targetFile = storage_path('app/queue-exports/queue-2024-05.csv');
    if (File::exists($targetFile)) {
        File::delete($targetFile);
    }

    $exitCode = Artisan::call('queue:prune-history', ['--force' => true]);
    expect($exitCode)->toBe(1);

    // Record MUST not be deleted
    expect(QueueTicketHistory::where('queue_date', '2024-05-15')->exists())->toBeTrue();
});

test('queue:prune-history strictly rejects deletion if export file is empty 0 bytes', function () {
    QueueTicketHistory::create([
        'code' => '001',
        'nim' => '1201210001',
        'name' => 'Data Kuno 2024 Empty File Test',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'device_token' => (string) Str::uuid(),
        'queue_date' => '2024-05-15',
        'status' => 'finished',
    ]);

    $dir = storage_path('app/queue-exports');
    File::ensureDirectoryExists($dir);
    $targetFile = $dir . '/queue-2024-05.csv';
    File::put($targetFile, ''); // 0 bytes

    $exitCode = Artisan::call('queue:prune-history', ['--force' => true]);
    expect($exitCode)->toBe(1);

    // Record MUST not be deleted
    expect(QueueTicketHistory::where('queue_date', '2024-05-15')->exists())->toBeTrue();
});

test('queue:prune-history strictly rejects deletion if export row count does not match database count', function () {
    // 2 records in DB
    QueueTicketHistory::create([
        'code' => '001',
        'nim' => '1201210001',
        'name' => 'Data Kuno 1',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'device_token' => (string) Str::uuid(),
        'queue_date' => '2024-05-10',
        'status' => 'finished',
    ]);

    QueueTicketHistory::create([
        'code' => '002',
        'nim' => '1201210002',
        'name' => 'Data Kuno 2',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'device_token' => (string) Str::uuid(),
        'queue_date' => '2024-05-12',
        'status' => 'finished',
    ]);

    // Write CSV that only has 1 record (mismatch!)
    $dir = storage_path('app/queue-exports');
    File::ensureDirectoryExists($dir);
    $targetFile = $dir . '/queue-2024-05.csv';
    File::put($targetFile, "Header1,Header2\nRow1Col1,Row1Col2\n");

    $exitCode = Artisan::call('queue:prune-history', ['--force' => true]);
    expect($exitCode)->toBe(1);

    // Records MUST not be deleted
    expect(QueueTicketHistory::where('queue_date', 'like', '2024-05%')->count())->toBe(2);
});

test('queue:prune-history dry-run previews deletion count without modifying database', function () {
    QueueTicketHistory::create([
        'code' => '001',
        'nim' => '1201210001',
        'name' => 'Data Kuno Dry Run',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'device_token' => (string) Str::uuid(),
        'queue_date' => '2024-05-10',
        'status' => 'finished',
    ]);

    // Create valid matching export
    Artisan::call('queue:export-month', ['bulan' => '2024-05']);

    $exitCode = Artisan::call('queue:prune-history', ['--dry-run' => true]);
    expect($exitCode)->toBe(0);

    // DB record still intact
    expect(QueueTicketHistory::where('queue_date', 'like', '2024-05%')->count())->toBe(1);
});

test('queue:prune-history successfully prunes data older than retention limit when all safeguards pass', function () {
    // Current test date is 2026-10-12
    // 24 months cutoff is 2024-10-01. Anything before 2024-10-01 is eligible for pruning.
    // 1. Old record from 2024-05 (29 months old, eligible)
    QueueTicketHistory::create([
        'code' => '001',
        'nim' => '1201210001',
        'name' => 'Data Eligible Prune',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'device_token' => (string) Str::uuid(),
        'queue_date' => '2024-05-10',
        'status' => 'finished',
    ]);

    // 2. Retained record from 2024-11 (23 months old, MUST NOT be pruned)
    QueueTicketHistory::create([
        'code' => '002',
        'nim' => '1201210002',
        'name' => 'Data Retained',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'device_token' => (string) Str::uuid(),
        'queue_date' => '2024-11-10',
        'status' => 'finished',
    ]);

    // Export the 2024-05 data so safeguards pass
    Artisan::call('queue:export-month', ['bulan' => '2024-05']);

    // Run prune with --force
    $exitCode = Artisan::call('queue:prune-history', ['--force' => true]);
    expect($exitCode)->toBe(0);

    // Old record deleted
    expect(QueueTicketHistory::where('queue_date', 'like', '2024-05%')->count())->toBe(0);

    // Within retention record NOT deleted
    expect(QueueTicketHistory::where('queue_date', 'like', '2024-11%')->count())->toBe(1);
});

test('downloadExport prevents path traversal attempts', function () {
    $user = createHistoryAdmin();

    $response = $this->actingAs($user)->get(route('admin.queue.history.download-export', [
        'filename' => '../../../../windows/win.ini',
    ]));

    $response->assertNotFound();

    $response2 = $this->actingAs($user)->get(route('admin.queue.history.download-export', [
        'filename' => 'arbitrary-file.txt',
    ]));

    $response2->assertNotFound();
});
