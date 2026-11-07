<?php

use App\Models\AcademicPeriod;
use App\Models\Course;
use App\Models\StudentCourse;
use App\Services\StudentAcademicProfileImportService;
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
    $this->travelTo(\Carbon\Carbon::parse('2026-11-04 12:00:00', config('app.timezone')));
    Storage::fake('local');
    $this->user = User::factory()->create([
        'username' => '1202200001', 'email' => 'review-ui@mahasiswa.test',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false,
    ]);
    $this->student = Student::create([
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
    AcademicPeriod::query()->update(['is_active' => false]);
    $academic = AcademicPeriod::create(['year' => '2026/2027', 'semester' => 1, 'label' => 'Meet 61 KRS',
        'start_date' => '2026-08-01', 'end_date' => '2027-01-31', 'is_active' => true]);
    Course::create(['course_code' => 'MEET61-FINAL', 'name' => 'TUGAS AKHIR 2', 'academic_year' => '2026/2027',
        'semester_term' => 'GANJIL', 'study_program' => 'Informatika', 'class' => 'A', 'semester' => 8, 'credits' => 4]);
    StudentCourse::create(['student_number' => $this->student->student_number, 'course_code' => 'MEET61-FINAL',
        'course_name' => 'TUGAS AKHIR 2', 'course_class' => 'A', 'academic_year' => '2026/2027', 'semester' => 8,
        'academic_period_id' => $academic->id]);
    $row = json_decode(file_get_contents(base_path('docs/fixtures/sidang-ta/03-profil-akademik-dummy.json')),
        true, 512, JSON_THROW_ON_ERROR)['data'][0];
    $row['nim'] = $this->student->student_number;
    $row['nama'] = $this->student->name;
    $result = app(StudentAcademicProfileImportService::class)->import([$row]);
    expect($result['errors'])->toBe([]);
    $this->revisionSource = function () {
        $source = SidangTaRegistration::create([
            'sidang_ta_period_id' => $this->period->id,
            'student_number' => $this->student->student_number, 'student_name' => $this->student->name,
            'study_program' => $this->student->study_program, 'requirements_version' => 2,
            'academic_snapshot' => ['admission' => ['eprt' => ['mode' => 'automatic']]],
            'student_phone' => '081234567890', 'parent_phone' => '081234567891',
            'graduation_payment_date' => today()->toDateString(), 'diploma_biodata_accurate' => true,
            'status' => 'needs_review', 'submitted_by' => $this->user->id,
            'submitted_at' => now()->subHour(), 'reviewed_by' => $this->user->id,
            'reviewed_at' => now(), 'review_notes' => 'Perbaiki dokumen.',
        ]);
        $source->forceFill(['lock_version' => 2])->save();
        expect($source->fresh()->lock_version)->toBe(2);
        foreach (array_merge(Requirements::CAPTURES, Requirements::PAYMENT, Requirements::DIPLOMA) as $index => $type) {
            $path = 'sidang_ta/registrations/'.$source->id.'/'.$type.'.pdf';
            Storage::disk('local')->put($path, '%PDF-1.4 source final');
            $source->documents()->create(['document_type' => $type, 'file_name' => $type.'.pdf',
                'file_path' => $path, 'file_type' => 'pdf', 'file_size' => 25, 'status' => $index === 0 ? 'rejected' : 'verified',
                'notes' => $index === 0 ? 'Ganti capture.' : null]);
        }
        $source->logAction('submitted', null, 'submitted', 'Pendaftaran awal.', $this->user->id);
        $source->logAction('needs_review', 'submitted', 'needs_review', 'Perbaiki dokumen.', $this->user->id);
        return $source->fresh();
    };
    $this->reviewedDraft = function (bool $accurate = true) {
        $id = $this->postJson(route('sidang-ta-drafts.store'), [
            'sidang_ta_period_id' => $this->period->id,
            'student_phone' => '081234567890', 'parent_phone' => '081234567891',
        ])->assertCreated()->json('data.id');
        $draft = SidangTaRegistrationDraft::findOrFail($id);
        expect($draft->requirements_version)->toBe(2);
        foreach ([2, 3, 4] as $step) {
            $data = ['version' => $draft->fresh()->version];
            if ($step === 3) $data['graduation_payment_date'] = today()->toDateString();
            if ($step === 4) { $data['diploma_biodata_accurate'] = $accurate; if (!$accurate) $data['pdm_submission_date'] = today()->toDateString(); }
            foreach (Requirements::typesForStep($step, $accurate) as $type) {
                $data['documents'][$type] = \Illuminate\Http\UploadedFile::fake()
                    ->create($type.'.pdf', 100, 'application/pdf');
            }
            $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => $step]), $data)->assertOk();
        }
        $draft = $draft->fresh();
        expect($draft->documents()->count())->toBe($accurate ? 7 : 6);
        $this->postJson(route('sidang-ta-drafts.review', $draft), ['version' => $draft->version])->assertOk();
        return $draft->fresh();
    };
});

afterEach(function () { $this->travelBack(); });

test('review_summary_and_download_buttons_do_not_submit_registration', function () {
    $draft = ($this->reviewedDraft)();
    $response = $this->get(route('sidang-ta-drafts.preview', $draft))->assertOk();
    $crawler = new Crawler($response->getContent());
    $summary = $crawler->filter('[data-review-summary]');
    expect($summary->count())->toBe(1);
    expect($summary->text(null, true))->toContain('Review UI Test')->toContain('1202200001')
        ->toContain('Periode Review UI')->toContain('081234567890')->toContain('081234567891')
        ->toContain('7 dokumen')->toContain('Belum dikirim');
    expect($crawler->filter('[data-review-group]')->count())->toBe(3);
    $links = $crawler->filter('a[data-document-preview]');
    expect($links->count())->toBe(7);
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
    expect($crawler->filter('[data-open-registration]')->getNode(0)->hasAttribute('disabled'))->toBeFalse();
    expect($crawler->filter('[data-confirm-registration]')->getNode(0)->hasAttribute('disabled'))->toBeTrue();
    expect($crawler->filter('[data-confirm-registration]')->attr(':disabled'))->toBe('!canConfirm');
});

