@if($items !== [])
    <div class="grid gap-4 sm:grid-cols-2">
        @foreach($items as $item)
            <article data-automatic-card="{{ $item['key'] }}" data-read-only
                     class="min-w-0 rounded-xl border border-slate-200 bg-slate-100 p-4 text-slate-700">
                <div class="flex items-start justify-between gap-3">
                    <h3 class="text-sm font-semibold">{{ $item['title'] }}</h3>
                    <span class="shrink-0 rounded-full bg-white px-2 py-1 text-[10px] font-medium text-slate-600">Tanpa unggahan</span>
                </div>
                <p class="mt-3 break-words text-sm font-bold">{{ $item['value'] }}</p>
                <p class="mt-1.5 text-xs leading-relaxed text-slate-600">{{ $item['hint'] }}</p>
            </article>
        @endforeach
    </div>
@endif
