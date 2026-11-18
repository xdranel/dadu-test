<?php

use App\Http\Controllers\Admin\QueueHistoryController;
use App\Models\QueueCounter;
use App\Models\QueueTicket;
use App\Models\QueueTicketHistory;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    Carbon::setTestNow(Carbon::parse('2026-10-12 10:00:00', 'Asia/Jakarta'));
});

afterEach(function () {
    Carbon::setTestNow();
});

function createStaffUser(string $role = 'Petugas'): User
{
    $user = User::factory()->create(['status' => 'active']);
    $user->assignRole($role);
    return $user;
}

function parseCsvContent(string $content): array
{
    // Hapus UTF-8 BOM jika ada
    if (str_starts_with($content, "\xEF\xBB\xBF")) {
        $content = substr($content, 3);
    }
    $lines = explode("\n", trim($content));
    $rows = [];
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, $content);
    rewind($stream);
    while (($data = fgetcsv($stream)) !== false) {
        $rows[] = $data;
    }
    fclose($stream);
    return $rows;
}

/* =========================================================================
 * A. FEATURE TEST: SIMPAN CATATAN PETUGAS
 * ========================================================================= */

test('A1: Petugas login bisa menyimpan catatan pada tiket finished', function () {
    $officer = createStaffUser('Petugas');
    $ticket = QueueTicket::factory()->create([
        'status' => 'finished',
        'called_at' => now()->subMinutes(10),
        'finished_at' => now(),
    ]);

    $response = $this->actingAs($officer)
        ->patchJson(route('queue.tickets.staff-notes', $ticket), [
            'staff_notes' => 'Mahasiswa sudah melengkapi berkas ijazah.',
        ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'staff_notes' => 'Mahasiswa sudah melengkapi berkas ijazah.',
        ]);

    $this->assertDatabaseHas('queue_tickets', [
        'id' => $ticket->id,
        'staff_notes' => 'Mahasiswa sudah melengkapi berkas ijazah.',
    ]);
});

test('A2: Guest dilarang (401/redirect) dan user tanpa role/permission diizinkan mendapat 403', function () {
    $ticket = QueueTicket::factory()->create(['status' => 'finished']);

    // Guest JSON request
    $guestResponse = $this->patchJson(route('queue.tickets.staff-notes', $ticket), [
        'staff_notes' => 'Catatan tamu',
    ]);
    $guestResponse->assertUnauthorized();

    // User dengan role mahasiswa (tanpa permission queue_ticket_update)
    $student = createStaffUser('mahasiswa');
    $studentResponse = $this->actingAs($student)
        ->patchJson(route('queue.tickets.staff-notes', $ticket), [
            'staff_notes' => 'Catatan mahasiswa',
        ]);
    $studentResponse->assertForbidden();
});

test('A3: Validasi: catatan > 500 karakter atau bukan string ditolak dengan 422', function () {
    $officer = createStaffUser('Petugas');
    $ticket = QueueTicket::factory()->create(['status' => 'finished']);

    // > 500 karakter (501 karakter)
    $responseTooLong = $this->actingAs($officer)
        ->patchJson(route('queue.tickets.staff-notes', $ticket), [
            'staff_notes' => str_repeat('a', 501),
        ]);
    $responseTooLong->assertStatus(422)
        ->assertJsonValidationErrors(['staff_notes']);

    // Tipe bukan string (array)
    $responseNotString = $this->actingAs($officer)
        ->patchJson(route('queue.tickets.staff-notes', $ticket), [
            'staff_notes' => ['nested' => 'array'],
        ]);
    $responseNotString->assertStatus(422)
        ->assertJsonValidationErrors(['staff_notes']);
});

test('A4: String kosong atau spasi saja tersimpan sebagai NULL (catatan terhapus)', function () {
    $officer = createStaffUser('Petugas');
    $ticket = QueueTicket::factory()->create([
        'status' => 'finished',
        'staff_notes' => 'Catatan awal',
    ]);

    $response = $this->actingAs($officer)
        ->patchJson(route('queue.tickets.staff-notes', $ticket), [
            'staff_notes' => '     ',
        ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'staff_notes' => null,
        ]);

    $ticket->refresh();
    expect($ticket->staff_notes)->toBeNull();
});

