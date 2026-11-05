# 58 — Preview gambar contoh dan kontrak revisi terarah

> Pengguna menerapkan kode di folder asli, menguji, lalu menyalin hasil. Asisten hanya menulis docs dan memeriksa proposal di `/tmp`. Source aplikasi tidak diubah asisten.

**Tujuan:** contoh gambar mahasiswa dibuka melalui modal yang sama dengan dokumen; persiapan revisi terarah mempunyai kontrak dan test yang konsisten sebelum dihubungkan ke seluruh jalur penyimpanan/pengiriman.

**Arsitektur:** preview memakai komponen dan event existing, tanpa JavaScript produksi baru. Class kontrak revisi membaca persyaratan historis pengajuan asal, menentukan berkas yang boleh diganti, dan memvalidasi referensi berkas yang tetap terverifikasi.

**Teknologi:** Laravel/Pest, Blade/Alpine existing, MySQL Podman.

## 1. Rescan dan batas pekerjaan

Pengguna mengonfirmasi dua error textarea sudah diperbaiki. Delapan file checkpoint57 sudah identik antara kedua folder; markup textarea dan assertion ketat tetap dipertahankan. Diagnosis57bagian11 ditutup berdasarkan konfirmasi pengguna, bukan dengan mengendurkan assertion.

Audit revisi menemukan beberapa jalur yang masih memakai perilaku lama: penyalinan jenis dokumen, pemeriksaan kelengkapan, penyimpanan upload, dan finalizer. Membuka Minta Revisi sebelum semua jalur itu konsisten dapat mengizinkan penggantian berkas verified atau mereset status yang harus dipertahankan.

Checkpoint58 memisahkan dua hasil yang dapat diuji:

- **Aktif setelah diterapkan:** gambar contoh membuka modal; klik gambar tidak mengirim form atau membuka tab baru.
- **Persiapan yang diuji langsung:** class `SidangTaRevisionContract` beserta delapan test. Class ini belum dipanggil jalur HTTP produksi pada58.

**Minta Revisi dan kirim revisi versi2 tetap ditahan seperti57.** Checkpoint59 akan menghubungkan kontrak ke request/service, navigasi/Tinjau, UI readonly, finalizer, dan tombol petugas bersama-sama. Tidak ada aktivasi sebagian pada58. Draft biasa masih versi1 selama integrasi meet.

Tidak perlu import JSON, migrasi, seeder, atau menyiapkan akun dummy baru. Data JSON resmi yang masih menunggu tetap diperlakukan sesuai ketersediaannya.

## 2. Pembuatan file dan urutan

Kerjakan pada `/home/xdranel/Code/Project/php-laravel/dadu/`.

```bash
php artisan make:class Support/SidangTaRevisionContract
php artisan make:test SidangTaRevisionContractTest --pest
```

- [ ] Isi file test baru pada bagian3; jalankan sekali sebelum helper tersedia untuk melihat kegagalan awal.
- [ ] Isi helper bagian4, kemudian jalankan delapan test tersebut.
- [ ] Terapkan tiga perubahan Blade dan dua tambahan test preview pada bagian5.
- [ ] Jalankan regresi dan manual bagian6–7.
- [ ] Salin tujuh file bagian8 setelah lulus.

Fokus kontrak: rejected yang masih meminjam file lama belum selesai; berkas verified tidak boleh diganti; payload tidak berubah; sumber yang versinya berubah harus ditolak; file hilang atau tipe ekstra tidak boleh lolos transfer. Semua mempunyai assertion pada file baru.

## 3. Test kontrak revisi — file baru lengkap

Seluruh kode berikut masuk ke **`tests/Feature/SidangTaRevisionContractTest.php`**. File mempunyai `beforeEach` sendiri dan tidak ditempel ke file test UI.

