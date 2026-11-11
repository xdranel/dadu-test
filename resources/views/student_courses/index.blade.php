<x-app-layout>
    <x-slot name="header">Modul KRS Mahasiswa</x-slot>

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <a href="{{ route('academic_periods.index') }}" class="btn btn-secondary">Pengaturan Periode Akademik</a>
        @can('sync_execute')<a href="{{ route('sync.index') }}" class="text-sm text-blue-600 underline">Import JSON KRS</a>@endcan
    </div>

    <!-- Filter & Actions -->
    <div class="filter-card mb-4">
        <div class="flex flex-wrap gap-2.5 items-end justify-between">
            <!-- Search & Filter -->
            <form action="{{ route('student_courses.index') }}" method="GET"
                class="flex flex-wrap gap-2.5 items-end flex-1">
                
                <div class="min-w-[150px] max-w-[220px] flex-1">
                    <label class="campus-label">Pencarian</label>
                    <input type="text" name="search" value="{{ request('search') }}"
                        placeholder="NIM / Kode MK / Nama MK"
                        class="campus-input">
                </div>

                <div class="min-w-[130px]">
                    <label class="campus-label">Tahun Akademik</label>
                    <select name="tahun_akademik" class="campus-select">
                        <option value="">Semua Tahun</option>
                        @foreach ($tahunList as $tahun)
                            <option value="{{ $tahun }}" {{ request('tahun_akademik') == $tahun ? 'selected' : '' }}>
                                {{ $tahun }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="min-w-[100px]">
                    <label class="campus-label">Semester Studi</label>
                    <select name="semester" class="campus-select">
                        <option value="">Semua</option>
                        @for ($semester = 1; $semester <= 14; $semester++)
                            <option value="{{ $semester }}" @selected((string)request('semester') === (string)$semester)>{{ $semester }}</option>
                        @endfor
                    </select>
                </div>

                <div class="flex items-center gap-1.5">
                    <button type="submit" class="btn btn-secondary">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        Filter
                    </button>
                    <a href="{{ route('student_courses.index') }}" class="btn btn-secondary">Reset</a>
                </div>
            </form>

            <!-- Tombol Aksi -->
            <div class="flex items-center gap-1.5 flex-shrink-0">
                <button type="button"
                        onclick="document.getElementById('modalImport').classList.remove('hidden')"
                        class="btn btn-success">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    Import Data
                </button>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="campus-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="campus-table">
                <thead>
                    <tr>
                        <th>Mata Kuliah</th>
                        <th class="!text-center">Kelas & Prodi</th>
                        <th class="!text-center">Periode / SKS</th>
                        <th class="!text-center">Dosen</th>
                        <th class="!text-center">JUMLAH MHS</th>
                        <th class="!text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($krs as $item)
                        <tr>
                            <td class="text-left align-middle">
                                <div class="font-semibold text-slate-800">{{ $item->course_name }}</div>
                                <div class="text-xs text-slate-500 mt-0.5">{{ $item->course_code }}</div>
                            </td>
                            <td class="text-center align-middle">
                                <div class="text-slate-700">{{ $item->course_class ?: '-' }}</div>
                                <div class="text-xs text-slate-500 mt-0.5">{{ $item->study_program_display }}</div>
                            </td>
                            <td class="text-center align-middle">
                                <div class="text-slate-700">{{ $item->academic_year ?: '-' }}</div>
                                <div class="text-xs font-semibold text-gray-500 mt-0.5">{{ $item->credits ?: '-' }} SKS</div>
                            </td>
                            <td class="text-center align-middle">
                                @if (!empty($item->lecturers_list))
                                    @foreach ($item->lecturers_list as $dosen)
                                        <div class="mb-2 last:mb-0">
                                            <div class="text-slate-700 font-medium">{{ $dosen['name'] }}</div>
                                            <div class="text-xs text-slate-500 mt-0.5">{{ $dosen['code'] }}</div>
                                        </div>
                                    @endforeach
                                @else
                                    <div class="text-slate-700">-</div>
                                    <div class="text-xs text-slate-500 mt-0.5">-</div>
                                @endif
                            </td>
                            <td class="text-center align-middle">
                                <span class="text-slate-700 font-semibold">
                                    {{ $item->jumlah_mahasiswa }}
                                </span>
                            </td>
                            <td class="text-center align-middle whitespace-nowrap">
                                <a href="{{ route('student_courses.show', $item->id) }}"
                                   class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-all duration-200"
                                   title="Lihat">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-state">
                                <div class="empty-state-icon">
                                    <svg class="w-12 h-12 mx-auto text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                            d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                                    </svg>
                                </div>
                                <p class="empty-state-text">
                                    Tidak ada data KRS ditemukan.
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-5">{{ $krs->links() }}</div>

    <!-- Modal Import -->
    <div id="modalImport" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm"
         onclick="if(event.target===this) document.getElementById('modalImport').classList.add('hidden')">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 border border-slate-200">
            <div class="flex justify-between items-center p-6 border-b border-slate-100">
                <h3 class="text-lg font-bold text-slate-800">Import Data KRS</h3>
                <button onclick="document.getElementById('modalImport').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <form action="{{ route('student_courses.import') }}" method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">File Excel/CSV <span class="text-rose-400">*</span></label>
                    <input type="file" name="file" accept=".xlsx,.xls,.csv" required
                           class="block w-full text-sm text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 transition-colors cursor-pointer border border-slate-200 rounded-xl">
                    <p class="text-xs text-slate-500 mt-2">Format: .xlsx, .xls, .csv</p>
                </div>

                <div class="bg-amber-50 rounded-xl p-4 border border-amber-200">
                    <div class="flex gap-3">
                        <div class="flex-shrink-0 text-amber-500">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <h4 class="text-sm font-semibold text-amber-800">Format Import</h4>
                            <p class="text-xs text-amber-700 mt-1">Gunakan template yang disediakan untuk menghindari kegagalan proses import.</p>
                            <a href="{{ route('student_courses.template') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-amber-700 hover:text-amber-800 mt-2 bg-amber-100/50 hover:bg-amber-100 px-2 py-1 rounded-md transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                </svg>
                                Download Template
                            </a>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="document.getElementById('modalImport').classList.add('hidden')" class="btn btn-secondary">Batal</button>
                    <button type="submit" class="btn btn-primary">Mulai Import</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
