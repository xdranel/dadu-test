<?php

namespace App\Services;

use App\Enums\AccessGroup;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\User;

class DocumentImportValidator
{
    protected array $verifiedNimsCache = [];
    protected array $categoriesByPath = [];
    protected ?User $actor = null;
    protected string $mode = 'upload';
    protected bool $allowUnknownNim = false;
    protected bool $allowPublicToNonPublic = false;
    protected array $uploadedFiles = []; // [cleanName => fullPath]

    public function __construct(
        string $mode = 'upload',
        ?User $actor = null,
        bool $allowUnknownNim = false,
        bool $allowPublicToNonPublic = false,
        array $uploadedFiles = []
    ) {
        $this->mode = $mode;
        $this->actor = $actor;
        $this->allowUnknownNim = $allowUnknownNim;
        $this->allowPublicToNonPublic = $allowPublicToNonPublic;
        $this->uploadedFiles = $uploadedFiles;
    }

    /**
     * Siapkan cache kategori dan cache NIM valid (whereIn sekali per batch).
     */
    public function prepareBatchCache(array $rows): void
    {
        // 1. Kumpulkan semua NIM dari baris-baris
        $allNims = [];
        foreach ($rows as $row) {
            $nimVal = $row['nim'] ?? '';
            $rawNim = is_array($nimVal) ? implode(';', $nimVal) : (string) $nimVal;
            if ($rawNim !== '') {
                $parts = preg_split('/[;,]+/', $rawNim);
                foreach ($parts as $p) {
                    $clean = trim($p);
                    if ($clean !== '' && ctype_digit($clean)) {
                        $allNims[] = $clean;
                    }
                }
            }
        }
        $allNims = array_values(array_unique($allNims));

        if (!empty($allNims)) {
            $this->verifiedNimsCache = User::whereHas('roles', fn($q) => $q->whereIn('name', ['mahasiswa', 'Mahasiswa']))
                ->whereIn('employee_id', $allNims)
                ->pluck('employee_id')
                ->map(fn($v) => (string) $v)
                ->flip()
                ->toArray();
        }

        // 2. Cache seluruh folder yang ada
        $categories = DocumentCategory::with(['parent'])->get();
        foreach ($categories as $cat) {
            $path = strtolower(trim($cat->getFullPath()));
            $this->categoriesByPath[$path] = $cat;
        }
    }

    /**
     * Validasi satu batch penuh data.
     */
    public function validateBatch(array $rows): array
    {
        $this->prepareBatchCache($rows);

        $validatedRows = [];
        $seenOldNames = [];
        $matchedUploadFiles = [];

        // Deteksi duplikasi Nama Lama di dalam berkas
        $duplicateOldNames = [];
        foreach ($rows as $r) {
            $oldName = strtolower(trim((string) ($r['old_name'] ?? '')));
            if ($oldName !== '') {
                if (isset($seenOldNames[$oldName])) {
                    $duplicateOldNames[$oldName] = true;
                }
                $seenOldNames[$oldName] = true;
            }
        }

        foreach ($rows as $row) {
            $res = $this->validateRow($row, $duplicateOldNames, $matchedUploadFiles);
            $validatedRows[] = $res;
        }

        // Mode Upload: cek berkas yang diunggah tapi tidak ada di baris Excel
        if ($this->mode === 'upload') {
            $unmatchedFiles = [];
            foreach ($this->uploadedFiles as $cleanName => $realFile) {
                if (!isset($matchedUploadFiles[$cleanName])) {
                    $unmatchedFiles[] = basename($realFile);
                }
            }

            if (!empty($unmatchedFiles)) {
                // Tambahkan error ke baris atau catatan
                foreach ($validatedRows as &$vr) {
                    $vr['messages'][] = [
                        'type' => 'error',
                        'text' => 'Terdapat berkas fisik tanpa baris Excel: ' . implode(', ', array_slice($unmatchedFiles, 0, 5)),
                    ];
                    if ($vr['status'] !== 'error') {
                        $vr['status'] = 'error';
                    }
                }
            }
        }

        return $validatedRows;
    }

