# 35 — Preview dokumen TA dan penutup bagian student

**Goal:** hilangkan badge angka pada card tinjau; aksi utama dokumen menjadi Lihat berkas pada tinjau/detail, dengan Unduh dan Buka di tab baru di dalam modal.

**Architecture:** dua endpoint inline terotorisasi memakai disk local yang sama dengan download. Satu partial modal TA dan satu Alpine provider dipakai dua halaman student, dapat digunakan kembali pada halaman petugas nanti. Unduh tetap endpoint attachment, bukan dipaksa melalui JavaScript/blob.

**Tech stack:** Laravel 13, Blade, Alpine3, Tailwind3, Pest, Node test existing. Tidak menambah dependency/migrasi atau memindahkan berkas ke public.

**Spec:** rekomendasi preview dan badge telah disetujui pengguna setelah S2. Asisten hanya menulis docs; terapkan kode di project asli, lalu salin sesudah test/manual lulus.

**Pembaruan sesudah penerapan:** pengguna melaporkan124 PHP/12 Node/manual lulus. Rescan [36](36-rescan-preview-sebelum-commit-student.md) menemukan include modal terpasang sebelum layout, wrapper card index yang masih tertinggal, dan salah lokasi salinan test Node di dadu-test. Ikuti koreksi36 sebelum commit; dua regression test tambahan menaikkan target suite menjadi126, fokus29. Target124 di bagian sebelumnya merupakan baseline sebelum koreksi36.

## 1. Rescan S2 dan batas checkpoint

Pengguna melaporkan fokus35, suite116 dan manual S2 lulus. Source TA app/resources/routes/tests asli dan dadu-test identik; ditemukan116 definisi Feature. Tidak ada kebutuhan mengulang penerapan34.

Yang berubah:

- Badge angka2–4 pada header card tinjau dihapus; judul, jumlah berkas, teks Tahap dan tautan Ubah tahap tetap untuk orientasi. Tidak menambahkan badge1.
- Lihat berkas membuka modal PDF/gambar; jika JavaScript belum aktif, tautan tetap membuka preview di tab baru.
- Unduh dan Buka di tab baru berada di modal. Pesan bantuan selalu tersedia bila browser tidak dapat menampilkan PDF/gambar.
- Endpoint final mengizinkan permission read **atau** review, lalu tetap memeriksa pemilik atau reviewer. Reviewer-only boleh membaca final; tidak memperoleh akses draft mahasiswa. Permission periode saja tidak memberi akses berkas.
- Tidak mengubah status, version, reviewed_version, snapshot atau workflow kirim. Petugas dashboard/verifikasi masih checkpoint P1–P3 berikutnya.

## 2. File, command pembuatan dan urutan

Edit existing:

```text
routes/web.php
app/Http/Controllers/SidangTaDraftController.php
app/Http/Controllers/SidangTaRegistrationController.php
resources/js/sidang-ta-wizard.js
resources/js/app.js
resources/views/sidang_ta_registrations/wizard-review.blade.php
resources/views/sidang_ta_registrations/show.blade.php
tests/Feature/SidangTaReviewUiTest.php
tests/Feature/SidangTaDetailUiTest.php
tests/Js/sidang-ta-entry.test.mjs
```

Buat baru:

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
php artisan make:view sidang_ta_registrations.partials.document-preview-modal
php artisan make:test SidangTaDocumentPreviewTest --pest
touch tests/Js/sidang-ta-document-preview.test.mjs
```

- [ ] Isi test baru bagian8–9 serta pengganti assertion bagian7. Jalankan sebelum implementasi; route/provider/elemen baru semestinya belum ada.
- [ ] Terapkan bagian3–6.
- [ ] Jalankan fokus, suite lengkap, Node/build dan manual bagian10. Baru salin untuk rescan.

## 3. Dua endpoint preview dan middleware final download

Di grup route terautentikasi yang sama dengan download TA, tambahkan kedua route berikut. Jangan membuat route publik atau storage symlink baru.

```php
Route::get('sidang-ta-draft-documents/{document}/preview',
    [SidangTaDraftController::class, 'previewDocument'])
    ->middleware('permission:sidang_ta_registration_read')
    ->name('sidang-ta-draft-documents.preview');
Route::get('sidang-ta-registration-documents/{document}/preview',
    [\App\Http\Controllers\SidangTaRegistrationController::class, 'previewDocument'])
    ->middleware('permission:sidang_ta_registration_read|sidang_ta_registration_review')
    ->name('sidang-ta-registration-documents.preview');
