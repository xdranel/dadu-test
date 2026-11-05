# 60 — Deadline revisi dan penolakan otomatis Sidang TA versi 2

> Panduan implementasi pengguna. Asisten hanya mengubah dokumen; terapkan kode berikut di `/home/xdranel/Code/Project/php-laravel/dadu/`, uji, kemudian salin file ke dadu-test. Untuk pekerja agentik yang mendapat izin implementasi, gunakan skill executing-plans dan kerjakan setiap langkah beserta verifikasinya.

**Goal:** tutup perubahan revisi versi 2 setelah batas akhir periode dan tolak otomatis pengajuan yang masih menunggu revisi.

**Architecture:** satu policy deadline dipakai oleh jalur tulis dan konteks tampilan. Service expiry mengunci serta memeriksa ulang pengajuan sebelum mengubah status; command dijadwalkan setiap menit. Draft/file tidak dihapus oleh expiry.

**Tech stack:** Laravel 13, PHP, MySQL Podman, Pest, Blade/Alpine yang sudah tersedia.

**Spec:** [rancangan hasil meet](45-rancangan-revisi-hasil-meet-sidang-ta.md), lanjutan [59](59-integrasi-revisi-terarah-sidang-ta-versi-dua.md).

## 1. Hasil rescan dan aturan checkpoint

Pengguna melaporkan unit test checkpoint 59 lulus dan salin file selesai. Rescan saat menyiapkan60 memastikan **18 file checkpoint 59 identik**, bahkan seluruh 487 file pada direktori source yang dibandingkan identik antarfolder. PHP lint 66 file terkait source/prototype awal lulus; tiga suite Node dijalankan satu per satu:1 + 8 + 4 = 13 lulus. Ini verifikasi tambahan, bukan klaim menjalankan Feature MySQL.

Aturan implementasi:

- Hanya `requirements_version === 2` yang mengikuti deadline baru. Revisi versi 1 tetap mengikuti perilaku lama untuk kompatibilitas pengajuan historis.
- Tenggat adalah `period.close_at` pengajuan asal. Tepat pada `close_at` masih boleh; setelahnya tidak boleh. Menonaktifkan `is_active` saja tidak mempercepat tenggat revisi.
- Lewat tenggat: tidak boleh mulai/resume pengerjaan, simpan kontak/file, menandai tinjauan baru, atau mengirim revisi. Pengecekan server tetap berlaku sebelum scheduler berjalan.
- Preview draft yang sudah ditinjau dan tampilan wizard masih dapat dibaca sebelum expiry mengubah status. Setelah expiry, draft menjadi stale melalui status/version sumber; daftar menyediakan Hapus draft, sedangkan dokumen pengajuan final tetap dapat dilihat melalui detail.
- Command hanya menolak versi 2 `needs_review` yang lewat tenggat. `submitted`, `approved`, `rejected`, dan semua versi 1 tidak diubah. Petugas juga tidak dapat meminta revisi baru sesudah tenggat; persetujuan/penolakan pengajuan submitted masih mengikuti aturan dokumen yang ada.
- Penolakan otomatis menaikkan `lock_version` sekali dan membuat satu log dengan actor `null`/sistem. Snapshot, dokumen final, draft, dan seluruh file dipertahankan. Catatan permintaan revisi sebelumnya tetap ada dalam riwayat log petugas.
- Memperpanjang periode sebelum expiry diproses memakai tanggal terbaru. Memperpanjang sesudah pengajuan ditolak tidak menghidupkan kembali pengajuan tersebut.
- Request ulang draft consumed tetap mengembalikan hasil pengajuan yang sudah ada, termasuk keputusan petugas berikutnya. Deadline tidak boleh memindahkan pemeriksaan ke depan jalur replay itu.
- Pendaftaran baru biasa masih **versi 1** sampai checkpoint aktivasi berikutnya. Jangan mengubah default atau draft Krisna pada 60. Tidak ada migration, JSON baru, konfigurasi PHPUnit, atau import ulang untuk checkpoint ini.

**Review focus:** batas waktu tepat/terlewat; periode yang baru diperpanjang; request lama sebelum scheduler; expiry berhadapan dengan kirim revisi; berkas pinjaman dan pengganti tetap aman. Seluruhnya dicakup test lengkap di bawah. Test urutan request membuktikan dua hasil persaingan secara deterministik; bukan simulasi proses paralel nyata.

## 2. Buat test terlebih dahulu

- [ ] Jalankan dari folder development asli:

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
php artisan make:test SidangTaRevisionDeadlineTest --pest
```

Ganti **seluruh** isi `tests/Feature/SidangTaRevisionDeadlineTest.php` dengan berikut. Fixture dimiliki file ini; tidak perlu menyalin helper dari file test lain.

```php
<?php

use App\Models\AcademicPeriod;
use App\Models\Course;
use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDraft;
use App\Models\Student;
use App\Models\StudentCourse;
use App\Models\StudentEprt;
use App\Models\User;
use App\Services\SidangTaRevisionExpiry;
use App\Support\SidangTaRevisionDeadline;
use App\Services\StudentAcademicProfileImportService;
use App\Support\SidangTaMeetRequirements as Meet;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\DomCrawler\Crawler;

beforeEach(function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-11-04 12:00:00', config('app.timezone')));
    Storage::fake('local');
    $this->user = User::factory()->create(['username' => '1202299060', 'email' => 'revision-deadline@test.local',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
    $this->student = Student::create(['student_number' => $this->user->username, 'name' => 'Meet Final',
        'email' => $this->user->email, 'academic_year' => 2022, 'study_program' => 'Informatika', 'status' => 'Aktif']);
    foreach (['read', 'create', 'update'] as $action) {
        $permission = Permission::firstOrCreate(['name' => 'sidang_ta_registration_'.$action, 'guard_name' => 'web']);
        $this->user->givePermissionTo($permission);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($this->user);
    $this->period = SidangTaPeriod::create(['name' => 'Meet Final', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay()]);
    AcademicPeriod::query()->update(['is_active' => false]);
    $academic = AcademicPeriod::create(['year' => '2026/2027', 'semester' => 1, 'label' => 'Meet Final KRS',
        'start_date' => '2026-08-01', 'end_date' => '2027-01-31', 'is_active' => true]);
    Course::create(['course_code' => 'MEET-DEADLINE60', 'name' => 'TUGAS AKHIR 2', 'academic_year' => '2026/2027',
        'semester_term' => 'GANJIL', 'study_program' => 'Informatika', 'class' => 'A', 'semester' => 8, 'credits' => 4]);
    StudentCourse::create(['student_number' => $this->student->student_number, 'course_code' => 'MEET-DEADLINE60',
        'course_name' => 'TUGAS AKHIR 2', 'course_class' => 'A', 'academic_year' => '2026/2027', 'semester' => 8,
        'academic_period_id' => $academic->id]);
    $this->meetDraft = function (string $mode = 'automatic', bool $accurate = true, bool $complete = true) {
        $rows = json_decode(file_get_contents(base_path('docs/fixtures/sidang-ta/03-profil-akademik-dummy.json')), true, 512, JSON_THROW_ON_ERROR)['data'];
        $row = $rows[$mode === 'waiver' ? 1 : ($mode === 'retest' ? 2 : 0)];
        $row['nim'] = $this->student->student_number;
        $row['nama'] = $this->student->name;
        if ($mode === 'unknown') $row['eprt'] = [];
        StudentEprt::where('student_number', $this->student->student_number)->delete();
        $result = app(StudentAcademicProfileImportService::class)->import([$row]);
        expect($result['errors'])->toBe([]);
        $draft = SidangTaRegistrationDraft::create(['owner_user_id' => $this->user->id,
            'student_number' => $this->student->student_number, 'sidang_ta_period_id' => $this->period->id,
            'requirements_version' => 2, 'payload' => ['student_phone' => '081234567890', 'parent_phone' => '081234567891',
                'graduation_payment_date' => today()->toDateString(), 'diploma_biodata_accurate' => $accurate]]);
        if ($complete) {
            foreach (Meet::allTypes($accurate, $mode) as $type) {
                $path = 'sidang_ta/drafts/'.$draft->id.'/'.$type.'.pdf';
                Storage::disk('local')->put($path, '%PDF-1.4 meet ui');
                $draft->documents()->create(['document_type' => $type, 'file_path' => $path,
                    'file_name' => $type.'.pdf', 'file_type' => 'pdf', 'file_size' => 16]);
            }
        }
        return $draft->fresh();
    };
    $this->markReviewed = function ($draft) {
        $this->postJson(route('sidang-ta-drafts.review', $draft), ['version' => $draft->fresh()->version])->assertOk();
        return $draft->fresh();
    };
    $this->submitMeet = function ($draft, ?int $version = null) {
        return $this->postJson(route('sidang-ta-registrations.store'), [
            'draft_id' => $draft->id, 'version' => $version ?? $draft->fresh()->version, 'action' => 'submit',
        ]);
    };
    $this->asReviewer = function () {
        $permission = Permission::firstOrCreate(['name' => 'sidang_ta_registration_review', 'guard_name' => 'web']);
        $reviewer = User::factory()->create(['status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
        $reviewer->givePermissionTo($permission);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($reviewer);
        return $reviewer;
    };
    $this->submittedSource = function (string $mode = 'waiver', bool $accurate = true) {
        $this->actingAs($this->user);
        $draft = ($this->markReviewed)(($this->meetDraft)($mode, $accurate));
        $id = ($this->submitMeet)($draft)->assertOk()->json('data.id');
        return SidangTaRegistration::findOrFail($id);
    };
    $this->prepareRevision = function ($source, array $rejected = ['ta_data_capture']) {
        $this->reviewer = ($this->asReviewer)();
        foreach ($source->documents()->get() as $document) {
            $needsFix = in_array($document->document_type, $rejected, true);
            $this->postJson(route('admin.sidang-ta-registrations.documents.review', [
                'sidang_ta_registration' => $source, 'document' => $document,
            ]), ['registration_version' => $source->fresh()->lock_version,
                'status' => $needsFix ? 'rejected' : 'verified', 'notes' => $needsFix ? 'Perbaiki '.$document->document_type : null])->assertOk();
        }
        $this->postJson(route('admin.sidang-ta-registrations.review', $source), [
            'registration_version' => $source->fresh()->lock_version, 'action' => 'needs_review',
            'review_notes' => 'Perbaiki dokumen yang ditandai.',
        ])->assertRedirect();
        $this->actingAs($this->user);
        $id = $this->postJson(route('sidang-ta-drafts.revision', $source))->assertCreated()->json('data.id');
        return SidangTaRegistrationDraft::findOrFail($id);
    };
    $this->replaceDocument = function ($draft, string $type = 'ta_data_capture', int $step = 2, array $extra = []) {
        return $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => $step]),
            array_replace(['version' => $draft->fresh()->version,
                'documents' => [$type => UploadedFile::fake()->create('pengganti.pdf', 100, 'application/pdf')]], $extra));
    };
    $this->submitRevision = function ($source, $draft) {
        return $this->putJson(route('sidang-ta-registrations.update', $source), [
            'draft_id' => $draft->id, 'version' => $draft->fresh()->version, 'action' => 'submit',
        ]);
    };
});

afterEach(function () { $this->travelBack(); });

// Each test owns its fixtures. These tests deliberately use ordinary request ordering,
// not parallel processes, to exercise both outcomes of the expiry/resubmission race.
test('revision_deadline_exact_closing_instant_allows_save_but_one_second_later_blocks_it', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source);
    $this->period->update(['close_at' => now()]);
    expect(SidangTaRevisionDeadline::hasExpired($source))->toBeFalse();
    ($this->replaceDocument)($draft)->assertOk();
    $this->travel(1)->seconds();
    $before = $draft->fresh()->getAttributes();
    $paths = Storage::disk('local')->allFiles();
    expect(SidangTaRevisionDeadline::hasExpired($source))->toBeTrue();
    ($this->replaceDocument)($draft)->assertUnprocessable()->assertJsonValidationErrors('draft');
    expect($draft->fresh()->getAttributes())->toBe($before);
    expect(Storage::disk('local')->allFiles())->toBe($paths);
});

test('revision_deadline_rejects_starting_a_new_revision_after_close_without_creating_a_draft', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source);
    $this->deleteJson(route('sidang-ta-drafts.destroy', $draft), ['version' => $draft->version])->assertNoContent();
    $count = SidangTaRegistrationDraft::count();
    $before = $source->fresh()->getAttributes();
    $paths = Storage::disk('local')->allFiles();
    $this->period->update(['close_at' => now()->subSecond()]);
    $this->postJson(route('sidang-ta-drafts.revision', $source))->assertUnprocessable()->assertJsonValidationErrors('draft');
    expect(SidangTaRegistrationDraft::count())->toBe($count);
    expect($source->fresh()->getAttributes())->toBe($before);
    expect(Storage::disk('local')->allFiles())->toBe($paths);
});

