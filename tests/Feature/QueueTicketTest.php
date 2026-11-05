<?php

use App\Models\QueueTicket;
use App\Models\QueueCounter;
use App\Models\QueueState;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    // Atur waktu default saat tes ke waktu buka layanan (Senin jam 10:00 WIB)
    $this->travelTo(Carbon::parse('2026-10-12 10:00:00', 'Asia/Jakarta'));
});

test('visitor get take page displays form when queue is open', function () {
    $response = $this->get(route('queue.take'));

    $response->assertOk();
    $response->assertViewIs('queue-tickets.take');
    $response->assertSee('Silakan Lengkapi Data Diri');
    $response->assertSee('NIM');
    $response->assertSee('Program Studi');
});

test('ambil tiket sukses via POST /queue/take saves details and sets device_token cookie', function () {
    $payload = [
        'nim' => '1201210012',
        'name' => 'Fakhri Pratama',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'notes' => 'Pengurusan surat aktif kuliah',
    ];

    $response = $this->post(route('queue.store'), $payload);

    $response->assertRedirect(route('queue.status'));

    $this->assertDatabaseHas('queue_tickets', [
        'code' => 'A-001',
        'nim' => '1201210012',
        'name' => 'Fakhri Pratama',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'notes' => 'Pengurusan surat aktif kuliah',
        'status' => 'waiting',
        'queue_date' => now('Asia/Jakarta')->toDateString(),
    ]);

    $ticket = QueueTicket::first();
    $this->assertNotNull($ticket);
    $response->assertCookie('device_token', $ticket->device_token);
});

test('validasi gagal: NIM bukan angka', function () {
    $payload = [
        'nim' => '12012ABC12', // Bukan hanya angka
        'name' => 'Fakhri Pratama',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
    ];

    $response = $this->from(route('queue.take'))->post(route('queue.store'), $payload);

    $response->assertRedirect(route('queue.take'));
    $response->assertSessionHasErrors(['nim']);
    $this->assertEquals(0, QueueTicket::count());
});

test('validasi gagal: prodi di luar daftar', function () {
    $payload = [
        'nim' => '1201210012',
        'name' => 'Fakhri Pratama',
        'study_program' => 'Kedokteran Hewan', // Di luar daftar config
        'service_category' => 'Akademik',
    ];

    $response = $this->from(route('queue.take'))->post(route('queue.store'), $payload);

    $response->assertRedirect(route('queue.take'));
    $response->assertSessionHasErrors(['study_program']);
    $this->assertEquals(0, QueueTicket::count());
});

test('validasi gagal: kategori layanan di luar daftar atau nama terlalu pendek', function () {
    $payload = [
        'nim' => '1201210012',
        'name' => 'Al', // min:3
        'study_program' => 'Sains Data',
        'service_category' => 'Layanan Palsu',
    ];

    $response = $this->from(route('queue.take'))->post(route('queue.store'), $payload);

    $response->assertRedirect(route('queue.take'));
    $response->assertSessionHasErrors(['name', 'service_category']);
    $this->assertEquals(0, QueueTicket::count());
});

test('tiket ganda per device: perangkat dengan tiket aktif tidak bisa mengambil tiket baru', function () {
    $uuid = (string) Str::uuid();
    $existing = QueueTicket::create([
        'code' => 'A-001',
        'nim' => '1201210011',
        'name' => 'Budi Santoso',
        'study_program' => 'Informatika',
        'service_category' => 'Akademik',
        'status' => 'waiting',
        'device_token' => $uuid,
        'queue_date' => now('Asia/Jakarta')->toDateString(),
    ]);

    // Akses GET /queue/take dengan cookie device_token aktif -> redirect status
    $getResponse = $this->withCookie('device_token', $uuid)->get(route('queue.take'));
    $getResponse->assertRedirect(route('queue.status'));

    // Kirim POST /queue/take dengan cookie yang sama
    $postResponse = $this->withCookie('device_token', $uuid)->post(route('queue.store'), [
        'nim' => '1201219999',
        'name' => 'Orang Lain',
        'study_program' => 'Sains Data',
        'service_category' => 'Keuangan',
    ]);

    $postResponse->assertRedirect(route('queue.status'));

    // Tiket di database tetap hanya 1
    $this->assertEquals(1, QueueTicket::count());
    $this->assertEquals('A-001', QueueTicket::first()->code);
});

