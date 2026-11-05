<?php

namespace App\Policies;

use App\Models\GraduationRegistration;
use App\Models\ModuleRolePermission;
use App\Models\User;

class GraduationRegistrationPolicy
{
    /**
     * Determine if user can view any graduation registrations.
     */
    public function viewAny(User $user): bool
    {
        $userRoles = $user->getRoleNames()->toArray();
        
        foreach ($userRoles as $role) {
            if (ModuleRolePermission::hasAccess('graduation_registration', $role, 'read')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if user can view specific graduation registration.
     */
    public function view(User $user, GraduationRegistration $registration): bool
    {
        $userRoles = $user->getRoleNames()->toArray();

        foreach ($userRoles as $role) {
            if (!ModuleRolePermission::hasAccess('graduation_registration', $role, 'read')) {
                continue;
            }

            // Cek additional rules untuk scope
            $permission = ModuleRolePermission::where('module_name', 'graduation_registration')
                ->where('role_name', $role)
                ->first();

            if (!$permission) {
                continue;
            }

            $rules = $permission->additional_rules ?? [];
            $scope = $rules['read_scope'] ?? 'own';

            // Jika scope 'all', langsung allow
            if ($scope === 'all') {
                return true;
            }

            // Jika scope 'own', cek apakah milik user sendiri
            if ($scope === 'own') {
                $studentNumber = $this->getStudentNumber($user);
                if ($registration->student_number === $studentNumber) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Determine if user can create graduation registration.
     */
    public function create(User $user): bool
    {
        $userRoles = $user->getRoleNames()->toArray();

        foreach ($userRoles as $role) {
            if (ModuleRolePermission::hasAccess('graduation_registration', $role, 'create')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if user can update graduation registration.
     */
    public function update(User $user, GraduationRegistration $registration): bool
    {
        $userRoles = $user->getRoleNames()->toArray();

        foreach ($userRoles as $role) {
            if (!ModuleRolePermission::hasAccess('graduation_registration', $role, 'update')) {
                continue;
            }

            $permission = ModuleRolePermission::where('module_name', 'graduation_registration')
                ->where('role_name', $role)
                ->first();

            if (!$permission) {
                continue;
            }

            $rules = $permission->additional_rules ?? [];
            $scope = $rules['update_scope'] ?? 'own';

            // Jika scope 'all', langsung allow (tapi tetap cek status constraint jika ada)
            if ($scope === 'all') {
                // Super admin dan pegawai bisa update apapun
                return true;
            }

            // Jika scope 'own', cek ownership dan status constraint
            if ($scope === 'own') {
                $studentNumber = $this->getStudentNumber($user);
                if ($registration->student_number !== $studentNumber) {
                    continue;
                }

                // Cek status constraint (mahasiswa hanya bisa edit jika status tertentu)
                $allowedStatuses = $rules['update_status_constraint'] ?? [];
                if (!empty($allowedStatuses) && !in_array($registration->status, $allowedStatuses)) {
                    continue;
                }

                return true;
            }
        }

        return false;
    }

    /**
     * Determine if user can delete graduation registration.
     */
    public function delete(User $user, GraduationRegistration $registration): bool
    {
        $userRoles = $user->getRoleNames()->toArray();

        foreach ($userRoles as $role) {
            if (ModuleRolePermission::hasAccess('graduation_registration', $role, 'delete')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Helper to get student number from user.
     */
    private function getStudentNumber(User $user): string
    {
        if (!empty($user->employee_id)) {
            return (string) $user->employee_id;
        }

        if (!empty($user->username)) {
            return (string) $user->username;
        }

        return '';
    }
}
