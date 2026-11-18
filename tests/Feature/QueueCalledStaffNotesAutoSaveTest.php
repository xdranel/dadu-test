<?php

use App\Models\QueueCounter;
use App\Models\QueueState;
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

function createTestOfficer(string $role = 'Petugas'): User
{
    $user = User::factory()->create(['status' => 'active']);
    $user->assignRole($role);
    return $user;
}

test('1. Petugas dapat menyimpan draf pada tiket called -> 200, DB terisi', function () {
    $officer = createTestOfficer('Petugas');
    $counter = QueueCounter::create(['name' => 'Loket 1', 'is_active' => true]);

    $calledTicket = QueueTicket::factory()->create([
        'code' => 'A001',
        'status' => 'called',
        'counter_id' => $counter->id,
        'called_by' => $officer->id,
        'called_at' => now(),
        'queue_date' => now()->toDateString(),
    ]);

    $response = $this->actingAs($officer)
        ->patchJson(route('queue.tickets.staff-notes', $calledTicket), [
            'staff_notes' => 'Catatan sementara draf petugas',
            'client_seq' => 1,
        ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'staff_notes' => 'Catatan sementara draf petugas',
            'client_seq' => 1,
        ]);

    $calledTicket->refresh();
    expect($calledTicket->staff_notes)->toBe('Catatan sementara draf petugas')
        ->and($calledTicket->staff_notes_seq)->toBe(1)
        ->and($calledTicket->staff_notes_updated_at)->not->toBeNull();
});

test('2. Tiket waiting -> 422; guest -> 401/redirect; role tidak diizinkan -> 403', function () {
    $calledTicket = QueueTicket::factory()->create([
        'code' => 'A003',
        'status' => 'called',
        'queue_date' => now()->toDateString(),
    ]);

    // Guest ditolak 401
    $resGuest = $this->patchJson(route('queue.tickets.staff-notes', $calledTicket), [
        'staff_notes' => 'Catatan oleh tamu',
    ]);
    $resGuest->assertUnauthorized();

    // Petugas: Tiket waiting ditolak 422
    $officer = createTestOfficer('Petugas');
    $waitingTicket = QueueTicket::factory()->create([
        'code' => 'A002',
        'status' => 'waiting',
        'queue_date' => now()->toDateString(),
    ]);
    $resWaiting = $this->actingAs($officer)
        ->patchJson(route('queue.tickets.staff-notes', $waitingTicket), [
            'staff_notes' => 'Catatan tiket menunggu',
        ]);
    $resWaiting->assertStatus(422);

    // User tanpa role/permission antrian -> 403
    $unauthorizedUser = User::factory()->create(['status' => 'active']);
    $resForbidden = $this->actingAs($unauthorizedUser)
        ->patchJson(route('queue.tickets.staff-notes', $calledTicket), [
            'staff_notes' => 'Catatan ilegal',
        ]);
    $resForbidden->assertForbidden();
});

test('3. > 500 karakter -> 422; spasi saja -> NULL', function () {
    $officer = createTestOfficer('Petugas');
    $ticket = QueueTicket::factory()->create([
        'code' => 'A004',
        'status' => 'called',
        'staff_notes' => 'Catatan lama',
        'queue_date' => now()->toDateString(),
    ]);

    // 501 karakter -> 422
    $longNote = str_repeat('A', 501);
    $resLong = $this->actingAs($officer)
        ->patchJson(route('queue.tickets.staff-notes', $ticket), [
            'staff_notes' => $longNote,
        ]);
    $resLong->assertStatus(422);

    // Spasi saja -> NULL
    $resBlank = $this->actingAs($officer)
        ->patchJson(route('queue.tickets.staff-notes', $ticket), [
            'staff_notes' => '    ',
        ]);
    $resBlank->assertStatus(200)
        ->assertJson([
            'success' => true,
            'staff_notes' => null,
        ]);

    $ticket->refresh();
    expect($ticket->staff_notes)->toBeNull();
});

