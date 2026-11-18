<?php

use App\Enums\AccessGroup;
use App\Jobs\ProcessDocumentImportBatch;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentImportBatch;
use App\Models\DocumentImportRow;
use App\Models\DocumentNim;
use App\Models\User;
use App\Services\DocumentImportParser;
use App\Services\DocumentImportTemplateService;
use App\Services\DocumentImportValidator;
use App\Services\DocumentImportZipExtractor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Queue::fake();
    Http::fake();

    Document::query()->forceDelete();
    DocumentCategory::query()->forceDelete();
    DocumentImportRow::query()->forceDelete();
    DocumentImportBatch::query()->forceDelete();

    Storage::fake('public');
    Storage::fake('private');

    // Setup Roles
    Role::firstOrCreate(['name' => 'admin']);
    Role::firstOrCreate(['name' => 'Akademik']);
    Role::firstOrCreate(['name' => 'Petugas']);
    Role::firstOrCreate(['name' => 'mahasiswa']);

    Permission::firstOrCreate(['name' => 'public_document_read']);
    Permission::firstOrCreate(['name' => 'document_read']);
    Permission::firstOrCreate(['name' => 'document_create']);
    Permission::firstOrCreate(['name' => 'document_update']);
    Permission::firstOrCreate(['name' => 'document_delete']);

    $this->admin = User::factory()->create(['status' => 'active']);
    $this->admin->assignRole('admin');
    $this->admin->givePermissionTo(['document_read', 'document_create', 'document_update', 'document_delete', 'public_document_read']);

    $this->petugas = User::factory()->create(['status' => 'active']);
    $this->petugas->assignRole('Petugas');
    $this->petugas->givePermissionTo(['document_read', 'document_create', 'document_update', 'public_document_read']);

    $this->mhs1 = User::factory()->create(['status' => 'active', 'employee_id' => '1234567890']);
    $this->mhs1->assignRole('mahasiswa');
    $this->mhs1->givePermissionTo(['document_read', 'public_document_read']);

    $this->mhs2 = User::factory()->create(['status' => 'active', 'employee_id' => '0987654321']);
    $this->mhs2->assignRole('mahasiswa');
    $this->mhs2->givePermissionTo(['document_read', 'public_document_read']);

    // Folders
    $this->folderPublik = DocumentCategory::create([
        'name' => 'Folder Publik',
        'access_group' => AccessGroup::PUBLIK,
        'is_active' => true,
    ]);

    $this->folderAkademik = DocumentCategory::create([
        'name' => 'Folder Akademik',
        'access_group' => AccessGroup::AKADEMIK,
        'is_active' => true,
    ]);

    $this->folderMahasiswa = DocumentCategory::create([
        'name' => 'Folder Mahasiswa',
        'access_group' => AccessGroup::MAHASISWA,
        'is_active' => true,
    ]);
});

test('skema migration tabel import dan kolom baru menggunakan bahasa Inggris', function () {
    expect(Schema::hasColumn('documents', 'document_type'))->toBeTrue();
    expect(Schema::hasTable('document_import_batches'))->toBeTrue();
    expect(Schema::hasTable('document_import_rows'))->toBeTrue();

    expect(Schema::hasColumns('document_import_batches', [
        'id', 'user_id', 'mode', 'status', 'summary', 'file_path', 'created_at', 'updated_at'
    ]))->toBeTrue();

    expect(Schema::hasColumns('document_import_rows', [
        'id', 'batch_id', 'row_number', 'original_name', 'data', 'status', 'messages', 'document_id', 'applied_at', 'created_at', 'updated_at'
    ]))->toBeTrue();
});

test('template download xlsx dan csv menghasilkan header dan folder yang sesuai hak akses', function () {
    $service = app(DocumentImportTemplateService::class);

    // 1. Admin melihat seluruh folder
    $foldersAdmin = $service->getAvailableFolders($this->admin);
    expect($foldersAdmin)->toHaveCount(3);

    // 2. Petugas hanya melihat folder publik
    $foldersPetugas = $service->getAvailableFolders($this->petugas);
    expect($foldersPetugas)->toHaveCount(1);
    expect($foldersPetugas[0]['name'])->toBe('Folder Publik');

    // 3. Download CSV route
    $responseCsv = $this->actingAs($this->admin)->get(route('documents.import.template', ['format' => 'csv']));
    $responseCsv->assertOk();
    $responseCsv->assertHeader('content-type', 'text/csv; charset=UTF-8');

    // 4. Download XLSX route
    $responseXlsx = $this->actingAs($this->admin)->get(route('documents.import.template', ['format' => 'xlsx']));
    $responseXlsx->assertOk();
});

