<x-app-layout>
    <x-slot name="header">Edit Data Mahasiswa</x-slot>

    <div class="max-w-3xl mx-auto">
        <div class="campus-card p-6">

            @if ($errors->any())
                <div class="mb-5 p-4 bg-rose-50 border border-rose-200 rounded-xl">
                    <p class="font-semibold text-rose-700 mb-2">Terdapat kesalahan:</p>
                    <ul class="list-disc list-inside text-rose-600 text-sm space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('students.update', $student) }}" method="POST"
                  enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <!-- Identitas -->
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">
                    Identitas
                </p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">

                    <!-- NIM -->
                    <div>
                        <label class="campus-label">NIM (Nomor Induk Mahasiswa) <span class="text-rose-400">*</span></label>
                        <input type="text" name="student_number"
                               value="{{ old('student_number', $student->student_number) }}"
                               class="campus-input @error('student_number') border-rose-400 @enderror">
                        @error('student_number')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
                    </div>

                    <!-- Nama Mahasiswa -->
                    <div>
                        <label class="campus-label">Nama Lengkap <span class="text-rose-400">*</span></label>
                        <input type="text" name="name"
                               value="{{ old('name', $student->name) }}"
                               class="campus-input @error('name') border-rose-400 @enderror">
                        @error('name')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
                    </div>

                    <!-- Jenis Kelamin -->
                    <div>
                        <label class="campus-label">Jenis Kelamin</label>
                        <select name="gender" class="campus-select">
                            <option value="">- Pilih -</option>
                            @foreach (['Laki-laki', 'Perempuan'] as $jk)
                                <option value="{{ $jk }}"
                                    {{ old('gender', $student->gender) === $jk ? 'selected' : '' }}>
                                    {{ $jk }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Agama -->
                    <div>
                        <label class="campus-label">Agama</label>
                        <select name="religion" class="campus-select">
                            <option value="">- Pilih -</option>
                            @foreach (['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu'] as $religion)
                                <option value="{{ $religion }}"
                                    {{ old('religion', $student->religion) === $religion ? 'selected' : '' }}>
                                    {{ $religion }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                </div>

                <!-- Pas Foto -->
                <div class="mb-5">
                    <label class="campus-label">Pas Foto</label>
                    <div class="flex items-center gap-4">
                        @if($student->profile_photo)
                            <img src="{{ Storage::url($student->profile_photo) }}"
                                 alt="Foto" class="w-16 h-20 object-cover rounded-lg border border-slate-200">
                        @endif
                        <input type="file" name="profile_photo" accept="image/*"
                               class="campus-input text-sm text-slate-500
                                      file:mr-3 file:py-1.5 file:px-3 file:rounded-lg
                                      file:border-0 file:text-sm file:font-semibold
                                      file:bg-indigo-50 file:text-[#DA2D3B]
                                      hover:file:bg-indigo-100">
                    </div>
                    <p class="text-xs text-slate-400 mt-1">Format: JPG, PNG. Maks 2MB.</p>
                </div>

                <!-- Akademik -->
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3 mt-6">
                    Data Akademik
                </p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">

                    <!-- Fakultas -->
                    <div>
                        <label class="campus-label">Fakultas</label>
                        <input type="text" name="faculty"
                               value="{{ old('faculty', $student->faculty) }}"
                               placeholder="Contoh: Fakultas Informatika"
                               class="campus-input">
                    </div>

                    <!-- Program Studi -->
                    <div>
                        <label class="campus-label">Program Studi <span class="text-rose-400">*</span></label>
                        <select name="study_program"
                                class="campus-select @error('study_program') border-rose-400 @enderror">
                            <option value="">- Pilih Prodi -</option>
                            @foreach (\App\Constants\ProdiList::ALL as $prodi)
                                <option value="{{ $prodi }}"
                                    {{ old('study_program', $student->study_program) === $prodi ? 'selected' : '' }}>
                                    {{ $prodi }}
                                </option>
                            @endforeach
                        </select>
                        @error('study_program')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
                    </div>

                    <!-- Tahun Akademik -->
                    <div>
                        <label class="campus-label">Tahun Akademik <span class="text-rose-400">*</span></label>
                        <input type="text" name="academic_year"
                               value="{{ old('academic_year', $student->academic_year) }}"
                               placeholder="Contoh: 2024"
                               class="campus-input @error('academic_year') border-rose-400 @enderror">
                        @error('academic_year')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
                    </div>

                    <!-- Semester Tanggal -->
                    <div>
                        <label class="campus-label">Semester Aktif</label>
                        <select name="active_semester" class="campus-select">
                            <option value="">- Pilih -</option>
                            @foreach (range(1, 14) as $sem)
                                <option value="{{ $sem }}"
                                    {{ old('active_semester', $student->active_semester) == $sem ? 'selected' : '' }}>
                                    Semester {{ $sem }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Kelas -->
                    <div>
                        <label class="campus-label">Kelas</label>
                        <input type="text" name="class_name"
                               value="{{ old('class_name', $student->class_name) }}"
                               placeholder="Contoh: IF-04-03"
                               class="campus-input">
                    </div>



                    <!-- Status -->
                    <div>
                        <label class="campus-label">Status <span class="text-rose-400">*</span></label>
                        <select name="status"
                                class="campus-select @error('status') border-rose-400 @enderror">
                            @foreach (['Aktif', 'Cuti', 'Lulus', 'Drop Out'] as $status)
                                <option value="{{ $status }}"
                                    {{ old('status', $student->status) === $status ? 'selected' : '' }}>
                                    {{ $status }}
                                </option>
                            @endforeach
                        </select>
                        @error('status')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
                    </div>

                </div>

                <!-- Kontak -->
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3 mt-6">
                    Kontak
                </p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">

                    <!-- Email -->
                    <div>
                        <label class="campus-label">Email</label>
                        <input type="email" name="email"
                               value="{{ old('email', $student->email) }}"
                               placeholder="email@student.ac.id"
                               class="campus-input @error('email') border-rose-400 @enderror">
                        @error('email')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
                    </div>

                    <!-- No HP -->
                    <div>
                        <label class="campus-label">No. HP</label>
                        <input type="text" name="phone_number"
                               value="{{ old('phone_number', $student->phone_number) }}"
                               placeholder="08xxxxxxxxxx"
                               class="campus-input">
                    </div>

                </div>

                <!-- Tombol -->
                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                    <a href="{{ route('students.index') }}"
                       class="btn btn-secondary">Batal</a>
                    <button type="submit" class="btn btn-primary">
                        Perbarui Data
                    </button>
                </div>

            </form>
        </div>
    </div>
</x-app-layout>