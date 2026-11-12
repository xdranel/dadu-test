<?php

use App\Support\SidangTaAdmissionPolicy as Policy;
use App\Support\SidangTaMeetRequirements as Requirements;

// No database or Laravel application bootstrap is needed for this contract.

test('meet_requirements_stage_one_has_no_upload', function () {
    expect(Requirements::typesForStep(1, null, 'unknown'))->toBe([]);
});

test('meet_requirements_stage_two_requires_four_ta_pdfs', function () {
    foreach (['automatic', 'waiver', 'retest', 'unknown'] as $mode) {
        expect(Requirements::typesForStep(2, null, $mode))->toBe([
            'ta_data_capture', 'online_supervision_capture', 'ta_supervisor_sk', 'thesis_draft',
        ]);
    }
});

test('meet_requirements_stage_three_has_one_base_pdf_and_no_certificate_for_automatic_eprt', function () {
    expect(Requirements::typesForStep(3, null, 'automatic'))->toBe([
        'graduation_payment_proof',
    ]);
});

test('meet_requirements_eprt_waiver_adds_exactly_one_required_pdf', function () {
    $types = Requirements::typesForStep(3, null, Policy::eprt(449, 3)['mode']);
    expect($types)->toBe(['graduation_payment_proof', 'eprt_waiver_letter']);
    expect(Requirements::fileRules('eprt_waiver_letter'))->toBe(['nullable', 'file', 'max:5120', 'mimes:pdf']);
});

test('meet_requirements_unknown_or_retest_does_not_invent_a_waiver_upload', function () {
    foreach ([Policy::eprt(null, null), Policy::eprt(449, 2)] as $eprt) {
        expect(Requirements::typesForStep(3, null, $eprt['mode']))->toBe(Requirements::PAYMENT);
        expect($eprt['requires_waiver'])->toBeFalse();
        expect($eprt['mode'])->not->toBe('automatic');
    }
});

test('meet_requirements_stage_four_keeps_both_biodata_branches_and_requires_a_scalar_choice', function () {
    expect(Requirements::typesForStep(4, true, 'automatic'))->toBe(['diploma_statement', 'diploma_biodata_form']);
    expect(Requirements::typesForStep(4, false, 'automatic'))->toBe(['pdm_submission_proof']);
    expect(Requirements::typesForStep(4, null, 'automatic'))->toBe([]);
});

test('meet_requirements_counts_are_seven_or_six_plus_one_for_waiver', function () {
    foreach ([[true, 'automatic', 7], [false, 'automatic', 6], [true, 'waiver', 8], [false, 'waiver', 7]] as [$accurate, $mode, $count]) {
        $types = Requirements::allTypes($accurate, $mode);
        expect(count($types))->toBe($count);
        expect(count(array_unique($types)))->toBe($count);
    }
});

test('meet_requirements_removed_captures_and_pdfs_are_not_new_uploads', function () {
    foreach (['level_2_result_capture', 'igracias_approval_capture', 'ta_progress_capture', 'skpi_tak_capture',
                 'bpp_payment_proof', 'eprt_lac_certificate', 'transcript_khs', 'ta_course_ksm'] as $obsolete) {
        expect(Requirements::allTypes(true, 'waiver'))->not->toContain($obsolete);
        expect(fn () => Requirements::fileRules($obsolete))->toThrow(InvalidArgumentException::class);
    }
});

test('meet_requirements_every_upload_has_a_label_and_expected_format', function () {
    $types = array_unique(array_merge(Requirements::allTypes(true, 'waiver'), Requirements::allTypes(false, 'waiver')));
    foreach ($types as $type) {
        expect(Requirements::LABELS)->toHaveKey($type);
        expect(Requirements::LABELS[$type])->not->toBe('');
        $rules = Requirements::fileRules($type);
        expect($rules)->toContain('nullable')->toContain('file')->toContain('max:5120');
        expect(end($rules))->toBe('mimes:pdf');
    }
});

test('meet_requirements_rejects_unknown_steps_modes_and_upload_types', function () {
    foreach ([0, 5, -1] as $step) {
        expect(fn () => Requirements::typesForStep($step, true, 'automatic'))->toThrow(InvalidArgumentException::class);
    }
    foreach (['approved', '', 'WAIVER'] as $mode) {
        expect(fn () => Requirements::typesForStep(2, null, $mode))->toThrow(InvalidArgumentException::class);
    }
    expect(fn () => Requirements::fileRules('custom_upload'))->toThrow(InvalidArgumentException::class);
});

test('meet_requirements_eprt_threshold_switches_required_set_and_does_not_include_payment_date_as_a_file', function () {
    $waiver = Requirements::typesForStep(3, null, Policy::eprt(449, 3)['mode']);
    $automatic = Requirements::typesForStep(3, null, Policy::eprt(450, 3)['mode']);
    expect(array_values(array_diff($waiver, $automatic)))->toBe(['eprt_waiver_letter']);
    expect($waiver)->not->toContain('graduation_payment_date');
    expect($automatic)->not->toContain('graduation_payment_date');
});

