<?php

use App\Models\AcademicPeriod;
use App\Models\Course;
use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDraft;
use App\Models\Student;
use App\Models\StudentCourse;
use App\Models\StudentEprt;
use App\Models\StudentTak;
use App\Models\User;
use App\Services\SidangTaAdmissionFacts;
use App\Services\StudentAcademicProfileImportService;
use App\Support\SidangTaMeetRequirements as Meet;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\DomCrawler\Crawler;

beforeEach(function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-11-04 12:00:00', config('app.timezone')));
    Storage::fake('local');
    $this->user = User::factory()->create(['username' => '1202299055', 'email' => 'meet-ui@test.local',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
    $this->student = Student::create(['student_number' => $this->user->username, 'name' => 'Meet UI',
        'email' => $this->user->email, 'academic_year' => 2022, 'study_program' => 'Informatika', 'status' => 'Aktif']);
    foreach (['read', 'create', 'update'] as $action) {
        $permission = Permission::firstOrCreate(['name' => 'sidang_ta_registration_'.$action, 'guard_name' => 'web']);
        $this->user->givePermissionTo($permission);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($this->user);
    $this->period = SidangTaPeriod::create(['name' => 'Meet UI', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay()]);
    AcademicPeriod::query()->update(['is_active' => false]);
    $academic = AcademicPeriod::create(['year' => '2026/2027', 'semester' => 1, 'label' => 'Meet UI KRS',
        'start_date' => '2026-08-01', 'end_date' => '2027-01-31', 'is_active' => true]);
    Course::create(['course_code' => 'MEET-UI55', 'name' => 'TUGAS AKHIR 2', 'academic_year' => '2026/2027',
        'semester_term' => 'GANJIL', 'study_program' => 'Informatika', 'class' => 'A', 'semester' => 8, 'credits' => 4]);
    StudentCourse::create(['student_number' => $this->student->student_number, 'course_code' => 'MEET-UI55',
        'course_name' => 'TUGAS AKHIR 2', 'course_class' => 'A', 'academic_year' => '2026/2027', 'semester' => 8,
        'academic_period_id' => $academic->id]);
    $this->meetDraft = function (string $mode = 'automatic', bool $accurate = true, bool $complete = true) {
        $rows = json_decode(file_get_contents(base_path('docs/fixtures/sidang-ta/03-profil-akademik-dummy.json')), true, 512, JSON_THROW_ON_ERROR)['data'];
        $row = $rows[$mode === 'waiver' ? 1 : ($mode === 'retest' ? 2 : 0)];
        $row['nim'] = $this->student->student_number;
        $row['nama'] = $this->student->name;
        if ($mode === 'unknown') $row['eprt'] = [];
        StudentEprt::where('student_number', $this->student->student_number)->delete();
        $result = app(StudentAcademicProfileImportService::class)->import([$row]);
        expect($result['errors'])->toBe([]);
        $draft = SidangTaRegistrationDraft::create(['owner_user_id' => $this->user->id,
            'student_number' => $this->student->student_number, 'sidang_ta_period_id' => $this->period->id,
            'requirements_version' => 2, 'payload' => ['student_phone' => '081234567890', 'parent_phone' => '081234567891',
                'graduation_payment_date' => today()->toDateString(), 'diploma_biodata_accurate' => $accurate]]);
        if ($complete) {
            foreach (Meet::allTypes($accurate, $mode) as $type) {
                $path = 'sidang_ta/drafts/'.$draft->id.'/'.$type.'.pdf';
                Storage::disk('local')->put($path, '%PDF-1.4 meet ui');
                $draft->documents()->create(['document_type' => $type, 'file_path' => $path,
                    'file_name' => $type.'.pdf', 'file_type' => 'pdf', 'file_size' => 16]);
            }
        }
        return $draft->fresh();
    };
    $this->reviewPage = function ($draft) {
        $this->postJson(route('sidang-ta-drafts.review', $draft), ['version' => $draft->version])->assertOk();
        return $this->get(route('sidang-ta-drafts.preview', $draft))->assertOk();
    };
});

afterEach(function () { $this->travelBack(); });

test('meet_ui_stage_two_has_two_readonly_cards_and_only_two_uploads', function () {
    $draft = ($this->meetDraft)('automatic', true, false);
    $this->student->basilaApproval()->updateOrCreate(['student_number' => $this->student->student_number],
        ['photo_approval' => true, 'status' => 'approved']);
    $crawler = new Crawler($this->get(route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 2]))->assertOk()->getContent());
    $form = $crawler->filter('#sidang-ta-step-form');
    expect($form->filter('[data-upload-type]')->count())->toBe(2);
    expect($form->filter('[data-upload-type="ta_data_capture"]')->count())->toBe(1);
    expect($form->filter('[data-upload-type="online_supervision_capture"]')->count())->toBe(1);
    expect($form->filter('[data-automatic-card]')->count())->toBe(2);
    expect($form->filter('[data-automatic-card] input, [data-automatic-card] textarea, [data-automatic-card] select')->count())->toBe(0);
    expect($form->filter('[data-automatic-card="sidang_approval"]')->text(null, true))->toContain('Belum tersedia');
    expect($form->filter('[data-automatic-card="level_2"]')->text(null, true))->toContain('Lulus');
    expect($form->filter('[data-step-progress]')->text(null, true))->toContain('2 dokumen tersimpan.');
    expect($form->filter('[data-upload-type="skpi_tak_capture"]')->count())->toBe(0);
});

