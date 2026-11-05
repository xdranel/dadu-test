<?php

use App\Models\User;
use App\Models\GraduationPeriod;
use App\Models\GraduationRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin can see active period countdown banner on graduation registration index', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    // Create an active period that closes tomorrow
    $activePeriod = GraduationPeriod::create([
        'name' => 'Pendaftaran Yudisium Periode 3',
        'open_at' => now()->subDay(),
        'close_at' => now()->addDay(),
        'is_active' => true,
        'description' => 'Test period'
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.graduation-registrations.index'));

    $response->assertOk();
    $response->assertSee('Pendaftaran Yudisium Periode 3');
    $response->assertSee('DAYS');
});

test('countdown banner is not visible if there is no active period', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    // No active period is created

    $response = $this->actingAs($admin)
        ->get(route('admin.graduation-registrations.index'));

    $response->assertOk();
    $response->assertDontSee('DAYS');
});

test('countdown banner shows expired status when active period is closed', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    // Create an active period that closed yesterday
    $activePeriod = GraduationPeriod::create([
        'name' => 'Pendaftaran Yudisium Periode 3',
        'open_at' => now()->subDays(2),
        'close_at' => now()->subDay(),
        'is_active' => true,
        'description' => 'Closed test period'
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.graduation-registrations.index'));

    $response->assertOk();
    $response->assertSee('Pendaftaran Yudisium Periode 3');
    $response->assertSee('Tutup');
});

test('student can view graduation registration history table', function () {
    $student = User::factory()->create();
    $student->assignRole('mahasiswa');

    $response = $this->actingAs($student)
        ->get(route('graduation-registrations.index'));

    $response->assertOk();
    $response->assertSee('Riwayat Pendaftaran');
    $response->assertSee('Tanggal Pengajuan');
    $response->assertSee('Judul Tugas Akhir');
    $response->assertSee('Status');
    $response->assertSee('Aksi');
});

test('student can submit graduation registration with student email and conditional title change', function () {
    $studentUser = User::factory()->create([
        'username' => '1301190001',
        'email' => 'mahasiswa@student.telkomuniversity.ac.id'
    ]);
    $studentUser->assignRole('mahasiswa');

    $activePeriod = GraduationPeriod::create([
        'name' => 'Periode 2025/2026',
        'open_at' => now()->subDay(),
        'close_at' => now()->addDay(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($studentUser)
        ->post(route('graduation-registrations.store'), [
            'action' => 'submit',
            'graduation_period' => 'Periode 2025/2026',
            'student_phone' => '081234567890',
            'parent_phone' => '081987654321',
            'personal_email' => 'mahasiswa@gmail.com',
            'student_email' => 'mahasiswa@student.telkomuniversity.ac.id',
            'academic_advisor_name' => 'Dr. Dosen Wali, M.T.',
            'academic_advisor_code' => 'DDW',
            'has_title_change' => 'ya',
            'sk_update_status' => 'not_processed_laa',
            'sk_update_submission_date' => '2026-07-20',
            'thesis_title_id' => 'Pengembangan Sistem Yudisium',
            'thesis_title_en' => 'Graduation System Development',
            'tak_score' => 88,
        ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('graduation_registrations', [
        'student_email' => '1301190001@student.telkomuniversity.ac.id',
        'has_title_change' => 1,
        'sk_update_status' => 'not_processed_laa',
        'status' => 'submitted',
    ]);
});

test('admin can delete graduation registration submission', function () {
    $admin = User::factory()->create();
    $permission = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'graduation_registration_review']);
    $admin->givePermissionTo($permission);

    $reg = GraduationRegistration::create([
        'student_number' => '1301200001',
        'student_name' => 'Budi Santoso',
        'graduation_period' => 'Periode 2025/2026',
        'thesis_title_id' => 'Judul Test',
        'thesis_title_en' => 'Test Title',
        'status' => 'submitted'
    ]);

    $response = $this->actingAs($admin)
        ->delete('/dashboard/admin/graduation-registrations/' . $reg->id);

    $response->assertRedirect();
    $this->assertDatabaseMissing('graduation_registrations', [
        'id' => $reg->id,
    ]);
});

test('admin can view single submission detail show view', function () {
    $admin = User::factory()->create();
    $permission = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'graduation_registration_review']);
    $admin->givePermissionTo($permission);

    $reg = GraduationRegistration::create([
        'student_number' => '1301200001',
        'student_name' => 'Budi Santoso',
        'graduation_period' => 'Periode 2025/2026',
        'thesis_title_id' => 'Judul Test',
        'thesis_title_en' => 'Test Title',
        'status' => 'submitted'
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.graduation-registrations.show', $reg));

    $response->assertOk();
    $response->assertSee('Budi Santoso');
});

test('admin can preview document inline', function () {
    \Illuminate\Support\Facades\Storage::fake('public');

    $admin = User::factory()->create();
    $permission = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'graduation_registration_read']);
    $admin->givePermissionTo($permission);

    $reg = GraduationRegistration::create([
        'student_number' => '1301200001',
        'student_name' => 'Budi Santoso',
        'graduation_period' => 'Periode 2025/2026',
        'thesis_title_id' => 'Judul Test',
        'thesis_title_en' => 'Test Title',
        'status' => 'submitted'
    ]);

    $file = \Illuminate\Http\UploadedFile::fake()->create('test_document.pdf', 500, 'application/pdf');
    $path = $file->storeAs('graduation_documents', 'test_document.pdf', 'public');

    $doc = $reg->documents()->create([
        'document_type' => 'revision_form',
        'file_name' => 'test_document.pdf',
        'file_path' => $path,
        'file_size' => 500,
        'file_type' => 'pdf',
        'status' => 'pending',
        'uploaded_at' => now(),
    ]);

    $response = $this->actingAs($admin)
        ->get(route('graduation-registration-documents.preview', $doc));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
});

test('student can submit graduation registration with honors predicate cumlaude and criteria hki', function () {
    \Illuminate\Support\Facades\Storage::fake('public');

    $studentUser = User::factory()->create([
        'username' => '1301190001',
        'email' => 'mahasiswa@student.telkomuniversity.ac.id'
    ]);
    $studentUser->assignRole('mahasiswa');

    $activePeriod = GraduationPeriod::create([
        'name' => 'Periode 2025/2026',
        'open_at' => now()->subDay(),
        'close_at' => now()->addDay(),
        'is_active' => true,
    ]);

    $file = \Illuminate\Http\UploadedFile::fake()->create('persyaratan_cumlaude.pdf', 1000, 'application/pdf');

    $response = $this->actingAs($studentUser)
        ->post(route('graduation-registrations.store'), [
            'action' => 'submit',
            'graduation_period' => 'Periode 2025/2026',
            'student_phone' => '081234567890',
            'parent_phone' => '081987654321',
            'personal_email' => 'mahasiswa@gmail.com',
            'student_email' => 'mahasiswa@student.telkomuniversity.ac.id',
            'thesis_title_id' => 'Sistem Informasi Yudisium',
            'thesis_title_en' => 'Graduation Information System',
            'honors_predicate' => 'cumlaude',
            'predicate_criteria' => 'hki',
            'predicate_umkm_partner' => 'PT Karya UMKM Mandiri',
            'predicate_document' => $file,
        ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('graduation_registrations', [
        'student_email' => '1301190001@student.telkomuniversity.ac.id',
        'honors_predicate' => 'cumlaude',
        'predicate_criteria' => 'hki',
        'predicate_umkm_partner' => 'PT Karya UMKM Mandiri',
        'status' => 'submitted',
    ]);
});

test('admin can change review status of graduation registration', function () {
    $admin = User::factory()->create();
    $permission = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'graduation_registration_review']);
    $admin->givePermissionTo($permission);

    $reg = GraduationRegistration::create([
        'student_number' => '1301200001',
        'student_name' => 'Budi Santoso',
        'graduation_period' => 'Periode 2025/2026',
        'thesis_title_id' => 'Judul Test',
        'thesis_title_en' => 'Test Title',
        'status' => 'submitted'
    ]);

    $response = $this->actingAs($admin)
        ->post(route('admin.graduation-registrations.review', $reg), [
            'status' => 'approved',
            'review_notes' => 'Berkas lengkap dan sesuai.',
        ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('graduation_registrations', [
        'id' => $reg->id,
        'status' => 'approved',
        'review_notes' => 'Berkas lengkap dan sesuai.',
    ]);
});

test('backend blocks submission if student is ineligible due to failed courses or tak score', function () {
    $studentUser = User::factory()->create([
        'username' => '1301199999', // Ineligible mock student number
        'email' => 'mahasiswa_ineligible@student.telkomuniversity.ac.id'
    ]);
    $studentUser->assignRole('mahasiswa');

    $activePeriod = GraduationPeriod::create([
        'name' => 'Periode 2025/2026',
        'open_at' => now()->subDay(),
        'close_at' => now()->addDay(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($studentUser)
        ->post(route('graduation-registrations.store'), [
            'action' => 'submit',
            'graduation_period' => 'Periode 2025/2026',
            'student_phone' => '081234567890',
            'parent_phone' => '081987654321',
            'personal_email' => 'mahasiswa@gmail.com',
            'student_email' => 'mahasiswa_ineligible@student.telkomuniversity.ac.id',
            'thesis_title_id' => 'Judul Ineligible',
        ]);

    $response->assertSessionHas('error');
    $this->assertDatabaseMissing('graduation_registrations', [
        'student_number' => '1301199999',
        'thesis_title_id' => 'Judul Ineligible',
    ]);
});

