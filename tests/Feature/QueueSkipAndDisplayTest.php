<?php

use App\Models\QueueCounter;
use App\Models\QueueNumberCounter;
use App\Models\QueueState;
use App\Models\QueueTicket;
use App\Models\User;
use App\Support\QueueSchedule;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    // Default Senin 10:00 WIB (layanan buka)
    Carbon::setTestNow(Carbon::parse('2026-10-12 10:00:00', 'Asia/Jakarta'));
});

afterEach(function () {
    Carbon::setTestNow();
});

function createActiveAdminUser(): User {
    $user = User::factory()->create(['status' => 'active']);
    $user->assignRole('admin');
    return $user;
}

/* =========================================================================
 * A. AKSI LEWATI
 * ========================================================================= */

test('petugas yang memegang loket bisa melewati tiket called miliknya', function () {
    $user = createActiveAdminUser();
    $counter = QueueCounter::firstOrCreate(['id' => 1], ['name' => 'Loket 1']);
    $counter->update(['active_user_id' => $user->id]);

    $ticket = QueueTicket::create([
        'code' => '001',
        'nim' => '1201210001',
        'name' => 'Budi Santoso',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'device_token' => (string) Str::uuid(),
        'queue_date' => now('Asia/Jakarta')->toDateString(),
        'status' => 'called',
        'counter_id' => $counter->id,
        'called_at' => now('Asia/Jakarta'),
    ]);

    $state = QueueState::updateOrCreate(
        ['counter_id' => $counter->id],
        ['current_id' => $ticket->id, 'last_id' => null, 'call_seq' => 3]
    );

    $response = $this->actingAs($user)
        ->withSession(['queue_counter_id' => $counter->id])
        ->post(route('admin.queue.skip'));

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $ticket->refresh();
    $state->refresh();

    expect($ticket->status)->toBe('skipped');
    expect($ticket->skipped_at)->not->toBeNull();
    expect($ticket->skipped_by)->toBe($user->id);
    expect($state->current_id)->toBeNull();
});

test('lewati ditolak bila petugas belum memegang loket', function () {
    $user = createActiveAdminUser();

    $response = $this->actingAs($user)
        ->post(route('admin.queue.skip'));

    $response->assertRedirect();
    $response->assertSessionHas('error');
});

test('lewati ditolak bila loket tidak punya tiket called', function () {
    $user = createActiveAdminUser();
    $counter = QueueCounter::firstOrCreate(['id' => 1], ['name' => 'Loket 1']);
    $counter->update(['active_user_id' => $user->id]);

    QueueState::updateOrCreate(
        ['counter_id' => $counter->id],
        ['current_id' => null, 'last_id' => null]
    );

    $response = $this->actingAs($user)
        ->withSession(['queue_counter_id' => $counter->id])
        ->post(route('admin.queue.skip'));

    $response->assertRedirect();
    $response->assertSessionHas('error');
});

