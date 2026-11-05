{{--
    Partial: selection-toolbar.blade.php
    Dikontrol Alpine.js parent: selected[], selectionActive, isSingle, selectedDocs, selectedFolders
--}}
<div
    x-show="selectionActive"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0 max-h-0"
    x-transition:enter-end="opacity-100 max-h-20"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100 max-h-20"
    x-transition:leave-end="opacity-0 max-h-0"
    class="overflow-hidden"
    style="display:none;"
    role="toolbar"
    aria-label="Toolbar aksi item terpilih">
    <div class="flex items-center gap-2 flex-wrap bg-white border border-slate-200/60 rounded-xl px-3 py-2 shadow-sm">

        {{-- Counter + clear --}}
        <div class="flex items-center gap-2 mr-1">
            <span class="text-sm font-semibold text-slate-800" x-text="selected.length + ' dipilih'"></span>
            <button type="button"
                    @click="clearSelection()"
                    class="inline-flex items-center justify-center w-6 h-6 rounded-md text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-all"
                    title="Batalkan seleksi"
                    aria-label="Batalkan seleksi">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div class="h-4 w-px bg-slate-200 mx-1 flex-shrink-0"></div>

        {{-- Hapus --}}
        @can('document_delete')
        <button type="button"
                @click="openBulkDelete()"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium text-white bg-rose-500 hover:bg-rose-600 transition-all"
                title="Hapus item terpilih"
                aria-label="Hapus item terpilih">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
            </svg>
            Hapus
        </button>
        @endcan

        {{-- Unduh --}}
        <button type="button"
                @click="$refs.downloadForm.submit()"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium text-slate-700 border border-slate-300 bg-white hover:bg-slate-50 transition-all"
                title="Unduh item terpilih"
                aria-label="Unduh item terpilih">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            Unduh
        </button>

        {{-- Ganti Nama — hanya saat 1 item --}}
        <button type="button"
                x-show="isSingle"
                @click="openRename()"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium text-slate-700 border border-slate-300 bg-white hover:bg-slate-50 transition-all"
                title="Ganti nama item ini"
                aria-label="Ganti nama item ini"
                style="display:none;">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
            Ganti Nama
        </button>

        {{-- Detail — hanya saat 1 item --}}
        <button type="button"
                x-show="isSingle"
                @click="openDetail()"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium text-slate-700 border border-slate-300 bg-white hover:bg-slate-50 transition-all"
                title="Lihat & edit detail"
                aria-label="Lihat detail item ini"
                style="display:none;">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Detail
        </button>

        {{-- Form tersembunyi untuk bulk download --}}
        <form x-ref="downloadForm" action="{{ route('documents.bulk-download') }}" method="POST" class="hidden">
            @csrf
            <template x-for="item in selectedDocs" :key="'dl-doc-' + item.id">
                <input type="hidden" name="doc_ids[]" :value="item.id">
            </template>
            <template x-for="item in selectedFolders" :key="'dl-folder-' + item.id">
                <input type="hidden" name="folder_ids[]" :value="item.id">
            </template>
        </form>
    </div>
</div>
