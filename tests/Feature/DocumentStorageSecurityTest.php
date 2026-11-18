<?php

use App\Enums\AccessGroup;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
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
    Permission::firstOrCreate(['name' => 'document_category_read']);
    Permission::firstOrCreate(['name' => 'document_category_create']);

    $this->admin = User::factory()->create(['status' => 'active']);
    $this->admin->assignRole('admin');
    $this->admin->givePermissionTo([
        'document_create', 'document_read', 'document_update', 'document_delete',
        'document_category_create', 'document_category_read', 'public_document_read'
    ]);

    $this->akademik = User::factory()->create(['status' => 'active']);
    $this->akademik->assignRole('Akademik');
    $this->akademik->givePermissionTo([
        'document_create', 'document_read', 'document_update', 'document_delete',
        'document_category_create', 'document_category_read', 'public_document_read'
    ]);

    $this->petugas = User::factory()->create(['status' => 'active']);
    $this->petugas->assignRole('Petugas');
    $this->petugas->givePermissionTo([
        'public_document_read', 'document_read', 'document_create', 'document_category_create'
    ]);

    $this->mhsOwner = User::factory()->create(['status' => 'active', 'employee_id' => '012345']);
    $this->mhsOwner->assignRole('mahasiswa');
    $this->mhsOwner->givePermissionTo(['public_document_read', 'document_read']);

    $this->mhsOther = User::factory()->create(['status' => 'active', 'employee_id' => '999999']);
    $this->mhsOther->assignRole('mahasiswa');
    $this->mhsOther->givePermissionTo(['public_document_read', 'document_read']);

    // Kategori
    $this->catPublik = DocumentCategory::factory()->publik()->create(['name' => 'Kategori Publik']);
    $this->catDosenStaf = DocumentCategory::factory()->dosenStaf()->create(['name' => 'Kategori Dosen Staf']);
    $this->catAkademik = DocumentCategory::factory()->akademik()->create(['name' => 'Kategori Akademik']);
    $this->catMahasiswa = DocumentCategory::factory()->mahasiswa()->create(['name' => 'Kategori Mahasiswa']);
});

test('upload dokumen baru kategori publik tersimpan di disk public dan non-publik tersimpan di disk private', function () {
    $filePublik = UploadedFile::fake()->create('panduan.pdf', 100, 'application/pdf');
    $filePrivate = UploadedFile::fake()->create('rapat.pdf', 100, 'application/pdf');

    // 1. Upload ke kategori publik
    $resPublik = $this->actingAs($this->admin)->post(route('documents.store'), [
        'document_category_id' => $this->catPublik->id,
        'visibility'           => 'publik',
        'files'                => [$filePublik],
    ]);
    $resPublik->assertRedirect();

    $docPublik = Document::where('document_category_id', $this->catPublik->id)->first();
    expect($docPublik)->not->toBeNull();
    expect($docPublik->storage_disk)->toBe('public');
    expect($docPublik->checksum)->not->toBeNull();
    expect(Storage::disk('public')->exists($docPublik->file_path))->toBeTrue();
    expect(Storage::disk('private')->exists($docPublik->file_path))->toBeFalse();

    // 2. Upload ke kategori non-publik (dosen_staf)
    $resPrivate = $this->actingAs($this->admin)->post(route('documents.store'), [
        'document_category_id' => $this->catDosenStaf->id,
        'visibility'           => 'internal',
        'files'                => [$filePrivate],
    ]);
    $resPrivate->assertRedirect();

    $docPrivate = Document::where('document_category_id', $this->catDosenStaf->id)->first();
    expect($docPrivate)->not->toBeNull();
    expect($docPrivate->storage_disk)->toBe('private');
    expect($docPrivate->checksum)->not->toBeNull();
    // Harus ada di disk private dan TIDAK ADA di disk public
    expect(Storage::disk('private')->exists($docPrivate->file_path))->toBeTrue();
    expect(Storage::disk('public')->exists($docPrivate->file_path))->toBeFalse();
});