    /**
     * Validasi satu baris data import.
     */
    public function validateRow(array $row, array $duplicateOldNames = [], array &$matchedUploadFiles = []): array
    {
        $errors = [];
        $warnings = [];

        if (!empty($row['_parsing_errors']) && is_array($row['_parsing_errors'])) {
            foreach ($row['_parsing_errors'] as $pErr) {
                $errors[] = $pErr;
            }
        }

        $oldName = trim((string) ($row['old_name'] ?? ''));
        $newName = trim((string) ($row['new_name'] ?? ''));
        $rawRole = trim((string) ($row['role'] ?? ''));
        $rawFolder = trim((string) ($row['folder'] ?? ''));
        $rawNimVal = $row['nim'] ?? '';
        $rawNim = is_array($rawNimVal) ? implode(';', $rawNimVal) : trim((string) $rawNimVal);
        $rawDocType = trim((string) ($row['document_type'] ?? ''));
        $rawKeywordsVal = $row['keywords'] ?? '';
        $rawKeywords = is_array($rawKeywordsVal) ? implode(',', $rawKeywordsVal) : trim((string) $rawKeywordsVal);

        $normDocType = $this->normalizeDocumentType($rawDocType);
        $normKeywords = $this->normalizeKeywords($rawKeywords);

        // 1. Validasi Nama Lama
        if ($oldName === '') {
            $errors[] = 'Nama Lama wajib diisi.';
        } elseif (isset($duplicateOldNames[strtolower($oldName)])) {
            $errors[] = "Nama Lama '{$oldName}' duplikat di dalam file import.";
        }

        // 2. Validasi Nama Baru
        if ($newName === '' && $this->mode === 'upload') {
            $errors[] = 'Nama Baru wajib diisi.';
        }

        // 3. Validasi Role Dokumen
        $roleMap = config('documents.import_role_map', [
            'Mahasiswa'      => 'mahasiswa',
            'Dosen dan Staf' => 'dosen_staf',
            'Akademik'       => 'akademik',
            'Publik'         => 'publik',
        ]);

        $accessGroup = null;
        if ($rawRole === '') {
            if ($this->mode === 'upload') {
                $errors[] = 'Role Dokumen wajib dipilih (Mahasiswa, Dosen dan Staf, Akademik, atau Publik).';
            }
        } elseif (!isset($roleMap[$rawRole])) {
            $errors[] = "Role Dokumen '{$rawRole}' tidak valid. Hanya boleh bernilai: " . implode(', ', array_keys($roleMap)) . '.';
        } else {
            $accessGroup = AccessGroup::tryFrom($roleMap[$rawRole]);
        }

        // 4. Validasi Folder & Konflik Role vs Folder
        $category = null;
        if ($rawFolder === '') {
            if ($this->mode === 'upload') {
                $errors[] = 'Folder tujuan wajib diisi.';
            }
        } else {
            $cleanFolderPath = strtolower(trim($rawFolder));
            $category = $this->categoriesByPath[$cleanFolderPath] ?? DocumentCategory::findByFullPath($rawFolder);

            if (!$category) {
                $errors[] = "Folder '{$rawFolder}' tidak ditemukan di sistem.";
            } else {
                // Cek konflik Role vs Root Folder
                $rootCategory = $category->getRoot();
                $rootAg = $rootCategory->access_group instanceof AccessGroup
                    ? $rootCategory->access_group
                    : AccessGroup::tryFrom((string) $rootCategory->access_group);

                if ($accessGroup !== null && $rootAg !== null && $accessGroup !== $rootAg) {
                    $errors[] = "Konflik Role vs Folder: Role dokumen ('{$rawRole}') tidak sesuai dengan kategori root folder ('{$rootAg->label()}').";
                }

                // Cek izin role Petugas
                if ($this->actor && $this->actor->hasRole(['Petugas', 'petugas'])) {
                    if ($rootAg !== AccessGroup::PUBLIK) {
                        $errors[] = "Petugas hanya memiliki hak akses untuk folder publik.";
                    }
                }
            }
        }

        // 5. Validasi NIM
        $cleanNims = [];
        if ($rawNim !== '') {
            // Deteksi notasi ilmiah atau kehilangan nol
            if (preg_match('/[0-9]+(\.[0-9]+)?E\+[0-9]+/i', $rawNim)) {
                $errors[] = "Kolom NIM memuat notasi ilmiah ('{$rawNim}'). Format kolom NIM di Excel wajib Teks.";
            } else {
                $minLen = (int) config('documents.nim_min_length', 8);
                $maxLen = (int) config('documents.nim_max_length', 15);

                $splitNims = preg_split('/[;,]+/', $rawNim);
                foreach ($splitNims as $item) {
                    $trimmed = trim($item);
                    if ($trimmed === '') continue;

                    if (!ctype_digit($trimmed) || strlen($trimmed) < $minLen || strlen($trimmed) > $maxLen) {
                        $errors[] = "NIM '{$trimmed}' tidak valid (wajib hanya digit, panjang {$minLen}-{$maxLen}).";
                        continue;
                    }

                    $cleanNims[] = $trimmed;

                    // Cek NIM terdaftar di tabel users
                    $isVerified = isset($this->verifiedNimsCache[$trimmed]) ||
                        User::whereHas('roles', fn($q) => $q->whereIn('name', ['mahasiswa', 'Mahasiswa']))
                            ->where('employee_id', $trimmed)
                            ->exists();

                    if (! $isVerified) {
                        if ($this->allowUnknownNim) {
                            $warnings[] = "NIM '{$trimmed}' tidak terdaftar pada data mahasiswa (tetap diizinkan sesuai persetujuan).";
                        } else {
                            $errors[] = "NIM '{$trimmed}' tidak dikenal di sistem mahasiswa.";
                        }
                    }
                }
                $cleanNims = array_values(array_unique($cleanNims));
            }
        }

        // Role Mahasiswa wajib ada NIM
        if ($rawRole === 'Mahasiswa' && empty($cleanNims)) {
            $errors[] = 'Dokumen dengan Role Mahasiswa wajib memiliki setidaknya satu NIM.';
        }

        // Role non-Mahasiswa dengan NIM -> warning (NIM diabaikan)
        if ($rawRole !== '' && $rawRole !== 'Mahasiswa' && !empty($cleanNims)) {
            $warnings[] = "Role '{$rawRole}' tidak memerlukan NIM. NIM akan diabaikan.";
        }

        // Cek jika ada angka menyerupai NIM di Nama Baru tapi tidak ada di kolom NIM
        if ($newName !== '') {
            $minLen = (int) config('documents.nim_min_length', 8);
            $maxLen = (int) config('documents.nim_max_length', 15);
            preg_match_all('/(?<!\d)(\d{' . $minLen . ',' . $maxLen . '})(?!\d)/', $newName, $matches);
            $detectedInTitle = array_unique($matches[1] ?? []);
            foreach ($detectedInTitle as $det) {
                if (!in_array($det, $cleanNims, true)) {
                    $warnings[] = "Angka '{$det}' terdeteksi pada Nama Baru namun tidak ada di kolom NIM.";
                }
            }
        }

        // 6. Validasi Spesifik Mode Upload
        $matchedFile = null;
        if ($this->mode === 'upload' && $oldName !== '') {
            $matchedFile = $this->findUploadedFile($oldName);
            if (!$matchedFile) {
                $errors[] = "Berkas fisik untuk '{$oldName}' tidak ditemukan di antara berkas yang diunggah.";
            } else {
                $matchedUploadFiles[$matchedFile['clean_name']] = true;

                // Cek MIME dan ekstensi
                $ext = strtolower(pathinfo($matchedFile['real_path'], PATHINFO_EXTENSION));
                $allowedMimes = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'rar'];
                if (!in_array($ext, $allowedMimes, true)) {
                    $errors[] = "Format berkas '{$ext}' tidak didukung.";
                }
                if (file_exists($matchedFile['real_path']) && filesize($matchedFile['real_path']) > 15728640) {
                    $errors[] = "Ukuran berkas melebihi batas maksimal 15 MB.";
                }
            }
        }

