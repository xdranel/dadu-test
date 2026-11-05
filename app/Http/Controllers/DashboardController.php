<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $permissions = [
            'student_read' => $user->can('student_read'),
            'lecturer_read' => $user->can('lecturer_read'),
            'course_read' => $user->can('course_read'),
            'student_read_krs' => $user->can('student_read'),
            'jadwal_read' => $user->can('jadwal_read'),
            'room_read' => $user->can('room_read'),
            'document_category_read' => $user->can('document_category_read'),
            'document_read' => $user->can('document_read'),
            'public_document_read' => $user->can('public_document_read'),
            'graduation_registration_create' => $user->can('graduation_registration_create'),
            'graduation_registration_review' => $user->can('graduation_registration_review'),
            'grade_appeal_create' => $user->can('grade_appeal_create'),
            'grade_appeal_review' => $user->can('grade_appeal_review'),
            'queue_ticket_read' => $user->can('queue_ticket_read'),
        ];

        $roles = [
            'mahasiswa'   => $user->hasRole('mahasiswa'),
            'Akademik'    => $user->hasRole('Akademik'),
            'admin'       => $user->hasRole('admin'),
            'Petugas'     => $user->hasRole('Petugas'),
            'Pelaksana'   => $user->hasRole('Pelaksana'),
            'Pemimpin'    => $user->hasRole('Pemimpin'),
            'Pengamat'    => $user->hasRole('Pengamat'),
            'admin_or_staf_ssc' => $user->hasAnyRole('Akademik', 'admin', 'Petugas', 'Pelaksana'),
        ];
        $roles['admin_or_pegawai'] = $roles['admin_or_staf_ssc'];

        $activePeriod = \App\Models\GraduationPeriod::where('is_active', true)->first();

        $studentNumber = $user->student_number ?? $user->username ?? '';
        $myGraduationRegistration = null;
        if (($roles['mahasiswa'] ?? false) && $studentNumber) {
            $studentRegistrations = \App\Models\GraduationRegistration::where('student_number', $studentNumber);
            $myGraduationRegistration = (clone $studentRegistrations)
                ->where('status', \App\Models\GraduationRegistration::STATUS_APPROVED)
                ->latest()
                ->first()
                ?? (clone $studentRegistrations)
                    ->whereIn('status', [
                        \App\Models\GraduationRegistration::STATUS_SUBMITTED,
                        \App\Models\GraduationRegistration::STATUS_NEEDS_REVIEW,
                    ])
                    ->latest()
                    ->first();
        }

        $graduationStats = null;
        if ($roles['admin_or_pegawai'] ?? false) {
            $graduationStats = [
                'total'        => \App\Models\GraduationRegistration::count(),
                'needs_review' => \App\Models\GraduationRegistration::where('status', \App\Models\GraduationRegistration::STATUS_NEEDS_REVIEW)->count(),
                'submitted'    => \App\Models\GraduationRegistration::where('status', \App\Models\GraduationRegistration::STATUS_SUBMITTED)->count(),
                'approved'     => \App\Models\GraduationRegistration::where('status', \App\Models\GraduationRegistration::STATUS_APPROVED)->count(),
                'rejected'     => \App\Models\GraduationRegistration::where('status', \App\Models\GraduationRegistration::STATUS_REJECTED)->count(),
            ];
        }

        return view('dashboard', compact(
            'permissions', 'roles', 'activePeriod', 'myGraduationRegistration', 'graduationStats'
        ));
    }
}