test('ambil tiket saat tutup ditolak dan menampilkan view closed', function () {
    // Travel ke hari Minggu jam 11:00 WIB (layanan tutup)
    $this->travelTo(Carbon::parse('2026-10-18 11:00:00', 'Asia/Jakarta'));

    // GET /queue/take mengembalikan halaman closed
    $response = $this->get(route('queue.take'));
    $response->assertOk();
    $response->assertViewIs('queue-tickets.closed');
    $response->assertSee('Layanan Sedang Tutup');

    // POST /queue/take ditolak dan tidak membuat tiket
    $postResponse = $this->post(route('queue.store'), [
        'nim' => '1201210012',
        'name' => 'Fakhri Pratama',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
    ]);

    $postResponse->assertRedirect(route('queue.take'));
    $this->assertEquals(0, QueueTicket::count());
});

test('dua request berurutan/bersamaan tidak menghasilkan kode kembar', function () {
    $device1 = (string) Str::uuid();
    $device2 = (string) Str::uuid();

    $res1 = $this->withCookie('device_token', $device1)->post(route('queue.store'), [
        'nim' => '1201210001',
        'name' => 'Mahasiswa Satu',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
    ]);
    $res1->assertRedirect(route('queue.status'));

    $res2 = $this->withCookie('device_token', $device2)->post(route('queue.store'), [
        'nim' => '1201210002',
        'name' => 'Mahasiswa Dua',
        'study_program' => 'Informatika',
        'service_category' => 'Kemahasiswaan',
    ]);
    $res2->assertRedirect(route('queue.status'));

    $tickets = QueueTicket::orderBy('id', 'asc')->get();
    $this->assertCount(2, $tickets);
    $this->assertEquals('A-001', $tickets[0]->code);
    $this->assertEquals('A-002', $tickets[1]->code);
    $this->assertNotEquals($tickets[0]->code, $tickets[1]->code);
});

test('admin or pegawai can access queue dashboard', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $stafSsc = User::factory()->create();
    $stafSsc->assignRole('Pelaksana');

    $mahasiswa = User::factory()->create();
    $mahasiswa->assignRole('mahasiswa');

    // Admin passes
    $this->actingAs($admin)->get(route('admin.queue.index'))->assertOk();

    // Pelaksana (Staf SSC) passes
    $this->actingAs($stafSsc)->get(route('admin.queue.index'))->assertOk();

    // Mahasiswa fails with 403 (Forbidden)
    $this->actingAs($mahasiswa)->get(route('admin.queue.index'))->assertStatus(403);
});

test('admin click next when there is waiting queue calls the oldest waiting ticket', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $counter = QueueCounter::firstOrCreate(['id' => 1], ['name' => 'Loket 1']);
    $counter->update(['active_user_id' => $user->id]);
    $state = QueueState::firstOrCreate(['counter_id' => $counter->id], ['current_id' => null, 'last_id' => null]);

    $today = now('Asia/Jakarta')->toDateString();
    $t1 = QueueTicket::create([
        'code' => 'A-001',
        'nim' => '1201210001',
        'name' => 'Mhs 1',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'status' => 'waiting',
        'device_token' => (string)Str::uuid(),
        'queue_date' => $today,
    ]);
    $t2 = QueueTicket::create([
        'code' => 'A-002',
        'nim' => '1201210002',
        'name' => 'Mhs 2',
        'study_program' => 'Informatika',
        'service_category' => 'Akademik',
        'status' => 'waiting',
        'device_token' => (string)Str::uuid(),
        'queue_date' => $today,
    ]);

    // Acting as admin with active counter in session, post to next
    $response = $this->actingAs($user)->withSession(['queue_counter_id' => $counter->id])->post(route('admin.queue.next'));

    $response->assertRedirect();
    $response->assertSessionMissing('error');

    // Assert t1 is now 'called'
    $t1->refresh();
    $this->assertEquals('called', $t1->status);
    $this->assertNotNull($t1->called_at);
    $this->assertEquals($counter->id, $t1->counter_id);
    $this->assertEquals($user->id, $t1->called_by);

    // Assert state current_id is t1
    $state->refresh();
    $this->assertEquals($t1->id, $state->current_id);
    $this->assertNull($state->last_id);

    // Next click calls t2 and finishes t1
    $response2 = $this->actingAs($user)->withSession(['queue_counter_id' => $counter->id])->post(route('admin.queue.next'));

    $t1->refresh();
    $t2->refresh();
    $state->refresh();

    $this->assertEquals('finished', $t1->status);
    $this->assertNotNull($t1->finished_at);
    $this->assertEquals('called', $t2->status);
    $this->assertEquals($t2->id, $state->current_id);
    $this->assertEquals($t1->id, $state->last_id);
});

