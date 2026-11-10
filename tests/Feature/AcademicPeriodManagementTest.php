<?php

use App\Models\{AcademicPeriod, User};
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->periodActor93=User::factory()->create(['status'=>'active','auth_source'=>'manual','must_change_password'=>false]);
    foreach (['student_read','student_update'] as $permission) $this->periodActor93->givePermissionTo(
        Permission::firstOrCreate(['name'=>$permission,'guard_name'=>'web']));
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($this->periodActor93);
    $this->periodData93=['year'=>'2026/2027','semester'=>1,'label'=>'Ganjil 2026/2027','start_date'=>'2026-08-01','end_date'=>'2027-01-31'];
});

test('period93_settings_are_reachable_from_krs_and_creation_never_activates', function () {
    $this->get(route('student_courses.index'))->assertOk()->assertSee(route('academic_periods.index'));
    $this->get(route('academic_periods.index'))->assertOk();
    $this->post(route('academic_periods.store'),$this->periodData93+['is_active'=>1])->assertRedirect();
    expect(AcademicPeriod::sole()->is_active)->toBeFalse();
});

test('period93_validates_real_dates_year_term_and_duplicate_identity', function () {
    foreach ([['year'=>'2026/2028'],['semester'=>9],['start_date'=>'2027-02-01'],['end_date'=>'invalid']] as $change) {
        $this->post(route('academic_periods.store'),array_replace($this->periodData93,$change))->assertSessionHasErrors();
    }
    expect(AcademicPeriod::count())->toBe(0);
    $this->post(route('academic_periods.store'),$this->periodData93)->assertRedirect();
    $this->post(route('academic_periods.store'),$this->periodData93)->assertSessionHasErrors();
    expect(AcademicPeriod::count())->toBe(1);
});

test('period93_activation_is_explicit_and_selects_exactly_one_without_deleting_history', function () {
    $this->post(route('academic_periods.store'),$this->periodData93)->assertRedirect();
    $first=AcademicPeriod::sole();
    $other=AcademicPeriod::create(['year'=>'2025/2026','semester'=>2,'label'=>'Riwayat','start_date'=>'2026-02-01','end_date'=>'2026-07-31','is_active'=>true]);
    $this->post(route('academic_periods.activate',$first))->assertRedirect();
    expect(AcademicPeriod::where('is_active',true)->count())->toBe(1)->and($first->fresh()->is_active)->toBeTrue()
        ->and($other->fresh()->is_active)->toBeFalse()->and(AcademicPeriod::count())->toBe(2);
});

test('period93_read_permission_does_not_allow_period_writes', function () {
    $this->periodActor93->revokePermissionTo('student_update');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->get(route('academic_periods.index'))->assertOk();
    $this->post(route('academic_periods.store'),$this->periodData93)->assertForbidden();
    expect(AcademicPeriod::count())->toBe(0);
});


test('period93_referenced_period_identity_cannot_be_rewritten_as_another_term', function () {
    $this->post(route('academic_periods.store'),$this->periodData93)->assertRedirect();
    $period=AcademicPeriod::sole();
    $student=\App\Models\Student::create(['student_number'=>'1293990001','name'=>'Uji Periode',
        'academic_year'=>2022,'active_semester'=>9,'study_program'=>'Informatika','status'=>'Aktif']);
    \App\Models\StudentCourse::create(['student_number'=>$student->student_number,'course_code'=>'IDENTITY93',
        'course_name'=>'Uji','course_class'=>'A','academic_year'=>$period->year,'semester'=>9,'academic_period_id'=>$period->id]);
    $this->put(route('academic_periods.update',$period),array_replace($this->periodData93,['semester'=>2]))->assertSessionHasErrors('year');
    expect($period->fresh()->semester)->toBe(1);
});