test('revision_deadline_blocks_remaining_partial_replacement_before_expiry_job', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source, ['ta_data_capture', 'online_supervision_capture']);
    ($this->replaceDocument)($draft)->assertOk();
    $saved = $draft->documents()->orderBy('id')->get()->toArray();
    $paths = Storage::disk('local')->allFiles();
    $this->period->update(['close_at' => now()->subSecond()]);
    ($this->replaceDocument)($draft, 'online_supervision_capture')->assertUnprocessable()->assertJsonValidationErrors('draft');
    expect($draft->fresh()->version)->toBe(2);
    expect($draft->documents()->orderBy('id')->get()->toArray())->toBe($saved);
    expect($source->fresh()->status)->toBe('needs_review');
    expect(Storage::disk('local')->allFiles())->toBe($paths);
});

test('revision_deadline_blocks_unchanged_step_one_save_before_expiry_job', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source);
    $before = $draft->fresh()->getAttributes();
    $paths = Storage::disk('local')->allFiles();
    $this->period->update(['close_at' => now()->subSecond()]);
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 1]), [
        'version' => $draft->version, 'sidang_ta_period_id' => $this->period->id,
        'student_phone' => $draft->payload['student_phone'], 'parent_phone' => $draft->payload['parent_phone'],
    ])->assertUnprocessable()->assertJsonValidationErrors('draft');
    expect($draft->fresh()->getAttributes())->toBe($before);
    expect(Storage::disk('local')->allFiles())->toBe($paths);
});

test('revision_deadline_blocks_mark_review_without_changing_review_marker', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source);
    ($this->replaceDocument)($draft)->assertOk();
    $before = $draft->fresh()->getAttributes();
    $this->period->update(['close_at' => now()->subSecond()]);
    $this->postJson(route('sidang-ta-drafts.review', $draft), ['version' => $draft->fresh()->version])
        ->assertUnprocessable()->assertJsonValidationErrors('draft');
    expect($draft->fresh()->getAttributes())->toBe($before);
    expect($source->fresh()->status)->toBe('needs_review');
});

test('revision_deadline_rechecks_finalization_after_a_successful_preview', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source);
    ($this->replaceDocument)($draft)->assertOk();
    $draft = ($this->markReviewed)($draft);
    $this->get(route('sidang-ta-drafts.preview', $draft))->assertOk();
    $registration = $source->fresh()->getAttributes();
    $draftBefore = $draft->getAttributes();
    $documents = $source->documents()->orderBy('id')->get()->toArray();
    $logs = $source->logs()->count();
    $paths = Storage::disk('local')->allFiles();
    $this->period->update(['close_at' => now()->subSecond()]);
    ($this->submitRevision)($source, $draft)->assertUnprocessable()->assertJsonValidationErrors('draft');
    expect($source->fresh()->getAttributes())->toBe($registration);
    expect($draft->fresh()->getAttributes())->toBe($draftBefore);
    expect($source->documents()->orderBy('id')->get()->toArray())->toBe($documents);
    expect($source->logs()->count())->toBe($logs);
    expect(Storage::disk('local')->allFiles())->toBe($paths);
});

test('revision_expiry_rejects_once_and_preserves_snapshot_documents_draft_and_all_files', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source);
    ($this->replaceDocument)($draft)->assertOk();
    $draft = ($this->markReviewed)($draft);
    $version = $source->fresh()->lock_version;
    $snapshot = $source->fresh()->academic_snapshot;
    $draftBefore = $draft->getAttributes();
    $sourceDocuments = $source->documents()->orderBy('id')->get()->toArray();
    $draftDocuments = $draft->documents()->orderBy('id')->get()->toArray();
    expect($draft->documents()->whereNull('source_document_id')->count())->toBe(1);
    expect($draft->documents()->whereNotNull('source_document_id')->count())->toBeGreaterThan(0);
    $logs = $source->logs()->count();
    $paths = Storage::disk('local')->allFiles();
    $this->period->update(['close_at' => now()->subSecond()]);
    expect(app(SidangTaRevisionExpiry::class)->run())->toBe(1);
    $rejected = $source->fresh();
    expect($rejected->status)->toBe('rejected');
    expect($rejected->lock_version)->toBe($version + 1);
    expect($rejected->reviewed_by)->toBeNull();
    expect($rejected->reviewed_at->equalTo(now()))->toBeTrue();
    expect($rejected->review_notes)->toBe(SidangTaRevisionExpiry::NOTE);
    expect(SidangTaRevisionExpiry::NOTE)->toBe('Ditolak otomatis karena revisi tidak dikirim sampai batas akhir periode Sidang TA.');
    expect($rejected->academic_snapshot)->toBe($snapshot);
    expect($draft->fresh()->getAttributes())->toBe($draftBefore);
    expect($source->documents()->orderBy('id')->get()->toArray())->toBe($sourceDocuments);
    expect($draft->documents()->orderBy('id')->get()->toArray())->toBe($draftDocuments);
    expect($source->logs()->count())->toBe($logs + 1);
    $log = $source->logs()->orderByDesc('id')->firstOrFail();
    expect($log->action)->toBe('rejected');
    expect($log->from_status)->toBe('needs_review');
    expect($log->to_status)->toBe('rejected');
    expect($log->performed_by)->toBeNull();
    expect($log->notes)->toBe(SidangTaRevisionExpiry::NOTE);
    $after = $rejected->getAttributes();
    $this->travel(1)->minutes();
    expect(app(SidangTaRevisionExpiry::class)->run())->toBe(0);
    expect(app(SidangTaRevisionExpiry::class)->expire($source->id))->toBeFalse();
    expect($source->fresh()->getAttributes())->toBe($after);
    expect($source->logs()->count())->toBe($logs + 1);
    expect(Storage::disk('local')->allFiles())->toBe($paths);
});

test('revision_expiry_ignores_legacy_version_and_other_statuses', function (int $requirementsVersion, string $status) {
    $source = ($this->submittedSource)();
    $source->update(['requirements_version' => $requirementsVersion, 'status' => $status]);
    $this->period->update(['close_at' => now()->subSecond()]);
    $before = $source->fresh()->getAttributes();
    $logs = $source->logs()->count();
    expect(app(SidangTaRevisionExpiry::class)->run())->toBe(0);
    expect(app(SidangTaRevisionExpiry::class)->expire($source->id))->toBeFalse();
    expect($source->fresh()->getAttributes())->toBe($before);
    expect($source->logs()->count())->toBe($logs);
    if ($requirementsVersion === 1) expect(SidangTaRevisionDeadline::hasExpired($source))->toBeFalse();
})->with([[1, 'needs_review'], [2, 'submitted'], [2, 'approved'], [2, 'rejected']]);

test('revision_expiry_artisan_reports_actual_count_then_zero_on_repeat', function () {
    $source = ($this->submittedSource)();
    ($this->prepareRevision)($source);
    $this->period->update(['close_at' => now()->subSecond()]);
    $this->artisan('sidang-ta:expire-revisions')->expectsOutput('Pengajuan revisi ditolak otomatis: 1.')->assertExitCode(0);
    $this->artisan('sidang-ta:expire-revisions')->expectsOutput('Pengajuan revisi ditolak otomatis: 0.')->assertExitCode(0);
    expect($source->fresh()->status)->toBe('rejected');
});

test('revision_deadline_ignores_inactive_flag_before_close_and_expiry_still_includes_it_after_close', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source);
    $this->period->update(['is_active' => false]);
    expect(SidangTaRevisionDeadline::hasExpired($source))->toBeFalse();
    ($this->replaceDocument)($draft)->assertOk();
    expect(app(SidangTaRevisionExpiry::class)->run())->toBe(0);
    $this->period->update(['close_at' => now()->subSecond()]);
    expect(app(SidangTaRevisionExpiry::class)->run())->toBe(1);
    expect($source->fresh()->status)->toBe('rejected');
});

test('revision_deadline_reads_fresh_period_after_an_extension_before_expiry_runs', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source);
    $this->period->update(['close_at' => now()->subSecond()]);
    $source->load('period'); // Deliberately cache the old, already closed period.
    expect(SidangTaRevisionDeadline::hasExpired($source))->toBeTrue();
    SidangTaPeriod::whereKey($this->period->id)->update(['close_at' => now()->addHour()]);
    expect(SidangTaRevisionDeadline::hasExpired($source))->toBeFalse();
    expect(app(SidangTaRevisionExpiry::class)->expire($source->id))->toBeFalse();
    expect(app(SidangTaRevisionExpiry::class)->run())->toBe(0);
    ($this->replaceDocument)($draft)->assertOk();
    expect($source->fresh()->status)->toBe('needs_review');
});

test('revision_expiry_rechecks_status_when_a_previously_selected_source_has_changed', function () {
    $source = ($this->submittedSource)();
    ($this->prepareRevision)($source);
    $this->period->update(['close_at' => now()->subSecond()]);
    $candidateId = $source->id;
    $source->refresh()->update(['status' => 'submitted']);
    $before = $source->fresh()->getAttributes();
    $logs = $source->logs()->count();
    expect(app(SidangTaRevisionExpiry::class)->expire($candidateId))->toBeFalse();
    expect($source->fresh()->getAttributes())->toBe($before);
    expect($source->logs()->count())->toBe($logs);
});

test('revision_submitted_before_deadline_wins_and_consumed_replay_after_close_returns_later_state', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source);
    ($this->replaceDocument)($draft)->assertOk();
    $draft = ($this->markReviewed)($draft);
    ($this->submitRevision)($source, $draft)->assertOk()->assertJsonPath('data.status', 'submitted');
    $source->update(['status' => 'approved']);
    $sourceBefore = $source->fresh()->getAttributes();
    $version = $source->fresh()->lock_version;
    $logs = $source->logs()->count();
    $paths = Storage::disk('local')->allFiles();
    $this->period->update(['close_at' => now()->subSecond()]);
    expect(app(SidangTaRevisionExpiry::class)->expire($source->id))->toBeFalse();
    ($this->submitRevision)($source, $draft)->assertOk()->assertJsonPath('data.status', 'approved');
    expect($source->fresh()->getAttributes())->toBe($sourceBefore);
    expect($source->fresh()->lock_version)->toBe($version);
    expect($draft->fresh()->status)->toBe('consumed');
    expect($source->logs()->count())->toBe($logs);
    expect(Storage::disk('local')->allFiles())->toBe($paths);
});

test('revision_expiry_wins_then_stale_draft_discard_removes_only_owned_replacement', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source);
    $originalPaths = $source->documents()->pluck('file_path')->all();
    ($this->replaceDocument)($draft)->assertOk();
    $owned = $draft->documents()->whereNull('source_document_id')->firstOrFail()->file_path;
    $draft = ($this->markReviewed)($draft);
    $this->period->update(['close_at' => now()->subSecond()]);
    expect(app(SidangTaRevisionExpiry::class)->expire($source->id))->toBeTrue();
    ($this->submitRevision)($source, $draft)->assertConflict();
    Storage::disk('local')->assertExists($owned);
    $logs = $source->logs()->count();
    $version = $source->fresh()->lock_version;
    $index = new Crawler($this->get(route('sidang-ta-registrations.index'))->assertOk()->getContent());
    $card = $index->filter('[data-testid="draft-card"]')->reduce(fn (Crawler $node) => $node->filter('#discard-draft-'.$draft->id)->count() === 1);
    expect($card->count())->toBe(1);
    expect($card->filter('a[href="'.route('sidang-ta-drafts.edit', $draft).'"]')->count())->toBe(0);
    expect($card->filter('[data-open-discard]')->count())->toBe(1);
    $this->deleteJson(route('sidang-ta-drafts.destroy', $draft), ['version' => $draft->fresh()->version])->assertNoContent();
    Storage::disk('local')->assertMissing($owned);
    foreach ($originalPaths as $path) Storage::disk('local')->assertExists($path);
    expect($source->fresh()->status)->toBe('rejected');
    expect($source->fresh()->lock_version)->toBe($version);
    expect($source->logs()->count())->toBe($logs);
});

test('revision_deadline_staff_cannot_request_revision_after_close', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source);
    $this->deleteJson(route('sidang-ta-drafts.destroy', $draft), ['version' => $draft->version])->assertNoContent();
    $source->refresh()->update(['status' => 'submitted']);
    $this->period->update(['close_at' => now()->subSecond()]);
    $this->actingAs($this->reviewer);
    $before = $source->fresh()->getAttributes();
    $logs = $source->logs()->count();
    $crawler = new Crawler($this->get(route('admin.sidang-ta-registrations.show', $source))->assertOk()->getContent());
    expect($crawler->filter('[data-ta-revision-blockers]')->count())->toBe(1);
    expect($crawler->filter('[data-ta-request-revision]')->getNode(0)->hasAttribute('disabled'))->toBeTrue();
    $this->postJson(route('admin.sidang-ta-registrations.review', $source), [
        'registration_version' => $source->fresh()->lock_version, 'action' => 'needs_review',
        'review_notes' => 'Perbaiki dokumen yang ditandai.',
    ])->assertUnprocessable()->assertJsonValidationErrors('action');
    expect($source->fresh()->getAttributes())->toBe($before);
    expect($source->logs()->count())->toBe($logs);
});

