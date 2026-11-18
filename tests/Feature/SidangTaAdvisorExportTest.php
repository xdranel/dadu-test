<?php

use App\Models\Lecturer;
use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\Student;
use App\Models\StudentFinalProject;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Rap2hpoutre\FastExcel\FastExcel;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Storage::fake('local');
    Permission::findOrCreate('sidang_ta_registration_review', 'web');
    $reviewer = User::factory()->create(['status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
    $reviewer->givePermissionTo('sidang_ta_registration_review');
    $this->actingAs($reviewer);
    $this->period116 = SidangTaPeriod::create(['name' => 'Ekspor Pembimbing', 'is_active' => true, 'open_at' => now()->subDay(), 'close_at' => now()->addDay()]);
    $this->sequence116 = 0;
    $this->make116 = function (array $snapshot = []) {
        return SidangTaRegistration::create(['sidang_ta_period_id' => $this->period116->id,
            'student_number' => '116'.str_pad((string) ++$this->sequence116, 7, '0', STR_PAD_LEFT),
            'student_name' => 'Mahasiswa Ekspor', 'study_program' => 'Informatika',
            'student_phone' => '081234567890', 'parent_phone' => '081234567891',
            'graduation_payment_date' => today(), 'diploma_biodata_accurate' => true,
            'status' => 'submitted', 'submitted_at' => now(),
            'academic_snapshot' => array_replace(['ta_title' => 'Judul Indonesia', 'ta_title_en' => 'English Title',
                'admission' => ['eprt' => ['mode' => 'automatic']]], $snapshot)])->fresh();
    };
    $this->export116 = function (?Closure $inspect = null) {
        $response = $this->get(route('admin.sidang-ta-registrations.export'))->assertOk();
        $path = $response->baseResponse->getFile()->getPathname();
        try {
            $rows = (new FastExcel)->import($path);
            if ($inspect) $inspect($path);
            return $rows;
        } finally { if (is_file($path)) unlink($path); }
    };
});

test('export116_advisors_and_primary_expertise_follow_both_thesis_titles', function () {
    Lecturer::create(['name' => 'Dosen Utama', 'lecturer_code' => 'A116', 'front_title' => 'Dr.',
        'back_title' => 'M.T.', 'kk' => 'Rekayasa Perangkat Lunak']);
    Lecturer::create(['name' => 'Dosen Kedua', 'lecturer_code' => 'B116', 'kk' => 'Jaringan']);
    $r = ($this->make116)(['advisor_1' => 'A116', 'advisor_2' => 'Dosen Kedua', 'kk' => 'Bukan sumber KK']);
    $before = $r->getRawOriginal();
    $row = ($this->export116)()->first();
    $keys = array_keys($row);
    $title = array_search('Judul TA Indonesia', $keys, true);
    expect(array_slice($keys, $title, 5))->toBe(['Judul TA Indonesia', 'Judul TA Inggris', 'Pembimbing 1', 'Pembimbing 2', 'KK Pembimbing 1']);
    expect($row['Pembimbing 1'])->toBe('A116')
        ->and($row['Pembimbing 2'])->toBe('B116')
        ->and($row['KK Pembimbing 1'])->toBe('Rekayasa Perangkat Lunak');
    expect($r->fresh()->getRawOriginal())->toBe($before);
});

