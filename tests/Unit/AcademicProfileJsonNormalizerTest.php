<?php

use App\Support\AcademicProfileJsonNormalizer as Normalizer;

function academicJson65Language(array $overrides = []): array
{
    return array_replace([
        'nilai_bahasa' => '430', 'jenis_tes_bahasa' => 'Online EPrT',
        'jumlah_tes_bahasa' => 3,
        'riwayat_nilai_bahasa' => [
            ['jenis_tes' => 'Online EPrT', 'nilai' => '430',
                'kedaluwarsa' => '19 Mei 2028', 'status_berlaku' => 'Berlaku'],
        ],
    ], $overrides);
}

test('json65_missing_fields_mean_no_updates_instead_of_null_assignments', function () {
    expect((new Normalizer)->normalize(['nim' => '1202265001', 'nama' => 'Contoh Sintetis']))
        ->toBe(['updates' => [], 'skipped' => []]);
});

test('json65_skpi_uses_only_exact_status_skpi_and_ignores_three_flags', function () {
    $normalizer = new Normalizer;
    $approved = $normalizer->normalize(['status_skpi' => 'Disetujui',
        'skpi_status_1' => 'N', 'skpi_status_2' => 'N', 'skpi_status_3' => 'N']);
    expect($approved['updates']['skpi'])->toBe(['status' => 'Disetujui', 'approved' => true]);
    foreach (['Belum Disetujui', 'APPROVED', 'disetujui', null] as $status) {
        $result = $normalizer->normalize(['status_skpi' => $status,
            'skpi_status_1' => 'Y', 'skpi_status_2' => 'Y', 'skpi_status_3' => 'Y']);
        expect($result['updates']['skpi']['approved'])->toBeFalse();
    }
    expect($normalizer->normalize(['skpi_status_1' => 'Y'])['updates'])->toBe([]);
});

test('json65_sidang_approval_uses_explicit_summary_instead_of_numeric_status_ta', function () {
    $normalizer = new Normalizer;
    foreach (['1', '4'] as $raw) {
        $result = $normalizer->normalize(['status_ta' => $raw, 'persetujuan_maju_sidang' => 'APPROVED']);
        expect($result['updates']['sidang_approval']['approved'])->toBeTrue();
    }
    foreach (['BELUM DISETUJUI', null] as $status) {
        $result = $normalizer->normalize(['status_ta' => 'APPROVED', 'persetujuan_maju_sidang' => $status]);
        expect($result['updates']['sidang_approval']['approved'])->toBeFalse();
    }
    expect($normalizer->normalize(['status_ta' => 'APPROVED'])['updates'])->toBe([]);
});

test('json65_selected_menus_prevent_untouched_groups_from_being_overwritten', function () {
    $row = array_replace(academicJson65Language(), ['status_skpi' => null, 'total_tak' => null]);
    $result = (new Normalizer)->normalize($row, ['bahasa']);
    expect(array_keys($result['updates']))->toBe(['language']);
    expect((new Normalizer)->normalize($row, [])['updates'])->toBe([]);
});

test('json65_null_selected_menus_allow_present_fields_but_not_missing_ones', function () {
    $result = (new Normalizer)->normalize(['total_tak' => '80'], null);
    expect($result['updates'])->toBe(['tak' => ['score' => 80.0]]);
});

test('json65_rejects_malformed_selected_menus', function () {
    foreach ([['tak' => true], [true], [null]] as $menus) {
        expect(fn () => (new Normalizer)->normalize(['total_tak' => '80'], $menus))
            ->toThrow(InvalidArgumentException::class);
    }
});

test('json65_language_uses_authoritative_score_and_count_without_recalculating_history', function () {
    $row = academicJson65Language(['nilai_bahasa' => '440', 'jumlah_tes_bahasa' => 3,
        'riwayat_nilai_bahasa' => [
            ['jenis_tes' => 'Online EPrT', 'nilai' => '600', 'status_berlaku' => 'Kedaluwarsa'],
        ]]);
    $language = (new Normalizer)->normalize($row)['updates']['language'];
    expect($language['score'])->toBe(440.0);
    expect($language['attempts'])->toBe(3);
    expect($language['history'])->toBe($row['riwayat_nilai_bahasa']);
});

