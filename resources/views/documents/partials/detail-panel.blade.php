{{--
    Partial: detail-panel.blade.php
    Alpine parent state: showDetail, detailItem{ type, id, ... }, kategoriList (passed from view)
--}}

@php
    $globalPinnedCount = \App\Models\DocumentCategory::where('is_pinned', true)->count();
@endphp

{{-- Overlay --}}
<div x-show="showDetail"
     @click="showDetail = false"
     class="fixed inset-0 z-40 bg-black/20 backdrop-blur-[2px]"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     style="display:none;"
     aria-hidden="true">
</div>

{{-- Side panel --}}
<div x-show="showDetail"
     @keydown.escape.window="showDetail = false"
     class="fixed top-0 right-0 h-full w-full max-w-md z-50 bg-white shadow-2xl border-l border-slate-200 flex flex-col"
     x-transition:enter="transition ease-out duration-250"
     x-transition:enter-start="translate-x-full"
     x-transition:enter-end="translate-x-0"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="translate-x-0"
     x-transition:leave-end="translate-x-full"
     style="display:none;"
     role="dialog"
     aria-modal="true"
     aria-labelledby="detail-panel-title">

    {{-- Header --}}
    <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100 flex-shrink-0">
        <div class="flex items-center gap-2.5">
            {{-- Icon berdasarkan tipe --}}
            <template x-if="detailItem.type === 'folder'">
                <svg class="w-5 h-5 text-[#DA2D3B]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                </svg>
            </template>
            <template x-if="detailItem.type === 'doc'">
                <svg class="w-5 h-5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </template>
            <h2 id="detail-panel-title" class="text-base font-bold text-slate-800 truncate max-w-[240px]"
                x-text="detailItem.name || 'Detail'"></h2>
        </div>
        <button type="button"
                @click="showDetail = false"
                class="p-2 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-all"
                aria-label="Tutup panel detail">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    {{-- Content --}}
    <div class="flex-1 overflow-y-auto">

        {{-- ===== DETAIL FOLDER ===== --}}
        <template x-if="detailItem.type === 'folder'">
            <div>
                @can('document_category_update')
                <form :action="detailItem.updateUrl" method="POST" class="p-5 space-y-4" @submit="showDetail = false">
                    @csrf
                    <input type="hidden" name="_method" value="PUT">
                    <input type="hidden" name="redirect_to" value="{{ url()->full() }}">

                    <div>
                        <label class="campus-label">Nama Folder <span class="text-red-500">*</span></label>
                        <input type="text" name="name" x-model="detailItem.name"
                               required class="campus-input w-full"
                               :aria-label="'Nama folder ' + detailItem.name">
                    </div>

                    <div>
                        <label class="campus-label">Deskripsi</label>
                        <textarea name="description" rows="3"
                                  class="campus-input w-full"
                                  x-model="detailItem.description"
                                  placeholder="Deskripsi singkat folder (opsional)"></textarea>
                    </div>

                    <div>
                        <label class="campus-label">Visibilitas <span class="text-red-500">*</span></label>
                        <select name="visibility" class="campus-select w-full" x-model="detailItem.visibility" required>
                            <option value="publik">Publik dan Mahasiswa</option>
                            <option value="internal">Pegawai dan Dosen</option>
                        </select>
                    </div>

                    {{-- Toggle Pin --}}
                    @php $pinnedLimitReached = $globalPinnedCount >= 5; @endphp
                    <label class="flex items-center justify-between gap-3 py-2.5 border-t border-slate-100"
                           :class="{{ $pinnedLimitReached ? 'true' : 'false' }} && !detailItem.isPinned ? 'opacity-60 cursor-not-allowed' : 'cursor-pointer'"
                           :title="{{ $pinnedLimitReached ? 'true' : 'false' }} && !detailItem.isPinned ? 'Maksimal 5 folder yang bisa disematkan. Lepas sematan folder lain terlebih dahulu.' : ''">
                        <span class="text-sm font-medium text-slate-700">
                            Sematkan di atas
                            <span x-show="{{ $pinnedLimitReached ? 'true' : 'false' }} && !detailItem.isPinned" class="block text-xs text-amber-600 font-normal">
                                Maksimal 5 folder yang bisa disematkan.
                            </span>
                        </span>
                        <div class="relative">
                            <input type="hidden" name="is_pinned" value="0">
                            <input type="checkbox" name="is_pinned" value="1"
                                   class="sr-only peer"
                                   x-model="detailItem.isPinned"
                                   :disabled="{{ $pinnedLimitReached ? 'true' : 'false' }} && !detailItem.isPinned"
                                   id="detail-toggle-pinned">
                            <div class="w-10 h-5 bg-slate-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-[#DA2D3B]/20 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-[#DA2D3B]"></div>
                        </div>
                    </label>

                    {{-- Toggle Show When Empty --}}
                    <label class="flex items-center justify-between gap-3 py-2.5 cursor-pointer border-t border-slate-100">
                        <span class="text-sm font-medium text-slate-700">
                            Tampilkan di Portal meski kosong
                            <span class="block text-xs text-slate-400 font-normal">Folder tanpa dokumen tetap muncul di Portal Dokumen.</span>
                        </span>
                        <div class="relative">
                            <input type="hidden" name="show_when_empty" value="0">
                            <input type="checkbox" name="show_when_empty" value="1"
                                   class="sr-only peer"
                                   x-model="detailItem.showWhenEmpty"
                                   id="detail-toggle-show-empty">
                            <div class="w-10 h-5 bg-slate-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-[#DA2D3B]/20 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-[#DA2D3B]"></div>
                        </div>
                    </label>

                    <div class="pt-3 border-t border-slate-100">
                        <button type="submit" class="btn btn-primary w-full">Simpan Perubahan</button>
                    </div>
                </form>
                @else
                <div class="p-5">
                    <p class="text-sm text-slate-500">Anda tidak memiliki izin mengedit folder.</p>
                </div>
                @endcan
            </div>
        </template>

        {{-- ===== DETAIL DOKUMEN ===== --}}
        <template x-if="detailItem.type === 'doc'">
            <div>
                {{-- Metadata read-only --}}
                <div class="px-5 pt-4 pb-3 grid grid-cols-2 gap-3 border-b border-slate-100">
                    <div>
                        <p class="text-xs text-slate-400 font-medium uppercase tracking-wide mb-0.5">Tipe File</p>
                        <p class="text-sm font-semibold text-slate-700 uppercase" x-text="detailItem.fileType || '—'"></p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400 font-medium uppercase tracking-wide mb-0.5">Ukuran</p>
                        <p class="text-sm font-semibold text-slate-700" x-text="detailItem.fileSize || '—'"></p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400 font-medium uppercase tracking-wide mb-0.5">Tanggal Upload</p>
                        <p class="text-sm font-semibold text-slate-700" x-text="detailItem.createdAt || '—'"></p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400 font-medium uppercase tracking-wide mb-0.5">Diunduh</p>
                        <p class="text-sm font-semibold text-slate-700" x-text="(detailItem.downloadCount ?? 0) + 'x'"></p>
                    </div>
                </div>

                {{-- Form edit --}}
                @can('document_update')
                <form :action="detailItem.updateUrl" method="POST" class="p-5 space-y-4" @submit="showDetail = false">
                    @csrf
                    <input type="hidden" name="_method" value="PUT">
                    <input type="hidden" name="redirect_to" value="{{ url()->full() }}">

                    <div>
                        <label class="campus-label">Judul Tampilan <span class="text-red-500">*</span></label>
                        <input type="text" name="title" x-model="detailItem.name"
                               required class="campus-input w-full">
                    </div>

                    <div>
                        <label class="campus-label">Singkatan / Keyword</label>
                        <input type="text" name="abbreviation" x-model="detailItem.abbreviation"
                               class="campus-input w-full"
                               placeholder="Contoh: SK, SE, MoM">
                    </div>

                    <div>
                        <label class="campus-label">Deskripsi</label>
                        <textarea name="description" rows="3"
                                  class="campus-input w-full"
                                  x-model="detailItem.description"
                                  placeholder="Deskripsi singkat dokumen (opsional)"></textarea>
                    </div>

                    <div>
                        <label class="campus-label">Visibilitas <span class="text-red-500">*</span></label>
                        <select name="visibility" class="campus-select w-full" x-model="detailItem.visibility" required>
                            <option value="publik">Publik dan Mahasiswa</option>
                            <option value="internal">Pegawai dan Dosen</option>
                        </select>
                    </div>

                    <div>
                        <label class="campus-label">Folder / Kategori <span class="text-red-500">*</span></label>
                        <select name="document_category_id" class="campus-select w-full" x-model="detailItem.categoryId" required>
                            @foreach($kategoriList ?? [] as $kat)
                                <option value="{{ $kat->id }}">{{ $kat->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex gap-2">
                        <button type="submit" class="btn btn-primary flex-1">Simpan Perubahan</button>
                        <a :href="detailItem.showUrl"
                           class="btn btn-secondary flex-shrink-0 inline-flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                            </svg>
                            Buka
                        </a>
                    </div>
                </form>
                @else
                <div class="p-5 space-y-3">
                    <p class="text-sm text-slate-500">Anda tidak memiliki izin mengedit dokumen.</p>
                    <a :href="detailItem.showUrl" class="btn btn-secondary inline-flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        Lihat Detail Lengkap
                    </a>
                </div>
                @endcan
            </div>
        </template>
    </div>
</div>
