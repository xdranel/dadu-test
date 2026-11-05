<x-app-layout>
    <x-slot name="header">Kategori Dokumen</x-slot>

    <!-- Flash Messages -->
    @if(session('success'))
        <div class="mb-5 flash-message flex items-center gap-3 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span class="text-sm font-medium">{{ session('success') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div class="mb-5 flash-message flex items-center gap-3 bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-xl">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span class="text-sm font-medium">{{ session('error') }}</span>
        </div>
    @endif

    <!-- Actions & Search -->
    <div class="flex flex-wrap gap-3 items-center justify-between mb-5">
        <!-- Search Form -->
        <form action="{{ route('document_categories.index') }}" method="GET" class="flex items-center gap-2">
            <div>
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="Cari nama atau urutan..."
                       class="campus-input min-w-[240px]">
            </div>
            <button type="submit" class="btn btn-secondary">Cari</button>
            @if(request('search'))
                <a href="{{ route('document_categories.index') }}" class="btn btn-secondary">Reset</a>
            @endif
        </form>

        <!-- Tombol Tambah -->
        @can('document_category_create')
        <a href="{{ route('document_categories.create') }}" class="btn btn-success">
            Tambah Kategori
        </a>
        @endcan
    </div>

    <!-- Tabel -->
    <div class="campus-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="campus-table">
                <thead>
                    <tr>
                        <th class="w-20 text-center">Urutan</th>
                        <th>Nama Kategori</th>
                        <th>Deskripsi</th>
                        <th class="!text-center">Jumlah Dokumen</th>
                        <th class="!text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($categories as $category)
                        <tr class="align-middle">
                            <td class="text-center text-slate-400">{{ $loop->iteration + ($categories->currentPage() - 1) * $categories->perPage() }}</td>
                            <td>
                                <a href="{{ route('document_categories.show', $category) }}"
                                   class="font-semibold text-slate-900 hover:text-[#DA2D3B]">
                                    {{ $category->name }}
                                </a>
                            </td>
                            <td class="text-slate-500">
                                {{ Str::limit($category->description, 80) ?? '-' }}
                            </td>
                            <td class="text-center align-middle">
                                <span class="text-slate-700 font-medium">
                                    {{ $category->documents_count }} dokumen
                                </span>
                            </td>
                            <td class="text-center align-middle whitespace-nowrap">
                                <div class="inline-flex items-center justify-center gap-1">
                                    <!-- Tombol Lihat -->
                                    <a href="{{ route('document_categories.show', $category) }}"
                                       class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-all duration-200"
                                       title="Lihat">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </a>
                                    <!-- Tombol Edit -->
                                    @can('document_category_update')
                                    <a href="{{ route('document_categories.edit', $category) }}"
                                       class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-amber-500 hover:bg-amber-50 transition-all duration-200"
                                       title="Edit">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </a>
                                    @endcan
                                    <!-- Tombol Hapus -->
                                    @can('document_category_delete')
                                    <button type="button"
                                            @click="$dispatch('open-delete-modal', { url: '{{ route('document_categories.destroy', $category) }}', name: '{{ addslashes($category->name) }}' })"
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
                            <td colspan="5" class="empty-state">
                                <div class="empty-state-icon">
                                    <svg class="w-12 h-12 mx-auto text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                              d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                                    </svg>
                                </div>
                                <p class="empty-state-text">Belum ada kategori dokumen.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-5">{{ $categories->links() }}</div>

    <!-- Modal Konfirmasi Hapus -->
    <div x-data="{ openDelete: false, deleteUrl: '', categoryName: '' }"
         x-show="openDelete"
         @open-delete-modal.window="openDelete = true; deleteUrl = $event.detail.url; categoryName = $event.detail.name;"
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
                <p class="text-sm text-slate-500 mb-6 font-normal">Apakah Anda yakin ingin menghapus kategori <span class="font-semibold text-slate-800" x-text="categoryName"></span> beserta seluruh dokumen di dalamnya?</p>
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