<?php

namespace App\Observers;

use App\Models\Document;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DocumentObserver
{
    /**
     * Event created: dokumen baru diunggah.
     */
    public function created(Document $document): void
    {
        $extension = strtolower(pathinfo($document->file_path, PATHINFO_EXTENSION));
        
        // Hanya proses pdf, docx, pptx
        if (!in_array($extension, ['pdf', 'docx', 'pptx'])) {
            Log::info("[Chatbot] format tidak didukung untuk file: " . $document->file_name);
            return;
        }

        $docType = $this->guessDocType($document);
        $uploadedBy = auth()->user()?->name ?? 'Sistem';

        // Jalankan di background agar response upload tidak lambat
        dispatch(function () use ($document, $docType, $uploadedBy) {
            $this->sendToPython($document, $docType, $uploadedBy);
        });
    }

    /**
     * Event updated: dokumen diperbarui.
     */
    public function updated(Document $document): void
    {
        // Cek apakah file_path berubah
        if (!$document->wasChanged('file_path')) {
            Log::info("[Chatbot] metadata berubah, tidak perlu reindex");
            return;
        }

        $oldFilePath = $document->getOriginal('file_path');
        $newFilePath = $document->file_path;
        $docType = $this->guessDocType($document);
        $uploadedBy = auth()->user()?->name ?? 'Sistem';

        $extension = strtolower(pathinfo($newFilePath, PATHINFO_EXTENSION));
        if (!in_array($extension, ['pdf', 'docx', 'pptx'])) {
            Log::info("[Chatbot] format tidak didukung");
            
            // Hapus file lama dari index Python jika file baru tidak didukung
            dispatch(function () use ($oldFilePath) {
                $this->deleteFromPython($oldFilePath);
            });
            return;
        }

        // Jalankan di background
        dispatch(function () use ($oldFilePath, $document, $docType, $uploadedBy) {
            $this->deleteFromPython($oldFilePath);
            $this->sendToPython($document, $docType, $uploadedBy);
        });
    }

    /**
     * Event deleted: dokumen dihapus.
     */
    public function deleted(Document $document): void
    {
        $filePath = $document->file_path;
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        if (!in_array($extension, ['pdf', 'docx', 'pptx'])) {
            return;
        }

        // Hapus dari index Python
        $this->deleteFromPython($filePath);
    }

    /**
     * Mengirim dokumen ke Python FastAPI.
     */
    private function sendToPython(Document $document, string $docType, string $uploadedBy = 'Sistem'): bool
    {
        $filePath = $document->file_path;
        $filename = basename($filePath);
        $physicalPath = Storage::disk('public')->path($filePath);

        Log::info("[Chatbot] Memulai proses kirim dokumen ke Python: {$filename}");

        if (!file_exists($physicalPath)) {
            Log::warning("[Chatbot] File fisik tidak ditemukan di path: {$physicalPath}");
            return false;
        }

        try {
            $url = config('services.chatbot.url') . '/upload';
            Log::info("[Chatbot] Mengunggah file ke {$url}...");

            $response = Http::withHeaders([
                'X-API-Key' => config('services.chatbot.key')
            ])
            ->timeout(120)
            ->attach(
                'file',
                file_get_contents($physicalPath),
                $filename
            )
            ->post($url, [
                'doc_type' => $docType,
                'uploaded_by' => $uploadedBy
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $chunks = $data['chunks_created'] ?? 0;
                Log::info("[Chatbot] Berhasil mengindeks dokumen {$filename}. Jumlah chunks: {$chunks}");
                return true;
            } else {
                Log::warning("[Chatbot] Gagal mengindeks dokumen {$filename}. Status: " . $response->status() . " Response: " . $response->body());
            }
        } catch (\Exception $e) {
            Log::warning("[Chatbot] Terjadi kesalahan saat mengindeks dokumen {$filename}: " . $e->getMessage());
        }

        return false;
    }

    /**
     * Menghapus dokumen dari Python FastAPI.
     */
    private function deleteFromPython(string $filePath): bool
    {
        $filename = basename($filePath);
        $url = config('services.chatbot.url') . "/documents/{$filename}";
        
        Log::info("[Chatbot] Menghapus index dokumen: {$filename}");
        
        try {
            $response = Http::withHeaders([
                'X-API-Key' => config('services.chatbot.key')
            ])->timeout(15)->delete($url);
            
            if ($response->successful()) {
                Log::info("[Chatbot] Berhasil menghapus dokumen: {$filename}");
                return true;
            } else {
                Log::warning("[Chatbot] Gagal menghapus dokumen: {$filename}. Status: " . $response->status());
            }
        } catch (\Exception $e) {
            Log::warning("[Chatbot] Terjadi kesalahan saat menghapus dokumen {$filename}: " . $e->getMessage());
        }
        
        return false;
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
