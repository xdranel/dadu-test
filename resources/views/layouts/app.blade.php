<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'Dadu') }} {{ $title ?? '| Dashboard Akademik Terpadu' }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* Anti-FOUC: kunci dimensi ikon & logo sidebar sebelum bundle CSS termuat.
           Selector dipersempit agar chevron accordion (button > svg) tidak terkena. */
        aside nav a svg,
        aside nav button span svg { width: 1.25rem; height: 1.25rem; flex-shrink: 0; }
        aside img { height: 1.25rem; width: auto; flex-shrink: 0; }
    </style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="icon" href="{{ asset('assets/daduLogo.svg') }}" type="image/svg+xml">
</head>
<body class="bg-white font-sans antialiased">

<div class="flex h-screen overflow-hidden" 
     x-data="{ 
         sidebarOpen: window.innerWidth >= 1024,
         isDesktop: window.innerWidth >= 1024,
         handleResize() {
             const currentlyDesktop = window.innerWidth >= 1024;
             if (this.isDesktop !== currentlyDesktop) {
                 this.isDesktop = currentlyDesktop;
                 this.sidebarOpen = currentlyDesktop;
             }
         }
     }"
     @resize.window.debounce.150ms="handleResize()">

    <!-- Backdrop Overlay untuk Mobile (Menutup sidebar saat area luar diklik) -->
    <div x-show="sidebarOpen" 
         @click="sidebarOpen = false" 
         x-transition:enter="transition-opacity ease-linear duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-linear duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-40 lg:hidden"
         style="display: none;"></div>

    <!-- SIDEBAR (Optimized with cached permissions) -->
    @include('components.sidebar')

    <!-- MAIN CONTENT -->
    <div class="flex-1 min-w-0 flex flex-col h-full overflow-hidden">

        <!-- Topbar -->
        <header class="bg-white border-b border-slate-200/80 flex items-center justify-between px-4 sm:px-6 py-4 flex-shrink-0">
            <div class="flex items-center gap-4">
                <!-- Toggle Sidebar -->
                <button @click="sidebarOpen = !sidebarOpen"
                        class="text-slate-400 hover:text-slate-600 focus:outline-none p-1.5 rounded-lg hover:bg-slate-100 transition-colors"
                        aria-label="Toggle Sidebar">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>

                <!-- Page Title -->
                <h1 class="text-lg font-semibold text-slate-800">
                    {{ $header ?? 'Dashboard' }}
                </h1>
            </div>

        </header>

        <!-- Page Content -->
        <main class="flex-1 overflow-y-auto p-4 sm:p-6">
            {{ $slot }}
        </main>

    </div>
</div>

    {{-- Global Interactive Toast Notifications --}}
    <x-toast-notifications />

    {{-- Floating Chatbot Widget (Hidden for Mahasiswa role) --}}
    @if(auth()->check() && !auth()->user()->hasRole('mahasiswa'))
        @include('chatbot.index')
    @endif

</body>
</html>
