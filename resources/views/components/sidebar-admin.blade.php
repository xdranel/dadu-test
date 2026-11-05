{{--
    Sidebar Admin Accordion (role: admin / pegawai / dosen)
    - Item mandiri (config('sidebar.admin_standalone')) dirender di atas grup.
    - Grup accordion dari config('sidebar.admin') via nested @foreach.
    - Filter item per permission / roles + guard Route::has() SEBELUM render.
    - Grup tanpa item visible tidak ditampilkan (tidak ada header kosong).
    - Accordion single-open (openGroup): default tertutup, kecuali grup yang
      memuat route aktif otomatis terbuka saat load.
    Props: $groups (array), $standalone (array), $badgeService
--}}
@php
    $user = auth()->user();

    $filterItems = function (array $items) use ($user) {
        return array_values(array_filter($items, function ($item) use ($user) {
            $perm = $item['permission'] ?? null;
            $roles = $item['roles'] ?? null;
            $route = $item['route'] ?? null;
            if ($route && ! \Illuminate\Support\Facades\Route::has($route)) return false;
            if (array_key_exists('permissions_any', $item)) {
                $any = $item['permissions_any'];
                return is_array($any) && $any !== [] && $user->canAny($any);
            }
            if ($perm && $user->can($perm)) return true;
            if ($roles && $user->hasAnyRole($roles)) return true;
            return is_null($perm) && is_null($roles);
        }));
    };

    // Item mandiri (mis. Beranda)
    $standaloneItems = $filterItems($standalone ?? []);

    // Grup accordion yang punya minimal 1 item visible
    $visibleGroups = [];
    foreach ($groups as $key => $group) {
        $items = $filterItems($group['items'] ?? []);
        if (count($items) > 0) {
            $group['items'] = $items;
            $visibleGroups[$key] = $group;
        }
    }

    // Deteksi grup yang memuat route aktif -> auto-open saat load
    $activeGroupKey = null;
    foreach ($visibleGroups as $k => $g) {
        foreach ($g['items'] as $it) {
            if (request()->routeIs(...(array) ($it['active'] ?? $it['route']))) {
                $activeGroupKey = $k;
                break 2;
            }
        }
    }
@endphp

{{-- Standalone items --}}
@foreach($standaloneItems as $item)
    @php
        $isActive = request()->routeIs(...(array) ($item['active'] ?? $item['route']));
        $badge = ! empty($item['badge_key']) ? $badgeService->count($item['badge_key']) : 0;
        $iconPaths = (array) ($item['icon'] ?? []);
    @endphp
    <div class="relative group">
        <a href="{{ route($item['route']) }}"
           @if($isActive) aria-current="page" @endif
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
        <div x-show="!sidebarOpen"
             class="absolute left-full top-1/2 -translate-y-1/2 ml-2 hidden group-hover:block bg-slate-800 text-white text-xs px-2.5 py-1.5 rounded shadow-lg whitespace-nowrap z-50">
            {{ $item['label'] }}@if($badge > 0) ({{ $badge }})@endif
        </div>
    </div>
@endforeach

{{-- Accordion groups (single-open) --}}
<div x-data="{ openGroup: @js($activeGroupKey) }" class="space-y-0.5">
    @foreach($visibleGroups as $key => $group)
        @php $groupIcons = (array) ($group['icon'] ?? []); @endphp

        {{-- Group Header --}}
        <div class="pb-1 {{ ($loop->first && count($standaloneItems) === 0) ? '' : 'pt-4' }}">
            <button type="button"
                    :aria-expanded="(openGroup === '{{ $key }}' && sidebarOpen).toString()"
                    @click="if (!sidebarOpen) { sidebarOpen = true; openGroup = '{{ $key }}'; } else { openGroup = (openGroup === '{{ $key }}' ? null : '{{ $key }}'); }"
                    class="w-full flex items-center focus:outline-none hover:text-[#DA2D3B] hover:bg-slate-50 rounded-lg transition-colors"
                    :class="sidebarOpen ? 'justify-between px-3 py-2.5' : 'justify-center py-3'">
                <span class="flex items-center" :class="sidebarOpen ? 'gap-2' : ''">
                    <svg class="flex-shrink-0 shrink-0 text-slate-400 transition-colors" :class="sidebarOpen ? 'w-[18px] h-[18px]' : 'w-5 h-5'" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        @foreach($groupIcons as $d)
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $d }}"/>
                        @endforeach
                    </svg>
                    <span x-show="sidebarOpen" class="text-sm font-medium text-slate-700">{{ $group['label'] }}</span>
                </span>
                {{-- Chevron: menghadap kanan saat tertutup, rotasi ke bawah saat grup terbuka --}}
                <svg x-show="sidebarOpen" class="w-3 h-3 shrink-0 text-slate-400 transition-transform duration-200" :class="openGroup === '{{ $key }}' ? 'rotate-90' : ''" width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                </svg>
            </button>
        </div>

        {{-- Group Items --}}
        <div x-show="sidebarOpen && openGroup === '{{ $key }}'" x-transition class="space-y-0.5">
            @foreach($group['items'] as $item)
                @php
                    $isActive = request()->routeIs(...(array) ($item['active'] ?? $item['route']));
                    $badge = ! empty($item['badge_key']) ? $badgeService->count($item['badge_key']) : 0;
                @endphp
                <a href="{{ route($item['route']) }}"
                   @if($isActive) aria-current="page" @endif
                   class="flex items-center pl-8 pr-3 py-2.5 rounded-lg text-sm font-medium
                          {{ $isActive ? 'bg-[#DA2D3B]/10 text-[#DA2D3B] font-semibold' : 'text-slate-500 hover:bg-slate-50 hover:text-[#DA2D3B]' }}">
                    <span x-show="sidebarOpen" class="truncate flex-1">{{ $item['label'] }}</span>
                    @if($badge > 0)
                        <span x-show="sidebarOpen"
                              class="ml-auto text-[10px] font-bold bg-[#DA2D3B] text-white rounded-full px-1.5 py-0.5 leading-none">{{ $badge }}</span>
                    @endif
                </a>
            @endforeach
        </div>
    @endforeach
</div>
