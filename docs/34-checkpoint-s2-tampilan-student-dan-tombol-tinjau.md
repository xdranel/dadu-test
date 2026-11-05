# 34 — Rescan S1 dan S2: tampilan student serta tombol tinjau

**Goal:** tombol Tinjau jelas nonaktif sebelum seluruh tahap lengkap dan tersimpan; selesaikan daftar tampilan student yang disetujui pada [31](31-rancangan-draft-parsial-dan-review-petugas-ta.md).

**Architecture:** gunakan `$completedThrough` dari controller untuk readiness seluruh tahap. Pertahankan binding dirty/busy dan guard server. Ubah view lokal TA saja; tidak menyalin asumsi satu periode aktif dari Yudisium.

**Tech stack:** Blade, Alpine dan Tailwind existing; Pest dengan MySQL Podman. Tidak ada migrasi, dependency atau route baru.

## 1. Hasil rescan dan batas perubahan

Pengguna melaporkan **29 test fokus, 110 test PHP keseluruhan, serta manual S1 lulus**, dan file telah disalin. Rescan menemukan source TA pada app/resources/routes/tests identik dengan project asli, 110 definisi test, dan koreksi33 sudah terpasang. Pemeriksaan sintaks 31 file PHP TA lulus. Review independen statis tidak menemukan blocker S1.

Tombol tahap 4 sudah memiliki disabled HTML dan Alpine. Namun `.btn-primary` tidak mengatur tampilan disabled, sehingga tombol dapat terlihat aktif. Panduan ini memberi opacity/cursor khusus, aria, keterangan, dan kondisi lengkap seluruh tahap. Tombol baru aktif setelah data/file lengkap **disimpan**, bukan sekadar selesai dipilih di browser.

S2 juga menghapus Unduh berkas hanya pada form draft tahap2–4, menghapus kalimat bantuan yang diminta, dan mengganti card atas index dengan header/action seperti Yudisium. Section Draft tersimpan, modal hapus dan tabel Riwayat tetap dipertahankan. Preview serta detail tetap memiliki tombol unduh; endpoint privat tidak dihapus. Petugas belum diubah.

## 2. File dan urutan kerja

Edit file existing:

```text
resources/views/sidang_ta_registrations/wizard.blade.php
resources/views/sidang_ta_registrations/partials/wizard-upload.blade.php
resources/views/sidang_ta_registrations/partials/wizard-academic.blade.php
resources/views/sidang_ta_registrations/index.blade.php
tests/Feature/SidangTaWizardUiTest.php
```

Buat test baru:

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
php artisan make:test SidangTaStudentPolishTest --pest
```

- [ ] Isi test lengkap bagian7 dan jalankan fokus bagian8 dahulu. Sebagian assertion tampilan baru semestinya gagal sebelum perubahan view.
- [ ] Terapkan bagian3–6 dan satu koreksi assertion test lama pada bagian6.
- [ ] Jalankan fokus, suite keseluruhan, Node/build, lalu manual. Setelah lulus salin dan minta rescan.

## 3. Tombol Tinjau tahap 4

Di blok `@php` awal wizard, tambahkan sebelum `$permission = ...`:

```php
$reviewReady = $draft && $completedThrough === 4;
```

`$completedThrough` sudah disediakan controller: 4 hanya jika semua tahap lengkap termasuk keberadaan file aktual. Tidak perlu mengubah controller atau menambahkan perhitungan di JavaScript.

Dalam `@if($draft && $step === 4)` dan `@can($permission)`, ganti **seluruh form `data-review-draft` yang lama** dengan berikut; pertahankan penutup @can/@if:

```blade
                        <form data-review-draft method="POST" class="campus-card space-y-3 p-5"
                              action="{{ route('sidang-ta-drafts.review', $draft) }}"
                              x-on:submit="if (dirty || busy || {{ $reviewReady ? 'false' : 'true' }}) { $event.preventDefault(); } else { busy = true; }">
                            @csrf <input type="hidden" name="version" value="{{ $draft->version }}">
                            <p class="text-sm leading-relaxed text-slate-600">
                                Draft harus tersimpan dan seluruh tahap lengkap sebelum ditinjau. Pendaftaran baru dikirim setelah konfirmasi pada halaman tinjau.
                            </p>
                            <p id="review-readiness" class="text-xs text-slate-500">Lengkapi seluruh dokumen dan isian, lalu tekan Simpan draft untuk mengaktifkan tinjauan.</p>
                            <button data-review-submit type="submit" aria-describedby="review-readiness"
                                    aria-disabled="{{ $reviewReady ? 'false' : 'true' }}"
                                    :aria-disabled="dirty || busy || {{ $reviewReady ? 'false' : 'true' }} ? 'true' : 'false'"
                                    class="btn btn-primary disabled:cursor-not-allowed disabled:opacity-50 disabled:shadow-none"
                                    @disabled(!$reviewReady)
                                    :disabled="dirty || busy || {{ $reviewReady ? 'false' : 'true' }}">
                                Tinjau Sebelum Kirim
                            </button>
                        </form>
