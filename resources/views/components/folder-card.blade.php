@props(['category', 'href' => '#', 'mode' => 'public', 'editUrl' => null])
@php
    $subCount    = $category->children_count ?? 0;
    $docCount    = $category->documents_count ?? 0;
    $showFolders = $subCount > 0;
    $number      = $showFolders ? $subCount : $docCount;
    $numberLabel = $showFolders ? "$subCount subfolder" : "$docCount dokumen";

    $folderPath = config('documents.asset_dir', 'assets/images/document').'/'.config('documents.folder_image', 'foldercard.svg');
    $folderImg  = asset($folderPath).'?v='.@filemtime(public_path($folderPath));
    $link        = $href ?? '#';

    $folderItem = [
        'type' => 'folder',
        'id' => $category->id,
        'name' => $category->name,
        'updateUrl' => $editUrl ?? route('document_categories.update', $category),
        'moveUrl' => route('document_categories.move', $category),
        'pinUrl' => route('document_categories.pin', $category),
        'visibilityUrl' => route('document_categories.visibility', $category),
        'description' => $category->description,
        'isPinned' => (bool)$category->is_pinned,
        'visibility' => $category->visibility ?? 'publik',
        'parentId' => $category->parent_id,
        'depth' => $category->depth ?? 0,
        'showWhenEmpty' => (bool)$category->show_when_empty,
        'docCount' => $docCount,
        'childCount' => $subCount,
    ];
@endphp

{{-- Tidak dipilih: tanpa latar/border/bayangan. Hover: latar sangat tipis. Dipilih: latar + border. --}}
<div class="group relative w-full max-w-[132px] rounded-xl border border-transparent transition-colors hover:bg-slate-50"
     @if($mode === 'admin') :class="isSelected('folder', {{ $category->id }}) ? '!border-slate-300 !bg-slate-100' : ''" @endif>

    <a href="{{ $link }}" title="Buka folder {{ $category->name }}"
       class="block rounded-xl px-2 pt-4 pb-3 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#DA2D3B]/40">

        <div class="relative w-[76%] mx-auto">
            <img src="{{ $folderImg }}" alt="" width="1686" height="1383" decoding="async" draggable="false" class="w-full h-auto select-none">

            <span class="absolute left-[8%] bottom-[8%] text-xs font-normal leading-none text-slate-700 tabular-nums" title="{{ $numberLabel }}">
                {{ $number }}<span class="sr-only"> {{ $numberLabel }}</span>
            </span>

            @if($mode === 'admin')
                <span class="absolute right-[6%] bottom-[7%] w-[16%] aspect-square flex items-center justify-center" title="{{ $category->visibility_label }}">
                    <img src="{{ $category->visibility_icon_url }}" alt="Visibilitas: {{ $category->visibility_label }}"
                         class="max-w-full max-h-full object-contain opacity-80" decoding="async" draggable="false">
                </span>
            @endif
        </div>

        {{-- Jarak folder -> judul tetap mt-5. Judul -> tanggal dirapatkan (mt-0.5). --}}
        <h4 class="mt-5 px-0.5 text-center text-[13px] font-normal leading-tight text-slate-900 truncate" title="{{ $category->name }}">{{ $category->name }}</h4>
        <p class="mt-0.5 text-center text-[11px] font-light leading-tight text-slate-500">
            {{ $category->created_at ? (is_string($category->created_at) ? \Carbon\Carbon::parse($category->created_at)->translatedFormat('d F Y') : $category->created_at->translatedFormat('d F Y')) : '-' }}
        </p>
    </a>

    @if($category->is_pinned)
        <span class="absolute top-1.5 left-1.5 inline-flex items-center justify-center w-5 h-5 rounded-full bg-amber-100 text-amber-700" title="Disematkan" aria-label="Disematkan">
            <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M16 3l5 5-3 1-4 4v5l-2 2-3-3-4 4-1-1 4-4-3-3 2-2h5l4-4z"/></svg>
        </span>
    @endif

    @if($mode === 'admin')
        <label class="absolute top-1.5 right-1.5 z-10 cursor-pointer" @click.stop>
            <input type="checkbox" class="sr-only peer"
                   :checked="isSelected('folder', {{ $category->id }})"
                   @change="toggleItem(@js($folderItem))"
                   aria-label="Pilih folder {{ $category->name }}">
            <span class="w-5 h-5 flex items-center justify-center rounded-full border-2 transition peer-focus-visible:ring-2 peer-focus-visible:ring-[#DA2D3B]/40"
                  :class="isSelected('folder', {{ $category->id }})
                      ? 'bg-slate-700 border-slate-700 opacity-100'
                      : 'bg-white border-slate-300 ' + (selectionActive ? 'opacity-100' : 'opacity-0 group-hover:opacity-100 [@media(hover:none)]:opacity-100')">
                <svg class="w-3 h-3 text-white" :class="isSelected('folder', {{ $category->id }}) ? '' : 'hidden'" viewBox="0 0 12 10" fill="none" aria-hidden="true"><path d="M1.5 5.2l3 3 6-6.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
        </label>
    @endif
</div>
