<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'recaptcha' => [
        'enabled'    => env('AUTH_WITH_CAPTCHA', true),
        'site_key'   => env('RECAPTCHA_SITE_KEY'),
        'secret_key' => env('RECAPTCHA_SECRET_KEY'),
    ],

    'master_data' => [
        'base_url'            => env('MASTER_DATA_BASE_URL', ''),
        'cookie_name'         => env('MASTER_DATA_COOKIE_NAME', ''),
        'mahasiswa_endpoint'  => env('MASTER_DATA_BASE_URL', '') . env('MASTER_DATA_MAHASISWA_PATH', '/api/mahasiswa'),
        'matakuliah_endpoint' => env('MASTER_DATA_BASE_URL', '') . env('MASTER_DATA_MATAKULIAH_PATH', '/api/matakuliah'),
    ],

    'chatbot' => [
        'url' => env('CHATBOT_API_URL', 'http://localhost:8000'),
        'key' => env('CHATBOT_API_KEY'),
    ],

    'sso' => [
        'base_url' => env('SSO_BASE_URL', 'https://myapps-akd.telkomuniversity.ac.id/simka'),
        'timeout' => env('SSO_TIMEOUT', 10), // seconds
        'enabled' => env('SSO_ENABLED', true),
        'mock' => (bool) env('SSO_MOCK', false),
        // login SSO asli (untuk testing). Default: false
        'use_in_local' => (bool) env('SSO_IN_LOCAL', false),
        'allowed_roles' => ['mahasiswa', 'dosen'],
    ],
];