test('revision_deadline_expired_wizard_keeps_preview_and_disables_save_and_review_before_job', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source);
    ($this->replaceDocument)($draft)->assertOk();
    $this->period->update(['close_at' => now()->subSecond()]);
    $response = $this->get(route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 4]))->assertOk();
    $response->assertViewHas('meetContext', fn ($context) => $context['revision_expired'] === true && $context['can_submit'] === false);
    $crawler = new Crawler($response->getContent());
    expect($crawler->filter('[data-revision-deadline-expired][role="alert"]')->count())->toBe(1);
    expect($crawler->filter('[data-save-draft]')->getNode(0)->hasAttribute('disabled'))->toBeTrue();
    expect($crawler->filter('[data-review-submit]')->getNode(0)->hasAttribute('disabled'))->toBeTrue();
    $document = $draft->documents()->whereNotNull('source_document_id')->firstOrFail();
    $this->get(route('sidang-ta-draft-documents.preview', $document))->assertOk();
    expect($source->fresh()->status)->toBe('needs_review');
});

test('revision_deadline_expired_review_keeps_documents_readable_and_send_buttons_disabled_before_job', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source);
    ($this->replaceDocument)($draft)->assertOk();
    $draft = ($this->markReviewed)($draft);
    $this->period->update(['close_at' => now()->subSecond()]);
    $response = $this->get(route('sidang-ta-drafts.preview', $draft))->assertOk();
    $response->assertViewHas('meetContext', fn ($context) => $context['revision_expired'] === true && $context['can_submit'] === false);
    $crawler = new Crawler($response->getContent());
    expect($crawler->filter('[data-revision-deadline-expired][role="alert"]')->count())->toBe(1);
    expect($crawler->filter('a[data-document-preview]')->count())->toBe(8);
    foreach (['data-open-registration', 'data-confirm-registration'] as $selector) {
        expect($crawler->filter('['.$selector.']')->getNode(0)->hasAttribute('disabled'))->toBeTrue();
    }
    $document = $draft->documents()->whereNull('source_document_id')->firstOrFail();
    $this->get(route('sidang-ta-draft-documents.preview', $document))->assertOk();
    expect($source->fresh()->status)->toBe('needs_review');
});

test('revision_expiry_also_rejects_overdue_source_when_student_never_keeps_a_revision_draft', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source);
    $this->deleteJson(route('sidang-ta-drafts.destroy', $draft), ['version' => $draft->version])->assertNoContent();
    expect(SidangTaRegistrationDraft::where('source_registration_id', $source->id)->where('status', 'active')->exists())->toBeFalse();
    $documents = $source->documents()->orderBy('id')->get()->toArray();
    $paths = Storage::disk('local')->allFiles();
    $this->period->update(['close_at' => now()->subSecond()]);
    expect(app(SidangTaRevisionExpiry::class)->run())->toBe(1);
    expect($source->fresh()->status)->toBe('rejected');
    expect($source->documents()->orderBy('id')->get()->toArray())->toBe($documents);
    expect(Storage::disk('local')->allFiles())->toBe($paths);
});

test('revision_expiry_does_not_reject_at_exact_deadline_but_rejects_one_second_after', function () {
    $source = ($this->submittedSource)();
    ($this->prepareRevision)($source);
    $this->period->update(['close_at' => now()]);
    $before = $source->fresh()->getAttributes();
    expect(app(SidangTaRevisionExpiry::class)->expire($source->id))->toBeFalse();
    expect(app(SidangTaRevisionExpiry::class)->run())->toBe(0);
    expect($source->fresh()->getAttributes())->toBe($before);
    $this->travel(1)->seconds();
    expect(app(SidangTaRevisionExpiry::class)->run())->toBe(1);
    expect($source->fresh()->status)->toBe('rejected');
});

test('revision_deadline_expired_step_two_keeps_requested_document_preview_without_upload_input', function () {
    $source = ($this->submittedSource)();
    $draft = ($this->prepareRevision)($source);
    $this->period->update(['close_at' => now()->subSecond()]);
    $response = $this->get(route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 2]))->assertOk();
    $crawler = new Crawler($response->getContent());
    expect($crawler->filter('[data-revision-deadline-expired][role="alert"]')->count())->toBe(1);
    $requested = $crawler->filter('[data-upload-type="ta_data_capture"]');
    expect($requested->count())->toBe(1);
    expect($requested->filter('input[type="file"]')->count())->toBe(0);
    $document = $draft->documents()->where('document_type', 'ta_data_capture')->firstOrFail();
    expect($requested->text(null, true))->toContain($document->file_name);
    $this->get(route('sidang-ta-draft-documents.preview', $document))->assertOk();
    expect($crawler->filter('[data-save-draft]')->getNode(0)->hasAttribute('disabled'))->toBeTrue();
    expect($draft->fresh()->version)->toBe(1);
    expect($source->fresh()->status)->toBe('needs_review');
});
```

- [ ] Jalankan test baru sebelum implementasi untuk memastikan perubahan memang dibutuhkan:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaRevisionDeadlineTest.php --stop-on-failure
```

Pada kode59, test belum lulus: policy/expiry/command belum tersedia dan save revisi melewati tenggat belum ditolak. Setelah semua kode60 diterapkan, target file ini **23 kasus lulus** (20 deklarasi dan satu dataset empat baris).

## 3. Tambahkan policy, service, dan command

- [ ] Buat tiga file baru:

```bash
php artisan make:class Support/SidangTaRevisionDeadline
php artisan make:class Services/SidangTaRevisionExpiry
php artisan make:command ExpireSidangTaRevisions
```

### 3.1 `app/Support/SidangTaRevisionDeadline.php`

Ganti keseluruhan isi. Relasi periode selalu dibaca ulang; jangan menggantinya dengan `$registration->period` yang mungkin sudah di-cache.

```php
<?php

namespace App\Support;

use App\Models\SidangTaRegistration;
use Illuminate\Validation\ValidationException;

class SidangTaRevisionDeadline
{
    public const MESSAGE = 'Batas waktu revisi telah berakhir. Revisi tidak dapat disimpan atau dikirim lagi.';

    public static function hasExpired(SidangTaRegistration $registration, bool $lockPeriod = false): bool
    {
        if ($registration->requirements_version !== 2) return false;

        // Selalu baca ulang periode; relasi yang sudah dimuat dapat menyimpan tanggal lama.
        $query = $registration->period();
        $period = ($lockPeriod ? $query->lockForUpdate() : $query)->firstOrFail();
        return now()->greaterThan($period->close_at);
    }

    // Panggil di dalam transaksi sesudah lock Student/draft/pengajuan yang diperlukan.
    public static function ensureOpen(SidangTaRegistration $registration, string $field = 'draft'): void
    {
        if (self::hasExpired($registration, true)) {
            throw ValidationException::withMessages([$field => self::MESSAGE]);
        }
    }
}
```

### 3.2 `app/Services/SidangTaRevisionExpiry.php`

Ganti keseluruhan isi. Daftar kandidat hanya optimasi; keputusan tetap diperiksa ulang di dalam transaksi. Urutan lock mengikuti jalur revisi Student → draft aktif → pengajuan → periode. Tanpa menyentuh storage/file.

```php
<?php

namespace App\Services;

use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDraft;
use App\Models\Student;
use App\Support\SidangTaRevisionDeadline;
use Illuminate\Support\Facades\DB;

class SidangTaRevisionExpiry
{
    public const NOTE = 'Ditolak otomatis karena revisi tidak dikirim sampai batas akhir periode Sidang TA.';

    public function run(): int
    {
        $count = 0;
        SidangTaRegistration::query()->where('requirements_version', 2)->where('status', 'needs_review')
            ->whereHas('period', fn ($query) => $query->where('close_at', '<', now()))
            ->select(['id'])->chunkById(100, function ($registrations) use (&$count) {
                foreach ($registrations as $registration) {
                    if ($this->expire((int) $registration->id)) $count++;
                }
            });
        return $count;
    }

    public function expire(int $id): bool
    {
        $candidate = SidangTaRegistration::select(['id', 'student_number'])->find($id);
        if (!$candidate) return false;

        return DB::transaction(function () use ($candidate) {
            // Urutan sama dengan jalur revisi: Student -> draft -> pengajuan -> periode.
            // Pengajuan historis masih dapat ditolak walaupun master Student tidak tersedia.
            Student::where('student_number', $candidate->student_number)->lockForUpdate()->first();
            SidangTaRegistrationDraft::where('source_registration_id', $candidate->id)
                ->where('status', SidangTaRegistrationDraft::STATUS_ACTIVE)->orderBy('id')->lockForUpdate()->get();
            $registration = SidangTaRegistration::whereKey($candidate->id)->lockForUpdate()->first();
            if (!$registration || $registration->student_number !== $candidate->student_number
                || $registration->requirements_version !== 2 || $registration->status !== 'needs_review') return false;
            if (!SidangTaRevisionDeadline::hasExpired($registration, true)) return false;

            $registration->status = 'rejected';
            $registration->review_notes = self::NOTE;
            $registration->reviewed_by = null;
            $registration->reviewed_at = now();
            $registration->lock_version++;
            $registration->save();
            $registration->logAction('rejected', 'needs_review', 'rejected', self::NOTE, null);
            // Tidak mengubah draft, dokumen, snapshot, atau file. Discard tetap milik mahasiswa.
            return true;
        }, 3);
    }
}
```

### 3.3 `app/Console/Commands/ExpireSidangTaRevisions.php`