test('lewati tidak percaya counter_id atau ticket_id dari request body dan tetap memakai session', function () {
    $user = createActiveAdminUser();
    $counter1 = QueueCounter::firstOrCreate(['id' => 1], ['name' => 'Loket 1']);
    $counter2 = QueueCounter::firstOrCreate(['id' => 2], ['name' => 'Loket 2']);
    $counter1->update(['active_user_id' => $user->id]);

    $ticket2 = QueueTicket::create([
        'code' => '002',
        'nim' => '1201210002',
        'name' => 'Siti',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'device_token' => (string) Str::uuid(),
        'queue_date' => now('Asia/Jakarta')->toDateString(),
        'status' => 'called',
        'counter_id' => $counter2->id,
        'called_at' => now('Asia/Jakarta'),
    ]);

    QueueState::updateOrCreate(
        ['counter_id' => $counter1->id],
        ['current_id' => null, 'last_id' => null]
    );
    QueueState::updateOrCreate(
        ['counter_id' => $counter2->id],
        ['current_id' => $ticket2->id, 'last_id' => null]
    );

    // Kirim request manipulasi dengan counter_id / ticket_id milik loket 2
    $response = $this->actingAs($user)
        ->withSession(['queue_counter_id' => $counter1->id])
        ->post(route('admin.queue.skip'), [
            'counter_id' => $counter2->id,
            'ticket_id' => $ticket2->id,
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('error');

    $ticket2->refresh();
    expect($ticket2->status)->toBe('called'); // Tidak boleh ter-skip!
});

test('lewati ditolak bila tiket sudah berubah status', function () {
    $user = createActiveAdminUser();
    $counter = QueueCounter::firstOrCreate(['id' => 1], ['name' => 'Loket 1']);
    $counter->update(['active_user_id' => $user->id]);

    $ticket = QueueTicket::create([
        'code' => '001',
        'nim' => '1201210001',
        'name' => 'Budi',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'device_token' => (string) Str::uuid(),
        'queue_date' => now('Asia/Jakarta')->toDateString(),
        'status' => 'finished',
        'counter_id' => $counter->id,
    ]);

    QueueState::updateOrCreate(
        ['counter_id' => $counter->id],
        ['current_id' => $ticket->id, 'last_id' => null]
    );

    $response = $this->actingAs($user)
        ->withSession(['queue_counter_id' => $counter->id])
        ->post(route('admin.queue.skip'));

    $response->assertRedirect();
    $response->assertSessionHas('error');
});

test('lewati dua kali berturut-turut menghasilkan satu perubahan dan request kedua ditolak tanpa 500', function () {
    $user = createActiveAdminUser();
    $counter = QueueCounter::firstOrCreate(['id' => 1], ['name' => 'Loket 1']);
    $counter->update(['active_user_id' => $user->id]);

    $ticket = QueueTicket::create([
        'code' => '001',
        'nim' => '1201210001',
        'name' => 'Budi',
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
        ['current_id' => $ticket->id, 'last_id' => null]
    );

    // Request 1: Sukses
    $res1 = $this->actingAs($user)
        ->withSession(['queue_counter_id' => $counter->id])
        ->post(route('admin.queue.skip'));
    $res1->assertRedirect();
    $res1->assertSessionHas('success');

    // Request 2: Ditolak dengan session error tanpa 500
    $res2 = $this->actingAs($user)
        ->withSession(['queue_counter_id' => $counter->id])
        ->post(route('admin.queue.skip'));
    $res2->assertRedirect();
    $res2->assertSessionHas('error');
});

test('endpoint lewati menolak pengguna unauthenticated atau tanpa akses admin', function () {
    // 1. Unauthenticated -> redirect ke login
    $resGuest = $this->post(route('admin.queue.skip'));
    $resGuest->assertRedirect(route('login'));

    // 2. Mahasiswa tanpa permission queue_ticket_update -> 403
    $studentUser = User::factory()->create(['status' => 'active']);
    $studentUser->assignRole('mahasiswa');
    $resForbidden = $this->actingAs($studentUser)->post(route('admin.queue.skip'));
    $resForbidden->assertStatus(403);
});

/* =========================================================================
 * B. EFEK SAMPING YANG TIDAK BOLEH TERJADI
 * ========================================================================= */

test('call_seq loket tidak berubah setelah lewati dan display json tidak menandakan panggilan baru', function () {
    $user = createActiveAdminUser();
    $counter = QueueCounter::firstOrCreate(['id' => 1], ['name' => 'Loket 1']);
    $counter->update(['active_user_id' => $user->id]);

    $ticket = QueueTicket::create([
        'code' => '005',
        'nim' => '1201210005',
        'name' => 'Doni',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'device_token' => (string) Str::uuid(),
        'queue_date' => now('Asia/Jakarta')->toDateString(),
        'status' => 'called',
        'counter_id' => $counter->id,
    ]);

    $state = QueueState::updateOrCreate(
        ['counter_id' => $counter->id],
        ['current_id' => $ticket->id, 'last_id' => null, 'call_seq' => 10]
    );

    $this->actingAs($user)
        ->withSession(['queue_counter_id' => $counter->id])
        ->post(route('admin.queue.skip'));

    $state->refresh();
    expect($state->call_seq)->toBe(10); // call_seq TIDAK naik

    $disp = $this->get(route('queue.display.json'))->json();
    $cDisp = collect($disp['counters'])->firstWhere('id', $counter->id);
    expect($cDisp['call_seq'])->toBe(10);
    expect($cDisp['current_number'])->toBeNull();
    expect($cDisp['number'])->toBeNull();
});

test('lewati tidak otomatis memanggil tiket berikutnya: tiket waiting tetap waiting', function () {
    $user = createActiveAdminUser();
    $counter = QueueCounter::firstOrCreate(['id' => 1], ['name' => 'Loket 1']);
    $counter->update(['active_user_id' => $user->id]);

    $tCalled = QueueTicket::create([
        'code' => '001',
        'nim' => '1201210001',
        'name' => 'Budi',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'device_token' => (string) Str::uuid(),
        'queue_date' => now('Asia/Jakarta')->toDateString(),
        'status' => 'called',
        'counter_id' => $counter->id,
    ]);

    $tWaiting = QueueTicket::create([
        'code' => '002',
        'nim' => '1201210002',
        'name' => 'Dewi',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'device_token' => (string) Str::uuid(),
        'queue_date' => now('Asia/Jakarta')->toDateString(),
        'status' => 'waiting',
    ]);

    QueueState::updateOrCreate(
        ['counter_id' => $counter->id],
        ['current_id' => $tCalled->id, 'last_id' => null]
    );

    $this->actingAs($user)
        ->withSession(['queue_counter_id' => $counter->id])
        ->post(route('admin.queue.skip'));

    $tWaiting->refresh();
    expect($tWaiting->status)->toBe('waiting'); // Tetap waiting
});

test('next berikutnya tidak pernah memanggil tiket skipped dan memanggil waiting tertua', function () {
    $user = createActiveAdminUser();
    $counter = QueueCounter::firstOrCreate(['id' => 1], ['name' => 'Loket 1']);
    $counter->update(['active_user_id' => $user->id]);

    // Tiket yang di-skip
    $tSkipped = QueueTicket::create([
        'code' => '001',
        'nim' => '1201210001',
        'name' => 'Budi',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'device_token' => (string) Str::uuid(),
        'queue_date' => now('Asia/Jakarta')->toDateString(),
        'status' => 'skipped',
        'skipped_at' => now('Asia/Jakarta'),
    ]);

    // Tiket waiting
    $tWaiting = QueueTicket::create([
        'code' => '002',
        'nim' => '1201210002',
        'name' => 'Eka',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'device_token' => (string) Str::uuid(),
        'queue_date' => now('Asia/Jakarta')->toDateString(),
        'status' => 'waiting',
    ]);

    $state = QueueState::updateOrCreate(
        ['counter_id' => $counter->id],
        ['current_id' => null, 'last_id' => null, 'call_seq' => 0]
    );

    $this->actingAs($user)
        ->withSession(['queue_counter_id' => $counter->id])
        ->post(route('admin.queue.next'));

    $state->refresh();
    $tWaiting->refresh();
    $tSkipped->refresh();

    expect($state->current_id)->toBe($tWaiting->id);
    expect($tWaiting->status)->toBe('called');
    expect($tSkipped->status)->toBe('skipped');
});

test('back (undo) tetap aman setelah ada tiket skipped dan tidak mengembalikan tiket skipped ke waiting', function () {
    /**
     * Keputusan Desain & Perilaku:
     * Saat tiket dilewati (skip), loket mengosongkan current_id dan last_id (last_id = null).
     * Dengan demikian tombol Back dinonaktifkan (karena tidak ada last_id).
     * Jika request Back dipaksa, back() menolak dengan error dan TIDAK mengubah tiket skipped menjadi waiting/called.
     */
    $user = createActiveAdminUser();
    $counter = QueueCounter::firstOrCreate(['id' => 1], ['name' => 'Loket 1']);
    $counter->update(['active_user_id' => $user->id]);

    $tSkipped = QueueTicket::create([
        'code' => '001',
        'nim' => '1201210001',
        'name' => 'Budi',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'device_token' => (string) Str::uuid(),
        'queue_date' => now('Asia/Jakarta')->toDateString(),
        'status' => 'called',
        'counter_id' => $counter->id,
    ]);

    QueueState::updateOrCreate(
        ['counter_id' => $counter->id],
        ['current_id' => $tSkipped->id, 'last_id' => null]
    );

    // Lakukan skip
    $this->actingAs($user)
        ->withSession(['queue_counter_id' => $counter->id])
        ->post(route('admin.queue.skip'));

    $tSkipped->refresh();
    expect($tSkipped->status)->toBe('skipped');

    // Coba lakukan Back
    $resBack = $this->actingAs($user)
        ->withSession(['queue_counter_id' => $counter->id])
        ->post(route('admin.queue.back'));

    $resBack->assertRedirect();
    $resBack->assertSessionHas('error');

    $tSkipped->refresh();
    expect($tSkipped->status)->toBe('skipped'); // Tetap skipped, tidak berubah ke waiting
});

/* =========================================================================
 * C. SISI MAHASISWA & DISPLAY
 * ========================================================================= */

test('perangkat yang tiketnya dilewati bisa mengambil tiket baru dan mendapat nomor berikutnya', function () {
    $deviceToken = (string) Str::uuid();
    $today = now('Asia/Jakarta')->toDateString();

    QueueNumberCounter::updateOrCreate(
        ['queue_date' => $today],
        ['last_number' => 1]
    );

    $tOld = QueueTicket::create([
        'code' => '001',
        'nim' => '1201210001',
        'name' => 'Mahasiswa Lama',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'device_token' => $deviceToken,
        'queue_date' => $today,
        'status' => 'skipped',
        'skipped_at' => now('Asia/Jakarta'),
    ]);

    // Mahasiswa ambil tiket baru via POST /queue/take
    $response = $this->withCookie('device_token', $deviceToken)
        ->post(route('queue.store'), [
            'nim' => '1201210001',
            'name' => 'Mahasiswa Lama',
            'study_program' => 'Sains Data',
            'service_category' => 'Akademik',
        ]);

    $response->assertRedirect(route('queue.status'));

    $tNew = QueueTicket::where('device_token', $deviceToken)->latest('id')->first();
    expect($tNew->id)->not->toBe($tOld->id);
    expect($tNew->status)->toBe('waiting');
    expect($tNew->code)->not->toBe($tOld->code);
});

test('halaman status dan json polling mengembalikan status skipped beserta pesan dilewati', function () {
    $deviceToken = (string) Str::uuid();

    QueueTicket::create([
        'code' => '007',
        'nim' => '1201210007',
        'name' => 'Gita',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'device_token' => $deviceToken,
        'queue_date' => now('Asia/Jakarta')->toDateString(),
        'status' => 'skipped',
        'skipped_at' => now('Asia/Jakarta'),
    ]);

    // 1. Halaman web /queue/status
    $htmlRes = $this->withCookie('device_token', $deviceToken)->get(route('queue.status'));
    $htmlRes->assertOk();
    $htmlRes->assertSee('Nomor Anda dilewati karena tidak hadir saat dipanggil');
    $htmlRes->assertSee('Ambil Nomor Baru');

    // 2. JSON polling /queue/status/json
    $jsonRes = $this->withCookie('device_token', $deviceToken)->get(route('queue.status.json'));
    $jsonRes->assertOk();
    $data = $jsonRes->json();
    expect($data['status'])->toBe('skipped');
    expect($data['message'])->toContain('Nomor Anda dilewati');
    expect($data)->toHaveKey('take_url');
});

test('display publik loket yang tiketnya baru dilewati tampil null tanpa bocoran data pribadi', function () {
    $counter = QueueCounter::firstOrCreate(['id' => 1], ['name' => 'Loket 1']);

    // State loket tidak memiliki tiket aktif karena dilewati
    QueueState::updateOrCreate(
        ['counter_id' => $counter->id],
        ['current_id' => null, 'last_id' => null, 'call_seq' => 5]
    );

    $res = $this->get(route('queue.display.json'));
    $res->assertOk();
    $data = $res->json();

    $c = collect($data['counters'])->firstWhere('id', $counter->id);
    expect($c['current_number'])->toBeNull();
    expect($c['number'])->toBeNull();

    $content = $res->getContent();
    expect($content)->not->toContain('nim');
    expect($content)->not->toContain('study_program');
    expect($content)->not->toContain('service_category');
});

/* =========================================================================
 * D. STATISTIK DAN ARSIP
 * ========================================================================= */

test('rata-rata waktu layanan hanya dihitung dari tiket finished dan finished_today menghitung tiket skipped', function () {
    $user = createActiveAdminUser();
    $counter = QueueCounter::firstOrCreate(['id' => 1], ['name' => 'Loket 1']);
    $today = now('Asia/Jakarta')->toDateString();

    // Tiket 1: Finished, 10 menit
    QueueTicket::create([
        'code' => '001',
        'nim' => '1201210001',
        'name' => 'T1',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'device_token' => (string) Str::uuid(),
        'queue_date' => $today,
        'status' => 'finished',
        'counter_id' => $counter->id,
        'called_at' => Carbon::parse('2026-10-12 10:00:00', 'Asia/Jakarta'),
        'finished_at' => Carbon::parse('2026-10-12 10:10:00', 'Asia/Jakarta'),
    ]);

    // Tiket 2: Skipped (dihitung ke finished_today, tetapi tidak dihitung ke avg service time)
    QueueTicket::create([
        'code' => '002',
        'nim' => '1201210002',
        'name' => 'T2',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'device_token' => (string) Str::uuid(),
        'queue_date' => $today,
        'status' => 'skipped',
        'counter_id' => $counter->id,
        'called_at' => Carbon::parse('2026-10-12 10:15:00', 'Asia/Jakarta'),
        'skipped_at' => Carbon::parse('2026-10-12 10:18:00', 'Asia/Jakarta'),
    ]);

    $res = $this->actingAs($user)->get(route('admin.queue.stats'))->json();

    $cData = collect($res['counters'])->firstWhere('id', $counter->id);
    expect($cData['finished_today'])->toBe(2);
    expect($cData['skipped_today'])->toBe(1);
    expect($cData['avg_service_time'])->toEqual(10);

    expect($res['todayStats']['finishedToday'])->toBe(2);
    expect($res['todayStats']['skippedToday'])->toBe(1);
});

test('arsip harian 08:00 menyalin skipped_at dan skipped_by dan status skipped tetap skipped', function () {
    $user = createActiveAdminUser();
    $counter = QueueCounter::firstOrCreate(['id' => 1], ['name' => 'Loket 1']);
    $today = '2026-10-12';

    $ticket = QueueTicket::create([
        'code' => '099',
        'nim' => '1201210099',
        'name' => 'Hendra',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'device_token' => (string) Str::uuid(),
        'queue_date' => $today,
        'status' => 'skipped',
        'counter_id' => $counter->id,
        'called_at' => Carbon::parse('2026-10-12 11:00:00', 'Asia/Jakarta'),
        'skipped_at' => Carbon::parse('2026-10-12 11:05:00', 'Asia/Jakarta'),
        'skipped_by' => $user->id,
    ]);

    // Jalankan scheduler closure dari console.php
    $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);
    $events = $schedule->events();
    foreach ($events as $event) {
        $event->run(app());
    }

    // Periksa tabel queue_ticket_histories
    $archived = DB::table('queue_ticket_histories')->where('id', $ticket->id)->first();
    expect($archived)->not->toBeNull();
    expect($archived->status)->toBe('skipped'); // Bukan unserved!
    expect($archived->skipped_by)->toBe($user->id);
    expect($archived->skipped_at)->not->toBeNull();

    // Pastikan queue_tickets bersih
    expect(QueueTicket::count())->toBe(0);
});

/* =========================================================================
 * E. JAM PADA KARTU DAN TIMEZONE
 * ========================================================================= */

test('jam pada kartu adalah created_at WIB dan sama persis di ketiga kolom', function () {
    $user = createActiveAdminUser();
    $counter = QueueCounter::firstOrCreate(['id' => 1], ['name' => 'Loket 1']);

    // Buat tiket pada pukul 09:30:15 WIB
    Carbon::setTestNow(Carbon::parse('2026-10-12 09:30:15', 'Asia/Jakarta'));

    $tWaiting = QueueTicket::create([
        'code' => '010',
        'nim' => '1201210010',
        'name' => 'Tiket Uji',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'device_token' => (string) Str::uuid(),
        'queue_date' => '2026-10-12',
        'status' => 'waiting',
    ]);

    // Polling saat tiket di waiting
    Carbon::setTestNow(Carbon::parse('2026-10-12 09:40:00', 'Asia/Jakarta'));
    $res1 = $this->actingAs($user)->get(route('admin.queue.stats'))->json();
    $w = collect($res1['tickets']['waiting'])->firstWhere('id', $tWaiting->id);
    expect($w['created_at_formatted'])->toBe('09:30');

    // Pindah ke called pada 09:45
    Carbon::setTestNow(Carbon::parse('2026-10-12 09:45:00', 'Asia/Jakarta'));
    $tWaiting->update([
        'status' => 'called',
        'counter_id' => $counter->id,
        'called_at' => now('Asia/Jakarta'),
    ]);

    $res2 = $this->actingAs($user)->get(route('admin.queue.stats'))->json();
    $c = collect($res2['tickets']['called'])->firstWhere('id', $tWaiting->id);
    expect($c['created_at_formatted'])->toBe('09:30'); // Tetap 09:30!

    // Pindah ke finished pada 10:05
    Carbon::setTestNow(Carbon::parse('2026-10-12 10:05:00', 'Asia/Jakarta'));
    $tWaiting->update([
        'status' => 'finished',
        'finished_at' => now('Asia/Jakarta'),
    ]);

    $res3 = $this->actingAs($user)->get(route('admin.queue.stats'))->json();
    $f = collect($res3['tickets']['finished'])->firstWhere('id', $tWaiting->id);
    expect($f['created_at_formatted'])->toBe('09:30'); // Tetap 09:30!
});

test('kartu sedang dipanggil tidak memuat label teks Dipanggil', function () {
    $user = createActiveAdminUser();
    $counter = QueueCounter::firstOrCreate(['id' => 1], ['name' => 'Loket 1']);

    QueueTicket::create([
        'code' => '025',
        'nim' => '1201210025',
        'name' => 'Test Card',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'device_token' => (string) Str::uuid(),
        'queue_date' => now('Asia/Jakarta')->toDateString(),
        'status' => 'called',
        'counter_id' => $counter->id,
        'called_at' => now('Asia/Jakarta'),
    ]);

    $html = $this->actingAs($user)->get(route('admin.queue.index'))->getContent();

    // Pastikan tidak ada "Dipanggil: HH:mm" di kartu
    expect($html)->not->toContain('Dipanggil:');
    // Pastikan menggunakan format "Ambil:"
    expect($html)->toContain('Ambil:');
});

test('tiket yang dibuat pada 02:45 UTC tampil sebagai 09:45 WIB dan batas tanggal tepat', function () {
    // 02:45 UTC = 09:45 WIB
    Carbon::setTestNow(Carbon::parse('2026-10-12 02:45:00', 'UTC')->setTimezone('Asia/Jakarta'));

    $user = createActiveAdminUser();

    $ticket = QueueTicket::create([
        'code' => '033',
        'nim' => '1201210033',
        'name' => 'UTC Test',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'device_token' => (string) Str::uuid(),
        'queue_date' => now('Asia/Jakarta')->toDateString(),
        'status' => 'waiting',
    ]);

    $res = $this->actingAs($user)->get(route('admin.queue.stats'))->json();
    $tData = collect($res['tickets']['waiting'])->firstWhere('id', $ticket->id);

    expect($tData['created_at_formatted'])->toBe('09:45');

    // 17:30 UTC = 00:30 WIB hari berikutnya
    Carbon::setTestNow(Carbon::parse('2026-10-12 17:30:00', 'UTC')->setTimezone('Asia/Jakarta'));
    $wibDate = now('Asia/Jakarta')->toDateString();
    expect($wibDate)->toBe('2026-10-13');
});

/* =========================================================================
 * F. HAPUS PANGGIL ULANG & HITUNG DILEWATI SEBAGAI SELESAI
 * ========================================================================= */

test('route recall tidak lagi terdaftar dan post ke endpoint recall ditolak 404', function () {
    expect(\Illuminate\Support\Facades\Route::has('admin.queue.recall'))->toBeFalse();

    $user = createActiveAdminUser();
    $response = $this->actingAs($user)->post('/admin/queue/recall');
    $response->assertNotFound();
});

test('next menaikkan call_seq sedangkan back dan lewati tidak menaikkannya', function () {
    $user = createActiveAdminUser();
    $counter = QueueCounter::firstOrCreate(['id' => 1], ['name' => 'Loket 1']);
    $counter->update(['active_user_id' => $user->id]);

    $t1 = QueueTicket::create([
        'code' => '101',
        'nim' => '1201210101',
        'name' => 'Tiket Satu',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'device_token' => (string) Str::uuid(),
        'queue_date' => now('Asia/Jakarta')->toDateString(),
        'status' => 'waiting',
    ]);

    $t2 = QueueTicket::create([
        'code' => '102',
        'nim' => '1201210102',
        'name' => 'Tiket Dua',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'device_token' => (string) Str::uuid(),
        'queue_date' => now('Asia/Jakarta')->toDateString(),
        'status' => 'waiting',
    ]);

    $state = QueueState::updateOrCreate(
        ['counter_id' => $counter->id],
        ['current_id' => null, 'last_id' => null, 'call_seq' => 0]
    );

    // 1. Next: harus menaikkan call_seq dari 0 ke 1
    $this->actingAs($user)
        ->withSession(['queue_counter_id' => $counter->id])
        ->post(route('admin.queue.next'));

    $state->refresh();
    expect($state->call_seq)->toBe(1);
    expect($state->current_id)->toBe($t1->id);

    // 2. Next lagi memanggil t2, menaikkan call_seq dari 1 ke 2
    $this->actingAs($user)
        ->withSession(['queue_counter_id' => $counter->id])
        ->post(route('admin.queue.next'));

    $state->refresh();
    expect($state->call_seq)->toBe(2);
    expect($state->current_id)->toBe($t2->id);

    // 3. Back: membatalkan panggilan t2, call_seq TIDAK boleh naik (tetap 2)
    $this->actingAs($user)
        ->withSession(['queue_counter_id' => $counter->id])
        ->post(route('admin.queue.back'));

    $state->refresh();
    expect($state->call_seq)->toBe(2);

    // 4. Lewati: t1 sedang called, petugas melewati t1. call_seq TIDAK boleh naik (tetap 2)
    $this->actingAs($user)
        ->withSession(['queue_counter_id' => $counter->id])
        ->post(route('admin.queue.skip'));

    $state->refresh();
    expect($state->call_seq)->toBe(2);
});

test('setelah 2 tiket selesai dan 1 tiket dilewati counter selesai bernilai 3 dan daftar menampilkan badge yang benar', function () {
    $user = createActiveAdminUser();
    $counter = QueueCounter::firstOrCreate(['id' => 1], ['name' => 'Loket 1']);
    $today = now('Asia/Jakarta')->toDateString();

    // Tiket 1: Finished
    $t1 = QueueTicket::create([
        'code' => '051',
        'nim' => '1201210051',
        'name' => 'Mahasiswa 1',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'device_token' => (string) Str::uuid(),
        'queue_date' => $today,
        'status' => 'finished',
        'counter_id' => $counter->id,
        'called_at' => Carbon::parse('2026-10-12 09:00:00', 'Asia/Jakarta'),
        'finished_at' => Carbon::parse('2026-10-12 09:10:00', 'Asia/Jakarta'),
    ]);

    // Tiket 2: Finished
    $t2 = QueueTicket::create([
        'code' => '052',
        'nim' => '1201210052',
        'name' => 'Mahasiswa 2',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'device_token' => (string) Str::uuid(),
        'queue_date' => $today,
        'status' => 'finished',
        'counter_id' => $counter->id,
        'called_at' => Carbon::parse('2026-10-12 09:15:00', 'Asia/Jakarta'),
        'finished_at' => Carbon::parse('2026-10-12 09:25:00', 'Asia/Jakarta'),
    ]);

    // Tiket 3: Skipped
    $t3 = QueueTicket::create([
        'code' => '053',
        'nim' => '1201210053',
        'name' => 'Mahasiswa 3',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'device_token' => (string) Str::uuid(),
        'queue_date' => $today,
        'status' => 'skipped',
        'counter_id' => $counter->id,
        'called_at' => Carbon::parse('2026-10-12 09:30:00', 'Asia/Jakarta'),
        'skipped_at' => Carbon::parse('2026-10-12 09:35:00', 'Asia/Jakarta'),
    ]);

    // 1. Cek lewat JSON stats
    $stats = $this->actingAs($user)->get(route('admin.queue.stats'))->json();

    expect($stats['todayStats']['finishedToday'])->toBe(3);
    $c = collect($stats['counters'])->firstWhere('id', $counter->id);
    expect($c['finished_today'])->toBe(3);
    // Rata-rata layanan hanya dari t1 (10 mnt) dan t2 (10 mnt) = 10 mnt (t3 dilewati tidak ikut)
    expect($c['avg_service_time'])->toEqual(10);

    // List finished berisi ketiga tiket
    expect($stats['tickets']['finished'])->toHaveCount(3);

    // 2. Cek render halaman HTML Blade
    $html = $this->actingAs($user)->get(route('admin.queue.index'))->getContent();

    // Pastikan tidak ada lagi badge "Dilewati: N" di header
    expect($html)->not->toContain('id="badge-skipped"');
    expect($html)->not->toContain('Dilewati: ');

    // Pastikan badge finished menampilkan angka 3
    expect($html)->toContain('id="badge-finished" class="text-xs font-bold font-mono px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700">3</span>');

    // Pastikan badge "Dilewati" muncul untuk tiket 3
    expect($html)->toContain('bg-amber-100 text-amber-800 border border-amber-200">Dilewati</span>');

    // Pastikan badge nama loket muncul untuk tiket selesai
    expect($html)->toContain('bg-emerald-100/70 text-emerald-800">Loket 1</span>');

    // Pastikan tombol Panggil Ulang tidak ada di DOM
    expect($html)->not->toContain('Panggil Ulang');
    expect($html)->not->toContain('btn-recall');
});
