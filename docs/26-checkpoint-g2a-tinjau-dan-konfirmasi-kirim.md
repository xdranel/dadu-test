# 26 — G2a: halaman tinjau dan konfirmasi kirim

**Goal:** merapikan ringkasan tinjau, mengganti tautan unduh menjadi tombol, dan meminta konfirmasi sebelum form final dikirim.

**Architecture:** ganti satu view Blade. Tetap membaca draft yang telah ditinjau melalui service preview; gunakan dialog native seperti hapus draft dan state Alpine lokal untuk mencegah klik ganda. Endpoint/payload final tidak berubah.

**Tech stack:** Laravel 13, Blade, Tailwind 3, Alpine 3, dialog native dan Pest; tanpa dependency baru.

**Spec:** melanjutkan rancangan [22](22-rancangan-visual-student-sidang-ta.md) dan permintaan pengguna tentang modal pemeriksaan ulang sebelum kirim.

Tanggal: 2 November 2026. Pengguna melaporkan seluruh test setelah [25](25-koreksi-test-warning-draft-stale.md) berhasil. Source belum disalin ke dadu-test, sehingga scan dilakukan langsung pada folder asli. Asisten hanya menulis docs.

## Batas perubahan

- Fokus halaman **Tinjau Pendaftaran Sidang TA**. Perapian **Data akademik saat pengajuan** pada detail tetap menjadi G2b berikutnya, belum dianggap selesai.
- Tidak mengubah controller, FormRequest, service finalizer, route, permission, migrasi atau JavaScript global.
- Form final hanya mengirim CSRF, `draft_id`, `version`, `action=submit`, dan `_method=PUT` untuk revisi. Tidak mengirim file atau field biodata tambahan.
- Dialog adalah konfirmasi UI. Guard owner, periode, review version, transaksi dan idempotensi tetap bekerja di server; tidak menambahkan field konfirmasi baru.
- Seluruh nilai dan dokumen menggunakan data yang sudah dimuat service preview. Tidak menjalankan upload/snapshot baru untuk menggambar halaman ini.

## Rekomendasi visual yang diterapkan dalam contoh

Card utama memiliki aksen merah kampus, judul **Tinjau sebelum kirim**, badge **Belum dikirim**, identitas dan periode, lalu informasi kontak/pembayaran/biodata dalam kelompok label–nilai. Total dokumen ditampilkan sebagai informasi ringkas, bukan status persetujuan petugas.

Dokumen dikelompokkan menurut tahap dengan judul, jumlah berkas, tombol **Unduh**, dan tautan **Ubah tahap**. Nama panjang membungkus agar tombol tidak terdesak. Tombol Kirim ditempatkan pada card aksi dengan pengingat pemeriksaan ulang.

Modal berisi dampak kirim dan daftar hal yang perlu diperiksa. **Periksa kembali** adalah aksi awal yang difokuskan dan menutup modal tanpa request. Hanya **Ya, kirim pendaftaran** atau **Ya, kirim revisi** yang submit.

## Fokus review

1. Membuka preview/modal tidak membuat pengajuan final; diuji pada test summary/struktur dan browser Network.
2. File hanya diunduh melalui route privat, termasuk cabang PDM; diuji pada test link dan cabang.
3. Form baru tetap memakai draft/version/action yang tersimpan; diuji dengan mengekstrak form dari HTML lalu submit.
4. Revisi harus menuju pengajuan asal dengan spoof PUT, bukan membuat registration baru; diuji pada test kontrak form revisi.
5. Pemilik read-only tidak mendapat kontrol kirim; diuji sesudah permission create dicabut.

## 1. Berkas dan command

Edit file yang sudah ada:

```text
resources/views/sidang_ta_registrations/wizard-review.blade.php
tests/Feature/SidangTaWizardTest.php (satu blok test pesan preview, lihat akhir bagian 3)
```