```php
<?php

use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDraft;
use App\Models\User;
use App\Support\SidangTaReviewEligibility as Eligibility;
use App\Support\SidangTaRevisionContract as Contract;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Storage::fake('local');
    $this->owner = User::factory()->create();
    $period = SidangTaPeriod::create(['name' => 'Contract', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay()]);
    $this->source = SidangTaRegistration::create(['requirements_version' => 2,
        'sidang_ta_period_id' => $period->id, 'student_number' => '1202299058', 'student_name' => 'Contract',
        'student_phone' => '081234567890', 'parent_phone' => '081234567891',
        'graduation_payment_date' => today()->toDateString(), 'diploma_biodata_accurate' => true,
        'academic_snapshot' => ['admission' => ['eprt' => ['mode' => 'waiver']]], 'status' => 'needs_review'])->fresh();
    $this->payload = ['student_phone' => $this->source->student_phone, 'parent_phone' => $this->source->parent_phone,
        'graduation_payment_date' => today()->toDateString(), 'diploma_biodata_accurate' => true];
    $this->draft = SidangTaRegistrationDraft::create(['owner_user_id' => $this->owner->id,
        'student_number' => $this->source->student_number, 'sidang_ta_period_id' => $period->id,
        'requirements_version' => 2, 'source_registration_id' => $this->source->id,
        'source_version' => $this->source->lock_version, 'payload' => $this->payload])->fresh();
    foreach (Eligibility::requiredTypes($this->source) as $type) {
        $path = 'sidang_ta/contract/'.$type.'.pdf';
        Storage::disk('local')->put($path, '%PDF-contract');
        $doc = $this->source->documents()->create(['document_type' => $type, 'file_name' => $type.'.pdf',
            'file_path' => $path, 'file_size' => 13, 'file_type' => 'pdf',
            'status' => $type === 'ta_data_capture' ? 'rejected' : 'verified',
            'notes' => $type === 'ta_data_capture' ? 'Perbaiki' : null]);
        $this->draft->documents()->create(['document_type' => $type, 'file_name' => $doc->file_name,
            'file_path' => $path, 'file_size' => 13, 'file_type' => 'pdf', 'source_document_id' => $doc->id]);
    }
    $this->replaceRequested = function () {
        $path = 'sidang_ta/contract/replacement.pdf';
        Storage::disk('local')->put($path, '%PDF-new');
        $document = $this->draft->documents()->where('document_type', 'ta_data_capture')->firstOrFail();
        $document->update(['file_path' => $path, 'source_document_id' => null]);
        return $document;
    };
});

test('revision_contract_freezes_document_groups_to_the_stored_waiver_mode', function () {
    expect(Contract::isTargeted($this->draft))->toBeTrue();
    expect(Contract::typesForStep($this->draft, 2))->toBe(['ta_data_capture', 'online_supervision_capture']);
    expect(Contract::typesForStep($this->draft, 3))->toContain('eprt_waiver_letter')->toHaveCount(4);
    expect(Contract::editableTypes($this->draft))->toBe(['ta_data_capture']);
});

test('revision_contract_requires_rejected_documents_and_all_other_files_verified', function () {
    Contract::assertCanRequest($this->source);
    $doc = $this->source->documents()->where('document_type', 'thesis_draft')->firstOrFail();
    $doc->update(['status' => 'pending']);
    expect(fn () => Contract::assertCanRequest($this->source))->toThrow(ValidationException::class);
});

test('revision_contract_cannot_request_revision_without_any_rejected_document', function () {
    $this->source->documents()->update(['status' => 'verified', 'notes' => null]);
    expect(fn () => Contract::assertCanRequest($this->source))->toThrow(ValidationException::class);
});

test('revision_contract_borrowed_rejected_file_does_not_count_as_a_replacement', function () {
    $doc = $this->draft->documents()->where('document_type', 'ta_data_capture')->firstOrFail();
    expect(Contract::replacementRequired($this->draft, $doc))->toBeTrue();
    expect(fn () => Contract::assertTransferReady($this->draft))->toThrow(ValidationException::class);
    $replacement = ($this->replaceRequested)();
    expect(Contract::replacementRequired($this->draft, $replacement))->toBeFalse();
    Contract::assertTransferReady($this->draft);
});

test('revision_contract_frozen_payload_accepts_equivalent_html_boolean_but_rejects_changes', function () {
    Contract::assertPayloadUnchanged($this->draft, array_replace($this->payload, ['diploma_biodata_accurate' => '1']));
    foreach (['student_phone' => '089999999999', 'parent_phone' => '088888888888',
        'graduation_payment_date' => today()->subDay()->toDateString(), 'diploma_biodata_accurate' => false] as $key => $value) {
        expect(fn () => Contract::assertPayloadUnchanged($this->draft, array_replace($this->payload, [$key => $value])))
            ->toThrow(ValidationException::class);
    }
});

test('revision_contract_verified_file_must_keep_its_source_reference', function () {
    ($this->replaceRequested)();
    $doc = $this->draft->documents()->where('document_type', 'thesis_draft')->firstOrFail();
    $doc->update(['source_document_id' => null]);
    expect(fn () => Contract::assertTransferReady($this->draft))->toThrow(ValidationException::class);
});

test('revision_contract_missing_replacement_or_extra_document_blocks_transfer', function () {
    $replacement = ($this->replaceRequested)();
    Storage::disk('local')->delete($replacement->file_path);
    expect(fn () => Contract::assertTransferReady($this->draft))->toThrow(ValidationException::class);
    Storage::disk('local')->put($replacement->file_path, '%PDF-new');
    $this->draft->documents()->create(['document_type' => 'transcript_khs', 'file_name' => 'extra.pdf',
        'file_path' => 'extra.pdf', 'file_type' => 'pdf']);
    expect(fn () => Contract::assertTransferReady($this->draft))->toThrow(ValidationException::class);
});

test('revision_contract_rejects_stale_source_version_instead_of_using_cached_relation', function () {
    $this->draft->load('sourceRegistration');
    $this->source->lock_version++;
    $this->source->save();
    try {
        Contract::source($this->draft);
        $this->fail('A stale source must be rejected.');
    } catch (Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception) {
        expect($exception->getStatusCode())->toBe(409);
    }
});
```

