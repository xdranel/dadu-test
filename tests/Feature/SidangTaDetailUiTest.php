<?php

use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\DomCrawler\Crawler;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create([
        'username' => '1202200001', 'email' => 'detail-ui@mahasiswa.test',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false,
    ]);
    $this->student = Student::create([
        'student_number' => '1202200001', 'name' => 'Nama Saat Diajukan',
        'email' => $this->user->email, 'academic_year' => 2022,
        'study_program' => 'Informatika', 'status' => 'Aktif', 'gpa' => 3.25,
    ]);
    foreach (['read', 'update', 'review'] as $action) {
        Permission::firstOrCreate(['name' => "sidang_ta_registration_{$action}", 'guard_name' => 'web']);
    }
    $this->user->givePermissionTo(['sidang_ta_registration_read', 'sidang_ta_registration_update']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->period = SidangTaPeriod::create([
        'name' => 'Periode Detail UI', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay(),
    ]);
    $this->snapshot = [
        'student_name' => 'Nama Saat Diajukan', 'student_number' => '1202200001',
        'study_program' => 'Informatika Historis', 'student_email' => 'historis@mahasiswa.test',
        'semester' => 8, 'gpa' => 3.25, 'passed_credits' => 140,
        'tak_score' => 75, 'eprt_score' => 500, 'ta_title' => 'Judul TA Historis',
        'ta_title_en' => 'Historical Thesis Title', 'advisor_1' => 'Dosen Historis',
        'academic_source_updated_at' => '01/11/2026 10:00', 'bpp_status' => 'paid',
    ];
    $this->makeRegistration = function (array $overrides = []) {
        return SidangTaRegistration::create(array_replace([
            'sidang_ta_period_id' => $this->period->id, 'student_number' => '1202200001',
            'student_name' => 'Nama Saat Diajukan', 'study_program' => 'Informatika Historis',
            'student_phone' => '081234567890', 'parent_phone' => '081234567891',
            'graduation_payment_date' => today()->toDateString(), 'diploma_biodata_accurate' => true,
            'status' => 'needs_review', 'submitted_at' => now(), 'academic_snapshot' => $this->snapshot,
        ], $overrides))->fresh();
    };
    $this->actingAs($this->user);
});

test('detail_uses_saved_academic_snapshot_after_student_data_changes', function () {
    $registration = ($this->makeRegistration)();
    $this->student->update(['name' => 'Nama Terbaru Berbeda', 'gpa' => 3.99]);
    $this->student->finalProject()->create(['thesis_title_id' => 'Judul TA Terbaru Berbeda']);
    $crawler = new Crawler($this->get(route('sidang-ta-registrations.show', $registration))->assertOk()->getContent());
    $academic = $crawler->filter('[data-academic-detail]');
    expect($academic->count())->toBe(1);
    expect($academic->text(null, true))->toContain('Nama Saat Diajukan')->toContain('Judul TA Historis')
        ->toContain('Historical Thesis Title')->not->toContain('Dosen Historis')
        ->not->toContain('Nama Terbaru Berbeda')->not->toContain('Judul TA Terbaru Berbeda');
    expect($academic->filter('[data-snapshot-field="gpa"]')->text(null, true))->toBe('3,25');
    expect($academic->filter('[data-academic-group]')->count())->toBe(4);
    $stored = $registration->fresh()->academic_snapshot;
    $expected = $this->snapshot;
    ksort($stored);
    ksort($expected);
    expect($stored)->toBe($expected);
});

test('detail_preserves_zero_null_and_extra_snapshot_values_with_escaped_output', function () {
    $registration = ($this->makeRegistration)(['academic_snapshot' => [
        'gpa' => 0, 'passed_credits' => 0, 'tak_score' => null,
        'ta_title' => '<script>alert(1)</script>',
        'legacy_flag' => false, 'legacy_meta' => ['catatan' => '<b>Tambahan</b>'],
    ]]);
    $crawler = new Crawler($this->get(route('sidang-ta-registrations.show', $registration))->assertOk()->getContent());
    $academic = $crawler->filter('[data-academic-detail]');
    expect($academic->filter('[data-snapshot-field="gpa"]')->text(null, true))->toBe('0,00');
    expect($academic->filter('[data-snapshot-field="passed_credits"]')->text(null, true))->toBe('0');
    expect($academic->filter('[data-snapshot-field="tak_score"]')->text(null, true))->toBe('Belum tersedia');
    expect($academic->filter('[data-academic-group="extra"]')->text(null, true))
        ->toContain('Legacy flag')->toContain('Tidak')->toContain('<b>Tambahan</b>');
    expect($academic->filter('[data-snapshot-field="ta_title"]')->text(null, true))->toBe('<script>alert(1)</script>');
    expect($academic->filter('script, b')->count())->toBe(0);
});

