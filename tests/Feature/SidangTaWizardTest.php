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

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create([
        'username' => '1202200001', 'email' => 'wizard@mahasiswa.test',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false,
    ]);
    Student::create(['student_number' => '1202200001', 'name' => 'Wizard Test',
        'email' => $this->user->email, 'academic_year' => 2022, 'study_program' => 'Informatika', 'status' => 'Aktif']);
    foreach (['read', 'create', 'update'] as $action) {
        $this->user->givePermissionTo(Permission::firstOrCreate([
            'name' => "sidang_ta_registration_{$action}", 'guard_name' => 'web',
        ]));
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->period = SidangTaPeriod::create(['name' => 'Periode E', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay()]);
    $this->data = ['sidang_ta_period_id' => $this->period->id,
        'student_phone' => '081234567890', 'parent_phone' => '081234567891'];
    $this->actingAs($this->user);
    $this->completeDraft = function () {
        $id = $this->postJson(route('sidang-ta-drafts.store'), $this->data)->assertCreated()->json('data.id');
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

function sidangTaWizardPeriodIds(string $html): array
{
    $previous = libxml_use_internal_errors(true);
    try {
        $dom = new DOMDocument();
        $dom->loadHTML($html, LIBXML_NONET);
        $options = (new DOMXPath($dom))->query(
            '//select[@name="sidang_ta_period_id"]/option[@value!=""]'
        );
        $ids = [];
        foreach ($options as $option) {
            $ids[] = (int) $option->getAttribute('value');
        }
        sort($ids);
        return $ids;
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
}

test('html_create_lists_multiple_periods_without_creating_draft', function () {
    SidangTaPeriod::create(['name' => 'Periode E kedua', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay()]);
    $this->get(route('sidang-ta-registrations.create'))->assertOk()
        ->assertViewIs('sidang_ta_registrations.wizard')
        ->assertSee('Periode E kedua')->assertSee('data-current-step="1"', false);
    expect(SidangTaRegistrationDraft::count())->toBe(0);
});

test('html_save_redirects_and_future_step_is_locked', function () {
    $this->post(route('sidang-ta-drafts.store'), $this->data)->assertRedirect();
    $draft = SidangTaRegistrationDraft::where('owner_user_id', $this->user->id)->firstOrFail();
    $this->get(route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 2]))
        ->assertOk()->assertSee('data-current-step="2"', false);
    $this->get(route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 4]))
        ->assertRedirect(route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 2]));
});

test('incomplete_draft_cannot_be_marked_reviewed', function () {
    $id = $this->postJson(route('sidang-ta-drafts.store'), $this->data)->assertCreated()->json('data.id');
    $this->postJson(route('sidang-ta-drafts.review', $id), ['version' => 1])->assertUnprocessable();
    expect(SidangTaRegistrationDraft::findOrFail($id)->reviewed_version)->toBeNull();
});

test('review_marks_saved_version_and_renders_seven_documents', function () {
    $draft = ($this->completeDraft)();
    $this->post(route('sidang-ta-drafts.review', $draft), ['version' => $draft->version])
        ->assertRedirect(route('sidang-ta-drafts.preview', $draft));
    $response = $this->get(route('sidang-ta-drafts.preview', $draft))->assertOk()
        ->assertViewIs('sidang_ta_registrations.wizard-review')
        ->assertViewHas('draft', fn ($viewDraft) => $viewDraft->documents->count() === 7);
    $crawler = new \Symfony\Component\DomCrawler\Crawler($response->getContent());
    expect($crawler->text(null, true))->toContain('Pendaftaran belum dikirim.');
    expect($draft->fresh()->reviewed_version)->toBe($draft->version);
    expect(SidangTaRegistration::count())->toBe(0);
});

test('saved_change_invalidates_review', function () {
    $draft = ($this->completeDraft)();
    $this->postJson(route('sidang-ta-drafts.review', $draft), ['version' => $draft->version])->assertOk();
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 1]),
        array_replace($this->data, ['version' => $draft->version, 'student_phone' => '089999999999']))->assertOk();
    $this->getJson(route('sidang-ta-drafts.preview', $draft))->assertStatus(409);
    expect($draft->fresh()->reviewed_version)->toBeNull();
});

test('index_exposes_discard_for_stale_revision', function () {
    $source = SidangTaRegistration::create([
        'sidang_ta_period_id' => $this->period->id, 'student_number' => '1202200001',
        'student_name' => 'Wizard Test', 'student_phone' => '081234567890',
        'parent_phone' => '081234567891', 'graduation_payment_date' => today()->toDateString(),
        'diploma_biodata_accurate' => true, 'requirements_version' => 2, 'academic_snapshot' => ['admission' => ['eprt' => ['mode' => 'automatic']]],
        'status' => 'needs_review',
    ])->fresh();
    expect($source->requirements_version)->toBe(2);
    foreach (array_merge(Requirements::CAPTURES, Requirements::PAYMENT, Requirements::DIPLOMA) as $index => $type) {
        $path = "sidang_ta/final/{$source->id}/{$type}.pdf";
        Storage::disk('local')->put($path, '%PDF-1.4 source');
        $source->documents()->create(['document_type' => $type, 'file_name' => $type.'.pdf',
            'file_path' => $path, 'file_size' => 20, 'file_type' => 'pdf',
            'status' => $index === 0 ? 'rejected' : 'verified',
            'notes' => $index === 0 ? 'Unggah capture yang terbaca.' : null]);
    }
    $id = $this->postJson(route('sidang-ta-drafts.revision', $source))
        ->assertCreated()->json('data.id');
    $source->lock_version++;
    $source->save();

    $response = $this->get(route('sidang-ta-registrations.index'))->assertOk()
        ->assertSee(route('sidang-ta-drafts.destroy', $id), false)
        ->assertDontSee(route('sidang-ta-drafts.edit', $id), false);
    $crawler = new \Symfony\Component\DomCrawler\Crawler($response->getContent());
    $card = $crawler->filter('[data-testid="draft-card"]');
    expect($card->count())->toBe(1);
    expect($card->text(null, true))->toContain('Pengajuan asal berubah.');

    $this->delete(route('sidang-ta-drafts.destroy', $id), ['version' => 1])
        ->assertRedirect(route('sidang-ta-registrations.index'));
    expect(SidangTaRegistrationDraft::find($id))->toBeNull();
    expect($source->fresh()->status)->toBe('needs_review');
});


