<?php

use App\Models\User;
use App\Models\Course;
use App\Models\StudentCourse;
use App\Models\ExamSchedule;
use App\Models\Student;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(RolePermissionSeeder::class);
});

test('guest cannot access exam schedules index', function () {
    $this->get(route('exam_schedules.index'))
        ->assertRedirect(route('login'));
});

test('user with view permission can access index', function () {
    $user = User::factory()->create(['username' => 'admin_user']);
    $user->assignRole('admin');

    $this->actingAs($user)
        ->get(route('exam_schedules.index'))
        ->assertOk();
});

test('user can filter exam schedules by assessment type', function () {
    $user = User::factory()->create(['username' => 'admin_user']);
    $user->assignRole('admin');

    $course1 = Course::create([
        'course_code' => 'IF-101', 'name' => 'Web Programming', 'study_program' => 'Informatika', 'class' => 'IF-44-01', 'semester' => 4, 'credits' => 3, 'type' => 'Wajib', 'status' => 'Aktif'
    ]);
    $course2 = Course::create([
        'course_code' => 'IF-102', 'name' => 'Artificial Intelligence', 'study_program' => 'Informatika', 'class' => 'IF-44-02', 'semester' => 4, 'credits' => 3, 'type' => 'Wajib', 'status' => 'Aktif'
    ]);

    ExamSchedule::create([
        'course_id'            => $course1->id,
        'lecturer_code'        => 'DSN001',
        'exam_date'            => '2026-06-10',
        'start_time'           => '08:00',
        'end_time'             => '10:00',
        'room_1' => 'GD-A201',
        'assessment_type'      => 'MENGERJAKAN SOAL UJIAN',
        'execution_method'     => 'DIKERJAKAN ONSITE DI KAMPUS',
        'execution_time'       => 'SESUAI JADWAL KELAS PERKULIAHAN',
        'participant_division' => 'SEMUA PESERTA DIGABUNG DALAM 1 RUANGAN',
        'question_type'        => 'SOAL TERSEDIA DI LMS',
        'answer_sheet_type'    => 'TIDAK PERLU LEMBAR JAWABAN',
        'needs_scratch_paper'  => 'TIDAK PERLU',
        'proctor_type'         => 'DOSEN PENGAMPU',
        'status'               => 'Approved',
    ]);

    ExamSchedule::create([
        'course_id'            => $course2->id,
        'lecturer_code'        => 'DSN002',
        'exam_date'            => '2026-06-11',
        'start_time'           => '10:00',
        'end_time'             => '12:00',
        'room_1' => 'GD-A202',
        'assessment_type'      => 'TIDAK ADA ASSESSMEN BERSAMA',
        'execution_method'     => 'TIDAK ADA ASSESSMEN BERSAMA',
        'execution_time'       => 'TIDAK ADA ASSESSMEN BERSAMA',
        'participant_division' => 'TIDAK ADA ASSESSMEN BERSAMA',
        'question_type'        => 'TIDAK ADA ASSESSMEN BERSAMA',
        'answer_sheet_type'    => 'TIDAK ADA ASSESSMEN BERSAMA',
        'needs_scratch_paper'  => 'TIDAK PERLU',
        'proctor_type'         => 'TIDAK ADA ASSESSMEN BERSAMA',
        'status'               => 'Approved',
    ]);

    // Filter by MENGERJAKAN SOAL UJIAN
    $response = $this->actingAs($user)
        ->get(route('exam_schedules.index', ['jenis_assessment' => 'MENGERJAKAN SOAL UJIAN']));

    $response->assertOk();
    $response->assertSee('Web Programming');
    $response->assertDontSee('Artificial Intelligence');

    // Filter by TIDAK ADA ASSESSMEN BERSAMA
    $response2 = $this->actingAs($user)
        ->get(route('exam_schedules.index', ['jenis_assessment' => 'TIDAK ADA ASSESSMEN BERSAMA']));

    $response2->assertOk();
    $response2->assertDontSee('Web Programming');
    $response2->assertSee('Artificial Intelligence');
});

test('mahasiswa only sees their enrolled exam schedules', function () {
    // 1. Create a student user and corresponding Student record
    $studentUser = User::factory()->create(['username' => '1202200001']);
    $studentUser->assignRole('mahasiswa');

    Student::create([
        'student_number' => '1202200001',
        'name'           => 'Test Student',
        'academic_year'  => 2022,
        'study_program'  => 'Informatika',
        'email'          => 'student@example.com',
        'status'         => 'Aktif',
    ]);

    // 2. Create courses
    $courseEnrolled = Course::create([
        'course_code'   => 'IF-101',
        'name'          => 'Pemrograman Web',
        'study_program' => 'Informatika',
        'class'         => 'IF-44-01',
        'semester'      => 4,
        'credits'       => 3,
        'type'          => 'Wajib',
        'status'        => 'Aktif',
    ]);

    $courseNotEnrolled = Course::create([
        'course_code'   => 'IF-102',
        'name'          => 'Kecerdasan Buatan',
        'study_program' => 'Informatika',
        'class'         => 'IF-44-01',
        'semester'      => 4,
        'credits'       => 3,
        'type'          => 'Wajib',
        'status'        => 'Aktif',
    ]);

    // 3. Enroll student in courseEnrolled
    StudentCourse::create([
        'student_number' => '1202200001',
        'course_code'    => 'IF-101',
        'course_name'    => 'Pemrograman Web',
        'course_class'   => 'IF-44-01',
        'academic_year'  => '2025/2026',
        'semester'       => 4,
        'credits'        => 3,
    ]);

    // 4. Create schedules for both courses
    $scheduleEnrolled = ExamSchedule::create([
        'course_id'            => $courseEnrolled->id,
        'lecturer_code'        => 'DSN001',
        'exam_date'            => '2026-06-10',
        'start_time'           => '08:00',
        'end_time'             => '10:00',
        'room_1' => 'GD-A201',
        'assessment_type'      => 'MENGERJAKAN SOAL UJIAN',
        'execution_method'     => 'DIKERJAKAN ONSITE DI KAMPUS',
        'execution_time'       => 'SESUAI JADWAL KELAS PERKULIAHAN',
        'participant_division' => 'SEMUA PESERTA DIGABUNG DALAM 1 RUANGAN',
        'question_type'        => 'SOAL TERSEDIA DI LMS',
        'answer_sheet_type'    => 'TIDAK PERLU LEMBAR JAWABAN',
        'needs_scratch_paper'  => 'TIDAK PERLU',
        'proctor_type'         => 'DOSEN PENGAMPU',
    ]);

    $scheduleNotEnrolled = ExamSchedule::create([
        'course_id'            => $courseNotEnrolled->id,
        'lecturer_code'        => 'DSN002',
        'exam_date'            => '2026-06-11',
        'start_time'           => '10:00',
        'end_time'             => '12:00',
        'room_1' => 'GD-A202',
        'assessment_type'      => 'MENGERJAKAN SOAL UJIAN',
        'execution_method'     => 'DIKERJAKAN ONSITE DI KAMPUS',
        'execution_time'       => 'SESUAI JADWAL KELAS PERKULIAHAN',
        'participant_division' => 'SEMUA PESERTA DIGABUNG DALAM 1 RUANGAN',
        'question_type'        => 'SOAL TERSEDIA DI LMS',
        'answer_sheet_type'    => 'TIDAK PERLU LEMBAR JAWABAN',
        'needs_scratch_paper'  => 'TIDAK PERLU',
        'proctor_type'         => 'DOSEN PENGAMPU',
    ]);

    // 5. Assert student can see scheduleEnrolled but NOT scheduleNotEnrolled
    $response = $this->actingAs($studentUser)->get(route('exam_schedules.index'));
    
    $response->assertOk();
    $response->assertSee('Pemrograman Web');
    $response->assertDontSee('Kecerdasan Buatan');
});

