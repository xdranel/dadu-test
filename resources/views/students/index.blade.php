<x-app-layout>
    <x-slot name="header">Manajemen Mahasiswa</x-slot>

    <!-- Filter & Actions -->
    <div class="filter-card">
        <form action="{{ route('students.index') }}" method="GET"
              class="flex flex-wrap gap-2.5 items-end">

            <div class="min-w-[140px] max-w-[220px] flex-1">
                <label class="campus-label">Cari NIM / Nama</label>
                <input type="text" name="search" value="{{ request('search') }}"
                    placeholder="Ketik NIM atau nama..."
                    class="campus-input">
            </div>

            <div class="min-w-[160px] flex-1 max-w-[260px]">
                <label class="campus-label">Program Studi <span class="text-rose-400">*</span></label>
                <select name="prodi" class="campus-select">
                    <option value="" disabled {{ !request('prodi') ? 'selected' : '' }}>Pilih Program Studi</option>
                    @foreach ($prodiList as $prodi)
                        <option value="{{ $prodi }}" {{ request('prodi') == $prodi ? 'selected' : '' }}>
                            {{ $prodi }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="min-w-[160px] max-w-[260px]">
                <label class="campus-label">Tahun Angkatan <span class="text-rose-400">*</span></label>
                <select name="angkatan" class="campus-select">
                    <option value="" disabled {{ !request('angkatan') ? 'selected' : '' }}>Pilih</option>
                    @foreach ($angkatanList as $tahun)
                        <option value="{{ $tahun }}" {{ request('angkatan') == $tahun ? 'selected' : '' }}>
                            {{ $tahun }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-center gap-1.5">
                <button type="submit" class="btn btn-primary">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    Filter
                </button>
                <a href="{{ route('students.index') }}" class="btn btn-secondary">Reset</a>
            </div>

            @role('admin')
            <a href="{{ route('sync.index') }}" class="btn btn-secondary ml-auto">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                Sinkronisasi
            </a>
            @endrole

        </form>
    </div>

    @if ($hasFilter)
    <!-- Table -->
    <div class="campus-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="campus-table">
                <thead>
                    <tr>
                        <th>NIM</th>
                        <th>Nama Mahasiswa</th>
                        <th>Angkatan / Program Studi</th>
                        <th class="!text-center">Status</th>
                        <th class="!text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($students as $student)
                        <tr>
                            <td class="font-bold text-slate-800 whitespace-nowrap">
                                {{ $student->student_number }}
                            </td>
                            <td>
                                <div class="font-semibold text-slate-800">
                                    {{ $student->name }}
                                </div>
                                <div class="text-xs text-slate-400 mt-0.5">{{ $student->class_name ?? '-' }}</div>
                            </td>
                            <td>
                                <div class="text-slate-700">
                                    {{ $student->academic_year }}
                                </div>
                                <div class="text-xs font-semibold text-gray-500 mt-0.5">
                                    {{ $student->study_program }}
                                </div>
                            </td>
                            <td class="whitespace-nowrap text-center font-medium text-slate-600">
                                {{ $student->status }}
                            </td>
                            <td class="text-center whitespace-nowrap">
                                <div class="inline-flex items-center gap-1">
                                    {{-- Tombol Lihat --}}
                                    <a href="{{ route('students.show', $student->id) }}"
                                       class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-all duration-200"
                                       title="View">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </a>
                                    {{-- Tombol Edit --}}
                                    @can('student_update')
                                    <a href="{{ route('students.edit', $student->id) }}"
                                       class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-amber-500 hover:bg-amber-50 transition-all duration-200"
                                       title="Edit">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </a>
                                    @endcan
                                    {{-- Tombol Hapus --}}
                                    @can('student_delete')
                                    <button type="button"
                                            @click="$dispatch('open-delete-modal', { url: '{{ route('students.destroy', $student->id) }}', name: '{{ addslashes($student->name) }}' })"
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-rose-600 hover:bg-rose-50 transition-all duration-200"
                                            title="Delete">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="empty-state">
                                <div class="empty-state-icon">
                                    <svg class="w-12 h-12 mx-auto text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                            d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                                    </svg>
                                </div>
                                <p class="empty-state-text">Tidak ada data yang ditemukan dengan filter terpilih.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-5">{{ $students->links() }}</div>

    @else
    {{-- State awal: belum ada filter --}}
    <div class="campus-card">
        <div class="px-6 py-16 text-center">
            <svg class="w-16 h-16 mx-auto text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <h3 class="text-lg font-semibold text-slate-600 mb-1">Pilih Filter untuk Menampilkan Data</h3>
            <p class="text-sm text-slate-400">Gunakan filter <strong>Program Studi</strong> dan/atau <strong>Tahun Angkatan</strong> di atas untuk mencari mahasiswa.</p>
        </div>
    </div>
    @endif

    <!-- Modal Konfirmasi Hapus -->
    <div x-data="{ openDelete: false, deleteUrl: '', studentName: '' }"
         x-show="openDelete"
         @open-delete-modal.window="openDelete = true; deleteUrl = $event.detail.url; studentName = $event.detail.name;"
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm"
         style="display: none;"
         x-transition>
        <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-sm mx-4 border border-slate-200"
             @click.away="openDelete = false">
            <div class="text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-rose-100 text-rose-600 mb-4">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-slate-800 mb-2">Konfirmasi Hapus</h3>
                <p class="text-sm text-slate-500 mb-6 font-normal">Apakah Anda yakin ingin menghapus data mahasiswa <span class="font-semibold text-slate-800" x-text="studentName"></span>?</p>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" @click="openDelete = false"
                        class="btn btn-secondary w-full">Batal</button>
                <form :action="deleteUrl" method="POST" class="w-full">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-primary bg-rose-600 hover:bg-rose-700 w-full text-white">Ya, Hapus</button>
                </form>
            </div>
        </div>
    </div>

</x-app-layout>