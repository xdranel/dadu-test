<?php

use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDraft;
use App\Models\Student;
use App\Models\User;
use App\Support\SidangTaDocumentRequirements as Requirements;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Symfony\Component\DomCrawler\Crawler;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create([
        'username' => '1202200001', 'email' => 'student-polish@mahasiswa.test',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false,
    ]);
    $this->student = Student::create([
        'student_number' => '1202200001', 'name' => 'Student Polish Test',
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
});


test('s2_diploma_review_is_disabled_until_both_saved_files_exist', function () {
    $draft = ($this->completeDraft)();
    $missing = $draft->documents()->where('document_type', 'diploma_biodata_form')->firstOrFail();
    Storage::disk('local')->delete($missing->file_path);
    $url = route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 4]);
    $crawler = new Crawler($this->get($url)->assertOk()->getContent());
    $button = $crawler->filter('[data-review-submit]')->getNode(0);
    expect($button->hasAttribute('disabled'))->toBeTrue();
    expect($button->getAttribute('aria-disabled'))->toBe('true');
    expect($button->getAttribute('class'))->toContain('disabled:opacity-50')
        ->toContain('disabled:cursor-not-allowed');
    expect($crawler->filter('[data-step-progress]')->text(null, true))->toContain('1 dari 2 dokumen tersimpan.');
    $this->postJson(route('sidang-ta-drafts.review', $draft), ['version' => $draft->version])
        ->assertUnprocessable();
    Storage::disk('local')->put($missing->file_path, '%PDF-ui');
    $crawler = new Crawler($this->get($url)->assertOk()->getContent());
    $button = $crawler->filter('[data-review-submit]')->getNode(0);
    expect($button->hasAttribute('disabled'))->toBeFalse();
    expect($button->getAttribute('aria-disabled'))->toBe('false');
    expect($button->getAttribute(':disabled'))->toContain('dirty || busy');
    expect($crawler->filter('[data-review-draft]')->attr('x-on:submit'))->toContain('dirty || busy');
});

test('s2_pdm_review_requires_saved_pdm_after_switching_branch', function () {
    $draft = ($this->completeDraft)();
    $save = route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 4]);
    $this->postJson($save, ['version' => $draft->version, 'diploma_biodata_accurate' => false])
        ->assertOk()->assertJsonPath('data.first_incomplete_step', 4);
    $url = route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 4]);
    $crawler = new Crawler($this->get($url)->assertOk()->getContent());
    expect($crawler->filter('[data-review-submit]')->getNode(0)->hasAttribute('disabled'))->toBeTrue();
    expect($crawler->filter('[data-step-progress]')->text(null, true))->toContain('0 dari 1 dokumen tersimpan.');
    $this->postJson($save, ['version' => $draft->fresh()->version, 'documents' => [
        'pdm_submission_proof' => UploadedFile::fake()->create('pdm.pdf', 100, 'application/pdf'),
    ]])->assertOk()->assertJsonPath('data.first_incomplete_step', null);
    $crawler = new Crawler($this->get($url)->assertOk()->getContent());
    expect($crawler->filter('[data-review-submit]')->getNode(0)->hasAttribute('disabled'))->toBeFalse();
});

test('s2_upload_steps_hide_downloads_and_removed_hint_but_preview_keeps_downloads', function () {
    $draft = ($this->completeDraft)();
    $documents = $draft->documents()->get();
    foreach ([2, 3, 4] as $step) {
        $response = $this->get(route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => $step]))->assertOk();
        $crawler = new Crawler($response->getContent());
        $response->assertSee('Berkas tersimpan')->assertDontSee('Unduh berkas')
            ->assertDontSee('Informasi akademik bersifat baca saja.');
        foreach ($documents as $document) {
            $response->assertDontSee(route('sidang-ta-draft-documents.download', $document), false);
        }
        foreach (Requirements::typesForStep($step, true) as $type) {
            expect($crawler->filter('[data-upload-type="'.$type.'"]')->text(null, true))
                ->toContain($documents->firstWhere('document_type', $type)->file_name);
        }
    }
    $this->postJson(route('sidang-ta-drafts.review', $draft), ['version' => $draft->version])->assertOk();
    $response = $this->get(route('sidang-ta-drafts.preview', $draft))->assertOk();
    foreach ($documents as $document) {
        $response->assertSee(route('sidang-ta-draft-documents.download', $document), false);
    }
    $this->get(route('sidang-ta-draft-documents.download', $documents->first()))->assertOk();
});