```

Kondisi submit, disabled HTML, binding disabled dan aria memakai readiness yang sama. Dirty/busy tetap menonaktifkan setelah pilihan file/radio berubah. `x-on:submit` setara dengan shorthand Alpine `@submit`, tetapi atribut lengkapnya juga dapat diperiksa DomCrawler pada test. Syarat periode masih terbuka dan versi terbaru tetap diperiksa service review; halaman terbuka tidak dapat menjamin perubahan eksternal sesudah render.

## 4. Hapus tombol unduh pada form draft

Di `resources/views/sidang_ta_registrations/partials/wizard-upload.blade.php`, hapus **hanya** elemen berikut pada `@if($available)`:

```blade
<a class="btn btn-secondary text-xs" href="{{ route('sidang-ta-draft-documents.download', $existing) }}">
    Unduh berkas
</a>
```

Pertahankan nama/ukuran/status Berkas tersimpan, keterangan mengganti, peringatan file hilang, input file, drag/drop dan Batalkan pilihan baru. Tidak perlu mengubah JavaScript. Jangan menghapus route atau method download: preview masih menggunakannya.

## 5. Hapus kalimat bantuan

Di `resources/views/sidang_ta_registrations/partials/wizard-academic.blade.php`, hapus elemen `<p>` berikut:

```blade
<p class="text-xs leading-relaxed text-slate-500">
    Informasi akademik bersifat baca saja. Lengkapi dokumen sesuai persyaratan pada form.
</p>
```

Pertahankan section informasi akademik dan jadwal di sisi kanan pada seluruh tahap.

## 6. Header index dan satu test existing

Pada `resources/views/sidang_ta_registrations/index.blade.php`, ganti **section pertama saja**, mulai `<section class="campus-card space-y-3 p-5">` sampai penutup `</section>` tepat sebelum `data-testid="drafts-panel"`, dengan berikut. Wrapper `<div class="space-y-5">`, panel draft dan seluruh tabel riwayat tetap ada.

```blade
        @if($errors->any())
            <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">{{ $errors->first() }}</div>
        @endif
        @if(session('success'))
            <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif
        <div data-registration-header class="flex flex-wrap items-center justify-between gap-4">
            <div class="min-w-0 space-y-1">
                <h2 class="text-xl font-bold text-slate-800">Pendaftaran Sidang Tugas Akhir</h2>
                <p class="text-xs leading-relaxed text-slate-500 sm:text-sm">Daftar pengajuan pendaftaran sidang tugas akhir Anda.</p>
                <span data-open-period-count class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">{{ $openPeriods->count() }} periode terbuka</span>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if($activeRegistration)
                    <a data-registration-active class="btn btn-secondary" href="{{ route('sidang-ta-registrations.show', $activeRegistration) }}">Lihat pengajuan aktif</a>
                    @if($activeRegistration->status === 'needs_review')
                        @can('sidang_ta_registration_update')
                            <a data-registration-revise class="btn btn-primary" href="{{ route('sidang-ta-registrations.edit', $activeRegistration) }}">Mulai revisi</a>
                        @endcan
                    @endif
                @elseif($openPeriods->isNotEmpty())
                    @can('sidang_ta_registration_create')
                        <a data-registration-start href="{{ route('sidang-ta-registrations.create') }}" class="btn btn-primary shadow-sm">
                            <svg aria-hidden="true" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Mulai Pendaftaran
                        </a>
                    @else
                        <p data-registration-readonly class="text-xs text-slate-500">Akses Anda hanya untuk melihat pengajuan dan draft tersimpan.</p>
                    @endcan
                @else
                    <button data-registration-closed type="button" disabled class="btn btn-secondary cursor-not-allowed opacity-60">
                        <svg aria-hidden="true" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                        Pendaftaran Ditutup
                    </button>
                @endif
            </div>
        </div>
        @if(!$activeRegistration && $openPeriods->isEmpty())
            <p class="text-xs leading-relaxed text-slate-500">Pendaftaran baru belum tersedia. Draft tersimpan tetap dapat dilihat di bawah.</p>
        @endif