test('resolusi disk dan download/preview terpusat memvalidasi policy dan melayani berkas dengan aman', function () {
    // Siapkan dokumen publik di disk public
    $pathPublik = 'dokumen/test_publik.pdf';
    Storage::disk('public')->put($pathPublik, '%PDF-1.4 dummy public content');
    $docPublik = Document::factory()->publik()->create([
        'document_category_id' => $this->catPublik->id,
        'file_path'            => $pathPublik,
        'file_name'            => 'test_publik.pdf',
        'file_type'            => 'pdf',
        'storage_disk'         => 'public',
    ]);

    // Siapkan dokumen privat di disk private (dosen_staf)
    $pathDosenStaf = 'dokumen/test_private.pdf';
    Storage::disk('private')->put($pathDosenStaf, '%PDF-1.4 dummy pdf content');
    $docDosenStaf = Document::factory()->dosenStaf()->create([
        'document_category_id' => $this->catDosenStaf->id,
        'file_path'            => $pathDosenStaf,
        'file_name'            => 'test_private.pdf',
        'file_type'            => 'pdf',
        'storage_disk'         => 'private',
    ]);

    // Siapkan dokumen akademik di disk private
    $pathAkademik = 'dokumen/test_akademik.pdf';
    Storage::disk('private')->put($pathAkademik, '%PDF-1.4 akademik pdf content');
    $docAkademik = Document::factory()->akademik()->create([
        'document_category_id' => $this->catAkademik->id,
        'file_path'            => $pathAkademik,
        'file_name'            => 'test_akademik.pdf',
        'file_type'            => 'pdf',
        'storage_disk'         => 'private',
    ]);

    // Siapkan dokumen mahasiswa di disk private
    $pathMhs = 'dokumen/test_mhs.pdf';
    Storage::disk('private')->put($pathMhs, '%PDF-1.4 student pdf content');
    $docMhs = Document::factory()->mahasiswa('012345')->create([
        'document_category_id' => $this->catMahasiswa->id,
        'owner_nim'            => '012345',
        'file_path'            => $pathMhs,
        'file_name'            => 'test_mhs.pdf',
        'file_type'            => 'pdf',
        'storage_disk'         => 'private',
    ]);

    // a. Guest ditolak download/preview dokumen non-publik pada rute internal
    $this->get(route('documents.download', $docDosenStaf))->assertRedirect(route('login'));
    $this->get(route('documents.preview', $docDosenStaf))->assertRedirect(route('login'));

    // b. Mahasiswa NIM lain ditolak; mahasiswa pemilik boleh
    $this->actingAs($this->mhsOther)->get(route('documents.download', $docMhs))->assertStatus(403);
    $this->actingAs($this->mhsOther)->get(route('documents.preview', $docMhs))->assertStatus(403);
    $this->actingAs($this->mhsOwner)->get(route('documents.download', $docMhs))->assertStatus(200);
    $this->actingAs($this->mhsOwner)->get(route('documents.preview', $docMhs))->assertStatus(200);

    // c. Petugas boleh dosen_staf, ditolak akademik dan mahasiswa
    $this->actingAs($this->petugas)->get(route('documents.download', $docDosenStaf))->assertStatus(200);
    $this->actingAs($this->petugas)->get(route('documents.preview', $docDosenStaf))->assertStatus(200);
    $this->actingAs($this->petugas)->get(route('documents.download', $docAkademik))->assertStatus(403);
    $this->actingAs($this->petugas)->get(route('documents.preview', $docAkademik))->assertStatus(403);
    $this->actingAs($this->petugas)->get(route('documents.download', $docMhs))->assertStatus(403);
    $this->actingAs($this->petugas)->get(route('documents.preview', $docMhs))->assertStatus(403);

    // d. Admin/Akademik boleh semua kategori (publik, dosen_staf, akademik, mahasiswa)
    foreach ([$this->admin, $this->akademik] as $privilegedUser) {
        foreach ([$docPublik, $docDosenStaf, $docAkademik, $docMhs] as $doc) {
            $this->actingAs($privilegedUser)->get(route('documents.download', $doc))->assertStatus(200);
            $this->actingAs($privilegedUser)->get(route('documents.preview', $doc))->assertStatus(200);
        }
    }

    // e. Berkas non-publik di disk private TIDAK ada di disk public dan tidak ada URL publik yang membukanya
    expect(config('filesystems.disks.private.url'))->toBeNull();
    expect(Storage::disk('public')->exists($pathDosenStaf))->toBeFalse();
    expect(Storage::disk('public')->exists($pathAkademik))->toBeFalse();
    expect(Storage::disk('public')->exists($pathMhs))->toBeFalse();
    $this->get(route('public_documents.show', $docDosenStaf))->assertStatus(404);
    $this->get(route('public_documents.download', $docDosenStaf))->assertStatus(404);
    $this->get(route('public_documents.preview', $docDosenStaf))->assertStatus(404);

    // k. Preview mengirim header nosniff dan MIME application/pdf
    $resPreview = $this->actingAs($this->petugas)->get(route('documents.preview', $docDosenStaf));
    $resPreview->assertStatus(200);
    $resPreview->assertHeader('X-Content-Type-Options', 'nosniff');
    $resPreview->assertHeader('Content-Type', 'application/pdf');

    // j. Audit: download dan preview non-publik tercatat di activity log; download publik tidak dicatat
    $downloadLog = Activity::where('log_name', 'document_access')
        ->where('properties->action', 'download')
        ->where('properties->document_id', $docDosenStaf->id)
        ->first();
    expect($downloadLog)->not->toBeNull();
    expect($downloadLog->properties['document_id'])->toBe($docDosenStaf->id);
    expect($downloadLog->properties['action'])->toBe('download');
    expect($downloadLog->properties['access_group'])->toBe('dosen_staf');
    expect($downloadLog->causer_id)->toBe($this->petugas->id);

    $previewLog = Activity::where('log_name', 'document_access')
        ->where('properties->action', 'preview')
        ->where('properties->document_id', $docDosenStaf->id)
        ->first();
    expect($previewLog)->not->toBeNull();
    expect($previewLog->properties['action'])->toBe('preview');

    // Unduhan publik oleh admin tidak dicatat di activity log
    $publicLogs = Activity::where('log_name', 'document_access')
        ->where('properties->document_id', $docPublik->id)
        ->count();
    expect($publicLogs)->toBe(0);
});

