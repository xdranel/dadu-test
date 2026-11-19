<?php

use App\Models\RoomBookingRequest;
use App\Models\StudentFinalProject;
use App\Services\SidangTaExecutionExport;
use App\Services\SidangTaExecutionInfoService;
use App\Services\SidangTaExecutionOutcomeService;
use App\Services\SidangTaPlottingImportService;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Cell\DateTimeCell;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\TaRoomScenario;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-19 09:00:00', 'Asia/Jakarta'));
    $this->domainScenario = TaRoomScenario::make();
});

function executionDomainFile(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'execution-domain-').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->addRow(new Row(array_map(fn ($v) => new StringCell($v, null), ['Peserta TA', '', '', '', '', '', '', '', '', 'Plotting Jadwal'])));
    $writer->addRow(new Row(array_map(fn ($v) => new StringCell($v, null), ['Periode Sidang TA', 'NIM', 'Nama Mahasiswa', 'Program Studi', 'Status Pengajuan', 'Kelompok Keahlian', 'Judul TA', 'Kode Dosen Pembimbing 1', 'Kode Dosen Pembimbing 2', 'Hari', 'Tanggal', 'Jam Mulai', 'Jam Selesai', "Ruang\n(diisi oleh AKD)", 'Kode Penguji 1', 'Kode Penguji 2'])));
    foreach ($rows as $values) {
        $writer->addRow(new Row(array_map(fn ($v) => $v instanceof DateTimeInterface ? new DateTimeCell($v, null) : (is_float($v) || is_int($v) ? new NumericCell($v, null) : new StringCell((string) $v, null)), $values)));
    }
    $writer->close();

    return $path;
}
function executionDomainRow(array $s, array $override = []): array
{
    return array_replace([$s['period']->name, $s['student']->student_number, 'Peserta Excel', 'Informatika', 'Disetujui', 'KK Excel', 'Judul Excel', '001', '002', 'Senin', 46349, 8 / 24, 9.5 / 24, '', '003', '004'], $override);
}
function executionDomainBooked(array $s)
{
    $booking = RoomBookingRequest::create(['user_id' => $s['reviewer']->id, 'room_id' => $s['rooms']['a']->id, 'type' => 'single', 'start_date' => '2026-11-23', 'end_date' => '2026-11-23', 'start_time' => '08:00', 'end_time' => '09:30', 'purpose' => 'Sidang', 'participants' => 1, 'status' => 'approved', 'booking_origin' => 'sidang_ta', 'sidang_ta_registration_id' => $s['registration']->id]);
    $booking->occurrences()->create(['usage_date' => '2026-11-23', 'start_time' => '08:00', 'end_time' => '09:30']);

    return $s['registration']->roomSchedule()->create(['student_number' => $s['student']->student_number, 'state' => 'confirmed', 'was_confirmed' => true, 'room_booking_request_id' => $booking->id]);
}

test('domain plotting serial dates and room preferences never reserve a room', function () {
    $s = $this->domainScenario;
    $path = executionDomainFile([executionDomainRow($s, [13 => 'A'])]);
    try {
        $result = app(SidangTaPlottingImportService::class)->import($s['reviewer'], $s['period'], $path);
    } finally {
        unlink($path);
    }
    expect($result['processed'])->toHaveCount(1)->and($result['errors'])->toBeEmpty();
    $context = $s['registration']->roomSchedule()->firstOrFail();
    expect($context->preferred_date->format('Y-m-d'))->toBe('2026-11-23')->and(substr($context->preferred_start_time, 0, 5))->toBe('08:00')->and($context->preferred_room_id)->toBe($s['rooms']['a']->id)->and($context->room_booking_request_id)->toBeNull();
    expect(app(SidangTaExecutionInfoService::class)->forRegistration($s['registration'])['room'])->toBeNull();
    expect(RoomBookingRequest::count())->toBe(0);
});

test('domain plotting rejects conflicting duplicates invalid rooms wrong periods and unapproved participants', function () {
    $s = $this->domainScenario;
    $path = executionDomainFile([executionDomainRow($s), executionDomainRow($s, [6 => 'Different'])]);
    try {
        $result = app(SidangTaPlottingImportService::class)->import($s['reviewer'], $s['period'], $path);
    } finally {
        unlink($path);
    }
    expect($result['processed'])->toBeEmpty()->and($result['errors'])->toHaveCount(2);
    foreach ([[13 => 'NOT-REGISTERED-128'], [0 => 'Other period'], [1 => '00009999']] as $override) {
        $path = executionDomainFile([executionDomainRow($s, $override)]);
        try {
            $result = app(SidangTaPlottingImportService::class)->import($s['reviewer'], $s['period'], $path);
        } finally {
            unlink($path);
        }
        expect($result['processed'])->toBeEmpty();
        expect(count($result['errors']) + count($result['skipped']))->toBe(1);
    }
});

