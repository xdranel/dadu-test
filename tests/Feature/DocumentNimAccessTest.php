<?php

use App\Enums\AccessGroup;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentNim;
use App\Models\User;
use Illuminate\Database\QueryException;
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

    Storage::fake('public');
    Storage::fake('private');

    // Roles & Permissions
    Role::firstOrCreate(['name' => 'admin']);
    Role::firstOrCreate(['name' => 'Akademik']);
    Role::firstOrCreate(['name' => 'Petugas']);
    Role::firstOrCreate(['name' => 'Pelaksana']);
    Role::firstOrCreate(['name' => 'mahasiswa']);

    Permission::firstOrCreate(['name' => 'public_document_read']);
    Permission::firstOrCreate(['name' => 'document_read']);
    Permission::firstOrCreate(['name' => 'document_create']);
    Permission::firstOrCreate(['name' => 'document_update']);
    Permission::firstOrCreate(['name' => 'document_delete']);

    $this->admin = User::factory()->create(['status' => 'active']);
    $this->admin->assignRole('admin');
    $this->admin->givePermissionTo(['document_read', 'document_update', 'public_document_read']);

    $this->mhs1 = User::factory()->create(['status' => 'active', 'employee_id' => '0123456789']);
    $this->mhs1->assignRole('mahasiswa');
    $this->mhs1->givePermissionTo(['public_document_read', 'document_read']);

    $this->mhs2 = User::factory()->create(['status' => 'active', 'employee_id' => '0987654321']);
    $this->mhs2->assignRole('mahasiswa');
    $this->mhs2->givePermissionTo(['public_document_read', 'document_read']);

    $this->mhsOther = User::factory()->create(['status' => 'active', 'employee_id' => '1122334455']);
    $this->mhsOther->assignRole('mahasiswa');
    $this->mhsOther->givePermissionTo(['public_document_read', 'document_read']);

    // Dosen dengan NIP/NIK angka yang kebetulan sama dengan NIM mhs1
    $this->dosen = User::factory()->create(['status' => 'active', 'employee_id' => '0123456789']);
    $this->dosen->assignRole('Pelaksana');
    $this->dosen->givePermissionTo(['public_document_read', 'document_read']);

    $this->catMahasiswa = DocumentCategory::factory()->mahasiswa()->create(['name' => 'Kategori Mahasiswa']);
    $this->catPublik    = DocumentCategory::factory()->publik()->create(['name' => 'Kategori Publik']);
});

test('skema migration document_nim: tabel terbentuk, unique constraint berlaku, dan cascade delete bekerja', function () {
    expect(Schema::hasTable('document_nim'))->toBeTrue();
    expect(Schema::hasColumns('document_nim', ['id', 'document_id', 'nim', 'created_at', 'updated_at']))->toBeTrue();

    $doc = Document::factory()->mahasiswa()->create();
    $doc->syncNims(['0123456789']);

    expect(DocumentNim::where('document_id', $doc->id)->where('nim', '0123456789')->exists())->toBeTrue();

    // Unique constraint: memasukkan NIM yang sama pada dokumen yang sama melempar QueryException
    expect(function () use ($doc) {
        DocumentNim::create([
            'document_id' => $doc->id,
            'nim'         => '0123456789',
        ]);
    })->toThrow(QueryException::class);

    // Cascade delete: menghapus dokumen otomatis menghapus baris di document_nim
    $docId = $doc->id;
    $doc->forceDelete();
    expect(DocumentNim::where('document_id', $docId)->exists())->toBeFalse();
});