test('A5: Tiket berstatus waiting ditolak dengan 422 sedangkan called diizinkan dengan 200', function () {
    $officer = createStaffUser('Petugas');

    $waitingTicket = QueueTicket::factory()->create(['status' => 'waiting']);
    $calledTicket = QueueTicket::factory()->create(['status' => 'called']);

    $resWaiting = $this->actingAs($officer)
        ->patchJson(route('queue.tickets.staff-notes', $waitingTicket), [
            'staff_notes' => 'Catatan tiket menunggu',
        ]);
    $resWaiting->assertStatus(422);

    $resCalled = $this->actingAs($officer)
        ->patchJson(route('queue.tickets.staff-notes', $calledTicket), [
            'staff_notes' => 'Catatan tiket dipanggil',
        ]);
    $resCalled->assertStatus(200)
        ->assertJson([
            'success' => true,
            'staff_notes' => 'Catatan tiket dipanggil',
        ]);
    expect($calledTicket->fresh()->staff_notes)->toBe('Catatan tiket dipanggil');
});

test('A6: Tiket berstatus skipped boleh diberi catatan', function () {
    $officer = createStaffUser('Petugas');
    $ticket = QueueTicket::factory()->create([
        'status' => 'skipped',
        'skipped_at' => now(),
    ]);

    $response = $this->actingAs($officer)
        ->patchJson(route('queue.tickets.staff-notes', $ticket), [
            'staff_notes' => 'Mahasiswa tidak hadir setelah dipanggil 3 kali.',
        ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'staff_notes' => 'Mahasiswa tidak hadir setelah dipanggil 3 kali.',
        ]);

    $this->assertDatabaseHas('queue_tickets', [
        'id' => $ticket->id,
        'staff_notes' => 'Mahasiswa tidak hadir setelah dipanggil 3 kali.',
    ]);
});

test('A7: Input berisi tag HTML/script tersimpan apa adanya tapi ter-escape saat dirender di halaman', function () {
    $officer = createStaffUser('admin');
    $payload = '<script>alert(1)</script>';

    $ticket = QueueTicket::factory()->create([
        'status' => 'finished',
        'name' => 'Budi Santoso',
        'queue_date' => now('Asia/Jakarta')->toDateString(),
        'staff_notes' => $payload,
    ]);

    // Verifikasi tersimpan apa adanya di DB
    $this->assertDatabaseHas('queue_tickets', [
        'id' => $ticket->id,
        'staff_notes' => $payload,
    ]);

    // Render halaman dashboard admin
    $pageResponse = $this->actingAs($officer)->get(route('admin.queue.index'));
    $pageResponse->assertOk();

    // Halaman tidak boleh menampilkan tag mentah yang tidak ter-escape
    $pageResponse->assertDontSee('<script>alert(1)</script>', false);
});

test('A8: Dashboard menampilkan catatan petugas pada card yang benar dan teks Catatan Petugas pada tiap card', function () {
    $officer = createStaffUser('admin');
    $today = now('Asia/Jakarta')->toDateString();

    $ticketWithNote = QueueTicket::factory()->create([
        'code' => 'A-010',
        'status' => 'finished',
        'name' => 'Mahasiswa Ada Catatan',
        'queue_date' => $today,
        'staff_notes' => 'Perlu follow up ke prodi',
    ]);

    $ticketWithoutNote = QueueTicket::factory()->create([
        'code' => 'A-011',
        'status' => 'finished',
        'name' => 'Mahasiswa Tanpa Catatan',
        'queue_date' => $today,
        'staff_notes' => null,
    ]);

    $response = $this->actingAs($officer)->get(route('admin.queue.index'));
    $response->assertOk();

    $response->assertSee('Perlu follow up ke prodi');
    $response->assertSee('Catatan Petugas');
    $response->assertSee('+ tambah');
});


/* =========================================================================
 * B. FEATURE & UNIT TEST: EXPORT CSV
 * ========================================================================= */

test('B1: Header CSV berisi 2 kolom baru di urutan yang benar (setelah Jam Dilewati)', function () {
    $officer = createStaffUser('admin');
    $response = $this->actingAs($officer)->get(route('admin.queue.history.export'));
    $response->assertOk();

    $content = $response->streamedContent();
    $rows = parseCsvContent($content);

    expect($rows)->not->toBeEmpty();
    $headers = $rows[0];

    expect($headers)->toHaveCount(17);
    expect($headers[14])->toBe('Jam Dilewati');
    expect($headers[15])->toBe('Catatan Petugas');
    expect($headers[16])->toBe('Durasi Pelayanan');
});

