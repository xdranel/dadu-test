# 40 — P2b: ekspor Excel Review Sidang TA

**Goal:** ekspor seluruh hasil filter Review TA ke XLSX dengan identitas, kontak, snapshot akademik historis dan status/catatan dokumen; hanya reviewer dapat mengunduh.

**Architecture:** gunakan SidangTaReviewQuery dari39 untuk filter/query yang sama. Mapper ekspor menulis kolom tetap; FastExcel existing mengekspor ke file temp privat, lalu Laravel mengirim attachment dan menghapusnya setelah dikirim.

**Tech stack:** Laravel13, FastExcel v5.17.0 dan OpenSpout v4.32.0 dari composer.lock existing, Pest, MySQL Podman. Tidak ada dependency/migrasi/permission baru.

**Spec:** rancangan31 bagian8 disetujui. P2a sudah dilaporkan lulus unit/manual dan tersalin. Rescan source relevan kedua folder identik,152 definisi Feature TA; review independen tidak menemukan blocker P2a.

**Koreksi penerapan:** [41](41-koreksi-cache-ekspor-dan-tombol-hijau.md) menjelaskan kegagalan assertion Cache-Control: download() membuat respons public sehingga wajib diakhiri setPrivate(). Snippet di bawah sudah diperbarui, termasuk tombol ekspor hijau dan assertion tanpa public. Jumlah test tetap sama.

## 1. Kontrak ekspor

- Satu baris per pengajuan, seluruh hasil search/period/status/study_program, urutan sama dengan tabel. Parameter page divalidasi helper lalu tidak menjadi filter/limit ekspor.
- Nama/NIM/prodi utama dari registration dan data akademik dari academic_snapshot; tidak mengambil Student terbaru. Ada88kolom tetap:14identitas/pengajuan +26snapshot +3kolom untuk masing-masing16jenis dokumen.
- NIM/telepon tetap teks; Semester/IPK/SKS/TAK/EPrT menjadi angka bila nilainya numerik. Nol tidak berubah menjadi kosong. Null/blank/'-' menjadi Belum tersedia; boolean menjadi Ya/Tidak; nilai struktur pada key snapshot yang dikenal menjadi JSON teks.
- Dokumen tidak berlaku ditulis N/A untuk status/ketersediaan/catatan. Dokumen wajib tanpa metadata dibedakan dari metadata yang file aktualnya hilang. Status tetap berasal dari dokumen, bukan status registration.
- Tidak ada file_path, URL storage, URL preview/download atau raw snapshot ekstra yang tidak dikenal pada spreadsheet. Isi teks yang diawali '=' tetap literal, bukan formula.
- Hasil kosong tidak menghasilkan workbook tanpa header: tombol disabled dan direct request ditolak422 dengan pesan jelas. Validasi filter tetap sama dengan39.
- File temp dibuat unik oleh tempnam (privat pada sistem yang mendukung permission file), tidak memakai nama path dari request. Terhapus setelah send; kegagalan generate membersihkan file temp.

## 2. File dan urutan

Edit existing:

```text
routes/web.php
app/Http/Controllers/SidangTaRegistrationController.php (tambah exportExcel saja)
resources/views/sidang_ta_registrations/admin/index.blade.php (aksi header saja)
```

Buat baru:

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
php artisan make:class Services/SidangTaReviewExport
php artisan make:test SidangTaReviewExportTest --pest
```

- [ ] Isi test bagian6 dahulu dan jalankan fokus bagian7. Route/file baru semestinya belum ada sebelum implementasi.
- [ ] Terapkan bagian3–5. Jangan mengubah service query P2a atau seeder/permission.
- [ ] Jalankan suite/manual, lalu salin5file untuk rescan sebelum P3.

## 3. Service ekspor lengkap

Isi app/Services/SidangTaReviewExport.php:

```php
<?php

namespace App\Services;

