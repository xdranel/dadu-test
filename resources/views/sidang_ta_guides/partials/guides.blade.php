@forelse(($guides[$slot] ?? collect()) as $guide)
    <aside class="mt-3 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm space-y-2" data-form-guide="{{ $slot }}">
        <p class="font-semibold text-slate-800">{{ $guide->title }}</p>
        @if($guide->description)<p class="whitespace-pre-line text-slate-600">{{ $guide->description }}</p>@endif
        @if($guide->file_path)
            <a href="{{ route('sidang-ta-guides.media', $guide) }}" target="_blank" rel="noopener" class="block text-red-700 underline"
               @if($preview ?? false)
                   data-document-preview data-preview-url="{{ route('sidang-ta-guides.media', $guide) }}"
                   data-download-url="" data-file-name="{{ $guide->title }}"
                   data-file-type="{{ $guide->mime_type === 'application/pdf' ? 'pdf' : 'png' }}"
                   aria-haspopup="dialog" x-on:click.prevent="$dispatch('sidang-ta-preview', $el.dataset)"
               @endif>
                @if(in_array($guide->mime_type, ['image/png', 'image/jpeg'], true))
                    <img src="{{ route('sidang-ta-guides.media', $guide) }}" alt="{{ $guide->title }}" loading="lazy" class="max-h-64 max-w-full rounded border border-slate-200">
                @else Lihat contoh PDF @endif
            </a>
        @endif
        @if($guide->url)<a href="{{ $guide->url }}" target="_blank" rel="noopener noreferrer" class="block text-red-700 underline">{{ $guide->link_label ?: 'Buka panduan' }}</a>@endif
    </aside>
@empty
    <p class="mt-3 text-sm text-slate-500">{{ 'Contoh belum diatur.' }}</p>
@endforelse
