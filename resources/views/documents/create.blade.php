<x-app-layout>
    <x-slot name="header">Upload Dokumen</x-slot>

    <div class="max-w-4xl mx-auto"
         x-data="uploadForm({
            maxSize: 15 * 1024 * 1024,
            allowed: ['pdf','doc','docx','xls','xlsx','ppt','pptx','zip','rar'],
            folders: @js($folderOptions),
            category: @js((string) old('document_category_id', $selectedCategory ?? '')),
            visibility: @js(old('visibility', 'publik')),
         })">

        {{-- Breadcrumb sederhana --}}
        <nav class="flex items-center gap-1.5 text-sm text-slate-500 mb-3" aria-label="Breadcrumb">
            <a href="{{ route('documents.index') }}" class="hover:text-[#DA2D3B] font-medium">Dokumen</a>
            <span class="text-slate-300">/</span>
            <span class="text-slate-800 font-semibold" x-text="categoryPath || 'Upload'"></span>
        </nav>

        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 tracking-tight">Upload Dokumen</h2>
                <p class="text-sm text-slate-500 mt-1">Unggah satu atau beberapa file sekaligus ke folder pilihan Anda.</p>
            </div>
            <div>
                <a href="{{ route('documents.import.create') }}" class="btn btn-secondary inline-flex items-center gap-1.5 text-xs">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Import Massal (Excel)
                </a>
            </div>
        </div>

        @include('partials.flash')

        @if ($errors->any())
            <div class="mb-4 p-4 bg-rose-50 border border-rose-200 rounded-2xl">
                <p class="font-semibold text-rose-700 text-sm mb-1">Terdapat kesalahan:</p>
                <ul class="list-disc list-inside text-rose-600 text-sm">
                    @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('documents.store') }}" method="POST" enctype="multipart/form-data"
              @submit="onSubmit($event)" class="bg-white rounded-2xl border border-slate-200/60 shadow-sm p-6">
            @csrf
            @if(!empty($selectedCategory))
                <input type="hidden" name="from_folder" value="1">
                <input type="hidden" name="redirect_to" value="{{ route('documents.folder', $selectedCategory) }}">
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-5">
                {{-- Folder (pohon dengan pencarian dan indentasi berjenjang) --}}
                <div>
                    <label class="campus-label" for="folder-picker-button">Folder / Kategori <span class="text-red-500">*</span></label>
                    <div x-data="{ q: '', open: false }" class="relative" @keydown.escape="open = false" @click.outside="open = false">
                        <input type="hidden" name="document_category_id" :value="category">
                        <button id="folder-picker-button" type="button" @click="open = !open"
                                :class="errors.category ? 'border-red-500 ring-1 ring-red-500' : ''"
                                class="campus-input w-full text-left flex items-center justify-between"
                                aria-haspopup="listbox" :aria-expanded="open">
                            <span class="truncate" :class="categoryPath ? 'text-slate-800' : 'text-slate-400'" x-text="categoryPath || '- Pilih Folder -'"></span>
                            <svg class="w-4 h-4 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="open" x-cloak class="absolute z-30 mt-1 w-full bg-white rounded-xl border border-slate-200 shadow-lg">
                            <div class="p-2 border-b border-slate-100">
                                <input type="text" x-model="q" x-ref="s" x-effect="open && $nextTick(() => $refs.s.focus())" placeholder="Cari folder..." class="campus-input w-full">
                            </div>
                            <ul class="max-h-64 overflow-y-auto py-1" role="listbox">
                                <template x-for="f in filteredFolders(q)" :key="f.id">
                                    <li>
                                        <button type="button" role="option" @click="category = String(f.id); open = false; q = ''"
                                                class="w-full text-left px-3 py-1.5 text-sm hover:bg-slate-50 flex items-center gap-1.5"
                                                :class="String(f.id) === String(category) ? 'text-[#DA2D3B] font-semibold' : 'text-slate-700'"
                                                :style="q ? '' : `padding-left:${0.75 + Math.min(f.depth, 8) * 0.9}rem`">
                                            <span x-show="!q && f.depth" class="text-slate-300">↳</span>
                                            <span class="truncate" x-text="q ? f.path : f.name"></span>
                                        </button>
                                    </li>
                                </template>
                                <li x-show="filteredFolders(q).length === 0" class="px-3 py-2 text-xs text-slate-400">Folder tidak ditemukan.</li>
                            </ul>
                        </div>
                    </div>
                    <p x-show="categoryPath" x-cloak class="text-xs text-slate-500 mt-1.5">Lokasi: <span class="font-semibold text-slate-700" x-text="categoryPath"></span></p>
                    <p x-show="errors.category" x-cloak class="text-xs text-rose-600 mt-1 font-medium">Folder wajib dipilih.</p>
                    @error('document_category_id')<p class="text-xs text-rose-600 mt-1 font-medium">{{ $message }}</p>@enderror
                </div>

                {{-- Visibilitas --}}
                <div>
                    <label class="campus-label" for="visibility">Visibilitas <span class="text-red-500">*</span></label>
                    <select name="visibility" id="visibility" x-model="visibility"
                            :class="errors.visibility ? 'border-red-500 ring-1 ring-red-500' : ''"
                            class="campus-input w-full">
                        <option value="">Pilih Visibilitas</option>
                        @foreach(config('documents.visibility', []) as $vKey => $vMeta)
                            <option value="{{ $vKey }}">{{ $vMeta['label'] }}</option>
                        @endforeach
                    </select>
                    <p x-show="errors.visibility" x-cloak class="text-xs text-rose-600 mt-1 font-medium">Visibilitas wajib dipilih.</p>
                    @error('visibility')<p class="text-xs text-rose-600 mt-1 font-medium">{{ $message }}</p>@enderror
                </div>
            </div>

            {{-- Dropzone --}}
            <label class="campus-label">File Dokumen <span class="text-red-500">*</span>
                <span class="text-slate-400 font-normal">(dapat upload beberapa sekaligus)</span></label>

            <div @dragover.prevent="!submitting && (dragging = true)"
                 @dragleave.prevent="dragging = false"
                 @drop.prevent="dragging = false; !submitting && addFiles($event.dataTransfer.files)"
                 @click="!submitting && $refs.input.click()"
                 @keydown.enter.prevent="$refs.input.click()" tabindex="0" role="button"
                 aria-label="Pilih file untuk diunggah"
                 :class="dragging ? 'border-[#DA2D3B] bg-[#DA2D3B]/5' : 'border-slate-300 hover:border-[#DA2D3B]/50 hover:bg-slate-50'"
                 class="border-2 border-dashed rounded-2xl p-8 text-center cursor-pointer transition-colors focus:outline-none focus:ring-2 focus:ring-[#DA2D3B]/30">
                <div class="mx-auto w-12 h-12 rounded-2xl bg-[#DA2D3B]/10 text-[#DA2D3B] flex items-center justify-center mb-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1M12 4v12m0-12l-4 4m4-4l4 4"/></svg>
                </div>
                <p class="text-sm font-semibold text-slate-700">Drag &amp; drop file ke sini, atau <span class="text-[#DA2D3B] underline">klik untuk pilih</span></p>
                <p class="text-xs text-slate-400 mt-1">PDF, Word, Excel, PowerPoint, ZIP, RAR - Maks. 15 MB per file</p>
                <input type="file" name="files[]" multiple x-ref="input" class="hidden"
                       accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar"
                       @change="addFiles($event.target.files)">
            </div>
            <p x-show="errors.files" x-cloak class="text-xs text-rose-600 mt-2 font-medium">Pilih minimal satu file dokumen untuk diunggah.</p>

            {{-- File ditolak --}}
            <div x-show="rejected.length" x-cloak class="mt-3 p-3 rounded-xl bg-rose-50 border border-rose-200">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold text-rose-700" x-text="rejected.length + ' file ditolak'"></p>
                    <button type="button" @click="rejected = []" class="text-xs text-rose-500 hover:text-rose-700">Tutup</button>
                </div>
                <ul class="mt-1 space-y-0.5">
                    <template x-for="r in rejected" :key="r.name + r.reason">
                        <li class="text-xs text-rose-600"><span class="font-medium" x-text="r.name"></span> - <span x-text="r.reason"></span></li>
                    </template>
                </ul>
            </div>

            {{-- Ringkasan + daftar file --}}
            <div x-show="items.length" x-cloak class="mt-5">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="text-sm font-semibold text-slate-700">
                        <span x-text="items.length"></span> file siap diunggah
                        <span class="text-slate-400 font-normal">- total <span x-text="formatSize(totalSize)"></span></span>
                    </h4>
                    <button type="button" @click="clearAll()" :disabled="submitting" class="text-xs text-slate-500 hover:text-rose-600">Kosongkan</button>
                </div>

                <div class="space-y-3">
                    <template x-for="(it, idx) in items" :key="it.uid">
                        <div class="border border-slate-200 rounded-2xl p-3.5 bg-slate-50/50">
                            <div class="flex items-center gap-3">
                                <div :class="meta(it.ext).box" class="w-11 h-11 rounded-xl flex items-center justify-center text-[10px] font-extrabold tracking-wide flex-shrink-0">
                                    <span x-text="meta(it.ext).label"></span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-slate-800 truncate" :title="it.file.name" x-text="it.file.name"></p>
                                    <p class="text-xs text-slate-400" x-text="formatSize(it.file.size)"></p>
                                </div>
                                <button type="button" @click="it.open = !it.open" class="text-xs font-medium text-slate-500 hover:text-[#DA2D3B]"
                                        x-text="it.open ? 'Tutup detail' : 'Edit detail'"></button>
                                <button type="button" @click="remove(it.uid)" :disabled="submitting"
                                        class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50"
                                        aria-label="Hapus file dari daftar" title="Hapus">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>

                            {{-- Input SELALU dirender (bukan x-if) agar ikut terkirim; hanya disembunyikan saat collapsed --}}
                            <div x-show="it.open" x-cloak class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-3 pt-3 border-t border-slate-200/60">
                                <div class="sm:col-span-2">
                                    <label class="block text-xs text-slate-500 mb-1">Judul Tampilan</label>
                                    <input type="text" :name="`judul[${idx}]`" x-model="it.judul" class="campus-input w-full" placeholder="Judul dokumen">
                                </div>
                                <div>
                                    <label class="block text-xs text-slate-500 mb-1">Singkatan <span class="text-slate-400">(keyword)</span></label>
                                    <input type="text" :name="`singkatan[${idx}]`" x-model="it.singkatan" @input="it.singkatan = it.singkatan.toUpperCase()" class="campus-input w-full uppercase" placeholder="Contoh: SUKET">
                                </div>
                                <div class="sm:col-span-3">
                                    <label class="block text-xs text-slate-500 mb-1">Deskripsi (opsional)</label>
                                    <input type="text" :name="`deskripsi[${idx}]`" x-model="it.deskripsi" class="campus-input w-full" placeholder="Deskripsi singkat...">
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 mt-6 pt-5 border-t border-slate-100">
                <a href="{{ !empty($selectedCategory) ? route('documents.folder', $selectedCategory) : route('documents.index') }}" class="btn btn-secondary">Batal</a>
                <button type="submit" :disabled="submitting || items.length === 0" class="btn btn-primary inline-flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg x-show="submitting" x-cloak class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" class="opacity-25"/><path fill="currentColor" class="opacity-75" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                    <span x-text="submitting ? `Mengunggah ${items.length} dokumen...` : 'Upload Dokumen'"></span>
                </button>
            </div>
        </form>
    </div>

    <script>
    function uploadForm(cfg) {
        const ICONS = {
            pdf:  ['bg-red-50 text-red-600',     'PDF'],
            doc:  ['bg-blue-50 text-blue-600',    'DOC'],
            docx: ['bg-blue-50 text-blue-600',    'DOC'],
            xls:  ['bg-emerald-50 text-emerald-600', 'XLS'],
            xlsx: ['bg-emerald-50 text-emerald-600', 'XLS'],
            ppt:  ['bg-orange-50 text-orange-600',  'PPT'],
            pptx: ['bg-orange-50 text-orange-600',  'PPT'],
            zip:  ['bg-amber-50 text-amber-600',   'ZIP'],
            rar:  ['bg-amber-50 text-amber-600',   'RAR'],
        };

        return {
            items: [],
            rejected: [],
            dragging: false,
            submitting: false,
            errors: {},
            category: cfg.category,
            visibility: cfg.visibility,

            get totalSize() {
                return this.items.reduce((s, i) => s + i.file.size, 0);
            },

            get categoryPath() {
                const f = cfg.folders.find(x => String(x.id) === String(this.category));
                return f ? f.path : '';
            },

            filteredFolders(q) {
                q = (q || '').trim().toLowerCase();
                return q ? cfg.folders.filter(f => f.path.toLowerCase().includes(q)).slice(0, 50) : cfg.folders;
            },

            meta(ext) {
                const m = ICONS[ext] || ['bg-slate-100 text-slate-500', (ext || '?').slice(0, 3).toUpperCase()];
                return { box: m[0], label: m[1] };
            },

            formatSize(b) {
                return b >= 1048576 ? (b / 1048576).toFixed(2) + ' MB' : b >= 1024 ? (b / 1024).toFixed(1) + ' KB' : b + ' B';
            },

            humanize(name) {
                return name.replace(/\.[^/.]+$/, '').replace(/[-_]+/g, ' ').replace(/\s+/g, ' ').trim().replace(/\b\w/g, c => c.toUpperCase());
            },

            addFiles(list) {
                Array.from(list).forEach(file => {
                    const ext = (file.name.split('.').pop() || '').toLowerCase();
                    if (!cfg.allowed.includes(ext)) return this.reject(file, 'Tipe file tidak diizinkan');
                    if (file.size > cfg.maxSize)   return this.reject(file, 'Melebihi 15 MB');
                    if (this.items.some(i => i.file.name === file.name && i.file.size === file.size)) return this.reject(file, 'Sudah ada di daftar');
                    this.items.push({
                        uid: crypto.randomUUID(),
                        file,
                        ext,
                        open: false,
                        judul: this.humanize(file.name),
                        singkatan: '',
                        deskripsi: ''
                    });
                });
                this.syncInput();
                this.errors.files = false;
            },

            reject(file, reason) {
                this.rejected.push({ name: file.name, reason });
            },

            remove(uid) {
                this.items = this.items.filter(i => i.uid !== uid);
                this.syncInput();
            },

            clearAll() {
                this.items = [];
                this.syncInput();
            },

            syncInput() {
                const dt = new DataTransfer();
                this.items.forEach(i => dt.items.add(i.file));
                this.$refs.input.files = dt.files;
            },

            onSubmit(e) {
                if (this.submitting) return e.preventDefault();
                this.errors = {
                    category: !this.category,
                    visibility: !this.visibility,
                    files: this.items.length === 0
                };
                if (Object.values(this.errors).some(Boolean)) return e.preventDefault();
                this.submitting = true;
            },
        };
    }
    </script>
</x-app-layout>
