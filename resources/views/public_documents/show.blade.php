<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $document->title }} - Direktori Dokumen Publik</title>
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
    <main class="flex-grow max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 w-full">
        
        <!-- Back Link -->
        <div class="mb-6">
            <a href="javascript:history.back()"
               class="inline-flex items-center gap-2 text-sm font-bold text-slate-600 hover:text-[#DA2D3B] transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                <span>Kembali</span>
            </a>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm p-6 sm:p-8">

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 pb-6 border-b border-slate-100">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-bold text-slate-900 tracking-tight leading-tight">
                        {{ $document->title }}
                    </h1>
                    <div class="mt-2 flex">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-650 border border-slate-200/50">
                            {{ $document->category->name }}
                        </span>
                    </div>
                </div>
                <a href="{{ route('public_documents.download', $document) }}"
                   class="inline-flex items-center justify-center gap-2 px-6 h-12 text-sm font-bold text-white bg-[#DA2D3B] hover:bg-[#b92532] rounded-xl shadow-sm transition-all active:scale-[0.98] flex-shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                    </svg>
                    <span>Unduh Dokumen</span>
                </a>
            </div>

            @if($document->description)
                <div class="mb-8">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Deskripsi</h3>
                    <p class="text-sm text-slate-600 leading-relaxed font-medium">
                        {{ $document->description }}
                    </p>
                </div>
            @endif

            <!-- Info Metrics Grid -->
            <div class="grid grid-cols-3 gap-4 mb-8">
                <div class="bg-slate-50/80 rounded-xl p-4 text-center border border-slate-100">
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Tipe</p>
                    <p class="font-bold text-slate-800 uppercase text-sm mt-1">{{ $document->file_type }}</p>
                </div>
                <div class="bg-slate-50/80 rounded-xl p-4 text-center border border-slate-100">
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Ukuran</p>
                    <p class="font-bold text-slate-800 text-sm mt-1">{{ $document->file_size_formatted }}</p>
                </div>
                <div class="bg-slate-50/80 rounded-xl p-4 text-center border border-slate-100">
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Tanggal Unggah</p>
                    <p class="font-bold text-slate-800 text-sm mt-1">{{ $document->created_at->translatedFormat('d M Y') }}</p>
                </div>
            </div>

            <!-- PDF Preview (if applicable) -->
            @if($document->file_type === 'pdf')
                <div class="mb-2">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3.5">Pratinjau Dokumen</h3>
                    <div class="border border-slate-200/80 rounded-2xl overflow-hidden bg-slate-100 shadow-inner">
                        <iframe src="/storage/{{ $document->file_path }}"
                                class="w-full"
                                style="height: 600px; border: none;"></iframe>
                    </div>
                </div>
            @endif
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-slate-100 py-10 w-full mt-16">
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

</body>
</html>