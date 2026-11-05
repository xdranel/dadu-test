<?php

use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDraft;
use App\Models\Student;
use App\Models\User;
use App\Support\SidangTaDocumentRequirements as Requirements;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\DomCrawler\Crawler;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create([
        'username' => '1202200001', 'email' => 'review-ui@mahasiswa.test',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false,
    ]);
    Student::create([
        'student_number' => '1202200001', 'name' => 'Review UI Test',
        'email' => $this->user->email, 'academic_year' => 2022,
        'study_program' => 'Informatika', 'status' => 'Aktif',
    ]);
    foreach (['read', 'create', 'update'] as $action) {
        $this->user->givePermissionTo(Permission::firstOrCreate([
            'name' => "sidang_ta_registration_{$action}", 'guard_name' => 'web',
        ]));
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->period = SidangTaPeriod::create([
        'name' => 'Periode Review UI', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay(),
    ]);
    $this->actingAs($this->user);
    $this->reviewedDraft = function (bool $accurate = true) {
        $id = $this->postJson(route('sidang-ta-drafts.store'), [
            'sidang_ta_period_id' => $this->period->id,
            'student_phone' => '081234567890', 'parent_phone' => '081234567891',
        ])->assertCreated()->json('data.id');
        $draft = SidangTaRegistrationDraft::findOrFail($id);
        $draft->payload = array_replace($draft->payload, [
            'graduation_payment_date' => today()->toDateString(), 'diploma_biodata_accurate' => $accurate,
        ]);
        $draft->save();
        foreach (array_merge(Requirements::CAPTURES, Requirements::PAYMENT,
            Requirements::typesForStep(4, $accurate)) as $type) {
            $path = "sidang_ta/drafts/{$draft->id}/{$type}.pdf";
            Storage::disk('local')->put($path, '%PDF-review-ui');
            $draft->documents()->create([
                'document_type' => $type, 'file_name' => $type.'.pdf',
                'file_path' => $path, 'file_size' => 14, 'file_type' => 'pdf',
            ]);
        }
        $this->postJson(route('sidang-ta-drafts.review', $draft), ['version' => 1])->assertOk();
        return $draft->fresh();
    };
});

test('review_summary_and_download_buttons_do_not_submit_registration', function () {
    $draft = ($this->reviewedDraft)();
    $response = $this->get(route('sidang-ta-drafts.preview', $draft))->assertOk();
    $crawler = new Crawler($response->getContent());
    $summary = $crawler->filter('[data-review-summary]');
    expect($summary->count())->toBe(1);
    expect($summary->text(null, true))->toContain('Review UI Test')->toContain('1202200001')
        ->toContain('Periode Review UI')->toContain('081234567890')->toContain('081234567891')
        ->toContain('15 dokumen')->toContain('Belum dikirim');
    expect($crawler->filter('[data-review-group]')->count())->toBe(3);
    $links = $crawler->filter('a[data-document-preview]');
    expect($links->count())->toBe(15);
    $actual = $links->each(function (Crawler $node) {
        $classes = preg_split('/\s+/', trim($node->attr('class')));
        expect($classes)->toContain('btn')->toContain('btn-secondary')->not->toContain('underline');
        return $node->attr('href');
    });
    $expected = $draft->documents->map(fn ($document) => route('sidang-ta-draft-documents.preview', $document))->all();
    sort($actual);
    sort($expected);
    expect($actual)->toBe($expected);
    expect(SidangTaRegistration::count())->toBe(0);
    expect($draft->fresh()->status)->toBe('active');
});

