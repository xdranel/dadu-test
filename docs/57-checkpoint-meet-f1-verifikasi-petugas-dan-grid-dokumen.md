# 57 — Meet F1: verifikasi petugas versi2 dan kartu dokumen dua kolom

> Pelaksana: pengguna menerapkan kode di folder asli, menguji, lalu menyalin hasil ke `dadu-test`. Asisten menggunakan skill writing-plans untuk menyusun panduan; source aplikasi tidak diubah asisten. Melanjutkan [rancangan45](45-rancangan-revisi-hasil-meet-sidang-ta.md).

**Tujuan:** petugas dapat meninjau, memverifikasi dokumen, serta menyetujui/menolak pengajuan versi2 dengan layout sesuai tahap dan status yang jelas.

**Arsitektur:** transaksi dan optimistic version tetap digunakan. Pengajuan versi2 hanya dapat dimutasi petugas ketika submitted; perubahan status dokumen tidak otomatis membuka revisi mahasiswa. Resolver tetap memakai snapshot persyaratan pengajuan, bukan data akademik terkini. Perilaku versi1 dipertahankan untuk kompatibilitas.

**Teknologi:** Laravel/Pest, Blade/Alpine/Tailwind existing, MySQL Podman; tidak menambah paket.

## 0. Hasil rescan sebelum checkpoint

Pengguna melaporkan seluruh test56b lulus dan file dipindahkan. Pemeriksaan21file menemukan **20file identik** antara kedua folder. Satu perbedaan:

`app/Services/SidangTaRegistrationReview.php` pada `dadu-test` belum mempunyai guard sementara versi2 yang masih ada pada folder asli. Ini menjelaskan mengapa hasil test folder asli belum tentu sama bila dijalankan pada workspace sekarang.

Jika akan menjalankan baseline di `dadu-test` sebelum implementasi57, samakan file tersebut dengan menjalankan command berikut secara manual:

```bash
cp /home/xdranel/Code/Project/php-laravel/dadu/app/Services/SidangTaRegistrationReview.php /home/xdranel/Code/Project/secret/dadu-test/app/Services/SidangTaRegistrationReview.php
```

Kode57 di bawah mengganti service itu sepenuhnya dengan aturan baru. Implementasi tetap dilakukan di folder asli dahulu; setelah lulus baru salin delapan file pada bagian9. Asisten tidak menjalankan command copy tersebut.

## 1. Perilaku checkpoint ini

- Versi2 submitted: Verifikasi/Minta Perbaikan tersedia per dokumen. Verified berwarna hijau, rejected berwarna kuning, pending netral, file/metadata hilang berwarna oranye.
- Kartu dikelompokkan menurut tahap2/3/4 dengan dua kolom pada layar desktop dan satu kolom pada layar kecil. Hanya upload yang berlaku untuk pengajuan itu yang muncul.
- Tombol Verifikasi disabled setelah dokumen verified. Minta Perbaikan tetap tersedia agar kesalahan verifikasi bisa dibetulkan. Dokumen rejected dapat diverifikasi kembali; catatannya dikosongkan oleh server.
- Minta Perbaikan dokumen versi2 **tetap submitted**. Ini menyimpan temuan petugas, belum mengirim permintaan revisi kepada mahasiswa.
- Catatan rejected dapat diperbaiki sebelum keputusan. Request identik tidak menambah versi/log; perubahan catatan yang sah menambah versi/log.
- Setujui baru tersedia setelah semua dokumen wajib terverifikasi dan file ada. Tolak memerlukan catatan. Snapshot, berkas, dan riwayat yang sudah tersimpan tetap dipertahankan.
- Versi2 needs_review/approved/rejected: semua mutasi petugas ditolak server; preview tetap tersedia. Ini menjaga pengajuan yang sedang direvisi atau sudah diputuskan.
- **Minta Revisi versi2 masih disabled dan ditolak server pada checkpoint ini.** Membukanya sekarang akan memakai mekanisme revisi lama yang belum membatasi upload dan masih mereset semua status. Checkpoint berikutnya akan membuka revisi terarah dari temuan dokumen, mempertahankan verified berkas lain, dan mengizinkan pengiriman ulang versi2.
- Versi1 tetap memakai perilaku lama: rejected dokumen langsung needs_review dan review dapat diteruskan pada status tersebut.
- Waktu buka/tenggat periode ditampilkan pada detail petugas. Riwayat tetap tertutup secara default, dan aksi dokumen kembali ke anchor kartu yang sama.

Pembuatan draft biasa masih versi1 selama integrasi meet. Jangan mengubah versi semua pengajuan/draft atau mengaktifkan seluruh flow baru pada checkpoint ini. JSON resmi yang belum tersedia masih menunggu; **tidak perlu import JSON baru** untuk57.

## 2. File dan urutan pengerjaan

Kerjakan di `/home/xdranel/Code/Project/php-laravel/dadu/`.

```bash
php artisan make:test SidangTaMeetStaffReviewTest --pest
php artisan make:view sidang_ta_registrations.admin.partials.meet-document-groups
```

Command `make:view` sudah diperiksa pada framework yang terpasang. Tidak ada migrasi atau seeder baru; jangan menjalankan migrate:fresh.

- [ ] Isi file test baru persis pada path bagian3; jalankan sekali untuk melihat kegagalan pada perilaku versi2 yang masih ditahan.
- [ ] Ganti satu test existing pada bagian4. Test versi2 kirim revisi yang masih ditahan tetap dipertahankan.
- [ ] Terapkan enam file produksi bagian5–6.
- [ ] Jalankan test bagian7, lalu manual bagian8.
- [ ] Setelah hasil lulus, salin delapan file bagian9.

Fokus review/test: dokumen rejected tidak boleh memulai revisi otomatis; versi request lama tidak boleh menimpa keputusan; file hilang meskipun metadata verified harus menahan persetujuan; form yang gagal tidak boleh diam-diam memakai versi terbaru; perbaikan status tidak boleh membawa catatan lama ke verified. Kasus-kasus ini diuji pada file baru.

## 3. File test baru lengkap — 24 kasus

Seluruh isi kode berikut masuk ke **`tests/Feature/SidangTaMeetStaffReviewTest.php`**. File ini mempunyai `beforeEach` dan helper sendiri. Tidak ditempel ke WizardUiTest atau MeetUiTest.

### `tests/Feature/SidangTaMeetStaffReviewTest.php`

Ganti seluruh isi **file pada path tersebut** dengan kode lengkap berikut.

