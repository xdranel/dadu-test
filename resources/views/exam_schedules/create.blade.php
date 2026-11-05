@php
    $initialCourse = null;
    if (old('course_code')) {
        $courseCode = old('course_code');
        $course = \App\Models\Course::where('course_code', $courseCode)->first();
        if ($course) {
            $activePeriod = \App\Models\AcademicPeriod::current();
            $activeYear = $activePeriod?->year;
            $activeSemester = $activePeriod?->semester;
            if (!\App\Models\StudentCourse::where('academic_year', $activeYear)->where('semester', $activeSemester)->exists()) {
                $latestKrs = \App\Models\StudentCourse::orderByDesc('academic_year')->orderByDesc('semester')->first();
                if ($latestKrs) {
                    $activeYear = $latestKrs->academic_year;
                    $activeSemester = $latestKrs->semester;
                }
            }

            $kelasData = \App\Models\StudentCourse::where('course_code', $courseCode)
                ->where('academic_year', $activeYear)
                ->where('semester', $activeSemester)
                ->whereNotNull('course_class')
                ->where('course_class', '!=', '')
                ->selectRaw('course_class, COUNT(*) as mhs_count')
                ->groupBy('course_class')
                ->orderBy('course_class')
                ->get()
                ->map(fn($k) => [
                    'course_id'    => $course->id,
                    'course_class' => $k->course_class,
                    'class'        => $k->course_class,
                    'mhs_count'    => (int) $k->mhs_count,
                    'room_count'   => (int) ceil($k->mhs_count / 30),
                ]);

            if ($kelasData->isEmpty()) {
                $kelasData = collect([[
                    'course_id'    => $course->id,
                    'course_class' => $course->class ?: 'Umum',
                    'class'        => $course->class ?: 'Umum',
                    'mhs_count'    => 0,
                    'room_count'   => 1,
                ]]);
            }

            $initialCourse = [
                'id'            => $course->id,
                'course_code'   => $course->course_code,
                'name'          => $course->name,
                'credits'       => $course->credits,
                'study_program' => $course->study_program,
                'total_mhs'     => (int) $kelasData->sum('mhs_count'),
                'kelas'         => $kelasData,
            ];
        }
    }
