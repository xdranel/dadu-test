<x-app-layout>
    <x-slot name="header">Profil Dosen (Lecturer)</x-slot>

    <!-- Header Profil-->
    <div class="campus-card mb-5 overflow-hidden">
        <div class="bg-[#DA2D3B] px-6 py-8">
            <div class="flex items-center gap-6">

                <!-- Avatar/Icon Placeholder -->
                <div class="flex-shrink-0">
                    <div class="w-24 h-24 bg-white/20 rounded-full flex items-center justify-center border-2 border-white/30 shadow-lg text-white">
                        <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                    </div>
                </div>

                <!-- Info Utama -->
                <div class="text-white">
                    <h2 class="text-2xl font-bold">
                        {{ $lecturer->front_title ? $lecturer->front_title . ' ' : '' }}{{ $lecturer->name }}{{ $lecturer->back_title ? ', ' . $lecturer->back_title : '' }}
                    </h2>
                    <p class="text-white/80 text-sm mt-1">{{ $lecturer->lecturer_code }}</p>
                    <div class="flex flex-wrap gap-3 mt-3">
                        <span class="px-3 py-1 bg-white/20 rounded-full text-xs font-medium">
                            {{ $lecturer->study_program ?? 'Belum ada Prodi' }}
                        </span>
                        <span class="px-3 py-1 bg-white/20 rounded-full text-xs font-medium">
                            {{ $lecturer->employment_status ?? 'Status Kepegawaian N/A' }}
                        </span>
                        @if($lecturer->active_status)
                        <span class="px-3 py-1 bg-emerald-400/30 text-emerald-100 rounded-full text-xs font-bold">
                            {{ $lecturer->active_status }}
                        </span>
                        @endif
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Detail Data Dosen -->
    <div class="campus-card p-6">
        <h3 class="text-base font-semibold text-slate-800 mb-4 pb-2 border-b border-slate-100">
            Informasi Pribadi & Kepegawaian
        </h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-3">
            @php
                $fields = [
                    'Nama Lengkap' => ($lecturer->front_title ? $lecturer->front_title . ' ' : '') . $lecturer->name . ($lecturer->back_title ? ', ' . $lecturer->back_title : ''),
                    'Kode Dosen' => $lecturer->lecturer_code ?? '-',
                    'Status Kepegawaian' => $lecturer->employment_status ?? '-',
                    'Status Aktif' => $lecturer->active_status ?? '-',
                    'NIP YPT' => $lecturer->nip_ypt ?? '-',
                    'NIDN' => $lecturer->nidn ?? '-',
                    'NUPTK' => $lecturer->nuptk ?? '-',
                    'Email Domain' => $lecturer->email_domain ?? '-',
                ];
            @endphp
            @foreach ($fields as $label => $value)
                <div class="flex gap-3 py-2 border-b border-slate-50">
                    <span class="text-sm text-slate-400 w-40 flex-shrink-0">{{ $label }}</span>
                    <span class="text-sm font-medium text-slate-700">{{ $value }}</span>
                </div>
            @endforeach
        </div>

        <h3 class="text-base font-semibold text-slate-800 mt-6 mb-4 pb-2 border-b border-slate-100">
            Akademik & Jabatan Fungsional
        </h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-3">
            @php
                $academic = [
                    'Program Studi' => $lecturer->study_program ?? '-',
                    'JFA (Jabatan Fungsional)' => $lecturer->jfa ?? '-',
                    'Bidang Keilmuan' => $lecturer->academic_interest ?? '-',
                ];
            @endphp
            @foreach ($academic as $label => $value)
                <div class="flex gap-3 py-2 border-b border-slate-50">
                    <span class="text-sm text-slate-400 w-40 flex-shrink-0">{{ $label }}</span>
                    <span class="text-sm font-medium text-slate-700">{{ $value }}</span>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Tombol Kembali / Edit -->
    <div class="mt-5 flex items-center justify-between">
        <a href="{{ route('lecturers.index') }}"
           class="btn btn-secondary">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Kembali
        </a>
        <div class="flex items-center gap-2">
            <a href="{{ route('lecturers.export', ['id' => $lecturer->id]) }}" class="btn btn-secondary flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Download
            </a>
            <a href="{{ route('lecturers.edit', $lecturer->id) }}" class="btn btn-primary">
                Edit Data
            </a>
        </div>
    </div>
</x-app-layout>
