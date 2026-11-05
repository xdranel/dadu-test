<?php

use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDraft;
use App\Models\Student;
use App\Models\User;
use App\Support\SidangTaDocumentRequirements as Requirements;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\DomCrawler\Crawler;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create([
        'username' => '1202200001', 'email' => 'preview-ta@mahasiswa.test',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false,
    ]);
    $this->student = Student::create([
        'student_number' => '1202200001', 'name' => 'Preview TA Test',
        'email' => $this->user->email, 'academic_year' => 2022,
        'study_program' => 'Informatika', 'status' => 'Aktif', 'gpa' => 0,
    ]);
    foreach (['read', 'create', 'update'] as $action) {
        $this->user->givePermissionTo(Permission::firstOrCreate([
            'name' => "sidang_ta_registration_{$action}", 'guard_name' => 'web',
        ]));
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->period = SidangTaPeriod::create([
        'name' => 'Periode S2', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay(),
    ]);
    $this->data = ['sidang_ta_period_id' => $this->period->id,
        'student_phone' => '081234567890', 'parent_phone' => '081234567891'];
    $this->actingAs($this->user);
    $this->completeDraft = function () {
        $id = $this->postJson(route('sidang-ta-drafts.store'), $this->data)
            ->assertCreated()->json('data.id');
        $draft = SidangTaRegistrationDraft::findOrFail($id);
        expect($draft->requirements_version)->toBe(2);
        foreach ([2, 3, 4] as $step) {
            $data = ['version' => $draft->fresh()->version];
            if ($step === 3) $data['graduation_payment_date'] = today()->toDateString();
            if ($step === 4) $data['diploma_biodata_accurate'] = true;
            foreach (Requirements::typesForStep($step, true) as $type) {
                $data['documents'][$type] = \Illuminate\Http\UploadedFile::fake()
                    ->create($type.'.pdf', 100, 'application/pdf');
            }
            $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => $step]), $data)
                ->assertOk();
        }
        $draft = $draft->fresh();
        expect($draft->documents()->count())->toBe(7);
        return $draft;
    };

    Permission::firstOrCreate(['name' => 'sidang_ta_registration_review', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'sidang_ta_period_read', 'guard_name' => 'web']);
    $this->makeFinal = function (string $status = 'submitted') {
        $registration = SidangTaRegistration::create([
            'sidang_ta_period_id' => $this->period->id, 'student_number' => $this->student->student_number,
            'student_name' => $this->student->name, 'study_program' => $this->student->study_program,
            'student_phone' => '081234567890', 'parent_phone' => '081234567891',
            'graduation_payment_date' => today()->toDateString(), 'diploma_biodata_accurate' => true,
            'requirements_version' => 2,
            'academic_snapshot' => ['admission' => ['eprt' => ['mode' => 'automatic']]],
            'status' => $status, 'submitted_at' => now(),
        ])->fresh();
        expect($registration->requirements_version)->toBe(2);
        foreach (array_merge(Requirements::CAPTURES, Requirements::PAYMENT, Requirements::DIPLOMA) as $index => $type) {
            $path = "sidang_ta/final/{$registration->id}/{$type}.pdf";
            Storage::disk('local')->put($path, '%PDF-1.4 preview');
            $saved = $registration->documents()->create([
                'document_type' => $type, 'file_name' => $index === 0 ? 'capture.pdf' : $type.'.pdf',
                'file_path' => $path, 'file_type' => 'pdf', 'file_size' => 16,
                'status' => $status === 'needs_review' && $index === 0 ? 'rejected' : 'verified',
                'notes' => $status === 'needs_review' && $index === 0 ? 'Ganti capture.' : null,
            ]);
            if ($index === 0) $document = $saved;
        }
        return [$registration, $document];
    };
});