```php
<?php

use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\User;
use App\Support\SidangTaAdmissionPolicy as Policy;
use App\Support\SidangTaReviewEligibility as Eligibility;
use App\Support\SidangTaMeetRequirements as Meet;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\DomCrawler\Crawler;

beforeEach(function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-11-04 12:00:00', config('app.timezone')));
    Storage::fake('local');
    $permission = Permission::firstOrCreate(['name' => 'sidang_ta_registration_review', 'guard_name' => 'web']);
    $this->reviewer = User::factory()->create(['status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
    $this->reviewer->givePermissionTo($permission);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($this->reviewer);
    $this->period = SidangTaPeriod::create(['name' => 'Meet Staff', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay()]);
    $this->sequence = 0;
    $this->makeMeetRegistration = function (string $mode = 'automatic', bool $accurate = true, array $overrides = []) {
        $this->sequence++;
        $facts = ['level_2_passed' => true, 'ta2_registered' => true, 'skpi_approved' => true,
            'tak_score' => 60, 'passed_credits' => 120];
        $registration = SidangTaRegistration::create(array_replace([
            'sidang_ta_period_id' => $this->period->id, 'requirements_version' => 2,
            'student_number' => 'M57'.str_pad((string) $this->sequence, 7, '0', STR_PAD_LEFT),
            'student_name' => 'Meet Staff Student', 'study_program' => 'Informatika',
            'student_phone' => '081234567890', 'parent_phone' => '081234567891',
            'graduation_payment_date' => today()->toDateString(), 'diploma_biodata_accurate' => $accurate,
            'status' => 'submitted', 'submitted_at' => now(),
            'academic_snapshot' => ['student_name' => 'Nama Snapshot', 'tak_score' => 60,
                'admission' => ['facts' => $facts, 'academic' => Policy::academic($facts),
                    'eprt' => Policy::eprt($mode === 'automatic' ? 450 : 449, $mode === 'automatic' ? 1 : 3)]],
        ], $overrides))->fresh();
        foreach (Eligibility::requiredTypes($registration) as $type) {
            $path = 'sidang_ta/meet57/'.$registration->id.'/'.$type.'.pdf';
            Storage::disk('local')->put($path, '%PDF-1.4 meet staff');
            $registration->documents()->create(['document_type' => $type, 'file_path' => $path,
                'file_name' => $type.'.pdf', 'file_size' => 21, 'file_type' => 'pdf', 'status' => 'pending']);
        }
        return $registration;
    };
    $this->reviewDocument = function ($registration, $document, array $overrides = []) {
        return $this->postJson(route('admin.sidang-ta-registrations.documents.review', [
            'sidang_ta_registration' => $registration, 'document' => $document,
        ]), array_replace(['registration_version' => $registration->fresh()->lock_version,
            'status' => 'verified'], $overrides));
    };
    $this->decideMeet = function ($registration, string $action, array $overrides = []) {
        return $this->postJson(route('admin.sidang-ta-registrations.review', $registration), array_replace([
            'registration_version' => $registration->fresh()->lock_version,
            'action' => $action, 'review_notes' => $action === 'approved' ? null : 'Catatan petugas',
        ], $overrides));
    };
    $this->reviewPage = function ($registration) {
        return new Crawler($this->get(route('admin.sidang-ta-registrations.show', $registration))->assertOk()->getContent());
    };
});

afterEach(function () { $this->travelBack(); });

test('meet_staff_verifies_one_file_without_approving_the_registration', function () {
    $registration = ($this->makeMeetRegistration)();
    $document = $registration->documents()->firstOrFail();
    $paths = Storage::disk('local')->allFiles();
    $snapshot = $registration->academic_snapshot;
    ($this->reviewDocument)($registration, $document)->assertOk()->assertJsonPath('data.status', 'submitted')
        ->assertJsonPath('data.lock_version', 2);
    expect($document->fresh()->status)->toBe('verified');
    expect($registration->documents()->where('status', 'pending')->count())->toBe(6);
    expect($registration->logs()->firstOrFail()->action)->toBe('document_verified');
    expect($registration->fresh()->academic_snapshot)->toBe($snapshot);
    expect(Storage::disk('local')->allFiles())->toBe($paths);
});

test('meet_staff_rejecting_a_document_does_not_start_student_revision', function () {
    $registration = ($this->makeMeetRegistration)();
    $document = $registration->documents()->firstOrFail();
    ($this->reviewDocument)($registration, $document, ['status' => 'rejected', 'notes' => '  Perbaiki PDF  '])
        ->assertOk()->assertJsonPath('data.status', 'submitted');
    expect($document->fresh()->notes)->toBe('Perbaiki PDF');
    expect($document->fresh()->status)->toBe('rejected');
    expect($registration->fresh()->status)->toBe('submitted');
    $log = $registration->logs()->firstOrFail();
    expect($log->from_status)->toBe('submitted');
    expect($log->to_status)->toBe('submitted');
});

test('meet_staff_can_reverse_both_document_states_and_verification_clears_notes', function () {
    $registration = ($this->makeMeetRegistration)('waiver');
    $document = $registration->documents()->where('document_type', Meet::WAIVER)->firstOrFail();
    ($this->reviewDocument)($registration, $document)->assertOk();
    ($this->reviewDocument)($registration, $document, ['status' => 'rejected', 'notes' => 'Perbaiki surat'])->assertOk();
    ($this->reviewDocument)($registration, $document, ['notes' => 'Tidak boleh menjadi catatan verified'])->assertOk();
    expect($document->fresh()->status)->toBe('verified');
    expect($document->fresh()->notes)->toBeNull();
    expect($registration->fresh()->status)->toBe('submitted');
    expect($registration->fresh()->lock_version)->toBe(4);
    expect($registration->logs()->count())->toBe(3);
    expect($registration->logs()->where('action', 'document_verified')->latest('id')->firstOrFail()->notes)
        ->toContain('Surat Permakluman EPRT')->not->toContain('Tidak boleh');
});

test('meet_staff_same_verification_is_noop_even_when_unused_notes_are_sent', function () {
    $registration = ($this->makeMeetRegistration)();
    $document = $registration->documents()->firstOrFail();
    ($this->reviewDocument)($registration, $document)->assertOk();
    ($this->reviewDocument)($registration, $document, ['notes' => 'Diabaikan'])->assertOk()->assertJsonPath('data.lock_version', 2);
    expect($document->fresh()->notes)->toBeNull();
    expect($registration->logs()->count())->toBe(1);
});

test('meet_staff_can_correct_rejected_notes_without_changing_registration_state', function () {
    $registration = ($this->makeMeetRegistration)();
    $document = $registration->documents()->firstOrFail();
    ($this->reviewDocument)($registration, $document, ['status' => 'rejected', 'notes' => 'Catatan A'])->assertOk();
    ($this->reviewDocument)($registration, $document, ['status' => 'rejected', 'notes' => 'Catatan A'])->assertOk();
    expect($registration->fresh()->lock_version)->toBe(2);
    ($this->reviewDocument)($registration, $document, ['status' => 'rejected', 'notes' => 'Catatan B'])->assertOk();
    expect($document->fresh()->notes)->toBe('Catatan B');
    expect($registration->fresh()->lock_version)->toBe(3);
    expect($registration->logs()->count())->toBe(2);
});

test('meet_staff_document_rejection_requires_a_nonblank_note', function () {
    $registration = ($this->makeMeetRegistration)();
    $document = $registration->documents()->firstOrFail();
    foreach ([null, '', '   ', ['invalid']] as $notes) {
        ($this->reviewDocument)($registration, $document, ['status' => 'rejected', 'notes' => $notes])
            ->assertUnprocessable()->assertJsonValidationErrors('notes');
    }
    expect($registration->fresh()->lock_version)->toBe(1);
    expect($document->fresh()->status)->toBe('pending');
    expect($registration->logs()->count())->toBe(0);
});

test('meet_staff_stale_document_and_decision_requests_do_not_overwrite_newer_review', function () {
    $registration = ($this->makeMeetRegistration)();
    $documents = $registration->documents()->get();
    ($this->reviewDocument)($registration, $documents->first())->assertOk();
    ($this->reviewDocument)($registration, $documents->get(1), ['registration_version' => 1])->assertConflict();
    ($this->decideMeet)($registration, 'rejected', ['registration_version' => 1])->assertConflict();
    expect($documents->get(1)->fresh()->status)->toBe('pending');
    expect($registration->fresh()->status)->toBe('submitted');
    expect($registration->logs()->count())->toBe(1);
});

test('meet_staff_cannot_review_a_document_from_another_registration_or_inactive_branch', function () {
    $a = ($this->makeMeetRegistration)();
    $b = ($this->makeMeetRegistration)();
    ($this->reviewDocument)($a, $b->documents()->firstOrFail())->assertNotFound();
    $extra = $a->documents()->create(['document_type' => 'pdm_submission_proof', 'file_name' => 'extra.pdf',
        'file_path' => 'sidang_ta/extra.pdf', 'file_type' => 'pdf', 'status' => 'pending']);
    ($this->reviewDocument)($a, $extra)->assertUnprocessable();
    expect($a->fresh()->lock_version)->toBe(1);
    expect($b->fresh()->lock_version)->toBe(1);
});

test('meet_staff_missing_files_prevent_verification_and_approval', function () {
    $registration = ($this->makeMeetRegistration)();
    $registration->documents()->update(['status' => 'verified']);
    $document = $registration->documents()->firstOrFail();
    Storage::disk('local')->delete($document->file_path);
    ($this->reviewDocument)($registration, $document)->assertUnprocessable()->assertJsonValidationErrors('document');
    ($this->decideMeet)($registration, 'approved')->assertUnprocessable()->assertJsonValidationErrors('documents');
    $crawler = ($this->reviewPage)($registration);
    expect($crawler->filter('#review-document-'.$document->id)->attr('data-document-review-state'))->toBe('unavailable');
    expect($crawler->filter('[data-ta-approve]')->getNode(0)->hasAttribute('disabled'))->toBeTrue();
    expect($registration->fresh()->lock_version)->toBe(1);
});

test('meet_staff_approval_requires_all_required_documents_to_be_verified', function (string $mode) {
    $registration = ($this->makeMeetRegistration)($mode);
    ($this->decideMeet)($registration, 'approved')->assertUnprocessable()->assertJsonValidationErrors('documents');
    $registration->documents()->update(['status' => 'verified']);
    ($this->decideMeet)($registration, 'approved')->assertRedirect();
    expect($registration->fresh()->status)->toBe('approved');
    expect((int) $registration->fresh()->reviewed_by)->toBe($this->reviewer->id);
    expect($registration->logs()->count())->toBe(1);
    expect($registration->logs()->firstOrFail()->action)->toBe('approved');
})->with(['automatic', 'waiver']);

test('meet_staff_rejection_keeps_files_and_requires_a_note', function () {
    $registration = ($this->makeMeetRegistration)();
    $paths = Storage::disk('local')->allFiles();
    ($this->decideMeet)($registration, 'rejected', ['review_notes' => '   '])->assertUnprocessable()->assertJsonValidationErrors('review_notes');
    ($this->decideMeet)($registration, 'rejected', ['review_notes' => '  Berkas tidak sah  '])->assertRedirect();
    expect($registration->fresh()->review_notes)->toBe('Berkas tidak sah');
    expect($registration->fresh()->status)->toBe('rejected');
    expect(Storage::disk('local')->allFiles())->toBe($paths);
});

test('meet_staff_revision_request_is_still_held_without_mutating_any_saved_feedback', function () {
    $registration = ($this->makeMeetRegistration)();
    $document = $registration->documents()->firstOrFail();
    ($this->reviewDocument)($registration, $document, ['status' => 'rejected', 'notes' => 'Perbaiki scan'])->assertOk();
    ($this->decideMeet)($registration, 'needs_review')->assertUnprocessable()->assertJsonValidationErrors('action');
    expect($registration->fresh()->status)->toBe('submitted');
    expect($registration->fresh()->lock_version)->toBe(2);
    expect($document->fresh()->notes)->toBe('Perbaiki scan');
    expect($registration->logs()->count())->toBe(1);
});

test('meet_staff_waiting_revision_and_terminal_states_have_no_mutation_controls', function (string $status) {
    $registration = ($this->makeMeetRegistration)('automatic', true, ['status' => $status]);
    $document = $registration->documents()->firstOrFail();
    ($this->reviewDocument)($registration, $document)->assertUnprocessable();
    foreach (['approved', 'rejected', 'needs_review'] as $action) {
        ($this->decideMeet)($registration, $action)->assertUnprocessable();
    }
    $crawler = ($this->reviewPage)($registration);
    expect($crawler->filter('[data-doc-review-form], [data-ta-decision]')->count())->toBe(0);
    expect($crawler->filter('a[data-document-preview]')->count())->toBe(7);
    expect($registration->fresh()->lock_version)->toBe(1);
    expect($registration->logs()->count())->toBe(0);
})->with(['needs_review', 'approved', 'rejected']);

test('meet_staff_read_permission_alone_does_not_allow_document_or_decision_mutations', function () {
    $registration = ($this->makeMeetRegistration)();
    $reader = User::factory()->create(['status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
    $reader->givePermissionTo(Permission::firstOrCreate(['name' => 'sidang_ta_registration_read', 'guard_name' => 'web']));
    $this->actingAs($reader);
    ($this->reviewDocument)($registration, $registration->documents()->firstOrFail())->assertForbidden();
    ($this->decideMeet)($registration, 'approved')->assertForbidden();
    expect($registration->fresh()->lock_version)->toBe(1);
});

test('meet_staff_detail_groups_uploads_into_two_columns_with_status_colors_and_disabled_verify', function () {
    $registration = ($this->makeMeetRegistration)('waiver');
    $documents = $registration->documents()->get();
    $verified = $documents->first();
    $rejected = $documents->get(1);
    $verified->update(['status' => 'verified']);
    $rejected->update(['status' => 'rejected', 'notes' => 'Perbaiki']);
    $crawler = ($this->reviewPage)($registration);
    expect($crawler->filter('[data-meet-document-group]')->count())->toBe(3);
    expect($crawler->filter('[data-meet-document-grid]')->count())->toBe(3);
    foreach ($crawler->filter('[data-meet-document-grid]') as $node) {
        expect($node->getAttribute('class'))->toContain('lg:grid-cols-2');
    }
    expect($crawler->filter('[data-meet-document-group="2"] [data-ta-detail-document]')->count())->toBe(2);
    expect($crawler->filter('[data-meet-document-group="3"] [data-ta-detail-document]')->count())->toBe(4);
    expect($crawler->filter('[data-meet-document-group="4"] [data-ta-detail-document]')->count())->toBe(2);
    $green = $crawler->filter('#review-document-'.$verified->id);
    $yellow = $crawler->filter('#review-document-'.$rejected->id);
    expect($green->attr('class'))->toContain('bg-emerald-50')->toContain('text-emerald-900');
    expect($yellow->attr('class'))->toContain('bg-amber-50')->toContain('text-amber-900');
    expect($green->filter('[data-doc-verify]')->getNode(0)->hasAttribute('disabled'))->toBeTrue();
    expect($green->filter('[data-doc-reject]')->getNode(0)->hasAttribute('disabled'))->toBeFalse();
    expect($yellow->filter('[data-doc-verify]')->getNode(0)->hasAttribute('disabled'))->toBeFalse();
    expect($crawler->filter('[data-ta-request-revision]')->getNode(0)->hasAttribute('disabled'))->toBeTrue();
    expect($crawler->filter('[data-meet-revision-held]')->count())->toBe(1);
    expect($crawler->filter('[data-ta-review-window]')->text(null, true))->toContain($this->period->close_at->format('d/m/Y H:i'));
    expect($crawler->filter('[data-staff-registration-history]')->getNode(0)->hasAttribute('open'))->toBeFalse();
});

test('meet_staff_pdm_and_automatic_groups_only_show_their_six_uploads', function () {
    $registration = ($this->makeMeetRegistration)('automatic', false);
    $crawler = ($this->reviewPage)($registration);
    expect($crawler->filter('[data-ta-detail-document]')->count())->toBe(6);
    expect($crawler->filter('[data-meet-document-group="3"] [data-ta-detail-document]')->count())->toBe(3);
    expect($crawler->filter('[data-meet-document-group="4"] [data-ta-detail-document]')->count())->toBe(1);
    expect($crawler->filter('[data-doc-review-form]')->count())->toBe(6);
});

test('meet_staff_missing_metadata_is_visible_and_blocks_approval', function () {
    $registration = ($this->makeMeetRegistration)();
    $document = $registration->documents()->where('document_type', 'thesis_draft')->firstOrFail();
    $document->delete();
    $crawler = ($this->reviewPage)($registration);
    expect($crawler->filter('[data-ta-missing-document="thesis_draft"]')->count())->toBe(1);
    expect($crawler->filter('[data-ta-approve]')->getNode(0)->hasAttribute('disabled'))->toBeTrue();
    ($this->decideMeet)($registration, 'approved')->assertUnprocessable()->assertJsonValidationErrors('documents');
});

test('meet_staff_html_review_keeps_the_card_anchor_and_textarea_without_padding_whitespace', function () {
    $registration = ($this->makeMeetRegistration)();
    $document = $registration->documents()->firstOrFail();
    $url = route('admin.sidang-ta-registrations.show', $registration);
    $this->post(route('admin.sidang-ta-registrations.documents.review', [
        'sidang_ta_registration' => $registration, 'document' => $document,
    ]), ['registration_version' => 1, 'status' => 'rejected', 'notes' => 'fix en iki',
        '_review_form' => 'document:'.$document->id])->assertRedirect($url.'#review-document-'.$document->id);
    $card = ($this->reviewPage)($registration)->filter('#review-document-'.$document->id);
    expect($card->filter('textarea[name="notes"]')->getNode(0)->textContent)->toBe('fix en iki');
});

test('meet_staff_escapes_file_names_and_document_feedback', function () {
    $registration = ($this->makeMeetRegistration)();
    $document = $registration->documents()->firstOrFail();
    $document->update(['file_name' => '<script>badName()</script>.pdf', 'notes' => '<script>badNote()</script>', 'status' => 'rejected']);
    $card = ($this->reviewPage)($registration)->filter('#review-document-'.$document->id);
    expect($card->filter('script')->count())->toBe(0);
    expect($card->filter('[data-document-notes]')->text(null, true))->toBe('<script>badNote()</script>');
    expect($card->filter('textarea[name="notes"]')->getNode(0)->textContent)->toBe('<script>badNote()</script>');
});

test('meet_staff_unknown_requirements_version_is_rejected_before_mutation', function () {
    $registration = ($this->makeMeetRegistration)();
    $document = $registration->documents()->firstOrFail();
    $registration->update(['requirements_version' => 99]);
    ($this->reviewDocument)($registration, $document)->assertConflict();
    expect($document->fresh()->status)->toBe('pending');
    expect($registration->fresh()->lock_version)->toBe(1);
    expect($registration->logs()->count())->toBe(0);
});

test('meet_staff_failed_form_keeps_its_old_version_without_rebasing_other_cards', function () {
    $registration = ($this->makeMeetRegistration)();
    $documents = $registration->documents()->get();
    $document = $documents->first();
    $other = $documents->get(1);
    $url = route('admin.sidang-ta-registrations.show', $registration);
    $this->from($url)->post(route('admin.sidang-ta-registrations.documents.review', [
        'sidang_ta_registration' => $registration, 'document' => $document,
    ]), ['registration_version' => 1, 'status' => 'rejected', 'notes' => ['invalid'],
        '_review_form' => 'document:'.$document->id])->assertRedirect($url.'#review-document-'.$document->id)->assertSessionHasErrors('notes');
    $registration->lock_version++;
    $registration->save();
    $crawler = ($this->reviewPage)($registration);
    expect($crawler->filter('[data-doc-review-form="'.$document->id.'"] input[name="registration_version"]')->attr('value'))->toBe('1');
    expect($crawler->filter('[data-doc-review-form="'.$other->id.'"] input[name="registration_version"]')->attr('value'))->toBe('2');
    expect($crawler->filter('[data-ta-decision] input[name="registration_version"]')->attr('value'))->toBe('2');
    ($this->reviewDocument)($registration, $document, ['registration_version' => 1])->assertConflict();
    expect($registration->fresh()->lock_version)->toBe(2);
    expect($registration->logs()->count())->toBe(0);
});
```

