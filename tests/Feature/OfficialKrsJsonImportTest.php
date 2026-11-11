<?php

use App\Models\AcademicPeriod;
use App\Models\Course;
use App\Models\Student;
use App\Models\StudentAcademicProfile;
use App\Models\StudentCourse;
use App\Models\SyncLog;
use App\Models\User;
use App\Services\OfficialKrsJsonImportService;
use App\Services\SidangTaAcademicSnapshot;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

function officialKrs70Course(array $overrides = []): array
{
    return array_replace(['kode_mk' => 'KRS70TA', 'nama_mk' => 'TUGAS AKHIR',
        'kelas' => 'KELAS-DEMO-PANJANG-12345', 'kode_dosen' => 'D70',
        'tahun_ajaran' => '2627', 'semester' => '1', 'program_studi' => 'Informatika'], $overrides);
}

function officialKrs70Row(array $courses = [], array $overrides = []): array
{
    $courses = $courses === [] ? [officialKrs70Course()] : $courses;
    return array_replace(['no' => 1, 'nim' => '1202270001', 'nama' => 'Mahasiswa KRS 70',
        'jumlah_mk_krs' => count($courses), 'krs_mata_kuliah' => $courses], $overrides);
}

beforeEach(function () {
    $this->user = User::factory()->create(['status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
    $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'sync_execute', 'guard_name' => 'web']));
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($this->user);
    $this->student = Student::create(['student_number' => '1202270001', 'name' => 'Mahasiswa KRS 70',
        'email' => 'krs70@example.test', 'academic_year' => 2022, 'active_semester' => 8,
        'study_program' => 'Informatika', 'status' => 'Aktif']);
    AcademicPeriod::query()->update(['is_active' => false]);
    $this->period = AcademicPeriod::create(['year' => '2026/2027', 'semester' => 1,
        'label' => 'KRS 70', 'start_date' => '2026-08-01', 'end_date' => '2027-01-31', 'is_active' => true]);
    $this->run70 = fn (array $rows) => app(OfficialKrsJsonImportService::class)->import($rows);
    $this->upload70 = function (array $payload) {
        return $this->from(route('sync.index'))->post(route('sync.import-json'), ['type' => 'krs',
            'json_file' => UploadedFile::fake()->createWithContent('krs70.json', json_encode($payload, JSON_THROW_ON_ERROR))]);
    };
    $this->enrollment70 = fn () => StudentCourse::where('student_number', '1202270001')->where('course_code', 'KRS70TA')->firstOrFail();
});

test('official_krs70_upload_reads_nested_courses_and_logs_students_and_course_counts', function () {
    $second = officialKrs70Course(['kode_mk' => 'KRS70OTHER', 'nama_mk' => 'MATA KULIAH DEMO']);
    ($this->upload70)(['selected_menus' => ['krs_mk'], 'total' => 1,
        'data' => [officialKrs70Row([officialKrs70Course(), $second])]])
        ->assertRedirect(route('sync.index'))->assertSessionHasNoErrors()->assertSessionHas('success');
    $log = SyncLog::where('module', 'krs')->latest('id')->firstOrFail();
    expect($log->status)->toBe('completed');
    expect((int) $log->total_records)->toBe(1);
    expect((int) $log->processed_records)->toBe(1);
    expect((int) $log->skipped_records)->toBe(0);
    expect(StudentCourse::where('student_number', '1202270001')->count())->toBe(2);
});

test('official_krs70_preserves_study_semester_and_long_class_without_inventing_master_data', function () {
    expect(($this->run70)([officialKrs70Row()])['processed'])->toBe(1);
    $krs = ($this->enrollment70)();
    expect((int) $krs->academic_period_id)->toBe($this->period->id);
    expect((int) $krs->semester)->toBe(8);
    expect($krs->academic_year)->toBe('2026/2027');
    expect($krs->course_class)->toBe('KELAS-DEMO-PANJANG-12345');
    expect($krs->credits)->toBeNull();
    expect(Course::where('course_code', 'KRS70TA')->exists())->toBeFalse();
    expect((int) $this->student->fresh()->active_semester)->toBe(8);
    expect($this->student->fresh()->email)->toBe('krs70@example.test');
});