test('missing_file_prevents_preview_even_after_review', function () {
    $draft = ($this->completeDraft)();
    $this->postJson(route('sidang-ta-drafts.review', $draft), ['version' => $draft->version])->assertOk();
    Storage::disk('local')->delete($draft->documents()->first()->file_path);
    $this->getJson(route('sidang-ta-drafts.preview', $draft))->assertStatus(409);
});

test('other_user_cannot_open_html_wizard_or_review', function () {
    $draft = ($this->completeDraft)();
    $other = User::factory()->create(['status' => 'active', 'must_change_password' => false]);
    $other->givePermissionTo('sidang_ta_registration_read');
    $this->actingAs($other)->get(route('sidang-ta-drafts.edit', $draft))->assertForbidden();
    $this->get(route('sidang-ta-drafts.preview', $draft))->assertForbidden();
});

test('html_validation_preserves_stale_version_until_conflict_is_resolved', function () {
    $draft = ($this->completeDraft)();
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 1]),
        array_replace($this->data, ['version' => $draft->version, 'student_phone' => '089999999999']))->assertOk();
    $url = route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 1]);
    $this->from($url)->post(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 1]),
        array_replace($this->data, ['version' => $draft->version, 'student_phone' => '']))
        ->assertRedirect($url)->assertSessionHasErrors('student_phone');
    $this->get($url)->assertOk()->assertSee('name="version" value="'.$draft->version.'"', false);
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 1]),
        array_replace($this->data, ['version' => $draft->version, 'student_phone' => '080000000000']))->assertStatus(409);
    expect($draft->fresh()->payload['student_phone'])->toBe('089999999999');
});

test('preview_returns_loaded_snapshot_before_concurrent_branch_change', function () {
    $draft = ($this->completeDraft)();
    $this->postJson(route('sidang-ta-drafts.review', $draft), ['version' => $draft->version])->assertOk();
    $snapshot = app(\App\Services\SidangTaDraftReviewService::class)->preview($this->user, $draft);
    expect($snapshot->relationLoaded('documents'))->toBeTrue();
    expect($snapshot->relationLoaded('period'))->toBeTrue();
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 4]), [
        'version' => $draft->version, 'diploma_biodata_accurate' => false,
        'documents' => ['pdm_submission_proof' => \Illuminate\Http\UploadedFile::fake()->create('pdm.pdf', 100, 'application/pdf')],
    ])->assertOk();
    expect($snapshot->payload['diploma_biodata_accurate'])->toBeTrue();
    expect($snapshot->documents->count())->toBe(7);
    expect($snapshot->documents->firstWhere('document_type', 'diploma_statement'))->not->toBeNull();
});

test('future_active_period_appears_only_after_opening_time', function () {
    try {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-02 12:00:00', config('app.timezone')));
        $this->period->update([
            'open_at' => '2026-10-31 23:59:00',
            'close_at' => '2026-11-05 23:59:00',
        ]);
        $future = SidangTaPeriod::create([
            'name' => 'Periode 2 masa depan', 'is_active' => true,
            'open_at' => '2026-11-06 23:59:00', 'close_at' => '2026-11-30 23:56:00',
        ]);
        $response = $this->get(route('sidang-ta-registrations.create'))->assertOk();
        expect(sidangTaWizardPeriodIds($response->getContent()))->toBe([$this->period->id]);

        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-07 12:00:00', config('app.timezone')));
        $response = $this->get(route('sidang-ta-registrations.create'))->assertOk();
        expect(sidangTaWizardPeriodIds($response->getContent()))->toBe([$future->id]);
    } finally {
        $this->travelBack();
    }
});

test('new_registration_select_contains_both_overlapping_open_period_ids', function () {
    $other = SidangTaPeriod::create([
        'name' => 'Periode terbuka kedua', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay(),
    ]);
    $response = $this->get(route('sidang-ta-registrations.create'))->assertOk();
    $expected = [$this->period->id, $other->id];
    sort($expected);
    expect(sidangTaWizardPeriodIds($response->getContent()))->toBe($expected);
});

test('resumed_new_draft_select_keeps_all_currently_open_period_ids', function () {
    $other = SidangTaPeriod::create([
        'name' => 'Periode terbuka kedua', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay(),
    ]);
    $id = $this->postJson(route('sidang-ta-drafts.store'), $this->data)
        ->assertCreated()->json('data.id');
    $response = $this->get(route('sidang-ta-drafts.edit', ['draft' => $id, 'step' => 1]))->assertOk();
    $expected = [$this->period->id, $other->id];
    sort($expected);
    expect(sidangTaWizardPeriodIds($response->getContent()))->toBe($expected);
});

test('empty_step_query_uses_first_incomplete_step', function () {
    $id = $this->postJson(route('sidang-ta-drafts.store'), $this->data)
        ->assertCreated()->json('data.id');
    $this->get(route('sidang-ta-drafts.edit', $id).'?step=')->assertOk()
        ->assertViewIs('sidang_ta_registrations.wizard')
        ->assertViewHas('step', 2)
        ->assertSee('data-current-step="2"', false);
});