Jalankan sebelum implementasi produksi; sebagian test akan gagal karena aksi versi2 masih ditahan dan partial baru belum dipakai:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaMeetStaffReviewTest.php
```
## 4. Perbarui satu test finalisasi existing

Pada **`tests/Feature/SidangTaMeetFinalizationTest.php`**, ganti seluruh test lama bernama `meet_final_staff_can_read_preview_and_export_new_files_but_mutation_is_held` dengan berikut. Test lain tetap ada, termasuk `meet_final_version_two_revision_stays_held_without_changing_final_or_borrowed_files`.

```php
test('meet_final_staff_can_read_export_and_verify_while_revision_request_stays_held', function () {
    $draft = ($this->markReviewed)(($this->meetDraft)('waiver'));
    $id = ($this->submitMeet)($draft)->assertOk()->json('data.id');
    $registration = SidangTaRegistration::findOrFail($id);
    $snapshot = $registration->academic_snapshot;
    ($this->asReviewer)();
    $crawler = new Crawler($this->get(route('admin.sidang-ta-registrations.show', $registration))->assertOk()->getContent());
    expect($crawler->filter('[data-meet-review-held]')->count())->toBe(0);
    expect($crawler->filter('[data-meet-revision-held]')->count())->toBe(1);
    expect($crawler->filter('[data-doc-review-form]')->count())->toBe(8);
    expect($crawler->filter('form[action="'.route('admin.sidang-ta-registrations.review', $registration).'"]')->count())->toBe(1);
    expect($crawler->filter('a[data-document-preview]')->count())->toBe(8);
    $waiver = $registration->documents()->where('document_type', Meet::WAIVER)->firstOrFail();
    $this->get(route('sidang-ta-registration-documents.preview', $waiver))->assertOk();
    $row = (new Crawler($this->get(route('admin.sidang-ta-registrations.index'))->assertOk()->getContent()))
        ->filter('[data-ta-row="'.$id.'"]');
    expect($row->filter('[data-ta-doc]')->count())->toBe(8);
    expect($row->filter('[data-ta-doc="eprt_waiver_letter"]')->attr('title'))->toContain('Surat Permakluman EPRT');
    expect($row->filter('[data-ta-doc="level_2_result_capture"], [data-ta-doc="transcript_khs"]')->count())->toBe(0);
    $export = app(SidangTaReviewExport::class)->row($registration->fresh()->load(['documents', 'period']));
    expect($export)->toHaveCount(92);
    expect($export['Surat Permakluman EPRT — Status'])->toBe('Menunggu verifikasi');
    expect($export['KRS Sidang TA 2'])->toBe('Terdaftar');
    expect($export[SidangTaRegistration::DOCUMENT_TYPES['transcript_khs'].' — Status'])->toBe('N/A');
    $this->postJson(route('admin.sidang-ta-registrations.documents.review', [
        'sidang_ta_registration' => $registration, 'document' => $waiver,
    ]), ['registration_version' => $registration->lock_version, 'status' => 'verified'])->assertOk();
    $this->postJson(route('admin.sidang-ta-registrations.review', $registration), [
        'registration_version' => $registration->fresh()->lock_version, 'action' => 'needs_review', 'review_notes' => 'Perbaiki',
    ])->assertUnprocessable()->assertJsonValidationErrors('action');
    expect($waiver->fresh()->status)->toBe('verified');
    expect($registration->fresh()->status)->toBe('submitted');
    expect($registration->fresh()->academic_snapshot)->toBe($snapshot);
    expect($registration->logs()->count())->toBe(2);
});
```
Sesudah verifikasi berhasil, lock_version sudah berubah. Request berikut memakai versi terbaru dengan `fresh()`, sehingga test penahanan Minta Revisi menguji aturan action, bukan sekadar gagal karena versi lama. Ada dua log: submitted dan document_verified.

## 5. Service dan controller

### `app/Services/SidangTaRegistrationReview.php`

Ganti seluruh isi **file pada path tersebut** dengan kode lengkap berikut.

```php
<?php

