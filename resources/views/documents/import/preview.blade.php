<x-app-layout>
    <x-slot name="header">Pratinjau Import Massal</x-slot>

    <div class="max-w-7xl mx-auto py-4" x-data="batchPreview({
        batchId: {{ $batch->id }},
        status: '{{ $batch->status }}',
        statusUrl: '{{ route('documents.import.status', $batch) }}',
        hasErrors: {{ $hasUnignoredErrors ? 'true' : 'false' }}
    })">
        {{-- Breadcrumb --}}
        <nav class="flex items-center gap-1.5 text-sm text-slate-500 mb-4" aria-label="Breadcrumb">
            <a href="{{ route('documents.index') }}" class="hover:text-[#DA2D3B] font-medium">Dokumen</a>
            <span class="text-slate-300">/</span>
            <a href="{{ route('documents.import.create') }}" class="hover:text-[#DA2D3B] font-medium">Import Massal</a>
            <span class="text-slate-300">/</span>
            <span class="text-slate-800 font-semibold">Batch #{{ $batch->id }}</span>
        </nav>

        {{-- Top Bar --}}
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div>
                <div class="flex items-center gap-2.5">
                    <h2 class="text-2xl font-bold text-slate-900 tracking-tight">Pratinjau Import Dokumen #{{ $batch->id }}</h2>
                    <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-bold {{ $batch->mode === 'upload' ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800' }}">
                        MODE {{ strtoupper($batch->mode) }}
                    </span>
                    <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-bold {{ $batch->status === 'completed' ? 'bg-emerald-100 text-emerald-800' : ($batch->status === 'processing' ? 'bg-indigo-100 text-indigo-800' : ($batch->status === 'cancelled' ? 'bg-rose-100 text-rose-800' : 'bg-slate-100 text-slate-800')) }}">
                        STATUS: {{ strtoupper($batch->status) }}
                    </span>
                </div>
                <p class="text-xs text-slate-500 mt-1">Dibuat oleh {{ $batch->user->name ?? 'User' }} pada {{ $batch->created_at->format('d M Y, H:i') }}.</p>
            </div>

            <div class="flex items-center gap-2">
                @if ($batch->rows()->where('status', 'error')->exists())
                    <a href="{{ route('documents.import.errors-csv', $batch) }}" class="btn btn-secondary inline-flex items-center gap-1.5 text-xs py-2 px-3 border border-rose-300 text-rose-700 hover:bg-rose-50">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Unduh CSV Error
                    </a>
                @endif

                @if (in_array($batch->status, ['staging', 'validated']))
                    <form action="{{ route('documents.import.cancel', $batch) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin membatalkan batch ini? Berkas sementara akan dihapus.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-secondary text-xs py-2 px-3 text-slate-600 hover:text-rose-600">
                            Batalkan
                        </button>
                    </form>

                    <form action="{{ route('documents.import.confirm', $batch) }}" method="POST">
                        @csrf
                        <button type="submit" :disabled="hasErrors"
                                class="btn btn-primary inline-flex items-center gap-1.5 text-xs py-2 px-4 disabled:opacity-50 disabled:cursor-not-allowed">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            Konfirmasi & Proses
                        </button>
                    </form>
                @elseif ($batch->status === 'completed')
                    <a href="{{ route('documents.index') }}" class="btn btn-primary text-xs py-2 px-4">
                        Lihat Daftar Dokumen
                    </a>
                @endif
            </div>
        </div>

        @include('partials.flash')

        {{-- Status / Alerts --}}
        @if ($batch->status === 'processing')
            <div class="mb-6 p-4 bg-indigo-50 border border-indigo-200 rounded-2xl flex items-center gap-3 animate-pulse">
                <svg class="w-5 h-5 text-indigo-600 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <div class="text-xs text-indigo-900">
                    <span class="font-bold">Batch sedang diproses di antrean background.</span> Halaman akan otomatis memperbarui data secara berkala.
                </div>
            </div>
        @elseif ($batch->status === 'completed')
            <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 rounded-2xl flex items-center gap-3">
                <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <div class="text-xs text-emerald-900">
                    <span class="font-bold">Proses impor selesai!</span> Semua baris yang valid telah berhasil disimpan ke database dan direktori dokumen.
                </div>
            </div>
        @elseif ($hasUnignoredErrors)
            <div class="mb-6 p-4 bg-rose-50 border border-rose-200 rounded-2xl flex items-center gap-3">
                <svg class="w-5 h-5 text-rose-600 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <div class="text-xs text-rose-900">
                    <span class="font-bold">Terdapat baris bermasalah (Error).</span> Tombol konfirmasi terkunci hingga seluruh baris error diperbaiki melalui tombol <em>Edit</em> atau diabaikan menggunakan tombol <em>Abaikan</em>.
                </div>
            </div>
        @endif

        {{-- Summary Cards --}}
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-6">
            <div class="bg-white p-3.5 rounded-xl border border-slate-200">
                <div class="text-xs text-slate-500 font-medium">Total Baris</div>
                <div class="text-xl font-bold text-slate-900 mt-1">{{ $summary['total'] ?? 0 }}</div>
            </div>
            <div class="bg-white p-3.5 rounded-xl border border-slate-200">
                <div class="text-xs text-emerald-600 font-medium">Valid (OK)</div>
                <div class="text-xl font-bold text-emerald-700 mt-1">{{ $summary['ok'] ?? 0 }}</div>
            </div>
            <div class="bg-white p-3.5 rounded-xl border border-slate-200">
                <div class="text-xs text-amber-600 font-medium">Peringatan</div>
                <div class="text-xl font-bold text-amber-700 mt-1">{{ $summary['warning'] ?? 0 }}</div>
            </div>
            <div class="bg-white p-3.5 rounded-xl border border-slate-200">
                <div class="text-xs text-rose-600 font-medium">Error</div>
                <div class="text-xl font-bold text-rose-700 mt-1">{{ $summary['error'] ?? 0 }}</div>
            </div>
            <div class="bg-white p-3.5 rounded-xl border border-slate-200">
                <div class="text-xs text-slate-400 font-medium">Dilewati (Skip)</div>
                <div class="text-xl font-bold text-slate-600 mt-1">{{ $summary['skipped'] ?? 0 }}</div>
            </div>
        </div>

        {{-- Table Card --}}
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-6">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-600">
                    <thead class="bg-slate-50 text-slate-500 uppercase tracking-wider text-[11px] border-b border-slate-200">
                        <tr>
                            <th class="py-3 px-3">#</th>
                            <th class="py-3 px-3">Status</th>
                            <th class="py-3 px-3">Nama Lama</th>
                            <th class="py-3 px-3">Nama Baru</th>
                            <th class="py-3 px-3">Jenis Dokumen</th>
                            <th class="py-3 px-3">Role</th>
                            <th class="py-3 px-3">Folder</th>
                            <th class="py-3 px-3">NIM</th>
                            <th class="py-3 px-3">Pesan Validasi</th>
                            @if (in_array($batch->status, ['staging', 'validated']))
                                <th class="py-3 px-3 text-right">Aksi</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($rows as $row)
                            @php
                                $d = $row->data['parsed_data'] ?? [];
                                $diff = $row->data['diff'] ?? [];
                                $messages = $row->messages ?? [];
                            @endphp
                            <tr class="hover:bg-slate-50/70 {{ $row->status === 'skipped' ? 'opacity-50 bg-slate-50/50' : '' }}">
                                <td class="py-3 px-3 font-semibold text-slate-800">{{ $row->row_number }}</td>
                                <td class="py-3 px-3">
                                    @if ($row->status === 'ok')
                                        <span class="inline-flex px-2 py-0.5 rounded font-semibold bg-emerald-100 text-emerald-800">OK</span>
                                    @elseif ($row->status === 'warning')
                                        <span class="inline-flex px-2 py-0.5 rounded font-semibold bg-amber-100 text-amber-800">WARNING</span>
                                    @elseif ($row->status === 'error')
                                        <span class="inline-flex px-2 py-0.5 rounded font-semibold bg-rose-100 text-rose-800">ERROR</span>
                                    @elseif ($row->status === 'skipped')
                                        <span class="inline-flex px-2 py-0.5 rounded font-semibold bg-slate-200 text-slate-700">SKIP</span>
                                    @endif
                                </td>
                                <td class="py-3 px-3 max-w-[150px] truncate" title="{{ $row->original_name }}">
                                    {{ $row->original_name ?: '-' }}
                                </td>
                                <td class="py-3 px-3 max-w-[180px]">
                                    <div class="font-medium text-slate-800 truncate" title="{{ $d['new_name'] ?? '' }}">
                                        {{ $d['new_name'] ?? '-' }}
                                    </div>
                                    @if (!empty($diff['title']))
                                        <div class="text-[10px] text-slate-400 mt-0.5 truncate" title="Lama: {{ $diff['title']['old'] }}">
                                            Lama: {{ $diff['title']['old'] }}
                                        </div>
                                    @endif
                                </td>
                                <td class="py-3 px-3">{{ $d['document_type'] ?? '-' }}</td>
                                <td class="py-3 px-3">
                                    <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] bg-slate-100 text-slate-700">
                                        {{ $d['role'] ?? '-' }}
                                    </span>
                                </td>
                                <td class="py-3 px-3 max-w-[140px] truncate" title="{{ $d['folder'] ?? '' }}">
                                    {{ $d['folder'] ?? '-' }}
                                    @if (!empty($diff['folder']))
                                        <div class="text-[10px] text-slate-400 mt-0.5 truncate" title="Lama: {{ $diff['folder']['old'] }}">
                                            Lama: {{ $diff['folder']['old'] }}
                                        </div>
                                    @endif
                                </td>
                                <td class="py-3 px-3 max-w-[120px] truncate" title="{{ is_array($d['nim'] ?? null) ? implode(', ', $d['nim']) : ($d['nim'] ?? '-') }}">
                                    {{ is_array($d['nim'] ?? null) ? implode(', ', $d['nim']) : ($d['nim'] ?? '-') }}
                                </td>
                                <td class="py-3 px-3 max-w-[200px]">
                                    @if (!empty($messages))
                                        <ul class="space-y-0.5">
                                            @foreach ($messages as $m)
                                                <li class="text-[11px] {{ ($m['type'] ?? '') === 'error' ? 'text-rose-600 font-medium' : 'text-amber-600' }}">
                                                    • {{ $m['message'] ?? '' }}
                                                </li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <span class="text-slate-400">-</span>
                                    @endif
                                </td>
                                @if (in_array($batch->status, ['staging', 'validated']))
                                    <td class="py-3 px-3 text-right whitespace-nowrap space-x-1">
                                        <button type="button" @click="openEditModal({{ json_encode([
                                            'id' => $row->id,
                                            'row_number' => $row->row_number,
                                            'new_name' => $d['new_name'] ?? '',
                                            'document_type' => $d['document_type'] ?? '',
                                            'role' => $d['role'] ?? '',
                                            'folder' => $d['folder'] ?? '',
                                            'nim' => is_array($d['nim'] ?? null) ? implode(';', $d['nim']) : ($d['nim'] ?? ''),
                                            'keywords' => is_array($d['keywords'] ?? null) ? implode(',', $d['keywords']) : ($d['keywords'] ?? ''),
                                            'update_url' => route('documents.import.row.update', [$batch, $row]),
                                        ]) }})" class="btn btn-secondary text-[11px] py-1 px-2">
                                            Edit
                                        </button>
                                        <form action="{{ route('documents.import.row.toggle-skip', [$batch, $row]) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="btn btn-secondary text-[11px] py-1 px-2 {{ $row->status === 'skipped' ? 'text-emerald-700' : 'text-slate-500' }}">
                                                {{ $row->status === 'skipped' ? 'Pulihkan' : 'Abaikan' }}
                                            </button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="py-8 text-center text-slate-400">
                                    Tidak ada data baris pada batch ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($rows->hasPages())
                <div class="p-4 border-t border-slate-100">
                    {{ $rows->links() }}
                </div>
            @endif
        </div>

        {{-- Modal Edit Baris --}}
        <div x-show="editOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm px-4" @keydown.escape.window="editOpen = false">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg border border-slate-200 p-6" @click.away="editOpen = false">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                    <h3 class="text-base font-bold text-slate-800">
                        Edit Data Baris #<span x-text="editForm.row_number"></span>
                    </h3>
                    <button type="button" @click="editOpen = false" class="text-slate-400 hover:text-slate-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form @submit.prevent="saveEditRow()">
                    <div class="space-y-3.5">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Baru (Judul) <span class="text-rose-500">*</span></label>
                            <input type="text" x-model="editForm.new_name" required class="w-full text-xs rounded-xl border-slate-300 focus:border-[#DA2D3B] focus:ring-[#DA2D3B]/10">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Jenis Dokumen</label>
                                <input type="text" x-model="editForm.document_type" class="w-full text-xs rounded-xl border-slate-300 focus:border-[#DA2D3B] focus:ring-[#DA2D3B]/10">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Role Dokumen</label>
                                <select x-model="editForm.role" class="w-full text-xs rounded-xl border-slate-300 focus:border-[#DA2D3B] focus:ring-[#DA2D3B]/10">
                                    <option value="Publik">Publik</option>
                                    <option value="Dosen dan Staf">Dosen dan Staf</option>
                                    <option value="Akademik">Akademik</option>
                                    <option value="Mahasiswa">Mahasiswa</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Folder Target</label>
                            <input type="text" x-model="editForm.folder" class="w-full text-xs rounded-xl border-slate-300 focus:border-[#DA2D3B] focus:ring-[#DA2D3B]/10">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">NIM (pisahkan dengan titik koma ';')</label>
                            <input type="text" x-model="editForm.nim" class="w-full text-xs rounded-xl border-slate-300 focus:border-[#DA2D3B] focus:ring-[#DA2D3B]/10">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Keyword (pisahkan dengan koma)</label>
                            <input type="text" x-model="editForm.keywords" class="w-full text-xs rounded-xl border-slate-300 focus:border-[#DA2D3B] focus:ring-[#DA2D3B]/10">
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-5 border-t border-slate-100 mt-5">
                        <button type="button" @click="editOpen = false" class="btn btn-secondary text-xs">Batal</button>
                        <button type="submit" :disabled="editSaving" class="btn btn-primary text-xs disabled:opacity-50">
                            <span x-show="!editSaving">Simpan & Validasi Ulang</span>
                            <span x-show="editSaving" x-cloak>Menyimpan...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function batchPreview(config) {
            return {
                batchId: config.batchId,
                status: config.status,
                hasErrors: config.hasErrors,
                editOpen: false,
                editSaving: false,
                editForm: {
                    id: null,
                    row_number: '',
                    new_name: '',
                    document_type: '',
                    role: '',
                    folder: '',
                    nim: '',
                    keywords: '',
                    update_url: '',
                },
                init() {
                    if (this.status === 'processing') {
                        this.pollStatus();
                    }
                },
                pollStatus() {
                    const timer = setInterval(() => {
                        fetch(config.statusUrl, {
                            headers: { 'Accept': 'application/json' }
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.status !== 'processing') {
                                clearInterval(timer);
                                window.location.reload();
                            }
                        })
                        .catch(() => clearInterval(timer));
                    }, 3000);
                },
                openEditModal(data) {
                    this.editForm = Object.assign({}, data);
                    this.editOpen = true;
                },
                saveEditRow() {
                    this.editSaving = true;
                    fetch(this.editForm.update_url, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            new_name: this.editForm.new_name,
                            document_type: this.editForm.document_type,
                            role: this.editForm.role,
                            folder: this.editForm.folder,
                            nim: this.editForm.nim,
                            keywords: this.editForm.keywords
                        })
                    })
                    .then(r => r.json())
                    .then(data => {
                        this.editSaving = false;
                        if (data.ok) {
                            this.editOpen = false;
                            window.location.reload();
                        } else {
                            alert('Gagal menyimpan baris.');
                        }
                    })
                    .catch(err => {
                        this.editSaving = false;
                        alert('Terjadi kesalahan koneksi.');
                    });
                }
            };
        }
    </script>
</x-app-layout>
