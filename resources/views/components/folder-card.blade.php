@props([
    'category',
    'mode' => 'public',
    'href' => null,
    'editUrl' => null,
])

@php
    $count       = $category->documents_count ?? $category->documents()->count();
    $hasDocs     = $count > 0;
    $isAdmin     = $mode === 'admin';
    $link        = $href ?? '#';
    $childCount  = isset($category->children_count) ? $category->children_count : ($category->relationLoaded('children') ? $category->children->count() : 0);
    $countLabel  = $count > 99 ? '99+' : (string) $count;
    $countSize   = strlen($countLabel) > 2 ? 7 : (strlen($countLabel) === 2 ? 8 : 9);
    $folderTone  = $hasDocs ? 'text-[#DA2D3B]' : 'text-slate-300';
    $countInk    = $hasDocs ? '#FFFFFF' : '#475569';
@endphp

<div class="relative group h-full">

    {{-- Checkbox (admin only) — pojok kiri-atas, nyaman diklik dan kontras jelas --}}
    @if($isAdmin)
    <label class="absolute top-1.5 left-1.5 z-10 flex items-center justify-center p-1.5 cursor-pointer rounded-lg
                  transition-opacity duration-150"
           :class="selectionActive || isSelected('folder', {{ $category->id }}) ? 'opacity-100' : 'opacity-0 group-hover:opacity-100'"
           @click.stop>
        <input type="checkbox"
               class="sr-only peer"
               :checked="isSelected('folder', {{ $category->id }})"
               @change="toggleItem({
                   type: 'folder',
                   id: {{ $category->id }},
                   name: @js($category->name),
                   updateUrl: @js($editUrl),
                   description: @js($category->description),
                   isPinned: {{ $category->is_pinned ? 'true' : 'false' }},
                   showWhenEmpty: {{ $category->show_when_empty ? 'true' : 'false' }},
                   docCount: {{ $count }},
                   childCount: {{ $childCount }}
               })"
               aria-label="Pilih folder {{ $category->name }}">
        <span class="w-5 h-5 flex items-center justify-center rounded-md border-2 shadow-sm transition-all duration-150"
              :class="isSelected('folder', {{ $category->id }}) ? 'bg-[#DA2D3B] border-[#DA2D3B]' : 'bg-white border-slate-300 hover:border-[#DA2D3B]'">
            <svg class="w-3.5 h-3.5 transition-all duration-150"
                 :class="isSelected('folder', {{ $category->id }}) ? 'text-white opacity-100 scale-100' : 'text-slate-300 hover:text-slate-400 opacity-90 scale-95'"
                 viewBox="0 0 12 10" fill="none" aria-hidden="true">
                <path d="M1.5 5.2l3 3 6-6.5" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </span>
    </label>
    @endif

    {{-- Card link — naviguasi normal jika tidak ada seleksi aktif, toggle jika seleksi aktif --}}
    <a href="{{ $link }}"
       class="relative flex flex-col h-full bg-white rounded-2xl border border-slate-200/60 shadow-[0_1px_3px_rgba(0,0,0,0.06)] p-3 cursor-pointer transition-all duration-200 hover:border-[#DA2D3B]/40 hover:shadow-md hover:-translate-y-0.5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#DA2D3B]/40"
       :class="{ 'ring-2 ring-[#DA2D3B] border-[#DA2D3B]/60': isSelected('folder', {{ $category->id }}) }"
       title="{{ $category->name }}"
       @click.prevent="selectionActive
           ? toggleItem({
                 type: 'folder',
                 id: {{ $category->id }},
                 name: @js($category->name),
                 updateUrl: @js($editUrl),
                 description: @js($category->description),
                 isPinned: {{ $category->is_pinned ? 'true' : 'false' }},
                 showWhenEmpty: {{ $category->show_when_empty ? 'true' : 'false' }},
                 docCount: {{ $count }},
                 childCount: {{ $childCount }}
             })
           : (window.location.href = @js($link))">

        {{-- Tile ikon folder + jumlah dokumen --}}
        <div class="relative w-16 h-16 flex items-center justify-center rounded-xl bg-slate-50 group-hover:bg-[#DA2D3B]/5 transition-colors">
            <svg class="w-12 h-10 {{ $folderTone }}" viewBox="0 0 48 40" aria-hidden="true">
                <path fill="currentColor" opacity="0.45"
                      d="M2 7.5A3.5 3.5 0 0 1 5.5 4h11.6c.93 0 1.82.37 2.47 1.03L22.5 8h20A3.5 3.5 0 0 1 46 11.5V32a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V7.5Z"/>
                <path fill="currentColor"
                      d="M2 15.5A2.5 2.5 0 0 1 4.5 13h39a2.5 2.5 0 0 1 2.5 2.5V32a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V15.5Z"/>
                <text x="8" y="29.5" fill="{{ $countInk }}" font-size="{{ $countSize }}" font-weight="700" letter-spacing="-0.2">{{ $countLabel }}</text>
            </svg>
            <span class="sr-only">{{ $count }} dokumen</span>

            {{-- Badge disematkan --}}
            @if($isAdmin && $category->is_pinned)
                <span class="absolute -top-1.5 -right-1.5 w-5 h-5 rounded-full bg-amber-400 text-white ring-2 ring-white shadow-sm flex items-center justify-center"
                      title="Disematkan">
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M16 3l5 5-3 1-4 4v5l-2 2-3-3-4 4-1-1 4-4-3-3 2-2h5l4-4z"/>
                    </svg>
                    <span class="sr-only">Disematkan</span>
                </span>
            @endif

            {{-- Indikator tampil di Portal meski kosong --}}
            @if($isAdmin && $category->show_when_empty)
                <span class="absolute -bottom-1 -right-1 w-[18px] h-[18px] rounded-full bg-white text-emerald-600 ring-1 ring-emerald-200 shadow-sm flex items-center justify-center"
                      title="Tampil di Portal Publik meski kosong">
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span class="sr-only">Tampil di Portal Publik meski kosong</span>
                </span>
            @endif
        </div>

        <h3 class="mt-2 text-sm font-bold text-slate-800 leading-snug truncate group-hover:text-[#DA2D3B] transition-colors duration-200">
            {{ $category->name }}
        </h3>

        <p class="mt-0.5 text-xs leading-relaxed truncate {{ $category->description ? 'text-slate-500' : 'text-slate-400 italic' }}"
           title="{{ $category->description ?: 'Belum ada deskripsi' }}">
            {{ $category->description ?: 'Belum ada deskripsi' }}
        </p>
    </a>
</div>
