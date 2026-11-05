<x-app-layout>
    <x-slot name="header">Detail Jadwal Ujian</x-slot>

    <!-- Header Profil / Ringkasan -->
    <div class="campus-card mb-5 overflow-hidden">
        <div class="bg-[#DA2D3B] px-6 py-8">
            <div class="flex items-center gap-6">
                <!-- Icon Calendar/Schedule -->
                <div class="flex-shrink-0">
                    <div class="w-20 h-20 bg-white/20 rounded-xl flex items-center justify-center border-2 border-white/30 shadow-lg text-white">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </div>
                </div>

                <!-- Info Utama -->
                <div class="text-white">
                    <h2 class="text-2xl font-bold">{{ $schedule->course->name ?? 'Mata Kuliah Tidak Ditemukan' }}</h2>
                    <p class="text-white/80 text-sm mt-1">{{ $schedule->course->course_code ?? '-' }}</p>
                    <div class="flex flex-wrap gap-3 mt-3">
                        <span class="px-3 py-1 bg-white/20 rounded-full text-xs font-medium">
                            Prodi: {{ $studyProgram ?? $schedule->course->study_program ?? '-' }}
                        </span>
                        <span class="px-3 py-1 bg-white/20 rounded-full text-xs font-medium">
                            Kelas: {{ $schedule->course_class ?? $schedule->course->class ?? '-' }}
                        </span>
                        <span class="px-3 py-1 bg-white/20 rounded-full text-xs font-medium">
                            {{ $schedule->course->credits ?? '0' }} SKS
                        </span>
                        <span class="px-3 py-1 bg-white/20 rounded-full text-xs font-medium">
                            Semester {{ $schedule->course->semester ?? '-' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Informasi Detail -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-5 items-stretch">
        
        <!-- Left Column -->
        <div class="flex flex-col gap-5 h-full">
            <!-- Waktu & Tempat Ujian -->
            <div class="campus-card p-6 flex-1 flex flex-col justify-between">
                <div>
                    <h3 class="text-base font-bold text-slate-800 mb-4 pb-2 border-b border-slate-100 flex items-center gap-2">
                        Waktu & Tempat Ujian
                    </h3>
                    <div class="space-y-3">
                        @php
                            $dayNames = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
                            $dayName = $schedule->exam_date ? ($dayNames[$schedule->exam_date->dayOfWeek] ?? '-') : '-';
                        @endphp
                        <div class="flex gap-3 py-1.5 border-b border-slate-50">
                            <span class="text-sm text-slate-400 w-36 flex-shrink-0">Hari & Tanggal</span>
                            <span class="text-sm font-semibold text-slate-700">
                                @if ($schedule->exam_date)
                                    {{ $dayName }}, {{ $schedule->exam_date->translatedFormat('d F Y') }}
                                @else
                                    -
                                @endif
                            </span>
                        </div>
                        <div class="flex gap-3 py-1.5 border-b border-slate-50">
                            <span class="text-sm text-slate-400 w-36 flex-shrink-0">Jam Pelaksanaan</span>
                            <span class="text-sm font-semibold text-slate-700">
                                @if ($schedule->start_time && $schedule->end_time)
                                    {{ \Carbon\Carbon::parse($schedule->start_time)->format('H:i') }} - {{ \Carbon\Carbon::parse($schedule->end_time)->format('H:i') }} WIB
                                @else
                                    -
                                @endif
                            </span>
                        </div>
                        <div class="flex gap-3 py-1.5 border-b border-slate-50">
                            <span class="text-sm text-slate-400 w-36 flex-shrink-0">Ruangan</span>
                            <span class="text-sm font-normal text-slate-700 flex flex-col">
                                @if ($schedule->participant_division === 'DIBAGI 2 (JIKA PESERTA KELAS > 30)' && !empty($schedule->room_2))
                                    <span>{{ $schedule->room_1 }}</span>
                                    <span class="mt-0.5">{{ $schedule->room_2 }}</span>
                                @else
                                    {{ $schedule->room_1 ?? '-' }}
                                @endif
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dosen & Pengawas -->
            <div class="campus-card p-6 flex-1 flex flex-col">
                <h3 class="text-base font-bold text-slate-800 mb-4 pb-2 border-b border-slate-100 flex items-center gap-2">
                    Informasi Dosen & Pengawas
                </h3>
                <div class="space-y-3">
                    <div class="flex gap-3 py-1.5 border-b border-slate-50">
                        <span class="text-sm text-slate-400 w-36 flex-shrink-0">Dosen Pengampu 1</span>
                        <span class="text-sm font-semibold text-slate-700">
                            {{ $schedule->lecturer_code }} 
                            @if($schedule->lecturer)
                                <span class="text-slate-400 font-normal">({{ $schedule->lecturer->name }})</span>
                            @endif
                        </span>
                    </div>
                    <div class="flex gap-3 py-1.5 border-b border-slate-50">
                        <span class="text-sm text-slate-400 w-36 flex-shrink-0">Dosen Pengampu 2</span>
                        <span class="text-sm font-semibold text-slate-700">
                            @if($schedule->lecturer_code_2)
                                {{ $schedule->lecturer_code_2 }}
                                @if($schedule->lecturer2)
                                    <span class="text-slate-400 font-normal">({{ $schedule->lecturer2->name }})</span>
                                @endif
                            @else
                                -
                            @endif
                        </span>
                    </div>
                    <div class="flex gap-3 py-1.5 border-b border-slate-50">
                        <span class="text-sm text-slate-400 w-36 flex-shrink-0">Nama Pengawas</span>
                        <span class="text-sm font-normal text-slate-700 flex flex-col">
                            @if ($schedule->participant_division === 'DIBAGI 2 (JIKA PESERTA KELAS > 30)' && !empty($schedule->proctor_name_2))
                                <span>{{ $schedule->proctor_name_1 ?? '-' }}</span>
                                <span class="mt-0.5">{{ $schedule->proctor_name_2 }}</span>
                            @else
                                {{ $schedule->proctor_name_1 ?? '-' }}
                            @endif
                        </span>
                    </div>
                    <div class="flex gap-3 py-1.5 border-b border-slate-50">
                        <span class="text-sm text-slate-400 w-36 flex-shrink-0">Tipe Pengawas</span>
                        <span class="text-sm font-semibold text-slate-700">
                            {{ $schedule->proctor_type ?? '-' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="flex flex-col gap-5 h-full">
            <!-- Konfigurasi Pelaksanaan Ujian -->
            <div class="campus-card p-6 flex-1 flex flex-col">
                <h3 class="text-base font-bold text-slate-800 mb-4 pb-2 border-b border-slate-100 flex items-center gap-2">
                    Konfigurasi Pelaksanaan Ujian
                </h3>
                <div class="space-y-3">
                    <div class="flex gap-3 py-1.5 border-b border-slate-50">
                        <span class="text-sm text-slate-400 w-36 flex-shrink-0">Jenis Assessment</span>
                        <span class="text-sm font-semibold text-slate-700">
                            {{ $schedule->assessment_type ?? '-' }}
                        </span>
                    </div>
                    <div class="flex gap-3 py-1.5 border-b border-slate-50">
                        <span class="text-sm text-slate-400 w-36 flex-shrink-0">Metode Pelaksanaan</span>
                        <span class="text-sm font-semibold text-slate-700">
                            {{ $schedule->execution_method ?? '-' }}
                        </span>
                    </div>
                    <div class="flex gap-3 py-1.5 border-b border-slate-50">
                        <span class="text-sm text-slate-400 w-36 flex-shrink-0">Waktu Pelaksanaan</span>
                        <span class="text-sm font-semibold text-slate-700">
                            {{ $schedule->execution_time ?? '-' }}
                        </span>
                    </div>
                    <div class="flex gap-3 py-1.5 border-b border-slate-50">
                        <span class="text-sm text-slate-400 w-36 flex-shrink-0">Pembagian Peserta Ujian</span>
                        <span class="text-sm font-semibold text-slate-700">
                            {{ $schedule->participant_division ?? '-' }}
                        </span>
                    </div>
                    <div class="flex gap-3 py-1.5 border-b border-slate-50">
                        <span class="text-sm text-slate-400 w-36 flex-shrink-0">Soal Ujian</span>
                        <span class="text-sm font-semibold text-slate-700">
                            {{ $schedule->question_type ?? '-' }}
                        </span>
                    </div>
                    <div class="flex gap-3 py-1.5 border-b border-slate-50">
                        <span class="text-sm text-slate-400 w-36 flex-shrink-0">Lembar Jawaban</span>
                        <span class="text-sm font-semibold text-slate-700">
                            {{ $schedule->answer_sheet_type ?? '-' }}
                        </span>
                    </div>
                    <div class="flex gap-3 py-1.5 border-b border-slate-50">
                        <span class="text-sm text-slate-400 w-36 flex-shrink-0">Kertas Buram</span>
                        <span class="text-sm font-semibold text-slate-700">
                            {{ $schedule->needs_scratch_paper ?? '-' }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Tautan & Aturan Tambahan -->
            <div class="campus-card p-6 flex-1 flex flex-col justify-between">
                <div>
                    <h3 class="text-base font-bold text-slate-800 mb-4 pb-2 border-b border-slate-100 flex items-center gap-2">
                        Tautan & Aturan Tambahan
                    </h3>
                    <div class="space-y-4">
                        <div>
                            <span class="text-xs font-semibold text-slate-400 block mb-1">URL Soal Ujian</span>
                            @if($schedule->exam_question_url)
                                <a href="{{ $schedule->exam_question_url }}" target="_blank" class="inline-flex items-center text-sm font-semibold text-[#DA2D3B] hover:underline gap-1">
                                    <span>Buka Soal Ujian</span>
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                    </svg>
                                </a>
                            @else
                                <span class="text-sm font-medium text-slate-500">-</span>
                            @endif
                        </div>
                        <div>
                            <span class="text-xs font-semibold text-slate-400 block mb-1">URL Lembar Jawaban Custom</span>
                            @if($schedule->custom_answer_sheet_url)
                                <a href="{{ $schedule->custom_answer_sheet_url }}" target="_blank" class="inline-flex items-center text-sm font-semibold text-[#DA2D3B] hover:underline gap-1">
                                    <span>Buka Lembar Jawaban</span>
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                    </svg>
                                </a>
                            @else
                                <span class="text-sm font-medium text-slate-500">-</span>
                            @endif
                        </div>
                        <div>
                            <span class="text-xs font-semibold text-slate-400 block mb-1">Aturan Ujian</span>
                            <div class="text-sm font-medium text-slate-700 bg-slate-50 border border-slate-100 rounded-xl p-3 min-h-[80px]">
                                @if($schedule->exam_rules)
                                    <span class="whitespace-pre-line leading-relaxed">{{ $schedule->exam_rules }}</span>
                                @else
                                    <span class="text-slate-400 font-normal">Tidak ada aturan khusus.</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Tombol Aksi Bawah -->
    <div class="flex items-center justify-between mt-6">
        <a href="{{ route('exam_schedules.index') }}" class="btn btn-secondary flex items-center gap-1.5">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Kembali ke Daftar
        </a>

        <div class="flex items-center gap-2">
            <a href="{{ route('exam-schedules.export-pdf', $schedule->id) }}"
               class="btn btn-danger"
               id="btn-export-pdf">
               <i class="icon-print"></i> Export PDF
            </a>

            @can('jadwal_update')
            <a href="{{ route('exam_schedules.edit', $schedule->id) }}" class="btn btn-primary">
                Edit Jadwal
            </a>
            @endcan
        </div>
    </div>

    <script>
        document.getElementById('btn-export-pdf').addEventListener('click', function(e) {
            var btn = this;
            var originalHtml = btn.innerHTML;
            
            btn.innerHTML = 'Menyiapkan PDF...';
            btn.style.pointerEvents = 'none';
            btn.style.opacity = '0.6';
            
            setTimeout(function() {
                btn.innerHTML = originalHtml;
                btn.style.pointerEvents = 'auto';
                btn.style.opacity = '1';
            }, 3000);
        });
    </script>
</x-app-layout>