Buat satu file test baru:

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
php artisan make:test SidangTaReviewUiTest --pest
```

- [ ] Salin test lengkap pada bagian 3, jalankan command fokus pada bagian 4 sebelum mengganti Blade. Test render/control baru semestinya belum lulus. Jangan menganggap error koneksi DB sebagai kegagalan yang dimaksud.
- [ ] Ganti view pada bagian 2, lalu jalankan ulang test dan checklist browser.

Tidak ada command make:view untuk file yang sudah ada; jangan menimpa dengan generator atau `--force`.

## 2. Isi lengkap wizard-review.blade.php

Ganti seluruh isi view dengan berikut:

```blade
<x-app-layout>
    <x-slot name="header">Tinjau Pendaftaran Sidang TA</x-slot>
    @php
        $payload = $draft->payload;
        $accurate = (bool) $payload['diploma_biodata_accurate'];
        $isRevision = $draft->source_registration_id !== null;
        $permission = $isRevision ? 'sidang_ta_registration_update' : 'sidang_ta_registration_create';
        $groups = [2 => 'Verifikasi iGracias & akademik', 3 => 'Pembayaran & dokumen', 4 => 'Biodata ijazah'];
        $documentCount = count(array_merge(
            \App\Support\SidangTaDocumentRequirements::CAPTURES,
            \App\Support\SidangTaDocumentRequirements::PAYMENT,
            \App\Support\SidangTaDocumentRequirements::typesForStep(4, $accurate)
        ));
        $confirmLabel = $isRevision ? 'Ya, kirim revisi' : 'Ya, kirim pendaftaran';
    @endphp
    <div class="mx-auto max-w-6xl space-y-5">
        @if($errors->any())
            <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
                <p class="font-bold">Pendaftaran belum dapat dikirim.</p>
                <ul class="mt-2 list-inside list-disc space-y-1">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <section data-review-summary class="campus-card overflow-hidden">
            <header class="border-b border-rose-100 bg-rose-50/60 p-5 sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-[#DA2D3B]">Pemeriksaan akhir{{ $isRevision ? ' · Revisi' : '' }}</p>
                        <h2 class="mt-1 text-xl font-bold text-slate-900">Tinjau sebelum kirim</h2>
                        <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-600">Periksa kembali data dan seluruh berkas sebelum mengirim pendaftaran kepada petugas.</p>
                    </div>
                    <span class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800">Belum dikirim</span>
                </div>
            </header>
            <div class="space-y-5 p-5 sm:p-6">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="min-w-0 rounded-xl bg-slate-50 p-4">
                        <p class="text-xs font-semibold text-slate-500">Mahasiswa</p>
                        <p class="mt-1 break-words text-base font-bold text-slate-900">{{ $student->name }}</p>
                        <p class="mt-1 break-words text-sm text-slate-600">{{ $student->student_number }} · {{ $student->study_program ?: 'Program studi belum tersedia' }}</p>
                    </div>
                    <div class="min-w-0 rounded-xl bg-slate-50 p-4">
                        <p class="text-xs font-semibold text-slate-500">Periode Sidang TA</p>
                        <p class="mt-1 break-words text-base font-bold text-slate-900">{{ $draft->period->name }}</p>
                        <p class="mt-1 text-sm text-slate-600">{{ $documentCount }} dokumen dalam tinjauan{{ $isRevision ? ' · Periode pengajuan asal' : '' }}</p>
                    </div>
                </div>
                <dl class="grid gap-x-6 gap-y-5 sm:grid-cols-2">
                    <div><dt class="text-xs text-slate-500">Nomor HP mahasiswa</dt>
                        <dd class="mt-1 break-words text-sm font-semibold text-slate-800">{{ $payload['student_phone'] }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Nomor HP orang tua/wali</dt>
                        <dd class="mt-1 break-words text-sm font-semibold text-slate-800">{{ $payload['parent_phone'] }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Tanggal pembayaran wisuda</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-800">{{ \Illuminate\Support\Carbon::parse($payload['graduation_payment_date'])->translatedFormat('d M Y') }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Biodata dummy ijazah</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-800">{{ $accurate ? 'Sudah sesuai' : 'Belum sesuai, bukti PDM dilampirkan' }}</dd></div>
                </dl>
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
                    <p class="text-xs leading-relaxed text-slate-500">Data di bawah berasal dari draft yang tersimpan. Pendaftaran belum dikirim.</p>
                    <a class="text-sm font-semibold text-[#DA2D3B] hover:underline" href="{{ route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 1]) }}">Ubah data kontak</a>
                </div>
            </div>
        </section>

        @foreach($groups as $number => $title)
            @php($types = \App\Support\SidangTaDocumentRequirements::typesForStep($number, $accurate))
            <section data-review-group="{{ $number }}" class="campus-card overflow-hidden">
                <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4 sm:px-6">
                    <div class="flex items-center gap-3">
                        <span aria-hidden="true" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-rose-50 text-sm font-bold text-[#DA2D3B]">{{ $number }}</span>
                        <div><h3 class="text-base font-bold text-slate-800">{{ $title }}</h3>
                            <p class="mt-0.5 text-xs text-slate-500">Tahap {{ $number }} · {{ count($types) }} berkas tersimpan</p></div>
                    </div>
                    <a class="text-sm font-semibold text-[#DA2D3B] hover:underline" href="{{ route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => $number]) }}">Ubah tahap {{ $number }}</a>
                </header>
                <div class="divide-y divide-slate-100 px-5 sm:px-6">
                    @foreach($types as $type)
                        @php($document = $draft->documents->firstWhere('document_type', $type))
                        <div data-review-document="{{ $type }}" class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between sm:gap-5">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold leading-relaxed text-slate-800">{{ \App\Models\SidangTaRegistration::DOCUMENT_TYPES[$type] }}</p>
                                <p class="mt-1 break-words text-xs leading-relaxed text-slate-500">{{ $document->file_name }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ number_format(($document->file_size ?? 0) / 1024) }} KB</p>
                            </div>
                            <a data-document-download class="btn btn-secondary shrink-0 self-start whitespace-nowrap !text-xs no-underline sm:self-auto"
                               href="{{ route('sidang-ta-draft-documents.download', $document) }}"
                               aria-label="Unduh {{ \App\Models\SidangTaRegistration::DOCUMENT_TYPES[$type] }}">Unduh</a>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach

        <section data-review-actions class="campus-card space-y-4 p-5 sm:p-6"
                 x-data="{ sending: false }" @pageshow.window="sending = false">
            <div><h2 class="text-base font-bold text-slate-800">Sudah memeriksa semua data?</h2>
                <p class="mt-1 text-sm leading-relaxed text-slate-600">Pastikan periode, kontak, pembayaran, biodata, dan setiap berkas sudah benar. Anda masih dapat kembali ke form untuk memperbaikinya.</p></div>
            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-wrap gap-2">
                    <a class="btn btn-secondary" href="{{ route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 1]) }}">Kembali ke form</a>
                    <a class="btn btn-secondary" href="{{ route('sidang-ta-registrations.index') }}">Kembali ke pendaftaran</a>
                </div>
                @can($permission)
                    <button data-open-registration type="button" class="btn btn-primary" :disabled="sending"
                            aria-haspopup="dialog" aria-controls="confirm-send-registration"
                            onclick="document.getElementById('confirm-send-registration').showModal()">Kirim Pendaftaran</button>
                @endcan
            </div>
            @can($permission)
                <dialog id="confirm-send-registration" aria-labelledby="confirm-send-title" aria-describedby="confirm-send-description"
                        @cancel="if (sending) $event.preventDefault()"
                        class="m-auto w-11/12 max-w-lg rounded-2xl border border-slate-200 bg-white p-5 text-slate-800 shadow-xl backdrop:bg-slate-900/50 sm:p-6">
                    <h2 id="confirm-send-title" class="text-lg font-bold">{{ $isRevision ? 'Kirim revisi pendaftaran?' : 'Kirim pendaftaran sekarang?' }}</h2>
                    <div id="confirm-send-description" class="mt-3 space-y-3 text-sm leading-relaxed text-slate-600">
                        <p>Periksa kembali data dan seluruh dokumen. Setelah dikirim, pengajuan akan ditinjau oleh petugas.</p>
                        <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-amber-900">
                            <p class="font-semibold">Pastikan kembali:</p>
                            <ul class="mt-2 list-inside list-disc space-y-1">
                                <li>Periode Sidang TA dan nomor kontak sudah benar.</li>
                                <li>Tanggal pembayaran wisuda serta pilihan biodata sudah sesuai.</li>
                                <li>Seluruh berkas lengkap, benar, dan dapat dibaca.</li>
                            </ul>
                        </div>
                        <p>Jika masih ragu, pilih Periksa kembali. Jika sudah yakin, lanjutkan pengiriman. Perubahan setelah pengiriman mengikuti proses revisi yang tersedia.</p>
                    </div>
                    <form data-final-registration-form method="POST" class="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"
                          action="{{ $isRevision ? route('sidang-ta-registrations.update', $draft->source_registration_id) : route('sidang-ta-registrations.store') }}"
                          @submit="if (sending) { $event.preventDefault(); } else { sending = true; }">
                        @csrf
                        @if($isRevision) @method('PUT') @endif
                        <input type="hidden" name="draft_id" value="{{ $draft->id }}">
                        <input type="hidden" name="version" value="{{ $draft->version }}">
                        <input type="hidden" name="action" value="submit">
                        <button data-cancel-registration type="button" autofocus class="btn btn-secondary" :disabled="sending"
                                onclick="this.closest('dialog').close()">Periksa kembali</button>
                        <button data-confirm-registration type="submit" class="btn btn-primary" :disabled="sending"
                                x-text="sending ? 'Sedang mengirim…' : '{{ $confirmLabel }}'">{{ $confirmLabel }}</button>
                    </form>
                </dialog>
            @endcan
        </section>
    </div>
</x-app-layout>
```

Link unduh tetap elemen `<a>` dengan tampilan tombol dan route privat. Tautan **Ubah tahap** boleh tetap berupa teks; yang diganti menjadi tombol adalah tindakan unduh. `no-underline` mencegah dekorasi garis bawah pada tombol.

Form final hanya berada di dalam dialog. Tombol pembuka berada di luar form dan memiliki `type="button"`; Batal tidak submit. Jangan menambahkan `method="dialog"`/`formmethod="dialog"` pada form atau tombol konfirmasi karena pengiriman harus tetap menuju server. Saat pengiriman dimulai, kontrol konfirmasi/batal dinonaktifkan dan Escape dicegah; `pageshow` memulihkan state jika halaman dikunjungi lewat Back browser.

## 3. Test lengkap halaman tinjau

Isi seluruh `tests/Feature/SidangTaReviewUiTest.php` dengan berikut. `tests/Pest.php` sudah memasang RefreshDatabase untuk Feature. Helper memakai DOM yang dinormalisasi sehingga formatting/newline tidak mengulangi masalah 25.

```php
<?php

use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDraft;
use App\Models\Student;
use App\Models\User;
use App\Support\SidangTaDocumentRequirements as Requirements;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\DomCrawler\Crawler;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create([
        'username' => '1202200001', 'email' => 'review-ui@mahasiswa.test',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false,
    ]);
    Student::create([
        'student_number' => '1202200001', 'name' => 'Review UI Test',
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
        'name' => 'Periode Review UI', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay(),
    ]);
    $this->actingAs($this->user);
    $this->reviewedDraft = function (bool $accurate = true) {
        $id = $this->postJson(route('sidang-ta-drafts.store'), [
            'sidang_ta_period_id' => $this->period->id,
            'student_phone' => '081234567890', 'parent_phone' => '081234567891',
        ])->assertCreated()->json('data.id');
        $draft = SidangTaRegistrationDraft::findOrFail($id);
        $draft->payload = array_replace($draft->payload, [
            'graduation_payment_date' => today()->toDateString(), 'diploma_biodata_accurate' => $accurate,
        ]);
        $draft->save();
        foreach (array_merge(Requirements::CAPTURES, Requirements::PAYMENT,
            Requirements::typesForStep(4, $accurate)) as $type) {
            $path = "sidang_ta/drafts/{$draft->id}/{$type}.pdf";
            Storage::disk('local')->put($path, '%PDF-review-ui');
            $draft->documents()->create([
                'document_type' => $type, 'file_name' => $type.'.pdf',
                'file_path' => $path, 'file_size' => 14, 'file_type' => 'pdf',
            ]);
        }
        $this->postJson(route('sidang-ta-drafts.review', $draft), ['version' => 1])->assertOk();
        return $draft->fresh();
    };
});

