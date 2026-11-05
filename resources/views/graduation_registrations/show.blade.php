<x-app-layout>
    <x-slot name="header">Detail Pendaftaran Yudisium</x-slot>

    <div x-data="{
        openPreview: false,
        previewUrl: '',
        downloadUrl: '',
        previewName: '',
        isPdf: false,
        openPreviewModal(url, downloadUrl, fileName, isPdf) {
            this.previewUrl = url;
            this.downloadUrl = downloadUrl;
            this.previewName = fileName;
            this.isPdf = isPdf;
            this.openPreview = true;
        }
    }">
        <!-- Header -->
        <div class="flex items-start justify-between mb-5">
            <div>
                <div class="flex items-center gap-3 mb-1">
                    <span class="badge {{ $registration->status_badge }}">{{ $registration->status_label }}</span>
                    <span class="text-sm font-semibold text-slate-500">{{ $registration->graduation_period ?? '-' }}</span>
                </div>
                <p class="text-xs text-slate-400 mt-0.5">Dibuat: {{ $registration->created_at->translatedFormat('d F Y, H:i') }} WIB</p>
            </div>
            <div class="flex items-center gap-2">
                @if($registration->isEditable())
                <a href="{{ route('graduation-registrations.edit', $registration) }}" class="btn btn-primary">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Edit Pendaftaran
                </a>
                @endif
                <a href="{{ route('graduation-registrations.index') }}" class="btn btn-secondary">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Kembali
                </a>
            </div>
        </div>

        <!-- Review Notes -->
        @if($registration->review_notes && in_array($registration->status, ['needs_review', 'rejected', 'approved']))
        <div class="mb-5 p-4 rounded-2xl border {{ $registration->status === 'approved' ? 'bg-emerald-50 border-emerald-200' : 'bg-amber-50 border-amber-200' }}">
            <p class="text-sm font-bold {{ $registration->status === 'approved' ? 'text-emerald-800' : 'text-amber-800' }} mb-1">
                Catatan Reviewer
            </p>
            <p class="text-xs sm:text-sm {{ $registration->status === 'approved' ? 'text-emerald-700' : 'text-amber-700' }} leading-relaxed">{{ $registration->review_notes }}</p>
        </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            <!-- Main Content -->
            <div class="lg:col-span-7 space-y-5">
                <!-- 1. Data Mahasiswa -->
                <div class="campus-card">
                    <div class="px-5 py-3.5 border-b border-slate-100">
                        <h3 class="text-sm font-semibold text-slate-800">Data Wisuda & Kontak</h3>
                    </div>
                    <div class="px-5 py-4 space-y-1">

                        <div class="detail-row">
                            <span class="detail-label">Email Pribadi</span>
                            <span class="detail-value">{{ $registration->personal_email ?? '-' }}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">No. HP Mahasiswa</span>
                            <span class="detail-value">{{ $registration->student_phone ?? '-' }}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">No. HP Orang Tua</span>
                            <span class="detail-value">{{ $registration->parent_phone ?? '-' }}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Tanggal Selesai Revisi</span>
                            <span class="detail-value">{{ $registration->revision_completion_date ? \Carbon\Carbon::parse($registration->revision_completion_date)->translatedFormat('d F Y') : '-' }}</span>
                        </div>
                    
                    </div>
                </div>

                <!-- 2. Perubahan Judul Pasca Sidang -->
                <div class="campus-card">
                    <div class="px-5 py-3.5 border-b border-slate-100">
                        <h3 class="text-sm font-semibold text-slate-800">Perubahan Judul Pasca Sidang</h3>
                    </div>
                    <div class="px-5 py-4 space-y-1">
                        <div class="detail-row">
                            <span class="detail-label">Perubahan Judul</span>
                            <span class="detail-value font-semibold {{ $registration->has_title_change ? 'text-amber-700' : 'text-slate-700' }}">
                                {{ $registration->has_title_change ? 'Ya' : 'Tidak' }}
                            </span>
                        </div>
                        @if($registration->has_title_change)
                        <div class="detail-row">
                            <span class="detail-label">Status SK TA</span>
                            <span class="detail-value">
                                @if($registration->sk_update_status === 'processed_laa')
                                    Sudah Mengajukan dan Sudah Diproses LAA
                                @elseif($registration->sk_update_status === 'not_processed_laa')
                                    Sudah Mengajukan, Belum Diproses LAA
                                @else
                                    -
                                @endif
                            </span>
                        </div>
                        @if($registration->sk_update_submission_date)
                        <div class="detail-row">
                            <span class="detail-label">Tanggal Submit SK TA</span>
                            <span class="detail-value">{{ $registration->sk_update_submission_date->translatedFormat('d F Y') }}</span>
                        </div>
                        @endif
                        @endif
                    </div>
                </div>




                <!-- 6. Dokumen Pendukung -->
                <div class="campus-card">
                    <div class="px-5 py-3.5 border-b border-slate-100">
                        <h3 class="text-sm font-semibold text-slate-800">Dokumen Pendukung</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="campus-table">
                            
                            <thead>
                                <tr>
                                    <th>Jenis Dokumen</th>
                                    <th>File Dokumen</th>
                                    <th class="!text-center">Status Dokumen</th>
                                    <th class="!text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($documentTypes as $type => $label)
                                @php
                                    $doc = $registration->getDocumentByType($type);
                                    $isPdf = $doc && strtolower($doc->file_type) === 'pdf';
                                    $previewUrl = $doc ? route('graduation-registration-documents.preview', $doc) : '#';
                                    $downloadUrl = $doc ? route('graduation-registration-documents.download', $doc) : '#';
                                @endphp
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="font-medium text-slate-700">{{ $label }}</td>
                                    <td>
                                        @if($doc)
                                        <button type="button"
                                                @click="openPreviewModal('{{ $previewUrl }}', '{{ $downloadUrl }}', '{{ addslashes($doc->file_name) }}', {{ $isPdf ? 'true' : 'false' }})"
                                                class="flex items-center gap-1.5 group text-left cursor-pointer focus:outline-none"
                                                title="Klik untuk lihat preview">
                                            <span class="text-xs text-slate-800 font-semibold group-hover:text-sky-700 group-hover:underline truncate max-w-xs transition-colors">{{ $doc->file_name }}</span>
                                            <span class="text-[11px] text-slate-400">({{ $doc->file_size_human }})</span>
                                        </button>
                                        @else
                                        <span class="text-sm text-slate-400">-</span>
                                        @endif
                                    </td>
                                    <td class="text-center whitespace-nowrap">
                                    @if($doc)
                                        <span class="badge {{ $doc->status_badge }}">{{ $doc->status_label }}</span>
                                        @if($doc->notes && $doc->status === 'rejected')
                                            <div class="mt-1 text-xs text-slate-500">
                                                Catatan: {{ $doc->notes }}
                                            </div>
                                         @endif
                                    @else
                                        <span class="text-xs text-slate-400">-</span>
                                    @endif
                                    </td>
                                    <td class="text-center whitespace-nowrap">
                                        @if($doc)
                                        <div class="flex items-center justify-center gap-1">
                                            <!-- Icon Lihat Preview (No Text) -->
                                            <button type="button"
                                                    @click="openPreviewModal('{{ $previewUrl }}', '{{ $downloadUrl }}', '{{ addslashes($doc->file_name) }}', {{ $isPdf ? 'true' : 'false' }})"
                                                    class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-slate-400 hover:text-sky-600 hover:bg-sky-50 transition-all duration-200 cursor-pointer"
                                                    title="Lihat Preview Dokumen">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                </svg>
                                            </button>

                                            <!-- Icon Unduh Dokumen (No Text) -->
                                            <a href="{{ $downloadUrl }}"
                                               class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 transition-all duration-200 cursor-pointer"
                                               title="Unduh Dokumen">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                                </svg>
                                            </a>
                                        </div>
                                        @else
                                        <span class="text-xs text-slate-300">-</span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 7. Predikat Kelulusan (CUMLAUDE / SUMMA CUMLAUDE) -->
                <div class="campus-card">
                    <div class="px-5 py-3.5 border-b border-slate-100 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-slate-800">Predikat Kelulusan</h3>
                        <span class="badge {{ $registration->honors_predicate && $registration->honors_predicate !== 'none' ? 'badge-success' : 'badge-slate' }}">
                            {{ $registration->honors_predicate_label }}
                        </span>
                    </div>
                    <div class="px-5 py-4 space-y-1">
                        <div class="detail-row">
                            <span class="detail-label">Pengajuan Predikat</span>
                            <span class="detail-value font-semibold text-slate-800">{{ $registration->honors_predicate_label }}</span>
                        </div>
                        @if($registration->honors_predicate && $registration->honors_predicate !== 'none')
                        <div class="detail-row">
                            <span class="detail-label">Kriteria Tambahan</span>
                            <span class="detail-value font-semibold text-sky-700">{{ $registration->predicate_criteria_label }}</span>
                        </div>
                        @if($registration->predicate_criteria === 'hki')
                        <div class="detail-row">
                            <span class="detail-label">Nama Mitra UMKM</span>
                            <span class="detail-value">{{ $registration->predicate_umkm_partner ?? '-' }}</span>
                        </div>
                        @elseif($registration->predicate_criteria === 'lomba')
                        <div class="detail-row">
                            <span class="detail-label">Capaian Tingkat Lomba</span>
                            <span class="detail-value font-medium">{{ $registration->predicate_competition_level_label }}</span>
                        </div>
                        @elseif($registration->predicate_criteria === 'publikasi')
                        <div class="detail-row">
                            <span class="detail-label">Indeks Jurnal</span>
                            <span class="detail-value font-medium">{{ $registration->predicate_journal_index_label }}</span>
                        </div>
                        @endif

                        @if($registration->predicate_document_path)
                        @php
                            $isPredicatePdf = strtolower(pathinfo($registration->predicate_document_name, PATHINFO_EXTENSION)) === 'pdf';
                            $predicatePreviewUrl = asset('storage/' . $registration->predicate_document_path);
                        @endphp
                        <div class="detail-row">
                            <span class="detail-label">Dokumen Persyaratan Predikat</span>
                            <span class="detail-value flex items-center gap-2">
                                <button type="button"
                                        @click="openPreviewModal('{{ $predicatePreviewUrl }}', '{{ $predicatePreviewUrl }}', '{{ addslashes($registration->predicate_document_name) }}', {{ $isPredicatePdf ? 'true' : 'false' }})"
                                        class="text-xs text-sky-700 font-semibold hover:underline flex items-center gap-1 cursor-pointer">
                                    <svg class="w-4 h-4 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    {{ $registration->predicate_document_name }}
                                </button>
                                <a href="{{ $predicatePreviewUrl }}"
                                   onclick="event.preventDefault();fetch(this.href).then(r=>r.blob()).then(b=>{const a=document.createElement('a');a.href=URL.createObjectURL(b);a.download='{{ addslashes($registration->predicate_document_name) }}';a.click();URL.revokeObjectURL(a.href);})"
                                   class="text-slate-400 hover:text-slate-700 p-1" title="Unduh File">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                    </svg>
                                </a>
                            </span>
                        </div>
                        @endif
                        @endif
                    </div>
                </div>

                <!-- 8. Catatan Tambahan -->
                <div class="campus-card">
                    <div class="px-5 py-3.5 border-b border-slate-100">
                        <h3 class="text-sm font-semibold text-slate-800">Catatan Tambahan</h3>
                    </div>
                    <div class="px-5 py-4">
                        <p class="text-xs sm:text-sm text-slate-700 leading-relaxed">
                            {{ $registration->biodata_correction_note ?: 'Tidak ada catatan koreksi biodata.' }}
                        </p>
                    </div>
                </div>
            </div>

           <!-- PANEL KANAN: Data Akademik Otomatis + Timeline Riwayat Status -->
    <div class="lg:col-span-5 sticky top-6 space-y-5">

    <!-- Card 1: Data Akademik Otomatis Kampus (Read-Only) -->
    <x-graduation.campus-data-display :campusData="$campusData ?? []" />

    <!-- Card 2: Timeline Riwayat Status -->
    <div class="campus-card">
        <div class="px-5 py-3.5 border-b border-slate-100">
            <h3 class="text-sm font-semibold text-slate-800">Timeline Riwayat Status</h3>
        </div>
        <div class="px-5 py-4">
            <div class="relative pl-6 space-y-6 before:absolute before:left-2 before:top-2.5 before:bottom-2.5 before:w-0.5 before:bg-slate-200">
                @forelse($registration->logs as $log)
                @php $isLatest = $loop->first; @endphp
                <div class="relative group">
                    <div class="absolute -left-[23px] top-0.5 w-4 h-4 rounded-full flex items-center justify-center transition-all duration-300 {{ $isLatest ? 'bg-emerald-600 ring-4 ring-emerald-100 shadow-sm' : 'bg-slate-300 ring-2 ring-white' }}">
                        @if($isLatest)
                            <div class="w-1.5 h-1.5 bg-white rounded-full"></div>
                        @endif
                    </div>
                    <div class="space-y-1">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1 sm:gap-2">
                            <span class="font-bold text-xs {{ $isLatest ? 'text-slate-900' : 'text-slate-700' }}">
                                {{ $log->action_label ?? 'Aktivitas' }}
                            </span>
                            <span class="text-[11px] font-medium text-slate-400 whitespace-nowrap">
                                {{ ($log->performed_at ?? $log->created_at)?->translatedFormat('d M Y, H:i') }} WIB
                            </span>
                        </div>
                        @if($log->performer)
                        <p class="text-[11px] text-slate-500 font-medium">Oleh: <span class="text-slate-700 font-semibold">{{ $log->performer->name }}</span></p>
                        @endif
                        @if($log->notes)
                        <div class="mt-2 p-3 bg-slate-50 border border-slate-200/70 rounded-xl text-xs text-slate-700 leading-relaxed">
                            <span class="font-semibold text-slate-800 text-[11px] block mb-0.5">Catatan:</span>
                            {{ $log->notes }}
                        </div>
                        @endif
                    </div>
                </div>
                @empty
                <p class="text-xs text-slate-400 italic">Belum ada riwayat status.</p>
                @endforelse
            </div>
        </div>
    </div>

