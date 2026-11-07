<?php

use App\Models\AcademicPeriod;
use App\Models\Course;
use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDraft;
use App\Models\Student;
use App\Models\StudentAcademicProfile;
use App\Models\StudentCourse;
use App\Models\StudentCourseIssue;
use App\Models\StudentEprt;
use App\Models\StudentSidangTingkat;
use App\Models\StudentSkpi;
use App\Models\StudentTak;
use App\Models\User;
use App\Services\SidangTaAdmissionFacts;
use App\Services\StudentAcademicProfileImportService;
use App\Support\SidangTaMeetRequirements as Meet;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

function officialAdmission67Row(array $overrides = []): array
{
    return array_replace([
        'nim' => '1202267001', 'nama' => 'Mahasiswa Sintetis Admission 67',
        'sidang_tingkat_2' => 'Y', 'sks_lulus' => '130', 'ipk' => '3.50',
        'status_skpi' => 'Disetujui', 'persetujuan_maju_sidang' => 'APPROVED',
        'total_tak' => '60', 'nilai_bahasa' => '470', 'jenis_tes_bahasa' => 'Online EPrT',
        'jumlah_tes_bahasa' => 1,
        'riwayat_nilai_bahasa' => [['jenis_tes' => 'Online EPrT', 'nilai' => '470',
            'kedaluwarsa' => '19 Mei 2028', 'status_berlaku' => 'Berlaku']],
    ], $overrides);
}

beforeEach(function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-11-06 10:00:00', config('app.timezone')));
    Storage::fake('local');
    $this->user = User::factory()->create(['username' => '1202267001', 'email' => 'official67@test.local',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
    foreach (['sync_execute', 'sidang_ta_registration_read', 'sidang_ta_registration_create', 'sidang_ta_registration_update'] as $name) {
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($this->user);
    $this->student = Student::create(['student_number' => $this->user->username, 'name' => 'Mahasiswa Sintetis Admission 67',
        'email' => $this->user->email, 'academic_year' => 2022, 'study_program' => 'Informatika', 'status' => 'Aktif']);
    AcademicPeriod::query()->update(['is_active' => false]);
    $this->academicPeriod = AcademicPeriod::create(['year' => '2026/2027', 'semester' => 1, 'label' => 'Official 67',
        'start_date' => '2026-08-01', 'end_date' => '2027-01-31', 'is_active' => true]);
    Course::create(['course_code' => 'OFFICIAL67', 'name' => 'TUGAS AKHIR 2', 'academic_year' => '2026/2027',
        'semester_term' => 'GANJIL', 'study_program' => 'Informatika', 'class' => 'O67', 'semester' => 8, 'credits' => 4]);
    $this->krs = StudentCourse::create(['student_number' => $this->student->student_number, 'course_code' => 'OFFICIAL67',
        'course_name' => 'TUGAS AKHIR 2', 'course_class' => 'O67', 'academic_year' => '2026/2027',
        'semester' => 8, 'academic_period_id' => $this->academicPeriod->id]);
    $this->period = SidangTaPeriod::create(['name' => 'Official JSON 67', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDays(3)]);
    $this->read67 = fn () => app(SidangTaAdmissionFacts::class)->forStudent($this->student);
    $this->profile67 = fn () => StudentAcademicProfile::where('student_number', $this->student->student_number)->firstOrFail();
    $this->import67 = function (array $overrides = [], ?array $menus = null) {
        $result = app(StudentAcademicProfileImportService::class)->import([officialAdmission67Row($overrides)], $menus);
        expect($result['created'] + $result['updated'])->toBe(1);
        expect($result['skipped'])->toBe(0);
        return $result;
    };
    $this->finish67 = function (string $mode = 'automatic') {
        $id = $this->postJson(route('sidang-ta-drafts.store'), ['sidang_ta_period_id' => $this->period->id,
            'student_phone' => '081234567890', 'parent_phone' => '081234567891'])->assertCreated()->json('data.id');
        $draft = SidangTaRegistrationDraft::findOrFail($id);
        foreach ([2, 3, 4] as $step) {
            $files = [];
            foreach (Meet::typesForStep($step, true, $mode) as $type) {
                $files[$type] = UploadedFile::fake()->create($type.'.pdf', 100, 'application/pdf');
            }
            $extra = match ($step) {
                3 => ['graduation_payment_date' => today()->toDateString()],
                4 => ['diploma_biodata_accurate' => true], default => [],
            };
            $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => $step]),
                array_replace(['version' => $draft->fresh()->version, 'documents' => $files], $extra))->assertOk();
        }
        $this->postJson(route('sidang-ta-drafts.review', $draft), ['version' => $draft->fresh()->version])->assertOk();
        return $draft->fresh();
    };
    $this->submit67 = fn ($draft, array $extra = []) => $this->postJson(route('sidang-ta-registrations.store'),
        array_replace(['draft_id' => $draft->id, 'version' => $draft->fresh()->version, 'action' => 'submit', 'declaration_accepted' => true], $extra));
});

