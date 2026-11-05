{{-- Modal Konfirmasi Hapus Dokumen (dipicu event open-delete-modal) --}}
<div x-data="{ openDelete: false, deleteUrl: '', documentName: '' }"
     x-show="openDelete"
     @open-delete-modal.window="openDelete = true; deleteUrl = $event.detail.url; documentName = $event.detail.name;"
     class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm"
     style="display: none;"
     x-transition>
    <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-sm mx-4 border border-slate-200"
         @click.away="openDelete = false">
        <div class="text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-rose-100 text-rose-600 mb-4">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>
            <h3 class="text-lg font-bold text-slate-800 mb-2">Konfirmasi Hapus</h3>
            <p class="text-sm text-slate-500 mb-1 font-normal">Apakah Anda yakin ingin menghapus dokumen <span class="font-semibold text-slate-800" x-text="documentName"></span>?</p>
            <p class="text-xs text-slate-400 mb-6">Dokumen yang dihapus dapat dipulihkan oleh administrator.</p>
        </div>
        <div class="flex justify-end gap-3">
            <button type="button" @click="openDelete = false"
                    class="btn btn-secondary w-full">Batal</button>
            <form :action="deleteUrl" method="POST" class="w-full">
                @csrf
                @method('DELETE')
                <input type="hidden" name="redirect_to" value="{{ url()->full() }}">
                <button type="submit" class="btn btn-primary bg-rose-600 hover:bg-rose-700 w-full text-white">Ya, Hapus</button>
            </form>
        </div>
    </div>
</div>
