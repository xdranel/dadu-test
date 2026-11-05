<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Detail Dokumen</h2>
    </x-slot>

    {{-- Breadcrumb: Dokumen > [Folder Induk] > [Subfolder] > Judul Dokumen --}}
    @php
        $ancestors = $document->category ? $document->category->ancestors : collect();
    @endphp
    <div x-data="{ expandedBreadcrumb: false }">
        <nav class="flex items-center gap-1.5 text-sm mb-5 flex-wrap" aria-label="Breadcrumb">
            <a href="{{ route('documents.index') }}" class="text-slate-500 hover:text-[#DA2D3B] font-medium" title="Kembali ke daftar folder">Dokumen</a>
            @if($document->category)
                @if($ancestors->count() <= 1)
                    @foreach($ancestors as $ancestor)
                        <svg class="w-4 h-4 text-slate-300 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        <a href="{{ route('documents.folder', $ancestor) }}" class="text-slate-500 hover:text-[#DA2D3B] font-medium truncate max-w-[140px] sm:max-w-xs">{{ $ancestor->name }}</a>
                    @endforeach
                @else
                    <span x-show="!expandedBreadcrumb" class="inline-flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-slate-300 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        <button type="button" @click="expandedBreadcrumb = true"
                                class="px-1.5 py-0.5 rounded bg-slate-100 hover:bg-slate-200 text-slate-500 text-xs font-bold tracking-wider transition-colors"
                                title="Klik untuk menampilkan seluruh jalur folder">
                            &hellip;
                        </button>
                    </span>

                    <span x-show="expandedBreadcrumb" class="inline-flex items-center gap-1.5 flex-wrap">
                        @foreach($ancestors->slice(0, -1) as $ancestor)
                            <svg class="w-4 h-4 text-slate-300 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                            <a href="{{ route('documents.folder', $ancestor) }}" class="text-slate-500 hover:text-[#DA2D3B] font-medium truncate max-w-[140px] sm:max-w-xs">{{ $ancestor->name }}</a>
                        @endforeach
                    </span>

                    @php $lastAncestor = $ancestors->last(); @endphp
                    <svg class="w-4 h-4 text-slate-300 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    <a href="{{ route('documents.folder', $lastAncestor) }}" class="text-slate-500 hover:text-[#DA2D3B] font-medium truncate max-w-[140px] sm:max-w-xs">{{ $lastAncestor->name }}</a>
                @endif
                <svg class="w-4 h-4 text-slate-300 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                <a href="{{ route('documents.folder', $document->category) }}" class="text-slate-500 hover:text-[#DA2D3B] font-medium truncate max-w-[140px] sm:max-w-xs">{{ $document->category->name }}</a>
            @endif
            <svg class="w-4 h-4 text-slate-300 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            <span class="text-slate-800 font-semibold truncate max-w-xs">{{ $document->title }}</span>
        </nav>
    </div>

    <div class="campus-card p-6">
        <!-- Header Dokumen -->
        <div class="flex items-start justify-between mb-6">
            <div>
                <h3 class="text-xl font-bold text-gray-900">{{ $document->title }}</h3>
                @if($document->abbreviation)
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-slate-100 text-slate-600 uppercase tracking-wide mt-1.5" title="Tipe / Singkatan dokumen">
                        {{ $document->abbreviation }}
                    </span>
                @endif
            </div>
            <span class="px-3 py-1 text-sm font-semibold rounded-full {{ $document->visibility_badge }}">
                {{ $document->visibility }}
            </span>
        </div>

        @if($document->description)
            <p class="text-sm text-gray-600 mb-6 leading-relaxed">{{ $document->description }}</p>
        @endif

        <!-- Info File -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
            <div class="bg-gray-50 rounded-lg p-3 text-center">
                <p class="text-xs text-gray-500">Tipe File</p>
                <p class="text-lg font-bold text-gray-700 uppercase">{{ $document->file_type }}</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-3 text-center">
                <p class="text-xs text-gray-500">Ukuran</p>
                <p class="text-sm font-bold text-gray-700">{{ $document->file_size_formatted }}</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-3 text-center">
                <p class="text-xs text-gray-500">Diunduh</p>
                <p class="text-lg font-bold text-gray-700">{{ $document->download_count }}x</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-3 text-center">
                <p class="text-xs text-gray-500">Diunggah</p>
                <p class="text-sm font-bold text-gray-700">{{ $document->created_at->format('d M Y') }}</p>
            </div>
        </div>

        <!-- Preview PDF — simplified toolbar -->
        @if($document->file_type === 'pdf')
            <div class="mb-6">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-sm font-medium text-gray-700">Preview Dokumen:</p>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('documents.download', $document) }}"
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors"
                           title="Unduh dokumen">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            Unduh
                        </a>
                        <a href="/storage/{{ $document->file_path }}" target="_blank"
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors"
                           title="Buka di tab baru untuk print">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                            Print
                        </a>
                    </div>
                </div>
                {{-- PDF embed with minimal toolbar: page nav + zoom only --}}
                <iframe src="/storage/{{ $document->file_path }}#toolbar=0&navpanes=0&scrollbar=1"
                        class="w-full border border-gray-200 rounded-lg"
                        style="height: 600px;"
                        title="Preview {{ $document->title }}"></iframe>
            </div>
        @endif

        <!-- Tombol Aksi -->
        <div class="mt-5 flex items-center justify-between border-t pt-5">
            @if($document->category)
                <a href="{{ route('documents.folder', $document->category) }}"
                   class="btn btn-secondary inline-flex items-center gap-1.5"
                   title="Kembali ke folder {{ $document->category->name }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Kembali ke Folder
                </a>
            @else
                <a href="{{ route('documents.index') }}"
                   class="btn btn-secondary inline-flex items-center gap-1.5"
                   title="Kembali ke daftar dokumen">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Kembali
                </a>
            @endif
            @can('document_update')
            <a href="{{ route('documents.edit', $document) }}" class="btn btn-primary" title="Edit data dokumen">
                Edit Data
            </a>
            @endcan
        </div>
    </div>
</x-app-layout>