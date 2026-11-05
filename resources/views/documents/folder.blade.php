<x-app-layout>
    <x-slot name="header">Dokumen</x-slot>

    <div x-data="docSelection('{{ route('documents.bulk-delete') }}')">

        @php
            $isEmpty = $subfolders->isEmpty() && $documents->isEmpty();
        @endphp

        {{-- Breadcrumb fleksibel dengan truncation --}}
        <div x-data="{ expandedBreadcrumb: false }">
            <nav class="flex items-center gap-1.5 text-sm mb-3 flex-wrap" aria-label="Breadcrumb">
                <a href="{{ route('documents.index') }}" class="text-slate-500 hover:text-[#DA2D3B] font-medium" title="Kembali ke daftar folder">Dokumen</a>
                @if($ancestors->count() <= 2)
                    @foreach($ancestors as $ancestor)
                        <svg class="w-4 h-4 text-slate-300 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        <a href="{{ route('documents.folder', $ancestor) }}" class="text-slate-500 hover:text-[#DA2D3B] font-medium truncate max-w-[140px] sm:max-w-xs">{{ $ancestor->name }}</a>
                    @endforeach
                @else
                    {{-- Middle collapsed items --}}
                    <span x-show="!expandedBreadcrumb" class="inline-flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-slate-300 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        <button type="button" @click="expandedBreadcrumb = true"
                                class="px-1.5 py-0.5 rounded bg-slate-100 hover:bg-slate-200 text-slate-500 text-xs font-bold tracking-wider transition-colors"
                                title="Klik untuk menampilkan seluruh jalur folder">
                            &hellip;
                        </button>
                    </span>

                    <span x-show="expandedBreadcrumb" class="inline-flex items-center gap-1.5 flex-wrap">
                        @foreach($ancestors->slice(0, -1) as $ancestor)
                            <svg class="w-4 h-4 text-slate-300 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                            <a href="{{ route('documents.folder', $ancestor) }}" class="text-slate-500 hover:text-[#DA2D3B] font-medium truncate max-w-[140px] sm:max-w-xs">{{ $ancestor->name }}</a>
                        @endforeach
                    </span>

                    {{-- Immediate parent (last ancestor) is always visible --}}
                    @php $lastAncestor = $ancestors->last(); @endphp
                    <svg class="w-4 h-4 text-slate-300 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    <a href="{{ route('documents.folder', $lastAncestor) }}" class="text-slate-500 hover:text-[#DA2D3B] font-medium truncate max-w-[140px] sm:max-w-xs">{{ $lastAncestor->name }}</a>
                @endif
                <svg class="w-4 h-4 text-slate-300 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                <span class="text-slate-800 font-semibold truncate max-w-[160px] sm:max-w-sm">{{ $documentCategory->name }}</span>
            </nav>
        </div>

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

            {{-- Actions area --}}
            <div class="flex items-center gap-2 flex-shrink-0">
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

                {{-- Sembunyikan tombol header saat folder kosong (cukup tampilkan CTA di empty state) --}}
                @if(!$isEmpty || request('search'))
                    @if($documentCategory->canHaveChildren())
                        @can('document_category_create')
                        <button type="button"
                                @click="openCreateSub({{ $documentCategory->id }}, @js($documentCategory->name))"
                                class="btn btn-secondary inline-flex items-center gap-1.5"
                                title="Buat folder di dalam {{ $documentCategory->name }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 11v4m-2-2h4"/></svg>
                            Buat Folder
                        </button>
                        @endcan
                    @endif
                    @can('document_create')
                    <a href="{{ route('documents.create', ['category' => $documentCategory->id]) }}"
                       class="btn btn-primary inline-flex items-center gap-1.5 flex-shrink-0"
                       title="Upload dokumen ke folder ini">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1M12 4v12m0-12l-4 4m4-4l4 4"/></svg>
                        Upload Dokumen
                    </a>
                    @endcan
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
                $isEmpty = $subfolders->isEmpty() && $documents->isEmpty();
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
                                @if($documentCategory->canHaveChildren())
                                    @can('document_category_create')
                                    <button type="button" @click="openCreateSub({{ $documentCategory->id }}, @js($documentCategory->name))" class="btn btn-secondary">Buat Folder</button>
                                    @endcan
                                @endif
                                @can('document_create')
                                <a href="{{ route('documents.create', ['category' => $documentCategory->id]) }}" class="btn btn-primary">Upload dokumen pertama</a>
                                @endcan
                            </div>
                        @endunless
                    </div>
                @else
                    <div class="grid grid-cols-[repeat(auto-fill,minmax(130px,1fr))] gap-3">
                        {{-- Folder tiles first --}}
                        @foreach($subfolders as $sub)
                            <x-folder-card
                                :category="$sub"
                                mode="admin"
                                :href="route('documents.folder', $sub)"
                                :editUrl="route('document_categories.update', $sub)" />
                        @endforeach

                        {{-- Document tiles after --}}
                        @foreach($documents as $dok)
                            @include('documents.partials.doc-grid-card', ['dok' => $dok, 'mode' => 'admin'])
                        @endforeach
                    </div>
                    {{-- Pagination (documents only) --}}
                    @if($documents->hasPages())
                        <div class="mt-5">{{ $documents->links() }}</div>
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
                                    <th><a href="{{ $sortLink('title') }}" class="hover:text-[#DA2D3B]" title="Urutkan berdasarkan nama">Nama {{ $arrow('title') }}</a></th>
                                    <th class="!text-center">Tipe / Ukuran</th>
                                    <th class="!text-center">Visibilitas</th>
                                    <th class="!text-center"><a href="{{ $sortLink('created_at') }}" class="hover:text-[#DA2D3B]" title="Urutkan berdasarkan tanggal">Tanggal {{ $arrow('created_at') }}</a></th>
                                    <th class="!text-center"><a href="{{ $sortLink('download_count') }}" class="hover:text-[#DA2D3B]" title="Urutkan berdasarkan unduhan">Unduhan {{ $arrow('download_count') }}</a></th>
                        {{-- Kolom Aksi: checkbox selection --}}
                        <th class="!text-center w-10">Pilih</th>
                                </tr>
                            </thead>
                            <tbody>
                                {{-- ===== Baris Folder ===== --}}
                                @foreach($subfolders as $sub)
                                    @php $subCount = $sub->documents_count ?? 0; @endphp
                                    <tr class="align-middle bg-slate-50/60 hover:bg-slate-50 transition-colors">
                                        {{-- Nama folder — seluruh baris bisa diklik --}}
                                        <td>
                                            <a href="{{ route('documents.folder', $sub) }}"
                                               class="flex items-center gap-2.5 font-bold text-slate-800 hover:text-[#DA2D3B] transition-colors"
                                               title="Buka folder {{ $sub->name }}">
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
                                        {{-- Checkbox folder di list mode --}}
                                        <td class="text-center align-middle">
                                            <label class="inline-flex items-center justify-center p-1.5 cursor-pointer rounded-lg"
                                                   @click.stop>
                                                <input type="checkbox"
                                                       class="sr-only peer"
                                                       :checked="isSelected('folder', {{ $sub->id }})"
                                                       @change="toggleItem({
                                                           type: 'folder',
                                                           id: {{ $sub->id }},
                                                           name: @js($sub->name),
                                                           updateUrl: @js(route('document_categories.update', $sub)),
                                                           description: @js($sub->description),
                                                           isPinned: {{ $sub->is_pinned ? 'true' : 'false' }},
                                                           showWhenEmpty: {{ $sub->show_when_empty ? 'true' : 'false' }},
                                                           docCount: {{ $subCount }},
                                                           childCount: 0
                                                       })"
                                                       aria-label="Pilih folder {{ $sub->name }}">
                                                <span class="w-5 h-5 flex items-center justify-center rounded-md border-2 shadow-sm transition-all duration-150"
                                                      :class="isSelected('folder', {{ $sub->id }}) ? 'bg-[#DA2D3B] border-[#DA2D3B]' : 'bg-white border-slate-300 hover:border-[#DA2D3B]'">
                                                    <svg class="w-3.5 h-3.5 transition-all duration-150"
                                                         :class="isSelected('folder', {{ $sub->id }}) ? 'text-white opacity-100 scale-100' : 'text-slate-300 hover:text-slate-400 opacity-90 scale-95'"
                                                         viewBox="0 0 12 10" fill="none">
                                                        <path d="M1.5 5.2l3 3 6-6.5" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                    </svg>
                                                </span>
                                            </label>
                                        </td>
                                    </tr>
                                @endforeach

                                {{-- ===== Baris Dokumen ===== --}}
                                @forelse ($documents as $dok)
                                    <tr class="align-middle hover:bg-slate-50/50 transition-colors">
                                        <td>
                                            <a href="{{ route('documents.show', $dok) }}" class="font-semibold text-slate-900 hover:text-[#DA2D3B]">{{ $dok->title }}</a>
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
                                        {{-- Checkbox dokumen di list mode --}}
                                        <td class="text-center align-middle">
                                            <label class="inline-flex items-center justify-center p-1.5 cursor-pointer rounded-lg"
                                                   @click.stop>
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
                                    @if($subfolders->isEmpty())
                                        {{-- Tampilkan empty-state hanya jika juga tidak ada subfolder --}}
                                        <tr>
                                            <td colspan="6" class="empty-state">
                                                <div class="empty-state-icon">
                                                    <svg class="w-12 h-12 mx-auto text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                                                </div>
                                                <p class="empty-state-text">
                                                    {{ request('search') ? 'Tidak ada hasil untuk "'.request('search').'".' : 'Folder ini masih kosong.' }}
                                                </p>
                                                @can('document_create')
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

        </div>{{-- end x-data docViewToggle --}}

        {{-- ===== Modal Buat Folder (di dalam folder ini) ===== --}}
        @php
            $pinnedLimitReached = \App\Models\DocumentCategory::where('is_pinned', true)->count() >= 5;
        @endphp
        <div x-show="showCreateSub"
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm px-4"
             x-transition style="display:none;">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-md border border-slate-200" @click.away="showCreateSub = false">
                <form action="{{ route('document_categories.store') }}" method="POST" class="p-6">
                    @csrf
                    <input type="hidden" name="parent_id" :value="subParentId">
                    <input type="hidden" name="redirect_to" value="{{ url()->full() }}">

                    <h3 class="text-lg font-bold text-slate-800 mb-1">Buat Folder</h3>
                    <p class="text-sm text-slate-500 mb-4">
                        Membuat folder di dalam: <span class="font-semibold text-[#DA2D3B]" x-text="subParentName"></span>
                    </p>

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

                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" @click="showCreateSub = false" class="btn btn-secondary">Batal</button>
                        <button type="submit" class="btn btn-primary">Buat Folder</button>
                    </div>
                </form>
            </div>
        </div>

        @include('documents.partials.bulk-delete-modal')
        @include('documents.partials.rename-modal')
        @include('documents.partials.detail-panel')
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
                    this.renameItem = { type: item.type, id: item.id, name: item.name, updateUrl: item.updateUrl };
                    this.showRename = true;
                },
                openDetail() {
                    if (!this.isSingle) return;
                    this.detailItem = { ...this.selected[0] };
                    this.showDetail = true;
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
