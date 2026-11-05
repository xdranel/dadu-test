<?php

use App\Models\AcademicPeriod;
use App\Models\Course;
use App\Models\Student;
use App\Models\StudentCourse;
use App\Models\StudentCourseIssue;
use App\Models\StudentEprt;
use App\Models\StudentSidangTingkat;
use App\Models\StudentSkpi;
use App\Models\StudentTak;
use App\Services\SidangTaAdmissionFacts;

beforeEach(function () {
    $this->travelTo(now()->startOfDay());
    $this->student = Student::create(['student_number' => '1202200050', 'name' => 'Meet Facts',
        'academic_year' => 2022, 'study_program' => 'Informatika', 'status' => 'Aktif', 'total_credits' => null]);
    $this->readFacts = fn () => app(SidangTaAdmissionFacts::class)->forStudent($this->student);
    $this->eprtRow = function (array $values = []) {
        return StudentEprt::create(array_replace(['student_number' => $this->student->student_number,
            'total_score' => 400, 'attempt_number' => 1, 'test_date' => today()->subDays(3), 'category' => null], $values));
    };
});

afterEach(function () { $this->travelBack(); });

test('meet_facts_empty_profile_has_five_unknown_gates_and_unknown_eprt', function () {
    $result = ($this->readFacts)();
    expect(count($result['academic']['blockers']))->toBe(5);
    foreach ($result['academic']['gates'] as $gate) expect($gate['state'])->toBe('unknown');
    expect($result['eprt']['mode'])->toBe('unknown');
    expect($result['eprt_facts']['confirmed_attempts'])->toBeNull();
});

test('meet_facts_level_two_is_boolean_and_reads_new_data_after_relation_was_loaded', function () {
    $row = StudentSidangTingkat::create(['student_number' => $this->student->student_number, 'tingkat_2' => true]);
    $this->student->load('sidangTingkat');
    expect(($this->readFacts)()['facts']['level_2_passed'])->toBeTrue();
    $row->update(['tingkat_2' => false]);
    expect(($this->readFacts)()['facts']['level_2_passed'])->toBeFalse();
});

test('meet_facts_skpi_requires_recognized_non_conflicting_status', function () {
    foreach ([
                 [['status' => ' APPROVED '], true], [['is_approved' => true], true],
                 [['approval_status' => 'pending'], false], [['is_approved' => 0], false],
                 [[], null], [['exists' => true], null], [['status' => 'custom'], null],
                 [['status' => 'approved', 'is_approved' => false], null], [['status' => []], null],
             ] as [$raw, $expected]) {
        StudentSkpi::updateOrCreate(['student_number' => $this->student->student_number], ['raw_data' => $raw]);
        expect(($this->readFacts)()['facts']['skpi_approved'])->toBe($expected);
    }
});

test('meet_facts_tak_uses_valid_highest_score_and_preserves_zero', function () {
    $nim = $this->student->student_number;
    StudentTak::create(['student_number' => $nim, 'total_points' => 99, 'test_date' => today()->subYear(), 'expiry_date' => today()->subDay()]);
    StudentTak::create(['student_number' => $nim, 'total_points' => 90, 'test_date' => today()->addDay(), 'expiry_date' => null]);
    $valid = StudentTak::create(['student_number' => $nim, 'total_points' => 0, 'test_date' => today(), 'expiry_date' => today()]);
    expect((float) ($this->readFacts)()['facts']['tak_score'])->toBe(0.0);
    expect(($this->readFacts)()['academic']['gates']['tak_score']['state'])->toBe('failed');
    $valid->update(['total_points' => 60]);
    expect(($this->readFacts)()['academic']['gates']['tak_score']['state'])->toBe('passed');
    $valid->delete();
    expect(($this->readFacts)()['facts']['tak_score'])->toBeNull();
});

test('meet_facts_passed_credits_prefers_imported_passed_credits_including_zero', function () {
    $this->student->update(['total_credits' => 150]);
    expect((int) ($this->readFacts)()['facts']['passed_credits'])->toBe(150);
    $row = StudentCourseIssue::create(['student_number' => $this->student->student_number, 'credits_passed' => 0]);
    expect(($this->readFacts)()['facts']['passed_credits'])->toBe(0);
    expect(($this->readFacts)()['academic']['gates']['passed_credits']['state'])->toBe('failed');
    $row->update(['credits_passed' => 120]);
    expect(($this->readFacts)()['academic']['gates']['passed_credits']['state'])->toBe('passed');
});