```

Pada route existing `sidang-ta-registration-documents.download`, ganti hanya middleware read menjadi:

```php
->middleware('permission:sidang_ta_registration_read|sidang_ta_registration_review')
```

Jangan menambah route download duplikat. Download draft tetap read+owner. Route show student/admin lainnya tidak diubah pada checkpoint ini.

Tambahkan method berikut pada `SidangTaDraftController`, di dekat downloadDocument. Import Request, model dan Storage sudah ada:

```php
public function previewDocument(Request $request, SidangTaRegistrationDraftDocument $document)
{
    $this->service->owned($request->user(), $document->draft);
    $disk = Storage::disk('local');
    abort_unless($disk->exists($document->file_path), 404);
    $mime = $disk->mimeType($document->file_path);
    abort_unless(in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true), 415);
    return $disk->response($document->file_path, $document->file_name, [
        'Content-Type' => $mime,
        'X-Content-Type-Options' => 'nosniff',
        'Cache-Control' => 'private, no-store',
    ], 'inline');
}
```

Tambahkan pada `SidangTaRegistrationController`, di dekat downloadDocument. Import model final dan Storage sudah ada:

```php
public function previewDocument(SidangTaRegistrationDocument $document)
{
    $this->authorizeStudentOrReviewer($document->registration);
    $disk = Storage::disk('local');
    abort_unless($disk->exists($document->file_path), 404);
    $mime = $disk->mimeType($document->file_path);
    abort_unless(in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true), 415);
    return $disk->response($document->file_path, $document->file_name, [
        'Content-Type' => $mime,
        'X-Content-Type-Options' => 'nosniff',
        'Cache-Control' => 'private, no-store',
    ], 'inline');
}
```

MIME diperiksa dari isi file, bukan nama upload. File di luar PDF/JPEG/PNG tidak ditampilkan inline. Storage response menghasilkan Content-Disposition inline dengan nama aman; download existing tetap attachment. Guard pemilik/source draft dan pemilik/reviewer final tidak dilewati.

## 4. Alpine provider dan registrasi

Tambahkan export berikut pada bagian akhir `resources/js/sidang-ta-wizard.js`. Pertahankan default wizard dan export sidangTaUpload:

```js
export const sidangTaDocumentPreview = () => ({
    previewUrl: '',
    downloadUrl: '',
    fileName: '',
    isPdf: false,
    open(data) {
        this.previewUrl = data.previewUrl;
        this.downloadUrl = data.downloadUrl;
        this.fileName = data.fileName;
        this.isPdf = String(data.fileType).toLowerCase() === 'pdf';
        if (!this.$refs.dialog.open) this.$refs.dialog.showModal();
    },
    close() {
        this.$refs.dialog.close();
    },
    reset() {
        this.previewUrl = '';
        this.downloadUrl = '';
        this.fileName = '';
        this.isPdf = false;
    },
});
```

Ganti seluruh `resources/js/app.js` dengan berikut (sama dengan existing, ditambah satu named import/registrasi):

```js
import './bootstrap';
import Alpine from 'alpinejs';
import sidangTaWizard, { sidangTaUpload, sidangTaDocumentPreview } from './sidang-ta-wizard';