test('course info endpoint returns correct json data', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $course = Course::create([
        'course_code'   => 'IF-101',
        'name'          => 'Pemrograman Web',
        'study_program' => 'Informatika',
        'class'         => 'IF-44-01',
        'semester'      => 4,
        'credits'       => 3,
        'type'          => 'Wajib',
        'status'        => 'Aktif',
    ]);

    $response = $this->actingAs($user)
        ->get(route('exam_schedules.course-data', ['kode_mk' => $course->course_code, 'prodi' => $course->study_program]));

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'id' => $course->id,
            'prodi' => 'Informatika',
            'nama_mk' => 'Pemrograman Web',
            'kelas' => 'IF-44-01',
            'enrolled_students_count' => 0,
        ]);
});

test('course info endpoint resolves study program dynamically for general courses', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $course = Course::create([
        'course_code'   => 'UAKXACB2',
        'name'          => 'Agama Islam',
        'study_program' => 'Umum',
        'class'         => 'DB-04-05',
        'semester'      => 2,
        'credits'       => 2,
        'type'          => 'Wajib',
        'status'        => 'Aktif',
    ]);

    $student = Student::create([
        'student_number' => '108092500001',
        'name'           => 'John Doe',
        'study_program'  => 'S1 Teknologi Informasi - Kampus Surabaya',
        'academic_year'  => 2025,
        'status'         => 'Aktif',
    ]);

    StudentCourse::create([
        'student_number' => '108092500001',
        'course_code'    => 'UAKXACB2',
        'course_name'    => 'Agama Islam',
        'course_class'   => 'DB-04-05',
        'academic_year'  => '2025/2026',
        'semester'       => 2,
        'credits'        => 2,
    ]);

    $response = $this->actingAs($user)
        ->get(route('exam_schedules.course-data', ['kode_mk' => 'UAKXACB2']));

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'prodi' => 'S1 Teknologi Informasi - Kampus Surabaya',
            'enrolled_students_count' => 1,
        ]);
});

test('user without manage permission cannot perform CRUD', function () {
    $studentUser = User::factory()->create(['username' => '1202200001']);
    $studentUser->assignRole('mahasiswa');

    $this->actingAs($studentUser)
        ->get(route('exam_schedules.create'))
        ->assertStatus(403);

    $this->actingAs($studentUser)
        ->post(route('exam_schedules.store'), [])
        ->assertStatus(403);
});

test('user with manage permission can create exam schedule', function () {
    $adminUser = User::factory()->create();
    $adminUser->assignRole('admin');

    $course = Course::create([
        'course_code'   => 'IF-101',
        'name'          => 'Pemrograman Web',
        'study_program' => 'Informatika',
        'class'         => 'IF-44-01',
        'semester'      => 4,
        'credits'       => 3,
        'type'          => 'Wajib',
        'status'        => 'Aktif',
    ]);

    $data = [
        'course_id'            => $course->id,
        'lecturer_code'        => 'DSN001',
        'exam_date'            => '2026-06-10',
        'start_time'           => '08:00',
        'end_time'             => '10:00',
        'room_1' => 'GD-A201',
        'assessment_type'      => 'MENGERJAKAN SOAL UJIAN',
        'execution_method'     => 'DIKERJAKAN ONSITE DI KAMPUS',
        'execution_time'       => 'SESUAI JADWAL KELAS PERKULIAHAN',
        'participant_division' => 'SEMUA PESERTA DIGABUNG DALAM 1 RUANGAN',
        'question_type'        => 'SOAL TERSEDIA DI LMS',
        'answer_sheet_type'    => 'TIDAK PERLU LEMBAR JAWABAN',
        'needs_scratch_paper'  => 'TIDAK PERLU',
        'proctor_type'         => 'DOSEN PENGAMPU',
    ];

    $response = $this->actingAs($adminUser)
        ->post(route('exam_schedules.store'), $data);

    $response->assertRedirect(route('exam_schedules.index'));
    $this->assertDatabaseHas('exam_schedules', [
        'course_id'     => $course->id,
        'lecturer_code' => 'DSN001',
        'room_1' => 'GD-A201',
        'status'        => 'Approved',
    ]);
});

test('exam schedule creation gets Draft - Conflict when room capacity is exceeded', function () {
    $adminUser = User::factory()->create();
    $adminUser->assignRole('admin');

    $course = Course::create([
        'course_code'   => 'IF-101',
        'name'          => 'Pemrograman Web',
        'study_program' => 'Informatika',
        'class'         => 'IF-44-01',
        'semester'      => 4,
        'credits'       => 3,
        'type'          => 'Wajib',
        'status'        => 'Aktif',
    ]);

    // Create a room with capacity of 1
    \App\Models\Room::create([
        'room_code' => 'GD-A201',
        'name'      => 'A201',
        'building'  => 'Gedung A',
        'capacity'  => 1,
        'status'    => 'Dapat Dipakai',
    ]);

    // Create students first to satisfy foreign key constraints
    Student::create([
        'student_number' => '1202200001', 'name' => 'Student 1', 'academic_year' => 2022, 'study_program' => 'Informatika', 'email' => 'student1@example.com', 'status' => 'Aktif'
    ]);
    Student::create([
        'student_number' => '1202200002', 'name' => 'Student 2', 'academic_year' => 2022, 'study_program' => 'Informatika', 'email' => 'student2@example.com', 'status' => 'Aktif'
    ]);

    // Enroll 2 students in the course
    StudentCourse::create([
        'student_number' => '1202200001',
        'course_code'    => 'IF-101',
        'course_name'    => 'Pemrograman Web',
        'course_class'   => 'IF-44-01',
        'academic_year'  => '2025/2026',
        'semester'       => 4,
        'credits'        => 3,
    ]);

    StudentCourse::create([
        'student_number' => '1202200002',
        'course_code'    => 'IF-101',
        'course_name'    => 'Pemrograman Web',
        'course_class'   => 'IF-44-01',
        'academic_year'  => '2025/2026',
        'semester'       => 4,
        'credits'        => 3,
    ]);

    $data = [
        'course_id'            => $course->id,
        'lecturer_code'        => 'DSN001',
        'exam_date'            => '2026-06-10',
        'start_time'           => '08:00',
        'end_time'             => '10:00',
        'room_1' => 'GD-A201',
        'assessment_type'      => 'MENGERJAKAN SOAL UJIAN',
        'execution_method'     => 'DIKERJAKAN ONSITE DI KAMPUS',
        'execution_time'       => 'SESUAI JADWAL KELAS PERKULIAHAN',
        'participant_division' => 'SEMUA PESERTA DIGABUNG DALAM 1 RUANGAN',
        'question_type'        => 'SOAL TERSEDIA DI LMS',
        'answer_sheet_type'    => 'TIDAK PERLU LEMBAR JAWABAN',
        'needs_scratch_paper'  => 'TIDAK PERLU',
        'proctor_type'         => 'DOSEN PENGAMPU',
    ];

    $response = $this->actingAs($adminUser)
        ->post(route('exam_schedules.store'), $data);

    $response->assertRedirect(route('exam_schedules.index'));
    $response->assertSessionHas('warning');
    $this->assertDatabaseHas('exam_schedules', [
        'course_id' => $course->id,
        'status'    => 'Draft - Conflict',
    ]);
});

