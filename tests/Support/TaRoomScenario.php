<?php

namespace Tests\Support;

use App\Models\AcademicPeriod;
use App\Models\Room;
use App\Models\RoomScheduleSource;
use App\Models\SidangTaExecutionSetting;
use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\Student;
use App\Models\User;
use Spatie\Permission\Models\Permission;

final class TaRoomScenario
{
    public static function make(): array
    {
        $owner = User::factory()->create(['status' => 'active']);
        $reviewer = User::factory()->create(['status' => 'active']);
        foreach (['sidang_ta_registration_read', 'sidang_ta_registration_create', 'sidang_ta_registration_update', 'sidang_ta_registration_review', 'sidang_ta_period_read', 'sidang_ta_period_update', 'room_booking_review', 'room_update'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $owner->givePermissionTo(['sidang_ta_registration_read', 'sidang_ta_registration_create', 'sidang_ta_registration_update']);
        $reviewer->givePermissionTo(['sidang_ta_registration_review', 'sidang_ta_period_read', 'sidang_ta_period_update', 'room_booking_review', 'room_update']);
        $student = Student::create(['student_number' => '1110001', 'name' => 'Mahasiswa Jadwal Dummy', 'email' => $owner->email, 'study_program' => 'Informatika', 'academic_year' => 2022, 'status' => 'Aktif']);
        $academic = AcademicPeriod::create(['year' => '2026/2027', 'semester' => 1, 'label' => 'Ganjil 2026', 'start_date' => '2026-09-01', 'end_date' => '2027-01-31', 'is_active' => true]);
        $period = SidangTaPeriod::create(['name' => 'Sidang November Dummy', 'open_at' => '2026-11-09 00:00:00', 'close_at' => '2026-11-11 23:59:59', 'is_active' => false, 'revision_open_at' => '2026-11-12 00:00:00', 'revision_close_at' => '2026-11-20 23:59:59', 'is_revision_active' => true, 'show_to_students' => false]);
        $registration = SidangTaRegistration::create(['sidang_ta_period_id' => $period->id, 'student_number' => $student->student_number, 'student_name' => $student->name, 'study_program' => $student->study_program, 'student_phone' => '081234567890', 'parent_phone' => '081234567891', 'graduation_payment_date' => '2026-11-09', 'diploma_biodata_accurate' => true, 'academic_snapshot' => ['advisor_1' => 'DUM', 'admission' => ['eprt' => ['mode' => 'automatic']]], 'status' => 'approved', 'requirements_version' => 2, 'submitted_by' => $owner->id, 'submitted_at' => '2026-11-10 09:00:00', 'reviewed_by' => $reviewer->id, 'reviewed_at' => '2026-11-18 09:00:00']);
        // Explicit legacy fixture: existing 120-minute bookings remain valid after the new 90-minute default.
        $settings = SidangTaExecutionSetting::create(['sidang_ta_period_id' => $period->id, 'academic_period_id' => $academic->id,
            'start_date' => '2026-11-23', 'end_date' => '2026-11-27', 'duration_minutes' => 120,
            'break_start_time' => '12:00:00', 'break_end_time' => '13:00:00']);
        $source = RoomScheduleSource::create(['academic_period_id' => $academic->id, 'is_complete' => true, 'scope' => 'semester', 'fingerprint' => 'dummy-initial', 'version' => 1]);
        $rooms = [];
        foreach (['a' => 'KELAS A', 'b' => 'KELAS B', 'lab' => 'RUANG LAB', 'unknown' => 'RUANG UNKNOWN'] as $name => $roomName) {
            $rooms[$name] = Room::create(['room_code' => strtoupper($name), 'name' => $roomName, 'building' => 'Dummy', 'capacity' => 20, 'status' => 'Dapat Dipakai']);
        }

        return compact('owner', 'reviewer', 'student', 'academic', 'period', 'registration', 'settings', 'source', 'rooms');
    }
}
