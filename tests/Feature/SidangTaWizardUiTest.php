<?php

use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDraft;
use App\Models\Student;
use App\Models\User;
use App\Support\SidangTaDocumentRequirements as Requirements;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create([
        'username' => '1202200001', 'email' => 'wizard-ui@mahasiswa.test',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false,
    ]);
    $this->student = Student::create([
        'student_number' => '1202200001', 'name' => 'Wizard UI Test',
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
        'name' => 'Periode G1', 'is_active' => true,
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

function sidangTaUiXpath(string $html): DOMXPath
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

test('g1_fresh_step_one_is_active_without_creating_draft_or_unlocking_future_steps', function () {
    $response = $this->get(route('sidang-ta-registrations.create'))->assertOk();
    $xpath = sidangTaUiXpath($response->getContent());
    $active = $xpath->query('//*[@data-step="1" and @aria-current="step"]')->item(0);
    expect($active)->not->toBeNull();
    expect($active->getAttribute('class'))->toContain('bg-[#DA2D3B]');
    expect($xpath->query('//*[@aria-current="step"]')->length)->toBe(1);
    expect($xpath->query('//a[@data-step="2" or @data-step="3" or @data-step="4"]')->length)->toBe(0);
    expect($xpath->query('//*[@data-step="2" and @aria-disabled="true"]')->length)->toBe(1);
    expect($xpath->query('//*[@data-chevron="1"]')->item(0)->getAttribute('class'))->toContain('text-[#DA2D3B]');
    expect($xpath->query('//section[@aria-label="Progres pendaftaran"]//*[@data-save-status]')->length)->toBe(1);
    $response->assertSee('Belum ada data yang disimpan.');
    expect(SidangTaRegistrationDraft::count())->toBe(0);
});

test('g1_step_one_errors_are_inline_and_preserve_valid_contact_input', function () {
    $url = route('sidang-ta-registrations.create');
    $this->from($url)->post(route('sidang-ta-drafts.store'), [
        'sidang_ta_period_id' => '', 'student_phone' => '', 'parent_phone' => '089123456789',
    ])->assertRedirect($url)->assertSessionHasErrors(['sidang_ta_period_id', 'student_phone']);
    $response = $this->get($url)->assertOk();
    $xpath = sidangTaUiXpath($response->getContent());
    expect($xpath->query('//form[@id="sidang-ta-step-form" and @novalidate]')->length)->toBe(1);
    expect($xpath->query('//*[@id="error-sidang_ta_period_id"]')->length)->toBe(1);
    expect($xpath->query('//*[@id="error-student_phone"]')->item(0)->textContent)->toContain('Nomor HP mahasiswa wajib diisi.');
    expect($xpath->query('//input[@name="student_phone" and @aria-invalid="true"]')->length)->toBe(1);
    expect($xpath->query('//input[@name="parent_phone"]')->item(0)->getAttribute('value'))->toBe('089123456789');
    expect(SidangTaRegistrationDraft::count())->toBe(0);
});

test('g1_academic_panel_is_present_on_all_four_steps_and_keeps_zero_gpa', function () {
    $this->student->finalProject()->create([
        'thesis_title_id' => 'Judul akademik untuk G1',
        'thesis_title_en' => 'Academic title for G1', 'advisor_1' => 'Dosen Pembimbing G1',
    ]);
    $draft = ($this->completeDraft)();
    foreach ([1, 2, 3, 4] as $step) {
        $response = $this->get(route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => $step]))
            ->assertOk()->assertSee('Judul akademik untuk G1')->assertSee('Academic title for G1')
            ->assertDontSee('Dosen Pembimbing G1')->assertSee('0,00')
            ->assertSee('KRS Sidang TA 2')->assertDontSee('Pembimbing &amp; Penguji', false)
            ->assertDontSee('Jadwal sidang TA')->assertDontSee('Sidang tingkat 3')
            ->assertSee('Status BPP')->assertDontSee('Status i-Basila')->assertDontSee('Ketersediaan TAK');
        $xpath = sidangTaUiXpath($response->getContent());
        expect($xpath->query('//aside[@data-academic-panel]')->length)->toBe(1);
        expect($xpath->query('//aside[@data-academic-panel]//*[@data-save-status]')->length)->toBe(0);
    }
    expect(SidangTaRegistration::count())->toBe(0);
});


