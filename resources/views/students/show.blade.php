<x-app-layout>
    <x-slot name="header">Profil Mahasiswa</x-slot>

    <div x-data="{ activeTab: 'profil' }">

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
                        'yudisium'  => 'Data Yudisium',
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

        <!-- TAB 2: NILAI -->
        <div x-show="activeTab === 'nilai'" x-transition>
            <div class="campus-card overflow-hidden">

                <!-- KRS Aktif -->
                @if($krsAktif->count() > 0)
                    <div class="p-6 border-b border-slate-100">
                        <h3 class="text-base font-semibold text-slate-800 mb-1">Mata Kuliah Semester Ini (KRS Aktif)</h3>
                        <p class="text-xs text-slate-400 mb-4">
                            Periode akademik {{ $krsLabel }} · Semester studi {{ $krsStudySemester ?: '-' }}
                        </p>
                        <div class="overflow-x-auto">
                            <table class="campus-table">
                                <thead>
                                    <tr>
                                        <th>Kode MK</th>
                                        <th>Nama Mata Kuliah</th>
                                        <th class="text-center">SKS</th>
                                        <th>Kelas</th>
                                        <th>Dosen</th>
                                        <th class="text-center">Nilai</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($krsAktif as $krs)
                                        <tr>
                                            <td class="font-mono text-sm font-semibold text-slate-700">
                                                {{ $krs->course_code }}
                                            </td>
                                            <td class="font-medium text-slate-800">{{ $krs->course_name }}</td>
                                            <td class="text-center text-slate-600">{{ $krs->credits }}</td>
                                            <td class="text-slate-500">{{ $krs->course_class }}</td>
                                            <td class="text-slate-500">{{ $krs->lecturer_name ?? $krs->lecturer_code ?? '-' }}</td>
                                            <td class="text-center">
                                                @if(isset($studentGrades[$krs->course_code]) && $studentGrades[$krs->course_code]->grade)
                                                    @php
                                                        $gradeVal = $studentGrades[$krs->course_code]->grade;
                                                        $gradeColor = match(true) {
                                                            in_array($gradeVal, ['A', 'AB']) => 'badge-success',
                                                            in_array($gradeVal, ['B', 'BC']) => 'badge-info',
                                                            in_array($gradeVal, ['C'])       => 'badge-warning',
                                                            default                          => 'badge-danger',
                                                        };
                                                    @endphp
                                                    <span class="badge {{ $gradeColor }}">{{ $gradeVal }}</span>
                                                @else
                                                    <span class="badge badge-warning" style="font-size: 0.65rem;">Belum Ada Nilai</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="bg-slate-50">
                                        <td colspan="2" class="text-right text-sm font-semibold text-slate-600 px-4 py-3">
                                            Total SKS:
                                        </td>
                                        <td class="text-center font-bold text-[#DA2D3B] px-4 py-3">
                                            {{ $krsAktif->sum('credits') }}
                                        </td>
                                        <td colspan="3"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                @else
                    <div class="p-6 border-b border-slate-100">
                        <h3 class="text-base font-semibold text-slate-800 mb-1">Mata Kuliah Semester Ini (KRS Aktif)</h3>
                        <p class="text-xs text-slate-500">
                            {{ $activeAcademicPeriod
                                ? 'Belum ada data KRS yang tertaut ke periode akademik aktif (' . $activeAcademicPeriod->label . ').'
                                : 'Periode akademik aktif belum ditentukan oleh admin.' }}
                        </p>
                    </div>
                @endif

                <!-- Histori Nilai -->
                <div class="p-6">
                    <h3 class="text-base font-semibold text-slate-800 mb-4">Histori Nilai</h3>

                    @forelse ($nilaiPerTingkat as $tingkat => $nilais)
                        <div class="mb-6">
                            <div class="flex items-center gap-3 mb-3">
                                <span class="px-3 py-1 bg-[#DA2D3B]/10 text-[#DA2D3B] text-xs font-bold rounded-full">
                                    Tingkat {{ $tingkat }}
                                </span>
                                <span class="text-xs text-slate-400">
                                    {{ $nilais->count() }} mata kuliah
                                </span>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="campus-table">
                                    <thead>
                                        <tr>
                                            <th>Kode MK</th>
                                            <th>Nama Mata Kuliah</th>
                                            <th class="text-center">SKS</th>
                                            <th>Jenis</th>
                                            <th class="text-center">Nilai</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($nilais as $nilai)
                                            <tr>
                                                <td class="font-mono text-xs text-slate-500">
                                                    {{ $nilai->course_code }}
                                                </td>
                                                <td class="text-slate-800">{{ $nilai->course_name }}</td>
                                                <td class="text-center text-slate-600">{{ $nilai->credits }}</td>
                                                <td class="text-xs text-slate-500">{{ $nilai->type ?? '-' }}</td>
                                                <td class="text-center">
                                                    @if($nilai->grade)
                                                        @php
                                                            $nilaiColor = match(true) {
                                                                in_array($nilai->grade, ['A', 'AB']) => 'badge-success',
                                                                in_array($nilai->grade, ['B', 'BC']) => 'badge-info',
                                                                in_array($nilai->grade, ['C'])        => 'badge-warning',
                                                                default                              => 'badge-danger',
                                                            };
                                                        @endphp
                                                        <span class="badge {{ $nilaiColor }}">{{ $nilai->grade }}</span>
                                                    @else
                                                        <span class="text-xs text-slate-300">—</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @empty
                        <div class="empty-state">
                            <p class="empty-state-text">Belum ada data nilai.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- TAB: EPRT -->
        <div x-show="activeTab === 'eprt'" x-transition>
            <div class="campus-card p-6">
                <h3 class="text-base font-semibold text-slate-800 mb-4">Riwayat Tes EPRT</h3>

                @if($student->eprts->count() > 0)
                    <!-- Summary EPRT -->
                    @php $bestEprt = $student->eprts->first(); @endphp
                    <div class="bg-violet-50 border border-violet-200 rounded-xl p-4 mb-6 flex items-center gap-4">
                        <div class="w-14 h-14 bg-violet-600 rounded-xl flex items-center justify-center flex-shrink-0">
                            <span class="text-white text-lg font-bold">{{ $bestEprt->total_score ?? '-' }}</span>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-slate-800">Skor Tertinggi EPRT</p>
                            <p class="text-xs text-violet-600">dari {{ $student->eprts->count() }} kali tes</p>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="campus-table">
                            <thead>
                                <tr>
                                    <th class="w-10 text-center">No</th>
                                    <th class="text-center">Listening</th>
                                    <th class="text-center">Structure</th>
                                    <th class="text-center">Reading</th>
                                    <th class="text-center">Total</th>
                                    <th>Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($student->eprts as $i => $eprt)
                                    <tr>
                                        <td class="text-center text-slate-400">{{ $i + 1 }}</td>
                                        <td class="text-center text-slate-700">{{ $eprt->listening ?? '-' }}</td>
                                        <td class="text-center text-slate-700">{{ $eprt->structure ?? '-' }}</td>
                                        <td class="text-center text-slate-700">{{ $eprt->reading ?? '-' }}</td>
                                        <td class="text-center font-bold text-violet-700">{{ $eprt->total_score ?? '-' }}</td>
                                        <td>
                                            @if($eprt->details)
                                                <span class="badge {{ stripos($eprt->details, 'LULUS') !== false || stripos($eprt->details, 'PASS') !== false ? 'badge-success' : 'badge-warning' }}">
                                                    {{ $eprt->details }}
                                                </span>
                                            @else
                                                <span class="text-xs text-slate-300">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="empty-state">
                        <p class="empty-state-text">Belum ada data EPRT.</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- TAB 3: TAK -->
        <div x-show="activeTab === 'tak'" x-transition>
            <div class="campus-card p-6">

                <!--  Summary TAK -->
                <div class="bg-[#DA2D3B]/5 border border-[#DA2D3B]/20 rounded-xl p-4 mb-6 flex items-center gap-4">
                    <div class="w-14 h-14 bg-[#DA2D3B] rounded-xl flex items-center justify-center flex-shrink-0">
                        <span class="text-white text-xl font-bold">{{ $totalTak }}</span>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-slate-800">{{ $student->tak ? 'Skor TAK iGracias' : 'Total Poin TAK' }}</p>
                        <p class="text-xs text-[#DA2D3B]">
                            {{ $student->tak ? 'Skor resmi dari JSON; kegiatan lokal tercantum di bawah' : 'dari ' . $student->activities->count() . ' kegiatan' }}
                        </p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="campus-table">
                        <thead>
                            <tr>
                                <th class="w-10 text-center">No</th>
                                <th>Nama Kegiatan</th>
                                <th>Jenis</th>
                                <th>Peran</th>
                                <th class="text-center">Poin</th>
                                <th>Tanggal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($student->activities as $i => $activity)
                                <tr>
                                    <td class="text-center text-slate-400">{{ $i + 1 }}</td>
                                    <td class="font-medium text-slate-800">{{ $activity->activity_name }}</td>
                                    <td class="text-xs text-slate-500">{{ $activity->activity_type ?? '-' }}</td>
                                    <td class="text-xs text-slate-500">{{ $activity->role ?? '-' }}</td>
                                    <td class="text-center">
                                        <span class="badge badge-info">+{{ $activity->points }}</span>
                                    </td>
                                    <td class="text-xs text-slate-500">
                                        {{ $activity->date?->format('d M Y') ?? '-' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="empty-state">
                                        <p class="empty-state-text">Belum ada data TAK.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 4: KEHADIRAN -->
        <div x-show="activeTab === 'kehadiran'" x-transition>
            <div class="campus-card p-6">

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

                <!-- Sidang Tingkat -->
                <div class="campus-card p-6">
                    <h3 class="text-base font-semibold text-slate-800 mb-4 pb-2 border-b border-slate-100">Status Sidang Tingkat</h3>
                    @if($student->sidangTingkat)
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                            @foreach (['tingkat_1' => 'Tingkat 1', 'tingkat_2' => 'Tingkat 2', 'tingkat_3' => 'Tingkat 3', 'tingkat_4' => 'Tingkat 4'] as $field => $label)
                                <div class="border rounded-xl p-4 text-center {{ $student->sidangTingkat->$field ? 'bg-emerald-50 border-emerald-200' : 'bg-slate-50 border-slate-200' }}">
                                    <p class="text-xs text-slate-500 mb-1">{{ $label }}</p>
                                    <span class="badge {{ $student->sidangTingkat->$field ? 'badge-success' : 'badge-danger' }}">
                                        {{ $student->sidangTingkat->$field ? 'Lulus' : 'Belum' }}
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
                        @if($student->tak)
                            <p class="text-2xl font-bold text-[#DA2D3B]">{{ $student->tak->total_points ?? '—' }}</p>
                        @else
                            <span class="text-slate-300">—</span>
                        @endif
                    </div>

                    <!-- Approval BASILA -->
                    <div class="campus-card p-6 text-center">
                        <p class="text-xs text-slate-500 mb-2">Approval Foto BASILA</p>
                        @if($student->basilaApproval)
                            <span class="badge {{ $student->basilaApproval->photo_approval ? 'badge-success' : 'badge-danger' }} text-sm px-3 py-1">
                                {{ $student->basilaApproval->photo_approval ? 'Disetujui' : 'Belum' }}
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
                    <!-- BPP -->
                    <div class="campus-card p-6">
                        <h3 class="text-base font-semibold text-slate-800 mb-4 pb-2 border-b border-slate-100">Status Pembayaran BPP</h3>
                        @if($student->bppPayment)
                            <div class="flex gap-3 py-2">
                                <span class="text-sm text-slate-400 w-24 flex-shrink-0">Status</span>
                                <span class="text-sm font-medium text-slate-700">{{ $student->bppPayment->status ?? '-' }}</span>
                            </div>
                        @else
                            <div class="empty-state"><p class="empty-state-text">Belum ada data.</p></div>
                        @endif
                    </div>

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

                <!-- SKPI (placeholder) -->
                <div class="campus-card p-6">
                    <h3 class="text-base font-semibold text-slate-800 mb-4 pb-2 border-b border-slate-100">SKPI</h3>
                    @if($student->skpi && $student->skpi->raw_data)
                        <pre class="bg-slate-50 p-3 rounded-lg border border-slate-200 text-xs font-mono text-slate-600 overflow-x-auto max-h-48">{{ json_encode($student->skpi->raw_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                    @else
                        <div class="empty-state"><p class="empty-state-text">Belum ada data SKPI.</p></div>
                    @endif
                </div>

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
