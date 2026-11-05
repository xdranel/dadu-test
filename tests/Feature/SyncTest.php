<?php

use App\Models\User;
use App\Models\Student;
use App\Models\StudentCourse;
use App\Models\StudentAttendance;
use App\Models\SyncLog;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(RolePermissionSeeder::class);
});

test('guest cannot access sync index or sync import', function () {
    $this->get(route('sync.index'))
        ->assertRedirect(route('login'));

    $this->post(route('sync.import-json'), [])
        ->assertRedirect(route('login'));
});

test('admin can access sync index', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Akademik');

    $this->actingAs($admin)
        ->get(route('sync.index'))
        ->assertOk();
});

test('admin can import valid student JSON', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Akademik');

    $jsonData = json_encode([
        [
            'nim' => '1202200001',
            'nama_siswa' => 'Krisna Putra',
            'kelas' => 'IF-44-01',
            'kelamin' => 'Laki-laki',
            'agama' => 'Islam',
            'prodi' => 'Informatika'
        ]
    ]);

    $response = $this->actingAs($admin)
        ->post(route('sync.import-json'), [
            'type' => 'student',
            'json_text' => $jsonData
        ]);

    $response->assertRedirect(route('sync.index'));
    $response->assertSessionHasNoErrors();
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('students', [
        'student_number' => '1202200001',
        'name' => 'Krisna Putra',
        'class_name' => 'IF-44-01',
        'gender' => 'Laki-laki',
        'religion' => 'Islam',
        'study_program' => 'Informatika'
    ]);

    $this->assertDatabaseHas('sync_logs', [
        'module' => 'student',
        'status' => 'completed',
        'total_records' => 1,
        'processed_records' => 1,
    ]);
});

test('admin can import flat array Course JSON', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Akademik');

    // Create the lecturers to satisfy the FK constraint
    \App\Models\Lecturer::create(['lecturer_code' => 'CAP', 'name' => 'Lecturer CAP', 'is_external' => false]);
    \App\Models\Lecturer::create(['lecturer_code' => 'FZP', 'name' => 'Lecturer FZP', 'is_external' => false]);
    \App\Models\Lecturer::create(['lecturer_code' => '2526', 'name' => 'Lecturer 2526', 'is_external' => false]);

    $jsonData = json_encode([
        [
            "11138",                  // 0: course_id
            "CDK1GAB4",               // 1: course_code
            "ALGORITMA PEMROGRAMAN",  // 2: name
            "DS-05-01",               // 3: class
            "4",                      // 4: credits
            "40",                     // 5: capacity
            "4",                      // 6: semester
            "4",                      // 7: ..
            "0",                      // 8: ..
            "36",                     // 9: jumlah mhs
            "CAP",                    // 10: study_program / coordinator code
            "DOSEN PEGAWAI TETAP",    // 11: status dosen
            "FZP",                    // 12: koor dosen / team lecturer code
            "GENAP",                  // 13: semester term
            "2526",                   // 14: akademik
            "TIDAK",                  // 15: ..
            "YA",                     // 16: ..
            "",                       // 17: ..
            "ONSITE",                 // 18: pembelajaran
            "TIDAK",                  // 19: ..
            "TIDAK",                  // 20: ..
            "FZP",                    // 21: ..
            ""                        // 22: ..
        ]
    ]);

    $response = $this->actingAs($admin)
        ->post(route('sync.import-json'), [
            'type' => 'course',
            'json_text' => $jsonData
        ]);

    $response->assertRedirect(route('sync.index'));
    $response->assertSessionHasNoErrors();

    $this->assertDatabaseHas('courses', [
        'course_id' => '11138',
        'course_code' => 'CDK1GAB4',
        'name' => 'ALGORITMA PEMROGRAMAN',
        'class' => 'DS-05-01',
        'study_program' => 'Umum',
        'semester' => 4,
        'semester_term' => 'GENAP',
        'academic_year' => '2025/2026',
        'credits' => 4,
        'status_dosen' => 'DOSEN PEGAWAI TETAP',
        'learning_method' => 'ONSITE',
        'type' => 'Wajib',
        'status' => 'Aktif',
    ]);

    $this->assertDatabaseMissing('course_lecturers', [
        'course_code' => 'CDK1GAB4',
        'lecturer_code' => 'CAP',
        'role' => 'coordinator',
    ]);

    $this->assertDatabaseHas('course_lecturers', [
        'course_code' => 'CDK1GAB4',
        'lecturer_code' => '2526',
        'role' => 'team_member',
    ]);
});