```

Jumlah periode memakai seluruh periode terbuka. Pengajuan aktif tetap menghalangi tombol pendaftaran baru; revisi hanya terlihat dengan permission update. Tidak ada notifikasi Yudisium yang disalin.

Pada test existing `g1_missing_saved_file_keeps_step_incomplete_and_preserves_other_saved_files` dalam `tests/Feature/SidangTaWizardUiTest.php`, ganti assertion download saja:

```php
$response->assertSee(route('sidang-ta-draft-documents.download', $other), false)
    ->assertDontSee(route('sidang-ta-draft-documents.download', $document), false);
```

Menjadi:

```php
$response->assertDontSee(route('sidang-ta-draft-documents.download', $other), false)
    ->assertDontSee(route('sidang-ta-draft-documents.download', $document), false)
    ->assertSee($other->file_name);
```

Pertahankan seluruh assertion lain (counter5dari6, file aktual, version2 dan metadata15). Jangan menambah test duplikat atau mengubah test download endpoint/preview, karena fitur tersebut tetap berlaku.

## 7. Test baru lengkap

Isi seluruh `tests/Feature/SidangTaStudentPolishTest.php` dengan berikut. Fixture langsung menyiapkan dokumen tersimpan untuk pengujian UI, seperti WizardUi existing; test perubahan cabang memakai endpoint nyata.

```php
<?php

use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDraft;
use App\Models\Student;
use App\Models\User;
use App\Support\SidangTaDocumentRequirements as Requirements;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Symfony\Component\DomCrawler\Crawler;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create([
        'username' => '1202200001', 'email' => 'student-polish@mahasiswa.test',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false,
    ]);
    $this->student = Student::create([
        'student_number' => '1202200001', 'name' => 'Student Polish Test',
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
});


test('s2_diploma_review_is_disabled_until_both_saved_files_exist', function () {
    $draft = ($this->completeDraft)();
    $missing = $draft->documents()->where('document_type', 'diploma_biodata_form')->firstOrFail();
    Storage::disk('local')->delete($missing->file_path);
    $url = route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 4]);
    $crawler = new Crawler($this->get($url)->assertOk()->getContent());
    $button = $crawler->filter('[data-review-submit]')->getNode(0);
    expect($button->hasAttribute('disabled'))->toBeTrue();
    expect($button->getAttribute('aria-disabled'))->toBe('true');
    expect($button->getAttribute('class'))->toContain('disabled:opacity-50')
        ->toContain('disabled:cursor-not-allowed');
    expect($crawler->filter('[data-step-progress]')->text(null, true))->toContain('1 dari 2 dokumen tersimpan.');
    $this->postJson(route('sidang-ta-drafts.review', $draft), ['version' => $draft->version])
        ->assertUnprocessable();
    Storage::disk('local')->put($missing->file_path, '%PDF-ui');
    $crawler = new Crawler($this->get($url)->assertOk()->getContent());
    $button = $crawler->filter('[data-review-submit]')->getNode(0);
    expect($button->hasAttribute('disabled'))->toBeFalse();
    expect($button->getAttribute('aria-disabled'))->toBe('false');
    expect($button->getAttribute(':disabled'))->toContain('dirty || busy');
    expect($crawler->filter('[data-review-draft]')->attr('x-on:submit'))->toContain('dirty || busy');
});

