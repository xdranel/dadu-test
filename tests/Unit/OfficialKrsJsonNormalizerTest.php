<?php

use App\Support\OfficialKrsJsonNormalizer as Normalizer;

function krs70Course(array $overrides = []): array
{
    return array_replace(['kode_mk' => 'KRS70TA', 'nama_mk' => 'TUGAS AKHIR',
        'kelas' => 'IF-DEMO-70', 'kode_dosen' => 'D70', 'tahun_ajaran' => '2627',
        'semester' => '1', 'program_studi' => 'Informatika'], $overrides);
}

function krs70Student(array $courses = [], array $overrides = []): array
{
    $courses = $courses === [] ? [krs70Course()] : $courses;
    return array_replace(['no' => 1, 'nim' => '1202270001', 'nama' => 'Mahasiswa KRS 70',
        'jumlah_mk_krs' => count($courses), 'krs_mata_kuliah' => $courses], $overrides);
}

test('krs70_detects_wrapped_bare_and_single_official_rows_without_claiming_legacy_formats', function () {
    foreach ([['selected_menus' => ['krs_mk'], 'data' => []], ['data' => [krs70Student()]],
                 [krs70Student()], krs70Student()] as $payload) {
        expect(Normalizer::supports($payload))->toBeTrue();
    }
    foreach ([['data' => [['nim' => '1202270001', 'kode_matakuliah' => 'OLD']]],
                 [['1202270001', 'Nama']], ['data' => [['status_skpi' => 'Disetujui']]]] as $payload) {
        expect(Normalizer::supports($payload))->toBeFalse();
    }
});

test('krs70_extracts_official_rows_and_checks_wrapper_total_and_menu', function () {
    $row = krs70Student();
    $n = new Normalizer;
    expect($n->rows(['selected_menus' => ['krs_mk'], 'total' => 1, 'data' => [$row]]))->toBe([$row]);
    expect($n->rows([$row]))->toBe([$row]);
    expect($n->rows($row))->toBe([$row]);
    expect($n->rows(['selected_menus' => ['krs_mk'], 'total' => 0, 'data' => []]))->toBe([]);
});

test('krs70_rejects_invalid_wrapper_context_before_any_import', function () {
    foreach ([['total' => 2], ['total' => '1'], ['selected_menus' => ['bahasa']],
                 ['selected_menus' => 'krs_mk'], ['selected_menus' => ['krs_mk' => true]],
                 ['selected_menus' => ['krs_mk', false]], ['data' => 'broken'], ['data' => krs70Student()]] as $patch) {
        expect(fn () => (new Normalizer)->rows(array_replace(
            ['selected_menus' => ['krs_mk'], 'data' => [krs70Student()]], $patch)))
            ->toThrow(InvalidArgumentException::class);
    }
});

test('krs70_rejects_duplicate_student_rows_in_the_same_batch', function () {
    expect(fn () => (new Normalizer)->rows([krs70Student(), krs70Student()]))
        ->toThrow(InvalidArgumentException::class);
});

test('krs70_maps_source_year_and_term_without_creating_a_study_semester_or_credits', function () {
    $r = (new Normalizer)->normalize(krs70Student());
    expect($r['student_number'])->toBe('1202270001');
    expect($r['courses'][0])->toBe(['course_code' => 'KRS70TA', 'course_name' => 'TUGAS AKHIR',
        'course_class' => 'IF-DEMO-70', 'lecturer_code' => 'D70', 'study_program' => 'Informatika',
        'academic_year' => '2026/2027', 'term' => 1]);
    expect($r['courses'][0])->not->toHaveKey('semester')->not->toHaveKey('credits');
});

test('krs70_supports_explicit_even_term_and_other_consecutive_years', function () {
    $course = (new Normalizer)->normalize(krs70Student([krs70Course(['tahun_ajaran' => '2728', 'semester' => '2'])]))['courses'][0];
    expect($course['academic_year'])->toBe('2027/2028');
    expect($course['term'])->toBe(2);
});

test('krs70_preserves_class_names_longer_than_the_old_twenty_character_column', function () {
    $class = 'KELAS-DEMO-PANJANG-12345';
    expect(mb_strlen($class))->toBeGreaterThan(20);
    expect((new Normalizer)->normalize(krs70Student([krs70Course(['kelas' => $class])]))['courses'][0]['course_class'])->toBe($class);
});

