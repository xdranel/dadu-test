<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Detail Dokumen</h2>
    </x-slot>

    {{-- Breadcrumb interaktif --}}
    @php
        $ancestors = $document->category
            ? $document->category->ancestorsList()->push($document->category)
            : collect();
        $isPdf = strtolower($document->file_type) === 'pdf';
        $missing = !empty($fileMissing);
    @endphp
    @include('documents.partials.breadcrumb', ['ancestors' => $ancestors, 'current' => $document->title])

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
            <span class="px-2.5 py-1 text-xs font-semibold rounded-full {{ $document->visibility_badge }}">
                {{ $document->visibility_label }}
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

        @if($missing)
            {{-- Peringatan File Fisik Tidak Ditemukan --}}
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-center mb-6">
                <div class="w-12 h-12 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center mx-auto mb-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <h4 class="text-sm font-bold text-amber-800">File fisik tidak ditemukan di penyimpanan</h4>
                <p class="text-xs text-amber-700 mt-1">File fisik tidak ditemukan di penyimpanan. Hubungi admin sistem atau unggah ulang dokumen.</p>
                <button type="button" disabled class="btn btn-secondary mt-4 opacity-50 cursor-not-allowed">Unduh dinonaktifkan</button>
            </div>
        @else
            {{-- Area Preview Dokumen --}}
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-slate-700">Preview Dokumen</h3>
                <div class="flex items-center gap-2">
                    <a href="{{ route('documents.download', $document) }}" class="btn btn-secondary inline-flex items-center gap-1.5 !py-1.5 !text-xs" title="Unduh file dokumen">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1M12 4v12m0 0l-4-4m4 4l4-4"/></svg>
                        Unduh
                    </a>
                    @if($isPdf)
                    <button type="button" onclick="document.getElementById('pdfFrame').contentWindow.print()" class="btn btn-secondary inline-flex items-center gap-1.5 !py-1.5 !text-xs" title="Print file PDF">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4H7v4a2 2 0 002 2zM7 9V5a2 2 0 012-2h6a2 2 0 012 2v4"/></svg>
                        Print
                    </button>
                    @endif
                </div>
            </div>

            @if($isPdf)
                <div class="rounded-2xl border border-slate-200 overflow-hidden bg-slate-50 mb-6">
                    <iframe id="pdfFrame" src="{{ route('documents.preview', $document) }}#toolbar=1&navpanes=0"
                            class="w-full h-[75vh]" title="Pratinjau {{ $document->title }}" loading="lazy"></iframe>
                </div>
            @else
                <div class="rounded-2xl border border-slate-200 bg-slate-50 py-14 flex flex-col items-center text-center mb-6">
                    <x-file-icon :type="$document->file_type" size="lg" />
                    <p class="mt-4 text-sm font-semibold text-slate-700">Pratinjau tidak tersedia untuk tipe {{ strtoupper($document->file_type) }}</p>
                    <p class="text-xs text-slate-400 mt-1">Unduh file untuk membukanya.</p>
                    <a href="{{ route('documents.download', $document) }}" class="btn btn-primary mt-4">Unduh {{ $document->file_size_formatted }}</a>
                </div>
            @endif
        @endif

        <!-- Tombol Aksi -->
        <div class="mt-5 flex items-center justify-between border-t border-slate-100 pt-5">
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