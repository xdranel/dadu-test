<?php

use App\Enums\AccessGroup;
use App\Models\Document;
use App\Models\DocumentCategory;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    Document::query()->forceDelete();
    DocumentCategory::query()->forceDelete();
});

test('subfolder otomatis mewarisi access_group dari root', function () {
    $root = DocumentCategory::factory()->create([
        'name' => 'Root Akademik',
        'parent_id' => null,
        'access_group' => AccessGroup::AKADEMIK,
    ]);

    $subfolder = DocumentCategory::factory()->create([
        'name' => 'Sub Akademik Level 1',
        'parent_id' => $root->id,
        'access_group' => null, // Biarkan mewarisi
    ]);

    expect($subfolder->access_group)->toBe(AccessGroup::AKADEMIK);

    $subSubfolder = DocumentCategory::factory()->create([
        'name' => 'Sub Sub Akademik Level 2',
        'parent_id' => $subfolder->id,
    ]);

    expect($subSubfolder->access_group)->toBe(AccessGroup::AKADEMIK);
});

test('dokumen mewarisi access_group dari foldernya', function () {
    $folder = DocumentCategory::factory()->create([
        'name' => 'Folder Dosen Staf',
        'access_group' => AccessGroup::DOSEN_STAF,
    ]);

    $doc = Document::factory()->create([
        'document_category_id' => $folder->id,
        'access_group' => null,
    ]);

    expect($doc->access_group)->toBe(AccessGroup::DOSEN_STAF);
});

test('dokumen di root tanpa folder wajib menentukan access_group sendiri', function () {
    // Tanpa access_group dan tanpa folder -> harus ditolak
    expect(function () {
        Document::create([
            'document_category_id' => null,
            'title' => 'Dokumen Root Tanpa Access Group',
            'file_name' => 'test.pdf',
            'file_path' => 'documents/test.pdf',
            'file_type' => 'pdf',
            'file_size' => 1024,
            'access_group' => null,
        ]);
    })->toThrow(\InvalidArgumentException::class, 'Dokumen di root tanpa folder wajib menentukan access_group sendiri.');

    // Dengan access_group -> berhasil
    $doc = Document::create([
        'document_category_id' => null,
        'title' => 'Dokumen Root Dengan Access Group',
        'file_name' => 'test.pdf',
        'file_path' => 'documents/test.pdf',
        'file_type' => 'pdf',
        'file_size' => 1024,
        'access_group' => AccessGroup::PUBLIK,
    ]);

    expect($doc->access_group)->toBe(AccessGroup::PUBLIK);
});

test('percobaan mengubah access_group subfolder secara langsung ditolak', function () {
    $root = DocumentCategory::factory()->create([
        'name' => 'Root Publik',
        'access_group' => AccessGroup::PUBLIK,
    ]);

    $subfolder = DocumentCategory::factory()->create([
        'name' => 'Sub Publik',
        'parent_id' => $root->id,
    ]);

    expect(function () use ($subfolder) {
        $subfolder->update(['access_group' => AccessGroup::DOSEN_STAF]);
    })->toThrow(\DomainException::class, 'Access group subfolder tidak dapat diubah secara langsung karena selalu mewarisi dari root.');

    $subfolder->refresh();
    expect($subfolder->access_group)->toBe(AccessGroup::PUBLIK);
});

