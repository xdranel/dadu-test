<?php

use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDraft;
use App\Models\Student;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    \Illuminate\Support\Facades\Storage::fake('local');
    $this->user = User::factory()->create([
        'username' => '1202200001', 'email' => 'draft-card@mahasiswa.test',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false,
    ]);
    Student::create([
        'student_number' => '1202200001', 'name' => 'Draft Card Test',
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
        'name' => 'Periode Draft Card', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay(),
    ]);
    $this->actingAs($this->user);
    $this->newDraft = function () {
        $id = $this->postJson(route('sidang-ta-drafts.store'), [
            'sidang_ta_period_id' => $this->period->id,
            'student_phone' => '081234567890', 'parent_phone' => '081234567891',
        ])->assertCreated()->json('data.id');
        return SidangTaRegistrationDraft::findOrFail($id);
    };
});

function sidangTaDraftCardXpath(string $html): DOMXPath
{
    $previous = libxml_use_internal_errors(true);
    try {
        $document = new DOMDocument();
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET);
        return new DOMXPath($document);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
}

test('empty or fully expired drafts do not leave a draft panel on the student dashboard', function () {
    $xpath = sidangTaDraftCardXpath($this->get(route('sidang-ta-registrations.index'))->assertOk()->getContent());
    expect($xpath->query('//*[@data-testid="drafts-panel"]')->length)->toBe(0);
    $draft = ($this->newDraft)();
    $this->period->update(['close_at' => now()->subHour(), 'revision_open_at' => now()->subDay(), 'revision_close_at' => now()->subMinute()]);
    $xpath = sidangTaDraftCardXpath($this->get(route('sidang-ta-registrations.index'))->assertOk()->getContent());
    expect($xpath->query('//*[@data-testid="drafts-panel"]')->length)->toBe(0);
});

test('an initial draft is hidden after registration closes even while verification remains open', function () {
    ($this->newDraft)();
    $this->period->update(['close_at' => now()->subHour(),
        'revision_open_at' => now()->subHours(2), 'revision_close_at' => now()->addDay()]);
    $xpath = sidangTaDraftCardXpath($this->get(route('sidang-ta-registrations.index'))->assertOk()->getContent());
    expect($xpath->query('//*[@data-testid="drafts-panel"]')->length)->toBe(0);
});

test('a legacy revision without verification dates remains visible after registration closes', function () {
    $this->period->update(['close_at' => now()->subHour(), 'revision_open_at' => null, 'revision_close_at' => null]);
    $source = SidangTaRegistration::create(['sidang_ta_period_id' => $this->period->id,
        'student_number' => '1202200001', 'student_name' => 'Draft Card Test',
        'student_phone' => '081234567890', 'parent_phone' => '081234567891',
        'graduation_payment_date' => today()->toDateString(), 'diploma_biodata_accurate' => true,
        'status' => 'needs_review', 'academic_snapshot' => ['admission' => ['eprt' => ['mode' => 'automatic']]]])->fresh();
    $draft = SidangTaRegistrationDraft::create(['owner_user_id' => $this->user->id,
        'student_number' => '1202200001', 'sidang_ta_period_id' => $this->period->id,
        'source_registration_id' => $source->id, 'source_version' => $source->lock_version]);
    $response = $this->get(route('sidang-ta-registrations.index'))->assertOk();
    $response->assertSee(route('sidang-ta-drafts.edit', $draft), false);
});

test('period disclosure stays expanded for active legacy verification and collapses when disabled', function () {
    $this->user->givePermissionTo(Permission::findOrCreate('sidang_ta_period_read', 'web'));
    $this->period->update(['is_active' => false, 'close_at' => now()->subHour(),
        'is_revision_active' => true, 'revision_open_at' => null, 'revision_close_at' => null]);
    $crawler = new \Symfony\Component\DomCrawler\Crawler($this->get(route('admin.sidang-ta-periods.index'))->assertOk()->getContent());
    expect($crawler->filter('details[data-ta-period-id]')->getNode(0)->hasAttribute('open'))->toBeTrue();
    $this->period->update(['is_revision_active' => false]);
    $crawler = new \Symfony\Component\DomCrawler\Crawler($this->get(route('admin.sidang-ta-periods.index'))->assertOk()->getContent());
    expect($crawler->filter('details[data-ta-period-id]')->getNode(0)->hasAttribute('open'))->toBeFalse();
    expect($crawler->filter('details[data-ta-period-id] summary')->text())->toContain($this->period->name);
    expect($crawler->filter('details[data-ta-period-id] form')->count())->toBe(0);
});

test('extending an expired period discards its old draft without changing a final submission', function () {
    $draft = ($this->newDraft)();
    $final = SidangTaRegistration::create(['sidang_ta_period_id' => $this->period->id,
        'student_number' => '1202200001', 'student_name' => 'Draft Card Test',
        'student_phone' => '081234567890', 'parent_phone' => '081234567891',
        'graduation_payment_date' => today()->toDateString(), 'diploma_biodata_accurate' => true,
        'status' => 'rejected', 'academic_snapshot' => ['title_id' => 'Judul final tetap tersimpan']]);
    $before = $final->fresh()->getRawOriginal();
    $this->period->update(['close_at' => now()->subHour(), 'revision_open_at' => now()->subDay(), 'revision_close_at' => now()->subMinute()]);
    $this->user->givePermissionTo(Permission::findOrCreate('sidang_ta_period_update', 'web'));
    $this->put(route('admin.sidang-ta-periods.update', $this->period), [
        'name' => $this->period->name, 'open_at' => now()->subDay()->toDateTimeString(),
        'close_at' => now()->addDay()->toDateTimeString(), 'revision_open_at' => now()->subHour()->toDateTimeString(),
        'revision_close_at' => now()->addDays(2)->toDateTimeString(), 'is_active' => true, 'is_revision_active' => true,
    ])->assertRedirect()->assertSessionHasNoErrors();
    expect(SidangTaRegistrationDraft::find($draft->id))->toBeNull()
        ->and($final->fresh()->getRawOriginal())->toBe($before);
    $replacement = ($this->newDraft)();
    expect($replacement->id)->not->toBe($draft->id)->and($replacement->version)->toBe(1);
});

