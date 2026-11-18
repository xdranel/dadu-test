<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessDocumentImportBatch;
use App\Models\DocumentCategory;
use App\Models\DocumentImportBatch;
use App\Models\DocumentImportRow;
use App\Services\DocumentImportParser;
use App\Services\DocumentImportTemplateService;
use App\Services\DocumentImportValidator;
use App\Services\DocumentImportZipExtractor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class DocumentImportController extends Controller
{
    /**
     * Tampilkan formulir impor massal dokumen.
     */
    public function create()
    {
        $user = auth()->user();
        if (!$user->can('document_create') && !$user->can('document_update')) {
            abort(403, 'Anda tidak memiliki hak akses untuk melakukan impor dokumen.');
        }

        $canUpload = $user->can('document_create');
        $canUpdate = $user->can('document_update');

        $activeBatches = DocumentImportBatch::where('user_id', $user->id)
            ->whereIn('status', ['staging', 'processing'])
            ->latest()
            ->limit(5)
            ->get();

        return view('documents.import.create', compact('canUpload', 'canUpdate', 'activeBatches'));
    }

    /**
     * Unduh berkas template impor (XLSX atau CSV).
     */
    public function template(Request $request, DocumentImportTemplateService $service)
    {
        $user = auth()->user();
        if (!$user->can('document_create') && !$user->can('document_update')) {
            abort(403);
        }

        $format = strtolower($request->query('format', 'xlsx'));
        $tempPath = tempnam(sys_get_temp_dir(), 'tpl_');

        if ($format === 'csv') {
            $csvFile = $tempPath . '.csv';
            $service->generateCsv($csvFile, $user);
            return response()->streamDownload(function () use ($csvFile) {
                readfile($csvFile);
                @unlink($csvFile);
            }, 'Template_Import_Dokumen.csv', [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
        }

        $xlsxFile = $tempPath . '.xlsx';
        $service->generateXlsx($xlsxFile, $user);
        return response()->streamDownload(function () use ($xlsxFile) {
            readfile($xlsxFile);
            @unlink($xlsxFile);
        }, 'Template_Import_Dokumen.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Unggah berkas Excel/CSV dan berkas pendukung, parse, dan simpan di staging.
     */
    public function store(
        Request $request,
        DocumentImportParser $parser,
        DocumentImportZipExtractor $zipExtractor
    ) {
        $user = auth()->user();
        $mode = $request->input('mode', 'upload');

        if ($mode === 'upload' && !$user->can('document_create')) {
            abort(403, 'Anda tidak memiliki izin mengunggah dokumen baru.');
        }
        if ($mode === 'update' && !$user->can('document_update')) {
            abort(403, 'Anda tidak memiliki izin memperbarui dokumen.');
        }

        $request->validate([
            'mode'                       => 'required|in:upload,update',
            'excel_file'                 => 'required|file|max:20480', // 20 MB
            'zip_file'                   => 'nullable|file|mimes:zip|max:524288', // 500 MB
            'files'                      => 'nullable|array',
            'files.*'                    => 'file|max:15360', // 15 MB per file
            'allow_unknown_nim'          => 'nullable|boolean',
            'allow_public_to_non_public' => 'nullable|boolean',
        ]);

        $allowUnknownNim = $request->boolean('allow_unknown_nim');
        $allowPublicToNonPublic = $request->boolean('allow_public_to_non_public');

        // Buat batch baru di staging
        $batch = DocumentImportBatch::create([
            'user_id' => $user->id,
            'mode'    => $mode,
            'status'  => 'staging',
        ]);

        $stagingDir = "imports/{$batch->id}";
        Storage::disk('private')->makeDirectory($stagingDir);

        // Simpan file Excel/CSV
        $excelUploaded = $request->file('excel_file');
        $excelExt = $excelUploaded->getClientOriginalExtension();
        $excelLocalPath = Storage::disk('private')->path("{$stagingDir}/template.{$excelExt}");
        $excelUploaded->move(dirname($excelLocalPath), basename($excelLocalPath));

        $batch->update(['file_path' => "{$stagingDir}/template.{$excelExt}"]);

        // Tangani berkas fisik untuk mode Upload
        $uploadedFilesMap = [];
        if ($mode === 'upload') {
            $filesDir = "{$stagingDir}/files";
            Storage::disk('private')->makeDirectory($filesDir);
            $realFilesDir = Storage::disk('private')->path($filesDir);

            // Ekstrak ZIP jika ada
            if ($request->hasFile('zip_file')) {
                $zipUploaded = $request->file('zip_file');
                $zipExtracted = $zipExtractor->extract($zipUploaded->getRealPath(), $realFilesDir);
                foreach ($zipExtracted as $name => $path) {
                    $uploadedFilesMap[strtolower($name)] = $path;
                }
            }

            // Simpan berkas individual jika ada
            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $f) {
                    $orig = $f->getClientOriginalName();
                    $destPath = $realFilesDir . DIRECTORY_SEPARATOR . $orig;
                    $f->move($realFilesDir, $orig);
                    $uploadedFilesMap[strtolower($orig)] = $destPath;
                }
            }
        }

        // Parse berkas template
        $parsedRows = $parser->parse($excelLocalPath);

        // Validasi baris-baris
        $validator = new DocumentImportValidator(
            $mode,
            $user,
            $allowUnknownNim,
            $allowPublicToNonPublic,
            $uploadedFilesMap
        );

        $validatedRows = $validator->validateBatch($parsedRows);

        // Simpan baris-baris ke database
        $okCount = 0;
        $warningCount = 0;
        $errorCount = 0;

        foreach ($validatedRows as $i => $vr) {
            $rowNumber = $parsedRows[$i]['_row_number'] ?? ($i + 2);
            $status = $vr['status'];

            if ($status === 'ok') $okCount++;
            elseif ($status === 'warning') $warningCount++;
            elseif ($status === 'error') $errorCount++;

            DocumentImportRow::create([
                'batch_id'      => $batch->id,
                'row_number'    => $rowNumber,
                'original_name' => $vr['original_name'],
                'data'          => $vr,
                'status'        => $status,
                'messages'      => $vr['messages'],
                'document_id'   => $vr['document_id'] ?? null,
            ]);
        }

        $batch->update([
            'summary' => [
                'total'     => count($validatedRows),
                'ok'        => $okCount,
                'warning'   => $warningCount,
                'error'     => $errorCount,
                'skipped'   => 0,
                'mode'      => $mode,
            ],
        ]);

        activity('document_import_batch')
            ->performedOn($batch)
            ->withProperties([
                'batch_id' => $batch->id,
                'mode'     => $mode,
                'total'    => count($validatedRows),
                'errors'   => $errorCount,
            ])
            ->log("Membuat batch import dokumen ({$mode}) dengan " . count($validatedRows) . " baris");

        return redirect()->route('documents.import.preview', $batch);
    }

    /**
     * Halaman pratinjau batch impor.
     */
    public function preview(DocumentImportBatch $batch)
    {
        $this->authorizeBatchAccess($batch);

        $rows = $batch->rows()->paginate(50);
        $summary = $batch->summary ?? [
            'total'   => $batch->rows()->count(),
            'ok'      => $batch->rows()->where('status', 'ok')->count(),
            'warning' => $batch->rows()->where('status', 'warning')->count(),
            'error'   => $batch->rows()->where('status', 'error')->count(),
            'skipped' => $batch->rows()->where('status', 'skipped')->count(),
        ];

        $hasUnignoredErrors = $batch->rows()->where('status', 'error')->exists();

        return view('documents.import.preview', compact('batch', 'rows', 'summary', 'hasUnignoredErrors'));
    }

    /**
     * Edit baris di tempat (inline edit) dan validasi ulang baris tersebut.
     */
    public function updateRow(Request $request, DocumentImportBatch $batch, DocumentImportRow $row)
    {
        $this->authorizeBatchAccess($batch);

        if ($row->batch_id !== $batch->id) {
            abort(404);
        }

        $inputData = $request->validate([
            'new_name'      => 'nullable|string',
            'document_type' => 'nullable|string',
            'nim'           => 'nullable|string',
            'keywords'      => 'nullable|string',
            'folder'        => 'nullable|string',
            'role'          => 'nullable|string',
        ]);

        $currentData = $row->data ?? [];
        $parsed = $currentData['parsed_data'] ?? [];

        foreach ($inputData as $k => $v) {
            if ($v !== null) {
                $parsed[$k] = $v;
            }
        }

        $validator = new DocumentImportValidator(
            $batch->mode,
            auth()->user(),
            true, // Izinkan warning saat edit manual
            true
        );

        $validated = $validator->validateRow($parsed);

        $row->update([
            'data'     => $validated,
            'status'   => $validated['status'],
            'messages' => $validated['messages'],
        ]);

        $this->recalculateBatchSummary($batch);

        return response()->json([
            'ok'      => true,
            'status'  => $row->status,
            'messages'=> $row->messages,
            'summary' => $batch->summary,
        ]);
    }

    /**
     * Toggle abaikan / lewati baris (skip).
     */
    public function toggleSkip(DocumentImportBatch $batch, DocumentImportRow $row)
    {
        $this->authorizeBatchAccess($batch);

        if ($row->batch_id !== $batch->id) {
            abort(404);
        }

        if ($row->status === 'skipped') {
            $messages = $row->messages ?? [];
            $hasError = collect($messages)->contains('type', 'error');
            $row->update(['status' => $hasError ? 'error' : 'ok']);
        } else {
            $row->update(['status' => 'skipped']);
        }

        $this->recalculateBatchSummary($batch);

        return back()->with('success', "Status baris #{$row->row_number} berhasil diubah.");
    }

    /**
     * Unduh laporan baris error dalam format CSV.
     */
    public function exportErrors(DocumentImportBatch $batch)
    {
        $this->authorizeBatchAccess($batch);

        $errorRows = $batch->rows()->where('status', 'error')->get();
        $tempPath = tempnam(sys_get_temp_dir(), 'err_') . '.csv';

        $fp = fopen($tempPath, 'w');
        fwrite($fp, "\xEF\xBB\xBF"); // BOM

        fputcsv($fp, ['Baris', 'Nama Lama', 'Nama Baru', 'Role', 'Folder', 'NIM', 'Pesan Kesalahan']);

        foreach ($errorRows as $r) {
            $data = $r->data['parsed_data'] ?? [];
            $errTexts = collect($r->messages)->where('type', 'error')->pluck('text')->implode(' | ');

            fputcsv($fp, [
                $r->row_number,
                $r->original_name,
                $data['new_name'] ?? '',
                $data['role'] ?? '',
                $data['folder'] ?? '',
                $data['nim'] ?? '',
                $errTexts,
            ]);
        }

        fclose($fp);

        return response()->download($tempPath, "Laporan_Error_Batch_{$batch->id}.csv")
            ->deleteFileAfterSend(true);
    }

    /**
     * Konfirmasi impor dan jalankan queue job.
     */
    public function confirm(Request $request, DocumentImportBatch $batch)
    {
        $this->authorizeBatchAccess($batch);

        $user = auth()->user();
        if ($batch->mode === 'upload' && !$user->can('document_create')) {
            abort(403);
        }
        if ($batch->mode === 'update' && !$user->can('document_update')) {
            abort(403);
        }

        // Validasi ulang: pastikan tidak ada baris error yang belum diabaikan
        $hasErrors = $batch->rows()->where('status', 'error')->exists();
        if ($hasErrors) {
            return back()->withErrors(['confirm' => 'Tidak dapat melanjutkan impor. Masih terdapat baris yang memiliki status error. Mohon perbaiki atau abaikan baris tersebut.']);
        }

        $batch->update(['status' => 'processing']);

        activity('document_import_batch')
            ->performedOn($batch)
            ->withProperties(['batch_id' => $batch->id, 'mode' => $batch->mode])
            ->log("Mengonfirmasi eksekusi batch import dokumen ({$batch->mode})");

        // Dispatch job ke queue
        ProcessDocumentImportBatch::dispatch($batch->id, $user->id);

        return redirect()->route('documents.import.preview', $batch)
            ->with('success', 'Batch impor sedang diproses.');
    }

    /**
     * Batalkan batch impor dan bersihkan berkas sementara.
     */
    public function cancel(DocumentImportBatch $batch)
    {
        $this->authorizeBatchAccess($batch);

        $batch->update(['status' => 'cancelled']);

        $dir = "imports/{$batch->id}";
        if (Storage::disk('private')->exists($dir)) {
            Storage::disk('private')->deleteDirectory($dir);
        }

        activity('document_import_batch')
            ->performedOn($batch)
            ->withProperties(['batch_id' => $batch->id])
            ->log("Membatalkan batch import dokumen ID {$batch->id}");

        return redirect()->route('documents.import.create')
            ->with('success', 'Batch impor telah dibatalkan dan berkas sementara telah dibersihkan.');
    }

    /**
     * Status polling endpoint (JSON).
     */
    public function status(DocumentImportBatch $batch)
    {
        $this->authorizeBatchAccess($batch);

        return response()->json([
            'id'      => $batch->id,
            'status'  => $batch->status,
            'summary' => $batch->summary,
        ]);
    }

    protected function authorizeBatchAccess(DocumentImportBatch $batch): void
    {
        $user = auth()->user();
        if (!$user) {
            abort(401);
        }

        // Pembuat batch atau role admin / Akademik
        $isOwner = $batch->user_id === $user->id;
        $isAdmin = $user->hasRole(['admin', 'Akademik', 'superadmin']);

        if (!$isOwner && !$isAdmin) {
            abort(403, 'Anda tidak memiliki hak untuk mengakses batch impor ini.');
        }
    }

    protected function recalculateBatchSummary(DocumentImportBatch $batch): void
    {
        $total = $batch->rows()->count();
        $ok = $batch->rows()->where('status', 'ok')->count();
        $warning = $batch->rows()->where('status', 'warning')->count();
        $error = $batch->rows()->where('status', 'error')->count();
        $skipped = $batch->rows()->where('status', 'skipped')->count();

        $batch->update([
            'summary' => [
                'total'   => $total,
                'ok'      => $ok,
                'warning' => $warning,
                'error'   => $error,
                'skipped' => $skipped,
                'mode'    => $batch->mode,
            ],
        ]);
    }
}