test('B2: Baris selesai dengan called 10:00:00 dan finished 10:05:23 menghasilkan durasi 00:05:23', function () {
    $officer = createStaffUser('admin');
    $today = '2026-10-12';

    QueueTicket::factory()->create([
        'code' => 'A-001',
        'status' => 'finished',
        'queue_date' => $today,
        'called_at' => Carbon::parse("{$today} 10:00:00", 'Asia/Jakarta'),
        'finished_at' => Carbon::parse("{$today} 10:05:23", 'Asia/Jakarta'),
    ]);

    $response = $this->actingAs($officer)->get(route('admin.queue.history.export', ['month' => '2026-10']));
    $rows = parseCsvContent($response->streamedContent());

    $dataRow = $rows[1];
    expect($dataRow[1])->toBe('A-001');
    expect($dataRow[16])->toBe('00:05:23');
});

test('B3: Baris skipped menghasilkan durasi - dan TIDAK masuk rata-rata', function () {
    $officer = createStaffUser('admin');
    $today = '2026-10-12';

    QueueTicket::factory()->create([
        'code' => 'A-002',
        'status' => 'skipped',
        'queue_date' => $today,
        'called_at' => Carbon::parse("{$today} 10:00:00", 'Asia/Jakarta'),
        'skipped_at' => Carbon::parse("{$today} 10:03:00", 'Asia/Jakarta'),
    ]);

    $response = $this->actingAs($officer)->get(route('admin.queue.history.export', ['month' => '2026-10']));
    $rows = parseCsvContent($response->streamedContent());

    $dataRow = $rows[1];
    expect($dataRow[1])->toBe('A-002');
    expect($dataRow[16])->toBe('-');

    // Rata-rata di akhir baris ringkasan juga harus '-' karena tidak ada tiket finished valid
    $summaryRow = end($rows);
    expect($summaryRow[0])->toBe('Rata-rata Durasi Pelayanan');
    expect($summaryRow[16])->toBe('-');
});

test('B4: Baris dengan called_at/finished_at null atau finished < called menghasilkan durasi -', function () {
    $officer = createStaffUser('admin');
    $today = '2026-10-12';

    // called_at null
    QueueTicket::factory()->create([
        'code' => 'A-003',
        'status' => 'finished',
        'queue_date' => $today,
        'called_at' => null,
        'finished_at' => Carbon::parse("{$today} 10:05:00", 'Asia/Jakarta'),
    ]);

    // finished_at null
    QueueTicket::factory()->create([
        'code' => 'A-004',
        'status' => 'finished',
        'queue_date' => $today,
        'called_at' => Carbon::parse("{$today} 10:00:00", 'Asia/Jakarta'),
        'finished_at' => null,
    ]);

    // finished_at < called_at (tidak valid)
    QueueTicket::factory()->create([
        'code' => 'A-005',
        'status' => 'finished',
        'queue_date' => $today,
        'called_at' => Carbon::parse("{$today} 10:10:00", 'Asia/Jakarta'),
        'finished_at' => Carbon::parse("{$today} 10:05:00", 'Asia/Jakarta'),
    ]);

    $response = $this->actingAs($officer)->get(route('admin.queue.history.export', ['month' => '2026-10']));
    $rows = parseCsvContent($response->streamedContent());

    // Baris 1: A-003
    expect($rows[1][16])->toBe('-');
    // Baris 2: A-004
    expect($rows[2][16])->toBe('-');
    // Baris 3: A-005
    expect($rows[3][16])->toBe('-');

    $summaryRow = end($rows);
    expect($summaryRow[16])->toBe('-');
});