test('export116_keeps_recorded_advisors_when_current_project_has_changed', function () {
    Lecturer::create(['name' => 'Pembimbing Historis', 'lecturer_code' => 'OLD116', 'kk' => 'KK Historis']);
    Lecturer::create(['name' => 'Pembimbing Baru', 'lecturer_code' => 'NEW116', 'kk' => 'KK Baru']);
    $r = ($this->make116)(['advisor_1' => 'OLD116', 'advisor_2' => 'Pembimbing Kedua Historis']);
    Student::create(['student_number' => $r->student_number, 'name' => 'Mahasiswa', 'study_program' => 'Informatika', 'academic_year' => 2022, 'email' => $r->student_number.'@export.test']);
    $p = StudentFinalProject::create(['student_number' => $r->student_number, 'advisor_1' => 'NEW116', 'advisor_2' => 'Pembimbing Kedua Baru']);
    $before = $p->fresh()->getRawOriginal();
    $row = ($this->export116)()->first();
    expect($row['Pembimbing 1'])->toBe('OLD116')
        ->and($row['Pembimbing 2'])->toBe('Pembimbing Kedua Historis')
        ->and($row['KK Pembimbing 1'])->toBe('KK Historis');
    expect($p->fresh()->getRawOriginal())->toBe($before);
});

test('export116_fills_only_missing_advisors_from_later_academic_imports_without_writing_snapshot', function () {
    Lecturer::create(['name' => 'Diimpor Belakangan', 'lecturer_code' => 'L116', 'kk' => 'Sains Data']);
    $r = ($this->make116)(['advisor_1' => ' Belum tersedia ', 'advisor_2' => 'Pembimbing yang Tercatat']);
    Student::create(['student_number' => $r->student_number, 'name' => 'Mahasiswa', 'study_program' => 'Informatika', 'academic_year' => 2022, 'email' => $r->student_number.'@export.test']);
    StudentFinalProject::create(['student_number' => $r->student_number, 'advisor_1' => 'L116', 'advisor_2' => 'Tidak Menggantikan Snapshot']);
    $before = $r->getRawOriginal();
    $row = ($this->export116)()->first();
    expect($row['Pembimbing 1'])->toBe('L116')
        ->and($row['Pembimbing 2'])->toBe('Pembimbing yang Tercatat')
        ->and($row['KK Pembimbing 1'])->toBe('Sains Data');
    expect($r->fresh()->getRawOriginal())->toBe($before);
});

test('export116_missing_master_or_advisor_does_not_borrow_secondary_expertise', function ($value, $expected) {
    Lecturer::create(['name' => 'Dosen Kedua', 'lecturer_code' => 'B116', 'kk' => 'KK Pembimbing Kedua']);
    ($this->make116)(['advisor_1' => $value, 'advisor_2' => 'B116']);
    $row = ($this->export116)()->first();
    expect($row['Pembimbing 1'])->toBe($expected)->and($row['Pembimbing 2'])->toBe('B116')
        ->and($row['KK Pembimbing 1'])->toBe('Belum tersedia');
})->with([[null, 'Belum tersedia'], ['-', 'Belum tersedia'], [' ', 'Belum tersedia'], ['UNKNOWN116', 'UNKNOWN116']]);

test('export116_duplicate_names_are_not_guessed_and_exact_code_takes_precedence', function () {
    Lecturer::create(['name' => 'Nama Sama', 'lecturer_code' => 'D116A', 'kk' => 'KK Satu']);
    Lecturer::create(['name' => 'Nama Sama', 'lecturer_code' => 'D116B', 'kk' => 'KK Dua']);
    Lecturer::create(['name' => 'D116A', 'lecturer_code' => 'D116C', 'kk' => 'KK Nama Menyerupai Kode']);
    $ambiguous = ($this->make116)(['advisor_1' => 'Nama Sama']);
    $code = ($this->make116)(['advisor_1' => 'D116A']);
    $rows = ($this->export116)();
    expect($rows->firstWhere('ID Pengajuan', (string) $ambiguous->id)['KK Pembimbing 1'])->toBe('Belum tersedia');
    expect($rows->firstWhere('ID Pengajuan', (string) $code->id)['KK Pembimbing 1'])->toBe('KK Satu');
});