test('admin can import associative Course JSON', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Akademik');

    // Create the lecturers to satisfy the FK constraint
    \App\Models\Lecturer::create(['lecturer_code' => 'CAP', 'name' => 'Lecturer CAP', 'is_external' => false]);
    \App\Models\Lecturer::create(['lecturer_code' => 'FZP', 'name' => 'Lecturer FZP', 'is_external' => false]);

    $jsonData = json_encode([
        [
            'course_id' => '11138',
            'course_code' => 'CDK1GAB4',
            'name' => 'ALGORITMA PEMROGRAMAN',
            'class' => 'DS-05-01',
            'study_program' => 'CAP',
            'semester' => 4,
            'semester_term' => 'GENAP',
            'academic_year' => '2025/2026',
            'credits' => 4,
            'enrolled_count' => 36,
            'status_dosen' => 'DOSEN PEGAWAI TETAP',
            'learning_method' => 'ONSITE',
            'kode_koordinator' => 'CAP',
            'kode_anggota_tim_dosen' => 'FZP',
            'type' => 'Wajib',
            'status' => 'Aktif',
        ]
    ]);

    $response = $this->actingAs($admin)
        ->post(route('sync.import-json'), [
            'type' => 'course',
            'json_text' => $jsonData
        ]);

    $response->assertRedirect(route('sync.index'));
    $response->assertSessionHasNoErrors();

    $this->assertDatabaseHas('courses', [
        'course_id' => '11138',
        'course_code' => 'CDK1GAB4',
        'name' => 'ALGORITMA PEMROGRAMAN',
        'class' => 'DS-05-01',
        'study_program' => 'CAP',
        'semester' => 4,
        'credits' => 4,
        'semester_term' => 'GENAP',
        'academic_year' => '2025/2026',
        'status_dosen' => 'DOSEN PEGAWAI TETAP',
        'learning_method' => 'ONSITE',
    ]);

    $this->assertDatabaseMissing('course_lecturers', [
        'course_code' => 'CDK1GAB4',
        'lecturer_code' => 'CAP',
        'role' => 'coordinator',
    ]);

    $this->assertDatabaseHas('course_lecturers', [
        'course_code' => 'CDK1GAB4',
        'lecturer_code' => 'FZP',
        'role' => 'team_member',
    ]);
});