test('legacy_registration_with_null_snapshot_renders_fallback_without_live_student_values', function () {
    $registration = ($this->makeRegistration)(['academic_snapshot' => null]);
    $crawler = new Crawler($this->get(route('sidang-ta-registrations.show', $registration))->assertOk()->getContent());
    $academic = $crawler->filter('[data-academic-detail]');
    expect($academic->filter('[data-snapshot-field="student_name"]')->text(null, true))->toBe('Belum tersedia');
    expect($academic->filter('[data-snapshot-field="gpa"]')->text(null, true))->toBe('Belum tersedia');
    expect($academic->filter('[data-academic-group="extra"]')->count())->toBe(0);
    expect($registration->fresh()->academic_snapshot)->toBeNull();
});

test('owner_can_see_revise_button_through_email_and_username_fallback', function () {
    $registration = ($this->makeRegistration)();
    $url = route('sidang-ta-registrations.show', $registration);
    $crawler = new Crawler($this->get($url)->assertOk()->getContent());
    expect($crawler->filter('[data-revise-registration]')->attr('href'))
        ->toBe(route('sidang-ta-registrations.edit', $registration));
    $this->user->update(['email' => 'alias-detail@mahasiswa.test']);
    $this->actingAs($this->user->fresh());
    $crawler = new Crawler($this->get($url)->assertOk()->getContent());
    expect($crawler->filter('[data-revise-registration]')->count())->toBe(1);
    $this->get(route('sidang-ta-registrations.edit', $registration))->assertOk();
});

test('owner_without_update_permission_sees_no_revise_button_and_edit_is_forbidden', function () {
    $registration = ($this->makeRegistration)();
    $this->user->revokePermissionTo('sidang_ta_registration_update');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($this->user->fresh());
    $response = $this->get(route('sidang-ta-registrations.show', $registration))->assertOk()
        ->assertDontSee(route('sidang-ta-registrations.edit', $registration), false);
    $crawler = new Crawler($response->getContent());
    expect($crawler->filter('[data-revise-registration]')->count())->toBe(0);
    $this->get(route('sidang-ta-registrations.edit', $registration))->assertForbidden();
});