test('parser aman terhadap bom, delimiter, formula injection, dan notasi ilmiah nim', function () {
    $parser = app(DocumentImportParser::class);

    // Formula injection content
    $csvContent = "\xEF\xBB\xBF" .
        "Nama Lama;Jenis Dokumen;Role Dokumen;Folder;Nama Baru;NIM;Keyword\n" .
        "file1.pdf;SK;Publik;Folder Publik;=cmd|' /C calc'!A0;1234567890;tag1,tag2\n" .
        "file2.pdf;KRS;Mahasiswa;Folder Mahasiswa;Kartu Rencana;1.23E+09;tag3\n";

    $tmp = tempnam(sys_get_temp_dir(), 'csv_test_') . '.csv';
    file_put_contents($tmp, $csvContent);

    $rows = $parser->parse($tmp);
    @unlink($tmp);

    expect($rows)->toHaveCount(2);

    // Row 1: formula sanitized with single quote
    expect($rows[0]['new_name'])->toStartWith("'=");

    // Row 2: scientific notation in NIM recorded in errors
    expect($rows[1]['_parsing_errors'])->not->toBeEmpty();
    expect($rows[1]['_parsing_errors'][0])->toContain('notasi ilmiah');
});

test('validator memvalidasi konflik role vs root folder dan hak akses petugas', function () {
    // Konflik: Role Mahasiswa tapi target Folder Publik
    $rows = [
        [
            'row_number' => 1,
            'old_name' => 'doc1.pdf',
            'original_name' => 'doc1.pdf',
            'document_type' => 'Surat',
            'role' => 'Mahasiswa',
            'folder' => 'Folder Publik',
            'new_name' => 'Dokumen 1',
            'nim' => ['1234567890'],
            'keywords' => [],
        ],
    ];

    $validator = new DocumentImportValidator(
        mode: 'upload',
        actor: $this->admin,
        allowUnknownNim: false,
        allowPublicToNonPublic: false
    );
    $validator->prepareBatchCache($rows);
    $validated = $validator->validateBatch($rows);

    expect($validated[0]['status'])->toBe('error');
    expect(collect($validated[0]['messages'])->pluck('message')->implode(' '))->toContain('tidak sesuai dengan kategori root folder');

    // Petugas tidak boleh upload ke folder Mahasiswa
    $rowsPetugas = [
        [
            'row_number' => 1,
            'old_name' => 'doc2.pdf',
            'original_name' => 'doc2.pdf',
            'document_type' => 'Surat',
            'role' => 'Mahasiswa',
            'folder' => 'Folder Mahasiswa',
            'new_name' => 'Dokumen 2',
            'nim' => ['1234567890'],
            'keywords' => [],
        ],
    ];
    $validatorPetugas = new DocumentImportValidator(
        mode: 'upload',
        actor: $this->petugas,
        allowUnknownNim: false,
        allowPublicToNonPublic: false
    );
    $validatorPetugas->prepareBatchCache($rowsPetugas);
    $validatedPetugas = $validatorPetugas->validateBatch($rowsPetugas);

    expect($validatedPetugas[0]['status'])->toBe('error');
    expect(collect($validatedPetugas[0]['messages'])->pluck('message')->implode(' '))->toContain('hanya memiliki hak akses');
});

