<x-app-layout>
    <x-slot name="header">Dokumen</x-slot>

    <div x-data="docSelection('{{ route('documents.bulk-delete') }}')" @keydown.window="handleKey($event)">

        {{-- Header Bersama: aksi & heading --}}
        @include('documents.partials.page-header')

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

        {{-- ===== MODE GRID ===== --}}
        <div x-show="mode === 'grid'" x-cloak>
            @if($categories->count() > 0)
                @php
                    $pinnedFolders = $categories->where('is_pinned', true);
                    $unpinnedFolders = $categories->where('is_pinned', false);
                @endphp

                @if($pinnedFolders->isNotEmpty())
                    {{-- Section Disematkan --}}
                    <div class="mb-8">
                        <div class="flex items-center gap-2 mb-3">
                            <h2 class="text-xs font-bold text-slate-500 uppercase tracking-wider">
                                Disematkan
                            </h2>
                        </div>
                        <div class="folder-grid grid grid-cols-[repeat(auto-fill,132px)] gap-x-3 gap-y-4 justify-start" style="grid-template-columns: repeat(auto-fill, 132px);">
                            @foreach ($pinnedFolders as $kat)
                                <x-folder-card
                                    :category="$kat"
                                    mode="admin"
                                    :href="route('documents.folder', $kat)"
                                    :editUrl="route('document_categories.update', $kat)" />
                            @endforeach
                        </div>
                    </div>

                    {{-- Section Semua Folder --}}
                    @if($unpinnedFolders->isNotEmpty())
                        <div class="mb-8">
                            <div class="flex items-center gap-2 mb-3">
                                <h2 class="text-xs font-bold text-slate-500 uppercase tracking-wider">
                                    Semua Folder
                                </h2>
                            </div>
                            <div class="folder-grid grid grid-cols-[repeat(auto-fill,132px)] gap-x-3 gap-y-4 justify-start" style="grid-template-columns: repeat(auto-fill, 132px);">
                                @foreach ($unpinnedFolders as $kat)
                                    <x-folder-card
                                        :category="$kat"
                                        mode="admin"
                                        :href="route('documents.folder', $kat)"
                                        :editUrl="route('document_categories.update', $kat)" />
                                @endforeach
                            </div>
                        </div>
                    @endif
                @else
                    <div class="folder-grid grid grid-cols-[repeat(auto-fill,132px)] gap-x-3 gap-y-4 justify-start" style="grid-template-columns: repeat(auto-fill, 132px);">
                        @foreach ($categories as $kat)
                            <x-folder-card
                                :category="$kat"
                                mode="admin"
                                :href="route('documents.folder', $kat)"
                                :editUrl="route('document_categories.update', $kat)" />
                        @endforeach
                    </div>
                @endif
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
                    @can('create', App\Models\DocumentCategory::class)
                    @unless(request('search'))
                        <button type="button" @click="openCreate()" class="btn btn-primary mt-5">Buat Folder</button>
                    @endunless
                    @endcan
                </div>
            @endif
        </div>

        {{-- ===== MODE LIST (TABEL FOLDER) ===== --}}
        <div x-show="mode === 'list'" x-cloak>
            <div class="campus-card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="campus-table">
                        <thead>
                            <tr>
                                <th class="w-10 !px-3 text-center">
                                    <span class="sr-only">Pilih</span>
                                </th>
                                <th>Nama</th>
                                <th class="!text-center">Isi</th>
                                <th class="!text-center">Visibilitas</th>
                                <th class="!text-center">Dibuat</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $pinnedFolders = $categories->where('is_pinned', true);
                                $unpinnedFolders = $categories->where('is_pinned', false);
                                $allFoldersList = $pinnedFolders->concat($unpinnedFolders);
                            @endphp
                            @forelse($allFoldersList as $f)
                                @php
                                    $fItem = [
                                        'type' => 'folder',
                                        'id' => $f->id,
                                        'name' => $f->name,
                                        'updateUrl' => route('document_categories.update', $f),
                                        'moveUrl' => route('document_categories.move', $f),
                                        'pinUrl' => route('document_categories.pin', $f),
                                        'visibilityUrl' => route('document_categories.visibility', $f),
                                        'description' => $f->description,
                                        'isPinned' => (bool)$f->is_pinned,
                                        'visibility' => $f->visibility ?? 'publik',
                                        'parentId' => $f->parent_id,
                                        'depth' => $f->depth ?? 0,
                                        'showWhenEmpty' => (bool)$f->show_when_empty,
                                        'docCount' => $f->documents_count ?? 0,
                                        'childCount' => $f->children_count ?? 0,
                                    ];
                                @endphp
                                <tr class="align-middle hover:bg-slate-50/60 transition-colors"
                                    :class="isSelected('folder', {{ $f->id }}) ? 'bg-[#DA2D3B]/5' : ''">
                                    <td class="text-center align-middle w-10" @click.stop>
                                        <label class="inline-flex items-center justify-center p-1.5 cursor-pointer rounded-lg">
                                            <input type="checkbox"
                                                   class="sr-only peer"
                                                   :checked="isSelected('folder', {{ $f->id }})"
                                                   @change="toggleItem(@js($fItem))"
                                                   aria-label="Pilih folder {{ $f->name }}">
                                            <span class="w-5 h-5 flex items-center justify-center rounded-md border-2 bg-white shadow-sm transition-all duration-150"
                                                  :class="isSelected('folder', {{ $f->id }}) ? 'bg-[#DA2D3B] border-[#DA2D3B] opacity-100' : 'border-slate-300 ' + (selectionActive ? 'opacity-100' : 'opacity-0 group-hover:opacity-100 [@media(hover:none)]:opacity-100')">
                                                <svg class="w-3.5 h-3.5 text-white transition-all duration-150"
                                                     :class="isSelected('folder', {{ $f->id }}) ? 'opacity-100 scale-100' : 'opacity-0 scale-75 hidden'"
                                                     viewBox="0 0 12 10" fill="none" aria-hidden="true">
                                                    <path d="M1.5 5.2l3 3 6-6.5" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                            </span>
                                        </label>
                                    </td>
                                    <td>
                                        <a href="{{ route('documents.folder', $f) }}"
                                           class="flex items-center gap-2.5 text-slate-800 hover:text-[#DA2D3B] font-medium transition-colors"
                                           title="Buka folder {{ $f->name }}"
                                           @click="if (selectionActive) { $event.preventDefault(); toggleItem(@js($fItem)); }">
                                            <img src="{{ asset(config('documents.asset_dir').'/'.config('documents.folder_image', 'foldercard.svg')) }}" alt="" class="w-6 h-auto flex-shrink-0">
                                            <span class="truncate max-w-xs">{{ $f->name }}</span>
                                            @if($f->is_pinned)
                                                <svg class="w-3.5 h-3.5 text-amber-500 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24" aria-label="Disematkan"><path d="M16 3l5 5-3 1-4 4v5l-2 2-3-3-4 4-1-1 4-4-3-3 2-2h5l4-4z"/></svg>
                                            @endif
                                        </a>
                                        @if($f->description)
                                            <p class="text-xs text-slate-400 mt-0.5 ml-8 truncate max-w-xs" title="{{ $f->description }}">{{ $f->description }}</p>
                                        @endif
                                    </td>
                                    <td class="text-center text-xs text-slate-500">{{ $f->children_count }} subfolder &middot; {{ $f->documents_count }} dokumen</td>
                                    <td class="text-center">
                                        <span class="inline-flex items-center gap-1.5 text-xs text-slate-600 whitespace-nowrap">
                                            <img src="{{ $f->visibility_icon_url }}" alt="" class="w-4 h-4 object-contain">{{ $f->visibility_label }}
                                        </span>
                                    </td>
                                    <td class="text-center text-sm text-slate-500">{{ $f->created_at->translatedFormat('d M Y') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="empty-state"><p class="empty-state-text">{{ request('search') ? 'Folder tidak ditemukan.' : 'Belum ada folder.' }}</p></td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @can('create', App\Models\DocumentCategory::class)
        {{-- ===== Modal Buat/Edit Folder ===== --}}
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
                    <p x-show="!parentName && !isEdit" class="text-sm text-slate-400 mb-4">Folder akan dibuat di direktori utama.</p>
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
                            @foreach(config('documents.visibility', []) as $vKey => $vMeta)
                                <option value="{{ $vKey }}">{{ $vMeta['label'] }}</option>
                            @endforeach
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
        @endcan

        {{-- Floating Toast untuk error AJAX/patch --}}
        <div x-show="toast" x-cloak x-transition
             class="fixed bottom-6 right-6 z-50 flex items-center gap-3 bg-rose-600 text-white text-sm rounded-xl shadow-xl px-4 py-3 max-w-md">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span x-text="toast?.message"></span>
            <button type="button" @click="toast = null" class="text-white/80 hover:text-white ml-auto" aria-label="Tutup">&times;</button>
        </div>

        {{-- Partials baru --}}
        @include('documents.partials.bulk-delete-modal')
        @include('documents.partials.rename-modal')
        @include('documents.partials.detail-panel')
        @include('documents.partials.move-modal')

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

                // ============ Toast state ============
                toast: null,

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
                    this.openRenameFor(item);
                },
                openDetail() {
                    if (!this.isSingle) return;
                    this.detailItem = { ...this.selected[0] };
                    this.showDetail = true;
                },

                // ============ State Move Modal ============
                showMove: false,
                moveItems: [],
                childrenOf: {},
                expanded: new Set(),
                rootDisabled: false,
                moveTarget: undefined,
                moveQuery: '',
                searchResults: [],
                moveError: '',
                moveLoading: false,

                get rows() {
                    const out = [];
                    const walk = (key, depth) => {
                        (this.childrenOf[key] || []).forEach(n => {
                            out.push({ ...n, depth });
                            if (this.expanded.has(n.id)) {
                                walk(n.id, depth + 1);
                            }
                        });
                    };
                    walk('root', 0);
                    return out;
                },
                targetsUrl(extra = '') {
                    const it = this.moveItems[0] || {};
                    return `{{ route('documents.move-targets') }}?type=${it.type || ''}&id=${it.id || ''}${extra}`;
                },
                async loadChildren(key) {
                    if (this.childrenOf[key]) return;
                    const r = await fetch(this.targetsUrl(`&parent_id=${key === 'root' ? '' : key}`), {
                        headers: { Accept: 'application/json' }
                    });
                    const j = await r.json().catch(() => ({ nodes: [] }));
                    this.childrenOf = { ...this.childrenOf, [key]: j.nodes || [] };
                    if (key === 'root') {
                        this.rootDisabled = (this.moveItems[0]?.type === 'doc') || Boolean(j.rootDisabled);
                    }
                },
                async toggleExpand(id) {
                    if (this.expanded.has(id)) {
                        this.expanded.delete(id);
                    } else {
                        await this.loadChildren(id);
                        this.expanded.add(id);
                    }
                    this.expanded = new Set(this.expanded);
                },
                async searchTargets() {
                    const q = this.moveQuery.trim();
                    if (!q) return;
                    const r = await fetch(this.targetsUrl(`&q=${encodeURIComponent(q)}`), {
                        headers: { Accept: 'application/json' }
                    });
                    const j = await r.json().catch(() => ({ nodes: [] }));
                    this.searchResults = j.nodes || [];
                },
                async openMove() {
                    if (!this.selectionActive) return;
                    const hasFolder = this.selectedFolders.length > 0;
                    const hasDoc = this.selectedDocs.length > 0;
                    if (hasFolder && hasDoc) {
                        this.toast = { type: 'error', message: 'Tidak dapat memindahkan folder dan dokumen secara bersamaan. Pilih salah satu jenis item.' };
                        setTimeout(() => { if (this.toast) this.toast = null; }, 5000);
                        return;
                    }
                    this.moveItems = [...this.selected];
                    this.moveTarget = undefined;
                    this.moveError = '';
                    this.moveQuery = '';
                    this.childrenOf = {};
                    this.expanded = new Set();
                    this.showMove = true;
                    await this.loadChildren('root');
                },
                async submitMove() {
                    this.moveLoading = true;
                    this.moveError = '';
                    try {
                        for (const it of this.moveItems) {
                            const url = it.type === 'folder' ? it.moveUrl : it.updateUrl;
                            const body = it.type === 'folder'
                                ? { parent_id: this.moveTarget }
                                : { document_category_id: this.moveTarget };
                            const r = await fetch(url, {
                                method: it.type === 'folder' ? 'PATCH' : 'PUT',
                                headers: {
                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                    Accept: 'application/json',
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify(body)
                            });
                            if (!r.ok) {
                                const j = await r.json().catch(() => ({}));
                                this.moveError = j.message || (j.errors ? Object.values(j.errors).flat().join(' ') : 'Gagal memindahkan.');
                                return;
                            }
                        }
                        window.location.reload();
                    } catch (e) {
                        this.moveError = 'Terjadi kesalahan saat memindahkan item.';
                    } finally {
                        this.moveLoading = false;
                    }
                },

                // ============ Aksi Cepat Item Menu ============
                openRenameFor(item) {
                    this.renameItem = { type: item.type, id: item.id, name: item.name, updateUrl: item.updateUrl };
                    this.showRename = true;
                },
                openMoveFor(item) {
                    this.selected = [item];
                    this.openMove();
                },
                confirmDeleteFor(item) {
                    this.selected = [item];
                    this.showBulkDelete = true;
                },
                async togglePin(item) {
                    await this.patch(item.pinUrl, {});
                },
                async toggleVisibility(item) {
                    await this.patch(item.visibilityUrl, {
                        visibility: item.visibility === 'publik' ? 'internal' : 'publik'
                    });
                },
                async setItemVisibility(item, visibility) {
                    await this.patch(item.visibilityUrl, { visibility });
                },
                async patch(url, body) {
                    const token = document.querySelector('meta[name="csrf-token"]')?.content;
                    try {
                        const r = await fetch(url, {
                            method: 'PATCH',
                            headers: {
                                'X-CSRF-TOKEN': token,
                                'Accept': 'application/json',
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify(body)
                        });
                        const j = await r.json().catch(() => ({}));
                        if (!r.ok) {
                            this.toast = { type: 'error', message: j.message || 'Aksi gagal.' };
                            setTimeout(() => { if (this.toast) this.toast = null; }, 5000);
                            return;
                        }
                        window.location.reload();
                    } catch (err) {
                        this.toast = { type: 'error', message: 'Terjadi kesalahan jaringan.' };
                        setTimeout(() => { if (this.toast) this.toast = null; }, 5000);
                    }
                },
                handleKey(e) {
                    const typing = ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName);
                    if (e.key === 'Escape') {
                        this.clearSelection();
                    }
                    if (e.key === 'Delete' && !typing && this.selectionActive && @json(auth()->user()->can('document_delete'))) {
                        this.showBulkDelete = true;
                    }
                },

                // ============ View toggle (grid/list) ============
                mode: 'grid',
                setMode(m) {
                    this.mode = m;
                    localStorage.setItem('docViewMode', m);
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
                    const savedMode = localStorage.getItem('docViewMode');
                    if (savedMode === 'list' || savedMode === 'grid') { this.mode = savedMode; }

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

        if (typeof window.viewMode !== 'function') {
            window.viewMode = function(key = 'docViewMode') {
                return {
                    mode: 'grid',
                    init() {
                        const s = localStorage.getItem(key);
                        if (s === 'grid' || s === 'list') this.mode = s;
                    },
                    setMode(m) {
                        this.mode = m;
                        localStorage.setItem(key, m);
                    },
                };
            };
        }
    </script>

</x-app-layout>
