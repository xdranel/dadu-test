<?php

use App\Support\SidangTaAdmissionPolicy as Policy;

function meetAdmissionFacts(): array
{
    return ['level_2_passed' => true, 'ta2_registered' => true, 'skpi_approved' => true,
        'gpa' => 2, 'tak_score' => 60, 'passed_credits' => 120];
}

test('meet_policy_accepts_exact_six_academic_boundaries', function () {
    $result = Policy::academic(meetAdmissionFacts());
    expect($result['academic_passed'])->toBeTrue();
    expect($result['blockers'])->toBe([]);
    expect(count($result['gates']))->toBe(6);
    foreach ($result['gates'] as $gate) expect($gate['state'])->toBe('passed');
});

test('meet_policy_each_single_failure_blocks_academic_eligibility', function () {
    foreach (['level_2_passed' => false, 'ta2_registered' => false, 'skpi_approved' => false,
                 'gpa' => 1.99, 'tak_score' => 59.99, 'passed_credits' => 119] as $key => $value) {
        $result = Policy::academic(array_replace(meetAdmissionFacts(), [$key => $value]));
        expect($result['academic_passed'])->toBeFalse();
        expect(array_keys($result['blockers']))->toBe([$key]);
        expect($result['gates'][$key]['state'])->toBe('failed');
    }
});

test('meet_policy_missing_or_unconfirmed_facts_never_pass', function () {
    foreach (array_keys(meetAdmissionFacts()) as $key) {
        foreach ([null, '', [], 'unconfirmed'] as $value) {
            $result = Policy::academic(array_replace(meetAdmissionFacts(), [$key => $value]));
            expect($result['gates'][$key]['state'])->toBe('unknown');
            expect($result['academic_passed'])->toBeFalse();
            expect(array_keys($result['blockers']))->toBe([$key]);
        }
        $facts = meetAdmissionFacts();
        unset($facts[$key]);
        expect(Policy::academic($facts)['gates'][$key]['state'])->toBe('unknown');
    }
    expect(count(Policy::academic([])['blockers']))->toBe(6);
});

test('meet_policy_requires_normalized_booleans_instead_of_truthy_source_strings', function () {
    foreach (['level_2_passed', 'ta2_registered', 'skpi_approved'] as $key) {
        foreach (['N', 'Y', 'false', 'true', 'approved', 'rejected', 0, 1] as $raw) {
            $result = Policy::academic(array_replace(meetAdmissionFacts(), [$key => $raw]));
            expect($result['gates'][$key]['state'])->toBe('unknown');
        }
    }
});

test('meet_policy_numeric_strings_zero_and_invalid_values_are_distinguished', function () {
    $result = Policy::academic(array_replace(meetAdmissionFacts(), ['gpa' => '2.00', 'tak_score' => '60.00', 'passed_credits' => '120']));
    expect($result['academic_passed'])->toBeTrue();
    foreach (['gpa', 'tak_score', 'passed_credits'] as $key) {
        expect(Policy::academic(array_replace(meetAdmissionFacts(), [$key => 0]))['gates'][$key]['state'])->toBe('failed');
        foreach ([true, false, -1, 'invalid', INF, NAN] as $invalid) {
            expect(Policy::academic(array_replace(meetAdmissionFacts(), [$key => $invalid]))['gates'][$key]['state'])->toBe('unknown');
        }
    }
});

test('meet_policy_eprt_450_or_higher_is_automatic_without_upload_or_attempt_requirement', function () {
    foreach ([450, 500, '450'] as $score) {
        $result = Policy::eprt($score, null);
        expect($result['mode'])->toBe('automatic');
        expect($result['requires_waiver'])->toBeFalse();
    }
});

test('meet_policy_eprt_below_450_after_three_confirmed_tests_requires_waiver', function () {
    foreach ([[449, 3], [400, 4], ['449', '3'], [0, 3]] as [$score, $attempts]) {
        $result = Policy::eprt($score, $attempts);
        expect($result['mode'])->toBe('waiver');
        expect($result['requires_waiver'])->toBeTrue();
    }
});

test('meet_policy_eprt_below_450_before_three_tests_requires_retest', function () {
    foreach ([0, 1, 2] as $attempts) {
        $result = Policy::eprt(449, $attempts);
        expect($result['mode'])->toBe('retest');
        expect($result['requires_waiver'])->toBeFalse();
        expect($result['message'])->toBe('Ikuti Test EPRT lagi');
    }
});

test('meet_policy_eprt_unknown_data_never_activates_waiver_or_automatic_approval', function () {
    foreach ([[null, null], ['invalid', 3], [449, null], [449, 2.5], [true, 3], [-1, 3], [449, -1], [NAN, 3], [449, INF], [[], 3]] as [$score, $attempts]) {
        $result = Policy::eprt($score, $attempts);
        expect($result['mode'])->toBe('unknown');
        expect($result['requires_waiver'])->toBeFalse();
    }
});

test('meet_policy_eprt_is_not_a_seventh_academic_gate_and_waiver_is_not_final_approval', function () {
    $facts = array_replace(meetAdmissionFacts(), ['eprt_score' => 0, 'eprt_attempts' => 0]);
    $academic = Policy::academic($facts);
    expect($academic['academic_passed'])->toBeTrue();
    expect(count($academic['gates']))->toBe(6);
    expect(Policy::eprt(0, 0)['mode'])->toBe('retest');
    expect(Policy::eprt(449, 3)['mode'])->toBe('waiver');
    expect(Policy::eprt(449, 3))->not->toHaveKey('can_submit');
});



test('meet_policy_null_language_score_uses_official_count_without_inventing_zero', function () {
    expect(Policy::eprt(null, 3)['mode'])->toBe('waiver');
    expect(Policy::eprt(null, 3)['requires_waiver'])->toBeTrue();
    expect(Policy::eprt(null, 4)['mode'])->toBe('waiver');
    foreach ([0, 1, 2] as $count) {
        expect(Policy::eprt(null, $count)['mode'])->toBe('retest');
    }
    expect(Policy::eprt(null, null)['mode'])->toBe('unknown');
    expect(Policy::eprt('invalid', 3)['mode'])->toBe('unknown');
});

test('meet_policy_gpa_threshold_distinguishes_values_either_side_of_two', function ($value, $state) {
    $result = Policy::academic(array_replace(meetAdmissionFacts(), ['gpa' => $value]));
    expect($result['gates']['gpa']['state'])->toBe($state);
    expect($result['academic_passed'])->toBe($state === 'passed');
    expect(array_keys($result['blockers']))->toBe($state === 'passed' ? [] : ['gpa']);
})->with([[1.99, 'failed'], [2, 'passed'], [2.01, 'passed']]);
