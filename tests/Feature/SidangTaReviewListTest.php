<?php

use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\User;
use App\Support\SidangTaDocumentRequirements as Requirements;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\DomCrawler\Crawler;

beforeEach(function () {
    $this->travelTo(now()->startOfSecond());
    Storage::fake('local');
    foreach (['sidang_ta_registration_review', 'sidang_ta_period_read'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->reviewer = User::factory()->create(['username' => 'p2a-reviewer', 'email' => 'p2a-reviewer@test.local',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
    $this->reviewer->givePermissionTo('sidang_ta_registration_review');
    $this->actingAs($this->reviewer);
    $this->a = SidangTaPeriod::create(['name' => 'P2a A', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay()]);
    $this->b = SidangTaPeriod::create(['name' => 'P2a B', 'is_active' => true,
        'open_at' => now()->addHour(), 'close_at' => now()->addDays(2)]);
    $this->sequence = 0;
    $this->makeRegistration = function (array $overrides = []) {
        $this->sequence++;
        $overrides['academic_snapshot'] = array_replace_recursive(
            ['admission' => ['eprt' => ['mode' => 'automatic']]], $overrides['academic_snapshot'] ?? []
        );
        return SidangTaRegistration::create(array_replace([
            'sidang_ta_period_id' => $this->a->id, 'student_number' => 'P2A'.str_pad((string) $this->sequence, 6, '0', STR_PAD_LEFT),
            'student_name' => 'Mahasiswa '.$this->sequence, 'study_program' => 'Informatika',
            'student_phone' => '081234567890', 'parent_phone' => '081234567891',
            'graduation_payment_date' => today()->toDateString(), 'diploma_biodata_accurate' => true,
            'status' => 'submitted', 'submitted_at' => now(),
        ], $overrides))->fresh();
    };
    $this->attach = function ($registration, string $type, string $status = 'pending', bool $exists = true) {
        $path = "sidang_ta/p2a/{$registration->id}/{$type}.pdf";
        if ($exists) Storage::disk('local')->put($path, '%PDF-1.4 preview');
        return $registration->documents()->create(['document_type' => $type, 'file_name' => $type.'.pdf',
            'file_path' => $path, 'file_type' => 'pdf', 'file_size' => 16, 'status' => $status]);
    };
    $this->listing = function (array $filters = []) {
        return $this->get(route('admin.sidang-ta-registrations.index', $filters))->assertOk();
    };
});

afterEach(function () { $this->travelBack(); });

test('p2a_all_status_cards_match_full_unfiltered_result', function () {
    foreach (['submitted', 'needs_review', 'approved', 'rejected'] as $status) {
        ($this->makeRegistration)(['status' => $status]);
    }
    $response = ($this->listing)();
    $response->assertViewHas('counts', ['total' => 4, 'submitted' => 1, 'needs_review' => 1, 'approved' => 1, 'rejected' => 1]);
    $crawler = new Crawler($response->getContent());
    expect($crawler->filter('[data-ta-stat]')->count())->toBe(5);
    expect($crawler->filter('[data-ta-stat="total"]')->text())->toBe('4');
    expect($crawler->filter('[data-ta-row]')->count())->toBe(4);
});

test('p2a_combined_filters_group_name_nim_search_without_leaking_other_matches', function () {
    $target = ($this->makeRegistration)(['student_name' => 'Target Cocok', 'status' => 'approved']);
    ($this->makeRegistration)(['student_number' => 'Target-B', 'sidang_ta_period_id' => $this->b->id, 'status' => 'approved']);
    ($this->makeRegistration)(['student_number' => 'Target-Prodi', 'study_program' => 'Sistem Informasi', 'status' => 'approved']);
    ($this->makeRegistration)(['student_name' => 'Target Pending']);
    $response = ($this->listing)(['search' => 'Target', 'period' => $this->a->id, 'status' => 'approved', 'study_program' => 'Informatika']);
    $response->assertViewHas('registrations', fn ($rows) => $rows->total() === 1 && $rows->first()->id === $target->id)
        ->assertViewHas('counts', ['total' => 1, 'submitted' => 0, 'needs_review' => 0, 'approved' => 1, 'rejected' => 0]);
});

test('p2a_nim_search_and_zero_are_preserved', function () {
    $target = ($this->makeRegistration)(['student_number' => '123450', 'student_name' => 'Nama khusus']);
    ($this->makeRegistration)(['student_number' => '777777', 'student_name' => 'Nama lain']);
    ($this->listing)(['search' => '12345'])->assertViewHas('registrations', fn ($rows) => $rows->total() === 1 && $rows->first()->id === $target->id);
    ($this->listing)(['search' => '0'])->assertViewHas('filters', fn ($filters) => $filters['search'] === '0')
        ->assertViewHas('counts', fn ($counts) => $counts['total'] === 1);
});

test('p2a_invalid_filter_values_return_validation_errors', function () {
    foreach ([[['period' => 999999], 'period'], [['status' => 'unknown'], 'status'],
                 [['search' => ['bad']], 'search'], [['study_program' => ['bad']], 'study_program'],
                 [['search' => str_repeat('x', 101)], 'search'], [['page' => 0], 'page']] as [$filters, $field]) {
        $this->getJson(route('admin.sidang-ta-registrations.index', $filters))
            ->assertUnprocessable()->assertJsonValidationErrors($field);
    }
});

test('p2a_pagination_keeps_all_filters_and_counts_all_matching_rows', function () {
    foreach (range(1, 20) as $i) ($this->makeRegistration)(['student_name' => 'Pagination '.$i]);
    ($this->makeRegistration)(['student_name' => 'Pagination lain', 'study_program' => 'Sistem Informasi']);
    $filters = ['search' => 'Pagination', 'period' => $this->a->id, 'status' => 'submitted', 'study_program' => 'Informatika'];
    $response = ($this->listing)($filters)->assertViewHas('counts', fn ($counts) => $counts['total'] === 20)
        ->assertViewHas('registrations', fn ($rows) => $rows->count() === 15 && $rows->total() === 20);
    $rows = $response->viewData('registrations');
    parse_str(parse_url($rows->nextPageUrl(), PHP_URL_QUERY), $query);
    foreach ($filters as $key => $value) expect((string) $query[$key])->toBe((string) $value);
    expect((string) $query['page'])->toBe('2');
    ($this->listing)($filters + ['page' => 2])->assertViewHas('registrations', fn ($page) => $page->count() === 5)
        ->assertViewHas('counts', fn ($counts) => $counts['total'] === 20);
    $crawler = new Crawler($response->getContent());
    expect($crawler->filter('[data-ta-reset]')->attr('href'))->toBe(route('admin.sidang-ta-registrations.index'));
    $crawler = new Crawler(($this->listing)($filters + ['page' => 999])->getContent());
    expect($crawler->filter('[data-ta-empty]')->text(null, true))->toContain('Halaman ini tidak berisi pengajuan.');
    expect($crawler->filter('[data-ta-first-page]')->attr('href'))->toBe(route('admin.sidang-ta-registrations.index', $filters));
});

test('p2a_banner_lists_all_active_periods_and_selected_inactive_period', function () {
    $inactive = SidangTaPeriod::create(['name' => 'Nonaktif', 'is_active' => false,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay()]);
    $closed = SidangTaPeriod::create(['name' => 'Aktif tutup', 'is_active' => true,
        'open_at' => now()->subDays(2), 'close_at' => now()->subDay()]);
    $crawler = new Crawler(($this->listing)()->getContent());
    expect($crawler->filter('[data-ta-period-banner]')->count())->toBe(3);
    expect($crawler->filter('[data-ta-period-banner="'.$this->a->id.'"]')->text(null, true))->toContain('Sedang terbuka');
    expect($crawler->filter('[data-ta-period-banner="'.$this->b->id.'"]')->text(null, true))->toContain('Belum dibuka');
    expect($crawler->filter('[data-ta-period-banner="'.$closed->id.'"]')->text(null, true))->toContain('Ditutup');
    $crawler = new Crawler(($this->listing)(['period' => $inactive->id])->getContent());
    expect($crawler->filter('[data-ta-period-banner]')->count())->toBe(1);
    expect($crawler->filter('[data-ta-period-banner="'.$inactive->id.'"]')->text(null, true))->toContain('Tidak aktif');
});

test('p2a_no_active_period_and_empty_vs_filtered_empty_are_distinct', function () {
    SidangTaPeriod::query()->update(['is_active' => false]);
    $crawler = new Crawler(($this->listing)()->getContent());
    expect($crawler->filter('[data-ta-no-period]')->count())->toBe(1);
    expect($crawler->filter('[data-ta-empty]')->text(null, true))->toBe('Belum ada pengajuan Sidang TA.');
    $crawler = new Crawler(($this->listing)(['search' => 'Tidak Ada'])->getContent());
    expect($crawler->filter('[data-ta-empty]')->text(null, true))->toBe('Tidak ada pengajuan yang cocok dengan filter.');
});

test('p2a_document_matrix_shows_only_current_branch_and_distinguishes_all_review_states', function () {
    $accurate = ($this->makeRegistration)();
    $pdm = ($this->makeRegistration)(['diploma_biodata_accurate' => false]);
    ($this->attach)($accurate, Requirements::CAPTURES[0], 'verified');
    ($this->attach)($accurate, Requirements::CAPTURES[1], 'rejected');
    ($this->attach)($accurate, Requirements::PAYMENT[0]);
    ($this->attach)($accurate, Requirements::CAPTURES[2], 'verified', false);
    ($this->attach)($accurate, Requirements::PDM[0], 'verified');
    ($this->attach)($pdm, Requirements::PDM[0], 'verified');
    ($this->attach)($pdm, Requirements::DIPLOMA[0], 'verified');
    $crawler = new Crawler(($this->listing)()->getContent());
    $row = $crawler->filter('[data-ta-row="'.$accurate->id.'"]');
    expect($row->filter('[data-ta-doc]')->count())->toBe(7);
    foreach ([Requirements::CAPTURES[0] => 'verified', Requirements::CAPTURES[1] => 'rejected',
                 Requirements::PAYMENT[0] => 'pending', Requirements::CAPTURES[2] => 'unavailable',
                 Requirements::CAPTURES[3] => 'missing'] as $type => $state) {
        expect($row->filter('[data-ta-doc="'.$type.'"]')->attr('data-doc-state'))->toBe($state);
    }
    expect($row->filter('a[data-ta-doc="'.Requirements::CAPTURES[2].'"]')->count())->toBe(0);
    expect($row->filter('[data-ta-doc="'.Requirements::PDM[0].'"]')->count())->toBe(0);
    $row = $crawler->filter('[data-ta-row="'.$pdm->id.'"]');
    expect($row->filter('[data-ta-doc]')->count())->toBe(6);
    expect($row->filter('[data-ta-doc="'.Requirements::DIPLOMA[0].'"], [data-ta-doc="'.Requirements::DIPLOMA[1].'"]')->count())->toBe(0);
    expect($row->filter('a[data-ta-doc="'.Requirements::PDM[0].'"]')->count())->toBe(1);
});

test('p2a_approved_registration_does_not_turn_pending_document_green', function () {
    $registration = ($this->makeRegistration)(['status' => 'approved']);
    ($this->attach)($registration, Requirements::CAPTURES[0]);
    $crawler = new Crawler(($this->listing)()->getContent());
    expect($crawler->filter('[data-ta-row="'.$registration->id.'"] [data-ta-doc="'.Requirements::CAPTURES[0].'"]')->attr('data-doc-state'))->toBe('pending');
});

test('p2a_reviewer_without_student_read_uses_private_modal_links_and_detail', function () {
    $registration = ($this->makeRegistration)();
    $document = ($this->attach)($registration, Requirements::CAPTURES[0]);
    $document->update(['file_name' => 'nama " <img src=x>.pdf']);
    $crawler = new Crawler(($this->listing)()->getContent());
    $link = $crawler->filter('a[data-ta-doc="'.Requirements::CAPTURES[0].'"]');
    expect($link->attr('href'))->toBe(route('sidang-ta-registration-documents.preview', $document));
    expect($link->attr('data-file-name'))->toBe('nama " <img src=x>.pdf');
    expect($crawler->filter('[data-ta-row] img')->count())->toBe(0);
    expect($crawler->filter('[data-document-preview-modal]')->count())->toBe(1);
    $this->get($link->attr('href'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->get(route('admin.sidang-ta-registrations.show', $registration))->assertOk();
    expect($this->reviewer->can('sidang_ta_registration_read'))->toBeFalse();
});

test('p2a_period_only_still_has_no_registration_stats_program_query_or_preview', function () {
    ($this->makeRegistration)(['student_name' => 'Rahasia P2a']);
    $user = User::factory()->create(['username' => 'period-p2a', 'email' => 'period-p2a@test.local',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
    $user->givePermissionTo('sidang_ta_period_read');
    $queries = [];
    DB::listen(function (QueryExecuted $event) use (&$queries) {
        if (str_contains(strtolower($event->sql), 'sidang_ta_registrations')) $queries[] = $event->sql;
    });
    $response = $this->actingAs($user)->get(route('admin.sidang-ta-registrations.index', ['status' => 'invalid']))
        ->assertOk()->assertViewHas('registrations', fn ($value) => $value === null)
        ->assertViewHas('counts', fn ($value) => $value === null)
        ->assertViewHas('studyPrograms', fn ($value) => $value->isEmpty())
        ->assertDontSee('Rahasia P2a')->assertDontSee('Ringkasan hasil filter');
    expect($queries)->toBe([]);
    $crawler = new Crawler($response->getContent());
    expect($crawler->filter('[data-document-preview-modal]')->count())->toBe(0);
    expect($crawler->filter('[data-ta-period-only]')->count())->toBe(1);
});

test('p2a_detail_uses_historical_snapshot_and_handles_missing_file_safely', function () {
    $registration = ($this->makeRegistration)(['academic_snapshot' => ['student_name' => 'Nama Historis', 'gpa' => 0,
        'custom_field' => '<b>literal</b>']]);
    $document = ($this->attach)($registration, Requirements::CAPTURES[0], 'rejected', false);
    $document->update(['notes' => '<img src=x> perbaiki']);
    $html = $this->get(route('admin.sidang-ta-registrations.show', $registration))->assertOk()->getContent();
    expect(strtolower(ltrim($html)))->toStartWith('<!doctype html>');
    $crawler = new Crawler($html);
    expect($crawler->filter('[data-academic-detail]')->count())->toBe(1);
    expect($crawler->filter('[data-academic-detail]')->text(null, true))->toContain('Nama Historis')->toContain('0,00');
    $card = $crawler->filter('[data-ta-detail-document="'.$document->id.'"]');
    expect($card->text(null, true))->toContain('Berkas tidak ditemukan.')->toContain('<img src=x> perbaiki');
    expect($card->filter('img')->count())->toBe(0);
    expect($card->filter('a[data-document-preview]')->count())->toBe(0);
    expect($crawler->filter('body [data-document-preview-modal]')->count())->toBe(1);
    expect($crawler->filter('[data-ta-decision] input[name="registration_version"]')->attr('value'))->toBe((string) $registration->lock_version);
});

test('p2a_list_eager_loads_all_page_documents_in_one_query', function () {
    foreach (range(1, 15) as $i) {
        $registration = ($this->makeRegistration)();
        ($this->attach)($registration, Requirements::CAPTURES[0]);
    }
    $queries = [];
    DB::listen(function (QueryExecuted $event) use (&$queries) {
        if (str_contains(strtolower($event->sql), 'sidang_ta_registration_documents')) $queries[] = $event->sql;
    });
    ($this->listing)()->assertViewHas('registrations', fn ($rows) => $rows->every(fn ($row) => $row->relationLoaded('documents')));
    expect($queries)->toHaveCount(1);
});

test('p2a_unknown_program_filter_remains_visible_and_escaped_with_zero_results', function () {
    ($this->makeRegistration)(['study_program' => 'Prodi Historis']);
    $value = '<b>Prodi tidak tersedia</b>';
    $response = ($this->listing)(['study_program' => $value])->assertViewHas('counts', fn ($counts) => $counts['total'] === 0);
    $crawler = new Crawler($response->getContent());
    expect($crawler->filter('select[name="study_program"] option[selected]')->attr('value'))->toBe($value);
    expect($crawler->filter('select[name="study_program"] b')->count())->toBe(0);
    expect($crawler->filter('select[name="study_program"]')->text(null, true))->toContain('Prodi Historis');
});