test('reviewer_without_student_can_read_detail_but_has_no_student_revise_button', function () {
    $registration = ($this->makeRegistration)();
    $reviewer = User::factory()->create([
        'username' => 'reviewer-detail-28', 'email' => 'reviewer-detail@example.test', 'employee_id' => null,
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false,
    ]);
    $reviewer->givePermissionTo(['sidang_ta_registration_read', 'sidang_ta_registration_update', 'sidang_ta_registration_review']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $response = $this->actingAs($reviewer)
        ->get(route('sidang-ta-registrations.show', $registration))->assertOk()
        ->assertDontSee(route('sidang-ta-registrations.edit', $registration), false);
    $crawler = new Crawler($response->getContent());
    expect($crawler->filter('[data-academic-detail]')->count())->toBe(1);
    expect($crawler->filter('[data-revise-registration]')->count())->toBe(0);
});

test('another_student_still_cannot_read_registration_detail', function () {
    $registration = ($this->makeRegistration)();
    $other = User::factory()->create([
        'username' => '1202200002', 'email' => 'other-detail@mahasiswa.test',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false,
    ]);
    Student::create([
        'student_number' => '1202200002', 'name' => 'Mahasiswa Lain', 'email' => $other->email,
        'academic_year' => 2022, 'study_program' => 'Informatika', 'status' => 'Aktif',
    ]);
    $other->givePermissionTo(['sidang_ta_registration_read', 'sidang_ta_registration_update']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($other)->get(route('sidang-ta-registrations.show', $registration))->assertForbidden();
});

test('detail_hides_history_keeps_private_preview_and_preserves_audit', function () {
    $registration = ($this->makeRegistration)(['status' => 'submitted']);
    $path = 'sidang_ta/detail-ui/berkas.pdf';
    Storage::disk('local')->put($path, '%PDF-detail');
    $document = $registration->documents()->create([
        'document_type' => 'thesis_draft', 'file_name' => 'berkas.pdf',
        'file_path' => $path, 'file_size' => 11, 'file_type' => 'pdf',
    ]);
    $registration->logAction('submitted', null, 'submitted', 'Pendaftaran pertama.', $this->user->id);
    $registration->logAction('needs_review', 'submitted', 'needs_review', 'Periksa dokumen.', $this->user->id);
    $registration->logAction('resubmitted', 'needs_review', 'submitted', '<b>Catatan literal</b>', $this->user->id);
    $crawler = new Crawler($this->get(route('sidang-ta-registrations.show', $registration))->assertOk()->getContent());
    expect($crawler->filter('[data-registration-history]')->count())->toBe(0);
    expect($crawler->text(null, true))->not->toContain('Pendaftaran pertama.')
        ->not->toContain('Periksa dokumen.')->not->toContain('Catatan literal');
    expect($crawler->filter('[data-revise-registration]')->count())->toBe(0);
    $preview = $crawler->filter('a[data-document-preview]');
    expect($preview->attr('href'))->toBe(route('sidang-ta-registration-documents.preview', $document));
    expect($preview->attr('data-download-url'))->toBe(route('sidang-ta-registration-documents.download', $document));
    expect(preg_split('/\s+/', trim($preview->attr('class'))))->toContain('btn')->toContain('btn-secondary');
    $this->get($preview->attr('href'))->assertOk();
    $this->get($preview->attr('data-download-url'))->assertDownload('berkas.pdf');
    expect($registration->logs()->where('action', 'resubmitted')->exists())->toBeTrue();
});

test('meet_detail_uses_saved_krs_label_and_hides_retired_snapshot_fields_for_student_and_staff', function () {
    $snapshot = array_replace($this->snapshot, [
        'ta2_krs_status' => 'eligible', 'ta2_krs_label' => 'Terdaftar saat pengajuan',
        'level_3_status' => 'HIDDEN-LEVEL3', 'bpp_status' => 'HIDDEN-BPP',
        'ibasila_status' => 'HIDDEN-BASILA', 'skpi_tak' => 'HIDDEN-AVAILABILITY',
        'advisor_1' => 'HIDDEN-ADVISOR', 'examiner_1' => 'HIDDEN-EXAMINER',
        'exam_date' => 'HIDDEN-DATE', 'exam_time' => 'HIDDEN-TIME', 'exam_location' => 'HIDDEN-LOCATION',
    ]);
    $registration = ($this->makeRegistration)(['academic_snapshot' => $snapshot]);
    $this->student->update(['name' => 'Live student changed']);
    $response = $this->get(route('sidang-ta-registrations.show', $registration))->assertOk();
    $reviewer = \App\Models\User::factory()->create(['status' => 'active', 'auth_source' => 'manual',
        'must_change_password' => false]);
    $reviewer->givePermissionTo('sidang_ta_registration_review');
    $staff = $this->actingAs($reviewer)->get(route('admin.sidang-ta-registrations.show', $registration))->assertOk();
    foreach ([$response, $staff] as $page) {
        $academic = (new Crawler($page->getContent()))->filter('[data-academic-detail]');
        expect($academic->filter('[data-snapshot-field="ta2_krs_label"]')->text(null, true))->toBe('Terdaftar saat pengajuan');
        expect($academic->text(null, true))->not->toContain('HIDDEN-')->not->toContain('Live student changed');
        expect($academic->filter('[data-academic-group="lecturers"], [data-academic-group="schedule"]')->count())->toBe(0);
        expect($academic->filter('[data-snapshot-field="ta2_krs_status"]')->count())->toBe(0);
        expect($academic->text(null, true))->not->toContain('Status BPP')->not->toContain('Sidang tingkat 3');
    }
    expect($registration->fresh()->academic_snapshot)->toEqual($snapshot);
});

test('meet_legacy_snapshot_without_krs_label_does_not_derive_one_from_live_data_or_raw_status', function () {
    $snapshot = array_replace($this->snapshot, ['ta2_krs_status' => 'eligible']);
    unset($snapshot['ta2_krs_label']);
    $registration = ($this->makeRegistration)(['academic_snapshot' => $snapshot]);
    $academic = (new Crawler($this->get(route('sidang-ta-registrations.show', $registration))
        ->assertOk()->getContent()))->filter('[data-academic-detail]');
    expect($academic->filter('[data-snapshot-field="ta2_krs_label"]')->text(null, true))->toBe('Belum tersedia');
    expect($academic->text(null, true))->not->toContain('eligible');
    expect($registration->fresh()->academic_snapshot)->not->toHaveKey('ta2_krs_label');
});