use App\Models\SidangTaRegistration;
use App\Support\SidangTaDocumentRequirements as Requirements;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Rap2hpoutre\FastExcel\FastExcel;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class SidangTaReviewExport
{
    public const SNAPSHOT_COLUMNS = [
        'Snapshot NIM' => 'student_number', 'Snapshot Nama' => 'student_name', 'Snapshot Prodi' => 'study_program',
        'Email Kampus' => 'student_email', 'Semester' => 'semester', 'IPK' => 'gpa', 'SKS Lulus' => 'passed_credits',
        'Sidang Tingkat 2' => 'sidang_tingkat_2', 'Judul TA Indonesia' => 'ta_title', 'Judul TA Inggris' => 'ta_title_en',
        'Pembimbing 1' => 'advisor_1', 'Pembimbing 2' => 'advisor_2',
        'Penguji 1' => 'examiner_1', 'Penguji 2' => 'examiner_2', 'Penguji 3' => 'examiner_3',
        'Tanggal Sidang' => 'exam_date', 'Waktu Sidang' => 'exam_time', 'Lokasi Sidang' => 'exam_location',
        'TAK' => 'tak_score', 'EPrT' => 'eprt_score', 'Status BPP' => 'bpp_status', 'Status SKPI' => 'skpi_status',
        'Status TAK' => 'skpi_tak', 'Sidang Tingkat 3' => 'level_3_status', 'Status i-Basila' => 'ibasila_status',
        'Sumber Akademik Diperbarui' => 'academic_source_updated_at',
    ];

    private function text(mixed $value): string
    {
        if ($value === null || (is_string($value) && in_array(trim($value), ['', '-'], true))) return 'Belum tersedia';
        if (is_bool($value)) return $value ? 'Ya' : 'Tidak';
        if (is_scalar($value)) return (string) $value;
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)
            ?: 'Belum tersedia';
    }

    public function row(SidangTaRegistration $registration): array
    {
        $snapshot = is_array($registration->academic_snapshot) ? $registration->academic_snapshot : [];
        $row = [
            'ID Pengajuan' => (string) $registration->id,
            'Periode Sidang TA' => $this->text($registration->period?->name),
            'NIM' => $this->text($registration->student_number),
            'Nama Mahasiswa' => $this->text($registration->student_name),
            'Program Studi' => $this->text($registration->study_program),
            'HP Mahasiswa' => $this->text($registration->student_phone),
            'HP Orang Tua/Wali' => $this->text($registration->parent_phone),
            'Tanggal Bayar Wisuda' => $this->text($registration->graduation_payment_date?->format('Y-m-d')),
            'Biodata Ijazah' => $registration->diploma_biodata_accurate ? 'Sudah sesuai' : 'Belum sesuai (PDM)',
            'Status Pengajuan' => SidangTaReviewQuery::STATUSES[$registration->status] ?? $this->text($registration->status),
            'Dikirim' => $this->text($registration->submitted_at?->format('Y-m-d H:i:s')),
            'Ditinjau' => $this->text($registration->reviewed_at?->format('Y-m-d H:i:s')),
            'Catatan Petugas' => $this->text($registration->review_notes),
            'Versi Pengajuan' => (string) $registration->lock_version,
        ];
        foreach (self::SNAPSHOT_COLUMNS as $label => $key) $row[$label] = $this->text($snapshot[$key] ?? null);
        $required = array_merge(Requirements::CAPTURES, Requirements::PAYMENT,
            Requirements::typesForStep(4, $registration->diploma_biodata_accurate));
        $labels = ['pending' => 'Menunggu verifikasi', 'verified' => 'Terverifikasi', 'rejected' => 'Perlu perbaikan'];
        foreach (SidangTaRegistration::DOCUMENT_TYPES as $type => $label) {
            $applicable = in_array($type, $required, true);
            $document = $registration->documents->firstWhere('document_type', $type);
            $row[$label.' — Status'] = !$applicable ? 'N/A' : (!$document ? 'Belum ada dokumen'
                : ($labels[$document->status] ?? $this->text($document->status)));
            $row[$label.' — Ketersediaan'] = !$applicable ? 'N/A' : (!$document ? 'Tidak ada metadata'
                : (Storage::disk('local')->exists($document->file_path) ? 'Tersedia' : 'Tidak ditemukan'));
            $row[$label.' — Catatan'] = !$applicable ? 'N/A' : $this->text($document?->notes);
        }
        return $row;
    }

    public function download(Collection $registrations): BinaryFileResponse
    {
        if ($registrations->isEmpty()) {
            throw ValidationException::withMessages(['export' => 'Tidak ada pengajuan yang cocok untuk diekspor.']);
        }
        $rows = $registrations->map(fn (SidangTaRegistration $registration) => $this->row($registration));
        $temporary = tempnam(sys_get_temp_dir(), 'dadu-ta-xlsx-');
        if ($temporary === false) throw new RuntimeException('File sementara ekspor tidak dapat dibuat.');
        $path = $temporary.'.xlsx';
        if (!@rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('File sementara XLSX tidak dapat disiapkan.');
        }
        try {
            (new FastExcel($rows))->stringValues()->setColumnFormat([
                'Semester' => 'number', 'IPK' => 'number', 'SKS Lulus' => 'number',
                'TAK' => 'number', 'EPrT' => 'number',
            ])->escapeFormulas()->export($path);
            clearstatcache(true, $path);
            return response()->download($path, 'Sidang_TA_'.now()->format('Y-m-d_H-i-s').'.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ])->setPrivate()->deleteFileAfterSend(true);
        } catch (Throwable $exception) {
            if (is_file($path)) @unlink($path);
            throw $exception;
        }
    }
}
```

File temp unik diberi ekstensi .xlsx lewat rename sebelum ditulis, agar format writer/importer selalu pasti XLSX dan permission file temp tetap dipertahankan. Nama download juga berakhiran .xlsx. Memakai export ke file + BinaryFileResponse menghindari native header()/output buffer dari FastExcel download(), sehingga pengujian file tidak bergantung pada capture stream browser. Tidak membuat salinan export permanen pada storage aplikasi.

## 4. Route dan method controller

Di grup admin TA, tambahkan route **setelah index dan sebelum detail `{sidang_ta_registration}`**:

```php
Route::get('sidang-ta-registrations/export', [SidangTaRegistrationController::class, 'exportExcel'])
    ->middleware('permission:sidang_ta_registration_review')
    ->name('sidang-ta-registrations.export');
