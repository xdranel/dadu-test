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
