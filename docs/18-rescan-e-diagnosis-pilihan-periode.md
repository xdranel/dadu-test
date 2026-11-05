# 18 — Rescan E dan diagnosis pilihan periode

**Pembaruan bukti:** hasil Tinker terbaru pada [19](19-rescan-e-siap-finalisasi.md) memakai tanggal database yang sudah berbeda dan mengembalikan ID [1,2]. Analisis screenshot pada dokumen ini hanya berlaku untuk data screenshot saat itu, bukan diagnosis data terbaru.

Tanggal: 2 November 2026. Source terbaru dibaca langsung dari `/home/xdranel/Code/Project/php-laravel/dadu/`, karena pengguna belum memindahkannya ke dadu-test. Pengguna melaporkan semua test dan pengujian browser E berhasil. Asisten tidak menjalankan ulang suite/build/browser.

## 1. Mengapa hanya satu periode yang tampil

Screenshot pengguna `/home/xdranel/Pictures/Screenshots/ss1.png` memperlihatkan:

| Periode | Aktif | Dibuka | Tenggat | Kondisi pada 2 November 2026 |
| --- | --- | --- | --- | --- |
| Periode 1 2026/2027 | Ya | 31 Oktober 2026, 23.59 | 5 November 2026, 23.59 | Terbuka |
| Periode 2 2026/2027 | Ya | 6 November 2026, 23.59 | 30 November 2026, 23.56 | Belum dibuka |

Format tanggal pada screenshot adalah bulan/hari/tahun; terlihat jelas dari tanggal `11/30/2026`. Jadi `11/06/2026` berarti **6 November**, bukan 11 Juni.

Query `SidangTaPeriod::openForRegistration()` mensyaratkan ketiganya:

```text
is_active = true
open_at <= waktu aplikasi sekarang
close_at >= waktu aplikasi sekarang
```

Badge **AKTIF** hanya menunjukkan flag pertama. Ia tidak berarti tanggal mulai sudah terlewati. Berdasarkan tanggal yang terlihat dan waktu sekarang 2 November, hanya Periode 1 yang memenuhi aturan. **Ini sesuai keputusan pengguna bahwa mahasiswa memilih periode terbuka**, bukan bug dropdown.

Pada draft revisi, periode pengajuan asal memang dikunci dan ditampilkan sebagai teks. Revisi tidak boleh berpindah periode. Pada draft baru yang dilanjutkan, dropdown berisi semua periode yang terbuka ditambah periode lama tersimpan jika sudah tutup, agar pilihan lama tidak hilang diam-diam; pilihan lama yang tutup tetap tidak dapat disimpan sebagai pilihan baru.

## 2. Langkah yang perlu dilakukan

Tidak perlu mengubah scope, controller, atau Blade untuk kasus screenshot tersebut.

- Jika dua periode memang dijadwalkan berurutan seperti screenshot, pertahankan tanggalnya. Periode 2 akan tersedia mulai 6 November pukul 23.59 menurut timezone aplikasi, selama masih aktif.
- Jika ingin **menguji dua pilihan bersamaan**, gunakan data percobaan dengan rentang kedua periode yang sama-sama mencakup waktu sekarang. Contoh pada 2 November: periode uji kedua dibuka 1 November dan ditutup 30 November. Simpan perubahan melalui pengaturan periode, lalu reload tahap 1 **draft baru**, bukan revisi.
- Jangan menghapus filter tanggal supaya periode masa depan bisa dipilih. Itu akan melanggar aturan periode terbuka dan validasi server saat save/tinjau/final.
- Jika tanggal di screenshot baru diedit tetapi belum disimpan, klik Simpan perubahan dahulu; dropdown memakai data database, bukan nilai form admin yang belum dikirim.

Jika tanggal sudah disamakan tetapi hasil masih berbeda, gunakan diagnosis aplikasi pada bagian 3 untuk melihat nilai tersimpan dan waktu aplikasi yang sesungguhnya. Jangan mengubah timezone sebagai percobaan tanpa bukti salah konfigurasi.

## 3. Diagnosis lanjutan hanya jika diperlukan

Tidak ada file baru yang harus dibuat pada kasus ini. Jalankan dari folder asli:

```bash
php artisan tinker
```

Di prompt Tinker, jalankan kode baca berikut:

