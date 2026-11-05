<x-app-layout>
    <x-slot name="header">Dashboard Antrian</x-slot>

    <div x-data="{ 
        activeTab: 'dashboard', 
        showQrModal: false, 
        showConfirmBackModal: false,
        showCounterModal: {{ (!$selectedCounter) ? 'true' : 'false' }},
        showReleaseModal: {{ session('queue_release_ticket') ? 'true' : 'false' }}
    }">

        <!-- Header Controls: Tab Buttons + Pilih Loket & Tampilkan QR (Sejajar) -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
            <div class="flex items-center gap-2">
                <button type="button" @click="activeTab = 'dashboard'"
                        :class="activeTab === 'dashboard' ? 'bg-[#DA2D3B] text-white shadow-md shadow-red-100' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50'"
                        class="px-5 py-2 text-xs font-bold rounded-xl transition-all duration-200">
                    Dashboard
                </button>
                <button type="button" @click="activeTab = 'history'"
                        :class="activeTab === 'history' ? 'bg-[#DA2D3B] text-white shadow-md shadow-red-100' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50'"
                        class="px-5 py-2 text-xs font-bold rounded-xl transition-all duration-200">
                    Histori
                </button>
            </div>
            
            <div class="flex items-center gap-2 flex-wrap">
                <!-- Button Ganti Loket (Statis di samping kiri Tampilkan QR) -->
                <button type="button" @click="showCounterModal = true"
                        class="flex items-center gap-2 px-4 py-2 text-xs font-bold text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-all duration-200 shadow-sm">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                    </svg>
                    Ganti Loket
                </button>

                <!-- Button Tampilkan QR -->
                <button type="button" @click="showQrModal = true"
                        class="flex items-center gap-2 px-4 py-2 text-xs font-bold text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-all duration-200 shadow-sm">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                    </svg>
                    Tampilkan QR
                </button>
            </div>
        </div>

        @if(session('success'))
            <div class="mb-5 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-xs font-medium text-emerald-800 flex items-center justify-between">
                <span>{{ session('success') }}</span>
                <button type="button" onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700">&times;</button>
            </div>
        @endif

        @if(session('error') && !session('queue_release_ticket'))
            <div class="mb-5 p-4 rounded-xl bg-red-50 border border-red-200 text-xs font-medium text-red-800 flex items-center justify-between">
                <span>{{ session('error') }}</span>
                <button type="button" onclick="this.parentElement.remove()" class="text-red-500 hover:text-red-700">&times;</button>
            </div>
        @endif

        <!-- ===== TAB 1: DASHBOARD ===== -->
        <div x-show="activeTab === 'dashboard'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">

            <!-- Top Section: Current Queue Card + Call Controls -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

                <!-- Active Queue Card (Loket Petugas) -->
                <div class="bg-white border-2 {{ $selectedCounter ? 'border-[#DA2D3B]' : 'border-slate-300 border-dashed' }} rounded-2xl p-6 text-center flex flex-col justify-center items-center relative overflow-hidden min-h-[220px] shadow-sm">
                    <div class="absolute inset-0 opacity-5 pointer-events-none">
                        <div class="absolute top-[-30%] right-[-20%] w-[200px] h-[200px] rounded-full bg-[#DA2D3B] blur-[60px]"></div>
                        <div class="absolute bottom-[-20%] left-[-15%] w-[150px] h-[150px] rounded-full bg-[#DA2D3B] blur-[50px]"></div>
                    </div>
                    
                    <div class="relative z-10 w-full">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em] block mb-2" id="admin-current-header">
                            {{ $selectedCounter ? 'Sedang Dipanggil di ' . $selectedCounter->name : 'Loket Belum Dipilih' }}
                        </span>

                        <div id="admin-current-code" class="text-5xl md:text-6xl font-black text-[#DA2D3B] tracking-tight font-mono py-1 transition-all duration-300">
                            {{ $currentCall ? $currentCall->code : '-' }}
                        </div>

                        <p id="admin-current-label" class="text-xs text-slate-500 mt-2 font-medium">
                            @if(!$selectedCounter)
                                Pilih loket terlebih dahulu untuk mulai melayani antrian
                            @elseif($currentCall)
                                Sedang melayani mahasiswa
                            @else
                                Belum ada antrian dipanggil di loket Anda
                            @endif
                        </p>

                        @if(!$selectedCounter)
                            <div class="mt-4">
                                <button type="button" @click="showCounterModal = true"
                                        class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-bold text-white bg-[#DA2D3B] hover:bg-[#b82430] rounded-xl transition-all shadow-sm">
                                    Pilih Loket Anda
                                </button>
                            </div>
                        @endif

                        <!-- Student details for currently called ticket -->
                        <div id="admin-current-details" class="{{ $currentCall ? 'block' : 'hidden' }} mt-4 pt-3 border-t border-slate-100 text-left text-xs bg-slate-50/70 p-3.5 rounded-xl space-y-1.5">
                            <div class="flex justify-between items-center">
                                <span class="font-bold text-slate-800" id="current-detail-name">{{ $currentCall ? $currentCall->name : '-' }}</span>
                                <span class="text-[11px] font-mono text-slate-500" id="current-detail-nim">{{ $currentCall ? $currentCall->nim : '-' }}</span>
                            </div>
                            <div class="flex justify-between items-center text-[11px] text-slate-600">
                                <span id="current-detail-prodi">{{ $currentCall ? $currentCall->study_program : '-' }}</span>
                                <span class="font-bold text-[#DA2D3B]" id="current-detail-service">{{ $currentCall ? $currentCall->service_category : '-' }}</span>
                            </div>
                            <div id="current-detail-notes-container" class="{{ ($currentCall && $currentCall->notes) ? 'block' : 'hidden' }} pt-1 text-[11px] text-slate-500 border-t border-slate-200/50">
                                <span class="text-[10px] text-slate-400 block mb-0.5">Catatan:</span>
                                <div class="italic bg-white p-2 rounded border border-slate-100 break-words" id="current-detail-notes">{{ $currentCall ? $currentCall->notes : '' }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Call Controls -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wide">Kontrol Panggilan</h3>
                            @if($selectedCounter)
                                <span class="text-[10px] font-bold px-2.5 py-0.5 rounded-full bg-red-50 text-[#DA2D3B] border border-red-100">
                                    {{ $selectedCounter->name }}
                                </span>
                            @endif
                        </div>
                        <p class="text-[10px] text-slate-400 mb-5">
                            {{ $selectedCounter ? 'Tombol kontrol bekerja khusus untuk ' . $selectedCounter->name : 'Pilih loket terlebih dahulu untuk mengaktifkan tombol' }}
                        </p>
                    </div>

                    <div class="space-y-2.5">
                        @if($selectedCounter)
                            <form action="{{ route('admin.queue.next') }}" method="POST">
                                @csrf
                                <button type="submit" class="w-full flex items-center justify-center gap-2 px-5 py-3.5 bg-[#DA2D3B] hover:bg-[#c12531] active:scale-[0.98] text-white font-bold text-sm rounded-xl transition-all shadow-md shadow-red-100">
                                    Panggil Berikutnya
                                </button>
                            </form>

                            <form x-ref="backForm" action="{{ route('admin.queue.back') }}" method="POST">
                                @csrf
                                <button type="button" id="btn-back" {{ !$isBackActive ? 'disabled' : '' }}
                                        @click="showConfirmBackModal = true"
                                        class="w-full flex items-center justify-center gap-2 px-5 py-2.5 border border-slate-200 text-slate-700 bg-white hover:bg-slate-50 disabled:opacity-50 disabled:pointer-events-none active:scale-[0.98] font-semibold text-xs rounded-xl transition-all">
                                    Batalkan Panggilan
                                </button>
                            </form>
                        @else
                            <button type="button" @click="showCounterModal = true"
                                    class="w-full flex items-center justify-center gap-2 px-5 py-3.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-sm rounded-xl transition-all border border-slate-200">
                                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                                Pilih Loket Anda Terlebih Dahulu
                            </button>
                            <button type="button" disabled
                                    class="w-full flex items-center justify-center gap-2 px-5 py-2.5 border border-slate-200 text-slate-400 bg-slate-50 opacity-50 cursor-not-allowed font-semibold text-xs rounded-xl">
                                Batalkan Panggilan
                            </button>
                        @endif
                    </div>
                </div>

            </div>


            <!-- Statistik Tiket Hari Ini -->
            <div class="mb-6">
                <h2 class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-3">Statistik Tiket Hari Ini</h2>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <!-- Total Tiket -->
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 hover:shadow-md transition-shadow">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-2">Total Tiket Masuk</span>
                        <div id="stat-total" class="text-3xl font-black text-slate-900 font-mono">{{ $todayStats['totalToday'] }}</div>
                    </div>

                    <!-- Menunggu -->
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 hover:shadow-md transition-shadow">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-2">Menunggu</span>
                        <div id="stat-waiting" class="text-3xl font-black text-slate-900 font-mono">{{ $todayStats['waitingToday'] }}</div>
                    </div>

                    <!-- Sedang Dilayani -->
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 hover:shadow-md transition-shadow">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-2">Sedang Dilayani</span>
                        <div id="stat-called" class="text-3xl font-black text-slate-900 font-mono">{{ $todayStats['calledToday'] }}</div>
                    </div>

                    <!-- Selesai -->
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 hover:shadow-md transition-shadow">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-2">Selesai</span>
                        <div id="stat-finished" class="text-3xl font-black text-slate-900 font-mono">{{ $todayStats['finishedToday'] }}</div>
                    </div>
                </div>
            </div>

            <!-- Daftar Antrian Langsung -->
            <div class="mb-2">
                <h2 class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-3">Antrian Langsung</h2>
                <div x-data="{ listTab: 'waiting' }">
                    <!-- Mobile tabs -->
                    <div class="md:hidden flex rounded-xl bg-slate-100 p-1 mb-4">
                        <button type="button" @click="listTab = 'waiting'"
                                :class="listTab === 'waiting' ? 'bg-white shadow text-[#DA2D3B] font-bold' : 'text-slate-500'"
                                class="flex-1 text-center py-2 text-xs rounded-lg transition-all">
                            Menunggu (<span id="tab-count-waiting">{{ count($waitingTickets) }}</span>)
                        </button>
                        <button type="button" @click="listTab = 'called'"
                                :class="listTab === 'called' ? 'bg-white shadow text-[#DA2D3B] font-bold' : 'text-slate-500'"
                                class="flex-1 text-center py-2 text-xs rounded-lg transition-all">
                            Dipanggil (<span id="tab-count-called">{{ count($calledTickets) }}</span>)
                        </button>
                        <button type="button" @click="listTab = 'finished'"
                                :class="listTab === 'finished' ? 'bg-white shadow text-emerald-600 font-bold' : 'text-slate-500'"
                                class="flex-1 text-center py-2 text-xs rounded-lg transition-all">
                            Selesai (<span id="tab-count-finished">{{ count($finishedTickets) }}</span>)
                        </button>
                    </div>

                    <!-- Desktop columns -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

                        <!-- Menunggu -->
                        <div :class="listTab === 'waiting' ? 'block' : 'hidden md:block'"
                             class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col h-[480px]">
                            <div class="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                                <span class="font-bold text-xs uppercase tracking-wider text-slate-700">Menunggu</span>
                                <span id="badge-waiting" class="text-xs font-bold font-mono px-2 py-0.5 rounded-full bg-slate-200 text-slate-700">{{ count($waitingTickets) }}</span>
                            </div>
                            <div id="list-waiting" class="p-3 space-y-2 overflow-y-auto flex-1 bg-white">
                                @forelse($waitingTickets as $ticket)
                                    <div class="p-3 bg-slate-50 border border-slate-100 rounded-xl space-y-1">
                                        <div class="flex items-center justify-between">
                                            <span class="font-bold text-slate-800 font-mono text-sm">{{ $ticket->code }}</span>
                                            <span class="text-[9px] text-slate-400 font-medium">{{ $ticket->created_at ? $ticket->created_at->format('H:i') : '' }}</span>
                                        </div>
                                        @if($ticket->name)
                                            <div class="flex items-center justify-between text-xs pt-1 border-t border-slate-200/50">
                                                <span class="font-semibold text-slate-800 truncate max-w-[150px]">{{ $ticket->name }}</span>
                                                <span class="text-[10px] font-mono text-slate-500">{{ $ticket->nim }}</span>
                                            </div>
                                            <div class="flex items-center justify-between text-[11px] text-slate-600">
                                                <span class="truncate max-w-[140px] text-slate-500">{{ $ticket->study_program }}</span>
                                                <span class="font-semibold text-[#DA2D3B]">{{ $ticket->service_category }}</span>
                                            </div>
                                            @if($ticket->notes)
                                                <div class="text-[10px] text-slate-500 italic bg-white p-1.5 rounded border border-slate-100 break-words">
                                                    {{ $ticket->notes }}
                                                </div>
                                            @endif
                                        @endif
                                    </div>
                                @empty
                                    <div class="text-center py-8 text-slate-400 text-xs">Tidak ada antrian menunggu</div>
                                @endforelse
                            </div>
                        </div>

                        <!-- Dipanggil -->
                        <div :class="listTab === 'called' ? 'block' : 'hidden md:block'"
                             class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col h-[480px]">
                            <div class="p-4 border-b border-slate-100 flex items-center justify-between bg-red-50/30">
                                <span class="font-bold text-xs uppercase tracking-wider text-red-700">Sedang Dipanggil</span>
                                <span id="badge-called" class="text-xs font-bold font-mono px-2 py-0.5 rounded-full bg-red-100 text-[#DA2D3B]">{{ count($calledTickets) }}</span>
                            </div>
                            <div id="list-called" class="p-3 space-y-2 overflow-y-auto flex-1 bg-white">
                                @forelse($calledTickets as $ticket)
                                    <div class="p-3 bg-red-50/20 border border-red-100/50 rounded-xl space-y-1">
                                        <div class="flex items-center justify-between">
                                            <span class="font-bold text-red-600 font-mono text-sm">{{ $ticket->code }}</span>
                                            <div class="flex items-center gap-1.5">
                                                @if($ticket->counter)
                                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-red-100/70 text-red-800">{{ $ticket->counter->name }}</span>
                                                @endif
                                                <span class="text-[9px] text-slate-400 font-medium">Dipanggil: {{ $ticket->called_at ? $ticket->called_at->format('H:i') : '-' }}</span>
                                            </div>
                                        </div>
                                        @if($ticket->name)
                                            <div class="flex items-center justify-between text-xs pt-1 border-t border-red-100/40">
                                                <span class="font-semibold text-slate-800 truncate max-w-[150px]">{{ $ticket->name }}</span>
                                                <span class="text-[10px] font-mono text-slate-500">{{ $ticket->nim }}</span>
                                            </div>
                                            <div class="flex items-center justify-between text-[11px] text-slate-600">
                                                <span class="truncate max-w-[140px] text-slate-500">{{ $ticket->study_program }}</span>
                                                <span class="font-semibold text-[#DA2D3B]">{{ $ticket->service_category }}</span>
                                            </div>
                                            @if($ticket->calledByUser)
                                                <div class="text-[10px] text-slate-500 pt-0.5">
                                                    Petugas: <span class="font-medium text-slate-700">{{ $ticket->calledByUser->name }}</span>
                                                </div>
                                            @endif
                                            @if($ticket->notes)
                                                <div class="text-[10px] text-slate-500 italic bg-white p-1.5 rounded border border-slate-100 break-words">
                                                    {{ $ticket->notes }}
                                                </div>
                                            @endif
                                        @endif
                                    </div>
                                @empty
                                    <div class="text-center py-8 text-slate-400 text-xs">Tidak ada antrian dipanggil</div>
                                @endforelse
                            </div>
                        </div>

                        <!-- Selesai -->
                        <div :class="listTab === 'finished' ? 'block' : 'hidden md:block'"
                             class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col h-[480px]">
                            <div class="p-4 border-b border-slate-100 flex items-center justify-between bg-emerald-50/30">
                                <span class="font-bold text-xs uppercase tracking-wider text-emerald-700">Selesai Hari Ini</span>
                                <span id="badge-finished" class="text-xs font-bold font-mono px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700">{{ count($finishedTickets) }}</span>
                            </div>
                            <div id="list-finished" class="p-3 space-y-2 overflow-y-auto flex-1 bg-white">
                                @forelse($finishedTickets as $ticket)
                                    <div class="p-3 bg-emerald-50/10 border border-emerald-100/30 rounded-xl space-y-1">
                                        <div class="flex items-center justify-between">
                                            <span class="font-bold text-emerald-600 font-mono text-sm">{{ $ticket->code }}</span>
                                            <div class="flex items-center gap-1.5">
                                                @if($ticket->counter)
                                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-emerald-100/70 text-emerald-800">{{ $ticket->counter->name }}</span>
                                                @endif
                                                <span class="text-[9px] text-slate-400 font-medium">Selesai: {{ $ticket->finished_at ? $ticket->finished_at->format('H:i') : '-' }}</span>
                                            </div>
                                        </div>
                                        @if($ticket->name)
                                            <div class="flex items-center justify-between text-xs pt-1 border-t border-emerald-100/40">
                                                <span class="font-semibold text-slate-800 truncate max-w-[150px]">{{ $ticket->name }}</span>
                                                <span class="text-[10px] font-mono text-slate-500">{{ $ticket->nim }}</span>
                                            </div>
                                            <div class="flex items-center justify-between text-[11px] text-slate-600">
                                                <span class="truncate max-w-[140px] text-slate-500">{{ $ticket->study_program }}</span>
                                                <span class="font-semibold text-emerald-700">{{ $ticket->service_category }}</span>
                                            </div>
                                            @if($ticket->calledByUser)
                                                <div class="text-[10px] text-slate-500 pt-0.5">
                                                    Petugas: <span class="font-medium text-slate-700">{{ $ticket->calledByUser->name }}</span>
                                                </div>
                                            @endif
                                            @if($ticket->notes)
                                                <div class="text-[10px] text-slate-500 italic bg-white p-1.5 rounded border border-slate-100 break-words">
                                                    {{ $ticket->notes }}
                                                </div>
                                            @endif
                                        @endif
                                    </div>
                                @empty
                                    <div class="text-center py-8 text-slate-400 text-xs">Belum ada antrian selesai</div>
                                @endforelse
                            </div>
                        </div>

                    </div>
                </div>
            </div>

        </div>

        <!-- ===== TAB 2: HISTORI & PERFORMA ===== -->
        <div x-show="activeTab === 'history'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <!-- Waktu Tunggu Rata-rata Hari Ini -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-2">Rata-rata Waktu Tunggu Hari Ini</span>
                    <div id="stat-avg-wait" class="text-3xl font-black text-slate-900 font-mono">
                        {{ $todayStats['avgWaitTime'] !== null ? $todayStats['avgWaitTime'] . ' menit' : '-' }}
                    </div>
                    <span class="text-[11px] text-slate-400 mt-2 block">Sejak tiket dibuat hingga dipanggil</span>
                </div>

                <!-- Waktu Layanan Rata-rata Hari Ini -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-2">Rata-rata Durasi Layanan Hari Ini</span>
                    <div id="stat-avg-service" class="text-3xl font-black text-slate-900 font-mono">
                        {{ $todayStats['avgServiceTime'] !== null ? $todayStats['avgServiceTime'] . ' menit' : '-' }}
                    </div>
                    <span class="text-[11px] text-slate-400 mt-2 block">Sejak dipanggil hingga selesai</span>
                </div>

                <!-- Nomor Tiket Terakhir Hari Ini -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-2">Nomor Terakhir Hari Ini</span>
                    <div id="stat-last-ticket" class="text-3xl font-black text-slate-900 font-mono">
                        {{ $todayStats['lastTicketCode'] ?? '-' }}
                    </div>
                    <span class="text-[11px] text-slate-400 mt-2 block">Tiket paling baru dibuat</span>
                </div>
            </div>

            <!-- Perbandingan Historis -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Kemarin -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-bold text-slate-800">Kemarin</h3>
                        <span class="text-xs text-slate-400">{{ now('Asia/Jakarta')->subDay()->format('d M Y') }}</span>
                    </div>
                    <div class="space-y-3">
                        <div class="flex justify-between items-center text-xs">
                            <span class="text-slate-500">Total Tiket:</span>
                            <span class="font-bold font-mono">{{ $historyStats['yesterday']['total'] }}</span>
                        </div>
                        <div class="flex justify-between items-center text-xs">
                            <span class="text-slate-500">Selesai Dilayani:</span>
                            <span class="font-bold font-mono">{{ $historyStats['yesterday']['finished'] }}</span>
                        </div>
                        <div class="flex justify-between items-center text-xs">
                            <span class="text-slate-500">Rata-rata Waktu Tunggu:</span>
                            <span class="font-bold font-mono">{{ $historyStats['yesterday']['avgWaitTime'] !== null ? $historyStats['yesterday']['avgWaitTime'] . ' mnt' : '-' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Minggu Ini -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-bold text-slate-800">Minggu Ini</h3>
                        <span class="text-xs text-slate-400">Senin s/d Hari Ini</span>
                    </div>
                    <div class="space-y-3">
                        <div class="flex justify-between items-center text-xs">
                            <span class="text-slate-500">Total Tiket:</span>
                            <span class="font-bold font-mono">{{ $historyStats['thisWeek']['total'] }}</span>
                        </div>
                        <div class="flex justify-between items-center text-xs">
                            <span class="text-slate-500">Selesai Dilayani:</span>
                            <span class="font-bold font-mono">{{ $historyStats['thisWeek']['finished'] }}</span>
                        </div>
                        <div class="flex justify-between items-center text-xs">
                            <span class="text-slate-500">Rata-rata Waktu Tunggu:</span>
                            <span class="font-bold font-mono">{{ $historyStats['thisWeek']['avgWaitTime'] !== null ? $historyStats['thisWeek']['avgWaitTime'] . ' mnt' : '-' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== MODALS ===== -->

        <!-- Modal Pilih Loket -->
        <div x-show="showCounterModal" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
             @click.self="showCounterModal = false">
            <div class="w-full max-w-md bg-white rounded-3xl p-6 shadow-2xl border border-slate-100 animate-in fade-in zoom-in duration-200">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Pilih Loket Anda</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Loket yang dipakai petugas lain tidak dapat dipilih.</p>
                    </div>
                    @if($selectedCounter)
                        <button type="button" @click="showCounterModal = false" class="text-slate-400 hover:text-slate-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    @endif
                </div>

                <div class="space-y-3 my-4">
                    @foreach($counters as $counter)
                        @php
                            $isOccupiedByOther = $counter->active_user_id && (int)$counter->active_user_id !== (int)auth()->id();
                            $isCurrent = (int)$counter->active_user_id === (int)auth()->id();
                        @endphp
                        <form action="{{ route('admin.queue.claim') }}" method="POST">
                            @csrf
                            <input type="hidden" name="counter_id" value="{{ $counter->id }}">
                            <button type="submit" {{ $isOccupiedByOther ? 'disabled' : '' }}
                                    class="w-full flex items-center justify-between p-4 rounded-2xl border text-left transition-all duration-200
                                           {{ $isCurrent ? 'border-[#DA2D3B] bg-red-50/40 ring-2 ring-red-100' : ($isOccupiedByOther ? 'border-slate-200 bg-slate-50 opacity-60 cursor-not-allowed' : 'border-slate-200 hover:border-slate-300 hover:bg-slate-50') }}">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-slate-800 text-sm">{{ $counter->name }}</span>
                                        @if($isCurrent)
                                            <span class="text-[9px] font-bold px-2 py-0.5 rounded bg-[#DA2D3B] text-white uppercase tracking-wider">Loket Anda</span>
                                        @endif
                                    </div>
                                    <span class="text-xs text-slate-500 mt-1 block">
                                        @if($isOccupiedByOther)
                                            Dipakai oleh {{ $counter->activeUser?->name ?? 'Petugas lain' }}
                                        @elseif($isCurrent)
                                            Sedang aktif dipegang oleh Anda
                                        @else
                                            Tersedia
                                        @endif
                                    </span>
                                </div>
                                <div>
                                    @if($isOccupiedByOther)
                                        <span class="text-[11px] font-semibold text-slate-400 bg-slate-200/60 px-3 py-1 rounded-lg">Dipakai</span>
                                    @elseif($isCurrent)
                                        <span class="text-[11px] font-semibold text-[#DA2D3B] bg-red-100 px-3 py-1 rounded-lg">Aktif</span>
                                    @else
                                        <span class="text-[11px] font-bold text-white bg-[#DA2D3B] hover:bg-[#b82430] px-3.5 py-1.5 rounded-lg shadow-sm">Pilih</span>
                                    @endif
                                </div>
                            </button>
                        </form>
                    @endforeach
                </div>

                @if($selectedCounter)
                    <div class="pt-3 border-t border-slate-100 flex items-center gap-2">
                        <button type="button" @click="showCounterModal = false; showReleaseModal = true"
                                class="flex-1 py-2.5 text-xs font-bold text-red-600 bg-red-50 hover:bg-red-100 rounded-xl transition-all">
                            Keluar Loket
                        </button>
                        <button type="button" @click="showCounterModal = false"
                                class="flex-1 py-2.5 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-xl transition-all">
                            Tutup
                        </button>
                    </div>
                @endif
            </div>
        </div>

        <!-- Modal Konfirmasi Keluar Loket -->
        <div x-show="showReleaseModal" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
             @click.self="showReleaseModal = false">
            <div class="w-full max-w-sm bg-white rounded-3xl p-6 shadow-2xl border border-slate-100 animate-in fade-in zoom-in duration-200 text-center">
                <div class="w-12 h-12 rounded-full bg-red-50 text-[#DA2D3B] flex items-center justify-center mx-auto mb-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                </div>

                <h3 class="text-base font-bold text-slate-900">Keluar dari {{ $selectedCounter?->name ?? 'Loket' }}?</h3>

                @if(session('queue_release_ticket'))
                    <p class="text-xs text-slate-600 mt-2 leading-relaxed">
                        Masih ada nomor <strong class="font-mono text-red-600 font-bold text-sm">{{ session('queue_release_ticket') }}</strong> yang sedang dilayani di loket ini. Selesaikan dulu?
                    </p>
                @else
                    <p class="text-xs text-slate-500 mt-2 leading-relaxed">
                        Anda akan melepas loket ini sehingga dapat dipilih oleh petugas lain.
                    </p>
                @endif

                <div class="mt-6 flex items-center gap-2">
                    <button type="button" @click="showReleaseModal = false"
                            class="flex-1 px-4 py-2.5 text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl transition-all">
                        Batal
                    </button>
                    <form action="{{ route('admin.queue.release') }}" method="POST" class="flex-1">
                        @csrf
                        @if(session('queue_release_ticket'))
                            <input type="hidden" name="finish_current" value="1">
                        @endif
                        <button type="submit"
                                class="w-full px-4 py-2.5 text-xs font-bold text-white bg-[#DA2D3B] hover:bg-[#b82430] rounded-xl transition-all shadow-md shadow-red-100">
                            {{ session('queue_release_ticket') ? 'Selesaikan lalu Keluar' : 'Keluar Loket' }}
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Confirm Back Modal -->
        <div x-show="showConfirmBackModal" 
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
             @click.self="showConfirmBackModal = false">
            <div class="w-full max-w-sm bg-white rounded-3xl p-6 shadow-2xl border border-slate-100 animate-in fade-in zoom-in duration-200 text-center">
                <div class="w-12 h-12 rounded-full bg-red-50 text-[#DA2D3B] flex items-center justify-center mx-auto mb-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <h3 class="text-base font-bold text-slate-900">Batalkan Pemanggilan?</h3>
                <p class="text-xs text-slate-500 mt-2">
                    Nomor saat ini akan dikembalikan ke status <strong>Menunggu</strong>, dan nomor sebelumnya akan kembali berstatus <strong>Dipanggil</strong> di loket Anda.
                </p>
                <div class="mt-6 flex items-center gap-2">
                    <button type="button" @click="showConfirmBackModal = false"
                            class="flex-1 px-4 py-2.5 text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl transition-all">
                        Batal
                    </button>
                    <button type="button" @click="$refs.backForm.submit()"
                            class="flex-1 px-4 py-2.5 text-xs font-bold text-white bg-[#DA2D3B] hover:bg-[#b82430] rounded-xl transition-all shadow-md shadow-red-100">
                        Ya, Batalkan
                    </button>
                </div>
            </div>
        </div>

        <!-- QR Code Modal -->
        <div x-show="showQrModal" 
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
             @click.self="showQrModal = false">
            <div class="w-full max-w-sm bg-white rounded-3xl p-6 shadow-2xl border border-slate-100 animate-in fade-in zoom-in duration-200">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div class="flex items-center gap-2">
                        <img src="{{ asset('assets/images/queueSsc/Logo_SSC.svg') }}" alt="Logo SSC" class="w-6 h-6 object-contain">
                        <h3 class="text-sm font-bold text-slate-800">QR Pengunjung SSC</h3>
                    </div>
                    <button type="button" @click="showQrModal = false" class="text-slate-400 hover:text-slate-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                
                <div class="py-6 flex flex-col items-center">
                    <div class="p-3 bg-white border border-slate-200 rounded-2xl shadow-sm mb-4">
                        <canvas id="qr-canvas" class="w-48 h-48 object-contain"></canvas>
                    </div>
                    <p class="text-xs text-slate-500 text-center max-w-[240px]">
                        Scan QR ini menggunakan kamera HP mahasiswa untuk mengambil nomor antrian.
                    </p>
                </div>

                <div class="pt-2">
                    <button type="button" id="btn-download-qr"
                            class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs rounded-xl transition-all shadow-md">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Unduh QR Code (.png)
                    </button>
                </div>
            </div>
        </div>

    </div>

    <!-- QRious QR Code library CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Initialize QR Code
            const qrCanvas = document.getElementById('qr-canvas');
            if (qrCanvas) {
                const qr = new QRious({
                    element: qrCanvas,
                    value: '{{ route("queue.take") }}',
                    size: 250,
                    level: 'H'
                });

                const downloadBtn = document.getElementById('btn-download-qr');
                if (downloadBtn) {
                    downloadBtn.addEventListener('click', function () {
                        const link = document.createElement('a');
                        link.download = 'telu-queue-qr.png';
                        link.href = qr.toDataURL('image/png');
                        link.click();
                    });
                }
            }
        });

        // Elements
        const currentCodeDiv = document.getElementById('admin-current-code');
        const currentLabel = document.getElementById('admin-current-label');
        const btnBack = document.getElementById('btn-back');

        // Stat elements
        const statTotal = document.getElementById('stat-total');
        const statWaiting = document.getElementById('stat-waiting');
        const statCalled = document.getElementById('stat-called');
        const statFinished = document.getElementById('stat-finished');
        const statAvgWait = document.getElementById('stat-avg-wait');
        const statAvgService = document.getElementById('stat-avg-service');
        const statLastTicket = document.getElementById('stat-last-ticket');

        // List elements
        const badgeWaiting = document.getElementById('badge-waiting');
        const badgeCalled = document.getElementById('badge-called');
        const badgeFinished = document.getElementById('badge-finished');
        const tabCountWaiting = document.getElementById('tab-count-waiting');
        const tabCountCalled = document.getElementById('tab-count-called');
        const tabCountFinished = document.getElementById('tab-count-finished');
        const listWaiting = document.getElementById('list-waiting');
        const listCalled = document.getElementById('list-called');
        const listFinished = document.getElementById('list-finished');

        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        async function fetchDashboardStats() {
            try {
                const response = await fetch('{{ route("admin.queue.stats") }}', {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (response.ok) {
                    const data = await response.json();
                    if (data.success) {
                        // Update current call
                        if (currentCodeDiv) {
                            currentCodeDiv.innerText = data.state.current_called_code || '-';
                        }
                        if (currentLabel) {
                            if (!data.selectedCounter) {
                                currentLabel.innerText = 'Pilih loket terlebih dahulu untuk mulai melayani antrian';
                            } else {
                                currentLabel.innerText = data.state.current_called_code ? 'Sedang melayani mahasiswa' : 'Belum ada antrian dipanggil di loket Anda';
                            }
                        }

                        // Update current called ticket details
                        const curTicket = data.state.current_called_ticket;
                        const detailsContainer = document.getElementById('admin-current-details');
                        if (curTicket && detailsContainer) {
                            detailsContainer.classList.remove('hidden');
                            document.getElementById('current-detail-name').innerText = curTicket.name || '-';
                            document.getElementById('current-detail-nim').innerText = curTicket.nim || '-';
                            document.getElementById('current-detail-prodi').innerText = curTicket.study_program || '-';
                            document.getElementById('current-detail-service').innerText = curTicket.service_category || '-';
                            const notesElem = document.getElementById('current-detail-notes');
                            const notesContainer = document.getElementById('current-detail-notes-container');
                            if (curTicket.notes) {
                                notesContainer.classList.remove('hidden');
                                notesElem.innerText = curTicket.notes;
                            } else {
                                notesContainer.classList.add('hidden');
                            }
                        } else if (detailsContainer) {
                            detailsContainer.classList.add('hidden');
                        }

                        // Update back button
                        if (btnBack) {
                            if (data.state.is_back_active) {
                                btnBack.removeAttribute('disabled');
                            } else {
                                btnBack.setAttribute('disabled', 'disabled');
                            }
                        }



                        // Update stats cards
                        const s = data.todayStats;
                        if (statTotal) statTotal.innerText = s.totalToday;
                        if (statWaiting) statWaiting.innerText = s.waitingToday;
                        if (statCalled) statCalled.innerText = s.calledToday;
                        if (statFinished) statFinished.innerText = s.finishedToday;

                        // Update performance stats (in history tab)
                        if (statAvgWait) statAvgWait.innerText = s.avgWaitTime !== null ? s.avgWaitTime + ' menit' : '-';
                        if (statAvgService) statAvgService.innerText = s.avgServiceTime !== null ? s.avgServiceTime + ' menit' : '-';
                        if (statLastTicket) statLastTicket.innerText = s.lastTicketCode || '-';

                        // Update list badges & tab counters
                        const waitingLen = data.tickets.waiting.length;
                        const calledLen = data.tickets.called.length;
                        const finishedLen = data.tickets.finished.length;

                        if (badgeWaiting) badgeWaiting.innerText = waitingLen;
                        if (tabCountWaiting) tabCountWaiting.innerText = waitingLen;
                        if (badgeCalled) badgeCalled.innerText = calledLen;
                        if (tabCountCalled) tabCountCalled.innerText = calledLen;
                        if (badgeFinished) badgeFinished.innerText = finishedLen;
                        if (tabCountFinished) tabCountFinished.innerText = finishedLen;

                        // Render lists with student details & counter name
                        if (listWaiting) {
                            if (waitingLen > 0) {
                                listWaiting.innerHTML = data.tickets.waiting.map(ticket => {
                                    const timeStr = ticket.created_at ? new Date(ticket.created_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) : '';
                                    const detailsHtml = ticket.name ? `
                                        <div class="flex items-center justify-between text-xs pt-1 border-t border-slate-200/50">
                                            <span class="font-semibold text-slate-800 truncate max-w-[150px]">${escapeHtml(ticket.name)}</span>
                                            <span class="text-[10px] font-mono text-slate-500">${escapeHtml(ticket.nim || '')}</span>
                                        </div>
                                        <div class="flex items-center justify-between text-[11px] text-slate-600">
                                            <span class="truncate max-w-[140px] text-slate-500">${escapeHtml(ticket.study_program || '')}</span>
                                            <span class="font-semibold text-[#DA2D3B]">${escapeHtml(ticket.service_category || '')}</span>
                                        </div>
                                        ${ticket.notes ? `<div class="text-[10px] text-slate-500 italic bg-white p-1.5 rounded border border-slate-100 break-words">${escapeHtml(ticket.notes)}</div>` : ''}
                                    ` : '';
                                    return `
                                        <div class="p-3 bg-slate-50 border border-slate-100 rounded-xl space-y-1">
                                            <div class="flex items-center justify-between">
                                                <span class="font-bold text-slate-800 font-mono text-sm">${escapeHtml(ticket.code)}</span>
                                                <span class="text-[9px] text-slate-400 font-medium">${timeStr}</span>
                                            </div>
                                            ${detailsHtml}
                                        </div>
                                    `;
                                }).join('');
                            } else {
                                listWaiting.innerHTML = '<div class="text-center py-8 text-slate-400 text-xs">Tidak ada antrian menunggu</div>';
                            }
                        }

                        if (listCalled) {
                            if (calledLen > 0) {
                                listCalled.innerHTML = data.tickets.called.map(ticket => {
                                    const detailsHtml = ticket.name ? `
                                        <div class="flex items-center justify-between text-xs pt-1 border-t border-red-100/40">
                                            <span class="font-semibold text-slate-800 truncate max-w-[150px]">${escapeHtml(ticket.name)}</span>
                                            <span class="text-[10px] font-mono text-slate-500">${escapeHtml(ticket.nim || '')}</span>
                                        </div>
                                        <div class="flex items-center justify-between text-[11px] text-slate-600">
                                            <span class="truncate max-w-[140px] text-slate-500">${escapeHtml(ticket.study_program || '')}</span>
                                            <span class="font-semibold text-[#DA2D3B]">${escapeHtml(ticket.service_category || '')}</span>
                                        </div>
                                        ${ticket.caller_name && ticket.caller_name !== '-' ? `
                                            <div class="text-[10px] text-slate-500 pt-0.5">
                                                Petugas: <span class="font-medium text-slate-700">${escapeHtml(ticket.caller_name)}</span>
                                            </div>
                                        ` : ''}
                                        ${ticket.notes ? `<div class="text-[10px] text-slate-500 italic bg-white p-1.5 rounded border border-slate-100 break-words">${escapeHtml(ticket.notes)}</div>` : ''}
                                    ` : '';
                                    return `
                                        <div class="p-3 bg-red-50/20 border border-red-100/50 rounded-xl space-y-1">
                                            <div class="flex items-center justify-between">
                                                <span class="font-bold text-red-600 font-mono text-sm">${escapeHtml(ticket.code)}</span>
                                                <div class="flex items-center gap-1.5">
                                                    ${ticket.counter_name && ticket.counter_name !== '-' ? `
                                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-red-100/70 text-red-800">${escapeHtml(ticket.counter_name)}</span>
                                                    ` : ''}
                                                    <span class="text-[9px] text-slate-400 font-medium">Dipanggil: ${ticket.called_at_formatted || '-'}</span>
                                                </div>
                                            </div>
                                            ${detailsHtml}
                                        </div>
                                    `;
                                }).join('');
                            } else {
                                listCalled.innerHTML = '<div class="text-center py-8 text-slate-400 text-xs">Tidak ada antrian dipanggil</div>';
                            }
                        }

                        if (listFinished) {
                            if (finishedLen > 0) {
                                listFinished.innerHTML = data.tickets.finished.map(ticket => {
                                    const detailsHtml = ticket.name ? `
                                        <div class="flex items-center justify-between text-xs pt-1 border-t border-emerald-100/40">
                                            <span class="font-semibold text-slate-800 truncate max-w-[150px]">${escapeHtml(ticket.name)}</span>
                                            <span class="text-[10px] font-mono text-slate-500">${escapeHtml(ticket.nim || '')}</span>
                                        </div>
                                        <div class="flex items-center justify-between text-[11px] text-slate-600">
                                            <span class="truncate max-w-[140px] text-slate-500">${escapeHtml(ticket.study_program || '')}</span>
                                            <span class="font-semibold text-emerald-700">${escapeHtml(ticket.service_category || '')}</span>
                                        </div>
                                        ${ticket.caller_name && ticket.caller_name !== '-' ? `
                                            <div class="text-[10px] text-slate-500 pt-0.5">
                                                Petugas: <span class="font-medium text-slate-700">${escapeHtml(ticket.caller_name)}</span>
                                            </div>
                                        ` : ''}
                                        ${ticket.notes ? `<div class="text-[10px] text-slate-500 italic bg-white p-1.5 rounded border border-slate-100 break-words">${escapeHtml(ticket.notes)}</div>` : ''}
                                    ` : '';
                                    return `
                                        <div class="p-3 bg-emerald-50/10 border border-emerald-100/30 rounded-xl space-y-1">
                                            <div class="flex items-center justify-between">
                                                <span class="font-bold text-emerald-600 font-mono text-sm">${escapeHtml(ticket.code)}</span>
                                                <div class="flex items-center gap-1.5">
                                                    ${ticket.counter_name && ticket.counter_name !== '-' ? `
                                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-emerald-100/70 text-emerald-800">${escapeHtml(ticket.counter_name)}</span>
                                                    ` : ''}
                                                    <span class="text-[9px] text-slate-400 font-medium">Selesai: ${ticket.finished_at_formatted || '-'}</span>
                                                </div>
                                            </div>
                                            ${detailsHtml}
                                        </div>
                                    `;
                                }).join('');
                            } else {
                                listFinished.innerHTML = '<div class="text-center py-8 text-slate-400 text-xs">Belum ada antrian selesai</div>';
                            }
                        }
                    }
                }
            } catch (error) {
                console.error('Error fetching dashboard stats:', error);
            }
        }

        setInterval(fetchDashboardStats, 5000);
    </script>
</x-app-layout>
