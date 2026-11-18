@php
    $flashTypes = [
        'success' => [
            'box' => 'bg-emerald-50 border-emerald-200 text-emerald-800',
            'btn' => 'text-emerald-500 hover:text-emerald-800',
        ],
        'warning' => [
            'box' => 'bg-amber-50 border-amber-200 text-amber-800',
            'btn' => 'text-amber-500 hover:text-amber-800',
        ],
        'error' => [
            'box' => 'bg-rose-50 border-rose-200 text-rose-800',
            'btn' => 'text-rose-500 hover:text-rose-800',
        ],
    ];
@endphp

{{-- Toast Undo Melayang (Muncul setelah aksi hapus dengan opsi restore) --}}
@if (session('undo'))
    <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 8000)" x-show="show" x-transition
         class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 flex items-center gap-4 bg-slate-900 text-white text-sm rounded-xl shadow-xl px-4 py-3 border border-slate-700/50">
        <span>{{ session('success') }}</span>
        <form method="POST" action="{{ session('undo')['url'] }}" class="inline">
            @csrf
            @if(isset(session('undo')['payload']))
                @foreach(session('undo')['payload'] as $pk => $pv)
                    @if(is_array($pv))
                        @foreach($pv as $val)
                            <input type="hidden" name="{{ $pk }}[]" value="{{ $val }}">
                        @endforeach
                    @else
                        <input type="hidden" name="{{ $pk }}" value="{{ $pv }}">
                    @endif
                @endforeach
            @endif
            <button type="submit" class="font-semibold text-[#ff7a85] hover:text-white transition-colors cursor-pointer">
                {{ session('undo')['label'] ?? 'Urungkan' }}
            </button>
        </form>
    </div>
@endif

@foreach ($flashTypes as $key => $style)
    @if (session($key))
        {{-- Jangan render alert success biasa dua kali saat toast undo sedang aktif --}}
        @if ($key === 'success' && session('undo'))
            @continue
        @endif
        <div x-data="{ show: true }" x-show="show" class="mb-4 p-3.5 rounded-2xl border {{ $style['box'] }} text-sm flex items-start justify-between gap-3 shadow-sm">
            <div class="whitespace-pre-line">{{ session($key) }}</div>
            <button type="button" @click="show = false" class="{{ $style['btn'] }} text-lg leading-none transition-colors" aria-label="Tutup" title="Tutup">&times;</button>
        </div>
    @endif
@endforeach