window.Alpine = Alpine;
Alpine.data('sidangTaWizard', sidangTaWizard);
Alpine.data('sidangTaUpload', sidangTaUpload);
Alpine.data('sidangTaDocumentPreview', sidangTaDocumentPreview);
Alpine.start();
```

Tidak memakai fetch/blob untuk preview/unduh. File baru dimuat ketika modal dibuka. Penutupan native, tombol Tutup dan Escape memicu event close yang membersihkan URL viewer.

## 5. Partial modal lengkap

Isi `resources/views/sidang_ta_registrations/partials/document-preview-modal.blade.php`:

```blade
<div x-data="sidangTaDocumentPreview" x-on:sidang-ta-preview.window="open($event.detail)">
    <dialog data-document-preview-modal x-ref="dialog" x-on:close="reset()"
            aria-labelledby="ta-preview-title"
            class="m-auto max-h-[95vh] w-11/12 max-w-5xl overflow-y-auto rounded-2xl border border-slate-200 bg-white p-4 shadow-xl backdrop:bg-slate-900/50 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 pb-4">
            <h2 id="ta-preview-title" class="min-w-0 max-w-full break-words text-base font-bold text-slate-900" x-text="fileName">Preview berkas</h2>
            <div class="flex flex-wrap items-center gap-2">
                <a data-preview-download :href="downloadUrl" class="btn btn-secondary">Unduh</a>
                <a data-preview-new-tab :href="previewUrl" target="_blank" rel="noopener" class="btn btn-secondary">Buka di tab baru</a>
                <button data-preview-close type="button" autofocus x-on:click="close()" class="btn btn-primary">Tutup</button>
            </div>
        </div>
        <div class="mt-4 min-h-40 rounded-xl bg-slate-50 p-2">
            <template x-if="previewUrl && isPdf">
                <iframe :src="previewUrl" title="Pratinjau dokumen PDF" class="h-[65vh] w-full rounded-lg border border-slate-200 bg-white"></iframe>
            </template>
            <template x-if="previewUrl && !isPdf">
                <img :src="previewUrl" :alt="fileName" class="mx-auto max-h-[65vh] max-w-full rounded-lg object-contain">
            </template>
        </div>
        <p class="mt-3 text-xs leading-relaxed text-slate-500">Jika berkas tidak tampil, gunakan Buka di tab baru atau Unduh untuk memeriksanya.</p>
    </dialog>