namespace App\Services;

use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDocument;
use App\Models\User;
use App\Support\SidangTaReviewEligibility as Eligibility;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SidangTaRegistrationReview
{
    private function locked(SidangTaRegistration $registration, int $version): SidangTaRegistration
    {
        $locked = SidangTaRegistration::whereKey($registration->id)->lockForUpdate()->firstOrFail();
        abort_unless($locked->lock_version === $version, 409, 'Pengajuan berubah. Muat ulang sebelum meninjau.');
        abort_unless(in_array($locked->requirements_version, [1, 2], true), 409,
            'Versi persyaratan pengajuan tidak dikenali.');
        $allowed = $locked->requirements_version === 2 ? ['submitted'] : ['submitted', 'needs_review'];
        abort_unless(in_array($locked->status, $allowed, true), 422,
            'Peninjauan terkunci. Pengajuan sedang direvisi atau sudah diputuskan.');
        return $locked;
    }

    public function document(User $user, SidangTaRegistration $registration, SidangTaRegistrationDocument $document,
                             int $version, string $status, ?string $notes): SidangTaRegistration
    {
        abort_unless($user->can('sidang_ta_registration_review'), 403);
        abort_unless(in_array($status, ['verified', 'rejected'], true), 422);
        $notes = trim($notes ?? '');
        $notes = $notes === '' ? null : $notes;
        if ($status === 'rejected' && $notes === null) {
            throw ValidationException::withMessages(['notes' => 'Isi catatan perbaikan dokumen.']);
        }
        return DB::transaction(function () use ($user, $registration, $document, $version, $status, $notes) {
            $locked = $this->locked($registration, $version);
            $doc = $locked->documents()->whereKey($document->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($doc->document_type, Eligibility::requiredTypes($locked), true), 422,
                'Dokumen ini tidak berlaku untuk cabang biodata pengajuan.');
            if ($status === 'verified' && !Storage::disk('local')->exists($doc->file_path)) {
                throw ValidationException::withMessages(['document' => 'Berkas tidak ditemukan. Minta mahasiswa mengunggah ulang.']);
            }
            $oldRegistrationStatus = $locked->status;
            $isMeet = $locked->requirements_version === 2;
            if ($isMeet && $status === 'verified') $notes = null;
            $nextStatus = !$isMeet && $status === 'rejected' ? 'needs_review' : $locked->status;
            if ($doc->status === $status && $doc->notes === $notes && $locked->status === $nextStatus) return $locked;
            $labels = ['pending' => 'Menunggu verifikasi', 'verified' => 'Terverifikasi', 'rejected' => 'Perlu perbaikan'];
            $oldDocumentStatus = $labels[$doc->status] ?? $doc->status;
            $doc->status = $status;
            $doc->notes = $notes;
            $doc->save();
            $locked->status = $nextStatus;
            $locked->reviewed_by = $user->id;
            $locked->reviewed_at = now();
            $locked->lock_version++;
            $locked->save();
            $logNotes = SidangTaRegistration::DOCUMENT_TYPES[$doc->document_type].': '.$oldDocumentStatus.' → '.$labels[$status];
            if ($notes !== null) $logNotes .= '. Catatan: '.$notes;
            $locked->logAction($status === 'verified' ? 'document_verified' : 'document_rejected',
                $oldRegistrationStatus, $nextStatus, $logNotes, $user->id);
            return $locked;
        }, 3);
    }

    public function decide(User $user, SidangTaRegistration $registration, int $version,
                           string $action, ?string $notes): SidangTaRegistration
    {
        abort_unless($user->can('sidang_ta_registration_review'), 403);
        abort_unless(in_array($action, ['approved', 'needs_review', 'rejected'], true), 422);
        $notes = trim($notes ?? '');
        $notes = $notes === '' ? null : $notes;
        if (in_array($action, ['needs_review', 'rejected'], true) && $notes === null) {
            throw ValidationException::withMessages(['review_notes' => 'Isi catatan revisi atau penolakan.']);
        }
        return DB::transaction(function () use ($user, $registration, $version, $action, $notes) {
            $locked = $this->locked($registration, $version);
            if ($locked->requirements_version === 2 && $action === 'needs_review') {
                throw ValidationException::withMessages(['action' =>
                    'Permintaan revisi mahasiswa belum dibuka. Catatan dokumen tetap tersimpan.']);
            }
            if ($action === 'approved') {
                $locked->setRelation('documents', $locked->documents()->lockForUpdate()->get());
                $blockers = Eligibility::blockers($locked);
                if ($blockers !== []) {
                    throw ValidationException::withMessages(['documents' => 'Persetujuan belum dapat dilakukan: '.implode('; ', $blockers)]);
                }
            }
            $old = $locked->status;
            $locked->status = $action;
            $locked->review_notes = $notes;
            $locked->reviewed_by = $user->id;
            $locked->reviewed_at = now();
            $locked->lock_version++;
            $locked->save();
            $locked->logAction($action, $old, $action, $notes, $user->id);
            return $locked;
        }, 3);
    }
}
```

Pengosongan catatan hanya berlaku versi2. Versi1 tetap dapat memberi catatan pada verified seperti kontrak test lama. Semua perubahan berada di transaksi; request stale/status terkunci/permission gagal tidak menulis status atau log.

API transaksi/locking diperiksa melalui Context7: [transaksi Laravel13](https://laravel.com/docs/13.x/database#database-transactions) dan [pessimistic locking](https://laravel.com/docs/13.x/queries#pessimistic-locking).

### `app/Http/Controllers/SidangTaRegistrationController.php`

Ganti **method `adminShow()` saja** dengan berikut. Method `reviewDocument()` tetap menggunakan redirect `#review-document-ID`, dan method lain tidak perlu diubah.

