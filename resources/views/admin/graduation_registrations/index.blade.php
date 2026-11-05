<x-app-layout>
    <x-slot name="header">Pendaftaran Yudisium</x-slot>

    <!-- Main Container with Alpine State -->
    <div x-data="{
        showPeriodModal: false,
        editMode: false,
        formUrl: '{{ route('admin.graduation-periods.store') }}',
        form: {
            name: '',
            open_at: '',
            close_at: '',
            description: '',
            is_active: true
        },
        openCreate() {
            this.editMode = false;
            this.formUrl = '{{ route('admin.graduation-periods.store') }}';
            this.form = {
                name: '',
                open_at: '',
                close_at: '',
                description: '',
                is_active: true
            };
            this.showPeriodModal = true;
        },
        openEdit(period) {
            this.editMode = true;
            this.formUrl = '/dashboard/admin/graduation-periods/' + period.id;
            this.form = {
                name: period.name,
                open_at: period.open_at ? period.open_at.replace(' ', 'T').substring(0, 16) : '',
                close_at: period.close_at ? period.close_at.replace(' ', 'T').substring(0, 16) : '',
                description: period.description || '',
                is_active: Boolean(period.is_active)
            };
            this.showPeriodModal = true;
        },
        // Countdown banner minimize/maximize logic with localStorage persistence
        isMinimized: localStorage.getItem('countdown_card_minimized') === 'true',
        toggleMinimize() {
            this.isMinimized = !this.isMinimized;
            localStorage.setItem('countdown_card_minimized', this.isMinimized);
        },
        // Real-time countdown calculation
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
    }" x-init="initCountdown()">
        <!-- Countdown Banner Card Container (Slide Pop-up / Expandable) -->
        <div>
            <!-- State Minimized (Compact Floating Bar/Pill) -->
            <div x-show="isMinimized" x-transition:enter="transition ease-out duration-300 transform"
                x-transition:enter-start="opacity-0 -translate-y-2 scale-98"
                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                x-transition:leave="transition ease-in duration-200 transform"
                x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                x-transition:leave-end="opacity-0 -translate-y-2 scale-98"
                class="mb-4 flex items-center justify-between bg-white border border-slate-200 rounded-xl px-4 py-2.5 shadow-sm">

                <div class="flex items-center gap-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs font-bold text-slate-800 tracking-tight">
                            {{ $activePeriod ? $activePeriod->name : 'Periode Yudisium' }}
                        </span>
                        @if ($activePeriod)
                            <span x-show="isOpen"
                                class="px-2 py-0.5 inline-flex items-center text-[10px] font-semibold rounded-md bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/10">Buka</span>
                            <span x-show="!isOpen"
                                class="px-2 py-0.5 inline-flex items-center text-[10px] font-semibold rounded-md bg-rose-50 text-rose-700 ring-1 ring-rose-600/10"
                                style="display: none;">Tutup</span>
                        @else
                            <span
                                class="px-2 py-0.5 inline-flex items-center text-[10px] font-semibold rounded-md bg-slate-100 text-slate-600 ring-1 ring-slate-300">Belum
                                Diset</span>
                        @endif
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    @if ($activePeriod)
                        <button type="button" @click="openEdit({{ json_encode($activePeriod) }})"
                            class="inline-flex items-center gap-1 px-2.5 py-1 text-[10px] font-semibold rounded-full border border-[#DA2D3B] bg-white hover:bg-red-50 text-[#DA2D3B] transition-all duration-200">
                            <svg class="w-3 h-3 text-[#DA2D3B]" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                            </svg>
                            Kelola Waktu
                        </button>
                    @else
                        <button type="button" @click="openCreate()"
                            class="inline-flex items-center gap-1 px-2.5 py-1 text-[10px] font-semibold rounded-full border border-[#DA2D3B] bg-white hover:bg-red-50 text-[#DA2D3B] transition-all duration-200">
                            <svg class="w-3 h-3 text-[#DA2D3B]" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                            </svg>
                            Kelola Waktu
                        </button>
                    @endif

                    <button type="button" @click="toggleMinimize()"
                        class="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"
                        title="Maximize Card">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- State Maximized (Full Banner Card) -->
            <div x-show="!isMinimized" x-transition:enter="transition ease-out duration-300 transform"
                x-transition:enter-start="opacity-0 -translate-y-4 max-h-0 overflow-hidden"
                x-transition:enter-end="opacity-100 translate-y-0 max-h-[500px]"
                x-transition:leave="transition ease-in duration-200 transform"
                x-transition:leave-start="opacity-100 translate-y-0 max-h-[500px]"
                x-transition:leave-end="opacity-0 -translate-y-4 max-h-0 overflow-hidden"
                class="mb-4 p-4 bg-white border border-slate-200 rounded-xl shadow-sm hover:shadow-md transition-all duration-300">

                <div class="flex items-center justify-between border-b border-slate-100 pb-2 mb-3">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Menuju Penutupan
                        Pendaftaran</span>
                    <button type="button" @click="toggleMinimize()"
                        class="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"
                        title="Minimize Card">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                        </svg>
                    </button>
                </div>

                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    @if ($activePeriod)
                        <!-- Sisi Kiri -->
                        <div class="space-y-1.5">
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="text-xs md:text-sm font-bold text-slate-800 tracking-tight">{{ $activePeriod->name }}</span>
                                <span x-show="isOpen"
                                    class="px-2 py-0.5 inline-flex items-center text-[11px] font-semibold rounded-md bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/10">Buka</span>
                                <span x-show="!isOpen"
                                    class="px-2 py-0.5 inline-flex items-center text-[11px] font-semibold rounded-md bg-rose-50 text-rose-700 ring-1 ring-rose-600/10"
                                    style="display: none;">Tutup</span>
                            </div>

                            <p class="text-[11px] text-slate-500">
                                Waktu Pendaftaran: {{ $activePeriod->open_at?->translatedFormat('d M Y, H:i') }} WIB
                                s.d. {{ $activePeriod->close_at?->translatedFormat('d M Y, H:i') }} WIB
                            </p>

                            <div class="pt-1">
                                <button type="button" @click="openEdit({{ json_encode($activePeriod) }})"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 text-[10px] font-semibold rounded-full border border-[#DA2D3B] bg-white hover:bg-red-50 text-[#DA2D3B] transition-all duration-200">
                                    <svg class="w-3 h-3 text-[#DA2D3B]" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                    </svg>
                                    Kelola Waktu (Edit)
                                </button>
                            </div>
                        </div>

                        <!-- Sisi Kanan -->
                        <div class="flex flex-col items-start lg:items-end gap-1.5">
                            <div x-show="isOpen" class="flex items-center gap-1 sm:gap-1.5">
                                <div
                                    class="flex flex-col items-center justify-center bg-slate-100 border border-slate-200/50 rounded-lg w-11 h-11 sm:w-12 sm:h-12 md:w-14 md:h-14 shadow-sm transition-colors duration-300">
                                    <span class="text-base sm:text-lg md:text-xl font-bold tracking-tight"
                                        :class="isUrgent ? 'text-[#DA2D3B]' : 'text-slate-800'"
                                        x-text="String(days).padStart(2, '0')">00</span>
                                    <span
                                        class="text-[8px] md:text-[9px] font-bold text-slate-400 tracking-wider uppercase mt-0.5">DAYS</span>
                                </div>
                                <span class="text-slate-300 font-bold text-sm sm:text-base">:</span>
                                <div
                                    class="flex flex-col items-center justify-center bg-slate-100 border border-slate-200/50 rounded-lg w-11 h-11 sm:w-12 sm:h-12 md:w-14 md:h-14 shadow-sm transition-colors duration-300">
                                    <span class="text-base sm:text-lg md:text-xl font-bold tracking-tight"
                                        :class="isUrgent ? 'text-[#DA2D3B]' : 'text-slate-800'"
                                        x-text="String(hours).padStart(2, '0')">00</span>
                                    <span
                                        class="text-[8px] md:text-[9px] font-bold text-slate-400 tracking-wider uppercase mt-0.5">HOURS</span>
                                </div>
                                <span class="text-slate-300 font-bold text-sm sm:text-base">:</span>
                                <div
                                    class="flex flex-col items-center justify-center bg-slate-100 border border-slate-200/50 rounded-lg w-11 h-11 sm:w-12 sm:h-12 md:w-14 md:h-14 shadow-sm transition-colors duration-300">
                                    <span class="text-base sm:text-lg md:text-xl font-bold tracking-tight"
                                        :class="isUrgent ? 'text-[#DA2D3B]' : 'text-slate-800'"
                                        x-text="String(minutes).padStart(2, '0')">00</span>
                                    <span
                                        class="text-[8px] md:text-[9px] font-bold text-slate-400 tracking-wider uppercase mt-0.5">MIN</span>
                                </div>
                                <span class="text-slate-300 font-bold text-sm sm:text-base">:</span>
                                <div
                                    class="flex flex-col items-center justify-center bg-slate-100 border border-slate-200/50 rounded-lg w-11 h-11 sm:w-12 sm:h-12 md:w-14 md:h-14 shadow-sm transition-colors duration-300">
                                    <span class="text-base sm:text-lg md:text-xl font-bold tracking-tight"
                                        :class="isUrgent ? 'text-[#DA2D3B]' : 'text-slate-800'"
                                        x-text="String(seconds).padStart(2, '0')">00</span>
                                    <span
                                        class="text-[8px] md:text-[9px] font-bold text-slate-400 tracking-wider uppercase mt-0.5">SEC</span>
                                </div>
                            </div>

                            <div x-show="!isOpen"
                                class="flex items-center justify-center bg-slate-100 border border-slate-200/80 rounded-lg px-5 py-3 text-center w-full min-h-[44px] sm:min-h-[48px] md:min-h-[56px] shadow-sm"
                                style="display: none;">
                                <span
                                    class="text-xs sm:text-sm font-bold text-slate-400 tracking-wide uppercase">Pendaftaran
                                    Ditutup</span>
                            </div>
                        </div>
                    @else
                        <!-- State Kosong / Belum ada periode -->
                        <div class="space-y-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-xs md:text-sm font-bold text-slate-800 tracking-tight">Belum ada
                                    periode pendaftaran aktif</span>
                                <span
                                    class="px-2 py-0.5 inline-flex items-center text-[11px] font-semibold rounded-md bg-slate-100 text-slate-600 ring-1 ring-slate-300">Tidak
                                    Aktif</span>
                            </div>
                            <p class="text-[11px] text-slate-500">
                                Silakan atur atau buat periode pendaftaran baru.
                            </p>
                            <div>
                                <button type="button" @click="openCreate()"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 text-[10px] font-semibold rounded-full border border-[#DA2D3B] bg-white hover:bg-red-50 text-[#DA2D3B] transition-all duration-200">
                                    <svg class="w-3 h-3 text-[#DA2D3B]" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                    </svg>
                                    Kelola Waktu (Edit)
                                </button>
                            </div>
                        </div>

                        <div class="flex flex-col items-start lg:items-end gap-1.5">
                            <div
                                class="flex items-center justify-center bg-slate-100 border border-slate-200/80 rounded-lg px-5 py-3 text-center w-full min-h-[44px] sm:min-h-[48px] md:min-h-[56px] shadow-sm">
                                <span
                                    class="text-xs sm:text-sm font-bold text-slate-400 tracking-wide uppercase">Pendaftaran
                                    Ditutup</span>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Filter -->
        <div class="filter-card">
            <form action="{{ route('admin.graduation-registrations.index') }}" method="GET"
                class="flex flex-wrap gap-2.5 items-end justify-between">

                <div class="flex flex-wrap gap-2.5 items-end flex-1">
                    <div class="min-w-[140px] max-w-[200px] flex-1">
                        <label class="campus-label">Cari NIM / Nama</label>
                        <input type="text" name="search" value="{{ request('search') }}"
                            placeholder="Ketik NIM atau nama..." class="campus-input">
                    </div>

                    <div class="min-w-[130px] max-w-[160px]">
                        <label class="campus-label">Periode Pendaftaran</label>
                        <select name="period" class="campus-select">
                            <option value="">Semua Periode</option>
                            @foreach ($periods as $p)
                                <option value="{{ $p }}" {{ request('period') == $p ? 'selected' : '' }}>
                                    {{ $p }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="min-w-[130px] max-w-[160px]">
                        <label class="campus-label">Status</label>
                        <select name="status" class="campus-select">
                            <option value="">Semua Status</option>
                            @foreach ($statuses as $val => $label)
                                <option value="{{ $val }}"
                                    {{ request('status') == $val ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="min-w-[140px] max-w-[200px]">
                        <label class="campus-label">Program Studi</label>
                        <select name="study_program" class="campus-select">
                            <option value="">Semua Prodi</option>
                            @foreach ($studyPrograms as $sp)
                                <option value="{{ $sp }}"
                                    {{ request('study_program') == $sp ? 'selected' : '' }}>{{ $sp }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="submit" class="btn btn-secondary">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            Filter
                        </button>
                       <a href="{{ route('admin.graduation-registrations.export', request()->all()) }}" 
                       class="inline-flex items-center gap-2.5 px-3.5 h-9 bg-emerald-600 hover:bg-emerald-700/50 text-white text-xs font-semibold rounded-lg border border-transparent shadow-sm transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Export Excel
                        </a>

                    </div>
                </div>

            </form>
        </div>

        <!-- Stats Cards -->
        <div class="campus-card mb-5 flex flex-wrap divide-x divide-slate-100 overflow-hidden">
            <div class="flex items-center gap-3 px-5 py-4 border-l-4 border-slate-400 flex-1 min-w-[160px]">
                <span class="text-2xl font-bold text-slate-800">{{ $stats->total ?? 0 }}</span>
                <span class="text-xs text-slate-500 font-medium">Total Pendaftaran</span>
            </div>
            <div class="flex items-center gap-3 px-5 py-4 border-l-4 border-slate-400 flex-1 min-w-[160px]">
                <span class="text-2xl font-bold text-slate-800">{{ $stats->submitted ?? 0 }}</span>
                <span class="text-xs text-slate-500 font-medium">Menunggu Review</span>
            </div>
            <div class="flex items-center gap-3 px-5 py-4 border-l-4 border-slate-400 flex-1 min-w-[160px]">
                <span class="text-2xl font-bold text-slate-800">{{ $stats->approved ?? 0 }}</span>
                <span class="text-xs text-slate-500 font-medium">Disetujui</span>
            </div>
            <div class="flex items-center gap-3 px-5 py-4 border-l-4 border-slate-400 flex-1 min-w-[160px]">
                <span class="text-2xl font-bold text-slate-800">{{ $stats->rejected ?? 0 }}</span>
                <span class="text-xs text-slate-500 font-medium">Ditolak</span>
            </div>
            <div class="flex items-center gap-3 px-5 py-4 border-l-4 border-slate-400 flex-1 min-w-[160px]">
                <span class="text-2xl font-bold text-slate-800">{{ $stats->needs_review ?? 0 }}</span>
                <span class="text-xs text-slate-500 font-medium">Revisi</span>
            </div>
        </div>

        <!-- Table -->
        <div class="campus-card">
            <div class="overflow-x-auto -mx-4 sm:mx-0 w-full" style="-webkit-overflow-scrolling: touch;">
                @php
                    $docColumns = [
                        'approval_sheet' => 'Lembar Pengesahan',
                        'final_thesis_book_full' => 'Buku TA Full',
                        'ithenticate_book_result' => 'Hasil i-Thenticate Buku TA',
                        'ithenticate_journal_result' => 'Hasil i-Thenticate Jurnal',
                        'library_clearance_letter' => 'SBKP (Surat Bebas Pustaka)',
                        'openlib_clearance_letter' => 'Surat Bebas Openlib',
                        'financial_clearance_letter' => 'Surat Bebas Keuangan',
                        'diploma_biodata_statement' => 'Scan Pernyataan Dummy Ijazah',
                        'revision_sheet_scan' => 'Scan Lembar Revisi',
                    ];
                @endphp
                <table class="campus-table w-full text-sm min-w-max">
                    <thead>
                        <tr class="border-b border-slate-200/80 bg-slate-50/60">
                            <th class="!py-1.5 !px-1 text-center !font-bold !text-slate-600 text-[9px] leading-tight min-w-[65px] max-w-[80px] align-middle whitespace-nowrap ">
                                NIM</th>
                            <th class="!py-1.5 !px-1 text-center !font-bold !text-slate-600 text-[9px] leading-tight min-w-[65px] max-w-[100px] align-middle whitespace-nowrap">
                                MAHASISWA</th>
                            @foreach ($docColumns as $docKey => $docLabel)
                                <th
                                    class="!py-1.5 !px-2 text-center font-medium text-slate-600 text-[9px] leading-tight min-w-[82px] max-w-[112px] align-middle whitespace-normal">
                                    {{ $docLabel }}
                                </th>
                            @endforeach
                            <th class="py-3 px-3 text-center font-bold text-slate-600 text-[9px] leading-tight min-w-[60px]  align-middle whitespace-nowrap">ACTION
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse($registrations as $reg)
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="py-2 px-2 font-medium text-slate-700  whitespace-nowrap align-middle text-xs">
                                    {{ $reg->student_number }}
                                </td>
                                <td class="py-2 px-2 align-middle">
                                    <div class="font-bold text-slate-800 text-xs">{{ $reg->student_name }}</div>
                                </td>

                                @foreach ($docColumns as $docKey => $docLabel)
                                    @php
                                        $doc = $reg->documents->firstWhere('document_type', $docKey);
                                    @endphp
                                    <td class="py-2 px-1 text-center align-middle">
                                        @if ($doc && $doc->status === 'verified')
                                            <a href="{{ route('graduation-registration-documents.preview', $doc) }}"
                                                target="_blank"
                                                class="inline-flex items-center justify-center hover:scale-110 transition-transform"
                                                title="{{ $doc->file_name }} (Terverifikasi)">
                                                <div
                                                    class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-[#00E676] text-slate-900 shadow-xs">
                                                    <svg class="w-3.5 h-3.5 text-slate-900 stroke-[3.5]"
                                                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            d="M5 13l4 4L19 7" />
                                                    </svg>
                                                </div>
                                            </a>
                                        @elseif($doc && $doc->status === 'rejected')
                                            <a href="{{ route('graduation-registration-documents.preview', $doc) }}"
                                                target="_blank"
                                                class="inline-flex items-center justify-center hover:scale-110 transition-transform"
                                                title="{{ $doc->file_name }} (Perlu Revisi{{ $doc->notes ? ': ' . $doc->notes : '' }})">
                                                <div
                                                    class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-rose-500 text-white shadow-xs">
                                                    <svg class="w-3.5 h-3.5 text-white stroke-[3]" fill="none"
                                                        stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
                                                </div>
                                            </a>
                                        @elseif($doc && $doc->status === 'pending')
                                            <a href="{{ route('graduation-registration-documents.preview', $doc) }}"
                                                target="_blank"
                                                class="inline-flex items-center justify-center hover:scale-110 transition-transform"
                                                title="{{ $doc->file_name }} (Menunggu Review)">
                                                <div
                                                    class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-amber-400 text-white shadow-xs">
                                                    <svg class="w-3.5 h-3.5 text-white stroke-[2.5]" fill="none"
                                                        stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            d="M12 6v6l4 2" />
                                                    </svg>
                                                </div>
                                            </a>
                                        @else
                                            <span class="text-slate-300 text-xs font-light">-</span>
                                        @endif
                                    </td>
                                @endforeach

                                <td class="py-3.5 px-3 text-center whitespace-nowrap align-middle">
                                    <div class="flex items-center justify-center gap-2">
                                        {{-- 👁 Detail Button --}}
                                        <a href="{{ route('admin.graduation-registrations.show', $reg) }}"
                                            class="inline-flex items-center justify-center text-slate-400 hover:text-slate-600 transition-colors"
                                            title="Lihat Detail">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    stroke-width="1.8"
                                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </a>

                                        {{-- Delete Button --}}
                                        <button type="button"
                                            @click="$dispatch('open-delete-reg-modal', { url: '{{ route('admin.graduation-registrations.destroy', $reg) }}', name: '{{ addslashes($reg->student_name) }}' })"
                                            class="inline-flex items-center justify-center text-slate-400 hover:text-rose-600 transition-colors cursor-pointer"
                                            title="Hapus Pengajuan">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    stroke-width="1.8"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="empty-state py-12 text-center">
                                    <div class="empty-state-icon mb-2">
                                        <svg class="w-12 h-12 mx-auto text-slate-300" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                        </svg>
                                    </div>
                                    <p class="empty-state-text text-sm text-slate-500">Belum ada pengajuan pendaftaran
                                        yudisium yang sesuai.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-5">{{ $registrations->links() }}</div>

        <!-- STANDARDIZED MODAL PENGATURAN PERIODE -->
        <div x-show="showPeriodModal" x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4 overflow-y-auto"
            style="display: none;">

            <div class="bg-white rounded-2xl max-w-xl w-full p-6 shadow-xl border border-slate-200 space-y-5 max-h-[90vh] overflow-y-auto"
                @click.away="showPeriodModal = false">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div>
                        <h3 class="text-base font-bold text-slate-800">Edit Periode Pendaftaran Yudisium</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Atur jadwal pembukaan, penutupan, dan periode aktif.
                        </p>
                    </div>
                    <button type="button" @click="showPeriodModal = false"
                        class="text-slate-400 hover:text-slate-600 p-1 rounded-lg hover:bg-slate-100 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Form Edit Periode -->
                <form :action="formUrl" method="POST" class="space-y-4">
                    @csrf
                    <template x-if="editMode">
                        <input type="hidden" name="_method" value="PUT">
                    </template>

                    <div>
                        <label class="campus-label">Nama Periode <span class="text-rose-500">*</span></label>
                        <input type="text" name="name" x-model="form.name" required
                            placeholder="Contoh: Periode I 2025/2026" class="campus-input">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="campus-label">Waktu Pembukaan (Buka) <span
                                    class="text-rose-500">*</span></label>
                            <input type="datetime-local" name="open_at" x-model="form.open_at" required
                                class="campus-input">
                        </div>
                        <div>
                            <label class="campus-label">Waktu Penutupan (Tutup) <span
                                    class="text-rose-500">*</span></label>
                            <input type="datetime-local" name="close_at" x-model="form.close_at" required
                                class="campus-input">
                        </div>
                    </div>

                    <div>
                        <label class="campus-label">Catatan Tambahan (Opsional)</label>
                        <input type="text" name="description" x-model="form.description"
                            placeholder="Instruksi khusus untuk mahasiswa..." class="campus-input">
                    </div>

                    <div class="flex items-center gap-2 pt-1">
                        <input type="checkbox" id="is_active_check" name="is_active" value="1"
                            x-model="form.is_active"
                            class="rounded border-slate-300 text-[#DA2D3B] focus:ring-[#DA2D3B]">
                        <label for="is_active_check" class="text-xs font-semibold text-slate-700">Jadikan sebagai
                            Periode Aktif saat ini</label>
                    </div>

                    <div class="pt-3 flex justify-end gap-2 border-t border-slate-100">
                        <button type="button" @click="showPeriodModal = false"
                            class="btn btn-secondary">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- STANDARDIZED MODAL KONFIRMASI HAPUS PENGAJUAN -->
        <div x-data="{ openDelete: false, deleteUrl: '', studentName: '' }" x-show="openDelete"
            @open-delete-reg-modal.window="openDelete = true; deleteUrl = $event.detail.url; studentName = $event.detail.name;"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4"
            style="display: none;" x-transition>
            <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-sm border border-slate-200"
                @click.away="openDelete = false">
                <div class="text-center">
                    <div
                        class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-rose-100 text-rose-600 mb-4">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-slate-800 mb-2">Konfirmasi Hapus</h3>
                    <p class="text-xs sm:text-sm text-slate-500 mb-6 font-normal">
                        Yakin ingin menghapus pengajuan atas nama <span class="font-semibold text-slate-800"
                            x-text="studentName"></span>? Tindakan ini tidak bisa dibatalkan.
                    </p>
                </div>
                <div class="flex items-center justify-end gap-3">
                    <button type="button" @click="openDelete = false"
                        class="btn btn-secondary w-full">Batal</button>
                    <form method="POST" class="w-full" @submit.prevent="$el.action = deleteUrl; $el.submit()">
                        @csrf
                        <input type="hidden" name="_method" value="DELETE">
                        <button type="submit"
                            class="btn btn-primary bg-rose-600 hover:bg-rose-700 w-full text-white">Ya, Hapus</button>
                    </form>
                </div>
            </div>
        </div>

    </div>
</x-app-layout>