test('s2_pdm_review_requires_saved_pdm_after_switching_branch', function () {
    $draft = ($this->completeDraft)();
    $save = route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 4]);
    $this->postJson($save, ['version' => $draft->version, 'diploma_biodata_accurate' => false])
        ->assertOk()->assertJsonPath('data.first_incomplete_step', 4);
    $url = route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 4]);
    $crawler = new Crawler($this->get($url)->assertOk()->getContent());
    expect($crawler->filter('[data-review-submit]')->getNode(0)->hasAttribute('disabled'))->toBeTrue();
    expect($crawler->filter('[data-step-progress]')->text(null, true))->toContain('0 dari 1 dokumen tersimpan.');
    $this->postJson($save, ['version' => $draft->fresh()->version, 'documents' => [
        'pdm_submission_proof' => UploadedFile::fake()->create('pdm.pdf', 100, 'application/pdf'),
    ]])->assertOk()->assertJsonPath('data.first_incomplete_step', null);
    $crawler = new Crawler($this->get($url)->assertOk()->getContent());
    expect($crawler->filter('[data-review-submit]')->getNode(0)->hasAttribute('disabled'))->toBeFalse();
});

test('s2_upload_steps_hide_downloads_and_removed_hint_but_preview_keeps_downloads', function () {
    $draft = ($this->completeDraft)();
    $documents = $draft->documents()->get();
    foreach ([2, 3, 4] as $step) {
        $response = $this->get(route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => $step]))->assertOk();
        $crawler = new Crawler($response->getContent());
        $response->assertSee('Berkas tersimpan')->assertDontSee('Unduh berkas')
            ->assertDontSee('Informasi akademik bersifat baca saja.');
        foreach ($documents as $document) {
            $response->assertDontSee(route('sidang-ta-draft-documents.download', $document), false);
        }
        foreach (Requirements::typesForStep($step, true) as $type) {
            expect($crawler->filter('[data-upload-type="'.$type.'"]')->text(null, true))
                ->toContain($documents->firstWhere('document_type', $type)->file_name);
        }
    }
    $this->postJson(route('sidang-ta-drafts.review', $draft), ['version' => $draft->version])->assertOk();
    $response = $this->get(route('sidang-ta-drafts.preview', $draft))->assertOk();
    foreach ($documents as $document) {
        $response->assertSee(route('sidang-ta-draft-documents.download', $document), false);
    }
    $this->get(route('sidang-ta-draft-documents.download', $documents->first()))->assertOk();
});

test('s2_index_keeps_draft_and_history_when_multiple_periods_close', function () {
    $draft = ($this->completeDraft)();
    $second = SidangTaPeriod::create(['name' => 'Periode S2 kedua', 'is_active' => true,
        'open_at' => now()->subHour(), 'close_at' => now()->addDay()]);
    $url = route('sidang-ta-registrations.index');
    $crawler = new Crawler($this->get($url)->assertOk()->getContent());
    $header = $crawler->filter('[data-registration-header]');
    expect($header->count())->toBe(1);
    expect($header->attr('class'))->not->toContain('campus-card');
    expect($header->filter('[data-open-period-count]')->text(null, true))->toBe('2 periode terbuka');
    expect($header->filter('[data-registration-start]')->attr('href'))
        ->toBe(route('sidang-ta-registrations.create'));
    $this->period->update(['close_at' => now()->subMinute()]);
    $second->update(['close_at' => now()->subMinute()]);
    $response = $this->get($url)->assertOk()->assertSee('Riwayat Pengajuan');
    $crawler = new Crawler($response->getContent());
    expect($crawler->filter('[data-registration-start]')->count())->toBe(0);
    expect($crawler->filter('[data-registration-closed][disabled]')->count())->toBe(1);
    expect($crawler->filter('[data-open-period-count]')->text(null, true))->toBe('0 periode terbuka');
    expect($crawler->filter('[data-testid="drafts-panel"]')->count())->toBe(1);
    $response->assertSee(route('sidang-ta-drafts.edit', $draft), false);
});