```php
    public function adminShow(SidangTaRegistration $sidang_ta_registration)
    {
        $registration = $sidang_ta_registration->load(['period', 'documents', 'logs']);
        $reviewable = match ($registration->requirements_version) {
            1 => in_array($registration->status, ['submitted', 'needs_review'], true),
            2 => $registration->status === 'submitted',
            default => false,
        };
        $requiredTypes = \App\Support\SidangTaReviewEligibility::requiredTypes($registration);
        $approvalBlockers = \App\Support\SidangTaReviewEligibility::blockers($registration);
        $documentGroups = \App\Support\SidangTaReviewEligibility::groups($registration);
        return view('sidang_ta_registrations.admin.show', compact(
            'registration', 'reviewable', 'requiredTypes', 'approvalBlockers', 'documentGroups'
        ));
    }
```

## 6. Kode view lengkap

### `resources/views/sidang_ta_registrations/admin/show.blade.php`

Ganti seluruh isi **file pada path tersebut** dengan kode lengkap berikut.

```blade
<x-app-layout>
    <x-slot name="header">Peninjauan Pendaftaran Sidang TA</x-slot>
    @php
        $statusLabels = \App\Services\SidangTaReviewQuery::STATUSES;
        $documentLabels = [
            'pending' => 'Menunggu verifikasi',
            'verified' => 'Terverifikasi',
            'rejected' => 'Perlu perbaikan'
            ];
        $actionLabels = [
            'submitted' => 'Pendaftaran dikirim',
            'resubmitted' => 'Revisi dikirim',
            'needs_review' => 'Diminta revisi',
            'approved' => 'Pengajuan disetujui',
            'rejected' => 'Pengajuan ditolak',
            'document_verified' => 'Dokumen diverifikasi',
            'document_rejected' => 'Dokumen diminta perbaikan',
            ];
    @endphp
    <div class="mx-auto max-w-6xl space-y-5">
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-semibold text-[#DA2D3B]">
                    {{ $registration->period?->name ?? 'Periode tidak tersedia' }}
                </p>
                <p data-ta-review-window class="mt-1 text-xs text-slate-500">
                    Dibuka {{ $registration->period?->open_at?->format('d/m/Y H:i') ?? '-' }} ·
                    Tenggat {{ $registration->period?->close_at?->format('d/m/Y H:i') ?? '-' }}
                </p>
                <h2 class="mt-1 break-words text-xl font-bold text-slate-900">
                    {{ $registration->student_name }}
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    {{ $registration->student_number }} · {{ $registration->study_program ?: 'Program studi belum tersedia' }}
                </p>
            </div>
            <a href="{{ route('admin.sidang-ta-registrations.index') }}" class="btn btn-secondary">
                Kembali ke Review Sidang TA
            </a>
        </header>
        @if($errors->any())
            <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
                {{ $errors->first() }}
            </div>
        @endif
        @if(session('success'))
            <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
                {{ session('success') }}
            </div>
        @endif
        <section class="campus-card space-y-4 p-5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="font-bold text-slate-800">
                    Data pendaftaran
                </h3>
                <x-status-badge :status="$registration->status"
                                :label="$statusLabels[$registration->status] ?? $registration->status"/>
            </div>
            <dl class="grid gap-4 text-sm sm:grid-cols-2">
                @foreach(['HP mahasiswa' => $registration->student_phone, 'HP orang tua/wali' => $registration->parent_phone, 'Pembayaran wisuda' => $registration->graduation_payment_date?->format('d/m/Y'), 'Biodata ijazah' => $registration->diploma_biodata_accurate ? 'Sudah sesuai' : 'Belum sesuai, bukti PDM dilampirkan'] as $label => $value)
                    <div>
                        <dt class="text-xs text-slate-500">{{ $label }}</dt>
                        <dd class="mt-1 break-words font-medium text-slate-800">{{ $value ?? 'Belum tersedia' }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
        @if($registration->requirements_version === 2 && $registration->status === 'needs_review')
            <p data-meet-review-locked role="status" class="rounded-xl bg-amber-50 p-4 text-sm text-amber-900">
                Pengajuan sedang menunggu revisi mahasiswa. Berkas dapat dilihat; perubahan peninjauan dikunci.
            </p>
        @endif
        @include('sidang_ta_registrations.partials.detail-academic')
        @if($registration->requirements_version === 2)
            @include('sidang_ta_registrations.admin.partials.meet-document-groups')
        @else
        <section class="campus-card space-y-4 p-5">
            <h3 class="font-bold text-slate-800">Dokumen pengajuan</h3>
            <div class="divide-y divide-slate-100">
                @forelse($registration->documents as $document)
                    @php($available = \Illuminate\Support\Facades\Storage::disk('local')->exists($document->file_path))
                    <article id="review-document-{{ $document->id }}" data-ta-detail-document="{{ $document->id }}"
                             class="scroll-mt-24 flex flex-wrap items-center justify-between gap-3 py-4">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-800">
                                {{ \App\Models\SidangTaRegistration::DOCUMENT_TYPES[$document->document_type] ?? $document->document_type }}
                            </p>
                            <p class="mt-1 break-words text-xs text-slate-500">
                                {{ $document->file_name }} · {{ number_format(($document->file_size ?? 0) / 1024) }} KB
                            </p>
                            <p class="mt-1 text-xs text-slate-600">
                                {{ $documentLabels[$document->status] ?? $document->status }}
                            </p>
                            @if($document->notes)
                                <p class="mt-2 whitespace-pre-line break-words text-xs text-rose-700">
                                    {{ $document->notes }}
                                </p>
                            @endif
                        </div>
                        @if($available)
                            <a data-document-preview
                               href="{{ route('sidang-ta-registration-documents.preview', $document) }}"
                               data-preview-url="{{ route('sidang-ta-registration-documents.preview', $document) }}"
                               data-download-url="{{ route('sidang-ta-registration-documents.download', $document) }}"
                               data-file-name="{{ $document->file_name }}" data-file-type="{{ $document->file_type }}"
                               x-on:click.prevent="$dispatch('sidang-ta-preview', $el.dataset)" target="_blank"
                               rel="noopener" aria-haspopup="dialog" class="btn btn-secondary">
                                Lihat berkas
                            </a>
                        @else
                            <p class="text-xs font-medium text-orange-700">
                                Berkas tidak ditemukan.
                            </p>
                        @endif
                        @include('sidang_ta_registrations.admin.partials.document-review-form')
                    </article>
                @empty
                    <p class="py-4 text-sm text-slate-500">
                        Belum ada dokumen tersimpan.
                    </p>
                @endforelse
            </div>
        </section>
        @endif
        @if($reviewable)
            @include('sidang_ta_registrations.admin.partials.registration-decision')
        @endif
        @if($registration->review_notes)
            <section class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                <h3 class="text-sm font-bold text-amber-900">
                    Catatan petugas
                </h3>
                <p class="mt-1 whitespace-pre-line break-words text-sm text-amber-800">
                    {{ $registration->review_notes }}
                </p>
            </section>
        @endif
        <details data-staff-registration-history class="campus-card group p-5">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 font-bold text-slate-800 [&::-webkit-details-marker]:hidden">
                <span>Riwayat status</span>
                <svg aria-hidden="true" class="h-5 w-5 shrink-0 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                </svg>
            </summary>
            <ol class="mt-4 space-y-3">
                @forelse($registration->logs as $log)
                    <li class="border-l-2 border-rose-200 pl-3">
                        <p class="text-sm font-semibold text-slate-800">
                            {{ $actionLabels[$log->action] ?? ucfirst(str_replace('_', ' ', $log->action)) }}
                        </p>
                        <p class="mt-1 text-xs text-slate-500">
                            {{ $log->performed_at?->format('d/m/Y H:i') }}
                        </p>
                        @if(filled($log->notes))
                            <p class="mt-1 whitespace-pre-line break-words text-sm text-slate-600">
                                {{ $log->notes }}
                            </p>
                        @endif
                    </li>
                @empty
                    <li class="text-sm text-slate-500">
                        Belum ada riwayat status.
                    </li>
                @endforelse
            </ol>
        </details>
    </div>
    @include('sidang_ta_registrations.partials.document-preview-modal')
</x-app-layout>
```