test('review_summary_and_download_buttons_do_not_submit_registration', function () {
    $draft = ($this->reviewedDraft)();
    $response = $this->get(route('sidang-ta-drafts.preview', $draft))->assertOk();
    $crawler = new Crawler($response->getContent());
    $summary = $crawler->filter('[data-review-summary]');
    expect($summary->count())->toBe(1);
    expect($summary->text(null, true))->toContain('Review UI Test')->toContain('1202200001')
        ->toContain('Periode Review UI')->toContain('081234567890')->toContain('081234567891')
        ->toContain('15 dokumen')->toContain('Belum dikirim');
    expect($crawler->filter('[data-review-group]')->count())->toBe(3);
    $links = $crawler->filter('a[data-document-download]');
    expect($links->count())->toBe(15);
    $actual = $links->each(function (Crawler $node) {
        $classes = preg_split('/\s+/', trim($node->attr('class')));
        expect($classes)->toContain('btn')->toContain('btn-secondary')->not->toContain('underline');
        return $node->attr('href');
    });
    $expected = $draft->documents->map(fn ($document) => route('sidang-ta-draft-documents.download', $document))->all();
    sort($actual);
    sort($expected);
    expect($actual)->toBe($expected);
    expect(SidangTaRegistration::count())->toBe(0);
    expect($draft->fresh()->status)->toBe('active');
});