test('g1_saved_draft_still_cannot_navigate_to_unfinished_future_step', function () {
    $id = $this->postJson(route('sidang-ta-drafts.store'), $this->data)->assertCreated()->json('data.id');
    $response = $this->get(route('sidang-ta-drafts.edit', ['draft' => $id, 'step' => 2]))->assertOk();
    $xpath = sidangTaUiXpath($response->getContent());
    expect($xpath->query('//a[@data-step="1"]')->length)->toBe(1);
    expect($xpath->query('//*[@data-step="2" and @aria-current="step"]')->length)->toBe(1);
    expect($xpath->query('//a[@data-step="3" or @data-step="4"]')->length)->toBe(0);
    $this->get(route('sidang-ta-drafts.edit', ['draft' => $id, 'step' => 4]))
        ->assertRedirect(route('sidang-ta-drafts.edit', ['draft' => $id, 'step' => 2]));
});

test('g1_payment_date_error_is_inline_and_does_not_change_saved_documents', function () {
    $draft = ($this->completeDraft)();
    $url = route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 3]);
    $this->from($url)->post(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 3]), [
        'version' => $draft->version, 'graduation_payment_date' => today()->addDay()->toDateString(),
    ])->assertRedirect($url)->assertSessionHasErrors('graduation_payment_date');
    $response = $this->get($url)->assertOk();
    $xpath = sidangTaUiXpath($response->getContent());
    expect($xpath->query('//*[@id="error-graduation_payment_date"]')->item(0)->textContent)
        ->toContain('Tanggal pembayaran wisuda tidak boleh melewati hari ini.');
    expect($draft->fresh()->version)->toBe($draft->version);
    expect($draft->documents()->count())->toBe(7);
});

test('g1_inactive_biodata_upload_is_initially_disabled_and_has_dynamic_binding', function () {
    $draft = ($this->completeDraft)();
    $response = $this->get(route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 4]))->assertOk();
    $xpath = sidangTaUiXpath($response->getContent());
    $inactive = $xpath->query('//input[@id="file-pdm_submission_proof"]')->item(0);
    expect($inactive->hasAttribute('disabled'))->toBeTrue();
    expect($inactive->getAttribute(':disabled'))->toBe("accurate !== '0'");
    expect($xpath->query('//input[@id="file-diploma_statement" and @disabled]')->length)->toBe(0);
});

test('g1_non_field_errors_remain_visible_in_summary', function () {
    $draft = ($this->completeDraft)();
    $errors = (new ViewErrorBag())->put('default', new MessageBag([
        'version' => ['Versi draft tidak valid. Buka ulang draft terbaru.'],
        'documents' => ['Jenis dokumen tidak sesuai tahap atau cabang yang dipilih.'],
    ]));
    $response = $this->withSession(['errors' => $errors])
        ->get(route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 2]))->assertOk();
    $xpath = sidangTaUiXpath($response->getContent());
    $summary = $xpath->query('//*[@data-error-summary]')->item(0);
    expect($summary->textContent)->toContain('Versi draft tidak valid.')
        ->toContain('Jenis dokumen tidak sesuai tahap');
});

test('g1_invalid_biodata_error_is_attached_to_radio_group', function () {
    $draft = ($this->completeDraft)();
    $url = route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 4]);
    $this->from($url)->post(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 4]), [
        'version' => $draft->version, 'diploma_biodata_accurate' => 'invalid',
    ])->assertRedirect($url)->assertSessionHasErrors('diploma_biodata_accurate');
    $response = $this->get($url)->assertOk();
    $xpath = sidangTaUiXpath($response->getContent());
    expect($xpath->query('//fieldset[@id="diploma-biodata-group" and @aria-invalid="true"]')->length)->toBe(1);
    expect($xpath->query('//*[@id="error-diploma_biodata_accurate"]')->item(0)->textContent)
        ->toContain('Pilihan kesesuaian biodata tidak valid.');
    expect($draft->fresh()->version)->toBe($draft->version);
    expect($draft->documents()->count())->toBe(7);
});

