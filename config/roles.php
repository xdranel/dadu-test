<?php

/**
 * Role Metadata Configuration
 *
 * Deskripsi dan label setiap role yang selaras dengan konsep SSC.
 * File ini hanya menyimpan metadata statis (label, icon, warna, deskripsi, akses).
 *
 * PENTING:
 * - Key array harus sesuai PERSIS dengan nama role di database (case-sensitive).
 * - Metadata ini bukan mekanisme otorisasi. Otorisasi tetap dikendalikan
 *   oleh Spatie Laravel Permission.
 * - Field `sso_auto` hanya bersifat deskriptif; tidak digunakan untuk
 *   memberikan role secara otomatis kecuali aplikasi mengimplementasikannya secara eksplisit.
 * - Field `id` hanya metadata referensial dan bukan primary key database.
 */

return [

    'mahasiswa' => [
        'id'          => '101',
        'label'       => 'Mahasiswa',
        'icon'        => 'user',
        'color'       => 'emerald',
        'sso_auto'    => true,
        'description' => 'Mahasiswa',
        'access'      => 'Mengajukan & memantau layanan akademik',
    ],

    'Petugas' => [
        'id'          => '102',
        'label'       => 'Petugas',
        'icon'        => 'badge',
        'color'       => 'amber',
        'sso_auto'    => true,
        'description' => 'Petugas Front Desk / Operator Loket',
        'access'      => 'Mengelola antrean, memproses layanan, akses modul operasional.',
    ],

    'Pelaksana' => [
        'id'          => '103',
        'label'       => 'Pelaksana',
        'icon'        => 'cog',
        'color'       => 'blue',
        'sso_auto'    => false,
        'description' => 'Staf unit kerja teknis',
        'access'      => 'CRUD penuh modul operasional + review layanan.',
    ],

    'Pemimpin' => [
        'id'          => '104',
        'label'       => 'Pemimpin',
        'icon'        => 'star',
        'color'       => 'purple',
        'sso_auto'    => false,
        'description' => 'Kepala Urusan / Ketua Program Studi',
        'access'      => 'Monitoring unit, menyetujui dokumen, akses read + review.',
    ],

    'Pengamat' => [
        'id'          => '105',
        'label'       => 'Pengamat',
        'icon'        => 'eye',
        'color'       => 'indigo',
        'sso_auto'    => false,
        'description' => 'Pimpinan / Rektorat',
        'access'      => 'Monitoring dashboard makro & statistik lintas unit',
    ],

    'admin' => [
        'id'          => '---',
        'label'       => 'Administrator',
        'icon'        => 'shield',
        'color'       => 'sky',
        'sso_auto'    => false,
        'description' => 'Administrator operasional',
        'access'      => 'CRUD modul data akademik & dokumen tanpa hak review.',
    ],

    'Akademik' => [
        'id'          => '106',
        'label'       => 'Super Admin',
        'icon'        => 'crown',
        'color'       => 'rose',
        'sso_auto'    => false,
        'description' => 'Administrator dengan akses penuh.',
        'access'      => 'Full Access',
    ],

];

