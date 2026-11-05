<?php

use App\Models\AcademicPeriod;
use App\Models\Student;
use App\Models\StudentCourse;
use App\Models\StudentEprt;
use App\Models\StudentSkpi;
use App\Models\StudentTak;
use App\Models\SyncLog;
use App\Models\User;
use App\Services\SidangTaAdmissionFacts;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

function meetDummyJson(string $filename): array
{
    return json_decode(file_get_contents(base_path('docs/fixtures/sidang-ta/'.$filename)), true, 512, JSON_THROW_ON_ERROR);
}

beforeEach(function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-11-04 12:00:00', config('app.timezone')));
    $user = User::factory()->create(['status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
    $user->givePermissionTo(Permission::firstOrCreate(['name' => 'sync_execute', 'guard_name' => 'web']));
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($user);
    AcademicPeriod::query()->update(['is_active' => false]);
    $this->academicPeriod = AcademicPeriod::create(['year' => '2026/2027', 'semester' => 1,
        'label' => 'Dummy import', 'start_date' => '2026-08-01', 'end_date' => '2027-01-31', 'is_active' => true]);
    $this->importDummy = function (string $type, string $filename, ?array $payload = null) {
        $payload ??= meetDummyJson($filename);
        return $this->from(route('sync.index'))->post(route('sync.import-json'), [
            'type' => $type,
            'json_file' => UploadedFile::fake()->createWithContent($filename, json_encode($payload, JSON_THROW_ON_ERROR)),
        ])->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success');
    };
    $this->importAll = function () {
        ($this->importDummy)('student', '01-mahasiswa-dummy.json');
        ($this->importDummy)('krs', '02-krs-ta2-dummy.json');
        ($this->importDummy)('academic_profile', '03-profil-akademik-dummy.json');
    };
});

afterEach(function () { $this->travelBack(); });

test('meet_dummy_json_import_reaches_admission_facts_for_four_scenarios', function () {
    ($this->importAll)();
    foreach ([
                 ['1202299001', true, 'automatic', 1],
                 ['1202299002', true, 'waiver', 3],
                 ['1202299003', true, 'retest', 2],
                 ['1202299004', false, 'automatic', 1],
             ] as [$nim, $academicPassed, $mode, $attempts]) {
        $student = Student::where('student_number', $nim)->firstOrFail();
        $result = app(SidangTaAdmissionFacts::class)->forStudent($student);
        expect($result['academic']['academic_passed'])->toBe($academicPassed);
        expect($result['eprt']['mode'])->toBe($mode);
        expect($result['eprt_facts']['confirmed_attempts'])->toBe($attempts);
        expect($result['facts']['skpi_approved'])->toBeTrue();
        expect($result['facts']['level_2_passed'])->toBeTrue();
        expect($result['krs_status'])->toBe('eligible');
        $krs = StudentCourse::where('student_number', $nim)->firstOrFail();
        expect((int) $krs->academic_period_id)->toBe($this->academicPeriod->id);
        if (!$academicPassed) expect(array_keys($result['academic']['blockers']))->toBe(['tak_score']);
    }
    $log = SyncLog::where('module', 'academic_profile')->latest('id')->firstOrFail();
    expect((int) $log->skipped_records)->toBe(0);
    expect($log->status)->toBe('completed');
});

test('meet_dummy_import_repeat_does_not_inflate_eprt_attempts_or_duplicate_records', function () {
    ($this->importAll)();
    ($this->importAll)();
    $nims = ['1202299001', '1202299002', '1202299003', '1202299004'];
    expect(Student::whereIn('student_number', $nims)->count())->toBe(4);
    expect(StudentCourse::whereIn('student_number', $nims)->count())->toBe(4);
    expect(StudentSkpi::whereIn('student_number', $nims)->count())->toBe(4);
    expect(StudentTak::whereIn('student_number', $nims)->count())->toBe(4);
    expect(StudentEprt::whereIn('student_number', $nims)->count())->toBe(7);
    $student = Student::where('student_number', '1202299002')->firstOrFail();
    $result = app(SidangTaAdmissionFacts::class)->forStudent($student);
    expect($result['eprt_facts']['confirmed_attempts'])->toBe(3);
    expect($result['eprt']['mode'])->toBe('waiver');
});

test('meet_dummy_academic_profile_name_mismatch_skips_without_writing_approval_or_scores', function () {
    ($this->importDummy)('student', '01-mahasiswa-dummy.json');
    $row = meetDummyJson('03-profil-akademik-dummy.json')['data'][0];
    $row['nama'] = 'Nama sengaja berbeda';
    ($this->importDummy)('academic_profile', '03-profil-akademik-dummy.json', ['data' => [$row]]);
    $log = SyncLog::where('module', 'academic_profile')->latest('id')->firstOrFail();
    expect((int) $log->skipped_records)->toBe(1);
    expect(StudentSkpi::where('student_number', $row['nim'])->exists())->toBeFalse();
    expect(StudentEprt::where('student_number', $row['nim'])->exists())->toBeFalse();
    expect(StudentTak::where('student_number', $row['nim'])->exists())->toBeFalse();
    expect(Student::where('student_number', $row['nim'])->value('name'))->toBe('Dummy TA EPRT Otomatis');
});

test('meet_dummy_krs_can_be_linked_by_reimport_after_academic_period_is_initialized', function () {
    $this->academicPeriod->delete();
    ($this->importAll)();
    $student = Student::where('student_number', '1202299002')->firstOrFail();
    $krs = StudentCourse::where('student_number', $student->student_number)->firstOrFail();
    expect($krs->course_name)->toBe('TUGAS AKHIR 2');
    expect($krs->academic_period_id)->toBeNull();
    expect(app(SidangTaAdmissionFacts::class)->forStudent($student)['krs_status'])->toBe('unknown');
    $period = AcademicPeriod::create(['year' => '2026/2027', 'semester' => 1,
        'label' => 'Ganjil 2026/2027', 'start_date' => '2026-08-01', 'end_date' => '2027-01-31', 'is_active' => true]);
    ($this->importDummy)('krs', '02-krs-ta2-dummy.json');
    $facts = app(SidangTaAdmissionFacts::class)->forStudent($student);
    expect((int) $krs->fresh()->academic_period_id)->toBe($period->id);
    expect($facts['krs_status'])->toBe('eligible');
    expect($facts['academic']['academic_passed'])->toBeTrue();
    expect($facts['eprt']['mode'])->toBe('waiver');
    expect(StudentCourse::whereIn('student_number', ['1202299001', '1202299002', '1202299003', '1202299004'])->count())->toBe(4);
    expect(StudentEprt::where('student_number', $student->student_number)->count())->toBe(3);
});