test('official_krs70_reimport_updates_the_same_record_after_the_students_study_semester_changes', function () {
    ($this->run70)([officialKrs70Row()]);
    $id = ($this->enrollment70)()->id;
    $this->student->update(['active_semester' => 9]);
    ($this->run70)([officialKrs70Row([officialKrs70Course(['kelas' => 'NEW70'])])]);
    expect(StudentCourse::where('student_number', '1202270001')->count())->toBe(1);
    expect(($this->enrollment70)()->id)->toBe($id);
    expect(($this->enrollment70)()->course_class)->toBe('NEW70');
    expect((int) ($this->enrollment70)()->semester)->toBe(8);
});

test('official_krs70_skips_unknown_students_without_creating_accounts_or_student_rows', function () {
    $result = ($this->run70)([officialKrs70Row(overrides: ['nim' => '1202270999'])]);
    expect($result['skipped'])->toBe(1);
    expect(Student::where('student_number', '1202270999')->exists())->toBeFalse();
    expect(StudentCourse::count())->toBe(0);
});

test('official_krs70_rejects_name_mismatch_without_overwriting_student_identity', function () {
    $result = ($this->run70)([officialKrs70Row(overrides: ['nama' => 'Nama Berbeda'])]);
    expect($result['mismatched'])->toBe(1);
    expect(StudentCourse::count())->toBe(0);
    expect($this->student->fresh()->name)->toBe('Mahasiswa KRS 70');
});

test('official_krs70_accepts_name_case_and_surrounding_whitespace', function () {
    $result = ($this->run70)([officialKrs70Row(overrides: ['nama' => ' MAHASISWA KRS 70 '])]);
    expect($result['processed'])->toBe(1);
});

test('official_krs70_missing_period_skips_the_student_without_guessing_or_creating_a_period', function () {
    $this->period->delete();
    $result = ($this->run70)([officialKrs70Row()]);
    expect($result['skipped'])->toBe(1);
    expect(StudentCourse::count())->toBe(0);
    expect(AcademicPeriod::count())->toBe(0);
});

test('official_krs70_two_active_periods_do_not_change_the_explicit_import_period', function () {
    AcademicPeriod::create(['year' => '2026/2027', 'semester' => 2, 'label' => 'Other active 70',
        'start_date' => '2027-02-01', 'end_date' => '2027-07-31', 'is_active' => true]);
    expect(($this->run70)([officialKrs70Row()])['processed'])->toBe(1);
    expect((int) ($this->enrollment70)()->academic_period_id)->toBe($this->period->id);
    expect(app(SidangTaAcademicSnapshot::class)->ta2KrsStatus($this->student))->toBe('unknown');
});

test('official_krs70_uses_the_explicit_term_even_when_another_term_is_active', function () {
    $this->period->update(['is_active' => false]);
    AcademicPeriod::create(['year' => '2026/2027', 'semester' => 2, 'label' => 'Even 70',
        'start_date' => '2027-02-01', 'end_date' => '2027-07-31', 'is_active' => true]);
    expect(($this->run70)([officialKrs70Row()])['processed'])->toBe(1);
    expect((int) ($this->enrollment70)()->academic_period_id)->toBe($this->period->id);
    expect(app(SidangTaAcademicSnapshot::class)->ta2KrsStatus($this->student))->toBe('unknown');
});

test('official_krs70_malformed_second_course_does_not_partially_import_the_first', function () {
    $bad = officialKrs70Course(['kode_mk' => 'BAD70', 'semester' => '8']);
    expect(($this->run70)([officialKrs70Row([officialKrs70Course(), $bad])])['skipped'])->toBe(1);
    expect(StudentCourse::count())->toBe(0);
});

test('official_krs70_missing_period_later_in_the_row_leaves_no_partial_import', function () {
    $other = officialKrs70Course(['kode_mk' => 'LATER70', 'tahun_ajaran' => '2728']);
    expect(($this->run70)([officialKrs70Row([officialKrs70Course(), $other])])['skipped'])->toBe(1);
    expect(StudentCourse::count())->toBe(0);
});

