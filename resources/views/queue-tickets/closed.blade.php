<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Layanan Tutup - {{ config('app.name', 'Dadu') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .custom-glow {
            box-shadow: 0 20px 40px -15px rgba(218, 45, 59, 0.08), 0 0 0 1px rgba(218, 45, 59, 0.05);
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 font-sans min-h-screen flex items-center justify-center p-4 relative overflow-x-hidden selection:bg-[#DA2D3B]/20 selection:text-[#DA2D3B]">
    <!-- Decorative background elements -->
    <div class="absolute top-[-20%] left-[-10%] w-[300px] md:w-[500px] h-[300px] md:h-[500px] rounded-full bg-[#DA2D3B]/5 blur-[120px] pointer-events-none"></div>
    <div class="absolute bottom-[-20%] right-[-10%] w-[300px] md:w-[500px] h-[300px] md:h-[500px] rounded-full bg-slate-200/40 blur-[120px] pointer-events-none"></div>

    <div class="w-full max-w-md z-10 my-6 transition-all duration-300">
        <!-- Logo and Title -->
        <div class="text-center mb-6 flex flex-col items-center">
            <img src="{{ asset('assets/Telu_logo.png') }}" alt="Logo" class="w-14 h-14 md:w-16 md:h-16 object-contain mb-3 drop-shadow-md">
            <h1 class="text-xl md:text-2xl font-black tracking-tight text-slate-900 uppercase">Student Service Center</h1>
            <p class="text-[10px] text-slate-400 mt-1 uppercase tracking-widest font-bold">Telkom University Surabaya</p>
        </div>

        <!-- Main Card -->
        <div class="bg-white border border-slate-200 rounded-3xl p-6 md:p-8 text-center transition-all duration-300 custom-glow">

            <!-- Icon / Illustration -->
            <div class="w-16 h-16 rounded-2xl bg-slate-100 flex items-center justify-center mx-auto mb-4 text-slate-400">
                <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>

            <h2 class="text-xl font-bold text-slate-900 mb-2">Pengambilan Tiket Ditutup</h2>
            <p class="text-xs text-slate-500 leading-relaxed mb-6">
                Saat ini loket layanan tatap muka sedang berada di luar jam operasional atau dalam sesi istirahat.
            </p>

            <!-- Next Opening Highlight -->
            @if(!empty($nextOpening))
                <div class="bg-slate-50 border border-slate-200/70 rounded-2xl p-4 mb-6 text-center">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest block mb-1">
                        Buka Kembali Pada
                    </span>
                    <span class="text-base md:text-lg font-black text-[#DA2D3B] font-mono tracking-tight block">
                        {{ $nextOpening }}
                    </span>
                    <span class="text-[10px] text-slate-400 mt-1 block">
                        Tiket dibuka 5 menit sebelum sesi layanan dimulai.
                    </span>
                </div>
            @endif

            <!-- Jadwal Operasional Accordion / Info Box -->
            <div class="text-left bg-slate-50/70 rounded-2xl p-4 border border-slate-100 mb-6">
                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-2.5">
                    Jadwal Layanan Tatap Muka
                </span>
                <div class="space-y-2 text-xs text-slate-600">
                    <div class="flex justify-between items-center py-1 border-b border-slate-200/50">
                        <span class="font-medium text-slate-700">Senin – Kamis</span>
                        <span class="font-mono text-[11px] text-slate-600">09:00 - 12:00 & 13:00 - 15:00</span>
                    </div>
                    <div class="flex justify-between items-center py-1 border-b border-slate-200/50">
                        <span class="font-medium text-slate-700">Jumat</span>
                        <span class="font-mono text-[11px] text-slate-600">09:00 - 11:30 & 13:00 - 15:00</span>
                    </div>
                    <div class="flex justify-between items-center py-1">
                        <span class="font-medium text-slate-700">Sabtu & Minggu</span>
                        <span class="text-[11px] text-red-500 font-semibold">Tutup</span>
                    </div>
                </div>
            </div>

            <!-- Refresh Button -->
            <a href="{{ route('queue.take') }}"
               class="inline-flex w-full justify-center items-center gap-2 px-5 py-3.5 rounded-xl bg-slate-100 hover:bg-[#DA2D3B] hover:text-white text-slate-700 font-semibold text-xs md:text-sm transition-all duration-300 border border-slate-200 hover:border-[#DA2D3B] active:scale-[0.98]">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                Periksa Ketersediaan
            </a>
        </div>
    </div>
</body>
</html>
