<?php

use App\Support\SidangTaLanguageHistory as Language;
use App\Support\SidangTaAdmissionPolicy as Policy;

uses(Tests\TestCase::class);
beforeEach(fn () => $this->travelTo(\Carbon\Carbon::parse('2026-11-06 12:00:00', config('app.timezone'))));
afterEach(fn () => $this->travelBack());

function language77(array $history = [], mixed $score = 440, ?string $type = 'Online EPrT', ?int $attempts = 3): array
{
    return Language::facts(['score' => $score, 'type' => $type, 'attempts' => $attempts, 'history' => $history]);
}

test('language77_uses_inclusive_450_threshold_and_source_attempt_count', function ($score, $mode) {
    $facts = language77([], $score);
    expect(Policy::eprt($facts['score'], $facts['confirmed_attempts'])['mode'])->toBe($mode);
    expect($facts['confirmed_attempts'])->toBe(3);
})->with([[449, 'waiver'], [450, 'automatic'], [451, 'automatic']]);

test('language77_selects_highest_valid_main_score_on_the_same_scale', function () {
    $facts = language77([
        ['jenis_tes' => 'EPrT', 'nilai' => '470', 'kedaluwarsa' => '19 Mei 2028', 'status_berlaku' => 'Berlaku'],
        ['jenis_tes' => 'Online EPrT', 'nilai' => '510', 'kedaluwarsa' => '5 November 2026', 'status_berlaku' => 'Kedaluwarsa'],
        ['jenis_tes' => 'Online EPrT', 'nilai' => '900', 'category' => 'listening', 'status_berlaku' => 'Berlaku'],
        ['jenis_tes' => 'ITP TOEFL', 'nilai' => '650', 'status_berlaku' => 'Berlaku'],
    ]);
    expect($facts['score'])->toBe(470.0);
    expect($facts['history'][0])->toMatchArray(['type' => 'EPrT', 'score' => 470.0,
        'test_date' => null, 'expires_at' => '2028-05-19', 'validity' => 'valid']);
    expect($facts['history'][1]['validity'])->toBe('expired');
    expect($facts['confirmed_attempts'])->toBe(3);
});

test('language77_explicit_history_validity_replaces_stale_summary', function ($history, $score) {
    expect(language77($history, 510)['score'])->toBe($score);
})->with([
    [[['jenis_tes' => 'EPrT', 'nilai' => 420, 'status_berlaku' => 'Berlaku'],
      ['jenis_tes' => 'EPrT', 'nilai' => 510, 'status_berlaku' => 'Kedaluwarsa']], 420.0],
    [[['jenis_tes' => 'EPrT', 'nilai' => 510, 'kedaluwarsa' => '2026-11-05']], null],
]);

test('language77_keeps_legacy_summary_when_matching_history_has_no_validity_information', function () {
    $facts = language77([['jenis_tes' => 'EPrT', 'nilai' => 600]], 440);
    expect($facts['score'])->toBe(440.0);
    expect($facts['history'][0]['validity'])->toBe('unknown');
});

test('language77_rejects_future_invalid_and_conflicting_dates_as_passing_candidates', function ($row) {
    $facts = language77([array_replace(['jenis_tes' => 'EPrT', 'nilai' => 600], $row)], 440);
    expect($facts['score'])->not->toBe(600.0);
    expect(Policy::eprt($facts['score'], $facts['confirmed_attempts'])['mode'])->toBe('waiver');
})->with([
    [['tanggal_tes' => '2026-11-07', 'status_berlaku' => 'Berlaku']],
    [['kedaluwarsa' => '31 Februari 2027', 'status_berlaku' => 'Berlaku']],
    [['kedaluwarsa' => '2026-11-05', 'status_berlaku' => 'Berlaku']],
    [['kedaluwarsa' => '2028-05-19', 'status_berlaku' => 'Kedaluwarsa']],
    [['status_berlaku' => 'unrecognized']],
]);

test('language77_expiry_remains_valid_until_end_of_local_day_without_inventing_test_date', function () {
    $facts = language77([['jenis_tes' => 'EPrT', 'nilai' => 470, 'kedaluwarsa' => '6 November 2026']]);
    expect($facts['score'])->toBe(470.0);
    expect($facts['history'][0]['test_date'])->toBeNull();
    $this->travelTo(\Carbon\Carbon::parse('2026-11-07 00:00:00', config('app.timezone')));
    expect(language77([['jenis_tes' => 'EPrT', 'nilai' => 470, 'kedaluwarsa' => '6 November 2026']])['score'])->toBeNull();
});

test('language77_never_converts_unrelated_scales_or_ecct_null_scores', function ($type, $score, $historyType, $historyScore) {
    $facts = language77([['jenis_tes' => $historyType, 'nilai' => $historyScore, 'status_berlaku' => 'Berlaku']], $score, $type);
    expect($facts['score'])->toBe($score === null ? null : (float) $score);
    expect($facts['type'])->toBe($type);
})->with([
    ['Online ECCT', null, 'EPrT', 600], ['Online ECCT', null, 'Online ECCT', 600],
    ['IELTS', 6.5, 'IELTS', 9], ['IELTS', 6.5, 'EPrT', 600],
    ['ITP TOEFL', 440, 'iBT TOEFL', 110], ['iBT TOEFL', 100, 'ITP TOEFL', 600],
]);


test('language77_past_test_date_alone_does_not_prove_validity_or_discard_compatible_summary', function () {
    $facts = language77([['jenis_tes' => 'EPrT', 'nilai' => 600, 'tanggal_tes' => '2026-11-01']], 440);
    expect($facts['score'])->toBe(440.0);
    expect($facts['history'][0]['validity'])->toBe('unknown');
});


test('language77_unknown_official_scales_preserve_source_facts_but_are_not_identifiable', function ($type, $attempts) {
    $row = ['jenis_tes' => 'EPrT', 'nilai' => 470, 'status_berlaku' => 'Berlaku'];
    $facts = language77([$row], 600, $type, $attempts);
    expect($facts['scale_identified'])->toBeFalse();
    expect($facts['score'])->toBe(600.0);
    expect($facts['confirmed_attempts'])->toBe($attempts);
    expect($facts['type'])->toBe($type);
    expect($facts['history'][0]['source'])->toBe($row);
})->with([[null, null], ['', null], ['Unknown scale', null],
    [null, 3], ['   ', 3], ['Unknown scale', 3]]);

test('language77_supported_official_types_are_identifiable_without_changing_aggregate_facts', function ($type) {
    $facts = language77([], 600, $type, 3);
    expect($facts['scale_identified'])->toBeTrue();
    expect($facts['score'])->toBe(600.0);
    expect($facts['confirmed_attempts'])->toBe(3);
    expect($facts['type'])->toBe($type);
})->with(['EPrT', 'Online EPrT', 'ECCT', 'Online ECCT', 'TOEIC', 'IELTS',
    'ITP TOEFL', 'TOEFL ITP', 'iBT TOEFL', 'TOEFL iBT', " \tonLine   ePRt  ", '  online   ecct ', '  toefl   ITP  ']);
