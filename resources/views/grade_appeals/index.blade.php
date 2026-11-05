<x-app-layout>
    <x-slot name="header">Riwayat Pengajuan Banding Nilai</x-slot>

    <!-- Success/Error Flash Messages -->
    @if(session('success'))
    <div class="mb-5 flex items-start gap-3 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl flash-message">
        <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <p class="text-xs sm:text-sm font-medium">{{ session('success') }}</p>
    </div>
    @endif

    @if(session('error'))
    <div class="mb-5 flex items-start gap-3 bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-xl flash-message">
        <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <p class="text-xs sm:text-sm font-medium">{{ session('error') }}</p>
    </div>
    @endif

    <!-- Countdown Banner Card (Mahasiswa - Read Only) -->
    <div x-data="{
        openAt: '{{ $activePeriod?->open_at?->toIso8601String() }}',
        closeAt: '{{ $activePeriod?->close_at?->toIso8601String() }}',
        days: 0,
        hours: 0,
        minutes: 0,
        seconds: 0,
        isOpen: false,
        isUrgent: false,
        interval: null,
        updateCountdown() {
            if (!this.closeAt || !this.openAt) {
                this.isOpen = false;
                this.isUrgent = false;
                return;
            }
            const start = new Date(this.openAt).getTime();
            const target = new Date(this.closeAt).getTime();
            const now = new Date().getTime();

            this.isOpen = now >= start && now <= target;

            if (!this.isOpen) {
                this.days = 0;
                this.hours = 0;
                this.minutes = 0;
                this.seconds = 0;
                this.isUrgent = false;
                return;
            }

            const diff = target - now;
            this.isUrgent = diff < 24 * 60 * 60 * 1000;

            this.days = Math.floor(diff / (1000 * 60 * 60 * 24));
            this.hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            this.minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            this.seconds = Math.floor((diff % (1000 * 60)) / 1000);
        },
        initCountdown() {
            this.updateCountdown();
            this.interval = setInterval(() => this.updateCountdown(), 1000);
        }
    }"
    x-init="initCountdown()"
    class="mb-6 p-4 bg-white border border-slate-200 rounded-xl shadow-sm hover:shadow-md transition-shadow duration-300">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            @if($activePeriod)
                <!-- Sisi Kiri -->
                <div class="space-y-1.5">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs md:text-sm font-bold text-slate-800 tracking-tight">{{ $activePeriod->name }}</span>
                    </div>

                    <!-- Waktu Pengajuan Banding: [tgl_mulai] s.d. [tgl_akhir] -->
                    <p class="text-[11px] text-slate-500">
                        Waktu Pengajuan Banding: {{ $activePeriod->open_at?->translatedFormat('d M Y') }} s.d {{ $activePeriod->close_at?->translatedFormat('d M Y') }}
                    </p>
                </div>

                <!-- Sisi Kanan: 4 kotak countdown -->
                <div class="flex flex-col items-start lg:items-end gap-1.5">
                    <div x-show="isOpen" class="flex items-center gap-1 sm:gap-1.5">
                        <!-- DAYS -->
                        <div class="flex flex-col items-center justify-center bg-slate-100 border border-slate-200/50 rounded-lg w-11 h-11 sm:w-12 sm:h-12 md:w-14 md:h-14 shadow-sm transition-colors duration-300">
                            <span class="text-base sm:text-lg md:text-xl font-bold tracking-tight"
                                  :class="isUrgent ? 'text-[#DA2D3B]' : 'text-slate-800'"
                                  x-text="String(days).padStart(2, '0')">00</span>
                            <span class="text-[8px] md:text-[9px] font-bold text-slate-400 tracking-wider uppercase mt-0.5">DAYS</span>
                        </div>

                        <span class="flex items-center text-slate-300 font-bold text-sm sm:text-base">:</span>

                        <!-- HOURS -->
                        <div class="flex flex-col items-center justify-center bg-slate-100 border border-slate-200/50 rounded-lg w-11 h-11 sm:w-12 sm:h-12 md:w-14 md:h-14 shadow-sm transition-colors duration-300">
                            <span class="text-base sm:text-lg md:text-xl font-bold tracking-tight"
                                  :class="isUrgent ? 'text-[#DA2D3B]' : 'text-slate-800'"
                                  x-text="String(hours).padStart(2, '0')">00</span>
                            <span class="text-[8px] md:text-[9px] font-bold text-slate-400 tracking-wider uppercase mt-0.5">HOURS</span>
                        </div>

                        <span class="flex items-center text-slate-300 font-bold text-sm sm:text-base">:</span>

                        <!-- MIN -->
                        <div class="flex flex-col items-center justify-center bg-slate-100 border border-slate-200/50 rounded-lg w-11 h-11 sm:w-12 sm:h-12 md:w-14 md:h-14 shadow-sm transition-colors duration-300">
                            <span class="text-base sm:text-lg md:text-xl font-bold tracking-tight"
                                  :class="isUrgent ? 'text-[#DA2D3B]' : 'text-slate-800'"
                                  x-text="String(minutes).padStart(2, '0')">00</span>
                            <span class="text-[8px] md:text-[9px] font-bold text-slate-400 tracking-wider uppercase mt-0.5">MIN</span>
                        </div>

                        <span class="flex items-center text-slate-300 font-bold text-sm sm:text-base">:</span>

                        <!-- SEC -->
                        <div class="flex flex-col items-center justify-center bg-slate-100 border border-slate-200/50 rounded-lg w-11 h-11 sm:w-12 sm:h-12 md:w-14 md:h-14 shadow-sm transition-colors duration-300">
                            <span class="text-base sm:text-lg md:text-xl font-bold tracking-tight"
                                  :class="isUrgent ? 'text-[#DA2D3B]' : 'text-slate-800'"
                                  x-text="String(seconds).padStart(2, '0')">00</span>
                            <span class="text-[8px] md:text-[9px] font-bold text-slate-400 tracking-wider uppercase mt-0.5">SEC</span>
                        </div>
                    </div>
                </div>
            @else
                <!-- State Kosong Belum ada periode -->
                <div class="space-y-1.5">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs md:text-sm font-bold text-slate-800 tracking-tight">Belum ada periode pengajuan banding aktif</span>
                    </div>
                    <p class="text-[11px] text-slate-500">
                        Silakan periksa secara berkala untuk jadwal periode pengajuan banding nilai selanjutnya.
                    </p>
                </div>
            @endif
        </div>
    </div>

    <!-- Header Actions & Title -->
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div>
            <h2 class="text-xl font-bold text-slate-800">Riwayat Pengajuan Banding Nilai</h2>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">Daftar pengajuan banding nilai yang telah Anda ajukan.</p>
        </div>

        @if($activePeriod && $activePeriod->isOpen())
            <a href="{{ route('grade-appeals.create') }}" class="btn btn-primary shadow-sm hover:shadow transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Ajukan Banding
            </a>
        @else
            <button type="button" disabled class="btn btn-secondary opacity-60 cursor-not-allowed" title="Pengajuan Banding Nilai Ditutup">
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
                Pengajuan Banding Ditutup
            </button>
        @endif
    </div>

    <!-- Table Component -->
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden mb-6">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-body border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-semibold uppercase tracking-wider text-label">
                        <th scope="col" class="py-3.5 px-4">
                            Tanggal Pengajuan
                        </th>
                        <th scope="col" class="py-3.5 px-4">
                            Nama Mata Kuliah
                        </th>
                        <th scope="col" class="py-3.5 px-4">
                            Status
                        </th>
                        <th scope="col" class="py-3.5 px-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($appeals as $index => $appeal)
                        <tr class="{{ $index % 2 === 0 ? 'bg-white' : 'bg-[#F8FAFC]' }} hover:bg-slate-50 transition-colors">
                            <!-- Tanggal Pengajuan -->
                            <td class="py-3.5 px-4 whitespace-nowrap text-slate-600 font-medium">
                                {{ $appeal->submitted_at?->translatedFormat('d M Y') ?? $appeal->created_at->translatedFormat('d M Y') }}
                            </td>

                            <!-- Nama Mata Kuliah -->
                            <td class="py-3.5 px-4 font-semibold text-slate-800 max-w-xs md:max-w-md truncate">
                                {{ $appeal->course_name_manual ?? $appeal->course_name ?? '-' }}
                            </td>

                            <!-- Status -->
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <x-status-badge :status="$appeal->status" :label="$appeal->status_label" />
                            </td>

                            <!-- Aksi -->
                            <td class="py-3.5 px-4 whitespace-nowrap text-center">
                                <a href="{{ route('grade-appeals.show', $appeal) }}"
                                   class="text-sm px-3 py-1 border border-gray-300 rounded-md hover:bg-gray-50 transition-colors text-slate-700 font-medium"
                                   title="Lihat Detail" aria-label="Lihat Detail">
                                    Detail
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-12 px-4 text-center">
                                <p class="text-body font-semibold text-slate-600 mb-1">Belum Ada Pengajuan</p>
                                <p class="text-caption text-slate-400">Anda belum memiliki pengajuan banding nilai.</p>
                            </td>
                        </tr>
                    @endempty
                </tbody>
            </table>
        </div>

        <!-- Footer Pagination & Entries Info -->
        <div class="px-4 py-3 bg-slate-50 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-3 text-caption text-slate-500">
            <div>
                Showing {{ $appeals->firstItem() ?? 0 }} to {{ $appeals->lastItem() ?? 0 }} of {{ $appeals->total() ?? 0 }} entries
            </div>
            <div>
                {{ $appeals->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
