<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Direktori Dokumen Publik - Telkom University Surabaya</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Modern Premium Typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        h1, h2, h3, h4, h5, h6, .font-heading {
            font-family: 'Outfit', sans-serif;
        }
    </style>
</head>
<body class="bg-slate-50/70 text-slate-800 min-h-screen flex flex-col justify-between antialiased">

    <x-navbar />

    <!-- Main Content Wrapper -->
    <main class="flex-grow max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full">

        @if(!request('kategori') && !request('search') && !request('year'))
            <!-- Hero Section (Centered) — kompak, max py-10 -->
            <div class="text-center mb-6 max-w-3xl mx-auto pt-4 pb-2">
                <h1 class="text-4xl sm:text-5xl font-bold text-slate-900 tracking-tight leading-none">
                    Direktori Dokumen
                </h1>
                <p class="text-slate-500 mt-3 text-base sm:text-lg font-medium leading-relaxed">
                    Temukan template, surat edaran, dan dokumen resmi lainnya.
                </p>
            </div>

            <!-- Search Component (Compact Search Bar) -->
            <form action="{{ route('public_documents.index') }}" method="GET" class="mb-5 max-w-2xl mx-auto relative">
                <div class="flex flex-col sm:flex-row items-stretch gap-2 bg-white p-1.5 rounded-xl shadow-md border border-slate-200/60 transition-all focus-within:border-[#DA2D3B]/40 focus-within:ring-1 focus-within:ring-[#DA2D3B]/5">
                    <!-- Search Input -->
                    <div class="flex-1 flex items-center relative min-w-0">
                        <span class="absolute left-3 text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </span>
                        <input type="text" name="search" value="{{ request('search') }}"
                               placeholder="Cari dokumen, contoh: template TA, surat edaran..."
                               class="w-full bg-transparent border-0 focus:ring-0 text-slate-800 placeholder-slate-400 py-1.5 pl-9 pr-3 text-xs font-medium">
                    </div>

                    <!-- Action group: Filter Toggle & Cari Button -->
                    <div class="flex items-center gap-1.5 flex-shrink-0">
                        <!-- Filter dropdown button -->
                        <button type="button" id="filter-toggle-btn"
                                class="h-8 px-3.5 bg-slate-50 hover:bg-slate-100 text-slate-700 rounded-lg text-xs font-semibold flex items-center gap-1.5 border border-slate-200/80 transition-all duration-150 active:scale-[0.98]">
                            <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                            </svg>
                            <span>Filter</span>
                        </button>
                        <!-- Cari button -->
                        <button type="submit"
                                class="h-8 px-5 bg-[#DA2D3B] hover:bg-[#b92532] text-white rounded-lg text-xs font-bold transition-all shadow-sm active:scale-[0.99]">
                            Cari
                        </button>
                    </div>
                </div>

                <!-- Collapsible Dropdown Filter Panel (Kategori saja) -->
                <div id="filter-panel" class="{{ request('kategori') ? '' : 'hidden' }} absolute left-0 right-0 mt-3 bg-white p-6 rounded-2xl shadow-xl border border-slate-200 grid grid-cols-1 gap-5 z-40">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1.5 uppercase tracking-wider">Kategori Dokumen</label>
                        <select name="kategori"
                                class="w-full bg-slate-50 border border-slate-200/60 text-slate-700 rounded-xl py-2.5 px-3.5 text-sm cursor-pointer transition-all duration-150">
                            <option value="">Semua Kategori</option>
                            @foreach ($kategoriList as $kat)
                                <option value="{{ $kat->id }}" {{ request('kategori') == $kat->id ? 'selected' : '' }}>
                                    {{ $kat->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </form>
        @endif

        <!-- Dynamic Content Area -->
        @if(request('kategori') || request('search') || request('year'))
            <!-- Results View Header: Minimalist and Modern -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8 pb-4 border-b border-slate-200/50">
                <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                    <a href="{{ route('public_documents.index') }}"
                       class="inline-flex items-center justify-center px-4 h-10 rounded-xl bg-white border border-slate-200 text-slate-600 hover:text-[#DA2D3B] hover:border-[#DA2D3B]/20 transition-all shadow-sm hover:shadow-md font-bold text-xs gap-1.5"
                       title="Kembali ke Kategori">
                        <span>Kembali</span>
                    </a>
                    <div class="flex items-center gap-2">
                        <!-- Red Folder Icon -->
                        <svg class="w-5 h-5 text-[#DA2D3B]" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
                        </svg>
                        <h2 class="text-xl font-bold text-slate-900 tracking-tight flex items-center gap-1.5">
                            @if(request('kategori'))
                                @php $selectedKat = $kategoriList->firstWhere('id', request('kategori')); @endphp
                                <span>Folder: {{ $selectedKat ? $selectedKat->name : 'Kategori' }}</span>
                            @else
                                <span>Hasil Pencarian</span>
                            @endif
                        </h2>
                        @if(request('search'))
                            <span class="text-slate-300">|</span>
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">
                                Kata Kunci: <span class="text-[#DA2D3B] font-bold">"{{ request('search') }}"</span>
                            </span>
                        @endif
                        @if(request('year'))
                            <span class="text-slate-300">|</span>
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">
                                Tahun: <span class="text-[#DA2D3B] font-bold">{{ request('year') }}</span>
                            </span>
                        @endif
                    </div>
                </div>

                <!-- Small simple search bar on the right -->
                <form action="{{ route('public_documents.index') }}" method="GET" class="relative w-full sm:w-72">
                    @if(request('kategori'))
                        <input type="hidden" name="kategori" value="{{ request('kategori') }}">
                    @endif
                    @if(request('year'))
                        <input type="hidden" name="year" value="{{ request('year') }}">
                    @endif
                    <div class="relative flex items-center bg-white border border-slate-200 rounded-xl px-3.5 py-2 shadow-sm focus-within:border-[#DA2D3B]/40 focus-within:ring-2 focus-within:ring-[#DA2D3B]/5 transition-all">
                        <span class="text-slate-400 mr-2.5 flex-shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </span>
                        <input type="text" name="search" value="{{ request('search') }}"
                               placeholder="Cari di folder ini..."
                               class="w-full bg-transparent border-0 focus:ring-0 text-slate-800 placeholder-slate-400 p-0 text-xs font-semibold leading-none">
                        @if(request('search'))
                            <a href="{{ route('public_documents.index', array_filter(['kategori' => request('kategori'), 'year' => request('year')])) }}" class="text-slate-400 hover:text-slate-650 ml-1.5 flex-shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            <!-- Documents Grid -->
            @if($documents->count() > 0)
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                    @foreach ($documents as $dok)
                        <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm hover:shadow-md hover:border-[#DA2D3B]/40 transition-all duration-200 flex flex-col justify-between overflow-hidden relative group">
                            <div class="p-6">
                                <div class="flex items-start justify-between mb-3.5">
                                    <span class="px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider rounded-lg {{ $dok->file_type === 'pdf' ? 'bg-red-50 text-[#DA2D3B] ring-1 ring-red-500/10' : 'bg-blue-50 text-blue-700 ring-1 ring-blue-500/10' }}">
                                        {{ strtoupper($dok->file_type) }}
                                    </span>
                                    <span class="text-xs text-slate-400 font-semibold">{{ $dok->file_size_formatted }}</span>
                                </div>
                                <h3 class="font-bold text-slate-800 text-base mb-2 leading-snug group-hover:text-[#DA2D3B] transition-colors duration-200">
                                    <a href="{{ route('public_documents.show', $dok) }}"
                                       class="after:absolute after:inset-0">
                                        {{ $dok->title }}
                                    </a>
                                </h3>
                                @if($dok->description)
                                    <p class="text-xs text-slate-500 mt-2 leading-relaxed line-clamp-3">
                                        {{ $dok->description }}
                                    </p>
                                @endif
                            </div>
                            <div class="px-6 py-4 bg-slate-50/50 border-t border-slate-100/50 flex items-center justify-between">
                                <span class="text-xs text-slate-450 font-medium">Diunggah: {{ $dok->created_at->translatedFormat('d M Y') }}</span>
                                <a href="{{ route('public_documents.download', $dok) }}"
                                   class="inline-flex items-center gap-1.5 h-8 px-4 text-xs font-bold text-white bg-[#DA2D3B] hover:bg-[#b92532] rounded-lg shadow-sm transition-colors relative z-10">
                                    Unduh
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
                {{ $documents->withQueryString()->links() }}
            @else
                <div class="text-center py-16 text-slate-400 bg-white rounded-2xl border border-slate-200/60 shadow-sm flex flex-col items-center justify-center">
                    <div class="p-4 bg-slate-50 text-slate-400 rounded-full mb-4">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <p class="text-lg font-bold text-slate-700">Dokumen tidak ditemukan</p>
                    <p class="text-sm mt-1 text-slate-400">Coba kata kunci atau filter lainnya.</p>
                </div>
            @endif
        @else
            <!-- Directory Home View -->

            <!-- Main Directory Grid (Kategori Dokumen) — langsung setelah search bar -->
            <div class="max-w-5xl mx-auto mt-8">
                <h2 class="text-xl font-bold text-slate-900 mb-5 tracking-tight flex items-center gap-2">
                    Kategori Dokumen
                </h2>
                @if($kategoriList->count() > 0)
                    <div class="grid grid-cols-[repeat(auto-fill,minmax(130px,1fr))] gap-3">
                        @foreach ($kategoriList as $kat)
                            <x-folder-card
                                :category="$kat"
                                mode="public"
                                :href="route('public_documents.index', ['kategori' => $kat->id])" />
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-16 text-slate-400 bg-white rounded-2xl border border-slate-200/60 shadow-sm flex flex-col items-center justify-center">
                        <div class="p-4 bg-slate-50 text-slate-400 rounded-full mb-4">
                            <svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                        </div>
                        <p class="text-lg font-bold text-slate-700">Belum ada kategori dokumen</p>
                        <p class="text-sm mt-1 text-slate-400">Dokumen akan tampil di sini setelah tersedia.</p>
                    </div>
                @endif
            </div>

            <!-- Dokumen Terbaru Section -->
            @if($documents->count() > 0)
                <div class="max-w-5xl mx-auto mt-10">
                    <h2 class="text-xl font-bold text-slate-900 mb-5 tracking-tight flex items-center gap-2">
                        Dokumen Terbaru
                    </h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                        @foreach ($documents as $dok)
                            <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm hover:shadow-md hover:border-[#DA2D3B]/40 transition-all duration-200 flex flex-col justify-between overflow-hidden relative group">
                                <div class="p-5">
                                    <div class="flex items-start justify-between mb-3">
                                        <span class="px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider rounded-lg bg-blue-50 text-blue-700 ring-1 ring-blue-500/10">
                                            {{ strtoupper($dok->file_type) }}
                                        </span>
                                        <span class="text-xs text-slate-400 font-semibold">{{ $dok->file_size_formatted }}</span>
                                    </div>
                                    <h3 class="font-bold text-slate-800 text-base mb-1.5 leading-snug group-hover:text-[#DA2D3B] transition-colors duration-200">
                                        <a href="{{ route('public_documents.show', $dok) }}" class="after:absolute after:inset-0">
                                            {{ $dok->title }}
                                        </a>
                                    </h3>
                                    @if($dok->description)
                                        <p class="text-xs text-slate-500 mt-1 leading-relaxed line-clamp-2">
                                            {{ $dok->description }}
                                        </p>
                                    @endif
                                </div>
                                <div class="px-5 py-3.5 bg-slate-50/50 border-t border-slate-100/50 flex items-center justify-between">
                                    <span class="text-xs text-slate-400 font-medium">Diunggah: {{ $dok->created_at->translatedFormat('d M Y') }}</span>
                                    <a href="{{ route('public_documents.download', $dok) }}"
                                       class="inline-flex items-center gap-1.5 h-8 px-3.5 text-xs font-bold text-white bg-[#DA2D3B] hover:bg-[#b92532] rounded-lg shadow-sm transition-colors relative z-10">
                                        Unduh
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        @endif

    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-slate-100 py-10 w-full mt-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col items-center justify-center text-center gap-6">
                <!-- Logo & Alamat -->
                <div class="flex flex-col items-center gap-3">
                    <img src="{{ asset('assets/Telu_logo.png') }}" alt="Logo" class="h-12 w-auto object-contain">
                    <div>
                        <h4 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Kontak Kami</h4>
                        <p class="text-xs text-slate-500 mt-1.5 leading-relaxed font-medium">
                            Lantai 2, Gedung Utama<br>
                            Jl. Ketintang No. 156, Surabaya
                        </p>
                    </div>
                </div>

                <!-- Instagram & Credit -->
                <div class="flex flex-col items-center gap-3">
                    <a href="https://www.instagram.com/akademik.telkomsby/" target="_blank" rel="noopener noreferrer"
                       class="text-slate-400 hover:text-slate-600 transition-colors"
                       title="Instagram @akademik.telkomsby">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.051.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/>
                        </svg>
                    </a>
                    <span class="text-xs text-slate-400 font-semibold">
                        © 2026 - Dashboard Akademik Terpadu Telkom University Surabaya 1.0
                    </span>
                </div>
            </div>
        </div>
    </footer>

    <!-- Interactive script to toggle filter panel -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const btn = document.getElementById('filter-toggle-btn');
            const panel = document.getElementById('filter-panel');
            if (btn && panel) {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    panel.classList.toggle('hidden');
                });
                // Close filter panel when clicking outside
                document.addEventListener('click', function(e) {
                    if (!panel.classList.contains('hidden') && !panel.contains(e.target) && e.target !== btn && !btn.contains(e.target)) {
                        panel.classList.add('hidden');
                    }
                });
            }
        });
    </script>

</body>
</html>