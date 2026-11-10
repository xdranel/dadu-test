<?php

use App\Models\{AcademicPeriod, Course, Student, StudentCourse, User};
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $actor = User::factory()->create(['status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
    foreach (['course_read', 'course_update', 'course_delete'] as $permission) {
        $actor->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($actor);
    $this->period94 = AcademicPeriod::create(['year' => '2026/2027', 'semester' => 1,
        'label' => 'Ganjil 2026/2027', 'start_date' => '2026-08-01', 'end_date' => '2027-01-31', 'is_active' => true]);
    $student = Student::create(['student_number' => '1294000094', 'name' => 'Mahasiswa Uji Master KRS',
        'academic_year' => 2022, 'active_semester' => 9, 'study_program' => 'Informatika', 'status' => 'Aktif']);
    StudentCourse::create(['student_number' => $student->student_number, 'course_code' => 'NOMASTER94',
        'course_name' => 'Mata Kuliah dari KRS', 'course_class' => 'A', 'academic_year' => '2026/2027',
        'academic_period_id' => $this->period94->id, 'semester' => 9, 'credits' => null]);
});

test('course94 listing stays available for KRS without a course master and does not invent one', function () {
    $this->get(route('courses.index'))->assertOk()->assertSee('NOMASTER94')
        ->assertSee('Master mata kuliah belum tersedia');
    expect(Course::count())->toBe(0);
    expect(StudentCourse::sole()->course_code)->toBe('NOMASTER94');
});

test('course94 missing master does not prevent valid master actions on the same page', function () {
    $course = Course::create(['course_code' => 'MASTER94', 'name' => 'Master Mata Kuliah Uji',
        'study_program' => 'Informatika', 'class' => 'B', 'semester' => 9, 'credits' => 3]);
    $this->get(route('courses.index'))->assertOk()->assertSee('NOMASTER94')->assertSee('MASTER94')
        ->assertSee(route('courses.show', $course))->assertSee(route('courses.edit', $course))
        ->assertSee(route('courses.destroy', $course));
    expect(Course::count())->toBe(1);
});