Jalankan test tersebut sebelum dan sesudah mengisi helper:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaRevisionContractTest.php
```
Target setelah helper terisi: **8 passed**. Fixture menggunakan pengajuan needs_review langsung untuk menguji kontrak internal, bukan membuka tombol/API revisi pada aplikasi.

## 4. Helper kontrak — file baru lengkap

Isi **`app/Support/SidangTaRevisionContract.php`**:

```php
<?php

namespace App\Support;

use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDraft;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SidangTaRevisionContract
{
    public static function isTargeted(SidangTaRegistrationDraft $draft): bool
    {
        return $draft->requirements_version === 2 && $draft->source_registration_id !== null;
    }

    public static function source(SidangTaRegistrationDraft $draft): SidangTaRegistration
    {
        abort_unless(self::isTargeted($draft), 409, 'Draft bukan revisi terarah.');
        $source = SidangTaRegistration::whereKey($draft->source_registration_id)->firstOrFail();
        abort_unless($source->requirements_version === 2
            && $source->status === 'needs_review'
            && $source->student_number === $draft->student_number
            && $source->lock_version === $draft->source_version, 409,
            'Pengajuan asal berubah. Muat ulang revisi.');
        return $source;
    }

    public static function typesForStep(SidangTaRegistrationDraft $draft, int $step): array
    {
        $groups = array_values(SidangTaReviewEligibility::groups(self::source($draft)));
        return in_array($step, [2, 3, 4], true) ? $groups[$step - 2] : [];
    }

    public static function editableTypes(SidangTaRegistrationDraft $draft): array
    {
        $source = self::source($draft);
        return $source->documents()->whereIn('document_type', SidangTaReviewEligibility::requiredTypes($source))
            ->where('status', 'rejected')->pluck('document_type')->all();
    }

    public static function assertPayloadUnchanged(SidangTaRegistrationDraft $draft, array $mergedPayload): void
    {
        $source = self::source($draft);
        $expected = ['student_phone' => $source->student_phone, 'parent_phone' => $source->parent_phone,
            'graduation_payment_date' => $source->graduation_payment_date?->format('Y-m-d'),
            'diploma_biodata_accurate' => $source->diploma_biodata_accurate];
        $errors = [];
        foreach ($expected as $key => $value) {
            $actual = $mergedPayload[$key] ?? null;
            // Accept the validated boolean equivalents sent by HTML forms.
            if ($key === 'diploma_biodata_accurate' && in_array($actual, [true, false, 0, 1, '0', '1'], true)) {
                $actual = (bool) $actual;
            }
            if ($actual !== $value) $errors[$key] = 'Isian ini mengikuti pengajuan asal dan tidak dapat diubah pada revisi.';
        }
        if ($errors !== []) throw ValidationException::withMessages($errors);
    }

    public static function replacementRequired(SidangTaRegistrationDraft $draft, $document): bool
    {
        if (!self::isTargeted($draft)) return false;
        return in_array($document->document_type, self::editableTypes($draft), true)
            && $document->source_document_id !== null;
    }

    public static function assertCanRequest(SidangTaRegistration $source): void
    {
        $documents = $source->documents()->get();
        $errors = [];
        $rejected = 0;
        foreach (SidangTaReviewEligibility::requiredTypes($source) as $type) {
            $document = $documents->firstWhere('document_type', $type);
            if (!$document) {
                $errors['documents.'.$type] = 'Metadata dokumen belum tersedia.';
            } elseif ($document->status === 'rejected') {
                $rejected++;
            } elseif ($document->status !== 'verified' || !Storage::disk('local')->exists($document->file_path)) {
                $errors['documents.'.$type] = 'Selesaikan verifikasi atau tandai dokumen untuk diperbaiki terlebih dahulu.';
            }
        }
        if ($rejected === 0) $errors['action'] = 'Tandai minimal satu dokumen untuk diperbaiki sebelum meminta revisi.';
        if ($errors !== []) throw ValidationException::withMessages($errors);
    }

    public static function assertTransferReady(SidangTaRegistrationDraft $draft): void
    {
        $source = self::source($draft);
        self::assertCanRequest($source);
        self::assertPayloadUnchanged($draft, $draft->payload ?? []);
        $required = SidangTaReviewEligibility::requiredTypes($source);
        $sourceDocuments = $source->documents()->get()->keyBy('document_type');
        $documents = $draft->documents()->get();
        $actual = $documents->pluck('document_type')->all();
        $expected = $required;
        sort($actual); sort($expected);
        if ($actual !== $expected) throw ValidationException::withMessages(['draft' => 'Daftar dokumen revisi tidak sesuai pengajuan asal.']);
        $errors = [];
        foreach ($documents as $document) {
            $original = $sourceDocuments->get($document->document_type);
            if (!Storage::disk('local')->exists($document->file_path)) {
                $errors['documents.'.$document->document_type] = 'Berkas tidak ditemukan. Muat ulang revisi.';
            } elseif ($original->status === 'rejected') {
                if ($document->source_document_id !== null || $document->file_path === $original->file_path) {
                    $errors['documents.'.$document->document_type] = 'Unggah berkas pengganti untuk dokumen yang diminta diperbaiki.';
                }
            } elseif ($original->status !== 'verified'
                || (int) $document->source_document_id !== (int) $original->id
                || $document->file_path !== $original->file_path) {
                $errors['documents.'.$document->document_type] = 'Dokumen yang tidak direvisi harus tetap mengikuti berkas terverifikasi.';
            }
        }
        if ($errors !== []) throw ValidationException::withMessages($errors);
    }
}
```
### Ketentuan integrasi59

Helper ini bukan pengganti ownership, permission, atau transaksi. Caller59 wajib memanggil guard Access terlebih dahulu dan mengikuti urutan lock Student → draft → source, kemudian memvalidasi kembali sebelum menulis. `source()` membaca sumber baru dari DB tetapi tidak memperoleh lock sendiri. `assertCanRequest()` sengaja tidak membatasi status, supaya nanti dapat dipanggil sebelum submitted diubah menjadi needs_review; status dan versi harus diperiksa service pemanggil.

Kontrak dokumen revisi mengikuti snapshot EPRT dan pilihan biodata saat pengajuan awal. Perubahan data EPRT terkini tidak boleh diam-diam menghapus surat verified atau menambah persyaratan upload pada kartu yang terkunci. Pengiriman awal tetap memakai pemeriksaan EPRT terbaru seperti56; pada integrasi revisi59, lima gate akademik diperiksa ulang, sedangkan kontrak dokumen/EPRT pengajuan asal dan snapshot historis dipertahankan. Kebijakan ini menjaga revisi tetap terbatas pada berkas yang diminta petugas.

Pada59, permintaan revisi hanya boleh dikirim jika sedikitnya satu dokumen rejected dan dokumen wajib lainnya verified beserta file yang tersedia. Setelah mahasiswa mengirim revisi, hanya pengganti yang kembali pending; berkas yang tidak direvisi tetap verified. Scalar kontak/tanggal/pilihan biodata tetap mengikuti pengajuan asal.

Jangan menghapus guard pada `RegistrationReview`, mengubah finalizer, atau memasang helper ke satu jalur saja pada58. Pada59 helper harus dipakai bersama oleh request/service/progress/UI/final agar daftar persyaratan dan izin upload tidak berbeda.

API error validasi mengikuti [ValidationException Laravel13](https://laravel.com/docs/13.x/validation#manually-creating-validators), diperiksa melalui Context7 serta pola existing pada project.

## 5. Preview gambar contoh — perubahan aktif


Tiga view berubah. Tidak ada route/controller/JavaScript produksi baru. Dua gambar contoh sudah tersedia pada kedua folder: `public/assets/images/forms/sidang-ta/ta-data.png` dan `bimbingan-online.png`. Pertahankan kedua asset tersebut pada salinan/commit; test render mengharapkan keduanya tersedia.

Gambar contoh menggunakan button biasa agar dapat diaktifkan klik/Enter/Space, tidak mengirim form, dan tidak menavigasi ke tab baru. Modal memakai handler existing `sidangTaDocumentPreview`, dengan fileType png dan downloadUrl kosong. Tautan Unduh disembunyikan untuk contoh; dokumen mahasiswa tetap menampilkan Unduh seperti sebelumnya. Opsi Buka di tab baru tetap opsional di dalam modal seperti pada preview dokumen, bukan aksi awal ketika gambar diklik.

### `resources/views/sidang_ta_registrations/partials/wizard-upload.blade.php`

Cari:

```blade
        <a data-upload-guide href="{{ asset($guide) }}" target="_blank" rel="noopener" class="mt-3 block">
            <img loading="lazy" src="{{ asset($guide) }}" alt="Panduan {{ $label }}"
                 class="w-full rounded-lg border border-slate-200 object-contain">
        </a>