</div>

        </div>

        <!-- MODAL PREVIEW DOKUMEN -->
        <div x-show="openPreview"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4 overflow-y-auto"
             style="display: none;">

            <div class="bg-white rounded-2xl max-w-4xl w-full p-5 shadow-2xl border border-slate-200 space-y-4 flex flex-col max-h-[92vh]"
                 @click.away="openPreview = false">
                
                <!-- Header Modal -->
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2.5 truncate max-w-lg">
                        <div class="w-8 h-8 rounded-lg bg-sky-50 text-sky-600 flex items-center justify-center flex-shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </div>
                        <div class="truncate">
                            <h3 class="text-sm font-bold text-slate-800 truncate" x-text="previewName">Preview Dokumen</h3>
                            <p class="text-[11px] text-slate-400">Preview langsung dalam browser tanpa mengunduh file</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <a :href="downloadUrl"
                           @click.prevent="fetch(downloadUrl).then(r=>r.blob()).then(b=>{const a=document.createElement('a');a.href=URL.createObjectURL(b);a.download=previewName;a.click();URL.revokeObjectURL(a.href);})"
                           class="btn btn-secondary !py-1 text-xs gap-1.5"
                           title="Unduh file ke perangkat">
                            <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                            Unduh Dokumen
                        </a>

                        <button type="button" @click="openPreview = false" class="p-1 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Preview Body -->
                <div class="flex-1 overflow-auto bg-slate-50/50 rounded-xl p-2 min-h-[50vh] flex items-center justify-center">
                    <template x-if="isPdf">
                        <iframe :src="previewUrl" class="w-full h-[70vh] rounded-xl border border-slate-200 shadow-inner"></iframe>
                    </template>
                    <template x-if="!isPdf">
                        <div class="flex items-center justify-center p-4 w-full h-[70vh] overflow-auto">
                            <img :src="previewUrl" :alt="previewName" class="max-h-full max-w-full rounded-xl shadow-md object-contain">
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
