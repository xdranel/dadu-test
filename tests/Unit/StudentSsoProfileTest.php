<?php

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

test('student profile takes email, phone and photo from linked SSO user', function () {
    User::factory()->create([
        'username' => 'krisnaputra',
        'employee_id' => '103072400079',
        'email' => 'krisnaputra@student.telkomuniversity.ac.id',
        'phone' => '0821000000681',
        'photo' => 'https://images.telkomuniversity.ac.id/foto.jpeg',
        'status' => 'active',
    ]);

    $student = Student::create([
        'student_number' => '103072400079',
        'name' => 'KRISNA',
        'study_program' => 'Informatika',
        'academic_year' => '2024',
        'status' => 'Aktif',
    ]);

    expect($student->institutional_email)->toBe('krisnaputra@student.telkomuniversity.ac.id');
    expect($student->contact_phone)->toBe('0821000000681');
    expect($student->photo_url)->toBe('https://images.telkomuniversity.ac.id/foto.jpeg');
});

test('student profile falls back to own data when no SSO user is linked', function () {
    $student = Student::create([
        'student_number' => '999',
        'name' => 'TANPA SSO',
        'study_program' => 'Informatika',
        'academic_year' => '2024',
        'status' => 'Aktif',
        'email' => 'lokal@example.test',
        'phone_number' => '0800',
    ]);

    expect($student->institutional_email)->toBe('lokal@example.test');
    expect($student->contact_phone)->toBe('0800');
    expect($student->photo_url)->toBeNull();
});

