<x-app-layout>
    <x-slot name="header">Detail Mata Kuliah: {{ $studentCourse->course_name }}</x-slot>

    <!-- Kembali dan Detail Header -->
    <div class="mb-5">
        <a href="{{ route('student_courses.index') }}" class="inline-flex items-center text-sm font-semibold text-slate-500 hover:text-slate-700 transition-colors mb-4">
            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Kembali ke Daftar KRS
        </a>

        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-wrap justify-between items-start gap-4">
            <div>
                <h2 class="text-xl font-bold text-slate-800">{{ $studentCourse->course_name }}</h2>
                <div class="flex items-center gap-3 mt-1.5 text-sm font-medium text-slate-500">
                    <span class="bg-slate-100 px-2 py-1 rounded-md text-slate-700">{{ $studentCourse->course_code }}</span>
                    <span>Kelas {{ $studentCourse->course_class ?: '-' }}</span>
                </div>
            </div>
            
            <div class="flex gap-4 text-sm">
                <div class="bg-slate-50 px-4 py-2.5 rounded-xl border border-slate-100 text-center">
                    <p class="text-slate-400 text-xs font-semibold mb-0.5">Tahun Akademik</p>
                    <p class="font-bold text-slate-700">{{ $studentCourse->academic_year ?: '-' }}</p>
                </div>
                <div class="bg-slate-50 px-4 py-2.5 rounded-xl border border-slate-100 text-center">
                    <p class="text-slate-400 text-xs font-semibold mb-0.5">Semester Studi</p>
                    <p class="font-bold text-slate-700">{{ $studentCourse->semester ?: '-' }}</p>
                </div>
                <div class="bg-slate-50 px-4 py-2.5 rounded-xl border border-slate-100 text-center">
                    <p class="text-slate-400 text-xs font-semibold mb-0.5">Periode Akademik</p>
                    <p class="font-bold text-slate-700">{{ $studentCourse->academicPeriod?->label ?? 'Belum ditautkan' }}</p>
                </div>
                <div class="bg-rose-50 px-4 py-2.5 rounded-xl border border-rose-100 text-center min-w-[100px] text-[#DA2D3B]">
                    <p class="text-xs font-semibold mb-0.5 opacity-80">Total Mahasiswa</p>
                    <p class="font-bold text-lg">{{ count($students) }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Table Mahasiswa -->
    <div class="campus-card overflow-hidden">
        <div class="p-4 border-b border-slate-100">
            <h3 class="font-bold text-slate-800">Daftar Mahasiswa Terdaftar</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="campus-table">
                <thead>
                    <tr>
                        <th class="w-16 text-center">No</th>
                        <th>NIM</th>
                        <th>Nama Mahasiswa</th>
                        <th>Prodi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($students as $index => $mhs)
                        <tr>
                            <td class="text-center text-slate-500 font-medium">{{ $index + 1 }}</td>
                            <td class="font-bold text-slate-800 whitespace-nowrap">{{ $mhs->student_number }}</td>
                            <td>
                                <div class="font-semibold text-slate-800">{{ $mhs->student_name_display }}</div>
                            </td>
                            <td>
                                <div class="text-slate-700">{{ $mhs->study_program_display }}</div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="empty-state">
                                <p class="empty-state-text">Tidak ada mahasiswa terdaftar.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
