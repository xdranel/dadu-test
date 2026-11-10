<x-app-layout>
    <x-slot name="header">Profil Mahasiswa</x-slot>

    <div x-data="{ activeTab: @js(request('tab') === 'yudisium' ? 'yudisium' : 'profil') }">
        @if(session('success'))<p role="status" class="mb-4 rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</p>@endif

        <!-- Header Profil-->
        <div class="campus-card mb-5 overflow-hidden">
            <div class="bg-[#DA2D3B] px-6 py-8">
                <div class="flex items-center gap-6">

                    <!-- Pas Foto -->
                    <div class="flex-shrink-0">
                        @if($student->photo_url)
                            <img src="{{ $student->photo_url }}"
                                 alt="Foto {{ $student->name }}"
                                 referrerpolicy="no-referrer"
                                 class="w-24 h-28 object-cover rounded-lg border-2 border-white/30 shadow-lg">
                        @else
                            <div class="w-24 h-28 rounded-lg border-2 border-white/30 shadow-lg bg-white/10 backdrop-blur-sm flex items-center justify-center text-white/60">
                                <svg class="w-12 h-12" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                </svg>
                            </div>
                        @endif
                    </div>

                    <!-- Info Utama -->
                    <div class="text-white">
                        <h2 class="text-2xl font-bold">{{ $student->name }}</h2>
                        <p class="text-white/80 text-sm mt-1">{{ $student->student_number }}</p>
                        <div class="flex flex-wrap gap-3 mt-3">
                            <span class="px-3 py-1 bg-white/20 rounded-full text-xs font-medium">
                                {{ $student->study_program }}
                            </span>
                            <span class="px-3 py-1 bg-white/20 rounded-full text-xs font-medium">
                                Angkatan {{ $student->academic_year }}
                            </span>
                            <span class="px-3 py-1 bg-white/20 rounded-full text-xs font-medium">
                                {{ $student->active_semester == 1 ? 'Semester Ganjil' : ($student->active_semester == 2 ? 'Semester Genap' : 'Semester ' . $student->active_semester) }}
                            </span>
                            @if($student->gpa)
                            <span class="px-3 py-1 bg-emerald-400/30 text-emerald-100 rounded-full text-xs font-bold">
                                IPK {{ $student->gpa }}
                            </span>
                            @endif
                            @if($student->total_credits)
                            <span class="px-3 py-1 bg-sky-400/30 text-sky-100 rounded-full text-xs font-bold">
                                {{ $student->total_credits }} SKS
                            </span>
                            @endif
                            @php
                                $statusColor = match($student->status) {
                                    'Aktif'  => 'bg-emerald-400/30 text-emerald-100',
                                    'Cuti'   => 'bg-amber-400/30 text-amber-100',
                                    'Lulus'  => 'bg-sky-400/30 text-sky-100',
                                    default  => 'bg-rose-400/30 text-rose-100',
                                };
                            @endphp
                            <span class="px-3 py-1 rounded-full text-xs font-medium {{ $statusColor }}">
                                {{ $student->status }}
                            </span>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Tab Navigation -->
            <div class="border-b border-slate-200 bg-white">
                <nav class="flex px-6 gap-1 overflow-x-auto">
                    @foreach ([
                        'profil'    => 'Profil',
                        'nilai'     => 'Rencana Studi & Nilai',
                        'eprt'      => 'EPRT',
                        'tak'       => 'TAK',
                        'kehadiran' => 'Kehadiran',
                        'yudisium'  => 'Data Yudisium & Tugas Akhir',
                    ] as $tab => $label)
                        <button @click="activeTab = '{{ $tab }}'"
                                :class="activeTab === '{{ $tab }}'
                                    ? 'border-b-2 border-[#DA2D3B] text-[#DA2D3B] font-semibold'
                                    : 'text-slate-500 hover:text-slate-700'"
                                class="px-4 py-3 text-sm transition-colors whitespace-nowrap">
                            {{ $label }}
                        </button>
                    @endforeach
                </nav>
            </div>
        </div>

        <!-- TAB 1: PROFIL -->
        <div x-show="activeTab === 'profil'" x-transition>
            <div class="campus-card p-6">
                <h3 class="text-base font-semibold text-slate-800 mb-4 pb-2 border-b border-slate-100">
                    Data Diri
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-3">
                    @php
                        $fields = [
                            'NIM'              => $student->student_number,
                            'Nama Mahasiswa'   => $student->name,
                            'Jenis Kelamin'    => $student->gender ?? '-',
                            'Agama'            => $student->religion ?? '-',
                            'Email Institusi'  => $student->institutional_email ?? '-',
                            'No. HP'           => $student->contact_phone ?? '-',
                        ];
                    @endphp
                    @foreach ($fields as $label => $value)
                        <div class="flex gap-3 py-2 border-b border-slate-50">
                            <span class="text-sm text-slate-400 w-40 flex-shrink-0">{{ $label }}</span>
                            <span class="text-sm font-medium text-slate-700">{{ $value }}</span>
                        </div>
                    @endforeach
                </div>

                <h3 class="text-base font-semibold text-slate-800 mt-6 mb-4 pb-2 border-b border-slate-100">
                    Data Akademik
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-3">
                    @php
                        $academic = [
                            'Fakultas'       => $student->faculty ?? '-',
                            'Program Studi'  => $student->study_program,
                            'Tahun Akademik' => $student->academic_year,
                            'Semester Aktif' => $student->active_semester == 1 ? 'Ganjil' : ($student->active_semester == 2 ? 'Genap' : $student->active_semester),
                            'Kelas'          => $student->class_name ?? '-',
                            'Status'         => $student->status,
                            'IPK'            => $student->gpa ?? '-',
                            'Total SKS'      => $student->total_credits ?? '-',
                        ];
                    @endphp
                    @foreach ($academic as $label => $value)
                        <div class="flex gap-3 py-2 border-b border-slate-50">
                            <span class="text-sm text-slate-400 w-40 flex-shrink-0">{{ $label }}</span>
                            <span class="text-sm font-medium text-slate-700">{{ $value }}</span>
                        </div>
                    @endforeach
                </div>

                <!-- Data Portal iGracias -->
                @if($student->high_school || $student->parent_phone_number || $student->passed_tpb)
                <h3 class="text-base font-semibold text-slate-800 mt-6 mb-4 pb-2 border-b border-slate-100">
                    Data Portal iGracias
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-3">
                    @php
                        $portal = [
                            'Asal Sekolah'  => $student->high_school ?? '-',
                            'No HP Ortu'    => $student->parent_phone_number ?? '-',
                            'Lulus TPB'     => $student->passed_tpb ?? '-',
                            'Lulus TKII'    => $student->passed_tkii ?? '-',
                            'Lulus TKIII'   => $student->passed_tkiii ?? '-',
                            'Lulus TKIV'    => $student->passed_tkiv ?? '-',
                        ];
                    @endphp
                    @foreach ($portal as $label => $value)
                        <div class="flex gap-3 py-2 border-b border-slate-50">
                            <span class="text-sm text-slate-400 w-40 flex-shrink-0">{{ $label }}</span>
                            <span class="text-sm font-medium text-slate-700">{{ $value }}</span>
                        </div>
                    @endforeach
                </div>
                @endif
            </div>
        </div>

        <!-- Rencana studi dan riwayat akademik -->
        <div x-show="activeTab === 'nilai'" x-transition>@include('students.partials.study-records')</div>
        <div x-show="activeTab === 'eprt'" x-transition>@include('students.partials.language')</div>
        <div x-show="activeTab === 'tak'" x-transition>@include('students.partials.tak')</div>

        <!-- TAB 4: KEHADIRAN -->
        <div data-student-attendance x-show="activeTab === 'kehadiran'" x-transition>
            <div class="campus-card p-6">

                @if($student->attendances->isNotEmpty())
                <!-- Summary Kehadiran -->
                <div class="grid grid-cols-3 gap-4 mb-6">
                    @php
                        $totalHadir    = $student->attendances->sum('attended_count');
                        $totalMeeting  = $student->attendances->sum('meetings_count');
                        $avgKehadiran  = $totalMeeting > 0 ? round(($totalHadir / $totalMeeting) * 100, 1) : 0;
                        $cardColor     = $avgKehadiran >= 80 ? 'bg-emerald-50 border-emerald-100' : ($avgKehadiran >= 60 ? 'bg-amber-50 border-amber-100' : 'bg-rose-50 border-rose-100');
                        $textColor     = $avgKehadiran >= 80 ? 'text-emerald-700' : ($avgKehadiran >= 60 ? 'text-amber-700' : 'text-rose-700');
                    @endphp
                    <div class="border rounded-xl p-4 text-center {{ $cardColor }}">
                        <p class="text-2xl font-bold {{ $textColor }}">{{ $avgKehadiran }}%</p>
                        <p class="text-xs text-slate-500 mt-1">Rata-rata Kehadiran</p>
                    </div>
                    <div class="border border-slate-100 bg-slate-50 rounded-xl p-4 text-center">
                        <p class="text-2xl font-bold text-slate-700">{{ $totalHadir }}</p>
                        <p class="text-xs text-slate-500 mt-1">Total Hadir</p>
                    </div>
                    <div class="border border-slate-100 bg-slate-50 rounded-xl p-4 text-center">
                        <p class="text-2xl font-bold text-slate-700">{{ $totalMeeting }}</p>
                        <p class="text-xs text-slate-500 mt-1">Total Pertemuan</p>
                    </div>
                </div>

                @endif
                <div class="overflow-x-auto">
                    <table class="campus-table">
                        <thead>
                            <tr>
                                <th class="w-10 text-center">No</th>
                                <th>Kode MK</th>
                                <th>Nama Mata Kuliah</th>
                                <th>Kelas</th>
                                <th class="text-center">Hadir</th>
                                <th class="text-center">Pertemuan</th>
                                <th class="text-center">Persentase</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($student->attendances as $i => $absen)
                                <tr>
                                    <td class="text-center text-slate-400">{{ $i + 1 }}</td>
                                    <td class="font-mono text-xs text-slate-500">{{ $absen->course_code }}</td>
                                    <td class="font-medium text-slate-800">{{ $absen->course_name }}</td>
                                    <td class="text-slate-500">{{ $absen->class ?? '-' }}</td>
                                    <td class="text-center font-semibold text-slate-700">{{ $absen->attended_count }}</td>
                                    <td class="text-center text-slate-500">{{ $absen->meetings_count }}</td>
                                    <td class="text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            {{-- Progress Bar --}}
                                            <div class="w-20 bg-slate-200 rounded-full h-1.5">
                                                <div class="h-1.5 rounded-full {{ $absen->percentage >= 80 ? 'bg-emerald-500' : ($absen->percentage >= 60 ? 'bg-amber-500' : 'bg-rose-500') }}"
                                                     style="width: {{ min($absen->percentage, 100) }}%"></div>
                                            </div>
                                            <span class="badge {{ $absen->badge_color }} text-xs min-w-[52px] justify-center">
                                                {{ $absen->percentage }}%
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="empty-state">
                                        <p class="empty-state-text">Belum ada data kehadiran.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 6: DATA YUDISIUM -->
        <div x-show="activeTab === 'yudisium'" x-transition>
            <div class="space-y-5">
                @can('student_update')
                    @can('update', $student)
                        <div class="flex justify-end"><a data-edit-academic-data class="btn btn-primary" href="{{ route('students.academic-data.edit',$student) }}">Edit Data Yudisium &amp; TA</a></div>
                    @endcan
                @endcan

                <!-- Sidang Tingkat -->
                <div class="campus-card p-6">
                    <h3 class="text-base font-semibold text-slate-800 mb-4 pb-2 border-b border-slate-100">Status Sidang Tingkat</h3>
                    @if($student->sidangTingkat)
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                            @foreach (['tingkat_1' => 'Tingkat 1', 'tingkat_2' => 'Tingkat 2', 'tingkat_3' => 'Tingkat 3', 'tingkat_4' => 'Tingkat 4'] as $field => $label)
                                @php $levelPassed = $student->sidangTingkat->$field; @endphp
                                <div class="border rounded-xl p-4 text-center {{ $student->sidangTingkat->$field ? 'bg-emerald-50 border-emerald-200' : 'bg-slate-50 border-slate-200' }}">
                                    <p class="text-xs text-slate-500 mb-1">{{ $label }}</p>
                                    <span class="badge {{ $levelPassed === null ? 'bg-slate-100 text-slate-600' : ($levelPassed ? 'badge-success' : 'badge-danger') }}">
                                        {{ $levelPassed === null ? 'Belum tersedia' : ($levelPassed ? 'Lulus' : 'Belum') }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="empty-state"><p class="empty-state-text">Belum ada data sidang tingkat.</p></div>
                    @endif
                </div>

                <!-- Tugas Akhir -->
                <div class="campus-card p-6">
                    <h3 class="text-base font-semibold text-slate-800 mb-4 pb-2 border-b border-slate-100">Data Tugas Akhir</h3>
                    @if($student->finalProject)
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-3">
                            @php
                                $fpData = [
                                    'Judul TA (Indonesia)' => $student->finalProject->thesis_title_id ?? '-',
                                    'Judul TA (Inggris)' => $student->finalProject->thesis_title_en ?? '-',
                                    'Pembimbing 1' => $student->finalProject->advisor_1 ?? '-',
                                    'Pembimbing 2' => $student->finalProject->advisor_2 ?? '-',
                                    'Penguji 1'    => $student->finalProject->examiner_1 ?? '-',
                                    'Penguji 2'    => $student->finalProject->examiner_2 ?? '-',
                                    'Tanggal Sidang' => $student->finalProject->exam_date?->format('d M Y') ?? '-',
                                    'Waktu'        => $student->finalProject->exam_time ?? '-',
                                    'Tempat'       => $student->finalProject->exam_location ?? '-',
                                ];
                            @endphp
                            @foreach ($fpData as $label => $value)
                                <div class="flex gap-3 py-2 border-b border-slate-50">
                                    <span class="text-sm text-slate-400 w-40 flex-shrink-0">{{ $label }}</span>
                                    <span class="text-sm font-medium text-slate-700">{{ $value }}</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="empty-state"><p class="empty-state-text">Belum ada data tugas akhir.</p></div>
                    @endif
                </div>

                <!-- Nilai TA + TAK + EPRT (JSON) -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                    <!-- Nilai Tugas Akhir -->
                    <div class="campus-card p-6 text-center">
                        <p class="text-xs text-slate-500 mb-2">Indeks Nilai TA</p>
                        @if($student->finalProjectGrade)
                            @php
                                $taGrade = $student->finalProjectGrade->grade;
                                $taColor = match(true) {
                                    in_array($taGrade, ['A', 'AB']) => 'badge-success',
                                    in_array($taGrade, ['B', 'BC']) => 'badge-info',
                                    in_array($taGrade, ['C'])       => 'badge-warning',
                                    default                         => 'badge-danger',
                                };
                            @endphp
                            <span class="badge {{ $taColor }} text-lg px-4 py-2">{{ $taGrade }}</span>
                        @else
                            <span class="text-slate-300">—</span>
                        @endif
                    </div>

                    <!-- TAK dari JSON -->
                    <div class="campus-card p-6 text-center">
                        <p class="text-xs text-slate-500 mb-2">Total TAK (JSON)</p>
                        @if($totalTak !== null)
                            <p class="text-2xl font-bold text-[#DA2D3B]">{{ (float) $totalTak }}</p>
                        @else
                            <span class="text-slate-300">—</span>
                        @endif
                    </div>

                    <!-- Approval BASILA -->
                    <div class="campus-card p-6 text-center">
                        <p class="text-xs text-slate-500 mb-2">Approval Foto BASILA</p>
                        @if($student->basilaApproval)
                            <span class="badge {{ $student->basilaApproval->photo_approval === null ? 'bg-slate-100 text-slate-600' : ($student->basilaApproval->photo_approval ? 'badge-success' : 'badge-danger') }} text-sm px-3 py-1">
                                {{ $student->basilaApproval->photo_approval === null ? 'Belum tersedia' : ($student->basilaApproval->photo_approval ? 'Disetujui' : 'Belum') }}
                            </span>
                        @else
                            <span class="text-slate-300">—</span>
                        @endif
                    </div>
                </div>

                <!-- Status Mata Kuliah (MK Mengulang & Belum Lulus) -->
                <div class="campus-card p-6">
                    <h3 class="text-base font-semibold text-slate-800 mb-4 pb-2 border-b border-slate-100">Status Mata Kuliah</h3>
                    @if($student->courseIssues)
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-4">
                            <div class="border border-emerald-100 bg-emerald-50 rounded-xl p-4 text-center">
                                <p class="text-2xl font-bold text-emerald-700">{{ $student->courseIssues->credits_passed ?? 0 }}</p>
                                <p class="text-xs text-slate-500 mt-1">SKS Lulus</p>
                            </div>
                            <div class="border border-rose-100 bg-rose-50 rounded-xl p-4 text-center">
                                <p class="text-2xl font-bold text-rose-700">{{ $student->courseIssues->credits_remaining ?? 0 }}</p>
                                <p class="text-xs text-slate-500 mt-1">SKS Belum Lulus</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm font-semibold text-slate-600 mb-2">MK Mengulang</p>
                                @if(!empty($student->courseIssues->retaking_courses))
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($student->courseIssues->retaking_courses as $code)
                                            <span class="badge badge-warning">{{ $code }}</span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-xs text-slate-300">Tidak ada</span>
                                @endif
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-slate-600 mb-2">MK Belum Lulus</p>
                                @if(!empty($student->courseIssues->failed_courses))
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($student->courseIssues->failed_courses as $code)
                                            <span class="badge badge-danger">{{ $code }}</span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-xs text-slate-300">Tidak ada</span>
                                @endif
                            </div>
                        </div>
                    @else
                        <div class="empty-state"><p class="empty-state-text">Belum ada data status mata kuliah.</p></div>
                    @endif
                </div>

                <!-- Status Pembayaran -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    @include('students.partials.bpp')

                    <!-- Wisuda (placeholder) -->
                    <div class="campus-card p-6">
                        <h3 class="text-base font-semibold text-slate-800 mb-4 pb-2 border-b border-slate-100">Status Bayar Wisuda</h3>
                        @if($student->graduationPayment)
                            <div class="flex gap-3 py-2">
                                <span class="text-sm text-slate-400 w-24 flex-shrink-0">Status</span>
                                <span class="text-sm font-medium text-slate-700">{{ $student->graduationPayment->status ?? '-' }}</span>
                            </div>
                        @else
                            <div class="empty-state"><p class="empty-state-text">Belum ada data.</p></div>
                        @endif
                    </div>
                </div>

                @include('students.partials.skpi')

            </div>
        </div>

        <!-- Tombol Kembali -->
        <div class="mt-5 flex items-center justify-between">
            <a href="{{ route('students.index') }}"
               class="btn btn-secondary">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Kembali
            </a>
            @can('update', $student)
                <a href="{{ route('students.edit', $student) }}" class="btn btn-primary">
                    Edit Data
                </a>
            @endcan
        </div>

    </div>
</x-app-layout>
