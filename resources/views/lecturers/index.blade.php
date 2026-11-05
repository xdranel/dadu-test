<x-app-layout>
    <x-slot name="header">Manajemen Data Dosen (Lecturers)</x-slot>

    {{-- Banner: peringatan data tidak lengkap --}}
    @if($incompleteCount > 0 && !request()->boolean('incomplete'))
        <div class="mb-5 flex items-center justify-between gap-4 bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded-xl">
            <div class="flex items-center gap-2.5">
                <svg class="w-4 h-4 flex-shrink-0 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                </svg>
                <span class="text-sm font-medium">
                    <strong>{{ $incompleteCount }} dosen</strong> memiliki data tidak lengkap (nama atau NIP belum diisi).
                </span>
            </div>
            <a href="{{ route('lecturers.index', array_merge(request()->query(), ['incomplete' => '1'])) }}"
               class="flex-shrink-0 text-xs font-semibold text-amber-700 border border-amber-300 bg-white hover:bg-amber-100 px-3 py-1.5 rounded-lg transition-colors whitespace-nowrap">
                Lihat Daftar
            </a>
        </div>
    @endif

    {{-- Banner aktif: sedang memfilter data tidak lengkap --}}
    @if(request()->boolean('incomplete'))
        <div class="mb-5 flex items-center justify-between gap-4 bg-amber-50 border border-amber-300 text-amber-800 px-4 py-3 rounded-xl">
            <div class="flex items-center gap-2.5">
                <svg class="w-4 h-4 flex-shrink-0 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/>
                </svg>
                <span class="text-sm font-medium">
                    Menampilkan <strong>{{ $incompleteCount }} dosen</strong> dengan data tidak lengkap.
                </span>
            </div>
            <a href="{{ route('lecturers.index') }}"
               class="flex-shrink-0 text-xs font-semibold text-amber-700 border border-amber-300 bg-white hover:bg-amber-100 px-3 py-1.5 rounded-lg transition-colors whitespace-nowrap">
                ✕ Hapus Filter
            </a>
        </div>
    @endif

   <!-- Filter & Actions -->
    <div class="filter-card mb-4">
        <div class="flex flex-wrap gap-2.5 items-end justify-between">

            <!-- Search & Filter -->
            <form method="GET" action="{{ route('lecturers.index') }}"
                class="flex flex-wrap gap-2.5 items-end flex-1">

                <div class="min-w-[150px] max-w-[220px] flex-1">
                    <label class="campus-label">Cari NIP / Kode / Nama</label>
                    <input type="text" name="search" value="{{ request('search') }}"
                        placeholder="Ketik NIP, kode, atau nama..."
                        class="campus-input">
                </div>

                <div class="min-w-[130px]">
                    <label class="campus-label">Program Studi</label>
                    <select name="prodi" class="campus-select">
                        <option value="">Semua Prodi</option>
                        @foreach ($prodiList as $p)
                            <option value="{{ $p }}" {{ request('prodi') == $p ? 'selected' : '' }}>
                                {{ $p }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="min-w-[110px]">
                    <label class="campus-label">Status</label>
                    <select name="status_kepegawaian" class="campus-select">
                        <option value="">Semua</option>
                        @foreach ($statusList as $status)
                            <option value="{{ $status }}" {{ request('status_kepegawaian') == $status ? 'selected' : '' }}>
                                {{ $status }}
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
                    <a href="{{ route('lecturers.index') }}" class="btn btn-secondary">Reset</a>
                </div>

            </form>

            <!-- KANAN — Tombol Aksi -->
            <div class="flex items-center gap-1.5 flex-shrink-0">

                <!-- Import -->
                @can('lecturer_create')
                <button type="button"
                        onclick="document.getElementById('modalImport').classList.remove('hidden')"
                        class="btn btn-secondary">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    Import
                </button>
                @endcan

                <!-- Tambah -->
                @can('lecturer_create')
                <a href="{{ route('lecturers.create') }}" class="btn btn-success">
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
                        <th class="w-12">No</th>
                        <th>Kode</th>
                        <th>NIP / Nama Dosen</th>
                        <th>JFA & Homebase</th>
                        <th class="!text-center">Status</th>
                        <th class="!text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($lecturers as $index => $lecturer)
                    @php
                        // Dosen dianggap tidak lengkap jika nama atau NIP (nip_ypt) kosong
                        $isIncomplete = empty($lecturer->name) || empty($lecturer->nip_ypt);
                    @endphp
                    <tr class="{{ $isIncomplete ? 'bg-amber-50/60 hover:bg-amber-50' : '' }}">
                        <td class="text-slate-400 text-center">
                            {{ $lecturers->firstItem() + $index }}
                        </td>
                        <td class="font-bold text-slate-800 whitespace-nowrap">
                            {{ $lecturer->lecturer_code }}
                        </td>
                        <td>
                            <div class="text-xs text-slate-400">{{ $lecturer->nip_ypt ?? $lecturer->nidn ?? '-' }}</div>
                            <div class="flex items-center gap-2">
                                <span class="font-semibold {{ $isIncomplete ? 'text-slate-400 italic' : 'text-slate-800' }}">
                                    {{ $lecturer->front_title ? $lecturer->front_title . ' ' : '' }}{{ $lecturer->name ?? '(Nama belum diisi)' }}{{ $lecturer->back_title ? ', ' . $lecturer->back_title : '' }}
                                </span>
                                @if($isIncomplete)
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-700 border border-amber-200 whitespace-nowrap">
                                        <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                                d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                                        </svg>
                                        Data Tidak Lengkap
                                    </span>
                                @endif
                            </div>
                        </td>
                        <td>
                            <div class="font-medium text-slate-700">{{ $lecturer->jfa ?? 'Belum ada JFA' }}</div>
                            <div class="text-xs text-slate-400 mt-0.5">{{ $lecturer->study_program }}</div>
                        </td>
                        <td class="whitespace-nowrap text-center font-medium text-slate-600">
                            {{ $lecturer->employment_status }}
                        </td>
                        <td class="whitespace-nowrap text-center">
                            <div class="inline-flex items-center gap-1">
                                {{-- Tombol Lihat --}}
                                <a href="{{ route('lecturers.show', $lecturer->id) }}"
                                   class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-all duration-200"
                                   title="Lihat">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </a>
                                {{-- Tombol Edit --}}
                                @can('lecturer_update')
                                <a href="{{ route('lecturers.edit', $lecturer->id) }}"
                                   class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-amber-500 hover:bg-amber-50 transition-all duration-200"
                                   title="Edit">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </a>
                                @endcan
                                {{-- Tombol Hapus --}}
                                @can('lecturer_delete')
                                <button type="button"
                                        @click="$dispatch('open-delete-modal', { url: '{{ route('lecturers.destroy', $lecturer->id) }}', name: '{{ addslashes($lecturer->name ?? $lecturer->lecturer_code) }}' })"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-rose-600 hover:bg-rose-50 transition-all duration-200"
                                        title="Hapus">
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
                            <td colspan="6" class="empty-state">
                                <div class="empty-state-icon">
                                    <svg class="w-12 h-12 mx-auto text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                    </svg>
                                </div>
                                <p class="empty-state-text">Belum ada data dosen. Silakan lakukan Import Data.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-5">{{ $lecturers->links() }}</div>

    <!-- Modal Import -->
    <div id="modalImport"
         class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm"
         onclick="if(event.target===this) this.classList.add('hidden')">
        <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-md mx-4 border border-slate-200">
            <div class="flex justify-between items-center mb-5">
                <div>
                    <h3 class="text-lg font-bold text-slate-800">Import Data Dosen</h3>
                    <p class="text-xs text-slate-400 mt-1">Upload file Excel untuk import data</p>
                </div>
                <button onclick="document.getElementById('modalImport').classList.add('hidden')"
                        class="text-slate-400 hover:text-slate-600 p-1 rounded-lg hover:bg-slate-100">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <form action="{{ route('lecturers.import') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="mb-4">
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Upload file Excel (.xlsx, .xls, .csv)</label>
                    <input type="file" name="file" accept=".xlsx,.xls,.csv" required
                           class="w-full text-sm text-slate-500 border border-slate-300 rounded-lg p-2.5 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-[#DA2D3B] hover:file:bg-indigo-100">
                </div>
                <div class="mb-5 p-3.5 bg-slate-50 rounded-xl border border-slate-200">
                    <p class="text-xs font-semibold text-slate-600 mb-1">Format header kolom Excel (atau langsung gunakan tombol Download):</p>
                    <code class="text-[10px] block leading-relaxed text-[#DA2D3B] font-medium">
                        NAMA | Status Aktif | PRODI | NIP YPT | NIDN | NUPTK | STATUS Kepegawaian | GELAR DEPAN | GELAR BELAKANG | JFA | BIDANG KEILMUAN | KODE DOSEN | Email Domain
                    </code>
                    <p class="text-xs text-slate-400 mt-1.5">* Minimal NAMA dan (NIDN atau KODE DOSEN) wajib diisi. NIDN/Kode ganda akan dilewati.</p>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button"
                            onclick="document.getElementById('modalImport').classList.add('hidden')"
                            class="btn btn-secondary">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                        </svg>
                        Upload & Import
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Konfirmasi Hapus -->
    <div x-data="{ openDelete: false, deleteUrl: '', lecturerName: '' }"
         x-show="openDelete"
         @open-delete-modal.window="openDelete = true; deleteUrl = $event.detail.url; lecturerName = $event.detail.name;"
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
                <p class="text-sm text-slate-500 mb-6 font-normal">Apakah Anda yakin ingin menghapus data dosen <span class="font-semibold text-slate-800" x-text="lecturerName"></span>?</p>
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