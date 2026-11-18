<?php

namespace App\Jobs;

use App\Enums\AccessGroup;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentImportBatch;
use App\Models\DocumentImportRow;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProcessDocumentImportBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $batchId;
    public ?int $actorId;

    public function __construct(int $batchId, ?int $actorId = null)
    {
        $this->batchId = $batchId;
        $this->actorId = $actorId;
    }

    public function handle(): void
    {
        $batch = DocumentImportBatch::find($this->batchId);
        if (!$batch || $batch->status === 'completed' || $batch->status === 'cancelled') {
            return;
        }

        $batch->update(['status' => 'processing']);

        $chunkSize = 50;

        $batch->rows()
            ->whereIn('status', ['ok', 'warning'])
            ->whereNull('applied_at')
            ->chunkById($chunkSize, function ($rows) use ($batch) {
                foreach ($rows as $row) {
                    if ($row->applied_at !== null) {
                        continue;
                    }

                    try {
                        DB::transaction(function () use ($batch, $row) {
                            if ($batch->mode === 'upload') {
                                $this->applyUploadRow($batch, $row);
                            } else {
                                $this->applyUpdateRow($batch, $row);
                            }
                        });
                    } catch (\Throwable $e) {
                        $messages = $row->messages ?? [];
                        $messages[] = ['type' => 'error', 'text' => 'Gagal menerapkan perubahan: ' . $e->getMessage()];
                        $row->update([
                            'status'   => 'error',
                            'messages' => $messages,
                        ]);
                    }
                }
            });

        // Hitung ringkasan akhir
        $totalRows = $batch->rows()->count();
        $appliedCount = $batch->rows()->whereNotNull('applied_at')->count();
        $errorCount = $batch->rows()->where('status', 'error')->count();
        $skippedCount = $batch->rows()->where('status', 'skipped')->count();

        $finalStatus = ($errorCount > 0 && $appliedCount === 0) ? 'failed' : 'completed';

        $batch->update([
            'status'  => $finalStatus,
            'summary' => [
                'total'    => $totalRows,
                'applied'  => $appliedCount,
                'errors'   => $errorCount,
                'skipped'  => $skippedCount,
                'mode'     => $batch->mode,
            ],
        ]);

        activity('document_import_batch')
            ->performedOn($batch)
            ->withProperties([
                'batch_id' => $batch->id,
                'mode'     => $batch->mode,
                'applied'  => $appliedCount,
                'errors'   => $errorCount,
                'status'   => $finalStatus,
            ])
            ->log("Eksekusi batch import dokumen ({$batch->mode}): {$appliedCount} berhasil, {$errorCount} gagal");
    }

    protected function applyUploadRow(DocumentImportBatch $batch, DocumentImportRow $row): void
    {
        $data = $row->data ?? [];
        $parsed = $data['parsed_data'] ?? [];
        $filePath = $data['matched_file'] ?? null;

        if (!$filePath) {
            throw new \RuntimeException("Berkas fisik tidak ditentukan.");
        }

        if (!file_exists($filePath)) {
            if (Storage::disk('private')->exists($filePath)) {
                $filePath = Storage::disk('private')->path($filePath);
            } else {
                throw new \RuntimeException("Berkas fisik tidak ditemukan di penyimpanan sementara ({$filePath}).");
            }
        }

        $origName = basename($filePath);
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $origName);
        $storageName = now()->format('YmdHis') . '_' . $safeName;
        $targetRelativePath = "dokumen/{$storageName}";

        $accessGroupVal = $parsed['access_group'] ?? AccessGroup::PUBLIK->value;
        $targetDisk = Document::determineStorageDisk($accessGroupVal, 'publik');

        // Pindahkan berkas fisik ke disk tujuan
        $stream = fopen($filePath, 'r');
        if (!$stream) {
            throw new \RuntimeException("Gagal membuka stream berkas {$filePath}");
        }
        $written = Storage::disk($targetDisk)->put($targetRelativePath, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        if (!$written || !Storage::disk($targetDisk)->exists($targetRelativePath)) {
            throw new \RuntimeException("Gagal menyimpan berkas ke disk {$targetDisk}");
        }

        $checksum = hash_file('sha256', $filePath);
        $fileSize = filesize($filePath);
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        $rawKeywords = $parsed['keywords'] ?? null;
        if (is_array($rawKeywords)) {
            $rawKeywords = implode(', ', $rawKeywords);
        }

        $doc = Document::create([
            'document_category_id' => $parsed['category_id'] ?? null,
            'title'                => $parsed['new_name'] ?? $origName,
            'file_name'            => $origName,
            'original_file_name'   => $origName,
            'file_path'            => $targetRelativePath,
            'storage_disk'         => $targetDisk,
            'checksum'             => $checksum,
            'file_type'            => $ext,
            'file_size'            => $fileSize,
            'access_group'         => $accessGroupVal,
            'visibility'           => $accessGroupVal === AccessGroup::PUBLIK->value ? 'publik' : 'private',
            'document_type'        => !empty($parsed['document_type']) ? $parsed['document_type'] : null,
            'keywords'             => !empty($rawKeywords) ? $rawKeywords : null,
        ]);

        if (!empty($parsed['nims_array'])) {
            $doc->syncNims($parsed['nims_array']);
        }

        $row->update([
            'applied_at'  => now(),
            'document_id' => $doc->id,
            'status'      => 'ok',
        ]);
    }

    protected function applyUpdateRow(DocumentImportBatch $batch, DocumentImportRow $row): void
    {
        $data = $row->data ?? [];
        $parsed = $data['parsed_data'] ?? [];
        $docId = $row->document_id ?? ($data['document_id'] ?? null);

        $doc = Document::find($docId);
        if (!$doc) {
            throw new \RuntimeException("Dokumen ID {$docId} tidak ditemukan.");
        }

        $oldData = [
            'title'         => $doc->title,
            'folder'        => $doc->category ? $doc->category->getFullPath() : 'Root',
            'category_id'   => $doc->document_category_id,
            'access_group'  => $doc->access_group instanceof AccessGroup ? $doc->access_group->value : $doc->access_group,
            'document_type' => $doc->document_type,
            'keywords'      => $doc->keywords,
            'nims'          => $doc->nims()->pluck('nim')->toArray(),
        ];

        // 1. Ubah Judul jika ada nilai baru
        if (!empty($parsed['new_name'])) {
            $doc->title = $parsed['new_name'];
        }

        // 2. Ganti Folder & Kategori jika diisi
        if (!empty($parsed['category_id']) && $parsed['category_id'] != $doc->document_category_id) {
            $newCategory = DocumentCategory::find($parsed['category_id']);
            $doc->moveToCategory($newCategory);
        }

        // 3. Jenis Dokumen jika diisi
        if (!empty($parsed['document_type'])) {
            $doc->document_type = $parsed['document_type'];
        }

        // 4. Keyword jika diisi
        if (!empty($parsed['keywords'])) {
            $doc->keywords = $parsed['keywords'];
        }

        // 5. NIM jika diisi (menggantikan relasi NIM lama)
        if (isset($parsed['nims_array']) && !empty($parsed['nims_array'])) {
            $doc->syncNims($parsed['nims_array']);
        }

        $doc->save();

        activity('document_import_update')
            ->performedOn($doc)
            ->withProperties([
                'document_id' => $doc->id,
                'old'         => $oldData,
                'new'         => [
                    'title'         => $doc->title,
                    'folder'        => $doc->category ? $doc->category->getFullPath() : 'Root',
                    'category_id'   => $doc->document_category_id,
                    'access_group'  => $doc->access_group instanceof AccessGroup ? $doc->access_group->value : $doc->access_group,
                    'document_type' => $doc->document_type,
                    'keywords'      => $doc->keywords,
                    'nims'          => $doc->nims()->pluck('nim')->toArray(),
                ],
            ])
            ->log("Update dokumen massal ID {$doc->id}: {$doc->title}");

        $row->update([
            'applied_at' => now(),
            'status'     => 'ok',
        ]);
    }
}
