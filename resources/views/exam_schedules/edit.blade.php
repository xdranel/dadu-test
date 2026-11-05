<x-app-layout>
    <x-slot name="header">Edit Jadwal Ujian</x-slot>

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

            <form action="{{ route('exam_schedules.update', $schedule->id) }}" method="POST" x-data="examForm()">
                @csrf
                @method('PUT')

                <!-- Data Mata Kuliah -->
                <div class="mb-8">
                    <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-4">
                        Data Mata Kuliah
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <!-- Kode Mata Kuliah -->
                        <div>
                            <label class="campus-label" for="kode_mk">
                                Kode Mata Kuliah <span class="text-rose-500">*</span>
                            </label>
                            <input type="text" id="kode_mk" name="kode_mk" x-model="kodeMk"
                                   @input.debounce.500ms="fetchCourseDataByCode()"
                                   placeholder="Contoh: IF-101"
                                   class="campus-input @error('course_id') !border-rose-500 @enderror"
                                   value="{{ old('kode_mk', $schedule->course->course_code ?? '') }}">
                            <input type="hidden" name="course_id" x-model="courseId">
                            @error('course_id')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                            <p x-show="errorMessage" class="mt-1 text-xs text-rose-600" x-text="errorMessage" x-cloak></p>
                        </div>

                        <!-- Kode Dosen -->
                        <div>
                            <label class="campus-label" for="lecturer_code">Kode Dosen <span class="text-rose-500">*</span></label>
                            <input type="text" id="lecturer_code" name="lecturer_code"
                                   x-model="lecturerCode" @input.debounce.500ms="checkRoomAvailability()" placeholder="Contoh: DSN001"
                                   class="campus-input @error('lecturer_code') !border-rose-500 @enderror">
                            @error('lecturer_code')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Kode Dosen 2 (Opsional) -->
                        <div>
                            <label class="campus-label" for="lecturer_code_2">Kode Dosen 2 (Opsional)</label>
                            <input type="text" id="lecturer_code_2" name="lecturer_code_2"
                                   x-model="lecturerCode2" @input.debounce.500ms="checkRoomAvailability()" placeholder="Contoh: DSN002"
                                   class="campus-input @error('lecturer_code_2') !border-rose-500 @enderror">
                            @error('lecturer_code_2')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Dynamic Course Info Panel -->
                    <div x-show="courseData" x-cloak
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         class="mt-4 p-4 bg-slate-50 rounded-xl border border-slate-200">
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
                                <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Kelas</p>
                                <p class="text-sm font-bold text-slate-800 mt-1" x-text="courseData?.kelas || '-'"></p>
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

                    <!-- Student Availability Warning -->
                    <div x-show="studentAvailabilityMessage" x-cloak class="mt-4">
                        <div class="p-3 bg-rose-50 border border-rose-100 rounded-lg text-rose-700 text-xs font-medium flex items-center gap-2">
                            <svg class="w-4 h-4 text-rose-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                            <span x-text="studentAvailabilityMessage"></span>
                        </div>
                    </div>

                    <div x-show="loading" class="mt-4 flex items-center gap-2 text-sm text-slate-400">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Memuat data mata kuliah...
                    </div>
                </div>

                <!-- Informasi Ujian -->
                <div class="mb-8">
                    <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-4">
                        Informasi Ujian
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div>
                            <label class="campus-label" for="exam_date">Tanggal Ujian <span class="text-rose-500" x-show="assessmentType !== 'TIDAK ADA ASSESSMEN BERSAMA'">*</span></label>
                            <input type="date" id="exam_date" name="exam_date"
                                   x-model="examDate" @input.debounce.500ms="checkRoomAvailability()"
                                   class="campus-input @error('exam_date') !border-rose-500 @enderror">
                            @error('exam_date')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Ruangan Utama -->
                        <div>
                            <label class="campus-label" for="room_1">Ruangan Utama <span class="text-rose-500" x-show="assessmentType !== 'TIDAK ADA ASSESSMEN BERSAMA'">*</span></label>
                            <input type="text" id="room_1" name="room_1"
                                   x-model="room1" @input.debounce.500ms="checkRoomAvailability()"
                                   placeholder="Contoh: GD-A201"
                                   class="campus-input @error('room_1') !border-rose-500 @enderror">
                            @error('room_1')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Ruangan Kedua (Opsional) -->
                        <div x-show="participantDivision === 'DIBAGI 2 (JIKA PESERTA KELAS > 30)'" x-cloak>
                            <label class="campus-label" for="room_2">Ruangan Kedua (Opsional)</label>
                            <input type="text" id="room_2" name="room_2"
                                   x-model="room2" @input.debounce.500ms="checkRoomAvailability()"
                                   placeholder="Contoh: GD-A202"
                                   class="campus-input @error('room_2') !border-rose-500 @enderror">
                            @error('room_2')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Jam Mulai -->
                        <div>
                            <label class="campus-label" for="start_time">Jam Mulai <span class="text-rose-500" x-show="assessmentType !== 'TIDAK ADA ASSESSMEN BERSAMA'">*</span></label>
                            <input type="time" id="start_time" name="start_time"
                                   x-model="startTime" @input.debounce.500ms="checkRoomAvailability()"
                                   class="campus-input @error('start_time') !border-rose-500 @enderror">
                            @error('start_time')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Jam Selesai -->
                        <div>
                            <label class="campus-label" for="end_time">Jam Selesai <span class="text-rose-500" x-show="assessmentType !== 'TIDAK ADA ASSESSMEN BERSAMA'">*</span></label>
                            <input type="time" id="end_time" name="end_time"
                                   x-model="endTime" @input.debounce.500ms="checkRoomAvailability()"
                                   class="campus-input @error('end_time') !border-rose-500 @enderror">
                            @error('end_time')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

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

                        <!-- Warning Room 2 / Proctor 2 Empty when divided into 2 -->
                        <div x-show="participantDivision === 'DIBAGI 2 (JIKA PESERTA KELAS > 30)' && (!room2 || !proctorName2)" x-cloak class="lg:col-span-3 sm:col-span-2">
                            <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg text-amber-800 text-xs font-medium flex items-center gap-2">
                                <svg class="w-4 h-4 text-amber-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                                <span>Peringatan: Pilihan pembagian peserta "DIBAGI 2" aktif. Harap lengkapi Ruangan Kedua dan Pengawas Kedua.</span>
                            </div>
                        </div>

                        <!-- Room Availability Message -->
                        <div x-show="!roomAvailable && roomAvailabilityMessage" x-cloak class="lg:col-span-3 sm:col-span-2">
                            <div class="p-3 bg-rose-50 border border-rose-100 rounded-lg text-rose-700 text-xs font-medium flex items-center gap-2">
                                <svg class="w-4 h-4 text-rose-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                                <span x-text="roomAvailabilityMessage"></span>
                            </div>
                        </div>

                        <!-- Lecturer Availability Message -->
                        <div x-show="lecturerAvailabilityMessage" x-cloak class="lg:col-span-3 sm:col-span-2">
                            <div class="p-3 bg-rose-50 border border-rose-100 rounded-lg text-rose-700 text-xs font-medium flex items-center gap-2">
                                <svg class="w-4 h-4 text-rose-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                                <span x-text="lecturerAvailabilityMessage"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Konfigurasi Assessment -->
                <div class="mb-8">
                    <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-4">
                        Konfigurasi Assessment
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @php
                            $dropdowns = [
                                ['name' => 'assessment_type', 'label' => 'Jenis Assessment', 'options' => ['TIDAK ADA ASSESSMEN BERSAMA', 'MENGERJAKAN SOAL UJIAN', 'UJIAN LISAN / PRESENTASI / PROJECT BASED']],
                                ['name' => 'execution_method', 'label' => 'Metode Pelaksanaan', 'options' => ['TIDAK ADA ASSESSMEN BERSAMA', 'DIKERJAKAN ONSITE DI KAMPUS', 'TAKE HOME ATAU DIKERJAKAN FROM ANYWHERE']],
                                ['name' => 'execution_time', 'label' => 'Waktu Pelaksanaan', 'options' => ['TIDAK ADA ASSESSMEN BERSAMA', 'SESUAI JADWAL KELAS PERKULIAHAN', 'JADWAL SERENTAK se-TUS per MK Prodi', 'JADWAL SERENTAK se-TUNC']],
                                ['name' => 'participant_division', 'label' => 'Pembagian Peserta Ujian', 'options' => ['TIDAK ADA ASSESSMEN BERSAMA', 'DIBAGI 2 (JIKA PESERTA KELAS > 30)', 'SEMUA PESERTA DIGABUNG DALAM 1 RUANGAN']],
                                ['name' => 'question_type', 'label' => 'Soal Ujian', 'options' => ['TIDAK ADA ASSESSMEN BERSAMA', 'PERLU PENGGANDAAN FISIK (HARDCOPY)', 'SOAL TERSEDIA DI LMS']],
                                ['name' => 'answer_sheet_type', 'label' => 'Lembar Jawaban', 'options' => ['TIDAK ADA ASSESSMEN BERSAMA', 'MENGGUNAKAN LEMBAR JAWABAN STANDAR AKADEMIK', 'LEMBAR JAWABAN CUSTOM (PERLU PENGGANDAAN)', 'TIDAK PERLU LEMBAR JAWABAN']],
                                ['name' => 'needs_scratch_paper', 'label' => 'Kebutuhan Kertas Buram', 'options' => ['TIDAK PERLU', 'PERLU']],
                                ['name' => 'proctor_type', 'label' => 'Pengawas', 'options' => ['TIDAK ADA ASSESSMEN BERSAMA', 'DOSEN PENGAMPU', 'ASPRAK / ASLAB / ASDOS (PRODI SETOR NAMA)', 'TLH / STUDENT STAFF UNIT (DICARIKAN AKADEMIK)', 'TIDAK PERLU PENGAWAS']],
                            ];
                        @endphp

                        @foreach ($dropdowns as $dd)
                            <div>
                                <label class="campus-label" for="{{ $dd['name'] }}">
                                    {{ $dd['label'] }}
                                    @if($dd['name'] === 'assessment_type')
                                        <span class="text-rose-500">*</span>
                                    @else
                                        <span class="text-rose-500" x-show="assessmentType !== 'TIDAK ADA ASSESSMEN BERSAMA'">*</span>
                                    @endif
                                </label>
                                <select id="{{ $dd['name'] }}" name="{{ $dd['name'] }}"
                                        @if($dd['name'] === 'assessment_type') 
                                            x-model="assessmentType" @change="onAssessmentTypeChange()" 
                                        @elseif($dd['name'] === 'execution_method') 
                                            x-model="executionMethod"
                                        @elseif($dd['name'] === 'execution_time') 
                                            x-model="executionTime"
                                        @elseif($dd['name'] === 'participant_division') 
                                            x-model="participantDivision"
                                        @elseif($dd['name'] === 'question_type') 
                                            x-model="questionType"
                                        @elseif($dd['name'] === 'answer_sheet_type') 
                                            x-model="answerSheetType"
                                        @elseif($dd['name'] === 'needs_scratch_paper') 
                                            x-model="needsScratchPaper"
                                        @elseif($dd['name'] === 'proctor_type') 
                                            x-model="proctorType"
                                        @endif
                                        class="campus-select @error($dd['name']) !border-rose-500 @enderror">
                                    <option value="">- Pilih -</option>
                                    @foreach ($dd['options'] as $opt)
                                        <option value="{{ $opt }}" {{ old($dd['name'], $schedule->{$dd['name']}) === $opt ? 'selected' : '' }}>
                                            {{ $opt }}
                                        </option>
                                    @endforeach
                                </select>
                                @error($dd['name'])
                                    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- URL & Aturan -->
                <div class="mb-8">
                    <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-4">
                        URL & Aturan Ujian
                    </h3>

                    <div class="space-y-4">
                        <div>
                            <label class="campus-label" for="exam_question_url">URL Soal Ujian</label>
                            <input type="url" id="exam_question_url" name="exam_question_url"
                                   value="{{ old('exam_question_url', $schedule->exam_question_url) }}" placeholder="https://drive.google.com/..."
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

                        <div>
                            <label class="campus-label" for="exam_rules">Aturan Ujian</label>
                            <textarea id="exam_rules" name="exam_rules" rows="3"
                                      placeholder="Contoh: Open book, boleh kalkulator..."
                                      class="campus-input !h-auto @error('exam_rules') !border-rose-500 @enderror">{{ old('exam_rules', $schedule->exam_rules) }}</textarea>
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
                        Perbarui Jadwal
                    </button>
                </div>
            </form>

        </div>
    </div>

    <!-- Alpine.js: AJAX fetch course data -->
    <script>
        function examForm() {
            return {
                courseId: '{{ old("course_id", $schedule->course_id) }}',
                prodi: '{{ old("prodi", $schedule->course->study_program ?? "") }}',
                kodeMk: '{{ old("kode_mk", $schedule->course->course_code ?? "") }}',
                courseData: null,
                loading: false,
                errorMessage: '',Location: 'edit.blade.php',

                // Room capacities map
                roomsData: {
                    @foreach ($rooms as $r)
                        '{{ $r->room_code }}': { capacity: {{ $r->capacity }} },
                    @endforeach
                },

                 // Check Room Availability fields
                examDate: '{!! old("exam_date", $schedule->exam_date ? $schedule->exam_date->format("Y-m-d") : "") !!}',
                startTime: '{!! old("start_time", $schedule->start_time ? \Carbon\Carbon::parse($schedule->start_time)->format("H:i") : "") !!}',
                endTime: '{!! old("end_time", $schedule->end_time ? \Carbon\Carbon::parse($schedule->end_time)->format("H:i") : "") !!}',
                room1: '{!! old("room_1", $schedule->room_1) !!}',
                room2: '{!! old("room_2", $schedule->room_2) !!}',
                proctorName1: '{!! old("proctor_name_1", $schedule->proctor_name_1) !!}',
                proctorName2: '{!! old("proctor_name_2", $schedule->proctor_name_2) !!}',
                participantDivision: '{!! old("participant_division", $schedule->participant_division) !!}',
                executionMethod: '{!! old("execution_method", $schedule->execution_method) !!}',
                executionTime: '{!! old("execution_time", $schedule->execution_time) !!}',
                questionType: '{!! old("question_type", $schedule->question_type) !!}',
                answerSheetType: '{!! old("answer_sheet_type", $schedule->answer_sheet_type) !!}',
                needsScratchPaper: '{!! old("needs_scratch_paper", $schedule->needs_scratch_paper) !!}',
                proctorType: '{!! old("proctor_type", $schedule->proctor_type) !!}',
                customAnswerSheetUrl: '{!! old("custom_answer_sheet_url", $schedule->custom_answer_sheet_url) !!}',
                lecturerCode: '{!! old("lecturer_code", $schedule->lecturer_code) !!}',
                lecturerCode2: '{!! old("lecturer_code_2", $schedule->lecturer_code_2) !!}',
                assessmentType: '{!! old("assessment_type", $schedule->assessment_type) !!}',
                formAvailable: true,
                roomAvailable: true,
                roomAvailabilityMessage: '',
                lecturerAvailable: true,
                lecturerAvailabilityMessage: '',
                studentAvailable: true,
                studentAvailabilityMessage: '',
                roomChecking: false,

                init() {
                    if (this.kodeMk) {
                        this.fetchCourseDataByCode();
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

                async fetchCourseDataByCode() {
                    if (!this.kodeMk) {
                        this.courseId = '';
                        this.courseData = null;
                        this.errorMessage = '';
                        return;
                    }

                    this.loading = true;
                    this.errorMessage = '';

                    try {
                        let url = `{{ route('exam_schedules.course-data') }}?kode_mk=${encodeURIComponent(this.kodeMk)}`;
                        if (this.prodi) {
                            url += `&prodi=${encodeURIComponent(this.prodi)}`;
                        }
                        url += `&class=${encodeURIComponent('{{ $schedule->course_class }}')}`;

                        const response = await fetch(url, {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            }
                        });

                        const result = await response.json();

                        if (response.ok && result.success) {
                            this.courseData = result;
                            this.courseId = result.id;
                            this.prodi = result.prodi; // auto-populate prodi if it wasn't selected
                            this.checkRoomAvailability(); // check availability immediately after course data resolves
                        } else {
                            this.courseId = '';
                            this.courseData = null;
                            this.errorMessage = result.message || 'Mata kuliah tidak ditemukan.';
                        }
                    } catch (error) {
                        console.error('Failed to fetch course data:', error);
                        this.courseId = '';
                        this.courseData = null;
                        this.errorMessage = 'Terjadi kesalahan sistem saat mencari mata kuliah.';
                    } finally {
                        this.loading = false;
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
                        let url = `{{ route('exam_schedules.check-availability') }}?exam_date=${encodeURIComponent(this.examDate)}&start_time=${encodeURIComponent(this.startTime)}&end_time=${encodeURIComponent(this.endTime)}&exclude_id={{ $schedule->id }}&assessment_type=${encodeURIComponent(this.assessmentType)}`;
                        
                        if (this.room1) {
                            url += `&room_1=${encodeURIComponent(this.room1)}`;
                        }
                        if (this.room2) {
                            url += `&room_2=${encodeURIComponent(this.room2)}`;
                        }
                        if (this.courseId) {
                            url += `&course_id=${encodeURIComponent(this.courseId)}`;
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
                            this.formAvailable = true; // fallback
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