test('exam schedule creation gets Draft - Conflict when room is already used at overlapping time', function () {
    $adminUser = User::factory()->create();
    $adminUser->assignRole('admin');

    $course1 = Course::create([
        'course_code' => 'IF-101', 'name' => 'Web', 'study_program' => 'IF', 'class' => 'IF-44-01', 'semester' => 4, 'credits' => 3, 'type' => 'Wajib', 'status' => 'Aktif'
    ]);
    $course2 = Course::create([
        'course_code' => 'IF-102', 'name' => 'AI', 'study_program' => 'IF', 'class' => 'IF-44-02', 'semester' => 4, 'credits' => 3, 'type' => 'Wajib', 'status' => 'Aktif'
    ]);

    // Create an approved schedule using room GD-A201 from 08:00 to 10:00
    ExamSchedule::create([
        'course_id'            => $course1->id,
        'lecturer_code'        => 'DSN001',
        'exam_date'            => '2026-06-10',
        'start_time'           => '08:00',
        'end_time'             => '10:00',
        'room_1' => 'GD-A201',
        'assessment_type'      => 'MENGERJAKAN SOAL UJIAN',
        'execution_method'     => 'DIKERJAKAN ONSITE DI KAMPUS',
        'execution_time'       => 'SESUAI JADWAL KELAS PERKULIAHAN',
        'participant_division' => 'SEMUA PESERTA DIGABUNG DALAM 1 RUANGAN',
        'question_type'        => 'SOAL TERSEDIA DI LMS',
        'answer_sheet_type'    => 'TIDAK PERLU LEMBAR JAWABAN',
        'needs_scratch_paper'  => 'TIDAK PERLU',
        'proctor_type'         => 'DOSEN PENGAMPU',
        'status'               => 'Approved',
    ]);

    // Attempt to schedule course2 at overlapping time (09:00 to 11:00) in the same room GD-A201
    $data = [
        'course_id'            => $course2->id,
        'lecturer_code'        => 'DSN002',
        'exam_date'            => '2026-06-10',
        'start_time'           => '09:00',
        'end_time'             => '11:00',
        'room_1' => 'GD-A201',
        'assessment_type'      => 'MENGERJAKAN SOAL UJIAN',
        'execution_method'     => 'DIKERJAKAN ONSITE DI KAMPUS',
        'execution_time'       => 'SESUAI JADWAL KELAS PERKULIAHAN',
        'participant_division' => 'SEMUA PESERTA DIGABUNG DALAM 1 RUANGAN',
        'question_type'        => 'SOAL TERSEDIA DI LMS',
        'answer_sheet_type'    => 'TIDAK PERLU LEMBAR JAWABAN',
        'needs_scratch_paper'  => 'TIDAK PERLU',
        'proctor_type'         => 'DOSEN PENGAMPU',
    ];

    $response = $this->actingAs($adminUser)
        ->post(route('exam_schedules.store'), $data);

    $response->assertRedirect(route('exam_schedules.index'));
    $response->assertSessionHas('warning');
    $this->assertDatabaseHas('exam_schedules', [
        'course_id' => $course2->id,
        'status'    => 'Draft - Conflict',
    ]);
});

test('exam schedule creation gets Draft - Conflict when lecturer is already busy at overlapping time', function () {
    $adminUser = User::factory()->create();
    $adminUser->assignRole('admin');

    $course1 = Course::create([
        'course_code' => 'IF-101', 'name' => 'Web', 'study_program' => 'IF', 'class' => 'IF-44-01', 'semester' => 4, 'credits' => 3, 'type' => 'Wajib', 'status' => 'Aktif'
    ]);
    $course2 = Course::create([
        'course_code' => 'IF-102', 'name' => 'AI', 'study_program' => 'IF', 'class' => 'IF-44-02', 'semester' => 4, 'credits' => 3, 'type' => 'Wajib', 'status' => 'Aktif'
    ]);

    // Create an approved schedule with lecturer DSN001 from 08:00 to 10:00
    ExamSchedule::create([
        'course_id'            => $course1->id,
        'lecturer_code'        => 'DSN001',
        'exam_date'            => '2026-06-10',
        'start_time'           => '08:00',
        'end_time'             => '10:00',
        'room_1' => 'GD-A201',
        'assessment_type'      => 'MENGERJAKAN SOAL UJIAN',
        'execution_method'     => 'DIKERJAKAN ONSITE DI KAMPUS',
        'execution_time'       => 'SESUAI JADWAL KELAS PERKULIAHAN',
        'participant_division' => 'SEMUA PESERTA DIGABUNG DALAM 1 RUANGAN',
        'question_type'        => 'SOAL TERSEDIA DI LMS',
        'answer_sheet_type'    => 'TIDAK PERLU LEMBAR JAWABAN',
        'needs_scratch_paper'  => 'TIDAK PERLU',
        'proctor_type'         => 'DOSEN PENGAMPU',
        'status'               => 'Approved',
    ]);

    // Attempt to schedule course2 at overlapping time (09:00 to 11:00) with same lecturer DSN001 in another room GD-A202
    $data = [
        'course_id'            => $course2->id,
        'lecturer_code'        => 'DSN001',
        'exam_date'            => '2026-06-10',
        'start_time'           => '09:00',
        'end_time'             => '11:00',
        'room_1' => 'GD-A202',
        'assessment_type'      => 'MENGERJAKAN SOAL UJIAN',
        'execution_method'     => 'DIKERJAKAN ONSITE DI KAMPUS',
        'execution_time'       => 'SESUAI JADWAL KELAS PERKULIAHAN',
        'participant_division' => 'SEMUA PESERTA DIGABUNG DALAM 1 RUANGAN',
        'question_type'        => 'SOAL TERSEDIA DI LMS',
        'answer_sheet_type'    => 'TIDAK PERLU LEMBAR JAWABAN',
        'needs_scratch_paper'  => 'TIDAK PERLU',
        'proctor_type'         => 'DOSEN PENGAMPU',
    ];

    $response = $this->actingAs($adminUser)
        ->post(route('exam_schedules.store'), $data);

    $response->assertRedirect(route('exam_schedules.index'));
    $response->assertSessionHas('warning');
    $this->assertDatabaseHas('exam_schedules', [
        'course_id' => $course2->id,
        'status'    => 'Draft - Conflict',
    ]);
});

test('exam schedule creation gets Draft - Conflict when student courses overlap at same time', function () {
    $adminUser = User::factory()->create();
    $adminUser->assignRole('admin');

    $course1 = Course::create([
        'course_code' => 'IF-101', 'name' => 'Web', 'study_program' => 'IF', 'class' => 'IF-44-01', 'semester' => 4, 'credits' => 3, 'type' => 'Wajib', 'status' => 'Aktif'
    ]);
    $course2 = Course::create([
        'course_code' => 'IF-102', 'name' => 'AI', 'study_program' => 'IF', 'class' => 'IF-44-02', 'semester' => 4, 'credits' => 3, 'type' => 'Wajib', 'status' => 'Aktif'
    ]);

    // Create student first to satisfy foreign key constraints
    Student::create([
        'student_number' => '1202200001', 'name' => 'Student 1', 'academic_year' => 2022, 'study_program' => 'Informatika', 'email' => 'student1@example.com', 'status' => 'Aktif'
    ]);

    // Student 1202200001 is enrolled in both Web and AI
    StudentCourse::create([
        'student_number' => '1202200001',
        'course_code'    => 'IF-101',
        'course_name'    => 'Web',
        'course_class'   => 'IF-44-01',
        'academic_year'  => '2025/2026',
        'semester'       => 4,
        'credits'        => 3,
    ]);
    StudentCourse::create([
        'student_number' => '1202200001',
        'course_code'    => 'IF-102',
        'course_name'    => 'AI',
        'course_class'   => 'IF-44-02',
        'academic_year'  => '2025/2026',
        'semester'       => 4,
        'credits'        => 3,
    ]);

    // Schedule course1 from 08:00 to 10:00 (Approved)
    ExamSchedule::create([
        'course_id'            => $course1->id,
        'lecturer_code'        => 'DSN001',
        'exam_date'            => '2026-06-10',
        'start_time'           => '08:00',
        'end_time'             => '10:00',
        'room_1' => 'GD-A201',
        'assessment_type'      => 'MENGERJAKAN SOAL UJIAN',
        'execution_method'     => 'DIKERJAKAN ONSITE DI KAMPUS',
        'execution_time'       => 'SESUAI JADWAL KELAS PERKULIAHAN',
        'participant_division' => 'SEMUA PESERTA DIGABUNG DALAM 1 RUANGAN',
        'question_type'        => 'SOAL TERSEDIA DI LMS',
        'answer_sheet_type'    => 'TIDAK PERLU LEMBAR JAWABAN',
        'needs_scratch_paper'  => 'TIDAK PERLU',
        'proctor_type'         => 'DOSEN PENGAMPU',
        'status'               => 'Approved',
    ]);

    // Attempt to schedule course2 at overlapping time (09:00 to 11:00)
    $data = [
        'course_id'            => $course2->id,
        'lecturer_code'        => 'DSN002',
        'exam_date'            => '2026-06-10',
        'start_time'           => '09:00',
        'end_time'             => '11:00',
        'room_1' => 'GD-A202',
        'assessment_type'      => 'MENGERJAKAN SOAL UJIAN',
        'execution_method'     => 'DIKERJAKAN ONSITE DI KAMPUS',
        'execution_time'       => 'SESUAI JADWAL KELAS PERKULIAHAN',
        'participant_division' => 'SEMUA PESERTA DIGABUNG DALAM 1 RUANGAN',
        'question_type'        => 'SOAL TERSEDIA DI LMS',
        'answer_sheet_type'    => 'TIDAK PERLU LEMBAR JAWABAN',
        'needs_scratch_paper'  => 'TIDAK PERLU',
        'proctor_type'         => 'DOSEN PENGAMPU',
    ];

    $response = $this->actingAs($adminUser)
        ->post(route('exam_schedules.store'), $data);

    $response->assertRedirect(route('exam_schedules.index'));
    $response->assertSessionHas('warning');
    $this->assertDatabaseHas('exam_schedules', [
        'course_id' => $course2->id,
        'status'    => 'Draft - Conflict',
    ]);
});