```

Ganti dengan:

```blade
        <button data-upload-guide data-document-preview type="button"
                class="mt-3 block w-full rounded-lg text-left focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2"
                data-preview-url="{{ asset($guide) }}" data-download-url=""
                data-file-name="Contoh {{ $label }}" data-file-type="png"
                aria-label="Lihat contoh {{ $label }}" aria-haspopup="dialog"
                x-on:click="$dispatch('sidang-ta-preview', $el.dataset)">
            <img loading="lazy" src="{{ asset($guide) }}" alt="Panduan {{ $label }}"
                 class="w-full rounded-lg border border-slate-200 object-contain">
            <span class="mt-1 block text-xs font-medium text-[#DA2D3B]">Lihat contoh</span>
        </button>
```

### `resources/views/sidang_ta_registrations/wizard.blade.php`

Cari:

```blade
</x-app-layout>
```

Ganti dengan:

```blade
    @include('sidang_ta_registrations.partials.document-preview-modal')
</x-app-layout>
```

### `resources/views/sidang_ta_registrations/partials/document-preview-modal.blade.php`

Cari:

```blade
                <a data-preview-download :href="downloadUrl" class="btn btn-secondary">
```

Ganti dengan:

```blade
                <a data-preview-download x-show="downloadUrl" x-cloak :href="downloadUrl" class="btn btn-secondary">
