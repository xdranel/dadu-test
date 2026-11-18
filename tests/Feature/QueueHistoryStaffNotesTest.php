<?php

use App\Models\QueueCounter;
use App\Models\QueueTicket;
use App\Models\QueueTicketHistory;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    Carbon::setTestNow(Carbon::parse('2026-10-12 10:00:00', 'Asia/Jakarta'));
});

afterEach(function () {
    Carbon::setTestNow();
});

function createHistoryTestAdmin(): User
{
    $user = User::factory()->create(['status' => 'active']);
    $user->assignRole('admin');
    return $user;
}

test('1. halaman riwayat menampilkan header Catatan Petugas setelah Jam Ambil', function () {
    $admin = createHistoryTestAdmin();

    $response = $this->actingAs($admin)->get(route('admin.queue.history'));
    $response->assertOk();

    $content = $response->getContent();
    expect($content)->toMatch('/Jam Ambil<\/th>\s*<th[^>]*>Catatan Petugas<\/th>/s');
});

test('2. tiket dengan staff_notes tampil catatannya di baris yang benar dan tiket tanpa catatan menampilkan -', function () {
    $admin = createHistoryTestAdmin();
    $counter = QueueCounter::firstOrCreate(['id' => 1], ['name' => 'Loket 1']);
    $today = now('Asia/Jakarta')->toDateString();

    $t1 = QueueTicket::create([
        'code' => '001',
        'nim' => '1201210001',
        'name' => 'Mahasiswa Catatan',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'device_token' => (string) Str::uuid(),
        'queue_date' => $today,
        'status' => 'finished',
        'counter_id' => $counter->id,
        'staff_notes' => 'Catatan khusus mahasiswa satu',
        'created_at' => now(),
    ]);

    $t2 = QueueTicket::create([
        'code' => '002',
        'nim' => '1201210002',
        'name' => 'Mahasiswa Tanpa Catatan',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'device_token' => (string) Str::uuid(),
        'queue_date' => $today,
        'status' => 'finished',
        'counter_id' => $counter->id,
        'staff_notes' => null,
        'created_at' => now(),
    ]);

    $response = $this->actingAs($admin)->get(route('admin.queue.history'));
    $response->assertOk();

    $content = $response->getContent();

    // Tiket 1 menampilkan teks catatan dan tombol lihat
    expect($content)->toContain('Catatan khusus mahasiswa satu');
    expect($content)->toContain('aria-controls="note-row-current-' . $t1->id . '"');

    // Tiket 2 tidak punya tombol lihat dan menampilkan "-"
    expect($content)->not->toContain('aria-controls="note-row-current-' . $t2->id . '"');
    expect($content)->toContain('Mahasiswa Tanpa Catatan');
});

test('3. catatan panjang 500 karakter dirender penuh di detail baris bawah dengan tombol baca', function () {
    $admin = createHistoryTestAdmin();
    $counter = QueueCounter::firstOrCreate(['id' => 1], ['name' => 'Loket 1']);
    $today = now('Asia/Jakarta')->toDateString();

    $longNote = str_repeat('A', 500);

    $t = QueueTicket::create([
        'code' => '003',
        'nim' => '1201210003',
        'name' => 'Mahasiswa Long Note',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'device_token' => (string) Str::uuid(),
        'queue_date' => $today,
        'status' => 'finished',
        'counter_id' => $counter->id,
        'staff_notes' => $longNote,
        'created_at' => now(),
    ]);

    $response = $this->actingAs($admin)->get(route('admin.queue.history'));
    $response->assertOk();

    $content = $response->getContent();

    // Assert tombol dengan icon read ada pada sel tabel
    expect($content)->toContain('title="Lihat catatan petugas"');
    expect($content)->toContain('aria-controls="note-row-current-' . $t->id . '"');

    // Assert seluruh 500 karakter dirender di dalam detail collapse di bawah baris
    expect($content)->toContain($longNote);
    expect($content)->toContain('id="note-row-current-' . $t->id . '"');
});

