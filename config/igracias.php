<?php

return [
    /*
    |--------------------------------------------------------------------------
    | iGracias Scraping Endpoints & Configuration
    |--------------------------------------------------------------------------
    |
    | Di sini Anda bisa mengustomisasi URL endpoint AJAX dan Page ID yang
    | digunakan untuk melakukan scraping data dari iGracias.
    | Jika Admin memiliki akses ke URL yang berbeda, Anda bisa mengubahnya
    | di file .env atau langsung di sini.
    |
    */

    'pageid' => env('IGRACIAS_PAGEID', '156'),

    'endpoints' => [
        // Phase 1: Daftar Mahasiswa
        'student_list' => env('IGRACIAS_URL_STUDENT_LIST', 'https://igracias.telkomuniversity.ac.id/libraries/ajax/ajax.student.php'),
        
        // Phase 2 & 3: Profil & Nilai
        'perwalian'    => env('IGRACIAS_URL_PERWALIAN', 'https://igracias.telkomuniversity.ac.id/libraries/ajax/ajax.perwalian.php'),
        
        // Phase 4: EPRT
        'eprt'         => env('IGRACIAS_URL_EPRT', 'https://igracias.telkomuniversity.ac.id/languagecenter/ajax/languagecenter.ajax.languagetest.data.php'),
        
        // Phase 5: KRS & Mata Kuliah
        'krs'          => env('IGRACIAS_URL_KRS', 'https://igracias.telkomuniversity.ac.id/libraries/ajax/ajax.reg.php'),
    ],
];
