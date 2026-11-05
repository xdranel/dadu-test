# 55 — Meet E3: form dan tinjau versi 2 sesuai upload dan data otomatis

**Tujuan:** menampilkan kartu otomatis dan upload yang sesuai pada draft meet, termasuk Surat Permakluman bersyarat serta lima pemeriksaan akademik pada Tinjau.
**Arsitektur:** controller menyiapkan satu konteks fakta untuk render. Versi2 memakai registry51 dan adapter50; versi1 mempertahankan form lama. Shared partial membuat kartu readonly, sedangkan upload memakai komponen drag-and-drop yang sudah diuji.
**Spesifikasi:**45 dan49–54. Enam file54 dilaporkan lulus test/manual dan tersalin; scan kedua folder menunjukkan isi yang identik.

## 1. Perilaku checkpoint ini

- Tahap 2 versi 2: dua kartu readonly untuk hasil sidang tingkat 2 dan persetujuan sidang iGracias, serta dua upload Data TA/Bimbingan. SKPI dan TAK cukup di panel.
- Tahap 3: BPP/EPRT readonly, tiga PDF dasar, ditambah Surat Permakluman untuk mode waiver. Mode automatic tidak meminta sertifikat; retest/unknown tidak membuat jenis upload baru.
- Tanda `*` hanya pada upload versi 2. Kontak, tanggal, dan pilihan biodata tetap divalidasi sebelum tahap dapat dilanjutkan. Form tetap `novalidate` dan memakai error inline server.
- Tinjau menampilkan 7/8/6 file sesuai mode dan cabang biodata, lima syarat akademik, serta kondisi EPRT. Syarat akademik yang gagal tidak menghalangi Tinjau.
- **Kirim versi 2 tetap ditahan pada checkpoint ini.** Tombol utama dan konfirmasi disabled, termasuk binding Alpine. Guard server 54 tetap berlaku.
- Draft baru dari UI masih versi 1. Jangan mengubah default model, migrasi, atau `DraftService::start` menjadi versi 2 sendiri. Bagian 8 menyediakan prosedur uji terbatas untuk draft dummy baru dan kosong.

Persetujuan sidang ditampilkan **Belum tersedia**, meskipun foto BASILA approved, karena pemetaan persetujuan sidang belum ada. Tabel BPP hanya menyimpan NIM dan status, sehingga periode pembayarannya ditampilkan belum terkonfirmasi. Keduanya tidak menjadi syarat akademik tambahan.

## 2. File dan pembuatan

Empat file existing:

```text
app/Http/Controllers/SidangTaDraftController.php
resources/views/sidang_ta_registrations/wizard.blade.php
resources/views/sidang_ta_registrations/wizard-review.blade.php
resources/views/sidang_ta_registrations/partials/wizard-upload.blade.php
```

Tiga file baru: dua partial dan satu Feature test. Total **7 file** dipindahkan sesudah berhasil.

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
php artisan make:view sidang_ta_registrations.partials.meet-automatic-cards
php artisan make:view sidang_ta_registrations.partials.meet-admission-summary
php artisan make:test SidangTaMeetUiTest --pest
```

Tidak ada migrasi atau perubahan JavaScript. JSON 03 dari panduan 53 harus tersedia di `docs/fixtures/sidang-ta/` pada folder asli agar test dapat membacanya. Jika belum ada, gunakan command penyalinan pada 53 bagian 2.

## 3. Isi test dahulu

Ganti seluruh `tests/Feature/SidangTaMeetUiTest.php` dengan:

```php
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
        expect($crawler->filter('[data-review-document]')->count())->toBe(7);
        $this->deleteJson(route('sidang-ta-drafts.destroy', $draft), ['version' => $draft->version])->assertNoContent();
    }
});

test('meet_ui_waiver_review_has_eight_previews_and_held_send_buttons', function () {
    $draft = ($this->meetDraft)('waiver');
    $crawler = new Crawler(($this->reviewPage)($draft)->getContent());
    expect($crawler->filter('[data-review-document]')->count())->toBe(8);
    expect($crawler->filter('[data-review-document="eprt_waiver_letter"]')->text(null, true))->toContain('Surat Permakluman EPRT');
    expect($crawler->filter('[data-review-document] a[data-document-preview]')->count())->toBe(8);
    expect($crawler->filter('[data-review-summary]')->text(null, true))->toContain('8 dokumen');
    expect($crawler->filter('[data-meet-send-held]')->count())->toBe(1);
    foreach (['[data-open-registration]', '[data-confirm-registration]'] as $selector) {
        $button = $crawler->filter($selector);
        expect($button->getNode(0)->hasAttribute('disabled'))->toBeTrue();
        expect($button->attr(':disabled'))->toContain('true');
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

```

Jalankan sebelum mengubah kode produksi. Test perilaku tampilan baru seharusnya gagal; pesan kegagalannya bisa berbeda:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaMeetUiTest.php
```

Fixture menggunakan importer profil akademik dengan NIM test terpisah dan membuat metadata versi 2 di server. Mahasiswa tetap tidak bisa memilih versi lewat request. Test tidak memerlukan impor manual pada database aplikasi.

## 4. Kode lengkap

### app/Http/Controllers/SidangTaDraftController.php

Ganti seluruh isi file (untuk partial baru, isi seluruh file):

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveSidangTaDraftStepRequest;
use App\Models\SidangTaRegistrationDraft;
use App\Models\SidangTaRegistration;
use App\Services\SidangTaAcademicSnapshot;
use App\Services\SidangTaRevisionDraftService;
use App\Services\SidangTaDraftService;
use App\Models\SidangTaPeriod;
use App\Models\Student;
use App\Services\SidangTaStudentContext;
use App\Services\SidangTaDraftReviewService;
use App\Models\SidangTaRegistrationDraftDocument;
use App\Support\SidangTaDocumentRequirements;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class SidangTaDraftController extends Controller
{
    public function __construct(private SidangTaDraftService $service,
                                private SidangTaRevisionDraftService $revisions,
                                private SidangTaDraftReviewService $reviews
    ) {}

    private function respond(SidangTaRegistrationDraft $draft, int $status = 200): JsonResponse
    {
        $draft->loadMissing('documents');
        return response()->json(['data' => [
            'id' => $draft->id,
            'sidang_ta_period_id' => $draft->sidang_ta_period_id,
            'payload' => $draft->payload,
            'version' => $draft->version,
            'status' => $draft->status,
            'first_incomplete_step' => SidangTaDocumentRequirements::firstIncompleteStep($draft),
            'documents' => $draft->documents->sortBy('document_type')->values()->map(fn ($document) => [
                'id' => $document->id, 'document_type' => $document->document_type,
                'file_name' => $document->file_name, 'file_size' => $document->file_size,
                'download_url' => route('sidang-ta-draft-documents.download', $document),
            ])->all(),
            'source_registration_id' => $draft->source_registration_id,
            'source_version' => $draft->source_version,
        ]], $status);
    }

    public function store(SaveSidangTaDraftStepRequest $request): JsonResponse|RedirectResponse
    {
        $draft = $this->service->start($request->user(), $request->validated());
        return $request->expectsJson()
            ? $this->respond($draft, 201)
            : redirect()->route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 1])
                ->with('success', 'Draft tersimpan. Anda dapat melanjutkan setelah tahap ini lengkap.');
    }

    private function meetContext(SidangTaRegistrationDraft $draft, Student $student): ?array
    {
        if ($draft->requirements_version !== 2) return null;
        $admission = app(\App\Services\SidangTaAdmissionFacts::class)->forStudent($student);
        $mode = $admission['eprt']['mode'];
        $facts = $admission['facts'];
        $level = match ($facts['level_2_passed']) { true => 'Lulus', false => 'Belum lulus', default => 'Belum tersedia' };
        $skpi = match ($facts['skpi_approved']) { true => 'Disetujui', false => 'Belum memenuhi', default => 'Belum tersedia' };
        $krs = match ($admission['krs_status']) { 'eligible' => 'Terdaftar', 'unconfirmed' => 'Belum terkonfirmasi', default => 'Belum tersedia' };
        $payment = strtolower(trim((string) ($student->bppPayment()->value('status') ?? '')));
        $payment = match ($payment) { 'paid', 'lunas' => 'Lunas', 'unpaid', 'belum lunas', 'belum_lunas' => 'Belum lunas', default => 'Belum tersedia' };
        $score = $admission['eprt_facts']['score'];
        $attempts = $admission['eprt_facts']['confirmed_attempts'];
        $payload = $draft->payload ?? [];
        $choice = $payload['diploma_biodata_accurate'] ?? null;
        $accurate = $choice === null ? null : (bool) $choice;
        $types = [];
        foreach ([2, 3, 4] as $step) {
            $types[$step] = \App\Support\SidangTaMeetRequirements::typesForStep($step, $accurate, $mode);
        }
        return [
            'admission' => $admission, 'types' => $types,
            'academic' => ['sidang_tingkat_2' => $level, 'skpi_status' => $skpi, 'ta2_krs_label' => $krs,
                'tak_score' => $facts['tak_score'], 'passed_credits' => $facts['passed_credits'], 'eprt_score' => $score],
            'automatic' => [
                2 => [
                    ['key' => 'level_2', 'title' => 'Hasil sidang tingkat 2', 'value' => $level, 'hint' => 'Tidak memerlukan unggahan.'],
                    ['key' => 'sidang_approval', 'title' => 'Persetujuan sidang iGracias', 'value' => 'Belum tersedia', 'hint' => 'Persetujuan sidang belum tercatat.'],
                ],
                3 => [
                    ['key' => 'bpp', 'title' => 'BPP semester berjalan', 'value' => $payment,
                        'hint' => 'Periode pembayaran belum terkonfirmasi. Tidak memerlukan unggahan.'],
                    ['key' => 'eprt', 'title' => 'EPRT', 'value' => $score === null ? 'Belum tersedia' : 'Nilai '.$score,
                        'hint' => $admission['eprt']['message'].($attempts === null ? '' : ' '.$attempts.' tes tercatat.')],
                ],
            ],
        ];
    }

    public function edit(Request $request, SidangTaRegistrationDraft $draft): JsonResponse|View|RedirectResponse
    {
        $this->service->owned($request->user(), $draft);
        if ($request->expectsJson()) return $this->respond($draft);
        $query = $request->validate(['step' => ['nullable', 'integer', 'between:1,4']]);
        $firstIncomplete = SidangTaDocumentRequirements::firstIncompleteStep($draft);
        $maxStep = $firstIncomplete ?? 4;
        $completedThrough = ($firstIncomplete ?? 5) - 1;
        $step = (int) ($query['step'] ?? $maxStep);
        if ($step > $maxStep) {
            return redirect()->route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => $maxStep]);
        }
        $draft->load(['period', 'documents', 'sourceRegistration']);
        $stepProgress = SidangTaDocumentRequirements::progress($draft, $step);
        $student = app(SidangTaStudentContext::class)->studentFor($request->user());
        $openPeriods = SidangTaPeriod::query()->openForRegistration()->get();
        $academic = app(SidangTaAcademicSnapshot::class)->forStudent($student);
        $meetContext = $this->meetContext($draft, $student);
        if ($meetContext) $academic = array_replace($academic, $meetContext['academic']);
        return view('sidang_ta_registrations.wizard', compact(
            'draft', 'student', 'step', 'maxStep', 'completedThrough', 'openPeriods', 'academic', 'stepProgress', 'meetContext'
        ));
    }

    public function saveStep(SaveSidangTaDraftStepRequest $request, SidangTaRegistrationDraft $draft, int $step): JsonResponse|RedirectResponse
    {
        $draft = $this->service->saveStep($request->user(), $draft, $step,
            (int) $request->validated('version'), $request->validated());
        if ($request->expectsJson()) return $this->respond($draft);
        $complete = SidangTaDocumentRequirements::complete($draft, $step);
        return redirect()->route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => $step])
            ->with('success', $complete
                ? 'Draft tersimpan. Tahap ini sudah lengkap.'
                : 'Draft tersimpan. Lengkapi persyaratan tahap ini sebelum melanjutkan.'
            );
    }

    public function previewDocument(Request $request, SidangTaRegistrationDraftDocument $document)
    {
        $this->service->owned($request->user(), $document->draft);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($document->file_path), 404);
        $mime = $disk->mimeType($document->file_path);
        abort_unless(in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true), 415);
        return $disk->response($document->file_path, $document->file_name, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ], 'inline');
    }

    public function downloadDocument(Request $request, SidangTaRegistrationDraftDocument $document)
    {
        $this->service->owned($request->user(), $document->draft);
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->download($document->file_path, $document->file_name);
    }

    public function startRevision(Request $request, SidangTaRegistration $registration): JsonResponse|RedirectResponse
    {
        $request->validate([
            'owner_user_id' => 'prohibited', 'student_number' => 'prohibited',
            'sidang_ta_period_id' => 'prohibited', 'payload' => 'prohibited',
            'source_registration_id' => 'prohibited', 'source_version' => 'prohibited',
            'documents' => 'prohibited',
        ]);
        $draft = $this->revisions->start($request->user(), $registration);
        return $request->expectsJson() ? $this->respond($draft, 201)
            : redirect()->route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 1]);
    }

    public function destroy(Request $request, SidangTaRegistrationDraft $draft)
    {
        $data = $request->validate(['version' => ['required', 'integer', 'min:1']]);
        $this->revisions->discard($request->user(), $draft, (int) $data['version']);
        return $request->expectsJson() ? response()->noContent()
            : redirect()->route('sidang-ta-registrations.index')->with('success', 'Draft dihapus.');
    }

    public function review(Request $request, SidangTaRegistrationDraft $draft): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['version' => ['required', 'integer', 'min:1']]);
        $draft = $this->reviews->mark($request->user(), $draft, (int) $data['version']);
        return $request->expectsJson() ? $this->respond($draft)
            : redirect()->route('sidang-ta-drafts.preview', $draft);
    }

    public function preview(Request $request, SidangTaRegistrationDraft $draft): JsonResponse|View
    {
        $draft = $this->reviews->preview($request->user(), $draft);
        if ($request->expectsJson()) {
            return $this->respond($draft);
        }
        $draft->loadMissing(['period', 'documents']);
        $student = app(SidangTaStudentContext::class)->studentFor($request->user());
        $meetContext = $this->meetContext($draft, $student);
        $documentGroups = $meetContext['types'] ?? [];
        if (!$meetContext) {
            foreach ([2, 3, 4] as $step) {
                $documentGroups[$step] = SidangTaDocumentRequirements::typesForDraftStep($draft, $step);
            }
        }
        // Use one resolved set in the view. A mode change after service validation must not cause null dereferences.
        foreach ($documentGroups as $types) {
            foreach ($types as $type) {
                $document = $draft->documents->firstWhere('document_type', $type);
                abort_unless($document && Storage::disk('local')->exists($document->file_path), 409,
                    'Persyaratan atau berkas berubah. Buka form dan tinjau ulang.');
            }
        }
        return view('sidang_ta_registrations.wizard-review', compact('draft', 'student', 'meetContext', 'documentGroups'));
    }
}