test('4. Request dengan seq lama tidak menimpa data yang lebih baru', function () {
    $officer = createTestOfficer('Petugas');
    $ticket = QueueTicket::factory()->create([
        'code' => 'A005',
        'status' => 'called',
        'staff_notes' => 'Versi 10 terbaru',
        'staff_notes_seq' => 10,
        'queue_date' => now()->toDateString(),
    ]);

    // Kirim seq 5 (lebih lama dari 10)
    $response = $this->actingAs($officer)
        ->patchJson(route('queue.tickets.staff-notes', $ticket), [
            'staff_notes' => 'Versi 5 stale terlambat',
            'client_seq' => 5,
        ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'ignored' => true,
            'staff_notes' => 'Versi 10 terbaru',
            'client_seq' => 10,
        ]);

    $ticket->refresh();
    expect($ticket->staff_notes)->toBe('Versi 10 terbaru')
        ->and($ticket->staff_notes_seq)->toBe(10);
});

test('5. Setelah tiket di-finish/skip lewat aksi yang ada, staff_notes tetap ada dan tampil di card Selesai Hari Ini serta halaman riwayat', function () {
    $officer = createTestOfficer('Petugas');
    $counter = QueueCounter::create(['name' => 'Loket 1', 'is_active' => true, 'active_user_id' => $officer->id]);

    $ticket = QueueTicket::factory()->create([
        'code' => 'A006',
        'status' => 'called',
        'counter_id' => $counter->id,
        'called_by' => $officer->id,
        'called_at' => now(),
        'staff_notes' => 'Catatan penting saat dipanggil',
        'queue_date' => now()->toDateString(),
    ]);

    QueueState::create([
        'counter_id' => $counter->id,
        'current_id' => $ticket->id,
    ]);

    // Petugas melewati tiket via skip
    $resSkip = $this->actingAs($officer)
        ->withSession(['selected_counter_id' => $counter->id])
        ->postJson(route('admin.queue.skip'));
    $resSkip->assertStatus(200);

    $ticket->refresh();
    expect($ticket->status)->toBe('skipped')
        ->and($ticket->staff_notes)->toBe('Catatan penting saat dipanggil');

    // Cek dashboard admin menampilkan card selesai dengan catatan
    $resDashboard = $this->actingAs($officer)
        ->withSession(['selected_counter_id' => $counter->id])
        ->get(route('admin.queue.index'));
    $resDashboard->assertStatus(200)
        ->assertSee('Catatan penting saat dipanggil');

    // Cek halaman riwayat
    $resHistory = $this->actingAs($officer)
        ->get(route('admin.queue.history'));
    $resHistory->assertStatus(200)
        ->assertSee('Catatan penting saat dipanggil');
});

test('6. Aksi finish/skip tanpa mengirim staff_notes tidak menghapus catatan', function () {
    $officer = createTestOfficer('Petugas');
    $counter = QueueCounter::create(['name' => 'Loket 2', 'is_active' => true, 'active_user_id' => $officer->id]);

    $ticket = QueueTicket::factory()->create([
        'code' => 'A007',
        'status' => 'called',
        'counter_id' => $counter->id,
        'called_by' => $officer->id,
        'called_at' => now(),
        'staff_notes' => 'Catatan jangan terhapus',
        'queue_date' => now()->toDateString(),
    ]);

    QueueState::create([
        'counter_id' => $counter->id,
        'current_id' => $ticket->id,
    ]);

    // Panggil next() tanpa parameter staff_notes
    $resNext = $this->actingAs($officer)
        ->withSession(['selected_counter_id' => $counter->id])
        ->post(route('admin.queue.next'));
    $resNext->assertRedirect();

    $ticket->refresh();
    expect($ticket->status)->toBe('finished')
        ->and($ticket->staff_notes)->toBe('Catatan jangan terhapus');
});