afterEach(function () { $this->travelBack(); });

test('official67_full_import_supplies_six_gates_and_authoritative_language_summary', function () {
    $result = ($this->import67)();
    expect($result['errors'])->toBe([]);
    $facts = ($this->read67)();
    expect($facts['academic']['academic_passed'])->toBeTrue();
    expect(count($facts['academic']['gates']))->toBe(6);
    expect((float) $facts['eprt_facts']['score'])->toBe(470.0);
    expect($facts['eprt_facts']['confirmed_attempts'])->toBe(1);
    expect($facts['eprt']['mode'])->toBe('automatic');
});

test('official67_uses_official_count_even_when_history_has_one_or_no_entries', function () {
    foreach ([[], [['jenis_tes' => 'Online EPrT', 'nilai' => '440', 'status_berlaku' => 'Berlaku']]] as $history) {
        ($this->import67)(['nilai_bahasa' => '440', 'jumlah_tes_bahasa' => 3, 'riwayat_nilai_bahasa' => $history]);
        expect(($this->read67)()['eprt_facts']['confirmed_attempts'])->toBe(3);
        expect(($this->read67)()['eprt']['mode'])->toBe('waiver');
    }
});

test('official67_ecct_null_score_with_three_tests_requires_waiver_without_fabricating_zero', function () {
    ($this->import67)(['nilai_bahasa' => null, 'jenis_tes_bahasa' => 'Online ECCT', 'jumlah_tes_bahasa' => 3]);
    $facts = ($this->read67)();
    expect($facts['eprt_facts']['score'])->toBeNull();
    expect($facts['eprt_facts']['type'])->toBe('Online ECCT');
    expect($facts['eprt']['mode'])->toBe('waiver');
    expect($facts['eprt']['requires_waiver'])->toBeTrue();
});

test('official67_below_threshold_or_null_before_three_tests_requires_retest', function () {
    foreach ([null, '440'] as $score) {
        ($this->import67)(['nilai_bahasa' => $score, 'jumlah_tes_bahasa' => 2, 'riwayat_nilai_bahasa' => []]);
        expect(($this->read67)()['eprt']['mode'])->toBe('retest');
    }
});

test('official67_qualifying_score_needs_no_count_and_unknown_count_cannot_create_waiver', function () {
    ($this->import67)(['nilai_bahasa' => '450', 'jumlah_tes_bahasa' => null, 'riwayat_nilai_bahasa' => []]);
    expect(($this->read67)()['eprt']['mode'])->toBe('automatic');
    ($this->import67)(['nilai_bahasa' => null, 'jumlah_tes_bahasa' => null, 'riwayat_nilai_bahasa' => []]);
    expect(($this->read67)()['eprt']['mode'])->toBe('unknown');
});

test('official67_canonical_language_does_not_borrow_high_manual_scores', function () {
    StudentEprt::create(['student_number' => $this->student->student_number, 'total_score' => 650]);
    ($this->import67)(['nilai_bahasa' => '440', 'jumlah_tes_bahasa' => 2, 'riwayat_nilai_bahasa' => []]);
    expect(($this->read67)()['eprt']['mode'])->toBe('retest');
    expect((float) ($this->read67)()['eprt_facts']['score'])->toBe(440.0);
    ($this->import67)(['nilai_bahasa' => null, 'jumlah_tes_bahasa' => 3, 'riwayat_nilai_bahasa' => []]);
    expect(($this->read67)()['eprt']['mode'])->toBe('waiver');
});