```

### resources/views/sidang_ta_registrations/partials/meet-automatic-cards.blade.php

Ganti seluruh isi file (untuk partial baru, isi seluruh file):

```blade
@if($items !== [])
    <div class="grid gap-4 sm:grid-cols-2">
        @foreach($items as $item)
            <article data-automatic-card="{{ $item['key'] }}" data-read-only
                     class="min-w-0 rounded-xl border border-slate-200 bg-slate-100 p-4 text-slate-700">
                <div class="flex items-start justify-between gap-3">
                    <h3 class="text-sm font-semibold">{{ $item['title'] }}</h3>
                    <span class="shrink-0 rounded-full bg-white px-2 py-1 text-[10px] font-medium text-slate-600">Tanpa unggahan</span>
                </div>
                <p class="mt-3 break-words text-sm font-bold">{{ $item['value'] }}</p>
                <p class="mt-1.5 text-xs leading-relaxed text-slate-600">{{ $item['hint'] }}</p>
            </article>
        @endforeach
    </div>
@endif

```

### resources/views/sidang_ta_registrations/partials/meet-admission-summary.blade.php

Ganti seluruh isi file (untuk partial baru, isi seluruh file):

```blade
<section data-meet-admission class="campus-card space-y-4 p-5 sm:p-6">
    <header>
        <h2 class="text-lg font-bold text-slate-900">Pemeriksaan persyaratan</h2>
        <p class="mt-1 text-sm leading-relaxed text-slate-600">
            Draft dapat disimpan dan ditinjau. Pengiriman membutuhkan seluruh syarat akademik serta persyaratan EPRT terpenuhi.
        </p>
    </header>
    <dl class="grid gap-3 sm:grid-cols-2">
        @foreach($admission['academic']['gates'] as $key => $gate)
            @php
                $tone = match ($gate['state']) {
                    'passed' => 'border-emerald-200 bg-emerald-50 text-emerald-900',
                    'failed' => 'border-amber-200 bg-amber-50 text-amber-900',
                    default => 'border-slate-200 bg-slate-100 text-slate-700',
                };
                $label = match ($gate['state']) { 'passed' => 'Memenuhi', 'failed' => 'Belum memenuhi', default => 'Belum tersedia' };
            @endphp
            <div data-admission-gate="{{ $key }}" data-gate-state="{{ $gate['state'] }}" class="rounded-xl border p-3 {{ $tone }}">
                <dt class="text-sm font-semibold">{{ $gate['label'] }}</dt>
                <dd class="mt-1 text-xs">{{ $label }}</dd>
            </div>
        @endforeach
    </dl>
    @if($admission['academic']['blockers'] !== [])
        <ul data-academic-blockers class="list-inside list-disc space-y-1 text-xs leading-relaxed text-amber-900">
            @foreach($admission['academic']['blockers'] as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    @endif
    <div data-eprt-mode="{{ $admission['eprt']['mode'] }}" class="rounded-xl border border-slate-200 bg-slate-50 p-3">
        <p class="text-sm font-semibold text-slate-800">EPRT</p>
        <p class="mt-1 text-xs leading-relaxed text-slate-600">{{ $admission['eprt']['message'] }}</p>
    </div>
</section>

```

### resources/views/sidang_ta_registrations/partials/wizard-upload.blade.php

Ganti seluruh isi file (untuk partial baru, isi seluruh file):

```blade
@php
    $existing = $draft?->documents?->firstWhere('document_type', $type);
    $available = $existing && \Illuminate\Support\Facades\Storage::disk('local')->exists($existing->file_path);
    $isMeetUpload = $draft?->requirements_version === 2;
    $label = ($isMeetUpload ? \App\Support\SidangTaMeetRequirements::LABELS[$type] ?? null : null)
        ?? \App\Models\SidangTaRegistration::DOCUMENT_TYPES[$type] ?? $type;
    $capture = in_array($type, $isMeetUpload ? \App\Support\SidangTaMeetRequirements::CAPTURES
        : \App\Support\SidangTaDocumentRequirements::CAPTURES, true);
    $guide = $isMeetUpload ? match ($type) {
        'ta_data_capture' => 'assets/images/forms/sidang-ta/ta-data.png',
        'online_supervision_capture' => 'assets/images/forms/sidang-ta/bimbingan-online.png',
        default => null,
    } : null;
    $guideAvailable = $guide && is_file(public_path($guide));
    $conditional = $conditional ?? null;
    $errorKey = "documents.{$type}";
    $uploadInitial = ['serverError' => $errors->has($errorKey)];
@endphp
<div data-upload-type="{{ $type }}" class="min-w-0 rounded-xl border border-slate-200 bg-white p-4"
     x-data="sidangTaUpload(@js($uploadInitial))">
    <label class="block text-sm font-semibold leading-relaxed text-slate-800" for="file-{{ $type }}">
        {{ $label }} <span class="text-rose-600" aria-hidden="true">*</span>
    </label>
    <p id="help-{{ $type }}" class="mt-1.5 text-xs leading-relaxed text-slate-500">
        @unless($guideAvailable)
            {{ \App\Models\SidangTaRegistration::DOCUMENT_DESCRIPTIONS[$type] ?? '' }}
        @endunless
        {{ $capture ? 'PDF/JPG/JPEG/PNG' : 'PDF' }}, maksimal 10 MB per file.
    </p>
    @if($guideAvailable)
        <a data-upload-guide href="{{ asset($guide) }}" target="_blank" rel="noopener" class="mt-3 block">
            <img loading="lazy" src="{{ asset($guide) }}" alt="Panduan {{ $label }}"
                 class="w-full rounded-lg border border-slate-200 object-contain">
        </a>
    @endif
    @if($isMeetUpload && $type === \App\Support\SidangTaMeetRequirements::WAIVER)
        <p class="mt-2 text-xs leading-relaxed text-slate-600">
            Unduh template, lengkapi, lalu unggah kembali dalam format PDF.
            <a data-waiver-template href="https://telkomuniversityofficial-my.sharepoint.com/:w:/g/personal/akademiksby_telkomuniversity_ac_id/EYC5y40Ky_1PhVWh4_LswJoBtC8yKT6PuTEAwUTDiFY-2A?e=qRenjo"
               target="_blank" rel="noopener" class="font-semibold text-[#DA2D3B] underline">Link Surat Permakluman</a>
        </p>
    @endif
    @if($available)
        <div class="mt-3 space-y-2 rounded-lg border border-emerald-200 bg-emerald-50 p-3">
            <p class="text-xs font-semibold text-emerald-800">Berkas tersimpan</p>
            <p class="break-words text-sm text-slate-800">{{ $existing->file_name }}</p>
            <p class="text-xs text-slate-500">
                {{ number_format(($existing->file_size ?? 0) / 1024) }} KB · Pilih berkas baru hanya untuk mengganti.
            </p>
        </div>
    @elseif($existing)
        <p class="mt-3 text-sm text-rose-700">
            Berkas tersimpan tidak ditemukan. Unggah kembali.
        </p>
    @endif
    <div
        class="relative mt-3 rounded-xl border-2 border-dashed p-4 transition-colors focus-within:ring-2 focus-within:ring-rose-500 focus-within:ring-offset-2"
        :class="hasError() ? 'border-rose-500 bg-rose-50' : (dragDepth > 0 ? 'border-rose-500 bg-rose-50' : 'border-slate-300 bg-slate-50 hover:border-rose-400')"
        @dragenter.prevent.stop="enter()" @dragover.prevent.stop
        @dragleave.prevent.stop="leaveDrop()" @drop.prevent.stop="drop($event)">
        <input id="file-{{ $type }}" x-ref="file" type="file" name="documents[{{ $type }}]"
               accept="{{ $capture ? '.pdf,.jpg,.jpeg,.png' : '.pdf' }}"
               class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
               aria-describedby="help-{{ $type }} error-documents-{{ $type }}"
               aria-invalid="{{ $errors->has($errorKey) ? 'true' : 'false' }}"
               :aria-invalid="hasError() ? 'true' : 'false'"
               @change="picked()"
               @if($conditional !== null)
                   @disabled($accurate !== $conditional)
                   :disabled="accurate !== '{{ $conditional }}'"
               :required="accurate === '{{ $conditional }}' && {{ $available ? 'false' : 'true' }}"
        @else
            @required(!$available)
            @endif>
        <div class="pointer-events-none py-3 text-center">
            <svg aria-hidden="true" class="mx-auto h-7 w-7 text-slate-400" fill="none" stroke="currentColor"
                 viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                      d="M12 16V4m0 0L7 9m5-5 5 5M4 16v3a1 1 0 001 1h14a1 1 0 001-1v-3"/>
            </svg>
            <p class="mt-2 text-sm font-medium text-slate-700">
                Klik untuk memilih atau seret file ke sini
            </p>
            <p class="mt-1 text-xs text-slate-500">
                {{ $capture ? 'PDF/JPG/PNG' : 'PDF' }} · Maksimal 10 MB · Satu berkas
            </p>
        </div>
    </div>
    <div x-show="fileName" x-cloak class="mt-3 space-y-2 rounded-lg border border-amber-200 bg-amber-50 p-3">
        <p class="text-xs font-semibold text-amber-800">
            Pilihan baru · Belum disimpan
        </p>
        <p class="break-words text-sm text-slate-800" x-text="fileName"></p>
        <p class="text-xs text-slate-500" x-text="fileSize"></p>
        <button type="button" class="btn btn-secondary text-xs" @click="clear()">
            Batalkan pilihan baru
        </button>
    </div>
    <p id="error-documents-{{ $type }}" role="status" aria-live="polite"
       class="mt-1.5 text-xs leading-relaxed text-rose-700" x-show="hasError()">
        <span x-show="localError" x-text="localError"></span>
        <span x-show="!localError && !selectionChanged">{{ $errors->first($errorKey) }}</span>
    </p>
</div>

```

### resources/views/sidang_ta_registrations/wizard.blade.php

Ganti seluruh isi file (untuk partial baru, isi seluruh file):

```blade
<x-app-layout>
    <x-slot name="header">
        {{ $draft?->source_registration_id ? 'Draft Revisi Sidang TA' : 'Pendaftaran Sidang TA' }}
    </x-slot>
    @php
        $payload = $draft?->payload ?? [];
        $isMeet = $draft?->requirements_version === 2;
        $meetContext = $meetContext ?? null;
        $rawAccurate = old('diploma_biodata_accurate', $payload['diploma_biodata_accurate'] ?? null);
        $accurate = match ($rawAccurate) {
            true, 1, '1' => '1', false, 0, '0' => '0', default => '',
        };
        $stepProgress = $stepProgress ?? ['complete' => false, 'stored_documents' => 0,
            'required_documents' => 0, 'missing' => []];
        $selectedPeriod = (string) old('sidang_ta_period_id', $draft?->sidang_ta_period_id ?? '');
        $periods = $openPeriods;
        if ($draft && !$periods->contains('id', $draft->sidang_ta_period_id)) {
            $periods = $periods->prepend($draft->period);
        }
        $selected = $periods->first(fn ($period) => (string) $period->id === $selectedPeriod);
        $windowFor = static fn ($period) => $period->open_at->format('d/m/Y H:i').' – '
            .$period->close_at->format('d/m/Y H:i').($period->isOpen() ? '' : ' (ditutup)');
        $initial = ['accurate' => $accurate, 'dirty' => $errors->any(), 'hasErrors' => $errors->any(),
            'periodHint' => $selected ? $windowFor($selected) : ''];
        $reviewReady = $draft && $completedThrough === 4;
        $permission = $draft?->source_registration_id ? 'sidang_ta_registration_update' : 'sidang_ta_registration_create';
        $titles = [1 => 'Data dasar & kontak', 2 => 'Verifikasi iGracias & akademik',
            3 => 'Pembayaran & dokumen', 4 => 'Biodata ijazah'];
    @endphp
    <div class="mx-auto max-w-7xl space-y-5" data-current-step="{{ $step }}"
         x-data="sidangTaWizard(@js($initial))" @sidang-ta-file-changed="dirty = true"
         @pageshow.window="busy = false"
         @beforeunload.window="if (dirty && !busy) { $event.preventDefault(); $event.returnValue = ''; }">
        @if($errors->any())
            <div data-error-summary tabindex="-1" role="alert"
                 class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-rose-800">
                <p class="text-sm font-bold">
                    Periksa isian berikut:
                </p>
                <ul class="mt-2 list-inside list-disc space-y-1 text-sm">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <p class="mt-2 text-xs leading-relaxed">
                    Data yang sudah tersimpan tetap tersedia. File pilihan baru yang ditolak perlu dipilih ulang.
                </p>
            </div>
        @endif
        @if(session('success'))
            <p role="status" class="rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('success') }}
            </p>
        @endif

        <section class="campus-card overflow-hidden" aria-label="Progres pendaftaran">
            <nav aria-label="Tahap pendaftaran" data-progress-scroll class="overflow-x-auto p-4 sm:p-5">
                <ol class="flex min-w-max items-stretch gap-2">
                    @foreach($titles as $number => $title)
                        @php
                            $reachable = $number <= $maxStep;
                            $state = $number === $step ? 'current' : ($number < $step && $number <= $completedThrough
                                ? 'complete' : ($reachable ? 'available' : 'locked'));
                            $tone = match ($state) {
                                'current' => 'border-[#DA2D3B] bg-[#DA2D3B] text-white',
                                'complete' => 'border-rose-200 bg-rose-50 text-rose-800',
                                'available' => 'border-slate-200 bg-white text-slate-700',
                                default => 'border-slate-200 bg-slate-100 text-slate-400',
                            };
                        @endphp
                        <li class="flex items-center gap-2">
                            @if($draft && $reachable)
                                <a data-step="{{ $number }}" data-state="{{ $state }}" @click="leave($event)"
                                   href="{{ route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => $number]) }}"
                                   @if($number === $step) aria-current="step" @endif
                                   class="block w-40 rounded-xl border px-3 py-3 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2 {{ $tone }}">
                                    <span class="text-sm font-bold">
                                        Tahap {{ $number }}{{ $state === 'complete' ? ' ✓' : '' }}
                                    </span>
                                    <span class="mt-1 block whitespace-normal text-xs leading-relaxed">
                                        {{ $title }}
                                    </span>
                                </a>
                            @else
                                <span data-step="{{ $number }}" data-state="{{ $state }}"
                                      @if($number === $step) aria-current="step" @else aria-disabled="true" @endif
                                      class="block w-40 rounded-xl border px-3 py-3 {{ $tone }}">
                                    <span class="text-sm font-bold">
                                        Tahap {{ $number }}
                                    </span>
                                    <span class="mt-1 block whitespace-normal text-xs leading-relaxed">
                                        {{ $title }}
                                    </span>
                                </span>
                            @endif
                            @if($number < 4)
                                <span data-chevron="{{ $number }}" aria-hidden="true"
                                      class="px-1 text-xl font-bold {{ $number <= $step ? 'text-[#DA2D3B]' : 'text-slate-300' }}">&raquo;</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>
            <div data-save-status
                 class="space-y-1 border-t border-slate-100 bg-slate-50 px-4 py-3 text-xs leading-relaxed sm:px-5">
                <p class="font-medium text-slate-700">
                    {{ $draft ? 'Terakhir disimpan '.$draft->updated_at->format('d/m/Y H:i').'.' : 'Belum ada data yang disimpan.' }}
                </p>
                <p class="text-slate-500">
                    Tekan Simpan agar data dan file dapat dilanjutkan setelah halaman ditutup.
                </p>
                <p role="status" aria-live="polite" class="font-medium text-amber-800"
                   x-text="busy ? 'Sedang menyimpan…' : (dirty ? 'Ada perubahan yang belum disimpan.' : '')"></p>
            </div>
        </section>

        <div class="grid items-start gap-6 xl:grid-cols-12">
            <div class="min-w-0 space-y-5 xl:col-span-8">
                @if($draft?->sourceRegistration?->review_notes)
                    <section class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                        <h2 class="text-sm font-bold text-amber-900">
                            Catatan petugas
                        </h2>
                        <p class="mt-1 whitespace-pre-line text-sm leading-relaxed text-amber-800">
                            {{ $draft->sourceRegistration->review_notes }}
                        </p>
                    </section>
                @endif
                @if($draft?->sourceRegistration)
                    @include('sidang_ta_registrations.partials.revision-feedback', ['registration' => $draft->sourceRegistration])
                @endif

                <form id="sidang-ta-step-form" method="POST" novalidate enctype="multipart/form-data"
                      class="campus-card space-y-5 p-5 sm:p-6"
                      action="{{ $draft ? route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => $step]) : route('sidang-ta-drafts.store') }}"
                      @input="dirty = true" @change="dirty = true"
                      @submit="if (busy) { $event.preventDefault(); } else { busy = true; }">
                    @csrf
                    @if($draft)
                        <input type="hidden" name="version" value="{{ old('version', $draft->version) }}">
                    @endif
                    <header class="space-y-1.5 border-b border-slate-100 pb-4">
                        <h2 class="text-lg font-bold text-slate-900">Tahap {{ $step }} — {{ $titles[$step] }}</h2>
                        <p class="text-xs text-slate-500">
                            @if($isMeet)
                                Tanda * menunjukkan unggahan wajib. Draft boleh disimpan sebelum lengkap.
                            @else
                                Isian bertanda <span class="font-bold text-rose-600">*</span> wajib lengkap sebelum
                                lanjut/kirim. Draft boleh disimpan sebelum lengkap.
                            @endif
                        </p>
                    </header>
                    @if($draft && !$stepProgress['complete'])
                        <section data-step-progress role="status"
                                 class="space-y-2 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                            <p class="font-semibold">
                                Lengkapi tahap ini sebelum melanjutkan.
                            </p>
                            @if($stepProgress['required_documents'] !== null && $stepProgress['required_documents'] > 0)
                                <p>
                                    {{ $stepProgress['stored_documents'] }}
                                    dari {{ $stepProgress['required_documents'] }} dokumen tersimpan.
                                </p>
                            @endif
                            <p class="text-xs">
                                Masih perlu dilengkapi:
                            </p>
                            <ul class="list-inside list-disc space-y-1 text-xs leading-relaxed">
                                @foreach($stepProgress['missing'] as $missing)
                                    <li>{{ $missing }}</li>
                                @endforeach
                            </ul>
                        </section>
                    @endif
                    @if($step === 1)
                        <div class="max-w-lg">
                            <label for="sidang-ta-period-id" class="mb-1.5 block text-sm font-semibold text-slate-700">
                                Periode Sidang TA @unless($isMeet)<span class="text-rose-600" aria-hidden="true">*</span>@endunless
                            </label>
                            @if($draft?->source_registration_id)
                                <input type="hidden" name="sidang_ta_period_id"
                                       value="{{ $draft->sidang_ta_period_id }}">
                                <p id="sidang-ta-period-id" tabindex="-1"
                                   class="rounded-lg bg-slate-50 p-3 text-sm font-medium text-slate-800"
                                   aria-invalid="{{ $errors->has('sidang_ta_period_id') ? 'true' : 'false' }}"
                                   aria-describedby="period-window error-sidang_ta_period_id">
                                    {{ $draft->period->name }}(periode pengajuan asal)
                                </p>
                            @else
                                <select id="sidang-ta-period-id" name="sidang_ta_period_id" required
                                        class="campus-input w-full {{ $errors->has('sidang_ta_period_id') ? '!border-rose-500' : '' }}"
                                        aria-invalid="{{ $errors->has('sidang_ta_period_id') ? 'true' : 'false' }}"
                                        aria-describedby="period-window error-sidang_ta_period_id"
                                        @change="updatePeriodHint($event.target)">
                                    <option value="">Pilih periode terbuka</option>
                                    @foreach($periods as $period)
                                        <option value="{{ $period->id }}"
                                                data-window="{{ $windowFor($period) }}" @selected($selectedPeriod === (string) $period->id)>
                                            {{ $period->name }}{{ $period->isOpen() ? '' : ' (ditutup)' }}
                                        </option>
                                    @endforeach
                                </select>
                            @endif
                            <p id="period-window" class="mt-1.5 text-xs leading-relaxed text-slate-500"
                               x-text="periodHint || 'Pilih periode yang masih terbuka.'">{{ $initial['periodHint'] ?: 'Pilih periode yang masih terbuka.' }}</p>
                            @include('sidang_ta_registrations.partials.wizard-error', ['field' => 'sidang_ta_period_id'])
                        </div>
                        <div class="grid max-w-xl gap-4 sm:grid-cols-2">
                            <div class="min-w-0">
                                <label for="student-phone" class="mb-1.5 block text-sm font-semibold text-slate-700">
                                    Nomor HP mahasiswa @unless($isMeet)<span class="text-rose-600" aria-hidden="true">*</span>@endunless
                                </label>
                                <input id="student-phone"
                                       class="campus-input w-full {{ $errors->has('student_phone') ? '!border-rose-500' : '' }}"
                                       name="student_phone" inputmode="tel" maxlength="30" required
                                       placeholder="08xxxxxxxxxx"
                                       aria-invalid="{{ $errors->has('student_phone') ? 'true' : 'false' }}"
                                       aria-describedby="error-student_phone"
                                       value="{{ old('student_phone', $payload['student_phone'] ?? $student->phone_number) }}">
                                @include('sidang_ta_registrations.partials.wizard-error', ['field' => 'student_phone'])
                            </div>
                            <div class="min-w-0">
                                <label for="parent-phone" class="mb-1.5 block text-sm font-semibold text-slate-700">
                                    Nomor HP orang tua/wali @unless($isMeet)<span class="text-rose-600" aria-hidden="true">*</span>@endunless
                                </label>
                                <input id="parent-phone"
                                       class="campus-input w-full {{ $errors->has('parent_phone') ? '!border-rose-500' : '' }}"
                                       name="parent_phone" inputmode="tel" maxlength="30" required
                                       placeholder="08xxxxxxxxxx"
                                       aria-invalid="{{ $errors->has('parent_phone') ? 'true' : 'false' }}"
                                       aria-describedby="error-parent_phone"
                                       value="{{ old('parent_phone', $payload['parent_phone'] ?? $student->parent_phone_number) }}">
                                @include('sidang_ta_registrations.partials.wizard-error', ['field' => 'parent_phone'])
                            </div>
                        </div>
                    @elseif($step === 2 || $step === 3)
                        @if($step === 3)
                            <div class="max-w-xs">
                                <label for="graduation-payment-date"
                                       class="mb-1.5 block text-sm font-semibold text-slate-700">
                                    Tanggal pembayaran wisuda @unless($isMeet)<span class="text-rose-600" aria-hidden="true">*</span>@endunless
                                </label>
                                <input id="graduation-payment-date"
                                       class="campus-input w-full {{ $errors->has('graduation_payment_date') ? '!border-rose-500' : '' }}"
                                       type="date" name="graduation_payment_date" required
                                       max="{{ today()->toDateString() }}"
                                       aria-invalid="{{ $errors->has('graduation_payment_date') ? 'true' : 'false' }}"
                                       aria-describedby="error-graduation_payment_date"
                                       value="{{ old('graduation_payment_date', $payload['graduation_payment_date'] ?? '') }}">
                                @include('sidang_ta_registrations.partials.wizard-error', ['field' => 'graduation_payment_date'])
                            </div>
                        @endif
                        @if($isMeet)
                            @include('sidang_ta_registrations.partials.meet-automatic-cards', ['items' => $meetContext['automatic'][$step] ?? []])
                        @endif
                        <div class="grid gap-4 sm:grid-cols-2">
                            @foreach(($isMeet ? $meetContext['types'][$step] : \App\Support\SidangTaDocumentRequirements::typesForStep($step)) as $type)
                                @include('sidang_ta_registrations.partials.wizard-upload', ['type' => $type, 'conditional' => null])
                            @endforeach
                        </div>
                    @else
                        <fieldset id="diploma-biodata-group" tabindex="-1" aria-required="true"
                                  aria-invalid="{{ $errors->has('diploma_biodata_accurate') ? 'true' : 'false' }}"
                                  aria-describedby="error-diploma_biodata_accurate">
                            <legend class="text-sm font-semibold text-slate-700">
                                Apakah biodata dummy ijazah sudah sesuai? @unless($isMeet)<span class="text-rose-600" aria-hidden="true">*</span>@endunless
                            </legend>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                @foreach(['1' => 'Sudah sesuai', '0' => 'Belum sesuai, sudah mengajukan PDM'] as $value => $label)
                                    <label
                                        class="flex cursor-pointer items-start gap-3 rounded-xl border p-3 text-sm leading-relaxed"
                                        :class="accurate === '{{ $value }}' ? 'border-rose-300 bg-rose-50 text-rose-900' : 'border-slate-200 text-slate-700'">
                                        <input type="radio" name="diploma_biodata_accurate" value="{{ $value }}"
                                               x-model="accurate" @checked($accurate === (string) $value) required
                                               class="mt-0.5 border-slate-300 text-rose-600 focus:ring-rose-500">
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @include('sidang_ta_registrations.partials.wizard-error', ['field' => 'diploma_biodata_accurate'])
                        </fieldset>
                        <p class="text-xs leading-relaxed text-slate-500">
                            Menyimpan pilihan biodata yang berbeda akan mengganti lampiran cabang draft sebelumnya.
                            Berkas pengajuan final tetap tersimpan.
                        </p>
                        <div x-show="accurate === '1'" x-cloak class="grid gap-4 sm:grid-cols-2">
                            @foreach(\App\Support\SidangTaDocumentRequirements::DIPLOMA as $type)
                                @include('sidang_ta_registrations.partials.wizard-upload', ['type' => $type, 'conditional' => '1'])
                            @endforeach
                        </div>
                        <div x-show="accurate === '0'" x-cloak>
                            @include('sidang_ta_registrations.partials.wizard-upload', ['type' => 'pdm_submission_proof', 'conditional' => '0'])
                        </div>
                    @endif
                    <div
                        class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                        <a @click="leave($event)" class="btn btn-secondary"
                           href="{{ route('sidang-ta-registrations.index') }}">
                            Kembali ke pendaftaran
                        </a>
                        @can($permission)
                            <div class="flex flex-wrap items-center gap-2">
                                <button data-save-draft type="submit" class="btn btn-secondary" :disabled="busy"
                                        x-text="busy ? 'Sedang menyimpan…' : 'Simpan draft'">
                                    Simpan draft
                                </button>
                                @if($step < 4)
                                    @if($draft && $stepProgress['complete'])
                                        <a data-next-step class="btn btn-primary" aria-disabled="false"
                                           href="{{ route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => $step + 1]) }}"
                                           :aria-disabled="dirty || busy ? 'true' : 'false'"
                                           :tabindex="dirty || busy ? -1 : 0"
                                           :class="dirty || busy ? 'pointer-events-none opacity-50' : ''"
                                           @click="if (dirty || busy) $event.preventDefault()">
                                            Lanjutkan
                                        </a>
                                    @else
                                        <button data-next-step type="button" disabled
                                                class="btn btn-primary cursor-not-allowed opacity-50">
                                            Lanjutkan
                                        </button>
                                    @endif
                                @endif
                            </div>
                        @endcan
                    </div>
                </form>
                @if($draft && $step === 4)
                    @can($permission)
                        <form data-review-draft method="POST" class="campus-card space-y-3 p-5"
                              action="{{ route('sidang-ta-drafts.review', $draft) }}"
                              x-on:submit="if (dirty || busy || {{ $reviewReady ? 'false' : 'true' }}) { $event.preventDefault(); } else { busy = true; }">
                            @csrf <input type="hidden" name="version" value="{{ $draft->version }}">
                            <p class="text-sm leading-relaxed text-slate-600">
                                Draft harus tersimpan dan seluruh tahap lengkap sebelum ditinjau. Pendaftaran baru
                                dikirim setelah konfirmasi pada halaman tinjau.
                            </p>
                            <p id="review-readiness" class="text-xs text-slate-500">Lengkapi seluruh dokumen dan isian,
                                lalu tekan Simpan draft untuk mengaktifkan tinjauan.</p>
                            <button data-review-submit type="submit" aria-describedby="review-readiness"
                                    aria-disabled="{{ $reviewReady ? 'false' : 'true' }}"
                                    :aria-disabled="dirty || busy || {{ $reviewReady ? 'false' : 'true' }} ? 'true' : 'false'"
                                    class="btn btn-primary disabled:cursor-not-allowed disabled:opacity-50 disabled:shadow-none"
                                    @disabled(!$reviewReady)
                                    :disabled="dirty || busy || {{ $reviewReady ? 'false' : 'true' }}">
                                Tinjau Sebelum Kirim
                            </button>
                        </form>
                    @endcan
                @endif
            </div>
            @include('sidang_ta_registrations.partials.wizard-academic')
        </div>
    </div>
</x-app-layout>

```

### resources/views/sidang_ta_registrations/wizard-review.blade.php

Ganti seluruh isi file (untuk partial baru, isi seluruh file):

```blade
<x-app-layout>
    <x-slot name="header">Tinjau Pendaftaran Sidang TA</x-slot>
    @php
        $payload = $draft->payload;
        $isMeet = $draft->requirements_version === 2;
        $meetContext = $meetContext ?? null;
        $accurate = (bool) $payload['diploma_biodata_accurate'];
        $isRevision = $draft->source_registration_id !== null;
        $permission = $isRevision ? 'sidang_ta_registration_update' : 'sidang_ta_registration_create';
        $groups = [2 => 'Verifikasi iGracias & akademik', 3 => 'Pembayaran & dokumen', 4 => 'Biodata ijazah'];
        $documentCount = array_sum(array_map('count', $documentGroups));
        $confirmLabel = $isRevision ? 'Ya, kirim revisi' : 'Ya, kirim pendaftaran';
    @endphp
    <div class="mx-auto max-w-6xl space-y-5">
        @if($errors->any())
            <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
                <p class="font-bold">Pendaftaran belum dapat dikirim.</p>
                <ul class="mt-2 list-inside list-disc space-y-1">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <section data-review-summary class="campus-card overflow-hidden">
            <header class="border-b border-rose-100 bg-rose-50/60 p-5 sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-[#DA2D3B]">Pemeriksaan akhir{{ $isRevision ? ' · Revisi' : '' }}</p>
                        <h2 class="mt-1 text-xl font-bold text-slate-900">Tinjau sebelum kirim</h2>
                        <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-600">Periksa kembali data dan seluruh berkas sebelum mengirim pendaftaran kepada petugas.</p>
                    </div>
                    <span class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800">Belum dikirim</span>
                </div>
            </header>
            <div class="space-y-5 p-5 sm:p-6">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="min-w-0 rounded-xl bg-slate-50 p-4">
                        <p class="text-xs font-semibold text-slate-500">Mahasiswa</p>
                        <p class="mt-1 break-words text-base font-bold text-slate-900">{{ $student->name }}</p>
                        <p class="mt-1 break-words text-sm text-slate-600">{{ $student->student_number }} · {{ $student->study_program ?: 'Program studi belum tersedia' }}</p>
                    </div>
                    <div class="min-w-0 rounded-xl bg-slate-50 p-4">
                        <p class="text-xs font-semibold text-slate-500">Periode Sidang TA</p>
                        <p class="mt-1 break-words text-base font-bold text-slate-900">{{ $draft->period->name }}</p>
                        <p class="mt-1 text-sm text-slate-600">{{ $documentCount }} dokumen dalam tinjauan{{ $isRevision ? ' · Periode pengajuan asal' : '' }}</p>
                    </div>
                </div>
                <dl class="grid gap-x-6 gap-y-5 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs text-slate-500">
                            Nomor HP mahasiswa
                        </dt>
                        <dd class="mt-1 break-words text-sm font-semibold text-slate-800">
                            {{ $payload['student_phone'] }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">
                            Nomor HP orang tua/wali
                        </dt>
                        <dd class="mt-1 break-words text-sm font-semibold text-slate-800">
                            {{ $payload['parent_phone'] }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">
                            Tanggal pembayaran wisuda
                        </dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-800">
                            {{ \Illuminate\Support\Carbon::parse($payload['graduation_payment_date'])->translatedFormat('d M Y') }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">
                            Biodata dummy ijazah
                        </dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-800">
                            {{ $accurate ? 'Sudah sesuai' : 'Belum sesuai, bukti PDM dilampirkan' }}
                        </dd>
                    </div>
                </dl>
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
                    <p class="text-xs leading-relaxed text-slate-500">
                        Data di bawah berasal dari draft yang tersimpan. Pendaftaran belum dikirim.
                    </p>
                    <a class="text-sm font-semibold text-[#DA2D3B] hover:underline" href="{{ route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 1]) }}">
                        Ubah data kontak
                    </a>
                </div>
            </div>
        </section>

        @if($isMeet)
            @include('sidang_ta_registrations.partials.meet-admission-summary', ['admission' => $meetContext['admission']])
        @endif

        @foreach($groups as $number => $title)
            @php
                $types = $documentGroups[$number];
            @endphp
            <section data-review-group="{{ $number }}" class="campus-card overflow-hidden">
                <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4 sm:px-6">
                    <div class="flex items-center gap-3">
                        <div>
                            <h3 class="text-base font-bold text-slate-800">
                                {{ $title }}
                            </h3>
                            <p class="mt-0.5 text-xs text-slate-500">
                                Tahap {{ $number }} · {{ count($types) }} berkas tersimpan
                            </p>
                        </div>
                    </div>
                    <a class="text-sm font-semibold text-[#DA2D3B] hover:underline" href="{{ route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => $number]) }}">Ubah tahap {{ $number }}</a>
                </header>
                @if($isMeet && isset($meetContext['automatic'][$number]))
                    <div class="px-5 pt-4 sm:px-6">
                        @include('sidang_ta_registrations.partials.meet-automatic-cards', ['items' => $meetContext['automatic'][$number]])
                    </div>
                @endif
                <div class="divide-y divide-slate-100 px-5 sm:px-6">
                    @foreach($types as $type)
                        @php
                            $document = $draft->documents->firstWhere('document_type', $type);
                            $documentLabel = ($isMeet ? \App\Support\SidangTaMeetRequirements::LABELS[$type] ?? null : null)
                                ?? \App\Models\SidangTaRegistration::DOCUMENT_TYPES[$type] ?? $type;
                        @endphp
                        <div data-review-document="{{ $type }}" class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between sm:gap-5">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold leading-relaxed text-slate-800">{{ $documentLabel }}</p>
                                <p class="mt-1 break-words text-xs leading-relaxed text-slate-500">{{ $document->file_name }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ number_format(($document->file_size ?? 0) / 1024) }} KB</p>
                            </div>
                            <a data-document-preview
                               class="btn btn-secondary shrink-0 self-start whitespace-nowrap !text-xs no-underline sm:self-auto"
                               href="{{ route('sidang-ta-draft-documents.preview', $document) }}"
                               data-preview-url="{{ route('sidang-ta-draft-documents.preview', $document) }}"
                               data-download-url="{{ route('sidang-ta-draft-documents.download', $document) }}"
                               data-file-name="{{ $document->file_name }}" data-file-type="{{ $document->file_type }}"
                               target="_blank" rel="noopener" aria-haspopup="dialog"
                               aria-label="Lihat {{ $documentLabel }}"
                               x-on:click.prevent="$dispatch('sidang-ta-preview', $el.dataset)">
                                Lihat berkas
                            </a>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach

        <section data-review-actions class="campus-card space-y-4 p-5 sm:p-6"
                 x-data="{ sending: false }" @pageshow.window="sending = false">
            <div>
                <h2 class="text-base font-bold text-slate-800">
                    Sudah memeriksa semua data?
                </h2>
                <p class="mt-1 text-sm leading-relaxed text-slate-600">
                    Pastikan periode, kontak, pembayaran, biodata, dan setiap berkas sudah benar. Anda masih dapat kembali ke form untuk memperbaikinya.
                </p>
            </div>
            @if($isMeet)
                <p data-meet-send-held role="status" class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900">
                    Pengiriman draft ini belum tersedia. Data dan berkas tetap tersimpan.
                </p>
            @endif
            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-wrap gap-2">
                    <a class="btn btn-secondary" href="{{ route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 1]) }}">
                        Kembali ke form
                    </a>
                    <a class="btn btn-secondary" href="{{ route('sidang-ta-registrations.index') }}">
                        Kembali ke pendaftaran
                    </a>
                </div>
                @can($permission)
                    <button data-open-registration type="button" class="btn btn-primary" @disabled($isMeet) :disabled="sending || {{ $isMeet ? 'true' : 'false' }}"
                            aria-haspopup="dialog" aria-controls="confirm-send-registration"
                            onclick="document.getElementById('confirm-send-registration').showModal()">Kirim Pendaftaran</button>
                @endcan
            </div>
            @can($permission)
                <dialog id="confirm-send-registration" aria-labelledby="confirm-send-title" aria-describedby="confirm-send-description"
                        @cancel="if (sending) $event.preventDefault()"
                        class="m-auto w-11/12 max-w-lg rounded-2xl border border-slate-200 bg-white p-5 text-slate-800 shadow-xl backdrop:bg-slate-900/50 sm:p-6">
                    <h2 id="confirm-send-title" class="text-lg font-bold">
                        {{ $isRevision ? 'Kirim revisi pendaftaran?' : 'Kirim pendaftaran sekarang?' }}
                    </h2>
                    <div id="confirm-send-description" class="mt-3 space-y-3 text-sm leading-relaxed text-slate-600">
                        <p>Periksa kembali data dan seluruh dokumen. Setelah dikirim, pengajuan akan ditinjau oleh petugas.</p>
                        <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-amber-900">
                            <p class="font-semibold">Pastikan kembali:</p>
                            <ul class="mt-2 list-inside list-disc space-y-1">
                                <li>Periode Sidang TA dan nomor kontak sudah benar.</li>
                                <li>Tanggal pembayaran wisuda serta pilihan biodata sudah sesuai.</li>
                                <li>Seluruh berkas lengkap, benar, dan dapat dibaca.</li>
                            </ul>
                        </div>
                        <p>Jika masih ragu, pilih Periksa kembali. Jika sudah yakin, lanjutkan pengiriman. Perubahan setelah pengiriman mengikuti proses revisi yang tersedia.</p>
                    </div>
                    <form data-final-registration-form method="POST" class="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"
                          action="{{ $isRevision ? route('sidang-ta-registrations.update', $draft->source_registration_id) : route('sidang-ta-registrations.store') }}"
                          @submit="if (sending || {{ $isMeet ? 'true' : 'false' }}) { $event.preventDefault(); } else { sending = true; }">
                        @csrf
                        @if($isRevision) @method('PUT') @endif
                        <input type="hidden" name="draft_id" value="{{ $draft->id }}">
                        <input type="hidden" name="version" value="{{ $draft->version }}">
                        <input type="hidden" name="action" value="submit">
                        <button data-cancel-registration type="button" autofocus class="btn btn-secondary" :disabled="sending"
                                onclick="this.closest('dialog').close()">Periksa kembali</button>
                        <button data-confirm-registration type="submit" class="btn btn-primary" @disabled($isMeet) :disabled="sending || {{ $isMeet ? 'true' : 'false' }}"
                                x-text="sending ? 'Sedang mengirim…' : '{{ $confirmLabel }}'">{{ $confirmLabel }}</button>
                    </form>
                </dialog>
            @endcan
        </section>
    </div>
    @include('sidang_ta_registrations.partials.document-preview-modal')
</x-app-layout>

```

## 5. Perilaku dan perubahan mode saat Tinjau

Controller membentuk satu daftar dokumen untuk render. Sebelum view mengakses dokumen, preview memeriksa metadata dan keberadaan setiap file pada daftar tersebut. Jika mode EPRT berubah menjadi waiver setelah validasi service, sementara surat belum ada, controller mengembalikan 409 dan meminta tinjau ulang, bukan menghasilkan 500.

Panel versi 2 memakai TAK, SKS lulus, EPRT, dan status SKPI/tingkat2/KRS dari adapter yang sama dengan kondisi upload. Versi 1 tetap menggunakan perilaku sebelumnya. Nilai otomatis tidak diambil dari old input dan tidak memiliki input yang bisa diedit.

Kartu readonly memakai latar abu-abu dengan teks yang tetap terbaca. Link surat diberi teks **Link Surat Permakluman** dan membuka tab baru. Akses file SharePoint tetap mengikuti hak akses pemilik tautan; test hanya memeriksa URL yang diberikan pengguna.

`Model::DOCUMENT_TYPES` belum diubah: label surat diambil dari registry meet untuk tampilan baru, sehingga kolom ekspor lama tetap stabil sampai dukungan versi 2 disiapkan.

## 6. Gambar panduan

Path gambar sesuai rekomendasi:

```text
public/assets/images/forms/sidang-ta/ta-data.png
public/assets/images/forms/sidang-ta/bimbingan-online.png
```

```bash
mkdir -p public/assets/images/forms/sidang-ta
```

Dua gambar asli belum tersedia saat scan. Jika sudah ada, taruh dengan nama di atas. Partial hanya merender gambar yang filenya tersedia, sehingga tidak menampilkan broken image. Gambar dapat dibuka dalam ukuran penuh dan menggantikan deskripsi contoh capture; format dan batas ukuran upload tetap ditampilkan.

Selama gambar belum tersedia, petunjuk teks yang sudah ada tetap digunakan. Ini berarti kebutuhan dua gambar asli masih perlu diselesaikan. Keduanya belum termasuk tujuh file checkpoint. Jika ditambahkan, salin juga ke dadu-test dan beri tahu saat rescan. Jangan memakai gambar BASILA/yudisium sebagai panduan Data TA yang berbeda.

## 7. Test setelah kode

```bash
php -l app/Http/Controllers/SidangTaDraftController.php
php artisan view:clear
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaMeetUiTest.php
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaMeetUiTest.php tests/Feature/SidangTaMeetDraftTest.php tests/Feature/SidangTaWizardUiTest.php tests/Feature/SidangTaReviewUiTest.php tests/Feature/SidangTaReviewFeedbackTest.php
node tests/Js/sidang-ta-entry.test.mjs
node tests/Js/sidang-ta-wizard.test.mjs
node tests/Js/sidang-ta-document-preview.test.mjs
```

Target **12 test baru**, fokus gabungan **51 passed** (12+14+10+5+10), dan Node12. Feature TA bertambah12 (239 jika sebelumnya227); UnitTA21 tetap. Jalankan seluruh suite TA seperti biasa setelah fokus lulus. Tidak perlu migrate, seed, atau fresh.

Jalankan aplikasi dan Vite seperti biasa untuk manual. Jika memakai aset hasil build, jalankan:

```bash
npm run build
```

Asisten tidak menjalankan build atau Feature test pada DB. Probe Blade di memori tidak menggantikan test di atas.

## 8. Manual versi 2 pada akun dummy — prosedur diperbaiki

Jika mendapat `ModelNotFoundException` untuk SidangTaRegistrationDraft pada panduan awal, **perubahan ke versi2 belum terjadi**. Query hanya mencari draft aktif milik akun/NIM tertentu, tetapi tidak membuat draft jika belum ada. UI tetap versi1 karena server memang belum mengaktifkan versi2 untuk semua pendaftaran.

Scan implementasi55 di folder asli cocok dengan panduan. Ini masalah persiapan data manual, bukan alasan mengubah default model, menghapus database, atau menonaktifkan pemeriksaan pemilik. Semua test yang lulus membuat data terpisah di `dadu_testing`; data tersebut tidak otomatis muncul pada DB aplikasi.

### A0. Buat akun mahasiswa dummy lokal, sebelum langkah Tinker lainnya

**Koreksi arahan sebelumnya:** form akun manual IT Panel memang hanya menerima admin, Pelaksana, Pemimpin, dan Pengamat, termasuk ketika dibuka oleh superadmin. Method `ManualUserController::assignableRoles()` menetapkan daftar tersebut. Ini bukan bug dropdown. Tidak perlu menambahkan mahasiswa ke daftar itu untuk keperluan test.

Sebelum menjalankan A0, login superadmin dan impor `01-mahasiswa-dummy.json` melalui **IT Panel → Sinkronisasi Data → Profil Mahasiswa (student)**. Impor Student ini tidak memakai dropdown role akun manual dan tidak membutuhkan akun dummy User terlebih dahulu. Untuk isi akademik lengkap, lanjutkan urutan impor02/03 pada53.

Untuk akun dummy lokal, gunakan pola yang sudah dipakai `SidangTaTesterSeeder`: User manual dengan role mahasiswa. Blok berikut hanya bekerja pada APP_ENV local, memeriksa Student hasil impor01, dan membuat/memperbarui **akun dummy002**. Password dummy ditetapkan menjadi `password`, sehingga bila akun dummy sudah ada passwordnya juga disetel ke nilai itu. Role akun dummy menjadi mahasiswa saja; tidak menyentuh akun Krisna, superadmin, atau role global.

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
php artisan tinker
```

```php
if (!app()->environment('local')) {
    throw new RuntimeException('Prosedur akun dummy ini hanya untuk lingkungan local.');
}
$dummyNim = '1202299002';
$dummyEmail = 'dummy-ta-2@mahasiswa.test';
$dummyStudent = App\Models\Student::where('student_number', $dummyNim)->first();
if (!$dummyStudent || $dummyStudent->email !== $dummyEmail) {
    throw new RuntimeException('Impor file01 mahasiswa dummy dari53 dahulu; NIM dan email harus sesuai.');
}
if (App\Models\Student::where('email', $dummyEmail)->where('student_number', '!=', $dummyNim)->exists()) {
    throw new RuntimeException('Email dummy terhubung ke lebih dari satu mahasiswa. Periksa data impor dahulu.');
}
$studentRole = Spatie\Permission\Models\Role::where('name', 'mahasiswa')->where('guard_name', 'web')->first();
if (!$studentRole) {
    throw new RuntimeException('Role mahasiswa belum tersedia. Periksa setup role aplikasi sebelum membuat akun dummy.');
}
$matches = App\Models\User::where(function ($query) use ($dummyNim, $dummyEmail) {
    $query->where('username', $dummyNim)->orWhere('email', $dummyEmail);
})->get();
foreach ($matches as $match) {
    if ($match->username !== $dummyNim || $match->email !== $dummyEmail) {
        throw new RuntimeException('Username atau email dummy berbenturan dengan akun lain. Tidak ada akun yang diubah.');
    }
}
if ($matches->count() > 1) {
    throw new RuntimeException('Identitas akun dummy ambigu. Periksa akun terlebih dahulu.');
}
$dummyUser = Illuminate\Support\Facades\DB::transaction(function () use ($matches, $dummyStudent, $dummyNim, $dummyEmail, $studentRole) {
    $user = $matches->first() ?? new App\Models\User();
    $user->fill([
        'name' => $dummyStudent->name,
        'username' => $dummyNim,
        'email' => $dummyEmail,
        'password' => Illuminate\Support\Facades\Hash::make('password'),
        'auth_source' => 'manual',
        'status' => 'active',
        'must_change_password' => false,
    ]);
    $user->save();
    $user->syncRoles([$studentRole]);
    return $user;
});
app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
dump($dummyUser->only(['id', 'username', 'email', 'auth_source', 'status']), $dummyUser->getRoleNames()->all());
```

Login browser memakai **dummy-ta-2@mahasiswa.test / password**. Jika sebelumnya membuat akun dummy002 ber-role Pelaksana/admin karena pilihan yang tersedia, blok di atas mengubah hanya akun dummy yang identitasnya tepat menjadi mahasiswa. Akun lain yang kebetulan berbenturan tidak diubah.

Tetap di sesi Tinker yang sama untuk A–C berikutnya, atau buka sesi baru dan mulai dari pengisian `$owner` pada A. Tidak perlu menjalankan seeder global, migrate:fresh, atau mengubah controller IT Panel.

### A. Pastikan akun, database, dan data mahasiswa

Jika belum melakukan impor manual53, jalankan **53 bagian4–6** dahulu: Mahasiswa → KRS → Profil Akademik. JSON53 hanya membuat Student. Akun User dummy002 disiapkan melalui A0 di bawah, bukan form akun manual IT Panel.

Jalankan di folder asli, tanpa prefix environment command test:

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
php artisan tinker
```

Paste blok berikut, termasuk pengisian `$owner` meskipun sudah pernah diisi pada sesi sebelumnya:

```php
$targetNim = '1202299002';
$owner = App\Models\User::where('username', $targetNim)->first();
if (!$owner) {
    throw new RuntimeException('Akun User dummy002 belum ada. Jalankan A0 pada panduan55 dahulu.');
}
$student = app(App\Services\SidangTaStudentContext::class)->studentFor($owner);
if ($student->student_number !== $targetNim) {
    throw new RuntimeException('Akun dummy terhubung ke NIM lain. Periksa username/email akun, jangan mengganti pemilik draft.');
}
$dbName = Illuminate\Support\Facades\DB::connection()->getDatabaseName();
dump([
    'database' => $dbName,
    'owner' => $owner->only(['id', 'username', 'email']),
    'student' => $student->only(['student_number', 'name', 'email']),
    'can_read' => $owner->can('sidang_ta_registration_read'),
    'can_create' => $owner->can('sidang_ta_registration_create'),
]);
dump(App\Models\SidangTaRegistrationDraft::where('student_number', $targetNim)
    ->get(['id', 'owner_user_id', 'source_registration_id', 'status', 'requirements_version', 'version'])->toArray());
```

Database harus sama dengan aplikasi yang dibuka di browser. Jika tampil `dadu_testing`, keluar dan jalankan Tinker memakai konfigurasi DB aplikasi. Jika Student tidak ditemukan/akun tidak terhubung, periksa impor01 dan identitas akun. Jika izin false, perbaiki role/izin melalui Superadmin; jangan melewati guard.

Array draft kosong berarti belum ada draft untuk NIM002 pada database itu. Draft yang dibuat ketika login **Krisna** tidak termasuk draft002, karena pemilik dan NIM berbeda.

### B. Buat atau lanjutkan draft lewat service existing

Paste seluruh blok sekaligus. Query dibungkus tanda kurung supaya PsySH/Tinker tidak mengeksekusi baris pertama sebelum seluruh chain selesai. Jika mengetik satu baris per Enter, tunggu prompt lanjutan sampai tanda kurung ditutup.

Blok ini mengambil draft aktif milik akun yang benar. Jika belum ada, service yang dipakai UI akan membuatkannya setelah memeriksa izin, mahasiswa, pengajuan aktif, dan periode. Tidak mengubah atau mengambil alih draft akun lain.

Untuk pembuatan **draft dummy lokal** ini, contoh memilih periode terbuka pertama menurut ID. Daftar periodenya dicetak; jika ingin periode lain, ganti nilai `$periodId` dengan ID dari daftar sebelum pemanggilan `start`. Pada UI normal mahasiswa tetap memilih periodenya sendiri.

```php
if ($dbName === 'dadu_testing') {
    throw new RuntimeException('Gunakan DB aplikasi untuk manual browser, bukan dadu_testing.');
}
if (!$owner->can('sidang_ta_registration_create') || !$owner->can('sidang_ta_registration_read')) {
    throw new RuntimeException('Akun dummy memerlukan izin baca dan pendaftaran Sidang TA.');
}
$draft = (
    App\Models\SidangTaRegistrationDraft::where('owner_user_id', $owner->id)
        ->where('student_number', $targetNim)->whereNull('source_registration_id')
        ->where('status', 'active')->latest()->first()
);
if (!$draft) {
    $periods = App\Models\SidangTaPeriod::query()->openForRegistration()->orderBy('id')->get();
    dump($periods->map(fn ($period) => $period->only(['id', 'name', 'open_at', 'close_at']))->all());
    $periodId = $periods->first()?->id;
    if (!$periodId) {
        throw new RuntimeException('Belum ada periode sidang terbuka. Buka periode lewat pengaturan petugas dahulu.');
    }
    $draft = app(App\Services\SidangTaDraftService::class)->start($owner, [
        'sidang_ta_period_id' => $periodId,
        'student_phone' => '081234567890',
        'parent_phone' => '081234567891',
    ]);
}
dump($draft->only(['id', 'owner_user_id', 'student_number', 'status', 'requirements_version', 'version']));
```

Jika service mengembalikan409 tentang pengajuan aktif, periksa pengajuan dummy itu lewat UI; jangan mengubah status/owner langsung untuk melewati pemeriksaan. Jika draft sudah mempunyai file versi1, prosedur berikut akan berhenti agar file lama tidak ditafsirkan ulang secara diam-diam.

### C. Siapkan versi2 tanpa menghapus file

Jalankan pada sesi Tinker yang sama. Blok mengunci Student → Draft, memeriksa pemilik dan NIM, serta tidak bekerja pada draft revisi. Jika draft sudah versi2, blok hanya mengembalikan identitasnya; tidak mereset atau menaikkan versi lagi.

```php
$prepared = Illuminate\Support\Facades\DB::transaction(function () use ($owner, $targetNim, $draft) {
    $student = App\Models\Student::where('student_number', $targetNim)->lockForUpdate()->firstOrFail();
    $locked = App\Models\SidangTaRegistrationDraft::whereKey($draft->id)
        ->where('owner_user_id', $owner->id)->where('student_number', $student->student_number)
        ->whereNull('source_registration_id')->where('status', 'active')->lockForUpdate()->firstOrFail();
    if ($locked->requirements_version === 2) {
        return $locked->only(['id', 'requirements_version', 'version']);
    }
    if ($locked->requirements_version !== 1 || $locked->documents()->exists() || $locked->reviewed_version !== null) {
        throw new RuntimeException('Gunakan draft dummy baru versi1 tanpa berkas atau tinjauan; draft ini tidak diubah.');
    }
    $locked->update(['requirements_version' => 2, 'version' => $locked->version + 1, 'reviewed_version' => null]);
    return $locked->only(['id', 'requirements_version', 'version']);
});
dump($prepared);
dump(route('sidang-ta-drafts.edit', ['draft' => $prepared['id'], 'step' => 2], false));
```

Harus tampil `requirements_version: 2` dan alamat relatif berisi UUID draft yang sama. Jika terjadi exception, jangan menganggap perubahan berhasil. Transaksi yang gagal tidak menyiapkan versi2.

### D. Buka draft yang tepat di browser

1. Login sebagai **dummy002**, bukan akun Krisna. Pilih role Mahasiswa jika tersedia pilihan role.
2. Buka path hasil `dump(route(..., false))` pada host/port aplikasi yang sedang berjalan. Contoh bentuknya `/sidang-ta-drafts/UUID/edit?step=2`. Jangan membuka ulang UUID draftKrisna.
3. Muat ulang halaman. Tahap2 harus menampilkan dua kartu abu-abu readonly dan dua upload DataTA/Bimbingan. Tinker untuk dummy002 tidak mengubah draft mahasiswa lain.
4. Tahap3 dummy002 mempunyai empat upload termasuk SuratPermakluman. Jika hanya tiga, periksa hasil Facts pada53bagian6: mode harus `waiver`, bukan unknown karena profil03 belum diimpor.
5. Lengkapi tanggal, berkas, dan biodata. Tinjau menampilkan delapan preview serta lima pemeriksaan akademik. Kirim tetap disabled pada checkpoint55.
6. Uji drag/drop, Simpan parsial, mobile/desktop, preview, dan resume setelah browser ditutup. Regresi versi1 pada akun lama tetap berjalan.

Jika UUID dan versi2 sudah benar tetapi browser masih memakai template lama, keluar dari Tinker lalu jalankan:

```bash
php artisan view:clear
```

Muat ulang **alamat draft yang sama**. Cache bukan pengganti pemeriksaan akun/UUID/database. Bila tetap berbeda, kirim output diagnostik A, hasil `$prepared`, dan path yang dibuka, tanpa password.

Untuk automatic/retest, ulangi dengan pasangan NIM/email yang sesuai: dummy001 memakai `1202299001` / `dummy-ta-1@mahasiswa.test`, dummy003 memakai `1202299003` / `dummy-ta-3@mahasiswa.test`. Ganti `$dummyNim` dan `$dummyEmail` pada A0, lalu `$targetNim` pada A. Login browser memakai akun yang sama. Jangan memakai prosedur ini untuk pengajuan asli atau draft revisi. Tidak perlu migrate:fresh atau mengulang unit test karena yang diperbaiki hanya prosedur manual, bukan kode aplikasi.

## 9. Pemindahan dan kelanjutan

Setelah test dan manual berhasil, salin tujuh file pada bagian2. Dua PNG hanya ikut disalin jika sudah tersedia. Jangan memindahkan `.env`, database, vendor/cache, atau konfigurasi PHPUnit lokal.

Berikutnya: pemeriksaan final lima syarat dan EPRT, snapshot keputusan, dan aktivasi pembuatan versi2. Penahan Kirim55 dibuka setelah guard final siap. Revisi terarah, penguncian petugas, batas waktu, grid status, ikon, dan ekspor tetap bagian pekerjaan selanjutnya.

Rujukan Context7: [Laravel13 Blade](https://github.com/laravel/docs/blob/13.x/blade.md), untuk include, escaping, dan conditional attributes. Asisten hanya menulis panduan; kode diterapkan pengguna.


## 10. Pemeriksaan panduan

Pada versi awal, tiga blok PHP dan lima blok Blade lulus pemeriksaan sintaks. Setelah koreksi manual dan pembuatan akun pada bagian8, keenam blok PHP saat ini juga lulus pemeriksaan sintaks. Blade dikompilasi di memori; tag komponen layout dikeluarkan hanya untuk probe agar aplikasi tidak dibootstrap. Render view aktual yang diusulkan lulus64assertion untuk empat tahap dan review: versi1cabangsesuai/PDM, versi2automatic/waiver/retest/unknown, jumlahfile, statusdisabled, danpermissionreadonly. Helpers,storage,dankonteksfactsdipasangstub; ini bukan FeaturetestDB atauujiAlpinebrowser.

Review independen tidak menemukan blocker pada route/fixture, kartu otomatis, jumlahfile, escapingfilename, pembatasanoldinput, ataupreflightmodechange. Pertahankan blok `@php ... @endphp` pada viewreview seperti contoh; sintaks blok tersebut sudah diuji bersama seluruh loop.

Asisten tidak menjalankan migrasi, build, FeatureDB, atau manualbrowser. Source aplikasi di kedua folder dipindai sebelum/sesudah penulisan; asisten hanya mengubah Markdown. Hasil test pengguna tetap diperlukan sebelum pemindahan dan langkah berikutnya.


## 11. Catatan koreksi manual setelah unit test lulus

Pengguna melaporkan semua unit test55 lulus tetapi query draft pada Tinker melempar ModelNotFoundException. Scan ketujuh file implementasi di folder asli cocok dengan kode panduan jika whitespace diabaikan. Tidak ada perubahan kode aplikasi yang diperlukan untuk laporan ini.

Bagian8 diperbaiki karena prosedur awal hanya mencari draft dengan firstOrFail. Sekarang tersedia diagnostik akun/NIM/DB, pembuatan atau resume melalui DraftService, dan persiapan versi2 yang idempotent serta menolak migrasi draft lama berfile. Panduan tidak menyimpulkan apakah pengguna sedang loginKrisna atau berada di DB berbeda; outputdiagnostik menunjukkan penyebab aktual.

Referensi Context7: [Laravel13 Eloquent not-found exceptions](https://github.com/laravel/docs/blob/13.x/eloquent.md#not-found-exceptions). Asisten tidak membaca atau mengubah DB aplikasi; snippet diperiksa secara sintaks, sedangkan eksekusi manual dilakukan pengguna.


## 12. Koreksi pembuatan akun dummy

Pengguna menemukan form akun manual hanya menawarkan empat role. Scan `ManualUserController::assignableRoles()` mengonfirmasi pembatasan itu disengaja. Arahan lama membuat mahasiswa dummy melalui panel tersebut tidak sesuai kode dan telah diganti pada bagian8A0. Student berasal dari JSON01; User dummy lokal dibuat terpisah dan diberi role mahasiswa yang sudah ada, mengikuti pola seeder test.

Tidak ada perubahan kode produksi, daftar role panel, atau unit test. Jalankan A0 lalu lanjutkan A–D; hasil test55 tetap berlaku. Asisten tidak mengeksekusi blok akun atau membaca DB aplikasi.


## 13. Koreksi pengetikan query pada Tinker

Jika baris pertama `$draft = ...::where(...)` sudah menghasilkan Eloquent Builder, berarti Tinker mengeksekusinya sebagai satu perintah lengkap. Baris baru yang dimulai `->where(...)` tidak mempunyai objek di sebelah kirinya, sehingga menjadi parse error. Ini bukan error database atau kebutuhan migrate.

Bagian8B sekarang membungkus chain dalam tanda kurung. Untuk memperbaiki variabel `$draft` yang telanjur berisi Builder, jalankan ulang query lengkap ini dalam **satu baris**:

```php
$draft = App\Models\SidangTaRegistrationDraft::where('owner_user_id', $owner->id)->where('student_number', $targetNim)->whereNull('source_registration_id')->where('status', 'active')->latest()->first();
dump($draft?->only(['id', 'owner_user_id', 'student_number', 'requirements_version', 'version']));
```

Jika hasil null, belum ada draft yang cocok: lanjutkan bagian8B lengkap untuk pembuatan lewat service, bukan langsung bagianC. Jika berhasil mendapat model, lanjutkan bagianC. Jangan meneruskan chain setelah output `= Illuminate\Database\Eloquent\Builder` sudah tercetak; mulai ulang dari assignment lengkap.
