<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <div>
        <h2 class="text-xl font-bold text-slate-900 tracking-tight">Folder</h2>
    </div>

    {{-- Aksi --}}
    <div class="flex items-center gap-2">
        {{-- Toggle grid/list --}}
        <div class="flex items-center bg-slate-100 rounded-xl p-1 gap-0.5" role="group" aria-label="Mode tampilan">
            <button type="button" @click="setMode('grid')" :class="mode === 'grid' ? 'bg-white text-[#DA2D3B] shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                    class="inline-flex items-center justify-center w-8 h-8 rounded-lg transition-all" title="Tampilan Grid" aria-label="Tampilan Grid">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
            </button>
            <button type="button" @click="setMode('list')" :class="mode === 'list' ? 'bg-white text-[#DA2D3B] shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                    class="inline-flex items-center justify-center w-8 h-8 rounded-lg transition-all" title="Tampilan List" aria-label="Tampilan List">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
            </button>
        </div>

        @can('create', App\Models\DocumentCategory::class)
            <button type="button" @click="openCreate()" class="btn btn-secondary inline-flex items-center gap-1.5" title="Buat folder baru di direktori utama">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 11v4m-2-2h4"/></svg>
                Buat Folder
            </button>
        @endcan
        @can('create', App\Models\Document::class)
            <a href="{{ route('documents.import.create') }}" class="btn btn-secondary inline-flex items-center gap-1.5" title="Import Massal Dokumen (Excel / ZIP)">
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Import Massal
            </a>
            <a href="{{ route('documents.create') }}" class="btn btn-primary inline-flex items-center gap-1.5" title="Upload dokumen baru">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1M12 4v12m0-12l-4 4m4-4l4 4"/></svg>
                Upload Dokumen
            </a>
        @endcan
    </div>
</div>