### `resources/views/sidang_ta_registrations/admin/partials/meet-document-groups.blade.php`

Ganti seluruh isi **file pada path tersebut** dengan kode lengkap berikut.

```blade
<section aria-labelledby="meet-documents-title" class="space-y-4">
    <h2 id="meet-documents-title" class="text-lg font-bold text-slate-800">Dokumen pengajuan</h2>
@foreach($documentGroups as $title => $types)
    @php
        $stage = $loop->iteration + 1;
    @endphp
    <section data-meet-document-group="{{ $stage }}" class="campus-card space-y-4 p-5">
        <header>
            <p class="text-xs font-semibold text-slate-500">Tahap {{ $stage }}</p>
            <h3 class="mt-1 font-bold text-slate-800">{{ $title }}</h3>
            <p class="mt-1 text-xs text-slate-500">{{ count($types) }} berkas pengajuan</p>
        </header>
        <div data-meet-document-grid class="grid items-start gap-4 lg:grid-cols-2">
            @foreach($types as $type)
                @php
                    $document = $registration->documents->firstWhere('document_type', $type);
                    $label = \App\Models\SidangTaRegistration::DOCUMENT_TYPES[$type] ?? $type;
                    $available = $document && \Illuminate\Support\Facades\Storage::disk('local')->exists($document->file_path);
                    $state = !$document ? 'missing' : (!$available ? 'unavailable' : $document->status);
                    $tone = match ($state) {
                        'verified' => 'border-emerald-200 bg-emerald-50 text-emerald-900',
                        'rejected' => 'border-amber-200 bg-amber-50 text-amber-900',
                        'missing', 'unavailable' => 'border-orange-200 bg-orange-50 text-orange-900',
                        default => 'border-slate-200 bg-slate-50 text-slate-800',
                    };
                    $stateLabel = match ($state) {
                        'verified' => 'Terverifikasi', 'rejected' => 'Perlu perbaikan',
                        'missing' => 'Dokumen belum tersedia', 'unavailable' => 'Berkas tidak ditemukan',
                        default => 'Menunggu verifikasi',
                    };
                @endphp
                @if($document)
                    <article id="review-document-{{ $document->id }}" data-ta-detail-document="{{ $document->id }}"
                             data-document-review-state="{{ $state }}" class="scroll-mt-24 space-y-3 rounded-xl border p-4 {{ $tone }}">
                        <div class="min-w-0">
                            <h4 class="text-sm font-bold leading-relaxed">{{ $label }}</h4>
                            <p class="mt-1 break-words text-xs opacity-80">{{ $document->file_name }} · {{ number_format(($document->file_size ?? 0) / 1024) }} KB</p>
                            <span data-document-state-label class="mt-2 inline-flex rounded-full border border-current bg-white/60 px-2 py-1 text-xs font-semibold">{{ $stateLabel }}</span>
                            @if($document->notes)
                                <p data-document-notes class="mt-2 whitespace-pre-line break-words text-xs">{{ $document->notes }}</p>
                            @endif
                        </div>
                        @if($available)
                            <a data-document-preview class="btn btn-secondary"
                               href="{{ route('sidang-ta-registration-documents.preview', $document) }}"
                               data-preview-url="{{ route('sidang-ta-registration-documents.preview', $document) }}"
                               data-download-url="{{ route('sidang-ta-registration-documents.download', $document) }}"
                               data-file-name="{{ $document->file_name }}" data-file-type="{{ $document->file_type }}"
                               x-on:click.prevent="$dispatch('sidang-ta-preview', $el.dataset)" target="_blank" rel="noopener" aria-haspopup="dialog">Lihat berkas</a>
                        @else
                            <p role="alert" class="text-xs font-semibold">Berkas tidak ditemukan. Dokumen belum dapat diverifikasi.</p>
                        @endif
                        @include('sidang_ta_registrations.admin.partials.document-review-form')
                    </article>
                @else
                    <article data-ta-missing-document="{{ $type }}" class="space-y-2 rounded-xl border p-4 {{ $tone }}">
                        <h4 class="text-sm font-bold">{{ $label }}</h4>
                        <p role="alert" class="text-xs">Metadata dokumen belum tersedia. Persetujuan pengajuan ditahan.</p>
                    </article>
                @endif
            @endforeach
        </div>
    </section>
@endforeach
</section>
```