test('s2_index_keeps_draft_and_history_when_multiple_periods_close', function () {
    $draft = ($this->completeDraft)();
    $second = SidangTaPeriod::create(['name' => 'Periode S2 kedua', 'is_active' => true,
        'open_at' => now()->subHour(), 'close_at' => now()->addDay()]);
    $url = route('sidang-ta-registrations.index');
    $crawler = new Crawler($this->get($url)->assertOk()->getContent());
    $header = $crawler->filter('[data-registration-header]');
    $headerNode = $header->getNode(0);
    $xpath = new \DOMXPath($headerNode->ownerDocument);
    expect($xpath->query(
        'ancestor::section[contains(concat(" ", normalize-space(@class), " "), " campus-card ")]',
        $headerNode
    )->length)->toBe(0);
    expect($header->count())->toBe(1);
    expect($header->attr('class'))->not->toContain('campus-card');
    expect($header->filter('[data-open-period-count]')->text(null, true))->toBe('2 periode terbuka');
    expect($header->filter('[data-registration-start]')->attr('href'))
        ->toBe(route('sidang-ta-registrations.create'));
    $this->period->update(['close_at' => now()->subMinute()]);
    $second->update(['close_at' => now()->subMinute()]);
    $response = $this->get($url)->assertOk()->assertSee('Riwayat Pengajuan');
    $crawler = new Crawler($response->getContent());
    expect($crawler->filter('[data-registration-start]')->count())->toBe(0);
    expect($crawler->filter('[data-registration-closed][disabled]')->count())->toBe(1);
    expect($crawler->filter('[data-open-period-count]')->text(null, true))->toBe('0 periode terbuka');
    expect($crawler->filter('[data-testid="drafts-panel"]')->count())->toBe(1);
    $response->assertSee(route('sidang-ta-drafts.edit', $draft), false);
});

test('s2_index_active_registration_hides_start_and_respects_revision_permission', function () {
    $registration = SidangTaRegistration::create([
        'sidang_ta_period_id' => $this->period->id, 'student_number' => $this->student->student_number,
        'student_name' => $this->student->name, 'study_program' => $this->student->study_program,
        'student_phone' => '081234567890', 'parent_phone' => '081234567891',
        'graduation_payment_date' => today()->toDateString(), 'diploma_biodata_accurate' => true,
        'requirements_version' => 2,
        'academic_snapshot' => ['admission' => ['eprt' => ['mode' => 'automatic']]],
        'status' => 'needs_review', 'submitted_at' => now(),
    ]);
    $url = route('sidang-ta-registrations.index');
    $crawler = new Crawler($this->get($url)->assertOk()->getContent());
    $header = $crawler->filter('[data-registration-header]');
    expect($header->filter('[data-registration-start]')->count())->toBe(0);
    expect($header->filter('[data-registration-active]')->attr('href'))
        ->toBe(route('sidang-ta-registrations.show', $registration));
    expect($header->filter('[data-registration-revise]')->attr('href'))
        ->toBe(route('sidang-ta-registrations.edit', $registration));
    $this->user->revokePermissionTo('sidang_ta_registration_update');
    $crawler = new Crawler($this->get($url)->assertOk()->getContent());
    expect($crawler->filter('[data-registration-revise]')->count())->toBe(0);
    $registration->update(['status' => 'submitted']);
    $this->user->givePermissionTo('sidang_ta_registration_update');
    $crawler = new Crawler($this->get($url)->assertOk()->getContent());
    expect($crawler->filter('[data-registration-revise]')->count())->toBe(0);
});

test('s2_read_only_owner_keeps_draft_access_without_create_or_delete_controls', function () {
    $draft = ($this->completeDraft)();
    $this->user->revokePermissionTo('sidang_ta_registration_create');
    $this->user->revokePermissionTo('sidang_ta_registration_update');
    $response = $this->get(route('sidang-ta-registrations.index'))->assertOk();
    $crawler = new Crawler($response->getContent());
    expect($crawler->filter('[data-registration-start]')->count())->toBe(0);
    expect($crawler->filter('[data-registration-readonly]')->count())->toBe(1);
    expect($crawler->filter('[data-testid="drafts-panel"]')->count())->toBe(1);
    expect($crawler->filter('[data-open-discard]')->count())->toBe(0);
    $response->assertSee(route('sidang-ta-drafts.edit', $draft), false);
});
