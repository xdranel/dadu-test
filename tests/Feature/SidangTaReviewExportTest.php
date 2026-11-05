<?php

use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\Student;
use App\Models\User;
use App\Services\SidangTaReviewExport;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Rap2hpoutre\FastExcel\FastExcel;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

beforeEach(function () {
    $this->travelTo(now()->startOfSecond());
    Storage::fake('local');
    $this->temporaryExports = [];
    foreach (['sidang_ta_registration_review', 'sidang_ta_registration_read', 'sidang_ta_period_read'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->reviewer = User::factory()->create(['username' => 'export-reviewer', 'email' => 'export-reviewer@test.local',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
    $this->reviewer->givePermissionTo('sidang_ta_registration_review');
    $this->actingAs($this->reviewer);
    $this->a = SidangTaPeriod::create(['name' => 'Export A', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay()]);
    $this->b = SidangTaPeriod::create(['name' => 'Export B', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDays(2)]);
    $this->sequence = 0;
    $this->makeRegistration = function (array $overrides = []) {
        $this->sequence++;
        $overrides['academic_snapshot'] = array_replace_recursive(
            ['student_name' => 'Nama Historis', 'gpa' => 3.25, 'passed_credits' => 140,
                'admission' => ['eprt' => ['mode' => 'automatic']]], $overrides['academic_snapshot'] ?? []
        );
        return SidangTaRegistration::create(array_replace([
            'sidang_ta_period_id' => $this->a->id, 'student_number' => '0000'.str_pad((string) $this->sequence, 6, '0', STR_PAD_LEFT),
            'student_name' => 'Export '.$this->sequence, 'study_program' => 'Informatika',
            'student_phone' => '081234567890', 'parent_phone' => '081234567891',
            'graduation_payment_date' => today()->toDateString(), 'diploma_biodata_accurate' => true,
            'status' => 'submitted', 'submitted_at' => now(),
            'academic_snapshot' => ['student_name' => 'Nama Historis', 'gpa' => 3.25, 'passed_credits' => 140],
        ], $overrides))->fresh();
    };
    $this->attach = function ($registration, string $type, string $status = 'pending', bool $exists = true, ?string $notes = null) {
        $path = "sidang_ta/export-test/{$registration->id}/{$type}.pdf";
        if ($exists) Storage::disk('local')->put($path, '%PDF-export');
        return $registration->documents()->create(['document_type' => $type, 'file_name' => $type.'.pdf',
            'file_path' => $path, 'file_type' => 'pdf', 'file_size' => 11, 'status' => $status, 'notes' => $notes]);
    };
    $this->exportRows = function (array $filters = []) {
        $response = $this->get(route('admin.sidang-ta-registrations.export', $filters))->assertOk()
            ->assertDownload('Sidang_TA_'.now()->format('Y-m-d_H-i-s').'.xlsx');
        expect($response->baseResponse)->toBeInstanceOf(BinaryFileResponse::class);
        $path = $response->baseResponse->getFile()->getPathname();
        $this->temporaryExports[] = $path;
        return [$response, (new FastExcel)->import($path), $path];
    };
});

afterEach(function () {
    foreach ($this->temporaryExports as $path) if (is_file($path)) @unlink($path);
    $this->travelBack();
});

test('p2b_exports_all_filtered_rows_beyond_page_with_same_filter_counts', function () {
    $ids = [];
    foreach (range(1, 20) as $i) $ids[] = ($this->makeRegistration)(['student_name' => 'Target '.$i])->id;
    ($this->makeRegistration)(['student_name' => 'Target periode lain', 'sidang_ta_period_id' => $this->b->id]);
    ($this->makeRegistration)(['student_name' => 'Target prodi lain', 'study_program' => 'Sistem Informasi']);
    ($this->makeRegistration)(['student_name' => 'Target status lain', 'status' => 'approved']);
    $filters = ['search' => 'Target', 'period' => $this->a->id, 'status' => 'submitted', 'study_program' => 'Informatika'];
    $listing = $this->get(route('admin.sidang-ta-registrations.index', $filters + ['page' => 2]))->assertOk();
    expect($listing->viewData('counts')['total'])->toBe(20);
    [, $rows] = ($this->exportRows)($filters + ['page' => 2]);
    expect($rows)->toHaveCount(20);
    expect($rows->pluck('ID Pengajuan')->all())->toBe(array_map('strval', array_reverse($ids)));
    expect($rows->pluck('Status Pengajuan')->unique()->all())->toBe(['Menunggu peninjauan']);
});

test('p2b_export_keeps_historical_snapshot_after_live_student_changes', function () {
    $student = Student::create(['student_number' => '00123456', 'name' => 'Nama Awal', 'email' => 'snapshot-export@test.local',
        'academic_year' => 2022, 'study_program' => 'Informatika', 'status' => 'Aktif', 'gpa' => 3.25]);
    ($this->makeRegistration)(['student_number' => $student->student_number, 'student_name' => 'Nama Pengajuan',
        'academic_snapshot' => ['student_name' => 'Nama Snapshot', 'study_program' => 'Prodi Snapshot',
            'gpa' => 0, 'passed_credits' => 0, 'tak_score' => null, 'bpp_status' => false]]);
    $student->update(['name' => 'Nama Terbaru', 'study_program' => 'Prodi Terbaru', 'gpa' => 4]);
    [, $rows] = ($this->exportRows)();
    $row = $rows->first();
    expect($row['Nama Mahasiswa'])->toBe('Nama Pengajuan');
    expect($row['Snapshot Nama'])->toBe('Nama Snapshot');
    expect($row['Snapshot Prodi'])->toBe('Prodi Snapshot');
    expect($row['IPK'])->toBe(0);
    expect($row['SKS Lulus'])->toBe(0);
    expect($row['TAK'])->toBe('Belum tersedia');
    expect($row['Status BPP'])->toBe('Tidak');
    expect(array_values($row))->not->toContain('Nama Terbaru')->not->toContain('Prodi Terbaru');
});

test('p2b_xlsx_preserves_text_ids_phones_and_literal_formula_like_content', function () {
    $registration = ($this->makeRegistration)(['student_number' => '001234567890123456', 'student_name' => '=2+2',
        'student_phone' => '001234567890', 'parent_phone' => '000987654321',
        'review_notes' => '=HYPERLINK("https://example.invalid","x")',
        'academic_snapshot' => ['gpa' => '=1+1', 'ta_title' => '=SUM(1,2)']]);
    ($this->attach)($registration, 'thesis_draft', 'rejected', true, '=SUM(2,3)');
    [, $rows, $path] = ($this->exportRows)();
    $row = $rows->first();
    expect($row)->toHaveCount(57);
    expect($row['NIM'])->toBe('001234567890123456');
    expect($row['HP Mahasiswa'])->toBe('001234567890');
    expect($row['HP Orang Tua/Wali'])->toBe('000987654321');
    $zip = new ZipArchive;
    expect($zip->open($path))->toBeTrue();
    try { $xml = $zip->getFromName('xl/worksheets/sheet1.xml'); } finally { $zip->close(); }
    $document = new DOMDocument;
    expect($document->loadXML($xml, LIBXML_NONET))->toBeTrue();
    $xpath = new DOMXPath($document);
    expect($xpath->query('//*[local-name()="f"]')->length)->toBe(0);
    // Reader OpenSpout dapat menafsirkan teks berawalan '=' sebagai FormulaCell.
    // Periksa literal langsung dari inlineStr pada XLSX, bukan computed value reader.
    $textCell = function (string $column) use ($xpath): string {
        foreach ($xpath->query('//*[local-name()="row" and @r="1"]/*[local-name()="c"]') as $header) {
            if ($xpath->evaluate('string(.//*[local-name()="t"])', $header) === $column) {
                $address = preg_replace('/\d+$/', '2', $header->getAttribute('r'));
                return $xpath->evaluate('string(//*[local-name()="c" and @r="'.$address.'"]/*[local-name()="is"]/*[local-name()="t"])');
            }
        }
        throw new RuntimeException('Kolom tidak ditemukan: '.$column);
    };
    expect($textCell('Nama Mahasiswa'))->toBe('=2+2');
    expect($textCell('Catatan Petugas'))->toBe('=HYPERLINK("https://example.invalid","x")');
    expect($textCell('IPK'))->toBe('=1+1');
    expect($textCell('Judul TA Indonesia'))->toBe('=SUM(1,2)');
    expect($textCell(SidangTaRegistration::DOCUMENT_TYPES['thesis_draft'].' — Catatan'))->toBe('=SUM(2,3)');
    foreach (['C2', 'F2', 'G2'] as $cell) {
        $node = $xpath->query('//*[local-name()="c" and @r="'.$cell.'"]')->item(0);
        expect(in_array($node->getAttribute('t'), ['inlineStr', 's'], true))->toBeTrue();
    }
});

test('p2b_export_document_columns_distinguish_na_missing_and_unavailable', function () {
    $accurate = ($this->makeRegistration)();
    $pdm = ($this->makeRegistration)(['diploma_biodata_accurate' => false]);
    ($this->attach)($accurate, 'thesis_draft', 'verified', false);
    ($this->attach)($accurate, 'pdm_submission_proof', 'verified', true, 'Cabang lama');
    ($this->attach)($pdm, 'pdm_submission_proof', 'rejected', true, 'Perbaiki PDM');
    [, $rows] = ($this->exportRows)();
    $a = $rows->firstWhere('ID Pengajuan', (string) $accurate->id);
    $b = $rows->firstWhere('ID Pengajuan', (string) $pdm->id);
    $thesis = SidangTaRegistration::DOCUMENT_TYPES['thesis_draft'];
    $capture = SidangTaRegistration::DOCUMENT_TYPES['ta_data_capture'];
    $pdmLabel = SidangTaRegistration::DOCUMENT_TYPES['pdm_submission_proof'];
    expect($a[$thesis.' — Status'])->toBe('Terverifikasi');
    expect($a[$thesis.' — Ketersediaan'])->toBe('Tidak ditemukan');
    expect($a[$capture.' — Status'])->toBe('Belum ada dokumen');
    expect($a[$capture.' — Ketersediaan'])->toBe('Tidak ada metadata');
    foreach (['Status', 'Ketersediaan', 'Catatan'] as $column) expect($a[$pdmLabel.' — '.$column])->toBe('N/A');
    expect($b[$pdmLabel.' — Status'])->toBe('Perlu perbaikan');
    expect($b[$pdmLabel.' — Catatan'])->toBe('Perbaiki PDM');
    foreach (['diploma_statement', 'diploma_biodata_form'] as $type) {
        expect($b[SidangTaRegistration::DOCUMENT_TYPES[$type].' — Status'])->toBe('N/A');
    }
});

test('p2b_empty_export_is_disabled_in_ui_and_direct_request_is_rejected', function () {
    $crawler = new Crawler($this->get(route('admin.sidang-ta-registrations.index'))->assertOk()->getContent());
    expect($crawler->filter('[data-ta-export]')->count())->toBe(0);
    expect($crawler->filter('[data-ta-export-empty][disabled]')->count())->toBe(1);
    $this->getJson(route('admin.sidang-ta-registrations.export'))->assertUnprocessable()->assertJsonValidationErrors('export');
});

test('p2b_period_only_and_student_read_without_review_cannot_export', function () {
    ($this->makeRegistration)();
    $queries = [];
    DB::listen(function (QueryExecuted $event) use (&$queries) {
        if (str_contains(strtolower($event->sql), 'sidang_ta_registrations')) $queries[] = $event->sql;
    });
    foreach (['sidang_ta_period_read', 'sidang_ta_registration_read'] as $permission) {
        $user = User::factory()->create(['status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
        $user->givePermissionTo($permission);
        $this->actingAs($user)->get(route('admin.sidang-ta-registrations.export'))->assertForbidden();
        if ($permission === 'sidang_ta_period_read') {
            $crawler = new Crawler($this->get(route('admin.sidang-ta-registrations.index'))->assertOk()->getContent());
            expect($crawler->filter('[data-ta-export], [data-ta-export-empty]')->count())->toBe(0);
        }
    }
    expect($queries)->toBe([]);
});

test('p2b_export_uses_same_invalid_filter_validation_as_listing', function () {
    foreach ([['status' => 'bad'], ['period' => 999999], ['search' => ['bad']],
                 ['study_program' => str_repeat('x', 256)], ['page' => 0]] as $filters) {
        $this->getJson(route('admin.sidang-ta-registrations.export', $filters))->assertUnprocessable();
    }
});

test('p2b_export_link_keeps_applied_filters_without_current_page', function () {
    foreach (range(1, 16) as $i) ($this->makeRegistration)(['student_name' => 'Tautan '.$i]);
    $filters = ['search' => 'Tautan', 'period' => $this->a->id, 'status' => 'submitted', 'study_program' => 'Informatika'];
    $crawler = new Crawler($this->get(route('admin.sidang-ta-registrations.index', $filters + ['page' => 2]))->assertOk()->getContent());
    $href = $crawler->filter('[data-ta-export]')->attr('href');
    expect($href)->toBe(route('admin.sidang-ta-registrations.export', $filters));
    parse_str(parse_url($href, PHP_URL_QUERY), $query);
    expect(array_key_exists('page', $query))->toBeFalse();
});

test('p2b_export_omits_private_paths_urls_and_unknown_snapshot_fields', function () {
    $registration = ($this->makeRegistration)(['academic_snapshot' => [
        'file_path' => 'private-secret-snapshot-path', 'gpa' => null,
    ]]);
    $document = ($this->attach)($registration, 'thesis_draft');
    [, $rows] = ($this->exportRows)();
    $row = $rows->first();
    expect($row['IPK'])->toBe('Belum tersedia');
    expect(implode('|', array_keys($row)))->not->toContain('file_path')->not->toContain('download_url');
    expect(implode('|', array_values($row)))->not->toContain($document->file_path)
        ->not->toContain('private-secret-snapshot-path')->not->toContain('/storage/');
});

test('p2b_private_attachment_is_deleted_after_send_without_mutating_registration', function () {
    $registration = ($this->makeRegistration)();
    [$response, , $path] = ($this->exportRows)();
    $response->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    expect($response->headers->get('Cache-Control'))->toContain('private')->toContain('no-store');
    expect($response->headers->hasCacheControlDirective('public'))->toBeFalse();
    expect($response->baseResponse->shouldDeleteFileAfterSend())->toBeTrue();
    ob_start();
    try { $response->baseResponse->sendContent(); $bytes = ob_get_contents(); } finally { ob_end_clean(); }
    expect($bytes)->toStartWith('PK');
    expect(is_file($path))->toBeFalse();
    expect($registration->fresh()->status)->toBe('submitted');
    expect($registration->fresh()->lock_version)->toBe(1);
    expect($registration->logs()->count())->toBe(0);
});