test('7. Catatan <script>alert(1)</script> ter-escape saat dirender', function () {
    $officer = createTestOfficer('Petugas');
    $counter = QueueCounter::create(['name' => 'Loket 3', 'is_active' => true]);

    $ticket = QueueTicket::factory()->create([
        'code' => 'A008',
        'status' => 'called',
        'counter_id' => $counter->id,
        'called_by' => $officer->id,
        'called_at' => now(),
        'staff_notes' => '<script>alert(1)</script>',
        'queue_date' => now()->toDateString(),
    ]);

    $resDashboard = $this->actingAs($officer)
        ->withSession(['selected_counter_id' => $counter->id])
        ->get(route('admin.queue.index'));

    $resDashboard->assertStatus(200)
        ->assertDontSee('<script>alert(1)</script>', false);

    $resHistory = $this->actingAs($officer)
        ->get(route('admin.queue.history'));

    $resHistory->assertStatus(200)
        ->assertDontSee('<script>alert(1)</script>', false);
});

test('8. CSV tetap benar (kolom Catatan Petugas terisi untuk tiket tersebut)', function () {
    $officer = createTestOfficer('Petugas');
    $counter = QueueCounter::create(['name' => 'Loket 1', 'is_active' => true]);

    QueueTicket::factory()->create([
        'code' => 'A009',
        'name' => 'Budi Santoso',
        'nim' => '1201210001',
        'study_program' => 'S1 Teknik Informatika',
        'service_category' => 'Surat Pengantar',
        'status' => 'finished',
        'counter_id' => $counter->id,
        'called_by' => $officer->id,
        'called_at' => '2026-10-12 10:00:00',
        'finished_at' => '2026-10-12 10:05:00',
        'staff_notes' => 'Mahasiswa sudah melengkapi berkas',
        'queue_date' => '2026-10-12',
    ]);

    $response = $this->actingAs($officer)
        ->get(route('admin.queue.history.export'));

    $response->assertStatus(200)
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $content = $response->streamedContent();

    if (str_starts_with($content, "\xEF\xBB\xBF")) {
        $content = substr($content, 3);
    }

    $stream = fopen('php://memory', 'r+');
    fwrite($stream, $content);
    rewind($stream);
    $rows = [];
    while (($data = fgetcsv($stream)) !== false) {
        $rows[] = $data;
    }
    fclose($stream);

    // Header harus memiliki 'Catatan Petugas'
    $header = $rows[0];
    expect($header)->toContain('Catatan Petugas');

    $notesIndex = array_search('Catatan Petugas', $header);
    expect($notesIndex)->not->toBeFalse();

    // Baris tiket A009
    $ticketRow = collect($rows)->firstWhere(1, 'A009');
    expect($ticketRow)->not->toBeNull()
        ->and($ticketRow[$notesIndex])->toBe('Mahasiswa sudah melengkapi berkas');
});

