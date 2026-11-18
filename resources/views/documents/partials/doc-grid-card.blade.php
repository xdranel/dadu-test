@props(['dok', 'mode' => 'public', 'showPath' => false, 'showCategory' => false])
@php
    $isAdmin = ($mode ?? 'admin') === 'admin';
    $showPath = $showPath ?? ($showCategory ?? false);
    $mi = 'w-full flex items-center gap-2.5 px-3.5 py-2 text-sm text-slate-700 hover:bg-slate-50 text-left';
    $miDanger = 'w-full flex items-center gap-2.5 px-3.5 py-2 text-sm text-rose-600 hover:bg-rose-50 text-left';

    $docItem = [
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

<div class="group relative bg-white rounded-2xl border border-slate-200/70 shadow-sm hover:shadow-md hover:border-[#DA2D3B]/30 transition-all duration-150 overflow-hidden"
     x-data="{ open: false }"
     @if($isAdmin) @contextmenu.prevent="open = true" @endif
     :class="{ 'ring-2 ring-[#DA2D3B] border-[#DA2D3B]/60': typeof isSelected === 'function' && isSelected('doc', {{ $dok->id }}) }">

    @if($isAdmin)
        {{-- Checkbox kiri atas: tampil saat hover, saat terpilih, atau saat ada seleksi aktif --}}
        <label class="absolute top-2.5 left-2.5 z-10 cursor-pointer p-1" @click.stop>
            <input type="checkbox"
                   class="sr-only peer"
                   :checked="isSelected('doc', {{ $dok->id }})"
                   @change="toggleItem(@js($docItem))"
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

        {{-- Menu aksi kanan atas --}}
        <div class="absolute top-2 right-2 z-10" @click.stop>
            <x-item-menu label="Menu dokumen {{ $dok->title }}">
                <a role="menuitem" href="{{ route('documents.show', $dok) }}" class="{{ $mi }}">Pratinjau</a>
                <a role="menuitem" href="{{ route('documents.download', $dok) }}" class="{{ $mi }}">Unduh</a>
                @can('document_update')
                    <a role="menuitem" href="{{ route('documents.edit', $dok) }}" class="{{ $mi }}">Edit detail</a>
                    <button type="button" role="menuitem" class="{{ $mi }}" @click="open = false; openRenameFor(@js($docItem))">Ganti nama</button>
                    <button type="button" role="menuitem" class="{{ $mi }}" @click="open = false; openMoveFor(@js($docItem))">Pindahkan</button>
                    <div class="my-1 border-t border-slate-100"></div>
                    <div class="px-3.5 py-1 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Ubah Visibilitas</div>
                    @foreach(config('documents.visibility', []) as $vKey => $vMeta)
                        <button type="button" role="menuitem"
                                class="{{ $mi }} {{ ($dok->visibility ?? 'publik') === $vKey ? 'text-[#DA2D3B] font-semibold bg-[#DA2D3B]/5' : '' }}"
                                @click="open = false; setItemVisibility(@js($docItem), '{{ $vKey }}')">
                            <span class="w-2 h-2 rounded-full mr-1 {{ ($dok->visibility ?? 'publik') === $vKey ? 'bg-[#DA2D3B]' : 'bg-transparent' }}"></span>
                            {{ $vMeta['label'] }}
                        </button>
                    @endforeach
                @endcan
                @can('document_delete')
                    <div class="my-1 border-t border-slate-100"></div>
                    <button type="button" role="menuitem" class="{{ $miDanger }}" @click="open = false; confirmDeleteFor(@js($docItem))">Hapus</button>
                @endcan
            </x-item-menu>
        </div>
    @endif

    <a href="{{ $isAdmin ? route('documents.show', $dok) : route('public_documents.show', $dok) }}"
       class="block focus:outline-none focus-visible:ring-2 focus-visible:ring-[#DA2D3B]/40 rounded-2xl"
       title="{{ $dok->title }}"
       @if($isAdmin)
       @click="if (selectionActive) { $event.preventDefault(); toggleItem(@js($docItem)); }"
       @endif>
        <div class="h-24 bg-slate-50 flex items-center justify-center border-b border-slate-100">
            <x-file-icon :type="$dok->file_type" size="lg" />
        </div>
        <div class="p-3">
            <h4 class="text-sm font-semibold text-slate-800 line-clamp-2 leading-snug min-h-[2.5rem]">{{ $dok->title }}</h4>
            @if(!empty($showPath) && $dok->category)
                <p class="text-[11px] text-slate-400 truncate mt-1" title="{{ $dok->category->full_path ?? $dok->category->name }}">
                    {{ $dok->category->full_path ?? $dok->category->name }}
                </p>
            @endif
            <div class="mt-2 flex items-center justify-between gap-2">
                @if($isAdmin)
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold whitespace-nowrap {{ $dok->visibility_badge }}">{{ $dok->visibility_label }}</span>
                @endif
            </div>
            <div class="mt-1 flex items-center justify-between gap-2 text-[11px] text-slate-400">
                <span>{{ $dok->created_at->translatedFormat('d M Y') }}</span>
                <span class="whitespace-nowrap flex-shrink-0">{{ $dok->file_size_formatted }}</span>
            </div>
        </div>
    </a>
</div>