test('meet_ui_scalar_labels_have_no_required_star_but_keep_required_validation', function () {
    $draft = ($this->meetDraft)();
    foreach ([1, 3, 4] as $step) {
        $form = (new Crawler($this->get(route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => $step]))
            ->assertOk()->getContent()))->filter('#sidang-ta-step-form');
        $labels = $form->filter('label:not([for^="file-"]), legend');
        $labels->each(function (Crawler $label) { expect($label->text(null, true))->not->toContain('*'); });
    }
    $form = (new Crawler($this->get(route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 1]))
        ->assertOk()->getContent()))->filter('#sidang-ta-step-form');
    expect($form->getNode(0)->hasAttribute('novalidate'))->toBeTrue();
    expect($form->filter('input[name="student_phone"]')->getNode(0)->hasAttribute('required'))->toBeTrue();
});

test('meet_ui_automatic_eprt_has_three_stage_three_uploads_and_no_certificate', function () {
    $draft = ($this->meetDraft)();
    $form = (new Crawler($this->get(route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 3]))
        ->assertOk()->getContent()))->filter('#sidang-ta-step-form');
    expect($form->filter('[data-upload-type]')->count())->toBe(3);
    expect($form->filter('[data-automatic-card="eprt"]')->text(null, true))->toContain('Nilai 450');
    expect($form->filter('[data-automatic-card="bpp"]')->text(null, true))->toContain('Lunas')->toContain('Periode pembayaran belum terkonfirmasi');
    expect($form->filter('[data-upload-type="eprt_lac_certificate"], [data-upload-type="bpp_payment_proof"], [data-upload-type="transcript_khs"], [data-upload-type="ta_course_ksm"]')->count())->toBe(0);
    expect($form->filter('input[name="eprt_score"], input[name="tak_score"]')->count())->toBe(0);
});

test('meet_ui_waiver_upload_has_pdf_format_and_masked_template_link', function () {
    $draft = ($this->meetDraft)('waiver');
    $form = (new Crawler($this->get(route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 3]))
        ->assertOk()->getContent()))->filter('#sidang-ta-step-form');
    expect($form->filter('[data-upload-type]')->count())->toBe(4);
    $waiver = $form->filter('[data-upload-type="eprt_waiver_letter"]');
    expect($waiver->text(null, true))->toContain('Surat Permakluman EPRT');
    expect($waiver->filter('input[type="file"]')->attr('accept'))->toBe('.pdf');
    expect($waiver->filter('[data-waiver-template]')->text(null, true))->toBe('Link Surat Permakluman');
    expect($waiver->filter('[data-waiver-template]')->attr('href'))->toBe('https://telkomuniversityofficial-my.sharepoint.com/:w:/g/personal/akademiksby_telkomuniversity_ac_id/EYC5y40Ky_1PhVWh4_LswJoBtC8yKT6PuTEAwUTDiFY-2A?e=qRenjo');
});