        // 7. Validasi Spesifik Mode Update
        $matchedDocument = null;
        $willBecomeNonPublic = false;
        $diff = [];

        if ($this->mode === 'update' && $oldName !== '') {
            $matchResult = $this->findExistingDocument($oldName);
            if ($matchResult['status'] === 'not_found') {
                $errors[] = "Dokumen dengan judul atau nama berkas '{$oldName}' tidak ditemukan.";
            } elseif ($matchResult['status'] === 'ambiguous') {
                $ids = implode(', ', $matchResult['candidate_ids']);
                $errors[] = "Ditemukan lebih dari 1 dokumen yang cocok dengan '{$oldName}' (ID: {$ids}). Mohon gunakan judul yang spesifik.";
            } else {
                $matchedDocument = $matchResult['document'];

                // Hitung Diff sebelum/sesudah
                $oldTitle = $matchedDocument->title;
                $oldFolder = $matchedDocument->category ? $matchedDocument->category->getFullPath() : 'Root';
                $oldAg = $matchedDocument->access_group instanceof AccessGroup ? $matchedDocument->access_group->label() : ($matchedDocument->access_group ?? 'Publik');
                $oldDocType = $matchedDocument->document_type ?? '-';
                $oldNims = $matchedDocument->nims()->pluck('nim')->toArray();

                $targetAg = $accessGroup ? $accessGroup->label() : $oldAg;
                $targetFolder = $category ? $category->getFullPath() : $oldFolder;
                $targetTitle = $newName !== '' ? $newName : $oldTitle;
                $targetDocType = $normDocType !== '' ? $normDocType : $oldDocType;
                $targetNims = !empty($cleanNims) ? $cleanNims : $oldNims;

                $diff = [
                    'title'         => ['old' => $oldTitle, 'new' => $targetTitle],
                    'folder'        => ['old' => $oldFolder, 'new' => $targetFolder],
                    'access_group'  => ['old' => $oldAg, 'new' => $targetAg],
                    'document_type' => ['old' => $oldDocType, 'new' => $targetDocType],
                    'nims'          => ['old' => implode('; ', $oldNims), 'new' => implode('; ', $targetNims)],
                ];

                // Cek apakah dokumen publik akan menjadi non-publik
                if ($matchedDocument->isPublic() && $accessGroup !== null && $accessGroup !== AccessGroup::PUBLIK) {
                    $willBecomeNonPublic = true;
                    if (! $this->allowPublicToNonPublic) {
                        $errors[] = "Dokumen publik akan diubah menjadi non-publik. Diperlukan konfirmasi persetujuan.";
                    } else {
                        $warnings[] = "Perhatian: Dokumen ini tidak akan lagi dapat diakses secara publik.";
                    }
                }
            }
        }