test('json65_language_accepts_ecct_null_score_and_official_three_test_count', function () {
    $row = academicJson65Language(['nilai_bahasa' => null, 'jenis_tes_bahasa' => 'Online ECCT',
        'riwayat_nilai_bahasa' => []]);
    $language = (new Normalizer)->normalize($row)['updates']['language'];
    expect($language['score'])->toBeNull();
    expect($language['type'])->toBe('Online ECCT');
    expect($language['attempts'])->toBe(3);
});

test('json65_language_does_not_restrict_accepted_types_to_eprt', function () {
    foreach (['Online EPrT', 'ITP TOEFL', 'Online ECCT'] as $type) {
        $result = (new Normalizer)->normalize(academicJson65Language(['jenis_tes_bahasa' => $type]));
        expect($result['updates']['language']['type'])->toBe($type);
    }
});

test('json65_language_preserves_zero_and_distinguishes_missing_count', function () {
    $result = (new Normalizer)->normalize(academicJson65Language(['nilai_bahasa' => '0', 'jumlah_tes_bahasa' => '0']));
    expect($result['updates']['language']['score'])->toBe(0.0);
    expect($result['updates']['language']['attempts'])->toBe(0);
    $result = (new Normalizer)->normalize(academicJson65Language(['jumlah_tes_bahasa' => null]));
    expect($result['updates']['language']['attempts'])->toBeNull();
});

test('json65_language_is_atomic_when_one_summary_field_is_missing', function () {
    $row = academicJson65Language();
    unset($row['jumlah_tes_bahasa']);
    $result = (new Normalizer)->normalize($row);
    expect($result['updates'])->not->toHaveKey('language');
    expect($result['skipped'])->toHaveKey('language');
});

test('json65_language_rejects_invalid_counts_without_coercing_them', function () {
    foreach ([true, false, -1, 2.5, '2.5', [], 'tiga', INF] as $count) {
        $result = (new Normalizer)->normalize(academicJson65Language(['jumlah_tes_bahasa' => $count]));
        expect($result['updates'])->not->toHaveKey('language');
        expect($result['skipped'])->toHaveKey('language');
    }
});

test('json65_invalid_score_does_not_silently_become_zero_or_null', function () {
    foreach ([true, false, -1, 'invalid', [], INF, NAN] as $score) {
        $result = (new Normalizer)->normalize(academicJson65Language(['nilai_bahasa' => $score]));
        expect($result['updates'])->not->toHaveKey('language');
        expect($result['skipped'])->toHaveKey('language');
    }
});

test('json65_history_keeps_source_dates_and_ecct_values_without_treating_expiry_as_test_date', function () {
    $history = [['jenis_tes' => 'Online ECCT', 'nilai' => '3,5',
        'kedaluwarsa' => '19 Mei 2028', 'status_berlaku' => 'Berlaku']];
    $result = (new Normalizer)->normalize(academicJson65Language(['riwayat_nilai_bahasa' => $history]));
    expect($result['updates']['language']['history'])->toBe($history);
    expect($result['updates']['language']['history'][0])->not->toHaveKey('test_date');
});

test('json65_language_rejects_invalid_history_shape', function () {
    foreach ([null, 'invalid', ['nilai' => '430'], [430], [[]]] as $history) {
        $result = (new Normalizer)->normalize(academicJson65Language(['riwayat_nilai_bahasa' => $history]));
        expect($result['updates'])->not->toHaveKey('language');
        expect($result['skipped'])->toHaveKey('language');
    }
});

test('json65_tak_null_zero_and_confirmed_total_remain_distinct', function () {
    foreach ([[null, null], ['0', 0.0], ['60', 60.0], ['80', 80.0]] as [$raw, $expected]) {
        $result = (new Normalizer)->normalize(['total_tak' => $raw]);
        expect($result['updates']['tak']['score'])->toBe($expected);
    }
});

