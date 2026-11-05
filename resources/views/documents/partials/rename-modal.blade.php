{{--
    Partial: rename-modal.blade.php
    Alpine parent state: showRename, renameItem{ type, id, name, updateUrl }
--}}
<div x-show="showRename"
     class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm px-4"
     x-transition
     style="display:none;"
     role="dialog"
     aria-modal="true"
     aria-labelledby="rename-title">

    <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm border border-slate-200 p-6"
         @click.away="showRename = false"
         @keydown.escape.window="showRename = false">

        <h3 id="rename-title" class="text-lg font-bold text-slate-800 mb-1">Ganti Nama</h3>
        <p class="text-sm text-slate-500 mb-4"
           x-text="renameItem.type === 'folder' ? 'Ubah nama folder' : 'Ubah judul tampilan dokumen'"></p>

        <form :action="renameItem.updateUrl" method="POST" @submit="showRename = false">
            @csrf
            <input type="hidden" name="_method" value="PUT">
            <input type="hidden" name="redirect_to" value="{{ url()->full() }}">

            {{-- Untuk folder: field name --}}
            <template x-if="renameItem.type === 'folder'">
                <div class="mb-4">
                    <label class="campus-label">
                        Nama Folder <span class="text-red-500">*</span>
                    </label>
                    <input type="text"
                           name="name"
                           :value="renameItem.name"
                           required
                           autofocus
                           class="campus-input w-full"
                           :aria-label="'Ganti nama folder ' + renameItem.name">
                </div>
            </template>

            {{-- Untuk dokumen: field title --}}
            <template x-if="renameItem.type === 'doc'">
                <div class="mb-4">
                    <label class="campus-label">
                        Judul Dokumen <span class="text-red-500">*</span>
                    </label>
                    <input type="text"
                           name="title"
                           :value="renameItem.name"
                           required
                           autofocus
                           class="campus-input w-full"
                           :aria-label="'Ganti judul dokumen ' + renameItem.name">
                </div>
            </template>

            <div class="flex justify-end gap-3 mt-5">
                <button type="button"
                        @click="showRename = false"
                        class="btn btn-secondary">
                    Batal
                </button>
                <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>