        // Tentukan status baris
        $status = 'ok';
        if (!empty($errors)) {
            $status = 'error';
        } elseif (!empty($warnings)) {
            $status = 'warning';
        }

        $allMessages = [];
        foreach ($errors as $e) {
            $allMessages[] = ['type' => 'error', 'message' => $e, 'text' => $e];
        }
        foreach ($warnings as $w) {
            $allMessages[] = ['type' => 'warning', 'message' => $w, 'text' => $w];
        }

        return [
            'original_name'           => $oldName,
            'status'                  => $status,
            'messages'                => $allMessages,
            'matched_file'            => $matchedFile ? $matchedFile['real_path'] : null,
            'document_id'             => $matchedDocument?->id,
            'will_become_non_public'  => $willBecomeNonPublic,
            'diff'                    => $diff,
            'parsed_data'             => [
                'old_name'      => $oldName,
                'new_name'      => $newName,
                'role'          => $rawRole,
                'access_group'  => $accessGroup?->value,
                'folder'        => $rawFolder,
                'category_id'   => $category?->id,
                'nim'           => implode(';', $cleanNims),
                'nims_array'    => $cleanNims,
                'document_type' => $normDocType,
                'keywords'      => $normKeywords,
            ],
        ];
    }

    /**
     * Cari berkas yang diunggah berdasarkan nama lama (case-insensitive, toleran ekstensi).
     */
    protected function findUploadedFile(string $oldName): ?array
    {
        $cleanSearch = strtolower(trim($oldName));
        $searchWithoutExt = strtolower(pathinfo($cleanSearch, PATHINFO_FILENAME));

        $matches = [];
        foreach ($this->uploadedFiles as $key => $realPath) {
            $fileBase = strtolower(pathinfo($key, PATHINFO_BASENAME));
            $fileNameNoExt = strtolower(pathinfo($key, PATHINFO_FILENAME));

            if ($fileBase === $cleanSearch || $fileNameNoExt === $cleanSearch || $fileNameNoExt === $searchWithoutExt) {
                $matches[] = [
                    'clean_name' => $key,
                    'real_path'  => $realPath,
                ];
            }
        }

        return count($matches) === 1 ? $matches[0] : null;
    }

    /**
     * Cari dokumen lama (Priority 1: judul persis; Priority 2: nama berkas asli).
     */
    protected function findExistingDocument(string $oldName): array
    {
        $cleanSearch = trim($oldName);

        // 1. Prioritas Judul
        $byTitle = Document::whereRaw('LOWER(title) = ?', [strtolower($cleanSearch)])->get();
        if ($byTitle->count() === 1) {
            return ['status' => 'found', 'document' => $byTitle->first()];
        }
        if ($byTitle->count() > 1) {
            return ['status' => 'ambiguous', 'candidate_ids' => $byTitle->pluck('id')->toArray()];
        }

        // 2. Prioritas Nama Berkas
        $byFile = Document::where(function ($q) use ($cleanSearch) {
            $q->whereRaw('LOWER(file_name) = ?', [strtolower($cleanSearch)])
              ->orWhereRaw('LOWER(original_file_name) = ?', [strtolower($cleanSearch)]);
        })->get();

        if ($byFile->count() === 1) {
            return ['status' => 'found', 'document' => $byFile->first()];
        }
        if ($byFile->count() > 1) {
            return ['status' => 'ambiguous', 'candidate_ids' => $byFile->pluck('id')->toArray()];
        }

        return ['status' => 'not_found'];
    }

    protected function normalizeDocumentType(string $val): string
    {
        $trimmed = trim($val);
        if ($trimmed === '') return '';
        $collapsed = preg_replace('/\s+/', ' ', $trimmed);
        return ucwords(strtolower($collapsed));
    }

    protected function normalizeKeywords(string $val): string
    {
        $trimmed = trim($val);
        if ($trimmed === '') return '';
        $items = array_filter(array_map('trim', explode(',', $trimmed)), fn($v) => $v !== '');
        $items = array_map('strtolower', $items);
        return implode(', ', array_unique($items));
    }
}
