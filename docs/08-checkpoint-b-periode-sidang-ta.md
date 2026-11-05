# 08 — Checkpoint B: siapkan beberapa periode Sidang TA

Upload sudah dilaporkan berhasil oleh pengguna. Lanjutkan fondasi periode sebelum draft dan wizard. `max_input_time` yang masih terbaca `-1` dicatat sebagai investigasi tertunda; tidak menghalangi tahap coding ini sesuai instruksi pengguna.

Seluruh langkah kode berikut dikerjakan **pengguna di folder asli**. Asisten hanya menulis panduan. Peta besar tetap [05](05-langkah-form-empat-tahap-sidang-ta.md); hasil coding nantinya dipindah ke dadu-test untuk rescan.

## 1. Pahami hasil scan terbaru

Saat panduan awal ditulis, kedua folder masih menggunakan satu periode aktif. Scan lanjutan setelah pengguna mengerjakan B menemukan scope dan perubahan aksi aktivasi sudah ada di folder asli; dadu-test belum diperbarui. Test di bawah dijalankan pengguna pada folder asli dahulu. Ini bukan klaim seluruh checkpoint telah lulus.

Sementara itu `store()` student masih mengambil periode dari `active()` dan belum memakai pilihan mahasiswa. Jadi menambahkan dropdown saja akan menampilkan pilihan yang tidak benar-benar dihormati saat kirim.

Checkpoint ini menyiapkan query dan pengelolaan beberapa periode. Integrasi pilihan yang tersimpan dilakukan bersama draft di C/D dan finalisasi F. **Jangan memakai alur submit student untuk pengujian beberapa periode sampai integrasi itu selesai.**

## 2. Siapkan pengujian sebelum mengubah perilaku