test('official67_status_skpi_is_the_only_approval_authority_even_when_cached_flags_conflict', function () {
    ($this->import67)();
    foreach (['Belum Disetujui', 'APPROVED', 'disetujui', null] as $status) {
        $profile = ($this->profile67)();
        $groups = $profile->groups;
        $groups['skpi'] = ['status' => $status, 'approved' => true];
        $profile->update(['groups' => $groups]);
        expect(($this->read67)()['facts']['skpi_approved'])->toBeFalse();
    }
});

test('official67_first_partial_profile_does_not_fallback_to_unimported_manual_gate_values', function () {
    $nim = $this->student->student_number;
    $this->student->update(['total_credits' => 150, 'gpa' => 4]);
    StudentSidangTingkat::create(['student_number' => $nim, 'tingkat_2' => true]);
    StudentSkpi::create(['student_number' => $nim, 'raw_data' => ['status' => 'approved']]);
    StudentEprt::create(['student_number' => $nim, 'total_score' => 550]);
    ($this->import67)([], ['tak']);
    $facts = ($this->read67)();
    expect(array_keys($facts['academic']['blockers']))->toBe(['level_2_passed', 'skpi_approved', 'gpa', 'passed_credits']);
    expect($facts['eprt']['mode'])->toBe('unknown');
});

test('official67_explicit_null_credits_do_not_fallback_to_student_or_course_issue_values', function () {
    ($this->import67)(['sks_lulus' => null]);
    $this->student->update(['total_credits' => 150]);
    StudentCourseIssue::where('student_number', $this->student->student_number)->update(['credits_passed' => 150]);
    expect(($this->read67)()['facts']['passed_credits'])->toBeNull();
    expect(($this->read67)()['academic']['gates']['passed_credits']['state'])->toBe('unknown');
});

test('official67_current_tak_below_threshold_is_not_replaced_by_an_older_higher_score', function () {
    StudentTak::create(['student_number' => $this->student->student_number, 'total_points' => 100, 'test_date' => today()->subDay()]);
    ($this->import67)(['total_tak' => '59']);
    expect((float) ($this->read67)()['facts']['tak_score'])->toBe(59.0);
    expect(($this->read67)()['academic']['gates']['tak_score']['state'])->toBe('failed');
});

test('official67_reads_fresh_profile_after_reimport_even_if_student_relations_were_loaded', function () {
    ($this->import67)();
    $this->student->load(['eprts', 'tak', 'skpi', 'sidangTingkat', 'courseIssues']);
    expect(($this->read67)()['eprt']['mode'])->toBe('automatic');
    ($this->import67)(['nilai_bahasa' => '440', 'jumlah_tes_bahasa' => 2, 'riwayat_nilai_bahasa' => []]);
    expect(($this->read67)()['eprt']['mode'])->toBe('retest');
});

test('official67_does_not_borrow_another_students_canonical_profile', function () {
    $other = Student::create(['student_number' => '1202267002', 'name' => 'Mahasiswa Lain 67',
        'academic_year' => 2022, 'study_program' => 'Informatika', 'status' => 'Aktif']);
    StudentAcademicProfile::create(['student_number' => $other->student_number, 'groups' => [
        'academic' => ['level_2_passed' => true, 'passed_credits' => 150, 'gpa' => 4],
        'skpi' => ['status' => 'Disetujui', 'approved' => true], 'tak' => ['score' => 100],
        'language' => ['score' => 550, 'attempts' => 1, 'type' => 'Online EPrT', 'history' => []],
    ]]);
    expect(count(($this->read67)()['academic']['blockers']))->toBe(5);
    expect(($this->read67)()['eprt']['mode'])->toBe('unknown');
});

test('official67_reimport_error_preserves_successful_old_group_and_updates_other_groups', function () {
    ($this->import67)();
    ($this->import67)(['total_tak' => null, 'status_skpi' => 'Belum Disetujui', 'error' => 'tak: source failed']);
    expect((float) ($this->read67)()['facts']['tak_score'])->toBe(60.0);
    expect(($this->read67)()['facts']['skpi_approved'])->toBeFalse();
});

