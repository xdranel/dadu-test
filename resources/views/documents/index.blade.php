<x-app-layout>
    <x-slot name="header">Dokumen</x-slot>

    <div x-data="docSelection('{{ route('documents.bulk-delete') }}')">

        {{-- Header: tabs + actions --}}
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 mb-4">
            {{-- Tabs --}}
            <div class="inline-flex items-center gap-1 bg-slate-100 p-1 rounded-xl w-fit">
                <a href="{{ route('documents.index') }}"
                   class="px-4 py-1.5 text-sm font-semibold rounded-lg transition-all {{ request('view') !== 'all' ? 'bg-white text-[#DA2D3B] shadow-sm' : 'text-slate-500 hover:text-slate-700' }}">
                    Folder
                </a>
                <a href="{{ route('documents.index', ['view' => 'all']) }}"
                   class="px-4 py-1.5 text-sm font-semibold rounded-lg transition-all {{ request('view') === 'all' ? 'bg-white text-[#DA2D3B] shadow-sm' : 'text-slate-500 hover:text-slate-700' }}">
                    Semua Dokumen
                </a>
            </div>

            {{-- Actions area --}}
            <div class="flex items-center gap-2.5">
                @can('document_category_create')
                <button type="button" @click="openCreate()"
                        class="btn btn-secondary inline-flex items-center gap-1.5" title="Buat folder baru di level utama">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 11v4m-2-2h4"/></svg>
                    Buat Folder
                </button>
                @endcan
                @can('document_create')
                <a href="{{ route('documents.create') }}" class="btn btn-primary inline-flex items-center gap-1.5" title="Upload dokumen baru">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1M12 4v12m0-12l-4 4m4-4l4 4"/></svg>
                    Upload
                </a>
                @endcan
            </div>
        </div>

        {{-- Search folder --}}
        <form action="{{ route('documents.index') }}" method="GET" class="mb-4 max-w-md">
            <div class="relative flex items-center bg-white border border-slate-200 rounded-xl px-3.5 py-2 shadow-sm focus-within:border-[#DA2D3B]/40 focus-within:ring-2 focus-within:ring-[#DA2D3B]/5 transition-all">
                <span class="text-slate-400 mr-2.5 flex-shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </span>
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="Cari folder..."
                       class="w-full bg-transparent border-0 focus:ring-0 text-slate-800 placeholder-slate-400 p-0 text-sm">
                @if(request('search'))
                    <a href="{{ route('documents.index') }}" class="text-slate-400 hover:text-slate-600 ml-1.5" aria-label="Reset pencarian" title="Reset pencarian">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </a>
                @endif
            </div>
        </form>

        {{-- Toolbar Selection (muncul di bawah search bar) --}}
        <div class="mb-4">
            @include('documents.partials.selection-toolbar')
        </div>

        {{-- Folder grid --}}
        @if($categories->count() > 0)
            <div class="grid grid-cols-[repeat(auto-fill,minmax(130px,1fr))] gap-3">
                @foreach ($categories as $kat)
                    <x-folder-card
                        :category="$kat"
                        mode="admin"
                        :href="route('documents.folder', $kat)"
                        :editUrl="route('document_categories.update', $kat)" />
                @endforeach
            </div>
        @else
            <div class="text-center py-16 bg-white rounded-2xl border border-slate-200/60 shadow-sm flex flex-col items-center justify-center">
                <div class="p-4 bg-slate-50 text-slate-400 rounded-full mb-4">
                    <svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                </div>
                <p class="text-lg font-bold text-slate-700">
                    {{ request('search') ? 'Folder tidak ditemukan' : 'Belum ada folder' }}
                </p>
                <p class="text-sm mt-1 text-slate-400">
                    {{ request('search') ? 'Coba kata kunci lain.' : 'Buat folder pertama untuk mulai menata dokumen.' }}
                </p>
                @can('document_category_create')
                @unless(request('search'))
                    <button type="button" @click="openCreate()" class="btn btn-primary mt-5">Buat Folder</button>
                @endunless
                @endcan
            </div>
        @endif

        {{-- ===== Modal Buat/Edit Folder ===== --}}
        @php
            $pinnedLimitReached = \App\Models\DocumentCategory::where('is_pinned', true)->count() >= 5;
        @endphp
        <div x-show="show"
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm px-4"
             x-transition style="display:none;">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-md border border-slate-200" @click.away="show = false">
                <form :action="action" method="POST" class="p-6">
                    @csrf
                    <input type="hidden" name="redirect_to" value="{{ url()->full() }}">
                    <template x-if="isEdit"><input type="hidden" name="_method" value="PUT"></template>
                    <template x-if="parentId"><input type="hidden" name="parent_id" :value="parentId"></template>

                    <h3 class="text-lg font-bold text-slate-800 mb-1" x-text="modalTitle"></h3>
                    <p x-show="parentName" class="text-sm text-slate-500 mb-4">
                        Membuat subfolder di dalam: <span class="font-semibold text-[#DA2D3B]" x-text="parentName"></span>
                    </p>
                    <p x-show="!parentName && !isEdit" class="text-sm text-slate-400 mb-4">Folder akan dibuat di level utama.</p>
                    <p x-show="isEdit" class="text-sm text-slate-400 mb-4" style="display:none;"></p>

                    <div class="mb-4">
                        <label class="campus-label">Nama Folder <span class="text-red-500">*</span></label>
                        <input type="text" name="name" x-model="form.name" required
                               placeholder="Contoh: Panduan Tugas Akhir"
                               class="campus-input w-full @error('name') border-red-500 @enderror">
                        @error('name')
                            <p class="text-xs text-rose-600 mt-1 font-medium">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="mb-4">
                        <label class="campus-label">Deskripsi</label>
                        <textarea name="description" x-model="form.description" rows="3"
                                  placeholder="Deskripsi singkat folder (opsional)"
                                  class="campus-input w-full"></textarea>
                    </div>
                    <div class="mb-4">
                        <label class="campus-label">Visibilitas <span class="text-red-500">*</span></label>
                        <select name="visibility" x-model="form.visibility" class="campus-select w-full" required>
                            <option value="publik">Publik dan Mahasiswa</option>
                            <option value="internal">Pegawai dan Dosen</option>
                        </select>
                    </div>

                    {{-- Toggle: Sematkan di atas --}}
                    <label class="flex items-center justify-between gap-3 py-2.5 border-t border-slate-100"
                           :class="{{ $pinnedLimitReached ? 'true' : 'false' }} && !form.isPinned ? 'opacity-60 cursor-not-allowed' : 'cursor-pointer'"
                           :title="{{ $pinnedLimitReached ? 'true' : 'false' }} && !form.isPinned ? 'Maksimal 5 folder yang bisa disematkan. Lepas sematan folder lain terlebih dahulu.' : ''">
                        <span class="text-sm font-medium text-slate-700">
                            Sematkan di atas
                            <span x-show="{{ $pinnedLimitReached ? 'true' : 'false' }} && !form.isPinned" class="block text-xs text-amber-600 font-normal">
                                Maksimal 5 folder yang bisa disematkan.
                            </span>
                        </span>
                        <div class="relative">
                            <input type="hidden" name="is_pinned" value="0">
                            <input type="checkbox" name="is_pinned" value="1" x-model="form.isPinned"
                                   class="sr-only peer" id="toggle-pinned"
                                   :disabled="{{ $pinnedLimitReached ? 'true' : 'false' }} && !form.isPinned">
                            <div class="w-10 h-5 bg-slate-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-[#DA2D3B]/20 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-[#DA2D3B]"></div>
                        </div>
                    </label>

                    {{-- Toggle: Tampilkan di Portal meski kosong --}}
                    <label class="flex items-center justify-between gap-3 py-2.5 cursor-pointer border-t border-slate-100">
                        <span class="text-sm font-medium text-slate-700">
                            Tampilkan di Portal meski kosong
                            <span class="block text-xs text-slate-400 font-normal">Folder tanpa dokumen tetap muncul di Portal Dokumen.</span>
                        </span>
                        <div class="relative">
                            <input type="hidden" name="show_when_empty" value="0">
                            <input type="checkbox" name="show_when_empty" value="1" x-model="form.showWhenEmpty"
                                   class="sr-only peer" id="toggle-show-empty">
                            <div class="w-10 h-5 bg-slate-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-[#DA2D3B]/20 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-[#DA2D3B]"></div>
                        </div>
                    </label>

                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" @click="show = false" class="btn btn-secondary">Batal</button>
                        <button type="submit" class="btn btn-primary" x-text="isEdit ? 'Simpan' : 'Buat Folder'"></button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Partials baru --}}
        @include('documents.partials.bulk-delete-modal')
        @include('documents.partials.rename-modal')
        @include('documents.partials.detail-panel')

    </div>

    <script>
        function docSelection(bulkDeleteUrl) {
            return {
                // ============ Modal buat/edit folder (tetap dipertahankan) ============
                show: false,
                isEdit: false,
                action: '{{ route('document_categories.store') }}',
                form: { name: '', description: '', isPinned: false, showWhenEmpty: false },
                parentId: null,
                parentName: '',
                modalTitle: 'Buat Folder Baru',

                // ============ Selection state ============
                selected: [],
                showBulkDelete: false,
                showRename: false,
                showDetail: false,
                bulkDeleteUrl: bulkDeleteUrl,
                renameItem: { type: '', id: null, name: '', updateUrl: '' },
                detailItem: {},

                // ============ Computed ============
                get selectionActive() { return this.selected.length > 0; },
                get isSingle() { return this.selected.length === 1; },
                get selectedDocs() { return this.selected.filter(i => i.type === 'doc'); },
                get selectedFolders() { return this.selected.filter(i => i.type === 'folder'); },

                // ============ Methods ============
                isSelected(type, id) {
                    return this.selected.some(i => i.type === type && i.id === id);
                },
                toggleItem(item) {
                    const idx = this.selected.findIndex(i => i.type === item.type && i.id === item.id);
                    if (idx >= 0) {
                        this.selected.splice(idx, 1);
                    } else {
                        this.selected.push(item);
                    }
                },
                clearSelection() {
                    this.selected = [];
                },
                openBulkDelete() {
                    this.showBulkDelete = true;
                },
                openRename() {
                    if (!this.isSingle) return;
                    const item = this.selected[0];
                    this.renameItem = {
                        type: item.type,
                        id: item.id,
                        name: item.name,
                        updateUrl: item.updateUrl,
                    };
                    this.showRename = true;
                },
                openDetail() {
                    if (!this.isSingle) return;
                    this.detailItem = { ...this.selected[0] };
                    this.showDetail = true;
                },

                // ============ Folder create/edit modal (existing) ============
                openCreate(parentId = null, parentName = '') {
                    this.isEdit = false;
                    this.action = '{{ route('document_categories.store') }}';
                    this.form = { name: '', description: '', visibility: 'publik', isPinned: false, showWhenEmpty: false };
                    this.parentId = parentId;
                    this.parentName = parentName;
                    this.modalTitle = parentId ? 'Buat Subfolder' : 'Buat Folder Baru';
                    this.show = true;
                },
                init() {
                    window.addEventListener('open-folder-modal', (e) => {
                        this.isEdit = true;
                        this.action = e.detail.action;
                        this.parentId = null;
                        this.parentName = '';
                        this.modalTitle = 'Edit Folder';
                        this.form = {
                            name: e.detail.name || '',
                            description: e.detail.description || '',
                            visibility: e.detail.visibility || 'publik',
                            isPinned: !!e.detail.isPinned,
                            showWhenEmpty: !!e.detail.showWhenEmpty,
                        };
                        this.show = true;
                    });

                    @if($errors->has('name') || $errors->has('parent_id') || $errors->has('is_pinned') || $errors->has('visibility'))
                        this.openCreate();
                        this.form.name = @js(old('name', ''));
                        this.form.description = @js(old('description', ''));
                        this.form.visibility = @js(old('visibility', 'publik'));
                        this.form.isPinned = {{ old('is_pinned') ? 'true' : 'false' }};
                        this.form.showWhenEmpty = {{ old('show_when_empty') ? 'true' : 'false' }};
                    @endif
                },
            }
        }
    </script>

</x-app-layout>
