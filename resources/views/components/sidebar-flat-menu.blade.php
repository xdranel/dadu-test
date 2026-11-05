{{--
    Sidebar Flat Menu (role: mahasiswa)
    Merender item dari config('sidebar.mahasiswa') via @foreach.
    - Filter permission via auth()->user()->can()
    - Guard Route::has() (item dengan route null = placeholder href="#")
    Props: $items (array), $badgeService (App\Services\SidebarBadgeService)
--}}
@foreach($items as $item)
    @php
        $perm         = $item['permission'] ?? null;
        $routeName    = $item['route'] ?? null;
        $isPlaceholder = is_null($routeName);

        $skip = false;
        if ($perm && ! auth()->user()->can($perm)) {
            $skip = true;
        }
        if (! $isPlaceholder && ! \Illuminate\Support\Facades\Route::has($routeName)) {
            $skip = true;
        }

        $href     = $isPlaceholder ? '#' : route($routeName);
        $isActive = ! $isPlaceholder && ! empty($item['active']) && request()->routeIs($item['active']);
        $badge    = ! empty($item['badge_key']) ? $badgeService->count($item['badge_key']) : 0;
        $iconPaths = (array) ($item['icon'] ?? []);
    @endphp

    @continue($skip)

    <div class="relative group">
        <a href="{{ $href }}"
           class="w-full flex items-center transition-colors rounded-lg
                  {{ $isActive ? 'bg-[#DA2D3B]/10 text-[#DA2D3B] font-semibold' : 'text-slate-500 hover:bg-slate-50 hover:text-[#DA2D3B]' }}"
           :class="sidebarOpen ? 'px-3 py-2.5 text-sm font-medium gap-2' : 'justify-center py-3'">

            <svg class="flex-shrink-0 shrink-0 transition-colors {{ $isActive ? 'text-[#DA2D3B]' : 'text-slate-400 group-hover:text-[#DA2D3B]' }}"
                 :class="sidebarOpen ? 'w-[18px] h-[18px]' : 'w-5 h-5'"
                 width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                @foreach($iconPaths as $d)
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $d }}"/>
                @endforeach
            </svg>

            <span x-show="sidebarOpen" class="truncate flex-1">{{ $item['label'] }}</span>

            @if($badge > 0)
                <span x-show="sidebarOpen"
                      class="ml-auto text-[10px] font-bold bg-[#DA2D3B] text-white rounded-full px-1.5 py-0.5 leading-none">{{ $badge }}</span>
            @endif
        </a>

        {{-- Tooltip saat sidebar collapsed --}}
        <div x-show="!sidebarOpen"
             class="absolute left-full top-1/2 -translate-y-1/2 ml-2 hidden group-hover:block bg-slate-800 text-white text-xs px-2.5 py-1.5 rounded shadow-lg whitespace-nowrap z-50">
            {{ $item['label'] }}@if($badge > 0) ({{ $badge }})@endif
        </div>
    </div>
@endforeach
