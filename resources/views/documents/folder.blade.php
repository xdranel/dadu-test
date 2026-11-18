<x-app-layout>
    <x-slot name="header">Dokumen</x-slot>

    @php
        $mi = 'w-full flex items-center gap-2.5 px-3.5 py-2 text-sm text-slate-700 hover:bg-slate-50 text-left';
        $miDanger = 'w-full flex items-center gap-2.5 px-3.5 py-2 text-sm text-rose-600 hover:bg-rose-50 text-left';
    @endphp

    <div x-data="docSelection('{{ route('documents.bulk-delete') }}')" @keydown.window="handleKey($event)">

        @can('create', App\Models\Document::class)
        <div @dragenter.window.prevent="onDragEnter($event)" @dragover.window.prevent
             @dragleave.window.prevent="onDragLeave($event)" @drop.window.prevent="onDropFiles($event)"></div>

        <div x-show="dropActive" x-cloak x-transition.opacity class="fixed inset-0 z-[60] bg-[#DA2D3B]/10 backdrop-blur-[2px] flex items-center justify-center pointer-events-none">
            <div class="bg-white border-2 border-dashed border-[#DA2D3B] rounded-3xl px-12 py-10 text-center shadow-2xl">
                <div class="mx-auto w-14 h-14 rounded-2xl bg-[#DA2D3B]/10 text-[#DA2D3B] flex items-center justify-center mb-3">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1M12 4v12m0-12l-4 4m4-4l4 4"/></svg>
                </div>
                <p class="text-lg font-bold text-slate-800">Lepas file di sini</p>
                <p class="text-sm text-slate-500 mt-1">untuk diunggah ke <span class="font-semibold text-[#DA2D3B]">{{ $documentCategory->name }}</span></p>
            </div>
        </div>

        {{-- Modal konfirmasi singkat setelah drop --}}
        <div x-show="showDropConfirm" x-cloak x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm px-4" @keydown.escape.window="cancelDrop()">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-md border border-slate-200 p-6" @click.away="cancelDrop()">
                <h3 class="text-lg font-bold text-slate-800">Unggah <span x-text="dropFiles.length"></span> file</h3>
                <p class="text-sm text-slate-500 mt-1">Ke folder <span class="font-semibold text-[#DA2D3B]">{{ $documentCategory->name }}</span></p>

                <ul class="mt-4 max-h-40 overflow-y-auto space-y-1.5">
                    <template x-for="f in dropFiles" :key="f.name">
                        <li class="flex items-center justify-between text-sm"><span class="truncate" x-text="f.name"></span><span class="text-xs text-slate-400 ml-3 flex-shrink-0" x-text="fmt(f.size)"></span></li>
                    </template>
                </ul>
                <p x-show="dropRejected.length" x-cloak class="mt-3 text-xs text-rose-600" x-text="dropRejected.length + ' file ditolak: ' + dropRejected.map(r => r.name + ' (' + r.reason + ')').join(', ')"></p>

                <label class="campus-label mt-4">Visibilitas</label>
                <select x-model="dropVisibility" class="campus-input w-full">
                    @foreach(config('documents.visibility', []) as $vKey => $vMeta)
                        <option value="{{ $vKey }}">{{ $vMeta['label'] }}</option>
                    @endforeach
                </select>

                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" @click="cancelDrop()" class="btn btn-secondary">Batal</button>
                    <button type="button" @click="startUpload()" :disabled="!dropFiles.length" class="btn btn-primary disabled:opacity-50">Unggah</button>
                </div>
            </div>
        </div>

        {{-- Panel progress mengambang (kanan bawah) --}}
        <div x-show="upload.open" x-cloak x-transition class="fixed bottom-4 right-4 z-[70] w-80 bg-white rounded-2xl border border-slate-200 shadow-2xl overflow-hidden" role="status" aria-live="polite">
            <div class="flex items-center justify-between px-4 py-3 bg-slate-50 border-b border-slate-100">
                <p class="text-sm font-bold text-slate-800" x-text="upload.done ? 'Unggahan selesai' : 'Mengunggah ' + upload.files.length + ' file...'"></p>
                <div class="flex items-center gap-1">
                    <button type="button" @click="upload.min = !upload.min" class="p-1 text-slate-400 hover:text-slate-700" aria-label="Ciutkan panel" title="Ciutkan"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg></button>
                    <button type="button" x-show="upload.done" @click="closeUpload()" class="p-1 text-slate-400 hover:text-slate-700" aria-label="Tutup panel" title="Tutup"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
                </div>
            </div>
            <ul x-show="!upload.min" class="max-h-64 overflow-y-auto divide-y divide-slate-100">
                <template x-for="f in upload.files" :key="f.name">
                    <li class="px-4 py-2.5">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-sm text-slate-700 truncate" :title="f.name" x-text="f.name"></span>
                            <span class="text-[11px] font-semibold flex-shrink-0"
                                  :class="{'text-slate-400': f.status==='menunggu', 'text-[#DA2D3B]': f.status==='mengunggah', 'text-emerald-600': f.status==='berhasil', 'text-rose-600': f.status==='gagal'}"
                                  x-text="f.status"></span>
                        </div>
                        <div class="h-1.5 mt-1.5 bg-slate-100 rounded-full overflow-hidden"><div class="h-full rounded-full transition-all" :class="f.status==='gagal' ? 'bg-rose-500' : (f.status==='berhasil' ? 'bg-emerald-500' : 'bg-[#DA2D3B]')" :style="`width:${f.progress}%`"></div></div>
                        <p x-show="f.reason" class="text-[11px] text-rose-600 mt-1" x-text="f.reason"></p>
                    </li>
                </template>
            </ul>
            <div class="px-4 py-3 border-t border-slate-100 flex justify-between items-center">
                <button type="button" x-show="!upload.done" @click="abortUpload()" class="text-xs font-semibold text-rose-600 hover:text-rose-700">Batalkan</button>
                <button type="button" x-show="upload.done" @click="window.location.reload()" class="btn btn-primary !py-1.5 !text-xs ml-auto">Muat ulang daftar</button>
            </div>
        </div>
        @endcan

        @php
            $isEmpty = $subfolders->isEmpty() && $documents->isEmpty();
        @endphp

        {{-- Breadcrumb interaktif --}}
        @include('documents.partials.breadcrumb', ['ancestors' => $ancestors, 'current' => $documentCategory->name])

        {{-- Folder header --}}
        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3 mb-4">
            <div class="flex items-start gap-3">
                <span class="flex-shrink-0 mt-1 text-[#DA2D3B]">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                </span>
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <h2 class="text-2xl font-bold text-slate-900 tracking-tight">{{ $documentCategory->name }}</h2>
                        @if($documentCategory->is_pinned)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-50 text-amber-700 border border-amber-200/60 uppercase tracking-wide" title="Folder ini disematkan di atas">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M16 3l5 5-3 1-4 4v5l-2 2-3-3-4 4-1-1 4-4-3-3 2-2h5l4-4z"/></svg>
                                Pin
                            </span>
                        @endif
                    </div>
                    <p class="text-sm mt-1 {{ $documentCategory->description ? 'text-slate-500' : 'text-slate-400 italic' }}">
                        {{ $documentCategory->description ?: 'Belum ada deskripsi' }}
                    </p>
                </div>
            </div>

            {{-- Actions area (Toolbar: Sort, Grid/List, + Baru) --}}
            <div class="flex items-center gap-2 flex-wrap justify-end flex-shrink-0">
                {{-- Sort dropdown --}}
                <div x-data="{ open: false }" @keydown.escape.window="open = false" class="relative">
                    <button type="button" @click="open = !open" class="btn btn-secondary inline-flex items-center gap-1.5" aria-haspopup="menu" :aria-expanded="open">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h12M3 12h8M3 17h4m10-10v10m0 0l-3-3m3 3l3-3"/></svg>
                        Urutkan
                    </button>
                    <div x-show="open" x-cloak @click.outside="open = false" role="menu" class="absolute right-0 mt-2 w-52 bg-white rounded-xl border border-slate-200 shadow-lg py-1.5 z-30">
                        @foreach(['title' => 'Nama', 'created_at' => 'Tanggal', 'file_size' => 'Ukuran', 'download_count' => 'Unduhan'] as $col => $label)
                            @foreach(['asc' => 'naik', 'desc' => 'turun'] as $d => $dl)
                                <a role="menuitem" href="{{ route('documents.folder', array_merge([$documentCategory], request()->only('search'), ['sort' => $col, 'dir' => $d])) }}"
                                   class="flex items-center justify-between px-3.5 py-2 text-sm hover:bg-slate-50 {{ $sort === $col && $dir === $d ? 'text-[#DA2D3B] font-semibold' : 'text-slate-700' }}">
                                    {{ $label }} ({{ $dl }}) @if($sort === $col && $dir === $d)<span>&#10003;</span>@endif
                                </a>
                            @endforeach
                        @endforeach
                    </div>
                </div>

                {{-- Grid / List toggle --}}
                <div class="flex items-center bg-slate-100 rounded-xl p-1 gap-0.5 flex-shrink-0" role="group" aria-label="Mode tampilan">
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

                {{-- + Baru dropdown --}}
                @if(auth()->user()?->can('create', App\Models\DocumentCategory::class) || auth()->user()?->can('create', App\Models\Document::class))
                <div x-data="{ open: false }" @keydown.escape.window="open = false" class="relative">
                    <button type="button" @click="open = !open" class="btn btn-primary inline-flex items-center gap-1.5" aria-haspopup="menu" :aria-expanded="open">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/></svg>
                        Baru
                    </button>
                    <div x-show="open" x-cloak @click.outside="open = false" role="menu" class="absolute right-0 mt-2 w-56 bg-white rounded-xl border border-slate-200 shadow-lg py-1.5 z-30">
                        @can('create', App\Models\DocumentCategory::class)
                        <button type="button" role="menuitem" @click="open = false; openCreateSub({{ $documentCategory->id }}, @js($documentCategory->name))" class="w-full flex items-center gap-2.5 px-3.5 py-2 text-sm text-slate-700 hover:bg-slate-50 text-left">
                            <svg class="w-4 h-4 text-[#DA2D3B]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 11v4m-2-2h4"/></svg>
                            Folder baru
                        </button>
                        @endcan
                        @can('create', App\Models\Document::class)
                        <a role="menuitem" href="{{ route('documents.create', ['category' => $documentCategory->id]) }}" class="flex items-center gap-2.5 px-3.5 py-2 text-sm text-slate-700 hover:bg-slate-50">
                            <svg class="w-4 h-4 text-[#DA2D3B]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1M12 4v12m0-12l-4 4m4-4l4 4"/></svg>
                            Upload file
                        </a>
                        @endcan
                    </div>
                </div>
                @endif
            </div>
        </div>

        {{-- Unified Search + Content --}}
        <div>

            {{-- Search bar --}}
            <form action="{{ route('documents.folder', $documentCategory) }}" method="GET" class="mb-4 max-w-md">
                <div class="relative flex items-center bg-white border border-slate-200 rounded-xl px-3.5 py-2 shadow-sm focus-within:border-[#DA2D3B]/40 focus-within:ring-2 focus-within:ring-[#DA2D3B]/5 transition-all">
                    <span class="text-slate-400 mr-2.5 flex-shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="Cari di folder ini..."
                           class="w-full bg-transparent border-0 focus:ring-0 text-slate-800 placeholder-slate-400 p-0 text-sm">
                    @if(request('search'))
                        <a href="{{ route('documents.folder', $documentCategory) }}" class="text-slate-400 hover:text-slate-600 ml-1.5" aria-label="Reset pencarian" title="Reset pencarian">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </a>
                    @endif
                </div>
            </form>

            {{-- Toolbar Selection (muncul di bawah search bar) --}}
            <div class="mb-4">
                @include('documents.partials.selection-toolbar')
            </div>

            @php
                $sortLink = function ($col) use ($documentCategory, $sort, $dir) {
                    $nextDir = ($sort === $col && $dir === 'asc') ? 'desc' : 'asc';
                    return route('documents.folder', array_merge([$documentCategory], request()->only('search'), ['sort' => $col, 'dir' => $nextDir]));
                };
                $arrow = fn($col) => $sort === $col ? ($dir === 'asc' ? '▲' : '▼') : '';
            @endphp

            {{-- ===== MODE GRID ===== --}}
            <div x-show="mode === 'grid'"
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100">

                @if($isEmpty)
                    {{-- Empty state --}}
                    <div class="text-center py-16 bg-white rounded-2xl border border-slate-200/60 shadow-sm flex flex-col items-center justify-center">
                        <div class="p-4 bg-slate-50 text-slate-300 rounded-full mb-4">
                            <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                        </div>
                        <p class="text-base font-bold text-slate-600">
                            {{ request('search') ? 'Tidak ada hasil untuk "'.request('search').'".' : 'Folder ini masih kosong.' }}
                        </p>
                        @unless(request('search'))
                            <div class="flex items-center gap-2 mt-4">
                                @can('create', App\Models\DocumentCategory::class)
                                <button type="button" @click="openCreateSub({{ $documentCategory->id }}, @js($documentCategory->name))" class="btn btn-secondary">Buat Folder</button>
                                @endcan
                                @can('create', App\Models\Document::class)
                                <a href="{{ route('documents.create', ['category' => $documentCategory->id]) }}" class="btn btn-primary">Upload dokumen pertama</a>
                                @endcan
                            </div>
                        @endunless
                    </div>
                @else
                    @php
                        $pinnedSubs = $subfolders->where('is_pinned', true);
                        $otherSubs  = $subfolders->where('is_pinned', false);
                    @endphp

                    @if($subfolders->isNotEmpty())
                        <section class="mb-8" aria-labelledby="sec-folder">
                            <h3 id="sec-folder" class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-500 mb-3">
                                Folder <span class="px-1.5 py-0.5 rounded-md bg-slate-100 text-slate-500 text-[10px]">{{ $subfolders->count() }}</span>
                            </h3>
                            <div class="folder-grid grid grid-cols-[repeat(auto-fill,132px)] gap-x-3 gap-y-4 justify-start" style="grid-template-columns: repeat(auto-fill, 132px);">
                                @foreach($pinnedSubs->concat($otherSubs) as $sub)
                                    <x-folder-card :category="$sub" mode="admin"
                                                   :href="route('documents.folder', $sub)"
                                                   :editUrl="route('document_categories.update', $sub)" />
                                @endforeach
                            </div>
                        </section>
                    @endif

                    @if($documents->isNotEmpty())
                        <section aria-labelledby="sec-file">
                            <h3 id="sec-file" class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-500 mb-3">
                                File <span class="px-1.5 py-0.5 rounded-md bg-slate-100 text-slate-500 text-[10px]">{{ $documents->total() }}</span>
                            </h3>
                            <div class="grid grid-cols-[repeat(auto-fill,minmax(180px,1fr))] gap-3" style="grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));">
                                @foreach($documents as $dok)
                                    @include('documents.partials.doc-grid-card', ['dok' => $dok, 'mode' => 'admin'])
                                @endforeach
                            </div>
                            @if($documents->hasPages())<div class="mt-5">{{ $documents->links() }}</div>@endif
                        </section>
                    @endif
                @endif
            </div>

            {{-- ===== MODE LIST (Tabel gabungan folder + dokumen) ===== --}}
            <div x-show="mode === 'list'"
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100">

                <div class="campus-card overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="campus-table">
                            <thead>
                                <tr>
                                    {{-- Kolom Checkbox Selection (Kolom 1) --}}
                                    <th class="!text-center w-10">
                                        <span class="sr-only">Pilih</span>
                                    </th>
                                    <th><a href="{{ $sortLink('title') }}" class="hover:text-[#DA2D3B]" title="Urutkan berdasarkan nama">Nama {{ $arrow('title') }}</a></th>
                                    <th class="!text-center">Tipe / Ukuran</th>
                                    <th class="!text-center">Visibilitas</th>
                                    <th class="!text-center"><a href="{{ $sortLink('created_at') }}" class="hover:text-[#DA2D3B]" title="Urutkan berdasarkan tanggal">Tanggal {{ $arrow('created_at') }}</a></th>
                                    <th class="!text-center"><a href="{{ $sortLink('download_count') }}" class="hover:text-[#DA2D3B]" title="Urutkan berdasarkan unduhan">Unduhan {{ $arrow('download_count') }}</a></th>
                                    {{-- Kolom Aksi Cepat (Kolom 7) --}}
                                    <th class="!text-center w-12">
                                        <span class="sr-only">Aksi</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {{-- ===== Baris Folder ===== --}}
                                @foreach($subfolders as $sub)
                                    @php
                                        $subCount = $sub->documents_count ?? 0;
                                        $subItem = [
                                            'type' => 'folder',
                                            'id' => $sub->id,
                                            'name' => $sub->name,
                                            'updateUrl' => route('document_categories.update', $sub),
                                            'moveUrl' => route('document_categories.move', $sub),
                                            'pinUrl' => route('document_categories.pin', $sub),
                                            'visibilityUrl' => route('document_categories.visibility', $sub),
                                            'description' => $sub->description,
                                            'isPinned' => (bool)$sub->is_pinned,
                                            'visibility' => $sub->visibility ?? 'publik',
                                            'parentId' => $sub->parent_id,
                                            'depth' => $sub->depth ?? 0,
                                            'showWhenEmpty' => (bool)$sub->show_when_empty,
                                            'docCount' => $subCount,
                                            'childCount' => $sub->children_count ?? 0,
                                        ];
                                    @endphp
                                    <tr class="group align-middle bg-slate-50/60 hover:bg-slate-50 transition-colors"
                                        x-data="{ open: false }"
                                        @contextmenu.prevent="open = true">
                                        {{-- Checkbox folder di list mode (kolom 1) --}}
                                        <td class="text-center align-middle w-10">
                                            <label class="inline-flex items-center justify-center p-1.5 cursor-pointer rounded-lg"
                                                   @click.stop>
                                                <input type="checkbox"
                                                       class="sr-only peer"
                                                       :checked="isSelected('folder', {{ $sub->id }})"
                                                       @change="toggleItem(@js($subItem))"
                                                       aria-label="Pilih folder {{ $sub->name }}">
                                                <span class="w-5 h-5 flex items-center justify-center rounded-md border-2 bg-white shadow-sm transition-all duration-150"
                                                      :class="isSelected('folder', {{ $sub->id }}) ? 'bg-[#DA2D3B] border-[#DA2D3B] opacity-100' : 'border-slate-300 ' + (selectionActive ? 'opacity-100' : 'opacity-0 group-hover:opacity-100 [@media(hover:none)]:opacity-100')">
                                                    <svg class="w-3.5 h-3.5 text-white transition-all duration-150"
                                                         :class="isSelected('folder', {{ $sub->id }}) ? 'opacity-100 scale-100' : 'opacity-0 scale-75 hidden'"
                                                         viewBox="0 0 12 10" fill="none" aria-hidden="true">
                                                        <path d="M1.5 5.2l3 3 6-6.5" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                    </svg>
                                                </span>
                                            </label>
                                        </td>
                                        {{-- Nama folder — seluruh baris bisa diklik --}}
                                        <td>
                                            <a href="{{ route('documents.folder', $sub) }}"
                                               class="flex items-center gap-2.5 font-bold text-slate-800 hover:text-[#DA2D3B] transition-colors"
                                               title="Buka folder {{ $sub->name }}"
                                               @click="if (selectionActive) { $event.preventDefault(); toggleItem(@js($subItem)); }">
                                                {{-- Folder icon kecil --}}
                                                <svg class="w-5 h-5 text-[#DA2D3B] flex-shrink-0" viewBox="0 0 48 40" aria-hidden="true">
                                                    <path fill="currentColor" opacity="0.45" d="M2 7.5A3.5 3.5 0 0 1 5.5 4h11.6c.93 0 1.82.37 2.47 1.03L22.5 8h20A3.5 3.5 0 0 1 46 11.5V32a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V7.5Z"/>
                                                    <path fill="currentColor" d="M2 15.5A2.5 2.5 0 0 1 4.5 13h39a2.5 2.5 0 0 1 2.5 2.5V32a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V15.5Z"/>
                                                </svg>
                                                {{ $sub->name }}
                                                @if($sub->is_pinned)
                                                    <svg class="w-3 h-3 text-amber-500 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24" title="Disematkan"><path d="M16 3l5 5-3 1-4 4v5l-2 2-3-3-4 4-1-1 4-4-3-3 2-2h5l4-4z"/></svg>
                                                @endif
                                            </a>
                                            @if($sub->description)
                                                <p class="text-xs text-slate-400 mt-0.5 ml-7 truncate max-w-xs" title="{{ $sub->description }}">{{ $sub->description }}</p>
                                            @endif
                                        </td>
                                        {{-- Tipe/Ukuran: jumlah item --}}
                                        <td class="text-center align-middle">
                                            <span class="text-xs text-slate-500 font-medium">{{ $subCount }} item</span>
                                        </td>
                                        {{-- Visibilitas folder --}}
                                        <td class="text-center align-middle">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $sub->visibility_badge }}">{{ $sub->visibility_label }}</span>
                                        </td>
                                        {{-- Tanggal dibuat --}}
                                        <td class="text-center text-slate-500 align-middle text-sm">
                                            {{ $sub->created_at->translatedFormat('d M Y') }}
                                        </td>
                                        {{-- Unduhan: tidak relevan --}}
                                        <td class="text-center align-middle text-slate-400">-</td>
                                        {{-- Menu aksi folder (kolom 7) --}}
                                        <td class="text-center align-middle w-12" @click.stop>
                                            <x-item-menu label="Menu folder {{ $sub->name }}">
                                                <a role="menuitem" href="{{ route('documents.folder', $sub) }}" class="{{ $mi }}">Buka</a>
                                                @can('document_category_update')
                                                    <button type="button" role="menuitem" class="{{ $mi }}" @click="open = false; openRenameFor(@js($subItem))">Ganti nama</button>
                                                    <button type="button" role="menuitem" class="{{ $mi }}" @click="open = false; openMoveFor(@js($subItem))">Pindahkan</button>
                                                    <button type="button" role="menuitem" class="{{ $mi }}" @click="open = false; togglePin(@js($subItem))">{{ $sub->is_pinned ? 'Lepas sematan' : 'Sematkan' }}</button>
                                                    <div class="my-1 border-t border-slate-100"></div>
                                                    <div class="px-3.5 py-1 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Ubah Visibilitas</div>
                                                    @foreach(config('documents.visibility', []) as $vKey => $vMeta)
                                                        <button type="button" role="menuitem"
                                                                class="{{ $mi }} {{ ($sub->visibility ?? 'publik') === $vKey ? 'text-[#DA2D3B] font-semibold bg-[#DA2D3B]/5' : '' }}"
                                                                @click="open = false; setItemVisibility(@js($subItem), '{{ $vKey }}')">
                                                            <span class="w-2 h-2 rounded-full mr-1 {{ ($sub->visibility ?? 'publik') === $vKey ? 'bg-[#DA2D3B]' : 'bg-transparent' }}"></span>
                                                            {{ $vMeta['label'] }}
                                                        </button>
                                                    @endforeach
                                                @endcan
                                                @can('document_category_delete')
                                                    <div class="my-1 border-t border-slate-100"></div>
                                                    <button type="button" role="menuitem" class="{{ $miDanger }}" @click="open = false; confirmDeleteFor(@js($subItem))">Hapus</button>
                                                @endcan
                                            </x-item-menu>
                                        </td>
                                    </tr>
                                @endforeach

                                {{-- ===== Baris Dokumen ===== --}}
                                @forelse ($documents as $dok)
                                    @php
                                        $dokItem = [
                                            'type' => 'doc',
                                            'id' => $dok->id,
                                            'name' => $dok->title,
                                            'updateUrl' => route('documents.update', $dok),
                                            'showUrl' => route('documents.show', $dok),
                                            'downloadUrl' => route('documents.download', $dok),
                                            'visibilityUrl' => route('documents.visibility', $dok),
                                            'abbreviation' => $dok->abbreviation,
                                            'description' => $dok->description,
                                            'visibility' => $dok->visibility,
                                            'categoryId' => $dok->document_category_id,
                                            'fileType' => strtoupper($dok->file_type),
                                            'fileSize' => $dok->file_size_formatted,
                                            'createdAt' => $dok->created_at->translatedFormat('d M Y'),
                                            'downloadCount' => $dok->download_count,
                                        ];
                                    @endphp
                                    <tr class="group align-middle hover:bg-slate-50/80 transition-colors"
                                        x-data="{ open: false }"
                                        @contextmenu.prevent="open = true">
                                        {{-- Checkbox dokumen di list mode (kolom 1) --}}
                                        <td class="text-center align-middle w-10">
                                            <label class="inline-flex items-center justify-center p-1.5 cursor-pointer rounded-lg"
                                                   @click.stop>
                                                <input type="checkbox"
                                                       class="sr-only peer"
                                                       :checked="isSelected('doc', {{ $dok->id }})"
                                                       @change="toggleItem(@js($dokItem))"
                                                       aria-label="Pilih dokumen {{ $dok->title }}">
                                                <span class="w-5 h-5 flex items-center justify-center rounded-md border-2 bg-white shadow-sm transition-all duration-150"
                                                      :class="isSelected('doc', {{ $dok->id }}) ? 'bg-[#DA2D3B] border-[#DA2D3B] opacity-100' : 'border-slate-300 ' + (selectionActive ? 'opacity-100' : 'opacity-0 group-hover:opacity-100 [@media(hover:none)]:opacity-100')">
                                                    <svg class="w-3.5 h-3.5 text-white transition-all duration-150"
                                                         :class="isSelected('doc', {{ $dok->id }}) ? 'opacity-100 scale-100' : 'opacity-0 scale-75 hidden'"
                                                         viewBox="0 0 12 10" fill="none" aria-hidden="true">
                                                        <path d="M1.5 5.2l3 3 6-6.5" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                    </svg>
                                                </span>
                                            </label>
                                        </td>
                                        {{-- Judul dokumen --}}
                                        <td>
                                            <a href="{{ route('documents.show', $dok) }}"
                                               class="font-semibold text-slate-900 hover:text-[#DA2D3B] transition-colors"
                                               @click="if (selectionActive) { $event.preventDefault(); toggleItem(@js($dokItem)); }">
                                                {{ $dok->title }}
                                            </a>
                                            @if($dok->abbreviation)
                                                <div class="mt-0.5">
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-600 uppercase tracking-wide" title="Singkatan">{{ $dok->abbreviation }}</span>
                                                </div>
                                            @endif
                                        </td>
                                        <td class="text-slate-500 text-center align-middle">
                                            <span class="uppercase font-semibold text-slate-600">{{ $dok->file_type }}</span>
                                            <div class="text-xs text-slate-400 mt-0.5">{{ $dok->file_size_formatted }}</div>
                                        </td>
                                        <td class="text-center align-middle">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $dok->visibility_badge }}">{{ $dok->visibility_label }}</span>
                                        </td>
                                        <td class="text-center text-slate-500 align-middle text-sm">
                                            {{ $dok->created_at->translatedFormat('d M Y') }}
                                        </td>
                                        <td class="text-center text-slate-500 align-middle">{{ $dok->download_count }}x</td>
                                        {{-- Menu aksi dokumen (kolom 7) --}}
                                        <td class="text-center align-middle w-12" @click.stop>
                                            <x-item-menu label="Menu dokumen {{ $dok->title }}">
                                                <a role="menuitem" href="{{ route('documents.show', $dok) }}" class="{{ $mi }}">Pratinjau</a>
                                                <a role="menuitem" href="{{ route('documents.download', $dok) }}" class="{{ $mi }}">Unduh</a>
                                                @can('document_update')
                                                    <a role="menuitem" href="{{ route('documents.edit', $dok) }}" class="{{ $mi }}">Edit detail</a>
                                                    <button type="button" role="menuitem" class="{{ $mi }}" @click="open = false; openRenameFor(@js($dokItem))">Ganti nama</button>
                                                    <button type="button" role="menuitem" class="{{ $mi }}" @click="open = false; openMoveFor(@js($dokItem))">Pindahkan</button>
                                                    <div class="my-1 border-t border-slate-100"></div>
                                                    <div class="px-3.5 py-1 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Ubah Visibilitas</div>
                                                    @foreach(config('documents.visibility', []) as $vKey => $vMeta)
                                                        <button type="button" role="menuitem"
                                                                class="{{ $mi }} {{ ($dok->visibility ?? 'publik') === $vKey ? 'text-[#DA2D3B] font-semibold bg-[#DA2D3B]/5' : '' }}"
                                                                @click="open = false; setItemVisibility(@js($dokItem), '{{ $vKey }}')">
                                                            <span class="w-2 h-2 rounded-full mr-1 {{ ($dok->visibility ?? 'publik') === $vKey ? 'bg-[#DA2D3B]' : 'bg-transparent' }}"></span>
                                                            {{ $vMeta['label'] }}
                                                        </button>
                                                    @endforeach
                                                @endcan
                                                @can('document_delete')
                                                    <div class="my-1 border-t border-slate-100"></div>
                                                    <button type="button" role="menuitem" class="{{ $miDanger }}" @click="open = false; confirmDeleteFor(@js($dokItem))">Hapus</button>
                                                @endcan
                                            </x-item-menu>
                                        </td>
                                    </tr>
                                @empty
                                    @if($subfolders->isEmpty())
                                        {{-- Tampilkan empty-state hanya jika juga tidak ada subfolder --}}
                                        <tr>
                                            <td colspan="7" class="empty-state">
                                                <div class="empty-state-icon">
                                                    <svg class="w-12 h-12 mx-auto text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                                                </div>
                                                <p class="empty-state-text">
                                                    {{ request('search') ? 'Tidak ada hasil untuk "'.request('search').'".' : 'Folder ini masih kosong.' }}
                                                </p>
                                                @can('create', App\Models\Document::class)
                                                @unless(request('search'))
                                                    <a href="{{ route('documents.create', ['category' => $documentCategory->id]) }}" class="btn btn-primary mt-4 inline-block">Upload dokumen pertama</a>
                                                @endunless
                                                @endcan
                                            </td>
                                        </tr>
                                    @endif
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Pagination dokumen (list mode) --}}
                @if($documents->hasPages())
                    <div class="mt-5">{{ $documents->links() }}</div>
                @endif
            </div>

        </div>

        @can('create', App\Models\DocumentCategory::class)
        {{-- ===== Modal Buat Folder (di dalam folder ini) ===== --}}
        <div x-show="showCreateSub"
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm px-4"
             x-transition style="display:none;">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-md border border-slate-200" @click.away="showCreateSub = false">
                <form action="{{ route('document_categories.store') }}" method="POST" class="p-6">
                    @csrf
                    <input type="hidden" name="parent_id" :value="subParentId">
                    <input type="hidden" name="redirect_to" value="{{ url()->full() }}">

                    <h3 class="text-lg font-bold text-slate-800 mb-1">Buat Folder</h3>
                    <p class="text-sm text-slate-500 mb-4">Folder akan dibuat di dalam: <span class="font-semibold text-[#DA2D3B]" x-text="subParentName"></span></p>

                    <div class="mb-4">
                        <label class="campus-label">Nama Folder <span class="text-red-500">*</span></label>
                        <input type="text" name="name" required value="{{ old('name') }}"
                               placeholder="Contoh: Semester Ganjil 2026"
                               class="campus-input w-full @error('name') border-red-500 @enderror">
                        @error('name')
                            <p class="text-xs text-rose-600 mt-1 font-medium">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="mb-4">
                        <label class="campus-label">Deskripsi</label>
                        <textarea name="description" rows="3"
                                  placeholder="Deskripsi singkat folder (opsional)"
                                  class="campus-input w-full">{{ old('description') }}</textarea>
                    </div>

                    <div class="mb-4">
                        <label class="campus-label">Visibilitas <span class="text-red-500">*</span></label>
                        <select name="visibility" class="campus-input w-full">
                            @foreach(config('documents.visibility', []) as $vKey => $vMeta)
                                <option value="{{ $vKey }}" {{ old('visibility', $documentCategory->visibility) === $vKey ? 'selected' : '' }}>{{ $vMeta['label'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Toggle: Sematkan di atas --}}
                    <label class="flex items-center justify-between gap-3 py-2.5 border-t border-slate-100 {{ $pinnedLimitReached ? 'opacity-60 cursor-not-allowed' : 'cursor-pointer' }}"
                           title="{{ $pinnedLimitReached ? 'Maksimal 5 folder yang bisa disematkan. Lepas sematan folder lain terlebih dahulu.' : '' }}">
                        <span class="text-sm font-medium text-slate-700">
                            Sematkan di atas
                            @if($pinnedLimitReached)
                                <span class="block text-xs text-amber-600 font-normal">Maksimal 5 folder yang bisa disematkan.</span>
                            @endif
                        </span>
                        <div class="relative">
                            <input type="hidden" name="is_pinned" value="0">
                            <input type="checkbox" name="is_pinned" value="1" class="sr-only peer"
                                   {{ $pinnedLimitReached ? 'disabled' : '' }}>
                            <div class="w-10 h-5 bg-slate-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-[#DA2D3B]/20 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-[#DA2D3B]"></div>
                        </div>
                    </label>

                    {{-- Toggle: Tampilkan di Portal meski kosong --}}
                    <label class="flex items-center justify-between gap-3 py-2.5 border-t border-slate-100 cursor-pointer">
                        <span class="text-sm font-medium text-slate-700">Tampilkan di Portal meski kosong
                            <span class="block text-xs text-slate-400 font-normal">Folder tanpa dokumen tetap muncul di Portal Dokumen.</span></span>
                        <div class="relative"><input type="hidden" name="show_when_empty" value="0">
                            <input type="checkbox" name="show_when_empty" value="1" class="sr-only peer">
                            <div class="w-10 h-5 bg-slate-200 rounded-full peer peer-checked:bg-[#DA2D3B] peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border after:border-gray-300 after:rounded-full after:h-4 after:w-4 after:transition-all"></div></div>
                    </label>

                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" @click="showCreateSub = false" class="btn btn-secondary">Batal</button>
                        <button type="submit" class="btn btn-primary">Buat Folder</button>
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

        @include('documents.partials.bulk-delete-modal')
        @include('documents.partials.rename-modal')
        @include('documents.partials.detail-panel')
        @include('documents.partials.move-modal')
    </div>

    <script>
        function docSelection(bulkDeleteUrl) {
            return {
                // ============ Modal buat subfolder ============
                showCreateSub: false,
                subParentId: {{ $documentCategory->id }},
                subParentName: @js($documentCategory->name),
                openCreateSub(parentId, parentName) {
                    this.subParentId = parentId;
                    this.subParentName = parentName;
                    this.showCreateSub = true;
                },

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

                // ============ Drag & Drop Upload State ============
                dropActive: false,
                dragDepth: 0,
                showDropConfirm: false,
                dropFiles: [],
                dropRejected: [],
                dropVisibility: @js($documentCategory->visibility ?? 'publik'),
                upload: { open: false, min: false, done: false, files: [] },
                xhr: null,
                ALLOWED: ['pdf','doc','docx','xls','xlsx','ppt','pptx','zip','rar'],
                MAX: 15 * 1024 * 1024,
                fmt(b) { return b >= 1048576 ? (b/1048576).toFixed(2)+' MB' : (b/1024).toFixed(1)+' KB'; },

                onDragEnter(e) {
                    if (e.dataTransfer?.types?.includes('Files')) {
                        this.dragDepth++;
                        this.dropActive = true;
                    }
                },
                onDragLeave() {
                    this.dragDepth = Math.max(0, this.dragDepth - 1);
                    if (!this.dragDepth) this.dropActive = false;
                },
                onDropFiles(e) {
                    this.dragDepth = 0;
                    this.dropActive = false;
                    if (!e.dataTransfer?.files?.length) return;
                    this.dropFiles = [];
                    this.dropRejected = [];
                    Array.from(e.dataTransfer.files).forEach(f => {
                        const ext = f.name.split('.').pop().toLowerCase();
                        if (!this.ALLOWED.includes(ext)) {
                            return this.dropRejected.push({ name: f.name, reason: 'tipe tidak diizinkan' });
                        }
                        if (f.size > this.MAX) {
                            return this.dropRejected.push({ name: f.name, reason: '> 15 MB' });
                        }
                        this.dropFiles.push(f);
                    });
                    this.showDropConfirm = true;
                },
                cancelDrop() {
                    this.showDropConfirm = false;
                    this.dropFiles = [];
                    this.dropRejected = [];
                },
                startUpload() {
                    const files = this.dropFiles;
                    if (!files.length) return;
                    this.showDropConfirm = false;
                    this.upload = {
                        open: true,
                        min: false,
                        done: false,
                        files: files.map(f => ({ name: f.name, size: f.size, status: 'menunggu', progress: 0, reason: '' }))
                    };

                    const fd = new FormData();
                    fd.append('_token', document.querySelector('meta[name=csrf-token]').content);
                    fd.append('document_category_id', {{ $documentCategory->id }});
                    fd.append('visibility', this.dropVisibility);
                    files.forEach((f, i) => {
                        fd.append('files[]', f);
                        fd.append(`judul[${i}]`, '');
                        fd.append(`singkatan[${i}]`, '');
                        fd.append(`deskripsi[${i}]`, '');
                    });

                    const total = files.reduce((s, f) => s + f.size, 0);
                    const xhr = this.xhr = new XMLHttpRequest();
                    xhr.open('POST', @js(route('documents.store')));
                    xhr.setRequestHeader('Accept', 'application/json');
                    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                    xhr.upload.onprogress = (e) => {
                        let sent = e.loaded;
                        this.upload.files.forEach((f, i) => {
                            const part = Math.max(0, Math.min(files[i].size, sent));
                            sent -= files[i].size;
                            f.progress = Math.round((part / files[i].size) * 100);
                            f.status = f.progress >= 100 ? 'mengunggah' : (part > 0 ? 'mengunggah' : 'menunggu');
                        });
                    };
                    xhr.onload = () => {
                        this.upload.done = true;
                        if (xhr.status === 429) {
                            this.upload.files.forEach(f => {
                                f.status = 'gagal';
                                f.reason = 'Terlalu banyak unggahan. Coba lagi dalam 1 menit.';
                            });
                            return;
                        }
                        let j = {};
                        try { j = JSON.parse(xhr.responseText); } catch (_) {}
                        if (xhr.status >= 200 && xhr.status < 300) {
                            const failed = Object.fromEntries((j.failed || []).map(x => [x.name, x.reason]));
                            this.upload.files.forEach(f => {
                                if (failed[f.name]) {
                                    f.status = 'gagal';
                                    f.reason = failed[f.name];
                                } else {
                                    f.status = 'berhasil';
                                    f.progress = 100;
                                }
                            });
                        } else {
                            this.upload.files.forEach(f => {
                                f.status = 'gagal';
                                f.reason = j.message || (j.errors ? Object.values(j.errors).flat().join(' ') : 'Unggahan gagal.');
                            });
                        }
                    };
                    xhr.onerror = () => {
                        this.upload.done = true;
                        this.upload.files.forEach(f => { f.status = 'gagal'; f.reason = 'Koneksi terputus.'; });
                    };
                    xhr.onabort = () => {
                        this.upload.done = true;
                        this.upload.files.forEach(f => {
                            if (f.status !== 'berhasil') { f.status = 'gagal'; f.reason = 'Dibatalkan.'; }
                        });
                    };
                    xhr.send(fd);
                },
                abortUpload() { this.xhr?.abort(); },
                closeUpload() { this.upload.open = false; },

                // ============ Methods ============
                isSelected(type, id) {
                    return this.selected.some(i => i.type === type && i.id === id);
                },
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
                    if (typeof this.openMove === 'function') {
                        this.openMove();
                    }
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
                init() {
                    const saved = localStorage.getItem('docViewMode');
                    if (saved === 'list' || saved === 'grid') { this.mode = saved; }

                    @if($errors->has('name') || $errors->has('parent_id') || $errors->has('is_pinned'))
                        this.showCreateSub = true;
                    @endif
                },
            }
        }
    </script>
</x-app-layout>
