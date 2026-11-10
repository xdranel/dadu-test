<x-app-layout>
    <x-slot name="header">Manajemen Data Mata Kuliah (Courses)</x-slot>

    <!-- Filter & Actions -->
    <div class="filter-card mb-4">
        <div class="flex flex-wrap gap-2.5 items-end justify-between">

            <!--Search & Filter-->
            <form action="{{ route('courses.index') }}" method="GET"
                class="flex flex-wrap gap-2.5 items-end">

                <div class="w-48">
                    <label class="campus-label">Cari Kode / Nama MK</label>
                    <input type="text" name="search" value="{{ request('search') }}"
                        placeholder="Ketik kode atau nama..."
                        class="campus-input">
                </div>

                <div class="w-36">
                    <label class="campus-label">Program Studi</label>
                    <select name="prodi" class="campus-select">
                        <option value="">Semua Prodi</option>
                        @foreach (\App\Constants\ProdiList::ALL as $prodi)
                            <option value="{{ $prodi }}" {{ request('prodi') === $prodi ? 'selected' : '' }}>
                                {{ $prodi }}
                            </option>
                        @endforeach
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
                    <a href="{{ route('courses.index') }}" class="btn btn-secondary">Reset</a>
                </div>

            </form>

            <!-- Tombol Aksi -->
            <div class="flex items-center gap-1.5 flex-shrink-0">

                <!-- Sinkronisasi -->
                @role('admin')
                <a href="{{ route('sync.index') }}"
                   class="btn btn-secondary">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Sinkronisasi
                </a>
                @endrole

                <!-- Tambah -->
                @can('course_create')
                <a href="{{ route('courses.create') }}" class="btn btn-success">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    Tambah
                </a>
                @endcan
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="campus-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="campus-table">
                <thead>
                    <tr>
                        <th>Kode MK</th>
                        <th>Nama Mata Kuliah</th>
                        <th>Prodi / Kelas</th>
                        <th class="!text-center">Jumlah Mhs</th>
                        <th>Jenis</th>
                        <th class="!text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($courses as $mk)
                        <tr>
                            <td class="font-bold text-slate-800 whitespace-nowrap">
                                {{ $mk->course_code }}
                            </td>
                            <td class="text-slate-800">
                                <div class="font-semibold text-slate-800">{{ $mk->name }}</div>
                                <div class="text-xs text-slate-500 mt-0.5">
                                    {{ $mk->credits }} SKS
                                </div>
                            </td>
                            <td class="whitespace-nowrap">
                                <span class="font-semibold text-slate-700">{{ $mk->study_program }}</span>
                                <div class="text-xs text-slate-500 mt-0.5">
                                    @if($mk->kelas_count <= 3)
                                        {{ $mk->kelas_list->implode(', ') }}
                                    @else
                                        {{ $mk->kelas_list->take(2)->implode(', ') }}
                                        <span x-data="{ open: false }" class="relative inline-block">
                                            <span @click="open = !open" @click.away="open = false" class="cursor-pointer text-blue-500 hover:text-blue-700 hover:underline select-none font-semibold">
                                                +{{ $mk->kelas_count - 2 }} kelas lainnya
                                            </span>
                                            <div x-show="open" style="display: none;" class="absolute z-10 bottom-full left-1/2 transform -translate-x-1/2 mb-2 w-48 bg-slate-800 text-white text-[11px] rounded px-2.5 py-1.5 shadow-lg whitespace-normal font-normal text-center leading-tight">
                                                {{ $mk->kelas_list->implode(', ') }}
                                                <!-- Arrow -->
                                                <div class="absolute top-full left-1/2 transform -translate-x-1/2 border-4 border-transparent border-t-slate-800"></div>
                                            </div>
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="whitespace-nowrap font-bold text-slate-800 !text-center">
                                {{ $mk->enrolled_count }}
                            </td>
                            <td class="whitespace-nowrap font-medium text-slate-600">
                                {{ $mk->type }}
                            </td>
                            <td class="whitespace-nowrap text-center">
                                @if($mk->id !== null)
                                <div class="inline-flex items-center justify-center gap-1">
                                    {{-- Tombol Lihat --}}
                                    <a href="{{ route('courses.show', $mk->id) }}"
                                       class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-all duration-200"
                                       title="Lihat">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </a>
                                    {{-- Tombol Edit --}}
                                    @can('course_update')
                                    <a href="{{ route('courses.edit', $mk->id) }}"
                                       class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-amber-500 hover:bg-amber-50 transition-all duration-200"
                                       title="Edit">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </a>
                                    @endcan
                                    {{-- Tombol Hapus --}}
                                    @can('course_delete')
                                    <button type="button"
                                            @click="$dispatch('open-delete-modal', { url: '{{ route('courses.destroy', $mk->id) }}', name: '{{ addslashes($mk->name) }}' })"
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-rose-600 hover:bg-rose-50 transition-all duration-200"
                                            title="Hapus">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                    @endcan
                                </div>
                                @else
                                    <span class="text-xs text-slate-500">Master mata kuliah belum tersedia</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-state">
                                <div class="empty-state-icon">
                                    <svg class="w-12 h-12 mx-auto text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                                    </svg>
                                </div>
                                <p class="empty-state-text">Belum ada data mata kuliah.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-5">{{ $courses->links() }}</div>



    <!-- Modal Konfirmasi Hapus -->
    <div x-data="{ openDelete: false, deleteUrl: '', courseName: '' }"
         x-show="openDelete"
         @open-delete-modal.window="openDelete = true; deleteUrl = $event.detail.url; courseName = $event.detail.name;"
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
                <p class="text-sm text-slate-500 mb-6 font-normal">Apakah Anda yakin ingin menghapus data mata kuliah <span class="font-semibold text-slate-800" x-text="courseName"></span>?</p>
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
