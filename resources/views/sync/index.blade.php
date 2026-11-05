<x-app-layout>
    <x-slot name="header">Sinkronisasi Data</x-slot>

    <!-- ROW 1: Import JSON (kiri) + Status & Help (kanan) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5" x-data="{ importType: 'student' }">
        <!-- Panel Import JSON -->
        <div class="lg:col-span-2">
            <div class="campus-card p-6 h-full">
                <div class="flex items-center justify-between mb-5">
                    <div>
                        <h3 class="text-base font-bold text-slate-800">
                            Import Data via JSON
                        </h3>
                        <p class="text-xs text-slate-400 mt-0.5">Unggah file JSON untuk memasukkan data mahasiswa, KRS, mata kuliah, atau profil akademik (iGracias + BASILA)</p>
                    </div>
                </div>

                <form action="{{ route('sync.import-json') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                    @csrf

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Pilih Jenis Data -->
                        <div>
                            <label class="campus-label" for="import_type">Jenis Data <span class="text-rose-400">*</span></label>
                            <select id="import_type" name="type" x-model="importType" class="campus-select w-full" required>
                                <option value="student">Profil Mahasiswa (student)</option>
                                <option value="krs">KRS Mahasiswa (krs)</option>
                                <option value="attendance">Jadwal/Kehadiran Mahasiswa (attendance)</option>
                                <option value="course">Mata Kuliah (course)</option>
                                <option value="academic_profile">Profil Akademik (iGracias + BASILA)</option>
                            </select>
                        </div>

                        <!-- Pilih File JSON -->
                        <div>
                            <label class="campus-label" for="json_file">Pilih File JSON (.json) <span class="text-rose-400">*</span></label>
                            <input type="file" id="json_file" name="json_file" accept=".json" class="campus-input w-full py-1.5" required>
                        </div>
                    </div>

                    <!-- Catatan Khusus Profil Akademik -->
                    <div x-show="importType === 'academic_profile'" x-cloak class="bg-amber-50 border border-amber-200 rounded-lg p-3 text-xs text-amber-700">
                        <span class="font-semibold">Catatan Profil Akademik:</span> File JSON harus berformat <code class="bg-amber-100 px-1 rounded">{"data": [...]}</code> dari sumber iGracias + BASILA (sidang tingkat, tugas akhir, TAK, nilai TA, MK mengulang/belum lulus, approval BASILA, dan status BPP). Matching dilakukan berdasarkan NIM + nama.
                    </div>

                    <!-- Submit -->
                    <button type="submit" class="btn btn-primary w-full justify-center py-3">
                        Jalankan Import
                    </button>
                </form>
            </div>
        </div>

        <!-- Kolom Kanan: Status Data + Help Format JSON -->
        <div class="space-y-5">
            <!-- PANEL: Status Data di Database -->
            <div class="campus-card p-5 flex flex-col">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-sm font-bold text-slate-800">Status Data</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Jumlah data di database</p>
                    </div>
                    <button id="refreshStatsBtn" onclick="refreshStats()"
                            class="text-xs text-[#DA2D3B] hover:underline">
                        Refresh
                    </button>
                </div>

                <div id="statsGrid" class="grid grid-cols-2 gap-3 flex-1">
                    @foreach ($dataStats as $stat)
                        <div class="stat-card relative p-4 pl-5 bg-white rounded-xl border border-slate-200/60 shadow-sm hover:shadow-md hover:border-[#DA2D3B]/30 transition-all duration-300 flex flex-col justify-center overflow-hidden group"
                             data-key="{{ $stat['key'] }}">
                            <div class="absolute left-0 top-0 w-1 h-full bg-slate-100 group-hover:bg-[#DA2D3B] transition-colors duration-300"></div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">{{ $stat['label'] }}</p>
                            <p class="text-2xl font-extrabold text-slate-800 stat-count tracking-tight">
                                {{ number_format($stat['count']) }}
                            </p>
                        </div>
                    @endforeach
                </div>

                <p class="text-[10px] text-slate-300 text-center mt-3" id="statsLastUpdate">
                    Terakhir diperbarui: {{ now()->format('H:i:s') }}
                </p>
            </div>

            <!-- Panel Help / Format Template JSON -->
            <div class="campus-card p-5 flex flex-col">
                <h3 class="text-sm font-bold text-slate-800 mb-3">Format Data JSON</h3>
                
                <div class="overflow-y-auto text-xs space-y-3 max-h-[200px] pr-1">
                    <!-- Template Student -->
                    <div x-show="importType === 'student'">
                        <p class="font-semibold text-slate-600 mb-1">Format Profil Mahasiswa:</p>
                        <pre class="bg-slate-50 p-2.5 rounded-lg border border-slate-200 font-mono text-[10px] text-slate-700 overflow-x-auto select-all">[
  {
    "nim": "1202200001",
    "nama_siswa": "Krisna Putra",
    "kelas": "IF-44-01",
    "kelamin": "Laki-laki",
    "agama": "Islam",
    "prodi": "Informatika"
  }
]</pre>
                    </div>

                    <!-- Template KRS -->
                    <div x-show="importType === 'krs'">
                        <p class="font-semibold text-slate-600 mb-1">Format KRS Mahasiswa:</p>
                        <pre class="bg-slate-50 p-2.5 rounded-lg border border-slate-200 font-mono text-[10px] text-slate-700 overflow-x-auto select-all">[
  {
    "nim": "1202200001",
    "kode_matakuliah": "IF-101",
    "nama_matakuliah": "Pemrograman Web",
    "kelas_matakuliah": "IF-44-01",
    "kode_dosen": "DSN001",
    "nama_dosen": "Dosen A",
    "sks": 3,
    "tahun_akademik": "2025/2026",
    "semester": 4
  }
]</pre>
                        <p class="mt-2 text-[10px] leading-relaxed text-amber-700">
                            Nilai <code>semester</code> adalah semester studi mahasiswa (misalnya 4 atau 8), bukan semester ganjil/genap. Untuk memenuhi syarat yudisium, impor baris KRS TA2 yang sebenarnya dari iGracias. Kode/nama contoh IF-101 dan Pemrograman Web bukan TA2. Tahun akademik harus sama dengan periode akademik aktif.
                        </p>
                    </div>

                    <!-- Template Attendance -->
                    <div x-show="importType === 'attendance'">
                        <p class="font-semibold text-slate-600 mb-1">Format Jadwal/Kehadiran:</p>
                        <pre class="bg-slate-50 p-2.5 rounded-lg border border-slate-200 font-mono text-[10px] text-slate-700 overflow-x-auto select-all">[
  {
    "nim": "1202200001",
    "kode_matakuliah": "IF-101",
    "nama_matakuliah": "Pemrograman Web",
    "kelas": "IF-44-01",
    "jumlah_hadir": 14,
    "jumlah_pertemuan": 16,
    "tahun_akademik": "2025/2026",
    "semester": 4
  }
]</pre>
                    </div>

                    <!-- Template Course -->
                    <div x-show="importType === 'course'">
                        <p class="font-semibold text-slate-600 mb-1">Format Mata Kuliah:</p>
                        <pre class="bg-slate-50 p-2.5 rounded-lg border border-slate-200 font-mono text-[10px] text-slate-700 overflow-x-auto select-all">[
  [
    "11138",
    "CDK1GAB4",
    "ALGORITMA PEMROGRAMAN",
    "DS-05-01",
    "4",
    "40",
    "4",
    "4",
    "0",
    "36",
    "CAP",
    "DOSEN PEGAWAI TETAP",
    "FZP",
    "GENAP",
    "2526",
    "TIDAK",
    "YA",
    "",
    "ONSITE",
    "TIDAK",
    "TIDAK",
    "FZP",
    ""
  ]
]</pre>
                    </div>

                    <!-- Template Academic Profile -->
                    <div x-show="importType === 'academic_profile'">
                        <p class="font-semibold text-slate-600 mb-1">Format Profil Akademik (iGracias + BASILA):</p>
                        <pre class="bg-slate-50 p-2.5 rounded-lg border border-slate-200 font-mono text-[10px] text-slate-700 overflow-x-auto select-all">{
  "data": [
    {
      "nim": "1202200001",
      "nama": "Krisna Putra",
      "sidang_tingkat": [ ... ],
      "tugas_akhir": { ... },
      "tak": { ... },
      "eprt": { ... },
      "nilai_ta": { ... },
      "skpi": { "approval_status": "approved" },
      "course_issues": [ ... ],
      "basila": { ... },
      "bpp": { "status": "lunas" },
      "pembayaran_wisuda": { "status": "paid" },
      "ipk": 3.77,
      "semester_aktif": 8
    }
  ]
}</pre>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ROW 2: Log Sinkronisasi Terbaru -->
    <div class="campus-card overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
            <div>
                <h3 class="text-sm font-bold text-slate-800">Log Sinkronisasi Terbaru</h3>
                <p class="text-xs text-slate-400 mt-0.5">Riwayat proses import yang pernah dijalankan</p>
            </div>
            <div class="flex items-center gap-3">
                @if($latestLogs->flatten()->whereIn('status', ['pending', 'processing'])->count() > 0)
                    <span class="text-xs text-amber-600 font-medium">
                        Proses sedang berjalan…
                    </span>
                @endif
                <button onclick="window.location.reload()" class="btn btn-secondary text-xs">
                    Refresh Log
                </button>
            </div>
        </div>

        @php
            $allLogs = $latestLogs->flatten()->sortByDesc('created_at')->take(15);
        @endphp

        @if($allLogs->isEmpty())
            <div class="empty-state py-12">
                <p class="empty-state-text">Belum ada riwayat sinkronisasi.</p>
                <p class="text-xs text-slate-300 mt-1">Jalankan import pertama kali menggunakan form di atas.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="campus-table">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Modul</th>
                            <th>Dijalankan Oleh</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Diproses</th>
                            <th class="text-center">Dilewati</th>
                            <th>Durasi</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($allLogs as $log)
                            <tr>
                                <td class="text-xs text-slate-500 whitespace-nowrap">
                                    {{ $log->created_at->format('d M Y') }}
                                    <span class="block text-slate-400">{{ $log->created_at->format('H:i:s') }}</span>
                                </td>
                                <td>
                                    <span class="text-xs font-semibold text-slate-700">
                                        {{ $settings->firstWhere('module', $log->module)?->label ?? $log->module }}
                                    </span>
                                </td>
                                <td class="text-xs text-slate-500">{{ $log->triggered_by }}</td>
                                <td class="text-center">
                                    <span class="badge {{ $log->status_badge }} text-xs">
                                        {{ ucfirst($log->status) }}
                                    </span>
                                </td>
                                <td class="text-center font-semibold text-slate-700">
                                    {{ $log->processed_records !== null ? number_format($log->processed_records) : '—' }}
                                </td>
                                <td class="text-center text-slate-400">
                                    {{ $log->skipped_records > 0 ? number_format($log->skipped_records) : '—' }}
                                </td>
                                <td class="text-xs text-slate-400">{{ $log->durasi }}</td>
                                <td class="text-xs">
                                    @if($log->status === 'failed' && $log->error_message)
                                        <span class="text-rose-500 truncate block max-w-[200px]" title="{{ $log->error_message }}">
                                            {{ $log->error_message }}
                                        </span>
                                    @elseif($log->status === 'completed' && $log->error_message)
                                        <span class="text-amber-600 truncate block max-w-[200px]" title="{{ $log->error_message }}">
                                            {{ $log->error_message }}
                                        </span>
                                    @elseif($log->status === 'completed')
                                        <span class="text-emerald-600">Selesai</span>
                                    @elseif($log->status === 'processing')
                                        <span class="text-sky-600">Sedang berjalan…</span>
                                    @else
                                        <span class="text-slate-300">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

<script>
// Refresh Stats via AJAX
function refreshStats() {
    const btn = document.getElementById('refreshStatsBtn');
    btn.style.opacity = '0.5';
    btn.disabled = true;

    fetch('{{ route('sync.data-stats') }}')
        .then(r => r.json())
        .then(stats => {
            stats.forEach(stat => {
                const card = document.querySelector(`.stat-card[data-key="${stat.key}"]`);
                if (card) {
                    const countEl = card.querySelector('.stat-count');
                    if (countEl) {
                        countEl.textContent = Number(stat.count).toLocaleString('id-ID');
                    }
                }
            });
            document.getElementById('statsLastUpdate').textContent =
                'Terakhir diperbarui: ' + new Date().toLocaleTimeString('id-ID');
        })
        .catch(() => {})
        .finally(() => {
            btn.style.opacity = '1';
            btn.disabled = false;
        });
}

// Hindari reload halaman otomatis; log dapat diperbarui secara manual.
@if($latestLogs->flatten()->whereIn('status', ['pending', 'processing'])->count() > 0)
    // Hanya refresh statistik tanpa memuat ulang halaman.
    setInterval(refreshStats, 5000);
@endif
</script>
</x-app-layout>
