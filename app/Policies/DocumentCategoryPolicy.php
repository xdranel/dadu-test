<?php

namespace App\Policies;

use App\Models\DocumentCategory;
use App\Models\User;

class DocumentCategoryPolicy
{
    /**
     * Determine if the user can view any folders (termasuk Guest di portal dokumen).
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the folder.
     * - 'publik'  : Dapat diakses oleh Guest (tanpa login) dan Mahasiswa.
     * - 'internal': Hanya dapat diakses oleh Dosen, Pegawai, dan Superadmin.
     */
    public function view(?User $user, DocumentCategory $category): bool
    {
        $visibility = strtolower($category->visibility ?? 'publik');

        if ($visibility === 'publik') {
            return true;
        }

        if ($visibility === 'internal') {
            return $user !== null && ($user->hasRole('admin') || $user->hasRole('Petugas') || $user->hasRole('Pelaksana') || $user->hasRole('Pemimpin') || $user->hasRole('Pengamat') || $user->hasRole('Akademik'));
        }

        return false;
    }

    /**
     * Determine if the user can create folders.
     * Hanya Superadmin.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine if the user can update the folder.
     * Hanya Superadmin.
     */
    public function update(User $user, DocumentCategory $category): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine if the user can delete the folder.
     * Hanya Superadmin.
     */
    public function delete(User $user, DocumentCategory $category): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine if the user can move a folder to another parent.
     * Hanya Superadmin.
     */
    public function move(User $user, DocumentCategory $category): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine if the user can restore a soft-deleted folder.
     * Hanya Superadmin.
     */
    public function restore(User $user, DocumentCategory $category): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine if the user can permanently delete the folder.
     * Hanya Superadmin.
     */
    public function forceDelete(User $user, DocumentCategory $category): bool
    {
        return $user->hasRole('admin');
    }
}