@endphp
<x-app-layout>
    <x-slot name="header">Tambah Jadwal Ujian</x-slot>

    <style>
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    </style>

    <div class="w-full">
        <div class="campus-card p-6">

            <!-- Error Messages -->
            @php
                $otherErrors = collect($errors->toArray())->except('custom_answer_sheet_url')->flatten();
            @endphp
            @if ($otherErrors->isNotEmpty())
                <div class="mb-5 p-4 bg-rose-50 border border-rose-200 rounded-xl">
                    <p class="font-semibold text-rose-700 mb-2 text-sm">Terdapat kesalahan input:</p>
                    <ul class="list-disc list-inside text-rose-600 text-sm space-y-0.5">
                        @foreach ($otherErrors as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('exam_schedules.store') }}" method="POST" x-data="examForm()"
                  @course-selected.window="onCourseChange($event.detail.course)"
                  @course-cleared.window="selectedCourseCode = ''; onCourseChange()"
                  @lecturer-selected.window="lecturerCode = $event.detail.lecturer.lecturer_code; onLecturerChange()"
                  @lecturer-cleared.window="lecturerCode = ''; onLecturerChange()"
                  @lecturer2-selected.window="lecturerCode2 = $event.detail.lecturer.lecturer_code; onLecturerChange()"
                  @lecturer2-cleared.window="lecturerCode2 = ''; onLecturerChange()">
                @csrf

                <!-- Data Mata Kuliah -->
                <div class="mb-8">
                    <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-4">
                        Data Mata Kuliah
                    </h3>

                    <!-- Three column layout: Kode MK, Kode Dosen 1, Kode Dosen 2 -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <!-- Kode Mata Kuliah (Kiri) -->
                        <div x-data="{ ac: makeAutocomplete('/api/search/courses') }"
                             x-init="ac.initFromValue(selectedCourseCode, courses, 'course_code', 'name')"
                             style="position: relative">
                            <label class="campus-label">
                                Kode Mata Kuliah <span class="text-rose-500">*</span>
                            </label>
                            <div style="position:relative">
                                <input type="text"
                                       x-model="ac.query"
                                       @input="ac.search()"
                                       @keydown.escape="ac.open = false"
                                       @click.outside="ac.open = false"
                                       placeholder="Ketik kode atau nama mata kuliah..."
                                       class="campus-input @error('course_code') !border-rose-500 @enderror"
                                       autocomplete="off">
                                
                                <span x-show="ac.loading" 
                                      style="position:absolute;right:10px;top:50%;transform:translateY(-50%)">
                                    <svg class="w-4 h-4 text-slate-400" style="animation:spin 1s linear infinite" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </span>
                                <button type="button" x-show="ac.selected" 
                                        @click="ac.clear(); $dispatch('course-cleared')"
                                        style="position:absolute;right:10px;top:50%;
                                               transform:translateY(-50%);background:none;
                                               border:none;cursor:pointer;padding:0;display:flex;align-items:center;">
                                    <svg class="w-4 h-4 text-slate-400 hover:text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <ul x-show="ac.open" style="position:absolute;z-index:50;
                                width:100%;background:white;
                                border:1px solid #e2e8f0;
                                border-radius:0.5rem;
                                margin-top:4px;max-height:240px;overflow-y:auto;
                                list-style:none;padding:4px 0;box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);" x-cloak>
                                <template x-for="item in ac.results" :key="item.course_code">
                                    <li @click="ac.select(item, 'name', 'course_code'); 
                                                $dispatch('course-selected', { course: item })"
                                        style="padding:8px 12px;cursor:pointer;font-size:13px"
                                        @mouseenter="$el.style.background='#f1f5f9'"
                                        @mouseleave="$el.style.background=''">
                                        <span x-text="item.course_code" style="font-weight:500"></span>
                                        &nbsp;—&nbsp;
                                        <span x-text="item.name" style="color:#64748b"></span>
                                    </li>
                                </template>
                                <li x-show="ac.results.length === 0 && !ac.loading && ac.query.length >= 2"
                                    style="padding:8px 12px;font-size:13px;color:#64748b">
                                    Mata kuliah tidak ditemukan
                                </li>
                            </ul>

                            <input type="hidden" name="course_code" :value="ac.selected ? ac.selected.course_code : ''">
                            @error('course_code')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Kode Dosen 1 (Tengah) -->
                        <div x-data="{ ac: makeAutocomplete('/api/search/lecturers') }"
                             x-init="ac.initFromValue(lecturerCode, lecturers, 'lecturer_code', 'name')"
                             style="position: relative">
                            <label class="campus-label">
                                Kode Dosen <span class="text-rose-500">*</span>
                            </label>
                            <div style="position:relative">
                                <input type="text"
                                       x-model="ac.query"
                                       @input="ac.search()"
                                       @keydown.escape="ac.open = false"
                                       @click.outside="ac.open = false"
                                       placeholder="Ketik kode atau nama dosen..."
                                       class="campus-input @error('lecturer_code') !border-rose-500 @enderror"
                                       autocomplete="off">
                                
                                <span x-show="ac.loading" 
                                      style="position:absolute;right:10px;top:50%;transform:translateY(-50%)">
                                    <svg class="w-4 h-4 text-slate-400" style="animation:spin 1s linear infinite" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </span>
                                <button type="button" x-show="ac.selected" 
                                        @click="ac.clear(); $dispatch('lecturer-cleared')"
                                        style="position:absolute;right:10px;top:50%;
                                               transform:translateY(-50%);background:none;
                                               border:none;cursor:pointer;padding:0;display:flex;align-items:center;">
                                    <svg class="w-4 h-4 text-slate-400 hover:text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <ul x-show="ac.open" style="position:absolute;z-index:50;
                                width:100%;background:white;
                                border:1px solid #e2e8f0;
                                border-radius:0.5rem;
                                margin-top:4px;max-height:240px;overflow-y:auto;
                                list-style:none;padding:4px 0;box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);" x-cloak>
                                <template x-for="item in ac.results" :key="item.lecturer_code">
                                    <li @click="ac.select(item, 'name', 'lecturer_code'); 
                                                $dispatch('lecturer-selected', { lecturer: item })"
                                        style="padding:8px 12px;cursor:pointer;font-size:13px"
                                        @mouseenter="$el.style.background='#f1f5f9'"
                                        @mouseleave="$el.style.background=''">
                                        <span x-text="item.lecturer_code" style="font-weight:700"></span>
                                        &nbsp;—&nbsp;
                                        <span x-text="item.name" style="color:#64748b"></span>
                                    </li>
                                </template>
                                <li x-show="ac.results.length === 0 && !ac.loading && ac.query.length >= 2"
                                    style="padding:8px 12px;font-size:13px;color:#64748b">
                                    Dosen tidak ditemukan
                                </li>
                            </ul>

                            <input type="hidden" name="lecturer_code" :value="ac.selected ? ac.selected.lecturer_code : ''">
                            @error('lecturer_code')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Kode Dosen 2 (Kanan) -->
                        <div x-data="{ ac: makeAutocomplete('/api/search/lecturers') }"
                             x-init="ac.initFromValue(lecturerCode2, lecturers, 'lecturer_code', 'name')"
                             style="position: relative">
                            <label class="campus-label">
                                Kode Dosen 2 (Opsional)
                            </label>
                            <div style="position:relative">
                                <input type="text"
                                       x-model="ac.query"
                                       @input="ac.search()"
                                       @keydown.escape="ac.open = false"
                                       @click.outside="ac.open = false"
                                       placeholder="Ketik kode atau nama dosen..."
                                       class="campus-input @error('lecturer_code_2') !border-rose-500 @enderror"
                                       autocomplete="off">
                                
                                <span x-show="ac.loading" 
                                      style="position:absolute;right:10px;top:50%;transform:translateY(-50%)">
                                    <svg class="w-4 h-4 text-slate-400" style="animation:spin 1s linear infinite" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </span>
                                <button type="button" x-show="ac.selected" 
                                        @click="ac.clear(); $dispatch('lecturer2-cleared')"
                                        style="position:absolute;right:10px;top:50%;
                                               transform:translateY(-50%);background:none;
                                               border:none;cursor:pointer;padding:0;display:flex;align-items:center;">
                                    <svg class="w-4 h-4 text-slate-400 hover:text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <ul x-show="ac.open" style="position:absolute;z-index:50;
                                width:100%;background:white;
                                border:1px solid #e2e8f0;
                                border-radius:0.5rem;
                                margin-top:4px;max-height:240px;overflow-y:auto;
                                list-style:none;padding:4px 0;box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);" x-cloak>
                                <template x-for="item in ac.results" :key="item.lecturer_code">
                                    <li @click="ac.select(item, 'name', 'lecturer_code'); 
                                                $dispatch('lecturer2-selected', { lecturer: item })"
                                        style="padding:8px 12px;cursor:pointer;font-size:13px"
                                        @mouseenter="$el.style.background='#f1f5f9'"
                                        @mouseleave="$el.style.background=''">
                                        <span x-text="item.lecturer_code" style="font-weight:700"></span>
                                        &nbsp;—&nbsp;
                                        <span x-text="item.name" style="color:#64748b"></span>
                                    </li>
                                </template>
                                <li x-show="ac.results.length === 0 && !ac.loading && ac.query.length >= 2"
                                    style="padding:8px 12px;font-size:13px;color:#64748b">
                                    Dosen tidak ditemukan
                                </li>
                            </ul>

                            <input type="hidden" name="lecturer_code_2" :value="ac.selected ? ac.selected.lecturer_code : ''">
                            @error('lecturer_code_2')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Langkah 2: Pilih Kelas & Mode Penjadwalan -->
                <div class="mb-8" x-show="selectedCourseCode" x-cloak
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0">
                    <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-4">
                        Kelas & Informasi Ujian
                    </h3>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
                        <div>
                            <label class="campus-label" for="selected_kelas">
                                Pilih Kelas <span class="text-rose-500">*</span>
                            </label>
                            <select id="selected_kelas" x-model="selectedKelas" @change="onKelasChange()"
                                    class="campus-select">
                                <option value="">- Pilih Kelas -</option>
                                <template x-if="selectedCourse">
                                    <option value="semua" x-text="'Semua kelas (' + kelasList.length + ' kelas · ' + selectedCourse.total_mhs + ' mhs)'"></option>
                                </template>
                                <template x-for="k in kelasList" :key="k.course_class">
                                    <option :value="k.course_class" x-text="k.course_class + ' — ' + k.mhs_count + ' mahasiswa'"></option>
                                </template>
                            </select>
                            <!-- Mode hidden input to submit to controller -->
                            <input type="hidden" name="mode" x-model="mode">
                            <!-- course_class hidden input for single mode -->
                            <input type="hidden" name="course_class" :value="selectedKelas">
                            <!-- course_id hidden input for single mode -->
                            <input type="hidden" name="course_id" :value="selectedCourse ? selectedCourse.id : ''">
                        </div>
                        
                        <div class="sm:col-span-2">
                            <!-- Dynamic Info Panel styled beautifully -->
                            <div x-show="courseData" class="p-4 bg-slate-50 rounded-xl border border-slate-200 h-full flex flex-col justify-center">
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                                    <div>
                                        <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Prodi</p>
                                        <p class="text-sm font-bold text-slate-800 mt-1" x-text="courseData?.prodi || '-'"></p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Nama MK</p>
                                        <p class="text-sm font-bold text-slate-800 mt-1" x-text="courseData?.nama_mk || '-'"></p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Kelas Paralel</p>
                                        <p class="text-sm font-bold text-slate-800 mt-1" x-text="infoKelas || '-'"></p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Jumlah Mahasiswa</p>
                                        <p class="text-sm font-bold mt-1" :class="(courseData?.enrolled_students_count || 0) > 30 ? 'text-amber-600' : 'text-emerald-600'">
                                            <span x-text="courseData?.enrolled_students_count || 0"></span>
                                            <span class="text-slate-400 font-normal">orang</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Langkah 3a: Mode Single (Kelas Individu) -->
                <div class="mb-8" x-show="selectedKelas && selectedKelas !== 'semua' && mode === 'single'" x-cloak
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0">
                    
                    <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-4">
                        Waktu & Tempat Ujian (Kelas Individu)
                    </h3>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <!-- Tanggal -->
                        <div>
                            <label class="campus-label" for="exam_date">
                                Tanggal Ujian <span class="text-rose-500" x-show="assessmentType !== 'TIDAK ADA ASSESSMEN BERSAMA'">*</span>
                            </label>
                            <input type="date" id="exam_date" name="exam_date"
                                   x-model="examDate" @input.debounce.500ms="checkRoomAvailability()"
                                   class="campus-input @error('exam_date') !border-rose-500 @enderror">
                            @error('exam_date')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Jam Mulai -->
                        <div>
                            <label class="campus-label" for="start_time">
                                Jam Mulai <span class="text-rose-500" x-show="assessmentType !== 'TIDAK ADA ASSESSMEN BERSAMA'">*</span>
                            </label>
                            <input type="time" id="start_time" name="start_time"
                                   x-model="startTime" @input.debounce.500ms="checkRoomAvailability()"
                                   class="campus-input @error('start_time') !border-rose-500 @enderror">
                            @error('start_time')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Jam Selesai -->
                        <div>
                            <label class="campus-label" for="end_time">
                                Jam Selesai <span class="text-rose-500" x-show="assessmentType !== 'TIDAK ADA ASSESSMEN BERSAMA'">*</span>
                            </label>
                            <input type="time" id="end_time" name="end_time"
                                   x-model="endTime" @input.debounce.500ms="checkRoomAvailability()"
                                   class="campus-input @error('end_time') !border-rose-500 @enderror">
                            @error('end_time')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Ruangan Pilihan Dinamis -->
                        <template x-if="selectedKelas && mode === 'single' && selectedKelasData">
                            <div class="sm:col-span-2 lg:col-span-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                <template x-if="selectedKelasData.room_count > 1">
                                    <div class="sm:col-span-2 lg:col-span-3">
                                        <p class="text-xs text-slate-500" x-text="'Kelas ini membutuhkan ' + selectedKelasData.room_count + ' ruangan (total ' + selectedKelasData.mhs_count + ' mahasiswa, maks. 30/ruangan)'"></p>
                                    </div>
                                </template>
                                <template x-for="(room, i) in selectedRooms" :key="i">
                                    <div>
                                        <label class="campus-label" x-text="'Ruangan ' + (i + 1) + ' *'"></label>
                                        <input type="text" 
                                               :name="'rooms[' + i + ']'" 
                                               x-model="selectedRooms[i]" 
                                               @input.debounce.500ms="checkRoomAvailability()" 
                                               placeholder="Contoh: GD-A201" 
                                               class="campus-input">
                                    </div>
                                </template>
                            </div>
                        </template>

                        <!-- Pengawas Utama -->
                        <div>
                            <label class="campus-label" for="proctor_name_1">Pengawas Utama</label>
                            <input type="text" id="proctor_name_1" name="proctor_name_1"
                                   x-model="proctorName1" placeholder="Nama pengawas utama"
                                   class="campus-input @error('proctor_name_1') !border-rose-500 @enderror">
                            @error('proctor_name_1')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Pengawas Kedua (Opsional) -->
                        <div x-show="participantDivision === 'DIBAGI 2 (JIKA PESERTA KELAS > 30)'" x-cloak>
                            <label class="campus-label" for="proctor_name_2">Pengawas Kedua (Opsional)</label>
                            <input type="text" id="proctor_name_2" name="proctor_name_2"
                                   x-model="proctorName2" placeholder="Nama pengawas kedua"
                                   class="campus-input @error('proctor_name_2') !border-rose-500 @enderror">
                            @error('proctor_name_2')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Warnings for Single Mode -->
                    <div class="space-y-3 mt-4">
                        <!-- Division warning -->
                        <div x-show="participantDivision === 'DIBAGI 2 (JIKA PESERTA KELAS > 30)' && (!room2 || !proctorName2)" x-cloak>
                            <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg text-amber-800 text-xs font-medium flex items-center gap-2">
                                <svg class="w-4 h-4 text-amber-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                                <span>Peringatan: Pilihan pembagian peserta "DIBAGI 2" aktif. Harap lengkapi Ruangan Kedua dan Pengawas Kedua.</span>
                            </div>
                        </div>

                        <!-- Room conflict warning -->
                        <div x-show="!roomAvailable && roomAvailabilityMessage" x-cloak>
                            <div class="p-3 bg-rose-50 border border-rose-100 rounded-lg text-rose-700 text-xs font-medium flex items-center gap-2">
                                <svg class="w-4 h-4 text-rose-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                                <span x-text="roomAvailabilityMessage"></span>
                            </div>
                        </div>

                        <!-- Lecturer conflict warning -->
                        <div x-show="lecturerAvailabilityMessage" x-cloak>
                            <div class="p-3 bg-rose-50 border border-rose-100 rounded-lg text-rose-700 text-xs font-medium flex items-center gap-2">
                                <svg class="w-4 h-4 text-rose-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                                <span x-text="lecturerAvailabilityMessage"></span>
                            </div>
                        </div>

                        <!-- Student conflict warning -->
                        <div x-show="studentAvailabilityMessage" x-cloak>
                            <div class="p-3 bg-rose-50 border border-rose-100 rounded-lg text-rose-700 text-xs font-medium flex items-center gap-2">
                                <svg class="w-4 h-4 text-rose-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                                <span x-text="studentAvailabilityMessage"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Langkah 3b: Mode Bulk / All (Semua Kelas) -->
                <div class="mb-8" x-show="selectedKelas === 'semua' && mode === 'all'" x-cloak
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0">
                    
                    <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-4">
                        Waktu & Tempat Ujian (Semua Kelas Paralel)
                    </h3>
                    
                    <div class="overflow-x-auto rounded-xl border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 bg-white">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider w-24">Kelas</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider w-32">Mhs & Kebutuhan</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider w-44">Tanggal Ujian <span class="text-rose-500">*</span></th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider w-32">Mulai <span class="text-rose-500">*</span></th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider w-32">Selesai <span class="text-rose-500">*</span></th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Ruangan Ujian <span class="text-rose-500">*</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white">
                                <template x-for="(k, index) in kelasList" :key="k.course_class || k.class">
                                    <tr class="hover:bg-slate-50/50">
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-bold text-slate-800">
                                            Kelas <span x-text="k.class"></span>
                                            <input type="hidden" :name="'kelas[' + index + '][course_id]'" :value="k.course_id">
                                            <input type="hidden" :name="'kelas[' + index + '][course_class]'" :value="k.course_class || k.class">
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-xs text-slate-500">
                                            <div><span x-text="k.mhs_count"></span> mahasiswa</div>
                                            <div class="text-[10px] text-slate-400">Rekomendasi: <span x-text="k.room_count"></span> ruangan</div>
                                        </td>
                                        <td class="px-2 py-2">
                                            <input type="date" :name="'kelas[' + index + '][date]'" x-model="k.date"
                                                   @change="checkClassAvailability(index)"
                                                   class="campus-input py-1 text-sm font-medium w-full">
                                        </td>
                                        <td class="px-2 py-2">
                                            <input type="time" :name="'kelas[' + index + '][start_time]'" x-model="k.start_time"
                                                   @change="checkClassAvailability(index)"
                                                   class="campus-input py-1 text-sm font-medium w-full">
                                        </td>
                                        <td class="px-2 py-2">
                                            <input type="time" :name="'kelas[' + index + '][end_time]'" x-model="k.end_time"
                                                   @change="checkClassAvailability(index)"
                                                   class="campus-input py-1 text-sm font-medium w-full">
                                        </td>
                                        <td class="px-2 py-2">
                                            <div class="flex flex-col gap-1">
                                                <template x-for="(room, j) in k.rooms" :key="j">
                                                    <div>
                                                        <input type="text" 
                                                               :name="'kelas[' + index + '][rooms][' + j + ']'" 
                                                               x-model="k.rooms[j]" 
                                                               @input.debounce.500ms="checkClassAvailability(index)" 
                                                               placeholder="Nama ruangan..." 
                                                               class="campus-input py-1 text-xs font-medium w-full min-w-[120px]">
                                                    </div>
                                                </template>
                                            </div>
                                        </td>
                                    </tr>
                                    <!-- Warn row if conflict exists -->
                                    <tr x-show="!k.available && k.message" class="bg-rose-50/30">
                                        <td colspan="6" class="px-4 py-2 border-t-0">
                                            <div class="text-[11px] text-rose-600 font-semibold flex items-center gap-1.5">
                                                <svg class="w-3.5 h-3.5 text-rose-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                                </svg>
                                                <span x-text="k.message"></span>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Konfigurasi Assessment -->
                <div class="mb-8">
                    <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-4">
                        Konfigurasi Assessment
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Jenis Assessment -->
                        <div>
                            <label class="campus-label" for="assessment_type">Jenis Assessment <span class="text-rose-500">*</span></label>
                            <select id="assessment_type" name="assessment_type"
                                    x-model="assessmentType" @change="onAssessmentTypeChange()"
                                    class="campus-select @error('assessment_type') !border-rose-500 @enderror">
                                <option value="">- Pilih -</option>
                                @foreach (['TIDAK ADA ASSESSMEN BERSAMA', 'MENGERJAKAN SOAL UJIAN', 'UJIAN LISAN / PRESENTASI / PROJECT BASED'] as $opt)
                                    <option value="{{ $opt }}" {{ old('assessment_type') === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                                @endforeach
                            </select>
                            @error('assessment_type')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Metode Pelaksanaan -->
                        <div>
                            <label class="campus-label" for="execution_method">Metode Pelaksanaan <span class="text-rose-500">*</span></label>
                            <select id="execution_method" name="execution_method"
                                    x-model="executionMethod"
                                    class="campus-select @error('execution_method') !border-rose-500 @enderror">
                                <option value="">- Pilih -</option>
                                @foreach (['TIDAK ADA ASSESSMEN BERSAMA', 'DIKERJAKAN ONSITE DI KAMPUS', 'TAKE HOME ATAU DIKERJAKAN FROM ANYWHERE'] as $opt)
                                    <option value="{{ $opt }}" {{ old('execution_method') === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                                @endforeach
                            </select>
                            @error('execution_method')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Waktu Pelaksanaan -->
                        <div>
                            <label class="campus-label" for="execution_time">Waktu Pelaksanaan <span class="text-rose-500">*</span></label>
                            <select id="execution_time" name="execution_time"
                                    x-model="executionTime"
                                    class="campus-select @error('execution_time') !border-rose-500 @enderror">
                                <option value="">- Pilih -</option>
                                @foreach (['TIDAK ADA ASSESSMEN BERSAMA', 'SESUAI JADWAL KELAS PERKULIAHAN', 'JADWAL SERENTAK se-TUS per MK Prodi', 'JADWAL SERENTAK se-TUNC'] as $opt)
                                    <option value="{{ $opt }}" {{ old('execution_time') === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                                @endforeach
                            </select>
                            @error('execution_time')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Pembagian Peserta Ujian -->
                        <div>
                            <label class="campus-label" for="participant_division">Pembagian Peserta Ujian <span class="text-rose-500">*</span></label>
                            <select id="participant_division" name="participant_division"
                                    x-model="participantDivision"
                                    class="campus-select @error('participant_division') !border-rose-500 @enderror">
                                <option value="">- Pilih -</option>
                                @foreach (['TIDAK ADA ASSESSMEN BERSAMA', 'DIBAGI 2 (JIKA PESERTA KELAS > 30)', 'SEMUA PESERTA DIGABUNG DALAM 1 RUANGAN'] as $opt)
                                    <option value="{{ $opt }}" {{ old('participant_division') === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                                @endforeach
                            </select>
                            @error('participant_division')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Soal Ujian -->
                        <div>
                            <label class="campus-label" for="question_type">Soal Ujian <span class="text-rose-500">*</span></label>
                            <select id="question_type" name="question_type"
                                    x-model="questionType"
                                    class="campus-select @error('question_type') !border-rose-500 @enderror">
                                <option value="">- Pilih -</option>
                                @foreach (['TIDAK ADA ASSESSMEN BERSAMA', 'PERLU PENGGANDAAN FISIK (HARDCOPY)', 'SOAL TERSEDIA DI LMS'] as $opt)
                                    <option value="{{ $opt }}" {{ old('question_type') === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                                @endforeach
                            </select>
                            @error('question_type')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Lembar Jawaban -->
                        <div>
                            <label class="campus-label" for="answer_sheet_type">Lembar Jawaban <span class="text-rose-500">*</span></label>
                            <select id="answer_sheet_type" name="answer_sheet_type"
                                    x-model="answerSheetType"
                                    class="campus-select @error('answer_sheet_type') !border-rose-500 @enderror">
                                <option value="">- Pilih -</option>
                                @foreach (['TIDAK ADA ASSESSMEN BERSAMA', 'MENGGUNAKAN LEMBAR JAWABAN STANDAR AKADEMIK', 'LEMBAR JAWABAN CUSTOM (PERLU PENGGANDAAN)', 'TIDAK PERLU LEMBAR JAWABAN'] as $opt)
                                    <option value="{{ $opt }}" {{ old('answer_sheet_type') === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                                @endforeach
                            </select>
                            @error('answer_sheet_type')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Kebutuhan Kertas Buram -->
                        <div>
                            <label class="campus-label" for="needs_scratch_paper">Kebutuhan Kertas Buram <span class="text-rose-500">*</span></label>
                            <select id="needs_scratch_paper" name="needs_scratch_paper"
                                    x-model="needsScratchPaper"
                                    class="campus-select @error('needs_scratch_paper') !border-rose-500 @enderror">
                                <option value="">- Pilih -</option>
                                @foreach (['TIDAK PERLU', 'PERLU'] as $opt)
                                    <option value="{{ $opt }}" {{ old('needs_scratch_paper') === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                                @endforeach
                            </select>
                            @error('needs_scratch_paper')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Pengawas -->
                        <div>
                            <label class="campus-label" for="proctor_type">Pengawas <span class="text-rose-500">*</span></label>
                            <select id="proctor_type" name="proctor_type"
                                    x-model="proctorType"
                                    class="campus-select @error('proctor_type') !border-rose-500 @enderror">
                                <option value="">- Pilih -</option>
                                @foreach (['TIDAK ADA ASSESSMEN BERSAMA', 'DOSEN PENGAMPU', 'ASPRAK / ASLAB / ASDOS (PRODI SETOR NAMA)', 'TLH / STUDENT STAFF UNIT (DICARIKAN AKADEMIK)', 'TIDAK PERLU PENGAWAS'] as $opt)
                                    <option value="{{ $opt }}" {{ old('proctor_type') === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                                @endforeach
                            </select>
                            @error('proctor_type')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- URL & Aturan -->
                <div class="mb-8">
                    <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-4">
                        URL & Aturan Ujian
                    </h3>

                    <div class="space-y-4">
                        <!-- URL Soal Ujian -->
                        <div>
                            <label class="campus-label" for="exam_question_url">URL Soal Ujian</label>
                            <input type="url" id="exam_question_url" name="exam_question_url"
                                   value="{{ old('exam_question_url') }}" placeholder="https://drive.google.com/..."
                                   class="campus-input @error('exam_question_url') !border-rose-500 @enderror">
                            <p class="mt-1.5 text-xs text-slate-400 flex items-start gap-1">
                                <svg class="w-3.5 h-3.5 flex-shrink-0 mt-0.5 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                File diupload di drive masing-masing dosen, share url view only hanya ke akademiksby@telkomuniversity.ac.id
                            </p>
                            @error('exam_question_url')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- URL Lembar Jawaban Custom -->
                        <div>
                            <label class="campus-label" for="custom_answer_sheet_url">
                                URL Lembar Jawaban Custom <span class="text-rose-500" x-show="answerSheetType === 'LEMBAR JAWABAN CUSTOM (PERLU PENGGANDAAN)'" x-cloak>*</span>
                            </label>
                            <input type="url" id="custom_answer_sheet_url" name="custom_answer_sheet_url"
                                   x-model="customAnswerSheetUrl" placeholder="https://drive.google.com/..."
                                   class="campus-input @error('custom_answer_sheet_url') !border-rose-500 @enderror">
                            <p class="mt-1.5 text-xs text-slate-400 flex items-start gap-1">
                                <svg class="w-3.5 h-3.5 flex-shrink-0 mt-0.5 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                File diupload di drive masing-masing dosen, share url view only hanya ke akademiksby@telkomuniversity.ac.id
                            </p>
                            @error('custom_answer_sheet_url')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Aturan Ujian -->
                        <div>
                            <label class="campus-label" for="exam_rules">Aturan Ujian</label>
                            <textarea id="exam_rules" name="exam_rules" rows="3"
                                      placeholder="Contoh: Open book, boleh kalkulator..."
                                      class="campus-input !h-auto @error('exam_rules') !border-rose-500 @enderror">{{ old('exam_rules') }}</textarea>
                            <p class="mt-1.5 text-xs text-slate-400 flex items-start gap-1">
                                <svg class="w-3.5 h-3.5 flex-shrink-0 mt-0.5 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Open book / close book / open catatan / boleh kalkulator / dll
                            </p>
                            @error('exam_rules')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- BUTTONS -->
                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200">
                    <a href="{{ route('exam_schedules.index') }}" class="btn btn-secondary">Batal</a>
                    <button type="submit" class="btn btn-primary" :disabled="!formAvailable" :class="{'opacity-50 cursor-not-allowed': !formAvailable}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Simpan Jadwal
                    </button>
                </div>
            </form>

        </div>
    </div>

    <!-- Alpine.js: AJAX fetch course data -->
    <script>
        function makeAutocomplete(endpoint, initialItem = null, labelKey = 'name') {
            return {
                query: initialItem ? initialItem[labelKey] : '',
                results: [],
                selected: initialItem || null,
                open: false,
                loading: false,
                debounceTimer: null,

                initFromValue(val, list, valKey, lblKey) {
                    if (val && list) {
                        const found = list.find(item => item[valKey] === val);
                        if (found) {
                            this.selected = found;
                            this.query = found[lblKey];
                        }
                    }
                },

                async search() {
                    clearTimeout(this.debounceTimer);
                    if (this.query.length < 2) {
                        this.results = [];
                        this.open = false;
                        if (this.query.length === 0) {
                            this.selected = null;
                        }
                        return;
                    }
                    this.debounceTimer = setTimeout(async () => {
                        this.loading = true;
                        try {
                            const res = await fetch(
                                endpoint + '?q=' + encodeURIComponent(this.query),
                                { headers: { 
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector(
                                        'meta[name="csrf-token"]'
                                    ).content 
                                }}
                            );
                            this.results = await res.json();
                            this.open = this.results.length > 0;
                        } catch(e) {
                            this.results = [];
                        } finally {
                            this.loading = false;
                        }
                    }, 300);
                },

                select(item, labelKey, valueKey) {
                    this.selected = item;
                    this.query = item[labelKey];
                    this.open = false;
                    this.results = [];
                },

                clear() {
                    this.query = '';
                    this.selected = null;
                    this.open = false;
                    this.results = [];
                }
            }
        }

        function examForm() {
            return {
                courses: @json($courses),
                lecturers: @json($lecturers),
                selectedCourseCode: '{{ old("course_code", "") }}',
                selectedKelas: '{{ old("selected_kelas", "") }}',
                kelasList: [],
                mode: '{{ old("mode", "single") }}',
                prodiLabel: '',
                infoKelas: '',
                courseId: '{{ old("course_id", "") }}',
                courseData: null,
                loading: false,

                 // Check Room Availability fields
                examDate: '{!! old("exam_date", "") !!}',
                startTime: '{!! old("start_time", "") !!}',
                endTime: '{!! old("end_time", "") !!}',
                rooms: @json($rooms),
                selectedRooms: @json(old('rooms', ['', ''])),
                get room1() {
                    return this.selectedRooms[0] || '';
                },
                get room2() {
                    return this.selectedRooms[1] || '';
                },
                proctorName1: '{!! old("proctor_name_1", "") !!}',
                proctorName2: '{!! old("proctor_name_2", "") !!}',
                participantDivision: '{!! old("participant_division", "") !!}',
                executionMethod: '{!! old("execution_method", "") !!}',
                executionTime: '{!! old("execution_time", "") !!}',
                questionType: '{!! old("question_type", "") !!}',
                answerSheetType: '{!! old("answer_sheet_type", "") !!}',
                needsScratchPaper: '{!! old("needs_scratch_paper", "") !!}',
                proctorType: '{!! old("proctor_type", "") !!}',
                customAnswerSheetUrl: '{!! old("custom_answer_sheet_url", "") !!}',
                lecturerCode: '{!! old("lecturer_code", "") !!}',
                lecturerCode2: '{!! old("lecturer_code_2", "") !!}',
                assessmentType: '{!! old("assessment_type", "") !!}',
                formAvailable: true,
                roomAvailable: true,
                roomAvailabilityMessage: '',
                lecturerAvailable: true,
                lecturerAvailabilityMessage: '',
                studentAvailable: true,
                studentAvailabilityMessage: '',
                roomChecking: false,

                get selectedCourse() {
                    if (!this.selectedCourseCode) return null;
                    return this.courses.find(c => c.course_code === this.selectedCourseCode) || null;
                },

                get selectedKelasData() {
                    if (!this.selectedKelas || this.selectedKelas === 'semua') return null;
                    if (!this.selectedCourse) return null;
                    return this.selectedCourse.kelas.find(k => k.course_class === this.selectedKelas || k.class === this.selectedKelas) || null;
                },

                get recommendedRoomCount() {
                    if (this.mode === 'single') {
                        const kData = this.selectedKelasData;
                        return kData ? kData.room_count : 0;
                    }
                    return 0;
                },

                init() {
                    if (this.selectedCourseCode) {
                        this.onCourseChange(true);
                    }
                    
                    const oldKelas = @json(old('kelas', []));
                    if (oldKelas && oldKelas.length > 0) {
                        this.mode = 'all';
                        this.selectedKelas = 'semua';
                        this.kelasList.forEach((k, idx) => {
                            const match = oldKelas.find(ok => ok.course_class === k.course_class && ok.course_id == k.course_id);
                            if (match) {
                                k.date = match.date || '';
                                k.start_time = match.start_time || '';
                                k.end_time = match.end_time || '';
                                k.rooms = match.rooms || Array(k.room_count).fill('');
                                this.checkClassAvailability(idx);
                            }
                        });
                    }

                    this.checkRoomAvailability();
                },

                onAssessmentTypeChange() {
                    this.checkRoomAvailability();
                    if (this.assessmentType === 'TIDAK ADA ASSESSMEN BERSAMA') {
                        this.executionMethod = 'TIDAK ADA ASSESSMEN BERSAMA';
                        this.executionTime = 'TIDAK ADA ASSESSMEN BERSAMA';
                        this.participantDivision = 'TIDAK ADA ASSESSMEN BERSAMA';
                        this.questionType = 'TIDAK ADA ASSESSMEN BERSAMA';
                        this.answerSheetType = 'TIDAK ADA ASSESSMEN BERSAMA';
                        this.needsScratchPaper = 'TIDAK PERLU';
                        this.proctorType = 'TIDAK ADA ASSESSMEN BERSAMA';
                    }
                },

                onCourseChange(courseOrInit = false) {
                    let isInit = false;
                    if (courseOrInit === true) {
                        isInit = true;
                    } else if (courseOrInit && typeof courseOrInit === 'object') {
                        this.selectedCourseCode = courseOrInit.course_code;
                    }

                    if (!isInit) {
                        this.selectedKelas = '';
                        this.mode = 'single';
                        this.courseId = '';
                        this.courseData = null;
                        this.selectedRooms = [''];
                    }
                    
                    if (this.selectedCourseCode) {
                        const found = this.courses.find(c => c.course_code === this.selectedCourseCode);
                        if (found) {
                            this.courseData = {
                                id: found.id || '',
                                prodi: found.study_program,
                                nama_mk: found.name,
                                kelas: found.kelas.map(k => k.class).join(', '),
                                enrolled_students_count: found.total_mhs
                            };
                            this.prodiLabel = found.study_program;
                            this.infoKelas = found.kelas.map(k => k.class).join(', ');
                            
                            this.kelasList = found.kelas.map(k => ({
                                course_id: k.course_id,
                                course_class: k.course_class || k.class,
                                class: k.class,
                                mhs_count: k.mhs_count,
                                room_count: k.room_count,
                                date: '',
                                start_time: '',
                                end_time: '',
                                rooms: Array(k.room_count).fill(''),
                                available: true,
                                message: ''
                            }));
                            
                            if (!isInit) {
                                this.courseId = found.id || '';
                            } else {
                                const oldCourseId = '{{ old("course_id", "") }}';
                                const oldMode = '{{ old("mode", "") }}';
                                if (oldMode === 'all') {
                                    this.selectedKelas = 'semua';
                                    this.mode = 'all';
                                } else {
                                    const oldClass = '{{ old("course_class", "") }}';
                                    if (oldClass) {
                                        this.selectedKelas = oldClass;
                                        this.courseId = oldCourseId;
                                        this.mode = 'single';
                                        
                                        const clsObj = found.kelas.find(k => k.course_class === oldClass || k.class === oldClass);
                                        if (clsObj) {
                                            this.courseData.enrolled_students_count = clsObj.mhs_count;
                                            const rCount = clsObj.room_count || 1;
                                            const oldRooms = @json(old('rooms', []));
                                            if (oldRooms && oldRooms.length > 0) {
                                                this.selectedRooms = oldRooms;
                                            } else {
                                                this.selectedRooms = Array(rCount).fill('');
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    } else {
                        this.prodiLabel = '';
                        this.infoKelas = '';
                        this.kelasList = [];
                    }
                    this.checkRoomAvailability();
                },

                onKelasChange() {
                    if (this.selectedKelas === 'semua') {
                        this.mode = 'all';
                        this.courseId = '';
                    } else {
                        this.mode = 'single';
                        
                        if (this.selectedCourseCode && this.courseData) {
                            const found = this.courses.find(c => c.course_code === this.selectedCourseCode);
                            if (found) {
                                this.courseId = found.id || '';
                                const clsObj = found.kelas.find(k => k.course_class === this.selectedKelas || k.class === this.selectedKelas);
                                if (clsObj) {
                                    this.courseData.enrolled_students_count = clsObj.mhs_count;
                                    const rCount = clsObj.room_count || 1;
                                    this.selectedRooms = Array(rCount).fill('');
                                } else {
                                    this.courseData.enrolled_students_count = found.total_mhs;
                                    this.selectedRooms = [''];
                                }
                            }
                        }
                    }
                    
                    this.checkRoomAvailability();
                    if (this.mode === 'all') {
                        this.kelasList.forEach((_, idx) => this.checkClassAvailability(idx));
                    }
                },

                onLecturerChange() {
                    this.checkRoomAvailability();
                    if (this.mode === 'all') {
                        this.kelasList.forEach((_, idx) => this.checkClassAvailability(idx));
                    }
                },

                async checkClassAvailability(index) {
                    const item = this.kelasList[index];
                    if (!item.date || !item.start_time || !item.end_time) {
                        item.available = true;
                        item.message = '';
                        return;
                    }
                    
                    try {
                        let url = `{{ route('exam_schedules.check-availability') }}?exam_date=${encodeURIComponent(item.date)}&start_time=${encodeURIComponent(item.start_time)}&end_time=${encodeURIComponent(item.end_time)}&assessment_type=${encodeURIComponent(this.assessmentType)}`;
                        
                        const r1 = (item.rooms && item.rooms[0]) || '';
                        const r2 = (item.rooms && item.rooms[1]) || '';
                        if (r1) {
                            url += `&room_1=${encodeURIComponent(r1)}`;
                        }
                        if (r2) {
                            url += `&room_2=${encodeURIComponent(r2)}`;
                        }
                        if (item.course_id) {
                            url += `&course_id=${encodeURIComponent(item.course_id)}`;
                        }
                        if (item.course_class) {
                            url += `&course_class=${encodeURIComponent(item.course_class)}`;
                        }
                        if (this.lecturerCode) {
                            url += `&lecturer_code=${encodeURIComponent(this.lecturerCode)}`;
                        }
                        if (this.lecturerCode2) {
                            url += `&lecturer_code_2=${encodeURIComponent(this.lecturerCode2)}`;
                        }

                        const response = await fetch(url, {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            }
                        });

                        const result = await response.json();

                        if (response.ok) {
                            item.available = result.available;
                            item.message = result.message;
                        } else {
                            item.available = true;
                            item.message = '';
                        }
                    } catch (error) {
                        console.error('Failed to check class availability:', error);
                        item.available = true;
                        item.message = '';
                    }
                },

                async checkRoomAvailability() {
                    if (this.assessmentType === 'TIDAK ADA ASSESSMEN BERSAMA') {
                        this.formAvailable = true;
                        this.roomAvailable = true;
                        this.roomAvailabilityMessage = '';
                        this.lecturerAvailable = true;
                        this.lecturerAvailabilityMessage = '';
                        this.studentAvailable = true;
                        this.studentAvailabilityMessage = '';
                        return;
                    }

                    if (!this.examDate || !this.startTime || !this.endTime) {
                        this.formAvailable = true;
                        this.roomAvailable = true;
                        this.roomAvailabilityMessage = '';
                        this.lecturerAvailable = true;
                        this.lecturerAvailabilityMessage = '';
                        this.studentAvailable = true;
                        this.studentAvailabilityMessage = '';
                        return;
                    }

                    this.roomChecking = true;

                    try {
                        let url = `{{ route('exam_schedules.check-availability') }}?exam_date=${encodeURIComponent(this.examDate)}&start_time=${encodeURIComponent(this.startTime)}&end_time=${encodeURIComponent(this.endTime)}&assessment_type=${encodeURIComponent(this.assessmentType)}`;
                        
                        if (this.room1) {
                            url += `&room_1=${encodeURIComponent(this.room1)}`;
                        }
                        if (this.room2) {
                            url += `&room_2=${encodeURIComponent(this.room2)}`;
                        }
                        if (this.courseId) {
                            url += `&course_id=${encodeURIComponent(this.courseId)}`;
                        }
                        if (this.selectedKelas && this.selectedKelas !== 'semua') {
                            url += `&course_class=${encodeURIComponent(this.selectedKelas)}`;
                        }
                        if (this.lecturerCode) {
                            url += `&lecturer_code=${encodeURIComponent(this.lecturerCode)}`;
                        }
                        if (this.lecturerCode2) {
                            url += `&lecturer_code_2=${encodeURIComponent(this.lecturerCode2)}`;
                        }

                        const response = await fetch(url, {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            }
                        });

                        const result = await response.json();

                        if (response.ok) {
                            this.formAvailable = result.available;
                            this.roomAvailable = result.room_available;
                            this.roomAvailabilityMessage = result.room_message;
                            this.lecturerAvailable = result.lecturer_available;
                            this.lecturerAvailabilityMessage = result.lecturer_message;
                            this.studentAvailable = result.student_available;
                            this.studentAvailabilityMessage = result.student_message;
                        } else {
                            this.formAvailable = true;
                            this.roomAvailable = true;
                            this.roomAvailabilityMessage = '';
                            this.lecturerAvailable = true;
                            this.lecturerAvailabilityMessage = '';
                            this.studentAvailable = true;
                            this.studentAvailabilityMessage = '';
                        }
                    } catch (error) {
                        console.error('Failed to check room availability:', error);
                        this.formAvailable = true;
                        this.roomAvailable = true;
                        this.roomAvailabilityMessage = '';
                        this.lecturerAvailable = true;
                        this.lecturerAvailabilityMessage = '';
                        this.studentAvailable = true;
                        this.studentAvailabilityMessage = '';
                    } finally {
                        this.roomChecking = false;
                    }
                }
            }
        }
    </script>

</x-app-layout>
