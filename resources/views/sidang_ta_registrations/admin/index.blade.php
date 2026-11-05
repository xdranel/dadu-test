<x-app-layout>
    <x-slot name="header">Review Pendaftaran Sidang TA</x-slot>
    @php
        $statusLabels = \App\Services\SidangTaReviewQuery::STATUSES;
        $groups = ['iGracias & Akademik' => \App\Support\SidangTaDocumentRequirements::CAPTURES,
            'Pembayaran & Dokumen' => \App\Support\SidangTaDocumentRequirements::PAYMENT,
            'Biodata' => array_merge(\App\Support\SidangTaDocumentRequirements::DIPLOMA, \App\Support\SidangTaDocumentRequirements::PDM)];
        $at = now();
    @endphp
    <div data-ta-review-hub class="space-y-5">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-bold text-slate-800">
                    Review Sidang TA
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    Tinjau pengajuan dan kelengkapan berkas mahasiswa.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if($canReview)
                    @if($counts['total'] > 0)
                        <a data-ta-export href="{{ route('admin.sidang-ta-registrations.export', $filters) }}" class="btn btn-success">
                            <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-width="1.7" stroke-linejoin="round" d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm0 0v6h6M8 12l5 6m0-6l-5 6"/></svg>
                            Ekspor Excel
                        </a>
                    @else
                        <button data-ta-export-empty type="button" disabled class="btn btn-success cursor-not-allowed opacity-50" title="Tidak ada pengajuan untuk diekspor">Ekspor Excel</button>
                    @endif
                @endif
                @can('sidang_ta_period_read')
                    <a data-manage-ta-periods href="{{ route('admin.sidang-ta-periods.index') }}" class="btn btn-secondary">Kelola Periode</a>
                @endcan
            </div>
        </header>
        @if(session('success'))
            <div role="status"
                 class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
                {{ session('success') }}
            </div>
        @endif
        @if($errors->any())
            <div role="alert"
                 class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
                {{ $errors->first() }}
            </div>
        @endif
        @if($canReview)
            <div data-ta-review-data class="space-y-5">
                <section class="space-y-3">
                    <h3 class="text-sm font-bold text-slate-800">{{ isset($filters['period']) ? 'Periode terpilih' : 'Periode aktif' }}</h3>
                    <div class="grid gap-3 lg:grid-cols-2">
                        @forelse($periodCards as $period)
                            @php
                                $state = match (true) { !$period->is_active => 'Tidak aktif', $at->lt($period->open_at) => 'Belum dibuka', $at->gt($period->close_at) => 'Ditutup', default => 'Sedang terbuka' };
                            @endphp
                            <article data-ta-period-banner="{{ $period->id }}"
                                     class="rounded-xl border border-slate-200 bg-white p-4">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <h4 class="break-words font-bold text-slate-800">
                                        {{ $period->name }}
                                    </h4>
                                    <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">
                                        {{ $state }}
                                    </span>
                                </div>
                                <p class="mt-2 text-xs leading-relaxed text-slate-500">
                                    Dibuka {{ $period->open_at->format('d/m/Y H:i') }} ·
                                    Tenggat {{ $period->close_at->format('d/m/Y H:i') }}
                                </p>
                            </article>
                        @empty
                            <p data-ta-no-period
                               class="rounded-xl border border-dashed border-slate-300 bg-white p-4 text-sm text-slate-500">
                                {{ isset($filters['period']) ? 'Periode terpilih tidak tersedia.' : 'Belum ada periode aktif. Riwayat pengajuan tetap dapat ditinjau.' }}
                            </p>
                        @endforelse
                    </div>
                </section>
                <section class="space-y-3">
                    <h3 class="text-sm font-bold text-slate-800">
                        Ringkasan hasil filter
                    </h3>
                    <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
                        @foreach(['total' => 'Total'] + $statusLabels as $key => $label)
                            <article class="rounded-xl border border-slate-200 bg-white p-4">
                                <p class="text-xs text-slate-500">
                                    {{ $label }}
                                </p>
                                <p data-ta-stat="{{ $key }}"
                                   class="mt-2 text-2xl font-bold {{ $key === 'total' ? 'text-[#DA2D3B]' : 'text-slate-800' }}">
                                    {{ $counts[$key] }}
                                </p>
                            </article>
                        @endforeach
                    </div>
                </section>
                <form data-ta-filters method="GET" action="{{ route('admin.sidang-ta-registrations.index') }}"
                      class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-2 xl:grid-cols-5">
                    <label class="text-xs font-semibold text-slate-600">Nama atau NIM
                        <input class="campus-input mt-1" name="search" value="{{ $filters['search'] ?? '' }}"
                               placeholder="Cari nama atau NIM">
                    </label>
                    <label class="text-xs font-semibold text-slate-600">Periode
                        <select class="campus-select mt-1" name="period">
                            <option value="">Semua periode</option>
                            @foreach($periods as $period)
                                <option value="{{ $period->id }}" @selected(($filters['period'] ?? null) == $period->id)>
                                    {{ $period->name }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <label class="text-xs font-semibold text-slate-600">Status
                        <select class="campus-select mt-1" name="status">
                            <option value="">Semua status</option>
                            @foreach($statusLabels as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <label class="text-xs font-semibold text-slate-600">Program studi
                        <select class="campus-select mt-1" name="study_program">
                            <option value="">Semua prodi</option>
                            @if(isset($filters['study_program']) && !$studyPrograms->contains($filters['study_program']))
                                <option value="{{ $filters['study_program'] }}" selected>{{ $filters['study_program'] }}
                                </option>
                            @endif
                            @foreach($studyPrograms as $program)
                                <option value="{{ $program }}" @selected(($filters['study_program'] ?? '') === $program)>
                                    {{ $program }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <div class="flex flex-wrap items-end gap-2">
                        <button type="submit" class="btn btn-primary">
                            Terapkan
                        </button>
                        <a data-ta-reset href="{{ route('admin.sidang-ta-registrations.index') }}" class="btn btn-secondary">
                            Reset
                        </a>
                    </div>
                </form>
                <p class="text-xs leading-relaxed text-slate-500">
                    Ikon berkas: kuning menunggu verifikasi, hijau terverifikasi, merah perlu perbaikan, tanda ! belum ada/tidak ditemukan, tanda – tidak berlaku.
                    Arahkan kursor atau fokuskan ikon untuk melihat nama dan status.
                </p>
                <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[1100px] text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Mahasiswa</th>
                                <th class="px-4 py-3">Prodi</th>
                                <th class="px-4 py-3">Periode</th>
                                <th class="px-4 py-3">Dikirim</th>
                                @foreach($groups as $title => $types)
                                    <th class="px-4 py-3">{{ $title }}</th>
                                @endforeach
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Aksi</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                            @forelse($registrations as $registration)
                                @php
                                    $requiredTypes = array_merge(\App\Support\SidangTaDocumentRequirements::CAPTURES, \App\Support\SidangTaDocumentRequirements::PAYMENT, \App\Support\SidangTaDocumentRequirements::typesForStep(4, $registration->diploma_biodata_accurate));
                                @endphp
                                <tr data-ta-row="{{ $registration->id }}" class="hover:bg-slate-50">
                                    <td class="px-4 py-4">
                                        <p class="font-semibold text-slate-800">{{ $registration->student_name }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ $registration->student_number }}</p>
                                    </td>
                                    <td class="px-4 py-4 text-xs text-slate-600">
                                        {{ $registration->study_program ?: 'Belum tersedia' }}
                                    </td>
                                    <td class="px-4 py-4 text-xs text-slate-600">
                                        {{ $registration->period?->name ?? 'Periode tidak tersedia' }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-4 text-xs text-slate-600">
                                        {{ $registration->submitted_at?->format('d/m/Y H:i') ?? '-' }}
                                    </td>
                                    @foreach($groups as $types)
                                        <td class="px-4 py-4">
                                            <div class="flex max-w-32 flex-wrap gap-1.5">@include('sidang_ta_registrations.admin.partials.document-icons')</div>
                                        </td>
                                    @endforeach
                                    <td class="px-4 py-4">
                                        <x-status-badge :status="$registration->status"
                                                        :label="$statusLabels[$registration->status] ?? $registration->status"/>
                                    </td>
                                    <td class="px-4 py-4">
                                        <a data-ta-detail href="{{ route('admin.sidang-ta-registrations.show', $registration) }}"
                                           class="btn btn-secondary" aria-label="Tinjau pengajuan {{ $registration->student_name }}"
                                           title="Tinjau pengajuan">
                                            <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none"
                                                 stroke="currentColor">
                                                <path stroke-width="1.7"
                                                      d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/>
                                                <circle cx="12" cy="12" r="3" stroke-width="1.7"/>
                                            </svg>
                                            <span>Tinjau</span>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" data-ta-empty class="px-4 py-12 text-center text-sm text-slate-500">
                                        @if($registrations->total() > 0)
                                            <p>Halaman ini tidak berisi pengajuan.</p>
                                            <a data-ta-first-page
                                               href="{{ route('admin.sidang-ta-registrations.index', $filters) }}"
                                               class="mt-3 inline-flex font-semibold text-[#DA2D3B] hover:underline">Kembali
                                                ke halaman pertama
                                            </a>
                                        @else
                                            {{ $filters !== [] ? 'Tidak ada pengajuan yang cocok dengan filter.' : 'Belum ada pengajuan Sidang TA.' }}
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="border-t border-slate-100 px-4 py-3">{{ $registrations->links() }}</div>
                </section>
            </div>
        @else
            <section data-ta-period-only class="campus-card space-y-2 p-5">
                <h3 class="font-bold text-slate-800">
                    Pengaturan periode pendaftaran
                </h3>
                <p class="text-sm text-slate-500">
                    Gunakan Kelola Periode untuk melihat jadwal pendaftaran sidang tugas akhir.
                </p>
            </section>
        @endif
    </div>
    @if($canReview)
        @include('sidang_ta_registrations.partials.document-preview-modal')
    @endif
</x-app-layout>