test('admin can import valid KRS JSON when student exists', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Akademik');

    // Create student first to avoid FK / existence constraint skip
    Student::create([
        'student_number' => '1202200001',
        'name' => 'Krisna Putra',
        'study_program' => 'Informatika',
        'academic_year' => '2022',
        'email' => 'krisna@example.com',
        'status' => 'Aktif'
    ]);

    // Create course first to satisfy FK constraint in student_courses
    \App\Models\Course::create([
        'course_code' => 'IF-101',
        'name' => 'Pemrograman Web',
        'study_program' => 'Informatika',
        'class' => 'A',
        'semester' => 4,
        'credits' => 3,
        'type' => 'Wajib',
        'status' => 'Aktif'
    ]);

    $jsonData = json_encode([
        [
            'nim' => '1202200001',
            'kode_matakuliah' => 'IF-101',
            'nama_matakuliah' => 'Pemrograman Web',
            'kelas_matakuliah' => 'IF-44-01',
            'kode_dosen' => 'DSN001',
            'nama_dosen' => 'Dosen A',
            'sks' => 3,
            'tahun_akademik' => '2025/2026',
            'semester' => 4
        ]
    ]);

    $response = $this->actingAs($admin)
        ->post(route('sync.import-json'), [
            'type' => 'krs',
            'json_text' => $jsonData
        ]);

    $response->assertRedirect(route('sync.index'));
    $response->assertSessionHasNoErrors();

    $this->assertDatabaseHas('student_courses', [
        'student_number' => '1202200001',
        'course_code' => 'IF-101',
        'course_name' => 'Pemrograman Web',
        'course_class' => 'IF-44-01',
        'lecturer_code' => 'DSN001',
        'lecturer_name' => 'Dosen A',
        'credits' => 3,
        'academic_year' => '2025/2026',
        'semester' => 4,
        'study_program' => 'Informatika',
    ]);

    $this->assertDatabaseHas('sync_logs', [
        'module' => 'krs',
        'status' => 'completed',
        'total_records' => 1,
        'processed_records' => 1,
    ]);
});

test('admin can import flat array KRS JSON and automatically create or update student profile', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Akademik');

    // Create course first to satisfy FK constraint in student_courses
    \App\Models\Course::create([
        'course_code' => 'UAKXDCB2',
        'name' => 'AGAMA HINDU',
        'study_program' => 'Umum',
        'class' => 'A',
        'semester' => 2,
        'credits' => 2,
        'type' => 'Wajib',
        'status' => 'Aktif'
    ]);

    // Prepare flat array KRS JSON payload wrapped in aaData (matching the user's example format)
    $jsonData = json_encode([
        'sEcho' => 16,
        'iTotalRecords' => 1693,
        'iTotalDisplayRecords' => 1693,
        'aaData' => [
            [
                "102052400067",                           // 0: nim
                "NI KADEK ALYA CANDRA DEWI",              // 1: name
                "IT-08-GAB",                              // 2: Kelas
                "WANITA",                                 // 3: gender
                "HINDU",                                  // 4: religion
                "S1 Teknologi Informasi - Kampus Surabaya", // 5: study_program
                "DKS",                                    // 6: ignored
                "UAKXDCB2",                               // 7: course_code
                "AGAMA HINDU",                            // 8: course_name
                "XGP",                                    // 9: lecturer_code
                "2526",                                   // 10: academic_year (raw)
                "2",                                      // 11: semester
                "119",                                    // 12: prodi ID
                "10",                                     // 13: studentType
                "TI-07-02"                                // 14: Kelas Mata Kuliah / KRS
            ]
        ]
    ]);

    $response = $this->actingAs($admin)
        ->post(route('sync.import-json'), [
            'type' => 'krs',
            'json_text' => $jsonData
        ]);

    $response->assertRedirect(route('sync.index'));
    $response->assertSessionHasNoErrors();

    // Verify student is automatically created/updated with correct fields
    $this->assertDatabaseHas('students', [
        'student_number' => '102052400067',
        'name' => 'NI KADEK ALYA CANDRA DEWI',
        'class_name' => 'IT-08-GAB', // Index 2: Kelas
        'gender' => 'Perempuan',
        'religion' => 'Hindu',
        'study_program' => 'S1 Teknologi Informasi - Kampus Surabaya',
        'academic_advisor' => null, // Omitted/not updated from this JSON format
        'academic_year' => 2024, // extracted from length 12 NIM: index 5 is '24' -> 2024
        'active_semester' => 2,
    ]);

    // Verify course is created/updated
    $this->assertDatabaseHas('student_courses', [
        'student_number' => '102052400067',
        'course_code' => 'UAKXDCB2',
        'course_name' => 'AGAMA HINDU',
        'course_class' => 'TI-07-02', // Index 14: Kelas Mata Kuliah / KRS
        'lecturer_code' => 'XGP',
        'academic_year' => '2025/2026', // converted from '2526'
        'semester' => 2,
        'quota' => null,
        'credits' => 2, // fallback for course containing AGAMA
        'study_program' => 'S1 Teknologi Informasi - Kampus Surabaya',
    ]);

    // Verify course master is automatically updated by the auto-sync trigger
    $this->assertDatabaseHas('courses', [
        'course_code' => 'UAKXDCB2',
        'study_program' => 'S1 Teknologi Informasi - Kampus Surabaya',
    ]);
});

