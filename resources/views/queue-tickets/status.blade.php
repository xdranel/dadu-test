<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status Antrian - {{ config('app.name', 'Dadu') }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .custom-glow {
            box-shadow: 0 20px 40px -15px rgba(218, 45, 59, 0.08), 0 0 0 1px rgba(218, 45, 59, 0.05);
        }
        .custom-glow-emerald {
            box-shadow: 0 20px 40px -15px rgba(16, 185, 129, 0.08), 0 0 0 1px rgba(16, 185, 129, 0.05);
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 font-sans min-h-screen flex items-center justify-center p-4 relative overflow-x-hidden selection:bg-[#DA2D3B]/20 selection:text-[#DA2D3B]">
    <!-- Decorative background elements -->
    <div class="absolute top-[-20%] left-[-10%] w-[300px] md:w-[500px] h-[300px] md:h-[500px] rounded-full bg-[#DA2D3B]/5 blur-[120px] pointer-events-none"></div>
    <div class="absolute bottom-[-20%] right-[-10%] w-[300px] md:w-[500px] h-[300px] md:h-[500px] rounded-full bg-slate-200/40 blur-[120px] pointer-events-none"></div>

    <div class="w-full max-w-md z-10 transition-all duration-500">
        <!-- Logo and Title -->
        <div class="text-center mb-6 flex flex-col items-center">
            <img src="{{ asset('assets/images/queueSsc/Logo_SSC.svg') }}" alt="Logo" class="w-20 h-20 md:w-24 md:h-24 object-contain mb-3 drop-shadow-md">
            <h1 class="text-xl md:text-2xl font-black tracking-tight text-slate-900 uppercase">Student Service Center</h1>
            <p class="text-[10px] text-slate-400 mt-1 uppercase tracking-widest font-bold">Telkom University Surabaya</p>
        </div>

        <!-- Main Card -->
        <div id="ticket-card" class="bg-white border border-slate-200 rounded-3xl p-6 md:p-8 text-center transition-all duration-500 custom-glow">
            <!-- Ticket Status Badge -->
            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] md:text-xs font-semibold uppercase tracking-wider mb-6 bg-slate-100 text-slate-600 transition-all border border-slate-200/50" id="status-badge">
                <span class="w-2 h-2 rounded-full bg-slate-400 animate-pulse" id="status-dot"></span>
                <span id="status-text">Memuat...</span>
            </div>

            <!-- Ticket Code -->
            <div class="text-[10px] md:text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Nomor Tiket</div>
            <div class="text-5xl md:text-6xl font-black text-slate-900 tracking-tighter mb-4 transition-transform hover:scale-105 duration-300 font-mono" id="ticket-code">
                {{ $ticket->code }}
            </div>

            <!-- Divider -->
            <div class="border-t border-slate-100 my-4 md:my-5"></div>

            <!-- Student & Service Details -->
            @if($ticket->name || $ticket->service_category)
                <div class="bg-slate-50/90 rounded-2xl p-4 border border-slate-100 text-left space-y-2 mb-5">
                    <div class="flex justify-between items-start text-xs">
                        <span class="text-slate-400 font-medium">Nama</span>
                        <span class="font-bold text-slate-800 text-right">{{ $ticket->name }}</span>
                    </div>
                    @if($ticket->nim)
                        <div class="flex justify-between items-center text-xs">
                            <span class="text-slate-400 font-medium">NIM</span>
                            <span class="font-mono text-slate-700">{{ $ticket->nim }}</span>
                        </div>
                    @endif
                    @if($ticket->study_program)
                        <div class="flex justify-between items-center text-xs">
                            <span class="text-slate-400 font-medium">Program Studi</span>
                            <span class="text-slate-700 text-right">{{ $ticket->study_program }}</span>
                        </div>
                    @endif
                    @if($ticket->service_category)
                        <div class="flex justify-between items-center text-xs pt-1 border-t border-slate-200/60">
                            <span class="text-slate-400 font-medium">Layanan</span>
                            <span class="font-bold text-[#DA2D3B] text-right">{{ $ticket->service_category }}</span>
                        </div>
                    @endif
                    @if($ticket->notes)
                        <div class="pt-1.5 border-t border-slate-200/60 text-[11px]">
                            <span class="text-slate-400 block mb-1 font-medium">Catatan:</span>
                            <div class="text-slate-600 bg-white p-2.5 rounded-xl border border-slate-200/50 break-words leading-relaxed">
                                {{ $ticket->notes }}
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            <!-- Detail Info Section -->
            <div id="info-section">
                <!-- State: waiting -->
                <div id="state-waiting" class="hidden transition-all duration-300">
                    <p class="text-xs md:text-sm text-slate-500 leading-relaxed py-1">
                        Mohon menunggu di area yang disediakan. <br> Petugas kami akan segera memanggil giliran Anda.
                    </p>
                </div>

                <!-- State: called -->
                <div id="state-called" class="hidden transition-all duration-300">
                    <div class="bg-red-50 border border-red-200/60 rounded-2xl p-4 md:p-5 animate-pulse">
                        <p class="text-base md:text-lg font-bold text-red-600 mb-1">Giliran Anda!</p>
                        <p class="text-xl md:text-2xl font-black text-[#DA2D3B] mb-2 tracking-tight" id="called-counter-text">
                            Silakan menuju {{ $ticket->counter ? $ticket->counter->name : ($counterName ?? 'Loket Layanan') }}
                        </p>
                        <p class="text-xs md:text-sm text-slate-600">
                            Staf kami sudah siap membantu keperluan Anda di meja layanan.
                        </p>
                    </div>
                </div>

                <!-- State: finished -->
                <div id="state-finished" class="hidden transition-all duration-300">
                    <div class="bg-emerald-50 border border-emerald-200/60 rounded-2xl p-4 md:p-5 mb-4">
                        <p class="text-base md:text-lg font-bold text-emerald-600 mb-2">Layanan Selesai</p>
                        <p class="text-xs md:text-sm text-slate-500">
                            Semoga Anda puas dengan layanan kami!
                        </p>
                    </div>
                    <a href="{{ route('queue.take') }}" class="inline-flex w-full justify-center items-center gap-2 px-5 py-3 rounded-xl bg-slate-100 hover:bg-[#DA2D3B] hover:text-white text-slate-700 font-semibold text-xs md:text-sm transition-all duration-300 border border-slate-200 hover:border-[#DA2D3B]">
                        Ambil Tiket Baru
                    </a>
                </div>
            </div>
        </div>

    </div>

    <!-- Script for Dynamic Polling -->
    <script>
        const statusBadge = document.getElementById('status-badge');
        const statusDot = document.getElementById('status-dot');
        const statusText = document.getElementById('status-text');
        const ticketCard = document.getElementById('ticket-card');
        const calledCounterText = document.getElementById('called-counter-text');

        const stateWaiting = document.getElementById('state-waiting');
        const stateCalled = document.getElementById('state-called');
        const stateFinished = document.getElementById('state-finished');

        function updateUI(data) {
            if (data.status === 'waiting') {
                statusText.innerText = 'Menunggu';
                statusDot.className = 'hidden';
                statusBadge.className = 'inline-flex items-center px-3 py-1 rounded-full text-[10px] md:text-xs font-semibold uppercase tracking-wider mb-6 bg-amber-50 border border-amber-200/50 text-amber-700';
                ticketCard.className = 'bg-white border border-slate-200 rounded-3xl p-6 md:p-8 text-center transition-all duration-500 custom-glow';

                stateWaiting.classList.remove('hidden');
                stateCalled.classList.add('hidden');
                stateFinished.classList.add('hidden');

            } else if (data.status === 'called') {
                statusText.innerText = 'Sedang Dipanggil';
                statusDot.className = 'w-2 h-2 rounded-full bg-red-500 animate-ping';
                statusBadge.className = 'inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] md:text-xs font-semibold uppercase tracking-wider mb-6 bg-red-50 border border-red-200/50 text-red-700';
                ticketCard.className = 'bg-white border border-red-200 rounded-3xl p-6 md:p-8 text-center transition-all duration-500 custom-glow scale-[1.02] shadow-xl shadow-red-50';

                if (calledCounterText) {
                    calledCounterText.innerText = 'Silakan menuju ' + (data.counter_name || 'Loket Layanan');
                }

                stateWaiting.classList.add('hidden');
                stateCalled.classList.remove('hidden');
                stateFinished.classList.add('hidden');

            } else if (data.status === 'finished') {
                statusText.innerText = 'Selesai';
                statusDot.className = 'hidden';
                statusBadge.className = 'inline-flex items-center px-3 py-1 rounded-full text-[10px] md:text-xs font-semibold uppercase tracking-wider mb-6 bg-emerald-50 border border-emerald-200/50 text-emerald-700';
                ticketCard.className = 'bg-white border border-slate-200 rounded-3xl p-6 md:p-8 text-center transition-all duration-500 custom-glow-emerald';

                stateWaiting.classList.add('hidden');
                stateCalled.classList.add('hidden');
                stateFinished.classList.remove('hidden');
            }
        }

        // Run immediately
        const initialData = {
            status: @json($ticket->status),
            counter_name: @json($counterName)
        };
        updateUI(initialData);

        // Polling function
        async function fetchStatus() {
            try {
                const response = await fetch('{{ route("queue.status.json") }}', {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                if (response.ok) {
                    const data = await response.json();
                    if (data.success) {
                        updateUI(data);
                    }
                }
            } catch (error) {
                console.error('Error fetching ticket status:', error);
            }
        }

        setInterval(fetchStatus, 3000);
    </script>
</body>
</html>