</div>
```

## 6. Dua halaman pemakai

### Tinjau: badge dan link dokumen

Pada `resources/views/sidang_ta_registrations/wizard-review.blade.php`, hapus hanya elemen badge:

```blade
<span aria-hidden="true" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-rose-50 text-sm font-bold text-[#DA2D3B]">{{ $number }}</span>
```

Pertahankan h3 judul, subtitle Tahap/jumlah dan Ubah tahap. Jangan mengubah `$groups` atau key `data-review-group` karena masih dipakai pemetaan tahap/test.

Ganti anchor `data-document-download` pada loop dokumen dengan:

```blade
<a data-document-preview
   class="btn btn-secondary shrink-0 self-start whitespace-nowrap !text-xs no-underline sm:self-auto"
   href="{{ route('sidang-ta-draft-documents.preview', $document) }}"
   data-preview-url="{{ route('sidang-ta-draft-documents.preview', $document) }}"
   data-download-url="{{ route('sidang-ta-draft-documents.download', $document) }}"
   data-file-name="{{ $document->file_name }}" data-file-type="{{ $document->file_type }}"
   target="_blank" rel="noopener" aria-haspopup="dialog"
   aria-label="Lihat {{ \App\Models\SidangTaRegistration::DOCUMENT_TYPES[$type] }}"
   x-on:click.prevent="$dispatch('sidang-ta-preview', $el.dataset)">Lihat berkas</a>
```

### Detail: link dokumen

Pada **`resources/views/sidang_ta_registrations/show.blade.php`**, bukan `admin/show.blade.php`, ganti anchor `data-final-download` dengan:

```blade
<a data-document-preview
   class="btn btn-secondary shrink-0 self-start whitespace-nowrap !text-xs no-underline sm:self-auto"
   href="{{ route('sidang-ta-registration-documents.preview', $document) }}"
   data-preview-url="{{ route('sidang-ta-registration-documents.preview', $document) }}"
   data-download-url="{{ route('sidang-ta-registration-documents.download', $document) }}"
   data-file-name="{{ $document->file_name }}" data-file-type="{{ $document->file_type }}"
   target="_blank" rel="noopener" aria-haspopup="dialog"
   x-on:click.prevent="$dispatch('sidang-ta-preview', $el.dataset)">Lihat berkas</a>
```

Pada **masing-masing** kedua file tersebut, tambahkan include tepat sebelum `</x-app-layout>` (di luar container utama, satu kali per halaman):

```blade
@include('sidang_ta_registrations.partials.document-preview-modal')
```

Filename diteruskan melalui atribut Blade escaped dan dataset, bukan disisipkan ke string JavaScript. PDF/gambar sama-sama dapat diunduh melalui modal. Viewer bergantung dukungan browser; tab baru/unduh menjadi fallback.

## 7. Sesuaikan test existing, jangan diduplikasi

### ReviewUi

Pada `tests/Feature/SidangTaReviewUiTest.php`:

1. Ganti ketiga selector `a[data-document-download]` menjadi `a[data-document-preview]` (test jumlah15,14,15).
2. Pada test pertama, ganti route pada `$expected` dari download menjadi preview:

```php
$expected = $draft->documents->map(fn ($document) => route('sidang-ta-draft-documents.preview', $document))->all();
```

Semua assertion lain tetap, termasuk kelas tombol, jumlah dokumen, status, version dan modal kirim. Nama test lama boleh tetap agar tidak terjadi blok duplikat.

### DetailUi

Pada test `detail_has_indonesian_history_private_download_and_no_revision_for_submitted_status` di `tests/Feature/SidangTaDetailUiTest.php`, ganti blok dari `$download = ...` sampai assertDownload dengan:

```php
$preview = $crawler->filter('a[data-document-preview]');
expect($preview->attr('href'))->toBe(route('sidang-ta-registration-documents.preview', $document));
expect($preview->attr('data-download-url'))->toBe(route('sidang-ta-registration-documents.download', $document));
expect(preg_split('/\s+/', trim($preview->attr('class'))))->toContain('btn')->toContain('btn-secondary');
$this->get($preview->attr('href'))->assertOk();
$this->get($preview->attr('data-download-url'))->assertDownload('berkas.pdf');
```

Pertahankan assertion riwayat, escaping dan larangan revisi. Test StudentPolish S2 tetap valid: form draft tidak memiliki download, sedangkan URL unduh di halaman tinjau sekarang disimpan pada data-download-url untuk modal.

### Entry JavaScript

Pada `tests/Js/sidang-ta-entry.test.mjs`, setelah assertion registrasi sidangTaUpload sebelum start, tambahkan:

```js
assert.equal(typeof providersAtStart?.get('sidangTaDocumentPreview'), 'function',
    'sidangTaDocumentPreview harus terdaftar sebelum Alpine.start().');
```

Provider baru berasal dari module wizard yang sudah di-inline oleh test entry. Tidak perlu mock import module tambahan.

## 8. Feature test baru lengkap

Isi seluruh `tests/Feature/SidangTaDocumentPreviewTest.php`:

```php
<?php

use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDraft;
use App\Models\Student;
use App\Models\User;
use App\Support\SidangTaDocumentRequirements as Requirements;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\DomCrawler\Crawler;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create([
        'username' => '1202200001', 'email' => 'preview-ta@mahasiswa.test',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false,
    ]);
    $this->student = Student::create([
        'student_number' => '1202200001', 'name' => 'Preview TA Test',
        'email' => $this->user->email, 'academic_year' => 2022,
        'study_program' => 'Informatika', 'status' => 'Aktif', 'gpa' => 0,
    ]);
    foreach (['read', 'create', 'update'] as $action) {
        $this->user->givePermissionTo(Permission::firstOrCreate([
            'name' => "sidang_ta_registration_{$action}", 'guard_name' => 'web',
        ]));
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->period = SidangTaPeriod::create([
        'name' => 'Periode S2', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay(),
    ]);
    $this->data = ['sidang_ta_period_id' => $this->period->id,
        'student_phone' => '081234567890', 'parent_phone' => '081234567891'];
    $this->actingAs($this->user);
    $this->completeDraft = function () {
        $id = $this->postJson(route('sidang-ta-drafts.store'), $this->data)
            ->assertCreated()->json('data.id');
        $draft = SidangTaRegistrationDraft::findOrFail($id);
        $draft->payload = array_replace($draft->payload, [
            'graduation_payment_date' => today()->toDateString(), 'diploma_biodata_accurate' => true,
        ]);
        $draft->save();
        foreach (array_merge(Requirements::CAPTURES, Requirements::PAYMENT, Requirements::DIPLOMA) as $type) {
            $path = "sidang_ta/drafts/{$draft->id}/{$type}.pdf";
            Storage::disk('local')->put($path, '%PDF-ui');
            $draft->documents()->create([
                'document_type' => $type, 'file_name' => $type.'.pdf',
                'file_path' => $path, 'file_size' => 7, 'file_type' => 'pdf',
            ]);
        }
        return $draft;
    };

    Permission::firstOrCreate(['name' => 'sidang_ta_registration_review', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'sidang_ta_period_read', 'guard_name' => 'web']);
    $this->makeFinal = function (string $status = 'submitted') {
        $registration = SidangTaRegistration::create([
            'sidang_ta_period_id' => $this->period->id, 'student_number' => $this->student->student_number,
            'student_name' => $this->student->name, 'study_program' => $this->student->study_program,
            'student_phone' => '081234567890', 'parent_phone' => '081234567891',
            'graduation_payment_date' => today()->toDateString(), 'diploma_biodata_accurate' => true,
            'status' => $status, 'submitted_at' => now(),
        ])->fresh();
        $path = "sidang_ta/final/{$registration->id}/capture.pdf";
        Storage::disk('local')->put($path, '%PDF-1.4 preview');
        $document = $registration->documents()->create([
            'document_type' => 'level_2_result_capture', 'file_name' => 'capture.pdf',
            'file_path' => $path, 'file_type' => 'pdf', 'file_size' => 16,
        ]);
        return [$registration, $document];
    };
});



