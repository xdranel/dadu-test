<x-app-layout>
    <x-slot name="header">Detail Banding Nilai</x-slot>

    <!-- Success Flash -->
    @if(session('success'))
    <div class="mb-5 flex items-start gap-3 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl flash-message">
        <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <p class="text-xs sm:text-sm font-medium">{{ session('success') }}</p>
    </div>
    @endif

    <!-- Back Button + Status -->
    <div class="flex items-center justify-between mb-5">
        <a href="{{ route('admin.grade-appeals.index') }}" class="btn btn-secondary">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Kembali
        </a>
        <span class="badge {{ $appeal->status_badge }} text-xs px-3 py-1">
            {{ $appeal->status_label }}
        </span>
    </div>

    <!-- Card: Data Mahasiswa -->
    <div class="form-section mb-5" x-data="{ open: true }">
        <div class="form-section-header" @click="open = !open">
            <span class="form-section-title">
                <svg class="w-[18px] h-[18px] text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                Data Mahasiswa
            </span>
            <svg class="w-4 h-4 text-slate-400 transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </div>
        <div class="form-section-body" x-show="open" x-transition>
            <div class="space-y-0">
                <div class="detail-row">
                    <span class="detail-label">Nama</span>
                    <span class="detail-value">{{ $appeal->student_name }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">NIM</span>
                    <span class="detail-value font-medium">{{ $appeal->student_number }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Program Studi</span>
                    <span class="detail-value">{{ $appeal->study_program }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Card: Data Pengajuan -->
    <div class="form-section mb-5" x-data="{ open: true }">
        <div class="form-section-header" @click="open = !open">
            <span class="form-section-title">
                <svg class="w-[18px] h-[18px] text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Data Pengajuan Banding
            </span>
            <svg class="w-4 h-4 text-slate-400 transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </div>
        <div class="form-section-body" x-show="open" x-transition>
            <div class="space-y-0">
                <div class="detail-row">
                    <span class="detail-label">Mata Kuliah</span>
                    <span class="detail-value">
                        @if($appeal->course_name_manual)
                            {{ $appeal->course_name_manual }}
                        @else
                            <span class="font-semibold">{{ $appeal->course_name }}</span>
                            @if($appeal->course_code)
                            <span class="text-slate-400 text-xs ml-1">({{ $appeal->course_code }})</span>
                            @endif
                        @endif
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Tanggal Pengajuan</span>
                    <span class="detail-value">{{ $appeal->submitted_at?->translatedFormat('l, d F Y - H:i') }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Card: Evidence File -->
    <div class="form-section mb-5" x-data="{ open: true }">
        <div class="form-section-header" @click="open = !open">
            <span class="form-section-title">
                <svg class="w-[18px] h-[18px] text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                </svg>
                Dokumen Evidence
            </span>
            <svg class="w-4 h-4 text-slate-400 transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </div>
        <div class="form-section-body" x-show="open" x-transition>
            <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-lg border border-slate-200">
                <div class="w-10 h-10 rounded-lg bg-rose-100 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-semibold text-slate-800 truncate">{{ $appeal->evidence_file_name }}</p>
                    <p class="text-[11px] text-slate-400">PDF Document</p>
                </div>
                <a href="{{ asset('storage/' . $appeal->evidence_file_path) }}"
                   target="_blank"
                   class="btn btn-secondary text-[11px] h-7 px-2.5 flex-shrink-0">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Unduh
                </a>
            </div>
        </div>
    </div>

    <!-- Card: Review Form (only if pending) -->
    @if($appeal->status === 'pending')
    <div class="form-section mb-5" x-data="{ open: true, action: '' }">
        <div class="form-section-header" @click="open = !open">
            <span class="form-section-title">
                <svg class="w-[18px] h-[18px] text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                </svg>
                Review Pengajuan
            </span>
            <svg class="w-4 h-4 text-slate-400 transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </div>
        <div class="form-section-body" x-show="open" x-transition>
            <form action="{{ route('admin.grade-appeals.review', $appeal) }}" method="POST">
                @csrf
                <input type="hidden" name="status" :value="action">

                <div class="mb-4">
                    <label class="campus-label">Catatan (opsional)</label>
                    <textarea name="admin_notes" rows="3" class="campus-input h-auto py-2" placeholder="Tambahkan catatan review...">{{ old('admin_notes') }}</textarea>
                </div>

                <div class="flex items-center gap-3">
                    <button type="submit" @click="action = 'approved'" class="btn btn-success px-4">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Setujui
                    </button>
                    <button type="submit" @click="action = 'rejected'" class="btn bg-rose-600 text-white hover:bg-rose-700 focus:ring-rose-500 px-4">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        Tolak
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <!-- Card: Review Result (if already reviewed) -->
    @if($appeal->status !== 'pending')
    <div class="form-section mb-5" x-data="{ open: true }">
        <div class="form-section-header" @click="open = !open">
            <span class="form-section-title">
                <svg class="w-[18px] h-[18px] text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                </svg>
                Hasil Review
            </span>
            <svg class="w-4 h-4 text-slate-400 transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </div>
        <div class="form-section-body" x-show="open" x-transition>
            <div class="space-y-0">
                <div class="detail-row">
                    <span class="detail-label">Keputusan</span>
                    <span class="detail-value">
                        <span class="badge {{ $appeal->status_badge }}">{{ $appeal->status_label }}</span>
                    </span>
                </div>
                @if($appeal->admin_notes)
                <div class="detail-row">
                    <span class="detail-label">Catatan</span>
                    <span class="detail-value">{{ $appeal->admin_notes }}</span>
                </div>
                @endif
                @if($appeal->reviewer)
                <div class="detail-row">
                    <span class="detail-label">Direview oleh</span>
                    <span class="detail-value">{{ $appeal->reviewer->name }}</span>
                </div>
                @endif
                @if($appeal->reviewed_at)
                <div class="detail-row">
                    <span class="detail-label">Tanggal Review</span>
                    <span class="detail-value">{{ $appeal->reviewed_at->translatedFormat('l, d F Y — H:i') }} WIB</span>
                </div>
                @endif
            </div>
        </div>
    </div>
    @endif
</x-app-layout>
