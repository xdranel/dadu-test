<x-app-layout>
    <x-slot name="header">Detail Pendaftaran Sidang TA</x-slot>
    @php
        $statusLabel = static fn ($status) => match ($status) {
            'submitted' => 'Menunggu peninjauan',
            'needs_review' => 'Perlu revisi',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            default => ucfirst(str_replace('_', ' ', (string) $status)),
        };
    @endphp
    <div class="mx-auto max-w-6xl space-y-5">
        <section class="campus-card overflow-hidden">
            <div
                class="flex flex-wrap items-start justify-between gap-4 border-b border-rose-100 bg-rose-50/60 p-5 sm:p-6">
                <div class="min-w-0">
                    <p class="break-words text-xs font-semibold uppercase tracking-wide text-[#DA2D3B]">{{ $registration->period?->name ?? 'Periode tidak tersedia' }}</p>
                    <h2 class="mt-1 break-words text-xl font-bold text-slate-900">{{ $registration->student_name }}</h2>
                    <p class="mt-1 break-words text-sm text-slate-600">{{ $registration->student_number }}
                        · {{ $registration->study_program ?: 'Program studi belum tersedia' }}</p>
                </div>
                <x-status-badge :status="$registration->status" :label="$statusLabel($registration->status)"/>
            </div>
            <p class="px-5 py-3 text-xs text-slate-500 sm:px-6">
                Dikirim: {{ $registration->submitted_at?->translatedFormat('d M Y H:i') ?? 'Belum tersedia' }}</p>
        </section>
        @if(filled($registration->review_notes))
            <section class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                <h2 class="text-sm font-bold text-amber-900">Catatan petugas</h2>
                <p class="mt-1 whitespace-pre-line break-words text-sm leading-relaxed text-amber-800">{{ $registration->review_notes }}</p>
            </section>
        @endif
        <section class="campus-card p-5 sm:p-6">
            <h2 class="text-lg font-bold text-slate-900">Data pendaftaran</h2>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs text-slate-500">Nomor WA Aktif Mahasiswa</dt>
                    <dd class="mt-1 break-words text-sm font-semibold text-slate-800">{{ $registration->student_phone }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">Nomor WA Aktif Orang Tua/Wali</dt>
                    <dd class="mt-1 break-words text-sm font-semibold text-slate-800">{{ $registration->parent_phone }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">Tanggal pembayaran wisuda</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-800">{{ $registration->graduation_payment_date?->translatedFormat('d M Y') ?? 'Belum tersedia' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">Biodata ijazah</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-800">{{ $registration->diploma_biodata_accurate ? 'Sudah sesuai' : 'Belum sesuai, bukti pengajuan PDM dilampirkan' }}</dd>
                </div>
                @unless($registration->diploma_biodata_accurate)
                    <div><dt class="text-xs text-slate-500">Tanggal pengajuan PDM</dt><dd data-pdm-date class="mt-1 text-sm font-semibold">{{ $registration->pdm_submission_date?->format('d/m/Y') ?? 'Belum tersedia' }}</dd></div>
                @endunless
            </dl>
        </section>

        @include('sidang_ta_registrations.partials.detail-academic')

        <section class="campus-card overflow-hidden">
            <h2 class="border-b border-slate-100 px-5 py-4 text-lg font-bold text-slate-900 sm:px-6">
                Dokumen yang diunggah
            </h2>
            <div data-final-documents class="divide-y divide-slate-100 px-5 sm:px-6">
                @forelse($registration->documents as $document)
                    <div class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between sm:gap-5">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold leading-relaxed text-slate-800">
                                {{ \App\Models\SidangTaRegistration::DOCUMENT_TYPES[$document->document_type] ?? $document->document_type }}
                            </p>
                            <p class="mt-1 break-words text-xs text-slate-500">
                                {{ $document->file_name }} · {{ number_format(($document->file_size ?? 0) / 1024) }} KB
                            </p>
                            @include('sidang_ta_registrations.partials.document-review-status')
                        </div>
                        <a data-document-preview
                           class="btn btn-secondary shrink-0 self-start whitespace-nowrap !text-xs no-underline sm:self-auto"
                           href="{{ route('sidang-ta-registration-documents.preview', $document) }}"
                           data-preview-url="{{ route('sidang-ta-registration-documents.preview', $document) }}"
                           data-download-url="{{ route('sidang-ta-registration-documents.download', $document) }}"
                           data-file-name="{{ $document->file_name }}" data-file-type="{{ $document->file_type }}"
                           target="_blank" rel="noopener" aria-haspopup="dialog"
                           x-on:click.prevent="$dispatch('sidang-ta-preview', $el.dataset)">
                            Lihat berkas
                        </a>
                    </div>
                @empty
                    <p class="py-4 text-sm text-slate-500">Belum ada dokumen tersimpan.</p>
                @endforelse
            </div>
        </section>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('sidang-ta-registrations.index') }}" class="btn btn-secondary">Kembali</a>
            @if($canRevise)
                <a data-revise-registration href="{{ route('sidang-ta-registrations.edit', $registration) }}"
                   class="btn btn-primary">Perbaiki Pengajuan</a>
            @endif
        </div>
    </div>
    @include('sidang_ta_registrations.partials.document-preview-modal')
</x-app-layout>