test('krs70_trims_text_and_treats_an_empty_lecturer_code_as_unknown', function () {
    $row = krs70Student([krs70Course(['kode_mk' => ' KRS70TA ', 'nama_mk' => ' TUGAS AKHIR ', 'kode_dosen' => ' '])],
        ['nim' => ' 1202270001 ', 'nama' => ' Mahasiswa KRS 70 ']);
    $r = (new Normalizer)->normalize($row);
    expect($r['name'])->toBe('Mahasiswa KRS 70');
    expect($r['courses'][0]['course_code'])->toBe('KRS70TA');
    expect($r['courses'][0]['lecturer_code'])->toBeNull();
});

test('krs70_rejects_invalid_or_nonconsecutive_academic_years', function () {
    foreach (['2628', '26/27', '2026/2027', '', null, 2627, true] as $year) {
        expect(fn () => (new Normalizer)->normalize(krs70Student([krs70Course(['tahun_ajaran' => $year])])))
            ->toThrow(InvalidArgumentException::class);
    }
});

test('krs70_never_reads_a_study_semester_as_the_academic_term', function () {
    foreach (['0', '3', '8', null, 1, true] as $term) {
        expect(fn () => (new Normalizer)->normalize(krs70Student([krs70Course(['semester' => $term])])))
            ->toThrow(InvalidArgumentException::class);
    }
});

test('krs70_rejects_incomplete_or_miscounted_course_lists', function () {
    foreach ([['jumlah_mk_krs' => 2], ['jumlah_mk_krs' => '1'], ['jumlah_mk_krs' => -1],
                 ['krs_mata_kuliah' => null], ['krs_mata_kuliah' => ['course' => krs70Course()]],
                 ['krs_mata_kuliah' => [false]]] as $patch) {
        expect(fn () => (new Normalizer)->normalize(krs70Student(overrides: $patch)))
            ->toThrow(InvalidArgumentException::class);
    }
});

test('krs70_keeps_a_genuine_empty_list_distinct_from_an_invalid_missing_list', function () {
    $row = krs70Student(overrides: ['jumlah_mk_krs' => 0, 'krs_mata_kuliah' => []]);
    expect((new Normalizer)->normalize($row)['courses'])->toBe([]);
    unset($row['krs_mata_kuliah']);
    expect(fn () => (new Normalizer)->normalize($row))->toThrow(InvalidArgumentException::class);
});

test('krs70_rejects_repeated_course_keys_in_one_students_period', function () {
    expect(fn () => (new Normalizer)->normalize(krs70Student([krs70Course(), krs70Course(['kelas' => 'OTHER'])])))
        ->toThrow(InvalidArgumentException::class);
});

test('krs70_requires_every_official_course_field', function () {
    foreach (array_keys(krs70Course()) as $key) {
        $course = krs70Course(); unset($course[$key]);
        expect(fn () => (new Normalizer)->normalize(krs70Student([$course])))
            ->toThrow(InvalidArgumentException::class);
    }
});

test('krs70_rejects_invalid_identifiers_empty_names_and_oversized_fields', function () {
    foreach ([['nim' => true], ['nim' => ''], ['nim' => str_repeat('1', 16)], ['nama' => null], ['nama' => ' ']] as $patch) {
        expect(fn () => (new Normalizer)->normalize(krs70Student(overrides: $patch)))
            ->toThrow(InvalidArgumentException::class);
    }
    foreach (['kode_mk' => 21, 'nama_mk' => 256, 'kelas' => 256, 'kode_dosen' => 21, 'program_studi' => 256] as $field => $length) {
        expect(fn () => (new Normalizer)->normalize(krs70Student([krs70Course([$field => str_repeat('X', $length)])])))
            ->toThrow(InvalidArgumentException::class);
    }
});

test('krs70_source_errors_prevent_a_row_from_overwriting_existing_krs', function () {
    foreach (['krs_mk: sumber gagal', ['error'], false] as $error) {
        expect(fn () => (new Normalizer)->normalize(krs70Student(overrides: ['error' => $error])))
            ->toThrow(InvalidArgumentException::class);
    }
});

test('krs70_does_not_mutate_input_or_guess_from_the_current_date', function () {
    $row = krs70Student([krs70Course(['tahun_ajaran' => '2425'])]);
    $copy = $row;
    expect((new Normalizer)->normalize($row)['courses'][0]['academic_year'])->toBe('2024/2025');
    expect($row)->toBe($copy);
});
