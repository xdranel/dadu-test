<?php

use App\Support\JsonSourceLocations;
use App\Support\SyncImportIssues;

test('sync88_source_locations_track_physical_lines_for_each_supported_root', function () {
    foreach ([
        ["[\n  {\"nim\":\"1\"},\n  {\"nim\":\"2\"}\n]", '$[0]', 2, '$[1]', 3],
        ["{\n \"data\": [\n  {\"nim\":\"1\"},\n  {\"nim\":\"2\"}\n ]\n}", '$.data[0]', 3, '$.data[1]', 4],
        ["{\n \"aaData\": [\n  {\"nim\":\"1\"},\n  {\"nim\":\"2\"}\n ]\n}", '$.aaData[0]', 3, '$.aaData[1]', 4],
    ] as [$source, $firstPath, $firstLine, $secondPath, $secondLine]) {
        expect(JsonSourceLocations::rows($source))->toBe([
            ['path' => $firstPath, 'line' => $firstLine],
            ['path' => $secondPath, 'line' => $secondLine],
        ]);
    }
    expect(JsonSourceLocations::rows("{\n \"nim\": \"1\"\n}"))->toBe([['path' => '$', 'line' => 1]]);
    expect(JsonSourceLocations::rows('{"data":[]}'))->toBe([]);
});

test('sync88_source_locations_ignore_braces_quotes_and_escaped_newlines_inside_strings', function () {
    $source = "{\n  \"data\": [\n    ".json_encode(['nim' => '1', 'nama' => "A } ] { \" \\ \nB"]).",\n    null,\n    {\"nim\": \"2\"}\n  ]\n}";
    expect(JsonSourceLocations::rows($source))->toBe([
        ['path' => '$.data[0]', 'line' => 3],
        ['path' => '$.data[1]', 'line' => 4],
        ['path' => '$.data[2]', 'line' => 5],
    ]);
});

test('sync88_issue_identity_uses_record_index_without_claiming_an_ide_line', function () {
    $details = SyncImportIssues::fromErrors(['Baris #0: Mahasiswa belum terdaftar.', 'Baris #1: NIM atau nama tidak valid.'],
        [['nim' => '1202200001', 'nama' => 'Nama'], null]);
    expect($details[0])->toMatchArray(['nim' => '1202200001', 'name' => 'Nama', 'record' => 1, 'path' => '$[0]', 'line' => null]);
    expect($details[1])->toMatchArray(['nim' => null, 'name' => null, 'record' => 2, 'path' => '$[1]', 'line' => null]);
});


test('sync88_source_menu_failures_are_visible_even_when_failed_groups_are_absent', function () {
    $rows = [['nim' => '1', 'nama' => 'Nama', 'total_tak' => 0, 'error' => 'bahasa: token=private-secret']];
    $errors = SyncImportIssues::withSourceFailures([], $rows);
    expect($errors)->toHaveCount(1);
    expect($errors[0])->toBe('Baris #0: Pengambilan data menu bahasa gagal.');
    expect(implode(' ', $errors))->not->toContain('private-secret');
    expect(SyncImportIssues::withSourceFailures($errors, $rows))->toBe($errors);
    expect(SyncImportIssues::withSourceFailures([], [['nim' => '1', 'total_tak' => 0]]))->toBe([]);
});

test('sync88_ambiguous_legacy_nim_keeps_identity_without_inventing_a_source_position', function () {
    $details = SyncImportIssues::fromErrors(['Baris NIM 1: Error — SQLSTATE private-binding'],
        [['nim' => '1', 'nama' => 'A'], ['nim' => '1', 'nama' => 'B']]);
    expect($details[0])->toMatchArray(['nim' => '1', 'name' => null, 'record' => null, 'path' => null, 'line' => null]);
    expect($details[0]['message'])->not->toContain('SQLSTATE');
});

test('sync88_large_note_sets_keep_all_structured_details_while_legacy_text_stays_within_capacity', function () {
    $errors = array_fill(0, 200, 'Baris #0: '.str_repeat('Catatan panjang. ', 30));
    expect(SyncImportIssues::fromErrors($errors, [['nim' => '1', 'nama' => 'A']]))->toHaveCount(200);
    expect(strlen(SyncImportIssues::summary($errors)))->toBeLessThan(65535);
});


test('sync88_source_locations_support_crlf_and_single_wrapped_objects', function () {
    expect(JsonSourceLocations::rows("{\r\n \"data\": {\r\n \"nim\": \"1\"\r\n }\r\n}"))->toBe([['path' => '$.data', 'line' => 2]]);
    expect(JsonSourceLocations::rows("[\r\n {\"nim\":\"1\"},\r\n {\"nim\":\"2\"}\r\n]"))->toBe([['path' => '$[0]', 'line' => 2], ['path' => '$[1]', 'line' => 3]]);
});


test('sync88_identity_supports_import_aliases_and_only_interprets_known_flat_krs_rows', function () {
    $student = SyncImportIssues::fromErrors(['Baris #0: gagal'], [['nim' => '1', 'nama_siswa' => 'Nama Siswa']], [], 'student');
    expect($student[0]['name'])->toBe('Nama Siswa');
    $unknown = SyncImportIssues::fromErrors(['Baris #0: gagal'], [['CODE', 'Course']]);
    expect($unknown[0])->toMatchArray(['nim' => null, 'name' => null]);
    $krs = SyncImportIssues::fromErrors(['Baris #0: gagal'], [['1', 'Mahasiswa']], [], 'krs');
    expect($krs[0])->toMatchArray(['nim' => '1', 'name' => 'Mahasiswa']);
});


test('sync88_scalar_data_field_on_single_record_is_not_a_wrapper', function () {
    $source = '{"nim":"1","data":"unrelated metadata"}';
    expect(\App\Support\JsonSourceLocations::rows($source))->toBe([['path'=>'$', 'line'=>1]]);
});