test('validator memeriksa format nim, single query whereIn, dan flag allow_unknown_nim', function () {
    // NIM tidak terdaftar di database
    $rows = [
        [
            'row_number' => 1,
            'old_name' => 'doc1.pdf',
            'original_name' => 'doc1.pdf',
            'document_type' => 'KTM',
            'role' => 'Mahasiswa',
            'folder' => 'Folder Mahasiswa',
            'new_name' => 'KTM Mahasiswa',
            'nim' => ['9999999999'], // valid format tapi tidak terdaftar
            'keywords' => [],
        ],
    ];

    $dummyUploadedFiles = ['doc1.pdf' => sys_get_temp_dir() . '/doc1.pdf'];

    // Tanpa allow_unknown_nim: status ERROR
    $vError = new DocumentImportValidator('upload', $this->admin, allowUnknownNim: false, uploadedFiles: $dummyUploadedFiles);
    $vError->prepareBatchCache($rows);
    $resError = $vError->validateBatch($rows);
    expect($resError[0]['status'])->toBe('error');
    expect(collect($resError[0]['messages'])->pluck('message')->implode(' '))->toContain('tidak dikenal');

    // Dengan allow_unknown_nim: status WARNING
    $vWarn = new DocumentImportValidator('upload', $this->admin, allowUnknownNim: true, uploadedFiles: $dummyUploadedFiles);
    $vWarn->prepareBatchCache($rows);
    $resWarning = $vWarn->validateBatch($rows);
    expect($resWarning[0]['status'])->toBe('warning');
});

test('zip extractor menolak path traversal zip-slip dan mengekstrak berkas aman', function () {
    $extractor = app(DocumentImportZipExtractor::class);

    // Buat ZIP normal di temp
    $zipPath = tempnam(sys_get_temp_dir(), 'zip_test_') . '.zip';
    $zip = new ZipArchive();
    $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('file1.pdf', 'dummy content 1');
    $zip->addFromString('sub/file2.pdf', 'dummy content 2');
    $zip->close();

    $dest = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test_extract_' . uniqid();
    @mkdir($dest, 0777, true);

    $extracted = $extractor->extract($zipPath, $dest);
    @unlink($zipPath);

    expect($extracted)->toHaveCount(2);
    expect(array_keys($extracted))->toContain('file1.pdf');
    expect(array_keys($extracted))->toContain('file2.pdf');

    // Bersihkan direktori temp
    @unlink($dest . DIRECTORY_SEPARATOR . 'file1.pdf');
    @unlink($dest . DIRECTORY_SEPARATOR . 'file2.pdf');
    @rmdir($dest);
});

test('mode upload berhasil menyimpan dokumen ke storage yang sesuai dan menyinkronkan nim', function () {
    $batch = DocumentImportBatch::create([
        'user_id' => $this->admin->id,
        'mode' => 'upload',
        'status' => 'validated',
        'summary' => ['total' => 1, 'ok' => 1, 'warning' => 0, 'error' => 0, 'skipped' => 0],
    ]);

    $tempDir = "imports/{$batch->id}";
    Storage::disk('private')->put("{$tempDir}/sk_rektor.pdf", '%PDF-1.4 dummy pdf');

    $row = DocumentImportRow::create([
        'batch_id' => $batch->id,
        'row_number' => 1,
        'original_name' => 'sk_rektor.pdf',
        'status' => 'ok',
        'data' => [
            'matched_file' => "{$tempDir}/sk_rektor.pdf",
            'parsed_data' => [
                'new_name' => 'SK Rektor 2026',
                'document_type' => 'Surat Keputusan',
                'role' => 'Publik',
                'folder' => 'Folder Publik',
                'category_id' => $this->folderPublik->id,
                'nim' => [],
                'keywords' => ['sk', 'rektor'],
            ],
            'file_match' => [
                'matched_file' => 'sk_rektor.pdf',
                'storage_path' => "{$tempDir}/sk_rektor.pdf",
                'size' => 18,
                'mime' => 'application/pdf',
                'ext' => 'pdf',
            ],
        ],
    ]);

    // Jalankan Job pemrosesan
    $job = new ProcessDocumentImportBatch($batch->id, $this->admin->id);
    $job->handle(app(DocumentImportValidator::class));

    $batch->refresh();
    expect($batch->status)->toBe('completed');

    $row->refresh();
    expect($row->applied_at)->not->toBeNull();
    expect($row->document_id)->not->toBeNull();

    $doc = Document::find($row->document_id);
    expect($doc)->not->toBeNull();
    expect($doc->title)->toBe('SK Rektor 2026');
    expect($doc->document_type)->toBe('Surat Keputusan');
    expect($doc->access_group)->toBe(AccessGroup::PUBLIK);
    expect($doc->storage_disk)->toBe('public');
    expect($doc->checksum)->not->toBeNull();
    expect(Storage::disk('public')->exists($doc->file_path))->toBeTrue();
});

