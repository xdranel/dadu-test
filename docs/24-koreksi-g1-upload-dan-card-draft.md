# 24 — Koreksi G1: registrasi upload dan card draft

Tanggal: 2 November 2026. Fokus hanya bug pilihan file/drag & drop dan card Draft tersimpan. G2 tinjau/detail menunggu bagian ini diuji.

Pembaruan hasil test: dua assertion warning stale yang sensitif terhadap newline diperbaiki pada [25](25-koreksi-test-warning-draft-stale.md). Contoh test di bawah sudah diselaraskan; bila file test sudah dibuat, cukup ganti blok terkait sesuai 25.

## 1. Hasil scan dan penyebab

Source G1 terbaru berada di folder asli `/home/xdranel/Code/Project/php-laravel/dadu/`, belum disalin ke dadu-test. Asisten membaca keduanya tetapi hanya menulis dokumentasi.

Di folder asli ditemukan:

- `resources/js/sidang-ta-wizard.js` sudah mengekspor `sidangTaUpload`.
- Partial upload sudah memanggil `x-data="sidangTaUpload(...)"`, `picked()` dan `drop()`.
- **`resources/js/app.js` masih mengimpor default wizard saja dan hanya mendaftarkan `sidangTaWizard`. Registrasi `sidangTaUpload` belum ada.**

Ini menjelaskan kedua gejala: komponen Alpine upload tidak terinisialisasi, sehingga handler drop dan tampilan pilihan baru tidak berjalan; input file HTML masih dapat dipilih dan dikirim multipart. Sesudah save, server merender metadata berkas tersimpan sehingga nama dan tombol unduh terlihat saat kembali. Perbaikan bukan mengganti service penyimpanan atau membuat upload otomatis.

Test factory pada 23 belum menguji hubungan `app.js` dengan provider Alpine. Test baru di bawah menjalankan entry yang dibaca dari source, dengan bootstrap/Alpine diganti adapter pengujian agar tidak membutuhkan DOM. Test ini menangkap provider yang hilang atau baru didaftarkan sesudah `start()`. Reaktivitas DOM tetap perlu uji browser.

## 2. Buat test baru terlebih dahulu

Jalankan dari folder asli. File yang sudah ada tidak perlu dibuat ulang.

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
mkdir -p tests/Js
touch tests/Js/sidang-ta-entry.test.mjs
php artisan make:test SidangTaDraftCardTest --pest
```

Isi lengkap `tests/Js/sidang-ta-entry.test.mjs`:

```javascript
import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';

const moduleUrl = (source) => `data:text/javascript;base64,${Buffer.from(source).toString('base64')}`;

