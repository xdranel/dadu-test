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
    \App\Models\Student::create(['student_number' => $this->source->student_number, 'name' => 'Contract', 'academic_year' => 2022, 'study_program' => 'Informatika', 'status' => 'Aktif']);
    foreach (range(1, 3) as $attempt) {
        \App\Models\StudentEprt::create(['student_number' => $this->source->student_number,
            'total_score' => 440, 'attempt_number' => $attempt, 'test_date' => today()->subDays($attempt)]);
    }
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

test('revision_contract_resolves_live_document_groups_and_keeps_unrelated_verified_files_locked', function () {
    expect(Contract::isTargeted($this->draft))->toBeTrue();
    expect(Contract::typesForStep($this->draft, 2))->toBe(['ta_data_capture', 'online_supervision_capture', 'ta_supervisor_sk', 'thesis_draft']);
    expect(Contract::typesForStep($this->draft, 3))->toContain('eprt_waiver_letter')->toHaveCount(2);
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
    foreach (['graduation_payment_date' => today()->subDay()->toDateString(), 'diploma_biodata_accurate' => false] as $key => $value) {
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

test('revision_contract_unsupported_marker_is_rejected_before_targeted_classification', function (int $marker) {
    $this->draft->update(['requirements_version' => $marker]);
    $before = $this->draft->fresh()->getAttributes();
    $paths = Storage::disk('local')->allFiles();
    try {
        Contract::isTargeted($this->draft);
        $this->fail('Unsupported requirements must fail closed.');
    } catch (Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception) {
        expect($exception->getStatusCode())->toBe(409);
        expect($exception->getMessage())->toBe('Persyaratan pengajuan tidak sesuai. Muat ulang atau hubungi petugas.');
    }
    expect($this->draft->fresh()->getAttributes())->toBe($before);
    expect(Storage::disk('local')->allFiles())->toBe($paths);
})->with([1, 3]);