Ganti keseluruhan isi. Laravel mengenali command di `app/Console/Commands` secara otomatis; tidak perlu membuat Console Kernel atau mengubah bootstrap. [Dokumentasi Artisan Laravel13](https://github.com/laravel/docs/blob/13.x/artisan.md).

```php
<?php

namespace App\Console\Commands;

use App\Services\SidangTaRevisionExpiry;
use Illuminate\Console\Command;

class ExpireSidangTaRevisions extends Command
{
    protected $signature = 'sidang-ta:expire-revisions';
    protected $description = 'Tolak otomatis revisi Sidang TA versi 2 yang melewati batas akhir periode';

    public function handle(SidangTaRevisionExpiry $expiry): int
    {
        $count = $expiry->run();
        $this->info('Pengajuan revisi ditolak otomatis: '.$count.'.');
        return self::SUCCESS;
    }
}
```

## 4. Pasang guard pada seluruh jalur tulis revisi

- [ ] Terapkan keempat perubahan berikut sebelum menjalankan ulang test.

### 4.1 `app/Services/SidangTaDraftAccess.php`

Ganti keseluruhan isi. Guard berada setelah pemeriksaan owner/status/version sumber dan hanya berjalan pada `write=true`. Dengan demikian save tahap 1, upload tahap 2–4, tinjau baru, dan finalisasi yang memakai access ini seluruhnya tertutup setelah tenggat. Discard tidak memanggil pemeriksaan sumber ini sehingga draft stale masih bisa dihapus pemiliknya.

```php
<?php

namespace App\Services;

use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDraft;
use App\Models\Student;
use App\Models\User;

class SidangTaDraftAccess
{
    public function __construct(private SidangTaStudentContext $context) {}

    public function owner(User $user, SidangTaRegistrationDraft $draft): Student
    {
        abort_unless((string) $draft->owner_user_id === (string) $user->id, 403);
        $student = $this->context->studentFor($user);
        abort_unless($draft->student_number === $student->student_number, 403);
        return $student;
    }

    public function assertCanWrite(User $user, SidangTaRegistrationDraft $draft): void
    {
        $permission = $draft->source_registration_id === null
            ? 'sidang_ta_registration_create' : 'sidang_ta_registration_update';
        abort_unless($user->can($permission), 403);
    }

    public function check(User $user, SidangTaRegistrationDraft $draft, bool $write = false): void
    {
        $this->owner($user, $draft);
        abort_unless($draft->status === SidangTaRegistrationDraft::STATUS_ACTIVE, 409);
        if ($write) {
            $this->assertCanWrite($user, $draft);
        }
        if ($draft->source_registration_id === null) {
            return;
        }

        // write=true hanya dipanggil di dalam transaksi setelah lock Student/draft.
        $query = SidangTaRegistration::whereKey($draft->source_registration_id);
        $source = ($write ? $query->lockForUpdate() : $query)->firstOrFail();
        abort_unless($source->student_number === $draft->student_number, 403);
        abort_unless($draft->requirements_version === $source->requirements_version, 409,
            'Versi persyaratan draft tidak cocok dengan pengajuan asal. Muat ulang pengajuan.');
        abort_unless($source->status === 'needs_review'
            && $source->lock_version === $draft->source_version, 409,
            'Pengajuan berubah. Buang draft revisi lama lalu muat ulang pengajuan.');

        if ($write) \App\Support\SidangTaRevisionDeadline::ensureOpen($source);

        foreach ($draft->documents()->whereNotNull('source_document_id')->get() as $document) {
            $valid = $source->documents()->whereKey($document->source_document_id)
                ->where('document_type', $document->document_type)
                ->where('file_path', $document->file_path)->exists();
            abort_unless($valid, 409, 'Referensi dokumen pengajuan sudah berubah.');
        }
    }
}
```

### 4.2 `app/Services/SidangTaRevisionDraftService.php`

Ganti **seluruh method `start()`** dengan berikut. Method `discard()`, constructor, import, dan bagian kelas lainnya tetap. Ini menutup pembuatan draft revisi pertama sesudah tenggat, ketika belum ada draft untuk diperiksa DraftAccess.

```php
    public function start(User $user, SidangTaRegistration $registration): SidangTaRegistrationDraft
    {
        abort_unless($user->can('sidang_ta_registration_update'), 403);
        $student = $this->context->studentFor($user);
        abort_unless($registration->student_number === $student->student_number, 403);

        return DB::transaction(function () use ($user, $registration, $student) {
            Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $existing = SidangTaRegistrationDraft::where('source_registration_id', $registration->id)
                ->where('status', SidangTaRegistrationDraft::STATUS_ACTIVE)->lockForUpdate()->first();
            $source = SidangTaRegistration::whereKey($registration->id)->lockForUpdate()->firstOrFail();
            abort_unless($source->student_number === $student->student_number, 403);
            abort_unless($source->status === 'needs_review', 409, 'Pengajuan tidak sedang membutuhkan revisi.');
            \App\Support\SidangTaRevisionDeadline::ensureOpen($source);
            if ($source->requirements_version === 2) Revision::assertCanRequest($source);
            if ($existing) {
                abort_unless((string) $existing->owner_user_id === (string) $user->id, 409);
                $this->access->check($user, $existing, true);
                return $existing;
            }

            $draft = SidangTaRegistrationDraft::create([
                'owner_user_id' => $user->id, 'student_number' => $student->student_number,
                'sidang_ta_period_id' => $source->sidang_ta_period_id,
                'source_registration_id' => $source->id,
                'source_version' => $source->lock_version,
                'requirements_version' => $source->requirements_version,
                'payload' => [
                    'student_phone' => $source->student_phone, 'parent_phone' => $source->parent_phone,
                    'graduation_payment_date' => $source->graduation_payment_date?->toDateString(),
                    'diploma_biodata_accurate' => $source->diploma_biodata_accurate,
                ],
            ]);
            $types = $source->requirements_version === 2
                ? \App\Support\SidangTaReviewEligibility::requiredTypes($source)
                : array_merge(Requirements::CAPTURES, Requirements::PAYMENT,
                    Requirements::typesForStep(4, $source->diploma_biodata_accurate));
            foreach ($source->documents()->whereIn('document_type', $types)->get() as $document) {
                $draft->documents()->create([
                    'document_type' => $document->document_type, 'file_name' => $document->file_name,
                    'file_path' => $document->file_path, 'file_size' => $document->file_size,
                    'file_type' => $document->file_type, 'source_document_id' => $document->id,
                ]);
            }
            return $draft;
        });
    }
```

### 4.3 `app/Services/SidangTaRegistrationReview.php`

Ganti **seluruh method `decide()`** dengan berikut. Method lain tetap. Petugas tidak boleh membuat permintaan revisi baru yang tenggatnya sudah lewat.

```php
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
                \App\Support\SidangTaRevisionDeadline::ensureOpen($locked, 'action');
                \App\Support\SidangTaRevisionContract::assertCanRequest($locked);
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
```

### 4.4 `app/Http/Controllers/SidangTaRegistrationController.php`

Ganti **seluruh method `adminShow()`** dengan berikut. Daftar alasan yang sudah ada akan menonaktifkan Minta Revisi sesudah tenggat, sesuai penolakan server. Method lain tetap.

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
        $revisionBlockers = [];
        if ($registration->requirements_version === 2 && $reviewable) {
            try {
                if (\App\Support\SidangTaRevisionDeadline::hasExpired($registration)) {
                    throw ValidationException::withMessages(['action' => \App\Support\SidangTaRevisionDeadline::MESSAGE]);
                }
                \App\Support\SidangTaRevisionContract::assertCanRequest($registration);
            } catch (ValidationException $exception) {
                $revisionBlockers = array_values(array_merge(...array_values($exception->errors())));
            }
        }
        return view('sidang_ta_registrations.admin.show', compact(
            'registration', 'reviewable', 'requiredTypes', 'approvalBlockers', 'documentGroups', 'revisionBlockers'
        ));
    }
```

Tidak perlu mengubah `SidangTaDraftFinalizer.php` maupun `SidangTaDraftReviewService.php`: keduanya sudah memanggil `check(..., true)` di dalam transaksi. Finalizer tetap mengembalikan hasil consumed **sebelum** memanggil check. Jangan menambahkan guard di awal finalizer atau di FormRequest secara global karena itu akan menggagalkan replay yang sah.

## 5. Tampilkan deadline dan tutup kontrol pengerjaan

- [ ] Terapkan controller dan empat Blade di bawah. Halaman yang sudah dibuka sebelum tenggat perlu dimuat ulang untuk memperbarui keadaan tombol; server tetap menolak request terlambat dari halaman lama.

### 5.1 `app/Http/Controllers/SidangTaDraftController.php`

Ganti **seluruh method private `meetContext()`** dengan berikut. Method lain tetap. Gate akademik tetap fresh dan kontrak EPRT revisi tetap mengikuti snapshot sumber seperti59.

```php
    private function meetContext(SidangTaRegistrationDraft $draft, Student $student): ?array
    {
        if ($draft->requirements_version !== 2) return null;
        $admission = app(\App\Services\SidangTaAdmissionFacts::class)->forStudent($student);
        $targeted = \App\Support\SidangTaRevisionContract::isTargeted($draft);
        $revisionExpired = false;
        if ($targeted) {
            $source = \App\Support\SidangTaRevisionContract::source($draft);
            $revisionExpired = \App\Support\SidangTaRevisionDeadline::hasExpired($source);
            $snapshot = $source->academic_snapshot ?? [];
            $stored = $snapshot['admission'] ?? [];
            $storedMode = $stored['eprt']['mode'] ?? null;
            abort_unless(in_array($storedMode, ['automatic', 'waiver'], true), 409,
                'Persyaratan EPRT pengajuan asal belum lengkap.');
            $admission['eprt'] = ['mode' => $storedMode, 'requires_waiver' => $storedMode === 'waiver',
                'message' => $storedMode === 'waiver' ? 'Surat Permakluman mengikuti pengajuan asal.' : 'EPRT memenuhi syarat saat pengajuan asal.'];
            $admission['eprt_facts'] = ['score' => $stored['eprt_facts']['score'] ?? $snapshot['eprt_score'] ?? null,
                'confirmed_attempts' => $stored['eprt_facts']['confirmed_attempts'] ?? null];
        }
        $mode = $admission['eprt']['mode'];
        $facts = $admission['facts'];
        $level = match ($facts['level_2_passed']) { true => 'Lulus', false => 'Belum lulus', default => 'Belum tersedia' };
        $skpi = match ($facts['skpi_approved']) { true => 'Disetujui', false => 'Belum memenuhi', default => 'Belum tersedia' };
        $krs = match ($admission['krs_status']) { 'eligible' => 'Terdaftar', 'unconfirmed' => 'Belum terkonfirmasi', default => 'Belum tersedia' };
        $payment = strtolower(trim((string) ($student->bppPayment()->value('status') ?? '')));
        $payment = match ($payment) { 'paid', 'lunas' => 'Lunas', 'unpaid', 'belum lunas', 'belum_lunas' => 'Belum lunas', default => 'Belum tersedia' };
        $score = $admission['eprt_facts']['score'];
        $attempts = $admission['eprt_facts']['confirmed_attempts'];
        $payload = $draft->payload ?? [];
        $choice = $payload['diploma_biodata_accurate'] ?? null;
        $accurate = $choice === null ? null : (bool) $choice;
        $types = [];
        foreach ([2, 3, 4] as $step) {
            $types[$step] = $targeted ? \App\Support\SidangTaRevisionContract::typesForStep($draft, $step)
                : \App\Support\SidangTaMeetRequirements::typesForStep($step, $accurate, $mode);
        }
        return [
            'admission' => $admission, 'types' => $types, 'targeted' => $targeted,
            'revision_expired' => $revisionExpired,
            'editable_types' => $targeted ? \App\Support\SidangTaRevisionContract::editableTypes($draft) : [],
            'can_submit' => !$revisionExpired && ($draft->source_registration_id === null || $targeted)
                && $admission['academic']['academic_passed']
                && in_array($mode, ['automatic', 'waiver'], true),
            'academic' => ['sidang_tingkat_2' => $level, 'skpi_status' => $skpi, 'ta2_krs_label' => $krs,
                'tak_score' => $facts['tak_score'], 'passed_credits' => $facts['passed_credits'], 'eprt_score' => $score],
            'automatic' => [
                2 => [
                    ['key' => 'level_2', 'title' => 'Hasil sidang tingkat 2', 'value' => $level, 'hint' => 'Tidak memerlukan unggahan.'],
                    ['key' => 'sidang_approval', 'title' => 'Persetujuan sidang iGracias', 'value' => 'Belum tersedia', 'hint' => 'Persetujuan sidang belum tercatat.'],
                ],
                3 => [
                    ['key' => 'bpp', 'title' => 'BPP semester berjalan', 'value' => $payment,
                        'hint' => 'Periode pembayaran belum terkonfirmasi. Tidak memerlukan unggahan.'],
                    ['key' => 'eprt', 'title' => $targeted ? 'EPRT pengajuan asal' : 'EPRT', 'value' => $score === null ? 'Belum tersedia' : 'Nilai '.$score,
                        'hint' => $admission['eprt']['message'].($attempts === null ? '' : ' '.$attempts.' tes tercatat.')],
                ],
            ],
        ];
    }