- [ ] Gunakan MySQL Podman untuk testing mengikuti [09 bagian 1](09-checkpoint-c1-struktur-draft-mysql.md#1-gunakan-mysql-podman-untuk-testing). Arahan ini menggantikan SQLite pada versi awal panduan. RefreshDatabase dapat mereset database tujuan test.
- [ ] Buat `tests/Feature/SidangTaPeriodTest.php`. Jangan menjalankan test pada database aplikasi dengan data yang ingin dipertahankan.
- [ ] Test `lists_only_open_periods`: buat periode aktif+terbuka, aktif+masa depan, aktif+sudah tutup dan nonaktif+dalam rentang; query baru harus hanya menghasilkan ID aktif+terbuka.
- [ ] Test `allows_two_open_periods`: kirim dua POST route `admin.sidang-ta-periods.store` memakai user aktif dengan permission `sidang_ta_period_create`; kedua row harus tetap aktif. Lanjutkan test update dengan permission update agar mengedit satu periode tidak mematikan lainnya.
- [ ] Fixture user wajib status active dan tidak diwajibkan ganti password. Buat permission dengan guard web sesuai pola test proyek; jangan mematikan middleware auth/permission agar test lulus.
- [ ] Tetapkan waktu test memakai helper waktu Laravel dan pulihkan sesudah test. Sertakan kondisi tepat open_at/close_at karena aturan batas inklusif.

Test pertama awalnya gagal karena scope belum ada; test kedua gagal karena controller mematikan periode pertama. Catat kegagalan yang sesuai sebelum perbaikan.

## 3. Tambahkan query periode terbuka

File: `app/Models/SidangTaPeriod.php`.

Tambahkan import `Illuminate\Database\Eloquent\Builder` dan method berikut di dalam class, mengikuti gaya scope yang sudah ada pada model proyek:

```php
public function scopeOpenForRegistration(Builder $query): Builder
{
    $at = now();

    return $query
        ->where('is_active', true)
        ->where('open_at', '<=', $at)
        ->where('close_at', '>=', $at)
        ->orderBy('open_at')
        ->orderBy('id');
}
```

Cara mengambil opsi nantinya:

```php
$openPeriods = SidangTaPeriod::query()->openForRegistration()->get();
```

`is_active` artinya periode diizinkan menerima pendaftaran; tanggal tetap menentukan kapan benar-benar terbuka. Tidak perlu migrasi baru untuk periode jamak karena kolom boolean tersebut sudah ada dan tidak memiliki batas unik satu aktif.

Jangan menghapus `active()` langsung pada tahap ini, karena index/create/store/dashboard lama masih memanggilnya. Penggantian seluruh pemanggil dilakukan saat integrasi C–F, bukan dengan mengganti return type active() dari model menjadi collection yang akan merusak view lama.

Rujukan: [local scopes Laravel 13](https://github.com/laravel/docs/blob/13.x/eloquent.md#local-scopes), diperiksa melalui Context7. Laravel 13 juga mendokumentasikan atribut `#[Scope]`; panduan memakai bentuk `scope...` agar sesuai pola proyek. Gunakan salah satu bentuk saja untuk method ini.

## 4. Hentikan penonaktifan periode lain

File: `app/Http/Controllers/SidangTaRegistrationController.php`.

- [ ] Di `storePeriod()`, hapus statement setelah create yang menjalankan `where('id', '!=', $period->id)->update(['is_active' => false])`.
- [ ] Di `updatePeriod()`, hapus statement sejenis setelah update.
- [ ] Di `setActivePeriod()`, hapus `SidangTaPeriod::query()->update(['is_active' => false])`; pertahankan update periode target ke true.
- [ ] Pertahankan validasi nama, tanggal, description dan permission route yang sudah ada.
- [ ] Nonaktifkan satu periode melalui checkbox pada form edit yang sudah ada. Jangan menambahkan endpoint tanpa permission atau mengubah pengelolaan periode yudisium.

## 5. Sesuaikan teks pengaturan periode

File: `resources/views/sidang_ta_registrations/admin/periods.blade.php`.

Ganti kalimat satu periode aktif menjadi:

> Beberapa periode dapat diaktifkan. Mahasiswa hanya dapat memilih periode aktif yang berada dalam rentang waktu pendaftaran.

Checkbox tetap mengubah periode target saja. Badge AKTIF menunjukkan flag, bukan jaminan tanggal sudah terbuka. Jika menambah label waktu, bedakan Belum dibuka / Sedang dibuka / Ditutup; tidak perlu mendesain ulang halaman petugas sekarang.

## 6. Periksa checkpoint ini

Ini **feature test**, ditempatkan di `tests/Feature/SidangTaPeriodTest.php` karena memeriksa database, route dan middleware. Salin **seluruh** blok berikut ke file tersebut; jangan salin pagar Markdown. Tidak perlu menambahkan `uses(RefreshDatabase::class)` lagi karena `tests/Pest.php` sudah mengaturnya.

```php
<?php

use App\Models\SidangTaPeriod;
use App\Models\User;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

function sidangTaPeriodTestData(array $overrides = []): array
{
    return array_replace([
        'name' => 'Periode Sidang TA Test',
        'open_at' => now()->subDay()->format('Y-m-d H:i:s'),
        'close_at' => now()->addDay()->format('Y-m-d H:i:s'),
        'description' => 'Fixture pengujian, bukan data aplikasi.',
        'is_active' => true,
    ], $overrides);
}

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-01 12:00:00', config('app.timezone')));

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $role = Role::firstOrCreate([
        'name' => 'Sidang TA Test Operator',
        'guard_name' => 'web',
    ]);

    foreach (['read', 'create', 'update'] as $action) {
        $permission = Permission::firstOrCreate([
            'name' => "sidang_ta_period_{$action}",
            'guard_name' => 'web',
        ]);
        $role->givePermissionTo($permission);
    }

    $this->operator = User::factory()->create([
        'status' => 'active',
        'auth_source' => 'manual',
        'must_change_password' => false,
    ]);
    $this->operator->assignRole($role);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

afterEach(function () {
    $this->travelBack();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

test('lists_only_open_periods', function () {
    $open = SidangTaPeriod::create(sidangTaPeriodTestData());

    SidangTaPeriod::create(sidangTaPeriodTestData([
        'name' => 'Belum dibuka',
        'open_at' => now()->addDay(),
        'close_at' => now()->addDays(2),
    ]));
    SidangTaPeriod::create(sidangTaPeriodTestData([
        'name' => 'Sudah ditutup',
        'open_at' => now()->subDays(2),
        'close_at' => now()->subDay(),
    ]));
    SidangTaPeriod::create(sidangTaPeriodTestData([
        'name' => 'Nonaktif',
        'is_active' => false,
    ]));

    expect(SidangTaPeriod::query()->openForRegistration()->pluck('id')->all())
        ->toBe([$open->id]);
});

test('includes_exact_open_and_close_boundaries', function () {
    $opensNow = SidangTaPeriod::create(sidangTaPeriodTestData([
        'name' => 'Dibuka tepat sekarang',
        'open_at' => now(),
    ]));
    $closesNow = SidangTaPeriod::create(sidangTaPeriodTestData([
        'name' => 'Ditutup tepat sekarang',
        'close_at' => now(),
    ]));

    // closesNow memiliki open_at lebih awal, sehingga berada di urutan pertama.
    expect(SidangTaPeriod::query()->openForRegistration()->pluck('id')->all())
        ->toBe([$closesNow->id, $opensNow->id]);
    expect($opensNow->isOpen())->toBeTrue();
    expect($closesNow->isOpen())->toBeTrue();
});

test('orders_open_periods_by_open_date_then_id', function () {
    $later = SidangTaPeriod::create(sidangTaPeriodTestData([
        'open_at' => now()->subHour(),
    ]));
    $earlier = SidangTaPeriod::create(sidangTaPeriodTestData([
        'open_at' => now()->subHours(2),
    ]));
    $sameTime = SidangTaPeriod::create(sidangTaPeriodTestData([
        'open_at' => now()->subHours(2),
    ]));

    expect(SidangTaPeriod::query()->openForRegistration()->pluck('id')->all())
        ->toBe([$earlier->id, $sameTime->id, $later->id]);
});

test('allows_two_open_periods', function () {
    $url = route('admin.sidang-ta-periods.index');

    foreach (['Periode A', 'Periode B'] as $name) {
        $this->actingAs($this->operator)
            ->from($url)
            ->post(route('admin.sidang-ta-periods.store'), sidangTaPeriodTestData([
                'name' => $name,
            ]))
            ->assertRedirect($url)
            ->assertSessionHasNoErrors();
    }

    foreach (['Periode A', 'Periode B'] as $name) {
        $this->assertDatabaseHas('sidang_ta_periods', [
            'name' => $name,
            'is_active' => true,
        ]);
    }
    expect(SidangTaPeriod::query()->openForRegistration()->count())->toBe(2);
});

test('updating_period_keeps_other_period_active', function () {
    $first = SidangTaPeriod::create(sidangTaPeriodTestData(['name' => 'A']));
    $second = SidangTaPeriod::create(sidangTaPeriodTestData(['name' => 'B']));
    $url = route('admin.sidang-ta-periods.index');

    $this->actingAs($this->operator)->from($url)
        ->put(route('admin.sidang-ta-periods.update', $second), sidangTaPeriodTestData([
            'name' => 'B diperbarui',
        ]))
        ->assertRedirect($url)
        ->assertSessionHasNoErrors();

    expect($first->fresh()->is_active)->toBeTrue();
    expect($second->fresh()->is_active)->toBeTrue();
    expect($second->fresh()->name)->toBe('B diperbarui');
});

test('activating_period_keeps_other_period_active', function () {
    $first = SidangTaPeriod::create(sidangTaPeriodTestData(['name' => 'A']));
    $second = SidangTaPeriod::create(sidangTaPeriodTestData([
        'name' => 'B', 'is_active' => false,
    ]));
    $url = route('admin.sidang-ta-periods.index');

    $this->actingAs($this->operator)->from($url)
        ->post(route('admin.sidang-ta-periods.activate', $second))
        ->assertRedirect($url)
        ->assertSessionHasNoErrors();

    expect($first->fresh()->is_active)->toBeTrue();
    expect($second->fresh()->is_active)->toBeTrue();
});

test('deactivating_period_only_changes_target', function () {
    $first = SidangTaPeriod::create(sidangTaPeriodTestData(['name' => 'A']));
    $second = SidangTaPeriod::create(sidangTaPeriodTestData(['name' => 'B']));
    $data = sidangTaPeriodTestData(['name' => 'B']);
    unset($data['is_active']); // Checkbox tidak dicentang tidak dikirim browser.
    $url = route('admin.sidang-ta-periods.index');

    $this->actingAs($this->operator)->from($url)
        ->put(route('admin.sidang-ta-periods.update', $second), $data)
        ->assertRedirect($url)
        ->assertSessionHasNoErrors();

    expect($first->fresh()->is_active)->toBeTrue();
    expect($second->fresh()->is_active)->toBeFalse();
    expect(SidangTaPeriod::query()->openForRegistration()->pluck('id')->all())
        ->toBe([$first->id]);
});

test('rejects_period_changes_without_permission', function () {
    $user = User::factory()->create([
        'status' => 'active',
        'auth_source' => 'manual',
        'must_change_password' => false,
    ]);
    $period = SidangTaPeriod::create(sidangTaPeriodTestData());

    $this->actingAs($user)
        ->post(route('admin.sidang-ta-periods.store'), sidangTaPeriodTestData([
            'name' => 'Tidak boleh dibuat',
        ]))->assertForbidden();

    $this->put(route('admin.sidang-ta-periods.update', $period), sidangTaPeriodTestData([
        'name' => 'Tidak boleh berubah',
    ]))->assertForbidden();

    $this->post(route('admin.sidang-ta-periods.activate', $period))->assertForbidden();

    expect($period->fresh()->name)->toBe('Periode Sidang TA Test');
    expect($period->fresh()->is_active)->toBeTrue();
    $this->assertDatabaseMissing('sidang_ta_periods', ['name' => 'Tidak boleh dibuat']);
});

test('rejects_period_end_before_start', function () {
    $url = route('admin.sidang-ta-periods.index');

    $this->actingAs($this->operator)->from($url)
        ->post(route('admin.sidang-ta-periods.store'), sidangTaPeriodTestData([
            'name' => 'Tanggal salah',
            'close_at' => now()->subDays(2)->format('Y-m-d H:i:s'),
        ]))
        ->assertRedirect($url)
        ->assertSessionHasErrors('close_at');

    $this->assertDatabaseMissing('sidang_ta_periods', ['name' => 'Tanggal salah']);
});
```

Jalankan **seluruh file**, bukan hanya filter dua test awal, dari folder asli. Untuk Linux/zsh yang kamu pakai:

```bash
php artisan config:clear
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing CACHE_STORE=array SESSION_DRIVER=array php artisan test tests/Feature/SidangTaPeriodTest.php
```

`config:clear` dikerjakan pengguna untuk menghindari konfigurasi development yang tercache. Pengaturan koneksi pada perintah berlaku hanya untuk proses test. Siapkan database/user dan ganti pengaturan phpunit.xml mengikuti dokumen 09. Pastikan `pdo_mysql` tersedia. Tidak perlu SQLite atau seeder akun dummy.

- [ ] Kesembilan test lulus di koneksi testing aman.
- [ ] Pada fixture/test, dua periode tetap aktif sesudah create, edit dan aktivasi target.
- [ ] Periode nonaktif, masa depan dan sudah tutup tidak masuk query opsi.
- [ ] Uji tanpa permission tetap ditolak; akses tidak bergantung nama role Petugas semata.

Tidak perlu build JS pada tahap yang hanya mengubah PHP dan teks Blade. Jangan menjalankan migrate:fresh atau mereset data untuk mencoba periode.

Jika gagal sebelum nama test muncul karena migrasi MySQL/dependensi, kirim error pertama lengkap: itu kegagalan setup test yang harus dibedakan dari assertion periode. Jangan menggantinya dengan database development agar cepat lulus. Kode ini sudah diperiksa terhadap route dan helper framework terpasang; asisten hanya memeriksa sintaks dan belum menjalankan suite aplikasi.

Referensi: [Laravel HTTP tests](https://laravel.com/docs/13.x/http-tests), [Pest hooks](https://pestphp.com/docs/hooks). Context7 dicoba tetapi koneksinya gagal pada sesi pembaruan ini; dokumentasi resmi dan source vendor dipakai sebagai fallback.

## 7. Kirim hasil untuk rescan sebelum melanjutkan draft

Pindahkan empat file berikut ke dadu-test:

1. `app/Models/SidangTaPeriod.php`
2. `app/Http/Controllers/SidangTaRegistrationController.php`
3. `resources/views/sidang_ta_registrations/admin/periods.blade.php`
4. `tests/Feature/SidangTaPeriodTest.php`

Kirim hasil test dan sebutkan bahwa checkpoint B fondasi periode selesai. Saya akan membaca ulang kode, mengulas perubahan terhadap panduan, lalu melanjutkan C: tabel draft, kepemilikan, penyimpanan tahap 1 dan dropdown periode yang sungguh tersimpan. UI empat tahap, review dan detail tetap mengikuti urutan 05, tidak dilompati.

Status dokumen: panduan sudah ditulis setelah scan sumber kedua folder; implementasi dan test belum dijalankan asisten. Scan ulang sesudah penulisan digunakan untuk memastikan source aplikasi tetap tidak diubah oleh asisten.


Pembaruan rescan: [10 bagian 5](10-rescan-b-c1-koreksi-model-dokumen.md#5-rendah-variabel-tidak-dipakai-pada-create-periode) mencatat variabel hasil create yang tidak lagi dipakai setelah perubahan B. Pembersihan ini opsional dan tidak mengubah perilaku.