test('domain finalized info remains frozen after academic and imported participant changes', function () {
    $s = $this->domainScenario;
    StudentFinalProject::create(['student_number' => $s['student']->student_number, 'thesis_title_id' => 'Original title', 'advisor_1' => '001']);
    executionDomainBooked($s);
    app(SidangTaExecutionInfoService::class)->capture($s['registration']);
    StudentFinalProject::where('student_number', $s['student']->student_number)->update(['thesis_title_id' => 'Changed title']);
    $path = executionDomainFile([executionDomainRow($s)]);
    try {
        $result = app(SidangTaPlottingImportService::class)->import($s['reviewer'], $s['period'], $path);
    } finally {
        unlink($path);
    }
    expect($result['processed'])->toBeEmpty();
    $dto = app(SidangTaExecutionInfoService::class)->forRegistration($s['registration']->fresh(), true);
    expect($dto['title'])->toBe('Original title')->and($dto['room_id'])->toBe($s['rooms']['a']->id);
});

test('domain results require confirmed approved execution and keep registration audit separate', function () {
    $s = $this->domainScenario;
    $service = app(SidangTaExecutionOutcomeService::class);
    expect(fn () => $service->set($s['reviewer'], $s['registration'], 'passed', 0))->toThrow(ValidationException::class);
    $context = executionDomainBooked($s);
    $logs = $s['registration']->logs()->count();
    $context = $service->set($s['reviewer'], $s['registration'], 'passed', $context->lock_version, 'Lulus');
    expect($context->result_status)->toBe('passed')->and($context->executionLogs()->count())->toBe(1);
    expect(fn () => $service->set($s['reviewer'], $s['registration'], 'failed', $context->lock_version - 1))->toThrow(HttpException::class);
    $context = $service->set($s['reviewer'], $s['registration'], 'pending', $context->lock_version);
    expect($context->result_status)->toBe('pending')->and($s['registration']->fresh()->status)->toBe('approved')->and($s['registration']->logs()->count())->toBe($logs)->and($context->executionLogs()->count())->toBe(2);
});

test('domain execution export preserves leading zero text and never writes user formulas', function () {
    $s = $this->domainScenario;
    $s['registration']->roomSchedule()->create(['student_number' => $s['student']->student_number, 'participant_data' => ['advisor_1_code' => '001', 'title' => '=SUM(1,2)']]);
    $response = app(SidangTaExecutionExport::class)->download(collect([$s['registration']]));
    $path = $response->getFile()->getPathname();
    $zip = new ZipArchive;
    $zip->open($path);
    $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $strings = $zip->getFromName('xl/sharedStrings.xml') ?: '';
    expect($xml)->not->toContain('<f>');
    expect($xml.$strings)->toContain('001')->toContain('=SUM(1,2)');
    $zip->close();
    unlink($path);
});

test('domain import requires reviewer permission and readable valid headers', function () {
    $s = $this->domainScenario;
    expect(fn () => app(SidangTaPlottingImportService::class)->import($s['owner'], $s['period'], '/missing.xlsx'))->toThrow(HttpException::class);
    expect(fn () => app(SidangTaPlottingImportService::class)->import($s['reviewer'], $s['period'], '/missing.xlsx'))->toThrow(ValidationException::class);
    expect(fn () => app(SidangTaExecutionOutcomeService::class)->set($s['owner'], $s['registration'], 'passed', 0))->toThrow(HttpException::class);
});

test('domain plotting rejects formula cells and invalid dates without partial writes', function () {
    $s = $this->domainScenario;
    foreach ([[6 => '=HYPERLINK("https://example.test","x")'], [10 => '2026-02-30'], [11 => '25:00'], [12 => '09:00']] as $override) {
        $path = executionDomainFile([executionDomainRow($s, $override)]);
        try {
            $result = app(SidangTaPlottingImportService::class)->import($s['reviewer'], $s['period'], $path);
        } finally {
            unlink($path);
        }
        expect($result['errors'])->toHaveCount(1)->and($result['processed'])->toBeEmpty();
        expect($s['registration']->roomSchedule()->exists())->toBeFalse();
    }
});

test('domain plotting repeated identical rows do not increment metadata versions', function () {
    $s = $this->domainScenario;
    $path = executionDomainFile([executionDomainRow($s), executionDomainRow($s)]);
    try {
        $first = app(SidangTaPlottingImportService::class)->import($s['reviewer'], $s['period'], $path);
        $version = $s['registration']->roomSchedule()->firstOrFail()->lock_version;
        $second = app(SidangTaPlottingImportService::class)->import($s['reviewer'], $s['period'], $path);
    } finally {
        unlink($path);
    }
    expect($first['processed'])->toHaveCount(1)->and($first['skipped'])->toHaveCount(1)->and($second['processed'])->toBeEmpty();
    expect($s['registration']->roomSchedule()->firstOrFail()->lock_version)->toBe($version);
});

test('domain plotting reads formatted Excel date objects and strict textual dates', function () {
    $s = $this->domainScenario;
    foreach ([new DateTimeImmutable('2026-11-23'), '23/11/2026'] as $date) {
        $path = executionDomainFile([executionDomainRow($s, [10 => $date, 11 => '08.00', 12 => '09.30'])]);
        try {
            $result = app(SidangTaPlottingImportService::class)->import($s['reviewer'], $s['period'], $path);
        } finally {
            unlink($path);
        }
        expect($result['errors'])->toBeEmpty();
        expect($s['registration']->roomSchedule()->firstOrFail()->preferred_date->format('Y-m-d'))->toBe('2026-11-23');
    }
});
