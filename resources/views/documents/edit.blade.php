<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Edit Dokumen</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">

                @if ($errors->any())
                    <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                        <p class="font-semibold text-red-700 mb-2">Terdapat kesalahan:</p>
                        <ul class="list-disc list-inside text-red-600 text-sm">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <!-- Info file saat ini -->
                <div class="mb-6 p-4 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">File saat ini</p>
                        <p class="text-sm text-slate-800 font-bold truncate max-w-md">{{ $document->original_file_name ?? $document->file_name }}</p>
                        <p class="text-xs text-slate-400 mt-0.5">{{ $document->file_size_formatted }} &bull; {{ strtoupper($document->file_type) }}</p>
                    </div>
                    <a href="{{ route('documents.download', $document) }}"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-slate-700 bg-white border border-slate-200 hover:bg-slate-50 rounded-lg shadow-sm transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Unduh
                    </a>
                </div>

                <form action="{{ route('documents.update', $document) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    @if(request('redirect_to'))
                        <input type="hidden" name="redirect_to" value="{{ request('redirect_to') }}">
                    @elseif($document->category)
                        <input type="hidden" name="redirect_to" value="{{ route('documents.folder', $document->category) }}">
                    @endif

                    <div class="mb-4">
                        <label class="campus-label" for="document_category_id">
                            Folder / Kategori <span class="text-red-500">*</span>
                        </label>
                        <select id="document_category_id" name="document_category_id"
                                class="campus-select w-full @error('document_category_id') border-red-500 @enderror" required>
                            <option value="">- Pilih Folder -</option>
                            @foreach ($kategoriList as $kat)
                                <option value="{{ $kat->id }}"
                                    {{ old('document_category_id', $document->document_category_id) == $kat->id ? 'selected' : '' }}>
                                    {{ $kat->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('document_category_id')
                            <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="campus-label" for="title">
                            Judul Dokumen <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="title" name="title"
                               value="{{ old('title', $document->title) }}"
                               required
                               class="campus-input w-full @error('title') border-red-500 @enderror">
                        @error('title')
                            <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="campus-label" for="abbreviation">
                            Singkatan / Keyword <span class="text-gray-400 font-normal">(opsional)</span>
                        </label>
                        <input type="text" id="abbreviation" name="abbreviation"
                               value="{{ old('abbreviation', $document->abbreviation) }}"
                               placeholder="Contoh: SK, SE, MoM"
                               style="text-transform:uppercase"
                               class="campus-input w-full @error('abbreviation') border-red-500 @enderror">
                        @error('abbreviation')
                            <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="campus-label" for="description">
                            Deskripsi <span class="text-gray-400 font-normal">(opsional)</span>
                        </label>
                        <textarea id="description" name="description" rows="3"
                                  placeholder="Deskripsi singkat dokumen..."
                                  class="campus-input w-full @error('description') border-red-500 @enderror">{{ old('description', $document->description) }}</textarea>
                        @error('description')
                            <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="campus-label" for="visibility">
                            Visibilitas <span class="text-red-500">*</span>
                        </label>
                        <select id="visibility" name="visibility"
                                class="campus-select w-full @error('visibility') border-red-500 @enderror" required>
                            <option value="publik" {{ old('visibility', $document->visibility) === 'publik' ? 'selected' : '' }}>
                                Publik dan Mahasiswa
                            </option>
                            <option value="internal" {{ old('visibility', $document->visibility) === 'internal' ? 'selected' : '' }}>
                                Pegawai dan Dosen
                            </option>
                        </select>
                        @error('visibility')
                            <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-6">
                        <label class="campus-label" for="file">
                            Ganti File <span class="text-gray-400 font-normal">(opsional)</span>
                        </label>
                        <input type="file" id="file" name="file"
                               accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar"
                               class="w-full text-sm text-gray-500 border border-gray-300 rounded-lg p-2 focus:outline-none">
                        <p class="text-xs text-gray-400 mt-1">Kosongkan jika tidak ingin mengganti file. Maks: 15MB (PDF, Word, Excel, PPT, ZIP, RAR).</p>
                        @error('file')
                            <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                        @if($document->category)
                            <a href="{{ route('documents.folder', $document->category) }}"
                               class="btn btn-secondary">Batal</a>
                        @else
                            <a href="{{ route('documents.show', $document) }}"
                               class="btn btn-secondary">Batal</a>
                        @endif
                        <button type="submit" class="btn btn-primary">
                            Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
