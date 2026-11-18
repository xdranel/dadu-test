@php
    $chev = '<svg class="w-4 h-4 text-slate-300 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>';
    $collapse = $ancestors->count() > 2;
    $hidden = $collapse ? $ancestors->slice(0, -1) : collect();
    $shown  = $collapse ? $ancestors->slice(-1) : $ancestors;
    $parent = $ancestors->last();
@endphp
<nav class="flex items-center gap-1.5 text-sm mb-3 min-w-0" aria-label="Breadcrumb">
    {{-- Naik satu level --}}
    <a href="{{ $parent ? route('documents.folder', $parent->id ?? $parent) : route('documents.index') }}"
       class="w-8 h-8 inline-flex items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-[#DA2D3B] flex-shrink-0 transition-colors"
       aria-label="Naik satu level" title="Naik satu level">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
    </a>

    <a href="{{ route('documents.index') }}" class="inline-flex items-center gap-1.5 text-slate-500 hover:text-[#DA2D3B] font-medium flex-shrink-0 transition-colors" title="Kembali ke daftar folder">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l9-9 9 9M5 10v10h5v-6h4v6h5V10"/></svg>
        Dokumen
    </a>

    @if($hidden->isNotEmpty())
        {!! $chev !!}
        <div x-data="{ open: false }" @keydown.escape.window="open = false" class="relative">
            <button type="button" @click="open = !open"
                    class="px-2 py-0.5 rounded-md bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold transition-colors cursor-pointer"
                    aria-haspopup="menu" :aria-expanded="open" aria-label="Tampilkan folder tersembunyi" title="Tampilkan folder tersembunyi">&hellip;</button>
            <div x-show="open" x-cloak @click.outside="open = false" role="menu"
                 class="absolute left-0 top-8 z-30 w-60 bg-white rounded-xl border border-slate-200 shadow-lg py-1.5">
                @foreach($hidden as $a)
                    <a role="menuitem" href="{{ route('documents.folder', $a->id ?? $a) }}"
                       class="flex items-center gap-2 px-3.5 py-2 text-sm text-slate-700 hover:bg-slate-50 transition-colors"
                       style="padding-left: {{ 0.875 + min($loop->index, 8) * 0.75 }}rem" title="{{ $a->name }}">
                        <svg class="w-4 h-4 text-[#DA2D3B] flex-shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/></svg>
                        <span class="truncate">{{ $a->name }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    @foreach($shown as $a)
        {!! $chev !!}
        <a href="{{ route('documents.folder', $a->id ?? $a) }}"
           class="text-slate-500 hover:text-[#DA2D3B] font-medium truncate max-w-[140px] sm:max-w-[220px] transition-colors"
           title="{{ $a->name }}">{{ $a->name }}</a>
    @endforeach

    {!! $chev !!}
    <span class="text-slate-800 font-semibold truncate max-w-[180px] sm:max-w-sm" aria-current="page" title="{{ $current }}">{{ $current }}</span>
</nav>