test('dokumen legacy dengan storage_disk NULL tetap dapat diunduh dari disk public', function () {
    $pathLegacy = 'dokumen/legacy_doc.pdf';
    Storage::disk('public')->put($pathLegacy, '%PDF-1.4 legacy content');

    $legacyDoc = Document::factory()->publik()->create([
        'document_category_id' => $this->catPublik->id,
        'file_path'            => $pathLegacy,
        'file_name'            => 'legacy_doc.pdf',
        'file_type'            => 'pdf',
        'storage_disk'         => null, // Legacy null
    ]);

    expect($legacyDoc->getStorageDisk())->toBe('public');
    expect($legacyDoc->fileExists())->toBeTrue();

    $res = $this->actingAs($this->admin)->get(route('documents.download', $legacyDoc));
    $res->assertStatus(200);
});

test('bulk download ZIP hanya memuat dokumen yang boleh diakses dan membaca dari disk yang benar', function () {
    $pathPublik = 'dokumen/bulk_publik.pdf';
    Storage::disk('public')->put($pathPublik, 'publik content');

    $pathPrivate = 'dokumen/bulk_private.pdf';
    Storage::disk('private')->put($pathPrivate, 'private content');

    $pathMalicious = 'dokumen/bulk_evil.pdf';
    Storage::disk('public')->put($pathMalicious, 'evil content');

    $docPublik = Document::factory()->publik()->create([
        'title'                => 'bulk_publik',
        'document_category_id' => $this->catPublik->id,
        'file_path'            => $pathPublik,
        'file_name'            => 'bulk_publik.pdf',
        'storage_disk'         => 'public',
    ]);

    $docAkademik = Document::factory()->akademik()->create([
        'title'                => 'bulk_private',
        'document_category_id' => $this->catAkademik->id,
        'file_path'            => $pathPrivate,
        'file_name'            => 'bulk_private.pdf',
        'storage_disk'         => 'private',
    ]);

    // Dokumen publik dengan nama file mengandung path traversal dan karakter aneh
    $docMalicious = Document::factory()->publik()->create([
        'title'                => '../../etc/passwd',
        'document_category_id' => $this->catPublik->id,
        'file_path'            => $pathMalicious,
        'file_name'            => '../../etc/passwd.pdf',
        'storage_disk'         => 'public',
    ]);

    // Petugas request unduh massal memilih ketiga dokumen
    $res = $this->actingAs($this->petugas)->post(route('documents.bulk-download'), [
        'doc_ids' => [$docPublik->id, $docAkademik->id, $docMalicious->id],
    ]);

    $res->assertStatus(200);
    $res->assertHeader('content-disposition');

    // Buka berkas ZIP dan periksa entri di dalamnya
    $zipFile = $res->getFile();
    $zip = new ZipArchive();
    expect($zip->open($zipFile->getPathname()))->toBeTrue();

    $entryNames = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entryNames[] = $zip->getNameIndex($i);
    }
    $zip->close();

    // 1. Dokumen yang tidak berwenang (Akademik) TIDAK masuk ke dalam ZIP dan namanya tidak bocor
    expect($entryNames)->not->toContain('bulk_private.pdf');

    // 2. Dokumen yang berwenang (Publik) ada di dalam ZIP
    expect($entryNames)->toContain('bulk_publik.pdf');

    // 3. Nama entri disanitasi: tidak boleh memuat '../' atau '..\\' (anti zip-slip)
    foreach ($entryNames as $name) {
        expect($name)->not->toContain('../');
        expect($name)->not->toContain('..\\');
    }
    expect(collect($entryNames)->contains(fn($name) => str_contains($name, 'passwd.pdf')))->toBeTrue();
});

