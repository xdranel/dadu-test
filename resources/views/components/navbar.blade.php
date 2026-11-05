<nav class="bg-white/95 border-b border-slate-100 sticky top-0 z-50 shadow-sm backdrop-blur-md">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
        {{-- Logo (Kiri) --}}
        <a href="{{ route('public_documents.index') }}" class="flex items-center gap-2 hover:opacity-90 transition-opacity flex-shrink-0" aria-label="Dokumen Publik">
            <img src="{{ asset('assets/Telu_logo.png') }}" alt="Logo Telkom University" class="h-10 w-auto object-contain">
        </a>

        {{-- Nav Links (Kanan) --}}
        <div class="flex items-center gap-1">
            {{-- Portal SSC: hover abu-abu --}}
            <a href="https://ssc.telu-sby.id/portal-informasi" target="_blank" rel="noopener noreferrer"
               class="px-3.5 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100 hover:text-slate-800 rounded-lg transition-colors duration-150">
                Portal SSC
            </a>

            @auth
                {{-- Dashboard: hover abu-abu (seragam) --}}
                <a href="{{ route('dashboard') }}"
                   class="px-3.5 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100 hover:text-slate-800 rounded-lg transition-colors duration-150">
                    Dashboard
                </a>

                {{-- Logout --}}
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center justify-center px-5 py-2 text-sm font-bold text-[#DA2D3B] bg-white hover:bg-red-50 border border-[#DA2D3B]/30 rounded-xl shadow-sm transition-all hover:shadow-md hover:scale-[1.02] active:scale-[0.98]">
                        Logout
                    </button>
                </form>
            @else
                <a href="{{ route('login') }}"
                   class="inline-flex items-center justify-center px-5 py-2 text-sm font-bold text-white bg-[#DA2D3B] hover:bg-[#b92532] rounded-xl shadow-sm transition-all hover:shadow-md hover:scale-[1.02] active:scale-[0.98]">
                    Masuk
                </a>
            @endauth
        </div>
    </div>
</nav>