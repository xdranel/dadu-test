<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class MasterDataApiService
{
    public function fetchWithCookieString(string $url, string $cookieString): array
    {
        if (empty($url)) {
            return [
                'success' => false,
                'data'    => [],
                'message' => 'URL sumber data belum dikonfigurasi.'
            ];
        }

        if (empty(trim($cookieString))) {
            return [
                'success' => false,
                'data'    => [],
                'message' => 'Cookie string tidak boleh kosong.'
            ];
        }

        $sanitized = preg_replace('/[^\x20-\x7E]/', '', $cookieString);

        $context = stream_context_create([
            'http' => [
                'method'        => 'GET',
                'header'        => implode("\r\n", [
                    "Cookie: {$sanitized}",
                    "Accept: application/json",
                    "User-Agent: Dadu/1.0",
                    "X-Requested-With: XMLHttpRequest",
                ]),
                'timeout'       => 20,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ]
        ]);

        try {
            $response = file_get_contents($url, false, $context);

            if ($response === false) {
                Log::error("MasterDataApiService: Gagal fetch dari {$url}");
                return [
                    'success' => false,
                    'data'    => [],
                    'message' => 'Gagal terhubung. Pastikan cookies masih valid.'
                ];
            }

            $httpCode = 0;
            if (isset($http_response_header)) {
                preg_match('/HTTP\/\d\.\d\s+(\d+)/', $http_response_header[0], $matches);
                $httpCode = (int)($matches[1] ?? 0);
            }

            if ($httpCode === 401 || $httpCode === 403) {
                return [
                    'success' => false,
                    'data'    => [],
                    'message' => 'Cookies tidak valid atau sesi telah berakhir. Silakan ambil cookies baru.'
                ];
            }

            $data = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error("MasterDataApiService: Response bukan JSON dari {$url}");
                return [
                    'success' => false,
                    'data'    => [],
                    'message' => 'Format response tidak valid.'
                ];
            }

            return ['success' => true, 'data' => $data];

        } catch (\Exception $e) {
            Log::error("MasterDataApiService Exception: " . $e->getMessage());
            return ['success' => false, 'data' => [], 'message' => $e->getMessage()];
        }
    }
}