test('admin can import valid Attendance JSON when student exists', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Akademik');

    // Create student first to avoid foreign key violation
    Student::create([
        'student_number' => '1202200001',
        'name' => 'Krisna Putra',
        'study_program' => 'Informatika',
        'academic_year' => '2022',
        'email' => 'krisna@example.com',
        'status' => 'Aktif'
    ]);

    // Create course first to satisfy FK constraint in student_attendances
    \App\Models\Course::create([
        'course_code' => 'IF-101',
        'name' => 'Pemrograman Web',
        'study_program' => 'Informatika',
        'class' => 'A',
        'semester' => 4,
        'credits' => 3,
        'type' => 'Wajib',
        'status' => 'Aktif'
    ]);

    $jsonData = json_encode([
        [
            'nim' => '1202200001',
            'kode_matakuliah' => 'IF-101',
            'nama_matakuliah' => 'Pemrograman Web',
            'kelas' => 'IF-44-01',
            'jumlah_hadir' => 14,
            'jumlah_pertemuan' => 16,
            'tahun_akademik' => '2025/2026',
            'semester' => 4
        ]
    ]);

    $response = $this->actingAs($admin)
        ->post(route('sync.import-json'), [
            'type' => 'attendance',
            'json_text' => $jsonData
        ]);

    $response->assertRedirect(route('sync.index'));
    $response->assertSessionHasNoErrors();

    $this->assertDatabaseHas('student_attendances', [
        'student_number' => '1202200001',
        'course_code' => 'IF-101',
        'course_name' => 'Pemrograman Web',
        'class' => 'IF-44-01',
        'attended_count' => 14,
        'meetings_count' => 16,
        'academic_year' => '2025/2026',
        'semester' => 4
    ]);

    $this->assertDatabaseHas('sync_logs', [
        'module' => 'attendance',
        'status' => 'completed',
        'total_records' => 1,
        'processed_records' => 1,
    ]);
});

test('sync import skips rows if student does not exist for KRS and Attendance', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Akademik');

    // We do NOT create the student record here

    $jsonData = json_encode([
        [
            'nim' => '1202209999', // Non-existent student
            'kode_matakuliah' => 'IF-101',
            'nama_matakuliah' => 'Pemrograman Web',
            'kelas_matakuliah' => 'IF-44-01',
            'kode_dosen' => 'DSN001',
            'nama_dosen' => 'Dosen A',
            'sks' => 3,
            'tahun_akademik' => '2025/2026',
            'semester' => 4
        ]
    ]);

    $response = $this->actingAs($admin)
        ->post(route('sync.import-json'), [
            'type' => 'krs',
            'json_text' => $jsonData
        ]);

    $response->assertRedirect(route('sync.index'));
    $this->assertDatabaseMissing('student_courses', [
        'student_number' => '1202209999'
    ]);

    $this->assertDatabaseHas('sync_logs', [
        'module' => 'krs',
        'status' => 'completed',
        'total_records' => 1,
        'processed_records' => 0,
        'skipped_records' => 1,
    ]);
});