test('app registers upload before start so picked file can immediately update its component', async () => {
    // Jalankan dari root project, seperti command di dokumen ini.
    const entry = await readFile(resolve('resources/js/app.js'), 'utf8');
    const wizard = await readFile(resolve('resources/js/sidang-ta-wizard.js'), 'utf8');
    const providers = new Map();
    let providersAtStart;
    const alpine = {
        data(name, provider) { providers.set(name, provider); },
        start() { providersAtStart = new Map(providers); },
    };
    const previousWindow = globalThis.window;
    const previousAdapter = globalThis.__sidangTaEntryAlpine;
    const adapterUrl = moduleUrl('export default globalThis.__sidangTaEntryAlpine;');
    // Bootstrap Axios tidak terkait registrasi komponen dan membutuhkan window.
    // Entry lainnya tetap dieksekusi; test tidak menambahkan registrasi yang hilang.
    const executable = entry
        .replace(/^\s*import\s+['"]\.\/bootstrap(?:\.js)?['"];?\s*$/m, '')
        .replace(/from\s+(['"])alpinejs\1/g, `from '${adapterUrl}'`)
        .replace(/from\s+(['"])\.\/sidang-ta-wizard(?:\.js)?\1/g, `from '${moduleUrl(wizard)}'`);
    try {
        globalThis.window = {};
        globalThis.__sidangTaEntryAlpine = alpine;
        await import(moduleUrl(executable));
        assert.equal(typeof providersAtStart?.get('sidangTaWizard'), 'function');
        assert.equal(typeof providersAtStart?.get('sidangTaUpload'), 'function',
            'sidangTaUpload harus terdaftar sebelum Alpine.start().');

        const upload = providersAtStart.get('sidangTaUpload')();
        const events = [];
        const file = { name: 'langsung-terlihat.pdf', size: 1024 * 1024 };
        upload.$refs = { file: { files: [file], disabled: false } };
        upload.$dispatch = (name) => events.push(name);
        upload.picked();
        assert.equal(upload.fileName, 'langsung-terlihat.pdf');
        assert.equal(upload.fileSize, '1.00 MB');
        assert.deepEqual(events, ['sidang-ta-file-changed']);
    } finally {
        if (previousWindow === undefined) delete globalThis.window;
        else globalThis.window = previousWindow;
        if (previousAdapter === undefined) delete globalThis.__sidangTaEntryAlpine;
        else globalThis.__sidangTaEntryAlpine = previousAdapter;
    }
});
```

Jalankan **sebelum mengganti app.js**:

```bash
node tests/Js/sidang-ta-entry.test.mjs
```

Pada source hasil scan, hasil yang diharapkan adalah FAIL dengan pesan **sidangTaUpload harus terdaftar sebelum Alpine.start()**. Jika gagal karena file/import tidak ditemukan, periksa folder kerja dan kode test dahulu. Setelah perbaikan bagian 3, test yang sama harus lulus.

## 3. Perbaiki registrasi komponen upload

Ganti seluruh `resources/js/app.js` menjadi:

```javascript
import './bootstrap';
import Alpine from 'alpinejs';
import sidangTaWizard, { sidangTaUpload } from './sidang-ta-wizard';

window.Alpine = Alpine;
Alpine.data('sidangTaWizard', sidangTaWizard);
Alpine.data('sidangTaUpload', sidangTaUpload);
Alpine.start();
```

Kedua registrasi harus sebelum satu-satunya `Alpine.start()`. Tidak perlu menambah script Alpine CDN, global `window.sidangTaUpload`, listener `alpine:init` kedua atau mengubah factory upload yang sudah benar. Ini adalah langkah app.js pada 23 yang belum tercermin di source asli hasil scan.

Jalankan kembali:

```bash
node tests/Js/sidang-ta-entry.test.mjs
node tests/Js/sidang-ta-wizard.test.mjs
```

Hasil yang diharapkan: **9 test Node lulus** (1 entry + 8 sebelumnya).

Pastikan halaman memakai JavaScript terbaru. Jika memakai Vite dev server, jalankan `npm run dev` seperti biasa dan muat ulang penuh halaman. Jika memakai aset build, jalankan `npm run build`, lalu muat ulang penuh halaman. Jangan menjalankan kedua mode hanya untuk mengejar cache; gunakan mode yang memang dipakai aplikasi.

Saat membuka tahap 2, Console seharusnya tidak lagi menampilkan `sidangTaUpload is not defined`, `picked is not defined`, atau error turunannya. Klik pilih file harus langsung menampilkan **Pilihan baru · Belum disimpan**, nama dan ukuran, sebelum menekan Simpan. Tombol Unduh baru merujuk file tersimpan setelah save berhasil; jangan menjanjikan pilihan browser sudah terunggah.

## 4. Card Draft tersimpan lebih ringkas dan konfirmasi hapus

Pada `resources/views/sidang_ta_registrations/index.blade.php`, ganti **hanya section Draft tersimpan**, yaitu section kedua yang sekarang diawali `class="campus-card space-y-4 p-5"`. Jangan mengganti section header atau tabel Riwayat Pengajuan.

Lebar section dibatasi `max-w-xl` (36rem); dua tombol berada dalam satu flex row dengan ukuran ringkas. Tombol Hapus tidak langsung submit: ia membuka dialog warning. Fokus awal pada Batal; Escape/Batal menutup tanpa request hapus. Hanya **Ya, hapus draft** yang mengirim form DELETE dengan CSRF dan version yang sama.

```blade
<section data-testid="drafts-panel" class="campus-card w-full max-w-xl space-y-4 p-5">
    <div>
        <h3 class="text-base font-bold text-slate-800">Draft tersimpan</h3>
        <p class="mt-1 text-xs leading-relaxed text-slate-500">Lanjutkan progres yang sudah disimpan sebelum mengirim pendaftaran.</p>
    </div>
    @forelse($drafts as $savedDraft)
        @php
            $source = $savedDraft->sourceRegistration;
            $stale = $savedDraft->source_registration_id !== null
                && (!$source || $source->status !== 'needs_review' || $source->lock_version !== $savedDraft->source_version);
            $writePermission = $savedDraft->source_registration_id === null
                ? 'sidang_ta_registration_create' : 'sidang_ta_registration_update';
        @endphp
        <article class="min-w-0 space-y-3 rounded-xl border border-slate-200 p-4" data-testid="draft-card">
            <div class="space-y-1">
                <p class="break-words text-sm font-semibold text-slate-800">{{ $savedDraft->period?->name ?? 'Periode tidak tersedia' }}</p>
                <p class="text-xs text-slate-500">{{ $savedDraft->source_registration_id ? 'Draft revisi' : 'Draft pendaftaran baru' }}</p>
                <p class="text-xs text-slate-500">Terakhir disimpan {{ $savedDraft->updated_at->format('d/m/Y H:i') }}</p>
            </div>
            @if($stale)
                <p class="rounded-lg bg-amber-50 p-3 text-xs leading-relaxed text-amber-800">Pengajuan asal berubah. Hapus draft lama sebelum memulai revisi kembali.</p>
            @endif
            <div data-draft-actions class="flex items-center gap-2">
                @unless($stale)
                    <a class="btn btn-secondary whitespace-nowrap !px-3 !py-2 !text-xs"
                       href="{{ route('sidang-ta-drafts.edit', $savedDraft) }}">Lanjutkan draft</a>
                @endunless
                @can($writePermission)
                    <button data-open-discard type="button" aria-haspopup="dialog"
                            aria-controls="discard-draft-{{ $savedDraft->id }}"
                            class="btn btn-secondary whitespace-nowrap !border-rose-200 !px-3 !py-2 !text-xs !text-rose-700 hover:!bg-rose-50"
                            onclick="this.closest('article').querySelector('dialog').showModal()">Hapus draft</button>
                @endcan
            </div>
            @can($writePermission)
                <dialog id="discard-draft-{{ $savedDraft->id }}"
                        aria-labelledby="discard-title-{{ $savedDraft->id }}"
                        aria-describedby="discard-description-{{ $savedDraft->id }}"
                        class="m-auto w-11/12 max-w-md rounded-2xl border border-slate-200 bg-white p-5 text-slate-800 shadow-xl backdrop:bg-slate-900/50">
                    <h4 id="discard-title-{{ $savedDraft->id }}" class="text-lg font-bold">Hapus draft ini?</h4>
                    <p class="mt-2 break-words text-sm font-medium text-slate-700">{{ $savedDraft->period?->name ?? 'Periode tidak tersedia' }}</p>
                    <div id="discard-description-{{ $savedDraft->id }}" class="mt-3 space-y-2 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm leading-relaxed text-amber-900">
                        <p>Draft yang dihapus tidak dapat dipulihkan. Progres dan berkas khusus draft ini akan dihapus.</p>
                        <p>Pengajuan yang sudah dikirim beserta berkas finalnya tetap tersimpan.</p>
                    </div>
                    <form method="POST" class="mt-5 flex items-center justify-end gap-2"
                          action="{{ route('sidang-ta-drafts.destroy', $savedDraft) }}">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="version" value="{{ $savedDraft->version }}">
                        <button data-cancel-discard type="button" autofocus class="btn btn-secondary"
                                onclick="this.closest('dialog').close()">Batal</button>
                        <button data-confirm-discard type="submit" class="btn btn-primary">Ya, hapus draft</button>
                    </form>
                </dialog>
            @endcan
        </article>
    @empty
        <p class="text-sm text-slate-500">Belum ada draft tersimpan.</p>
    @endforelse
</section>
```

Dialog native dipilih agar tidak perlu provider Alpine atau dependency tambahan untuk konfirmasi ini. Jangan menambahkan atribut `open`, `method="dialog"` pada form DELETE, atau `formmethod="dialog"` pada tombol Ya: form konfirmasi memang harus mengirim request ke server. Tombol Batal sengaja `type="button"`.

Backend `discard()` tetap memeriksa pemilik, permission, status active dan version. Dialog bukan pengganti guard tersebut. Backend juga sudah mengecualikan file final yang dipinjam draft revisi dari daftar penghapusan; tidak mengubah service untuk memenuhi desain warning.

## 5. Kode lengkap test card draft

Isi `tests/Feature/SidangTaDraftCardTest.php` dengan berikut. Kamu dapat menyalinnya dan menjalankan test pertama sebelum mengganti section pada bagian 4; kontrol dialog belum ada pada view lama sehingga test pertama seharusnya gagal.

```php
<?php

use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDraft;
use App\Models\Student;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->user = User::factory()->create([
        'username' => '1202200001', 'email' => 'draft-card@mahasiswa.test',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false,
    ]);
    Student::create([
        'student_number' => '1202200001', 'name' => 'Draft Card Test',
        'email' => $this->user->email, 'academic_year' => 2022,
        'study_program' => 'Informatika', 'status' => 'Aktif',
    ]);
    foreach (['read', 'create', 'update'] as $action) {
        $this->user->givePermissionTo(Permission::firstOrCreate([
            'name' => "sidang_ta_registration_{$action}", 'guard_name' => 'web',
        ]));
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->period = SidangTaPeriod::create([
        'name' => 'Periode Draft Card', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay(),
    ]);
    $this->actingAs($this->user);
    $this->newDraft = function () {
        $id = $this->postJson(route('sidang-ta-drafts.store'), [
            'sidang_ta_period_id' => $this->period->id,
            'student_phone' => '081234567890', 'parent_phone' => '081234567891',
        ])->assertCreated()->json('data.id');
        return SidangTaRegistrationDraft::findOrFail($id);
    };
});

function sidangTaDraftCardXpath(string $html): DOMXPath
{
    $previous = libxml_use_internal_errors(true);
    try {
        $document = new DOMDocument();
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET);
        return new DOMXPath($document);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
}

test('draft_delete_requires_dialog_confirmation_and_keeps_cancel_non_submitting', function () {
    $draft = ($this->newDraft)();
    $response = $this->get(route('sidang-ta-registrations.index'))->assertOk();
    $xpath = sidangTaDraftCardXpath($response->getContent());
    $actions = $xpath->query('//*[@data-draft-actions]')->item(0);
    expect($actions)->not->toBeNull();
    expect($xpath->query('.//a', $actions)->length)->toBe(1);
    expect($xpath->query('.//button[@data-open-discard and @type="button"]', $actions)->length)->toBe(1);
    expect($xpath->query('.//form', $actions)->length)->toBe(0);

    $dialog = $xpath->query('//dialog[@id="discard-draft-'.$draft->id.'"]')->item(0);
    expect($dialog)->not->toBeNull();
    expect($dialog->hasAttribute('open'))->toBeFalse();
    expect($dialog->textContent)->toContain('tidak dapat dipulihkan');
    expect($xpath->query('.//button[@data-cancel-discard and @type="button" and @autofocus]', $dialog)->length)->toBe(1);
    expect($xpath->query('.//button[@data-confirm-discard and @type="submit"]', $dialog)->length)->toBe(1);
    $form = $xpath->query('.//form', $dialog)->item(0);
    expect($form->getAttribute('method'))->toBe('POST');
    expect($form->getAttribute('action'))->toBe(route('sidang-ta-drafts.destroy', $draft));
    expect($xpath->query('.//input[@name="_method" and @value="DELETE"]', $form)->length)->toBe(1);
    expect($xpath->query('.//input[@name="_token"]', $form)->length)->toBe(1);
    expect($xpath->query('.//input[@name="version"]', $form)->item(0)->getAttribute('value'))->toBe((string) $draft->version);
    expect($draft->fresh())->not->toBeNull();
});

test('read_only_owner_can_see_continue_but_not_delete_controls_or_dialog', function () {
    $draft = ($this->newDraft)();
    $this->user->revokePermissionTo('sidang_ta_registration_create');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($this->user->fresh());
    $response = $this->get(route('sidang-ta-registrations.index'))->assertOk()
        ->assertSee(route('sidang-ta-drafts.edit', $draft), false);
    $xpath = sidangTaDraftCardXpath($response->getContent());
    expect($xpath->query('//*[@data-open-discard]')->length)->toBe(0);
    expect($xpath->query('//dialog')->length)->toBe(0);
});

test('stale_revision_has_delete_confirmation_without_continue_and_preserves_final_registration', function () {
    $source = SidangTaRegistration::create([
        'sidang_ta_period_id' => $this->period->id, 'student_number' => '1202200001',
        'student_name' => 'Draft Card Test', 'student_phone' => '081234567890',
        'parent_phone' => '081234567891', 'graduation_payment_date' => today()->toDateString(),
        'diploma_biodata_accurate' => true, 'status' => 'needs_review',
    ])->fresh();
    $id = $this->postJson(route('sidang-ta-drafts.revision', $source))
        ->assertCreated()->json('data.id');
    $draft = SidangTaRegistrationDraft::findOrFail($id);
    $source->lock_version++;
    $source->save();
    $response = $this->get(route('sidang-ta-registrations.index'))->assertOk()
        ->assertDontSee(route('sidang-ta-drafts.edit', $draft), false);
    $crawler = new \Symfony\Component\DomCrawler\Crawler($response->getContent());
    $card = $crawler->filter('[data-testid="draft-card"]');
    expect($card->count())->toBe(1);
    expect($card->text(null, true))->toContain('Pengajuan asal berubah.');
    $xpath = sidangTaDraftCardXpath($response->getContent());
    expect($xpath->query('//*[@data-open-discard]')->length)->toBe(1);
    expect($xpath->query('//dialog')->length)->toBe(1);
    $this->delete(route('sidang-ta-drafts.destroy', $draft), ['version' => $draft->version])
        ->assertRedirect(route('sidang-ta-registrations.index'));
    expect(SidangTaRegistrationDraft::find($id))->toBeNull();
    expect($source->fresh()->status)->toBe('needs_review');
});
```

Tiga test baru memeriksa struktur aksi yang tidak langsung menghapus, permission UI, dan draft revisi stale. Test regresi version/owner/file final tetap berada di `SidangTaRevisionDraftTest.php`; jangan menghapusnya. Lebar card dan kedua tombol benar-benar satu baris diverifikasi pada browser.

## 6. Command pengujian setelah kedua perbaikan

```bash
php artisan config:clear
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaDraftCardTest.php tests/Feature/SidangTaWizardUiTest.php tests/Feature/SidangTaWizardTest.php tests/Feature/SidangTaRevisionDraftTest.php
node tests/Js/sidang-ta-entry.test.mjs
node tests/Js/sidang-ta-wizard.test.mjs
```

Untuk verifikasi akhir sebelum menyalin, jalankan seluruh test TA:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaDraftSchemaTest.php tests/Feature/SidangTaPeriodTest.php tests/Feature/SidangTaDraftTest.php tests/Feature/SidangTaDraftUploadTest.php tests/Feature/SidangTaRevisionDraftTest.php tests/Feature/SidangTaWizardTest.php tests/Feature/SidangTaFinalizationTest.php tests/Feature/SidangTaWizardUiTest.php tests/Feature/SidangTaDraftCardTest.php
```

Hasil yang diharapkan sesudah diterapkan: 85 definisi test PHP TA dan 9 test Node lulus. Gunakan database `dadu_testing` pada MySQL Podman yang sama, bukan `dadu` aplikasi. Tidak mengubah phpunit.xml sementara dari asisten. Jika sedang memakai aset build, `npm run build` juga harus berhasil dan aset terbaru dimuat.

Command Node sengaja dijalankan per file di atas. Pastikan output menampilkan nama test dan ringkasan **1 test entry** serta **8 test wizard**, bukan hanya satu baris nama berkas. Tidak perlu mengubah package.json.

## 7. Verifikasi browser yang wajib

- [ ] Setelah JavaScript terbaru dimuat, Console tidak memiliki error komponen upload. Klik pilih file: nama dan ukuran pilihan baru langsung muncul **tanpa save**; status tetap Belum disimpan.
- [ ] Seret satu file dari file manager ke kotak: nama langsung muncul. Simpan lalu kembali: file yang sama tersedia untuk unduh.
- [ ] Coba drop dua file atau teks saat sudah ada pilihan baru yang valid: pilihan sebelumnya tetap ada, error lokal terlihat. Batalkan pilihan baru: input kosong, file yang sudah tersimpan tetap dapat diunduh.
- [ ] Tahap 4: cabang tersembunyi tetap disabled; hanya dokumen cabang aktif ikut request.
- [ ] Di index, card Draft tersimpan tidak melebar seluruh halaman. Tombol Lanjutkan draft dan Hapus draft berada satu baris, termasuk pada lebar layar sekitar 320–375px; teks periode panjang membungkus tanpa overflow.
- [ ] Klik Hapus draft: baru muncul warning berisi dampak penghapusan dan dua tombol. **Belum ada request DELETE/POST hapus** pada Network.
- [ ] Batal atau Escape: dialog menutup, draft/file tetap ada, fokus kembali ke tombol Hapus. Buka lagi lalu pilih Ya, hapus draft: baru ada request POST dengan `_method=DELETE` dan draft hilang setelah server berhasil.
- [ ] Pada draft revisi, pengajuan final dan dokumen final tetap tersedia setelah discard. UI tidak menawarkan Lanjutkan untuk draft stale, tetapi pemilik dengan permission update tetap dapat membuangnya.

Setelah berhasil, salin seluruh berkas G1 pada tabel 23 **beserta** `resources/views/sidang_ta_registrations/index.blade.php`, `tests/Feature/SidangTaDraftCardTest.php`, dan `tests/Js/sidang-ta-entry.test.mjs` ke dadu-test. Jangan menyalin `.env`, cache/build, database atau phpunit.xml sementara. Baru minta rescan aktual sebelum G2.

Referensi: urutan registrasi dari [dokumentasi Alpine.data](https://alpinejs.dev/globals/alpine-data) melalui Context7; mekanisme dialog/penutupan dari [MDN dialog](https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Elements/dialog).

## Catatan verifikasi asisten

Test entry baru dijalankan dari isi Markdown terhadap source asli dan **gagal dengan assertion provider upload belum terdaftar**. Test yang sama lulus ketika isi app.js pengganti pada bagian 3 dipasok di memori; file aplikasi tidak diubah. Delapan test factory yang ada di folder asli juga lulus ketika dijalankan langsung melalui Node. Blok JavaScript dan PHP lolos syntax check; Blade section dikompilasi di memori tanpa resolusi component tag dan hasil PHP lolos lint. Test Feature Laravel, browser/dialog aktual, Vite/build dan database belum dijalankan asisten. Kelulusan di memori bukan bukti source aplikasi sudah diperbaiki: pengguna tetap harus menerapkan dan menguji panduan ini.