test('preview_owner_gets_inline_pdf_jpeg_png_for_draft_and_final', function () {
    $draft = ($this->completeDraft)();
    $draftDocument = $draft->documents()->firstOrFail();
    [, $finalDocument] = ($this->makeFinal)();
    $samples = [
        ['pdf', 'application/pdf', '%PDF-1.4 preview'],
        ['jpg', 'image/jpeg', hex2bin('ffd8ffe000104a46494600010100000100010000ffd9')],
        ['png', 'image/png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=')],
    ];
    foreach ($samples as [$extension, $mime, $bytes]) {
        foreach ([['sidang-ta-draft-documents.preview', $draftDocument],
            ['sidang-ta-registration-documents.preview', $finalDocument]] as [$route, $document]) {
            Storage::disk('local')->put($document->file_path, $bytes);
            $document->update(['file_name' => 'berkas.'.$extension, 'file_type' => $extension]);
            $response = $this->get(route($route, $document))->assertOk()
                ->assertHeader('Content-Type', $mime)->assertHeader('X-Content-Type-Options', 'nosniff');
            expect($response->headers->get('Content-Disposition'))->toStartWith('inline;');
            expect($response->headers->get('Cache-Control'))->toContain('private')->toContain('no-store');
            expect($response->streamedContent())->toBe($bytes);
        }
    }
});