test('official_krs70_a_failed_student_does_not_prevent_another_valid_student_from_importing', function () {
    $other = Student::create(['student_number' => '1202270002', 'name' => 'Mahasiswa Kedua KRS 70',
        'email' => 'second-krs70@example.test', 'academic_year' => 2022, 'active_semester' => 8,
        'study_program' => 'Informatika', 'status' => 'Aktif']);
    $rows = [officialKrs70Row(overrides: ['nama' => 'Mismatch']),
        officialKrs70Row(overrides: ['nim' => $other->student_number, 'nama' => $other->name])];
    $result = ($this->run70)($rows);
    expect($result['processed'])->toBe(1);
    expect($result['mismatched'])->toBe(1);
    expect(StudentCourse::where('student_number', $other->student_number)->count())->toBe(1);
    expect(StudentCourse::where('student_number', '1202270001')->count())->toBe(0);
});

test('official_krs70_credits_use_only_an_existing_master_for_the_same_year_and_term', function () {
    Course::create(['course_code' => 'KRS70TA', 'name' => 'Master KRS 70', 'study_program' => 'Informatika',
        'class' => 'M70', 'semester' => 8, 'credits' => 4, 'academic_year' => '2026/2027', 'semester_term' => 'GANJIL']);
    ($this->run70)([officialKrs70Row()]);
    expect((int) ($this->enrollment70)()->credits)->toBe(4);
    ($this->enrollment70)()->update(['credits' => 6]);
    ($this->run70)([officialKrs70Row()]);
    expect((int) ($this->enrollment70)()->credits)->toBe(6);
});

test('official_krs70_never_borrows_master_credits_from_another_period', function () {
    Course::create(['course_code' => 'KRS70TA', 'name' => 'Master Old KRS 70', 'study_program' => 'Informatika',
        'class' => 'M70', 'semester' => 8, 'credits' => 4, 'academic_year' => '2025/2026', 'semester_term' => 'GENAP']);
    ($this->run70)([officialKrs70Row()]);
    expect(($this->enrollment70)()->credits)->toBeNull();
});

test('official_krs70_empty_lists_and_source_errors_preserve_existing_enrollments', function () {
    ($this->run70)([officialKrs70Row()]);
    $id = ($this->enrollment70)()->id;
    foreach ([['jumlah_mk_krs' => 0, 'krs_mata_kuliah' => []], ['error' => 'krs_mk: gagal']] as $patch) {
        expect(($this->run70)([officialKrs70Row(overrides: $patch)])['skipped'])->toBe(1);
        expect(($this->enrollment70)()->id)->toBe($id);
    }
});

test('official_krs70_invalid_study_semester_does_not_use_term_one_as_a_replacement', function () {
    $this->student->update(['active_semester' => 0]);
    expect(($this->run70)([officialKrs70Row()])['skipped'])->toBe(1);
    expect(StudentCourse::count())->toBe(0);
});

test('official_krs98_historical_linked_krs_can_still_be_updated_without_the_retired_importer', function () {
    $old = StudentCourse::create(['student_number' => '1202270001', 'course_code' => 'KRS70TA',
        'course_name' => 'TUGAS AKHIR', 'course_class' => 'OLD70', 'credits' => 4,
        'academic_year' => '2026/2027', 'academic_period_id' => $this->period->id, 'semester' => 8]);
    ($this->run70)([officialKrs70Row()]);
    expect(StudentCourse::where('student_number', '1202270001')->count())->toBe(1);
    expect(($this->enrollment70)()->id)->toBe($old->id);
    expect((int) ($this->enrollment70)()->semester)->toBe(8);
});

test('official_krs70_rejects_wrapper_count_mismatch_before_creating_a_sync_log', function () {
    ($this->upload70)(['selected_menus' => ['krs_mk'], 'total' => 2, 'data' => [officialKrs70Row()]])
        ->assertSessionHasErrors('json_file');
    expect(SyncLog::where('module', 'krs')->count())->toBe(0);
    expect(StudentCourse::count())->toBe(0);
});