test('json65_tak_error_preserves_tak_and_keeps_other_successful_groups', function () {
    $row = ['total_tak' => null, 'status_skpi' => 'Disetujui',
        'error' => 'tak: Token gateway TAK sudah kedaluwarsa.'];
    $result = (new Normalizer)->normalize($row);
    expect($result['updates'])->not->toHaveKey('tak');
    expect($result['skipped'])->toHaveKey('tak');
    expect($result['updates']['skpi']['approved'])->toBeTrue();
});

test('json65_multiple_tagged_errors_skip_only_failed_groups', function () {
    $row = array_replace(academicJson65Language(), ['total_tak' => null, 'status_skpi' => null,
        'error' => "tak: gagal; skpi: gagal"]);
    $result = (new Normalizer)->normalize($row);
    expect(array_keys($result['updates']))->toBe(['language']);
    expect(array_keys($result['skipped']))->toBe(['skpi', 'tak']);
});

test('json65_unclassified_error_preserves_all_recognized_groups', function () {
    foreach (['Pengambilan data gagal.', ['tak' => 'gagal']] as $error) {
        $result = (new Normalizer)->normalize(['total_tak' => null, 'status_skpi' => null, 'error' => $error]);
        expect($result['updates'])->toBe([]);
        expect($result['skipped'])->toHaveKeys(['skpi', 'tak']);
    }
});

test('json65_bpp_maps_real_credit_status_and_does_not_invent_a_period_from_study_semester', function () {
    $bpp = (new Normalizer)->normalize(['status_keuangan' => 'PERLU VERIFIKASI',
        'status_keuangan_asli' => 'KREDIT', 'semester' => '7'])['updates']['bpp'];
    expect($bpp['status'])->toBe('KREDIT');
    expect($bpp['source_status'])->toBe('PERLU VERIFIKASI');
    expect($bpp['source_semester'])->toBe('7');
    expect($bpp['period'])->toBeNull();
});

test('json65_bpp_accepts_explicit_confirmed_import_period', function () {
    $bpp = (new Normalizer)->normalize(['status_keuangan' => 'LUNAS',
        'status_keuangan_asli' => 'SUDAH LUNAS', 'semester' => '9'], ['keuangan'], '2627/1')['updates']['bpp'];
    expect($bpp['status'])->toBe('LUNAS');
    expect($bpp['period'])->toBe('2627/1');
    expect($bpp['source_semester'])->toBe('9');
});

test('json65_bpp_can_read_actual_period_code_if_it_is_present_in_source', function () {
    $bpp = (new Normalizer)->normalize(['status_keuangan' => 'LUNAS', 'semester' => '2627/1'])['updates']['bpp'];
    expect($bpp['period'])->toBe('2627/1');
});

test('json65_bpp_rejects_invalid_or_conflicting_period_without_losing_skpi_update', function () {
    foreach (['7', '2026/2027', '2627/3', '2627/2'] as $period) {
        $result = (new Normalizer)->normalize(['status_keuangan' => 'LUNAS', 'semester' => '2627/1',
            'status_skpi' => 'Disetujui'], null, $period);
        expect($result['updates'])->not->toHaveKey('bpp');
        expect($result['skipped'])->toHaveKey('bpp');
        expect($result['updates']['skpi']['approved'])->toBeTrue();
    }
});

test('json65_does_not_mutate_source_input_or_leak_ignored_identifiers_into_updates', function () {
    $row = array_replace(academicJson65Language(), ['nim' => '1202265001', 'nama' => 'Contoh Sintetis']);
    $copy = $row;
    $result = (new Normalizer)->normalize($row);
    expect($row)->toBe($copy);
    expect($result['updates'])->not->toHaveKey('nim')->not->toHaveKey('nama');
});

test('json65_result_from_previous_call_is_not_kept_for_another_student', function () {
    $normalizer = new Normalizer;
    expect($normalizer->normalize(['status_skpi' => 'Disetujui'])['updates']['skpi']['approved'])->toBeTrue();
    expect($normalizer->normalize([]))->toBe(['updates' => [], 'skipped' => []]);
});

