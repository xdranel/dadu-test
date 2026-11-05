<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CacheUserPermissions
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            $user = $request->user();
            
            $permissions = [
                'student_read' => $user->can('student_read'),
                'lecturer_read' => $user->can('lecturer_read'),
                'course_read' => $user->can('course_read'),
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
                'Pelaksana'   => $user->hasRole('Pelaksana'),
                'Pengamat'    => $user->hasRole('Pengamat'),
                'Pemimpin'    => $user->hasRole('Pemimpin'),
                'Petugas'     => $user->hasRole('Petugas'),
            ];

            view()->share(['permissions' => $permissions, 'roles' => $roles]);
        }

        return $next($request);
    }
}