test('meet_ui_retest_and_unknown_can_open_review_with_five_academic_checks', function () {
    foreach (['retest', 'unknown'] as $mode) {
        $draft = ($this->meetDraft)($mode);
        $crawler = new Crawler(($this->reviewPage)($draft)->getContent());
        expect($crawler->filter('[data-admission-gate]')->count())->toBe(5);
        expect($crawler->filter('[data-eprt-mode="'.$mode.'"]')->count())->toBe(1);
        foreach (['[data-open-registration]', '[data-confirm-registration]'] as $selector) {
            expect($crawler->filter($selector)->getNode(0)->hasAttribute('disabled'))->toBeTrue();
            expect($crawler->filter($selector)->attr(':disabled'))->toBe('sending || true');
        }
        expect($crawler->filter('[data-review-document]')->count())->toBe(7);
        $this->deleteJson(route('sidang-ta-drafts.destroy', $draft), ['version' => $draft->version])->assertNoContent();
    }
});

test('meet_ui_waiver_review_has_eight_previews_and_enabled_send_buttons', function () {
    $draft = ($this->meetDraft)('waiver');
    $crawler = new Crawler(($this->reviewPage)($draft)->getContent());
    expect($crawler->filter('[data-review-document]')->count())->toBe(8);
    expect($crawler->filter('[data-review-document="eprt_waiver_letter"]')->text(null, true))->toContain('Surat Permakluman EPRT');
    expect($crawler->filter('[data-review-document] a[data-document-preview]')->count())->toBe(8);
    expect($crawler->filter('[data-review-summary]')->text(null, true))->toContain('8 dokumen');
    expect($crawler->filter('[data-meet-send-held]')->count())->toBe(0);
    foreach (['[data-open-registration]', '[data-confirm-registration]'] as $selector) {
        $button = $crawler->filter($selector);
        expect($button->getNode(0)->hasAttribute('disabled'))->toBeFalse();
        expect($button->attr(':disabled'))->toBe('sending || false');
    }
    expect($crawler->filter('form[data-final-registration-form] input[type="file"]')->count())->toBe(0);
    expect(SidangTaRegistration::count())->toBe(0);
});

test('meet_ui_pdm_review_has_six_uploads_and_excludes_legacy_requirements', function () {
    $draft = ($this->meetDraft)('automatic', false);
    $crawler = new Crawler(($this->reviewPage)($draft)->getContent());
    expect($crawler->filter('[data-review-document]')->count())->toBe(6);
    expect($crawler->filter('[data-review-document="pdm_submission_proof"]')->count())->toBe(1);
    expect($crawler->filter('[data-review-document="diploma_statement"], [data-review-document="transcript_khs"]')->count())->toBe(0);
});

test('meet_ui_failed_tak_is_shown_without_blocking_the_review_page', function () {
    $draft = ($this->meetDraft)();
    StudentTak::where('student_number', $this->student->student_number)->update(['total_points' => 59]);
    $crawler = new Crawler(($this->reviewPage)($draft)->getContent());
    $gate = $crawler->filter('[data-admission-gate="tak_score"]');
    expect($gate->attr('data-gate-state'))->toBe('failed');
    expect($gate->text(null, true))->toContain('TAK minimal 60')->toContain('Belum memenuhi');
    expect($crawler->filter('[data-academic-blockers]')->text(null, true))->toContain('TAK minimal 60');
    expect($crawler->filter('[data-open-registration]')->getNode(0)->hasAttribute('disabled'))->toBeTrue();
    expect($crawler->filter('[data-confirm-registration]')->attr(':disabled'))->toBe('sending || true');
    expect($draft->fresh()->status)->toBe('active');
});

test('meet_ui_does_not_use_old_input_to_forge_automatic_scores_or_waiver_mode', function () {
    $draft = ($this->meetDraft)('waiver');
    $this->withSession(['_old_input' => ['eprt_score' => 999, 'tak_score' => 999, 'eprt_mode' => 'automatic']]);
    $form = (new Crawler($this->get(route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 3]))
        ->assertOk()->getContent()))->filter('#sidang-ta-step-form');
    expect($form->filter('[data-automatic-card="eprt"]')->text(null, true))->toContain('Nilai 449')->not->toContain('999');
    expect($form->filter('[data-upload-type="eprt_waiver_letter"]')->count())->toBe(1);
});