test('check availability endpoint returns true when room is available', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    \App\Models\Room::firstOrCreate(
        ['room_code' => 'GD-A201'],
        ['name' => 'A201', 'building' => 'Gedung A', 'capacity' => 40, 'status' => 'Dapat Dipakai']
    );

    $response = $this->actingAs($user)
        ->getJson(route('exam_schedules.check-availability', [
            'exam_date'  => '2026-06-10',
            'start_time' => '08:00',
            'end_time'   => '10:00',
            'room_1' => 'GD-A201',
        ]));

    $response->assertOk()
        ->assertJson([
            'available' => true,
            'message'   => 'Ruangan tersedia dan tidak ada konflik jadwal.',
        ]);
});

test('check availability endpoint returns false when room is booked', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    \App\Models\Room::firstOrCreate(
        ['room_code' => 'GD-A201'],
        ['name' => 'A201', 'building' => 'Gedung A', 'capacity' => 40, 'status' => 'Dapat Dipakai']
    );

    $course = Course::create([
        'course_code' => 'IF-101', 'name' => 'Web', 'study_program' => 'IF', 'class' => 'IF-44-01', 'semester' => 4, 'credits' => 3, 'type' => 'Wajib', 'status' => 'Aktif'
    ]);

    ExamSchedule::create([
        'course_id'            => $course->id,
        'lecturer_code'        => 'DSN001',
        'exam_date'            => '2026-06-10',
        'start_time'           => '08:00',
        'end_time'             => '10:00',
        'room_1' => 'GD-A201',
        'assessment_type'      => 'MENGERJAKAN SOAL UJIAN',
        'execution_method'     => 'DIKERJAKAN ONSITE DI KAMPUS',
        'execution_time'       => 'SESUAI JADWAL KELAS PERKULIAHAN',
        'participant_division' => 'SEMUA PESERTA DIGABUNG DALAM 1 RUANGAN',
        'question_type'        => 'SOAL TERSEDIA DI LMS',
        'answer_sheet_type'    => 'TIDAK PERLU LEMBAR JAWABAN',
        'needs_scratch_paper'  => 'TIDAK PERLU',
        'proctor_type'         => 'DOSEN PENGAMPU',
        'status'               => 'Approved',
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('exam_schedules.check-availability', [
            'exam_date'  => '2026-06-10',
            'start_time' => '09:00',
            'end_time'   => '11:00',
            'room_1' => 'GD-A201',
        ]));

    $response->assertOk()
        ->assertJson([
            'available' => false,
        ])
        ->assertJsonFragment([
            'message' => 'Ruangan GD-A201 sudah digunakan oleh mata kuliah Web (IF-101) pada jam 08:00 - 10:00.',
        ]);
});

test('check availability endpoint returns false when lecturer is busy', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    \App\Models\Room::firstOrCreate(
        ['room_code' => 'GD-A201'],
        ['name' => 'A201', 'building' => 'Gedung A', 'capacity' => 40, 'status' => 'Dapat Dipakai']
    );
    \App\Models\Room::firstOrCreate(
        ['room_code' => 'GD-A202'],
        ['name' => 'A202', 'building' => 'Gedung A', 'capacity' => 40, 'status' => 'Dapat Dipakai']
    );

    $course = Course::create([
        'course_code' => 'IF-101', 'name' => 'Web', 'study_program' => 'IF', 'class' => 'IF-44-01', 'semester' => 4, 'credits' => 3, 'type' => 'Wajib', 'status' => 'Aktif'
    ]);

    ExamSchedule::create([
        'course_id'            => $course->id,
        'lecturer_code'        => 'DSN001',
        'exam_date'            => '2026-06-10',
        'start_time'           => '08:00',
        'end_time'             => '10:00',
        'room_1' => 'GD-A201',
        'assessment_type'      => 'MENGERJAKAN SOAL UJIAN',
        'execution_method'     => 'DIKERJAKAN ONSITE DI KAMPUS',
        'execution_time'       => 'SESUAI JADWAL KELAS PERKULIAHAN',
        'participant_division' => 'SEMUA PESERTA DIGABUNG DALAM 1 RUANGAN',
        'question_type'        => 'SOAL TERSEDIA DI LMS',
        'answer_sheet_type'    => 'TIDAK PERLU LEMBAR JAWABAN',
        'needs_scratch_paper'  => 'TIDAK PERLU',
        'proctor_type'         => 'DOSEN PENGAMPU',
        'status'               => 'Approved',
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('exam_schedules.check-availability', [
            'exam_date'     => '2026-06-10',
            'start_time'    => '09:00',
            'end_time'      => '11:00',
            'room_1' => 'GD-A202',
            'lecturer_code' => 'DSN001',
        ]));

    $response->assertOk()
        ->assertJson([
            'available' => false,
        ])
        ->assertJsonFragment([
            'message' => 'Dosen DSN001 memiliki jadwal mengajar/mengawas ujian matakuliah Web (IF-101) pada jam 08:00 - 10:00.',
        ]);
});

test('check availability endpoint returns false when student courses overlap', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    \App\Models\Room::firstOrCreate(
        ['room_code' => 'GD-A201'],
        ['name' => 'A201', 'building' => 'Gedung A', 'capacity' => 40, 'status' => 'Dapat Dipakai']
    );
    \App\Models\Room::firstOrCreate(
        ['room_code' => 'GD-A202'],
        ['name' => 'A202', 'building' => 'Gedung A', 'capacity' => 40, 'status' => 'Dapat Dipakai']
    );

    $course1 = Course::create([
        'course_code' => 'IF-101', 'name' => 'Web', 'study_program' => 'IF', 'class' => 'IF-44-01', 'semester' => 4, 'credits' => 3, 'type' => 'Wajib', 'status' => 'Aktif'
    ]);
    $course2 = Course::create([
        'course_code' => 'IF-102', 'name' => 'AI', 'study_program' => 'IF', 'class' => 'IF-44-02', 'semester' => 4, 'credits' => 3, 'type' => 'Wajib', 'status' => 'Aktif'
    ]);

    // Create student and enroll in both
    Student::create([
        'student_number' => '1202200001', 'name' => 'Student 1', 'academic_year' => 2022, 'study_program' => 'Informatika', 'email' => 'student1@example.com', 'status' => 'Aktif'
    ]);
    StudentCourse::create([
        'student_number' => '1202200001', 'course_code' => 'IF-101', 'course_name' => 'Web', 'course_class' => 'IF-44-01', 'academic_year' => '2025/2026', 'semester' => 4, 'credits' => 3
    ]);
    StudentCourse::create([
        'student_number' => '1202200001', 'course_code' => 'IF-102', 'course_name' => 'AI', 'course_class' => 'IF-44-02', 'academic_year' => '2025/2026', 'semester' => 4, 'credits' => 3
    ]);

    ExamSchedule::create([
        'course_id'            => $course1->id,
        'lecturer_code'        => 'DSN001',
        'exam_date'            => '2026-06-10',
        'start_time'           => '08:00',
        'end_time'             => '10:00',
        'room_1' => 'GD-A201',
        'assessment_type'      => 'MENGERJAKAN SOAL UJIAN',
        'execution_method'     => 'DIKERJAKAN ONSITE DI KAMPUS',
        'execution_time'       => 'SESUAI JADWAL KELAS PERKULIAHAN',
        'participant_division' => 'SEMUA PESERTA DIGABUNG DALAM 1 RUANGAN',
        'question_type'        => 'SOAL TERSEDIA DI LMS',
        'answer_sheet_type'    => 'TIDAK PERLU LEMBAR JAWABAN',
        'needs_scratch_paper'  => 'TIDAK PERLU',
        'proctor_type'         => 'DOSEN PENGAMPU',
        'status'               => 'Approved',
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('exam_schedules.check-availability', [
            'exam_date'  => '2026-06-10',
            'start_time' => '09:00',
            'end_time'   => '11:00',
            'room_1' => 'GD-A202',
            'course_id'  => $course2->id,
        ]));

    $response->assertOk()
        ->assertJson([
            'available' => false,
        ])
        ->assertJsonFragment([
            'message' => 'Tabrakan jadwal mahasiswa! Terdapat 1 mahasiswa yang mengambil mata kuliah ini dan juga Web (IF-101) yang dijadwalkan pada jam 08:00 - 10:00.',
        ]);
});

