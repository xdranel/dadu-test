<x-app-layout>
    <x-slot name="header">Manajemen Data Ruangan</x-slot>

    <!-- Filter & Actions -->
    <div class="filter-card mb-4">
        <div class="flex flex-wrap gap-2.5 items-end justify-between">

            <!--Search & Filter-->
            <form action="{{ route('rooms.index') }}" method="GET"
                class="flex flex-wrap gap-2.5 items-end flex-1">

                <div class="min-w-[150px] max-w-[220px] flex-1">
                    <label class="campus-label">Cari Kode / Nama</label>
                    <input type="text" name="search" value="{{ request('search') }}"
                        placeholder="Ketik kode atau nama..."
                        class="campus-input">
                </div>

                <div class="min-w-[130px]">
                    <label class="campus-label">Lokasi Gedung</label>
                    <select name="gedung" class="campus-select">
                        <option value="">Semua Gedung</option>
                        @foreach ($gedungList as $gedung)
                            <option value="{{ $gedung }}" {{ request('gedung') === $gedung ? 'selected' : '' }}>
                                {{ $gedung }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="min-w-[110px]">
                    <label class="campus-label">Status</label>
                    <select name="status" class="campus-select">
                        <option value="">Semua</option>
                        <option value="Dapat Dipakai" {{ request('status') === 'Dapat Dipakai' ? 'selected' : '' }}>Dapat Dipakai</option>
                        <option value="Tidak Dapat Dipakai" {{ request('status') === 'Tidak Dapat Dipakai' ? 'selected' : '' }}>Non Aktif</option>
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
                    <a href="{{ route('rooms.index') }}" class="btn btn-secondary">Reset</a>
                </div>

            </form>

            <!-- Tombol Aksi -->
            <div class="flex items-center gap-1.5 flex-shrink-0">

                <!-- Export -->
                <a href="{{ route('rooms.export', request()->only(['search', 'gedung', 'status'])) }}"
                class="btn btn-secondary">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Download
                </a>

                <!-- Import -->
                @can('room_create')
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
                @can('room_create')
                <a href="{{ route('rooms.create') }}" class="btn btn-success">
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
                        <th>Kode Ruang</th>
                        <th>Nama Ruangan</th>
                        <th>Lokasi Gedung</th>
                        <th>Kapasitas</th>
                        <th class="!text-center">Status</th>
                        <th class="!text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rooms as $r)
                        <tr>
                            <td class="font-bold text-slate-800 whitespace-nowrap">
                                {{ $r->room_code }}
                            </td>
                            <td class="text-slate-800">
                                {{ $r->name }}
                            </td>
                            <td class="whitespace-nowrap text-slate-500">
                                {{ $r->building }}
                            </td>
                            <td class="whitespace-nowrap font-semibold text-slate-700">
                                {{ $r->capacity }} orang
                            </td>
                            <td class="whitespace-nowrap text-center">
                                @if($r->status === 'Dapat Dipakai')
                                    <span class="badge badge-success">
                                        Dapat Dipakai
                                    </span>
                                @else
                                    <span class="badge badge-danger">
                                        Tidak Dapat Dipakai
                                    </span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap text-center">
                                 <div class="inline-flex items-center justify-center gap-1">
                                     {{-- Tombol Edit --}}
                                     @can('room_update')
                                     <a href="{{ route('rooms.edit', $r->id) }}"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-amber-500 hover:bg-amber-50 transition-all duration-200"
                                        title="Edit">
                                         <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                             <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                         </svg>
                                     </a>
                                     @endcan
                                     {{-- Tombol Hapus --}}
                                     @can('room_delete')
                                     <button type="button"
                                             @click="$dispatch('open-delete-modal', { url: '{{ route('rooms.destroy', $r->id) }}', name: '{{ addslashes($r->name) }}' })"
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
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                    </svg>
                                </div>
                                <p class="empty-state-text">Belum ada data ruangan.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-5">{{ $rooms->links() }}</div>

    <!-- Modal Import -->
    <div id="modalImport"
         class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm"
         onclick="if(event.target===this) this.classList.add('hidden')">
        <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-md mx-4 border border-slate-200">
            <div class="flex justify-between items-center mb-5">
                <div>
                    <h3 class="text-lg font-bold text-slate-800">Import Data Ruangan</h3>
                    <p class="text-xs text-slate-400 mt-1">Upload file Excel untuk import data</p>
                </div>
                <button onclick="document.getElementById('modalImport').classList.add('hidden')"
                        class="text-slate-400 hover:text-slate-600 p-1 rounded-lg hover:bg-slate-100">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <form action="{{ route('rooms.import') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="mb-4">
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Upload file Excel (.xlsx, .xls, .csv)</label>
                    <input type="file" name="file" accept=".xlsx,.xls,.csv" required
                           class="w-full text-sm text-slate-500 border border-slate-300 rounded-lg p-2.5 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-[#DA2D3B] hover:file:bg-indigo-100">
                </div>
                <div class="mb-5 p-3.5 bg-slate-50 rounded-xl border border-slate-200">
                    <p class="text-xs font-semibold text-slate-600 mb-1">Format header kolom Excel:</p>
                    <code class="text-xs text-[#DA2D3B] font-medium">room_code | name | building | capacity | status</code>
                    <p class="text-xs text-slate-400 mt-1.5">* Kode ruang duplikat akan otomatis diperbarui.</p>
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
    <div x-data="{ openDelete: false, deleteUrl: '', roomName: '' }"
         x-show="openDelete"
         @open-delete-modal.window="openDelete = true; deleteUrl = $event.detail.url; roomName = $event.detail.name;"
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
                <p class="text-sm text-slate-500 mb-6 font-normal">Apakah Anda yakin ingin menghapus data ruangan <span class="font-semibold text-slate-800" x-text="roomName"></span>?</p>
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