```

### `resources/views/sidang_ta_registrations/partials/document-preview-modal.blade.php`

Cari:

```blade
        <p class="mt-3 text-xs leading-relaxed text-slate-500">Jika berkas tidak tampil, gunakan Buka di tab baru atau Unduh untuk memeriksanya.</p>
```

Ganti dengan:

```blade
        <p class="mt-3 text-xs leading-relaxed text-slate-500"
           x-text="downloadUrl ? 'Jika berkas tidak tampil, gunakan Buka di tab baru atau Unduh untuk memeriksanya.' : 'Gambar ini merupakan contoh pengisian. Tekan Tutup untuk kembali ke form.'"></p>
```

Include modal di akhir wizard hanya **satu kali**. Button contoh tetap berada di luar fieldset disabled/upload-only restriction saat draft revisi, sehingga mahasiswa tetap dapat melihat contoh walaupun card upload terkunci.

### Test Feature — tambahkan ke file yang mempunyai helper meetDraft

Tambahkan test berikut satu kali pada **`tests/Feature/SidangTaMeetUiTest.php`**. File itu sudah mempunyai `beforeEach`, helper `meetDraft`, serta import `Crawler`. Jangan menambahkannya ke `SidangTaWizardUiTest.php`.

```php
test('meet_ui_capture_examples_open_the_shared_modal_without_navigation_or_form_submission', function () {
    $draft = ($this->meetDraft)();
    $crawler = new Crawler($this->get(route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 2]))
        ->assertOk()->getContent());
    foreach (['ta_data_capture' => 'ta-data.png', 'online_supervision_capture' => 'bimbingan-online.png'] as $type => $filename) {
        $guide = $crawler->filter('[data-upload-type="'.$type.'"] button[data-upload-guide]');
        expect($guide->count())->toBe(1);
        expect($guide->attr('type'))->toBe('button');
        expect($guide->getNode(0)->hasAttribute('href'))->toBeFalse();
        expect($guide->getNode(0)->hasAttribute('target'))->toBeFalse();
        expect($guide->attr('aria-haspopup'))->toBe('dialog');
        expect($guide->attr('data-preview-url'))->toBe(asset('assets/images/forms/sidang-ta/'.$filename));
        expect($guide->attr('data-download-url'))->toBe('');
        expect($guide->attr('data-file-type'))->toBe('png');
        expect($guide->attr('x-on:click'))->toContain("sidang-ta-preview");
        expect($guide->filter('img')->attr('src'))->toBe($guide->attr('data-preview-url'));
        expect($guide->filter('img')->attr('alt'))->not->toBe('');
    }
    expect($crawler->filter('[data-document-preview-modal]')->count())->toBe(1);
    expect($crawler->filter('[data-preview-download]')->attr('x-show'))->toBe('downloadUrl');
});
```
### Test Node — tambahkan ke file existing

Tambahkan test berikut ke **`tests/Js/sidang-ta-document-preview.test.mjs`**. Gunakan helper `setup`, import `test`/`assert`, dan tiga test yang sudah ada. File ini sekarang berjumlah empat test.

```javascript
test('guide image reuses the document dialog without a download URL or extra opening', () => {
    const { state, calls } = setup();
    state.open({ previewUrl: '/documents/1/preview', downloadUrl: '/documents/1/download', fileName: 'a.pdf', fileType: 'pdf' });
    state.open({ previewUrl: '/assets/images/forms/sidang-ta/ta-data.png', downloadUrl: '',
        fileName: 'Contoh Data TA', fileType: 'png' });
    assert.equal(state.previewUrl, '/assets/images/forms/sidang-ta/ta-data.png');
    assert.equal(state.downloadUrl, '');
    assert.equal(state.fileName, 'Contoh Data TA');
    assert.equal(state.isPdf, false);
    assert.deepEqual(calls, ['show']);
    state.close();
    state.reset();
    assert.equal(state.previewUrl, '');
    assert.equal(state.downloadUrl, '');
});
```
### Manual preview

1. Mahasiswa membuka tahap2 dari draft versi2. Gambar contoh terlihat untuk Data TA dan Bimbingan Online.
2. Klik gambar: modal preview yang sama dengan dokumen terbuka, gambar lebih besar terlihat, tidak ada tab baru atau submit form. Nama contoh sesuai card.
3. Klik Tutup atau tekan Escape: kembali ke tahap yang sama, file yang sudah dipilih dan dirty state tetap terjaga.
4. Fokuskan button contoh menggunakan Tab, lalu Enter/Space: modal juga terbuka.
5. Pada tinjauan/dokumen final, preview PDF/gambar dan tombol Unduh tetap berfungsi seperti sebelumnya.
Catatan integrasi59: button contoh harus tetap di luar kontrol upload yang disabled, agar contoh tetap dapat dilihat pada kartu revisi readonly. Ini belum menjadi skenario manual58 karena revisi terarah belum dibuka.

### Pemeriksaan asisten

Empat Node test preview dijalankan terhadap modul produksi existing tanpa mengubah source dan lulus. Test Feature tambahan lolos PHP lint; tiga Blade dikompilasi di memori. Feature DB/build/browser tidak dijalankan asisten. Prototype dan dokumen bagian ini hanya ditulis di `/tmp`; source aplikasi/asset/test tidak diubah.

## 6. Verifikasi gabungan

### A. Helper dan UI mahasiswa

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaRevisionContractTest.php tests/Feature/SidangTaMeetUiTest.php tests/Feature/SidangTaMeetStaffReviewTest.php
```
Target **48 passed**: 8kontrak +16UI meet +24petugas. File MeetUi bertambah satu test, dari15menjadi16; file StaffReview tetap24.