test('meet_ui_read_only_owner_can_preview_without_send_controls', function () {
    $draft = ($this->meetDraft)();
    $this->postJson(route('sidang-ta-drafts.review', $draft), ['version' => $draft->version])->assertOk();
    $this->user->revokePermissionTo(['sidang_ta_registration_create', 'sidang_ta_registration_update']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($this->user->fresh());
    $crawler = new Crawler($this->get(route('sidang-ta-drafts.preview', $draft))->assertOk()->getContent());
    expect($crawler->filter('a[data-document-preview]')->count())->toBe(7);
    expect($crawler->filter('[data-open-registration], #confirm-send-registration')->count())->toBe(0);
});

test('meet_ui_waiver_file_name_is_escaped_in_wizard_and_review', function () {
    $draft = ($this->meetDraft)('waiver');
    $document = $draft->documents()->where('document_type', Meet::WAIVER)->firstOrFail();
    $document->update(['file_name' => '<script>alert(1)</script>.pdf']);
    $wizard = new Crawler($this->get(route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 3]))->assertOk()->getContent());
    $review = new Crawler(($this->reviewPage)($draft)->getContent());
    foreach ([$wizard->filter('[data-upload-type="eprt_waiver_letter"]'), $review->filter('[data-review-document="eprt_waiver_letter"]')] as $row) {
        expect($row->text(null, true))->toContain('<script>alert(1)</script>.pdf');
        expect($row->filter('script')->count())->toBe(0);
    }
});

test('meet_ui_mode_change_between_validation_and_render_returns_conflict_not_null_document_error', function () {
    $draft = ($this->meetDraft)();
    $this->postJson(route('sidang-ta-drafts.review', $draft), ['version' => $draft->version])->assertOk();
    $real = app(SidangTaAdmissionFacts::class);
    $calls = 0;
    $fake = Mockery::mock(SidangTaAdmissionFacts::class);
    $fake->shouldReceive('forStudent')->andReturnUsing(function ($student) use ($real, &$calls) {
        $result = $real->forStudent($student);
        if (++$calls === 2) {
            $result['eprt'] = ['mode' => 'waiver', 'requires_waiver' => true, 'message' => 'Unggah Surat Permakluman.'];
            $result['eprt_facts'] = ['score' => 449, 'confirmed_attempts' => 3];
        }
        return $result;
    });
    $this->app->instance(SidangTaAdmissionFacts::class, $fake);
    $paths = Storage::disk('local')->allFiles();
    $this->get(route('sidang-ta-drafts.preview', $draft))->assertConflict();
    expect($calls)->toBe(2);
    expect($draft->documents()->count())->toBe(7);
    expect(Storage::disk('local')->allFiles())->toBe($paths);
});

test('meet_ui_bpp_is_visible_on_every_step_without_adding_an_academic_gate', function () {
    $draft = ($this->meetDraft)();
    $this->student->bppPayment()->firstOrFail()->update(['status' => 'paid']);
    foreach ([1, 2, 3, 4] as $step) {
        $crawler = new Crawler($this->get(route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => $step]))
            ->assertOk()->getContent());
        $panel = $crawler->filter('[data-academic-panel]');
        expect($panel->text(null, true))->toContain('Status BPP')->toContain('Periode pembayaran belum terkonfirmasi');
        expect($panel->filter('[data-academic-field="bpp_status"]')->text(null, true))->toBe('Lunas');
    }
    $this->student->bppPayment()->firstOrFail()->update(['status' => 'unpaid']);
    $crawler = new Crawler(($this->reviewPage)($draft)->getContent());
    expect($crawler->filter('[data-admission-gate]')->count())->toBe(5);
    expect($crawler->filter('[data-open-registration]')->getNode(0)->hasAttribute('disabled'))->toBeFalse();
    expect($crawler->filter('[data-confirm-registration]')->getNode(0)->hasAttribute('disabled'))->toBeFalse();
});