test('exam schedule creation is successful when assessment type is TIDAK ADA ASSESSMEN BERSAMA and fields are null', function () {
    $adminUser = User::factory()->create();
    $adminUser->assignRole('admin');

    $course = Course::create([
        'course_code'   => 'IF-101',
        'name'          => 'Pemrograman Web',
        'study_program' => 'Informatika',
        'class'         => 'IF-44-01',
        'semester'      => 4,
        'credits'       => 3,
        'type'          => 'Wajib',
        'status'        => 'Aktif',
    ]);

    $data = [
        'course_id'            => $course->id,
        'lecturer_code'        => 'DSN001',
        'lecturer_code_2'      => 'DSN002',
        'exam_date'            => null,
        'start_time'           => null,
        'end_time'             => null,
        'room_1' => null,
        'assessment_type'      => 'TIDAK ADA ASSESSMEN BERSAMA',
        'execution_method'     => 'TIDAK ADA ASSESSMEN BERSAMA',
        'execution_time'       => 'TIDAK ADA ASSESSMEN BERSAMA',
        'participant_division' => 'TIDAK ADA ASSESSMEN BERSAMA',
        'question_type'        => 'TIDAK ADA ASSESSMEN BERSAMA',
        'answer_sheet_type'    => 'TIDAK ADA ASSESSMEN BERSAMA',
        'needs_scratch_paper'  => 'TIDAK PERLU',
        'proctor_type'         => 'TIDAK ADA ASSESSMEN BERSAMA',
    ];

    $response = $this->actingAs($adminUser)
        ->post(route('exam_schedules.store'), $data);

    $response->assertRedirect(route('exam_schedules.index'));
    $this->assertDatabaseHas('exam_schedules', [
        'course_id'       => $course->id,
        'lecturer_code'   => 'DSN001',
        'lecturer_code_2' => 'DSN002',
        'exam_date'       => null,
        'status'          => 'Approved',
    ]);
});

test('check availability endpoint returns false when lecturer 2 is busy', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    \App\Models\Room::firstOrCreate(
        ['room_code' => 'GD-A201'],
        ['name' => 'A201', 'building' => 'Gedung A', 'capacity' => 40, 'status' => 'Dapat Dipakai']
    );
    \App\Models\Room::firstOrCreate(
        ['room_code' => 'GD-A202'],
        ['name' => 'A202', 'building' => 'Gedung A', 'capacity' => 40, 'status' => 'Dapat Dipakai']
    );

    $course = Course::create([
        'course_code' => 'IF-101', 'name' => 'Web', 'study_program' => 'IF', 'class' => 'IF-44-01', 'semester' => 4, 'credits' => 3, 'type' => 'Wajib', 'status' => 'Aktif'
    ]);

    ExamSchedule::create([
        'course_id'            => $course->id,
        'lecturer_code'        => 'DSN001',
        'exam_date'            => '2026-06-10',
        'start_time'           => '08:00',
        'end_time'             => '10:00',
        'room_1' => 'GD-A201',
        'assessment_type'      => 'MENGERJAKAN SOAL UJIAN',
        'execution_method'     => 'DIKERJAKAN ONSITE DI KAMPUS',
        'execution_time'       => 'SESUAI JADWAL KELAS PERKULIAHAN',
        'participant_division' => 'SEMUA PESERTA DIGABUNG DALAM 1 RUANGAN',
        'question_type'        => 'SOAL TERSEDIA DI LMS',
        'answer_sheet_type'    => 'TIDAK PERLU LEMBAR JAWABAN',
        'needs_scratch_paper'  => 'TIDAK PERLU',
        'proctor_type'         => 'DOSEN PENGAMPU',
        'status'               => 'Approved',
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('exam_schedules.check-availability', [
            'exam_date'       => '2026-06-10',
            'start_time'      => '09:00',
            'end_time'        => '11:00',
            'room_1' => 'GD-A202',
            'lecturer_code'   => 'DSN002',
            'lecturer_code_2' => 'DSN001', // conflicts with original lecturer DSN001
        ]));

    $response->assertOk()
        ->assertJson([
            'available' => false,
        ])
        ->assertJsonFragment([
            'message' => 'Dosen DSN001 memiliki jadwal mengajar/mengawas ujian matakuliah Web (IF-101) pada jam 08:00 - 10:00.',
        ]);
});

test('user with read permission can access exam schedule detail page', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $course = Course::create([
        'course_code' => 'IF-101', 'name' => 'Web', 'study_program' => 'IF', 'class' => 'IF-44-01', 'semester' => 4, 'credits' => 3, 'type' => 'Wajib', 'status' => 'Aktif'
    ]);

    $schedule = ExamSchedule::create([
        'course_id'            => $course->id,
        'lecturer_code'        => 'DSN001',
        'exam_date'            => '2026-06-10',
        'start_time'           => '08:00',
        'end_time'             => '10:00',
        'room_1' => 'GD-A201',
        'assessment_type'      => 'MENGERJAKAN SOAL UJIAN',
        'execution_method'     => 'DIKERJAKAN ONSITE DI KAMPUS',
        'execution_time'       => 'SESUAI JADWAL KELAS PERKULIAHAN',
        'participant_division' => 'SEMUA PESERTA DIGABUNG DALAM 1 RUANGAN',
        'question_type'        => 'SOAL TERSEDIA DI LMS',
        'answer_sheet_type'    => 'TIDAK PERLU LEMBAR JAWABAN',
        'needs_scratch_paper'  => 'TIDAK PERLU',
        'proctor_type'         => 'DOSEN PENGAMPU',
        'status'               => 'Approved',
    ]);

    $response = $this->actingAs($user)
        ->get(route('exam_schedules.show', $schedule->id));

    $response->assertOk()
        ->assertSee('Detail Jadwal Ujian')
        ->assertSee('Web')
        ->assertSee('GD-A201');
});

