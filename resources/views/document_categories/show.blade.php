<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $documentCategory->name }}
        </h2>
    </x-slot>

    @if(session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                    <strong>Berhasil!</strong> {{ session('success') }}
                </div>
            @endif

            <!-- Info Kategori -->
            @if($documentCategory->description)
                <div class="mb-6 bg-slate-50 border border-slate-200 rounded-lg p-4">
                    <p class="text-sm text-slate-900 font-semibold">{{ $documentCategory->description }}</p>
                </div>
            @endif

            <!-- Filter -->
            <form action="{{ route('document_categories.show', $documentCategory) }}" method="GET"
                  class="mb-4 bg-white p-4 rounded-lg shadow-sm flex flex-wrap gap-3 items-end">
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-xs text-gray-500 mb-1">Cari Dokumen</label>
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="Cari judul dokumen..."
                           class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                </div>
                <div class="min-w-[160px]">
                    <label class="block text-xs text-gray-500 mb-1">Visibilitas</label>
                    <select name="visibilitas" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                        <option value="">Semua</option>
                        <option value="publik" {{ request('visibilitas') === 'publik' ? 'selected' : '' }}>Publik dan Mahasiswa</option>
                        <option value="internal" {{ request('visibilitas') === 'internal' ? 'selected' : '' }}>Pegawai dan Dosen</option>
                    </select>
                </div>
                <button type="submit" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm rounded-md">Filter</button>
                <a href="{{ route('document_categories.show', $documentCategory) }}"
                   class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm rounded-md">Reset</a>
                <a href="{{ route('documents.create') }}"
                   class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold rounded-md shadow whitespace-nowrap">
                    Tambah Dokumen
                </a>
            </form>

            <!-- Daftar Dokumen -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Judul Dokumen</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipe / Ukuran</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Visibilitas</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Unduhan</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse ($documents as $dok)
                                <tr class="hover:bg-gray-50 transition-colors duration-150">
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <a href="{{ route('documents.show', $dok) }}"
                                           class="font-semibold text-slate-900 hover:text-[#DA2D3B]">
                                            {{ $dok->title }}
                                        </a>
                                        <div class="text-xs text-gray-400">{{ Str::limit($dok->description, 60) }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <span class="uppercase font-semibold">{{ $dok->file_type }}</span>
                                        <div class="text-xs">{{ $dok->file_size_formatted }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $dok->visibility_badge }}">
                                            {{ $dok->visibility_label }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-center text-sm text-gray-500">
                                        {{ $dok->download_count }}x
                                    </td>
                                     <td class="px-6 py-4 text-center text-sm font-medium whitespace-nowrap">
                                         <div class="inline-flex items-center justify-center gap-1">
                                             {{-- Tombol Unduh --}}
                                             <a href="{{ route('documents.download', $dok) }}"
                                                class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 transition-all duration-200"
                                                title="Unduh">
                                                 <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                                 </svg>
                                             </a>
                                             {{-- Tombol Edit --}}
                                             <a href="{{ route('documents.edit', $dok) }}"
                                                class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-amber-500 hover:bg-amber-50 transition-all duration-200"
                                                title="Edit">
                                                 <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                 </svg>
                                             </a>
                                             {{-- Tombol Hapus --}}
                                             <form action="{{ route('documents.destroy', $dok) }}"
                                                   method="POST" class="inline-flex"
                                                   onsubmit="return confirm('Yakin hapus dokumen {{ $dok->title }}?')">
                                                 @csrf
                                                 @method('DELETE')
                                                 <button type="submit"
                                                         class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-rose-600 hover:bg-rose-50 transition-all duration-200"
                                                         title="Hapus">
                                                     <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                         <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                     </svg>
                                                 </button>
                                             </form>
                                         </div>
                                     </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-10 text-center text-gray-400">
                                        <p class="text-lg">📄</p>
                                        <p class="text-sm mt-1">Belum ada dokumen dalam kategori ini.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="mt-4">{{ $documents->links() }}</div>
</x-app-layout>