<x-app-layout>
    <x-slot name="header">Jadwal Ujian</x-slot>

    <!-- Filter & Actions -->
    <div class="filter-card mb-4">
        <div class="flex flex-wrap gap-2.5 items-end justify-between">

            <!-- Search & Filter -->
            <form action="{{ route('exam_schedules.index') }}" method="GET"
                  class="flex flex-wrap gap-2.5 items-end flex-1">

                <div class="min-w-[160px] max-w-[240px] flex-1">
                    <label class="campus-label">Cari Mata Kuliah</label>
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="Ketik nama atau kode..."
                           class="campus-input">
                </div>

                <div class="min-w-[110px]">
                    <label class="campus-label">Hari</label>
                    <select name="hari" class="campus-select">
                        <option value="">Semua Hari</option>
                        @foreach (['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'] as $hari)
                            <option value="{{ $hari }}" {{ request('hari') === $hari ? 'selected' : '' }}>
                                {{ $hari }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="min-w-[140px]">
                    <label class="campus-label">Jenis Assessment</label>
                    <select name="jenis_assessment" class="campus-select">
                        <option value="">Semua Jenis</option>
                        <option value="TIDAK ADA ASSESSMEN BERSAMA" {{ request('jenis_assessment') === 'TIDAK ADA ASSESSMEN BERSAMA' ? 'selected' : '' }}>Tidak Ada</option>
                        <option value="MENGERJAKAN SOAL UJIAN" {{ request('jenis_assessment') === 'MENGERJAKAN SOAL UJIAN' ? 'selected' : '' }}>Soal Ujian</option>
                        <option value="UJIAN LISAN / PRESENTASI / PROJECT BASED" {{ request('jenis_assessment') === 'UJIAN LISAN / PRESENTASI / PROJECT BASED' ? 'selected' : '' }}>Lisan / Presentasi</option>
                    </select>
                </div>

                <div class="flex items-center gap-1.5">
                    <button type="submit" class="btn btn-secondary">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        Filter
                    </button>
                    <a href="{{ route('exam_schedules.index') }}" class="btn btn-secondary">Reset</a>
                </div>

            </form>

            <!-- Tambah & Import Button (Admin/Dosen only) -->
            @can('jadwal_create')
            <div class="flex items-center gap-2 flex-shrink-0">
                <button type="button"
                        onclick="document.getElementById('modalImport').classList.remove('hidden')"
                        class="btn btn-secondary">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    Import Jadwal
                </button>
                <a href="{{ route('exam_schedules.create') }}" class="btn btn-success">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    Tambah Jadwal
                </a>
            </div>
            @endcan

        </div>
    </div>

    <!-- Table -->
    <div class="campus-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="campus-table">
                <thead>
                    <tr>
                        <th class="!text-center">Mata Kuliah</th>
                        <th class="!text-center">Kelas</th>
                        <th class="!text-center">Jadwal</th>
                        <th class="!text-center">Ruangan</th>
                        @canany(['jadwal_create', 'jadwal_update', 'jadwal_delete'])
                            <th class="!text-center">Kode Dosen</th>
                            <th class="!text-center">Pengawas</th>
                            <th class="!text-center">Jenis Assessment</th>
                        @endcanany
                        <th class="!text-center">Status</th>
                        @if(!$isMahasiswa)
                            <th class="!text-center">Aksi</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($schedules as $schedule)
                        @php
                            $dayNames = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
                            $dayName = $schedule->exam_date ? ($dayNames[$schedule->exam_date->dayOfWeek] ?? '-') : '-';
                        @endphp
                        <tr>
                            <td class="text-center align-middle">
                                <div class="font-semibold text-slate-800">{{ $schedule->course->name ?? '-' }}</div>
                                <div class="text-xs text-slate-400 mt-0.5">{{ $schedule->course->course_code ?? '' }}</div>
                            </td>
                            <td class="whitespace-nowrap text-center align-middle text-slate-600">
                                {{ $schedule->course_class ?? $schedule->course->class ?? '-' }}
                            </td>
                            <td class="whitespace-nowrap text-center align-middle">
                                <div class="font-medium text-slate-800">
                                    @if ($schedule->exam_date)
                                        {{ $dayName }}, {{ $schedule->exam_date->translatedFormat('d M Y') }}
                                    @else
                                        -
                                    @endif
                                </div>
                                <div class="text-xs text-slate-400 mt-0.5">
                                    @if ($schedule->start_time && $schedule->end_time)
                                        {{ \Carbon\Carbon::parse($schedule->start_time)->format('H:i') }} - {{ \Carbon\Carbon::parse($schedule->end_time)->format('H:i') }}
                                    @else
                                        -
                                    @endif
                                </div>
                            </td>
                             <td class="whitespace-nowrap text-center align-middle text-slate-600 text-sm">
                                @if ($schedule->participant_division === 'DIBAGI 2 (JIKA PESERTA KELAS > 30)' && !empty($schedule->room_2))
                                    <div>{{ $schedule->room_1 }}</div>
                                    <div class="mt-0.5">{{ $schedule->room_2 }}</div>
                                @else
                                    {{ $schedule->room_1 ?? '-' }}
                                @endif
                            </td>
                            @canany(['jadwal_create', 'jadwal_update', 'jadwal_delete'])
                                <td class="whitespace-nowrap text-center align-middle text-slate-600">
                                    {{ $schedule->lecturer_code }}
                                </td>
                                <td class="whitespace-nowrap text-center align-middle text-slate-600 text-sm">
                                    @if ($schedule->participant_division === 'DIBAGI 2 (JIKA PESERTA KELAS > 30)' && !empty($schedule->proctor_name_2))
                                        <div>{{ $schedule->proctor_name_1 ?? '-' }}</div>
                                        <div class="mt-0.5">{{ $schedule->proctor_name_2 }}</div>
                                    @else
                                        {{ $schedule->proctor_name_1 ?? '-' }}
                                    @endif
                                </td>
                                <td class="whitespace-nowrap text-center align-middle">
                                    @if($schedule->assessment_type === 'TIDAK ADA ASSESSMEN BERSAMA')
                                        <span class="badge badge-warning">
                                            Tidak Ada
                                        </span>
                                    @elseif($schedule->assessment_type === 'MENGERJAKAN SOAL UJIAN')
                                        <span class="badge badge-success">
                                            Soal Ujian
                                        </span>
                                    @else
                                        <span class="badge badge-purple">
                                            Lisan/Presentasi
                                        </span>
                                    @endif
                                </td>
                            @endcanany
                            <td class="whitespace-nowrap text-center align-middle">
                                @if ($schedule->isComplete())
                                    <span class="badge badge-success">
                                        Lengkap
                                    </span>
                                @else
                                    <span class="badge badge-warning">
                                        Tinjau
                                    </span>
                                @endif
                            </td>
                            @if(!$isMahasiswa)
                                <td class="whitespace-nowrap text-center align-middle">
                                    <div class="inline-flex items-center justify-center gap-1">
                                        {{-- Tombol Detail --}}
                                        <a href="{{ route('exam_schedules.show', $schedule->id) }}"
                                           class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-blue-500 hover:bg-blue-50 transition-all duration-200"
                                           title="Lihat Detail">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                        </a>
                                        <!-- Tombol Edit -->
                                        @can('jadwal_update')
                                        <a href="{{ route('exam_schedules.edit', $schedule->id) }}"
                                           class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-amber-500 hover:bg-amber-50 transition-all duration-200"
                                           title="Edit">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                        </a>
                                        @endcan
                                        <!-- Tombol Hapus -->
                                        @can('jadwal_delete')
                                        <button type="button"
                                                @click="$dispatch('open-delete-modal', { url: '{{ route('exam_schedules.destroy', $schedule->id) }}', name: '{{ addslashes($schedule->course->name ?? 'Jadwal Ujian') }}' })"
                                                class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-rose-600 hover:bg-rose-50 transition-all duration-200"
                                                title="Hapus">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                        @endcan
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                             <td colspan="{{ $isMahasiswa ? 5 : ((auth()->user()->can('jadwal_create') || auth()->user()->can('jadwal_update') || auth()->user()->can('jadwal_delete')) ? 9 : 6) }}" class="empty-state">
                                <div class="empty-state-icon">
                                    <svg class="w-12 h-12 mx-auto text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                                <p class="empty-state-text">Belum ada jadwal ujian.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-5">{{ $schedules->links() }}</div>

    <!-- Modal Konfirmasi Hapus -->
    <div x-data="{ openDelete: false, deleteUrl: '', scheduleName: '' }"
         x-show="openDelete"
         @open-delete-modal.window="openDelete = true; deleteUrl = $event.detail.url; scheduleName = $event.detail.name;"
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm"
         style="display: none;"
         x-transition>
        <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-sm mx-4 border border-slate-200"
             @click.away="openDelete = false">
            <div class="text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-rose-100 text-rose-600 mb-4">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-slate-800 mb-2">Konfirmasi Hapus</h3>
                <p class="text-sm text-slate-500 mb-6 font-normal">Apakah Anda yakin ingin menghapus jadwal ujian <span class="font-semibold text-slate-800" x-text="scheduleName"></span>?</p>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" @click="openDelete = false"
                        class="btn btn-secondary w-full">Batal</button>
                <form :action="deleteUrl" method="POST" class="w-full">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-primary bg-rose-600 hover:bg-rose-700 w-full text-white">Ya, Hapus</button>
                </form>
            </div>
        </div>
    </div>

    @can('jadwal_create')
    <!-- Modal Import -->
    <div id="modalImport"
         class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm"
         onclick="if(event.target===this) this.classList.add('hidden')">
        <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-lg mx-4 border border-slate-200">
            <div class="flex justify-between items-center mb-5">
                <div>
                    <h3 class="text-lg font-bold text-slate-800">Import Jadwal Ujian</h3>
                    <p class="text-xs text-slate-400 mt-1">Upload file Excel untuk import data jadwal ujian</p>
                </div>
                <button onclick="document.getElementById('modalImport').classList.add('hidden')"
                        class="text-slate-400 hover:text-slate-600 p-1 rounded-lg hover:bg-slate-100">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <form action="{{ route('exam_schedules.import') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="mb-4">
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Upload file Excel (.xlsx, .xls, .csv)</label>
                    <input type="file" name="file" accept=".xlsx,.xls,.csv" required
                           class="w-full text-sm text-slate-500 border border-slate-300 rounded-lg p-2.5 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-[#DA2D3B] hover:file:bg-indigo-100">
                </div>
                <div class="mb-5 p-3.5 bg-slate-50 rounded-xl border border-slate-200">
                    <p class="text-xs font-semibold text-slate-600 mb-1.5">Ketentuan Import:</p>
                    <ul class="list-disc list-inside text-xs text-slate-500 space-y-1">
                        <li>Gunakan template excel resmi untuk menghindari kegagalan format.</li>
                        <li>Urutan Kolom Excel (Mulai dari Kiri):</li>
                    </ul>
                    <div class="mt-2 bg-white border border-slate-100 rounded-lg p-2 max-h-40 overflow-y-auto">
                        <ol class="list-decimal list-inside text-[11px] text-slate-600 space-y-0.5 font-medium">
                            <li>Program Studi</li>
                            <li>Kode MK</li>
                            <li>Nama MK</li>
                            <li>Kelas</li>
                            <li>Jumlah Mahasiswa</li>
                            <li>Kode Dosen 1</li>
                            <li>Kode Dosen 2</li>
                            <li>Jenis Assesment</li>
                            <li>Metode Pelaksanaan</li>
                            <li>Waktu Pelaksanaan</li>
                            <li>Pembagian Peserta Ujian</li>
                            <li>Soal Ujian</li>
                            <li>Lembar Jawaban</li>
                            <li>Kebutuhan Kertas Buram</li>
                            <li>Pengawal</li>
                            <li>Jadwal Ujian Serentak se-TUNC (Tanggal: dd/mm/yyyy)</li>
                            <li>URL Soal Ujian</li>
                            <li>URL Lembar Jawaban Custom</li>
                            <li>Aturan Ujian</li>
                        </ol>
                    </div>
                </div>
                <div class="flex justify-between items-center gap-3">
                    <a href="{{ route('exam_schedules.template') }}" class="inline-flex items-center text-xs font-semibold text-[#DA2D3B] hover:underline gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Unduh Template Excel
                    </a>
                    <div class="flex gap-2">
                        <button type="button"
                                onclick="document.getElementById('modalImport').classList.add('hidden')"
                                class="btn btn-secondary">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            Upload & Import
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    @endcan

</x-app-layout>
