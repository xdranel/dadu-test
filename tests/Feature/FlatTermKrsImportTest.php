<?php

use App\Models\{AcademicPeriod, Student, StudentCourse, SyncLog, User};
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

function flatTerm93Row(array $changes = []): array
{
    return array_replace(['nim'=>'1293000001','kode_matakuliah'=>'FLAT93','nama_matakuliah'=>'Mata Kuliah Uji',
        'kelas_matakuliah'=>'KELAS93','kode_dosen'=>'D93','nama_dosen'=>'Dosen Uji','sks'=>null,
        'tahun_akademik'=>'2026/2027','semester'=>1], $changes);
}

beforeEach(function () {
    $this->actor93=User::factory()->create(['status'=>'active','auth_source'=>'manual','must_change_password'=>false]);
    foreach (['sync_execute','student_read','student_update'] as $permission) {
        $this->actor93->givePermissionTo(Permission::firstOrCreate(['name'=>$permission,'guard_name'=>'web']));
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($this->actor93);
    AcademicPeriod::query()->update(['is_active'=>false]);
    $this->period93=AcademicPeriod::create(['year'=>'2026/2027','semester'=>1,'label'=>'Ganjil 2026/2027',
        'start_date'=>'2026-08-01','end_date'=>'2027-01-31','is_active'=>false]);
    $this->student93=Student::create(['student_number'=>'1293000001','name'=>'Mahasiswa Uji',
        'academic_year'=>2022,'active_semester'=>9,'study_program'=>'Informatika','status'=>'Aktif']);
    $this->upload93=function(array $rows, string $mode='term') {
        return $this->post(route('sync.import-json'),['type'=>'krs','krs_semester_mode'=>$mode,
            'json_file'=>UploadedFile::fake()->createWithContent('flat93.json',json_encode($rows,JSON_THROW_ON_ERROR))]);
    };
    $this->apply93=function() {
        return $this->post(route('sync.krs-term-apply'),['preview_token'=>session('krs_term_preview.token'),'confirmed'=>1]);
    };
});

test('flat93_term_upload_previews_without_writing_and_apply_uses_student_study_semester', function () {
    ($this->upload93)([flatTerm93Row()])->assertOk()->assertSee('Pratinjau KRS')->assertSee('Ganjil 2026/2027');
    expect(StudentCourse::count())->toBe(0);
    ($this->apply93)()->assertRedirect(route('sync.index'))->assertSessionHas('success');
    $row=StudentCourse::sole();
    expect((int)$row->semester)->toBe(9)->and((int)$row->academic_period_id)->toBe($this->period93->id)
        ->and($row->credits)->toBeNull()->and($row->lecturer_name)->toBe('Dosen Uji');
    expect($this->period93->fresh()->is_active)->toBeFalse();
    expect((int)$this->student93->fresh()->active_semester)->toBe(9);
    expect(SyncLog::latest('id')->first()->issue_details)->toBe([]);
});

test('flat93_unique_unlinked_wrong_term_is_repaired_in_place_only_after_review', function () {
    $old=StudentCourse::create(['student_number'=>$this->student93->student_number,'course_code'=>'FLAT93',
        'course_name'=>'Mata Kuliah Uji','course_class'=>'KELAS93','academic_year'=>'2026/2027','semester'=>1]);
    ($this->upload93)([flatTerm93Row()])->assertOk()->assertSee('Perbaiki tautan');
    expect($old->fresh()->academic_period_id)->toBeNull();
    ($this->apply93)()->assertRedirect();
    expect(StudentCourse::count())->toBe(1)->and((int)$old->fresh()->semester)->toBe(9)
        ->and((int)$old->fresh()->academic_period_id)->toBe($this->period93->id);
    ($this->upload93)([flatTerm93Row()])->assertOk();
    ($this->apply93)()->assertRedirect();
    expect(StudentCourse::sole()->id)->toBe($old->id);
});

test('flat93_ambiguous_unlinked_or_conflicting_period_does_not_change_history', function () {
    foreach ([1,3] as $semester) StudentCourse::create(['student_number'=>$this->student93->student_number,
        'course_code'=>'FLAT93','course_name'=>'Mata Kuliah Uji','course_class'=>'KELAS93',
        'academic_year'=>'2026/2027','semester'=>$semester]);
    ($this->upload93)([flatTerm93Row()])->assertOk()->assertSee('ambigu');
    ($this->apply93)()->assertRedirect();
    expect(StudentCourse::whereNotNull('academic_period_id')->count())->toBe(0);
    expect(SyncLog::latest('id')->first()->skipped_records)->toBe(1);
});

test('flat93_missing_period_student_invalid_term_and_duplicate_have_specific_source_issues', function () {
    $rows=[flatTerm93Row(['semester'=>2]),flatTerm93Row(['nim'=>'1293999999']),flatTerm93Row(['semester'=>9]),
        flatTerm93Row(['kode_matakuliah'=>'DUP93']),flatTerm93Row(['kode_matakuliah'=>'DUP93'])];
    ($this->upload93)($rows)->assertOk()->assertSee('belum terdaftar')->assertSee('Term')->assertSee('berulang');
    ($this->apply93)()->assertRedirect();
    expect(StudentCourse::count())->toBe(0)->and(AcademicPeriod::count())->toBe(1);
    $log=SyncLog::latest('id')->first();
    expect((int)$log->skipped_records)->toBe(5)->and($log->issue_details)->toHaveCount(5);
    expect($log->issue_details[0]['record'])->toBe(1)->and($log->issue_details[0]['path'])->toBe('$[0]');
});

test('flat93_preview_detects_changed_student_before_any_apply', function () {
    ($this->upload93)([flatTerm93Row()])->assertOk();
    $this->student93->update(['active_semester'=>10]);
    ($this->apply93)()->assertRedirect()->assertSessionHasErrors('preview_token');
    expect(StudentCourse::count())->toBe(0);
});

test('flat93_apply_requires_confirmation_and_matching_preview_token', function () {
    ($this->upload93)([flatTerm93Row()])->assertOk();
    $this->post(route('sync.krs-term-apply'),['preview_token'=>'wrong','confirmed'=>1])->assertSessionHasErrors('preview_token');
    $this->post(route('sync.krs-term-apply'),['preview_token'=>session('krs_term_preview.token')])->assertSessionHasErrors('confirmed');
    expect(StudentCourse::count())->toBe(0);
});

test('flat93_additive_import_preserves_other_periods_and_absent_courses', function () {
    $even=AcademicPeriod::create(['year'=>'2026/2027','semester'=>2,'label'=>'Genap 2026/2027',
        'start_date'=>'2027-02-01','end_date'=>'2027-07-31','is_active'=>false]);
    $other=StudentCourse::create(['student_number'=>$this->student93->student_number,'course_code'=>'OTHER93',
        'course_name'=>'Riwayat Uji','course_class'=>'A','academic_year'=>'2026/2027','semester'=>8,'academic_period_id'=>$even->id]);
    $absent=StudentCourse::create(['student_number'=>$this->student93->student_number,'course_code'=>'ABSENT93',
        'course_name'=>'Tidak Diunggah','course_class'=>'A','academic_year'=>'2026/2027','semester'=>9,'academic_period_id'=>$this->period93->id]);
    ($this->upload93)([flatTerm93Row()])->assertOk();
    ($this->apply93)()->assertRedirect();
    expect(StudentCourse::count())->toBe(3)->and($other->fresh()->academic_period_id)->toBe($even->id)
        ->and($absent->fresh()->academic_period_id)->toBe($this->period93->id);
});

test('flat98_legacy_study_mode_is_rejected_without_writes', function () {
    $this->period93->update(['is_active'=>true]);
    ($this->upload93)([flatTerm93Row(['semester'=>4,'sks'=>3])],'study')
        ->assertRedirect()->assertSessionHasErrors('krs_semester_mode');
    expect(StudentCourse::count())->toBe(0)->and(\App\Models\Course::count())->toBe(0);
});

test('flat93_rosters_and_group_counts_separate_academic_period_identity', function () {
    $even=AcademicPeriod::create(['year'=>'2026/2027','semester'=>2,'label'=>'Genap 2026/2027',
        'start_date'=>'2027-02-01','end_date'=>'2027-07-31','is_active'=>false]);
    $a=StudentCourse::create(['student_number'=>$this->student93->student_number,'course_code'=>'FLAT93',
        'course_name'=>'Mata Kuliah Uji','course_class'=>'KELAS93','academic_year'=>'2026/2027','semester'=>9,'academic_period_id'=>$this->period93->id]);
    $other=Student::create(['student_number'=>'1293000002','name'=>'Mahasiswa Periode Lain','academic_year'=>2022,
        'active_semester'=>9,'study_program'=>'Informatika','status'=>'Aktif']);
    StudentCourse::create(['student_number'=>$other->student_number,'course_code'=>'FLAT93','course_name'=>'Mata Kuliah Uji',
        'course_class'=>'KELAS93','academic_year'=>'2026/2027','semester'=>9,'academic_period_id'=>$even->id]);
    $this->get(route('student_courses.index'))->assertOk()->assertViewHas('krs',fn($krs)=>$krs->total()===2);
    $this->get(route('student_courses.show',$a))->assertOk()->assertViewHas('students',fn($students)=>$students->count()===1);
});


test('flat93_conflicting_explicit_period_id_is_reported_without_writing', function () {
    $other=AcademicPeriod::create(['year'=>'2025/2026','semester'=>1,'label'=>'Riwayat',
        'start_date'=>'2025-08-01','end_date'=>'2026-01-31','is_active'=>false]);
    ($this->upload93)([flatTerm93Row(['academic_period_id'=>$other->id])])->assertOk()->assertSee('ID periode');
    ($this->apply93)()->assertRedirect();
    expect(StudentCourse::count())->toBe(0);
});

test('flat93_mixed_integer_and_string_term_duplicates_do_not_collide_on_apply', function () {
    ($this->upload93)([flatTerm93Row(),flatTerm93Row(['semester'=>'1'])])->assertOk()->assertSee('berulang');
    ($this->apply93)()->assertRedirect();
    expect(StudentCourse::count())->toBe(0)->and((int)SyncLog::latest('id')->first()->skipped_records)->toBe(2);
});

test('flat93_expired_or_different_user_preview_never_applies', function () {
    ($this->upload93)([flatTerm93Row()])->assertOk();
    $preview=session('krs_term_preview');
    $preview['expires_at']=now()->subSecond()->timestamp;
    session(['krs_term_preview'=>$preview]);
    ($this->apply93)()->assertSessionHasErrors('preview_token');
    ($this->upload93)([flatTerm93Row()])->assertOk();
    $preview=session('krs_term_preview');
    $preview['user_id']=$this->actor93->id+1000;
    session(['krs_term_preview'=>$preview]);
    ($this->apply93)()->assertSessionHasErrors('preview_token');
    expect(StudentCourse::count())->toBe(0);
});

test('flat93_retakes_with_same_legacy_unique_key_preserve_other_period', function () {
    $even=AcademicPeriod::create(['year'=>'2026/2027','semester'=>2,'label'=>'Genap 2026/2027',
        'start_date'=>'2027-02-01','end_date'=>'2027-07-31','is_active'=>false]);
    $old=StudentCourse::create(['student_number'=>$this->student93->student_number,'course_code'=>'FLAT93',
        'course_name'=>'Mata Kuliah Uji','course_class'=>'KELAS93','academic_year'=>'2026/2027','semester'=>9,'academic_period_id'=>$even->id]);
    ($this->upload93)([flatTerm93Row()])->assertOk()->assertSee('bentrok');
    ($this->apply93)()->assertRedirect();
    expect(StudentCourse::sole()->id)->toBe($old->id)->and($old->fresh()->academic_period_id)->toBe($even->id);
});


test('flat93_same_batch_cross_term_legacy_key_conflicts_skip_both_instead_of_rolling_back_valid_rows', function () {
    AcademicPeriod::create(['year'=>'2026/2027','semester'=>2,'label'=>'Genap 2026/2027',
        'start_date'=>'2027-02-01','end_date'=>'2027-07-31','is_active'=>false]);
    ($this->upload93)([flatTerm93Row(),flatTerm93Row(['semester'=>2]),flatTerm93Row(['kode_matakuliah'=>'VALID93'])])
        ->assertOk()->assertSee('bentrok');
    ($this->apply93)()->assertRedirect()->assertSessionHas('success');
    expect(StudentCourse::count())->toBe(1)->and(StudentCourse::sole()->course_code)->toBe('VALID93')
        ->and((int)SyncLog::latest('id')->first()->skipped_records)->toBe(2);
});


test('flat93_malformed_identity_is_skipped_without_crashing_preview_or_valid_rows', function () {
    ($this->upload93)([flatTerm93Row(['nim'=>['bad']]),flatTerm93Row()])->assertOk()->assertSee('Pratinjau KRS');
    ($this->apply93)()->assertRedirect();
    expect(StudentCourse::count())->toBe(1)->and(SyncLog::latest('id')->first()->skipped_records)->toBe(1);
});

test('flat93_collation_equivalent_codes_are_conflicts_instead_of_overwrites', function () {
    $rows=[flatTerm93Row(['kode_matakuliah'=>'CASE93']),flatTerm93Row(['kode_matakuliah'=>'case93']),flatTerm93Row(['kode_matakuliah'=>'VALID93'])];
    ($this->upload93)($rows)->assertOk()->assertSee('berulang');($this->apply93)()->assertRedirect();
    expect(StudentCourse::pluck('course_code')->all())->toBe(['VALID93']);
    expect(SyncLog::latest('id')->first()->skipped_records)->toBe(2);
});