test('official67_malformed_or_incomplete_language_group_fails_closed_without_waiver', function () {
    ($this->import67)();
    $base = ($this->profile67)()->groups;
    foreach ([['score' => null, 'attempts' => 3],
                 ['score' => -1, 'attempts' => 3, 'type' => null, 'history' => []],
                 ['score' => true, 'attempts' => 3, 'type' => null, 'history' => []],
                 ['score' => null, 'attempts' => 3.5, 'type' => null, 'history' => []],
                 ['score' => null, 'attempts' => 3, 'type' => null, 'history' => [123]],
             ] as $language) {
        ($this->profile67)()->update(['groups' => array_replace($base, ['language' => $language])]);
        expect(($this->read67)()['eprt']['mode'])->toBe('unknown');
    }
});

test('official67_canonical_string_boolean_is_unknown_instead_of_truthy_pass', function () {
    ($this->import67)();
    $profile = ($this->profile67)();
    $groups = $profile->groups;
    $groups['academic']['level_2_passed'] = 'Y';
    $profile->update(['groups' => $groups]);
    expect(($this->read67)()['academic']['gates']['level_2_passed']['state'])->toBe('unknown');
});

test('official67_accepts_whole_krs_names_and_rejects_proposal_ta1_and_ta20', function () {
    ($this->import67)();
    foreach (['TUGAS AKHIR', 'TUGAS AKHIR 2', '  tugas  akhir  '] as $name) {
        $this->krs->update(['course_name' => $name]);
        expect(($this->read67)()['academic']['academic_passed'])->toBeTrue();
    }
    foreach (['TUGAS AKHIR 1', 'TUGAS AKHIR 20', 'PROPOSAL TUGAS AKHIR', 'TUGAS AKHIR LANJUTAN'] as $name) {
        $this->krs->update(['course_name' => $name]);
        expect(($this->read67)()['academic']['academic_passed'])->toBeFalse();
    }
});

test('official67_krs_still_requires_link_to_exactly_one_active_academic_period', function () {
    ($this->import67)();
    $other = AcademicPeriod::create(['year' => '2026/2027', 'semester' => 2, 'label' => 'Other 67',
        'start_date' => '2027-02-01', 'end_date' => '2027-07-31', 'is_active' => false]);
    foreach ([$other->id, null] as $id) {
        $this->krs->update(['academic_period_id' => $id]);
        expect(($this->read67)()['facts']['ta2_registered'])->toBeNull();
    }
    $this->krs->update(['academic_period_id' => $this->academicPeriod->id]);
    $other->update(['is_active' => true]);
    expect(($this->read67)()['krs_status'])->toBe('unknown');
});

test('official67_each_single_failed_academic_requirement_still_blocks_registration', function () {
    foreach ([['sidang_tingkat_2' => 'N'], ['status_skpi' => 'Belum Disetujui'],
                 ['total_tak' => '59'], ['sks_lulus' => '119']] as $overrides) {
        ($this->import67)($overrides);
        expect(count(($this->read67)()['academic']['blockers']))->toBe(1);
    }
    ($this->import67)();
    $this->krs->update(['course_name' => 'TUGAS AKHIR 1']);
    expect(array_keys(($this->read67)()['academic']['blockers']))->toBe(['ta2_registered']);
});

test('official67_automatic_submission_saves_official_score_and_seven_documents', function () {
    ($this->import67)();
    $draft = ($this->finish67)();
    $id = ($this->submit67)($draft)->assertOk()->json('data.id');
    $registration = SidangTaRegistration::findOrFail($id);
    expect($registration->documents()->count())->toBe(7);
    expect((float) $registration->academic_snapshot['eprt_score'])->toBe(470.0);
    expect($registration->academic_snapshot['admission']['eprt_facts']['confirmed_attempts'])->toBe(1);
});

test('official67_ecct_null_score_submits_with_waiver_and_eight_documents', function () {
    StudentEprt::create(['student_number' => $this->student->student_number, 'total_score' => 650]);
    ($this->import67)(['nilai_bahasa' => null, 'jenis_tes_bahasa' => 'Online ECCT', 'jumlah_tes_bahasa' => 3]);
    $draft = ($this->finish67)('waiver');
    $id = ($this->submit67)($draft)->assertOk()->json('data.id');
    $registration = SidangTaRegistration::findOrFail($id);
    expect($registration->documents()->count())->toBe(8);
    expect($registration->documents()->where('document_type', Meet::WAIVER)->exists())->toBeTrue();
    expect($registration->academic_snapshot['eprt_score'])->toBeNull();
    expect($registration->academic_snapshot['admission']['eprt_facts']['type'])->toBe('Online ECCT');
});

