<?php

return [
    'asset_dir' => 'assets/images/document',
    'folder_image' => 'foldercard.svg',
    'visibility' => [
        'publik'   => [
            'label' => 'Publik dan Mahasiswa',
            'icon'  => 'publicVisibility.svg',
            'badge' => 'bg-emerald-50 text-emerald-700 border border-emerald-200/60',
        ],
        'internal' => [
            'label' => 'Pegawai dan Dosen',
            'icon'  => 'stafVisibility.svg',
            'badge' => 'bg-amber-50 text-amber-700 border border-amber-200/60',
        ],
        'private'  => [
            'label' => 'Internal Staf',
            'icon'  => 'privateVisibility.svg',
            'badge' => 'bg-violet-50 text-violet-700 border border-violet-200/60',
        ],
    ],
    'access_groups' => [
        'mahasiswa' => [
            'label' => 'Mahasiswa',
            'badge' => 'bg-sky-50 text-sky-700 border border-sky-200/60',
        ],
        'dosen_staf' => [
            'label' => 'Dosen & Staf',
            'badge' => 'bg-amber-50 text-amber-700 border border-amber-200/60',
        ],
        'akademik' => [
            'label' => 'Akademik',
            'badge' => 'bg-violet-50 text-violet-700 border border-violet-200/60',
        ],
        'publik' => [
            'label' => 'Publik',
            'badge' => 'bg-emerald-50 text-emerald-700 border border-emerald-200/60',
        ],
    ],
    'nim_min_length' => 8,
    'nim_max_length' => 15,

    'import_max_rows'  => 2000,
    'import_max_files' => 500,

    'import_headers' => [
        'old_name'      => 'Nama Lama',
        'document_type' => 'Jenis Dokumen',
        'role'          => 'Role Dokumen',
        'folder'        => 'Folder',
        'new_name'      => 'Nama Baru',
        'nim'           => 'NIM',
        'keywords'      => 'Keyword',
    ],

    'import_role_map' => [
        'Mahasiswa'      => 'mahasiswa',
        'Dosen dan Staf' => 'dosen_staf',
        'Akademik'       => 'akademik',
        'Publik'         => 'publik',
    ],
];
