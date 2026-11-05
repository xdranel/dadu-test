<x-app-layout>
    <x-slot name="header">Detail Pendaftaran Yudisium</x-slot>

    <div x-data="{
        sidebarOpen: true,
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
                <h2 class="text-lg font-bold text-slate-800">{{ $registration->student_name }} - {{ $registration->student_number }}</h2>
                <p class="text-xs text-slate-400 mt-0.5">{{ $registration->study_program ?? '-' }}</p>
            </div>
            <div class="flex items-center gap-2">
                <!-- Toggle Sidebar Button -->
                <button type="button" @click="sidebarOpen = !sidebarOpen"
                        class="btn btn-secondary !px-2.5 !py-2 flex items-center justify-center transition-colors"
                        :title="sidebarOpen ? 'Sembunyikan Sidebar' : 'Tampilkan Sidebar'">
                    <svg class="w-4 h-4 transition-transform duration-300" :class="sidebarOpen ? '' : 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"/>
                    </svg>
                </button>
                <a href="{{ route('admin.graduation-registrations.index') }}" class="btn btn-secondary">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Kembali
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 transition-all duration-300 gap-5" :class="sidebarOpen ? 'lg:grid-cols-3' : 'lg:grid-cols-1'">
            <!-- Main Content -->
            <div :class="sidebarOpen ? 'lg:col-span-2' : 'lg:col-span-1'" class="space-y-5">

                <!-- 1. Data Mahasiswa -->
                <div class="campus-card">
                    <div class="px-5 py-3.5 border-b border-slate-100">
                        <h3 class="text-sm font-semibold text-slate-800">Data Mahasiswa</h3>
                    </div>
                    <div class="px-5 py-4 space-y-1">
                        <div class="detail-row">
                            <span class="detail-label">NIM</span>
                            <span class="detail-value font-semibold">{{ $registration->student_number }}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Nama Mahasiswa</span>
                            <span class="detail-value font-semibold">{{ $registration->student_name }}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Program Studi</span>
                            <span class="detail-value">{{ $registration->study_program ?? '-' }}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Email Mahasiswa</span>
                            <span class="detail-value text-blue-600 font-medium">{{ $registration->student_email ?? auth()->user()->email }}</span>
                        </div>
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
                            <span class="detail-label">Nama Dosen Wali</span>
                            <span class="detail-value">{{ $registration->academic_advisor_name ?? '-' }}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Kode Dosen Wali</span>
                            <span class="detail-value">{{ $registration->academic_advisor_code ?? '-' }}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Periode Yudisium</span>
                            <span class="detail-value font-medium text-slate-800">{{ $registration->graduation_period ?? '-' }}</span>
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

                <!-- 3. Judul Tugas Akhir -->
                <div class="campus-card">
                    <div class="px-5 py-3.5 border-b border-slate-100">
                        <h3 class="text-sm font-semibold text-slate-800">Judul Tugas Akhir</h3>
                    </div>
                    <div class="px-5 py-4 space-y-1">
                        <div class="detail-row">
                            <span class="detail-label">Judul (Bahasa Indonesia)</span>
                            <span class="detail-value font-medium">{{ $registration->thesis_title_id ?? '-' }}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Judul (Bahasa Inggris)</span>
                            <span class="detail-value italic text-slate-700">{{ $registration->thesis_title_en ?? '-' }}</span>
                        </div>
                    </div>
                </div>

                <!-- 4. Pembimbing & Penguji -->
                <div class="campus-card">
                    <div class="px-5 py-3.5 border-b border-slate-100">
                        <h3 class="text-sm font-semibold text-slate-800">Pembimbing & Penguji</h3>
                    </div>
                    <div class="px-5 py-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6">
                            <div>
                                <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Pembimbing</h4>
                                <div class="detail-row">
                                    <span class="detail-label">Pembimbing 1</span>
                                    <span class="detail-value">{{ $registration->advisor_1_name ?? '-' }} {{ $registration->advisor_1_code ? '('.$registration->advisor_1_code.')' : '' }}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Pembimbing 2</span>
                                    <span class="detail-value">{{ $registration->advisor_2_name ?? '-' }} {{ $registration->advisor_2_code ? '('.$registration->advisor_2_code.')' : '' }}</span>
                                </div>
                            </div>
                            <div>
                                <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Penguji</h4>
                                @foreach([1, 2, 3] as $i)
                                <div class="detail-row">
                                    <span class="detail-label">Penguji {{ $i }}</span>
                                    <span class="detail-value">{{ $registration->{"examiner_{$i}_name"} ?? '-' }} {{ $registration->{"examiner_{$i}_code"} ? '('.$registration->{"examiner_{$i}_code"}.')' : '' }}</span>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 5. Data Akademik dari Sistem -->
                <div class="campus-card">
                    <div class="px-5 py-3.5 border-b border-slate-100">
                        <h3 class="text-sm font-semibold text-slate-800">Data Akademik (Sistem Kampus)</h3>
                    </div>
                    <div class="px-5 py-4">
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div class="text-center p-3 bg-slate-50 rounded-xl border border-slate-100">
                                <p class="text-xs text-slate-500 font-medium">Nilai TA Terbaru</p>
                                <p class="text-xl font-bold text-slate-800 mt-1">
                                    {{ !is_null($registration->tak_score) ? (int)$registration->tak_score : '-' }}
                                </p>
                            </div>
                            <div class="text-center p-3 bg-slate-50 rounded-xl border border-slate-100">
                                <p class="text-xs text-slate-500 font-medium">Skor EPRT</p>
                                <p class="text-xl font-bold text-slate-800 mt-1">
                                    {{ !is_null($registration->eprt_score) ? number_format($registration->eprt_score, 0) : '-' }}
                                </p>
                            </div>
                            <div class="text-center p-3 bg-slate-50 rounded-xl border border-slate-100">
                                <p class="text-xs text-slate-500 font-medium">Status Sidang Tingkat 3</p>
                                <p class="text-sm font-semibold text-slate-800 mt-1">
                                    @if($registration->level_3_exam_status)
                                        <span class="badge {{ $registration->level_3_exam_status === 'passed' ? 'badge-success' : 'badge-warning' }}">
                                            {{ $registration->level_3_exam_status === 'passed' ? 'Lulus' : 'Belum Lulus' }}
                                        </span>
                                    @else
                                        -
                                    @endif
                                </p>
                            </div>
                            <div class="text-center p-3 bg-slate-50 rounded-xl border border-slate-100">
                                <p class="text-xs text-slate-500 font-medium">Approval Foto i-Basila</p>
                                <p class="text-sm font-semibold text-slate-800 mt-1">
                                    @if($registration->ibasila_photo_approval_status)
                                        <span class="badge {{ $registration->ibasila_photo_approval_status === 'approved' ? 'badge-success' : ($registration->ibasila_photo_approval_status === 'rejected' ? 'badge-danger' : 'badge-warning') }}">
                                            {{ $registration->ibasila_photo_approval_status === 'approved' ? 'Disetujui' : ($registration->ibasila_photo_approval_status === 'rejected' ? 'Ditolak' : 'Pending') }}
                                        </span>
                                    @else
                                        -
                                    @endif
                                </p>
                            </div>
                        </div>

                        @if($registration->failed_courses && count($registration->failed_courses) > 0)
                        <div class="mt-4 p-4 bg-rose-50 border border-rose-200 rounded-lg">
                            <label class="text-xs font-bold text-rose-800 mb-2 block">Mata Kuliah Belum Lulus</label>
                            <ul class="mt-2 space-y-1 text-xs text-rose-700">
                                @foreach($registration->failed_courses as $course)
                                <li class="flex items-start gap-2">
                                    <span class="font-mono bg-rose-100 px-2 py-0.5 rounded">{{ $course['course_code'] ?? '-' }}</span>
                                    <span>{{ $course['course_name'] ?? '-' }}</span>
                                </li>
                                @endforeach
                            </ul>
                        </div>
                        @endif

                        @if($registration->retaken_courses && count($registration->retaken_courses) > 0)
                        <div class="mt-4 p-4 bg-amber-50 border border-amber-200 rounded-lg">
                            <label class="text-xs font-bold text-amber-800 mb-2 block">Mata Kuliah Mengulang</label>
                            <ul class="mt-2 space-y-1 text-xs text-amber-700">
                                @foreach($registration->retaken_courses as $course)
                                <li class="flex items-start gap-2">
                                    <span class="font-mono bg-amber-100 px-2 py-0.5 rounded">{{ $course['course_code'] ?? '-' }}</span>
                                    <span>{{ $course['course_name'] ?? '-' }} (Attempt: {{ $course['attempt'] ?? '-' }})</span>
                                </li>
                                @endforeach
                            </ul>
                        </div>
                        @endif

                        <div class="mt-4 text-xs text-slate-500">
                            <p>Terakhir di-sync: {{ $registration->last_synced_at?->format('d/m/Y H:i') ?? 'Belum pernah sync' }}</p>
                        </div>

                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4 pt-4 border-t border-slate-200">
                            <div class="text-center p-3 bg-slate-50 rounded-xl border border-slate-100">
                                <p class="text-xs text-slate-500 font-medium">Tgl Sidang</p>
                                <p class="text-sm font-semibold text-slate-800 mt-1">{{ $registration->exam_date?->format('d/m/Y') ?? '-' }}</p>
                            </div>
                            <div class="text-center p-3 bg-slate-50 rounded-xl border border-slate-100">
                                <p class="text-xs text-slate-500 font-medium">Batas Revisi</p>
                                <p class="text-sm font-semibold text-slate-800 mt-1">{{ $registration->revision_deadline?->format('d/m/Y') ?? '-' }}</p>
                            </div>
                            <div class="text-center p-3 bg-slate-50 rounded-xl border border-slate-100" colspan="2">
                                <p class="text-xs text-slate-500 font-medium">Selesai Revisi</p>
                                <p class="text-sm font-semibold text-slate-800 mt-1">{{ $registration->revision_completion_date?->format('d/m/Y') ?? '-' }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 6. Dokumen Pendukung (Preview Langsung Tanpa Unduh, Tanpa Kolom Status) -->
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
                                              @if($doc->status === 'rejected' && $doc->notes)
                                                  <div class="mt-1 text-xs text-slate-500">Catatan: {{ $doc->notes }}</div>
                                              @endif
                                          @else
                                              <span class="text-xs text-slate-400">-</span>
                                          @endif
                                     </td>
                                     <td class="text-center whitespace-nowrap">
                                         @if($doc)
                                             @can('graduation_registration_review')
                                                <div x-data="documentReview({{ $doc->id }}, '{{ route('graduation-registration-documents.review', $doc) }}')">
                                                   <!-- Tombol review -->
                                                   <div x-show="!reviewing" class="flex items-center justify-center">
                                                       <button type="button"
                                                               @click="openForm()"
                                                               class="text-xs text-sky-600 font-medium hover:underline">
                                                           Review
                                                       </button>
                                                   </div>

                                                   <!-- Form review -->
                                                   <div x-show="reviewing" class="space-y-2 text-left p-2 bg-slate-50 border border-slate-200 rounded-lg min-w-[200px]" style="display: none;">
                                                       <div class="text-[11px] font-bold text-slate-700 mb-1">Pilih Status Review:</div>
                                                       <div class="flex items-center gap-2">
                                                           <label class="text-xs font-medium flex items-center gap-1.5 cursor-pointer">
                                                               <input type="radio" name="status_{{ $doc->id }}" value="verified" x-model="reviewStatus" @change="reviewError = ''" class="text-[#DA2D3B] focus:ring-[#DA2D3B]">
                                                               Sesuai
                                                           </label>
                                                       </div>
                                                        <div class="flex items-center gap-2">
                                                            <label class="text-xs font-medium flex items-center gap-1.5 cursor-pointer">
                                                                <input type="radio" name="status_{{ $doc->id }}" value="rejected" x-model="reviewStatus" @change="reviewError = ''" class="text-[#DA2D3B] focus:ring-[#DA2D3B]">
                                                                Perlu Revisi
                                                            </label>
                                                        </div>

                                                        <div x-show="reviewStatus === 'rejected'" x-transition>
                                                            <label for="review-notes-{{ $doc->id }}" class="block text-xs font-medium text-slate-700 mb-1">
                                                                Catatan revisi <span class="font-normal text-slate-400">(opsional)</span>
                                                            </label>
                                                            <textarea id="review-notes-{{ $doc->id }}"
                                                                      x-model="reviewNotes"
                                                                      maxlength="500"
                                                                      rows="3"
                                                                      placeholder="Jelaskan bagian dokumen yang perlu diperbaiki..."
                                                                      class="w-full rounded-lg border border-slate-300 px-2.5 py-2 text-xs text-slate-700 placeholder:text-slate-400 focus:border-[#DA2D3B] focus:ring-[#DA2D3B]"></textarea>
                                                            <div class="mt-1 text-right text-[10px] text-slate-400">Maksimal 500 karakter</div>
                                                        </div>

                                                        <!-- Tampilkan pesan error/sukses -->
                                                       <div x-show="reviewError" class="text-xs text-rose-600 font-medium my-1" x-text="reviewError" style="display: none;"></div>
                                                       <div x-show="reviewSuccess" class="text-xs text-emerald-600 font-medium my-1" x-text="reviewSuccess" style="display: none;"></div>

                                                       <div class="flex items-center justify-end gap-2 pt-1">
                                                           <button type="button"
                                                                   @click="cancel()"
                                                                   :disabled="saving"
                                                                   class="btn btn-secondary !px-2.5 !py-1 text-xs">
                                                               Batal
                                                           </button>

                                                           <!-- Tombol Setujui (hanya jika memilih Sesuai) -->
                                                           <button x-show="reviewStatus === 'verified'"
                                                                   type="button"
                                                                   @click="submitReview($event)"
                                                                   :disabled="saving"
                                                                   class="btn btn-primary !px-2.5 !py-1 text-xs"
                                                                   style="display: none;">
                                                               <span x-show="!saving">Setujui</span>
                                                               <span x-show="saving" style="display: none;">Memproses...</span>
                                                           </button>

                                                           <!-- Tombol Kirim Revisi (hanya jika memilih Perlu Revisi) -->
                                                           <button x-show="reviewStatus === 'rejected'"
                                                                   type="button"
                                                                   @click="submitReview($event)"
                                                                   :disabled="saving"
                                                                   class="btn btn-primary bg-rose-600 hover:bg-rose-700 !px-2.5 !py-1 text-xs"
                                                                   style="display: none;">
                                                               <span x-show="!saving">Kirim Revisi</span>
                                                               <span x-show="saving" style="display: none;">Memproses...</span>
                                                           </button>
                                                       </div>
                                                   </div>
                                               </div>
                                            @else
                                                <span class="text-xs text-slate-400">-</span>
                                            @endcan
                                        @else
                                            <span class="text-xs text-slate-400">-</span>
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

            <!-- Sidebar / Review Action Box -->
            <div x-show="sidebarOpen" x-transition class="space-y-5">
                <!-- Form Review (jika belum final) -->
                @can('graduation_registration_review')
                <div class="campus-card">
                    <div class="px-5 py-3.5 border-b border-slate-100 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-slate-800">Aksi Review Admin</h3>
                        <span class="text-[10px] font-bold text-slate-400 uppercase">Verifikasi AKD</span>
                    </div>
                    <div class="px-5 py-4 space-y-4">
                        <form action="{{ route('admin.graduation-registrations.review', $registration) }}" method="POST" class="space-y-4">
                            @csrf
                        <!-- Validasi Manual Checklist (A5 & A6) -->
                        <div class="p-3 bg-white border border-slate-100 rounded-xl space-y-3">
                            <span class="text-sm font-bold text-amber-900 block flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Checklist Validasi Tambahan Admin
                            </span>
                            <div class="space-y-2 text-sm text-amber-900 ">
                                <label class="flex items-start gap-2 cursor-pointer select-none">
                                    <input type="checkbox" name="is_free_of_sanction" value="1" {{ old('is_free_of_sanction', $registration->is_free_of_sanction) ? 'checked' : '' }} class="rounded border-amber-300 text-emerald-600 focus:ring-emerald-500 mt-0.5">
                                    <span>Status Sanksi Disiplin/Kemahasiswaan: <strong>Bebas Sanksi</strong></span>
                                </label>
                                @if($registration->honors_predicate && $registration->honors_predicate !== 'none')
                                <label class="flex items-start gap-2 cursor-pointer select-none">
                                    <input type="checkbox" name="cumlaude_verified_by_admin" value="1" {{ old('cumlaude_verified_by_admin', $registration->cumlaude_verified_by_admin) ? 'checked' : '' }} class="rounded border-amber-300 text-emerald-600 focus:ring-emerald-500 mt-0.5">
                                    <span>Verifikasi Predikat Cumlaude / Kriteria HKI/Lomba/Jurnal (BPA/LPPM) Valid</span>
                                </label>
                                @endif
                            </div>
                        </div>

                            <div>
                                <label class="campus-label">Status Pengajuan</label>
                                <select name="status" class="campus-select">
                                    <option value="approved" {{ $registration->status === 'approved' ? 'selected' : '' }}>Setujui</option>
                                    <option value="needs_review" {{ $registration->status === 'needs_review' ? 'selected' : '' }}>Revisi</option>
                                    <option value="rejected" {{ $registration->status === 'rejected' ? 'selected' : '' }}>Tolak</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary w-full">
                                Simpan Hasil Review
                            </button>
                        </form>
                    </div>
                </div>
                @endcan

                <!-- Combined Card: Review Terakhir & Timeline Riwayat Status -->
                <div class="campus-card sticky top-6">
                    <div class="px-5 py-3.5 border-b border-slate-100 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-slate-800 flex items-center gap-2">
                            Review & Riwayat Status
                        </h3>
                        <button type="button" @click="sidebarOpen = false"
                                class="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"
                                title="Sembunyikan Sidebar">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"/>
                            </svg>
                        </button>
                    </div>

                    <div class="px-5 py-4 space-y-5">
                        <!-- Review Terakhir -->
                        @if($registration->reviewed_at)
                        <div class="pb-4 border-b border-slate-100">
                            <p class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-2.5">Review Terakhir</p>
                            <div class="space-y-1.5 bg-slate-50 p-3 rounded-xl border border-slate-100">
                                <div class="detail-row">
                                    <span class="detail-label">Reviewer</span>
                                    <span class="detail-value">{{ $registration->reviewer?->name ?? '-' }}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Waktu</span>
                                    <span class="detail-value">{{ $registration->reviewed_at->translatedFormat('d M Y, H:i') }} WIB</span>
                                </div>
                               
                            </div>
                        </div>
                        @endif

                        <!-- Timeline Riwayat Status (UI Presisi, Rapi, Responsive) -->
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-4">Timeline Riwayat Status</p>
                            <div class="relative pl-6 space-y-6 before:absolute before:left-2 before:top-2.5 before:bottom-2.5 before:w-0.5 before:bg-slate-200">
                                @forelse($registration->logs as $log)
                                @php $isLatest = $loop->first; @endphp
                                <div class="relative group">
                                    <!-- Timeline Dot -->
                                    <div class="absolute -left-[23px] top-0.5 w-4 h-4 rounded-full flex items-center justify-center transition-all duration-300 {{ $isLatest ? 'bg-emerald-600 ring-4 ring-emerald-100 shadow-sm' : 'bg-slate-300 ring-2 ring-white' }}">
                                        @if($isLatest)
                                            <div class="w-1.5 h-1.5 bg-white rounded-full"></div>
                                        @endif
                                    </div>

                                    <!-- Content -->
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
        </div>

        <!-- MODAL PREVIEW DOKUMEN (In-Browser Live Viewer) -->
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
                        <!-- Tombol Unduh Opsional -->
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

    <!-- Script Review Dokumen Yudisium (Tahap 2B) -->
    <script>
        function documentReview(docId, reviewUrl) {
            return {
                reviewing: false,
                saving: false,
                reviewStatus: '',
                reviewNotes: '',
                reviewError: '',
                reviewSuccess: '',
                openForm() {
                    this.reviewing = true;
                    this.reviewError = '';
                    this.reviewSuccess = '';
                },
                cancel() {
                    this.reviewing = false;
                    this.saving = false;
                    this.reviewStatus = '';
                    this.reviewNotes = '';
                    this.reviewError = '';
                    this.reviewSuccess = '';
                },
                submitReview(event) {
                    if (!this.reviewStatus) {
                        this.reviewError = 'Pilih status review terlebih dahulu.';
                        return;
                    }
                
                    this.saving = true;
                    this.reviewError = '';
                    this.reviewSuccess = '';

                    const tokenMeta = document.querySelector('meta[name="csrf-token"]');
                    const token = tokenMeta ? tokenMeta.getAttribute('content') : '';

                    const params = new URLSearchParams({
                        status: this.reviewStatus,
                        notes: this.reviewNotes
                    });

                    fetch(reviewUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-CSRF-TOKEN': token,
                            'Accept': 'application/json'
                        },
                        body: params
                    })
                    .then(res => res.json())
                    .then(data => {
                        this.saving = false;
                        if (data.success) {
                            this.reviewing = false;
                            this.reviewStatus = '';
                            this.reviewNotes = '';
                            this.reviewSuccess = 'Review dokumen berhasil disimpan.';

                            const row = event.target.closest('tr');
                            if (row) {
                                const badge = row.querySelector('td:nth-child(3) span.badge');
                                if (badge && data.data) {
                                    badge.className = 'badge ' + data.data.status_badge;
                                    badge.textContent = data.data.status_label;
                                }
                                const noteDiv = row.querySelector('td:nth-child(3) div.mt-1');
                                if (data.data.status === 'rejected' && data.data.notes) {
                                    if (noteDiv) {
                                        noteDiv.textContent = 'Catatan: ' + data.data.notes;
                                        noteDiv.style.display = 'block';
                                    } else {
                                        const statusCell = row.querySelector('td:nth-child(3)');
                                        if (statusCell) {
                                            const newNote = document.createElement('div');
                                            newNote.className = 'mt-1 text-xs text-slate-500';
                                            newNote.textContent = 'Catatan: ' + data.data.notes;
                                            statusCell.appendChild(newNote);
                                        }
                                    }
                                } else if (noteDiv) {
                                    noteDiv.style.display = 'none';
                                }
                            }
                        } else {
                            this.reviewError = data.message || 'Terjadi kesalahan.';
                        }
                    })
                    .catch(err => {
                        this.saving = false;
                        this.reviewError = 'Terjadi kesalahan jaringan.';
                    });
                }
            };
        }
    </script>
</x-app-layout>