test('only_confirmation_form_submits_saved_draft_with_current_contract', function () {
    $draft = ($this->reviewedDraft)();
    $crawler = new Crawler($this->get(route('sidang-ta-drafts.preview', $draft))->assertOk()->getContent());
    $open = $crawler->filter('[data-open-registration]');
    expect($open->count())->toBe(1);
    expect($open->attr('type'))->toBe('button');
    expect($open->getNode(0)->hasAttribute('disabled'))->toBeFalse();
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
    expect($dialog->filter('[data-confirm-registration]')->getNode(0)->hasAttribute('disabled'))->toBeTrue();
    expect($dialog->filter('[data-confirm-registration]')->attr(':disabled'))->toBe('!canConfirm');
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
    expect($keys)->toBe(['_token', 'action', 'declaration_accepted', 'draft_id', 'version']);
    expect($body['draft_id'])->toBe($draft->id);
    expect($body['version'])->toBe((string) $draft->version);
    expect($body['action'])->toBe('submit');
    expect($form->filter('input[name="declaration_accepted"]')->getNode(0)->hasAttribute('checked'))->toBeFalse();
    $body['declaration_accepted'] = '1';
    expect(SidangTaRegistration::count())->toBe(0);

    $response = $this->post($form->attr('action'), $body);
    $registration = SidangTaRegistration::firstOrFail();
    $response->assertRedirect(route('sidang-ta-registrations.show', $registration));
    expect(SidangTaRegistration::count())->toBe(1);
    expect($registration->status)->toBe('submitted');
    expect($registration->documents()->count())->toBe(7);
    expect($draft->fresh()->status)->toBe('consumed');
});

test('pdm_review_displays_only_its_six_required_documents', function () {
    $draft = ($this->reviewedDraft)(false);
    $crawler = new Crawler($this->get(route('sidang-ta-drafts.preview', $draft))->assertOk()->getContent());
    expect($crawler->filter('[data-review-summary]')->text(null, true))
        ->toContain('6 dokumen')->toContain('Belum sesuai, bukti PDM dilampirkan');
    expect($crawler->filter('a[data-document-preview]')->count())->toBe(6);
    expect($crawler->filter('[data-review-document="pdm_submission_proof"]')->count())->toBe(1);
    expect($crawler->filter('[data-review-document="diploma_statement"]')->count())->toBe(0);
    expect($crawler->filter('[data-review-document="diploma_biodata_form"]')->count())->toBe(0);
});

test('revision_confirmation_form_keeps_original_registration_and_put_method', function () {
    $source = ($this->revisionSource)();
    $revisionId = $this->postJson(route('sidang-ta-drafts.revision', $source))
        ->assertCreated()->json('data.id');
    $revision = SidangTaRegistrationDraft::findOrFail($revisionId);
    expect($revision->requirements_version)->toBe(2);
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $revision, 'step' => 2]), [
        'version' => $revision->version,
        'documents' => ['ta_data_capture' => \Illuminate\Http\UploadedFile::fake()
            ->create('replacement.pdf', 100, 'application/pdf')],
    ])->assertOk();
    $revision = $revision->fresh();
    $this->postJson(route('sidang-ta-drafts.review', $revision), ['version' => $revision->version])->assertOk();
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
    expect($crawler->filter('a[data-document-preview]')->count())->toBe(7);
    expect($crawler->filter('[data-open-registration]')->count())->toBe(0);
    expect($crawler->filter('#confirm-send-registration')->count())->toBe(0);
    expect($crawler->filter('form[data-final-registration-form]')->count())->toBe(0);
    expect(SidangTaRegistration::count())->toBe(0);
});


test('unknown_eprt_allows_seven_document_review_but_blocks_send', function () {
    \App\Models\StudentEprt::where('student_number', $this->student->student_number)->delete();
    expect(app(\App\Services\SidangTaAdmissionFacts::class)->forStudent($this->student)['eprt']['mode'])
        ->toBe('unknown');
    $draft = ($this->reviewedDraft)();
    $crawler = new Crawler($this->get(route('sidang-ta-drafts.preview', $draft))->assertOk()->getContent());
    expect($draft->requirements_version)->toBe(2);
    expect($crawler->filter('a[data-document-preview]')->count())->toBe(7);
    expect($crawler->filter('[data-review-group="3"] [data-review-document]')->count())->toBe(1);
    expect($crawler->filter('[data-review-document="eprt_waiver_letter"]')->count())->toBe(0);
    expect($crawler->filter('[data-open-registration]')->getNode(0)->hasAttribute('disabled'))->toBeTrue();
    expect($crawler->filter('[data-confirm-registration]')->getNode(0)->hasAttribute('disabled'))->toBeTrue();
    $this->postJson(route('sidang-ta-registrations.store'), [
        'action' => 'submit', 'declaration_accepted' => true, 'draft_id' => $draft->id, 'version' => $draft->version,
    ])->assertUnprocessable();
    expect(SidangTaRegistration::count())->toBe(0);
    expect($draft->fresh()->status)->toBe('active');
});