test('meet_ui_missing_bpp_is_displayed_as_unavailable', function () {
    $draft = ($this->meetDraft)();
    $this->student->bppPayment()->delete();
    $crawler = new Crawler($this->get(route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 2]))
        ->assertOk()->getContent());
    expect($crawler->filter('[data-academic-panel] [data-academic-field="bpp_status"]')->text(null, true))->toBe('Belum tersedia');
});

test('meet_ui_unconfirmed_krs_disables_send_visibly_and_reenables_after_period_link_is_restored', function () {
    $draft = ($this->meetDraft)();
    $krs = StudentCourse::where('student_number', $this->student->student_number)->firstOrFail();
    $periodId = $krs->academic_period_id;
    $krs->update(['academic_period_id' => null]);
    $crawler = new Crawler(($this->reviewPage)($draft)->getContent());
    expect($crawler->filter('[data-admission-gate="ta2_registered"]')->attr('data-gate-state'))->toBe('unknown');
    expect($crawler->filter('#registration-send-readiness')->count())->toBe(1);
    foreach (['[data-open-registration]', '[data-confirm-registration]'] as $selector) {
        $button = $crawler->filter($selector);
        expect($button->getNode(0)->hasAttribute('disabled'))->toBeTrue();
        expect($button->attr(':disabled'))->toBe('sending || true');
        expect($button->attr('aria-disabled'))->toBe('true');
        expect($button->attr('aria-describedby'))->toBe('registration-send-readiness');
        expect($button->attr('class'))->toContain('disabled:opacity-50')->toContain('disabled:cursor-not-allowed');
    }
    $krs->update(['academic_period_id' => $periodId]);
    $crawler = new Crawler($this->get(route('sidang-ta-drafts.preview', $draft))->assertOk()->getContent());
    expect($crawler->filter('[data-admission-gate="ta2_registered"]')->attr('data-gate-state'))->toBe('passed');
    expect($crawler->filter('#registration-send-readiness')->count())->toBe(0);
    foreach (['[data-open-registration]', '[data-confirm-registration]'] as $selector) {
        $button = $crawler->filter($selector);
        expect($button->getNode(0)->hasAttribute('disabled'))->toBeFalse();
        expect($button->attr(':disabled'))->toBe('sending || false');
        expect($button->attr('aria-disabled'))->toBe('false');
    }
    expect($crawler->filter('[data-open-registration]')->attr('aria-controls'))->toBe('confirm-send-registration');
    expect($crawler->filter('#confirm-send-registration')->count())->toBe(1);
    expect(SidangTaRegistration::count())->toBe(0);
});

test('meet_ui_capture_examples_open_the_shared_modal_without_navigation_or_form_submission', function () {
    $draft = ($this->meetDraft)();
    $crawler = new Crawler($this->get(route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 2]))
        ->assertOk()->getContent());
    foreach (['ta_data_capture' => 'ta-data.png', 'online_supervision_capture' => 'bimbingan-online.png'] as $type => $filename) {
        $guide = $crawler->filter('[data-upload-type="'.$type.'"] button[data-upload-guide]');
        expect($guide->count())->toBe(1);
        expect($guide->attr('type'))->toBe('button');
        expect($guide->getNode(0)->hasAttribute('href'))->toBeFalse();
        expect($guide->getNode(0)->hasAttribute('target'))->toBeFalse();
        expect($guide->attr('aria-haspopup'))->toBe('dialog');
        expect($guide->attr('data-preview-url'))->toBe(asset('assets/images/forms/sidang-ta/'.$filename));
        expect($guide->attr('data-download-url'))->toBe('');
        expect($guide->attr('data-file-type'))->toBe('png');
        expect($guide->attr('x-on:click'))->toContain("sidang-ta-preview");
        expect($guide->filter('img')->attr('src'))->toBe($guide->attr('data-preview-url'));
        expect($guide->filter('img')->attr('alt'))->not->toBe('');
    }
    expect($crawler->filter('[data-document-preview-modal]')->count())->toBe(1);
    expect($crawler->filter('[data-preview-download]')->attr('x-show'))->toBe('downloadUrl');
});