test('only_confirmation_form_submits_saved_draft_with_original_contract', function () {
    $draft = ($this->reviewedDraft)();
    $crawler = new Crawler($this->get(route('sidang-ta-drafts.preview', $draft))->assertOk()->getContent());
    $open = $crawler->filter('[data-open-registration]');
    expect($open->count())->toBe(1);
    expect($open->attr('type'))->toBe('button');
    $openNode = $open->getNode(0);
    $xpath = new \DOMXPath($openNode->ownerDocument);
    expect($xpath->query('ancestor::form', $openNode)->length)->toBe(0);
    $dialog = $crawler->filter('dialog#confirm-send-registration');
    expect($dialog->count())->toBe(1);
    expect($dialog->getNode(0)->hasAttribute('open'))->toBeFalse();
    expect($dialog->text(null, true))->toContain('Periksa kembali data dan seluruh dokumen.');
    expect($dialog->filter('[data-cancel-registration]')->attr('type'))->toBe('button');
    expect($dialog->filter('[data-cancel-registration]')->getNode(0)->hasAttribute('autofocus'))->toBeTrue();
    expect($dialog->filter('[data-confirm-registration]')->attr('type'))->toBe('submit');
    $form = $dialog->filter('form[data-final-registration-form]');
    expect($crawler->filter('form[data-final-registration-form]')->count())->toBe(1);
    expect($form->attr('method'))->toBe('POST');
    expect($form->attr('action'))->toBe(route('sidang-ta-registrations.store'));
    expect($form->filter('input[type="file"]')->count())->toBe(0);
    $fields = $form->filter('input[name]')->each(fn (Crawler $node) => [
        'name' => $node->attr('name'), 'value' => $node->attr('value'),
    ]);
    $body = array_column($fields, 'value', 'name');
    $keys = array_keys($body);
    sort($keys);
    expect($keys)->toBe(['_token', 'action', 'draft_id', 'version']);
    expect($body['draft_id'])->toBe($draft->id);
    expect($body['version'])->toBe((string) $draft->version);
    expect($body['action'])->toBe('submit');
    expect(SidangTaRegistration::count())->toBe(0);

    $response = $this->post($form->attr('action'), $body);
    $registration = SidangTaRegistration::firstOrFail();
    $response->assertRedirect(route('sidang-ta-registrations.show', $registration));
    expect(SidangTaRegistration::count())->toBe(1);
    expect($registration->status)->toBe('submitted');
    expect($registration->documents()->count())->toBe(15);
    expect($draft->fresh()->status)->toBe('consumed');
});

