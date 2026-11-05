<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('student_read') || $user->hasRole('Akademik');
    }

    public function view(User $user, Student $student): bool
    {
        if ($user->hasRole('Akademik') || $user->hasPermissionTo('student_read')) return true;

        return $user->email === $student->email;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('student_create') || $user->hasRole('Akademik');
    }

    public function update(User $user, Student $student): bool
    {
        return $user->hasPermissionTo('student_update') || $user->hasRole('Akademik');
    }

    public function delete(User $user, Student $student): bool
    {
        return $user->hasPermissionTo('student_delete') || $user->hasRole('Akademik');
    }
}
