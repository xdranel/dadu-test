<?php

namespace App\Services;

use App\Traits\ConsumeExternalService;

class SatuService
{
    use ConsumeExternalService;

    public $baseUri;

    public function __construct()
    {
        // Gateway resmi Telkom University
        $this->baseUri = env('TELKOM_GATEWAY_URL', 'https://gateway.telkomuniversity.ac.id');
    }

    /**
     * Login untuk mendapatkan Bearer Token SSO
     */
    public function login(array $data)
    {
        return $this->performRequestFormData('POST', $this->baseUri . '/issueauth', $data);
    }

    /**
     * Ambil profil user berdasarkan Bearer Token SSO
     */
    public function profile(string $token)
    {
        return $this->performRequestFormData(
            'GET', 
            $this->baseUri . '/issueprofile', 
            [], 
            ['Authorization' => 'Bearer ' . $token]
        );
    }
}