test('pdm_review_displays_only_its_fourteen_required_documents', function () {
    $draft = ($this->reviewedDraft)(false);
    $crawler = new Crawler($this->get(route('sidang-ta-drafts.preview', $draft))->assertOk()->getContent());
    expect($crawler->filter('[data-review-summary]')->text(null, true))
        ->toContain('14 dokumen')->toContain('Belum sesuai, bukti PDM dilampirkan');
    expect($crawler->filter('a[data-document-download]')->count())->toBe(14);
    expect($crawler->filter('[data-review-document="pdm_submission_proof"]')->count())->toBe(1);
    expect($crawler->filter('[data-review-document="diploma_statement"]')->count())->toBe(0);
    expect($crawler->filter('[data-review-document="diploma_biodata_form"]')->count())->toBe(0);
});

test('revision_confirmation_form_keeps_original_registration_and_put_method', function () {
    $initial = ($this->reviewedDraft)();
    $id = $this->postJson(route('sidang-ta-registrations.store'), [
        'draft_id' => $initial->id, 'version' => $initial->version, 'action' => 'submit',
    ])->assertOk()->json('data.id');
    $source = SidangTaRegistration::findOrFail($id);
    $source->status = 'needs_review';
    $source->lock_version++;
    $source->save();
    $revisionId = $this->postJson(route('sidang-ta-drafts.revision', $source))
        ->assertCreated()->json('data.id');
    $revision = SidangTaRegistrationDraft::findOrFail($revisionId);
    $this->postJson(route('sidang-ta-drafts.review', $revision), ['version' => 1])->assertOk();
    $crawler = new Crawler($this->get(route('sidang-ta-drafts.preview', $revision))->assertOk()->getContent());
    $form = $crawler->filter('#confirm-send-registration form[data-final-registration-form]');
    expect($form->attr('action'))->toBe(route('sidang-ta-registrations.update', $source));
    expect($form->filter('input[name="_method"]')->attr('value'))->toBe('PUT');
    expect($form->filter('input[name="draft_id"]')->attr('value'))->toBe($revision->id);
    expect($form->filter('input[name="version"]')->attr('value'))->toBe((string) $revision->version);
    expect($form->filter('[data-confirm-registration]')->text(null, true))->toBe('Ya, kirim revisi');
    expect(SidangTaRegistration::count())->toBe(1);
    expect($source->fresh()->status)->toBe('needs_review');
});