test('normalisasi NIM: rentang dinamis config (8, 10, 12 digit valid), leading zero aman, non-digit dan luar rentang ditolak', function () {
    // 8 digit valid
    $nim8 = Document::normalizeNim('12345678');
    expect($nim8)->toBe('12345678');

    // 10 digit dengan leading zero aman
    $nim10LeadingZero = Document::normalizeNim('  0123456789  ');
    expect($nim10LeadingZero)->toBe('0123456789');

    // 10 digit tanpa leading zero (string persis tidak sama)
    $nim10Other = Document::normalizeNim('1234567890');
    expect($nim10LeadingZero)->not->toBe($nim10Other);

    // 12 digit valid
    $nim12 = Document::normalizeNim('123456789012');
    expect($nim12)->toBe('123456789012');

    // Kurang dari rentang min (7 digit) -> ditolak
    expect(fn () => Document::normalizeNim('1234567'))->toThrow(\InvalidArgumentException::class);

    // Lebih dari rentang max default (16 digit) -> ditolak
    expect(fn () => Document::normalizeNim('1234567890123456'))->toThrow(\InvalidArgumentException::class);

    // Mengandung huruf / karakter spesial / spasi di tengah -> ditolak
    expect(fn () => Document::normalizeNim('012345678A'))->toThrow(\InvalidArgumentException::class);
    expect(fn () => Document::normalizeNim('01234-6789'))->toThrow(\InvalidArgumentException::class);
    expect(fn () => Document::normalizeNim('01234 6789'))->toThrow(\InvalidArgumentException::class);
});

test('portal publik (/public-documents): dokumen mahasiswa hanya untuk pemilik & multi-NIM, guest/dosen/NIM lain 404 identik, non-pdf 415, audit tercatat', function () {
    // Siapkan berkas fisik di disk private
    $pathSingle = 'dokumen/single.pdf';
    $pathMulti  = 'dokumen/multi.pdf';
    $pathDocx   = 'dokumen/document.docx';
    $pathPublik = 'dokumen/panduan.pdf';

    Storage::disk('private')->put($pathSingle, '%PDF-1.4 single content');
    Storage::disk('private')->put($pathMulti, '%PDF-1.4 multi content');
    Storage::disk('private')->put($pathDocx, 'DOCX binary content');
    Storage::disk('public')->put($pathPublik, '%PDF-1.4 public guide');

    // 1. Dokumen mahasiswa dengan 1 NIM (mhs1)
    $docSingle = Document::factory()->create([
        'document_category_id' => $this->catMahasiswa->id,
        'access_group'         => AccessGroup::MAHASISWA,
        'file_path'            => $pathSingle,
        'file_name'            => 'single.pdf',
        'file_type'            => 'pdf',
        'storage_disk'         => 'private',
    ]);
    $docSingle->syncNims(['0123456789']);

    // 2. Dokumen mahasiswa dengan 2 NIM (mhs1 dan mhs2)
    $docMulti = Document::factory()->create([
        'document_category_id' => $this->catMahasiswa->id,
        'access_group'         => AccessGroup::MAHASISWA,
        'file_path'            => $pathMulti,
        'file_name'            => 'multi.pdf',
        'file_type'            => 'pdf',
        'storage_disk'         => 'private',
    ]);
    $docMulti->syncNims(['0123456789', '0987654321']);

    // 3. Dokumen mahasiswa bertipe non-PDF (docx)
    $docDocx = Document::factory()->create([
        'document_category_id' => $this->catMahasiswa->id,
        'access_group'         => AccessGroup::MAHASISWA,
        'file_path'            => $pathDocx,
        'file_name'            => 'document.docx',
        'file_type'            => 'docx',
        'storage_disk'         => 'private',
    ]);
    $docDocx->syncNims(['0123456789']);

    // 4. Dokumen publik
    $docPublik = Document::factory()->create([
        'document_category_id' => $this->catPublik->id,
        'access_group'         => AccessGroup::PUBLIK,
        'file_path'            => $pathPublik,
        'file_name'            => 'panduan.pdf',
        'file_type'            => 'pdf',
        'storage_disk'         => 'public',
        'download_count'       => 0,
    ]);

    // A. Guest mengakses rute publik untuk dokumen mahasiswa -> 404 identik
    $this->get(route('public_documents.show', $docSingle))->assertStatus(404);
    $this->get(route('public_documents.preview', $docSingle))->assertStatus(404);
    $this->get(route('public_documents.download', $docSingle))->assertStatus(404);

    // B. Dosen mengakses rute publik untuk dokumen mahasiswa -> 404 identik
    $this->actingAs($this->dosen)->get(route('public_documents.preview', $docSingle))->assertStatus(404);
    $this->actingAs($this->dosen)->get(route('public_documents.download', $docSingle))->assertStatus(404);

    // C. Mahasiswa NIM lain (mhsOther) -> 404 identik
    $this->actingAs($this->mhsOther)->get(route('public_documents.preview', $docSingle))->assertStatus(404);
    $this->actingAs($this->mhsOther)->get(route('public_documents.download', $docSingle))->assertStatus(404);

    // D. Mahasiswa pemilik (mhs1) -> BERHASIL lihat detail, preview, dan unduh
    $this->actingAs($this->mhs1)->get(route('public_documents.show', $docSingle))->assertStatus(200);
    $this->actingAs($this->mhs1)->get(route('public_documents.preview', $docSingle))->assertStatus(200);
    $this->actingAs($this->mhs1)->get(route('public_documents.download', $docSingle))->assertStatus(200);

    // E. Mahasiswa kedua pada dokumen multi-NIM (mhs2) -> BERHASIL unduh dan preview docMulti
    $this->actingAs($this->mhs2)->get(route('public_documents.preview', $docMulti))->assertStatus(200);
    $this->actingAs($this->mhs2)->get(route('public_documents.download', $docMulti))->assertStatus(200);

    // Tetapi mhs2 DITOLAK 404 pada docSingle (bukan miliknya)
    $this->actingAs($this->mhs2)->get(route('public_documents.download', $docSingle))->assertStatus(404);

    // F. Dokumen mahasiswa non-PDF di preview -> mengembalikan 415
    $this->actingAs($this->mhs1)->get(route('public_documents.preview', $docDocx))->assertStatus(415);

    // G. Audit activity log tercatat untuk akses dokumen mahasiswa
    $activityCount = Activity::where('log_name', 'student_document_access')->count();
    expect($activityCount)->toBeGreaterThan(0);

    $lastLog = Activity::where('log_name', 'student_document_access')->latest('id')->first();
    expect($lastLog->causer_id)->toBe($this->mhs2->id);
    expect($lastLog->properties['action'])->toBe('download');

    // H. Unduhan dokumen mahasiswa TIDAK menambah download_count
    $docSingle->refresh();
    expect($docSingle->download_count)->toBe(0);

    // I. Unduhan dokumen publik MENAMBAH download_count dan TIDAK mencatat log orang per-orang
    $initialPublikCount = $docPublik->download_count;
    $this->get(route('public_documents.download', $docPublik))->assertStatus(200);
    $docPublik->refresh();
    expect($docPublik->download_count)->toBe($initialPublikCount + 1);
});

