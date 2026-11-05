<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Validasi NIM Mahasiswa
    |--------------------------------------------------------------------------
    | Panjang minimal dan maksimal digit NIM kampus (hanya angka).
    | Ubah nilai ini jika format digit NIM kampus berubah.
    */
    'nim_min_length' => env('QUEUE_NIM_MIN_LENGTH', 8),
    'nim_max_length' => env('QUEUE_NIM_MAX_LENGTH', 15),

    /*
    |--------------------------------------------------------------------------
    | Toleransi Buka Tiket Sebelum Sesi Layanan (Menit)
    |--------------------------------------------------------------------------
    | Tiket dapat mulai diambil N menit sebelum sesi layanan dimulai.
    */
    'open_before_minutes' => 5,

    /*
    |--------------------------------------------------------------------------
    | Jadwal Layanan Tatap Muka
    |--------------------------------------------------------------------------
    | Hari: 1 = Senin, 2 = Selasa, 3 = Rabu, 4 = Kamis, 5 = Jumat, 6 = Sabtu, 7 = Minggu
    */
    'schedule' => [
        // Senin - Kamis
        1 => [
            ['start' => '06:00', 'end' => '12:00'],
            ['start' => '13:00', 'end' => '17:00'],
        ],
        2 => [
            ['start' => '06:00', 'end' => '12:00'],
            ['start' => '13:00', 'end' => '17:00'],
        ],
        3 => [
            ['start' => '06:00', 'end' => '12:00'],
            ['start' => '13:00', 'end' => '17:00'],
        ],
        4 => [
            ['start' => '06:00', 'end' => '12:00'],
            ['start' => '13:00', 'end' => '17:00'],
        ],
        // Jumat
        5 => [
            ['start' => '06:00', 'end' => '11:30'],
            ['start' => '13:00', 'end' => '17:00'],
        ],
        // Sabtu & Minggu tutup (kosong)
        6 => [],
        7 => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Daftar Program Studi (Dropdown Searchable)
    |--------------------------------------------------------------------------
    */
    'study_programs' => [
        'Sains Data',
        'Bisnis Digital',
        'Sistem Informasi',
        'Informatika',
        'Teknik Komputer',
        'Teknik Elektro',
        'Teknik Telekomunikasi',
        'Teknik Logistik',
        'Rekayasa Perangkat Lunak',
        'Teknik Industri',
        'Teknologi Informasi',
    ],

    /*
    |--------------------------------------------------------------------------
    | Daftar Kategori Layanan
    |--------------------------------------------------------------------------
    */
    'categories' => [
        'Akademik',
        'Kemahasiswaan',
        'Pusat Teknologi Informasi',
        'Riset dan Kerjasama',
        'Sekretariat Pimpinan',
        'Logistik',
        'Keuangan',
        'Perpustakaan dan Pusat Bahasa',
    ],
];
