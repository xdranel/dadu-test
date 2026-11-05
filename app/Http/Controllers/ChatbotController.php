<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatbotController extends Controller
{
    private string $apiUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->apiUrl = config('services.chatbot.url', 'http://localhost:8000');
        $this->apiKey = config('services.chatbot.key', '');
    }

    // ─────────────────────────────────────────────
    //  POST /chatbot/chat
    //  Dipanggil dari widget Alpine.js di blade
    // ─────────────────────────────────────────────
    public function chat(Request $request)
    {
        $request->validate([
            'question' => ['required', 'string', 'max:500'],
        ]);

        try {
            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey,
            ])
            ->timeout(60)
            ->post("{$this->apiUrl}/chat", [
                'question' => $request->question,
            ]);

            if ($response->serverError()) {
                Log::error('Chatbot API error', ['status' => $response->status()]);
                return response()->json([
                    'answer'  => 'Maaf, asisten sedang tidak tersedia. Silakan coba beberapa saat lagi.',
                    'sources' => [],
                ], 200); // tetap 200 agar frontend tidak masuk catch error
            }

            return response()->json($response->json());

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Chatbot tidak bisa dihubungi: ' . $e->getMessage());
            return response()->json([
                'answer'  => 'Koneksi ke asisten akademik gagal. Pastikan layanan chatbot sedang berjalan.',
                'sources' => [],
            ], 200);
        }
    }

    // ─────────────────────────────────────────────
    //  GET /chatbot/status
    //  Health check untuk admin/debugging
    // ─────────────────────────────────────────────
    public function status()
    {
        try {
            $response = Http::timeout(5)->get("{$this->apiUrl}/health");
            return response()->json($response->json());
        } catch (\Exception $e) {
            return response()->json(['status' => 'unreachable', 'error' => $e->getMessage()], 503);
        }
    }

    // ─────────────────────────────────────────────
    //  GET /chatbot/documents
    //  Daftar dokumen yang sudah terindex di Python
    // ─────────────────────────────────────────────
    public function indexedDocuments()
    {
        try {
            $response = Http::withHeaders(['X-API-Key' => $this->apiKey])
                ->timeout(10)
                ->get("{$this->apiUrl}/documents");

            return response()->json($response->json());
        } catch (\Exception $e) {
            return response()->json(['total' => 0, 'documents' => []], 200);
        }
    }
}