test('preview_owner_gets_inline_pdf_jpeg_png_for_draft_and_final', function () {
    $draft = ($this->completeDraft)();
    $draftDocument = $draft->documents()->firstOrFail();
    [, $finalDocument] = ($this->makeFinal)();
    $samples = [
        ['pdf', 'application/pdf', '%PDF-1.4 preview'],
        ['jpg', 'image/jpeg', hex2bin('ffd8ffe000104a46494600010100000100010000ffd9')],
        ['png', 'image/png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=')],
    ];
    foreach ($samples as [$extension, $mime, $bytes]) {
        foreach ([['sidang-ta-draft-documents.preview', $draftDocument],
                     ['sidang-ta-registration-documents.preview', $finalDocument]] as [$route, $document]) {
            Storage::disk('local')->put($document->file_path, $bytes);
            $document->update(['file_name' => 'berkas.'.$extension, 'file_type' => $extension]);
            $response = $this->get(route($route, $document))->assertOk()
                ->assertHeader('Content-Type', $mime)->assertHeader('X-Content-Type-Options', 'nosniff');
            expect($response->headers->get('Content-Disposition'))->toStartWith('inline;');
            expect($response->headers->get('Cache-Control'))->toContain('private')->toContain('no-store');
            expect($response->streamedContent())->toBe($bytes);
        }
    }
});

