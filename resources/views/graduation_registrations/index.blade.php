<x-app-layout>
    <x-slot name="header">Pendaftaran Yudisium</x-slot>

    @if($revisionNotifications->isNotEmpty())
        <div class="mb-5 rounded-xl border border-amber-300 bg-amber-50 p-4">
            <h2 class="text-sm font-bold text-amber-900">Berkas perlu diperbaiki</h2>
            <div class="mt-2 space-y-3">
                @foreach($revisionNotifications as $notification)
                    <div class="flex flex-col gap-2 border-t border-amber-200 pt-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="text-xs text-amber-900">
                            <p class="font-semibold">{{ $notification->data['document_type_label'] ?? 'Dokumen' }}</p>
                            @if(!empty($notification->data['review_notes']))
                                <p class="mt-0.5">{{ $notification->data['review_notes'] }}</p>
                            @endif
                        </div>
                        @if(!empty($notification->data['registration_id']))
                            <a href="{{ route('graduation-registrations.edit', $notification->data['registration_id']) }}"
                               class="btn btn-primary shrink-0 text-xs">
                                Perbaiki Pengajuan
                            </a>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

 
    <!-- Header Actions & Title -->
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div>
            <h2 class="text-xl font-bold text-slate-800">Riwayat Pendaftaran</h2>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">Daftar pengajuan pendaftaran yudisium Anda.</p>
        </div>

        @php
    $isInProgress = $hasActiveRegistration 
        && in_array($activeRegistration->status, ['submitted', 'needs_review']);
        @endphp

        @if($activePeriod && $activePeriod->isOpen() && !$isInProgress)
            <a href="{{ route('graduation-registrations.create') }}" class="btn btn-primary shadow-sm hover:shadow transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Buat Pengajuan
            </a>
        @elseif($isInProgress)
            <a href="{{ $activeRegistration->status === 'needs_review' 
            ? route('graduation-registrations.edit', $activeRegistration) 
            : route('graduation-registrations.show', $activeRegistration) }}" 
            class="btn btn-secondary" 
            title="Anda masih memiliki pengajuan aktif">
        Lanjutkan Pengajuan Aktif
            </a>
        @else
            <button type="button" disabled class="btn btn-secondary opacity-60 cursor-not-allowed" title="Pendaftaran Yudisium Ditutup">
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
                Pendaftaran Ditutup
            </button>
        @endif

    </div>

    <!-- Table Component -->
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden mb-6">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-body border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-semibold uppercase tracking-wider text-label">
                        <th scope="col" class="py-3.5 px-4">
                            Tanggal Pengajuan
                        </th>
                        <th scope="col" class="py-3.5 px-4">
                            Judul Tugas Akhir
                        </th>
                        <th scope="col" class="py-3.5 px-4">
                            Status
                        </th>
                        <th scope="col" class="py-3.5 px-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($registrations as $index => $reg)
                        <tr class="{{ $index % 2 === 0 ? 'bg-white' : 'bg-[#F8FAFC]' }} hover:bg-slate-50 transition-colors {{ $reg->status === 'needs_review' ? 'border-l-4 border-status-needsReview-accent' : '' }}">
                            <!-- Tanggal Pengajuan -->
                            <td class="py-3.5 px-4 whitespace-nowrap text-slate-600 font-medium">
                                {{ $reg->submitted_at?->translatedFormat('d M Y') ?? $reg->created_at->translatedFormat('d M Y') }}
                            </td>

                            <!-- Tujuan / Judul -->
                            <td class="py-3.5 px-4 font-semibold text-slate-800 max-w-xs md:max-w-md truncate">
                                {{ $reg->thesis_title_id ?: 'Pengajuan Pendaftaran Yudisium' }}
                            </td>

                            <!-- Status -->
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <x-status-badge :status="$reg->status" :label="$reg->status_label" />
                            </td>

                            <!-- Aksi -->
                            <td class="py-3.5 px-4 whitespace-nowrap text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    <!-- Detail Button -->
                                    <a href="{{ route('graduation-registrations.show', $reg) }}"
                                       class="text-sm px-3 py-1 border border-gray-300 rounded-md hover:bg-gray-50 transition-colors text-slate-700 font-medium"
                                       title="Lihat Detail" aria-label="Lihat Detail">
                                        Detail
                                    </a>

            
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-12 px-4 text-center">
                                <p class="text-body font-semibold text-slate-600 mb-1">Belum Ada Pengajuan</p>
                                <p class="text-caption text-slate-400">Anda belum memiliki pengajuan pendaftaran yudisium.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Footer Pagination & Entries Info -->
        <div class="px-4 py-3 bg-slate-50 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-3 text-caption text-slate-500">
            <div>
                Showing {{ $registrations->firstItem() ?? 0 }} to {{ $registrations->lastItem() ?? 0 }} of {{ $registrations->total() ?? 0 }} entries
            </div>
            <div>
                {{ $registrations->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