test('admin can import exam schedules from excel', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $course = Course::create([
        'course_code'   => 'CSG2A3',
        'name'          => 'Algoritma dan Pemrograman',
        'study_program' => 'S1 Informatika',
        'class'         => 'IF-43-01',
        'semester'      => 4,
        'credits'       => 3,
        'type'          => 'Wajib',
        'status'        => 'Aktif',
    ]);

    // Create a temporary Excel file
    $data = collect([[
        'Program Studi' => 'S1 Informatika',
        'Kode MK' => 'CSG2A3',
        'Nama MK' => 'Algoritma dan Pemrograman',
        'Kelas' => 'IF-43-01',
        'Jumlah Mahasiswa' => '35',
        'Kode Dosen 1' => 'BDI',
        'Kode Dosen 2' => '',
        'Jenis Assesment' => 'MENGERJAKAN SOAL UJIAN',
        'Metode Pelaksanaan' => 'DIKERJAKAN ONSITE DI KAMPUS',
        'Waktu Pelaksanaan' => 'SESUAI JADWAL KELAS PERKULIAHAN',
        'Pembagian Peserta Ujian' => 'SEMUA PESERTA DIGABUNG DALAM 1 RUANGAN',
        'Soal Ujian' => 'PERLU PENGGANDAAN FISIK (HARDCOPY)',
        'Lembar Jawaban' => 'MENGGUNAKAN LEMBAR JAWABAN STANDAR AKADEMIK',
        'Kebutuhan Kertas Buram' => 'TIDAK PERLU',
        'Pengawal' => 'DOSEN PENGAMPU',
        'Jadwal Ujian Serentak se-TUNC' => '22/06/2026',
        'URL Soal Ujian' => 'https://drive.google.com/soal-test',
        'URL Lembar Jawaban Custom' => '',
        'Aturan Ujian' => 'Open Book, boleh kalkulator',
    ]]);

    $filePath = tempnam(sys_get_temp_dir(), 'import_test_') . '.xlsx';
    (new \Rap2hpoutre\FastExcel\FastExcel($data))->export($filePath);

    $file = new \Illuminate\Http\UploadedFile($filePath, 'import_test.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    $response = $this->actingAs($user)
        ->post(route('exam_schedules.import'), [
            'file' => $file
        ]);

    $response->assertRedirect(route('exam_schedules.index'));
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('exam_schedules', [
        'course_id' => $course->id,
        'lecturer_code' => 'BDI',
        'exam_date' => '2026-06-22 00:00:00',
        'assessment_type' => 'MENGERJAKAN SOAL UJIAN',
        'status' => 'Approved',
    ]);

    @unlink($filePath);
});

test('admin can import exam schedules from excel with dirty headers', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $course = Course::create([
        'course_code'   => 'DDK4MAB3',
        'name'          => 'AGILE METHODOLOGY',
        'study_program' => 'S1 Bisnis Digital',
        'class'         => 'DB-03-01',
        'semester'      => 4,
        'credits'       => 3,
        'type'          => 'Wajib',
        'status'        => 'Aktif',
    ]);

    // Create a temporary Excel file with headers containing parenthesis descriptions
    $data = collect([[
        'PROGRAM STUDI (contoh S1 Bisnis Digital)' => 'S1 Bisnis Digital',
        'KODE MK (DDK4MAB3)' => 'DDK4MAB3',
        'NAMA MK (AGILE METHODOLOGY)' => 'AGILE METHODOLOGY',
        'KELAS ( DB-03-01 )' => 'DB-03-01',
        'JUMLAH MAHASISWA ( 30 )' => '30',
        'KODE DOSEN 1 (WEK)' => 'WEK',
        'KODE DOSEN 2 (BRL)' => 'BRL',
        'JENIS ASSESSMENT (WAJIB DIISI)' => 'MENGERJAKAN SOAL UJIAN',
        'METODE PELAKSANAAN (WAJIB DIISI)' => 'DIKERJAKAN ONSITE DI KAMPUS',
        'WAKTUPELAKSANAAN (WAJIB DIISI)' => 'SESUAI JADWAL KELAS PERKULIAHAN',
        'PEMBAGIAN PESERTA UJIAN (WAJIB DIISI)' => 'SEMUA PESERTA DIGABUNG DALAM 1 RUANGAN',
        'SOAL UJIAN (WAJIB DIISI)' => 'PERLU PENGGANDAAN FISIK (HARDCOPY)',
        'LEMBAR JAWABAN (WAJIB DIISI)' => 'MENGGUNAKAN LEMBAR JAWABAN STANDAR AKADEMIK',
        'KEBUTUHAN KERTAS BURAM (WAJIB DIISI)' => 'TIDAK PERLU',
        'PENGAWAS (WAJIB DIISI)' => 'DOSEN PENGAMPU',
        'JADWAL UJIAN SERENTAK SE-TUNC (WAJIB DIISI)' => '22/06/2026',
        "URL SOAL UJIAN (WAJIB DIISI)\nFILE DIUPLOAD DI DRIVE MASING-MASING DOSEN, SHARE URL VIEW ONLY HANYA KE akademiksby@telkomuniversity.ac.id" => 'https://drive.google.com/soal-test',
        "URL LEMBAR JAWABAN CUSTOM (WAJIB DIISI)\nFILE DIUPLOAD DI DRIVE MASING-MASING DOSEN, SHARE URL VIEW ONLY HANYA KE akademiksby@telkomuniversity.ac.id" => '',
        "ATURAN UJIAN (WAJIB DIISI)\n(Open book/ Close book/ Open Catatan/ Boleh Kalkulator/ DLL)" => 'Open Book, boleh kalkulator',
    ]]);

    $filePath = tempnam(sys_get_temp_dir(), 'import_test_dirty_') . '.xlsx';
    (new \Rap2hpoutre\FastExcel\FastExcel($data))->export($filePath);

    $file = new \Illuminate\Http\UploadedFile($filePath, 'import_test_dirty.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    $response = $this->actingAs($user)
        ->post(route('exam_schedules.import'), [
            'file' => $file
        ]);

    $response->assertRedirect(route('exam_schedules.index'));
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('exam_schedules', [
        'course_id' => $course->id,
        'lecturer_code' => 'WEK',
        'lecturer_code_2' => 'BRL',
        'exam_date' => '2026-06-22 00:00:00',
        'assessment_type' => 'MENGERJAKAN SOAL UJIAN',
        'status' => 'Approved',
    ]);

    @unlink($filePath);
});