test('mode update memperbarui dokumen, mencatat diff dan log aktivitas tanpa menghapus berkas fisik', function () {
    $oldFilePath = 'documents/' . uniqid() . '.pdf';
    Storage::disk('public')->put($oldFilePath, '%PDF-1.4 old content');

    $doc = Document::create([
        'title' => 'Judul Dokumen Lama',
        'file_name' => 'old_file.pdf',
        'original_file_name' => 'old_file.pdf',
        'file_path' => $oldFilePath,
        'file_type' => 'pdf',
        'file_size' => 20,
        'storage_disk' => 'public',
        'access_group' => AccessGroup::PUBLIK,
        'document_category_id' => $this->folderPublik->id,
        'created_by' => $this->admin->id,
    ]);

    $batch = DocumentImportBatch::create([
        'user_id' => $this->admin->id,
        'mode' => 'update',
        'status' => 'validated',
        'summary' => ['total' => 1, 'ok' => 1, 'warning' => 0, 'error' => 0, 'skipped' => 0],
    ]);

    $row = DocumentImportRow::create([
        'batch_id' => $batch->id,
        'row_number' => 1,
        'original_name' => 'old_file.pdf',
        'document_id' => $doc->id,
        'status' => 'ok',
        'data' => [
            'matched_document_id' => $doc->id,
            'parsed_data' => [
                'new_name' => 'Judul Dokumen Diperbarui',
                'document_type' => 'Pedoman',
                'role' => 'Publik',
                'folder' => 'Folder Publik',
                'nim' => [],
                'keywords' => ['revisi'],
            ],
            'diff' => [
                'title' => ['old' => 'Judul Dokumen Lama', 'new' => 'Judul Dokumen Diperbarui'],
            ],
        ],
    ]);

    $job = new ProcessDocumentImportBatch($batch->id, $this->admin->id);
    $job->handle(app(DocumentImportValidator::class));

    $doc->refresh();
    expect($doc->title)->toBe('Judul Dokumen Diperbarui');
    expect($doc->document_type)->toBe('Pedoman');
    expect(Storage::disk('public')->exists($doc->file_path))->toBeTrue();
});

test('mode update perpindahan kategori antar disk memindahkan berkas secara aman', function () {
    $oldPath = 'documents/move_test.pdf';
    Storage::disk('public')->put($oldPath, '%PDF-1.4 move test content');

    $doc = Document::create([
        'title' => 'Dokumen Pindah Kategori',
        'file_name' => 'move_test.pdf',
        'original_file_name' => 'move_test.pdf',
        'file_path' => $oldPath,
        'file_type' => 'pdf',
        'file_size' => 25,
        'storage_disk' => 'public',
        'access_group' => AccessGroup::PUBLIK,
        'document_category_id' => $this->folderPublik->id,
        'created_by' => $this->admin->id,
    ]);

    // Pindahkan dari folder publik ke folder akademik (private)
    $doc->moveToCategory($this->folderAkademik);

    $doc->refresh();
    expect($doc->storage_disk)->toBe('private');
    expect($doc->access_group)->toBe(AccessGroup::AKADEMIK);
    expect(Storage::disk('private')->exists($doc->file_path))->toBeTrue();
    expect(Storage::disk('public')->exists($oldPath))->toBeFalse();
});

test('download filename menggunakan judul dan ekstensi asli serta header rfc5987', function () {
    $doc = new Document([
        'title' => 'Laporan Akhir: "Tahun 2026" / Versi Baru * Final?',
        'original_file_name' => 'upload_raw.pdf',
        'file_name' => 'stored_hash.pdf',
    ]);

    $downloadName = $doc->getDownloadFilename();
    expect($downloadName)->not->toContain('/');
    expect($downloadName)->not->toContain('\\');
    expect($downloadName)->not->toContain(':');
    expect($downloadName)->not->toContain('*');
    expect($downloadName)->not->toContain('?');
    expect($downloadName)->not->toContain('"');
    expect($downloadName)->toEndWith('.pdf');

    $disposition = $doc->getContentDisposition('attachment');
    expect($disposition)->toContain('attachment;');
    expect($disposition)->toContain('filename=');
    expect($disposition)->toContain("filename*=UTF-8''");
});