test('preview_other_student_cannot_open_draft_or_final_by_id', function () {
    $draft = ($this->completeDraft)();
    [, $finalDocument] = ($this->makeFinal)();
    $other = User::factory()->create(['username' => '1202200002', 'email' => 'other-preview@mahasiswa.test',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
    Student::create(['student_number' => '1202200002', 'name' => 'Other', 'email' => $other->email,
        'academic_year' => 2022, 'study_program' => 'Informatika', 'status' => 'Aktif']);
    $other->givePermissionTo('sidang_ta_registration_read');
    $this->actingAs($other)->get(route('sidang-ta-draft-documents.preview', $draft->documents()->first()))->assertForbidden();
    $this->get(route('sidang-ta-registration-documents.preview', $finalDocument))->assertForbidden();
});

test('preview_consumed_and_stale_revision_drafts_are_rejected', function () {
    $draft = ($this->completeDraft)();
    $document = $draft->documents()->firstOrFail();
    $draft->update(['status' => 'consumed']);
    $this->get(route('sidang-ta-draft-documents.preview', $document))->assertStatus(409);
    [$source, $finalDocument] = ($this->makeFinal)('needs_review');
    $id = $this->postJson(route('sidang-ta-drafts.revision', $source))->assertCreated()->json('data.id');
    $revision = SidangTaRegistrationDraft::findOrFail($id);
    $borrowed = $revision->documents()->firstOrFail();
    $source->lock_version++;
    $source->save();
    $this->get(route('sidang-ta-draft-documents.preview', $borrowed))->assertStatus(409);
    $this->get(route('sidang-ta-registration-documents.preview', $finalDocument))->assertOk();
});

test('preview_missing_files_return_404_and_html_content_is_not_rendered_inline', function () {
    $draft = ($this->completeDraft)();
    [, $finalDocument] = ($this->makeFinal)();
    foreach ([['sidang-ta-draft-documents.preview', $draft->documents()->firstOrFail()],
        ['sidang-ta-registration-documents.preview', $finalDocument]] as [$route, $document]) {
        Storage::disk('local')->delete($document->file_path);
        $this->get(route($route, $document))->assertNotFound();
        Storage::disk('local')->put($document->file_path, '<html><script>alert(1)</script></html>');
        $this->get(route($route, $document))->assertStatus(415);
    }
});

test('preview_reviewer_only_can_open_final_and_download_but_not_private_drafts', function () {
    $draft = ($this->completeDraft)();
    [, $document] = ($this->makeFinal)();
    $reviewer = User::factory()->create(['username' => 'reviewer-preview', 'email' => 'reviewer-preview@test.local',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
    $reviewer->givePermissionTo('sidang_ta_registration_review');
    $this->actingAs($reviewer)->get(route('sidang-ta-registration-documents.preview', $document))->assertOk();
    $this->get(route('sidang-ta-registration-documents.download', $document))->assertDownload('capture.pdf');
    $this->get(route('sidang-ta-draft-documents.preview', $draft->documents()->first()))->assertForbidden();
    $reviewer->revokePermissionTo('sidang_ta_registration_review');
    $reviewer->givePermissionTo('sidang_ta_period_read');
    $this->get(route('sidang-ta-registration-documents.preview', $document))->assertForbidden();
    $this->get(route('sidang-ta-registration-documents.download', $document))->assertForbidden();
});

test('preview_review_page_has_no_number_badges_and_escaped_modal_triggers', function () {
    $draft = ($this->completeDraft)();
    $document = $draft->documents()->firstOrFail();
    $name = 'nama " <img src=x onerror=alert(1)>.pdf';
    $document->update(['file_name' => $name]);
    $this->postJson(route('sidang-ta-drafts.review', $draft), ['version' => $draft->version])->assertOk();
    $crawler = new Crawler($this->get(route('sidang-ta-drafts.preview', $draft))->assertOk()->getContent());
    expect($crawler->filter('[data-review-group]')->count())->toBe(3);
    expect($crawler->filter('[data-review-group] header span[aria-hidden="true"]')->count())->toBe(0);
    expect($crawler->filter('a[data-document-preview]')->count())->toBe(15);
    expect($crawler->filter('[data-document-preview-modal]')->count())->toBe(1);
    $link = $crawler->filter('a[data-document-preview]')->reduce(fn (Crawler $n) =>
        $n->attr('data-preview-url') === route('sidang-ta-draft-documents.preview', $document));
    expect($link->attr('data-file-name'))->toBe($name);
    expect($link->attr('href'))->toBe(route('sidang-ta-draft-documents.preview', $document));
    expect($link->attr('data-download-url'))->toBe(route('sidang-ta-draft-documents.download', $document));
    expect($link->attr('x-on:click.prevent'))->toBe("\$dispatch('sidang-ta-preview', \$el.dataset)");
    expect($crawler->filter('[data-review-document] img')->count())->toBe(0);
    expect($crawler->filter('[data-preview-download]')->count())->toBe(1);
    expect($crawler->filter('[data-preview-new-tab]')->attr('target'))->toBe('_blank');
    expect($crawler->filter('[data-preview-close]')->attr('type'))->toBe('button');
});

test('preview_final_detail_uses_one_modal_and_keeps_direct_download_as_secondary_data', function () {
    [$registration, $document] = ($this->makeFinal)();
    $crawler = new Crawler($this->get(route('sidang-ta-registrations.show', $registration))->assertOk()->getContent());
    $link = $crawler->filter('a[data-document-preview]');
    expect($link->count())->toBe(1);
    expect($link->attr('href'))->toBe(route('sidang-ta-registration-documents.preview', $document));
    expect($link->attr('data-download-url'))->toBe(route('sidang-ta-registration-documents.download', $document));
    expect($crawler->filter('[data-document-preview-modal]')->count())->toBe(1);
    expect($crawler->filter('a[data-final-download]')->count())->toBe(0);
    expect($crawler->filter('[data-document-preview-modal] iframe')->getNode(0)->hasAttribute('src'))->toBeFalse();
});

test('preview_read_only_owner_can_view_without_mutating_saved_state', function () {
    $draft = ($this->completeDraft)();
    [$registration, $finalDocument] = ($this->makeFinal)();
    $this->user->revokePermissionTo('sidang_ta_registration_create');
    $this->user->revokePermissionTo('sidang_ta_registration_update');
    $version = $draft->version;
    $this->get(route('sidang-ta-draft-documents.preview', $draft->documents()->first()))->assertOk();
    $this->get(route('sidang-ta-registration-documents.preview', $finalDocument))->assertOk();
    expect($draft->fresh()->version)->toBe($version);
    expect($draft->fresh()->reviewed_version)->toBeNull();
    expect($registration->fresh()->status)->toBe('submitted');
    expect($registration->logs()->count())->toBe(0);
});

```

## 9. Node test baru lengkap

Isi `tests/Js/sidang-ta-document-preview.test.mjs`:

```js
import test from 'node:test';
import assert from 'node:assert/strict';
import { sidangTaDocumentPreview } from '../../resources/js/sidang-ta-wizard.js';

const setup = () => {
    const state = sidangTaDocumentPreview();
    const calls = [];
    state.$refs = { dialog: {
        open: false,
        showModal() { this.open = true; calls.push('show'); },
        close() { this.open = false; calls.push('close'); },
    } };
    return { state, calls };
};

test('preview opens a PDF using server supplied routes and a literal filename', () => {
    const { state, calls } = setup();
    const data = { previewUrl: '/documents/1/preview', downloadUrl: '/documents/1/download',
        fileName: 'nama " <img>.pdf', fileType: 'PDF' };
    state.open(data);
    assert.equal(state.previewUrl, data.previewUrl);
    assert.equal(state.downloadUrl, data.downloadUrl);
    assert.equal(state.fileName, data.fileName);
    assert.equal(state.isPdf, true);
    assert.deepEqual(calls, ['show']);
});

test('preview switches from PDF to image without reopening an open dialog', () => {
    const { state, calls } = setup();
    state.open({ previewUrl: '/pdf/preview', downloadUrl: '/pdf/download', fileName: 'a.pdf', fileType: 'pdf' });
    state.open({ previewUrl: '/image/preview', downloadUrl: '/image/download', fileName: 'a.png', fileType: 'png' });
    assert.equal(state.previewUrl, '/image/preview');
    assert.equal(state.downloadUrl, '/image/download');
    assert.equal(state.isPdf, false);
    assert.deepEqual(calls, ['show']);
});

test('preview close requests native close and close event reset removes viewer URLs', () => {
    const { state, calls } = setup();
    state.open({ previewUrl: '/preview', downloadUrl: '/download', fileName: 'a.pdf', fileType: 'pdf' });
    state.close();
    assert.deepEqual(calls, ['show', 'close']);
    // Native event close memanggil reset melalui x-on:close pada partial.
    state.reset();
    assert.equal(state.previewUrl, '');
    assert.equal(state.downloadUrl, '');
    assert.equal(state.fileName, '');
    assert.equal(state.isPdf, false);
});
```

## 10. Command dan manual test

```bash
php artisan route:clear
php artisan view:clear
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaDocumentPreviewTest.php tests/Feature/SidangTaReviewUiTest.php tests/Feature/SidangTaDetailUiTest.php tests/Feature/SidangTaStudentPolishTest.php
```

Target fokus **27 passed** =8baru+5ReviewUi+8DetailUi+6StudentPolish. Tidak menambah ulang test existing.

Sesudah fokus lulus:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaDraftSchemaTest.php tests/Feature/SidangTaPeriodTest.php tests/Feature/SidangTaDraftTest.php tests/Feature/SidangTaDraftUploadTest.php tests/Feature/SidangTaRevisionDraftTest.php tests/Feature/SidangTaWizardTest.php tests/Feature/SidangTaFinalizationTest.php tests/Feature/SidangTaWizardUiTest.php tests/Feature/SidangTaDraftCardTest.php tests/Feature/SidangTaReviewUiTest.php tests/Feature/SidangTaDetailUiTest.php tests/Feature/SidangTaPartialDraftTest.php tests/Feature/SidangTaStudentPolishTest.php tests/Feature/SidangTaDocumentPreviewTest.php
node tests/Js/sidang-ta-entry.test.mjs
node tests/Js/sidang-ta-wizard.test.mjs
node tests/Js/sidang-ta-document-preview.test.mjs
npm run build
```

Target **124 PHP, 12 Node**, build berhasil. Tetap schema MySQL Podman dadu_testing. Jangan menjalankan RefreshDatabase terhadap dadu atau mengubah phpunit.xml permanen.

Checklist browser:

- [ ] Login mahasiswa: halaman tinjau tidak menampilkan badge2–4, judul/jumlah serta Ubah tahap tetap ada.
- [ ] Klik Lihat berkas PDF di tinjau: modal terbuka, nama benar, PDF terbaca. Uji PNG/JPG pada capture; gambar terlihat tanpa terpotong.
- [ ] Tutup dengan tombol dan Escape. Buka berkas lain: isi/nama berubah benar dan tidak menampilkan berkas sebelumnya.
- [ ] Unduh dalam modal mendapatkan file yang sama; Buka di tab baru menampilkan preview melalui route privat. Bila viewer ponsel tidak mendukung PDF, fallback ini dapat digunakan.
- [ ] Detail pengajuan final menggunakan modal yang sama, termasuk pengajuan lama dan hasil revisi. Riwayat, snapshot, tombol revisi dan modal kirim tidak berubah.
- [ ] Tahap form2–4 tetap tanpa tombol unduh/preview baru; S2 tidak dibatalkan. Periksa lagi Tinjau disabled sebelum lengkap/tersimpan.
- [ ] Nama berkas dengan kutip, ampersand dan spasi tampil literal; tidak merusak tombol/modal. Uji layar sempit dan keyboard Tab/Enter/Escape.
- [ ] Jika file hilang di server, preview memberikan404 dan fallback tidak membuat halaman seolah file tersedia. Tidak mengirim ulang atau mengubah status karena membuka preview.

## 11. Salin, rescan dan commit student

Setelah seluruh test dan manual lulus, salin seluruh file existing/new pada bagian2 ke dadu-test. Minta rescan akhir student sebelum mulai P1. **Belum pindah ke implementasi petugas pada checkpoint ini.** Route final mendukung reviewer sebagai fondasi modal yang nanti dipakai kembali; daftar review/verifikasi per dokumen belum ditulis.

Pengguna meminta commit message sebelum berpindah ke petugas. Simpan pesan berikut untuk **sesudah checkpoint35 lulus dan rescan akhir bersih**, jika commit ini mencakup perbaikan S1/S2/preview sejak commit student sebelumnya:

```text
feat(sidang-ta): sempurnakan draft parsial dan preview dokumen mahasiswa

- simpan progres draft tanpa membuka tahap yang belum lengkap
- rapikan tampilan pendaftaran dan status tombol tinjau
- tambahkan preview privat PDF dan gambar dengan opsi unduh
- lengkapi pengujian akses berkas dan tampilan mahasiswa
```

Jika S1/S2 sudah dicommit terpisah, gunakan pesan yang lebih sempit:

```text
feat(sidang-ta): tambahkan preview dokumen pada tinjau dan detail mahasiswa
```

Asisten tidak melakukan git add/commit/push. Sebelum commit, kembalikan phpunit.xml sementara seperti rencana pengguna, periksa diff agar .env/password/schema/cache/build/dependencies tidak ikut. Jangan menimpa perubahan konfigurasi lain yang memang ingin disimpan. Commit message akhir akan disesuaikan lagi dengan diff yang benar-benar akan dikomit pada rescan berikutnya.

## 12. Verifikasi panduan oleh asisten

Tujuh blok PHP diperiksa sintaksnya; fragment middleware diperiksa dalam konteks fluent route, kedua method dalam konteks class. Lima blok Blade dikompilasi di memori tanpa component tags lalu hasil PHP diperiksa sintaksnya. Empat blok JavaScript lulus pemeriksaan sintaks.

Tiga test Node preview dan satu test entry diperiksa dengan kode usulan yang dimuat melalui data URL di memori; delapan test wizard existing juga lulus. Total pemeriksaan Node adalah12, tanpa mengubah resources/js/app.js atau module aplikasi. Reviewer independen tidak menemukan blocker pada route/controller/modal maupun delapan test Feature; probe finfo mengonfirmasi MIME fixture PDF/JPEG/PNG dan probe DOM mengonfirmasi atribut x-on:click.prevent serta iframe template tetap terbaca.

Source kedua folder dipindai sebelum/sesudah setiap penulisan dokumentasi dan tidak diubah oleh asisten. Tidak menjalankan suite Feature PHP, build, bootstrap aplikasi atau database. Suite124/manual preview belum dijalankan pengguna; hasil116S2 tidak menjadi bukti checkpoint35 lulus.

Referensi: [Laravel13 responses](https://github.com/laravel/docs/blob/13.x/responses.md), [filesystem](https://github.com/laravel/docs/blob/13.x/filesystem.md) melalui Context7. Signature response($path,$name,$headers,$disposition) juga diperiksa langsung pada FilesystemAdapter dependency yang terpasang.