```

Jika diletakkan setelah detail, kata export dapat dianggap ID registration. Jangan menambah route duplikat atau memperluas akses ke period_read.

Tambahkan method berikut pada SidangTaRegistrationController; import Request sudah ada:

```php
public function exportExcel(Request $request)
{
    $query = app(\App\Services\SidangTaReviewQuery::class);
    $filters = $query->filters($request);
    $registrations = $query->query($filters)->with(['period', 'documents'])
        ->orderByDesc('submitted_at')->orderByDesc('id')->get();
    return app(\App\Services\SidangTaReviewExport::class)->download($registrations);
}
```

Seluruh filter dipakai ulang; tidak ada paginate/limit atau join Student di sini. Semua data diambil sebelum file ditulis sehingga error query/validasi tidak memulai pengiriman XLSX.

## 5. Tombol header

Pada resources/views/sidang_ta_registrations/admin/index.blade.php, di header utama ganti **blok @can('sidang_ta_period_read') untuk Kelola Periode saja** dengan berikut (judul/header lain tetap):

```blade
<div class="flex flex-wrap items-center gap-2">
    @if($canReview)
        @if($counts['total'] > 0)
            <a data-ta-export href="{{ route('admin.sidang-ta-registrations.export', $filters) }}" class="btn btn-success">
                <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-width="1.7" stroke-linejoin="round" d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm0 0v6h6M8 12l5 6m0-6l-5 6"/></svg>
                Ekspor Excel
            </a>
        @else
            <button data-ta-export-empty type="button" disabled class="btn btn-success cursor-not-allowed opacity-50" title="Tidak ada pengajuan untuk diekspor">Ekspor Excel</button>
        @endif
    @endif
    @can('sidang_ta_period_read')
        <a data-manage-ta-periods href="{{ route('admin.sidang-ta-periods.index') }}" class="btn btn-secondary">Kelola Periode</a>
    @endcan
</div>
```

Tombol mengikuti filter yang sudah diterapkan/tersimpan pada URL. Perubahan pilihan filter yang belum menekan Terapkan belum memengaruhi ekspor. period-only tidak menerima tombol/data export. Kondisi counts tidak dibaca saat canReview=false.

## 6. Test baru lengkap

Isi tests/Feature/SidangTaReviewExportTest.php. Test membaca XLSX aktual dari BinaryFileResponse; file temp test dibersihkan setelah tiap test:

```php
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
    expect($row)->toHaveCount(88);
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
    $capture = SidangTaRegistration::DOCUMENT_TYPES['level_2_result_capture'];
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

```

## 7. Command test dan manual

```bash
php artisan route:clear
php artisan view:clear
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaReviewExportTest.php tests/Feature/SidangTaReviewListTest.php tests/Feature/SidangTaStaffNavigationTest.php
```

Target fokus **36 passed** =10Export+14ReviewList+12StaffNavigation. Suite lengkap:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaDraftSchemaTest.php tests/Feature/SidangTaPeriodTest.php tests/Feature/SidangTaDraftTest.php tests/Feature/SidangTaDraftUploadTest.php tests/Feature/SidangTaRevisionDraftTest.php tests/Feature/SidangTaWizardTest.php tests/Feature/SidangTaFinalizationTest.php tests/Feature/SidangTaWizardUiTest.php tests/Feature/SidangTaDraftCardTest.php tests/Feature/SidangTaReviewUiTest.php tests/Feature/SidangTaDetailUiTest.php tests/Feature/SidangTaPartialDraftTest.php tests/Feature/SidangTaStudentPolishTest.php tests/Feature/SidangTaDocumentPreviewTest.php tests/Feature/SidangTaStaffNavigationTest.php tests/Feature/SidangTaReviewListTest.php tests/Feature/SidangTaReviewExportTest.php
node tests/Js/sidang-ta-entry.test.mjs
node tests/Js/sidang-ta-wizard.test.mjs
node tests/Js/sidang-ta-document-preview.test.mjs
npm run build
```