test('command documents:backfill-nim: verifikasi tabel users, kelompok single, multi, zero, kandidat tidak dikenal masuk CSV, dan tidak memotong angka panjang', function () {
    // Daftarkan mahasiswa yang valid di sistem
    User::factory()->create(['status' => 'active', 'employee_id' => '1101210001'])->assignRole('mahasiswa');
    User::factory()->create(['status' => 'active', 'employee_id' => '1101210002'])->assignRole('mahasiswa');
    User::factory()->create(['status' => 'active', 'employee_id' => '1101210003'])->assignRole('mahasiswa');

    // 1. Doc Single: Tepat 1 NIM 10 digit terverifikasi
    $docSingle = Document::factory()->create([
        'document_category_id' => $this->catMahasiswa->id,
        'access_group'         => AccessGroup::MAHASISWA,
        'title'                => 'Laporan Tugas Akhir',
        'file_name'            => 'TA_1101210001_final.pdf',
    ]);

    // 2. Doc Multi: 2 NIM terverifikasi
    $docMulti = Document::factory()->create([
        'document_category_id' => $this->catMahasiswa->id,
        'access_group'         => AccessGroup::MAHASISWA,
        'title'                => 'Proyek Bersama 1101210002 dan 1101210003',
        'file_name'            => 'proyek_kelompok.pdf',
    ]);

    // 3. Doc Unknown: Memuat kandidat digit valid tetapi TIDAK dikenal di tabel users
    $docUnknown = Document::factory()->create([
        'document_category_id' => $this->catMahasiswa->id,
        'access_group'         => AccessGroup::MAHASISWA,
        'title'                => 'Dokumen Mahasiswa 99998888 Belum Terdaftar',
        'file_name'            => 'unknown.pdf',
    ]);

    // 4. Doc Zero: Angka bukan NIM (nomor surat pendek 4 digit atau angka panjang 16 digit timestamp)
    $docZero = Document::factory()->create([
        'document_category_id' => $this->catMahasiswa->id,
        'access_group'         => AccessGroup::MAHASISWA,
        'title'                => 'Surat No. 0001/SK/2026 Timestamp 2026111311000000',
        'file_name'            => 'sk_rektor.pdf',
    ]);

    // A. Dry Run: Menampilkan kelompok dan tidak mengisi pivot
    $this->artisan('documents:backfill-nim', ['--dry-run' => true])
        ->expectsOutputToContain('MODE DRY-RUN')
        ->expectsOutputToContain('Kelompok (a) Single NIM (langsung diisi) : 1')
        ->expectsOutputToContain('Kelompok (b) Multi NIM (pending review) : 1')
        ->expectsOutputToContain('Kelompok (c) Tanpa NIM (0 NIM)          : 1')
        ->expectsOutputToContain('Kelompok (d) Kandidat Tidak Dikenal     : 1')
        ->assertSuccessful();

    expect(DocumentNim::count())->toBe(0);

    // B. Eksekusi nyata tanpa --include-multi:
    // Kelompok (a) terisi, kelompok (b), (c), dan (d) masuk CSV review
    $this->artisan('documents:backfill-nim')
        ->expectsOutputToContain('Kelompok (a) Single NIM (langsung diisi) : 1')
        ->expectsOutputToContain('Kelompok (b) Multi NIM (pending review) : 1')
        ->expectsOutputToContain('Kelompok (c) Tanpa NIM (0 NIM)          : 1')
        ->expectsOutputToContain('Kelompok (d) Kandidat Tidak Dikenal     : 1')
        ->assertSuccessful();

    // Verifikasi kelompok (a) terisi di pivot
    expect($docSingle->nims()->pluck('nim')->toArray())->toEqual(['1101210001']);
    // Kelompok (b), (c), dan (d) belum terisi
    expect($docMulti->nims()->count())->toBe(0);
    expect($docUnknown->nims()->count())->toBe(0);
    expect($docZero->nims()->count())->toBe(0);

    // Verifikasi laporan CSV review tersimpan dengan status verifikasi
    $files = Storage::disk('private')->files('reports');
    $csvFile = collect($files)->first(fn($f) => str_starts_with($f, 'reports/review_nim_'));
    expect($csvFile)->not->toBeNull();
    $csvContent = Storage::disk('private')->get($csvFile);
    expect($csvContent)->toContain('1101210002;1101210003');
    expect($csvContent)->toContain('TERVERIFIKASI');
    expect($csvContent)->toContain('TIDAK_DIKENAL');
    expect($csvContent)->toContain('99998888');

    // C. Eksekusi dengan opsi --include-multi: Kelompok (b) sekarang terisi
    $this->artisan('documents:backfill-nim', ['--include-multi' => true])
        ->expectsOutputToContain('Kelompok (b) Multi NIM (diisi)          : 1')
        ->assertSuccessful();

    $docMulti->refresh();
    expect($docMulti->nims()->pluck('nim')->sort()->values()->toArray())->toEqual(['1101210002', '1101210003']);
});

