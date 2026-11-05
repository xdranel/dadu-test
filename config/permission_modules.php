<?php

/**
 * Registry modul & permission (sumber tunggal untuk halaman Edit Role).
 *
 * Konvensi nama permission proyek: "{modul}_{aksi}" (underscore), mis. student_read.
 * Pola ini dipertahankan agar permission yang sudah tersimpan tetap valid.
 *
 * Struktur modul:
 *   'label'     => nama tampil
 *   'group'     => pengelompokan baris pada tabel
 *   'sensitive' => true  => HANYA role Akademik (superadmin) yang boleh memberi/mencabut
 *                  permission modul ini (IT PANEL). Role lain tidak otomatis memilikinya.
 *   'actions'   => aksi yang BENAR-BENAR relevan secara fungsional untuk modul tsb.
 *                  Kunci baku (create/read/update/delete/review) tampil di kolom C/R/U/D/Review.
 *                  Kunci lain = aksi khusus (kolom "Aksi Khusus").
 *                  Nama permission default "{modul}_{aksi}", dapat ditimpa lewat 'permission'.
 *
 * Aksi yang tidak didaftarkan ditampilkan sebagai strip (tidak relevan / tidak ada fiturnya).
 * Permission lama di DB yang tidak ada di registry (mis. student_review) tidak disentuh.
 */
return [

    'standard_actions' => [
        'create' => 'C',
        'read'   => 'R',
        'update' => 'U',
        'delete' => 'D',
        'review' => 'Review',
    ],

    'modules' => [

        // ── Data Akademik ───────────────────────────────────────────────
        'student'  => ['label' => 'Mahasiswa',    'group' => 'Data Akademik', 'actions' => ['create' => [], 'read' => [], 'update' => [], 'delete' => []]],
        'lecturer' => ['label' => 'Dosen',        'group' => 'Data Akademik', 'actions' => ['create' => [], 'read' => [], 'update' => [], 'delete' => []]],
        'course'   => ['label' => 'Mata Kuliah',  'group' => 'Data Akademik', 'actions' => ['create' => [], 'read' => [], 'update' => [], 'delete' => []]],
        'room'     => ['label' => 'Ruangan',      'group' => 'Data Akademik', 'actions' => ['create' => [], 'read' => [], 'update' => [], 'delete' => []]],
        'jadwal'   => ['label' => 'Jadwal Ujian', 'group' => 'Data Akademik', 'actions' => ['create' => [], 'read' => [], 'update' => [], 'delete' => []]],

        // ── Dokumen ─────────────────────────────────────────────────────
        'document_category' => ['label' => 'Kategori Dokumen', 'group' => 'Dokumen', 'actions' => ['create' => [], 'read' => [], 'update' => [], 'delete' => []]],
        'document'          => ['label' => 'Dokumen',          'group' => 'Dokumen', 'actions' => ['create' => [], 'read' => [], 'update' => [], 'delete' => []]],
        'public_document'   => ['label' => 'Dokumen Publik',   'group' => 'Dokumen', 'actions' => ['read' => []]],

        // ── Layanan & Form ──────────────────────────────────────────────
        'queue_ticket'            => ['label' => 'Antrian Layanan',      'group' => 'Layanan & Form', 'actions' => ['read' => [], 'update' => []]],
        'graduation_registration' => ['label' => 'Pendaftaran Yudisium', 'group' => 'Layanan & Form', 'actions' => ['create' => [], 'read' => [], 'update' => [], 'delete' => [], 'review' => []]],
        'sidang_ta_registration'  => ['label' => 'Pendaftaran Sidang TA', 'group' => 'Layanan & Form', 'actions' => ['create' => [], 'read' => [], 'update' => [], 'review' => []]],
        'sidang_ta_period'        => ['label' => 'Periode Sidang TA', 'group' => 'Layanan & Form', 'actions' => ['create' => [], 'read' => [], 'update' => [], 'delete' => []]],
        'grade_appeal'            => ['label' => 'Form Banding Nilai',   'group' => 'Layanan & Form', 'actions' => ['create' => [], 'read' => [], 'update' => [], 'review' => []]],

        // ── IT PANEL (hanya Akademik yang boleh mendelegasikan) ──────────
        'sync' => [
            'label' => 'Sinkronisasi', 'group' => 'IT Panel', 'sensitive' => true,
            'actions' => [
                'read'    => [], // melihat halaman, status, statistik & pengaturan sinkronisasi
                'update'  => [], // mengubah pengaturan sinkronisasi
                'execute' => ['label' => 'Jalankan Sinkronisasi / Impor'], // scrape, dispatch, import JSON/profil
            ],
        ],
        'role' => [
            'label' => 'Pengaturan Role', 'group' => 'IT Panel', 'sensitive' => true,
            'actions' => [
                'read'        => [], // melihat daftar role & permission
                'update'      => [], // mengubah permission suatu role
                'assign_user' => ['label' => 'Beri Role ke Pengguna'],
                'revoke_user' => ['label' => 'Cabut Role dari Pengguna'],
            ],
        ],
        'manual_user' => [
            'label' => 'Kelola Akun Manual', 'group' => 'IT Panel', 'sensitive' => true,
            'actions' => [
                'create' => [], 'read' => [], 'update' => [], 'delete' => [], // delete = nonaktifkan akun
                'reset_password' => ['label' => 'Reset Password', 'permission' => 'user_reset_password'], // nama lama dipertahankan
            ],
        ],
    ],
];
