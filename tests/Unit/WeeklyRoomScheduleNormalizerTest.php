<?php

use App\Support\WeeklyRoomScheduleNormalizer;

function weekly111Payload(array $rowChanges = [], array $changes = []): array
{
    $row = array_replace(['id_jadwal' => 'x1', 'periode' => '2627/1', 'hari' => 'SENIN', 'tanggal' => '09-11-2026', 'ruangan' => 'A', 'jam_mulai' => '08:30:00', 'jam_selesai' => '10:30:00', 'jenis_jadwal' => 'TETAP', 'kode_dosen' => 'DUM', 'nama_mk' => 'Mata Kuliah Dummy'], $rowChanges);

    return array_replace(['source' => 'iGracias', 'total' => 1, 'selected_menus' => ['jadwal_perkuliahan'], 'filter_jadwal_perkuliahan' => ['schoolYear' => '2627/1', 'studyProgramId' => '', 'roomId' => '', 'isConflict' => ''], 'data' => [$row]], $changes);
}
function weekly111Context(): array
{
    return ['id' => 1, 'year' => '2026/2027', 'semester' => 1, 'start_date' => '2026-09-01', 'end_date' => '2027-01-31'];
}
it('normalizes an exact registered semester and keeps late evening source times', function () {
    $n = new WeeklyRoomScheduleNormalizer;
    $r = $n->normalize(weekly111Payload(['jam_mulai' => '19:30:00', 'jam_selesai' => '23:30:00']), weekly111Context());
    expect($r['period_id'])->toBe(1)->and($r['patterns'][0])->toMatchArray(['weekday' => 1, 'start_time' => '19:30:00', 'end_time' => '23:30:00', 'source_room_label' => 'A']);
});
it('deduplicates the same weekly pattern exported in different reference weeks', function () {
    $p = weekly111Payload();
    $row = $p['data'][0];
    $row['tanggal'] = '16-11-2026';
    $p['data'][] = $row;
    $p['total'] = 2;
    expect((new WeeklyRoomScheduleNormalizer)->normalize($p, weekly111Context())['patterns'])->toHaveCount(1);
});
it('rejects incomplete ambiguous or conflicting source contracts', function (string $case) {
    $p = weekly111Payload();
    match ($case) {
        'weekday' => $p['data'][0]['hari'] = 'SELASA',
        'type' => $p['data'][0]['jenis_jadwal'] = 'TIDAK DIKENAL',
        'period' => $p['data'][0]['periode'] = '2627/2',
        'count' => $p['total'] = 2,
        'filter' => $p['filter_jadwal_perkuliahan']['roomId'] = 'A',
        'invalid_date' => $p['data'][0]['tanggal'] = '31-11-2026',
        'time' => $p['data'][0]['jam_selesai'] = '07:00:00',
        'reverse_seconds' => [$p['data'][0]['jam_mulai'] = '08:30:59', $p['data'][0]['jam_selesai'] = '08:30:01'],
    };
    expect(fn () => (new WeeklyRoomScheduleNormalizer)->normalize($p, weekly111Context()))->toThrow(InvalidArgumentException::class);
})->with(['weekday', 'type', 'period', 'count', 'filter', 'invalid_date', 'time', 'reverse_seconds']);