```php
dump([
    'timezone' => config('app.timezone'),
    'now' => now()->format('Y-m-d H:i:s P'),
    'database' => DB::connection()->getDatabaseName(),
]);
dump(App\Models\SidangTaPeriod::orderBy('id')->get()->map(fn ($period) => [
    'id' => $period->id,
    'name' => $period->name,
    'is_active' => $period->is_active,
    'open_at' => $period->open_at->format('Y-m-d H:i:s P'),
    'close_at' => $period->close_at->format('Y-m-d H:i:s P'),
    'is_open' => $period->isOpen(),
])->all());
dump(App\Models\SidangTaPeriod::query()->openForRegistration()->pluck('id')->all());
```

Keluar dengan `exit`. Tidak perlu membagikan password atau seluruh `.env`. Hasil Tinker mencerminkan proses CLI; jika PHP web memakai konfigurasi berbeda, catat juga cara menjalankan web. Source `config/app.php` saat scan memakai `APP_TIMEZONE` dengan default Asia/Jakarta; nilai runtime belum dibaca oleh asisten.

## 4. Perkuat test agar memeriksa option dropdown yang sebenarnya

Test lama E memastikan nama periode kedua muncul di halaman, tetapi belum memeriksa kedua ID berada pada elemen select. Tambahan ini menguji pilihan HTML, bukan mengubah perilaku query.

File `tests/Feature/SidangTaWizardTest.php` **sudah ada**: cukup edit, tidak perlu `make:test` lagi. Pertahankan seluruh fixture dan sepuluh test sebelumnya. Tambahkan helper ini di file tersebut (di luar closure test):

```php
function sidangTaWizardPeriodIds(string $html): array
{
    $previous = libxml_use_internal_errors(true);
    try {
        $dom = new DOMDocument();
        $dom->loadHTML($html, LIBXML_NONET);
        $options = (new DOMXPath($dom))->query(
            '//select[@name="sidang_ta_period_id"]/option[@value!=""]'
        );
        $ids = [];
        foreach ($options as $option) {
            $ids[] = (int) $option->getAttribute('value');
        }
        sort($ids);
        return $ids;
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
}
```

Tambahkan tiga test lengkap berikut. Fixture beforeEach, model imports, `$this->period`, `$this->user` dan `$this->data` memakai file E yang sudah ada. Setiap perubahan tanggal di sini hanya fixture database **testing MySQL Podman**, bukan data aplikasi:

```php
test('future_active_period_appears_only_after_opening_time', function () {
    try {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-02 12:00:00', config('app.timezone')));
        $this->period->update([
            'open_at' => '2026-10-31 23:59:00',
            'close_at' => '2026-11-05 23:59:00',
        ]);
        $future = SidangTaPeriod::create([
            'name' => 'Periode 2 masa depan', 'is_active' => true,
            'open_at' => '2026-11-06 23:59:00', 'close_at' => '2026-11-30 23:56:00',
        ]);
        $response = $this->get(route('sidang-ta-registrations.create'))->assertOk();
        expect(sidangTaWizardPeriodIds($response->getContent()))->toBe([$this->period->id]);

        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-07 12:00:00', config('app.timezone')));
        $response = $this->get(route('sidang-ta-registrations.create'))->assertOk();
        expect(sidangTaWizardPeriodIds($response->getContent()))->toBe([$future->id]);
    } finally {
        $this->travelBack();
    }
});

test('new_registration_select_contains_both_overlapping_open_period_ids', function () {
    $other = SidangTaPeriod::create([
        'name' => 'Periode terbuka kedua', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay(),
    ]);
    $response = $this->get(route('sidang-ta-registrations.create'))->assertOk();
    $expected = [$this->period->id, $other->id];
    sort($expected);
    expect(sidangTaWizardPeriodIds($response->getContent()))->toBe($expected);
});

test('resumed_new_draft_select_keeps_all_currently_open_period_ids', function () {
    $other = SidangTaPeriod::create([
        'name' => 'Periode terbuka kedua', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay(),
    ]);
    $id = $this->postJson(route('sidang-ta-drafts.store'), $this->data)
        ->assertCreated()->json('data.id');
    $response = $this->get(route('sidang-ta-drafts.edit', ['draft' => $id, 'step' => 1]))->assertOk();
    $expected = [$this->period->id, $other->id];
    sort($expected);
    expect(sidangTaWizardPeriodIds($response->getContent()))->toBe($expected);
});
```

DOMDocument/DOMXPath memerlukan ekstensi PHP DOM yang sudah menjadi prasyarat proyek. Generator tidak diperlukan karena hanya menambah test pada file existing.

Jalankan setelah kode test ditambahkan:

```bash
php artisan config:clear
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing php artisan test tests/Feature/SidangTaWizardTest.php
```

File wizard menjadi **14 test** jika tiga tambahan periode di atas dan satu regresi query kosong di bawah dipasang. Username/password mengikuti phpunit.xml lokal sementara; bila sudah dipulihkan, gunakan environment lengkap pada 11. Asisten hanya memeriksa sintaks, belum menjalankan test tambahan ini.