test('official67_submit_rechecks_new_skpi_and_keeps_draft_files_when_blocked', function () {
    ($this->import67)();
    $draft = ($this->finish67)();
    ($this->import67)(['status_skpi' => 'Belum Disetujui']);
    ($this->submit67)($draft, ['skpi_approved' => true])->assertUnprocessable()->assertJsonValidationErrors('eligibility.skpi_approved');
    expect($draft->fresh()->status)->toBe('active');
    expect($draft->documents()->count())->toBe(7);
    expect(count(Storage::disk('local')->allFiles()))->toBe(7);
    expect(SidangTaRegistration::where('student_number', $this->student->student_number)->count())->toBe(0);
});

test('official67_submit_rechecks_official_count_after_review_without_consuming_draft', function () {
    ($this->import67)(['nilai_bahasa' => '440', 'jumlah_tes_bahasa' => 3, 'riwayat_nilai_bahasa' => []]);
    $draft = ($this->finish67)('waiver');
    ($this->import67)(['nilai_bahasa' => '440', 'jumlah_tes_bahasa' => 2, 'riwayat_nilai_bahasa' => []]);
    ($this->submit67)($draft)->assertUnprocessable()->assertJsonValidationErrors('eligibility.eprt');
    expect($draft->fresh()->status)->toBe('active');
    expect($draft->documents()->count())->toBe(8);
});

test('official67_later_import_does_not_change_existing_final_academic_snapshot', function () {
    ($this->import67)();
    $draft = ($this->finish67)();
    $id = ($this->submit67)($draft)->assertOk()->json('data.id');
    $registration = SidangTaRegistration::findOrFail($id);
    $before = $registration->academic_snapshot;
    ($this->import67)(['status_skpi' => 'Belum Disetujui', 'total_tak' => '0', 'nilai_bahasa' => null, 'jumlah_tes_bahasa' => null]);
    expect($registration->fresh()->academic_snapshot)->toEqual($before);
    expect($registration->fresh()->status)->toBe('submitted');
});

test('official67_demo_payload_imports_two_student_profiles_for_three_login_accounts', function () {
    $usersBefore = User::count();
    $students = $courses = $profiles = [];
    foreach ([['1202200001', 'Krisna Mahasiswa', 'krisna@mahasiswa.test', '470', 1, 'TUGAS AKHIR'],
                 ['1202299002', 'Mahasiswa Dummy TA 2', 'dummy-ta-2@mahasiswa.test', '449', 3, 'TUGAS AKHIR 2'],
             ] as [$nim, $name, $email, $score, $count, $courseName]) {
        $students[] = ['nim' => $nim, 'nama_siswa' => $name, 'prodi' => 'Informatika',
            'academic_year' => 2022, 'active_semester' => 8, 'email' => $email, 'kelas' => 'DEMO67'];
        $courses[] = ['nim' => $nim, 'kode_matakuliah' => 'DEMO67-'.$nim,
            'nama_matakuliah' => $courseName, 'kelas_matakuliah' => 'DEMO67', 'sks' => 4,
            'tahun_akademik' => '2026/2027', 'semester_term' => 'GANJIL', 'semester' => 8, 'prodi' => 'Informatika'];
        $profiles[] = officialAdmission67Row(['nim' => $nim, 'nama' => $name, 'nilai_bahasa' => $score,
            'jumlah_tes_bahasa' => $count, 'riwayat_nilai_bahasa' => [],
            'status_keuangan' => 'LUNAS', 'status_keuangan_asli' => 'SUDAH LUNAS', 'semester' => '8']);
    }
    $upload = function (array $payload, string $type) {
        $this->post(route('sync.import-json'), ['type' => $type,
            'json_file' => UploadedFile::fake()->createWithContent('demo67.json', json_encode($payload, JSON_THROW_ON_ERROR)),
        ])->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success');
    };
    $upload(['data' => $students], 'student');
    $upload(['data' => $courses], 'krs');
    $upload(['selected_menus' => ['akademik', 'bahasa', 'tak', 'skpi', 'maju_sidang', 'keuangan'],
        'finance_period' => '2627/1', 'data' => $profiles], 'academic_profile');
    foreach ([['1202200001', 'automatic'], ['1202299002', 'waiver']] as [$nim, $mode]) {
        $student = Student::where('student_number', $nim)->firstOrFail();
        expect(StudentAcademicProfile::where('student_number', $nim)->exists())->toBeTrue();
        $facts = app(SidangTaAdmissionFacts::class)->forStudent($student);
        expect($facts['academic']['academic_passed'])->toBeTrue();
        expect($facts['eprt']['mode'])->toBe($mode);
    }
    $upload(['selected_menus' => ['bahasa'], 'data' => [[
        'nim' => '1202299002', 'nama' => 'Mahasiswa Dummy TA 2', 'nilai_bahasa' => null,
        'jenis_tes_bahasa' => 'Online ECCT', 'jumlah_tes_bahasa' => 3, 'riwayat_nilai_bahasa' => [],
    ]]], 'academic_profile');
    $facts = app(SidangTaAdmissionFacts::class)->forStudent(Student::where('student_number', '1202299002')->firstOrFail());
    expect($facts['eprt_facts']['score'])->toBeNull();
    expect($facts['eprt_facts']['confirmed_attempts'])->toBe(3);
    expect($facts['eprt']['mode'])->toBe('waiver');
    expect(User::count())->toBe($usersBefore);
});


