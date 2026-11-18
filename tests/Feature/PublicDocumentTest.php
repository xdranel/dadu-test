<?php

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Queue::fake();
    Http::fake();
    // Clean up existing documents/categories
    Document::query()->delete();
    DocumentCategory::query()->delete();

    // Create test categories
    $this->category1 = DocumentCategory::create([
        'name' => 'Yudisium',
        'description' => 'Dokumen terkait persyaratan yudisium.',
        'sort_order' => 1
    ]);

    $this->category2 = DocumentCategory::create([
        'name' => 'Tugas Akhir',
        'description' => 'Template dan panduan penulisan tugas akhir.',
        'sort_order' => 2
    ]);

    // Create mock files in Storage
    Storage::fake('public');
    Storage::disk('public')->put('docs/yudisium_requirements.pdf', 'dummy content');
    Storage::disk('public')->put('docs/thesis_template.docx', 'dummy content');

    // Create test documents
    $this->doc1 = Document::create([
        'document_category_id' => $this->category1->id,
        'title' => 'Syarat Yudisium 2026',
        'abbreviation' => 'SY-2026',
        'description' => 'Dokumen ini berisi list persyaratan yudisium.',
        'file_name' => 'yudisium_requirements.pdf',
        'file_path' => 'docs/yudisium_requirements.pdf',
        'file_type' => 'pdf',
        'file_size' => 1024 * 50, // 50 KB
        'visibility' => 'Publik',
        'download_count' => 5
    ]);

    $this->doc2 = Document::create([
        'document_category_id' => $this->category2->id,
        'title' => 'Template Proposal Tugas Akhir',
        'abbreviation' => 'TP-TA',
        'description' => 'Format standar template proposal TA.',
        'file_name' => 'thesis_template.docx',
        'file_path' => 'docs/thesis_template.docx',
        'file_type' => 'docx',
        'file_size' => 1024 * 200, // 200 KB
        'visibility' => 'Publik',
        'download_count' => 2
    ]);

    // Dokumen non-publik untuk pengujian isolasi kebocoran
    $this->categoryInternal = DocumentCategory::create([
        'name' => 'Kategori Rahasia',
        'visibility' => 'Internal',
        'access_group' => 'dosen_staf',
    ]);

    $this->docInternal = Document::create([
        'document_category_id' => $this->categoryInternal->id,
        'title' => 'Dokumen Rahasia Rapat Internal',
        'abbreviation' => 'DR-INT',
        'description' => 'Khusus internal dosen dan staf.',
        'file_name' => 'internal_secret.pdf',
        'file_path' => 'docs/internal_secret.pdf',
        'file_type' => 'pdf',
        'file_size' => 1024 * 10,
        'visibility' => 'Internal',
        'access_group' => 'dosen_staf',
        'storage_disk' => 'private',
    ]);
});

it('displays categories as folders on initial page load', function () {
    $response = $this->get(route('public_documents.index'));

    $response->assertStatus(200);
    $response->assertSee('Yudisium');
    $response->assertSee('1 dokumen');

    $response->assertSee('Tugas Akhir');
    $response->assertSee('1 dokumen');

    // Document cards should be shown under "Dokumen Terbaru"
    $response->assertSee('Dokumen Terbaru');
    $response->assertSee('Syarat Yudisium 2026');
    $response->assertSee('Template Proposal Tugas Akhir');
    $response->assertDontSee('title="Kembali ke Kategori"', false);
});

it('displays documents of a specific category folder when filtered', function () {
    $response = $this->get(route('public_documents.index', ['kategori' => $this->category1->id]));

    $response->assertStatus(200);
    // Should show back navigation button and folder title
    $response->assertSee('title="Kembali ke Kategori"', false);
    $response->assertSee('Folder:');
    $response->assertSee('Yudisium');

    // Should show category 1 document, but NOT category 2 document
    $response->assertSee('Syarat Yudisium 2026');
    $response->assertSee('50 KB');
    $response->assertDontSee('Template Proposal Tugas Akhir');
});

it('displays search results across categories and allows searching within a folder', function () {
    // Search across all categories
    $response = $this->get(route('public_documents.index', ['search' => 'Proposal']));

    $response->assertStatus(200);
    $response->assertSee('title="Kembali ke Kategori"', false);
    $response->assertSee('Kata Kunci:');
    $response->assertSee('"Proposal"', false);
    $response->assertSee('Template Proposal Tugas Akhir');
    $response->assertDontSee('Syarat Yudisium 2026');

    // Search within a specific folder (kategori)
    $response2 = $this->get(route('public_documents.index', [
        'kategori' => $this->category1->id,
        'search' => 'Proposal'
    ]));

    $response2->assertStatus(200);
    $response2->assertSee('Folder:');
    $response2->assertSee('Yudisium');
    $response2->assertSee('Kata Kunci:');
    $response2->assertSee('"Proposal"', false);
    // Category 1 doesn't have any document matching "Proposal"
    $response2->assertSee('Dokumen tidak ditemukan');
});

it('allows downloading a public document and increments download count', function () {
    $initialDownloads = $this->doc1->download_count;

    $response = $this->get(route('public_documents.download', $this->doc1));

    $response->assertStatus(200);
    $response->assertHeader('content-disposition', 'attachment; filename="' . $this->doc1->getDownloadFilename() . '"');

    // Check database incremented count
    $this->doc1->refresh();
    expect($this->doc1->download_count)->toBe($initialDownloads + 1);
});

it('memastikan dokumen non-publik tidak pernah muncul di portal publik dan mengembalikan 404 identik', function () {
    // Portal publik tidak menampilkan dokumen atau folder internal
    $response = $this->get(route('public_documents.index'));
    $response->assertStatus(200);
    $response->assertDontSee('Dokumen Rahasia Rapat Internal');
    $response->assertDontSee('Kategori Rahasia');

    // Pencarian portal publik tidak membocorkan dokumen internal
    $searchResponse = $this->get(route('public_documents.index', ['search' => 'Rahasia']));
    $searchResponse->assertStatus(200);
    $searchResponse->assertDontSee('Dokumen Rahasia Rapat Internal');

    // Akses folder non-publik via parameter ?kategori mengembalikan 404 identik
    $this->get(route('public_documents.index', ['kategori' => $this->categoryInternal->id]))->assertStatus(404);

    // Akses ID non-existent mengembalikan 404
    $this->get(route('public_documents.show', 999999))->assertStatus(404);

    // Akses langsung show/download/preview dokumen non-publik oleh guest mengembalikan 404 (bukan 403)
    $this->get(route('public_documents.show', $this->docInternal))->assertStatus(404);
    $this->get(route('public_documents.download', $this->docInternal))->assertStatus(404);
    $this->get(route('public_documents.preview', $this->docInternal))->assertStatus(404);

    // Akses dokumen non-publik oleh user login lewat route portal publik juga mengembalikan 404
    $user = User::factory()->create(['status' => 'active']);
    $this->actingAs($user)->get(route('public_documents.show', $this->docInternal))->assertStatus(404);
    $this->actingAs($user)->get(route('public_documents.download', $this->docInternal))->assertStatus(404);
    $this->actingAs($user)->get(route('public_documents.preview', $this->docInternal))->assertStatus(404);
});
