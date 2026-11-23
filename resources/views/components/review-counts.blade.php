@props(['labels', 'counts', 'countAttribute'])

<div {{ $attributes->class('campus-card flex flex-wrap divide-x divide-slate-100 overflow-hidden') }}>
    @foreach($labels as $key => $label)
        <div class="flex min-w-[140px] flex-1 items-center gap-3 border-l-4 border-slate-400 px-5 py-4">
            <span {{ $countAttribute }}="{{ $key }}"
                  class="text-2xl font-bold {{ $key === 'total' ? 'text-[#DA2D3B]' : 'text-slate-800' }}">{{ $counts[$key] }}</span>
            <span class="text-xs font-medium text-slate-500">{{ $label }}</span>
        </div>
    @endforeach
</div>
