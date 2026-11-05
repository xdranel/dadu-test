<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    /**
     * Determine if the user can view any documents (termasuk Guest di portal dokumen).
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the document.
     * - 'publik'  : Dapat diakses oleh Guest (tanpa login) dan Mahasiswa.
     * - 'internal': Hanya dapat diakses oleh Dosen, Pegawai, dan Superadmin.
     */
    public function view(?User $user, Document $document): bool
    {
        $visibility = strtolower($document->visibility ?? '');

        if ($visibility === 'publik') {
            return true;
        }

        if ($visibility === 'internal') {
            return $user !== null && ($user->hasRole('admin') || $user->hasRole('Petugas') || $user->hasRole('Pelaksana') || $user->hasRole('Pemimpin') || $user->hasRole('Pengamat') || $user->hasRole('Akademik'));
        }

        return false;
    }

    /**
     * Determine if the user can download the document.
     * Mengikuti aturan yang sama dengan view.
     */
    public function download(?User $user, Document $document): bool
    {
        return $this->view($user, $document);
    }

    /**
     * Determine if the user can create documents.
     * Hanya Superadmin.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine if the user can update the document.
     * Hanya Superadmin.
     */
    public function update(User $user, Document $document): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine if the user can delete the document.
     * Hanya Superadmin.
     */
    public function delete(User $user, Document $document): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine if the user can restore a soft-deleted document.
     * Hanya Superadmin.
     */
    public function restore(User $user, Document $document): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine if the user can permanently delete the document.
     * Hanya Superadmin.
     */
    public function forceDelete(User $user, Document $document): bool
    {
        return $user->hasRole('admin');
    }
}