test('g1_missing_saved_file_keeps_step_incomplete_and_preserves_other_saved_files', function () {
    $draft = ($this->completeDraft)();
    $document = $draft->documents()->where('document_type', Requirements::CAPTURES[0])->firstOrFail();
    $other = $draft->documents()->where('document_type', Requirements::CAPTURES[1])->firstOrFail();
    Storage::disk('local')->delete($document->file_path);
    $url = route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 2]);
    $this->from($url)->post(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 2]), [
        'version' => $draft->version,
    ])->assertRedirect($url)->assertSessionHasNoErrors();
    $response = $this->get($url)->assertOk();
    $crawler = new \Symfony\Component\DomCrawler\Crawler($response->getContent());
    expect($crawler->filter('[data-step-progress]')->text(null, true))->toContain('1 dari 2 dokumen tersimpan.')
        ->toContain(\App\Models\SidangTaRegistration::DOCUMENT_TYPES[$document->document_type]);
    expect($crawler->filter('button[data-next-step][disabled]')->count())->toBe(1);
    $response->assertDontSee(route('sidang-ta-draft-documents.download', $other), false)
        ->assertDontSee(route('sidang-ta-draft-documents.download', $document), false)
        ->assertSee($other->file_name);
    expect(Storage::disk('local')->exists($other->file_path))->toBeTrue();
    expect($draft->fresh()->version)->toBe($draft->version + 1);
    expect($draft->documents()->count())->toBe(7);
});

test('meet_krs_requires_one_active_period_and_accepted_ta_names', function () {
    \App\Models\AcademicPeriod::query()->update(['is_active' => false]);
    $service = app(\App\Services\SidangTaAcademicSnapshot::class);
    expect($service->ta2KrsStatus($this->student))->toBe('unknown');
    $period = \App\Models\AcademicPeriod::create(['year' => '2026/2027', 'semester' => 1,
        'label' => 'Meet KRS', 'start_date' => '2026-08-01', 'end_date' => '2027-01-31', 'is_active' => true]);
    $course = \App\Models\Course::create(['course_code' => 'MEET-TA2', 'name' => 'TUGAS AKHIR 2',
        'academic_year' => '2026/2027', 'semester_term' => 'GANJIL', 'study_program' => 'Informatika',
        'class' => 'A', 'semester' => 8, 'credits' => 4, 'type' => 'Wajib', 'status' => 'Aktif']);
    $row = \App\Models\StudentCourse::create(['student_number' => $this->student->student_number,
        'course_code' => $course->course_code, 'course_name' => 'TUGAS AKHIR', 'course_class' => 'A',
        'academic_year' => '2026/2027', 'semester' => 8, 'academic_period_id' => $period->id]);
    foreach (['TUGAS AKHIR 1', 'TUGAS AKHIR 20', 'PROPOSAL TUGAS AKHIR'] as $name) {
        $row->update(['course_name' => $name]);
        expect($service->ta2KrsStatus($this->student))->toBe('unconfirmed');
    }
    foreach (['TUGAS AKHIR', '  tugas akhir  2  '] as $name) {
        $row->update(['course_name' => $name]);
        expect($service->ta2KrsStatus($this->student))->toBe('eligible');
    }
    $snapshot = $service->forStudent($this->student->fresh());
    expect($snapshot['ta2_krs_status'])->toBe('eligible');
    expect($snapshot['ta2_krs_label'])->toBe('Terdaftar');
    $row->update(['academic_period_id' => null]);
    expect($service->ta2KrsStatus($this->student))->toBe('unknown');
    $row->update(['academic_period_id' => $period->id]);
    $period->update(['is_active' => false]);
    expect($service->ta2KrsStatus($this->student))->toBe('unknown');
});