test('json73_bpp_reads_official_period_separately_from_the_study_semester', function () {
    $bpp = (new Normalizer)->normalize(['status_keuangan' => 'KREDIT',
        'periode_keuangan' => '2627/1', 'semester' => '13'], ['keuangan'])['updates']['bpp'];
    expect($bpp['period'])->toBe('2627/1');
    expect($bpp['source_semester'])->toBe('13');
    expect($bpp['status'])->toBe('KREDIT');
});

test('json73_bpp_reads_and_trims_official_even_term_without_a_study_semester', function () {
    $bpp = (new Normalizer)->normalize(['status_keuangan' => 'LUNAS',
        'periode_keuangan' => ' 2627/2 '], ['keuangan'])['updates']['bpp'];
    expect($bpp['period'])->toBe('2627/2');
    expect($bpp['source_semester'])->toBeNull();
});

test('json73_malformed_explicit_period_skips_bpp_without_losing_successful_skpi', function () {
    foreach ([null, '', ' ', '7', '2026/2027', '2627/3', '2627/0', 26271, true, []] as $period) {
        $result = (new Normalizer)->normalize(['status_keuangan' => 'LUNAS',
            'periode_keuangan' => $period, 'status_skpi' => 'Disetujui']);
        expect($result['updates'])->not->toHaveKey('bpp');
        expect($result['skipped'])->toHaveKey('bpp');
        expect($result['updates']['skpi']['approved'])->toBeTrue();
    }
});

test('json73_official_period_must_agree_with_confirmed_wrapper_context', function () {
    $row = ['status_keuangan' => 'LUNAS', 'periode_keuangan' => '2627/1', 'semester' => '7'];
    expect((new Normalizer)->normalize($row, ['keuangan'], '2627/1')['updates']['bpp']['period'])->toBe('2627/1');
    $result = (new Normalizer)->normalize($row, ['keuangan'], '2627/2');
    expect($result['updates'])->not->toHaveKey('bpp');
    expect($result['skipped'])->toHaveKey('bpp');
});

test('json73_official_period_cannot_conflict_with_a_legacy_semester_period', function () {
    $row = ['status_keuangan' => 'LUNAS', 'periode_keuangan' => '2627/1', 'semester' => '2627/2'];
    expect((new Normalizer)->normalize($row)['skipped'])->toHaveKey('bpp');
    $row['semester'] = '2627/1';
    expect((new Normalizer)->normalize($row, null, '2627/1')['updates']['bpp']['period'])->toBe('2627/1');
});

test('json73_keuangan_not_selected_or_source_error_preserves_the_bpp_group', function () {
    $row = ['status_keuangan' => 'LUNAS', 'periode_keuangan' => '2627/1', 'status_skpi' => 'Disetujui'];
    expect((new Normalizer)->normalize($row, ['skpi'])['updates'])->not->toHaveKey('bpp');
    $row['error'] = 'keuangan: gateway gagal';
    $result = (new Normalizer)->normalize($row);
    expect($result['skipped'])->toHaveKey('bpp');
    expect($result['updates']['skpi']['approved'])->toBeTrue();
});

test('json73_rows_can_have_different_confirmed_periods_without_shared_state', function () {
    $normalizer = new Normalizer;
    foreach (['2627/1', '2627/2', '2728/1'] as $period) {
        $result = $normalizer->normalize(['status_keuangan' => 'LUNAS', 'periode_keuangan' => $period]);
        expect($result['updates']['bpp']['period'])->toBe($period);
    }
});

test('json73_old_exports_remain_supported_without_inventing_a_finance_period', function () {
    $normalizer = new Normalizer;
    $row = ['status_keuangan' => 'LUNAS', 'semester' => 7];
    expect($normalizer->normalize($row)['updates']['bpp']['period'])->toBeNull();
    expect($normalizer->normalize($row, null, '2627/1')['updates']['bpp']['period'])->toBe('2627/1');
    $row['semester'] = '2627/2';
    expect($normalizer->normalize($row)['updates']['bpp']['period'])->toBe('2627/2');
});
