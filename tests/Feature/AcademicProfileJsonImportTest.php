<?php

use App\Models\Student;
use App\Models\StudentAcademicProfile;
use App\Models\StudentBasilaApproval;
use App\Models\StudentBppPayment;
use App\Models\StudentCourseIssue;
use App\Models\StudentEprt;
use App\Models\StudentFinalProject;
use App\Models\StudentSidangTingkat;
use App\Models\StudentSkpi;
use App\Models\StudentTak;
use App\Models\SyncLog;
use App\Models\User;
use App\Services\GraduationDataSyncService;
use App\Services\StudentAcademicProfileImportService;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

function academicJson66Row(array $overrides = []): array
{
    return array_replace([
        'nim' => '1202266001', 'nama' => 'Mahasiswa Sintetis JSON 66',
        'sidang_tingkat_2' => 'Y', 'sks_lulus' => '130', 'sks_belum_lulus' => '0',
        'ipk' => '3.50', 'semester' => '7',
        'status_skpi' => 'Disetujui', 'skpi_status_1' => 'Y', 'skpi_status_2' => 'Y', 'skpi_status_3' => 'Y',
        'persetujuan_maju_sidang' => 'APPROVED', 'status_ta' => '1',
        'nilai_bahasa' => '440', 'jenis_tes_bahasa' => 'Online EPrT', 'jumlah_tes_bahasa' => 3,
        'riwayat_nilai_bahasa' => [['jenis_tes' => 'Online EPrT', 'nilai' => '440',
            'kedaluwarsa' => '19 Mei 2028', 'status_berlaku' => 'Berlaku']],
        'total_tak' => '80', 'status_keuangan' => 'LUNAS', 'status_keuangan_asli' => 'SUDAH LUNAS',
        'judul_tugas_akhir' => 'Judul Sintetis JSON 66', 'tanggal_sidang' => '20-11-2026',
    ], $overrides);
}

