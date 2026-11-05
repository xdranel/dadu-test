<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Tambah Mahasiswa Baru') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form action="{{ route('students.store') }}" method="POST">
                        @csrf
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Student Number -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Nomor Induk Mahasiswa (NIM) <span class="text-red-500">*</span></label>
                                <input type="text" name="student_number" value="{{ old('student_number') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                @error('student_number') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>

                            <!-- Name -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Nama Mahasiswa <span class="text-red-500">*</span></label>
                                <input type="text" name="name" value="{{ old('name') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>

                            <!-- Gender -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Jenis Kelamin</label>
                                <select name="gender" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="" disabled selected>Pilih</option>
                                    <option value="Laki-laki" {{ old('gender') == 'Laki-laki' ? 'selected' : '' }}>Laki-laki</option>
                                    <option value="Perempuan" {{ old('gender') == 'Perempuan' ? 'selected' : '' }}>Perempuan</option>
                                </select>
                            </div>

                            <!-- Religion -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Agama</label>
                                <input type="text" name="religion" value="{{ old('religion') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <!-- Faculty -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Fakultas</label>
                                <input type="text" name="faculty" value="{{ old('faculty') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <!-- Study Program -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1" for="study_program">
                                    Program Studi <span class="text-red-500">*</span>
                                </label>
                                <select name="study_program" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                    <option value="" disabled selected>Pilih Program Studi</option>
                                    @foreach (\App\Constants\ProdiList::ALL as $prodi)
                                        <option value="{{ $prodi }}" {{ old('study_program') === $prodi ? 'selected' : '' }}>
                                            {{ $prodi }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('study_program') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <!-- Academic Year -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Tahun Angkatan <span class="text-red-500">*</span></label>
                                <input type="number" name="academic_year" value="{{ old('academic_year', date('Y')) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                            </div>

                            <!-- Active Semester -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Semester Aktif</label>
                                <input type="number" name="active_semester" value="{{ old('active_semester', 1) }}" min="1" max="14" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <!-- Class -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Kelas</label>
                                <input type="text" name="class_name" value="{{ old('class_name') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>



                            <!-- Status -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Status <span class="text-red-500">*</span></label>
                                <select name="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                    <option value="Aktif" {{ old('status') == 'Aktif' ? 'selected' : '' }}>Aktif</option>
                                    <option value="Cuti" {{ old('status') == 'Cuti' ? 'selected' : '' }}>Cuti</option>
                                    <option value="Lulus" {{ old('status') == 'Lulus' ? 'selected' : '' }}>Lulus</option>
                                    <option value="Drop Out" {{ old('status') == 'Drop Out' ? 'selected' : '' }}>Drop Out</option>
                                </select>
                            </div>

                            <!-- Email -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Email Kampus</label>
                                <input type="email" name="email" value="{{ old('email') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                @error('email') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>

                            <!-- Phone Number -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Nomor Telepon</label>
                                <input type="text" name="phone_number" value="{{ old('phone_number') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <!-- Parent Phone Number -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Nomor Telepon Orang Tua</label>
                                <input type="text" name="parent_phone_number" value="{{ old('parent_phone_number') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <!-- High School -->
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700">Asal Sekolah / SMA</label>
                                <input type="text" name="high_school" value="{{ old('high_school') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <!-- Passed Phases -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Lulus TPB</label>
                                <input type="text" name="passed_tpb" value="{{ old('passed_tpb') }}" placeholder="Contoh: 1819/2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Lulus TK II</label>
                                <input type="text" name="passed_tkii" value="{{ old('passed_tkii') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Lulus TK III</label>
                                <input type="text" name="passed_tkiii" value="{{ old('passed_tkiii') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Lulus TK IV</label>
                                <input type="text" name="passed_tkiv" value="{{ old('passed_tkiv') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                        </div>

                        <div class="mt-8 flex justify-end gap-3">
                            <a href="{{ route('students.index') }}" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-300">Batal</a>
                            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 font-bold shadow">
                                Simpan Data Mahasiswa
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>