test('B5: Rata-rata: 3 tiket valid (00:05:00, 00:10:00, 00:03:00) + 1 skipped + 1 null -> 00:06:00 dan pembulatan akurat', function () {
    $officer = createStaffUser('admin');
    $today = '2026-10-12';

    // 1. 5 menit = 300 detik
    QueueTicket::factory()->create([
        'code' => 'A-010',
        'status' => 'finished',
        'queue_date' => $today,
        'called_at' => Carbon::parse("{$today} 08:00:00", 'Asia/Jakarta'),
        'finished_at' => Carbon::parse("{$today} 08:05:00", 'Asia/Jakarta'),
    ]);

    // 2. 10 menit = 600 detik
    QueueTicket::factory()->create([
        'code' => 'A-011',
        'status' => 'finished',
        'queue_date' => $today,
        'called_at' => Carbon::parse("{$today} 08:10:00", 'Asia/Jakarta'),
        'finished_at' => Carbon::parse("{$today} 08:20:00", 'Asia/Jakarta'),
    ]);

    // 3. 3 menit = 180 detik
    QueueTicket::factory()->create([
        'code' => 'A-012',
        'status' => 'finished',
        'queue_date' => $today,
        'called_at' => Carbon::parse("{$today} 08:30:00", 'Asia/Jakarta'),
        'finished_at' => Carbon::parse("{$today} 08:33:00", 'Asia/Jakarta'),
    ]);

    // 4. Skipped (diabaikan dari rata-rata)
    QueueTicket::factory()->create([
        'code' => 'A-013',
        'status' => 'skipped',
        'queue_date' => $today,
        'called_at' => Carbon::parse("{$today} 08:40:00", 'Asia/Jakarta'),
        'skipped_at' => Carbon::parse("{$today} 08:45:00", 'Asia/Jakarta'),
    ]);

    // 5. Null finished_at (diabaikan dari rata-rata)
    QueueTicket::factory()->create([
        'code' => 'A-014',
        'status' => 'finished',
        'queue_date' => $today,
        'called_at' => Carbon::parse("{$today} 08:50:00", 'Asia/Jakarta'),
        'finished_at' => null,
    ]);

    // Total detik valid: 300 + 600 + 180 = 1080 detik. Jumlah valid = 3. Rata-rata = 360 detik = 00:06:00
    $response = $this->actingAs($officer)->get(route('admin.queue.history.export', ['month' => '2026-10']));
    $rows = parseCsvContent($response->streamedContent());

    $summaryRow = end($rows);
    expect($summaryRow[0])->toBe('Rata-rata Durasi Pelayanan');
    expect($summaryRow[16])->toBe('00:06:00');
});

test('B6: Tanpa data valid -> baris ringkasan menunjukkan -', function () {
    $officer = createStaffUser('admin');

    $response = $this->actingAs($officer)->get(route('admin.queue.history.export', ['month' => '2026-10']));
    $rows = parseCsvContent($response->streamedContent());

    // Baris 0: header, Baris 1: empty line, Baris 2: summary row
    $summaryRow = end($rows);
    expect($summaryRow[0])->toBe('Rata-rata Durasi Pelayanan');
    expect($summaryRow[16])->toBe('-');
});

test('B7: Catatan petugas dengan koma, tanda kutip, dan baris baru ter-escape benar', function () {
    $officer = createStaffUser('admin');
    $today = '2026-10-12';
    $complexNote = "Baris 1, dengan koma\nBaris 2 dengan \"tanda kutip\"\nBaris 3 selesai.";

    QueueTicket::factory()->create([
        'code' => 'A-020',
        'status' => 'finished',
        'queue_date' => $today,
        'staff_notes' => $complexNote,
        'called_at' => Carbon::parse("{$today} 09:00:00", 'Asia/Jakarta'),
        'finished_at' => Carbon::parse("{$today} 09:05:00", 'Asia/Jakarta'),
    ]);

    $response = $this->actingAs($officer)->get(route('admin.queue.history.export', ['month' => '2026-10']));
    $rows = parseCsvContent($response->streamedContent());

    $dataRow = $rows[1];
    expect($dataRow[15])->toBe($complexNote);
});

