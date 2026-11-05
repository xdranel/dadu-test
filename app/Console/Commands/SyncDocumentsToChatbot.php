<?php

namespace App\Console\Commands;

use App\Models\Document;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class SyncDocumentsToChatbot extends Command
{
    /**
     * Nama dan signature dari console command.
     *
     * @var string
     */
    protected $signature = 'chatbot:sync';

    /**
     * Deskripsi console command.
     *
     * @var string
     */
    protected $description = 'Melakukan sinkronisasi dokumen dari database Laravel ke index Chatbot RAG Python';

    /**
     * Eksekusi console command.
     */
    public function handle()
    {
        $this->info("=== Memulai Sinkronisasi Dokumen ke Chatbot ===");

        $url = config('services.chatbot.url');
        $key = config('services.chatbot.key');

        if (!$url || !$key) {
            $this->error("Error: Konfigurasi CHATBOT_API_URL atau CHATBOT_API_KEY belum diisi di .env.");
            return 1;
        }

        // 1. Ambil daftar dokumen yang sudah terindeks di Python
        $this->info("Mengambil daftar dokumen yang sudah terindeks dari server Python...");
        $existingFiles = [];

        try {
            $response = Http::withHeaders([
                'X-API-Key' => $key
            ])->timeout(15)->get("{$url}/documents");

            if ($response->successful()) {
                $data = $response->json();
                $existingFiles = collect($data['documents'] ?? [])->pluck('filename')->toArray();
                $this->info("Ditemukan " . count($existingFiles) . " dokumen di index Python.");
            } else {
                $this->warn("Gagal mengambil daftar dari Python. Status: " . $response->status() . ". Melanjutkan dengan asumsi index kosong.");
            }
        } catch (\Exception $e) {
            $this->error("Koneksi gagal ke Python API di {$url}: " . $e->getMessage());
            $this->error("Pastikan server Python FastAPI sudah berjalan.");
            return 1;
        }

        // 2. Query dokumen dari database dengan tipe pdf, docx, pptx
        $documents = Document::whereIn('file_type', ['pdf', 'docx', 'pptx'])
            ->orWhere(function ($query) {
                // Sebagai cadangan, cek ekstensi dari file_path jika file_type tidak terisi dengan benar
                $query->where('file_path', 'like', '%.pdf')
                      ->orWhere('file_path', 'like', '%.docx')
                      ->orWhere('file_path', 'like', '%.pptx');
            })
            ->get();

        $totalDocs = $documents->count();
        if ($totalDocs === 0) {
            $this->info("Tidak ada dokumen bertipe pdf/docx/pptx di database.");
            return 0;
        }

        $this->info("Menemukan {$totalDocs} dokumen di database untuk disinkronkan.");

        $indexed = 0;
        $skipped = 0;
        $failed = 0;

        // Inisialisasi progress bar
        $bar = $this->output->createProgressBar($totalDocs);
        $bar->start();

        foreach ($documents as $document) {
            $filePath = $document->file_path;
            $filename = basename($filePath);
            $physicalPath = Storage::disk('public')->path($filePath);

            // Cek apakah sudah ada di index Python
            if (in_array($filename, $existingFiles)) {
                $skipped++;
                $bar->advance();
                continue;
            }

            // Cek ketersediaan file fisik
            if (!file_exists($physicalPath)) {
                $failed++;
                $bar->advance();
                continue;
            }

            // Proses unggah dokumen ke Python
            $docType = $this->guessDocType($document);
            
            try {
                $uploadResponse = Http::withHeaders([
                    'X-API-Key' => $key
                ])
                ->timeout(120)
                ->attach(
                    'file',
                    file_get_contents($physicalPath),
                    $filename
                )
                ->post("{$url}/upload", [
                    'doc_type' => $docType,
                    'uploaded_by' => 'Sync CLI'
                ]);

                if ($uploadResponse->successful()) {
                    $indexed++;
                } else {
                    $failed++;
                }
            } catch (\Exception $e) {
                $failed++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // 3. Tampilkan ringkasan akhir
        $this->info("=== Ringkasan Sinkronisasi ===");
        $this->line("Dokumen Diindex : <info>{$indexed}</info>");
        $this->line("Dokumen Di-skip  : <comment>{$skipped}</comment> (Sudah terindeks)");
        $this->line("Dokumen Gagal    : <error>{$failed}</error>");
        
        return 0;
    }

    /**
     * Menebak tipe dokumen berdasarkan nama kategori, judul, dan nama file.
     * Menggunakan nama kategori asli secara dinamis agar otomatis mendukung kategori baru.
     */
    private function guessDocType(Document $document): string
    {
        // Gunakan nama kategori asli dari database jika tersedia
        if ($document->category && !empty($document->category->name)) {
            return $document->category->name;
        }

        // Fallback jika tidak memiliki kategori, tebak dari kata kunci
        $searchString = strtolower($document->title . ' ' . $document->file_name);

        if (str_contains($searchString, 'sop') || str_contains($searchString, 'prosedur')) {
            return 'SOP';
        }
        if (str_contains($searchString, 'pedoman') || str_contains($searchString, 'panduan')) {
            return 'Pedoman';
        }
        if (str_contains($searchString, 'mom') || str_contains($searchString, 'notulen') || str_contains($searchString, 'rapat')) {
            return 'MOM';
        }
        if (str_contains($searchString, 'jadwal')) {
            return 'Jadwal';
        }
        if (str_contains($searchString, 'surat') || str_contains($searchString, 'edaran')) {
            return 'Surat';
        }

        return 'Umum';
    }
}
