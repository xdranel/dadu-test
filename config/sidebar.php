<?php

/*
|--------------------------------------------------------------------------
| Sidebar Menu Configuration
|--------------------------------------------------------------------------
|
| Single source of truth untuk seluruh item menu sidebar DADU.
| Untuk menambah / mengubah / reorder menu, cukup edit file ini tanpa
| menyentuh markup Blade.
|
| Struktur item:
|   'label'      => teks menu (string)
|   'route'      => nama route Laravel (string|null). Jika null, item
|                   dirender sebagai placeholder href="#".
|   'active'     => pola untuk request()->routeIs() penanda menu aktif.
|                   Boleh wildcard, mis. 'students.*'. Default: nilai route.
|   'permission' => nama permission Spatie (string|null). Null = tidak
|                   dibatasi permission.
|   'roles'      => array role Spatie (opsional). Item tampil bila user
|                   punya salah satu role tsb. Dipakai untuk menu yang
|                   di-gate berdasarkan role, bukan permission granular.
|   'icon'       => path 'd' SVG (string) atau array of path (untuk ikon
|                   multi-path seperti gear).
|   'badge_key'  => kunci counter opsional (string). Dihitung oleh
|                   App\Services\SidebarBadgeService.
|
| Untuk role 'mahasiswa'      -> flat array of items.
| Untuk 'admin_standalone'    -> flat array item mandiri (di atas grup).
| Untuk role 'admin'          -> array grup accordion (label, icon, items).
|
*/

return [

    'mahasiswa' => [
        [
            'label'      => 'Beranda',
            'route'      => 'dashboard',
            'active'     => 'dashboard',
            'permission' => null,
            'icon'       => 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z',
        ],
        [
            'label'      => 'Portal Dokumen',
            'route'      => 'public_documents.index',
            'active'     => 'public_documents.*',
            'permission' => null,
            'icon'       => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        ],
        [
            'label'      => 'Jadwal Ujian',
            'route'      => 'exam_schedules.index',
            'active'     => 'exam_schedules.*',
            'permission' => 'jadwal_read',
            'icon'       => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
        ],
        [
            'label'      => 'Pendaftaran Yudisium',
            'route'      => 'graduation-registrations.index',
            'active'     => 'graduation-registrations.*',
            'permission' => 'graduation_registration_create',
            'icon'       => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
        ],
        [
            'label'      => 'Pendaftaran Sidang TA',
            'route'      => 'sidang-ta-registrations.index',
            'active'     => 'sidang-ta-registrations.*',
            'permission' => 'sidang_ta_registration_read',
            'icon'       => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        ],
        [
            'label'      => 'Banding Nilai',
            'route'      => 'grade-appeals.index',
            'active'     => 'grade-appeals.*',
            'permission' => null,
            'icon'       => 'M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3',
        ],
    ],

    'admin_standalone' => [
        [
            'label'      => 'Beranda',
            'route'      => 'dashboard',
            'active'     => 'dashboard',
            'permission' => null,
            'icon'       => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
        ],
    ],

    'admin' => [

        'data_akademik' => [
            'label' => 'Data Akademik',
            'icon'  => 'M12 14l9-5-9-5-9 5 9 5z M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222',
            'items' => [
                ['label' => 'Mahasiswa',     'route' => 'students.index',        'active' => 'students.*',           'permission' => 'student_read'],
                ['label' => 'Dosen',         'route' => 'lecturers.index',       'active' => 'lecturers.*',          'permission' => 'lecturer_read'],
                ['label' => 'Mata Kuliah',   'route' => 'courses.index',         'active' => 'courses.*',            'permission' => 'course_read'],
                ['label' => 'KRS Mahasiswa', 'route' => 'student_courses.index', 'active' => 'student_courses.*',    'permission' => 'student_read'],
                ['label' => 'Jadwal Ujian',  'route' => 'exam_schedules.index',  'active' => 'exam_schedules.*',     'permission' => 'jadwal_read'],
                ['label' => 'Ruangan',       'route' => 'rooms.index',           'active' => 'rooms.*',              'permission' => 'room_read'],
            ],
        ],

        'dokumen' => [
            'label' => 'Dokumen',
            'icon'  => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
            'items' => [
                ['label' => 'Dokumen',        'route' => 'documents.index',        'active' => 'documents.*',        'permission' => 'document_read'],
                ['label' => 'Portal Dokumen', 'route' => 'public_documents.index', 'active' => 'public_documents.*', 'permission' => null],
            ],
        ],

        'form' => [
            'label' => 'Form',
            'icon'  => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01',
            'items' => [
                ['label' => 'Review Yudisium',      'route' => 'admin.graduation-registrations.index', 'active' => 'admin.graduation-registrations.*', 'permission' => 'graduation_registration_review'],
                ['label' => 'Review Sidang TA',     'route' => 'admin.sidang-ta-registrations.index', 'active' => ['admin.sidang-ta-registrations.*', 'admin.sidang-ta-periods.*'], 'permissions_any' => ['sidang_ta_registration_review', 'sidang_ta_period_read']],
                ['label' => 'Review Banding Nilai', 'route' => 'admin.grade-appeals.index',            'active' => 'admin.grade-appeals.*',            'permission' => 'grade_appeal_review'],
                ['label' => 'Layanan Mahasiswa',    'route' => 'admin.queue.index',                    'active' => 'admin.queue.*',                    'permission' => 'queue_ticket_read', 'badge_key' => 'queue_pending'],
            ],
        ],

        'it_panel' => [
            'label' => 'IT Panel',
            'icon'  => [
                'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z',
                'M15 12a3 3 0 11-6 0 3 3 0 016 0z',
            ],
            'items' => [
                ['label' => 'Sinkronisasi',        'route' => 'sync.index',          'active' => 'sync.*',          'permission' => 'sync_read'],
                ['label' => 'Pengaturan Role',    'route' => 'role.index',          'active' => 'role.*',          'permission' => 'role_read'],
                ['label' => 'Kelola Akun Manual', 'route' => 'manual-users.index',  'active' => 'manual-users.*',  'permission' => 'manual_user_read'],
            ],
        ],

    ],
];