### `resources/views/sidang_ta_registrations/admin/partials/document-review-form.blade.php`

Ganti seluruh isi **file pada path tersebut** dengan kode lengkap berikut.

```blade
@if($reviewable && in_array($document->document_type, $requiredTypes, true))
    @php
        $oldForm = old('_review_form') === ('document:'.$document->id);
        $rawNotes = $oldForm ? old('notes') : $document->notes;
        $notesValue = is_string($rawNotes) ? $rawNotes : '';
        $rawVersion = $oldForm ? old('registration_version') : $registration->lock_version;
        $versionValue = is_scalar($rawVersion) ? (string) $rawVersion : '';
    @endphp
    <form data-doc-review-form="{{ $document->id }}" method="POST" novalidate
          action="{{ route('admin.sidang-ta-registrations.documents.review', ['sidang_ta_registration' => $registration, 'document' => $document]) }}"
          class="w-full space-y-3 border-t border-slate-100 pt-3">
        @csrf
        <input type="hidden" name="_review_form" value="document:{{ $document->id }}">
        <input type="hidden" name="registration_version" value="{{ $versionValue }}">
        <label class="block text-xs font-semibold text-slate-600" for="document-notes-{{ $document->id }}">
            Catatan dokumen (wajib jika meminta perbaikan)
        </label>
        <textarea id="document-notes-{{ $document->id }}" name="notes" maxlength="2000" class="campus-input min-h-20">{{ $notesValue }}</textarea>
        @if($oldForm && $errors->has('notes'))
            <p role="alert" class="text-xs text-rose-700">
                {{ $errors->first('notes') }}
            </p>
        @endif
        <div class="flex flex-wrap justify-end gap-2">
            <button data-doc-reject type="submit" name="status" value="rejected" class="btn btn-secondary">
                Minta perbaikan
            </button>
            <button data-doc-verify type="submit" name="status" value="verified"
                    @disabled(!$available || ($registration->requirements_version === 2 && $document->status === 'verified'))
                    class="btn btn-success disabled:cursor-not-allowed disabled:opacity-50">
                Verifikasi
            </button>
        </div>
    </form>
@endif
```

### `resources/views/sidang_ta_registrations/admin/partials/registration-decision.blade.php`

Ganti seluruh isi **file pada path tersebut** dengan kode lengkap berikut.

```blade
@php
    $oldForm = old('_review_form') === 'decision';
    $rawVersion = $oldForm ? old('registration_version') : $registration->lock_version;
    $versionValue = is_scalar($rawVersion) ? (string) $rawVersion : '';
    $rawNotes = $oldForm ? old('review_notes') : $registration->review_notes;
    $notesValue = is_string($rawNotes) ? $rawNotes : '';
@endphp
<form data-ta-decision method="POST" novalidate
      action="{{ route('admin.sidang-ta-registrations.review', $registration) }}" class="campus-card space-y-4 p-5">
    @csrf
    <input type="hidden" name="_review_form" value="decision">
    <input type="hidden" name="registration_version" value="{{ $versionValue }}">
    <h3 class="font-bold text-slate-800">
        Keputusan petugas
    </h3>
    @if($registration->requirements_version === 2)
        <p data-meet-revision-held role="status" class="rounded-xl bg-amber-50 p-3 text-xs text-amber-900">
            Permintaan revisi mahasiswa belum dibuka. Anda dapat memeriksa dokumen, menyetujui, atau menolak pengajuan.
        </p>
    @endif
    @if($approvalBlockers !== [])
        <div data-ta-approval-blockers
             class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900">
            <p class="font-semibold">
                Setujui tersedia setelah seluruh dokumen wajib terverifikasi dan berkasnya tersedia.
            </p>
            <ul class="mt-2 list-inside list-disc space-y-1">
                @foreach($approvalBlockers as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    @if($errors->has('registration_version'))
        <a data-ta-reload href="{{ route('admin.sidang-ta-registrations.show', $registration) }}" class="btn btn-secondary">
            Muat ulang pengajuan
        </a>
    @endif
    <label class="campus-label">Catatan untuk mahasiswa (wajib untuk revisi atau penolakan)
        <textarea name="review_notes" maxlength="2000" class="campus-input min-h-24">{{ $notesValue }}</textarea>
    </label>
    @if($oldForm && $errors->has('review_notes'))
        <p class="text-xs text-rose-700">
            {{ $errors->first('review_notes') }}
        </p>
    @endif
    <div class="flex flex-wrap justify-end gap-2">
        <button data-ta-request-revision type="submit"
                class="btn btn-secondary disabled:cursor-not-allowed disabled:opacity-50"
                name="action" value="needs_review" @disabled($registration->requirements_version === 2)>Minta Revisi</button>
        <button type="submit" class="btn btn-secondary" name="action" value="rejected">Tolak</button>
        <button data-ta-approve type="submit" class="btn btn-primary disabled:cursor-not-allowed disabled:opacity-50"
                name="action" value="approved" @disabled($approvalBlockers !== [])>Setujui
        </button>
    </div>
</form>
```

Catatan penting saat menyalin view:

- Teks pada textarea tetap berada langsung di antara `<textarea ...>{{ $notesValue }}</textarea>` agar tidak menambah spasi/newline.
- `_review_form` dan versi masing-masing form tetap dipertahankan. Card yang gagal validasi mempertahankan versi lamanya; card lain memakai versi terbaru.
- ID `review-document-ID` dipertahankan agar redirect tidak kembali ke puncak halaman.
- File hilang tidak ditampilkan sebagai hijau hanya karena metadata berstatus verified. Card menjadi oranye dan persetujuan tetap ditahan.
- Kelompok upload menggunakan snapshot saat dikirim. Berubahnya data EPRT/akademik saat ini tidak mengubah jenis dokumen yang sedang ditinjau.
- Button Minta Perbaikan tetap aktif pada kartu rejected untuk memperbaiki catatannya. Tombol Verifikasi aktif kembali pada keadaan itu.

## 7. Jalankan test sesudah seluruh kode diterapkan

### A. Test baru

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaMeetStaffReviewTest.php
```
Target **24 passed** dari21deklarasi test dan3tambahan dataset.

### B. Fokus petugas, integrasi finalisasi, dan kompatibilitas versi1

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaMeetStaffReviewTest.php tests/Feature/SidangTaMeetFinalizationTest.php tests/Feature/SidangTaDocumentReviewTest.php tests/Feature/SidangTaReviewFeedbackTest.php
```
Target **76 passed**: 24+27+15+10.