```

### 5.2 `resources/views/sidang_ta_registrations/wizard.blade.php`

Ganti keseluruhan isi.

```blade
<x-app-layout>
    <x-slot name="header">
        {{ $draft?->source_registration_id ? 'Draft Revisi Sidang TA' : 'Pendaftaran Sidang TA' }}
    </x-slot>
    @php
        $payload = $draft?->payload ?? [];
        $isMeet = $draft?->requirements_version === 2;
        $meetContext = $meetContext ?? null;
        $targetedRevision = (bool) ($meetContext['targeted'] ?? false);
        $revisionExpired = (bool) ($meetContext['revision_expired'] ?? false);
        $rawAccurate = $targetedRevision ? ($payload['diploma_biodata_accurate'] ?? null)
            : old('diploma_biodata_accurate', $payload['diploma_biodata_accurate'] ?? null);
        $accurate = match ($rawAccurate) {
            true, 1, '1' => '1', false, 0, '0' => '0', default => '',
        };
        $stepProgress = $stepProgress ?? ['complete' => false, 'stored_documents' => 0,
            'required_documents' => 0, 'missing' => []];
        $selectedPeriod = $targetedRevision ? (string) $draft->sidang_ta_period_id
            : (string) old('sidang_ta_period_id', $draft?->sidang_ta_period_id ?? '');
        $periods = $openPeriods;
        if ($draft && !$periods->contains('id', $draft->sidang_ta_period_id)) {
            $periods = $periods->prepend($draft->period);
        }
        $selected = $periods->first(fn ($period) => (string) $period->id === $selectedPeriod);
        $windowFor = static fn ($period) => $period->open_at->format('d/m/Y H:i').' – '
            .$period->close_at->format('d/m/Y H:i').($period->isOpen() ? '' : ' (ditutup)');
        $initial = ['accurate' => $accurate, 'dirty' => $errors->any(), 'hasErrors' => $errors->any(),
            'periodHint' => $selected ? $windowFor($selected) : ''];
        $reviewReady = $draft && $completedThrough === 4 && !$revisionExpired;
        $permission = $draft?->source_registration_id ? 'sidang_ta_registration_update' : 'sidang_ta_registration_create';
        $titles = [1 => 'Data dasar & kontak', 2 => 'Verifikasi iGracias & akademik',
            3 => 'Pembayaran & dokumen', 4 => 'Biodata ijazah'];
    @endphp
    <div class="mx-auto max-w-7xl space-y-5" data-current-step="{{ $step }}"
         x-data="sidangTaWizard(@js($initial))" @sidang-ta-file-changed="dirty = true"
         @pageshow.window="busy = false"
         @beforeunload.window="if (dirty && !busy) { $event.preventDefault(); $event.returnValue = ''; }">
        @if($errors->any())
            <div data-error-summary tabindex="-1" role="alert"
                 class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-rose-800">
                <p class="text-sm font-bold">
                    Periksa isian berikut:
                </p>
                <ul class="mt-2 list-inside list-disc space-y-1 text-sm">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <p class="mt-2 text-xs leading-relaxed">
                    Data yang sudah tersimpan tetap tersedia. File pilihan baru yang ditolak perlu dipilih ulang.
                </p>
            </div>
        @endif
        @if(session('success'))
            <p role="status" class="rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('success') }}
            </p>
        @endif

        <section class="campus-card overflow-hidden" aria-label="Progres pendaftaran">
            <nav aria-label="Tahap pendaftaran" data-progress-scroll class="overflow-x-auto p-4 sm:p-5">
                <ol class="flex min-w-max items-stretch gap-2">
                    @foreach($titles as $number => $title)
                        @php
                            $reachable = $number <= $maxStep;
                            $state = $number === $step ? 'current' : ($number < $step && $number <= $completedThrough
                                ? 'complete' : ($reachable ? 'available' : 'locked'));
                            $tone = match ($state) {
                                'current' => 'border-[#DA2D3B] bg-[#DA2D3B] text-white',
                                'complete' => 'border-rose-200 bg-rose-50 text-rose-800',
                                'available' => 'border-slate-200 bg-white text-slate-700',
                                default => 'border-slate-200 bg-slate-100 text-slate-400',
                            };
                        @endphp
                        <li class="flex items-center gap-2">
                            @if($draft && $reachable)
                                <a data-step="{{ $number }}" data-state="{{ $state }}" @click="leave($event)"
                                   href="{{ route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => $number]) }}"
                                   @if($number === $step) aria-current="step" @endif
                                   class="block w-40 rounded-xl border px-3 py-3 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2 {{ $tone }}">
                                    <span class="text-sm font-bold">
                                        Tahap {{ $number }}{{ $state === 'complete' ? ' ✓' : '' }}
                                    </span>
                                    <span class="mt-1 block whitespace-normal text-xs leading-relaxed">
                                        {{ $title }}
                                    </span>
                                </a>
                            @else
                                <span data-step="{{ $number }}" data-state="{{ $state }}"
                                      @if($number === $step) aria-current="step" @else aria-disabled="true" @endif
                                      class="block w-40 rounded-xl border px-3 py-3 {{ $tone }}">
                                    <span class="text-sm font-bold">
                                        Tahap {{ $number }}
                                    </span>
                                    <span class="mt-1 block whitespace-normal text-xs leading-relaxed">
                                        {{ $title }}
                                    </span>
                                </span>
                            @endif
                            @if($number < 4)
                                <span data-chevron="{{ $number }}" aria-hidden="true"
                                      class="px-1 text-xl font-bold {{ $number <= $step ? 'text-[#DA2D3B]' : 'text-slate-300' }}">&raquo;</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>
            <div data-save-status
                 class="space-y-1 border-t border-slate-100 bg-slate-50 px-4 py-3 text-xs leading-relaxed sm:px-5">
                <p class="font-medium text-slate-700">
                    {{ $draft ? 'Terakhir disimpan '.$draft->updated_at->format('d/m/Y H:i').'.' : 'Belum ada data yang disimpan.' }}
                </p>
                <p class="text-slate-500">
                    Tekan Simpan agar data dan file dapat dilanjutkan setelah halaman ditutup.
                </p>
                <p role="status" aria-live="polite" class="font-medium text-amber-800"
                   x-text="busy ? 'Sedang menyimpan…' : (dirty ? 'Ada perubahan yang belum disimpan.' : '')"></p>
            </div>
        </section>

        @if($revisionExpired)
            <div data-revision-deadline-expired role="alert" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                Batas waktu revisi telah berakhir. Draft dan berkas tetap tersimpan untuk dilihat, tetapi penyimpanan,
                unggah pengganti, tinjauan baru, dan pengiriman revisi sudah ditutup.
                Anda tetap dapat kembali ke daftar pendaftaran untuk menghapus draft.
            </div>
        @endif

        <div class="grid items-start gap-6 xl:grid-cols-12">
            <div class="min-w-0 space-y-5 xl:col-span-8">
                @if($draft?->sourceRegistration?->review_notes)
                    <section class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                        <h2 class="text-sm font-bold text-amber-900">
                            Catatan petugas
                        </h2>
                        <p class="mt-1 whitespace-pre-line text-sm leading-relaxed text-amber-800">
                            {{ $draft->sourceRegistration->review_notes }}
                        </p>
                    </section>
                @endif
                @if($draft?->sourceRegistration)
                    @include('sidang_ta_registrations.partials.revision-feedback', ['registration' => $draft->sourceRegistration])
                @endif

                <form id="sidang-ta-step-form" method="POST" novalidate enctype="multipart/form-data"
                      class="campus-card space-y-5 p-5 sm:p-6"
                      action="{{ $draft ? route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => $step]) : route('sidang-ta-drafts.store') }}"
                      @input="dirty = true" @change="dirty = true"
                      @submit="if (busy || {{ $revisionExpired ? 'true' : 'false' }}) { $event.preventDefault(); } else { busy = true; }">
                    @csrf
                    @if($draft)
                        <input type="hidden" name="version" value="{{ old('version', $draft->version) }}">
                    @endif
                    <header class="space-y-1.5 border-b border-slate-100 pb-4">
                        <h2 class="text-lg font-bold text-slate-900">Tahap {{ $step }} — {{ $titles[$step] }}</h2>
                        <p class="text-xs text-slate-500">
                            @if($isMeet)
                                Tanda * menunjukkan unggahan wajib. Draft boleh disimpan sebelum lengkap.
                            @else
                                Isian bertanda <span class="font-bold text-rose-600">*</span> wajib lengkap sebelum
                                lanjut/kirim. Draft boleh disimpan sebelum lengkap.
                            @endif
                        </p>
                    </header>
                    @if($draft && !$stepProgress['complete'])
                        <section data-step-progress role="status"
                                 class="space-y-2 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                            <p class="font-semibold">
                                Lengkapi tahap ini sebelum melanjutkan.
                            </p>
                            @if($stepProgress['required_documents'] !== null && $stepProgress['required_documents'] > 0)
                                <p>
                                    {{ $stepProgress['stored_documents'] }}
                                    dari {{ $stepProgress['required_documents'] }} dokumen tersimpan.
                                </p>
                            @endif
                            <p class="text-xs">
                                Masih perlu dilengkapi:
                            </p>
                            <ul class="list-inside list-disc space-y-1 text-xs leading-relaxed">
                                @foreach($stepProgress['missing'] as $missing)
                                    <li>{{ $missing }}</li>
                                @endforeach
                            </ul>
                        </section>
                    @endif
                    @if($step === 1)
                        <div class="max-w-lg">
                            <label for="sidang-ta-period-id" class="mb-1.5 block text-sm font-semibold text-slate-700">
                                Periode Sidang TA @unless($isMeet)<span class="text-rose-600" aria-hidden="true">*</span>@endunless
                            </label>
                            @if($draft?->source_registration_id)
                                <input type="hidden" name="sidang_ta_period_id"
                                       value="{{ $draft->sidang_ta_period_id }}">
                                <p id="sidang-ta-period-id" tabindex="-1"
                                   class="rounded-lg bg-slate-50 p-3 text-sm font-medium text-slate-800"
                                   aria-invalid="{{ $errors->has('sidang_ta_period_id') ? 'true' : 'false' }}"
                                   aria-describedby="period-window error-sidang_ta_period_id">
                                    {{ $draft->period->name }}(periode pengajuan asal)
                                </p>
                            @else
                                <select id="sidang-ta-period-id" name="sidang_ta_period_id" required
                                        class="campus-input w-full {{ $errors->has('sidang_ta_period_id') ? '!border-rose-500' : '' }}"
                                        aria-invalid="{{ $errors->has('sidang_ta_period_id') ? 'true' : 'false' }}"
                                        aria-describedby="period-window error-sidang_ta_period_id"
                                        @change="updatePeriodHint($event.target)">
                                    <option value="">Pilih periode terbuka</option>
                                    @foreach($periods as $period)
                                        <option value="{{ $period->id }}"
                                                data-window="{{ $windowFor($period) }}" @selected($selectedPeriod === (string) $period->id)>
                                            {{ $period->name }}{{ $period->isOpen() ? '' : ' (ditutup)' }}
                                        </option>
                                    @endforeach
                                </select>
                            @endif
                            <p id="period-window" class="mt-1.5 text-xs leading-relaxed text-slate-500"
                               x-text="periodHint || 'Pilih periode yang masih terbuka.'">{{ $initial['periodHint'] ?: 'Pilih periode yang masih terbuka.' }}</p>
                            @include('sidang_ta_registrations.partials.wizard-error', ['field' => 'sidang_ta_period_id'])
                        </div>
                        <div class="grid max-w-xl gap-4 sm:grid-cols-2">
                            <div class="min-w-0">
                                <label for="student-phone" class="mb-1.5 block text-sm font-semibold text-slate-700">
                                    Nomor HP mahasiswa @unless($isMeet)<span class="text-rose-600" aria-hidden="true">*</span>@endunless
                                </label>
                                <input id="student-phone"
                                       class="campus-input w-full {{ $errors->has('student_phone') ? '!border-rose-500' : '' }}"
                                       name="student_phone" @readonly($targetedRevision) inputmode="tel" maxlength="30" required
                                       placeholder="08xxxxxxxxxx"
                                       aria-invalid="{{ $errors->has('student_phone') ? 'true' : 'false' }}"
                                       aria-describedby="error-student_phone"
                                       value="{{ $targetedRevision ? ($payload['student_phone'] ?? '') : old('student_phone', $payload['student_phone'] ?? $student->phone_number) }}">
                                @include('sidang_ta_registrations.partials.wizard-error', ['field' => 'student_phone'])
                            </div>
                            <div class="min-w-0">
                                <label for="parent-phone" class="mb-1.5 block text-sm font-semibold text-slate-700">
                                    Nomor HP orang tua/wali @unless($isMeet)<span class="text-rose-600" aria-hidden="true">*</span>@endunless
                                </label>
                                <input id="parent-phone"
                                       class="campus-input w-full {{ $errors->has('parent_phone') ? '!border-rose-500' : '' }}"
                                       name="parent_phone" @readonly($targetedRevision) inputmode="tel" maxlength="30" required
                                       placeholder="08xxxxxxxxxx"
                                       aria-invalid="{{ $errors->has('parent_phone') ? 'true' : 'false' }}"
                                       aria-describedby="error-parent_phone"
                                       value="{{ $targetedRevision ? ($payload['parent_phone'] ?? '') : old('parent_phone', $payload['parent_phone'] ?? $student->parent_phone_number) }}">
                                @include('sidang_ta_registrations.partials.wizard-error', ['field' => 'parent_phone'])
                            </div>
                        </div>
                    @elseif($step === 2 || $step === 3)
                        @if($step === 3)
                            <div class="max-w-xs">
                                <label for="graduation-payment-date"
                                       class="mb-1.5 block text-sm font-semibold text-slate-700">
                                    Tanggal pembayaran wisuda @unless($isMeet)<span class="text-rose-600" aria-hidden="true">*</span>@endunless
                                </label>
                                <input id="graduation-payment-date"
                                       class="campus-input w-full {{ $errors->has('graduation_payment_date') ? '!border-rose-500' : '' }}"
                                       type="date" name="graduation_payment_date" @readonly($targetedRevision) required
                                       max="{{ today()->toDateString() }}"
                                       aria-invalid="{{ $errors->has('graduation_payment_date') ? 'true' : 'false' }}"
                                       aria-describedby="error-graduation_payment_date"
                                       value="{{ $targetedRevision ? ($payload['graduation_payment_date'] ?? '') : old('graduation_payment_date', $payload['graduation_payment_date'] ?? '') }}">
                                @include('sidang_ta_registrations.partials.wizard-error', ['field' => 'graduation_payment_date'])
                            </div>
                        @endif
                        @if($isMeet)
                            @include('sidang_ta_registrations.partials.meet-automatic-cards', ['items' => $meetContext['automatic'][$step] ?? []])
                        @endif
                        <div class="grid gap-4 sm:grid-cols-2">
                            @foreach(($isMeet ? $meetContext['types'][$step] : \App\Support\SidangTaDocumentRequirements::typesForStep($step)) as $type)
                                @include('sidang_ta_registrations.partials.wizard-upload', ['type' => $type, 'conditional' => null])
                            @endforeach
                        </div>
                    @else
                        @if($targetedRevision)
                            <input type="hidden" name="diploma_biodata_accurate" value="{{ $accurate }}">
                        @endif
                        <fieldset id="diploma-biodata-group" tabindex="-1" aria-required="true"
                                  aria-invalid="{{ $errors->has('diploma_biodata_accurate') ? 'true' : 'false' }}"
                                  aria-describedby="error-diploma_biodata_accurate">
                            <legend class="text-sm font-semibold text-slate-700">
                                Apakah biodata dummy ijazah sudah sesuai? @unless($isMeet)<span class="text-rose-600" aria-hidden="true">*</span>@endunless
                            </legend>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                @foreach(['1' => 'Sudah sesuai', '0' => 'Belum sesuai, sudah mengajukan PDM'] as $value => $label)
                                    <label
                                        class="flex cursor-pointer items-start gap-3 rounded-xl border p-3 text-sm leading-relaxed"
                                        :class="accurate === '{{ $value }}' ? 'border-rose-300 bg-rose-50 text-rose-900' : 'border-slate-200 text-slate-700'">
                                        <input type="radio" name="diploma_biodata_accurate" @disabled($targetedRevision) value="{{ $value }}"
                                               x-model="accurate" @checked($accurate === (string) $value) required
                                               class="mt-0.5 border-slate-300 text-rose-600 focus:ring-rose-500">
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @include('sidang_ta_registrations.partials.wizard-error', ['field' => 'diploma_biodata_accurate'])
                        </fieldset>
                        <p class="text-xs leading-relaxed text-slate-500">
                            Menyimpan pilihan biodata yang berbeda akan mengganti lampiran cabang draft sebelumnya.
                            Berkas pengajuan final tetap tersimpan.
                        </p>
                        <div x-show="accurate === '1'" x-cloak class="grid gap-4 sm:grid-cols-2">
                            @foreach(\App\Support\SidangTaDocumentRequirements::DIPLOMA as $type)
                                @include('sidang_ta_registrations.partials.wizard-upload', ['type' => $type, 'conditional' => '1'])
                            @endforeach
                        </div>
                        <div x-show="accurate === '0'" x-cloak>
                            @include('sidang_ta_registrations.partials.wizard-upload', ['type' => 'pdm_submission_proof', 'conditional' => '0'])
                        </div>
                    @endif
                    <div
                        class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                        <a @click="leave($event)" class="btn btn-secondary"
                           href="{{ route('sidang-ta-registrations.index') }}">
                            Kembali ke pendaftaran
                        </a>
                        @can($permission)
                            <div class="flex flex-wrap items-center gap-2">
                                <button data-save-draft type="submit" class="btn btn-secondary disabled:cursor-not-allowed disabled:opacity-50"
                                        @disabled($revisionExpired) :disabled="busy || {{ $revisionExpired ? 'true' : 'false' }}"
                                        x-text="busy ? 'Sedang menyimpan…' : 'Simpan draft'">
                                    Simpan draft
                                </button>
                                @if($step < 4)
                                    @if($draft && $stepProgress['complete'])
                                        <a data-next-step class="btn btn-primary" aria-disabled="false"
                                           href="{{ route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => $step + 1]) }}"
                                           :aria-disabled="dirty || busy ? 'true' : 'false'"
                                           :tabindex="dirty || busy ? -1 : 0"
                                           :class="dirty || busy ? 'pointer-events-none opacity-50' : ''"
                                           @click="if (dirty || busy) $event.preventDefault()">
                                            Lanjutkan
                                        </a>
                                    @else
                                        <button data-next-step type="button" disabled
                                                class="btn btn-primary cursor-not-allowed opacity-50">
                                            Lanjutkan
                                        </button>
                                    @endif
                                @endif
                            </div>
                        @endcan
                    </div>
                </form>
                @if($draft && $step === 4)
                    @can($permission)
                        <form data-review-draft method="POST" class="campus-card space-y-3 p-5"
                              action="{{ route('sidang-ta-drafts.review', $draft) }}"
                              x-on:submit="if (dirty || busy || {{ $reviewReady ? 'false' : 'true' }}) { $event.preventDefault(); } else { busy = true; }">
                            @csrf <input type="hidden" name="version" value="{{ $draft->version }}">
                            <p class="text-sm leading-relaxed text-slate-600">
                                Draft harus tersimpan dan seluruh tahap lengkap sebelum ditinjau. Pendaftaran baru
                                dikirim setelah konfirmasi pada halaman tinjau.
                            </p>
                            <p id="review-readiness" class="text-xs text-slate-500">
                                {{ $revisionExpired ? 'Batas waktu revisi telah berakhir. Tinjauan baru ditutup.'
                                    : 'Lengkapi seluruh dokumen dan isian, lalu tekan Simpan draft untuk mengaktifkan tinjauan.' }}
                            </p>
                            <button data-review-submit type="submit" aria-describedby="review-readiness"
                                    aria-disabled="{{ $reviewReady ? 'false' : 'true' }}"
                                    :aria-disabled="dirty || busy || {{ $reviewReady ? 'false' : 'true' }} ? 'true' : 'false'"
                                    class="btn btn-primary disabled:cursor-not-allowed disabled:opacity-50 disabled:shadow-none"
                                    @disabled(!$reviewReady)
                                    :disabled="dirty || busy || {{ $reviewReady ? 'false' : 'true' }}">
                                Tinjau Sebelum Kirim
                            </button>
                        </form>
                    @endcan
                @endif
            </div>
            @include('sidang_ta_registrations.partials.wizard-academic')
        </div>
    </div>
    @include('sidang_ta_registrations.partials.document-preview-modal')