test('meet_facts_ta2_uses_linked_active_period_and_can_complete_all_five_gates', function () {
    AcademicPeriod::query()->update(['is_active' => false]);
    $period = AcademicPeriod::create(['year' => '2026/2027', 'semester' => 1, 'label' => 'Meet facts',
        'start_date' => '2026-08-01', 'end_date' => '2027-01-31', 'is_active' => true]);
    Course::create(['course_code' => 'MEET-FACTS', 'name' => 'TUGAS AKHIR 2', 'academic_year' => '2026/2027',
        'semester_term' => 'GANJIL', 'study_program' => 'Informatika', 'class' => 'A', 'semester' => 8, 'credits' => 4]);
    $krs = StudentCourse::create(['student_number' => $this->student->student_number, 'course_code' => 'MEET-FACTS',
        'course_name' => 'TUGAS AKHIR 2', 'course_class' => 'A', 'academic_year' => '2026/2027', 'semester' => 8,
        'academic_period_id' => $period->id]);
    StudentSidangTingkat::create(['student_number' => $this->student->student_number, 'tingkat_2' => true]);
    StudentSkpi::create(['student_number' => $this->student->student_number, 'raw_data' => ['status' => 'approved']]);
    StudentTak::create(['student_number' => $this->student->student_number, 'total_points' => 60]);
    StudentCourseIssue::create(['student_number' => $this->student->student_number, 'credits_passed' => 120]);
    expect(($this->readFacts)()['academic']['academic_passed'])->toBeTrue();
    expect(($this->readFacts)()['eprt']['mode'])->toBe('unknown');
    $krs->update(['course_name' => 'TUGAS AKHIR']);
    expect(($this->readFacts)()['facts']['ta2_registered'])->toBeNull();
    expect(($this->readFacts)()['academic']['academic_passed'])->toBeFalse();
});

test('meet_facts_eprt_three_completed_unique_tests_require_waiver_and_duplicates_do_not_add_attempts', function () {
    foreach ([1, 2, 3] as $attempt) {
        ($this->eprtRow)(['attempt_number' => $attempt, 'test_date' => today()->subDays(4 - $attempt)]);
    }
    ($this->eprtRow)(); // exact same attempt/date as attempt one
    $result = ($this->readFacts)();
    expect($result['eprt_facts']['confirmed_attempts'])->toBe(3);
    expect($result['eprt']['mode'])->toBe('waiver');
});

test('meet_facts_eprt_component_rows_do_not_count_as_three_tests', function () {
    foreach (['listening', 'structure', 'reading'] as $category) {
        ($this->eprtRow)(['category' => $category, 'total_score' => 500]);
    }
    $result = ($this->readFacts)();
    expect($result['eprt_facts']['score'])->toBeNull();
    expect($result['eprt_facts']['confirmed_attempts'])->toBeNull();
    expect($result['eprt']['mode'])->toBe('unknown');
});

test('meet_facts_eprt_high_attempt_number_is_not_a_test_count', function () {
    ($this->eprtRow)(['attempt_number' => 1]);
    ($this->eprtRow)(['attempt_number' => 3, 'test_date' => today()->subDay()]);
    $result = ($this->readFacts)();
    expect($result['eprt_facts']['confirmed_attempts'])->toBe(2);
    expect($result['eprt']['mode'])->toBe('retest');
});

test('meet_facts_eprt_missing_metadata_or_conflicting_attempt_dates_are_unknown', function () {
    $first = ($this->eprtRow)(['test_date' => null]);
    expect(($this->readFacts)()['eprt']['mode'])->toBe('unknown');
    $first->update(['test_date' => today()->subDays(3)]);
    ($this->eprtRow)(['attempt_number' => 1, 'test_date' => today()->subDay()]);
    expect(($this->readFacts)()['eprt_facts']['confirmed_attempts'])->toBeNull();
    StudentEprt::query()->delete();
    ($this->eprtRow)(['attempt_number' => 1]);
    ($this->eprtRow)(['attempt_number' => 2]); // same date, different attempt
    expect(($this->readFacts)()['eprt']['mode'])->toBe('unknown');
});

test('meet_facts_eprt_qualifying_total_score_needs_no_attempt_metadata_but_future_tests_do_not_qualify', function () {
    $row = ($this->eprtRow)(['total_score' => 450, 'test_date' => null]);
    expect(($this->readFacts)()['eprt']['mode'])->toBe('automatic');
    $row->update(['test_date' => today()->addDay()]);
    expect(($this->readFacts)()['eprt']['mode'])->toBe('unknown');
});


test('meet_facts_never_borrows_another_students_academic_records', function () {
    $other = Student::create(['student_number' => '1202200051', 'name' => 'Other', 'academic_year' => 2022,
        'study_program' => 'Informatika', 'status' => 'Aktif', 'email' => 'other-admission-facts@test.local']);
    StudentSidangTingkat::create(['student_number' => $other->student_number, 'tingkat_2' => true]);
    StudentSkpi::create(['student_number' => $other->student_number, 'raw_data' => ['status' => 'approved']]);
    StudentTak::create(['student_number' => $other->student_number, 'total_points' => 100]);
    StudentEprt::create(['student_number' => $other->student_number, 'total_score' => 550]);
    expect(count(($this->readFacts)()['academic']['blockers']))->toBe(5);
    expect(($this->readFacts)()['eprt']['mode'])->toBe('unknown');
});
