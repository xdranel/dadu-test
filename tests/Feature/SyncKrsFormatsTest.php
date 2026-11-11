<?php

use App\Models\{AcademicPeriod, Course, Student, StudentCourse, SyncLog, User};
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\DomCrawler\Crawler;

beforeEach(function () {
    $actor = User::factory()->create(['status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
    foreach (['sync_read', 'sync_execute'] as $permission) {
        $actor->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($actor);
    $this->student98 = Student::create(['student_number' => '1298000001', 'name' => 'Mahasiswa Contoh',
        'academic_year' => 2022, 'active_semester' => 9, 'study_program' => 'Informatika', 'status' => 'Aktif']);
    $this->period98 = AcademicPeriod::create(['year' => '2026/2027', 'semester' => 1,
        'label' => 'Ganjil 2026/2027', 'start_date' => '2026-08-01', 'end_date' => '2027-01-31', 'is_active' => true]);
    $this->post98 = fn (array $data) => $this->post(route('sync.import-json'), ['type' => 'krs',
        'json_file' => UploadedFile::fake()->createWithContent('krs98.json', json_encode($data, JSON_THROW_ON_ERROR))]);
    $this->example98 = function (string $format) {
        $page = $this->get(route('sync.index'))->assertOk();
        return json_decode((new Crawler($page->getContent()))->filter('[data-krs-example="'.$format.'"]')->text(), true, 512, JSON_THROW_ON_ERROR);
    };
});

test('krs98 displayed flat example defaults to preview and only confirmation writes internal study semester', function () {
    $data = ($this->example98)('flat');
    $preview = ($this->post98)($data)->assertOk()->assertSee('Pratinjau KRS');
    $headers = (new Crawler($preview->getContent()))->filter('thead th')->each(fn ($node) => $node->text());
    expect($headers)->not->toContain('Semester Studi');
    expect(StudentCourse::count())->toBe(0)->and(Course::count())->toBe(0);
    $this->post(route('sync.krs-term-apply'), ['preview_token' => session('krs_term_preview.token'), 'confirmed' => 1])
        ->assertRedirect(route('sync.index'))->assertSessionHasNoErrors();
    $row = StudentCourse::sole();
    expect((int) $row->semester)->toBe(9)->and((int) $row->academic_period_id)->toBe($this->period98->id)
        ->and($row->credits)->toBeNull();
});

test('krs98 displayed official example is auto detected and replaces the complete included period list', function () {
    StudentCourse::create(['student_number' => $this->student98->student_number, 'course_code' => 'OLD98',
        'course_name' => 'Mata kuliah lama', 'course_class' => 'A', 'academic_year' => '2026/2027',
        'semester' => 9, 'academic_period_id' => $this->period98->id]);
    $data = ($this->example98)('official');
    ($this->post98)($data)->assertRedirect(route('sync.index'))->assertSessionHasNoErrors()->assertSessionHas('success');
    expect(StudentCourse::sole()->course_code)->toBe('DEMO-KRS-01');
    expect(session('krs_term_preview'))->toBeNull();
    expect((int) SyncLog::latest('id')->firstOrFail()->processed_records)->toBe(1);
});

test('krs98 missing mode never treats study semester nine as an importable term', function () {
    $preview = ($this->post98)([['nim' => $this->student98->student_number, 'kode_matakuliah' => 'BAD98',
        'nama_matakuliah' => 'Mata kuliah uji', 'kelas_matakuliah' => 'A',
        'tahun_akademik' => '2026/2027', 'semester' => 9]])->assertOk();
    expect($preview->viewData('plans')[0]['action'])->toBe('skip');
    expect(StudentCourse::count())->toBe(0)->and(Course::count())->toBe(0);
});

test('krs98 retired indexed aaData cannot create students or masters through the old fallback', function () {
    $before = Student::count();
    ($this->post98)(['aaData' => [['1298999999', 'Mahasiswa Sintetis Baru', 'KELAS', 'PRIA', 'ISLAM',
        'Informatika', '', 'RETIRED98', 'Mata Kuliah Uji', null, '2627', '9', '', '', 'A']]])
        ->assertRedirect()->assertSessionHasErrors('json_file');
    expect(Student::count())->toBe($before)->and(StudentCourse::count())->toBe(0)->and(Course::count())->toBe(0);
});

test('krs98 format card follows import in the left column while status stays in the right', function () {
    $page = $this->get(route('sync.index'))->assertOk();
    $dom = new Crawler($page->getContent());
    $cards = $dom->filter('[data-sync-import-column]')->children('[data-import-json-card], [data-json-format-card]')
        ->each(fn ($node) => $node->getNode(0)->hasAttribute('data-import-json-card') ? 'import' : 'format');
    expect($cards)->toBe(['import', 'format']);
    expect($dom->filter('[data-sync-status-column] #statsGrid')->count())->toBe(1);
    expect($dom->filter('[data-sync-status-column] [data-json-format-card]')->count())->toBe(0);
    expect($dom->filter('select[name="krs_semester_mode"]')->count())->toBe(0);
    expect($dom->filter('[data-json-format-card]')->ancestors()->filter('[x-data]')->first()->attr('x-data'))->toContain('importType');
});