test('pindah folder lintas root memindahkan berkas antar disk secara atomik dan konsisten', function () {
    $pathFile = 'dokumen/pindah_test.pdf';
    Storage::disk('public')->put($pathFile, 'isi berkas coba pindah');

    // Folder root publik dan subfoldernya
    $subfolder = DocumentCategory::factory()->create([
        'name'         => 'Subfolder Awal',
        'parent_id'    => $this->catPublik->id,
        'access_group' => AccessGroup::PUBLIK->value,
    ]);

    $doc = Document::factory()->publik()->create([
        'document_category_id' => $subfolder->id,
        'file_path'            => $pathFile,
        'file_name'            => 'pindah_test.pdf',
        'storage_disk'         => 'public',
    ]);

    expect(Storage::disk('public')->exists($pathFile))->toBeTrue();
    expect(Storage::disk('private')->exists($pathFile))->toBeFalse();

    // Pindah subfolder dari root publik ke root dosen_staf (pindah ke private)
    $subfolder->moveTo($this->catDosenStaf->id);

    $doc->refresh();
    expect($doc->access_group->value)->toBe(AccessGroup::DOSEN_STAF->value);
    expect($doc->storage_disk)->toBe('private');

    // Berkas fisik sekarang harus ada di disk private dan tidak ada di public
    expect(Storage::disk('private')->exists($pathFile))->toBeTrue();
    expect(Storage::disk('public')->exists($pathFile))->toBeFalse();
});

test('moveTo lintas root jika penyalinan gagal di tengah, berkas asli tidak hilang dan data konsisten (rollback)', function () {
    $path1 = 'dokumen/pindah_fail_1.pdf';
    $path2 = 'dokumen/pindah_fail_2.pdf';
    Storage::disk('public')->put($path1, 'isi berkas 1');
    Storage::disk('public')->put($path2, 'isi berkas 2');

    $subfolder = DocumentCategory::factory()->create([
        'name'         => 'Subfolder Gagal Pindah',
        'parent_id'    => $this->catPublik->id,
        'access_group' => AccessGroup::PUBLIK->value,
    ]);

    $doc1 = Document::factory()->publik()->create([
        'document_category_id' => $subfolder->id,
        'file_path'            => $path1,
        'file_name'            => 'pindah_fail_1.pdf',
        'storage_disk'         => 'public',
    ]);

    $doc2 = Document::factory()->publik()->create([
        'document_category_id' => $subfolder->id,
        'file_path'            => $path2,
        'file_name'            => 'pindah_fail_2.pdf',
        'storage_disk'         => 'public',
    ]);

    // Simulasikan disk private gagal ketika menulis berkas file2
    $realPrivateDisk = Storage::disk('private');
    $mockPrivateDisk = Mockery::mock($realPrivateDisk)->makePartial();
    $mockPrivateDisk->shouldReceive('put')
        ->with($path1, Mockery::any())
        ->andReturnUsing(function ($path, $resource) use ($realPrivateDisk) {
            return $realPrivateDisk->put($path, $resource);
        });
    $mockPrivateDisk->shouldReceive('put')
        ->with($path2, Mockery::any())
        ->andThrow(new \RuntimeException('Simulasi disk error di berkas ke-2'));

    Storage::set('private', $mockPrivateDisk);

    $exceptionThrown = false;
    try {
        $subfolder->moveTo($this->catDosenStaf->id);
    } catch (\Throwable $e) {
        $exceptionThrown = true;
    }

    expect($exceptionThrown)->toBeTrue();

    // Pulihkan disk private asli setelah mock selesai
    Storage::set('private', $realPrivateDisk);

    // Verifikasi berkas asli di disk public TIDAK HILANG
    expect(Storage::disk('public')->exists($path1))->toBeTrue();
    expect(Storage::disk('public')->exists($path2))->toBeTrue();

    // Verifikasi residu di disk private dibersihkan
    expect(Storage::disk('private')->exists($path1))->toBeFalse();
    expect(Storage::disk('private')->exists($path2))->toBeFalse();

    // Verifikasi database di-rollback ke kondisi awal
    $subfolder->refresh();
    expect($subfolder->parent_id)->toBe($this->catPublik->id);
    expect($subfolder->access_group->value)->toBe(AccessGroup::PUBLIK->value);

    $doc1->refresh();
    $doc2->refresh();
    expect($doc1->storage_disk)->toBe('public');
    expect($doc1->access_group->value)->toBe(AccessGroup::PUBLIK->value);
    expect($doc2->storage_disk)->toBe('public');
    expect($doc2->access_group->value)->toBe(AccessGroup::PUBLIK->value);
});