### C. Finalisasi, revisi lama, daftar, dan ekspor

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaFinalizationTest.php tests/Feature/SidangTaRevisionDraftTest.php tests/Feature/SidangTaReviewListTest.php tests/Feature/SidangTaReviewExportTest.php
```
Target47passed:13+10+14+10. Cakupan B+C berjumlah123test.

### D. Seluruh Sidang TA dan JavaScript

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTa*Test.php tests/Unit/SidangTaAdmissionPolicyTest.php tests/Unit/SidangTaMeetRequirementsTest.php
```
Bila baseline56b297kasus, target cakupan yang sama sekarang **321 passed**. Jumlah kasus lama tidak dikurangi; satu test existing diganti perilakunya karena verifikasi versi2 sudah dibuka.

```bash
node tests/Js/sidang-ta-entry.test.mjs
node tests/Js/sidang-ta-wizard.test.mjs
node tests/Js/sidang-ta-document-preview.test.mjs
php artisan view:clear
```

Jalankan server/Vite seperti biasa. Jika memakai asset hasil build, jalankan `npm run build` supaya class warna/status dan grid pada partial baru masuk hasil build. Tidak ada perubahan JavaScript, `.env`, database schema, atau pengaturan PHPUnit yang diperlukan.

## 8. Manual petugas versi2

Gunakan pengajuan dummy002 **yang sudah submitted melalui flow56b**, dengan delapan dokumen dan mode waiver. Jika belum dikirim, selesaikan manual56b dahulu; draft memang belum muncul pada daftar review petugas.

1. Login akun petugas yang biasa digunakan. Buka Review Sidang TA → pengajuan dummy002.
2. Detail menampilkan nama periode, waktu buka/tenggat, snapshot akademik, dan kelompok tahap2/3/4. Pada desktop setiap kelompok menggunakan dua kolom; di layar kecil satu kolom. Ada2+4+2kartu upload. Riwayat status tertutup secara default.
3. Pilih satu dokumen: isi catatan, tekan Minta Perbaikan. Redirect kembali ke kartu yang sama. Kartu menjadi kuning, status pengajuan masih submitted, dan catatan tersimpan tanpa spasi tambahan.
4. Tekan Verifikasi pada dokumen yang sama. Kartu menjadi hijau; catatan kosong; Verifikasi disabled dan Minta Perbaikan tetap tersedia.
5. Tekan Minta Perbaikan lagi dengan alasan baru. Kartu kembali kuning dan Verifikasi aktif. Coba perbaiki teks catatan lalu tekan Minta Perbaikan sekali lagi; teks baru tersimpan pada status yang sama.
6. Verifikasi kembali. Lihat daftar Review Sidang TA: ikon dokumen tersebut berubah hijau. Kembali ke detail: status kartu dan ikon tetap sesuai setelah refresh.
7. Buka detail pada dua tab. Verifikasi satu dokumen dari tabA; kirim aksi dari tabB yang masih mempunyai versi lama. Perubahan lama ditolak dan tersedia Muat ulang pengajuan. Data hasil tabA tetap tersimpan.
8. Preview setiap berkas tetap membuka modal preview dan unduhan tetap tersedia di dalamnya. Preview tidak menulis status verifikasi.
9. Sebelum semua dokumen terverifikasi, Setujui disabled dengan daftar alasannya. Setelah semuanya terverifikasi, Setujui aktif. Minta Revisi masih disabled dengan pemberitahuan penahanan sementara.

**Untuk menjaga dummy002 sebagai bahan checkpoint revisi berikutnya:** biarkan berstatus submitted sesudah uji dokumen. Setujui/Tolak sudah diperiksa oleh Feature test; jika ingin menguji keputusan final di browser, pakai pengajuan dummy lain yang memang disiapkan terpisah. Jangan memaksa pengajuan approved/rejected kembali ke submitted melalui Tinker.

Cek legacy: buka satu pengajuan versi1 tanpa mengubah keputusan data nyata. Layout/aksi lama masih tersedia sesuai status; test regresi memastikan rejected dan needs_review versi1 tetap memakai perilaku sebelumnya.

## 9. Pemindahan setelah lulus

Salin delapan file dari folder asli ke `dadu-test` dengan path sama:

- `app/Services/SidangTaRegistrationReview.php`
- `app/Http/Controllers/SidangTaRegistrationController.php`
- `resources/views/sidang_ta_registrations/admin/show.blade.php`
- `resources/views/sidang_ta_registrations/admin/partials/meet-document-groups.blade.php`
- `resources/views/sidang_ta_registrations/admin/partials/document-review-form.blade.php`
- `resources/views/sidang_ta_registrations/admin/partials/registration-decision.blade.php`
- `tests/Feature/SidangTaMeetStaffReviewTest.php`
- `tests/Feature/SidangTaMeetFinalizationTest.php`

Tidak ada file yang perlu dihapus. Penambahan hanya satu partial dan satu file test baru. Salinan service lengkap juga menyelesaikan perbedaan guard yang ditemukan pada rescan awal.

Berikutnya: membuka Minta Revisi versi2 bersama pembatasan upload mahasiswa hanya pada berkas yang diminta diperbaiki, mempertahankan verified berkas lain, dan finalisasi revisi. Setelah itu deadline/penolakan otomatis, aktivasi versi2 untuk pendaftaran biasa, serta audit penutupan. **Belum waktunya commit penutupan seluruh revisi meet.**

## 10. Pemeriksaan sebelum/sesudah penulisan

Usulan ditulis/diperiksa di `/tmp`. Empat file PHP lengkap (service, controller, test baru, test finalisasi) lolos lint; empat Blade dikompilasi dalam memori. Render actual Blade dengan helper/storage/context stub memeriksa100assertion pada10kombinasi versi/status/cabang/file hilang, termasuk layout, warna, old controls, textarea, lock controls dan riwayat.

Pemeriksaan independen tidak menemukan blocker pada fixture, kontrak controller, mutasi, atau template. Ini pemeriksaan statis/probe tanpa bootstrap aplikasi atau DB. Feature MySQL, build, dan browser tetap harus dijalankan pengguna.

Hash app/resources/routes/tests/database/migrations/config di kedua folder diperiksa sebelum dan sesudah penulisan docs. Source tidak diubah asisten; satu perbedaan antarfolder sudah dijelaskan pada bagian0. Catatan pendamping07 diperbarui setelah rescan.


## 11. Rescan setelah laporan dua test textarea gagal

**Status terbaru: selesai menurut konfirmasi pengguna.** Pengguna sudah memperbaiki textarea dan menyatakan error tidak terjadi lagi. Delapan file sudah identik. Langkah diagnosis di bawah disimpan sebagai riwayat; tidak perlu dijalankan ulang. Lanjutkan [58](58-preview-contoh-dan-kontrak-revisi-terarah.md).

Pengguna melaporkan22dari24test baru lulus, seluruh manual berhasil, dan file sudah dipindahkan. Delapan file57 sekarang identik antara kedua folder, termasuk service review yang sebelumnya berbeda.

Dua lokasi gagal adalah assertion `textContent` pada textarea. Markup yang tersalin tetap rapat `>{{ $notesValue }}</textarea>`; belum ada bukti bahwa whitespace tambahan merupakan penyebab. Lint empat file PHP saat ini lulus. Probe render menggunakan file view aktual, dengan layout/komponen yang disederhanakan dan helper/storage stub, menghasilkan107assertion lulus. Probe itu tidak menggantikan response HTTP lengkap dari Feature test.

Diagnosis masih memerlukan pesan kegagalan lengkap, khususnya nilai Expected/Actual atau error di atas baris290. Jangan mengubah assertion menjadi trim/contains atau mengubah service penyimpanan sebelum nilai yang berbeda diketahui.

Jalankan hanya test pertama berikut dan kirim satu blok FAILED lengkap:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaMeetStaffReviewTest.php --filter=meet_staff_html_review --stop-on-failure
```

Ini command diagnosis, bukan perbaikan atau test baru. Kode aplikasi dan assertion tetap dipertahankan sampai perbedaan aktual terkonfirmasi. Tahap revisi berikutnya menunggu dua kegagalan ini diselesaikan.
