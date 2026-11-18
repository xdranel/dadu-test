<x-app-layout>
    <x-slot name="header">Import Massal Dokumen</x-slot>

    <div class="max-w-5xl mx-auto py-4" x-data="{
        mode: '{{ old('mode', 'upload') }}',
        allowUnknownNim: {{ old('allow_unknown_nim') ? 'true' : 'false' }},
        allowPublicToNonPublic: {{ old('allow_public_to_non_public') ? 'true' : 'false' }},
        excelFileName: '',
        fileCount: 0
    }">
        {{-- Breadcrumb --}}
        <nav class="flex items-center gap-1.5 text-sm text-slate-500 mb-4" aria-label="Breadcrumb">
            <a href="{{ route('documents.index') }}" class="hover:text-[#DA2D3B] font-medium">Dokumen</a>
            <span class="text-slate-300">/</span>
            <span class="text-slate-800 font-semibold">Import Massal</span>
        </nav>

        <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 tracking-tight">Import Massal Dokumen</h2>
                <p class="text-sm text-slate-500 mt-1">Unggah berkas baru atau perbarui metadata dokumen yang sudah ada secara massal melalui template spreadsheet.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('documents.create') }}" class="btn btn-secondary inline-flex items-center gap-1.5 text-xs">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Upload Manual
                </a>
            </div>
        </div>

        @include('partials.flash')

        @if ($errors->any())
            <div class="mb-5 p-4 bg-rose-50 border border-rose-200 rounded-2xl">
                <p class="font-semibold text-rose-700 text-sm mb-1">Gagal memproses berkas:</p>
                <ul class="list-disc list-inside text-rose-600 text-sm space-y-0.5">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Template Download Card --}}
        <div class="bg-gradient-to-r from-slate-50 to-rose-50/30 border border-slate-200 rounded-2xl p-5 mb-6 flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-start gap-3.5">
                <div class="w-10 h-10 rounded-xl bg-[#DA2D3B]/10 text-[#DA2D3B] flex items-center justify-center flex-shrink-0 mt-0.5">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                </div>
                <div>
                    <h3 class="font-bold text-slate-800 text-sm">Unduh Template Import</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Gunakan format template resmi yang berisi sheet data, daftar folder tujuan, dan petunjuk pengisian.</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('documents.import.template', ['format' => 'xlsx']) }}" class="btn btn-secondary inline-flex items-center gap-1.5 text-xs font-semibold py-2 px-3 border border-slate-300 hover:border-slate-400">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Download Excel (.xlsx)
                </a>
                <a href="{{ route('documents.import.template', ['format' => 'csv']) }}" class="btn btn-secondary inline-flex items-center gap-1.5 text-xs font-semibold py-2 px-3 border border-slate-300 hover:border-slate-400">
                    <svg class="w-4 h-4 text-sky-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    Download CSV (.csv)
                </a>
            </div>
        </div>

        {{-- Form Card --}}
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 mb-8">
            {{-- Mode Switcher Tabs --}}
            <div class="flex border-b border-slate-200 mb-6 gap-2">
                <button type="button" @click="mode = 'upload'"
                        :class="mode === 'upload' ? 'border-[#DA2D3B] text-[#DA2D3B] font-bold' : 'border-transparent text-slate-500 hover:text-slate-800 font-medium'"
                        class="pb-3 px-4 border-b-2 text-sm flex items-center gap-2 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1M12 4v12m0-12l-4 4m4-4l4 4"/></svg>
                    Mode 1: Upload Berkas Baru
                </button>
                <button type="button" @click="mode = 'update'"
                        :class="mode === 'update' ? 'border-[#DA2D3B] text-[#DA2D3B] font-bold' : 'border-transparent text-slate-500 hover:text-slate-800 font-medium'"
                        class="pb-3 px-4 border-b-2 text-sm flex items-center gap-2 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    Mode 2: Update Dokumen Lama
                </button>
            </div>

            <form action="{{ route('documents.import.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="mode" :value="mode">

                {{-- Mode Explanations --}}
                <div x-show="mode === 'upload'" class="mb-5 p-3.5 bg-amber-50/70 border border-amber-200/60 rounded-xl text-xs text-amber-900 leading-relaxed">
                    <span class="font-bold">Mode Upload Berkas Baru:</span> Cocok untuk mengunggah dokumen baru beserta berkas fisiknya. Kolom <em>Nama Lama</em> di Excel harus sama dengan nama berkas fisik yang Anda lampirkan (baik file langsung maupun di dalam arsip ZIP).
                </div>
                <div x-show="mode === 'update'" x-cloak class="mb-5 p-3.5 bg-blue-50/70 border border-blue-200/60 rounded-xl text-xs text-blue-900 leading-relaxed">
                    <span class="font-bold">Mode Update Dokumen Lama:</span> Cocok untuk memperbarui metadata (judul, jenis, folder, visibilitas, keyword, atau daftar NIM) dokumen yang sudah tersimpan di sistem. Kolom <em>Nama Lama</em> diisi dengan judul dokumen atau nama file lama di sistem. Berkas fisik tidak perlu diunggah ulang.
                </div>

                <div class="space-y-6">
                    {{-- 1. Berkas Spreadsheet --}}
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">
                            Berkas Excel / CSV <span class="text-rose-500">*</span>
                        </label>
                        <p class="text-xs text-slate-500 mb-2">Pilih berkas template (.xlsx atau .csv) yang telah diisi sesuai petunjuk.</p>
                        <input type="file" name="file_excel" accept=".xlsx,.csv" required
                               @change="excelFileName = $event.target.files[0]?.name || ''"
                               class="block w-full text-sm text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200 cursor-pointer border border-slate-200 rounded-xl p-1.5 focus:outline-none focus:border-[#DA2D3B]">
                    </div>

                    {{-- 2. Berkas Fisik (Mode Upload Saja) --}}
                    <div x-show="mode === 'upload'" x-transition>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">
                            Berkas Dokumen Fisik / Arsip ZIP <span class="text-rose-500">*</span>
                        </label>
                        <p class="text-xs text-slate-500 mb-2">Pilih satu atau banyak file dokumen, atau unggah satu berkas .zip berisi seluruh dokumen yang terdaftar di spreadsheet (maksimal 500 berkas, 15MB per berkas).</p>
                        <input type="file" name="files[]" multiple
                               @change="fileCount = $event.target.files.length"
                               class="block w-full text-sm text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200 cursor-pointer border border-slate-200 rounded-xl p-1.5 focus:outline-none focus:border-[#DA2D3B]">
                        <p x-show="fileCount > 0" class="text-xs text-slate-600 mt-1.5">
                            <span class="font-semibold" x-text="fileCount"></span> berkas terpilih untuk dicocokkan.
                        </p>
                    </div>

                    {{-- 3. Opsi Tambahan / Checkbox Kebijakan --}}
                    <div class="border-t border-slate-100 pt-5 space-y-3">
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="checkbox" name="allow_unknown_nim" value="1"
                                   x-model="allowUnknownNim"
                                   class="mt-1 w-4 h-4 rounded text-[#DA2D3B] focus:ring-[#DA2D3B]/20 border-slate-300">
                            <div>
                                <span class="text-sm font-semibold text-slate-800">Izinkan NIM belum terdaftar di sistem</span>
                                <p class="text-xs text-slate-500">Jika dicentang, baris dengan NIM yang valid secara format namun belum memiliki akun mahasiswa di database akan tetap diproses dengan status <em>Peringatan</em>.</p>
                            </div>
                        </label>

                        <label x-show="mode === 'update'" x-cloak class="flex items-start gap-3 cursor-pointer">
                            <input type="checkbox" name="allow_public_to_non_public" value="1"
                                   x-model="allowPublicToNonPublic"
                                   class="mt-1 w-4 h-4 rounded text-[#DA2D3B] focus:ring-[#DA2D3B]/20 border-slate-300">
                            <div>
                                <span class="text-sm font-semibold text-slate-800">Izinkan penurunan visibilitas dari Publik ke Non-Publik</span>
                                <p class="text-xs text-slate-500">Konfirmasi eksplisit diperlukan jika pembaruan dokumen mengubah folder/akses dari publik ke kategori internal/akademik/mahasiswa.</p>
                            </div>
                        </label>
                    </div>

                    {{-- Submit button --}}
                    <div class="pt-2 flex items-center justify-end gap-3">
                        <a href="{{ route('documents.index') }}" class="btn btn-secondary text-sm">Batal</a>
                        <button type="submit" class="btn btn-primary inline-flex items-center gap-2 text-sm font-semibold px-6 py-2.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Unggah & Validasi (Preview)
                        </button>
                    </div>
                </div>
            </form>
        </div>

        {{-- Active Staging Batches --}}
        @if ($activeBatches->isNotEmpty())
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                <h3 class="text-base font-bold text-slate-800 mb-3">Impor Dalam Proses / Belum Dikonfirmasi</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-600">
                        <thead class="bg-slate-50 text-xs text-slate-500 uppercase tracking-wider">
                            <tr>
                                <th class="py-2.5 px-3">Batch ID</th>
                                <th class="py-2.5 px-3">Mode</th>
                                <th class="py-2.5 px-3">Jumlah Baris</th>
                                <th class="py-2.5 px-3">Status</th>
                                <th class="py-2.5 px-3">Tanggal Dibuat</th>
                                <th class="py-2.5 px-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($activeBatches as $b)
                                <tr class="hover:bg-slate-50/60">
                                    <td class="py-2.5 px-3 font-semibold text-slate-800">#{{ $b->id }}</td>
                                    <td class="py-2.5 px-3">
                                        <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium {{ $b->mode === 'upload' ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800' }}">
                                            {{ strtoupper($b->mode) }}
                                        </span>
                                    </td>
                                    <td class="py-2.5 px-3">{{ $b->summary['total_rows'] ?? $b->rows()->count() }}</td>
                                    <td class="py-2.5 px-3">
                                        <span class="inline-flex px-2 py-0.5 rounded text-xs font-semibold {{ $b->status === 'validated' ? 'bg-emerald-100 text-emerald-800' : ($b->status === 'processing' ? 'bg-indigo-100 text-indigo-800' : 'bg-slate-100 text-slate-700') }}">
                                            {{ ucfirst($b->status) }}
                                        </span>
                                    </td>
                                    <td class="py-2.5 px-3 text-xs">{{ $b->created_at->format('d M Y, H:i') }}</td>
                                    <td class="py-2.5 px-3 text-right space-x-2">
                                        <a href="{{ route('documents.import.preview', $b) }}" class="btn btn-secondary text-xs py-1 px-2.5">
                                            Buka Preview
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
