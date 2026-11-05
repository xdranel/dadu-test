<?php

namespace App\Traits;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\ConnectionException;

trait ConsumeExternalService
{
    /**
     * Kirim request multipart/form-data ke external API
     */
    public function performRequestFormData($method, $requestUrl, $data = [], $headers = [])
    {
        try {
            $response = Http::withHeaders($headers)
                ->retry(3, 100, function (Exception $exception, PendingRequest $request) {
                    return $exception instanceof ConnectionException;
                })
                ->$method($requestUrl, $data);

            return json_decode($response->getBody()->getContents());
        } catch (RequestException $e) {
            return json_decode($e->response->getBody()->getContents());
        } catch (ConnectionException $e) {
            return (object)[
                'message' => 'Gagal terhubung ke server Gateway SSO Telkom University.',
            ];
        }
    }
}
