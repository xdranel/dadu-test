{{--
    Partial: bulk-delete-modal.blade.php
    Variabel Alpine dari parent: showBulkDelete, selectedFolders[], selectedDocs[], foldersWithContent[]
--}}
<div x-show="showBulkDelete"
     class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm px-4"
     x-transition
     style="display:none;"
     role="dialog"
     aria-modal="true"
     aria-labelledby="bulk-delete-title">

    <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm border border-slate-200 p-6"
         @click.away="showBulkDelete = false">

        {{-- Icon --}}
        <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-rose-100 text-rose-600 mb-4">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
        </div>

        <h3 id="bulk-delete-title" class="text-lg font-bold text-slate-800 text-center mb-3">Konfirmasi Hapus</h3>

        {{-- Rincian item yang akan dihapus --}}
        <div class="bg-slate-50 rounded-xl p-3.5 mb-4 text-sm text-slate-700 space-y-1">
            <template x-if="selectedFolders.length > 0">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-[#DA2D3B] flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                    </svg>
                    <span x-text="selectedFolders.length + ' folder'"></span>
                </div>
            </template>
            <template x-if="selectedDocs.length > 0">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-slate-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <span x-text="selectedDocs.length + ' dokumen'"></span>
                </div>
            </template>
        </div>

        {{-- Peringatan cascade --}}
        <template x-if="selectedFolders.length > 0">
            <p class="text-xs text-rose-600 bg-rose-50 border border-rose-100 rounded-lg px-3 py-2 mb-4">
                <b>Perhatian:</b> Semua dokumen dan folder di dalamnya akan ikut terhapus.
            </p>
        </template>

        <div class="flex gap-3">
            <button type="button"
                    @click="showBulkDelete = false"
                    class="btn btn-secondary flex-1">
                Batal
            </button>
            <form :action="bulkDeleteUrl" method="POST" class="flex-1" x-ref="bulkDeleteForm">
                @csrf
                <input type="hidden" name="redirect_to" value="{{ url()->full() }}">
                <template x-for="item in selectedFolders" :key="'del-folder-' + item.id">
                    <input type="hidden" name="folder_ids[]" :value="item.id">
                </template>
                <template x-for="item in selectedDocs" :key="'del-doc-' + item.id">
                    <input type="hidden" name="doc_ids[]" :value="item.id">
                </template>
                <button type="submit"
                        class="btn btn-primary bg-rose-600 hover:bg-rose-700 w-full text-white">
                    Ya, Hapus
                </button>
            </form>
        </div>
    </div>
</div>
