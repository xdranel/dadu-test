<?php

use App\Enums\AccessGroup;
use App\Models\Document;
use App\Models\DocumentCategory;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Document::query()->forceDelete();
    DocumentCategory::query()->forceDelete();
    Activity::query()->delete();
});

test('dry-run tidak mengubah data di database tetapi menampilkan jumlah', function () {
    $catPublik = DocumentCategory::factory()->legacy('publik')->create(['name' => 'Legacy Cat Publik']);
    $catInternal = DocumentCategory::factory()->legacy('internal')->create(['name' => 'Legacy Cat Internal']);
    $catPrivate = DocumentCategory::factory()->legacy('private')->create(['name' => 'Legacy Cat Private']);

    $subfolder = DocumentCategory::factory()->create([
        'name' => 'Legacy Subfolder',
        'parent_id' => $catInternal->id,
        'access_group' => null,
        'visibility' => 'internal',
    ]);

    $docPublik = Document::factory()->legacy('publik')->create([
        'document_category_id' => $catPublik->id,
    ]);
    $docInternal = Document::factory()->legacy('internal')->create([
        'document_category_id' => $catInternal->id,
    ]);
    $docPrivate = Document::factory()->legacy('private')->create([
        'document_category_id' => $catPrivate->id,
    ]);

    $this->artisan('documents:backfill-access-group', ['--dry-run' => true])
        ->expectsOutputToContain('=== Memulai Backfill Access Group (MODE DRY-RUN) ===')
        ->expectsOutputToContain('[DRY-RUN] Tidak ada data yang diubah di database.')
        ->assertSuccessful();

    // Data di database harus tetap NULL (tidak berubah)
    expect($catPublik->fresh()->access_group)->toBeNull()
        ->and($catInternal->fresh()->access_group)->toBeNull()
        ->and($catPrivate->fresh()->access_group)->toBeNull()
        ->and($subfolder->fresh()->access_group)->toBeNull()
        ->and($docPublik->fresh()->access_group)->toBeNull()
        ->and($docInternal->fresh()->access_group)->toBeNull()
        ->and($docPrivate->fresh()->access_group)->toBeNull();

    // Activity log tidak boleh ditulis saat dry-run
    expect(Activity::where('log_name', 'documents')->count())->toBe(0);
});

test('eksekusi benar mengisi sesuai mapping dan menulis ke activity log; dijalankan dua kali hasilnya sama (idempoten)', function () {
    $catPublik = DocumentCategory::factory()->legacy('publik')->create(['name' => 'Cat Publik']);
    $catInternal = DocumentCategory::factory()->legacy('internal')->create(['name' => 'Cat Internal']);
    $catPrivate = DocumentCategory::factory()->legacy('private')->create(['name' => 'Cat Private']);

    $subfolder = DocumentCategory::factory()->create([
        'name' => 'Sub Internal',
        'parent_id' => $catInternal->id,
        'access_group' => null,
        'visibility' => 'internal',
    ]);

    $docPublik = Document::factory()->legacy('publik')->create([
        'document_category_id' => $catPublik->id,
    ]);
    $docInternal = Document::factory()->legacy('internal')->create([
        'document_category_id' => $catInternal->id,
    ]);
    $docPrivate = Document::factory()->legacy('private')->create([
        'document_category_id' => $catPrivate->id,
    ]);

    // Jalankan pertama kali
    $this->artisan('documents:backfill-access-group')
        ->expectsOutputToContain('Perubahan berhasil disimpan dan dicatat ke activity log.')
        ->assertSuccessful();

    // Verifikasi mapping
    expect($catPublik->fresh()->access_group)->toBe(AccessGroup::PUBLIK)
        ->and($catInternal->fresh()->access_group)->toBe(AccessGroup::DOSEN_STAF)
        ->and($catPrivate->fresh()->access_group)->toBe(AccessGroup::AKADEMIK)
        ->and($subfolder->fresh()->access_group)->toBe(AccessGroup::DOSEN_STAF)
        ->and($docPublik->fresh()->access_group)->toBe(AccessGroup::PUBLIK)
        ->and($docInternal->fresh()->access_group)->toBe(AccessGroup::DOSEN_STAF)
        ->and($docPrivate->fresh()->access_group)->toBe(AccessGroup::AKADEMIK);

    // Verifikasi Activity Log ditulis
    $activity = Activity::where('log_name', 'documents')->latest()->first();
    expect($activity)->not->toBeNull()
        ->and($activity->description)->toContain('Backfill access_group selesai')
        ->and($activity->properties['total_documents'])->toBe(3)
        ->and($activity->properties['total_categories'])->toBe(4);

    // Jalankan KEDUA KALI (idempotensi)
    $this->artisan('documents:backfill-access-group')
        ->assertSuccessful();

    // Status database harus tetap sama persis
    expect($catPublik->fresh()->access_group)->toBe(AccessGroup::PUBLIK)
        ->and($catInternal->fresh()->access_group)->toBe(AccessGroup::DOSEN_STAF)
        ->and($catPrivate->fresh()->access_group)->toBe(AccessGroup::AKADEMIK)
        ->and($subfolder->fresh()->access_group)->toBe(AccessGroup::DOSEN_STAF)
        ->and($docPublik->fresh()->access_group)->toBe(AccessGroup::PUBLIK)
        ->and($docInternal->fresh()->access_group)->toBe(AccessGroup::DOSEN_STAF)
        ->and($docPrivate->fresh()->access_group)->toBe(AccessGroup::AKADEMIK);
});

test('baris yang sudah punya access_group tidak ditimpa', function () {
    // Kategori dengan visibility 'publik', tapi access_group sengaja 'akademik'
    $catCustom = DocumentCategory::factory()->create([
        'name' => 'Custom Category',
        'visibility' => 'publik',
        'access_group' => AccessGroup::AKADEMIK,
    ]);

    // Dokumen di root (tanpa folder) dengan access_group 'mahasiswa'
    $docCustom = Document::factory()->withoutCategory(AccessGroup::MAHASISWA)->create([
        'title' => 'Dokumen Mandiri Mahasiswa',
        'visibility' => 'internal',
        'owner_nim' => '120199001',
    ]);

    // Dokumen di dalam folder yang sudah punya access_group
    $docInFolder = Document::factory()->create([
        'document_category_id' => $catCustom->id,
        'title' => 'Dokumen dalam Folder Akademik',
        'visibility' => 'publik',
    ]);

    // Jalankan backfill
    $this->artisan('documents:backfill-access-group')
        ->assertSuccessful();

    // Data yang sudah punya access_group TIDAK boleh ditimpa
    expect($catCustom->fresh()->access_group)->toBe(AccessGroup::AKADEMIK)
        ->and($docCustom->fresh()->access_group)->toBe(AccessGroup::MAHASISWA)
        ->and($docInFolder->fresh()->access_group)->toBe(AccessGroup::AKADEMIK);
});