test('read_only_owner_keeps_review_downloads_without_send_controls', function () {
    $draft = ($this->reviewedDraft)();
    $this->user->revokePermissionTo('sidang_ta_registration_create');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($this->user->fresh());
    $crawler = new Crawler($this->get(route('sidang-ta-drafts.preview', $draft))->assertOk()->getContent());
    expect($crawler->filter('a[data-document-download]')->count())->toBe(15);
    expect($crawler->filter('[data-open-registration]')->count())->toBe(0);
    expect($crawler->filter('#confirm-send-registration')->count())->toBe(0);
    expect($crawler->filter('form[data-final-registration-form]')->count())->toBe(0);
    expect(SidangTaRegistration::count())->toBe(0);
});
```

Ada lima test baru. Test kedua benar-benar memakai action dan hidden input dari form hasil render untuk melakukan final submit. Test modal sendiri hanya membuktikan kontrak HTML; tindakan klik, fokus, Escape dan tidak adanya request sebelum konfirmasi tetap diperiksa di browser. Tidak menambah framework browser test untuk perubahan ini.

### Pertahankan regresi pesan preview tanpa ketergantungan newline

Pada `tests/Feature/SidangTaWizardTest.php`, ganti hanya blok `review_marks_saved_version_and_renders_fifteen_documents` dengan berikut. Ini mengganti pencarian HTML mentah pada pesan preview, supaya autoformat Blade tidak mengulangi masalah 25. Jumlah test lama tetap sama.

```php
test('review_marks_saved_version_and_renders_fifteen_documents', function () {
    $draft = ($this->completeDraft)();
    $this->post(route('sidang-ta-drafts.review', $draft), ['version' => 1])
        ->assertRedirect(route('sidang-ta-drafts.preview', $draft));
    $response = $this->get(route('sidang-ta-drafts.preview', $draft))->assertOk()
        ->assertViewIs('sidang_ta_registrations.wizard-review')
        ->assertViewHas('draft', fn ($viewDraft) => $viewDraft->documents->count() === 15);
    $crawler = new \Symfony\Component\DomCrawler\Crawler($response->getContent());
    expect($crawler->text(null, true))->toContain('Pendaftaran belum dikirim.');
    expect($draft->fresh()->reviewed_version)->toBe(1);
    expect(SidangTaRegistration::count())->toBe(0);
});
```

## 4. Command test dan build

```bash
php artisan config:clear
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaReviewUiTest.php
```

Sesudah test fokus lulus:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaDraftSchemaTest.php tests/Feature/SidangTaPeriodTest.php tests/Feature/SidangTaDraftTest.php tests/Feature/SidangTaDraftUploadTest.php tests/Feature/SidangTaRevisionDraftTest.php tests/Feature/SidangTaWizardTest.php tests/Feature/SidangTaFinalizationTest.php tests/Feature/SidangTaWizardUiTest.php tests/Feature/SidangTaDraftCardTest.php tests/Feature/SidangTaReviewUiTest.php
node tests/Js/sidang-ta-entry.test.mjs
node tests/Js/sidang-ta-wizard.test.mjs
npm run build
```