test('official_krs70_duplicate_student_rows_are_rejected_before_writing', function () {
    ($this->upload70)(['data' => [officialKrs70Row(), officialKrs70Row()]])
        ->assertSessionHasErrors('json_file');
    expect(StudentCourse::count())->toBe(0);
});

test('official_krs70_failed_rows_are_visible_in_sync_log_counts_and_notes', function () {
    ($this->upload70)(['data' => [officialKrs70Row(overrides: ['nama' => 'Mismatch'])]])
        ->assertSessionHas('success');
    $log = SyncLog::where('module', 'krs')->latest('id')->firstOrFail();
    expect((int) $log->processed_records)->toBe(0);
    expect((int) $log->skipped_records)->toBe(1);
    expect($log->error_message)->not->toBeNull();
});

test('official_krs70_requires_the_existing_import_permission', function () {
    $this->user->revokePermissionTo('sync_execute');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    ($this->upload70)(['data' => [officialKrs70Row()]])->assertForbidden();
    expect(StudentCourse::count())->toBe(0);
});

test('official_krs70_accepts_whole_ta_names_and_keeps_proposals_and_ta_one_ineligible', function () {
    foreach (['TUGAS AKHIR' => 'eligible', 'TUGAS AKHIR 2' => 'eligible',
                 'TUGAS AKHIR 1' => 'unconfirmed', 'PROPOSAL TUGAS AKHIR' => 'unconfirmed'] as $name => $expected) {
        ($this->run70)([officialKrs70Row([officialKrs70Course(['nama_mk' => $name])])]);
        expect(app(SidangTaAcademicSnapshot::class)->ta2KrsStatus($this->student))->toBe($expected);
    }
});

test('official_krs70_does_not_modify_the_official_academic_profile', function () {
    $profile = StudentAcademicProfile::create(['student_number' => '1202270001',
        'groups' => ['bpp' => ['status' => 'LUNAS', 'period' => null], 'skpi' => ['status' => 'Disetujui']]]);
    $before = $profile->fresh()->groups;
    ($this->run70)([officialKrs70Row()]);
    expect($profile->fresh()->groups)->toBe($before);
});

test('official_krs70_unlinked_legacy_rows_need_period_evidence_even_after_study_semester_changes', function () {
    ($this->run70)([officialKrs70Row()]);
    $old = ($this->enrollment70)();
    $old->update(['academic_period_id' => null]);
    $this->student->update(['active_semester' => 9]);
    expect(($this->run70)([officialKrs70Row()])['skipped'])->toBe(1);
    expect(StudentCourse::where('student_number', '1202270001')->count())->toBe(1);
    expect(($this->enrollment70)()->id)->toBe($old->id);
    expect((int) ($this->enrollment70)()->semester)->toBe(8);
    expect(($this->enrollment70)()->academic_period_id)->toBeNull();
});

test('official_krs70_conflicting_legacy_key_does_not_steal_another_periods_enrollment', function () {
    ($this->run70)([officialKrs70Row()]);
    $other = AcademicPeriod::create(['year' => '2026/2027', 'semester' => 2, 'label' => 'Conflict 70',
        'start_date' => '2027-02-01', 'end_date' => '2027-07-31', 'is_active' => false]);
    ($this->enrollment70)()->update(['academic_period_id' => $other->id]);
    expect(($this->run70)([officialKrs70Row()])['skipped'])->toBe(1);
    expect((int) ($this->enrollment70)()->academic_period_id)->toBe($other->id);
});

test('official_krs70_a_late_write_failure_rolls_back_the_students_entire_row', function () {
    $dispatcher = clone StudentCourse::getEventDispatcher();
    try {
        StudentCourse::saving(function ($row) {
            if ($row->course_code === 'ROLLBACK70') throw new RuntimeException('Simulasi gagal simpan kedua.');
        });
        $result = ($this->run70)([officialKrs70Row([officialKrs70Course(), officialKrs70Course(['kode_mk' => 'ROLLBACK70'])])]);
        expect($result['skipped'])->toBe(1);
        expect(StudentCourse::where('student_number', '1202270001')->count())->toBe(0);
    } finally {
        StudentCourse::setEventDispatcher($dispatcher);
    }
});