test('student update cascades student_number to child tables', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Akademik');

    $student = Student::create([
        'student_number' => '1202200001',
        'name' => 'Krisna Putra',
        'study_program' => 'Informatika',
        'academic_year' => '2022',
        'email' => 'krisna@example.com',
        'status' => 'Aktif'
    ]);

    // Create course first to satisfy FK constraint in student_courses and student_attendances
    \App\Models\Course::create([
        'course_code' => 'IF-101',
        'name' => 'Pemrograman Web',
        'study_program' => 'Informatika',
        'class' => 'A',
        'semester' => 4,
        'credits' => 3,
        'type' => 'Wajib',
        'status' => 'Aktif'
    ]);

    StudentCourse::create([
        'student_number'  => '1202200001',
        'course_code'     => 'IF-101',
        'course_name'     => 'Pemrograman Web',
        'course_class'    => 'IF-44-01',
        'academic_year'   => '2025/2026',
        'semester'        => 4,
        'credits'         => 3,
    ]);

    StudentAttendance::create([
        'student_number'  => '1202200001',
        'course_code'     => 'IF-101',
        'course_name'     => 'Pemrograman Web',
        'class'           => 'IF-44-01',
        'attended_count'  => 14,
        'meetings_count'  => 16,
        'academic_year'   => '2025/2026',
        'semester'        => 4,
    ]);

    // Perform student number update
    $response = $this->actingAs($admin)
        ->put(route('students.update', $student->id), [
            'student_number'  => '1202200099', // updated nim
            'name'            => 'Krisna Putra',
            'study_program'   => 'Informatika',
            'academic_year'   => '2022',
            'email'           => 'krisna@example.com',
            'status'          => 'Aktif',
        ]);

    $response->assertRedirect(route('students.index'));
    $response->assertSessionHasNoErrors();

    // Verify student updated
    $this->assertDatabaseHas('students', [
        'id'             => $student->id,
        'student_number' => '1202200099',
    ]);

    // Verify course cascaded
    $this->assertDatabaseHas('student_courses', [
        'student_number' => '1202200099',
        'course_code'    => 'IF-101',
    ]);

    // Verify attendance cascaded
    $this->assertDatabaseHas('student_attendances', [
        'student_number' => '1202200099',
        'course_code'    => 'IF-101',
    ]);
});

test('admin can import graduation JSON payload', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $jsonData = json_encode([
        [
            'nim' => '1202209999',
            'nama' => 'Budi Yudisium',
            'prodi' => 'S1 Rekayasa Perangkat Lunak',
            'email' => 'budi@student.telkomuniversity.ac.id',
            'dosen_wali_nama' => 'Dr. Dosen Wali',
            'dosen_wali_kode' => 'DDW',
            'judul_ta_id' => 'Sistem Informasi Yudisium Kampus',
            'judul_ta_en' => 'Campus Graduation Information System',
            'pembimbing_1_kode' => 'P1',
            'pembimbing_1_nama' => 'Pembimbing Satu',
            'penguji_1_kode' => 'U1',
            'penguji_1_nama' => 'Penguji Satu',
            'penguji_2_kode' => 'U2',
            'penguji_2_nama' => 'Penguji Dua',
            'total_tak' => 75,
            'skor_eprt' => 500,
            'ipk' => 3.75,
            'sks_lulus' => 144,
            'sks_belum_lulus' => 0,
            'status_keuangan' => 'LUNAS',
            'status_foto_ibasila' => 'approved',
            'status_sidang_tingkat_3' => 'passed',
            'tgl_sidang' => '2026-08-15',
        ]
    ]);

    $response = $this->actingAs($admin)
        ->post(route('sync.import-json'), [
            'type' => 'graduation',
            'json_text' => $jsonData
        ]);

    $response->assertRedirect(route('sync.index'));
    $response->assertSessionHasNoErrors();
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('graduation_registrations', [
        'student_number' => '1202209999',
        'student_name' => 'Budi Yudisium',
        'gpa' => 3.75,
        'passed_credits' => 144,
        'remaining_credits_db' => 0,
        'payment_status' => 'LUNAS',
        'ibasila_photo_approval_status' => 'approved',
        'level_3_exam_status' => 'passed',
    ]);
});

