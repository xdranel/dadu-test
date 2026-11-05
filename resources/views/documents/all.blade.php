<x-app-layout>
    <x-slot name="header">Dokumen</x-slot>

    {{-- Tabs --}}
    <div class="inline-flex items-center gap-1 bg-slate-100 p-1 rounded-xl w-fit mb-4">
        <a href="{{ route('documents.index') }}"
           class="px-4 py-1.5 text-sm font-semibold rounded-lg transition-all text-slate-500 hover:text-slate-700">
            Folder
        </a>
        <a href="{{ route('documents.index', ['view' => 'all']) }}"
           class="px-4 py-1.5 text-sm font-semibold rounded-lg transition-all bg-white text-[#DA2D3B] shadow-sm">
            Semua Dokumen
        </a>
    </div>

    <!-- Filter & Actions -->
    <div x-data="docSelection('{{ route('documents.bulk-delete') }}')" x-init="init()">

    {{-- Filter & Actions --}}
    <div class="filter-card mb-4">
        <div class="flex flex-wrap gap-2.5 items-end justify-between">
            <form action="{{ route('documents.index') }}" method="GET"
                  class="flex flex-wrap items-end gap-2.5">
                <input type="hidden" name="view" value="all">
                <div>
                    <label class="campus-label">Cari Judul</label>
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="Cari judul dokumen..."
                           class="campus-input min-w-[170px]">
                </div>
                <div>
                    <label class="campus-label">Kategori</label>
                    <select name="kategori" class="campus-select min-w-[140px]">
                        <option value="">Semua Kategori</option>
                        @foreach ($kategoriList as $kat)
                            <option value="{{ $kat->id }}" {{ request('kategori') == $kat->id ? 'selected' : '' }}>
                                {{ $kat->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="campus-label">Visibilitas</label>
                    <select name="visibilitas" class="campus-select min-w-[110px]">
                        <option value="">Semua</option>
                        <option value="publik" {{ request('visibilitas') === 'publik' ? 'selected' : '' }}>Publik dan Mahasiswa</option>
                        <option value="internal" {{ request('visibilitas') === 'internal' ? 'selected' : '' }}>Pegawai dan Dosen</option>
                    </select>
                </div>
                <div class="flex items-center gap-1.5">
                    <button type="submit" class="btn btn-secondary">Cari</button>
                    @if(request('search') || request('kategori') || request('visibilitas'))
                        <a href="{{ route('documents.index', ['view' => 'all']) }}" class="btn btn-secondary">Reset</a>
                    @endif
                </div>
            </form>

            <div class="flex items-center gap-2">
                {{-- Grid / List toggle --}}
                <div class="flex items-center bg-slate-100 rounded-xl p-1 gap-0.5" role="group" aria-label="Mode tampilan dokumen">
                    <button type="button"
                            @click="setMode('grid')"
                            :class="mode === 'grid' ? 'bg-white text-[#DA2D3B] shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                            class="inline-flex items-center justify-center w-8 h-8 rounded-lg transition-all duration-150"
                            title="Tampilan Grid" aria-label="Tampilan Grid">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                        </svg>
                    </button>
                    <button type="button"
                            @click="setMode('list')"
                            :class="mode === 'list' ? 'bg-white text-[#DA2D3B] shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                            class="inline-flex items-center justify-center w-8 h-8 rounded-lg transition-all duration-150"
                            title="Tampilan List" aria-label="Tampilan List">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                        </svg>
                    </button>
                </div>

                @can('document_create')
                <a href="{{ route('documents.create') }}" class="btn btn-primary">Upload Dokumen</a>
                @endcan
            </div>
        </div>
    </div>

    {{-- Toolbar Selection (muncul di bawah filter-card) --}}
    <div class="mb-4">
        @include('documents.partials.selection-toolbar')
    </div>

    <!-- Tabel -->
    {{-- ===== MODE GRID ===== --}}
    <div x-show="mode === 'grid'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
        @if($documents->count() > 0)
            <div class="grid grid-cols-[repeat(auto-fill,minmax(130px,1fr))] gap-3">
                @foreach($documents as $dok)
                    @include('documents.partials.doc-grid-card', ['dok' => $dok, 'mode' => 'admin', 'showCategory' => true])
                @endforeach
            </div>
            <div class="mt-5">{{ $documents->links() }}</div>
        @else
            <div class="text-center py-14 bg-white rounded-2xl border border-slate-200/60 shadow-sm flex flex-col items-center justify-center">
                <div class="p-4 bg-slate-50 text-slate-300 rounded-full mb-4">
                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
                <p class="text-base font-bold text-slate-600">Belum ada dokumen.</p>
            </div>
        @endif
    </div>

    {{-- ===== MODE LIST (Tabel) ===== --}}
    <div x-show="mode === 'list'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
    <div class="campus-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="campus-table">
                <thead>
                    <tr>
                        <th>Judul Dokumen</th>
                        <th class="!text-center">Kategori</th>
                        <th class="!text-center">Tipe / Ukuran</th>
                        <th class="!text-center">Visibilitas</th>
                        <th class="!text-center">Unduhan</th>
                        <th class="!text-center w-10">Pilih</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($documents as $dok)
                        <tr class="align-middle">
                            <td>
                                <a href="{{ route('documents.show', $dok) }}"
                                   class="font-semibold text-slate-900 hover:text-[#DA2D3B]">
                                    {{ $dok->title }}
                                </a>
                                @if($dok->abbreviation)
                                    <div class="text-xs text-slate-400 mt-0.5">{{ $dok->abbreviation }}</div>
                                @endif
                            </td>
                            <td class="text-slate-500 text-center align-middle">
                                {{ $dok->category?->name }}
                            </td>
                            <td class="text-slate-500 text-center align-middle">
                                <span class="uppercase font-semibold text-slate-600">{{ $dok->file_type }}</span>
                                <div class="text-xs text-slate-400 mt-0.5">{{ $dok->file_size_formatted }}</div>
                            </td>
                            <td class="text-center align-middle">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $dok->visibility_badge }}">
                                    {{ $dok->visibility_label }}
                                </span>
                            </td>
                            <td class="text-center text-slate-500 align-middle">
                                {{ $dok->download_count }}x
                            </td>
                            {{-- Checkbox --}}
                            <td class="text-center align-middle">
                                <label class="inline-flex items-center justify-center p-1.5 cursor-pointer rounded-lg" @click.stop>
                                    <input type="checkbox"
                                           class="sr-only peer"
                                           :checked="isSelected('doc', {{ $dok->id }})"
                                           @change="toggleItem({
                                               type: 'doc',
                                               id: {{ $dok->id }},
                                               name: @js($dok->title),
                                               updateUrl: @js(route('documents.update', $dok)),
                                               showUrl: @js(route('documents.show', $dok)),
                                               downloadUrl: @js(route('documents.download', $dok)),
                                               abbreviation: @js($dok->abbreviation),
                                               description: @js($dok->description),
                                               visibility: @js($dok->visibility),
                                               categoryId: {{ $dok->document_category_id ?? 'null' }},
                                               fileType: @js(strtoupper($dok->file_type)),
                                               fileSize: @js($dok->file_size_formatted),
                                               createdAt: @js($dok->created_at->translatedFormat('d M Y')),
                                               downloadCount: {{ $dok->download_count }}
                                           })"
                                           aria-label="Pilih dokumen {{ $dok->title }}">
                                    <span class="w-5 h-5 flex items-center justify-center rounded-md border-2 shadow-sm transition-all duration-150"
                                          :class="isSelected('doc', {{ $dok->id }}) ? 'bg-[#DA2D3B] border-[#DA2D3B]' : 'bg-white border-slate-300 hover:border-[#DA2D3B]'">
                                        <svg class="w-3.5 h-3.5 transition-all duration-150"
                                             :class="isSelected('doc', {{ $dok->id }}) ? 'text-white opacity-100 scale-100' : 'text-slate-300 hover:text-slate-400 opacity-90 scale-95'"
                                             viewBox="0 0 12 10" fill="none">
                                            <path d="M1.5 5.2l3 3 6-6.5" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </span>
                                </label>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-state">
                                <div class="empty-state-icon">
                                    <svg class="w-12 h-12 mx-auto text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                </div>
                                <p class="empty-state-text">Belum ada dokumen.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-5">{{ $documents->links() }}</div>
    </div>{{-- end mode list --}}

    </div>{{-- end x-data docViewToggle --}}

    @include('documents.partials.bulk-delete-modal')
    @include('documents.partials.rename-modal')
    @include('documents.partials.detail-panel')

    <script>
        function docSelection(bulkDeleteUrl) {
            return {
                selected: [],
                showBulkDelete: false,
                showRename: false,
                showDetail: false,
                bulkDeleteUrl: bulkDeleteUrl,
                renameItem: { type: '', id: null, name: '', updateUrl: '' },
                detailItem: {},

                get selectionActive() { return this.selected.length > 0; },
                get isSingle() { return this.selected.length === 1; },
                get selectedDocs() { return this.selected.filter(i => i.type === 'doc'); },
                get selectedFolders() { return this.selected.filter(i => i.type === 'folder'); },

                isSelected(type, id) { return this.selected.some(i => i.type === type && i.id === id); },
                toggleItem(item) {
                    const idx = this.selected.findIndex(i => i.type === item.type && i.id === item.id);
                    if (idx >= 0) { this.selected.splice(idx, 1); }
                    else { this.selected.push(item); }
                },
                clearSelection() { this.selected = []; },
                openBulkDelete() { this.showBulkDelete = true; },
                openRename() {
                    if (!this.isSingle) return;
                    const item = this.selected[0];
                    this.renameItem = { type: item.type, id: item.id, name: item.name, updateUrl: item.updateUrl };
                    this.showRename = true;
                },
                openDetail() {
                    if (!this.isSingle) return;
                    this.detailItem = { ...this.selected[0] };
                    this.showDetail = true;
                },
                mode: 'grid',
                init() {
                    const saved = localStorage.getItem('docViewMode');
                    if (saved === 'list' || saved === 'grid') { this.mode = saved; }
                },
                setMode(m) {
                    this.mode = m;
                    localStorage.setItem('docViewMode', m);
                },
            }
        }
    </script>
</x-app-layout>