test('B8: Catatan berawalan =, +, @ ter-sanitasi dengan petik tunggal, sedangkan placeholder - tetap -', function () {
    $officer = createStaffUser('admin');
    $today = '2026-10-12';

    // Note dengan formula
    QueueTicket::factory()->create([
        'code' => 'A-031',
        'status' => 'finished',
        'queue_date' => $today,
        'staff_notes' => '=SUM(1,2)',
        'called_at' => Carbon::parse("{$today} 09:00:00", 'Asia/Jakarta'),
        'finished_at' => Carbon::parse("{$today} 09:05:00", 'Asia/Jakarta'),
    ]);

    QueueTicket::factory()->create([
        'code' => 'A-032',
        'status' => 'finished',
        'queue_date' => $today,
        'staff_notes' => '+628123456789',
        'called_at' => Carbon::parse("{$today} 09:00:00", 'Asia/Jakarta'),
        'finished_at' => Carbon::parse("{$today} 09:05:00", 'Asia/Jakarta'),
    ]);

    QueueTicket::factory()->create([
        'code' => 'A-033',
        'status' => 'finished',
        'queue_date' => $today,
        'staff_notes' => '@admin_handle',
        'called_at' => Carbon::parse("{$today} 09:00:00", 'Asia/Jakarta'),
        'finished_at' => Carbon::parse("{$today} 09:05:00", 'Asia/Jakarta'),
    ]);

    // Tiket tanpa staff_notes (placeholder '-')
    QueueTicket::factory()->create([
        'code' => 'A-034',
        'status' => 'finished',
        'queue_date' => $today,
        'staff_notes' => null,
        'called_at' => Carbon::parse("{$today} 09:00:00", 'Asia/Jakarta'),
        'finished_at' => Carbon::parse("{$today} 09:05:00", 'Asia/Jakarta'),
    ]);

    $response = $this->actingAs($officer)->get(route('admin.queue.history.export', ['month' => '2026-10']));
    $rows = parseCsvContent($response->streamedContent());

    expect($rows[1][15])->toBe("'=SUM(1,2)");
    expect($rows[2][15])->toBe("'+628123456789");
    expect($rows[3][15])->toBe("'@admin_handle");
    expect($rows[4][15])->toBe("-");
});

test('B9: Filter yang aktif tetap berlaku dan data di luar filter tidak ikut dihitung ke rata-rata', function () {
    $officer = createStaffUser('admin');

    // Tiket bulan Oktober (10 menit = 600 detik)
    QueueTicket::factory()->create([
        'code' => 'A-101',
        'status' => 'finished',
        'queue_date' => '2026-10-15',
        'called_at' => Carbon::parse('2026-10-15 10:00:00', 'Asia/Jakarta'),
        'finished_at' => Carbon::parse('2026-10-15 10:10:00', 'Asia/Jakarta'),
    ]);

    // Tiket bulan September (50 menit = 3000 detik, di luar filter)
    QueueTicket::factory()->create([
        'code' => 'A-102',
        'status' => 'finished',
        'queue_date' => '2026-09-15',
        'called_at' => Carbon::parse('2026-09-15 10:00:00', 'Asia/Jakarta'),
        'finished_at' => Carbon::parse('2026-09-15 10:50:00', 'Asia/Jakarta'),
    ]);

    // Filter bulan 2026-10
    $response = $this->actingAs($officer)->get(route('admin.queue.history.export', ['month' => '2026-10']));
    $rows = parseCsvContent($response->streamedContent());

    // Hanya tiket A-101 yang masuk
    expect($rows)->toHaveCount(4); // header, 1 data row, empty line, summary row
    expect($rows[1][1])->toBe('A-101');
    expect($rows[1][16])->toBe('00:10:00');

    $summaryRow = end($rows);
    expect($summaryRow[16])->toBe('00:10:00');
});

test('B10: Unit test untuk calculateServiceSeconds dan formatDuration', function () {
    $controller = new QueueHistoryController();

    // calculateServiceSeconds
    expect($controller->calculateServiceSeconds(null, '2026-10-12 10:00:00'))->toBeNull();
    expect($controller->calculateServiceSeconds('2026-10-12 10:00:00', null))->toBeNull();
    expect($controller->calculateServiceSeconds('2026-10-12 10:05:00', '2026-10-12 10:00:00'))->toBeNull(); // finished < called
    expect($controller->calculateServiceSeconds('2026-10-12 10:00:00', '2026-10-12 10:00:00'))->toBe(0);
    expect($controller->calculateServiceSeconds('2026-10-12 10:00:00', '2026-10-12 10:00:59'))->toBe(59);
    expect($controller->calculateServiceSeconds('2026-10-12 10:00:00', '2026-10-12 11:00:00'))->toBe(3600);
    // > 24 jam (25 jam = 90000 detik)
    expect($controller->calculateServiceSeconds('2026-10-12 10:00:00', '2026-10-13 11:00:00'))->toBe(90000);

    // formatDuration
    expect($controller->formatDuration(0))->toBe('00:00:00');
    expect($controller->formatDuration(59))->toBe('00:00:59');
    expect($controller->formatDuration(3600))->toBe('01:00:00');
    expect($controller->formatDuration(90000))->toBe('25:00:00');
    // Pembulatan pecahan detik (misal 100 detik / 3 = 33.333 detik -> 33 detik)
    expect($controller->formatDuration((int) round(100 / 3)))->toBe('00:00:33');
});

