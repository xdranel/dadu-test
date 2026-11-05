<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Upload Dokumen</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">

            @if ($errors->any())
                <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                    <p class="font-semibold text-red-700 mb-2">Terdapat kesalahan:</p>
                    <ul class="list-disc list-inside text-red-600 text-sm">
                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('documents.store') }}" method="POST"
                  enctype="multipart/form-data" id="uploadForm">
                @csrf
                @if(!empty($selectedCategory))
                    <input type="hidden" name="from_folder" value="1">
                    <input type="hidden" name="redirect_to" value="{{ route('documents.folder', $selectedCategory) }}">
                @endif

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1" for="document_category_id">
                            Folder / Kategori <span class="text-red-500">*</span>
                        </label>
                        <select name="document_category_id" id="document_category_id"
                                class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-[#DA2D3B] focus:border-[#DA2D3B] @error('document_category_id') border-red-500 @enderror">
                            <option value="">- Pilih Kategori -</option>
                            @foreach ($kategoriList as $kat)
                                <option value="{{ $kat->id }}" {{ (old('document_category_id', $selectedCategory ?? null) == $kat->id) ? 'selected' : '' }}>
                                    {{ $kat->name }}
                                </option>
                            @endforeach
                        </select>
                        <p id="categoryError" class="text-xs text-red-600 mt-1 font-medium hidden">Folder / Kategori wajib dipilih.</p>
                        @error('document_category_id')
                            <p class="text-xs text-red-600 mt-1 font-medium">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1" for="visibility">
                            Visibilitas <span class="text-red-500">*</span>
                        </label>
                        <select name="visibility" id="visibility"
                                class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-[#DA2D3B] focus:border-[#DA2D3B] @error('visibility') border-red-500 @enderror">
                            <option value="">Pilih Visibilitas</option>
                            <option value="publik" {{ old('visibility', 'publik') === 'publik' ? 'selected' : '' }}>Publik dan Mahasiswa</option>
                            <option value="internal" {{ old('visibility') === 'internal' ? 'selected' : '' }}>Pegawai dan Dosen</option>
                        </select>
                        <p id="visibilityError" class="text-xs text-red-600 mt-1 font-medium hidden">Visibilitas wajib dipilih.</p>
                        @error('visibility')
                            <p class="text-xs text-red-600 mt-1 font-medium">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Area Drag & Drop -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        File Dokumen <span class="text-red-500">*</span>
                        <span class="text-gray-400 font-normal">(dapat upload beberapa sekaligus)</span>
                    </label>

                    <div id="dropZone"
                          class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center
                                 hover:border-indigo-400 hover:bg-indigo-50 transition-colors cursor-pointer"
                          ondragover="handleDragOver(event)"
                          ondragleave="handleDragLeave(event)"
                          ondrop="handleDrop(event)"
                          onclick="document.getElementById('fileInput').click()">

                        <p class="text-sm font-medium text-gray-600">
                            Drag & drop file ke sini, atau <span class="text-[#DA2D3B] underline">klik untuk pilih</span>
                        </p>
                        <p class="text-xs text-gray-400 mt-1">
                            PDF, Word, Excel, PowerPoint, ZIP, RAR - Maks. 15MB
                        </p>

                        <input type="file" id="fileInput" name="files[]"
                               multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar"
                               class="hidden" onchange="handleFileSelect(this.files)">
                    </div>
                    <p id="fileError" class="text-xs text-red-600 mt-2 font-medium hidden text-center">Pilih minimal satu file dokumen untuk diupload.</p>
                </div>

                <!-- Preview & Detail File -->
                <div id="fileList" class="hidden mb-6">
                    <h4 class="text-sm font-semibold text-gray-700 mb-3">
                        File yang akan diupload:
                    </h4>
                    <div id="fileItems" class="space-y-3"></div>
                </div>

                <div class="flex items-center justify-end gap-4">
                    <a href="{{ route('documents.index') }}"
                       class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900">Batal</a>
                    <button type="submit" id="submitBtn" disabled
                            class="px-6 py-2 bg-[#DA2D3B] text-white text-sm font-medium rounded-md
                                   hover:bg-[#b0222e] disabled:opacity-50 disabled:cursor-not-allowed
                                   focus:outline-none focus:ring-2 focus:ring-[#DA2D3B]">
                        Upload Dokumen
                    </button>
                </div>
            </form>
        </div>
    </div>

<script>
let selectedFiles = new DataTransfer();
let customValues = {}; // Store custom inputs keyed by file name

function handleDragOver(e) {
    e.preventDefault();
    document.getElementById('dropZone').classList.add('border-[#DA2D3B]', 'bg-indigo-50');
}

function handleDragLeave(e) {
    document.getElementById('dropZone').classList.remove('border-[#DA2D3B]', 'bg-indigo-50');
}

function handleDrop(e) {
    e.preventDefault();
    document.getElementById('dropZone').classList.remove('border-[#DA2D3B]', 'bg-indigo-50');
    addFiles(e.dataTransfer.files);
}

function handleFileSelect(files) {
    addFiles(files);
}

function addFiles(files) {
    saveCurrentInputs();

    Array.from(files).forEach(file => {
        const existing = Array.from(selectedFiles.files).map(f => f.name);
        if (!existing.includes(file.name)) {
            selectedFiles.items.add(file);
        }
    });

    document.getElementById('fileInput').files = selectedFiles.files;
    document.getElementById('fileError').classList.add('hidden');
    renderFileList();
}

function removeFile(index) {
    saveCurrentInputs();

    const dt = new DataTransfer();
    Array.from(selectedFiles.files).forEach((file, i) => {
        if (i !== index) dt.items.add(file);
    });
    selectedFiles = dt;
    document.getElementById('fileInput').files = selectedFiles.files;
    renderFileList();
}

function saveCurrentInputs() {
    const files = Array.from(selectedFiles.files);
    files.forEach((file, index) => {
        const judulInput = document.querySelector(`input[name="judul[${index}]"]`);
        const singkatanInput = document.querySelector(`input[name="singkatan[${index}]"]`);
        const deskripsiInput = document.querySelector(`input[name="deskripsi[${index}]"]`);
        if (judulInput || singkatanInput || deskripsiInput) {
            customValues[file.name] = {
                judul: judulInput ? judulInput.value : '',
                singkatan: singkatanInput ? singkatanInput.value : '',
                deskripsi: deskripsiInput ? deskripsiInput.value : ''
            };
        }
    });
}

function formatSize(bytes) {
    if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + ' MB';
    if (bytes >= 1024)    return (bytes / 1024).toFixed(2) + ' KB';
    return bytes + ' B';
}

function humanizeName(name) {
    return name
        .replace(/\.[^/.]+$/, '')
        .replace(/[-_]+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim()
        .replace(/\b\w/g, c => c.toUpperCase());
}

function renderFileList() {
    const files = Array.from(selectedFiles.files);
    const container = document.getElementById('fileItems');
    const fileList  = document.getElementById('fileList');
    const submitBtn = document.getElementById('submitBtn');

    if (files.length === 0) {
        fileList.classList.add('hidden');
        submitBtn.disabled = true;
        return;
    }

    fileList.classList.remove('hidden');
    submitBtn.disabled = false;

    container.innerHTML = files.map((file, index) => {
        const saved = customValues[file.name] || {};
        const judul = saved.judul !== undefined ? saved.judul : humanizeName(file.name);
        const singkatan = saved.singkatan || '';
        const deskripsi = saved.deskripsi || '';

        return `
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <div>
                        <p class="text-sm font-medium text-gray-800">${file.name}</p>
                        <p class="text-xs text-gray-400">${formatSize(file.size)}</p>
                    </div>
                </div>
                <button type="button" onclick="removeFile(${index})"
                        class="text-red-500 hover:text-red-700 text-sm font-medium">
                    Hapus
                </button>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                <div class="sm:col-span-2">
                    <label class="block text-xs text-gray-500 mb-1">Judul Tampilan</label>
                    <input type="text" name="judul[${index}]"
                           value="${judul.replace(/"/g, '&quot;')}"
                           placeholder="Judul dokumen"
                           class="w-full border-gray-300 rounded text-sm px-2 py-1">
                    <p class="text-[11px] text-gray-400 mt-1">Nama file asli: ${file.name}</p>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">
                        Singkatan <span class="text-gray-400">(keyword)</span>
                    </label>
                    <input type="text" name="singkatan[${index}]"
                           value="${singkatan.replace(/"/g, '&quot;')}"
                           placeholder="Contoh: SUKET"
                           style="text-transform:uppercase"
                           class="w-full border-gray-300 rounded text-sm px-2 py-1">
                </div>
                <div class="sm:col-span-3">
                    <label class="block text-xs text-gray-500 mb-1">Deskripsi (opsional)</label>
                    <input type="text" name="deskripsi[${index}]"
                           value="${deskripsi.replace(/"/g, '&quot;')}"
                           placeholder="Deskripsi singkat..."
                           class="w-full border-gray-300 rounded text-sm px-2 py-1">
                </div>
            </div>
        </div>
    `;
    }).join('');
}

// Client-side validation: cegah submit & pertahankan file jika field wajib belum diisi
document.getElementById('uploadForm').addEventListener('submit', function(e) {
    let hasError = false;

    const catSelect = document.getElementById('document_category_id');
    const catErr = document.getElementById('categoryError');
    const visSelect = document.getElementById('visibility');
    const visErr = document.getElementById('visibilityError');
    const fileErr = document.getElementById('fileError');

    // Validasi Kategori
    if (!catSelect.value) {
        catSelect.classList.add('border-red-500', 'ring-1', 'ring-red-500');
        catErr.classList.remove('hidden');
        hasError = true;
    } else {
        catSelect.classList.remove('border-red-500', 'ring-1', 'ring-red-500');
        catErr.classList.add('hidden');
    }

    // Validasi Visibilitas
    if (!visSelect.value) {
        visSelect.classList.add('border-red-500', 'ring-1', 'ring-red-500');
        visErr.classList.remove('hidden');
        hasError = true;
    } else {
        visSelect.classList.remove('border-red-500', 'ring-1', 'ring-red-500');
        visErr.classList.add('hidden');
    }

    // Validasi File
    if (selectedFiles.files.length === 0) {
        fileErr.classList.remove('hidden');
        hasError = true;
    } else {
        fileErr.classList.add('hidden');
    }

    if (hasError) {
        e.preventDefault();
        e.stopPropagation();

        // Fokus ke field pertama yang bermasalah
        if (!catSelect.value) {
            catSelect.focus();
        } else if (!visSelect.value) {
            visSelect.focus();
        }
        return false;
    }
});

// Hapus style error saat user memilih opsi
document.getElementById('document_category_id').addEventListener('change', function() {
    if (this.value) {
        this.classList.remove('border-red-500', 'ring-1', 'ring-red-500');
        document.getElementById('categoryError').classList.add('hidden');
    }
});

document.getElementById('visibility').addEventListener('change', function() {
    if (this.value) {
        this.classList.remove('border-red-500', 'ring-1', 'ring-red-500');
        document.getElementById('visibilityError').classList.add('hidden');
    }
});
</script>
            </div>
        </div>
    </div>
</x-app-layout>