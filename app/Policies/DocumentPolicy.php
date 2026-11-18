<?php

namespace App\Policies;

use App\Enums\AccessGroup;
use App\Models\Document;
use App\Models\DocumentCategory;
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
     * Matriks Hak Akses:
     * - publik     : Guest, Mahasiswa, Petugas, Staf, Admin
     * - dosen_staf : Pelaksana, Pemimpin, Pengamat, Petugas, Admin
     * - akademik   : Admin & Akademik (superadmin)
     * - mahasiswa  : Admin, Akademik, dan Mahasiswa pemilik (NIM cocok sebagai string)
     */
    public function view(?User $user, Document $document): bool
    {
        // 1. Dokumen dengan access_group
        if ($document->access_group !== null) {
            $ag = $document->access_group instanceof AccessGroup
                ? $document->access_group->value
                : (string) $document->access_group;

            if ($ag === AccessGroup::PUBLIK->value) {
                return $user === null || $user->can('public_document_read');
            }

            if ($user === null) {
                return false;
            }

            // Superadmin (Akademik) dan Admin operasional boleh melihat semua
            if ($user->hasRole(['admin', 'Admin', 'Akademik', 'superadmin', 'Superadmin'])) {
                return true;
            }

            // Kategori dosen_staf: Pelaksana, Pemimpin, Pengamat, Petugas
            if ($ag === AccessGroup::DOSEN_STAF->value) {
                return $user->hasRole(['Pelaksana', 'Pemimpin', 'Pengamat', 'pelaksana', 'pemimpin', 'pengamat', 'Petugas', 'petugas']);
            }

            // Kategori mahasiswa: hanya mahasiswa dengan NIM yang cocok di pivot document_nim
            if ($ag === AccessGroup::MAHASISWA->value) {
                if ($user->hasRole(['mahasiswa', 'Mahasiswa'])) {
                    $userNim = $user->nim;
                    return $userNim !== null && $userNim !== '' && $document->nims()->where('nim', (string) $userNim)->exists();
                }
                return false;
            }

            return false;
        }

        // 2. Fallback visibilitas lama (access_group is null)
        $visibility = strtolower($document->visibility ?? 'publik');

        if ($visibility === 'publik') {
            return $user === null || $user->can('public_document_read');
        }

        if ($user === null) {
            return false;
        }

        if ($visibility === 'internal') {
            return $user->hasRole([
                'admin', 'Admin', 'Akademik', 'superadmin', 'Superadmin',
                'Pelaksana', 'Pemimpin', 'Pengamat', 'pelaksana', 'pemimpin', 'pengamat',
                'Petugas', 'petugas'
            ]);
        }

        if ($visibility === 'private') {
            return $user->hasRole(['admin', 'Admin', 'Akademik', 'superadmin', 'Superadmin']);
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
     * Determine if the user can create documents (upload).
     * Memakai Spatie permission 'document_create'.
     * Role Petugas hanya diizinkan mengunggah di folder publik.
     */
    public function create(User $user, ?DocumentCategory $category = null): bool
    {
        if (! $user->can('document_create')) {
            return false;
        }

        if ($user->hasRole(['admin', 'Admin', 'Akademik', 'superadmin', 'Superadmin'])) {
            return true;
        }

        if ($user->hasRole(['Petugas', 'petugas'])) {
            if ($category !== null) {
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

        return true;
    }

    /**
     * Determine if the user can update the document.
     */
    public function update(User $user, ?Document $document = null): bool
    {
        if (! $user->can('document_update')) {
            return false;
        }

        if ($user->hasRole(['admin', 'Admin', 'Akademik', 'superadmin', 'Superadmin'])) {
            return true;
        }

        if ($user->hasRole(['Petugas', 'petugas']) && $document !== null) {
            $ag = $document->access_group instanceof AccessGroup ? $document->access_group->value : $document->access_group;
            if ($ag !== null && $ag !== AccessGroup::PUBLIK->value) {
                return false;
            }
            if ($ag === null && strtolower($document->visibility ?? '') !== 'publik') {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine if the user can delete the document.
     */
    public function delete(User $user, ?Document $document = null): bool
    {
        if (! $user->can('document_delete')) {
            return false;
        }

        if ($user->hasRole(['admin', 'Admin', 'Akademik', 'superadmin', 'Superadmin'])) {
            return true;
        }

        if ($user->hasRole(['Petugas', 'petugas']) && $document !== null) {
            $ag = $document->access_group instanceof AccessGroup ? $document->access_group->value : $document->access_group;
            if ($ag !== null && $ag !== AccessGroup::PUBLIK->value) {
                return false;
            }
            if ($ag === null && strtolower($document->visibility ?? '') !== 'publik') {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine if the user can restore a soft-deleted document.
     */
    public function restore(User $user, ?Document $document = null): bool
    {
        return $this->delete($user, $document);
    }

    /**
     * Determine if the user can permanently delete the document.
     */
    public function forceDelete(User $user, ?Document $document = null): bool
    {
        return $this->delete($user, $document);
    }
}
