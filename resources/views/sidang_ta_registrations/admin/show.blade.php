<x-app-layout>
    <x-slot name="header">Peninjauan Pendaftaran Sidang TA</x-slot>
    @php
        $statusLabels = \App\Services\SidangTaReviewQuery::STATUSES;
        $documentLabels = [
            'pending' => 'Menunggu verifikasi',
            'verified' => 'Terverifikasi',
            'rejected' => 'Perlu perbaikan'
            ];
        $actionLabels = [
            'submitted' => 'Pendaftaran dikirim',
            'resubmitted' => 'Revisi dikirim',
            'needs_review' => 'Diminta revisi',
            'approved' => 'Pengajuan disetujui',
            'rejected' => 'Pengajuan ditolak',
            'document_verified' => 'Dokumen diverifikasi',
            'document_rejected' => 'Dokumen diminta perbaikan',
            ];
    @endphp
    <div class="mx-auto max-w-6xl space-y-5">
        <div class="campus-card p-4">@include('sidang_ta_registrations.partials.period-schedule', ['schedulePeriod' => $registration->period])</div>
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-semibold text-[#DA2D3B]">
                    {{ $registration->period?->name ?? 'Periode tidak tersedia' }}
                </p>
                <p data-ta-review-window class="mt-1 text-xs text-slate-500">
                    Dibuka {{ $registration->period?->open_at?->format('d/m/Y H:i') ?? '-' }} ·
                    Tenggat {{ $registration->period?->close_at?->format('d/m/Y H:i') ?? '-' }}
                </p>
                <h2 class="mt-1 break-words text-xl font-bold text-slate-900">
                    {{ $registration->student_name }}
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    {{ $registration->student_number }} · {{ $registration->study_program ?: 'Program studi belum tersedia' }}
                </p>
            </div>
            <a href="{{ route('admin.sidang-ta-registrations.index') }}" class="btn btn-secondary">
                Kembali ke Review Sidang TA
            </a>
        </header>
        @if($errors->any())
            <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
                {{ $errors->first() }}
            </div>
        @endif
        @if(session('success'))
            <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
                {{ session('success') }}
            </div>
        @endif
        <section class="campus-card space-y-4 p-5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="font-bold text-slate-800">
                    Data pendaftaran
                </h3>
                <x-status-badge :status="$registration->status"
                                :label="$statusLabels[$registration->status] ?? $registration->status"/>
            </div>
            <dl class="grid gap-4 text-sm sm:grid-cols-2">
                @foreach(['Nomor WA Aktif Mahasiswa' => $registration->student_phone, 'Nomor WA Aktif Orang Tua/Wali' => $registration->parent_phone, 'Pembayaran wisuda' => $registration->graduation_payment_date?->format('d/m/Y'), 'Biodata ijazah' => $registration->diploma_biodata_accurate ? 'Sudah sesuai' : 'Belum sesuai, bukti PDM dilampirkan'] as $label => $value)
                    <div>
                        <dt class="text-xs text-slate-500">{{ $label }}</dt>
                        <dd class="mt-1 break-words font-medium text-slate-800">{{ $value ?? 'Belum tersedia' }}</dd>
                    </div>
                @endforeach
                @unless($registration->diploma_biodata_accurate)
                    <div><dt class="text-xs text-slate-500">Tanggal pengajuan PDM</dt><dd data-pdm-date class="mt-1 font-medium text-slate-800">{{ $registration->pdm_submission_date?->format('d/m/Y') ?? 'Belum tersedia' }}</dd></div>
                @endunless
            </dl>
        </section>
        @if($readonlyNeedsReview)
            <p data-meet-review-locked role="status" class="rounded-xl bg-amber-50 p-4 text-sm text-amber-900">
                Pengajuan sedang menunggu revisi mahasiswa. Berkas dapat dilihat; perubahan peninjauan dikunci.
            </p>
        @endif
        @include('sidang_ta_registrations.partials.detail-academic')
        @include('sidang_ta_registrations.admin.partials.meet-document-groups')
        @if($reviewable)
            @include('sidang_ta_registrations.admin.partials.registration-decision')
        @endif
        @if($registration->review_notes)
            <section class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                <h3 class="text-sm font-bold text-amber-900">
                    Catatan petugas
                </h3>
                <p class="mt-1 whitespace-pre-line break-words text-sm text-amber-800">
                    {{ $registration->review_notes }}
                </p>
            </section>
        @endif
        <details data-staff-registration-history class="campus-card group p-5">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 font-bold text-slate-800 [&::-webkit-details-marker]:hidden">
                <span>Riwayat status</span>
                <svg aria-hidden="true" class="h-5 w-5 shrink-0 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                </svg>
            </summary>
            <ol class="mt-4 space-y-3">
                @forelse($registration->logs as $log)
                    <li class="border-l-2 border-rose-200 pl-3">
                        <p class="text-sm font-semibold text-slate-800">
                            {{ $actionLabels[$log->action] ?? ucfirst(str_replace('_', ' ', $log->action)) }}
                        </p>
                        <p class="mt-1 text-xs text-slate-500">
                            {{ $log->performed_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB · {{ $log->actor?->name ?? 'Sistem' }}
                        </p>
                        @if(filled($log->notes))
                            <p class="mt-1 whitespace-pre-line break-words text-sm text-slate-600">
                                {{ $log->notes }}
                            </p>
                        @endif
                    </li>
                @empty
                    <li class="text-sm text-slate-500">
                        Belum ada riwayat status.
                    </li>
                @endforelse
            </ol>
        </details>
    </div>
    @include('sidang_ta_registrations.partials.document-preview-modal')
</x-app-layout>