test('only_confirmation_form_submits_saved_draft_with_original_contract', function () {
    $draft = ($this->reviewedDraft)();
    $crawler = new Crawler($this->get(route('sidang-ta-drafts.preview', $draft))->assertOk()->getContent());
    $open = $crawler->filter('[data-open-registration]');
    expect($open->count())->toBe(1);
    expect($open->attr('type'))->toBe('button');
    $openNode = $open->getNode(0);
    $xpath = new \DOMXPath($openNode->ownerDocument);
    expect($xpath->query('ancestor::form', $openNode)->length)->toBe(0);
    $dialog = $crawler->filter('dialog#confirm-send-registration');
    expect($dialog->count())->toBe(1);
    expect($dialog->getNode(0)->hasAttribute('open'))->toBeFalse();
    expect($dialog->text(null, true))->toContain('Periksa kembali data dan seluruh dokumen.');
    expect($dialog->filter('[data-cancel-registration]')->attr('type'))->toBe('button');
    expect($dialog->filter('[data-cancel-registration]')->getNode(0)->hasAttribute('autofocus'))->toBeTrue();
    expect($dialog->filter('[data-confirm-registration]')->attr('type'))->toBe('submit');
    $form = $dialog->filter('form[data-final-registration-form]');
    expect($crawler->filter('form[data-final-registration-form]')->count())->toBe(1);
    expect($form->attr('method'))->toBe('POST');
    expect($form->attr('action'))->toBe(route('sidang-ta-registrations.store'));
    expect($form->filter('input[type="file"]')->count())->toBe(0);
    $fields = $form->filter('input[name]')->each(fn (Crawler $node) => [
        'name' => $node->attr('name'), 'value' => $node->attr('value'),
    ]);
    $body = array_column($fields, 'value', 'name');
    $keys = array_keys($body);
    sort($keys);
    expect($keys)->toBe(['_token', 'action', 'draft_id', 'version']);
    expect($body['draft_id'])->toBe($draft->id);
    expect($body['version'])->toBe((string) $draft->version);
    expect($body['action'])->toBe('submit');
    expect(SidangTaRegistration::count())->toBe(0);

    $response = $this->post($form->attr('action'), $body);
    $registration = SidangTaRegistration::firstOrFail();
    $response->assertRedirect(route('sidang-ta-registrations.show', $registration));
    expect(SidangTaRegistration::count())->toBe(1);
    expect($registration->status)->toBe('submitted');
    expect($registration->documents()->count())->toBe(15);
    expect($draft->fresh()->status)->toBe('consumed');
});

test('pdm_review_displays_only_its_fourteen_required_documents', function () {
    $draft = ($this->reviewedDraft)(false);
    $crawler = new Crawler($this->get(route('sidang-ta-drafts.preview', $draft))->assertOk()->getContent());
    expect($crawler->filter('[data-review-summary]')->text(null, true))
        ->toContain('14 dokumen')->toContain('Belum sesuai, bukti PDM dilampirkan');
    expect($crawler->filter('a[data-document-preview]')->count())->toBe(14);
    expect($crawler->filter('[data-review-document="pdm_submission_proof"]')->count())->toBe(1);
    expect($crawler->filter('[data-review-document="diploma_statement"]')->count())->toBe(0);
    expect($crawler->filter('[data-review-document="diploma_biodata_form"]')->count())->toBe(0);
});

test('revision_confirmation_form_keeps_original_registration_and_put_method', function () {
    $initial = ($this->reviewedDraft)();
    $id = $this->postJson(route('sidang-ta-registrations.store'), [
        'draft_id' => $initial->id, 'version' => $initial->version, 'action' => 'submit',
    ])->assertOk()->json('data.id');
    $source = SidangTaRegistration::findOrFail($id);
    $source->status = 'needs_review';
    $source->lock_version++;
    $source->save();
    $revisionId = $this->postJson(route('sidang-ta-drafts.revision', $source))
        ->assertCreated()->json('data.id');
    $revision = SidangTaRegistrationDraft::findOrFail($revisionId);
    $this->postJson(route('sidang-ta-drafts.review', $revision), ['version' => 1])->assertOk();
    $crawler = new Crawler($this->get(route('sidang-ta-drafts.preview', $revision))->assertOk()->getContent());
    $form = $crawler->filter('#confirm-send-registration form[data-final-registration-form]');
    expect($form->attr('action'))->toBe(route('sidang-ta-registrations.update', $source));
    expect($form->filter('input[name="_method"]')->attr('value'))->toBe('PUT');
    expect($form->filter('input[name="draft_id"]')->attr('value'))->toBe($revision->id);
    expect($form->filter('input[name="version"]')->attr('value'))->toBe((string) $revision->version);
    expect($form->filter('[data-confirm-registration]')->text(null, true))->toBe('Ya, kirim revisi');
    expect(SidangTaRegistration::count())->toBe(1);
    expect($source->fresh()->status)->toBe('needs_review');
});

test('read_only_owner_keeps_review_downloads_without_send_controls', function () {
    $draft = ($this->reviewedDraft)();
    $this->user->revokePermissionTo('sidang_ta_registration_create');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($this->user->fresh());
    $crawler = new Crawler($this->get(route('sidang-ta-drafts.preview', $draft))->assertOk()->getContent());
    expect($crawler->filter('a[data-document-preview]')->count())->toBe(15);
    expect($crawler->filter('[data-open-registration]')->count())->toBe(0);
    expect($crawler->filter('#confirm-send-registration')->count())->toBe(0);
    expect($crawler->filter('form[data-final-registration-form]')->count())->toBe(0);
    expect(SidangTaRegistration::count())->toBe(0);
});