test('meeting77_official_highest_valid_history_drives_facts_and_submitted_snapshot', function () {
    ($this->import67)(['nilai_bahasa' => '440', 'jumlah_tes_bahasa' => 3]);
    $facts = ($this->read67)();
    expect($facts['eprt_facts']['score'])->toBe(470.0);
    expect($facts['eprt_facts']['history'][0]['test_date'])->toBeNull();
    expect($facts['eprt_facts']['confirmed_attempts'])->toBe(3);
    $draft = ($this->finish67)();
    $id = ($this->submit67)($draft)->assertOk()->json('data.id');
    $snapshot = SidangTaRegistration::findOrFail($id)->academic_snapshot;
    expect($snapshot['admission']['eprt_facts']['score'])->toBe(470);
    expect($snapshot['admission']['eprt_facts']['history'][0]['expires_at'])->toBe('2028-05-19');
    expect($snapshot['admission']['eprt']['mode'])->toBe('automatic');
});


test('meeting77_unidentified_official_language_type_stays_unknown_and_preserves_imported_source', function ($type, $attempts) {
    ($this->import67)(['nilai_bahasa' => 600, 'jenis_tes_bahasa' => $type,
        'jumlah_tes_bahasa' => $attempts, 'riwayat_nilai_bahasa' => []]);
    $groups = ($this->profile67)()->groups;
    expect(($this->read67)()['eprt']['mode'])->toBe('unknown');
    expect(($this->read67)()['eprt']['requires_waiver'])->toBeFalse();
    expect(($this->read67)()['eprt_facts']['score'])->toBe(600.0);
    expect(($this->read67)()['eprt_facts']['confirmed_attempts'])->toBe($attempts);
    expect(($this->read67)()['eprt_facts']['scale_identified'])->toBeFalse();
    expect($groups['language']['score'])->toBe(600);
    expect($groups['language']['attempts'])->toBe($attempts);
    expect($groups['language']['type'])->toBe($type === '' || trim((string) $type) === '' ? null : $type);
    expect(($this->profile67)()->groups)->toBe($groups);
})->with([[null, null], ['', null], ['Unknown scale', null],
    [null, 3], ['   ', 3], ['Unknown scale', 3]]);


test('meeting77_unidentified_summary_cannot_borrow_a_history_type_and_keeps_raw_history', function () {
    $history = [['jenis_tes' => 'EPrT', 'nilai' => '470', 'status_berlaku' => 'Berlaku']];
    ($this->import67)(['nilai_bahasa' => 600, 'jenis_tes_bahasa' => 'Unknown scale',
        'jumlah_tes_bahasa' => 3, 'riwayat_nilai_bahasa' => $history]);
    $admission = ($this->read67)();
    expect($admission['eprt']['mode'])->toBe('unknown');
    expect($admission['eprt_facts']['confirmed_attempts'])->toBe(3);
    expect($admission['eprt_facts']['history'][0]['source'])->toEqual($history[0]);
    expect($admission['eprt_facts']['history'][0]['source']['nilai'])->toBe('470');
    expect(($this->profile67)()->groups['language']['history'])->toEqual($history);
});
