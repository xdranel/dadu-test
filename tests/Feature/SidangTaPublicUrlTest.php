<?php

use App\Models\SidangTaFormGuide;
use App\Models\SidangTaRegistrationDocument;
use App\Models\SidangTaRegistrationDraft;
use App\Models\SidangTaRegistrationDraftDocument;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\TaRoomScenario;

beforeEach(function () {
    Storage::fake('local');
    $this->travelTo(\Carbon\Carbon::parse('2026-11-19 09:00:00', 'Asia/Jakarta'));
    $this->scenario = TaRoomScenario::make();
    $this->registration = $this->scenario['registration']->fresh();
    Storage::disk('local')->put('sidang-ta/public-url.pdf', '%PDF-1.4 test');
    $this->document = $this->registration->documents()->create([
        'document_type' => 'ta_data_capture', 'file_name' => 'contoh.pdf',
        'file_path' => 'sidang-ta/public-url.pdf', 'file_type' => 'pdf', 'file_size' => 14,
    ]);
});

test('public TA links use opaque keys while database relationships and document paths stay intact', function () {
    $before = $this->registration->getRawOriginal();
    $url = route('sidang-ta-registrations.show', $this->registration);
    expect(Str::isUuid(basename(parse_url($url, PHP_URL_PATH))))->toBeTrue();
    $this->actingAs($this->scenario['owner'])->get($url)->assertOk();
    $this->get(route('sidang-ta-registration-documents.preview', $this->document))->assertOk();
    expect($this->registration->fresh()->getRawOriginal())->toBe($before)
        ->and($this->document->fresh()->sidang_ta_registration_id)->toBe($this->registration->id)
        ->and($this->document->fresh()->file_path)->toBe('sidang-ta/public-url.pdf');
});

test('legacy authorized numeric document and registration links redirect to their opaque URLs', function () {
    $this->actingAs($this->scenario['owner']);
    $this->get('/dashboard/sidang-ta-registrations/'.$this->registration->id)
        ->assertRedirect(route('sidang-ta-registrations.show', $this->registration));
    $this->get('/dashboard/sidang-ta-registration-documents/'.$this->document->id.'/preview')
        ->assertRedirect(route('sidang-ta-registration-documents.preview', $this->document));
});

test('opaque and legacy links never disclose another students document or redirect to its public key', function () {
    $intruder = User::factory()->create(['status' => 'active']);
    $intruder->givePermissionTo('sidang_ta_registration_read');
    \App\Models\Student::create(['student_number' => '1200000999', 'name' => 'Mahasiswa lain',
        'email' => $intruder->email, 'academic_year' => 2022, 'study_program' => 'Informatika']);
    $this->actingAs($intruder);
    foreach ([route('sidang-ta-registration-documents.preview', $this->document),
        '/dashboard/sidang-ta-registration-documents/'.$this->document->id.'/preview',
        route('sidang-ta-registrations.show', $this->registration),
        '/dashboard/sidang-ta-registrations/'.$this->registration->id] as $url) {
        $this->get($url)->assertForbidden()->assertHeaderMissing('Location');
    }
    $this->get('/dashboard/sidang-ta-registrations/'.Str::uuid())->assertNotFound();
});

test('draft document period and form guide links also use opaque keys', function () {
    $draft = SidangTaRegistrationDraft::create(['owner_user_id' => $this->scenario['owner']->id,
        'student_number' => $this->registration->student_number, 'sidang_ta_period_id' => $this->scenario['period']->id]);
    $draftDocument = $draft->documents()->create(['document_type' => 'ta_data_capture',
        'file_name' => 'draft.pdf', 'file_path' => 'sidang-ta/public-url.pdf', 'file_type' => 'pdf', 'file_size' => 14]);
    $guide = SidangTaFormGuide::create(['slot' => 'ta_data', 'title' => 'Contoh TA', 'is_active' => true]);
    foreach ([route('sidang-ta-draft-documents.preview', $draftDocument),
        route('admin.sidang-ta-periods.update', $this->scenario['period']),
        route('admin.sidang-ta-guides.edit', $guide)] as $url) {
        $parts = explode('/', trim(parse_url($url, PHP_URL_PATH), '/'));
        if (in_array(end($parts), ['preview', 'edit'], true)) array_pop($parts);
        expect(Str::isUuid(end($parts)))->toBeTrue();
    }
    $this->actingAs($this->scenario['owner'])->get(route('sidang-ta-draft-documents.preview', $draftDocument))->assertOk();
});

test('public ID backfill repairs old rows without changing primary keys status snapshots or foreign keys', function () {
    expect(Schema::hasColumn('sidang_ta_registrations', 'public_id'))->toBeTrue();
    $registrationId = $this->registration->id;
    $snapshot = $this->registration->getRawOriginal('academic_snapshot');
    DB::table('sidang_ta_registrations')->where('id', $registrationId)->update(['public_id' => null]);
    DB::table('sidang_ta_registration_documents')->where('id', $this->document->id)->update(['public_id' => null]);
    $migration = require database_path('migrations/2026_11_19_120000_add_public_ids_to_sidang_ta_routes.php');
    $migration->up();
    $this->registration->refresh();
    $this->document->refresh();
    expect(Str::isUuid($this->registration->public_id))->toBeTrue()
        ->and(Str::isUuid($this->document->public_id))->toBeTrue()
        ->and($this->registration->id)->toBe($registrationId)
        ->and($this->registration->status)->toBe('approved')
        ->and($this->registration->getRawOriginal('academic_snapshot'))->toBe($snapshot)
        ->and($this->document->sidang_ta_registration_id)->toBe($registrationId)
        ->and($this->document->file_path)->toBe('sidang-ta/public-url.pdf');
    $uuid = $this->registration->public_id;
    $migration->up();
    expect($this->registration->fresh()->public_id)->toBe($uuid);
});

test('review period filters and generated export links use public period identifiers', function () {
    $period = $this->scenario['period'];
    $response = $this->actingAs($this->scenario['reviewer'])
        ->get(route('admin.sidang-ta-registrations.index', ['period' => $period->public_id]))
        ->assertOk()->assertViewHas('counts', fn ($counts) => $counts['total'] === 1);
    $crawler = new \Symfony\Component\DomCrawler\Crawler($response->getContent());
    expect($crawler->filter('select[name="period"] option[selected]')->attr('value'))->toBe($period->public_id);
    parse_str(parse_url($crawler->filter('[data-ta-export]')->attr('href'), PHP_URL_QUERY), $query);
    expect($query['period'])->toBe($period->public_id);
    $this->get($crawler->filter('[data-ta-export]')->attr('href'))->assertOk();
});
