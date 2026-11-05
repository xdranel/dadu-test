<x-app-layout>
    <x-slot name="header">Pengajuan Banding Nilai</x-slot>

    @if(session('success'))
    <div class="mb-5 flex items-start gap-3 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl flash-message">
        <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <p class="text-xs sm:text-sm font-medium">{{ session('success') }}</p>
    </div>
    @endif

    <div x-data="{
        showPeriodModal: false,
        editMode: false,
        formUrl: '{{ route("admin.grade-appeal-periods.store") }}',
        form: { name: '', open_at: '', close_at: '', description: '', is_active: true },
        openCreate() {
            this.editMode = false;
            this.formUrl = '{{ route("admin.grade-appeal-periods.store") }}';
            this.form = { name: '', open_at: '', close_at: '', description: '', is_active: true };
            this.showPeriodModal = true;
        },
        openEdit(period) {
            this.editMode = true;
            this.formUrl = '/dashboard/admin/grade-appeal-periods/' + period.id;
            this.form = {
                name: period.name,
                open_at: period.open_at ? period.open_at.replace(' ', 'T').substring(0, 16) : '',
                close_at: period.close_at ? period.close_at.replace(' ', 'T').substring(0, 16) : '',
                description: period.description || '',
                is_active: Boolean(period.is_active)
            };
            this.showPeriodModal = true;
        },
        isMinimized: localStorage.getItem('appeal_countdown_minimized') === 'true',
        toggleMinimize() {
            this.isMinimized = !this.isMinimized;
            localStorage.setItem('appeal_countdown_minimized', this.isMinimized);
        },
        openAt: '{{ $activePeriod?->open_at?->toIso8601String() }}',
        closeAt: '{{ $activePeriod?->close_at?->toIso8601String() }}',
        days: 0, hours: 0, minutes: 0, seconds: 0,
        isOpen: false, isUrgent: false, interval: null,
        updateCountdown() {
            if (!this.closeAt || !this.openAt) { this.isOpen = false; this.isUrgent = false; return; }
            const start = new Date(this.openAt).getTime();
            const target = new Date(this.closeAt).getTime();
            const now = new Date().getTime();
            this.isOpen = now >= start && now <= target;
            if (!this.isOpen) { this.days = 0; this.hours = 0; this.minutes = 0; this.seconds = 0; this.isUrgent = false; return; }
            const diff = target - now;
            this.isUrgent = diff < 24 * 60 * 60 * 1000;
            this.days = Math.floor(diff / (1000 * 60 * 60 * 24));
            this.hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            this.minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            this.seconds = Math.floor((diff % (1000 * 60)) / 1000);
        },
        initCountdown() { this.updateCountdown(); this.interval = setInterval(() => this.updateCountdown(), 1000); }
    }"
    x-init="initCountdown()">

        <!-- Countdown Banner Card -->
        <div>
            <!-- State: Minimized -->
            <div x-show="isMinimized"
                 x-transition:enter="transition ease-out duration-300 transform"
                 x-transition:enter-start="opacity-0 -translate-y-2"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-200 transform"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 -translate-y-2"
                 class="mb-4 flex items-center justify-between bg-white border border-slate-200 rounded-xl px-4 py-2.5 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs font-bold text-slate-800 tracking-tight">
                            {{ $activePeriod ? $activePeriod->name : 'Periode Banding Nilai' }}
                        </span>
                        @if($activePeriod)
                            <span x-show="isOpen" class="px-2 py-0.5 inline-flex items-center text-[10px] font-semibold rounded-md bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/10">Buka</span>
                            <span x-show="!isOpen" class="px-2 py-0.5 inline-flex items-center text-[10px] font-semibold rounded-md bg-rose-50 text-rose-700 ring-1 ring-rose-600/10" style="display:none;">Tutup</span>
                        @else
                            <span class="px-2 py-0.5 inline-flex items-center text-[10px] font-semibold rounded-md bg-slate-100 text-slate-600 ring-1 ring-slate-300">Belum Diset</span>
                        @endif
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    @if($activePeriod)
                        <button type="button" @click="openEdit({{ json_encode($activePeriod) }})"
                                class="inline-flex items-center gap-1 px-2.5 py-1 text-[10px] font-semibold rounded-full border border-[#DA2D3B] bg-white hover:bg-red-50 text-[#DA2D3B] transition-all duration-200">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                            </svg>
                            Kelola Waktu
                        </button>
                    @else
                        <button type="button" @click="openCreate()"
                                class="inline-flex items-center gap-1 px-2.5 py-1 text-[10px] font-semibold rounded-full border border-[#DA2D3B] bg-white hover:bg-red-50 text-[#DA2D3B] transition-all duration-200">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                            Buat Periode
                        </button>
                    @endif
                    <button type="button" @click="toggleMinimize()"
                            class="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors" title="Maximize Card">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- State: Maximized -->
            <div x-show="!isMinimized"
                 x-transition:enter="transition ease-out duration-300 transform"
                 x-transition:enter-start="opacity-0 -translate-y-4 max-h-0 overflow-hidden"
                 x-transition:enter-end="opacity-100 translate-y-0 max-h-[500px]"
                 x-transition:leave="transition ease-in duration-200 transform"
                 x-transition:leave-start="opacity-100 translate-y-0 max-h-[500px]"
                 x-transition:leave-end="opacity-0 -translate-y-4 max-h-0 overflow-hidden"
                 class="mb-4 p-4 bg-white border border-slate-200 rounded-xl shadow-sm hover:shadow-md transition-all duration-300">

                <div class="flex items-center justify-between border-b border-slate-100 pb-2 mb-3">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Menuju Penutupan Pengajuan</span>
                    <button type="button" @click="toggleMinimize()"
                            class="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors" title="Minimize Card">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                        </svg>
                    </button>
                </div>

                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    @if($activePeriod)
                        <div class="space-y-1.5">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-xs md:text-sm font-bold text-slate-800 tracking-tight">{{ $activePeriod->name }}</span>
                                <span x-show="isOpen" class="px-2 py-0.5 inline-flex items-center text-[11px] font-semibold rounded-md bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/10">Buka</span>
                                <span x-show="!isOpen" class="px-2 py-0.5 inline-flex items-center text-[11px] font-semibold rounded-md bg-rose-50 text-rose-700 ring-1 ring-rose-600/10" style="display:none;">Tutup</span>
                            </div>
                            <p class="text-[11px] text-slate-500">
                                Waktu Pengajuan: {{ $activePeriod->open_at?->translatedFormat('d M Y, H:i') }} WIB s.d. {{ $activePeriod->close_at?->translatedFormat('d M Y, H:i') }} WIB
                            </p>
                            <div class="pt-1">
                                <button type="button" @click="openEdit({{ json_encode($activePeriod) }})"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 text-[10px] font-semibold rounded-full border border-[#DA2D3B] bg-white hover:bg-red-50 text-[#DA2D3B] transition-all duration-200">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                    </svg>
                                    Kelola Waktu (Edit)
                                </button>
                            </div>
                        </div>

                        <div class="flex flex-col items-start lg:items-end gap-1.5">
                            <div x-show="isOpen" class="flex items-center gap-1 sm:gap-1.5">
                                <div class="flex flex-col items-center justify-center bg-slate-100 border border-slate-200/50 rounded-lg w-11 h-11 sm:w-12 sm:h-12 md:w-14 md:h-14 shadow-sm">
                                    <span class="text-base sm:text-lg md:text-xl font-bold tracking-tight" :class="isUrgent ? 'text-[#DA2D3B]' : 'text-slate-800'" x-text="String(days).padStart(2,'0')">00</span>
                                    <span class="text-[8px] md:text-[9px] font-bold text-slate-400 tracking-wider uppercase mt-0.5">DAYS</span>
                                </div>
                                <span class="text-slate-300 font-bold text-sm sm:text-base">:</span>
                                <div class="flex flex-col items-center justify-center bg-slate-100 border border-slate-200/50 rounded-lg w-11 h-11 sm:w-12 sm:h-12 md:w-14 md:h-14 shadow-sm">
                                    <span class="text-base sm:text-lg md:text-xl font-bold tracking-tight" :class="isUrgent ? 'text-[#DA2D3B]' : 'text-slate-800'" x-text="String(hours).padStart(2,'0')">00</span>
                                    <span class="text-[8px] md:text-[9px] font-bold text-slate-400 tracking-wider uppercase mt-0.5">HOURS</span>
                                </div>
                                <span class="text-slate-300 font-bold text-sm sm:text-base">:</span>
                                <div class="flex flex-col items-center justify-center bg-slate-100 border border-slate-200/50 rounded-lg w-11 h-11 sm:w-12 sm:h-12 md:w-14 md:h-14 shadow-sm">
                                    <span class="text-base sm:text-lg md:text-xl font-bold tracking-tight" :class="isUrgent ? 'text-[#DA2D3B]' : 'text-slate-800'" x-text="String(minutes).padStart(2,'0')">00</span>
                                    <span class="text-[8px] md:text-[9px] font-bold text-slate-400 tracking-wider uppercase mt-0.5">MIN</span>
                                </div>
                                <span class="text-slate-300 font-bold text-sm sm:text-base">:</span>
                                <div class="flex flex-col items-center justify-center bg-slate-100 border border-slate-200/50 rounded-lg w-11 h-11 sm:w-12 sm:h-12 md:w-14 md:h-14 shadow-sm">
                                    <span class="text-base sm:text-lg md:text-xl font-bold tracking-tight" :class="isUrgent ? 'text-[#DA2D3B]' : 'text-slate-800'" x-text="String(seconds).padStart(2,'0')">00</span>
                                    <span class="text-[8px] md:text-[9px] font-bold text-slate-400 tracking-wider uppercase mt-0.5">SEC</span>
                                </div>
                            </div>
                            <div x-show="!isOpen" class="flex items-center justify-center bg-slate-100 border border-slate-200/80 rounded-lg px-5 py-3 text-center w-full min-h-[44px] sm:min-h-[48px] md:min-h-[56px] shadow-sm" style="display:none;">
                                <span class="text-xs sm:text-sm font-bold text-slate-400 tracking-wide uppercase">Pengajuan Ditutup</span>
                            </div>
                        </div>
                    @else
                        <div class="space-y-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-xs md:text-sm font-bold text-slate-800 tracking-tight">Belum ada periode banding nilai aktif</span>
                                <span class="px-2 py-0.5 inline-flex items-center text-[11px] font-semibold rounded-md bg-slate-100 text-slate-600 ring-1 ring-slate-300">Tidak Aktif</span>
                            </div>
                            <p class="text-[11px] text-slate-500">Silakan atur atau buat periode banding nilai baru.</p>
                            <div class="pt-1">
                                <button type="button" @click="openCreate()"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 text-[10px] font-semibold rounded-full border border-[#DA2D3B] bg-white hover:bg-red-50 text-[#DA2D3B] transition-all duration-200">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                    </svg>
                                    Buat Periode Baru
                                </button>
                            </div>
                        </div>
                        <div class="flex flex-col items-start lg:items-end gap-1.5">
                            <div class="flex items-center justify-center bg-slate-100 border border-slate-200/80 rounded-lg px-5 py-3 text-center w-full min-h-[44px] sm:min-h-[48px] md:min-h-[56px] shadow-sm">
                                <span class="text-xs sm:text-sm font-bold text-slate-400 tracking-wide uppercase">Pengajuan Ditutup</span>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-card">
            <form method="GET" action="{{ route('admin.grade-appeals.index') }}"
                  class="flex flex-wrap gap-2.5 items-end">
                <div class="min-w-[160px] max-w-[280px] flex-1">
                    <label class="campus-label">Cari Nama / NIM / Mata Kuliah</label>
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="Ketik nama, NIM, atau mata kuliah..."
                           class="campus-input">
                </div>
                <div class="min-w-[130px]">
                    <label class="campus-label">Status</label>
                    <select name="status" class="campus-select">
                        <option value="">Semua Status</option>
                        <option value="pending"  {{ request('status') == 'pending'  ? 'selected' : '' }}>Menunggu</option>
                        <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Disetujui</option>
                        <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Ditolak</option>
                    </select>
                </div>
                <div class="flex items-center gap-1.5">
                    <button type="submit" class="btn btn-secondary">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        Filter
                    </button>
                    @if(request('search') || request('status'))
                        <a href="{{ route('admin.grade-appeals.index') }}" class="btn btn-secondary">Reset</a>
                    @endif
                </div>
            </form>
        </div>

        <!-- Stats Cards -->
        <div class="campus-card mb-5 flex flex-wrap divide-x divide-slate-100 overflow-hidden">
            <div class="flex items-center gap-3 px-5 py-4 border-l-4 border-slate-400 flex-1 min-w-[160px]">
                <span class="text-2xl font-bold text-slate-800">{{ $stats->total }}</span>
                <span class="text-xs text-slate-500 font-medium">Total Pengajuan</span>
            </div>
            <div class="flex items-center gap-3 px-5 py-4 border-l-4 border-slate-400 flex-1 min-w-[160px]">
                <span class="text-2xl font-bold text-slate-800">{{ $stats->pending }}</span>
                <span class="text-xs text-slate-500 font-medium">Menunggu Review</span>
            </div>
            <div class="flex items-center gap-3 px-5 py-4 border-l-4 border-slate-400 flex-1 min-w-[160px]">
                <span class="text-2xl font-bold text-slate-800">{{ $stats->approved }}</span>
                <span class="text-xs text-slate-500 font-medium">Disetujui</span>
            </div>
            <div class="flex items-center gap-3 px-5 py-4 border-l-4 border-slate-400 flex-1 min-w-[160px]">
                <span class="text-2xl font-bold text-slate-800">{{ $stats->rejected }}</span>
                <span class="text-xs text-slate-500 font-medium">Ditolak</span>
            </div>
        </div>

        <!-- Table -->
        <div class="campus-card overflow-hidden">
            @if($appeals->count() > 0)
            <div class="overflow-x-auto">
                <table class="campus-table">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Mahasiswa</th>
                            <th>Mata Kuliah</th>
                            <th>Prodi</th>
                            <th>Status</th>
                            <th class="!text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($appeals as $i => $appeal)
                        <tr>
                            <td>
                                <span class="text-xs text-slate-700 font-medium">{{ $appeal->submitted_at?->translatedFormat('d M Y') }}</span>
                            </td>
                            <td>
<span class="text-xs font-medium text-slate-700">{{ $appeal->student_name }}</span>
                                <br>
                                <span class="text-[11px] text-slate-400">{{ $appeal->student_number }}</span>
                            </td>
                            <td>
                                <span class="text-xs font-medium text-slate-700 capitalize">{{ $appeal->course_name_manual ?? $appeal->course_name ?? '-' }}</span>
                                @if($appeal->course_code)
                                <br>
                                <span class="text-[11px] text-slate-400">{{ $appeal->course_code }}</span>
                                @endif
                            </td>
<td class="text-xs font-medium text-slate-700">{{ $appeal->study_program ?: '-' }}</td>
                            <td class="text-xs font-semibold">
                                <span class="{{ match($appeal->status) {
                                    'pending' => 'text-amber-600',
                                    'approved' => 'text-emerald-600',
                                    'rejected' => 'text-rose-600',
                                    default => 'text-slate-500',
                                } }}">{{ $appeal->status_label }}</span>
                            </td>
                            <td class="text-center align-middle">
                                <a href="{{ route('admin.grade-appeals.show', $appeal) }}"
                                   class="btn btn-secondary text-xs h-7 px-2.5">Detail</a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($appeals->hasPages())
            <div class="px-5 py-3 border-t border-slate-100">
                {{ $appeals->withQueryString()->links() }}
            </div>
            @endif

            @else
            <div class="empty-state">
                <svg class="w-12 h-12 mx-auto text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <p class="empty-state-text mt-3">Belum ada pengajuan banding nilai.</p>
            </div>
            @endif
        </div>

        <!-- Modal: Kelola Periode -->
        <div x-show="showPeriodModal"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4 overflow-y-auto"
             style="display: none;">
            <div class="bg-white rounded-2xl max-w-xl w-full p-6 shadow-xl border border-slate-200 space-y-5 max-h-[90vh] overflow-y-auto"
                 @click.away="showPeriodModal = false">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div>
                        <h3 class="text-base font-bold text-slate-800">
                            <span x-show="!editMode">Tambah Periode Banding Nilai</span>
                            <span x-show="editMode">Edit Periode Banding Nilai</span>
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">Atur jadwal pembukaan dan penutupan periode.</p>
                    </div>
                    <button type="button" @click="showPeriodModal = false"
                            class="text-slate-400 hover:text-slate-600 p-1 rounded-lg hover:bg-slate-100 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <form method="POST" :action="formUrl" class="space-y-4">
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
                            <label class="campus-label">Waktu Pembukaan <span class="text-rose-500">*</span></label>
                            <input type="datetime-local" name="open_at" x-model="form.open_at" required class="campus-input">
                        </div>
                        <div>
                            <label class="campus-label">Waktu Penutupan <span class="text-rose-500">*</span></label>
                            <input type="datetime-local" name="close_at" x-model="form.close_at" required class="campus-input">
                        </div>
                    </div>

                    <div>
                        <label class="campus-label">Catatan Tambahan (Opsional)</label>
                        <input type="text" name="description" x-model="form.description"
                               placeholder="Instruksi khusus untuk mahasiswa..." class="campus-input">
                    </div>

                    <div class="flex items-center gap-2 pt-1">
                        <input type="checkbox" id="is_active_appeal" name="is_active" value="1"
                               x-model="form.is_active"
                               class="rounded border-slate-300 text-[#DA2D3B] focus:ring-[#DA2D3B]">
                        <label for="is_active_appeal" class="text-xs font-semibold text-slate-700">
                            Jadikan sebagai Periode Aktif saat ini
                        </label>
                    </div>

                    <div class="pt-3 flex justify-end gap-2 border-t border-slate-100">
                        <button type="button" @click="showPeriodModal = false" class="btn btn-secondary">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <span x-show="!editMode">Simpan Periode</span>
                            <span x-show="editMode">Simpan Perubahan</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</x-app-layout>
