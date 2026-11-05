<?php

use App\Models\Document;
use App\Models\DocumentCategory;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
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
});

it('displays categories as folders on initial page load', function () {
    $response = $this->get(route('public_documents.index'));

    $response->assertStatus(200);
    $response->assertSee('Yudisium');
    $response->assertSee('Dokumen terkait persyaratan yudisium.');
    $response->assertSee('1 dokumen');

    $response->assertSee('Tugas Akhir');
    $response->assertSee('Template dan panduan penulisan tugas akhir.');
    $response->assertSee('1 dokumen');

    // Document cards should be shown under "Dokumen Terbaru"
    $response->assertSee('Dokumen Terbaru');
    $response->assertSee('Syarat Yudisium 2026');
    $response->assertSee('Template Proposal Tugas Akhir');
    $response->assertDontSee('← Kembali ke Kategori');
});

it('displays documents of a specific category folder when filtered', function () {
    $response = $this->get(route('public_documents.index', ['kategori' => $this->category1->id]));

    $response->assertStatus(200);
    // Should show back navigation button and folder title
    $response->assertSee('← Kembali ke Kategori');
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
    $response->assertSee('← Kembali ke Kategori');
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
    $response->assertHeader('content-disposition', 'attachment; filename=yudisium_requirements.pdf');

    // Check database incremented count
    $this->doc1->refresh();
    expect($this->doc1->download_count)->toBe($initialDownloads + 1);
});