test('admin click next when queue is empty returns error', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $counter = QueueCounter::firstOrCreate(['id' => 1], ['name' => 'Loket 1']);
    $counter->update(['active_user_id' => $user->id]);
    QueueState::firstOrCreate(['counter_id' => $counter->id], ['current_id' => null, 'last_id' => null]);

    $response = $this->actingAs($user)->withSession(['queue_counter_id' => $counter->id])->post(route('admin.queue.next'));

    $response->assertRedirect();
    $response->assertSessionHas('error', 'Tidak ada antrian.');
});

test('admin click back when last_id exists reverts state successfully', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $counter = QueueCounter::firstOrCreate(['id' => 1], ['name' => 'Loket 1']);
    $counter->update(['active_user_id' => $user->id]);

    $t1 = QueueTicket::create([
        'code' => 'A-001',
        'status' => 'finished',
        'device_token' => (string)Str::uuid(),
        'counter_id' => $counter->id,
        'called_by' => $user->id,
        'called_at' => now(),
        'finished_at' => now(),
    ]);
    $t2 = QueueTicket::create([
        'code' => 'A-002',
        'status' => 'called',
        'device_token' => (string)Str::uuid(),
        'counter_id' => $counter->id,
        'called_by' => $user->id,
        'called_at' => now(),
    ]);

    $state = QueueState::updateOrCreate(
        ['counter_id' => $counter->id],
        [
            'current_id' => $t2->id,
            'last_id' => $t1->id,
        ]
    );

    // Click back
    $response = $this->actingAs($user)->withSession(['queue_counter_id' => $counter->id])->post(route('admin.queue.back'));

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $t1->refresh();
    $t2->refresh();
    $state->refresh();

    // t2 (current_id) should be reverted to 'waiting'
    $this->assertEquals('waiting', $t2->status);
    $this->assertNull($t2->called_at);

    // t1 (last_id) should be reverted to 'called' and finished_at is null
    $this->assertEquals('called', $t1->status);
    $this->assertNull($t1->finished_at);

    // state current_id should be t1, and last_id is reset to null
    $this->assertEquals($t1->id, $state->current_id);
    $this->assertNull($state->last_id);
});

test('admin click back when last_id is null fails', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $counter = QueueCounter::firstOrCreate(['id' => 1], ['name' => 'Loket 1']);
    $counter->update(['active_user_id' => $user->id]);
    QueueState::updateOrCreate(['counter_id' => $counter->id], ['current_id' => null, 'last_id' => null]);

    $response = $this->actingAs($user)->withSession(['queue_counter_id' => $counter->id])->post(route('admin.queue.back'));

    $response->assertRedirect();
    $response->assertSessionHas('error', 'Tidak bisa membatalkan pemanggilan (maksimal mundur 1 langkah).');
});

test('daily reset at 8 AM archives tickets with unserved status and resets queue states', function () {
    $counter = QueueCounter::firstOrCreate(['id' => 1], ['name' => 'Loket 1']);

    // Populate some tickets
    $t1 = QueueTicket::create([
        'code' => 'A-001',
        'nim' => '1201210001',
        'name' => 'Mhs Selesai',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'notes' => 'Catatan 1',
        'status' => 'finished',
        'counter_id' => $counter->id,
        'device_token' => (string)Str::uuid(),
    ]);
    $t2 = QueueTicket::create([
        'code' => 'A-002',
        'nim' => '1201210002',
        'name' => 'Mhs Belum Selesai',
        'study_program' => 'Informatika',
        'service_category' => 'Kemahasiswaan',
        'status' => 'called', // Sisa yang tidak terlayani
        'counter_id' => $counter->id,
        'device_token' => (string)Str::uuid(),
    ]);

    QueueState::updateOrCreate(
        ['counter_id' => $counter->id],
        [
            'current_id' => $t2->id,
            'last_id' => $t1->id,
        ]
    );

    // Travel to 8:00 AM Asia/Jakarta time zone
    $this->travelTo(now()->setTimezone('Asia/Jakarta')->setTime(8, 0, 0));

    Artisan::call('schedule:run');

    // Assert tickets are moved to histories with new columns and unserved tag
    $this->assertDatabaseHas('queue_ticket_histories', [
        'id' => $t1->id,
        'code' => 'A-001',
        'nim' => '1201210001',
        'name' => 'Mhs Selesai',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'status' => 'finished',
        'counter_id' => $counter->id,
    ]);

    $this->assertDatabaseHas('queue_ticket_histories', [
        'id' => $t2->id,
        'code' => 'A-002',
        'nim' => '1201210002',
        'name' => 'Mhs Belum Selesai',
        'study_program' => 'Informatika',
        'service_category' => 'Kemahasiswaan',
        'status' => 'unserved', // Berubah menjadi unserved!
        'counter_id' => $counter->id,
    ]);

    // Assert active queue_tickets is empty
    $this->assertEquals(0, QueueTicket::count());

    // Assert queue_states is reset
    $state = QueueState::where('counter_id', $counter->id)->first();
    $this->assertNull($state->current_id);
    $this->assertNull($state->last_id);
});