### B. Regresi preview dan alur lama

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaDocumentPreviewTest.php tests/Feature/SidangTaWizardUiTest.php tests/Feature/SidangTaMeetFinalizationTest.php tests/Feature/SidangTaRevisionDraftTest.php
```
### C. Seluruh Sidang TA dan Node

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTa*Test.php tests/Unit/SidangTaAdmissionPolicyTest.php tests/Unit/SidangTaMeetRequirementsTest.php
```
Ada sembilan kasus Feature baru: delapan kontrak dan satu preview. Jika baseline57berjumlah321, cakupan yang sama menjadi **330 passed**.

```bash
node tests/Js/sidang-ta-entry.test.mjs
node tests/Js/sidang-ta-wizard.test.mjs
node tests/Js/sidang-ta-document-preview.test.mjs
php artisan view:clear
```

Preview Node sekarang4test; total Node sebelumnya12menjadi13. Jalankan Vite seperti biasa. Jika menggunakan asset hasil build, jalankan `npm run build`. File PNG sudah ada; tidak perlu membuat gambar baru atau mengubah asset gambar.

## 7. Urutan manual ringkas

1. Buka draft mahasiswa versi2 tahap2 seperti prosedur55/56b. Kedua contoh gambar terlihat.
2. Klik contoh Data TA dan Bimbingan Online. Modal yang sama terbuka dengan gambar yang benar; halaman/tahap tidak berubah dan tidak ada submit form.
3. Tutup dengan tombol atau Escape; file baru yang sedang dipilih belum disimpan tetap berada pada form. Coba juga fokus Tab lalu Enter/Space pada contoh.
4. Khusus contoh, Unduh disembunyikan. Pada dokumen mahasiswa di Tinjau/detail/petugas, Unduh dan preview PDF/gambar tetap bekerja.
5. Tidak perlu menekan Minta Revisi untuk menguji helper. Tombol itu masih disabled pada versi2 sesuai57. Delapan kasus internal menguji kontrak sebelum integrasinya pada59.

