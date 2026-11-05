{{-- Component Panel Kanan: Display Data Kampus (100% Read-Only / Grab Otomatis) --}}
@props(['campusData' => [], 'showRetakenCourses' => true])

<div class="space-y-4">

    <!-- Judul TA -->
    <div class="campus-card p-4 space-y-3">
        <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider border-b border-slate-100 pb-2">
            Judul Tugas Akhir (Resmi)
        </h4>
        <div class="space-y-2.5 text-xs">
            <div>
                <span class="text-[11px] font-semibold text-slate-500 block mb-1">Bahasa Indonesia:</span>
                <div class="p-2.5 bg-slate-50 border border-slate-200/80 rounded-lg text-slate-800 font-medium leading-relaxed">
                    {{ $campusData['thesis_title_id'] ?? 'Belum ada judul Bahasa Indonesia.' }}
                </div>
            </div>
            <div>
                <span class="text-[11px] font-semibold text-slate-500 block mb-1">Bahasa Inggris:</span>
                <div class="p-2.5 bg-slate-50 border border-slate-200/80 rounded-lg text-slate-700 italic leading-relaxed">
                    {{ $campusData['thesis_title_en'] ?? 'No English title provided.' }}
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Card Pembimbing & Penguji -->
    <div class="campus-card p-4 space-y-3">
        <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider border-b border-slate-100 pb-2">
            Pembimbing & Penguji
        </h4>
        <div class="space-y-3 text-xs">
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Dosen Pembimbing</span>
                <div class="space-y-1">
                    <div class="flex justify-between items-center bg-slate-50 px-2.5 py-1.5 rounded-md">
                        <span class="text-slate-500 text-[11px]">Pembimbing 1</span>
                        <span class="font-medium text-slate-800">{{ isset($campusData['advisor_1_name']) ? $campusData['advisor_1_name'] . ' ('.$campusData['advisor_1_code'].')' : '-' }}</span>
                    </div>
                    <div class="flex justify-between items-center bg-slate-50 px-2.5 py-1.5 rounded-md">
                        <span class="text-slate-500 text-[11px]">Pembimbing 2</span>
                        <span class="font-medium text-slate-800">{{ isset($campusData['advisor_2_name']) ? $campusData['advisor_2_name'] . ' ('.$campusData['advisor_2_code'].')' : '-' }}</span>
                    </div>
                </div>
            </div>

            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Dosen Penguji</span>
                <div class="space-y-1">
                    <div class="flex justify-between items-center bg-slate-50 px-2.5 py-1.5 rounded-md">
                        <span class="text-slate-500 text-[11px]">Penguji 1</span>
                        <span class="font-medium text-slate-800">{{ isset($campusData['examiner_1_name']) ? $campusData['examiner_1_name'] . ' ('.$campusData['examiner_1_code'].')' : '-' }}</span>
                    </div>
                    <div class="flex justify-between items-center bg-slate-50 px-2.5 py-1.5 rounded-md">
                        <span class="text-slate-500 text-[11px]">Penguji 2</span>
                        <span class="font-medium text-slate-800">{{ isset($campusData['examiner_2_name']) ? $campusData['examiner_2_name'] . ' ('.$campusData['examiner_2_code'].')' : '-' }}</span>
                    </div>
                    <div class="flex justify-between items-center bg-slate-50 px-2.5 py-1.5 rounded-md">
                        <span class="text-slate-500 text-[11px]">Penguji 3</span>
                        <span class="font-medium text-slate-800">{{ isset($campusData['examiner_3_name']) ? $campusData['examiner_3_name'] . ' ('.$campusData['examiner_3_code'].')' : '-' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. Card Indikator Syarat Akademik -->
    <div class="campus-card p-4 space-y-3">
        <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider border-b border-slate-100 pb-2">
            Syarat Akademik Yudisium
        </h4>
        <div class="grid grid-cols-2 gap-2 text-xs">
            <!-- IPK -->
            <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-100 text-center">
                <span class="text-[11px] text-slate-500 block">IPK (Kumulatif)</span>
                <span class="text-lg font-bold text-slate-800 block mt-0.5">{{ isset($campusData['gpa']) ? number_format((float)$campusData['gpa'], 2) : (isset($campusData['ipk']) ? number_format((float)$campusData['ipk'], 2) : '-') }}</span>
                <span class="text-[10px] font-semibold {{ (float)($campusData['gpa'] ?? $campusData['ipk'] ?? 0) >= 2.0 ? 'text-emerald-600' : 'text-rose-600' }}">
                    {{ (float)($campusData['gpa'] ?? $campusData['ipk'] ?? 0) >= 2.0 ? 'Memenuhi' : 'Belum Memenuhi' }}
                </span>
            </div>

            <!-- SKS Lulus & Belum Lulus -->
            <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-100 text-center">
                <span class="text-[11px] text-slate-500 block">SKS Lulus / Belum</span>
                <span class="text-lg font-bold text-slate-800 block mt-0.5">
                    {{ $campusData['passed_credits'] ?? $campusData['sks_lulus'] ?? '-' }} / {{ $campusData['remaining_credits_db'] ?? $campusData['remaining_credits'] ?? $campusData['sks_belum_lulus'] ?? '0' }}
                </span>
                <span class="text-[10px] font-semibold {{ ($campusData['remaining_credits_db'] ?? $campusData['remaining_credits'] ?? $campusData['sks_belum_lulus'] ?? 0) == 0 ? 'text-emerald-600' : 'text-amber-600' }}">
                    {{ ($campusData['remaining_credits_db'] ?? $campusData['remaining_credits'] ?? $campusData['sks_belum_lulus'] ?? 0) == 0 ? 'Lulus Semua SKS' : 'Ada SKS Belum Lulus' }}
                </span>
            </div>

            <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-100 text-center">
                <span class="text-[11px] text-slate-500 block">Skor TAK</span>
                <span class="text-lg font-bold text-slate-800 block mt-0.5">{{ $campusData['tak_score'] ?? '-' }}</span>
                <span class="text-[10px] font-semibold {{ ($campusData['tak_score'] ?? 0) >= 60 ? 'text-emerald-600' : 'text-rose-600' }}">
                    {{ ($campusData['tak_score'] ?? 0) >= 60 ? 'Memenuhi (≥60)' : 'Belum Memenuhi (<60)' }}
                </span>
            </div>

            <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-100 text-center">
                <span class="text-[11px] text-slate-500 block">Skor EPRT</span>
                <span class="text-lg font-bold text-slate-800 block mt-0.5">{{ isset($campusData['eprt_score']) ? number_format($campusData['eprt_score'], 0) : '-' }}</span>
                <span class="text-[10px] font-semibold {{ (float) ($campusData['eprt_score'] ?? 0) >= 450 ? 'text-emerald-600' : 'text-rose-600' }}">
                    {{ (float) ($campusData['eprt_score'] ?? 0) >= 450 ? 'Memenuhi (≥450)' : 'Belum Memenuhi' }}
                </span>
            </div>

            <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-100 text-center">
                <span class="text-[11px] text-slate-500 block">Sidang Tingkat 3</span>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold mt-1 {{ ($campusData['level_3_exam_status'] ?? '') === 'passed' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                    {{ ($campusData['level_3_exam_status'] ?? '') === 'passed' ? 'Lulus' : 'Belum Lulus' }}
                </span>
            </div>

            <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-100 text-center">
                <span class="text-[11px] text-slate-500 block">Foto i-Basila</span>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold mt-1 {{ ($campusData['ibasila_photo_approval_status'] ?? '') === 'approved' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                    {{ ucfirst($campusData['ibasila_photo_approval_status'] ?? 'pending') }}
                </span>
            </div>
        </div>

        <div class="flex justify-between items-center pt-2 border-t border-slate-100 text-xs">
            <span class="text-slate-500">Approval SKPI</span>
            <span class="font-bold {{ ($campusData['skpi_approval_status'] ?? '') === 'approved' ? 'text-emerald-700' : 'text-rose-700' }}">
                {{ $campusData['skpi_approval_status'] ?? 'Belum ada data' }}
            </span>
        </div>

        <div class="flex justify-between items-center pt-2 border-t border-slate-100 text-xs">
            <span class="text-slate-500">Pembayaran Wisuda</span>
            <span class="font-bold {{ in_array(strtolower((string) ($campusData['payment_status'] ?? '')), ['lunas', 'paid', 'free', 'bebas'], true) ? 'text-emerald-700' : 'text-rose-700' }}">
                {{ strtoupper($campusData['payment_status'] ?? 'Belum ada data') }}
            </span>
        </div>

        <div class="flex justify-between items-center pt-2 border-t border-slate-100 text-xs">
            <span class="text-slate-500">Status Pembayaran BPP</span>
            <span class="font-bold text-slate-700">{{ $campusData['bpp_payment_status'] ?? 'Belum ada data' }}</span>
        </div>

        <div class="flex justify-between items-center pt-2 border-t border-slate-100 text-xs">
            <span class="text-slate-500">Tanggal Sidang TA</span>
            <span class="font-bold text-slate-800">{{ isset($campusData['exam_date']) ? \Carbon\Carbon::parse($campusData['exam_date'])->translatedFormat('d F Y') : '-' }}</span>
        </div>

        <div class="flex justify-between items-center pt-2 border-t border-slate-100 text-xs">
            <span class="text-slate-500">Lama Studi / Semester</span>
            <span class="font-bold text-slate-800">{{ $campusData['active_semester_db'] ?? $campusData['active_semester'] ?? '-' }}</span>
        </div>
    </div>

    <!-- 5. Card Warning Mata Kuliah Belum Lulus & Mengulang -->
    @if(isset($campusData['failed_courses']) && !empty($campusData['failed_courses']))
    <div class="p-3.5 bg-rose-50 border border-rose-200 rounded-xl space-y-2">
        <label class="text-xs font-bold text-rose-800 flex items-center gap-1.5">
            <svg class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            Mata Kuliah Belum Lulus ({{ count($campusData['failed_courses']) }})
        </label>
        <ul class="space-y-1 text-xs text-rose-700">
            @foreach($campusData['failed_courses'] as $course)
            <li class="flex items-center gap-2">
                <span class="font-mono bg-rose-100 px-1.5 py-0.5 rounded text-[10px] font-bold">{{ $course['course_code'] }}</span>
                <span class="truncate">{{ $course['course_name'] }}</span>
            </li>
            @endforeach
        </ul>
    </div>
    @endif

    @if($showRetakenCourses && isset($campusData['retaken_courses']) && !empty($campusData['retaken_courses']))
    <div class="p-3.5 bg-amber-50 border border-amber-200 rounded-xl space-y-2">
        <label class="text-xs font-bold text-amber-800 flex items-center gap-1.5">
            <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Mata Kuliah Mengulang ({{ count($campusData['retaken_courses']) }})
        </label>
        <ul class="space-y-1 text-xs text-amber-700">
            @foreach($campusData['retaken_courses'] as $course)
            <li class="flex items-center gap-2">
                <span class="font-mono bg-amber-100 px-1.5 py-0.5 rounded text-[10px] font-bold">{{ $course['course_code'] }}</span>
                <span class="truncate">{{ $course['course_name'] }} (Attempt {{ $course['attempt'] }})</span>
            </li>
            @endforeach
        </ul>
    </div>
    @endif
</div>
