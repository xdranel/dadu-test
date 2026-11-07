<x-app-layout>
    <x-slot name="header">Riwayat Antrian Mahasiswa</x-slot>

    <div class="space-y-6">

        <!-- Navigation & Header Actions -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <a href="{{ route('admin.queue.index') }}"
                   class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-[#DA2D3B] transition-colors mb-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Kembali
                </a>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('admin.queue.history.export', request()->query()) }}"
                   class="inline-flex items-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 active:scale-[0.98] text-white text-xs font-bold rounded-xl shadow-sm transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Export CSV
                </a>
            </div>
        </div>

        <!-- Filter Card (Dropdown / Collapsible) -->
        <div x-data="{ 
                isOpen: {{ request()->hasAny(['start_date', 'end_date', 'nim', 'name', 'service_category', 'status']) ? 'true' : 'false' }} 
             }" 
             class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden transition-all duration-200">
            
            <!-- Toggle Header -->
            <button type="button" 
                    @click="isOpen = !isOpen"
                    class="w-full px-5 py-3.5 flex items-center justify-between text-left hover:bg-slate-50/70 transition-colors">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-red-50 text-[#DA2D3B] flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                        </svg>
                    </div>
                    <div>
                        <span class="text-xs font-bold text-slate-800 block">Filter Pencarian</span>
                        <span class="text-[10px] text-slate-400">Klik untuk membuka atau menutup panel filter</span>
                    </div>
                    @php
                        $activeFiltersCount = collect(['start_date', 'end_date', 'nim', 'name', 'service_category', 'status'])
                            ->filter(fn($key) => request()->filled($key))
                            ->count();
                    @endphp
                    @if($activeFiltersCount > 0)
                        <span class="ml-2 px-2 py-0.5 text-[10px] font-bold rounded-full bg-red-100 text-[#DA2D3B]">
                            {{ $activeFiltersCount }} filter aktif
                        </span>
                    @endif
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-[11px] font-semibold text-slate-400" x-text="isOpen ? 'Tutup Filter' : 'Buka Filter'"></span>
                    <svg class="w-4 h-4 text-slate-400 transition-transform duration-200"
                         :class="isOpen ? 'rotate-180' : ''"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </div>
            </button>

            <!-- Collapsible Filter Form Content -->
            <div x-show="isOpen" 
                 x-cloak
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 -translate-y-2"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 class="p-5 pt-2 border-t border-slate-100">
                <form method="GET" action="{{ route('admin.queue.history') }}" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 pt-2">
                        <!-- Rentang Tanggal Mulai -->
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1.5">Dari Tanggal</label>
                            <input type="date" name="start_date" value="{{ request('start_date') }}"
                                   class="w-full text-xs rounded-xl border-slate-200 focus:border-[#DA2D3B] focus:ring-[#DA2D3B]">
                        </div>

                        <!-- Rentang Tanggal Selesai -->
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1.5">Sampai Tanggal</label>
                            <input type="date" name="end_date" value="{{ request('end_date') }}"
                                   class="w-full text-xs rounded-xl border-slate-200 focus:border-[#DA2D3B] focus:ring-[#DA2D3B]">
                        </div>

                        <!-- NIM -->
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1.5">NIM (Awalan / Persis)</label>
                            <input type="text" name="nim" value="{{ request('nim') }}" placeholder="Contoh: 120121..."
                                   class="w-full text-xs rounded-xl border-slate-200 focus:border-[#DA2D3B] focus:ring-[#DA2D3B]">
                        </div>

                        <!-- Nama -->
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1.5">Nama Mahasiswa</label>
                            <input type="text" name="name" value="{{ request('name') }}" placeholder="Cari nama..."
                                   class="w-full text-xs rounded-xl border-slate-200 focus:border-[#DA2D3B] focus:ring-[#DA2D3B]">
                        </div>

                        <!-- Layanan -->
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1.5">Kategori Layanan</label>
                            <select name="service_category" class="w-full text-xs rounded-xl border-slate-200 focus:border-[#DA2D3B] focus:ring-[#DA2D3B]">
                                <option value="">Semua Layanan</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat }}" {{ request('service_category') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Status -->
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1.5">Status</label>
                            <select name="status" class="w-full text-xs rounded-xl border-slate-200 focus:border-[#DA2D3B] focus:ring-[#DA2D3B]">
                                <option value="">Semua Status</option>
                                <option value="finished" {{ request('status') === 'finished' ? 'selected' : '' }}>Selesai</option>
                                <option value="skipped" {{ request('status') === 'skipped' ? 'selected' : '' }}>Dilewati</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                        <a href="{{ route('admin.queue.history') }}"
                           class="px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-xl transition-colors">
                            Reset Filter
                        </a>
                        <button type="submit"
                                class="px-5 py-2 text-xs font-bold text-white bg-[#DA2D3B] hover:bg-[#b82430] rounded-xl transition-colors shadow-sm">
                            Terapkan Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Summary Statistics for Active Filter -->
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
            <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Total Tiket</span>
                <div class="text-2xl font-black text-slate-800 font-mono">{{ number_format($summary['total']) }}</div>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm">
                <span class="text-[10px] font-bold text-emerald-600 uppercase tracking-wider block mb-1">Selesai</span>
                <div class="text-2xl font-black text-emerald-700 font-mono">{{ number_format($summary['finished']) }}</div>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm">
                <span class="text-[10px] font-bold text-amber-600 uppercase tracking-wider block mb-1">Dilewati</span>
                <div class="text-2xl font-black text-amber-700 font-mono">{{ number_format($summary['skipped']) }}</div>
            </div>
        </div>

        <!-- Table Data -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-50/80 border-b border-slate-100 text-slate-500 font-bold uppercase tracking-wider text-[10px]">
                            <th class="py-3 px-4">Tanggal</th>
                            <th class="py-3 px-4">Nomor</th>
                            <th class="py-3 px-4">NIM & Mahasiswa</th>
                            <th class="py-3 px-4">Layanan & Prodi</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4">Loket</th>
                            <th class="py-3 px-4">Jam Ambil</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        @forelse($tickets as $ticket)
                            <tr class="hover:bg-slate-50/70 transition-colors">
                                <td class="py-3 px-4 font-mono font-medium whitespace-nowrap">
                                    {{ \Carbon\Carbon::parse($ticket->queue_date)->translatedFormat('d M Y') }}
                                </td>
                                <td class="py-3 px-4 font-mono font-bold text-[#DA2D3B]">
                                    {{ $ticket->code }}
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-semibold text-slate-800">{{ $ticket->name ?: '-' }}</div>
                                    <div class="font-mono text-[11px] text-slate-400">{{ $ticket->nim ?: '-' }}</div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-medium text-slate-700">{{ $ticket->service_category ?: '-' }}</div>
                                    <div class="text-[11px] text-slate-400">{{ $ticket->study_program ?: '-' }}</div>
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap">
                                    @if($ticket->status === 'finished')
                                        <span class="inline-flex px-2 py-0.5 text-[10px] font-bold rounded bg-emerald-50 text-emerald-700 border border-emerald-200">
                                             Selesai
                                        </span>
                                    @elseif($ticket->status === 'skipped')
                                        <span class="inline-flex px-2 py-0.5 text-[10px] font-bold rounded bg-amber-50 text-amber-700 border border-amber-200">
                                            Dilewati
                                        </span>
                                    @elseif($ticket->status === 'called')
                                        <span class="inline-flex px-2 py-0.5 text-[10px] font-bold rounded bg-red-50 text-red-700 border border-red-200">
                                            Dipanggil
                                        </span>
                                    @elseif($ticket->status === 'waiting')
                                        <span class="inline-flex px-2 py-0.5 text-[10px] font-bold rounded bg-blue-50 text-blue-700 border border-blue-200">
                                            Menunggu
                                        </span>
                                    @else
                                        <span class="inline-flex px-2 py-0.5 text-[10px] font-bold rounded bg-slate-100 text-slate-600 border border-slate-200">
                                            Tidak Terlayani
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap">
                                    @if($ticket->counter_id)
                                        <span class="font-medium text-slate-700">
                                            {{ optional($counters->firstWhere('id', $ticket->counter_id))->name ?? 'Loket ' . $ticket->counter_id }}
                                        </span>
                                    @else
                                        <span class="text-slate-400">-</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 font-mono text-slate-500 whitespace-nowrap">
                                    {{ $ticket->created_at ? \Carbon\Carbon::parse($ticket->created_at)->timezone('Asia/Jakarta')->format('H:i') : '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-12 text-center text-slate-400">
                                    <div class="max-w-sm mx-auto space-y-2">
                                        <svg class="w-10 h-10 mx-auto text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                        <p class="font-semibold text-slate-700">Tidak ada riwayat antrian untuk filter ini.</p>
                                        <p class="text-xs text-slate-500">
                                            Rentang data tersimpan tertua: <strong>{{ $oldestDate ? \Carbon\Carbon::parse($oldestDate)->translatedFormat('d F Y') : '-' }}</strong>
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($tickets->hasPages())
                <div class="p-4 border-t border-slate-100">
                    {{ $tickets->links() }}
                </div>
            @endif
        </div>

    </div>
</x-app-layout>