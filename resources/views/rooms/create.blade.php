<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Tambah Ruangan
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">

                @if ($errors->any())
                    <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                        <p class="font-semibold text-red-700 mb-2">Terdapat kesalahan input:</p>
                        <ul class="list-disc list-inside text-red-600 text-sm">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('rooms.store') }}" method="POST">
                    @csrf

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1" for="room_code">
                                Kode Ruang <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="room_code" name="room_code"
                                   value="{{ old('room_code') }}"
                                   placeholder="Contoh: GD-A101"
                                   class="w-full border-gray-300 rounded-md shadow-sm text-sm
                                          focus:ring-[#DA2D3B] focus:border-[#DA2D3B]
                                          @error('room_code') border-red-500 @enderror">
                            @error('room_code')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1" for="name">
                                Nama Ruangan <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="name" name="name"
                                   value="{{ old('name') }}"
                                   placeholder="Contoh: Lab Komputer A"
                                   class="w-full border-gray-300 rounded-md shadow-sm text-sm
                                          focus:ring-[#DA2D3B] focus:border-[#DA2D3B]
                                          @error('name') border-red-500 @enderror">
                            @error('name')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1" for="building">
                                Lokasi Gedung <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="building" name="building"
                                   value="{{ old('building') }}"
                                   placeholder="Contoh: Gedung A"
                                   class="w-full border-gray-300 rounded-md shadow-sm text-sm
                                          focus:ring-[#DA2D3B] focus:border-[#DA2D3B]
                                          @error('building') border-red-500 @enderror">
                            @error('building')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1" for="capacity">
                                Kapasitas <span class="text-red-500">*</span>
                            </label>
                            <input type="number" id="capacity" name="capacity"
                                   value="{{ old('capacity') }}"
                                   placeholder="Contoh: 40"
                                   min="1" max="1000"
                                   class="w-full border-gray-300 rounded-md shadow-sm text-sm
                                          focus:ring-[#DA2D3B] focus:border-[#DA2D3B]
                                          @error('capacity') border-red-500 @enderror">
                            @error('capacity')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1" for="status">
                            Status <span class="text-red-500">*</span>
                        </label>
                        <select id="status" name="status"
                                class="w-full border-gray-300 rounded-md shadow-sm text-sm
                                       focus:ring-[#DA2D3B] focus:border-[#DA2D3B]
                                       @error('status') border-red-500 @enderror">
                            <option value="">- Pilih Status -</option>
                            @foreach (['Dapat Dipakai', 'Tidak Dapat Dipakai'] as $status)
                                <option value="{{ $status }}" {{ old('status') === $status ? 'selected' : '' }}>
                                    {{ $status }}
                                </option>
                            @endforeach
                        </select>
                        @error('status')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center justify-end gap-4">
                        <a href="{{ route('rooms.index') }}"
                           class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900">Batal</a>
                        <button type="submit"
                                class="px-6 py-2 bg-[#DA2D3B] text-white text-sm font-medium rounded-md
                                       hover:bg-[#b0222e] focus:outline-none focus:ring-2 focus:ring-[#DA2D3B]">
                            Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>