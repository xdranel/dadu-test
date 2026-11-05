@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex items-center justify-between py-3">
        {{-- Mobile View --}}
        <div class="flex justify-between flex-1 sm:hidden gap-3">
            @if ($paginator->onFirstPage())
                <span class="relative inline-flex flex-1 justify-center items-center px-4 py-2.5 text-sm font-medium text-slate-400 bg-slate-50 border border-slate-200 cursor-not-allowed rounded-xl">
                    Sebelumnya
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="relative inline-flex flex-1 justify-center items-center px-4 py-2.5 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 hover:text-slate-900 transition-colors shadow-sm">
                    Sebelumnya
                </a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="relative inline-flex flex-1 justify-center items-center px-4 py-2.5 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 hover:text-slate-900 transition-colors shadow-sm">
                    Selanjutnya
                </a>
            @else
                <span class="relative inline-flex flex-1 justify-center items-center px-4 py-2.5 text-sm font-medium text-slate-400 bg-slate-50 border border-slate-200 cursor-not-allowed rounded-xl">
                    Selanjutnya
                </span>
            @endif
        </div>

        {{-- Desktop View --}}
        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
            <div>
                <p class="text-xs text-slate-500">
                    Menampilkan
                    <span class="font-bold text-slate-700">{{ $paginator->firstItem() ?? 0 }}</span>
                    -
                    <span class="font-bold text-slate-700">{{ $paginator->lastItem() ?? 0 }}</span>
                    dari
                    <span class="font-bold text-slate-700">{{ number_format($paginator->total()) }}</span>
                    data
                </p>
            </div>

            <div>
                <ul class="flex items-center gap-1.5">
                    {{-- Previous Page Link --}}
                    @if ($paginator->onFirstPage())
                        <li>
                            <span class="inline-flex items-center justify-center w-8 h-8 text-slate-400 bg-slate-50 border border-slate-100 rounded-lg cursor-not-allowed">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                            </span>
                        </li>
                    @else
                        <li>
                            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex items-center justify-center w-8 h-8 text-slate-500 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 hover:text-[#DA2D3B] hover:border-slate-300 transition-all shadow-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                            </a>
                        </li>
                    @endif

                    {{-- Pagination Elements --}}
                    @foreach ($elements as $element)
                        {{-- "Three Dots" Separator --}}
                        @if (is_string($element))
                            <li>
                                <span class="inline-flex items-center justify-center w-8 h-8 text-slate-400 bg-transparent text-sm font-medium">
                                    {{ $element }}
                                </span>
                            </li>
                        @endif

                        {{-- Array Of Links --}}
                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                @if ($page == $paginator->currentPage())
                                    <li>
                                        <span class="inline-flex items-center justify-center w-8 h-8 text-sm font-bold text-white bg-[#DA2D3B] rounded-lg shadow-sm shadow-red-600/20 cursor-default">
                                            {{ $page }}
                                        </span>
                                    </li>
                                @else
                                    <li>
                                        <a href="{{ $url }}" class="inline-flex items-center justify-center w-8 h-8 text-sm font-semibold text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 hover:text-[#DA2D3B] hover:border-[#DA2D3B]/30 transition-all shadow-sm">
                                            {{ $page }}
                                        </a>
                                    </li>
                                @endif
                            @endforeach
                        @endif
                    @endforeach

                    {{-- Next Page Link --}}
                    @if ($paginator->hasMorePages())
                        <li>
                            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex items-center justify-center w-8 h-8 text-slate-500 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 hover:text-[#DA2D3B] hover:border-slate-300 transition-all shadow-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                            </a>
                        </li>
                    @else
                        <li>
                            <span class="inline-flex items-center justify-center w-8 h-8 text-slate-400 bg-slate-50 border border-slate-100 rounded-lg cursor-not-allowed">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                            </span>
                        </li>
                    @endif
                </ul>
            </div>
        </div>
    </nav>
@endif