test('command documents:migrate-storage menyalin berkas non-publik ke private dan opsi cleanup bekerja', function () {
    $filePath = 'dokumen/file_lama.pdf';
    Storage::disk('public')->put($filePath, 'berkas internal di disk publik');

    $doc = Document::factory()->dosenStaf()->create([
        'document_category_id' => $this->catDosenStaf->id,
        'file_path'            => $filePath,
        'file_name'            => 'file_lama.pdf',
        'storage_disk'         => 'public',
    ]);

    // Dokumen dengan berkas hilang di disk public
    $docMissing = Document::factory()->dosenStaf()->create([
        'document_category_id' => $this->catDosenStaf->id,
        'file_path'            => 'dokumen/file_hilang.pdf',
        'file_name'            => 'file_hilang.pdf',
        'storage_disk'         => 'public',
    ]);

    // 1. Dry run: tidak mengubah berkas atau data
    $this->artisan('documents:migrate-storage', ['--dry-run' => true])
        ->expectsOutputToContain('DRY-RUN')
        ->assertSuccessful();

    $doc->refresh();
    expect($doc->storage_disk)->toBe('public');
    expect(Storage::disk('private')->exists($filePath))->toBeFalse();

    // 2. Eksekusi migrasi: menyalin ke private dan memperbarui storage_disk='private'
    // Dokumen yang berkasnya hilang dilewati dan storage_disk tetap public
    $this->artisan('documents:migrate-storage')
        ->expectsOutputToContain('Berhasil disalin  : 1')
        ->expectsOutputToContain('Dilewati (hilang) : 1')
        ->assertSuccessful();

    $doc->refresh();
    expect($doc->storage_disk)->toBe('private');
    expect(Storage::disk('private')->exists($filePath))->toBeTrue();
    // Berkas asli di public tetap ADA
    expect(Storage::disk('public')->exists($filePath))->toBeTrue();

    $docMissing->refresh();
    expect($docMissing->storage_disk)->toBe('public');

    // 3. Jalankan lagi: idempoten (dokumen yang sudah private tidak diproses ulang, docMissing tetap dilewati)
    $this->artisan('documents:migrate-storage')
        ->expectsOutputToContain('Berhasil disalin  : 0')
        ->expectsOutputToContain('Dilewati (hilang) : 1')
        ->assertSuccessful();

    // 4. Opsi cleanup tanpa --force: konfirmasi 'no' membatalkan penghapusan
    $this->artisan('documents:migrate-storage', ['--cleanup' => true])
        ->expectsConfirmation('Apakah Anda yakin ingin menghapus berkas salinan lama di disk public untuk dokumen yang sudah terverifikasi di disk private?', 'no')
        ->expectsOutputToContain('Operasi cleanup dibatalkan.')
        ->assertSuccessful();

    expect(Storage::disk('public')->exists($filePath))->toBeTrue();

    // 5. Opsi cleanup dengan konfirmasi 'yes': menghapus salinan di public
    $this->artisan('documents:migrate-storage', ['--cleanup' => true])
        ->expectsConfirmation('Apakah Anda yakin ingin menghapus berkas salinan lama di disk public untuk dokumen yang sudah terverifikasi di disk private?', 'yes')
        ->expectsOutputToContain('Salinan public dihapus : 1')
        ->assertSuccessful();

    expect(Storage::disk('private')->exists($filePath))->toBeTrue();
    expect(Storage::disk('public')->exists($filePath))->toBeFalse();
});

test('force delete menghapus berkas fisik dari storage disk dan soft delete tidak menghapus berkas', function () {
    $filePath = 'dokumen/delete_me.pdf';
    Storage::disk('private')->put($filePath, 'konten file');

    $doc = Document::factory()->dosenStaf()->create([
        'document_category_id' => $this->catDosenStaf->id,
        'file_path'            => $filePath,
        'file_name'            => 'delete_me.pdf',
        'storage_disk'         => 'private',
    ]);

    // Soft delete: berkas fisik TETAP ADA
    $doc->delete();
    expect(Storage::disk('private')->exists($filePath))->toBeTrue();

    // Force delete: berkas fisik TERHAPUS
    $doc->forceDelete();
    expect(Storage::disk('private')->exists($filePath))->toBeFalse();
});