test('command documents:import-nim-review: validasi rentang NIM baru, peringatan NIM tidak dikenal, dan flag --allow-unknown', function () {
    // Daftarkan mahasiswa yang valid
    User::factory()->create(['status' => 'active', 'employee_id' => '1101210010'])->assignRole('mahasiswa');
    User::factory()->create(['status' => 'active', 'employee_id' => '1101210011'])->assignRole('mahasiswa');
    User::factory()->create(['status' => 'active', 'employee_id' => '12345678'])->assignRole('mahasiswa'); // 8 digit

    $docA = Document::factory()->create([
        'document_category_id' => $this->catMahasiswa->id,
        'access_group'         => AccessGroup::MAHASISWA,
        'title'                => 'Dokumen A Review Staf',
    ]);

    $docB = Document::factory()->create([
        'document_category_id' => $this->catMahasiswa->id,
        'access_group'         => AccessGroup::MAHASISWA,
        'title'                => 'Dokumen B Review Staf',
    ]);

    $docC = Document::factory()->create([
        'document_category_id' => $this->catMahasiswa->id,
        'access_group'         => AccessGroup::MAHASISWA,
        'title'                => 'Dokumen C Review Staf',
    ]);

    // Siapkan file CSV review
    $csvPath = 'reports/staf_reviewed.csv';
    $csvData = "id,nim\n"
             . "{$docA->id},1101210010;1101210011\n" // 2 NIM terdaftar
             . "{$docB->id},12345678\n"              // 1 NIM 8 digit terdaftar
             . "{$docC->id},99887766\n"              // NIM tidak terdaftar (unknown)
             . "{$docA->id},12345\n";                // NIM tidak valid (< 8 digit)

    Storage::disk('private')->put($csvPath, $csvData);

    // 1. Eksekusi default (tanpa --allow-unknown): NIM 99887766 dilaporkan sebagai peringatan dan dilewati
    $this->artisan('documents:import-nim-review', ['file' => $csvPath])
        ->expectsOutputToContain('NIM \'99887766\' tidak terdaftar pada data mahasiswa')
        ->expectsOutputToContain('Dokumen berhasil diproses : 2')
        ->assertSuccessful();

    expect($docA->nims()->pluck('nim')->sort()->values()->toArray())->toEqual(['1101210010', '1101210011']);
    expect($docB->nims()->pluck('nim')->toArray())->toEqual(['12345678']);
    expect($docC->nims()->count())->toBe(0);

    // 2. Eksekusi dengan flag --allow-unknown: sekarang docC berhasil mengimpor NIM 99887766
    $this->artisan('documents:import-nim-review', ['file' => $csvPath, '--allow-unknown' => true])
        ->expectsOutputToContain('NIM \'99887766\' tidak dikenal, tetap diimpor')
        ->assertSuccessful();

    $docC->refresh();
    expect($docC->nims()->pluck('nim')->toArray())->toEqual(['99887766']);
});