## 8. Pemindahan setelah lulus

Salin tujuh file dari folder asli ke `dadu-test`:

- `app/Support/SidangTaRevisionContract.php`
- `tests/Feature/SidangTaRevisionContractTest.php`
- `resources/views/sidang_ta_registrations/partials/wizard-upload.blade.php`
- `resources/views/sidang_ta_registrations/wizard.blade.php`
- `resources/views/sidang_ta_registrations/partials/document-preview-modal.blade.php`
- `tests/Feature/SidangTaMeetUiTest.php`
- `tests/Js/sidang-ta-document-preview.test.mjs`

Tidak ada file yang dihapus. Kedua asset PNG sudah identik di kedua folder; pastikan tetap disertakan ketika nanti melakukan commit. `.env`, cache, build artifacts, dan pengaturan testing tidak perlu dipindahkan sebagai perubahan source.

## 9. Rescan dan pemeriksaan asisten

Sebelum penulisan: delapan file57 identik, markup perbaikan textarea dipertahankan, kontrak preview dan keberadaan dua PNG diperiksa. Source pada kedua folder direkam hash dan diperiksa kembali setelah penulisan/perubahan setiap docs.

Helper dan test kontrak lolos PHP lint; proposal tiga Blade dikompilasi dalam memori. Probe render156assertion memeriksa wizard/Tinjau lintas versi dan preview contoh, ditambah22assertion beranda/panel. Empat test Node preview terhadap modul existing lulus. Pemeriksaan independen kontrak dan fixture tidak menemukan blocker.

Asisten tidak menjalankan Laravel bootstrap, Feature DB, build, atau browser. Target48/330 harus dibuktikan setelah kode diterapkan. Prototype hanya berada di `/tmp`; penulisan pada project hanya docs.

Tahap berikutnya59: integrasi revisi terarah secara menyeluruh, membuka Minta Revisi serta pengiriman revisi versi2. Deadline otomatis dan aktivasi pendaftaran biasa menyusul sesudah jalur revisi lengkap. Belum waktunya commit penutupan fitur meet.