test('pindah folder lintas root memperbarui seluruh turunan (subfolder + dokumen) secara atomik', function () {
    // Root 1: Publik
    $root1 = DocumentCategory::factory()->create([
        'name' => 'Root 1 Publik',
        'access_group' => AccessGroup::PUBLIK,
    ]);

    $sub1 = DocumentCategory::factory()->create([
        'name' => 'Subfolder 1',
        'parent_id' => $root1->id,
    ]);

    $sub1Child = DocumentCategory::factory()->create([
        'name' => 'Sub Subfolder 1 Child',
        'parent_id' => $sub1->id,
    ]);

    $docSub1 = Document::factory()->create([
        'document_category_id' => $sub1->id,
        'title' => 'Dokumen di Sub 1',
    ]);

    $docSub1Child = Document::factory()->create([
        'document_category_id' => $sub1Child->id,
        'title' => 'Dokumen di Sub 1 Child',
    ]);

    expect($sub1->access_group)->toBe(AccessGroup::PUBLIK)
        ->and($sub1Child->access_group)->toBe(AccessGroup::PUBLIK)
        ->and($docSub1->access_group)->toBe(AccessGroup::PUBLIK)
        ->and($docSub1Child->access_group)->toBe(AccessGroup::PUBLIK);

    // Root 2: Akademik
    $root2 = DocumentCategory::factory()->create([
        'name' => 'Root 2 Akademik',
        'access_group' => AccessGroup::AKADEMIK,
    ]);

    // Pindah Sub 1 ke bawah Root 2
    $sub1->moveTo($root2->id);

    $sub1->refresh();
    $sub1Child->refresh();
    $docSub1->refresh();
    $docSub1Child->refresh();

    // Seluruh turunan harus ikut berubah menjadi AKADEMIK
    expect($sub1->parent_id)->toBe($root2->id)
        ->and($sub1->access_group)->toBe(AccessGroup::AKADEMIK)
        ->and($sub1Child->access_group)->toBe(AccessGroup::AKADEMIK)
        ->and($docSub1->access_group)->toBe(AccessGroup::AKADEMIK)
        ->and($docSub1Child->access_group)->toBe(AccessGroup::AKADEMIK);
});

test('pindah folder lintas root atomik dan rollback jika terjadi kegagalan', function () {
    $rootPublik = DocumentCategory::factory()->create([
        'name' => 'Root Publik',
        'access_group' => AccessGroup::PUBLIK,
    ]);

    $subfolder = DocumentCategory::factory()->create([
        'name' => 'Subfolder Publik',
        'parent_id' => $rootPublik->id,
    ]);

    $rootAkademik = DocumentCategory::factory()->create([
        'name' => 'Root Akademik',
        'access_group' => AccessGroup::AKADEMIK,
    ]);

    // Simulasi kegagalan di dalam transaksi
    try {
        DB::transaction(function () use ($subfolder, $rootAkademik) {
            $subfolder->moveTo($rootAkademik->id);
            throw new \RuntimeException('Simulasi error tak terduga saat pemindahan');
        });
    } catch (\RuntimeException $e) {
        // expected
    }

    $subfolder->refresh();
    // Data harus kembali seperti semula (rollback)
    expect($subfolder->parent_id)->toBe($rootPublik->id)
        ->and($subfolder->access_group)->toBe(AccessGroup::PUBLIK);
});

test('proteksi loop folder (wouldCreateCycle) tetap berfungsi saat pemindahan', function () {
    $parent = DocumentCategory::factory()->create(['name' => 'Parent Folder']);
    $child = DocumentCategory::factory()->create(['name' => 'Child Folder', 'parent_id' => $parent->id]);
    $grandchild = DocumentCategory::factory()->create(['name' => 'Grandchild Folder', 'parent_id' => $child->id]);

    // Tidak boleh memindahkan parent ke dirinya sendiri
    expect($parent->wouldCreateCycle($parent->id))->toBeTrue();

    // Tidak boleh memindahkan parent ke dalam child atau grandchild
    expect($parent->wouldCreateCycle($child->id))->toBeTrue();
    expect($parent->wouldCreateCycle($grandchild->id))->toBeTrue();

    // Memindahkan ke folder lain yang bukan keturunannya diizinkan
    $other = DocumentCategory::factory()->create(['name' => 'Other Folder']);
    expect($parent->wouldCreateCycle($other->id))->toBeFalse();

    // Percobaan moveTo yang menyebabkan cycle harus melempar exception
    expect(function () use ($parent, $child) {
        $parent->moveTo($child->id);
    })->toThrow(\InvalidArgumentException::class);
});