Hasil yang diharapkan setelah diterapkan: **90 definisi test PHP TA**, **9 test Node** dan build lulus. Gunakan schema `dadu_testing`, bukan database aplikasi. Tidak ada migrasi atau penggantian konfigurasi phpunit.xml. Jika memakai Vite dev server, jalankan seperti biasa untuk browser; build di atas menjadi pemeriksaan aset satu kali sesudah perubahan selesai.

## 5. Checklist browser

- [ ] Lengkapi empat tahap dan tekan Tinjau. Card utama menampilkan data yang benar, badge Belum dikirim, dan jumlah dokumen 15 untuk biodata sesuai atau 14 untuk cabang PDM.
- [ ] Teks identitas, periode panjang, nomor kontak dan nama file membungkus tanpa overflow. Pada mobile, baris dokumen/tombol dapat ditumpuk; tombol Unduh tetap mudah diklik dan tidak bergaris bawah.
- [ ] Unduh satu berkas pada setiap kelompok; isi/nama berkas sesuai. Link Ubah tahap membuka tahap terkait. Setelah mengubah data, simpan dan tinjau ulang sebelum kirim.
- [ ] Klik Kirim Pendaftaran: hanya modal muncul. Network belum memiliki request final store/update; riwayat belum berisi pengajuan baru.
- [ ] Modal berisi pengingat memeriksa periode, kontak, tanggal, biodata dan semua dokumen. Fokus awal pada Periksa kembali; Tab tetap di dialog. Periksa kembali/Escape menutup modal tanpa kirim dan fokus kembali ke tombol pembuka.
- [ ] Buka lagi, pilih Ya, kirim pendaftaran: baru ada request final. Payload tidak berisi file/field biodata, tombol menjadi Sedang mengirim, lalu menuju detail pengajuan. Klik ganda tidak membuat pengajuan/log ganda sebagaimana guard F.
- [ ] Uji revisi milik sendiri: modal menampilkan Ya, kirim revisi, request menuju pengajuan asal dengan PUT, dan registration ID tetap setelah sukses.
- [ ] Bila kembali melalui Back browser, state sending pulih. Jika draft sudah consumed atau versi berubah, biarkan server menolak/mengarahkan sesuai guard; jangan menghapus pengecekan versi demi tombol tetap berfungsi.

## 6. Berkas untuk rescan

Sesudah seluruh test dan browser lolos, salin **seluruh perubahan G1/24/25 yang belum dipindah**, lalu tambahkan:

```text
resources/views/sidang_ta_registrations/wizard-review.blade.php
tests/Feature/SidangTaReviewUiTest.php
tests/Feature/SidangTaWizardTest.php
```

Jangan hanya menyalin dua file ini jika dadu-test masih sebelum G1. Jangan menyalin `.env`, data database, phpunit.xml sementara, cache/build/vendor/node_modules. Sesudah tersinkron, minta rescan aktual. Berikutnya tetap G2b: card Data akademik saat pengajuan dan gate tombol Perbaiki pada detail, seperti daftar visual sebelumnya.

Referensi: [Alpine events](https://alpinejs.dev/essentials/events) dan [x-data scope](https://alpinejs.dev/directives/data), diperiksa melalui Context7; [MDN dialog](https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Elements/dialog) untuk pola showModal/close dan fokus.

## Catatan verifikasi panduan

Blok test PHP lolos lint. Blade dikompilasi di memori tanpa resolusi component tag/cache dan hasil PHP lolos lint; handler JavaScript modal juga lolos syntax check. Pemeriksaan ancestor form pada test dibuktikan dengan dua potongan DOM: tombol di luar form menghasilkan nol ancestor form, tombol di dalam form menghasilkan satu. Tidak menjalankan test Laravel/DB, browser atau build. Source kedua folder tetap tidak berubah setelah penulisan; hasil 90 test masih harus dibuktikan pengguna setelah menerapkan kode.