/* =========================================================================
 * C. FEATURE TEST: SCROLL CONTAINER DAN LAYOUT 3 KOLOM DASHBOARD
 * ========================================================================= */

test('C1: Dashboard merender tiga container (#list-waiting, #list-called, #list-finished) dan atribut/class kunci ada pada masing-masing', function () {
    $officer = createStaffUser('admin');

    $response = $this->actingAs($officer)->get(route('admin.queue.index'));
    $response->assertOk();

    $html = $response->getContent();

    // Pastikan ketiga ID ada
    expect($html)->toContain('id="list-waiting"');
    expect($html)->toContain('id="list-called"');
    expect($html)->toContain('id="list-finished"');

    // Pastikan class overflow-y-auto dan min-h-0 ada pada masing-masing list
    preg_match('/id="list-waiting"[^>]*class="([^"]*)"/', $html, $waitingMatches);
    preg_match('/id="list-called"[^>]*class="([^"]*)"/', $html, $calledMatches);
    preg_match('/id="list-finished"[^>]*class="([^"]*)"/', $html, $finishedMatches);

    expect($waitingMatches)->not->toBeEmpty();
    expect($waitingMatches[1])->toContain('overflow-y-auto');
    expect($waitingMatches[1])->toContain('min-h-0');
    expect($waitingMatches[1])->toContain('overscroll-contain');
    expect($waitingMatches[1])->toContain('touch-pan-y');

    expect($calledMatches)->not->toBeEmpty();
    expect($calledMatches[1])->toContain('overflow-y-auto');
    expect($calledMatches[1])->toContain('min-h-0');
    expect($calledMatches[1])->toContain('overscroll-contain');
    expect($calledMatches[1])->toContain('touch-pan-y');

    expect($finishedMatches)->not->toBeEmpty();
    expect($finishedMatches[1])->toContain('overflow-y-auto');
    expect($finishedMatches[1])->toContain('min-h-0');
    expect($finishedMatches[1])->toContain('overscroll-contain');
    expect($finishedMatches[1])->toContain('touch-pan-y');
});

test('C2: Ketiga list container memiliki tabindex="0", role="region", dan aria-label yang sesuai untuk aksesibilitas keyboard', function () {
    $officer = createStaffUser('admin');

    $response = $this->actingAs($officer)->get(route('admin.queue.index'));
    $response->assertOk();

    $html = $response->getContent();

    expect($html)->toContain('aria-label="Daftar Antrian Menunggu"');
    expect($html)->toContain('aria-label="Daftar Antrian Sedang Dipanggil"');
    expect($html)->toContain('aria-label="Daftar Antrian Selesai Hari Ini"');

    // Role region & tabindex 0
    expect($html)->toMatch('/id="list-waiting"[^>]*tabindex="0"[^>]*role="region"/');
    expect($html)->toMatch('/id="list-called"[^>]*tabindex="0"[^>]*role="region"/');
    expect($html)->toMatch('/id="list-finished"[^>]*tabindex="0"[^>]*role="region"/');
});

test('C3: Card pembungkus dan header menggunakan shrink-0 dan toggle tab mobile flex / hidden md:flex', function () {
    $officer = createStaffUser('admin');

    $response = $this->actingAs($officer)->get(route('admin.queue.index'));
    $response->assertOk();

    $html = $response->getContent();

    // Toggle tab tidak menggunakan block / hidden md:block yang merusak layout flex
    expect($html)->toContain(":class=\"listTab === 'waiting' ? 'flex' : 'hidden md:flex'\"");
    expect($html)->toContain(":class=\"listTab === 'called' ? 'flex' : 'hidden md:flex'\"");
    expect($html)->toContain(":class=\"listTab === 'finished' ? 'flex' : 'hidden md:flex'\"");

    // Komponen Alpine queueScrollContainer dan class scrollbar tipis
    expect($html)->toContain("queueScrollContainer('list-waiting')");
    expect($html)->toContain("queueScrollContainer('list-called')");
    expect($html)->toContain("queueScrollContainer('list-finished')");
    expect($html)->toContain('queue-custom-scroll');
    expect($html)->toContain('scrollbar-width: thin');
});
