# 56a — Koreksi test, tombol kirim, Status BPP, dan card beranda

Dokumen ini memperbaiki implementasi 56 yang sedang Anda uji, lalu menambahkan tiga permintaan terbaru. Terapkan di folder asli. Asisten hanya menulis dokumentasi; source aplikasi tidak diubah.

**Lanjutan koreksi:** gunakan [56b](56b-koreksi-letak-test-disabled-dan-krs-dummy.md) untuk pemindahan test BPP yang salah file, tampilan disabled, dan inisialisasi periode akademik dummy. Target/jumlah file pada panduan ini adalah baseline sebelum 56b.

## 1. Penyebab yang sudah terbukti

Rescan folder asli dibandingkan kode 56 menemukan:

1. `SidangTaReviewExport.php` sudah memanggil `Eligibility::requiredTypes()` tetapi import masih `SidangTaDocumentRequirements as Requirements`. Ini menyebabkan `Class "App\Services\Eligibility" not found`; sama dengan output kegagalan yang Anda kirim.
2. Assertion snapshot EPRT memakai `toBe()` langsung pada array sebelum dan sesudah penyimpanan JSON. Output menunjukkan key yang sama dan nilai yang sama tetapi urutannya berbeda. Urutan key object JSON bukan kontrak penyimpanan MySQL. Assertion dari panduan awal perlu diperbaiki; kode policy tidak perlu diubah.
3. Tombol pembuka modal sudah memakai `$sendBlocked`, tetapi tombol **Ya, kirim pendaftaran** masih memakai `$isMeet`, sehingga selalu disabled untuk versi 2. Keduanya harus menggunakan kondisi yang sama.
4. Guard peninjauan petugas juga tersalin lagi setelah penulisan log pada dua method. Guard yang diperlukan cukup pada `locked()` sebelum mutasi. Kode lengkap di bawah membersihkan duplikasi itu.

