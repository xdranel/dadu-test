<x-app-layout>
    <x-slot name="header">Detail Mata Kuliah</x-slot>

    <!-- Header Profil-->
    <div class="campus-card mb-5 overflow-hidden">
        <div class="bg-[#DA2D3B] px-6 py-8">
            <div class="flex items-center gap-6">

                <!-- Icon Placeholder -->
                <div class="flex-shrink-0">
                    <div class="w-24 h-24 bg-white/20 rounded-xl flex items-center justify-center border-2 border-white/30 shadow-lg text-white">
                        <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                        </svg>
                    </div>
                </div>

                <!-- Info Utama -->
                <div class="text-white">
                    <h2 class="text-2xl font-bold">{{ $course->name }}</h2>
                    <p class="text-white/80 text-sm mt-1">{{ $course->course_code }}</p>
                    <div class="flex flex-wrap gap-3 mt-3">
                        <span class="px-3 py-1 bg-white/20 rounded-full text-xs font-medium">
                            {{ $studyProgram ?: '-' }}
                        </span>

                        <span class="px-3 py-1 bg-white/20 rounded-full text-xs font-medium">
                            {{ $course->credits }} SKS
                        </span>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Detail Data Mata Kuliah -->
    <div class="campus-card p-6">
        <h3 class="text-base font-semibold text-slate-800 mb-4 pb-2 border-b border-slate-100">
            Informasi Mata Kuliah
        </h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-3">
            @php
                $coordinatorList = $course->coordinators->map(fn($l) => $l->name === $l->lecturer_code ? $l->name : $l->name . ' (' . $l->lecturer_code . ')')->implode(', ') ?: '-';
                $teamList = $course->teamLecturers->map(fn($l) => $l->name === $l->lecturer_code ? $l->name : $l->name . ' (' . $l->lecturer_code . ')')->implode(', ') ?: '-';

                $fields = [
                    'Nama Mata Kuliah' => $course->name,
                    'Kode MK' => $course->course_code,
                    'Program Studi' => $studyProgram ?: '-',
                    'Kelas' => $classList ?? '-',
                    'Semester' => '-',
                    'Tahun Akademik' => $course->academic_year ?? '-',
                    'Bobot SKS' => $course->credits . ' SKS',
                    'Jumlah Mahasiswa' => $course->enrolled_count ?? 0,
                    'Metode Pembelajaran' => $course->learning_method ?? '-',
                    'Koordinator Dosen' => $coordinatorList,
                    'Status Dosen Koordinator' => $course->status_dosen ?? '-',
                    'Tim Dosen (Anggota)' => $teamList,
                    'Jenis Mata Kuliah' => $course->type,
                    'Status' => $course->status,
                ];
            @endphp
            @foreach ($fields as $label => $value)
                <div class="flex gap-3 py-2 border-b border-slate-50">
                    <span class="text-sm text-slate-400 w-40 flex-shrink-0">{{ $label }}</span>
                    <span class="text-sm font-medium text-slate-700">
                        {{ $value }}
                    </span>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Tombol Kembali / Download / Edit -->
    <div class="mt-5 flex items-center justify-between">
        <a href="{{ route('courses.index') }}"
           class="btn btn-secondary">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Kembali
        </a>
        <div class="flex items-center gap-2">
            <a href="{{ route('courses.export', ['id' => $course->id]) }}" class="btn btn-secondary flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Download
            </a>
            <a href="{{ route('courses.edit', $course->id) }}" class="btn btn-primary">
                Edit Data
            </a>
        </div>
    </div>
</x-app-layout>