test('s2_index_active_registration_hides_start_and_respects_revision_permission', function () {
    $registration = SidangTaRegistration::create([
        'sidang_ta_period_id' => $this->period->id, 'student_number' => $this->student->student_number,
        'student_name' => $this->student->name, 'study_program' => $this->student->study_program,
        'student_phone' => '081234567890', 'parent_phone' => '081234567891',
        'graduation_payment_date' => today()->toDateString(), 'diploma_biodata_accurate' => true,
        'status' => 'needs_review', 'submitted_at' => now(),
    ]);
    $url = route('sidang-ta-registrations.index');
    $crawler = new Crawler($this->get($url)->assertOk()->getContent());
    $header = $crawler->filter('[data-registration-header]');
    expect($header->filter('[data-registration-start]')->count())->toBe(0);
    expect($header->filter('[data-registration-active]')->attr('href'))
        ->toBe(route('sidang-ta-registrations.show', $registration));
    expect($header->filter('[data-registration-revise]')->attr('href'))
        ->toBe(route('sidang-ta-registrations.edit', $registration));
    $this->user->revokePermissionTo('sidang_ta_registration_update');
    $crawler = new Crawler($this->get($url)->assertOk()->getContent());
    expect($crawler->filter('[data-registration-revise]')->count())->toBe(0);
    $registration->update(['status' => 'submitted']);
    $this->user->givePermissionTo('sidang_ta_registration_update');
    $crawler = new Crawler($this->get($url)->assertOk()->getContent());
    expect($crawler->filter('[data-registration-revise]')->count())->toBe(0);
});

test('s2_read_only_owner_keeps_draft_access_without_create_or_delete_controls', function () {
    $draft = ($this->completeDraft)();
    $this->user->revokePermissionTo('sidang_ta_registration_create');
    $this->user->revokePermissionTo('sidang_ta_registration_update');
    $response = $this->get(route('sidang-ta-registrations.index'))->assertOk();
    $crawler = new Crawler($response->getContent());
    expect($crawler->filter('[data-registration-start]')->count())->toBe(0);
    expect($crawler->filter('[data-registration-readonly]')->count())->toBe(1);
    expect($crawler->filter('[data-testid="drafts-panel"]')->count())->toBe(1);
    expect($crawler->filter('[data-open-discard]')->count())->toBe(0);
    $response->assertSee(route('sidang-ta-drafts.edit', $draft), false);
});

```

## 8. Command verifikasi

```bash
php artisan view:clear
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaStudentPolishTest.php tests/Feature/SidangTaPartialDraftTest.php tests/Feature/SidangTaWizardUiTest.php tests/Feature/SidangTaReviewUiTest.php tests/Feature/SidangTaDraftCardTest.php
```

Target fokus **35 passed**: 6 baru + 12 Partial + 9 WizardUi + 5 ReviewUi + 3 DraftCard. Satu assertion existing diganti, tidak menambah test di file WizardUi.

Setelah fokus lulus:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaDraftSchemaTest.php tests/Feature/SidangTaPeriodTest.php tests/Feature/SidangTaDraftTest.php tests/Feature/SidangTaDraftUploadTest.php tests/Feature/SidangTaRevisionDraftTest.php tests/Feature/SidangTaWizardTest.php tests/Feature/SidangTaFinalizationTest.php tests/Feature/SidangTaWizardUiTest.php tests/Feature/SidangTaDraftCardTest.php tests/Feature/SidangTaReviewUiTest.php tests/Feature/SidangTaDetailUiTest.php tests/Feature/SidangTaPartialDraftTest.php tests/Feature/SidangTaStudentPolishTest.php
node tests/Js/sidang-ta-entry.test.mjs
node tests/Js/sidang-ta-wizard.test.mjs
npm run build
```