test('counter claim is exclusive and restores owner on dashboard visit', function () {
    $first = User::factory()->create();
    $first->assignRole('admin');
    $second = User::factory()->create();
    $second->assignRole('admin');

    $counter = QueueCounter::firstOrCreate(['id' => 1], ['name' => 'Loket 1']);

    // Petugas 1 klaim Loket 1 -> sukses
    $this->actingAs($first)->post(route('admin.queue.claim'), ['counter_id' => $counter->id])
        ->assertRedirect()
        ->assertSessionHas('success');

    $counter->refresh();
    $this->assertEquals($first->id, $counter->active_user_id);

    // Petugas 2 coba klaim Loket 1 yang sedang dipegang Petugas 1 -> gagal
    $this->actingAs($second)->post(route('admin.queue.claim'), ['counter_id' => $counter->id])
        ->assertRedirect()
        ->assertSessionHas('error');

    $counter->refresh();
    $this->assertEquals($first->id, $counter->active_user_id);

    // Petugas 1 buka dashboard di browser lain -> sesi dipulihkan otomatis
    $this->actingAs($first)->get(route('admin.queue.index'))
        ->assertOk()
        ->assertSessionHas('queue_counter_id', $counter->id);
});

test('next rejects when logged-in user does not own any counter', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $response = $this->actingAs($user)->post(route('admin.queue.next'));

    $response->assertRedirect();
    $response->assertSessionHas('error', 'Silakan pilih loket terlebih dahulu sebelum memanggil antrian.');
});

test('releasing counter with called ticket prompts confirmation and finishes on confirm', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $counter = QueueCounter::firstOrCreate(['id' => 1], ['name' => 'Loket 1']);
    $counter->update(['active_user_id' => $user->id]);

    $ticket = QueueTicket::create([
        'code' => 'A-001',
        'status' => 'called',
        'device_token' => (string)Str::uuid(),
        'counter_id' => $counter->id,
        'called_by' => $user->id,
        'called_at' => now(),
    ]);

    QueueState::updateOrCreate(
        ['counter_id' => $counter->id],
        ['current_id' => $ticket->id, 'last_id' => null]
    );

    // Coba keluar tanpa konfirmasi finish -> harus meminta konfirmasi
    $response = $this->actingAs($user)->withSession(['queue_counter_id' => $counter->id])->post(route('admin.queue.release'));
    $response->assertSessionHas('queue_release_ticket', 'A-001');

    $counter->refresh();
    $this->assertEquals($user->id, $counter->active_user_id);

    // Keluar dengan konfirmasi finish_current=1 -> tiket selesai dan loket dilepas
    $response2 = $this->actingAs($user)->withSession(['queue_counter_id' => $counter->id])->post(route('admin.queue.release'), [
        'finish_current' => 1,
    ]);
    $response2->assertSessionHas('success');

    $ticket->refresh();
    $counter->refresh();
    $this->assertEquals('finished', $ticket->status);
    $this->assertNull($counter->active_user_id);
});

test('student status page and json polling display counter destination when called', function () {
    $counter = QueueCounter::firstOrCreate(['id' => 1], ['name' => 'Loket 1']);
    $uuid = (string) Str::uuid();

    $ticket = QueueTicket::create([
        'code' => 'A-001',
        'nim' => '1201210011',
        'name' => 'Budi Santoso',
        'study_program' => 'Informatika',
        'service_category' => 'Akademik',
        'status' => 'called',
        'counter_id' => $counter->id,
        'called_at' => now(),
        'device_token' => $uuid,
    ]);

    // Status web page shows "Silakan menuju Loket 1"
    $response = $this->withCookie('device_token', $uuid)->get(route('queue.status'));
    $response->assertOk();
    $response->assertSee('Silakan menuju Loket 1');

    // Status JSON polling shows counter_name and current_called_summary
    $jsonResponse = $this->withCookie('device_token', $uuid)->get(route('queue.status.json'));
    $jsonResponse->assertOk()
        ->assertJson([
            'success' => true,
            'code' => 'A-001',
            'status' => 'called',
            'counter_name' => 'Loket 1',
        ]);
});