test('exam schedule isComplete method and status badges on index page', function () {
    $user = User::factory()->create(['username' => 'admin_user']);
    $user->assignRole('admin');

    $course1 = Course::create([
        'course_code' => 'IF-101', 'name' => 'Web Programming', 'study_program' => 'Informatika', 'class' => 'IF-44-01', 'semester' => 4, 'credits' => 3, 'type' => 'Wajib', 'status' => 'Aktif'
    ]);

    // Scenario 1: Complete schedule
    $schedule1 = ExamSchedule::create([
        'course_id'            => $course1->id,
        'lecturer_code'        => 'DSN001',
        'exam_date'            => '2026-06-10',
        'start_time'           => '08:00',
        'end_time'             => '10:00',
        'room_1'               => 'GD-A201',
        'proctor_name_1'       => 'Proctor A',
        'assessment_type'      => 'MENGERJAKAN SOAL UJIAN',
        'execution_method'     => 'DIKERJAKAN ONSITE DI KAMPUS',
        'execution_time'       => 'SESUAI JADWAL KELAS PERKULIAHAN',
        'participant_division' => 'SEMUA PESERTA DIGABUNG DALAM 1 RUANGAN',
        'question_type'        => 'SOAL TERSEDIA DI LMS',
        'answer_sheet_type'    => 'TIDAK PERLU LEMBAR JAWABAN',
        'needs_scratch_paper'  => 'TIDAK PERLU',
        'proctor_type'         => 'DOSEN PENGAMPU',
        'status'               => 'Approved',
    ]);

    expect($schedule1->isComplete())->toBeTrue();

    // Scenario 2: Incomplete schedule (missing room and proctor)
    $schedule2 = ExamSchedule::create([
        'course_id'            => $course1->id,
        'lecturer_code'        => 'DSN001',
        'exam_date'            => '2026-06-10',
        'start_time'           => '08:00',
        'end_time'             => '10:00',
        'room_1'               => null,
        'proctor_name_1'       => null,
        'assessment_type'      => 'MENGERJAKAN SOAL UJIAN',
        'execution_method'     => 'DIKERJAKAN ONSITE DI KAMPUS',
        'execution_time'       => 'SESUAI JADWAL KELAS PERKULIAHAN',
        'participant_division' => 'SEMUA PESERTA DIGABUNG DALAM 1 RUANGAN',
        'question_type'        => 'SOAL TERSEDIA DI LMS',
        'answer_sheet_type'    => 'TIDAK PERLU LEMBAR JAWABAN',
        'needs_scratch_paper'  => 'TIDAK PERLU',
        'proctor_type'         => 'DOSEN PENGAMPU',
        'status'               => 'Approved',
    ]);

    expect($schedule2->isComplete())->toBeFalse();

    // Scenario 3: Missing room_2 / proctor_name_2 when divided into 2
    $schedule3 = ExamSchedule::create([
        'course_id'            => $course1->id,
        'lecturer_code'        => 'DSN001',
        'exam_date'            => '2026-06-10',
        'start_time'           => '08:00',
        'end_time'             => '10:00',
        'room_1'               => 'GD-A201',
        'proctor_name_1'       => 'Proctor A',
        'room_2'               => null,
        'proctor_name_2'       => null,
        'assessment_type'      => 'MENGERJAKAN SOAL UJIAN',
        'execution_method'     => 'DIKERJAKAN ONSITE DI KAMPUS',
        'execution_time'       => 'SESUAI JADWAL KELAS PERKULIAHAN',
        'participant_division' => 'DIBAGI 2 (JIKA PESERTA KELAS > 30)',
        'question_type'        => 'SOAL TERSEDIA DI LMS',
        'answer_sheet_type'    => 'TIDAK PERLU LEMBAR JAWABAN',
        'needs_scratch_paper'  => 'TIDAK PERLU',
        'proctor_type'         => 'DOSEN PENGAMPU',
        'status'               => 'Approved',
    ]);

    expect($schedule3->isComplete())->toBeFalse();

    // Scenario 4: Completed room_2 / proctor_name_2 when divided into 2
    $schedule3->update([
        'room_2' => 'GD-A202',
        'proctor_name_2' => 'Proctor B',
    ]);

    expect($schedule3->fresh()->isComplete())->toBeTrue();

    // Scenario 5: TIDAK ADA ASSESSMEN BERSAMA is always complete
    $schedule4 = ExamSchedule::create([
        'course_id'            => $course1->id,
        'lecturer_code'        => 'DSN001',
        'exam_date'            => null,
        'start_time'           => null,
        'end_time'             => null,
        'room_1'               => null,
        'proctor_name_1'       => null,
        'assessment_type'      => 'TIDAK ADA ASSESSMEN BERSAMA',
        'execution_method'     => 'TIDAK ADA ASSESSMEN BERSAMA',
        'execution_time'       => 'TIDAK ADA ASSESSMEN BERSAMA',
        'participant_division' => 'TIDAK ADA ASSESSMEN BERSAMA',
        'question_type'        => 'TIDAK ADA ASSESSMEN BERSAMA',
        'answer_sheet_type'    => 'TIDAK ADA ASSESSMEN BERSAMA',
        'needs_scratch_paper'  => 'TIDAK PERLU',
        'proctor_type'         => 'TIDAK ADA ASSESSMEN BERSAMA',
        'status'               => 'Approved',
    ]);

    expect($schedule4->isComplete())->toBeTrue();

    // Verify view has status labels
    $response = $this->actingAs($user)->get(route('exam_schedules.index'));
    $response->assertOk();
    $response->assertSee('Lengkap');
    $response->assertSee('Tinjau');
});

test('exam schedule validation requires custom answer sheet url when answer sheet type is LEMBAR JAWABAN CUSTOM (PERLU PENGGANDAAN)', function () {
    $adminUser = User::factory()->create();
    $adminUser->assignRole('admin');

    $course = Course::create([
        'course_code'   => 'IF-101',
        'name'          => 'Pemrograman Web',
        'study_program' => 'Informatika',
        'class'         => 'IF-44-01',
        'semester'      => 4,
        'credits'       => 3,
        'type'          => 'Wajib',
        'status'        => 'Aktif',
    ]);

    $data = [
        'course_id'            => $course->id,
        'lecturer_code'        => 'DSN001',
        'exam_date'            => '2026-06-10',
        'start_time'           => '08:00',
        'end_time'             => '10:00',
        'room_1'               => 'GD-A201',
        'assessment_type'      => 'MENGERJAKAN SOAL UJIAN',
        'execution_method'     => 'DIKERJAKAN ONSITE DI KAMPUS',
        'execution_time'       => 'SESUAI JADWAL KELAS PERKULIAHAN',
        'participant_division' => 'SEMUA PESERTA DIGABUNG DALAM 1 RUANGAN',
        'question_type'        => 'SOAL TERSEDIA DI LMS',
        'answer_sheet_type'    => 'LEMBAR JAWABAN CUSTOM (PERLU PENGGANDAAN)',
        'needs_scratch_paper'  => 'TIDAK PERLU',
        'proctor_type'         => 'DOSEN PENGAMPU',
        'custom_answer_sheet_url' => null,
    ];

    $response = $this->actingAs($adminUser)
        ->post(route('exam_schedules.store'), $data);

    $response->assertSessionHasErrors(['custom_answer_sheet_url']);
});

test('exam schedule validation succeeds when custom answer sheet url is provided for custom answer sheet type', function () {
    $adminUser = User::factory()->create();
    $adminUser->assignRole('admin');

    $course = Course::create([
        'course_code'   => 'IF-101',
        'name'          => 'Pemrograman Web',
        'study_program' => 'Informatika',
        'class'         => 'IF-44-01',
        'semester'      => 4,
        'credits'       => 3,
        'type'          => 'Wajib',
        'status'        => 'Aktif',
    ]);

    $data = [
        'course_id'            => $course->id,
        'lecturer_code'        => 'DSN001',
        'exam_date'            => '2026-06-10',
        'start_time'           => '08:00',
        'end_time'             => '10:00',
        'room_1'               => 'GD-A201',
        'assessment_type'      => 'MENGERJAKAN SOAL UJIAN',
        'execution_method'     => 'DIKERJAKAN ONSITE DI KAMPUS',
        'execution_time'       => 'SESUAI JADWAL KELAS PERKULIAHAN',
        'participant_division' => 'SEMUA PESERTA DIGABUNG DALAM 1 RUANGAN',
        'question_type'        => 'SOAL TERSEDIA DI LMS',
        'answer_sheet_type'    => 'LEMBAR JAWABAN CUSTOM (PERLU PENGGANDAAN)',
        'needs_scratch_paper'  => 'TIDAK PERLU',
        'proctor_type'         => 'DOSEN PENGAMPU',
        'custom_answer_sheet_url' => 'https://drive.google.com/drive/folders/123456',
    ];

    $response = $this->actingAs($adminUser)
        ->post(route('exam_schedules.store'), $data);

    $response->assertRedirect(route('exam_schedules.index'));
    $response->assertSessionHasNoErrors();
});

test('user with read permission can export exam schedule PDF', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $course = Course::create([
        'course_code'   => 'IF-101',
        'name'          => 'Pemrograman Web',
        'study_program' => 'Informatika',
        'class'         => 'IF-44-01',
        'semester'      => 4,
        'credits'       => 3,
        'type'          => 'Wajib',
        'status'        => 'Aktif',
    ]);

    $schedule = ExamSchedule::create([
        'course_id'            => $course->id,
        'lecturer_code'        => 'DSN001',
        'exam_date'            => '2026-06-10',
        'start_time'           => '08:00',
        'end_time'             => '10:00',
        'room_1'               => 'GD-A201',
        'assessment_type'      => 'MENGERJAKAN SOAL UJIAN',
        'execution_method'     => 'DIKERJAKAN ONSITE DI KAMPUS',
        'execution_time'       => 'SESUAI JADWAL KELAS PERKULIAHAN',
        'participant_division' => 'SEMUA PESERTA DIGABUNG DALAM 1 RUANGAN',
        'question_type'        => 'SOAL TERSEDIA DI LMS',
        'answer_sheet_type'    => 'TIDAK PERLU LEMBAR JAWABAN',
        'needs_scratch_paper'  => 'TIDAK PERLU',
        'proctor_type'         => 'DOSEN PENGAMPU',
        'status'               => 'Approved',
    ]);

    $response = $this->actingAs($user)
        ->get(route('exam-schedules.export-pdf', $schedule->id));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    $response->assertHeader('content-disposition', 'attachment; filename=Daftar_Hadir_Ujian_IF-101_IF-44-01_20260610.pdf');
});

