<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Tambah Mata Kuliah (Course)
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

                <form action="{{ route('courses.store') }}" method="POST">
                    @csrf

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                        <!-- Kode MK -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1" for="course_code">
                                Kode MK <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="course_code" name="course_code"
                                   value="{{ old('course_code') }}"
                                   placeholder="Contoh: IF201"
                                   class="w-full border-gray-300 rounded-md shadow-sm text-sm
                                          focus:ring-[#DA2D3B] focus:border-[#DA2D3B]
                                          @error('course_code') border-red-500 @enderror">
                            @error('course_code')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Nama MK -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1" for="name">
                                Nama Mata Kuliah <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="name" name="name"
                                   value="{{ old('name') }}"
                                   placeholder="Contoh: Pemrograman Web"
                                   class="w-full border-gray-300 rounded-md shadow-sm text-sm
                                          focus:ring-[#DA2D3B] focus:border-[#DA2D3B]
                                          @error('name') border-red-500 @enderror">
                            @error('name')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                        <!-- Prodi -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1" for="study_program">
                                Program Studi <span class="text-red-500">*</span>
                            </label>
                            <select id="study_program" name="study_program"
                                    class="w-full border-gray-300 rounded-md shadow-sm text-sm
                                           focus:ring-[#DA2D3B] focus:border-[#DA2D3B]
                                           @error('study_program') border-red-500 @enderror">
                                <option value="">- Pilih Prodi -</option>
                                @foreach (\App\Constants\ProdiList::ALL as $prodi)
                                    <option value="{{ $prodi }}" {{ old('study_program') === $prodi ? 'selected' : '' }}>
                                        {{ $prodi }}
                                    </option>
                                @endforeach
                            </select>
                            @error('study_program')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Kelas -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1" for="class">
                                Kelas <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="class" name="class"
                                   value="{{ old('class') }}"
                                   placeholder="Contoh: A"
                                   class="w-full border-gray-300 rounded-md shadow-sm text-sm
                                          focus:ring-[#DA2D3B] focus:border-[#DA2D3B]
                                          @error('class') border-red-500 @enderror">
                            @error('class')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
                        <!-- Semester -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1" for="semester">
                                Semester <span class="text-red-500">*</span>
                            </label>
                            <select id="semester" name="semester"
                                    class="w-full border-gray-300 rounded-md shadow-sm text-sm
                                           focus:ring-[#DA2D3B] focus:border-[#DA2D3B]
                                           @error('semester') border-red-500 @enderror">
                                <option value="">- Pilih -</option>
                                @foreach (range(1, 8) as $sem)
                                    <option value="{{ $sem }}" {{ old('semester') == $sem ? 'selected' : '' }}>
                                        Semester {{ $sem }}
                                    </option>
                                @endforeach
                            </select>
                            @error('semester')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- SKS -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1" for="credits">
                                SKS <span class="text-red-500">*</span>
                            </label>
                            <select id="credits" name="credits"
                                    class="w-full border-gray-300 rounded-md shadow-sm text-sm
                                           focus:ring-[#DA2D3B] focus:border-[#DA2D3B]
                                           @error('credits') border-red-500 @enderror">
                                <option value="">- Pilih -</option>
                                @foreach (range(1, 6) as $credits)
                                    <option value="{{ $credits }}" {{ old('credits') == $credits ? 'selected' : '' }}>
                                        {{ $credits }} SKS
                                    </option>
                                @endforeach
                            </select>
                            @error('credits')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Jenis -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1" for="type">
                                Jenis <span class="text-red-500">*</span>
                            </label>
                            <select id="type" name="type"
                                    class="w-full border-gray-300 rounded-md shadow-sm text-sm
                                           focus:ring-[#DA2D3B] focus:border-[#DA2D3B]
                                           @error('type') border-red-500 @enderror">
                                <option value="">- Pilih -</option>
                                @foreach (['Wajib', 'Pilihan'] as $type)
                                    <option value="{{ $type }}" {{ old('type') === $type ? 'selected' : '' }}>
                                        {{ $type }}
                                    </option>
                                @endforeach
                            </select>
                            @error('type')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Status -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1" for="status">
                            Status <span class="text-red-500">*</span>
                        </label>
                        <select id="status" name="status"
                                class="w-full border-gray-300 rounded-md shadow-sm text-sm
                                       focus:ring-[#DA2D3B] focus:border-[#DA2D3B]
                                       @error('status') border-red-500 @enderror">
                            <option value="">- Pilih Status -</option>
                            @foreach (['Aktif', 'Tidak Aktif'] as $status)
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
                        <a href="{{ route('courses.index') }}"
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
