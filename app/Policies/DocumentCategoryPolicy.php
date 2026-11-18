<?php

namespace App\Policies;

use App\Enums\AccessGroup;
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
     */
    public function view(?User $user, DocumentCategory $category): bool
    {
        // 1. Kategori dengan access_group
        if ($category->access_group !== null) {
            $ag = $category->access_group instanceof AccessGroup
                ? $category->access_group->value
                : (string) $category->access_group;

            if ($ag === AccessGroup::PUBLIK->value) {
                return $user === null || $user->can('public_document_read');
            }

            if ($user === null) {
                return false;
            }

            // Superadmin (Akademik) & Admin operasional
            if ($user->hasRole(['admin', 'Admin', 'Akademik', 'superadmin', 'Superadmin'])) {
                return true;
            }

            // dosen_staf: Pelaksana, Pemimpin, Pengamat, Petugas
            if ($ag === AccessGroup::DOSEN_STAF->value) {
                return $user->hasRole(['Pelaksana', 'Pemimpin', 'Pengamat', 'pelaksana', 'pemimpin', 'pengamat', 'Petugas', 'petugas']);
            }

            // Folder kategori mahasiswa: hanya staf berwenang (admin / Akademik) yang melihat struktur foldernya
            if ($ag === AccessGroup::MAHASISWA->value) {
                return false;
            }

            return false;
        }

        // 2. Fallback visibilitas lama (access_group is null)
        $visibility = strtolower($category->visibility ?? 'publik');

        if ($visibility === 'publik') {
            return $user === null || $user->can('public_document_read');
        }

        if ($user === null) {
            return false;
        }

        if ($visibility === 'internal') {
            if ($user->hasRole(['admin', 'Admin', 'Akademik', 'superadmin', 'Superadmin', 'Pelaksana', 'Pemimpin', 'Pengamat', 'pelaksana', 'pemimpin', 'pengamat', 'Petugas', 'petugas'])) {
                return true;
            }
            return $user->can('document_category_read');
        }

        if ($visibility === 'private') {
            return $user->hasRole(['admin', 'Admin', 'Akademik', 'superadmin', 'Superadmin']);
        }

        return false;
    }

    /**
     * Determine if the user can create folders.
     * Memakai Spatie permission 'document_category_create'.
     * Role Petugas hanya diizinkan membuat folder di kategori publik.
     */
    public function create(User $user, ?DocumentCategory $parent = null): bool
    {
        if (! $user->can('document_category_create')) {
            return false;
        }

        if ($user->hasRole(['admin', 'Admin', 'Akademik', 'superadmin', 'Superadmin'])) {
            return true;
        }

        if ($user->hasRole(['Petugas', 'petugas'])) {
            if ($parent !== null) {
                $ag = $parent->access_group instanceof AccessGroup ? $parent->access_group->value : $parent->access_group;
                if ($ag !== null && $ag !== AccessGroup::PUBLIK->value) {
                    return false;
                }
                if ($ag === null && strtolower($parent->visibility ?? '') !== 'publik') {
                    return false;
                }
            }
            return true;
        }

        return true;
    }

    /**
     * Determine if the user can update the folder.
     */
    public function update(User $user, ?DocumentCategory $category = null): bool
    {
        if (! $user->can('document_category_update')) {
            return false;
        }

        if ($user->hasRole(['admin', 'Admin', 'Akademik', 'superadmin', 'Superadmin'])) {
            return true;
        }

        if ($user->hasRole(['Petugas', 'petugas']) && $category !== null) {
            $ag = $category->access_group instanceof AccessGroup ? $category->access_group->value : $category->access_group;
            if ($ag !== null && $ag !== AccessGroup::PUBLIK->value) {
                return false;
            }
            if ($ag === null && strtolower($category->visibility ?? '') !== 'publik') {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine if the user can delete the folder.
     */
    public function delete(User $user, ?DocumentCategory $category = null): bool
    {
        if (! $user->can('document_category_delete')) {
            return false;
        }

        if ($user->hasRole(['admin', 'Admin', 'Akademik', 'superadmin', 'Superadmin'])) {
            return true;
        }

        if ($user->hasRole(['Petugas', 'petugas']) && $category !== null) {
            $ag = $category->access_group instanceof AccessGroup ? $category->access_group->value : $category->access_group;
            if ($ag !== null && $ag !== AccessGroup::PUBLIK->value) {
                return false;
            }
            if ($ag === null && strtolower($category->visibility ?? '') !== 'publik') {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine if the user can move a folder to another parent.
     */
    public function move(User $user, ?DocumentCategory $category = null): bool
    {
        return $this->update($user, $category);
    }

    /**
     * Determine if the user can restore a soft-deleted folder.
     */
    public function restore(User $user, ?DocumentCategory $category = null): bool
    {
        return $this->delete($user, $category);
    }

    /**
     * Determine if the user can permanently delete the folder.
     */
    public function forceDelete(User $user, ?DocumentCategory $category = null): bool
    {
        return $this->delete($user, $category);
    }
}