test('official_krs70_replaces_the_same_period_and_a_withdrawn_ta_course_stops_qualifying', function () {
    $other = officialKrs70Course(['kode_mk' => 'KEEP70', 'nama_mk' => 'MATA KULIAH LAMA']);
    ($this->run70)([officialKrs70Row([officialKrs70Course(), $other])]);
    expect(app(SidangTaAcademicSnapshot::class)->ta2KrsStatus($this->student))->toBe('eligible');
    $result = ($this->run70)([officialKrs70Row([$other])]);
    expect($result['removed'])->toBe(1);
    expect(StudentCourse::where('student_number', '1202270001')->count())->toBe(1);
    expect(StudentCourse::where('student_number', '1202270001')->where('course_code', 'KEEP70')->exists())->toBeTrue();
    expect(StudentCourse::where('student_number', '1202270001')->where('course_code', 'KRS70TA')->exists())->toBeFalse();
    expect(app(SidangTaAcademicSnapshot::class)->ta2KrsStatus($this->student))->toBe('unconfirmed');
});

test('official_krs70_changing_lecturer_code_does_not_keep_the_previous_lecturers_name', function () {
    ($this->run70)([officialKrs70Row()]);
    ($this->enrollment70)()->update(['lecturer_name' => 'Dosen Lama Sintetis']);
    ($this->run70)([officialKrs70Row()]);
    expect(($this->enrollment70)()->lecturer_name)->toBe('Dosen Lama Sintetis');
    ($this->run70)([officialKrs70Row([officialKrs70Course(['kode_dosen' => 'NEW70'])])]);
    expect(($this->enrollment70)()->lecturer_code)->toBe('NEW70');
    expect(($this->enrollment70)()->lecturer_name)->toBeNull();
});

test('official_krs70_ambiguous_unlinked_legacy_rows_are_not_guessed', function () {
    ($this->run70)([officialKrs70Row()]);
    $old = ($this->enrollment70)();
    $old->update(['academic_period_id' => null]);
    $copy = $old->replicate();
    $copy->semester = 7;
    $copy->save();
    expect(($this->run70)([officialKrs70Row()])['skipped'])->toBe(1);
    expect(StudentCourse::where('student_number', '1202270001')->whereNotNull('academic_period_id')->count())->toBe(0);
});

test('official_krs70_migration_rollback_refuses_to_truncate_long_class_names', function () {
    ($this->run70)([officialKrs70Row()]);
    $files = glob(database_path('migrations/*_expand_student_course_class_for_official_krs.php'));
    expect($files)->toHaveCount(1);
    $migration = require $files[0];
    expect(fn () => $migration->down())->toThrow(RuntimeException::class, 'Rollback ditolak');
    expect(($this->enrollment70)()->course_class)->toBe('KELAS-DEMO-PANJANG-12345');
});

test('official_krs70_replacement_preserves_other_students_and_historical_periods', function () {
    ($this->run70)([officialKrs70Row()]);
    $history = AcademicPeriod::create(['year' => '2025/2026', 'semester' => 1, 'label' => 'History 70',
        'start_date' => '2025-08-01', 'end_date' => '2026-01-31', 'is_active' => false]);
    $other = Student::create(['student_number' => '1202270002', 'name' => 'Mahasiswa Kedua KRS 70',
        'email' => 'other-krs70@example.test', 'academic_year' => 2022, 'active_semester' => 8,
        'study_program' => 'Informatika', 'status' => 'Aktif']);
    foreach ([['1202270001', $history->id, '2025/2026', 6], [$other->student_number, $this->period->id, '2026/2027', 8]] as [$nim, $period, $year, $semester]) {
        StudentCourse::create(['student_number' => $nim, 'course_code' => 'HISTORY70', 'course_name' => 'RIWAYAT',
            'course_class' => 'H70', 'academic_year' => $year, 'semester' => $semester, 'academic_period_id' => $period]);
    }
    ($this->run70)([officialKrs70Row([officialKrs70Course(['kode_mk' => 'NEW70', 'nama_mk' => 'MATA KULIAH BARU'])])]);
    expect(StudentCourse::where('course_code', 'HISTORY70')->count())->toBe(2);
    expect(StudentCourse::where('student_number', '1202270001')->where('academic_period_id', $this->period->id)->pluck('course_code')->all())->toBe(['NEW70']);
});