test('exam schedule creation works in single mode with course_code and mode parameters', function () {
    $adminUser = User::factory()->create();
    $adminUser->assignRole('admin');

    $course = Course::create([
        'course_code'   => 'IF-101',
        'name'          => 'Pemrograman Web',
        'study_program' => 'Informatika',
        'class'         => 'IF-04-03',
        'semester'      => 4,
        'credits'       => 3,
        'type'          => 'Wajib',
        'status'        => 'Aktif',
    ]);

    $data = [
        'mode'                 => 'single',
        'course_code'          => 'IF-101',
        'course_id'            => $course->id,
        'lecturer_code'        => 'DSN001',
        'date'                 => '2026-06-10',
        'start_time'           => '08:00',
        'end_time'             => '10:00',
        'room_1'               => 'GD-A201',
        'assessment_type'      => 'MENGERJAKAN SOAL UJIAN',
        'execution_method'     => 'DIKERJAKAN ONSITE DI KAMPUS',
        'execution_time'       => 'SESUAI JADWAL KELAS PERKULIAHAN',
        'participant_division' => 'SEMUA PESERTA DIGABUNG DALAM 1 RUANGAN',
        'question_type'        => 'SOAL TERSEDIA DI LMS',
        'answer_sheet_type'    => 'TIDAK PERLU LEMBAR JAWABAN',
        'needs_scratch_paper'  => 'TIDAK PERLU',
        'proctor_type'         => 'DOSEN PENGAMPU',
    ];

    $response = $this->actingAs($adminUser)
        ->post(route('exam_schedules.store'), $data);

    $response->assertRedirect(route('exam_schedules.index'));
    $response->assertSessionHasNoErrors();
    $this->assertDatabaseHas('exam_schedules', [
        'course_id'     => $course->id,
        'lecturer_code' => 'DSN001',
        'room_1'        => 'GD-A201',
        'status'        => 'Approved',
    ]);
});

test('exam schedule creation works in bulk mode with multiple parallel classes', function () {
    $adminUser = User::factory()->create();
    $adminUser->assignRole('admin');

    $course1 = Course::create([
        'course_code'   => 'IF-101',
        'name'          => 'Pemrograman Web',
        'study_program' => 'Informatika',
        'class'         => 'IF-04-03',
        'semester'      => 4,
        'credits'       => 3,
        'type'          => 'Wajib',
        'status'        => 'Aktif',
    ]);

    $course2 = Course::create([
        'course_code'   => 'IF-102',
        'name'          => 'Pemrograman Web Paralel',
        'study_program' => 'Informatika',
        'class'         => 'IF-04-04',
        'semester'      => 4,
        'credits'       => 3,
        'type'          => 'Wajib',
        'status'        => 'Aktif',
    ]);

    $data = [
        'mode'                 => 'all',
        'course_code'          => 'IF-101',
        'lecturer_code'        => 'DSN001',
        'assessment_type'      => 'MENGERJAKAN SOAL UJIAN',
        'execution_method'     => 'DIKERJAKAN ONSITE DI KAMPUS',
        'execution_time'       => 'SESUAI JADWAL KELAS PERKULIAHAN',
        'participant_division' => 'SEMUA PESERTA DIGABUNG DALAM 1 RUANGAN',
        'question_type'        => 'SOAL TERSEDIA DI LMS',
        'answer_sheet_type'    => 'TIDAK PERLU LEMBAR JAWABAN',
        'needs_scratch_paper'  => 'TIDAK PERLU',
        'proctor_type'         => 'DOSEN PENGAMPU',
        'kelas'                => [
            [
                'course_id'  => $course1->id,
                'date'       => '2026-06-10',
                'start_time' => '08:00',
                'end_time'   => '10:00',
                'room_1'     => 'GD-A201',
            ],
            [
                'course_id'  => $course2->id,
                'date'       => '2026-06-10',
                'start_time' => '10:30',
                'end_time'   => '12:30',
                'room_1'     => 'GD-A202',
            ]
        ]
    ];

    $response = $this->actingAs($adminUser)
        ->post(route('exam_schedules.store'), $data);

    $response->assertRedirect(route('exam_schedules.index'));
    $response->assertSessionHasNoErrors();
    $this->assertDatabaseHas('exam_schedules', [
        'course_id'     => $course1->id,
        'lecturer_code' => 'DSN001',
        'room_1'        => 'GD-A201',
        'status'        => 'Approved',
    ]);
    $this->assertDatabaseHas('exam_schedules', [
        'course_id'     => $course2->id,
        'lecturer_code' => 'DSN001',
        'room_1'        => 'GD-A202',
        'status'        => 'Approved',
    ]);
});

test('guest cannot access autocomplete search endpoints', function () {
    $this->get('/api/search/courses?q=CAK')
        ->assertRedirect(route('login'));

    $this->get('/api/search/lecturers?q=Tri')
        ->assertRedirect(route('login'));
});

test('authenticated user gets empty json if autocomplete query is empty or less than 2 characters', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $this->actingAs($user)
        ->get('/api/search/courses?q=')
        ->assertOk()
        ->assertJsonCount(0);

    $this->actingAs($user)
        ->get('/api/search/courses?q=C')
        ->assertOk()
        ->assertJsonCount(0);

    $this->actingAs($user)
        ->get('/api/search/lecturers?q=')
        ->assertOk()
        ->assertJsonCount(0);

    $this->actingAs($user)
        ->get('/api/search/lecturers?q=T')
        ->assertOk()
        ->assertJsonCount(0);
});

test('autocomplete search returns matching distinct courses', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    // Create courses with matching names/codes (distinct course codes to respect database constraints)
    Course::create([
        'course_code' => 'CAK2HAB3', 'name' => 'Dasar Kecerdasan Artifisial', 'study_program' => 'Informatika', 'class' => 'IF-01', 'semester' => 4, 'credits' => 3, 'type' => 'Wajib', 'status' => 'Aktif'
    ]);
    Course::create([
        'course_code' => 'CAK1GAB4', 'name' => 'Algoritma Pemrograman', 'study_program' => 'Informatika', 'class' => 'IF-01', 'semester' => 2, 'credits' => 4, 'type' => 'Wajib', 'status' => 'Aktif'
    ]);
    Course::create([
        'course_code' => 'MAT101', 'name' => 'Kalkulus I', 'study_program' => 'Informatika', 'class' => 'IF-01', 'semester' => 1, 'credits' => 3, 'type' => 'Wajib', 'status' => 'Aktif'
    ]);

    $response = $this->actingAs($user)
        ->get('/api/search/courses?q=CAK')
        ->assertOk()
        ->assertJsonCount(2);

    $json = $response->json();
    $this->assertEquals('CAK2HAB3', $json[0]['course_code']);
    $this->assertEquals('CAK1GAB4', $json[1]['course_code']);
});

test('autocomplete search returns matching lecturers with name validation', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    // Create lecturers
    \App\Models\Lecturer::create(['lecturer_code' => 'FZP', 'name' => 'Tri Arief Sardjono']);
    \App\Models\Lecturer::create(['lecturer_code' => 'BDS', 'name' => 'Budi Santoso']);
    \App\Models\Lecturer::create(['lecturer_code' => 'XYZ', 'name' => '']); // empty name should be skipped
    \App\Models\Lecturer::create(['lecturer_code' => 'ABC', 'name' => null]); // null name should be skipped

    $response = $this->actingAs($user)
        ->get('/api/search/lecturers?q=Tri')
        ->assertOk()
        ->assertJsonCount(1);

    $response->assertJsonFragment([
        'lecturer_code' => 'FZP',
        'name' => 'Tri Arief Sardjono'
    ]);
});