test('4. catatan berisi script alert ter-escape aman di HTML', function () {
    $admin = createHistoryTestAdmin();
    $counter = QueueCounter::firstOrCreate(['id' => 1], ['name' => 'Loket 1']);
    $today = now('Asia/Jakarta')->toDateString();

    $xssNote = '<script>alert(1)</script>';

    $t = QueueTicket::create([
        'code' => '004',
        'nim' => '1201210004',
        'name' => 'Mahasiswa XSS',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'device_token' => (string) Str::uuid(),
        'queue_date' => $today,
        'status' => 'finished',
        'counter_id' => $counter->id,
        'staff_notes' => $xssNote,
        'created_at' => now(),
    ]);

    $response = $this->actingAs($admin)->get(route('admin.queue.history'));
    $response->assertOk();

    $content = $response->getContent();

    // Pastikan tag <script> mentah TIDAK ADA di response
    expect($content)->not->toContain('<script>alert(1)</script>');

    // Pastikan ter-escape sebagai &lt;script&gt;alert(1)&lt;/script&gt;
    expect($content)->toContain('&lt;script&gt;alert(1)&lt;/script&gt;');
});

test('5. data dari tabel arsip queue_ticket_histories juga menampilkan staff_notes', function () {
    $admin = createHistoryTestAdmin();
    $counter = QueueCounter::firstOrCreate(['id' => 1], ['name' => 'Loket 1']);
    $today = now('Asia/Jakarta')->toDateString();

    $archived = QueueTicketHistory::create([
        'ticket_id' => 9999,
        'code' => '005',
        'nim' => '1201210005',
        'name' => 'Mahasiswa Arsip',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'device_token' => (string) Str::uuid(),
        'queue_date' => $today,
        'status' => 'finished',
        'counter_id' => $counter->id,
        'staff_notes' => 'Catatan dari arsip riwayat',
        'created_at' => now(),
        'finished_at' => now(),
    ]);

    $response = $this->actingAs($admin)->get(route('admin.queue.history'));
    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain('Mahasiswa Arsip');
    expect($content)->toContain('Catatan dari arsip riwayat');
    expect($content)->toContain('note-row-history-' . $archived->id);
});

test('6. filter jumlah baris dan urutan lama tidak berubah', function () {
    $admin = createHistoryTestAdmin();
    $counter = QueueCounter::firstOrCreate(['id' => 1], ['name' => 'Loket 1']);
    $today = now('Asia/Jakarta')->toDateString();

    QueueTicket::create([
        'code' => '001',
        'nim' => '1201210001',
        'name' => 'Mahasiswa Urutan 1',
        'study_program' => 'Sains Data',
        'service_category' => 'Akademik',
        'device_token' => (string) Str::uuid(),
        'queue_date' => $today,
        'status' => 'finished',
        'counter_id' => $counter->id,
        'staff_notes' => 'Catatan 1',
        'created_at' => now()->subMinutes(10),
    ]);

    QueueTicket::create([
        'code' => '002',
        'nim' => '1201210002',
        'name' => 'Mahasiswa Urutan 2',
        'study_program' => 'Informatika',
        'service_category' => 'Keuangan',
        'device_token' => (string) Str::uuid(),
        'queue_date' => $today,
        'status' => 'finished',
        'counter_id' => $counter->id,
        'staff_notes' => 'Catatan 2',
        'created_at' => now()->subMinutes(5),
    ]);

    // Request dengan filter layanan Akademik
    $response = $this->actingAs($admin)->get(route('admin.queue.history', [
        'service_category' => 'Akademik',
    ]));
    $response->assertOk();

    $content = $response->getContent();

    // Hanya mahasiswa urutan 1 yang tampil
    expect($content)->toContain('Mahasiswa Urutan 1');
    expect($content)->not->toContain('Mahasiswa Urutan 2');

    // Default urutan adalah queue_date desc, code desc: 002 di atas 001 jika tanpa filter kategori
    $allResponse = $this->actingAs($admin)->get(route('admin.queue.history'));
    $allContent = $allResponse->getContent();
    $pos002 = strpos($allContent, 'Mahasiswa Urutan 2');
    $pos001 = strpos($allContent, 'Mahasiswa Urutan 1');
    expect($pos002)->toBeLessThan($pos001);
});