test('official_krs70_invalid_replacement_preserves_the_previous_valid_ta_enrollment', function () {
    ($this->run70)([officialKrs70Row()]);
    $id = ($this->enrollment70)()->id;
    $result = ($this->run70)([officialKrs70Row([officialKrs70Course(['kode_mk' => 'NEW70', 'semester' => '8'])])]);
    expect($result['skipped'])->toBe(1);
    expect($result['removed'])->toBe(0);
    expect(($this->enrollment70)()->id)->toBe($id);
    expect(app(SidangTaAcademicSnapshot::class)->ta2KrsStatus($this->student))->toBe('eligible');
});

test('official_krs70_unlinked_odd_term_history_is_not_adopted_by_an_even_term_retake', function () {
    ($this->run70)([officialKrs70Row()]);
    ($this->enrollment70)()->update(['academic_period_id' => null, 'semester' => 7]);
    $even = AcademicPeriod::create(['year' => '2026/2027', 'semester' => 2, 'label' => 'Retake 70',
        'start_date' => '2027-02-01', 'end_date' => '2027-07-31', 'is_active' => false]);
    $result = ($this->run70)([officialKrs70Row([officialKrs70Course(['semester' => '2'])])]);
    expect($result['skipped'])->toBe(1);
    expect((int) ($this->enrollment70)()->semester)->toBe(7);
    expect(($this->enrollment70)()->academic_period_id)->toBeNull();
    expect(StudentCourse::where('academic_period_id', $even->id)->count())->toBe(0);
});

test('official_krs70_failure_during_second_period_pruning_restores_all_deletions_and_updates', function () {
    ($this->run70)([officialKrs70Row()]);
    $originalClass = ($this->enrollment70)()->course_class;
    $drop = ($this->enrollment70)()->replicate();
    $drop->course_code = 'DROP70';
    $drop->save();
    $history = AcademicPeriod::create(['year' => '2025/2026', 'semester' => 1, 'label' => 'Rollback history 70',
        'start_date' => '2025-08-01', 'end_date' => '2026-01-31', 'is_active' => false]);
    StudentCourse::create(['student_number' => '1202270001', 'course_code' => 'HISTORY70', 'course_name' => 'RIWAYAT',
        'course_class' => 'H70', 'academic_year' => '2025/2026', 'semester' => 6, 'academic_period_id' => $history->id]);
    $connection = DB::connection();
    $dispatcher = $connection->getEventDispatcher();
    $connection->setEventDispatcher(clone $dispatcher);
    $deletes = 0;
    try {
        $connection->listen(function ($query) use (&$deletes) {
            if (str_starts_with(strtolower(ltrim($query->sql)), 'delete')
                && str_contains($query->sql, 'student_courses') && ++$deletes === 2) {
                throw new RuntimeException('Simulasi gagal setelah pemangkasan periode kedua.');
            }
        });
        $result = ($this->run70)([officialKrs70Row([
            officialKrs70Course(['kelas' => 'CHANGED70']),
            officialKrs70Course(['kode_mk' => 'NEW70B', 'tahun_ajaran' => '2526']),
        ])]);
        expect($deletes)->toBe(2);
        expect($result['skipped'])->toBe(1);
        expect($result['removed'])->toBe(0);
        expect(($this->enrollment70)()->course_class)->toBe($originalClass);
        expect(StudentCourse::where('student_number', '1202270001')->orderBy('course_code')->pluck('course_code')->all())
            ->toBe(['DROP70', 'HISTORY70', 'KRS70TA']);
    } finally {
        $connection->setEventDispatcher($dispatcher);
    }
});