test('tampilan Dokumen Saya di portal publik: hanya muncul untuk mahasiswa login, paginasi dan filter tahun, search terisolasi', function () {
    // Siapkan dokumen mhs1
    $docMhs1A = Document::factory()->create([
        'document_category_id' => $this->catMahasiswa->id,
        'access_group'         => AccessGroup::MAHASISWA,
        'title'                => 'Transkrip Nilai Akademik Krisna',
        'keywords'             => 'transkrip, nilai, semester 7',
        'file_type'            => 'pdf',
        'created_at'           => '2026-03-01 10:00:00',
    ]);
    $docMhs1A->syncNims(['0123456789']);

    $docMhs1B = Document::factory()->create([
        'document_category_id' => $this->catMahasiswa->id,
        'access_group'         => AccessGroup::MAHASISWA,
        'title'                => 'Sertifikat Lomba Robotik',
        'keywords'             => 'prestasi, robotik, nasional',
        'file_type'            => 'pdf',
        'created_at'           => '2025-06-15 10:00:00',
    ]);
    $docMhs1B->syncNims(['0123456789']);

    // Dokumen mahasiswa lain
    $docOther = Document::factory()->create([
        'document_category_id' => $this->catMahasiswa->id,
        'access_group'         => AccessGroup::MAHASISWA,
        'title'                => 'Transkrip Nilai Mahasiswa Lain Rahasia',
        'keywords'             => 'transkrip, rahasia',
        'file_type'            => 'pdf',
        'created_at'           => '2026-03-01 10:00:00',
    ]);
    $docOther->syncNims(['1122334455']);

    // Dokumen publik biasa
    $docPublik = Document::factory()->create([
        'document_category_id' => $this->catPublik->id,
        'access_group'         => AccessGroup::PUBLIK,
        'title'                => 'Buku Panduan Akademik 2026',
        'file_type'            => 'pdf',
        'created_at'           => '2026-01-01 10:00:00',
    ]);

    // 1. Guest: bagian "Dokumen Saya" TIDAK muncul sama sekali di HTML
    $guestResponse = $this->get(route('public_documents.index'));
    $guestResponse->assertStatus(200);
    $guestResponse->assertDontSee('Dokumen Saya');
    $guestResponse->assertSee('Buku Panduan Akademik 2026');
    $guestResponse->assertDontSee('Transkrip Nilai Akademik Krisna');

    // 2. Dosen: bagian "Dokumen Saya" TIDAK muncul
    $dosenResponse = $this->actingAs($this->dosen)->get(route('public_documents.index'));
    $dosenResponse->assertStatus(200);
    $dosenResponse->assertDontSee('Dokumen Saya');

    // 3. Mahasiswa (mhs1): bagian "Dokumen Saya" muncul dan hanya memuat dokumen miliknya
    $mhsResponse = $this->actingAs($this->mhs1)->get(route('public_documents.index'));
    $mhsResponse->assertStatus(200);
    $mhsResponse->assertSee('Dokumen Saya');
    $mhsResponse->assertSee('Transkrip Nilai Akademik Krisna');
    $mhsResponse->assertSee('Sertifikat Lomba Robotik');
    $mhsResponse->assertDontSee('Transkrip Nilai Mahasiswa Lain Rahasia');
    // Memastikan NIM mahasiswa lain tidak bocor di tampilan
    $mhsResponse->assertDontSee('1122334455');

    // 4. Pencarian kata kunci terisolasi: cari 'Transkrip' hanya menampilkan transkrip miliknya
    $searchResponse = $this->actingAs($this->mhs1)->get(route('public_documents.index', ['search' => 'Transkrip']));
    $searchResponse->assertStatus(200);
    $searchResponse->assertSee('Transkrip Nilai Akademik Krisna');
    $searchResponse->assertDontSee('Transkrip Nilai Mahasiswa Lain Rahasia');

    // 5. Pencarian kata kunci dokumen orang lain: tidak membocorkan dokumen orang lain
    $searchOther = $this->actingAs($this->mhs1)->get(route('public_documents.index', ['search' => 'Rahasia']));
    $searchOther->assertStatus(200);
    $searchOther->assertDontSee('Transkrip Nilai Mahasiswa Lain Rahasia');

    // 6. Filter tahun: filter tahun 2025 hanya menampilkan sertifikat lomba
    $yearResponse = $this->actingAs($this->mhs1)->get(route('public_documents.index', ['year' => 2025]));
    $yearResponse->assertStatus(200);
    $yearResponse->assertSee('Sertifikat Lomba Robotik');
    $yearResponse->assertDontSee('Transkrip Nilai Akademik Krisna');
});