test('dokumen saya di portal publik dapat memfilter berdasarkan jenis dokumen', function () {
    $docA = Document::create([
        'title' => 'Transkrip Nilai',
        'file_name' => 'transkrip.pdf',
        'original_file_name' => 'transkrip.pdf',
        'file_path' => 'documents/transkrip.pdf',
        'file_type' => 'pdf',
        'file_size' => 10,
        'storage_disk' => 'private',
        'access_group' => AccessGroup::MAHASISWA,
        'document_type' => 'Transkrip',
        'document_category_id' => $this->folderMahasiswa->id,
        'created_by' => $this->admin->id,
    ]);
    DocumentNim::create(['document_id' => $docA->id, 'nim' => '1234567890']);

    $docB = Document::create([
        'title' => 'KRS Semester Ganjil',
        'file_name' => 'krs.pdf',
        'original_file_name' => 'krs.pdf',
        'file_path' => 'documents/krs.pdf',
        'file_type' => 'pdf',
        'file_size' => 10,
        'storage_disk' => 'private',
        'access_group' => AccessGroup::MAHASISWA,
        'document_type' => 'KRS',
        'document_category_id' => $this->folderMahasiswa->id,
        'created_by' => $this->admin->id,
    ]);
    DocumentNim::create(['document_id' => $docB->id, 'nim' => '1234567890']);

    // Filter type = Transkrip
    $resp = $this->actingAs($this->mhs1)->get(route('public_documents.index', ['document_type' => 'Transkrip']));
    $resp->assertOk();
    $resp->assertSee('Transkrip Nilai');
    $resp->assertDontSee('KRS Semester Ganjil');
});

test('prune command membersihkan batch kedaluwarsa dan berkas sementaranya', function () {
    $oldBatch = DocumentImportBatch::create([
        'user_id' => $this->admin->id,
        'mode' => 'upload',
        'status' => 'staging',
        'summary' => [],
    ]);
    DB::table('document_import_batches')->where('id', $oldBatch->id)->update([
        'created_at' => now()->subDays(8),
    ]);

    Storage::disk('private')->put("imports/{$oldBatch->id}/dummy.pdf", 'old');

    $newBatch = DocumentImportBatch::create([
        'user_id' => $this->admin->id,
        'mode' => 'upload',
        'status' => 'staging',
        'summary' => [],
    ]);
    Storage::disk('private')->put("imports/{$newBatch->id}/dummy.pdf", 'new');

    $this->artisan('documents:prune-import-batches')
        ->expectsOutputToContain('Berhasil membersihkan')
        ->assertSuccessful();

    // Old batch deleted
    expect(DocumentImportBatch::find($oldBatch->id))->toBeNull();
    expect(Storage::disk('private')->exists("imports/{$oldBatch->id}"))->toBeFalse();

    // New batch retained
    expect(DocumentImportBatch::find($newBatch->id))->not->toBeNull();
    expect(Storage::disk('private')->exists("imports/{$newBatch->id}/dummy.pdf"))->toBeTrue();
});

test('halaman preview mengunci tombol konfirmasi jika ada baris error dan membuka saat dilewati', function () {
    $batch = DocumentImportBatch::create([
        'user_id' => $this->admin->id,
        'mode' => 'upload',
        'status' => 'staging',
        'summary' => ['total' => 1, 'ok' => 0, 'warning' => 0, 'error' => 1, 'skipped' => 0],
    ]);

    $row = DocumentImportRow::create([
        'batch_id' => $batch->id,
        'row_number' => 1,
        'original_name' => 'error.pdf',
        'status' => 'error',
        'messages' => [['type' => 'error', 'message' => 'Berkas tidak ditemukan']],
        'data' => [],
    ]);

    $resp = $this->actingAs($this->admin)->get(route('documents.import.preview', $batch));
    $resp->assertOk();
    $resp->assertSee('Terdapat baris bermasalah (Error)');

    // Konfirmasi langsung harus ditolak karena ada baris error
    $confirmResp = $this->actingAs($this->admin)->post(route('documents.import.confirm', $batch));
    $confirmResp->assertSessionHasErrors('confirm');

    // Toggle skip baris error
    $this->actingAs($this->admin)->post(route('documents.import.row.toggle-skip', [$batch, $row]));
    $row->refresh();
    expect($row->status)->toBe('skipped');

    // Sekarang konfirmasi diperbolehkan
    $confirmResp2 = $this->actingAs($this->admin)->post(route('documents.import.confirm', $batch));
    $confirmResp2->assertSessionHasNoErrors();
    $batch->refresh();
    expect($batch->status)->toBe('processing');
});
