<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Edit Kategori Dokumen</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">

                @if ($errors->any())
                    <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                        <p class="font-semibold text-red-700 mb-2">Terdapat kesalahan:</p>
                        <ul class="list-disc list-inside text-red-600 text-sm">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('document_categories.update', $documentCategory) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1" for="name">
                            Nama Kategori <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="name" name="name"
                               value="{{ old('name', $documentCategory->name) }}"
                               class="w-full border-gray-300 rounded-md shadow-sm text-sm
                                      focus:ring-[#DA2D3B] focus:border-[#DA2D3B]
                                      @error('name') border-red-500 @enderror">
                        @error('name')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1" for="description">Deskripsi</label>
                        <textarea id="description" name="description" rows="3"
                                  class="w-full border-gray-300 rounded-md shadow-sm text-sm
                                         focus:ring-[#DA2D3B] focus:border-[#DA2D3B]">{{ old('description', $documentCategory->description) }}</textarea>
                    </div>

                    @php
                        $pinnedLimitReached = \App\Models\DocumentCategory::where('is_pinned', true)->where('id', '!=', $documentCategory->id)->count() >= 5;
                        $isChecked = old('sematkan', $documentCategory->is_pinned);
                    @endphp
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Penempatan Kategori
                        </label>
                        <div class="flex items-center">
                            <label for="sematkan" class="inline-flex items-center select-none {{ $pinnedLimitReached && !$isChecked ? 'opacity-60 cursor-not-allowed' : 'cursor-pointer' }}"
                                   title="{{ $pinnedLimitReached && !$isChecked ? 'Maksimal 5 folder yang bisa disematkan. Lepas sematan folder lain terlebih dahulu.' : '' }}">
                                <div class="relative">
                                    <input type="checkbox" id="sematkan" name="sematkan" value="1"
                                           class="sr-only peer"
                                           {{ $pinnedLimitReached && !$isChecked ? 'disabled' : ($isChecked ? 'checked' : '') }}>
                                    <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-focus:ring-2 peer-focus:ring-[#DA2D3B]/20 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#DA2D3B]"></div>
                                </div>
                                <span class="ml-3 text-sm font-medium text-gray-700">Sematkan di Atas</span>
                            </label>
                        </div>
                        @if($pinnedLimitReached && !$isChecked)
                            <p class="text-xs text-amber-600 mt-2 font-medium">
                                Maksimal 5 folder yang bisa disematkan. Lepas sematan folder lain terlebih dahulu.
                            </p>
                        @else
                            <p class="text-xs text-gray-400 mt-2">
                                Kategori yang disematkan akan selalu tampil di urutan teratas pada halaman.
                            </p>
                        @endif
                    </div>

                    <div class="flex items-center justify-end gap-4">
                        <a href="{{ route('document_categories.index') }}"
                           class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900">Batal</a>
                        <button type="submit"
                                class="px-6 py-2 bg-[#DA2D3B] text-white text-sm font-medium rounded-md
                                       hover:bg-[#b0222e] focus:outline-none focus:ring-2 focus:ring-[#DA2D3B]">
                            Perbarui Data
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>