test('admin dapat mengedit dan menyinkronkan NIM dokumen mahasiswa dengan rentang dinamis, perubahan pivot tercatat di activity log', function () {
    $doc = Document::factory()->create([
        'document_category_id' => $this->catMahasiswa->id,
        'access_group'         => AccessGroup::MAHASISWA,
        'title'                => 'Dokumen Sebelum Edit NIM',
    ]);
    $doc->syncNims(['0123456789']);

    // Admin mengupdate dokumen dan menambahkan NIM 8 digit dan 10 digit
    $response = $this->actingAs($this->admin)->put(route('documents.update', $doc->id), [
        'title'                => 'Dokumen Setelah Update NIM',
        'document_category_id' => $this->catMahasiswa->id,
        'visibility'           => 'publik',
        'nims'                 => ['0123456789', '12345678'],
    ]);

    $response->assertRedirect();

    $doc->refresh();
    expect($doc->title)->toBe('Dokumen Setelah Update NIM');
    expect($doc->nims()->pluck('nim')->sort()->values()->toArray())->toEqual(['12345678', '0123456789']);

    // Verifikasi activity log mencatat penambahan NIM
    $log = Activity::where('log_name', 'document_nim_sync')
        ->where('properties->document_id', $doc->id)
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->properties['added_nims'])->toContain('12345678');
    expect($log->causer_id)->toBe($this->admin->id);
});
