<?php

use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\Student;
use App\Models\User;
use App\Support\SidangTaReviewEligibility as Eligibility;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Symfony\Component\DomCrawler\Crawler;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->travelTo(now()->startOfSecond());
    Storage::fake('local');
    foreach (['sidang_ta_registration_review', 'sidang_ta_registration_read', 'sidang_ta_registration_create',
                 'sidang_ta_registration_update', 'sidang_ta_period_read'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->reviewer = User::factory()->create(['username' => 'p3-feedback-reviewer', 'email' => 'p3-feedback-reviewer@test.local',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
    $this->reviewer->givePermissionTo('sidang_ta_registration_review');
    $this->actingAs($this->reviewer);
    $this->owner = User::factory()->create(['username' => '1202200001', 'email' => 'owner-feedback@test.local',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
    $this->owner->givePermissionTo(['sidang_ta_registration_read', 'sidang_ta_registration_update', 'sidang_ta_registration_create']);
    Student::create(['student_number' => '1202200001', 'name' => 'Owner Feedback', 'email' => $this->owner->email,
        'academic_year' => 2022, 'study_program' => 'Informatika', 'status' => 'Aktif', 'gpa' => 0]);

    $this->period = SidangTaPeriod::create(['name' => 'P3', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay()]);
    $this->sequence = 0;
    $this->makeSource = function (array $overrides = [], string $documentStatus = 'pending') {
        $this->sequence++;
        $source = SidangTaRegistration::create(array_replace([
            'sidang_ta_period_id' => $this->period->id, 'student_number' => $this->owner->username,
            'student_name' => 'Student P3', 'study_program' => 'Informatika',
            'student_phone' => '081234567890', 'parent_phone' => '081234567891',
            'graduation_payment_date' => today()->toDateString(), 'diploma_biodata_accurate' => true,
            'status' => 'submitted', 'submitted_at' => now(),
        ], $overrides))->fresh();
        foreach (Eligibility::requiredTypes($source) as $type) {
            $path = "sidang_ta/p3/{$source->id}/{$type}.pdf";
            Storage::disk('local')->put($path, '%PDF-1.4 p3');
            $source->documents()->create(['document_type' => $type, 'file_name' => $type.'.pdf',
                'file_path' => $path, 'file_size' => 11, 'file_type' => 'pdf', 'status' => $documentStatus]);
        }
        return $source;
    };
    $this->reviewDoc = function ($source, $document, array $overrides = []) {
        return $this->postJson(route('admin.sidang-ta-registrations.documents.review', [
            'sidang_ta_registration' => $source, 'document' => $document,
        ]), array_replace(['registration_version' => $source->fresh()->lock_version, 'status' => 'verified'], $overrides));
    };
});

afterEach(function () { $this->travelBack(); });


test('p3_student_detail_shows_escaped_feedback_without_history', function () {
    $source = ($this->makeSource)();
    $document = $source->documents()->firstOrFail();
    ($this->reviewDoc)($source, $document, ['status' => 'rejected', 'notes' => '<b>Perbaiki scan</b>'])->assertOk();
    $listing = $this->get(route('admin.sidang-ta-registrations.index'))->assertOk();
    expect($listing->viewData('counts')['needs_review'])->toBe(1);
    $crawler = new Crawler($listing->getContent());
    expect($crawler->filter('[data-ta-row="'.$source->id.'"] [data-ta-doc="'.$document->document_type.'"]')->attr('data-doc-state'))->toBe('rejected');
    $response = $this->actingAs($this->owner)->get(route('sidang-ta-registrations.show', $source))->assertOk();
    $crawler = new Crawler($response->getContent());
    $status = $crawler->filter('[data-student-document-state="'.$document->id.'"]');
    expect($status->text(null, true))->toContain('Perlu perbaikan')->toContain('<b>Perbaiki scan</b>');
    expect($status->filter('b')->count())->toBe(0);
    expect($crawler->filter('[data-registration-history]')->count())->toBe(0);
    expect($source->logs()->where('action', 'document_rejected')->exists())->toBeTrue();
    expect($crawler->filter('[data-doc-review-form]')->count())->toBe(0);
});


test('p3_revision_start_shows_source_document_feedback_without_general_note', function () {
    $source = ($this->makeSource)(['status' => 'needs_review'], 'verified');
    $document = $source->documents()
        ->where('document_type', 'level_2_result_capture')->firstOrFail();
    $document->update(['status' => 'rejected', 'notes' => 'Perbaiki PDF']);
    $crawler = new Crawler($this->actingAs($this->owner)->get(route('sidang-ta-registrations.edit', $source))->assertOk()->getContent());
    expect($crawler->filter('[data-review-feedback]')->count())->toBe(1);
    expect($crawler->filter('[data-feedback-type="'.$document->document_type.'"]')->text(null, true))
        ->toContain('Perbaiki PDF')->toContain('Tahap 2');
    expect($source->fresh()->review_notes)->toBeNull();
});

test('p3_revision_wizard_keeps_source_feedback_after_new_draft_file_selection_is_saved', function () {
    $source = ($this->makeSource)(['status' => 'needs_review'], 'verified');
    $document = $source->documents()
        ->where('document_type', 'level_2_result_capture')->firstOrFail();
    $document->update(['status' => 'rejected', 'notes' => 'Catatan dokumen asal']);
    $id = $this->actingAs($this->owner)->postJson(route('sidang-ta-drafts.revision', $source))->assertCreated()->json('data.id');
    $url = route('sidang-ta-drafts.edit', ['draft' => $id, 'step' => 2]);
    $this->get($url)->assertOk()->assertSee('Catatan dokumen asal');
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $id, 'step' => 2]), [
        'version' => 1, 'documents' => [$document->document_type => UploadedFile::fake()->create('baru.pdf', 100, 'application/pdf')],
    ])->assertOk()->assertJsonPath('data.version', 2);
    $saved = \App\Models\SidangTaRegistrationDraftDocument::query()
        ->where('draft_id', $id)->where('document_type', $document->document_type)->firstOrFail();
    expect($saved->file_name)->toBe('baru.pdf');
    expect($saved->source_document_id)->toBeNull();
    expect($saved->file_path)->not->toBe($document->file_path);
    Storage::disk('local')->assertExists($saved->file_path);
    $crawler = new Crawler($this->get($url)->assertOk()->getContent());
    expect($crawler->filter('[data-review-feedback]')->text(null, true))->toContain('Catatan dokumen asal')
        ->toContain('Berkas yang diganti akan ditinjau kembali');
    expect($document->fresh()->notes)->toBe('Catatan dokumen asal');
    Storage::disk('local')->assertExists($document->file_path);
});

test('p3_resubmission_resets_all_review_statuses_and_notes_to_pending', function () {
    $source = ($this->makeSource)(['status' => 'needs_review'], 'verified');
    $document = $source->documents()->firstOrFail();
    $document->update(['status' => 'rejected', 'notes' => 'Catatan sebelum revisi']);
    $id = $this->actingAs($this->owner)->postJson(route('sidang-ta-drafts.revision', $source))->assertCreated()->json('data.id');
    $this->postJson(route('sidang-ta-drafts.review', $id), ['version' => 1])->assertOk();
    $this->putJson(route('sidang-ta-registrations.update', $source), [
        'draft_id' => $id, 'version' => 1, 'action' => 'submit',
    ])->assertOk()->assertJsonPath('data.status', 'submitted');
    expect($source->documents()->where('status', '!=', 'pending')->exists())->toBeFalse();
    expect($source->documents()->whereNotNull('notes')->exists())->toBeFalse();
    $crawler = new Crawler($this->get(route('sidang-ta-registrations.show', $source))->assertOk()->getContent());
    expect($crawler->filter('[data-student-document-state="'.$document->id.'"]')->text(null, true))->toContain('Menunggu verifikasi')
        ->not->toContain('Catatan sebelum revisi');
});

test('p3_admin_controls_follow_eligibility_and_terminal_status', function () {
    $source = ($this->makeSource)();
    $source->documents()->create(['document_type' => 'pdm_submission_proof', 'file_name' => 'old.pdf',
        'file_path' => 'not-active-branch.pdf', 'file_type' => 'pdf', 'status' => 'pending']);
    $url = route('admin.sidang-ta-registrations.show', $source);
    $crawler = new Crawler($this->get($url)->assertOk()->getContent());
    expect($crawler->filter('[data-doc-review-form]')->count())->toBe(15);
    expect($crawler->filter('[data-ta-approve]')->getNode(0)->hasAttribute('disabled'))->toBeTrue();
    expect($crawler->filter('[data-ta-approval-blockers]')->count())->toBe(1);
    $source->documents()->whereIn('document_type', Eligibility::requiredTypes($source))->update(['status' => 'verified']);
    $crawler = new Crawler($this->get($url)->assertOk()->getContent());
    expect($crawler->filter('[data-ta-approve]')->getNode(0)->hasAttribute('disabled'))->toBeFalse();
    $source->update(['status' => 'approved']);
    $crawler = new Crawler($this->get($url)->assertOk()->getContent());
    expect($crawler->filter('[data-doc-review-form]')->count())->toBe(0);
    expect($crawler->filter('[data-ta-decision]')->count())->toBe(0);
});

test('p3_validation_keeps_only_failed_form_old_version_without_rebasing_other_forms', function () {
    $source = ($this->makeSource)();
    $documents = $source->documents()->get();
    $document = $documents->first();
    $other = $documents->get(1);
    $url = route('admin.sidang-ta-registrations.show', $source);
    $this->from($url)->post(route('admin.sidang-ta-registrations.documents.review', [
        'sidang_ta_registration' => $source, 'document' => $document,
    ]), ['registration_version' => 1, 'status' => 'rejected', 'notes' => ['invalid'],
        '_review_form' => 'document:'.$document->id])->assertRedirect($url.'#review-document-'.$document->id)->assertSessionHasErrors('notes');
    $source->lock_version++;
    $source->save();
    $crawler = new Crawler($this->get($url)->assertOk()->getContent());
    expect($crawler->filter('[data-doc-review-form="'.$document->id.'"] input[name="registration_version"]')->attr('value'))->toBe('1');
    expect($crawler->filter('[data-doc-review-form="'.$other->id.'"] input[name="registration_version"]')->attr('value'))->toBe('2');
    expect($crawler->filter('[data-ta-decision] input[name="registration_version"]')->attr('value'))->toBe('2');
    ($this->reviewDoc)($source, $document, ['registration_version' => 1])->assertStatus(409);
    expect($source->fresh()->lock_version)->toBe(2);
});

test('p3_html_document_review_returns_to_matching_document_card', function () {
    foreach (['verified', 'rejected'] as $status) {
        $source = ($this->makeSource)();
        $document = $source->documents()->firstOrFail();
        $url = route('admin.sidang-ta-registrations.show', $source);
        $response = $this->post(route('admin.sidang-ta-registrations.documents.review', [
            'sidang_ta_registration' => $source, 'document' => $document,
        ]), ['registration_version' => 1, 'status' => $status,
            'notes' => $status === 'rejected' ? 'Perbaiki berkas ini' : null,
            '_review_form' => 'document:'.$document->id]);
        $response->assertRedirect($url.'#review-document-'.$document->id)->assertSessionHasNoErrors();
        expect($document->fresh()->status)->toBe($status);
        $crawler = new Crawler($this->get($url)->assertOk()->getContent());
        expect($crawler->filter('article#review-document-'.$document->id)->count())->toBe(1);
        expect($crawler->filter('article#review-document-'.$document->id.' [data-doc-review-form="'.$document->id.'"]')->count())->toBe(1);
    }
});

test('p3_html_document_validation_and_stale_version_return_to_matching_card', function () {
    $source = ($this->makeSource)();
    $document = $source->documents()->firstOrFail();
    $url = route('admin.sidang-ta-registrations.show', $source);
    $action = route('admin.sidang-ta-registrations.documents.review', [
        'sidang_ta_registration' => $source, 'document' => $document,
    ]);
    $this->from($url)->post($action, ['registration_version' => 1, 'status' => 'rejected',
        'notes' => '', '_review_form' => 'document:'.$document->id])
        ->assertRedirect($url.'#review-document-'.$document->id)->assertSessionHasErrors('notes');
    expect($document->fresh()->status)->toBe('pending');
    expect($source->fresh()->lock_version)->toBe(1);
    ($this->reviewDoc)($source, $document)->assertOk()->assertJsonPath('data.lock_version', 2);
    $this->post($action, ['registration_version' => 1, 'status' => 'rejected',
        'notes' => 'Catatan dari halaman lama', '_review_form' => 'document:'.$document->id])
        ->assertRedirect($url.'#review-document-'.$document->id)
        ->assertSessionHasErrors('registration_version');
    expect($document->fresh()->status)->toBe('verified');
    expect($source->fresh()->lock_version)->toBe(2);
    $crawler = new Crawler($this->get($url)->assertOk()->getContent());
    expect($crawler->filter('[data-doc-review-form="'.$document->id.'"] input[name="registration_version"]')->attr('value'))->toBe('1');
});

test('meet_staff_history_is_collapsed_localized_and_escaped', function () {
    $source = ($this->makeSource)();
    $source->logAction('document_rejected', 'submitted', 'submitted', '<b>Catatan audit internal</b>', $this->reviewer->id);
    $crawler = new Crawler($this->get(route('admin.sidang-ta-registrations.show', $source))->assertOk()->getContent());
    $history = $crawler->filter('details[data-staff-registration-history]');
    expect($history->count())->toBe(1);
    expect($history->getNode(0)->hasAttribute('open'))->toBeFalse();
    expect($history->filter('summary')->count())->toBe(1);
    expect($history->filter('summary svg')->count())->toBe(1);
    expect($history->text(null, true))->toContain('Riwayat status')
        ->toContain('Dokumen diminta perbaikan')->toContain('<b>Catatan audit internal</b>');
    expect($history->filter('b')->count())->toBe(0);
    expect($source->logs()->count())->toBe(1);
});

test('meet_staff_empty_history_is_collapsed_with_fallback', function () {
    $source = ($this->makeSource)();
    $crawler = new Crawler($this->get(route('admin.sidang-ta-registrations.show', $source))->assertOk()->getContent());
    $history = $crawler->filter('details[data-staff-registration-history]');
    expect($history->count())->toBe(1);
    expect($history->getNode(0)->hasAttribute('open'))->toBeFalse();
    expect($history->text(null, true))->toContain('Belum ada riwayat status.');
});