test('draft_delete_requires_dialog_confirmation_and_keeps_cancel_non_submitting', function () {
    $draft = ($this->newDraft)();
    $response = $this->get(route('sidang-ta-registrations.index'))->assertOk();
    $xpath = sidangTaDraftCardXpath($response->getContent());
    $actions = $xpath->query('//*[@data-draft-actions]')->item(0);
    expect($actions)->not->toBeNull();
    expect($xpath->query('.//a', $actions)->length)->toBe(1);
    expect($xpath->query('.//button[@data-open-discard and @type="button"]', $actions)->length)->toBe(1);
    expect($xpath->query('.//form', $actions)->length)->toBe(0);

    $dialog = $xpath->query('//dialog[@id="discard-draft-'.$draft->id.'"]')->item(0);
    expect($dialog)->not->toBeNull();
    expect($dialog->hasAttribute('open'))->toBeFalse();
    expect($dialog->textContent)->toContain('tidak dapat dipulihkan');
    expect($xpath->query('.//button[@data-cancel-discard and @type="button" and @autofocus]', $dialog)->length)->toBe(1);
    expect($xpath->query('.//button[@data-confirm-discard and @type="submit"]', $dialog)->length)->toBe(1);
    $form = $xpath->query('.//form', $dialog)->item(0);
    expect($form->getAttribute('method'))->toBe('POST');
    expect($form->getAttribute('action'))->toBe(route('sidang-ta-drafts.destroy', $draft));
    expect($xpath->query('.//input[@name="_method" and @value="DELETE"]', $form)->length)->toBe(1);
    expect($xpath->query('.//input[@name="_token"]', $form)->length)->toBe(1);
    expect($xpath->query('.//input[@name="version"]', $form)->item(0)->getAttribute('value'))->toBe((string) $draft->version);
    expect($draft->fresh())->not->toBeNull();
});

test('read_only_owner_can_see_continue_but_not_delete_controls_or_dialog', function () {
    $draft = ($this->newDraft)();
    $this->user->revokePermissionTo('sidang_ta_registration_create');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($this->user->fresh());
    $response = $this->get(route('sidang-ta-registrations.index'))->assertOk()
        ->assertSee(route('sidang-ta-drafts.edit', $draft), false);
    $xpath = sidangTaDraftCardXpath($response->getContent());
    expect($xpath->query('//*[@data-open-discard]')->length)->toBe(0);
    expect($xpath->query('//dialog')->length)->toBe(0);
});

test('stale_revision_has_delete_confirmation_without_continue_and_preserves_final_registration', function () {
    $source = SidangTaRegistration::create([
        'sidang_ta_period_id' => $this->period->id, 'student_number' => '1202200001',
        'student_name' => 'Draft Card Test', 'student_phone' => '081234567890',
        'parent_phone' => '081234567891', 'graduation_payment_date' => today()->toDateString(),
        'diploma_biodata_accurate' => true, 'status' => 'needs_review',
        'academic_snapshot' => ['admission' => ['eprt' => ['mode' => 'automatic']]],
    ])->fresh();
    foreach (\App\Support\SidangTaMeetRequirements::allTypes(true, 'automatic') as $i => $type) {
        $path = 'sidang_ta/card/'.$source->id.'/'.$type.'.pdf';
        \Illuminate\Support\Facades\Storage::disk('local')->put($path, '%PDF-1.4 card fixture');
        $source->documents()->create(['document_type' => $type, 'file_name' => $type.'.pdf',
            'file_path' => $path, 'file_type' => 'pdf', 'status' => $i === 0 ? 'rejected' : 'verified',
            'notes' => $i === 0 ? 'Perbaiki capture.' : null]);
    }
    $id = $this->postJson(route('sidang-ta-drafts.revision', $source))
        ->assertCreated()->json('data.id');
    $draft = SidangTaRegistrationDraft::findOrFail($id);
    $source->lock_version++;
    $source->save();

    $response = $this->get(route('sidang-ta-registrations.index'))->assertOk()
        ->assertDontSee(route('sidang-ta-drafts.edit', $draft), false);
    $crawler = new \Symfony\Component\DomCrawler\Crawler($response->getContent());
    $card = $crawler->filter('[data-testid="draft-card"]');
    expect($card->count())->toBe(1);
    expect($card->text(null, true))->toContain('Pengajuan asal berubah.');

    $xpath = sidangTaDraftCardXpath($response->getContent());
    expect($xpath->query('//*[@data-open-discard]')->length)->toBe(1);
    expect($xpath->query('//dialog')->length)->toBe(1);
    $this->delete(route('sidang-ta-drafts.destroy', $draft), ['version' => $draft->version])
        ->assertRedirect(route('sidang-ta-registrations.index'));
    expect(SidangTaRegistrationDraft::find($id))->toBeNull();
    expect($source->fresh()->status)->toBe('needs_review');
});
