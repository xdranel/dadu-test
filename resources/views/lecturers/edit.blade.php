<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Lecturer Data: ') }} {{ ($lecturer->front_title ? $lecturer->front_title . ' ' : '') . $lecturer->name . ($lecturer->back_title ? ', ' . $lecturer->back_title : '') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 border-b border-gray-200">
                    
                    <form action="{{ route('lecturers.update', $lecturer->id) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            
                            <!-- Nama -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Nama <span class="text-red-500">*</span></label>
                                <input type="text" name="name" value="{{ old('name', $lecturer->name) }}" required 
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#DA2D3B] focus:ring focus:ring-[#DA2D3B]/20">
                                @error('name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>

                            <!-- Kode Dosen -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Kode Dosen <span class="text-red-500">*</span></label>
                                <input type="text" name="lecturer_code" value="{{ old('lecturer_code', $lecturer->lecturer_code) }}" required 
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#DA2D3B] focus:ring focus:ring-[#DA2D3B]/20">
                                @error('lecturer_code') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>

                            <!-- Status Aktif -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Status Aktif</label>
                                <input type="text" name="active_status" value="{{ old('active_status', $lecturer->active_status) }}" 
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#DA2D3B] focus:ring focus:ring-[#DA2D3B]/20">
                            </div>

                            <!-- NIP YPT -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">NIP YPT</label>
                                <input type="text" name="nip_ypt" value="{{ old('nip_ypt', $lecturer->nip_ypt) }}"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#DA2D3B] focus:ring focus:ring-[#DA2D3B]/20">
                            </div>

                            <!-- NIDN -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">NIDN</label>
                                <input type="text" name="nidn" value="{{ old('nidn', $lecturer->nidn) }}"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#DA2D3B] focus:ring focus:ring-[#DA2D3B]/20">
                            </div>

                            <!-- NUPTK -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">NUPTK</label>
                                <input type="text" name="nuptk" value="{{ old('nuptk', $lecturer->nuptk) }}"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#DA2D3B] focus:ring focus:ring-[#DA2D3B]/20">
                            </div>

                            <!-- Gelar Depan -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Gelar Depan</label>
                                <input type="text" name="front_title" value="{{ old('front_title', $lecturer->front_title) }}" 
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#DA2D3B] focus:ring focus:ring-[#DA2D3B]/20">
                            </div>

                            <!-- Gelar Belakang -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Gelar Belakang</label>
                                <input type="text" name="back_title" value="{{ old('back_title', $lecturer->back_title) }}" 
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#DA2D3B] focus:ring focus:ring-[#DA2D3B]/20">
                            </div>

                            <!-- Email Domain -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Email Domain</label>
                                <input type="email" name="email_domain" value="{{ old('email_domain', $lecturer->email_domain) }}" 
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#DA2D3B] focus:ring focus:ring-[#DA2D3B]/20">
                            </div>

                            <!-- JFA -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">JFA</label>
                                <input type="text" name="jfa" value="{{ old('jfa', $lecturer->jfa) }}" 
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#DA2D3B] focus:ring focus:ring-[#DA2D3B]/20">
                            </div>

                            <!-- Bidang Keilmuan -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Bidang Keilmuan Jafa Terakhir</label>
                                <input type="text" name="academic_interest" value="{{ old('academic_interest', $lecturer->academic_interest) }}"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#DA2D3B] focus:ring focus:ring-[#DA2D3B]/20">
                            </div>

                            <!-- PRODI -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">PRODI</label>
                                <select name="study_program" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#DA2D3B] focus:ring focus:ring-[#DA2D3B]/20">
                                    <option value="">- Pilih Prodi -</option>
                                    @foreach (\App\Constants\ProdiList::ALL as $p)
                                        <option value="{{ $p }}" {{ old('study_program', $lecturer->study_program) === $p ? 'selected' : '' }}>
                                            {{ $p }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Status Kepegawaian -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Status Kepegawaian</label>
                                <input type="text" name="employment_status" value="{{ old('employment_status', $lecturer->employment_status) }}" 
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#DA2D3B] focus:ring focus:ring-[#DA2D3B]/20">
                            </div>

                        </div>

                        <!-- Buttons -->
                        <div class="mt-6 flex items-center justify-end gap-3">
                            <a href="{{ route('lecturers.index') }}" class="text-gray-600 hover:text-gray-900 font-medium px-4 py-2">Batal</a>
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded shadow">
                                Simpan Perubahan
                            </button>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>