test('9. Card Sedang Dipanggil dan Selesai memiliki dropdown Catatan Petugas yang konsisten', function () {
    $officer = createTestOfficer('Petugas');
    $counter = QueueCounter::create(['name' => 'Loket 1', 'is_active' => true]);

    $calledTicket = QueueTicket::factory()->create([
        'code' => 'A010',
        'status' => 'called',
        'counter_id' => $counter->id,
        'called_by' => $officer->id,
        'called_at' => now(),
        'staff_notes' => 'Catatan tiket dipanggil',
        'queue_date' => now()->toDateString(),
    ]);

    $finishedTicket = QueueTicket::factory()->create([
        'code' => 'A011',
        'status' => 'finished',
        'counter_id' => $counter->id,
        'called_by' => $officer->id,
        'called_at' => now()->subMinutes(10),
        'finished_at' => now()->subMinutes(2),
        'staff_notes' => 'Catatan tiket selesai',
        'queue_date' => now()->toDateString(),
    ]);

    $response = $this->actingAs($officer)
        ->withSession(['selected_counter_id' => $counter->id])
        ->get(route('admin.queue.index'));

    $response->assertStatus(200);
    $html = $response->getContent();

    // Verify called card structure
    expect($html)->toContain('data-called-ticket-id="' . $calledTicket->id . '"')
        ->and($html)->toContain('id="called-ticket-' . $calledTicket->id . '"')
        ->and($html)->toContain('calledStaffNotes(' . $calledTicket->id);

    // Verify finished card structure
    expect($html)->toContain('data-finished-ticket-id="' . $finishedTicket->id . '"')
        ->and($html)->toContain('id="finished-ticket-' . $finishedTicket->id . '"')
        ->and($html)->toContain('staffNotesCard(');

    // Both have "Catatan Petugas" header and "Simpan"
    expect(substr_count($html, 'Catatan Petugas'))->toBeGreaterThanOrEqual(2)
        ->and(substr_count($html, 'Simpan'))->toBeGreaterThanOrEqual(2);
});

test('10. Seluruh kartu Selesai berada di dalam #list-finished tanpa elemen liar di luar wrapper scroll', function () {
    $officer = createTestOfficer('Petugas');
    $counter = QueueCounter::create(['name' => 'Loket 1', 'is_active' => true]);

    // Buat 5 tiket selesai
    $finishedTickets = [];
    for ($i = 1; $i <= 5; $i++) {
        $finishedTickets[] = QueueTicket::factory()->create([
            'code' => sprintf('A%03d', $i),
            'status' => 'finished',
            'counter_id' => $counter->id,
            'called_by' => $officer->id,
            'called_at' => now()->subMinutes(30 - $i),
            'finished_at' => now()->subMinutes(25 - $i),
            'staff_notes' => 'Catatan tiket ' . $i,
            'queue_date' => now()->toDateString(),
        ]);
    }

    $response = $this->actingAs($officer)
        ->withSession(['selected_counter_id' => $counter->id])
        ->get(route('admin.queue.index'));

    $response->assertStatus(200);
    $html = $response->getContent();

    // Pastikan #list-finished berisi semua 5 tiket
    $crawler = new \DOMDocument();
    libxml_use_internal_errors(true);
    $crawler->loadHTML($html);
    libxml_clear_errors();

    $xpath = new \DOMXPath($crawler);
    $listFinishedNodes = $xpath->query('//*[@id="list-finished"]');
    expect($listFinishedNodes->length)->toBe(1);

    $listFinished = $listFinishedNodes->item(0);

    // Pastikan seluruh tiket selesai yang dirender dari Blade (dengan ID numerik) berada di dalam #list-finished
    $cardsInside = $xpath->query('.//*[@data-finished-ticket-id]', $listFinished);
    $allCards = $xpath->query('//*[@data-finished-ticket-id]');

    $outsideIds = [];
    $renderedBladeCount = 0;
    foreach ($allCards as $node) {
        $ticketId = $node->getAttribute('data-finished-ticket-id');
        // Abaikan string template JS di dalam <script>
        if (!is_numeric($ticketId)) {
            continue;
        }
        $renderedBladeCount++;
        $parent = $node->parentNode;
        $inList = false;
        while ($parent) {
            if ($parent->nodeType === XML_ELEMENT_NODE && $parent->getAttribute('id') === 'list-finished') {
                $inList = true;
                break;
            }
            $parent = $parent->parentNode;
        }
        if (!$inList) {
            $outsideIds[] = [
                'id' => $ticketId,
                'parent' => $node->parentNode ? $node->parentNode->nodeName . ' class=' . $node->parentNode->getAttribute('class') : 'null'
            ];
        }
    }
    expect($outsideIds)->toBeEmpty();
    expect($cardsInside->length)->toBe($renderedBladeCount);
    expect($renderedBladeCount)->toBeGreaterThanOrEqual(5);
});