beforeEach(function () {
    $this->student = Student::create(['student_number' => '1202266001', 'name' => 'Mahasiswa Sintetis JSON 66',
        'academic_year' => 2022, 'study_program' => 'Informatika', 'status' => 'Aktif']);
    $user = User::factory()->create(['status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
    $user->givePermissionTo(Permission::firstOrCreate(['name' => 'sync_execute', 'guard_name' => 'web']));
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($user);
    $this->menus = ['akademik', 'bahasa', 'tak', 'keuangan', 'mata_kuliah', 'jadwal_sidang', 'skpi', 'maju_sidang'];
    $this->profile = fn () => StudentAcademicProfile::where('student_number', '1202266001')->firstOrFail();
    $this->postPayload = function (array $payload, string $routeName = 'sync.import-json') {
        $input = ['json_file' => UploadedFile::fake()->createWithContent('sintetis-66.json', json_encode($payload, JSON_THROW_ON_ERROR))];
        if ($routeName === 'sync.import-json') $input['type'] = 'academic_profile';
        return $this->from(route('sync.index'))->post(route($routeName), $input);
    };
    $this->import66 = function (array $row, ?array $menus = null, ?string $period = null) {
        $payload = ['selected_menus' => $menus ?? $this->menus, 'data' => [$row]];
        if ($period !== null) $payload['finance_period'] = $period;
        return ($this->postPayload)($payload)->assertRedirect(route('sync.index'))
            ->assertSessionHasNoErrors()->assertSessionHas('success');
    };
});

test('json66_modern_import_stores_official_summary_and_updates_existing_academic_tables', function () {
    ($this->import66)(academicJson66Row());
    $profile = ($this->profile)();
    expect($profile->groups['skpi']['approved'])->toBeTrue();
    expect($profile->groups['sidang_approval']['approved'])->toBeTrue();
    expect((float) $profile->groups['language']['score'])->toBe(440.0);
    expect($profile->groups['language']['attempts'])->toBe(3);
    expect($profile->groups['language']['history'][0]['kedaluwarsa'])->toBe('19 Mei 2028');
    expect($profile->groups['bpp']['period'])->toBeNull();
    expect((int) $this->student->fresh()->total_credits)->toBe(130);
    expect(StudentSidangTingkat::where('student_number', $this->student->student_number)->firstOrFail()->tingkat_2)->toBeTrue();
    expect((float) StudentTak::where('student_number', $this->student->student_number)->value('total_points'))->toBe(80.0);
    expect(StudentSkpi::where('student_number', $this->student->student_number)->firstOrFail()->raw_data['status_skpi'])->toBe('Disetujui');
    expect(StudentFinalProject::where('student_number', $this->student->student_number)->value('thesis_title_id'))->toBe('Judul Sintetis JSON 66');
    // Official history stays in its JSON group; never overwrite manual EPRT rows.
    expect(StudentEprt::where('student_number', $this->student->student_number)->count())->toBe(0);
});

test('json66_reimport_replaces_official_language_history_without_duplicates_or_manual_row_deletion', function () {
    $manual = StudentEprt::create(['student_number' => $this->student->student_number, 'total_score' => 500]);
    ($this->import66)(academicJson66Row());
    $history = [['jenis_tes' => 'ITP TOEFL', 'nilai' => '470', 'status_berlaku' => 'Berlaku']];
    ($this->import66)(academicJson66Row(['nilai_bahasa' => '470', 'jenis_tes_bahasa' => 'ITP TOEFL',
        'jumlah_tes_bahasa' => 1, 'riwayat_nilai_bahasa' => $history]), ['bahasa']);
    expect(StudentAcademicProfile::count())->toBe(1);
    $language = ($this->profile)()->groups['language'];
    expect((float) $language['score'])->toBe(470.0);
    expect($language['attempts'])->toBe(1);
    expect($language['history'])->toEqual($history);
    expect((int) $manual->fresh()->total_score)->toBe(500);
    expect(StudentEprt::count())->toBe(1);
});

test('json66_menu_selection_preserves_unselected_existing_data_even_if_unused_fields_are_null', function () {
    ($this->import66)(academicJson66Row());
    $before = ($this->profile)()->groups;
    ($this->import66)(academicJson66Row(['total_tak' => '60', 'status_skpi' => null, 'nilai_bahasa' => null,
        'sidang_tingkat_2' => null, 'sks_lulus' => null, 'status_keuangan' => null]), ['tak']);
    $after = ($this->profile)()->groups;
    expect((float) $after['tak']['score'])->toBe(60.0);
    foreach (['skpi', 'language', 'academic', 'bpp'] as $group) expect($after[$group])->toEqual($before[$group]);
    expect((int) $this->student->fresh()->total_credits)->toBe(130);
    expect(StudentBppPayment::where('student_number', $this->student->student_number)->value('status'))->toBe('LUNAS');
});

test('json66_explicit_null_and_zero_are_not_treated_as_missing_data', function () {
    ($this->import66)(academicJson66Row());
    ($this->import66)(academicJson66Row(['total_tak' => '0', 'status_skpi' => 'Belum Disetujui']), ['tak', 'skpi']);
    expect((float) ($this->profile)()->groups['tak']['score'])->toBe(0.0);
    expect(($this->profile)()->groups['skpi']['approved'])->toBeFalse();
    ($this->import66)(academicJson66Row(['total_tak' => null]), ['tak']);
    expect(($this->profile)()->groups['tak']['score'])->toBeNull();
    expect(StudentTak::where('student_number', $this->student->student_number)->value('total_points'))->toBeNull();
});

test('json66_tak_gateway_error_preserves_old_tak_but_imports_successful_skpi', function () {
    ($this->import66)(academicJson66Row());
    ($this->import66)(academicJson66Row(['total_tak' => null, 'status_skpi' => 'Belum Disetujui',
        'error' => 'tak: Token gateway TAK sudah kedaluwarsa.']));
    expect((float) ($this->profile)()->groups['tak']['score'])->toBe(80.0);
    expect(($this->profile)()->groups['skpi']['approved'])->toBeFalse();
    expect((float) StudentTak::where('student_number', $this->student->student_number)->value('total_points'))->toBe(80.0);
    expect(SyncLog::latest('id')->firstOrFail()->error_message)->toContain('menu tak gagal');
});

test('json66_bpp_credit_to_paid_updates_same_period_and_keeps_other_periods', function () {
    ($this->import66)(academicJson66Row(['status_keuangan' => 'PERLU VERIFIKASI', 'status_keuangan_asli' => 'KREDIT']), ['keuangan'], '2627/1');
    expect(($this->profile)()->bpp_periods['2627/1']['status'])->toBe('KREDIT');
    ($this->import66)(academicJson66Row(), ['keuangan'], '2627/1');
    expect(($this->profile)()->bpp_periods['2627/1']['status'])->toBe('LUNAS');
    ($this->import66)(academicJson66Row(['status_keuangan' => 'PERLU VERIFIKASI', 'status_keuangan_asli' => 'KREDIT']), ['keuangan'], '2627/2');
    $periods = ($this->profile)()->bpp_periods;
    expect(count($periods))->toBe(2);
    expect($periods['2627/1']['status'])->toBe('LUNAS');
    expect($periods['2627/2']['status'])->toBe('KREDIT');
});

test('json66_study_semester_never_becomes_an_invented_finance_period', function () {
    ($this->import66)(academicJson66Row(['semester' => '9']), ['keuangan']);
    expect(($this->profile)()->groups['bpp']['period'])->toBeNull();
    expect(($this->profile)()->bpp_periods)->toBe([]);
});

test('json66_unknown_period_import_does_not_destroy_confirmed_period_history', function () {
    ($this->import66)(academicJson66Row(), ['keuangan'], '2627/1');
    ($this->import66)(academicJson66Row(['status_keuangan' => 'PERLU VERIFIKASI', 'status_keuangan_asli' => 'KREDIT']), ['keuangan']);
    expect(($this->profile)()->groups['bpp']['period'])->toBeNull();
    expect(($this->profile)()->bpp_periods['2627/1']['status'])->toBe('LUNAS');
});

test('json66_period_conflict_skips_bpp_but_keeps_other_successful_groups', function () {
    ($this->import66)(academicJson66Row(['semester' => '2627/2']), null, '2627/1');
    expect(($this->profile)()->groups)->not->toHaveKey('bpp');
    expect(($this->profile)()->groups['skpi']['approved'])->toBeTrue();
    expect(SyncLog::latest('id')->firstOrFail()->error_message)->toContain('berbeda dengan konteks impor');
});

test('json66_explicit_empty_menu_list_keeps_all_old_data', function () {
    ($this->import66)(academicJson66Row());
    $before = ($this->profile)()->groups;
    ($this->import66)(academicJson66Row(['total_tak' => '0']), []);
    expect(($this->profile)()->groups)->toEqual($before);
    expect((int) SyncLog::latest('id')->firstOrFail()->skipped_records)->toBe(1);
});

test('json66_ecct_null_score_and_three_official_attempts_are_stored_without_fabricated_score', function () {
    ($this->import66)(academicJson66Row(['nilai_bahasa' => null, 'jenis_tes_bahasa' => 'Online ECCT',
        'jumlah_tes_bahasa' => 3, 'riwayat_nilai_bahasa' => []]), ['bahasa']);
    $language = ($this->profile)()->groups['language'];
    expect($language['score'])->toBeNull();
    expect($language['type'])->toBe('Online ECCT');
    expect($language['attempts'])->toBe(3);
});

test('json66_official_import_without_selected_menus_is_detected_from_new_field_names', function () {
    ($this->postPayload)(['data' => [academicJson66Row()]])->assertSessionHasNoErrors()->assertSessionHas('success');
    expect(($this->profile)()->groups['language']['attempts'])->toBe(3);
});

test('json66_both_profile_import_routes_use_same_metadata_and_storage', function () {
    foreach (['sync.import-json', 'sync.import-academic-profile'] as $routeName) {
        ($this->postPayload)(['data' => [academicJson66Row()], 'selected_menus' => $this->menus,
            'finance_period' => '2627/1'], $routeName)->assertSessionHasNoErrors()->assertSessionHas('success');
        expect(($this->profile)()->groups['language']['attempts'])->toBe(3);
        expect(($this->profile)()->bpp_periods['2627/1']['status'])->toBe('LUNAS');
    }
    expect(StudentAcademicProfile::count())->toBe(1);
});

test('json66_mismatched_name_does_not_write_any_academic_table', function () {
    ($this->import66)(academicJson66Row(['nama' => 'Nama Berbeda']));
    expect(StudentAcademicProfile::count())->toBe(0);
    expect(StudentTak::count())->toBe(0);
    expect(StudentSkpi::count())->toBe(0);
    expect((int) SyncLog::latest('id')->firstOrFail()->skipped_records)->toBe(1);
});

test('json66_unknown_student_is_skipped_without_creating_student_or_profile', function () {
    ($this->import66)(academicJson66Row(['nim' => '1202266999']));
    expect(Student::count())->toBe(1);
    expect(StudentAcademicProfile::count())->toBe(0);
    expect((int) SyncLog::latest('id')->firstOrFail()->skipped_records)->toBe(1);
});

test('json66_import_never_changes_another_students_profile', function () {
    $other = Student::create(['student_number' => '1202266002', 'name' => 'Mahasiswa Lain Sintetis',
        'academic_year' => 2022, 'study_program' => 'Informatika']);
    $profile = StudentAcademicProfile::create(['student_number' => $other->student_number,
        'groups' => ['tak' => ['score' => 99]], 'bpp_periods' => []]);
    ($this->import66)(academicJson66Row());
    expect($profile->fresh()->groups)->toEqual(['tak' => ['score' => 99]]);
});


test('json66_failure_saving_summary_rolls_back_other_writes_for_the_same_row', function () {
    StudentAcademicProfile::saving(function () { throw new RuntimeException('Simulated save failure'); });
    try {
        ($this->import66)(academicJson66Row());
        expect(StudentAcademicProfile::count())->toBe(0);
        expect(StudentTak::count())->toBe(0);
        expect(StudentSkpi::count())->toBe(0);
        expect(StudentCourseIssue::count())->toBe(0);
        expect(StudentSidangTingkat::count())->toBe(0);
        expect($this->student->fresh()->total_credits)->toBeNull();
        expect((int) SyncLog::latest('id')->firstOrFail()->processed_records)->toBe(0);
    } finally {
        StudentAcademicProfile::flushEventListeners();
    }
});

test('json66_failed_refresh_after_commit_does_not_misreport_row_as_rolled_back', function () {
    $this->mock(GraduationDataSyncService::class)->shouldReceive('syncExistingRegistrations')->once()->andThrow(new RuntimeException('Refresh failed'));
    ($this->import66)(academicJson66Row());
    expect(StudentAcademicProfile::count())->toBe(1);
    expect((int) SyncLog::latest('id')->firstOrFail()->processed_records)->toBe(1);
    expect((int) SyncLog::latest('id')->firstOrFail()->skipped_records)->toBe(0);
    expect(SyncLog::latest('id')->firstOrFail()->error_message)->toContain('data tersimpan');
});

test('json66_malformed_language_preserves_previous_language_while_valid_tak_changes', function () {
    ($this->import66)(academicJson66Row());
    ($this->import66)(academicJson66Row(['jumlah_tes_bahasa' => '3.5', 'total_tak' => '60']));
    expect(($this->profile)()->groups['language']['attempts'])->toBe(3);
    expect((float) ($this->profile)()->groups['tak']['score'])->toBe(60.0);
    expect(SyncLog::latest('id')->firstOrFail()->error_message)->toContain('Jumlah tes');
});

test('json66_invalid_context_is_rejected_before_sync_log_or_data_changes', function () {
    foreach ([['selected_menus' => null], ['selected_menus' => ['bahasa' => true]], ['finance_period' => '7']] as $context) {
        ($this->postPayload)(array_replace(['data' => [academicJson66Row()]], $context))->assertSessionHasErrors();
    }
    expect(StudentAcademicProfile::count())->toBe(0);
    expect(SyncLog::count())->toBe(0);
});

test('json66_empty_data_list_is_a_zero_row_import_not_a_fake_single_record', function () {
    ($this->postPayload)(['selected_menus' => ['tak'], 'data' => []])->assertSessionHasNoErrors()->assertSessionHas('success');
    expect((int) SyncLog::latest('id')->firstOrFail()->total_records)->toBe(0);
    expect(StudentAcademicProfile::count())->toBe(0);
});

test('json66_legacy_nested_fixture_import_remains_compatible_until_facts_integration', function () {
    $row = ['nim' => $this->student->student_number, 'nama' => $this->student->name,
        'tak' => ['total_points' => 60], 'skpi' => ['approval_status' => 'approved'],
        'eprt' => [['total_score' => 449, 'attempt_number' => 1, 'test_date' => '2026-10-01']]];
    $result = app(StudentAcademicProfileImportService::class)->import([$row]);
    expect($result['skipped'])->toBe(0);
    expect(StudentAcademicProfile::count())->toBe(0);
    expect(StudentEprt::where('student_number', $this->student->student_number)->count())->toBe(1);
});

test('json66_profile_is_removed_when_its_student_is_deleted', function () {
    ($this->import66)(academicJson66Row());
    $this->student->delete();
    expect(StudentAcademicProfile::count())->toBe(0);
});

test('json66_import_still_requires_sync_permission_on_both_routes', function () {
    $this->actingAs(User::factory()->create(['status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]));
    foreach (['sync.import-json', 'sync.import-academic-profile'] as $routeName) {
        ($this->postPayload)(['selected_menus' => $this->menus, 'data' => [academicJson66Row()]], $routeName)->assertForbidden();
    }
    expect(StudentAcademicProfile::count())->toBe(0);
});

test('json66_flat_partial_row_without_menu_metadata_preserves_missing_academic_fields', function () {
    ($this->import66)(academicJson66Row());
    $before = ($this->profile)()->groups;
    ($this->postPayload)(['data' => [['nim' => $this->student->student_number, 'nama' => $this->student->name,
        'total_tak' => '61']]])->assertSessionHasNoErrors()->assertSessionHas('success');
    $after = ($this->profile)()->groups;
    expect((float) $after['tak']['score'])->toBe(61.0);
    expect($after['academic'])->toEqual($before['academic']);
    expect($after['language'])->toEqual($before['language']);
    expect((int) $this->student->fresh()->total_credits)->toBe(130);
});

test('json66_academic_error_preserves_previous_academic_fields_while_other_groups_update', function () {
    ($this->import66)(academicJson66Row());
    ($this->import66)(academicJson66Row(['sks_lulus' => null, 'sidang_tingkat_2' => null,
        'status_skpi' => 'Belum Disetujui', 'error' => 'akademik: gagal mengambil data.']));
    expect((int) $this->student->fresh()->total_credits)->toBe(130);
    expect(($this->profile)()->groups['academic']['level_2_passed'])->toBeTrue();
    expect(($this->profile)()->groups['skpi']['approved'])->toBeFalse();
    expect(SyncLog::latest('id')->firstOrFail()->error_message)->toContain('menu akademik gagal');
});

test('json66_invalid_academic_numbers_do_not_partially_change_academic_tables', function () {
    ($this->import66)(academicJson66Row());
    ($this->import66)(academicJson66Row(['sks_lulus' => '0', 'ipk' => true, 'status_skpi' => 'Belum Disetujui']));
    expect((int) $this->student->fresh()->total_credits)->toBe(130);
    expect((int) StudentCourseIssue::where('student_number', $this->student->student_number)->value('credits_passed'))->toBe(130);
    expect(($this->profile)()->groups['skpi']['approved'])->toBeFalse();
    expect(SyncLog::latest('id')->firstOrFail()->error_message)->toContain('Field angka tidak valid');
});

test('json66_real_multiline_courses_and_day_month_year_slash_dates_are_preserved', function () {
    ($this->import66)(academicJson66Row(['mk_mengulang' => "MK01 - CONTOH SATU (D, Semester 3)\nMK02 - CONTOH DUA (E, Semester 4)",
        'mk_belum_lulus' => '-', 'tanggal_sidang' => '31/07/2026']));
    $issues = StudentCourseIssue::where('student_number', $this->student->student_number)->firstOrFail();
    expect($issues->retaking_courses)->toBe(['MK01 - CONTOH SATU (D, Semester 3)', 'MK02 - CONTOH DUA (E, Semester 4)']);
    expect($issues->failed_courses)->toBe([]);
    expect(StudentFinalProject::where('student_number', $this->student->student_number)->firstOrFail()->exam_date->format('Y-m-d'))->toBe('2026-07-31');
});

test('json66_igracias_only_import_preserves_basila_until_photo_menu_is_explicitly_imported', function () {
    $approval = StudentBasilaApproval::create(['student_number' => $this->student->student_number,
        'photo_approval' => true, 'status' => 'approved']);
    ($this->import66)(academicJson66Row());
    expect($approval->fresh()->photo_approval)->toBeTrue();
    expect($approval->fresh()->status)->toBe('approved');
    ($this->import66)(academicJson66Row(['approval_foto_dosen_wali_basila' => 'N']), ['basila_foto']);
    expect($approval->fresh()->photo_approval)->toBeFalse();
    expect($approval->fresh()->status)->toBe('pending');
});
