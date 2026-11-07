<!-- SIDEBAR Component (Optimized with cached permissions) -->
<aside :class="{
           'translate-x-0 w-[260px] lg:w-[260px] lg:min-w-[260px]': sidebarOpen,
           '-translate-x-full lg:translate-x-0 lg:w-[72px] lg:min-w-[72px]': !sidebarOpen
       }"
       class="fixed inset-y-0 left-0 z-50 lg:static bg-white border-r border-slate-200 text-slate-700 flex flex-col flex-shrink-0 transition-all duration-300 ease-in-out h-full">


    <!-- Logo -->
    <div class="flex items-center justify-between px-5 py-5 border-b border-slate-200">
        <div x-show="sidebarOpen" class="flex items-center">
            <img src="{{ asset('assets/images/dadu-sidebar.svg') }}" alt="Logo" width="77" height="20" class="h-5 w-auto object-contain shrink-0">
        </div>
        <div x-show="!sidebarOpen" class="w-8 h-5 flex items-center justify-center mx-auto">
            <img src="{{ asset('assets/images/dadu-sidebar-minimize.svg') }}" alt="Logo" width="20" height="20" class="h-5 w-5 object-contain shrink-0">
        </div>
    </div>

    <!-- Menu -->
    <nav class="flex-1 py-4 space-y-0.5" :class="sidebarOpen ? 'overflow-y-auto px-3' : 'overflow-visible px-1.5'">

        @php
            // Entry point: deteksi role & siapkan badge service.
            // Definisi menu ada di config/sidebar.php (bukan di file ini).
            $userRole = auth()->user()->getRoleNames()->first() ?? 'user';
            $isMahasiswa = auth()->user()->hasRole('mahasiswa');
            $badgeService = app(\App\Services\SidebarBadgeService::class);
        @endphp

        @if($isMahasiswa)
            @include('components.sidebar-flat-menu', [
                'items' => config('sidebar.mahasiswa', []),
                'badgeService' => $badgeService,
            ])
        @else
            @include('components.sidebar-admin', [
                'groups' => config('sidebar.admin', []),
                'standalone' => config('sidebar.admin_standalone', []),
                'badgeService' => $badgeService,
            ])
        @endif

    </nav>

    <!-- User Info & Logout -->
    <div class="border-t border-slate-200 p-4">

        @php
            $hasMultipleRoles = isset($assignedRoles) && count($assignedRoles) > 1;
        @endphp

        {{-- Card Username & Role Switcher --}}
        <div x-data="{ roleMenuOpen: false }" @if($hasMultipleRoles) id="role-switcher" data-testid="role-switcher" @endif @click.outside="roleMenuOpen = false" class="relative">
            <div class="flex items-center gap-3">
                <div @if($hasMultipleRoles) @click="roleMenuOpen = !roleMenuOpen" title="Ganti Peran" @endif
                     class="w-9 h-9 rounded-full overflow-hidden bg-[#DA2D3B] flex items-center justify-center flex-shrink-0 ring-2 ring-slate-200 transition-all {{ $hasMultipleRoles ? 'cursor-pointer hover:ring-[#DA2D3B]' : '' }}">
                    @if(auth()->user()->photo)
                        <img src="{{ auth()->user()->photo }}" alt="{{ auth()->user()->name }}"
                             class="w-9 h-9 rounded-full object-cover"
                             referrerpolicy="no-referrer"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='inline';">
                        <span class="text-white text-xs font-bold" style="display:none;">
                            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                        </span>
                    @else
                        <span class="text-white text-xs font-bold">
                            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                        </span>
                    @endif
                </div>
                <div x-show="sidebarOpen" class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-slate-800 truncate">{{ auth()->user()->name }}</p>
                    <p class="text-[11px] text-slate-500 truncate">
                        {{ \App\Models\User::ROLE_LABEL_MAP[$activeRole] ?? ucfirst($activeRole ?? $userRole) }}
                    </p>
                </div>
                {{-- Tombol ubah role (hanya jika punya >1 role & sidebar terbuka) --}}
                @if($hasMultipleRoles)
                    <button type="button"
                            x-show="sidebarOpen"
                            @click="roleMenuOpen = !roleMenuOpen"
                            title="Ganti role"
                            class="flex-shrink-0 p-1.5 rounded-lg text-slate-400 hover:text-[#DA2D3B] hover:bg-[#DA2D3B]/10 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M8 7h12m0 0l-4-4m4 4l-4 4M16 17H4m0 0l4 4m-4-4l4-4"/>
                        </svg>
                    </button>
                @endif
            </div>

            {{-- Dropdown daftar role --}}
            @if($hasMultipleRoles)
                <div x-show="roleMenuOpen"
                     x-transition
                     style="display: none;"
                     :class="sidebarOpen ? 'bottom-full left-0 right-0 mb-2' : 'bottom-0 left-full ml-3 w-56'"
                     class="absolute bg-white border border-slate-200 rounded-xl shadow-xl z-50 py-1.5 overflow-hidden">
                    <p class="px-4 py-1.5 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Ganti role</p>
                    @foreach($assignedRoles as $r)
                        <form method="POST" action="{{ route('role.switch') }}">
                            @csrf
                            <input type="hidden" name="role" value="{{ $r }}">
                            <button type="submit"
                                    class="w-full flex items-center justify-between px-4 py-2 text-xs text-left hover:bg-slate-50 transition-colors {{ $r === $activeRole ? 'font-bold text-[#DA2D3B]' : 'text-slate-700' }}">
                                <span>{{ \App\Models\User::ROLE_LABEL_MAP[$r] ?? $r }}</span>
                                @if($r === $activeRole)
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                    </svg>
                                @endif
                            </button>
                        </form>
                    @endforeach
                </div>
            @endif
        </div>

        <form method="POST" action="{{ route('logout') }}" class="mt-3">
            @csrf
            <button type="submit"
                    class="w-full flex items-center gap-2 px-3 py-2 text-[13px] text-slate-400
                           hover:text-white hover:bg-slate-800 rounded-lg">
                <svg class="w-4 h-4 flex-shrink-0 shrink-0" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
                <span x-show="sidebarOpen">Logout</span>
            </button>
        </form>
    </div>

</aside>