</x-app-layout>
```

### 5.3 `resources/views/sidang_ta_registrations/partials/wizard-upload.blade.php`

Ganti keseluruhan isi.

```blade
@php
    $existing = $draft?->documents?->firstWhere('document_type', $type);
    $available = $existing && \Illuminate\Support\Facades\Storage::disk('local')->exists($existing->file_path);
    $isMeetUpload = $draft?->requirements_version === 2;
    $revisionExpired = (bool) ($meetContext['revision_expired'] ?? false);
    $lockedUpload = $revisionExpired || ((bool) ($meetContext['targeted'] ?? false)
        && !in_array($type, $meetContext['editable_types'] ?? [], true));
    $replacementNeeded = (bool) ($meetContext['targeted'] ?? false) && !$lockedUpload
        && $existing && $existing->source_document_id !== null;
    $label = ($isMeetUpload ? \App\Support\SidangTaMeetRequirements::LABELS[$type] ?? null : null)
        ?? \App\Models\SidangTaRegistration::DOCUMENT_TYPES[$type] ?? $type;
    $capture = in_array($type, $isMeetUpload ? \App\Support\SidangTaMeetRequirements::CAPTURES
        : \App\Support\SidangTaDocumentRequirements::CAPTURES, true);
    $guide = $isMeetUpload ? match ($type) {
        'ta_data_capture' => 'assets/images/forms/sidang-ta/ta-data.png',
        'online_supervision_capture' => 'assets/images/forms/sidang-ta/bimbingan-online.png',
        default => null,
    } : null;
    $guideAvailable = $guide && is_file(public_path($guide));
    $conditional = $conditional ?? null;
    $errorKey = "documents.{$type}";
    $uploadInitial = ['serverError' => $errors->has($errorKey)];
@endphp
<div data-upload-type="{{ $type }}" data-upload-locked="{{ $lockedUpload ? 'true' : 'false' }}"
     class="min-w-0 rounded-xl border border-slate-200 p-4 {{ $lockedUpload ? 'bg-slate-100 text-slate-500' : 'bg-white' }}"
     x-data="sidangTaUpload(@js($uploadInitial))">
    <label class="block text-sm font-semibold leading-relaxed text-slate-800" for="file-{{ $type }}">
        {{ $label }} @unless($lockedUpload)<span class="text-rose-600" aria-hidden="true">*</span>@endunless
    </label>
    <p id="help-{{ $type }}" class="mt-1.5 text-xs leading-relaxed text-slate-500">
        @unless($guideAvailable)
            {{ \App\Models\SidangTaRegistration::DOCUMENT_DESCRIPTIONS[$type] ?? '' }}
        @endunless
        {{ $capture ? 'PDF/JPG/JPEG/PNG' : 'PDF' }}, maksimal 10 MB per file.
    </p>
    @if($guideAvailable)
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

    @endif
    @if($isMeetUpload && $type === \App\Support\SidangTaMeetRequirements::WAIVER)
        <p class="mt-2 text-xs leading-relaxed text-slate-600">
            Unduh template, lengkapi, lalu unggah kembali dalam format PDF.
            <a data-waiver-template href="https://telkomuniversityofficial-my.sharepoint.com/:w:/g/personal/akademiksby_telkomuniversity_ac_id/EYC5y40Ky_1PhVWh4_LswJoBtC8yKT6PuTEAwUTDiFY-2A?e=qRenjo"
               target="_blank" rel="noopener" class="font-semibold text-[#DA2D3B] underline">Link Surat Permakluman</a>
        </p>
    @endif
    @if($available)
        <div class="mt-3 space-y-2 rounded-lg border border-emerald-200 bg-emerald-50 p-3">
            <p class="text-xs font-semibold text-emerald-800">Berkas tersimpan</p>
            <p class="break-words text-sm text-slate-800">{{ $existing->file_name }}</p>
            <p class="text-xs text-slate-500">
                {{ number_format(($existing->file_size ?? 0) / 1024) }} KB · {{ $lockedUpload ? 'Berkas ini tetap digunakan.' : 'Pilih berkas baru hanya untuk mengganti.' }}
            </p>
        </div>
    @elseif($existing)
        <p class="mt-3 text-sm text-rose-700">
            {{ $revisionExpired ? 'Berkas tersimpan tidak ditemukan. Batas waktu unggah pengganti telah berakhir.'
                : 'Berkas tersimpan tidak ditemukan. Unggah kembali.' }}
        </p>
    @endif
    @if($replacementNeeded)
        <p data-replacement-needed class="mt-3 rounded-lg bg-amber-50 p-3 text-xs font-semibold text-amber-900">
            Unggah berkas pengganti sesuai catatan petugas. Berkas pengajuan sebelumnya belum dihitung sebagai pengganti.
        </p>
    @endif
    @if($lockedUpload)
        <p data-upload-preserved class="mt-3 rounded-lg bg-slate-200/60 p-3 text-xs text-slate-600">
            {{ $revisionExpired ? 'Batas waktu revisi telah berakhir. Unggah pengganti sudah ditutup.'
                : 'Dokumen terverifikasi tetap digunakan. Pengunggahan hanya tersedia untuk berkas yang diminta diperbaiki.' }}
        </p>
    @else
        <div
            class="relative mt-3 rounded-xl border-2 border-dashed p-4 transition-colors focus-within:ring-2 focus-within:ring-rose-500 focus-within:ring-offset-2"
            :class="hasError() ? 'border-rose-500 bg-rose-50' : (dragDepth > 0 ? 'border-rose-500 bg-rose-50' : 'border-slate-300 bg-slate-50 hover:border-rose-400')"
            @dragenter.prevent.stop="enter()" @dragover.prevent.stop
            @dragleave.prevent.stop="leaveDrop()" @drop.prevent.stop="drop($event)">
            <input id="file-{{ $type }}" x-ref="file" type="file" name="documents[{{ $type }}]"
                   accept="{{ $capture ? '.pdf,.jpg,.jpeg,.png' : '.pdf' }}"
                   class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                   aria-describedby="help-{{ $type }} error-documents-{{ $type }}"
                   aria-invalid="{{ $errors->has($errorKey) ? 'true' : 'false' }}"
                   :aria-invalid="hasError() ? 'true' : 'false'"
                   @change="picked()"
                   @if($conditional !== null)
                       @disabled($accurate !== $conditional)
                       :disabled="accurate !== '{{ $conditional }}'"
                   :required="accurate === '{{ $conditional }}' && {{ $available && !$replacementNeeded ? 'false' : 'true' }}"
            @else
                @required(!$available || $replacementNeeded)
                @endif>
            <div class="pointer-events-none py-3 text-center">
                <svg aria-hidden="true" class="mx-auto h-7 w-7 text-slate-400" fill="none" stroke="currentColor"
                     viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                          d="M12 16V4m0 0L7 9m5-5 5 5M4 16v3a1 1 0 001 1h14a1 1 0 001-1v-3"/>
                </svg>
                <p class="mt-2 text-sm font-medium text-slate-700">
                    Klik untuk memilih atau seret file ke sini
                </p>
                <p class="mt-1 text-xs text-slate-500">
                    {{ $capture ? 'PDF/JPG/PNG' : 'PDF' }} · Maksimal 10 MB · Satu berkas
                </p>
            </div>
        </div>
        <div x-show="fileName" x-cloak class="mt-3 space-y-2 rounded-lg border border-amber-200 bg-amber-50 p-3">
            <p class="text-xs font-semibold text-amber-800">
                Pilihan baru · Belum disimpan
            </p>
            <p class="break-words text-sm text-slate-800" x-text="fileName"></p>
            <p class="text-xs text-slate-500" x-text="fileSize"></p>
            <button type="button" class="btn btn-secondary text-xs" @click="clear()">
                Batalkan pilihan baru
            </button>
        </div>
        <p id="error-documents-{{ $type }}" role="status" aria-live="polite"
           class="mt-1.5 text-xs leading-relaxed text-rose-700" x-show="hasError()">
            <span x-show="localError" x-text="localError"></span>
            <span x-show="!localError && !selectionChanged">{{ $errors->first($errorKey) }}</span>
        </p>
    @endif