## 5. Koreksi fungsi tambahan: query step kosong

Rescan menemukan satu bug nyata pada `SidangTaDraftController::edit()` di folder asli. Panduan E sebelumnya memvalidasi step sebagai nullable lalu memakai `$request->integer('step', $maxStep)`. Jika URL berisi `?step=`, nilai menjadi null dan helper integer menghasilkan 0, bukan default. Akibatnya view mengakses judul tahap 0 yang tidak tersedia.

Tidak ada file baru. Pada method edit(), **ganti** bagian validasi, maxStep dan penentuan step dengan:

```php
$query = $request->validate(['step' => ['nullable', 'integer', 'between:1,4']]);
$maxStep = SidangTaDocumentRequirements::firstIncompleteStep($draft) ?? 4;
$step = (int) ($query['step'] ?? $maxStep);
```

Pertahankan redirect bila step melebihi maxStep serta kode lainnya. Nilai kosong sekarang memakai default, sedangkan angka 0/5 tetap gagal validasi. Jangan mengubahnya menjadi clamp yang diam-diam menerima angka tidak valid.

Tambahkan test ke file wizard yang sama:

```php
test('empty_step_query_uses_first_incomplete_step', function () {
    $id = $this->postJson(route('sidang-ta-drafts.store'), $this->data)
        ->assertCreated()->json('data.id');
    $this->get(route('sidang-ta-drafts.edit', $id).'?step=')->assertOk()
        ->assertViewIs('sidang_ta_registrations.wizard')
        ->assertViewHas('step', 2)
        ->assertSee('data-current-step="2"', false);
});
```

Jalankan perintah seluruh file wizard pada bagian 4 setelah perubahan ini. Dokumen 17 juga telah dikoreksi agar kode contoh tidak memperkenalkan bug yang sama kembali.

## 6. Ringkasan rescan implementasi E

- Daftar periode pada index/create dan edit draft memakai koleksi `openForRegistration()->get()`, bukan `first()`.
- Blade dropdown melakukan foreach seluruh koleksi, tanpa limit satu item. Revisi memang memakai teks periode asal.
- Simpan tahap 1 draft baru memvalidasi periode masih terbuka; revisi tetap memakai periode asal.
- Kartu draft owner-filtered dan discard stale tersedia di index; tidak bergantung keberhasilan membuka edit.
- Mark review menyimpan reviewed_version; preview mengambil snapshot di bawah lock dan memakai loadMissing sesudahnya.
- Old version dipertahankan setelah validasi gagal; JS dirty/busy dan x-cloak sudah dipasang.
- Dashboard card TA sudah mengarah ke halaman pendaftaran, bukan memilih satu periode aktif secara acak.
- Format datetime-local admin menghasilkan `2026-11-02T12:30` pada pemeriksaan PHP literal source, bukan dugaan bug escape tanggal.
- Pipeline final legacy masih ada sesuai checkpoint antara. Tahap F harus menggantinya sebelum final Kirim diaktifkan.
- Query step kosong memerlukan koreksi pada bagian 5. Source asli memiliki 16 berkas berbeda/baru dibanding dadu-test pada direktori yang diperiksa; sesuai laporan pengguna bahwa E belum disalin.

Pemeriksaan ini tidak menyatakan test aplikasi diulang atau database produksi/lokal diperiksa langsung. Screenshot dan source cukup menjelaskan kasus periode di atas; nilai database tersimpan bisa dikonfirmasi lewat diagnosis bila diperlukan.

## 7. Prioritas selanjutnya

Keputusan pengguna: **visual, desain, warna, spacing dan perapian tampilan ditunda sampai seluruh fitur relevan student selesai.** Jangan mengerjakannya pada sesi finalisasi F. Setelah kasus periode ini dipahami, tahap fungsi berikutnya adalah final submit draft, idempotensi dan revisi final; perapian detail/visual dilakukan sesudah fungsi student lengkap.

Tidak perlu memindahkan source ke dadu-test sebelum pemeriksaan ini. Saat disalin nanti, sertakan semua Blade/JS/controller/service/test terkait E. phpunit.xml Podman tetap lokal sementara dan dipulihkan pengguna sebelum commit/push.

Rujukan Context7: [Eloquent local scopes](https://github.com/laravel/docs/blob/13.x/eloquent.md#local-scopes), [query tanggal](https://github.com/laravel/docs/blob/13.x/queries.md). Asisten hanya menulis Markdown dan melakukan pemeriksaan read-only.