Target suite TA **116 passed**, Node **9 passed**, build berhasil. Tetap memakai schema MySQL Podman `dadu_testing`; jangan menjalankan suite RefreshDatabase terhadap database aplikasi `dadu`. Tidak perlu migration baru atau perubahan phpunit.xml permanen.

## 9. Manual setelah test fokus/suite lulus

- [ ] Tahap 4 tanpa pilihan atau hanya satu dari dua dokumen Sesuai: Tinjau terlihat redup, cursor nonaktif, tidak bisa ditekan/diaktifkan dengan keyboard.
- [ ] Lengkapi pilihan dan file tetapi belum save: tetap nonaktif. Save, lalu Tinjau aktif ketika semua tahap lengkap. Coba juga cabang PDM dengan satu PDF.
- [ ] Sesudah lengkap, ubah radio/pilih pengganti/batalkan pilihan baru: Tinjau kembali nonaktif sampai save. Bila berpindah cabang, isi persyaratan cabang baru dan save dahulu.
- [ ] Pada tahap2–4, Berkas tersimpan tetap menampilkan nama/ukuran tanpa tombol unduh. Drag/drop, pilih pengganti dan Batalkan pilihan baru tetap berfungsi.
- [ ] Preview dan detail masih menyediakan tombol unduh privat; modal kirim final masih bekerja.
- [ ] Informasi akademik serta jadwal di kanan tetap ada pada tahap1–4; kalimat bantuan yang diminta hilang.
- [ ] Index: header/action mengikuti pola Yudisium, jumlah seluruh periode terbuka benar, Draft tersimpan tetap ringkas dan modal hapus tetap ada; tabel Riwayat tidak berubah.
- [ ] Ada pengajuan submitted: hanya tombol lihat aktif; needs_review: tombol revisi sesuai permission. Tidak muncul tombol pendaftaran baru bersamaan pengajuan aktif.
- [ ] Semua periode tutup: tombol Pendaftaran Ditutup disabled; draft tetap bisa dibuka. Akun read-only tidak mendapat tombol mulai/revisi/hapus yang memerlukan permission tulis.
- [ ] Periksa di layar sempit: judul dan action membungkus dengan rapi, panel draft dan riwayat tidak saling menimpa.

## 10. Salinan untuk rescan berikutnya

Setelah test/manual lulus, salin kelima file view/test existing yang disebut bagian2 dan file test baru ke dadu-test. Tidak ada file aplikasi yang perlu dihapus; penghapusan di checkpoint ini hanya elemen Blade dan penggantian assertion yang dijelaskan.

S2 belum dianggap selesai sebelum pengguna melaporkan hasil implementasi. Sesudah salinan dan rescan bersih, lanjut **P1**: satu pintu Review Sidang TA dan pengaturan periode dari halaman review, sesuai31. Kemampuan verifikasi per dokumen petugas tetap rencana P3.

## 11. Batas verifikasi asisten

Rescan dan pemeriksaan sintaks terhadap source S1 dilakukan tanpa menjalankan aplikasi/DB. Empat blok PHP dan empat blok Blade pada panduan S2 lulus pemeriksaan sintaks (Blade dikompilasi di memori tanpa component tags). Ekspresi Alpine juga diperiksa sintaksnya.

Probe render Blade di memori dengan Gate/session/URL stub memverifikasi tombol review disabled/aktif, aria dan atribut guard submit, serta header banyak periode/tertutup/pengajuan aktif/read-only. Sembilan test Node existing lulus. Review independen menemukan masalah pembacaan shorthand `@submit` oleh DOM parser pada test; sudah dikoreksi memakai `x-on:submit` dan probe ulang lulus. Tidak ditemukan blocker statis lain. Source kedua folder dipindai sebelum/sesudah penulisan dan tidak diubah oleh asisten.

Suite Feature PHP, build dan manual S2 harus dijalankan pengguna sesudah menerapkan kode. Jangan memakai hasil 110 test S1 sebagai bukti 116 test S2 sudah lulus.

Referensi dokumentasi Tailwind melalui Context7: [state variants termasuk disabled](https://tailwindcss.com/docs/hover-focus-and-other-states).