Sumber untuk perilaku JSON: [normalisasi JSON MySQL](https://dev.mysql.com/doc/refman/8.0/en/json.html#json-normalization). Test yang diperbaiki tetap memeriksa seluruh key, nilai, dan tipe secara ketat setelah kedua array diurutkan berdasarkan key. Pemeriksaan satu kali pembacaan fakta tetap ada.

Belum dapat dinyatakan bahwa semua 10 kegagalan sudah selesai sebelum Anda menjalankan ulang test. Temuan di atas sesuai kode dan dua output yang diberikan; tidak perlu mengganti database atau memakai SQLite.

**Tidak ada dummy 002 pada halaman petugas** sebelum POST Kirim berhasil adalah perilaku yang benar. Draft belum menjadi pengajuan final. Jangan menambah pengajuan manual atau mengulang import hanya untuk memunculkannya.

## 2. Perbaikan kode ekspor, guard petugas, dan tombol

Tiga file berikut diberikan lengkap agar import, guard, dan kedua tombol tidak terlewat saat mengganti fragmen.

### `app/Services/SidangTaReviewExport.php`

Ganti seluruh isi file berikut.

```php
<?php

namespace App\Services;

use App\Models\SidangTaRegistration;
use App\Support\SidangTaReviewEligibility as Eligibility;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Rap2hpoutre\FastExcel\FastExcel;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class SidangTaReviewExport
{
    public const SNAPSHOT_COLUMNS = [
        'Snapshot NIM' => 'student_number',
        'Snapshot Nama' => 'student_name',
        'Snapshot Prodi' => 'study_program',
        'Email Kampus' => 'student_email',
        'Semester' => 'semester',
        'IPK' => 'gpa',
        'SKS Lulus' => 'passed_credits',
        'Sidang Tingkat 2' => 'sidang_tingkat_2',
        'KRS Sidang TA 2' => 'ta2_krs_label',
        'Judul TA Indonesia' => 'ta_title',
        'Judul TA Inggris' => 'ta_title_en',
        'Pembimbing 1' => 'advisor_1',
        'Pembimbing 2' => 'advisor_2',
        'Penguji 1' => 'examiner_1',
        'Penguji 2' => 'examiner_2',
        'Penguji 3' => 'examiner_3',
        'Tanggal Sidang' => 'exam_date',
        'Waktu Sidang' => 'exam_time',
        'Lokasi Sidang' => 'exam_location',
        'TAK' => 'tak_score',
        'EPrT' => 'eprt_score',
        'Status BPP' => 'bpp_status',
        'Status SKPI' => 'skpi_status',
        'Status TAK' => 'skpi_tak',
        'Sidang Tingkat 3' => 'level_3_status',
        'Status i-Basila' => 'ibasila_status',
        'Sumber Akademik Diperbarui' => 'academic_source_updated_at',
    ];

    private function text(mixed $value): string
    {
        if ($value === null || (is_string($value) && in_array(trim($value), ['', '-'], true))) return 'Belum tersedia';
        if (is_bool($value)) return $value ? 'Ya' : 'Tidak';
        if (is_scalar($value)) return (string) $value;
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)
            ?: 'Belum tersedia';
    }

    public function row(SidangTaRegistration $registration): array
    {
        $snapshot = is_array($registration->academic_snapshot) ? $registration->academic_snapshot : [];
        $row = [
            'ID Pengajuan' => (string) $registration->id,
            'Periode Sidang TA' => $this->text($registration->period?->name),
            'NIM' => $this->text($registration->student_number),
            'Nama Mahasiswa' => $this->text($registration->student_name),
            'Program Studi' => $this->text($registration->study_program),
            'HP Mahasiswa' => $this->text($registration->student_phone),
            'HP Orang Tua/Wali' => $this->text($registration->parent_phone),
            'Tanggal Bayar Wisuda' => $this->text($registration->graduation_payment_date?->format('Y-m-d')),
            'Biodata Ijazah' => $registration->diploma_biodata_accurate ? 'Sudah sesuai' : 'Belum sesuai (PDM)',
            'Status Pengajuan' => SidangTaReviewQuery::STATUSES[$registration->status] ?? $this->text($registration->status),
            'Dikirim' => $this->text($registration->submitted_at?->format('Y-m-d H:i:s')),
            'Ditinjau' => $this->text($registration->reviewed_at?->format('Y-m-d H:i:s')),
            'Catatan Petugas' => $this->text($registration->review_notes),
            'Versi Pengajuan' => (string) $registration->lock_version,
        ];
        foreach (self::SNAPSHOT_COLUMNS as $label => $key) $row[$label] = $this->text($snapshot[$key] ?? null);
        $required = Eligibility::requiredTypes($registration);
        $labels = ['pending' => 'Menunggu verifikasi', 'verified' => 'Terverifikasi', 'rejected' => 'Perlu perbaikan'];
        foreach (SidangTaRegistration::DOCUMENT_TYPES as $type => $label) {
            $applicable = in_array($type, $required, true);
            $document = $registration->documents->firstWhere('document_type', $type);
            $row[$label.' — Status'] = !$applicable ? 'N/A' : (!$document ? 'Belum ada dokumen'
                : ($labels[$document->status] ?? $this->text($document->status)));
            $row[$label.' — Ketersediaan'] = !$applicable ? 'N/A' : (!$document ? 'Tidak ada metadata'
                : (Storage::disk('local')->exists($document->file_path) ? 'Tersedia' : 'Tidak ditemukan'));
            $row[$label.' — Catatan'] = !$applicable ? 'N/A' : $this->text($document?->notes);
        }
        return $row;
    }

    public function download(Collection $registrations): BinaryFileResponse
    {
        if ($registrations->isEmpty()) {
            throw ValidationException::withMessages(['export' => 'Tidak ada pengajuan yang cocok untuk diekspor.']);
        }
        $rows = $registrations->map(fn (SidangTaRegistration $registration) => $this->row($registration));
        $temporary = tempnam(sys_get_temp_dir(), 'dadu-ta-xlsx-');
        if ($temporary === false) throw new RuntimeException('File sementara ekspor tidak dapat dibuat.');
        $path = $temporary.'.xlsx';
        if (!@rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('File sementara XLSX tidak dapat disiapkan.');
        }
        try {
            (new FastExcel($rows))->stringValues()->setColumnFormat([
                'Semester' => 'number', 'IPK' => 'number', 'SKS Lulus' => 'number',
                'TAK' => 'number', 'EPrT' => 'number',
            ])->escapeFormulas()->export($path);
            clearstatcache(true, $path);
            return response()->download($path, 'Sidang_TA_'.now()->format('Y-m-d_H-i-s').'.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ])->setPrivate()->deleteFileAfterSend(true);
        } catch (Throwable $exception) {
            if (is_file($path)) @unlink($path);
            throw $exception;
        }
    }
}
```

### `app/Services/SidangTaRegistrationReview.php`

Ganti seluruh isi file berikut.

```php
<?php

namespace App\Services;

use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDocument;
use App\Models\User;
use App\Support\SidangTaReviewEligibility as Eligibility;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SidangTaRegistrationReview
{
    private function locked(SidangTaRegistration $registration, int $version): SidangTaRegistration
    {
        $locked = SidangTaRegistration::whereKey($registration->id)->lockForUpdate()->firstOrFail();
        abort_unless($locked->lock_version === $version, 409, 'Pengajuan berubah. Muat ulang sebelum meninjau.');
        abort_unless(in_array($locked->status, ['submitted', 'needs_review'], true), 422,
            'Pengajuan sudah diputuskan dan tidak dapat ditinjau kembali.');
        abort_unless($locked->requirements_version === 1, 422,
            'Peninjauan pengajuan ini belum dibuka. Berkas tetap dapat dilihat.');
        return $locked;
    }

    public function document(User $user, SidangTaRegistration $registration, SidangTaRegistrationDocument $document,
                             int $version, string $status, ?string $notes): SidangTaRegistration
    {
        abort_unless($user->can('sidang_ta_registration_review'), 403);
        abort_unless(in_array($status, ['verified', 'rejected'], true), 422);
        $notes = trim($notes ?? '');
        $notes = $notes === '' ? null : $notes;
        if ($status === 'rejected' && $notes === null) {
            throw ValidationException::withMessages(['notes' => 'Isi catatan perbaikan dokumen.']);
        }
        return DB::transaction(function () use ($user, $registration, $document, $version, $status, $notes) {
            $locked = $this->locked($registration, $version);
            $doc = $locked->documents()->whereKey($document->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($doc->document_type, Eligibility::requiredTypes($locked), true), 422,
                'Dokumen ini tidak berlaku untuk cabang biodata pengajuan.');
            if ($status === 'verified' && !Storage::disk('local')->exists($doc->file_path)) {
                throw ValidationException::withMessages(['document' => 'Berkas tidak ditemukan. Minta mahasiswa mengunggah ulang.']);
            }
            $oldRegistrationStatus = $locked->status;
            $nextStatus = $status === 'rejected' ? 'needs_review' : $locked->status;
            if ($doc->status === $status && $doc->notes === $notes && $locked->status === $nextStatus) return $locked;
            $labels = ['pending' => 'Menunggu verifikasi', 'verified' => 'Terverifikasi', 'rejected' => 'Perlu perbaikan'];
            $oldDocumentStatus = $labels[$doc->status] ?? $doc->status;
            $doc->status = $status;
            $doc->notes = $notes;
            $doc->save();
            $locked->status = $nextStatus;
            $locked->reviewed_by = $user->id;
            $locked->reviewed_at = now();
            $locked->lock_version++;
            $locked->save();
            $logNotes = SidangTaRegistration::DOCUMENT_TYPES[$doc->document_type].': '.$oldDocumentStatus.' → '.$labels[$status];
            if ($notes !== null) $logNotes .= '. Catatan: '.$notes;
            $locked->logAction($status === 'verified' ? 'document_verified' : 'document_rejected',
                $oldRegistrationStatus, $nextStatus, $logNotes, $user->id);
            return $locked;
        }, 3);
    }

    public function decide(User $user, SidangTaRegistration $registration, int $version,
                           string $action, ?string $notes): SidangTaRegistration
    {
        abort_unless($user->can('sidang_ta_registration_review'), 403);
        abort_unless(in_array($action, ['approved', 'needs_review', 'rejected'], true), 422);
        $notes = trim($notes ?? '');
        $notes = $notes === '' ? null : $notes;
        if (in_array($action, ['needs_review', 'rejected'], true) && $notes === null) {
            throw ValidationException::withMessages(['review_notes' => 'Isi catatan revisi atau penolakan.']);
        }
        return DB::transaction(function () use ($user, $registration, $version, $action, $notes) {
            $locked = $this->locked($registration, $version);
            if ($action === 'approved') {
                $locked->setRelation('documents', $locked->documents()->lockForUpdate()->get());
                $blockers = Eligibility::blockers($locked);
                if ($blockers !== []) {
                    throw ValidationException::withMessages(['documents' => 'Persetujuan belum dapat dilakukan: '.implode('; ', $blockers)]);
                }
            }
            $old = $locked->status;
            $locked->status = $action;
            $locked->review_notes = $notes;
            $locked->reviewed_by = $user->id;
            $locked->reviewed_at = now();
            $locked->lock_version++;
            $locked->save();
            $locked->logAction($action, $old, $action, $notes, $user->id);
            return $locked;
        }, 3);
    }
}
```

### `resources/views/sidang_ta_registrations/wizard-review.blade.php`

Ganti seluruh isi file berikut.

```php
<x-app-layout>
    <x-slot name="header">Tinjau Pendaftaran Sidang TA</x-slot>
    @php
        $payload = $draft->payload;
        $isMeet = $draft->requirements_version === 2;
        $meetContext = $meetContext ?? null;
        $sendBlocked = $isMeet && !($meetContext['can_submit'] ?? false);
        $accurate = (bool) $payload['diploma_biodata_accurate'];
        $isRevision = $draft->source_registration_id !== null;
        $permission = $isRevision ? 'sidang_ta_registration_update' : 'sidang_ta_registration_create';
        $groups = [2 => 'Verifikasi iGracias & akademik', 3 => 'Pembayaran & dokumen', 4 => 'Biodata ijazah'];
        $documentCount = array_sum(array_map('count', $documentGroups));
        $confirmLabel = $isRevision ? 'Ya, kirim revisi' : 'Ya, kirim pendaftaran';
    @endphp
    <div class="mx-auto max-w-6xl space-y-5">
        @if($errors->any())
            <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
                <p class="font-bold">Pendaftaran belum dapat dikirim.</p>
                <ul class="mt-2 list-inside list-disc space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section data-review-summary class="campus-card overflow-hidden">
            <header class="border-b border-rose-100 bg-rose-50/60 p-5 sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-[#DA2D3B]">Pemeriksaan
                            akhir{{ $isRevision ? ' · Revisi' : '' }}</p>
                        <h2 class="mt-1 text-xl font-bold text-slate-900">Tinjau sebelum kirim</h2>
                        <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-600">Periksa kembali data dan
                            seluruh berkas sebelum mengirim pendaftaran kepada petugas.</p>
                    </div>
                    <span
                        class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800">Belum dikirim</span>
                </div>
            </header>
            <div class="space-y-5 p-5 sm:p-6">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="min-w-0 rounded-xl bg-slate-50 p-4">
                        <p class="text-xs font-semibold text-slate-500">Mahasiswa</p>
                        <p class="mt-1 break-words text-base font-bold text-slate-900">{{ $student->name }}</p>
                        <p class="mt-1 break-words text-sm text-slate-600">{{ $student->student_number }}
                            · {{ $student->study_program ?: 'Program studi belum tersedia' }}</p>
                    </div>
                    <div class="min-w-0 rounded-xl bg-slate-50 p-4">
                        <p class="text-xs font-semibold text-slate-500">Periode Sidang TA</p>
                        <p class="mt-1 break-words text-base font-bold text-slate-900">{{ $draft->period->name }}</p>
                        <p class="mt-1 text-sm text-slate-600">{{ $documentCount }} dokumen dalam
                            tinjauan{{ $isRevision ? ' · Periode pengajuan asal' : '' }}</p>
                    </div>
                </div>
                <dl class="grid gap-x-6 gap-y-5 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs text-slate-500">
                            Nomor HP mahasiswa
                        </dt>
                        <dd class="mt-1 break-words text-sm font-semibold text-slate-800">
                            {{ $payload['student_phone'] }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">
                            Nomor HP orang tua/wali
                        </dt>
                        <dd class="mt-1 break-words text-sm font-semibold text-slate-800">
                            {{ $payload['parent_phone'] }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">
                            Tanggal pembayaran wisuda
                        </dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-800">
                            {{ \Illuminate\Support\Carbon::parse($payload['graduation_payment_date'])->translatedFormat('d M Y') }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">
                            Biodata dummy ijazah
                        </dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-800">
                            {{ $accurate ? 'Sudah sesuai' : 'Belum sesuai, bukti PDM dilampirkan' }}
                        </dd>
                    </div>
                </dl>
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
                    <p class="text-xs leading-relaxed text-slate-500">
                        Data di bawah berasal dari draft yang tersimpan. Pendaftaran belum dikirim.
                    </p>
                    <a class="text-sm font-semibold text-[#DA2D3B] hover:underline"
                       href="{{ route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 1]) }}">
                        Ubah data kontak
                    </a>
                </div>
            </div>
        </section>

        @if($isMeet)
            @include('sidang_ta_registrations.partials.meet-admission-summary', ['admission' => $meetContext['admission']])
        @endif

        @foreach($groups as $number => $title)
            @php
                $types = $documentGroups[$number];
            @endphp
            <section data-review-group="{{ $number }}" class="campus-card overflow-hidden">
                <header
                    class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4 sm:px-6">
                    <div class="flex items-center gap-3">
                        <div>
                            <h3 class="text-base font-bold text-slate-800">
                                {{ $title }}
                            </h3>
                            <p class="mt-0.5 text-xs text-slate-500">
                                Tahap {{ $number }} · {{ count($types) }} berkas tersimpan
                            </p>
                        </div>
                    </div>
                    <a class="text-sm font-semibold text-[#DA2D3B] hover:underline"
                       href="{{ route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => $number]) }}">Ubah
                        tahap {{ $number }}</a>
                </header>
                @if($isMeet && isset($meetContext['automatic'][$number]))
                    <div class="px-5 pt-4 sm:px-6">
                        @include('sidang_ta_registrations.partials.meet-automatic-cards', ['items' => $meetContext['automatic'][$number]])
                    </div>
                @endif
                <div class="divide-y divide-slate-100 px-5 sm:px-6">
                    @foreach($types as $type)
                        @php
                            $document = $draft->documents->firstWhere('document_type', $type);
                            $documentLabel = ($isMeet ? \App\Support\SidangTaMeetRequirements::LABELS[$type] ?? null : null)
                                ?? \App\Models\SidangTaRegistration::DOCUMENT_TYPES[$type] ?? $type;
                        @endphp
                        <div data-review-document="{{ $type }}"
                             class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between sm:gap-5">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold leading-relaxed text-slate-800">{{ $documentLabel }}</p>
                                <p class="mt-1 break-words text-xs leading-relaxed text-slate-500">{{ $document->file_name }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ number_format(($document->file_size ?? 0) / 1024) }}
                                    KB</p>
                            </div>
                            <a data-document-preview
                               class="btn btn-secondary shrink-0 self-start whitespace-nowrap !text-xs no-underline sm:self-auto"
                               href="{{ route('sidang-ta-draft-documents.preview', $document) }}"
                               data-preview-url="{{ route('sidang-ta-draft-documents.preview', $document) }}"
                               data-download-url="{{ route('sidang-ta-draft-documents.download', $document) }}"
                               data-file-name="{{ $document->file_name }}" data-file-type="{{ $document->file_type }}"
                               target="_blank" rel="noopener" aria-haspopup="dialog"
                               aria-label="Lihat {{ $documentLabel }}"
                               x-on:click.prevent="$dispatch('sidang-ta-preview', $el.dataset)">
                                Lihat berkas
                            </a>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach

        <section data-review-actions class="campus-card space-y-4 p-5 sm:p-6"
                 x-data="{ sending: false }" @pageshow.window="sending = false">
            <div>
                <h2 class="text-base font-bold text-slate-800">
                    Sudah memeriksa semua data?
                </h2>
                <p class="mt-1 text-sm leading-relaxed text-slate-600">
                    Pastikan periode, kontak, pembayaran, biodata, dan setiap berkas sudah benar. Anda masih dapat
                    kembali ke form untuk memperbaikinya.
                </p>
            </div>
            @if($sendBlocked)
                <p id="registration-send-readiness" data-meet-send-held role="status" class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900">
                    {{ $isRevision ? 'Pengiriman revisi ini belum tersedia. Data dan berkas tetap tersimpan.'
                        : 'Penuhi persyaratan akademik dan EPRT sebelum mengirim. Draft dan berkas tetap tersimpan.' }}
                </p>
            @endif
            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-wrap gap-2">
                    <a class="btn btn-secondary"
                       href="{{ route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 1]) }}">
                        Kembali ke form
                    </a>
                    <a class="btn btn-secondary" href="{{ route('sidang-ta-registrations.index') }}">
                        Kembali ke pendaftaran
                    </a>
                </div>
                @can($permission)
                    <button data-open-registration type="button"
                            class="btn btn-primary disabled:cursor-not-allowed disabled:opacity-50 disabled:shadow-none"
                            aria-disabled="{{ $sendBlocked ? 'true' : 'false' }}"
                            :aria-disabled="sending || {{ $sendBlocked ? 'true' : 'false' }} ? 'true' : 'false'"
                            @if($sendBlocked) aria-describedby="registration-send-readiness" @endif
                            @disabled($sendBlocked) :disabled="sending || {{ $sendBlocked ? 'true' : 'false' }}"
                            aria-haspopup="dialog" aria-controls="confirm-send-registration"
                            onclick="document.getElementById('confirm-send-registration').showModal()">Kirim Pendaftaran
                    </button>
                @endcan
            </div>
            @can($permission)
                <dialog id="confirm-send-registration" aria-labelledby="confirm-send-title"
                        aria-describedby="confirm-send-description"
                        @cancel="if (sending) $event.preventDefault()"
                        class="m-auto w-11/12 max-w-lg rounded-2xl border border-slate-200 bg-white p-5 text-slate-800 shadow-xl backdrop:bg-slate-900/50 sm:p-6">
                    <h2 id="confirm-send-title" class="text-lg font-bold">
                        {{ $isRevision ? 'Kirim revisi pendaftaran?' : 'Kirim pendaftaran sekarang?' }}
                    </h2>
                    <div id="confirm-send-description" class="mt-3 space-y-3 text-sm leading-relaxed text-slate-600">
                        <p>Periksa kembali data dan seluruh dokumen. Setelah dikirim, pengajuan akan ditinjau oleh
                            petugas.</p>
                        <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-amber-900">
                            <p class="font-semibold">Pastikan kembali:</p>
                            <ul class="mt-2 list-inside list-disc space-y-1">
                                <li>Periode Sidang TA dan nomor kontak sudah benar.</li>
                                <li>Tanggal pembayaran wisuda serta pilihan biodata sudah sesuai.</li>
                                <li>Seluruh berkas lengkap, benar, dan dapat dibaca.</li>
                            </ul>
                        </div>
                        <p>Jika masih ragu, pilih Periksa kembali. Jika sudah yakin, lanjutkan pengiriman. Perubahan
                            setelah pengiriman mengikuti proses revisi yang tersedia.</p>
                    </div>
                    <form data-final-registration-form method="POST"
                          class="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"
                          action="{{ $isRevision ? route('sidang-ta-registrations.update', $draft->source_registration_id) : route('sidang-ta-registrations.store') }}"
                          @submit="if (sending || {{ $sendBlocked ? 'true' : 'false' }}) { $event.preventDefault(); } else { sending = true; }">
                        @csrf
                        @if($isRevision)
                            @method('PUT')
                        @endif
                        <input type="hidden" name="draft_id" value="{{ $draft->id }}">
                        <input type="hidden" name="version" value="{{ $draft->version }}">
                        <input type="hidden" name="action" value="submit">
                        <button data-cancel-registration type="button" autofocus class="btn btn-secondary"
                                :disabled="sending"
                                onclick="this.closest('dialog').close()">Periksa kembali
                        </button>
                        <button data-confirm-registration type="submit"
                                class="btn btn-primary disabled:cursor-not-allowed disabled:opacity-50 disabled:shadow-none"
                                aria-disabled="{{ $sendBlocked ? 'true' : 'false' }}"
                                :aria-disabled="sending || {{ $sendBlocked ? 'true' : 'false' }} ? 'true' : 'false'"
                                @if($sendBlocked) aria-describedby="registration-send-readiness" @endif
                                @disabled($sendBlocked) :disabled="sending || {{ $sendBlocked ? 'true' : 'false' }}"
                                x-text="sending ? 'Sedang mengirim…' : '{{ $confirmLabel }}'">{{ $confirmLabel }}</button>
                    </form>
                </dialog>
            @endcan
        </section>
    </div>
    @include('sidang_ta_registrations.partials.document-preview-modal')
</x-app-layout>
```

Tombol Kirim tetap disabled bila salah satu dari lima gate atau kondisi EPRT belum memenuhi. Kondisi `can_submit` pada controller sudah benar berdasarkan rescan; jangan menggantinya menjadi selalu true. Persyaratan file/isian sudah diperiksa oleh jalur Tinjau dan dibaca ulang oleh finalizer.

## 3. Koreksi satu test finalisasi

Pada `tests/Feature/SidangTaMeetFinalizationTest.php`, ganti keseluruhan test `meet_final_required_files_and_snapshot_use_the_same_final_facts_result` dengan berikut. Test lain tetap dipertahankan.

```php
test('meet_final_required_files_and_snapshot_use_the_same_final_facts_result', function () {
    $draft = ($this->markReviewed)(($this->meetDraft)('waiver'));
    $finalFacts = app(SidangTaAdmissionFacts::class)->forStudent($this->student);
    $this->mock(SidangTaAdmissionFacts::class)->shouldReceive('forStudent')->once()->andReturn($finalFacts);
    $id = ($this->submitMeet)($draft)->assertOk()->json('data.id');
    $registration = SidangTaRegistration::findOrFail($id);
    expect($registration->documents()->count())->toBe(8);
    $stored = $registration->academic_snapshot['admission']['eprt'];
    $expected = $finalFacts['eprt'];
    ksort($stored);
    ksort($expected);
    expect($stored)->toBe($expected);
});
```

Test ini tidak diturunkan menjadi perbandingan longgar. Misalnya boolean `true` tetap berbeda dengan string `'1'`; hanya urutan key yang dinormalisasi.

## 4. Kembalikan Status BPP di panel kanan

BPP kembali muncul pada seluruh tahap. Nilai `paid/lunas` ditampilkan Lunas, `unpaid/belum_lunas/belum lunas` menjadi Belum lunas; data tidak dikenal/kosong menjadi Belum tersedia. Karena sumber BPP belum mempunyai bukti periode pembayaran, informasi periode belum terkonfirmasi tetap ditampilkan.

BPP adalah informasi tambahan sesuai permintaan terbaru, bukan gate akademik keenam. Lima gate dan kondisi EPRT tetap mengikuti rancangan meet. Tampilan snapshot detail historis tidak diubah oleh permintaan panel kanan ini.

### `resources/views/sidang_ta_registrations/partials/wizard-academic.blade.php`

Ganti seluruh isi file berikut.

```php
@php
    $display = static fn ($value) => is_scalar($value) && (string) $value !== ''
        ? (string) $value : 'Belum tersedia';
@endphp
<aside data-academic-panel class="min-w-0 space-y-4 xl:col-span-4" aria-label="Informasi akademik mahasiswa">
    <section class="campus-card space-y-4 p-5">
        <h2 class="border-b border-slate-100 pb-3 text-base font-bold text-slate-800">
            Data mahasiswa
        </h2>
        <p class="break-words text-base font-semibold text-slate-900">
            {{ $display($academic['student_name'] ?? null) }}
        </p>
        <dl class="grid grid-cols-2 gap-4 text-sm">
            @foreach(['NIM' => 'student_number', 'Program studi' => 'study_program', 'Semester aktif' => 'semester', 'Email kampus' => 'student_email'] as $label => $key)
                <div class="min-w-0">
                    <dt class="text-xs text-slate-500">
                        {{ $label }}
                    </dt>
                    <dd class="mt-1 break-words font-medium text-slate-800">
                        {{ $display($academic[$key] ?? null) }}
                    </dd>
                </div>
            @endforeach
        </dl>
        <p class="text-xs leading-relaxed text-slate-500">
            Sumber data diperbarui: {{ $display($academic['academic_source_updated_at'] ?? null) }}
        </p>
    </section>
    <section class="campus-card space-y-4 p-5">
        <h2 class="border-b border-slate-100 pb-3 text-base font-bold text-slate-800">
            Judul Tugas Akhir
        </h2>
        @foreach(['Bahasa Indonesia' => 'ta_title', 'Bahasa Inggris' => 'ta_title_en'] as $label => $key)
            <div>
                <h3 class="text-xs font-semibold text-slate-500">
                    {{ $label }}
                </h3>
                <p class="mt-1.5 break-words rounded-lg bg-slate-50 p-3 text-sm leading-relaxed text-slate-800">
                    {{ $display($academic[$key] ?? null) }}
                </p>
            </div>
        @endforeach
    </section>
    <section class="campus-card space-y-4 p-5">
        <h2 class="border-b border-slate-100 pb-3 text-base font-bold text-slate-800">
            Ringkasan akademik
        </h2>
        <dl class="grid grid-cols-2 gap-3">
            @foreach(['IPK' => 'gpa', 'SKS lulus' => 'passed_credits', 'TAK' => 'tak_score', 'EPrT' => 'eprt_score'] as $label => $key)
                @php($value = $academic[$key] ?? null)
                <div class="rounded-xl bg-slate-50 p-3">
                    <dt class="text-xs text-slate-500">
                        {{ $label }}
                    </dt>
                    <dd class="mt-1 text-lg font-bold text-slate-800">
                        {{ is_numeric($value) ? number_format((float) $value, $key === 'gpa' ? 2 : 0, ',', '.') : $display($value) }}
                    </dd>
                </div>
            @endforeach
        </dl>
        <dl class="divide-y divide-slate-100 text-sm">
            @foreach(['Sidang tingkat 2' => 'sidang_tingkat_2', 'Status SKPI' => 'skpi_status', 'KRS Sidang TA 2' => 'ta2_krs_label', 'Status BPP' => 'bpp_status'] as $label => $key)
                <div class="grid grid-cols-2 gap-3 py-2">
                    <dt class="text-slate-500">
                        {{ $label }}
                    </dt>
                    <dd data-academic-field="{{ $key }}" class="break-words font-medium text-slate-800">
                        @if($key === 'bpp_status')
                            {{ match (strtolower(trim((string) ($academic[$key] ?? '')))) {
                                'paid', 'lunas' => 'Lunas',
                                'unpaid', 'belum lunas', 'belum_lunas' => 'Belum lunas',
                                default => 'Belum tersedia',
                            } }}
                        @else
                            {{ $display($academic[$key] ?? null) }}
                        @endif
                    </dd>
                    @if($key === 'bpp_status')
                        <dd class="col-span-2 text-xs text-slate-400">Periode pembayaran belum terkonfirmasi.</dd>
                    @endif
                </div>
            @endforeach
        </dl>
    </section>
</aside>
```

### Sesuaikan test lama yang sebelumnya melarang Status BPP

Pada `tests/Feature/SidangTaWizardUiTest.php`, ganti test berikut secara keseluruhan. Assertion lainnya tetap memeriksa bahwa panel lama yang diminta dihapus tidak kembali.

```php
test('g1_academic_panel_is_present_on_all_four_steps_and_keeps_zero_gpa', function () {
    $this->student->finalProject()->create([
        'thesis_title_id' => 'Judul akademik untuk G1',
        'thesis_title_en' => 'Academic title for G1', 'advisor_1' => 'Dosen Pembimbing G1',
    ]);
    $draft = ($this->completeDraft)();
    foreach ([1, 2, 3, 4] as $step) {
        $response = $this->get(route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => $step]))
            ->assertOk()->assertSee('Judul akademik untuk G1')->assertSee('Academic title for G1')
            ->assertDontSee('Dosen Pembimbing G1')->assertSee('0,00')
            ->assertSee('KRS Sidang TA 2')->assertDontSee('Pembimbing &amp; Penguji', false)
            ->assertDontSee('Jadwal sidang TA')->assertDontSee('Sidang tingkat 3')
            ->assertSee('Status BPP')->assertDontSee('Status i-Basila')->assertDontSee('Ketersediaan TAK');
        $xpath = sidangTaUiXpath($response->getContent());
        expect($xpath->query('//aside[@data-academic-panel]')->length)->toBe(1);
        expect($xpath->query('//aside[@data-academic-panel]//*[@data-save-status]')->length)->toBe(0);
    }
    expect(SidangTaRegistration::count())->toBe(0);
});
```

### `tests/Feature/SidangTaMeetUiTest.php` — dua test UI BPP

Kedua test berikut ditempatkan **satu kali pada `tests/Feature/SidangTaMeetUiTest.php`**, yang mempunyai helper `meetDraft` dan `reviewPage`. Jika sudah berada pada file lain, pindahkan menurut [56b](56b-koreksi-letak-test-disabled-dan-krs-dummy.md). `beforeEach` dan import existing tetap dipakai. Total 14 berlaku sebelum tambahan satu test tombol/KRS pada 56b.

```php
test('meet_ui_bpp_is_visible_on_every_step_without_adding_an_academic_gate', function () {
    $draft = ($this->meetDraft)();
    $this->student->bppPayment()->firstOrFail()->update(['status' => 'paid']);
    foreach ([1, 2, 3, 4] as $step) {
        $crawler = new Crawler($this->get(route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => $step]))
            ->assertOk()->getContent());
        $panel = $crawler->filter('[data-academic-panel]');
        expect($panel->text(null, true))->toContain('Status BPP')->toContain('Periode pembayaran belum terkonfirmasi');
        expect($panel->filter('[data-academic-field="bpp_status"]')->text(null, true))->toBe('Lunas');
    }
    $this->student->bppPayment()->firstOrFail()->update(['status' => 'unpaid']);
    $crawler = new Crawler(($this->reviewPage)($draft)->getContent());
    expect($crawler->filter('[data-admission-gate]')->count())->toBe(5);
    expect($crawler->filter('[data-open-registration]')->getNode(0)->hasAttribute('disabled'))->toBeFalse();
    expect($crawler->filter('[data-confirm-registration]')->getNode(0)->hasAttribute('disabled'))->toBeFalse();
});
```

```php
test('meet_ui_missing_bpp_is_displayed_as_unavailable', function () {
    $draft = ($this->meetDraft)();
    $this->student->bppPayment()->delete();
    $crawler = new Crawler($this->get(route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 2]))
        ->assertOk()->getContent());
    expect($crawler->filter('[data-academic-panel] [data-academic-field="bpp_status"]')->text(null, true))->toBe('Belum tersedia');
});
```

## 5. Card beranda Sidang TA mengikuti Yudisium

Tampilan mengikuti struktur card Yudisium yang sudah ada: header merah, ikon, judul, deskripsi, kotak nama/tanggal periode, dan tombol. Data periode TA memakai tabel TA sendiri.

- Semua periode TA yang aktif dan sudah terbuka ditampilkan, bukan hanya `first()`.
- Jika tidak ada yang terbuka, periode mendatang terdekat ditampilkan bila ada.
- Jika tidak ada keduanya, tampilkan pesan belum ada periode terbuka.
- Link Buka Pendaftaran tetap aktif untuk pemilik permission read pada semua kondisi, supaya draft dan riwayat tetap dapat dibuka ketika periode tutup.
- Pemilihan periode tetap dilakukan pada form pendaftaran. Card beranda tidak memilihkan satu periode secara otomatis.

### `app/Http/Controllers/DashboardController.php`

Ganti seluruh isi file berikut.

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $permissions = [
            'student_read' => $user->can('student_read'),
            'lecturer_read' => $user->can('lecturer_read'),
            'course_read' => $user->can('course_read'),
            'student_read_krs' => $user->can('student_read'),
            'jadwal_read' => $user->can('jadwal_read'),
            'room_read' => $user->can('room_read'),
            'document_category_read' => $user->can('document_category_read'),
            'document_read' => $user->can('document_read'),
            'public_document_read' => $user->can('public_document_read'),
            'graduation_registration_create' => $user->can('graduation_registration_create'),
            'graduation_registration_review' => $user->can('graduation_registration_review'),
            'grade_appeal_create' => $user->can('grade_appeal_create'),
            'grade_appeal_review' => $user->can('grade_appeal_review'),
            'queue_ticket_read' => $user->can('queue_ticket_read'),
        ];

        $roles = [
            'mahasiswa'   => $user->hasRole('mahasiswa'),
            'Akademik'    => $user->hasRole('Akademik'),
            'admin'       => $user->hasRole('admin'),
            'Petugas'     => $user->hasRole('Petugas'),
            'Pelaksana'   => $user->hasRole('Pelaksana'),
            'Pemimpin'    => $user->hasRole('Pemimpin'),
            'Pengamat'    => $user->hasRole('Pengamat'),
            'admin_or_staf_ssc' => $user->hasAnyRole('Akademik', 'admin', 'Petugas', 'Pelaksana'),
        ];
        $roles['admin_or_pegawai'] = $roles['admin_or_staf_ssc'];

        $activePeriod = \App\Models\GraduationPeriod::where('is_active', true)->first();

        $taOpenPeriods = collect();
        $taUpcomingPeriod = null;
        if ($roles['mahasiswa'] && $user->can('sidang_ta_registration_read')) {
            $taOpenPeriods = \App\Models\SidangTaPeriod::query()->openForRegistration()->get();
            if ($taOpenPeriods->isEmpty()) {
                $taUpcomingPeriod = \App\Models\SidangTaPeriod::query()->where('is_active', true)
                    ->where('open_at', '>', now())->orderBy('open_at')->orderBy('id')->first();
            }
        }


        $studentNumber = $user->student_number ?? $user->username ?? '';
        $myGraduationRegistration = null;
        if (($roles['mahasiswa'] ?? false) && $studentNumber) {
            $studentRegistrations = \App\Models\GraduationRegistration::where('student_number', $studentNumber);
            $myGraduationRegistration = (clone $studentRegistrations)
                ->where('status', \App\Models\GraduationRegistration::STATUS_APPROVED)
                ->latest()
                ->first()
                ?? (clone $studentRegistrations)
                    ->whereIn('status', [
                        \App\Models\GraduationRegistration::STATUS_SUBMITTED,
                        \App\Models\GraduationRegistration::STATUS_NEEDS_REVIEW,
                    ])
                    ->latest()
                    ->first();
        }

        $graduationStats = null;
        if ($roles['admin_or_pegawai'] ?? false) {
            $graduationStats = [
                'total'        => \App\Models\GraduationRegistration::count(),
                'needs_review' => \App\Models\GraduationRegistration::where('status', \App\Models\GraduationRegistration::STATUS_NEEDS_REVIEW)->count(),
                'submitted'    => \App\Models\GraduationRegistration::where('status', \App\Models\GraduationRegistration::STATUS_SUBMITTED)->count(),
                'approved'     => \App\Models\GraduationRegistration::where('status', \App\Models\GraduationRegistration::STATUS_APPROVED)->count(),
                'rejected'     => \App\Models\GraduationRegistration::where('status', \App\Models\GraduationRegistration::STATUS_REJECTED)->count(),
            ];
        }

        return view('dashboard', compact(
            'permissions', 'roles', 'activePeriod', 'myGraduationRegistration', 'graduationStats',
            'taOpenPeriods', 'taUpcomingPeriod'
        ));
    }
}
```

### `resources/views/dashboard.blade.php`

Cari blok mulai komentar `{{-- Card: Pendaftaran Sidang TA --}}` sampai `@endcan` milik card tersebut. Ganti seluruh blok itu dengan berikut. Bagian card Yudisium tetap dipertahankan.

```blade
                    {{-- Card: Pendaftaran Sidang TA --}}
                    @can('sidang_ta_registration_read')
                        <section data-ta-dashboard-card
                                 class="flex flex-col overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm transition-shadow duration-300 hover:shadow-md">
                            <div class="relative flex h-24 items-center justify-center overflow-hidden bg-gradient-to-br from-[#DA2D3B] to-[#b91c2c]">
                                <div class="absolute -left-4 -top-4 h-20 w-20 rounded-full bg-white/10"></div>
                                <div class="absolute -bottom-6 -right-3 h-24 w-24 rounded-full bg-white/10"></div>
                                <svg aria-hidden="true" class="relative z-10 h-10 w-10 text-white drop-shadow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4h6a3 3 0 0 1 3 3v14a4 4 0 0 0-4-2H4V4Zm16 0h-4a3 3 0 0 0-3 3v14a4 4 0 0 1 4-2h3V4Z"/>
                                </svg>
                                <span class="absolute right-2.5 top-2.5 rounded-full bg-white/20 px-2.5 py-0.5 text-[9px] font-bold tracking-wide text-white">
                                    {{ $taOpenPeriods->isNotEmpty() ? 'TERBUKA' : ($taUpcomingPeriod ? 'AKAN DATANG' : 'BELUM DIBUKA') }}
                                </span>
                            </div>
                            <div class="flex flex-col p-4">
                                <h3 class="text-sm font-bold leading-snug text-slate-800">Pendaftaran Sidang TA</h3>
                                <p class="mt-1 text-xs leading-relaxed text-slate-500">
                                    Pengajuan sidang tugas akhir dan pemeriksaan kelengkapan berkas.
                                </p>
                                <div class="mt-3 space-y-2">
                                    @foreach($taOpenPeriods as $period)
                                        <div data-ta-dashboard-period="{{ $period->id }}" class="space-y-1 rounded-xl border border-slate-100 bg-slate-50 p-2.5">
                                            <p class="flex items-start gap-1.5 text-xs font-medium text-slate-600">
                                                <span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-[#DA2D3B]"></span>
                                                <span>Periode terbuka: <strong class="font-semibold text-[#DA2D3B]">{{ $period->name }}</strong></span>
                                            </p>
                                            <p class="pl-3 text-[11px] text-slate-500">
                                                <time data-ta-open-at datetime="{{ $period->open_at->toIso8601String() }}">{{ $period->open_at->translatedFormat('d M Y H:i') }}</time>
                                                s.d. <time data-ta-close-at datetime="{{ $period->close_at->toIso8601String() }}">{{ $period->close_at->translatedFormat('d M Y H:i') }}</time>
                                            </p>
                                        </div>
                                    @endforeach
                                    @if($taOpenPeriods->isEmpty() && $taUpcomingPeriod)
                                        <div data-ta-dashboard-upcoming="{{ $taUpcomingPeriod->id }}" class="space-y-1 rounded-xl border border-slate-100 bg-slate-50 p-2.5">
                                            <p class="text-xs font-medium text-slate-600">Periode mendatang: <strong class="font-semibold text-[#DA2D3B]">{{ $taUpcomingPeriod->name }}</strong></p>
                                            <p class="text-[11px] text-slate-500">
                                                <time data-ta-open-at datetime="{{ $taUpcomingPeriod->open_at->toIso8601String() }}">{{ $taUpcomingPeriod->open_at->translatedFormat('d M Y H:i') }}</time>
                                                s.d. <time data-ta-close-at datetime="{{ $taUpcomingPeriod->close_at->toIso8601String() }}">{{ $taUpcomingPeriod->close_at->translatedFormat('d M Y H:i') }}</time>
                                            </p>
                                        </div>
                                    @elseif($taOpenPeriods->isEmpty())
                                        <p data-ta-dashboard-empty class="rounded-xl border border-slate-100 bg-slate-50 p-2.5 text-xs text-slate-500">Belum ada periode Sidang TA terbuka.</p>
                                    @endif
                                </div>
                                <div class="mt-auto pt-3">
                                    <a data-ta-dashboard-link href="{{ route('sidang-ta-registrations.index') }}"
                                       class="btn btn-primary w-full justify-center rounded-xl py-2 text-xs font-bold shadow-sm transition-all hover:shadow-md">
                                        Buka Pendaftaran Sidang TA
                                    </a>
                                </div>
                            </div>
                        </section>
                    @endcan
```
### Test baru card beranda — kode lengkap

Jalankan generator:

```bash
php artisan make:test SidangTaDashboardCardTest --pest
```

### `tests/Feature/SidangTaDashboardCardTest.php`

Ganti seluruh isi file berikut.

```php
<?php

use App\Models\GraduationPeriod;
use App\Models\SidangTaPeriod;
use App\Models\Student;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\DomCrawler\Crawler;

beforeEach(function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-11-04 12:00:00', config('app.timezone')));
    $role = Role::firstOrCreate(['name' => 'mahasiswa', 'guard_name' => 'web']);
    $permission = Permission::firstOrCreate(['name' => 'sidang_ta_registration_read', 'guard_name' => 'web']);
    $role->syncPermissions([]);
    $this->user = User::factory()->create(['username' => '1202299057', 'email' => 'ta-dashboard@test.local',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
    $this->user->assignRole($role);
    $this->user->givePermissionTo($permission);
    Student::create(['student_number' => $this->user->username, 'name' => 'TA Dashboard',
        'email' => $this->user->email, 'academic_year' => 2022, 'study_program' => 'Informatika', 'status' => 'Aktif']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($this->user);
    $this->taPeriod = function (string $name, array $overrides = []) {
        return SidangTaPeriod::create(array_replace(['name' => $name, 'is_active' => true,
            'open_at' => now()->subDay(), 'close_at' => now()->addDays(2)], $overrides));
    };
    $this->dashboardCard = function () {
        return (new Crawler($this->get(route('dashboard'))->assertOk()->getContent()))->filter('[data-ta-dashboard-card]');
    };
});

afterEach(function () { $this->travelBack(); });

test('meet_home_card_lists_both_open_periods_with_their_dates_and_index_link', function () {
    $a = ($this->taPeriod)('Sidang TA A');
    $b = ($this->taPeriod)('Sidang TA B', ['open_at' => now()->subHours(2), 'close_at' => now()->addDays(5)]);
    $card = ($this->dashboardCard)();
    expect($card->count())->toBe(1);
    expect($card->filter('[data-ta-dashboard-period]')->count())->toBe(2);
    foreach ([$a, $b] as $period) {
        $entry = $card->filter('[data-ta-dashboard-period="'.$period->id.'"]');
        expect($entry->text(null, true))->toContain($period->name)->toContain($period->open_at->translatedFormat('d M Y H:i'));
        expect($entry->filter('[data-ta-open-at]')->attr('datetime'))->toBe($period->open_at->toIso8601String());
        expect($entry->filter('[data-ta-close-at]')->attr('datetime'))->toBe($period->close_at->toIso8601String());
    }
    expect($card->filter('a[data-ta-dashboard-link]')->attr('href'))->toBe(route('sidang-ta-registrations.index'));
});

test('meet_home_card_excludes_inactive_future_and_closed_periods_from_open_list', function () {
    $open = ($this->taPeriod)('Terbuka');
    ($this->taPeriod)('Tidak aktif', ['is_active' => false]);
    ($this->taPeriod)('Mendatang', ['open_at' => now()->addDay(), 'close_at' => now()->addDays(3)]);
    ($this->taPeriod)('Ditutup', ['open_at' => now()->subDays(4), 'close_at' => now()->subDay()]);
    $card = ($this->dashboardCard)();
    expect($card->filter('[data-ta-dashboard-period]')->count())->toBe(1);
    expect($card->filter('[data-ta-dashboard-period]')->attr('data-ta-dashboard-period'))->toBe((string) $open->id);
    expect($card->filter('[data-ta-dashboard-upcoming]')->count())->toBe(0);
});

test('meet_home_card_displays_nearest_upcoming_period_when_none_is_open', function () {
    ($this->taPeriod)('Lebih jauh', ['open_at' => now()->addDays(4), 'close_at' => now()->addDays(5)]);
    $near = ($this->taPeriod)('Paling dekat', ['open_at' => now()->addDay(), 'close_at' => now()->addDays(2)]);
    ($this->taPeriod)('Nonaktif besok', ['is_active' => false, 'open_at' => now()->addHour(), 'close_at' => now()->addDay()]);
    $card = ($this->dashboardCard)();
    expect($card->filter('[data-ta-dashboard-period]')->count())->toBe(0);
    expect($card->filter('[data-ta-dashboard-upcoming]')->attr('data-ta-dashboard-upcoming'))->toBe((string) $near->id);
    expect($card->filter('[data-ta-dashboard-upcoming]')->text(null, true))->toContain($near->name);
    expect($card->filter('a[data-ta-dashboard-link]')->attr('href'))->toBe(route('sidang-ta-registrations.index'));
});

test('meet_home_card_keeps_draft_and_history_access_when_no_period_is_available', function () {
    ($this->taPeriod)('Sudah tutup', ['open_at' => now()->subDays(4), 'close_at' => now()->subDay()]);
    $card = ($this->dashboardCard)();
    expect($card->filter('[data-ta-dashboard-empty]')->count())->toBe(1);
    expect($card->text(null, true))->toContain('Belum ada periode Sidang TA terbuka.');
    $link = $card->filter('a[data-ta-dashboard-link]');
    expect($link->attr('href'))->toBe(route('sidang-ta-registrations.index'));
    expect($link->getNode(0)->hasAttribute('disabled'))->toBeFalse();
    expect($link->getNode(0)->hasAttribute('aria-disabled'))->toBeFalse();
});

test('meet_home_card_respects_student_role_and_read_permission', function () {
    $this->user->revokePermissionTo('sidang_ta_registration_read');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($this->user->fresh());
    expect(($this->dashboardCard)()->count())->toBe(0);
    $this->user->givePermissionTo('sidang_ta_registration_read');
    $this->user->removeRole('mahasiswa');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($this->user->fresh());
    expect(($this->dashboardCard)()->count())->toBe(0);
});

test('meet_home_card_uses_sidang_periods_instead_of_the_graduation_period', function () {
    ($this->taPeriod)('Nama Periode Sidang');
    GraduationPeriod::create(['name' => 'Nama Periode Yudisium', 'is_active' => true,
        'open_at' => now()->subDays(3), 'close_at' => now()->addDays(7)]);
    $card = ($this->dashboardCard)();
    expect($card->text(null, true))->toContain('Nama Periode Sidang')->not->toContain('Nama Periode Yudisium');
});
```

## 6. Verifikasi otomatis

### A. Ulangi fokus finalisasi/ekspor dan test baru card

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaMeetFinalizationTest.php tests/Feature/SidangTaMeetDraftTest.php tests/Feature/SidangTaMeetUiTest.php tests/Feature/SidangTaFinalizationTest.php tests/Feature/SidangTaReviewExportTest.php tests/Feature/SidangTaDashboardCardTest.php
```
Target **84 passed**: 27 finalisasi meet +14 draft meet +14 UI meet +13 finalisasi lama +10 ekspor +6 card beranda.

Command lima file yang Anda jalankan sebelumnya sekarang akan berisi 78 test, karena dua test BPP ditambahkan. Menambahkan file card beranda menjadikannya 84.

### B. Test panel lama dan navigasi dashboard

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaWizardUiTest.php tests/Feature/SidangTaStaffNavigationTest.php
```
### C. Seluruh Sidang TA dan JavaScript

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTa*Test.php tests/Unit/SidangTaAdmissionPolicyTest.php tests/Unit/SidangTaMeetRequirementsTest.php
```
```bash
node tests/Js/sidang-ta-entry.test.mjs
node tests/Js/sidang-ta-wizard.test.mjs
node tests/Js/sidang-ta-document-preview.test.mjs
```
Dibanding hasil Anda yang berjumlah 287 kasus (10 failed +277 passed), penambahan delapan kasus menjadikan target cakupan yang sama **295 passed**, jika tidak ada perubahan test lain. Bukan delapan puluh empat test baru: delapan kasus baru terdiri dari dua test BPP dan enam test dashboard.

Jika masih gagal dan output panjang, jalankan command fokus dengan tambahan `--stop-on-failure`. Cukup kirim blok FAILED pertama beserta pesannya; tidak perlu seluruh output.

Sesudah kode Blade diterapkan:

```bash
php artisan view:clear
```

Jalankan server/Vite seperti biasa. Bila aplikasi memakai build asset, jalankan `npm run build` agar class Tailwind baru pada card masuk hasil build. Tidak ada migrasi, seeder, perubahan `.env`, atau import JSON baru.

## 7. Jika tombol pembuka modal masih disabled

Perbaikan tombol konfirmasi menjelaskan mengapa tombol Ya tidak dapat ditekan. Tombol pembuka modal sendiri memang harus disabled bila `can_submit` false. Untuk membedakan gate yang belum memenuhi dari cache, lakukan:

1. Hard refresh halaman Tinjau sesudah `view:clear`.
2. Lihat lima pemeriksaan akademik dan informasi EPRT pada halaman itu. Salah satu unknown/gagal atau EPRT retest/unknown akan menahan tombol.
3. Pastikan yang dibuka masih draft dummy 002 versi 2, bukan revisi atau akun lain. Jika manual TAK59 dari 56 sudah dijalankan, pastikan nilai dummy sudah dipulihkan ke 60 sesuai prosedur 56.
4. Jangan menghapus atribut disabled untuk memaksa submit. Server akan memeriksa syarat yang sama.

Bila perlu, jalankan pemeriksaan **baca saja** berikut di Tinker aplikasi lokal. Query ditulis satu baris penuh agar tidak mengulang error Builder/leading `->`.

```bash
php artisan tinker
```

```php
$owner = App\Models\User::where('email', 'dummy-ta-2@mahasiswa.test')->firstOrFail();
$student = app(App\Services\SidangTaStudentContext::class)->studentFor($owner);
$draft = App\Models\SidangTaRegistrationDraft::where('owner_user_id', $owner->id)->where('student_number', $student->student_number)->where('status', 'active')->latest()->first();
$admission = app(App\Services\SidangTaAdmissionFacts::class)->forStudent($student);
dump(['database' => Illuminate\Support\Facades\DB::connection()->getDatabaseName(), 'student_number' => $student->student_number, 'draft' => $draft?->only(['id', 'requirements_version', 'source_registration_id', 'version', 'reviewed_version']), 'academic_blockers' => $admission['academic']['blockers'], 'eprt' => $admission['eprt'], 'eprt_facts' => $admission['eprt_facts']]);
```
Hasil siap kirim untuk dummy 002: NIM 1202299002, draft versi 2 dan source null, reviewed_version sama dengan version, academic_blockers kosong, EPRT waiver. Jika draft null, periksa apakah pengajuan ternyata sudah terkirim; jangan membuat pengajuan final buatan.

Gate yang belum memenuhi harus diperbaiki dari data sumber/sinkronisasi yang sesuai. JSON resmi yang masih menunggu tidak dibuat lulus secara otomatis. Dummy tetap mengikuti data 53, tetapi tidak perlu diimpor ulang jika sudah benar.

## 8. Manual setelah test lulus

1. Buka beranda sebagai mahasiswa: card TA mengikuti Yudisium, nama dan rentang tanggal periode TA tampil. Jika ada dua periode terbuka, keduanya terlihat. Data Yudisium tidak menggantikan data TA.
2. Lanjutkan draft dummy 002; Status BPP terlihat di panel kanan pada tahap 1–4. Status tidak dikenal tetap Belum tersedia.
3. Pada Tinjau, lima gate terpenuhi dan EPRT waiver dengan dokumen lengkap mengaktifkan tombol Kirim. Klik: modal terbuka; Periksa kembali menutupnya tanpa submit.
4. Buka lagi: tombol Ya juga aktif. Lanjutkan manual TAK59 dari 56 bila belum dilakukan: modal lama harus ditolak server, draft/file tetap tersimpan; setelah TAK dipulihkan ke60 dan Tinjau ulang, kirim berhasil.
5. Sesudah berhasil, dummy002 baru terlihat pada Review Sidang TA petugas. Preview dan ekspor tersedia; aksi verifikasi/keputusan versi2 masih ditahan sesuai batas checkpoint56.
6. Bila pengajuan dummy sudah berhasil dikirim, jangan mengulangi submit dengan membuat final record sendiri. Gunakan pengajuan itu untuk cek detail/preview/ekspor; skenario retry ada pada test.
7. Saat syarat akademik/EPRT memang belum memenuhi, tombol pembuka dan tombol konfirmasi sama-sama disabled. Alasan tetap terlihat pada ringkasan pemeriksaan.

## 9. File yang dipindahkan

Karena hasil 56 belum dipindahkan, setelah seluruh test/manual di atas lulus salin **20 file** (15 file 56 +5 tambahan). Tidak ada file yang perlu dihapus:

- `app/Services/SidangTaDraftFinalizer.php`
- `app/Support/SidangTaReviewEligibility.php`
- `app/Models/SidangTaRegistration.php`
- `app/Services/SidangTaReviewExport.php`
- `app/Services/SidangTaRegistrationReview.php`
- `app/Http/Controllers/SidangTaRegistrationController.php`
- `resources/views/sidang_ta_registrations/admin/show.blade.php`
- `resources/views/sidang_ta_registrations/admin/index.blade.php`
- `resources/views/sidang_ta_registrations/partials/detail-academic.blade.php`
- `app/Http/Controllers/SidangTaDraftController.php`
- `resources/views/sidang_ta_registrations/wizard-review.blade.php`
- `tests/Feature/SidangTaMeetDraftTest.php`
- `tests/Feature/SidangTaMeetUiTest.php`
- `tests/Feature/SidangTaReviewExportTest.php`
- `tests/Feature/SidangTaMeetFinalizationTest.php`
- `app/Http/Controllers/DashboardController.php`
- `resources/views/dashboard.blade.php`
- `resources/views/sidang_ta_registrations/partials/wizard-academic.blade.php`
- `tests/Feature/SidangTaWizardUiTest.php`
- `tests/Feature/SidangTaDashboardCardTest.php`

Pengaturan testing Podman tetap mengikuti keputusan Anda. Tidak perlu memindahkan `.env`, cache, atau build artifacts sebagai perubahan source.

## 10. Pemeriksaan yang dilakukan asisten

Source asli diperiksa dan dibandingkan usulan56. Pemeriksaan koreksi ini meliputi lint tujuh file PHP lengkap, kompilasi tiga Blade dalam memori, 64 assertion render wizard/Tinjau, 22 assertion render card beranda/panel BPP, dan probe strict JSON comparison yang mempertahankan perbedaan tipe. Semua pemeriksaan tersebut lulus tanpa bootstrap aplikasi atau DB.

Feature test MySQL, build, dan browser belum dijalankan oleh asisten; hasil 84/295 harus dibuktikan oleh pengujian Anda. Asisten hanya mengubah 56/56a/catatan07, bukan file aplikasi di kedua folder.