test('export116_advisor_and_expertise_formula_like_values_stay_text_and_pdf_links_keep_working', function () {
    Lecturer::create(['name' => '=1+1', 'lecturer_code' => 'X116', 'kk' => '=SUM(1,2)']);
    $r = ($this->make116)(['advisor_1' => 'X116', 'advisor_2' => '@SUM(3,4)']);
    $path = 'sidang_ta/export116.pdf';
    Storage::disk('local')->put($path, '%PDF-1.4');
    $doc = $r->documents()->create(['document_type' => 'thesis_draft', 'file_name' => 'ta.pdf', 'file_path' => $path,
        'file_type' => 'pdf', 'file_size' => 8, 'status' => 'verified']);
    ($this->export116)(function ($path) use ($doc) {
        $zip = new ZipArchive;
        expect($zip->open($path))->toBeTrue();
        try { $xml = $zip->getFromName('xl/worksheets/sheet1.xml'); } finally { $zip->close(); }
        $dom = new DOMDocument;
        $dom->loadXML($xml, LIBXML_NONET);
        $xp = new DOMXPath($dom);
        expect($xp->query('//*[local-name()="f"]')->length)->toBe(1);
        expect($xp->query('//*[local-name()="f"]')->item(0)->textContent)
            ->toBe('HYPERLINK("'.route('sidang-ta-registration-documents.preview', $doc).'","Lihat PDF")');
        foreach (['Pembimbing 1' => 'X116', 'Pembimbing 2' => '@SUM(3,4)', 'KK Pembimbing 1' => '=SUM(1,2)'] as $label => $expected) {
            $found = false;
            foreach ($xp->query('//*[local-name()="row" and @r="1"]/*[local-name()="c"]') as $header) {
                if ($xp->evaluate('string(.//*[local-name()="t"])', $header) !== $label) continue;
                $address = preg_replace('/1$/', '2', $header->getAttribute('r'));
                expect($xp->evaluate('string(//*[local-name()="c" and @r="'.$address.'"]/*[local-name()="is"]/*[local-name()="t"])'))->toBe($expected);
                $found = true;
            }
            expect($found)->toBeTrue('Kolom tidak ditemukan: '.$label);
        }
    });
});

test('export116_batches_master_lookups_for_multiple_registrations', function () {
    Lecturer::create(['name' => 'Dosen Batch', 'lecturer_code' => 'BAT116', 'kk' => 'KK Batch']);
    foreach (range(1, 20) as $i) ($this->make116)(['advisor_1' => 'BAT116']);
    $queries = [];
    DB::listen(function (QueryExecuted $event) use (&$queries) {
        if (preg_match('/from [`"]?(lecturers|student_final_projects)[`"]?/i', $event->sql)) $queries[] = $event->sql;
    });
    $rows = ($this->export116)();
    expect($rows)->toHaveCount(20)->and($rows->pluck('KK Pembimbing 1')->unique()->all())->toBe(['KK Batch']);
    expect(count($queries))->toBeLessThanOrEqual(2);
});


test('export116_preserves_distinct_leading_zero_identifiers_and_zero_lecturer_codes', function () {
    $expected = [];
    foreach ([['00116', '001', 'Dosen Kode Nol Awal'], ['116', '1', 'Dosen Kode Satu'], ['0116', '0', 'Dosen Kode Nol']] as [$nim, $code, $name]) {
        Lecturer::create(['name' => $name, 'lecturer_code' => $code, 'kk' => 'KK '.$name]);
        $r = ($this->make116)();
        $r->update(['student_number' => $nim]);
        Student::create(['student_number' => $nim, 'name' => 'Mahasiswa', 'study_program' => 'Informatika',
            'academic_year' => 2022, 'email' => $nim.'@export.test']);
        StudentFinalProject::create(['student_number' => $nim, 'advisor_1' => $code]);
        $expected[$r->id] = [$nim, $code, $name];
    }
    $rows = ($this->export116)()->keyBy('ID Pengajuan');
    foreach ($expected as $id => [$nim, $code, $name]) {
        expect($rows[$id]['NIM'])->toBe($nim)->and($rows[$id]['Pembimbing 1'])->toBe($code)
            ->and($rows[$id]['KK Pembimbing 1'])->toBe('KK '.$name);
    }
});