test('preview_other_student_cannot_open_draft_or_final_by_id', function () {
    $draft = ($this->completeDraft)();
    [, $finalDocument] = ($this->makeFinal)();
    $other = User::factory()->create(['username' => '1202200002', 'email' => 'other-preview@mahasiswa.test',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
    Student::create(['student_number' => '1202200002', 'name' => 'Other', 'email' => $other->email,
        'academic_year' => 2022, 'study_program' => 'Informatika', 'status' => 'Aktif']);
    $other->givePermissionTo('sidang_ta_registration_read');
    $this->actingAs($other)->get(route('sidang-ta-draft-documents.preview', $draft->documents()->first()))->assertForbidden();
    $this->get(route('sidang-ta-registration-documents.preview', $finalDocument))->assertForbidden();
});

test('preview_consumed_and_stale_revision_drafts_are_rejected', function () {
    $draft = ($this->completeDraft)();
    $document = $draft->documents()->firstOrFail();
    $draft->update(['status' => 'consumed']);
    $this->get(route('sidang-ta-draft-documents.preview', $document))->assertStatus(409);
    [$source, $finalDocument] = ($this->makeFinal)('needs_review');
    $id = $this->postJson(route('sidang-ta-drafts.revision', $source))->assertCreated()->json('data.id');
    $revision = SidangTaRegistrationDraft::findOrFail($id);
    $borrowed = $revision->documents()->firstOrFail();
    $source->lock_version++;
    $source->save();
    $this->get(route('sidang-ta-draft-documents.preview', $borrowed))->assertStatus(409);
    $this->get(route('sidang-ta-registration-documents.preview', $finalDocument))->assertOk();
});

test('preview_missing_files_return_404_and_html_content_is_not_rendered_inline', function () {
    $draft = ($this->completeDraft)();
    [, $finalDocument] = ($this->makeFinal)();
    foreach ([['sidang-ta-draft-documents.preview', $draft->documents()->firstOrFail()],
                 ['sidang-ta-registration-documents.preview', $finalDocument]] as [$route, $document]) {
        Storage::disk('local')->delete($document->file_path);
        $this->get(route($route, $document))->assertNotFound();
        Storage::disk('local')->put($document->file_path, '<html><script>alert(1)</script></html>');
        $this->get(route($route, $document))->assertStatus(415);
    }
});

test('preview_reviewer_only_can_open_final_and_download_but_not_private_drafts', function () {
    $draft = ($this->completeDraft)();
    [, $document] = ($this->makeFinal)();
    $reviewer = User::factory()->create(['username' => 'reviewer-preview', 'email' => 'reviewer-preview@test.local',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
    $reviewer->givePermissionTo('sidang_ta_registration_review');
    $this->actingAs($reviewer)->get(route('sidang-ta-registration-documents.preview', $document))->assertOk();
    $this->get(route('sidang-ta-registration-documents.download', $document))->assertDownload('capture.pdf');
    $this->get(route('sidang-ta-draft-documents.preview', $draft->documents()->first()))->assertForbidden();
    $reviewer->revokePermissionTo('sidang_ta_registration_review');
    $reviewer->givePermissionTo('sidang_ta_period_read');
    $this->get(route('sidang-ta-registration-documents.preview', $document))->assertForbidden();
    $this->get(route('sidang-ta-registration-documents.download', $document))->assertForbidden();
});

test('preview_review_page_has_no_number_badges_and_escaped_modal_triggers', function () {
    $draft = ($this->completeDraft)();
    $document = $draft->documents()->firstOrFail();
    $name = 'nama " <img src=x onerror=alert(1)>.pdf';
    $document->update(['file_name' => $name]);
    $this->postJson(route('sidang-ta-drafts.review', $draft), ['version' => $draft->version])->assertOk();
    $crawler = new Crawler($this->get(route('sidang-ta-drafts.preview', $draft))->assertOk()->getContent());
    expect($crawler->filter('[data-review-group]')->count())->toBe(3);
    expect($crawler->filter('[data-review-group] header span[aria-hidden="true"]')->count())->toBe(0);
    expect($crawler->filter('a[data-document-preview]')->count())->toBe(7);
    expect($crawler->filter('[data-document-preview-modal]')->count())->toBe(1);
    $link = $crawler->filter('a[data-document-preview]')->reduce(fn (Crawler $n) =>
        $n->attr('data-preview-url') === route('sidang-ta-draft-documents.preview', $document));
    expect($link->attr('data-file-name'))->toBe($name);
    expect($link->attr('href'))->toBe(route('sidang-ta-draft-documents.preview', $document));
    expect($link->attr('data-download-url'))->toBe(route('sidang-ta-draft-documents.download', $document));
    expect($link->attr('x-on:click.prevent'))->toBe("\$dispatch('sidang-ta-preview', \$el.dataset)");
    expect($crawler->filter('[data-review-document] img')->count())->toBe(0);
    expect($crawler->filter('[data-preview-download]')->count())->toBe(1);
    expect($crawler->filter('[data-preview-new-tab]')->attr('target'))->toBe('_blank');
    expect($crawler->filter('[data-preview-close]')->attr('type'))->toBe('button');
});

test('preview_final_detail_uses_one_modal_and_keeps_direct_download_as_secondary_data', function () {
    [$registration, $document] = ($this->makeFinal)();
    $crawler = new Crawler($this->get(route('sidang-ta-registrations.show', $registration))->assertOk()->getContent());
    expect($crawler->filter('a[data-document-preview]')->count())->toBe(7);
    $link = $crawler->filter('a[data-document-preview]')->reduce(fn (Crawler $node) =>
        $node->attr('href') === route('sidang-ta-registration-documents.preview', $document));
    expect($link->count())->toBe(1);
    expect($link->attr('href'))->toBe(route('sidang-ta-registration-documents.preview', $document));
    expect($link->attr('data-download-url'))->toBe(route('sidang-ta-registration-documents.download', $document));
    expect($crawler->filter('[data-document-preview-modal]')->count())->toBe(1);
    expect($crawler->filter('a[data-final-download]')->count())->toBe(0);
    expect($crawler->filter('[data-document-preview-modal] iframe')->getNode(0)->hasAttribute('src'))->toBeFalse();
});

test('preview_read_only_owner_can_view_without_mutating_saved_state', function () {
    $draft = ($this->completeDraft)();
    [$registration, $finalDocument] = ($this->makeFinal)();
    $this->user->revokePermissionTo('sidang_ta_registration_create');
    $this->user->revokePermissionTo('sidang_ta_registration_update');
    $version = $draft->version;
    $this->get(route('sidang-ta-draft-documents.preview', $draft->documents()->first()))->assertOk();
    $this->get(route('sidang-ta-registration-documents.preview', $finalDocument))->assertOk();
    expect($draft->fresh()->version)->toBe($version);
    expect($draft->fresh()->reviewed_version)->toBeNull();
    expect($registration->fresh()->status)->toBe('submitted');
    expect($registration->logs()->count())->toBe(0);
});

test('preview_review_modal_is_inside_layout_after_document_doctype', function () {
    $draft = ($this->completeDraft)();
    $this->postJson(route('sidang-ta-drafts.review', $draft), ['version' => $draft->version])
        ->assertOk();
    $html = $this->get(route('sidang-ta-drafts.preview', $draft))->assertOk()->getContent();
    expect(strtolower(ltrim($html)))->toStartWith('<!doctype html>');
    $crawler = new Crawler($html);
    expect($crawler->filter('body [data-document-preview-modal]')->count())->toBe(1);
});

test('preview_final_modal_is_inside_layout_after_document_doctype', function () {
    [$registration] = ($this->makeFinal)();
    $html = $this->get(route('sidang-ta-registrations.show', $registration))->assertOk()->getContent();
    expect(strtolower(ltrim($html)))->toStartWith('<!doctype html>');
    $crawler = new Crawler($html);
    expect($crawler->filter('body [data-document-preview-modal]')->count())->toBe(1);
});