Target **162 PHP, 12 Node**, build berhasil. Test membuat XLSX temp dan membersihkannya; tetap schema MySQL Podman dadu_testing, bukan dadu. Tidak perlu mengubah phpunit.xml, composer.json/lock atau test existing.

Manual:

- [ ] Reviewer melihat Ekspor Excel dengan ikon Excel. Kombinasikan empat filter, tekan Terapkan, lalu unduh. Nama file berakhiran .xlsx dan dapat dibuka Excel/LibreOffice.
- [ ] Bandingkan jumlah baris data dengan Total hasil filter, bukan jumlah halaman. Dari halaman2 hasil lebih15 tetap semua hasil filter diekspor.
- [ ] NIM panjang/berawalan nol dan dua nomor HP tetap utuh sebagai teks; IPK/SKS bernilai0 tetap0, data null ditulis Belum tersedia.
- [ ] Identitas pengajuan dan snapshot historis tidak berubah ketika data Student diperbarui setelah pengajuan.
- [ ] Cabang ijazah/PDM yang tidak berlaku N/A. Berkas wajib yang tidak ada metadata dibedakan dari file aktual hilang. Catatan reviewer/dokumen tampil sebagai teks.
- [ ] Hasil filter kosong: tombol nonaktif. Direct URL export tidak menghasilkan file kosong/rusak; pengguna mendapat pesan validasi.
- [ ] Akun period-only tidak melihat ekspor dan direct URL ditolak; akun review-only tanpa read mahasiswa tetap dapat mengunduh.
- [ ] Tidak ada kolom path/URL privat. Preview/kelola periode/sidebar/keputusan existing tetap bekerja; ekspor tidak mengubah status atau version.

## 8. Handoff dan batas tahap

Sesudah test/manual berhasil, salin5file pada bagian2 ke dadu-test dan minta rescan. Berikutnya **P3 verifikasi tiap dokumen**, guard Setujui, version/log serta feedback mahasiswa, sesuai31. Jangan menganggap ekspor sekaligus mengimplementasikan verifikasi dokumen.

Tidak ada file fitur lama yang perlu dihapus. Commit/push petugas tetap dilakukan pengguna setelah scope yang diinginkan selesai dan direscan; tidak menambahkan konfigurasi lokal .env/phpunit.xml/package-lock tidak terkait atau berkas hasil ekspor ke commit.

## 9. Pemeriksaan panduan

Empat blok PHP lulus pemeriksaan sintaks; satu blok Blade dikompilasi di memori tanpa component tags lalu hasil PHP lulus sintaks. Probe memakai service usulan dan vendor aktual menulis XLSX nyata ke /tmp, membaca88kolom, memeriksa nol awal NIM/HP sebagai text cells, metrik0/null/false, literal formula pada XML tanpa elemen f, lalu mengirim BinaryFileResponse dan memverifikasi file terhapus. Probe lulus tanpa bootstrap aplikasi/DB dan file temp dibersihkan.

Probe pertama mengungkap bahwa reader OpenSpout dapat mengklasifikasikan ulang teks '=...' sebagai FormulaCell sehingga FastExcel import mengembalikan computed null, walaupun writer sudah menulis inlineStr yang benar. Test formula disesuaikan untuk memeriksa XML XLSX secara langsung; export escapeFormulas tetap. Leading-zero/angka/nilai biasa tetap diuji melalui import workbook aktual.

Review independen tidak menemukan blocker tersisa pada mapper, route/filter/permission, fixture atau cleanup. Dua belas test Node existing dijalankan ulang dan lulus. Source kedua folder dipindai sebelum/sesudah setiap penulisan dan tidak diubah; tidak ada instalasi dependency, operasi DB, suite Feature atau build dijalankan asisten.

Feature162/build/manual P2b belum dijalankan pengguna; hasil152 P2a tidak menjadi bukti ekspor lulus.

Referensi Context7: [FastExcel README](https://github.com/rap2hpoutre/fast-excel/blob/master/README.md), khusus export/import, stringValues, setColumnFormat dan escapeFormulas. Versi serta signature metode juga dicocokkan dengan composer.lock/vendor lokal, tanpa instalasi/update dependency.