</div>
```

### 5.4 `resources/views/sidang_ta_registrations/wizard-review.blade.php`

Ganti keseluruhan isi.

```blade
<x-app-layout>
    <x-slot name="header">Tinjau Pendaftaran Sidang TA</x-slot>
    @php
        $payload = $draft->payload;
        $isMeet = $draft->requirements_version === 2;
        $meetContext = $meetContext ?? null;
        $revisionExpired = (bool) ($meetContext['revision_expired'] ?? false);
        $sendBlocked = $isMeet && !($meetContext['can_submit'] ?? false);
        $accurate = (bool) $payload['diploma_biodata_accurate'];
        $isRevision = $draft->source_registration_id !== null;
        $permission = $isRevision ? 'sidang_ta_registration_update' : 'sidang_ta_registration_create';
        $groups = [2 => 'Verifikasi iGracias & akademik', 3 => 'Pembayaran & dokumen', 4 => 'Biodata ijazah'];
        $documentCount = array_sum(array_map('count', $documentGroups));
        $confirmLabel = $isRevision ? 'Ya, kirim revisi' : 'Ya, kirim pendaftaran';
    @endphp
    <div class="mx-auto max-w-6xl space-y-5">
        @if($errors->any())
            <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
                <p class="font-bold">Pendaftaran belum dapat dikirim.</p>
                <ul class="mt-2 list-inside list-disc space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section data-review-summary class="campus-card overflow-hidden">
            <header class="border-b border-rose-100 bg-rose-50/60 p-5 sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-[#DA2D3B]">Pemeriksaan
                            akhir{{ $isRevision ? ' · Revisi' : '' }}</p>
                        <h2 class="mt-1 text-xl font-bold text-slate-900">Tinjau sebelum kirim</h2>
                        <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-600">Periksa kembali data dan
                            seluruh berkas sebelum mengirim pendaftaran kepada petugas.</p>
                    </div>
                    <span
                        class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800">Belum dikirim</span>
                </div>
            </header>
            <div class="space-y-5 p-5 sm:p-6">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="min-w-0 rounded-xl bg-slate-50 p-4">
                        <p class="text-xs font-semibold text-slate-500">Mahasiswa</p>
                        <p class="mt-1 break-words text-base font-bold text-slate-900">{{ $student->name }}</p>
                        <p class="mt-1 break-words text-sm text-slate-600">{{ $student->student_number }}
                            · {{ $student->study_program ?: 'Program studi belum tersedia' }}</p>
                    </div>
                    <div class="min-w-0 rounded-xl bg-slate-50 p-4">
                        <p class="text-xs font-semibold text-slate-500">Periode Sidang TA</p>
                        <p class="mt-1 break-words text-base font-bold text-slate-900">{{ $draft->period->name }}</p>
                        <p class="mt-1 text-sm text-slate-600">{{ $documentCount }} dokumen dalam
                            tinjauan{{ $isRevision ? ' · Periode pengajuan asal' : '' }}</p>
                    </div>
                </div>
                <dl class="grid gap-x-6 gap-y-5 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs text-slate-500">
                            Nomor HP mahasiswa
                        </dt>
                        <dd class="mt-1 break-words text-sm font-semibold text-slate-800">
                            {{ $payload['student_phone'] }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">
                            Nomor HP orang tua/wali
                        </dt>
                        <dd class="mt-1 break-words text-sm font-semibold text-slate-800">
                            {{ $payload['parent_phone'] }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">
                            Tanggal pembayaran wisuda
                        </dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-800">
                            {{ \Illuminate\Support\Carbon::parse($payload['graduation_payment_date'])->translatedFormat('d M Y') }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">
                            Biodata dummy ijazah
                        </dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-800">
                            {{ $accurate ? 'Sudah sesuai' : 'Belum sesuai, bukti PDM dilampirkan' }}
                        </dd>
                    </div>
                </dl>
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
                    <p class="text-xs leading-relaxed text-slate-500">
                        Data di bawah berasal dari draft yang tersimpan. Pendaftaran belum dikirim.
                    </p>
                    <a class="text-sm font-semibold text-[#DA2D3B] hover:underline"
                       href="{{ route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 1]) }}">
                        Ubah data kontak
                    </a>
                </div>
            </div>
        </section>

        @if($isMeet)
            @include('sidang_ta_registrations.partials.meet-admission-summary', ['admission' => $meetContext['admission']])
        @endif

        @foreach($groups as $number => $title)
            @php
                $types = $documentGroups[$number];
            @endphp
            <section data-review-group="{{ $number }}" class="campus-card overflow-hidden">
                <header
                    class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4 sm:px-6">
                    <div class="flex items-center gap-3">
                        <div>
                            <h3 class="text-base font-bold text-slate-800">
                                {{ $title }}
                            </h3>
                            <p class="mt-0.5 text-xs text-slate-500">
                                Tahap {{ $number }} · {{ count($types) }} berkas tersimpan
                            </p>
                        </div>
                    </div>
                    <a class="text-sm font-semibold text-[#DA2D3B] hover:underline"
                       href="{{ route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => $number]) }}">Ubah
                        tahap {{ $number }}</a>
                </header>
                @if($isMeet && isset($meetContext['automatic'][$number]))
                    <div class="px-5 pt-4 sm:px-6">
                        @include('sidang_ta_registrations.partials.meet-automatic-cards', ['items' => $meetContext['automatic'][$number]])
                    </div>
                @endif
                <div class="divide-y divide-slate-100 px-5 sm:px-6">
                    @foreach($types as $type)
                        @php
                            $document = $draft->documents->firstWhere('document_type', $type);
                            $documentLabel = ($isMeet ? \App\Support\SidangTaMeetRequirements::LABELS[$type] ?? null : null)
                                ?? \App\Models\SidangTaRegistration::DOCUMENT_TYPES[$type] ?? $type;
                        @endphp
                        <div data-review-document="{{ $type }}"
                             class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between sm:gap-5">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold leading-relaxed text-slate-800">{{ $documentLabel }}</p>
                                <p class="mt-1 break-words text-xs leading-relaxed text-slate-500">{{ $document->file_name }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ number_format(($document->file_size ?? 0) / 1024) }}
                                    KB</p>
                            </div>
                            <a data-document-preview
                               class="btn btn-secondary shrink-0 self-start whitespace-nowrap !text-xs no-underline sm:self-auto"
                               href="{{ route('sidang-ta-draft-documents.preview', $document) }}"
                               data-preview-url="{{ route('sidang-ta-draft-documents.preview', $document) }}"
                               data-download-url="{{ route('sidang-ta-draft-documents.download', $document) }}"
                               data-file-name="{{ $document->file_name }}" data-file-type="{{ $document->file_type }}"
                               target="_blank" rel="noopener" aria-haspopup="dialog"
                               aria-label="Lihat {{ $documentLabel }}"
                               x-on:click.prevent="$dispatch('sidang-ta-preview', $el.dataset)">
                                Lihat berkas
                            </a>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach

        <section data-review-actions class="campus-card space-y-4 p-5 sm:p-6"
                 x-data="{ sending: false }" @pageshow.window="sending = false">
            <div>
                <h2 class="text-base font-bold text-slate-800">
                    Sudah memeriksa semua data?
                </h2>
                <p class="mt-1 text-sm leading-relaxed text-slate-600">
                    Pastikan periode, kontak, pembayaran, biodata, dan setiap berkas sudah benar. Anda masih dapat
                    kembali ke form untuk memperbaikinya.
                </p>
            </div>
            @if($sendBlocked)
                <p id="registration-send-readiness" data-meet-send-held @if($revisionExpired) data-revision-deadline-expired @endif role="{{ $revisionExpired ? 'alert' : 'status' }}" class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900">
                    {{ $revisionExpired ? 'Batas waktu revisi telah berakhir. Pengiriman revisi sudah ditutup. Data dan berkas tetap tersimpan untuk dilihat.'
                        : ($isRevision ? 'Penuhi persyaratan akademik sebelum mengirim revisi. Data dan berkas tetap tersimpan.'
                            : 'Penuhi persyaratan akademik dan EPRT sebelum mengirim. Draft dan berkas tetap tersimpan.') }}
                </p>
            @endif
            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-wrap gap-2">
                    <a class="btn btn-secondary"
                       href="{{ route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 1]) }}">
                        Kembali ke form
                    </a>
                    <a class="btn btn-secondary" href="{{ route('sidang-ta-registrations.index') }}">
                        Kembali ke pendaftaran
                    </a>
                </div>
                @can($permission)
                    <button data-open-registration type="button"
                            class="btn btn-primary disabled:cursor-not-allowed disabled:opacity-50 disabled:shadow-none"
                            aria-disabled="{{ $sendBlocked ? 'true' : 'false' }}"
                            :aria-disabled="sending || {{ $sendBlocked ? 'true' : 'false' }} ? 'true' : 'false'"
                            @if($sendBlocked) aria-describedby="registration-send-readiness" @endif
                            @disabled($sendBlocked) :disabled="sending || {{ $sendBlocked ? 'true' : 'false' }}"
                            aria-haspopup="dialog" aria-controls="confirm-send-registration"
                            onclick="document.getElementById('confirm-send-registration').showModal()">Kirim Pendaftaran
                    </button>

                @endcan
            </div>
            @can($permission)
                <dialog id="confirm-send-registration" aria-labelledby="confirm-send-title"
                        aria-describedby="confirm-send-description"
                        @cancel="if (sending) $event.preventDefault()"
                        class="m-auto w-11/12 max-w-lg rounded-2xl border border-slate-200 bg-white p-5 text-slate-800 shadow-xl backdrop:bg-slate-900/50 sm:p-6">
                    <h2 id="confirm-send-title" class="text-lg font-bold">
                        {{ $isRevision ? 'Kirim revisi pendaftaran?' : 'Kirim pendaftaran sekarang?' }}
                    </h2>
                    <div id="confirm-send-description" class="mt-3 space-y-3 text-sm leading-relaxed text-slate-600">
                        <p>Periksa kembali data dan seluruh dokumen. Setelah dikirim, pengajuan akan ditinjau oleh
                            petugas.</p>
                        <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-amber-900">
                            <p class="font-semibold">Pastikan kembali:</p>
                            <ul class="mt-2 list-inside list-disc space-y-1">
                                <li>Periode Sidang TA dan nomor kontak sudah benar.</li>
                                <li>Tanggal pembayaran wisuda serta pilihan biodata sudah sesuai.</li>
                                <li>Seluruh berkas lengkap, benar, dan dapat dibaca.</li>
                            </ul>
                        </div>
                        <p>Jika masih ragu, pilih Periksa kembali. Jika sudah yakin, lanjutkan pengiriman. Perubahan
                            setelah pengiriman mengikuti proses revisi yang tersedia.</p>
                    </div>
                    <form data-final-registration-form method="POST"
                          class="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"
                          action="{{ $isRevision ? route('sidang-ta-registrations.update', $draft->source_registration_id) : route('sidang-ta-registrations.store') }}"
                          @submit="if (sending || {{ $sendBlocked ? 'true' : 'false' }}) { $event.preventDefault(); } else { sending = true; }">
                        @csrf
                        @if($isRevision)
                            @method('PUT')
                        @endif
                        <input type="hidden" name="draft_id" value="{{ $draft->id }}">
                        <input type="hidden" name="version" value="{{ $draft->version }}">
                        <input type="hidden" name="action" value="submit">
                        <button data-cancel-registration type="button" autofocus class="btn btn-secondary"
                                :disabled="sending"
                                onclick="this.closest('dialog').close()">Periksa kembali
                        </button>
                        <button data-confirm-registration type="submit"
                                class="btn btn-primary disabled:cursor-not-allowed disabled:opacity-50 disabled:shadow-none"
                                aria-disabled="{{ $sendBlocked ? 'true' : 'false' }}"
                                :aria-disabled="sending || {{ $sendBlocked ? 'true' : 'false' }} ? 'true' : 'false'"
                                @if($sendBlocked) aria-describedby="registration-send-readiness" @endif
                                @disabled($sendBlocked) :disabled="sending || {{ $sendBlocked ? 'true' : 'false' }}"
                                x-text="sending ? 'Sedang mengirim…' : '{{ $confirmLabel }}'">{{ $confirmLabel }}
                        </button>
                    </form>
                </dialog>
            @endcan
        </section>
    </div>
    @include('sidang_ta_registrations.partials.document-preview-modal')
</x-app-layout>
```

### 5.5 `resources/views/sidang_ta_registrations/revision-start.blade.php`

Ganti keseluruhan isi.

```blade
<x-app-layout>
    <x-slot name="header">
        Revisi Pendaftaran Sidang TA
    </x-slot>
    @php
        $revisionExpired = \App\Support\SidangTaRevisionDeadline::hasExpired($registration);
    @endphp
    <section class="campus-card space-y-4 p-5">
        <h2 class="font-bold">
            Perbaiki pengajuan Anda
        </h2>
        <p>
            {{ $registration->review_notes ?: 'Periksa kembali data dan dokumen pengajuan.' }}
        </p>
        <p>
            Pengajuan yang sudah dikirim tetap tersimpan selama Anda mengerjakan draft revisi
        </p>
        @include('sidang_ta_registrations.partials.revision-feedback')
        @if($revisionExpired)
            <p data-revision-deadline-expired role="alert" class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900">
                Batas waktu revisi telah berakhir. Memulai dan melanjutkan pengerjaan revisi sudah ditutup.
                Draft yang sudah tersimpan tetap tersedia di daftar pendaftaran untuk dilihat atau dihapus.
            </p>
        @endif
        <form method="POST" action="{{ route('sidang-ta-drafts.revision', $registration) }}">
            @csrf
            <button type="submit" @disabled($revisionExpired)
                    class="btn btn-primary disabled:cursor-not-allowed disabled:opacity-50">
                Mulai atau lanjutkan draft revisi
            </button>
        </form>
        <a href="{{ route('sidang-ta-registrations.index') }}" class="btn btn-secondary">
            Kembali
        </a>
    </section>
</x-app-layout>
```

Sesudah expiry mengubah pengajuan menjadi rejected, tautan lanjutkan draft stale tidak ditampilkan oleh index yang sudah ada. Dialog Hapus draft tetap tersedia. Lihat berkas pengajuan melalui detail final; expiry tidak menghapusnya. Preview draft yang sudah stale tetap ditolak oleh guard sumber yang ada, bukan membuka akses baru untuk dokumen lama.

## 6. Daftarkan jadwal khusus TA

- [ ] Pada **bagian paling bawah** `routes/console.php`, sesudah jadwal antrean yang sudah ada, tambahkan satu baris berikut. Jangan menimpa isi file dan jangan menambahkan baris yang sama dua kali.

```php
Schedule::command('sidang-ta:expire-revisions')->everyMinute()->withoutOverlapping(10);
```

Import `Illuminate\Support\Facades\Schedule` sudah ada. Tidak ada closure reset antrean yang perlu diubah. Scheduler memakai cache lock 10 menit untuk mencegah task bertumpuk; transaksi dan pemeriksaan ulang tetap menjadi perlindungan utama jika command dijalankan langsung bersamaan. [Dokumentasi scheduler Laravel13](https://github.com/laravel/docs/blob/13.x/scheduling.md).

## 7. Verifikasi otomatis

- [ ] Bersihkan kompilasi view setelah mengganti Blade:

```bash
php artisan view:clear
```

- [ ] Test baru, target 23:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaRevisionDeadlineTest.php
```

- [ ] Fokus integrasi, target 109 jika baseline 59 sebelumnya 86:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaRevisionDeadlineTest.php tests/Feature/SidangTaTargetedRevisionTest.php tests/Feature/SidangTaRevisionContractTest.php tests/Feature/SidangTaMeetStaffReviewTest.php tests/Feature/SidangTaMeetFinalizationTest.php tests/Feature/SidangTaRequirementsVersionTest.php
```

- [ ] Seluruh Sidang TA, target 372 jika baseline sebelumnya 349:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTa*Test.php tests/Unit/SidangTaAdmissionPolicyTest.php tests/Unit/SidangTaMeetRequirementsTest.php
```

Test lama tidak perlu dihapus atau diganti. Test versi 1 yang menyimpan revisi sesudah periode tutup memang tetap lulus. Gunakan `dadu_testing`, bukan database aplikasi `dadu`, untuk test RefreshDatabase.

- [ ] Regresi JS yang sudah ada, total 13:

```bash
node tests/Js/sidang-ta-entry.test.mjs
node tests/Js/sidang-ta-wizard.test.mjs
node tests/Js/sidang-ta-document-preview.test.mjs
```

## 8. Manual test menggunakan pengajuan versi 2 dummy002

Unit test memiliki fixture sendiri; tidak memerlukan mengubah data lokal dengan Tinker. Manual memakai pengajuan versi 2 dummy002 yang ditahan submitted pada 59. Krisna mode Petugas untuk review, `dummy-ta-2@mahasiswa.test` mode Mahasiswa untuk pemiliknya; gunakan profil browser terpisah agar cookie login tidak tertukar. Password dummy mengikuti 53 (`password`).

### 8.1 Cek waktu dan pengajuan tanpa mengubah data

```bash
php artisan tinker --execute='dump(["timezone" => config("app.timezone"), "now" => now()->format("Y-m-d H:i:s P"), "database" => DB::connection()->getDatabaseName()]);'
php artisan tinker --execute='dump(App\Models\SidangTaRegistration::where("student_number", "1202299002")->latest("id")->first()?->only(["id", "requirements_version", "status", "sidang_ta_period_id", "lock_version"]));'
```

Pastikan database aplikasi `dadu`, versi 2, dan pengajuan yang tepat. Jam input periode mengikuti timezone aplikasi; jangan menyamakan jam UTC dengan WIB tanpa konversi. JSON resmi yang masih menunggu konfirmasi tidak perlu diimport untuk 60.

### 8.2 Revisi sebelum tenggat tetap berjalan

- [ ] Pastikan close_at periode masih di masa depan. Petugas tandai satu dokumen untuk diperbaiki dengan catatan; dokumen wajib lainnya harus sudah verified. Klik Minta Revisi dengan catatan umum.
- [ ] Mahasiswa buka revisi: hanya berkas yang diminta diperbaiki dapat diganti. Upload, Simpan draft, Tinjau, konfirmasi kirim.
- [ ] Pengajuan kembali submitted dengan ID yang sama. Berkas pengganti pending, dokumen lain tetap verified. Scheduler belum boleh menolak submitted, sekalipun kemudian periode ditutup.

### 8.3 Tenggat menutup kontrol sebelum command dijalankan

- [ ] Pada pengajuan yang sama, petugas minta perbaikan dua dokumen tahap 2; pastikan lainnya verified, lalu Minta Revisi.
- [ ] Mahasiswa upload satu pengganti dan Simpan: tersimpan parsial, tahapan belum lengkap. Upload pengganti kedua, Simpan, lalu Tinjau. Jangan kirim dulu.
- [ ] Melalui Kelola Periode di role Petugas, ubah close_at menjadi waktu yang sudah lewat **tetapi masih setelah open_at**. Ini memang memengaruhi semua pengajuan di periode tersebut; gunakan periode lokal pengujian. Simpan dengan timezone aplikasi yang benar.
- [ ] Sebelum menjalankan command, muat ulang wizard tahap 2 dan4: tampil peringatan tenggat, input upload tidak tersedia, Simpan serta Tinjau tidakaktif. Preview berkas tetap bekerja; tahap lain masih dapat dibaca.
- [ ] Muat ulang halaman tinjau yang tadi sudah berhasil dibuka: alasan deadline muncul, kedua tombol kirim disabled. Bukan pesan kekurangan akademik. Server juga memblokir request lama yang dikirim tanpa reload; test otomatis mencakupnya.
- [ ] Pada detail student/start revisi, tombol Mulai atau lanjutkan pengerjaan tidakaktif dan ada penjelasan. Pada daftar draft sebelum expiry, Lanjutkan draft masih membuka tampilan baca saja; Hapus draft tetap melalui dialog.

### 8.4 Jalankan expiry dan ulangi

```bash
php artisan sidang-ta:expire-revisions
php artisan sidang-ta:expire-revisions
```

Command memproses seluruh versi 2 needs_review yang overdue pada database aplikasi. Output pertama `Pengajuan revisi ditolak otomatis: N.` sesuai jumlah yang memenuhi syarat; kedua 0 jika tidak ada kandidat baru. Periksa:

- [ ] Pengajuan dummy002 menjadi Ditolak, catatan menyebut otomatis karena tenggat. `lock_version` naik satu dan riwayat petugas bertambah satu log, bukan dua. Riwayat tetap collapsed pada petugas dan tidak tampil untuk mahasiswa.
- [ ] File final dan metadata dokumen tetap tersedia. Pengganti milik draft juga tidak dihapus oleh command; unit test memeriksa kedua kelompok path tersebut.
- [ ] Daftar mahasiswa menampilkan draft stale tanpa Lanjutkan dan masih menyediakan Hapus draft. Batal pada dialog tidak menghapus apa pun. Konfirmasi Hapus hanya membersihkan file milik draft; final pengajuan tetap dapat dipreview.
- [ ] Memperpanjang kembali close_at tidak mengubah pengajuan rejected menjadi needs_review. Jangan mereset status melalui database. Dummy002 boleh berakhir rejected setelah pengujian60 ini.
- [ ] Sebelum meminta revisi lagi pada pengajuan submitted lain yang close_at-nya sudah lewat, tombol Minta Revisi di petugas harus disabled dengan alasan deadline. Setujui/Tolak tetap tunduk kelengkapan dokumen yang sudah ada.

### 8.5 Scheduler untuk penggunaan lokal berulang

```bash
php artisan schedule:list
```

Daftar harus memuat `sidang-ta:expire-revisions` setiap menit tepat satu kali. Untuk menjalankan otomatis, Laravel menyediakan:

```bash
php artisan schedule:work
```

Biarkan terminal itu berjalan. **Ini menjalankan seluruh jadwal aplikasi**, termasuk arsip/reset antrean yang sudah ada pada 08:00 Asia/Jakarta; kode existing memindahkan lalu menghapus `queue_tickets`. Untuk manual test expiry terarah, gunakan command khusus pada 8.4; `schedule:list` saja tidak menjalankan task. [Menjalankan scheduler lokal](https://github.com/laravel/docs/blob/13.x/scheduling.md#running-the-scheduler-locally).

## 9. Salin 14 file sesudah semua test dan manual lulus

Empat file baru: policy, service expiry, command, test. Sepuluh file lain diperbarui. Tidak ada file yang dihapus. Script berikut **menyalin**, bukan menghapus file asal; seluruh sumber diperiksa sebelum copy pertama dan setiap hasil dibandingkan sesudahnya. Tidak menyalin `.env`, `phpunit.xml`, cache, atau JSON.

```bash
bash <<'BASH'
set -euo pipefail
source_dir='/home/xdranel/Code/Project/php-laravel/dadu'
target_dir='/home/xdranel/Code/Project/secret/dadu-test'
files=(
    'app/Support/SidangTaRevisionDeadline.php'
    'app/Services/SidangTaRevisionExpiry.php'
    'app/Console/Commands/ExpireSidangTaRevisions.php'
    'app/Services/SidangTaDraftAccess.php'
    'app/Services/SidangTaRevisionDraftService.php'
    'app/Services/SidangTaRegistrationReview.php'
    'app/Http/Controllers/SidangTaRegistrationController.php'
    'app/Http/Controllers/SidangTaDraftController.php'
    'resources/views/sidang_ta_registrations/wizard.blade.php'
    'resources/views/sidang_ta_registrations/partials/wizard-upload.blade.php'
    'resources/views/sidang_ta_registrations/wizard-review.blade.php'
    'resources/views/sidang_ta_registrations/revision-start.blade.php'
    'routes/console.php'
    'tests/Feature/SidangTaRevisionDeadlineTest.php'
)
[[ -d "$source_dir" && -d "$target_dir" ]] || { echo 'Folder sumber/tujuan tidak ditemukan.' >&2; exit 1; }
for file in "${files[@]}"; do
    [[ -f "$source_dir/$file" ]] || { echo "File sumber belum ada: $file" >&2; exit 1; }
done
for file in "${files[@]}"; do
    mkdir -p -- "$target_dir/$(dirname -- "$file")"
    cp -- "$source_dir/$file" "$target_dir/$file"
done
for file in "${files[@]}"; do
    cmp -s -- "$source_dir/$file" "$target_dir/$file" || { echo "Hasil salin berbeda: $file" >&2; exit 1; }
done
printf '%s file tersalin dan identik.\n' "${#files[@]}"
BASH
```

## 10. Rescan panduan dan batas hasil verifikasi

Asisten memeriksa kode aktual sebelum menyusun perubahan, lalu proposal policy/scheduler dengan 12 pemeriksaan runtime tanpa DB/bootstrap. Blade dirender dengan stub helper/storage/context: 164 assertion wizard/review dan 22 regresi panel/beranda lulus; empat Blade dikompilasi dan di-lint. Review terpisah memeriksa urutan lock, lokasi guard, replay consumed, deadline inklusif, dan service expiry. Test Feature lengkap disertakan, tetapi **belum dijalankan asisten** karena memakai database/cache aplikasi; hasil23/109/372 tetap harus dibuktikan di lingkungan Anda.

Hash source kedua folder diperiksa sebelum dan sesudah setiap perubahan dokumen; kode aplikasi tidak diubah asisten. Setelah semua hasil lulus dan 14 file disalin, laporkan hasil untuk rescan. Tahap berikutnya aktivasi default versi 2 untuk pendaftaran baru dan penanganan draft awal versi 1 agar tidak melewati aturan meet, kemudian audit penutup serta commit message. Fitur meet belum dinyatakan selesai pada 60.
