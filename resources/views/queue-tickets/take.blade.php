<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ambil Antrian Mahasiswa - {{ config('app.name', 'Dadu') }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .custom-glow {
            box-shadow: 0 20px 40px -15px rgba(218, 45, 59, 0.08), 0 0 0 1px rgba(218, 45, 59, 0.05);
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 font-sans min-h-screen flex items-center justify-center p-4 relative overflow-x-hidden selection:bg-[#DA2D3B]/20 selection:text-[#DA2D3B]">
    <!-- Decorative background elements -->
    <div class="absolute top-[-20%] left-[-10%] w-[300px] md:w-[500px] h-[300px] md:h-[500px] rounded-full bg-[#DA2D3B]/5 blur-[120px] pointer-events-none"></div>
    <div class="absolute bottom-[-20%] right-[-10%] w-[300px] md:w-[500px] h-[300px] md:h-[500px] rounded-full bg-slate-200/40 blur-[120px] pointer-events-none"></div>

    <div class="w-full max-w-lg z-10 my-6 transition-all duration-300">
        <!-- Logo and Title -->
        <div class="text-center mb-6 flex flex-col items-center">
            <img src="{{ asset('assets/images/queueSsc/Logo_SSC.svg') }}" alt="Logo" class="w-20 h-20 md:w-24 md:h-24 object-contain mb-3 drop-shadow-md">
            <h1 class="text-xl md:text-2xl font-black tracking-tight text-slate-900 uppercase">Student Service Center</h1>
            <p class="text-[10px] text-slate-400 mt-1 uppercase tracking-widest font-bold">Telkom University Surabaya</p>
        </div>

        <!-- Main Form Card -->
        <div class="bg-white border border-slate-200 rounded-3xl p-6 md:p-8 transition-all duration-300 custom-glow">
            <div class="mb-6 text-center border-b border-slate-100 pb-5">
                <h2 class="text-lg md:text-xl font-bold text-slate-900">Silahkan Lengkapi Data Diri</h2>
                <p class="text-xs text-slate-500 mt-1">Data digunakan untuk memanggil dan memproses kebutuhan layanan Anda</p>
            </div>

            @if(session('error'))
                <div class="mb-5 p-4 rounded-2xl bg-red-50 border border-red-200 text-red-700 text-xs flex items-center gap-3">
                    <svg class="w-5 h-5 shrink-0 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            <form id="queue-form" action="{{ route('queue.store') }}" method="POST" class="space-y-4">
                @csrf

                <!-- 1. NIM -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="nim" class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                            NIM <span class="text-[#DA2D3B]">*</span>
                        </label>
                        <span id="nim-status-badge" class="text-[11px] font-medium hidden"></span>
                    </div>
                    <div class="relative">
                        <input type="text"
                               name="nim"
                               id="nim"
                               value="{{ old('nim') }}"
                               inputmode="numeric"
                               pattern="[0-9]*"
                               placeholder="Contoh: 1201212112"
                               class="w-full px-4 py-3 rounded-xl border {{ $errors->has('nim') ? 'border-red-400 bg-red-50/20' : 'border-slate-200 bg-slate-50/50' }} text-slate-800 text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#DA2D3B]/20 focus:border-[#DA2D3B] transition-all font-mono pr-10"
                               required
                               autofocus>
                        <div id="nim-loader" class="hidden absolute right-3.5 top-3.5 text-[#DA2D3B]">
                            <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </div>
                    </div>
                    <p id="nim-hint" class="text-[11px] text-slate-400 mt-1">Masukkan NIM untuk mengisi nama & prodi otomatis</p>
                    @error('nim')
                        <p class="text-[11px] text-red-600 font-medium mt-1.5 flex items-center gap-1">
                            <span>•</span> {{ $message }}
                        </p>
                    @enderror
                </div>

                <!-- 2. Nama Lengkap -->
                <div>
                    <label for="name" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Nama Lengkap <span class="text-[#DA2D3B]">*</span>
                    </label>
                    <input type="text"
                           name="name"
                           id="name"
                           value="{{ old('name') }}"
                           placeholder="Contoh: Muhammad Fakhri"
                           class="w-full px-4 py-3 rounded-xl border {{ $errors->has('name') ? 'border-red-400 bg-red-50/20' : 'border-slate-200 bg-slate-50/50' }} text-slate-800 text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#DA2D3B]/20 focus:border-[#DA2D3B] transition-all"
                           required>
                    @error('name')
                        <p class="text-[11px] text-red-600 font-medium mt-1.5 flex items-center gap-1">
                            <span>•</span> {{ $message }}
                        </p>
                    @enderror
                </div>

                <!-- 3. Program Studi (Searchable Dropdown) -->
                <div>
                    <label for="study_program_search" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Program Studi <span class="text-[#DA2D3B]">*</span>
                    </label>
                    <div class="relative" id="prodi-dropdown-container">
                        <input type="text"
                               id="study_program_search"
                               autocomplete="off"
                               placeholder="Cari atau pilih Program Studi..."
                               value="{{ old('study_program') }}"
                               class="w-full px-4 py-3 rounded-xl border {{ $errors->has('study_program') ? 'border-red-400 bg-red-50/20' : 'border-slate-200 bg-slate-50/50' }} text-slate-800 text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#DA2D3B]/20 focus:border-[#DA2D3B] transition-all pr-10 cursor-pointer">
                        <input type="hidden" name="study_program" id="study_program" value="{{ old('study_program') }}" required>
                        
                        <div class="absolute right-3.5 top-3.5 text-slate-400 pointer-events-none transition-transform duration-200" id="prodi-chevron">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>

                        <!-- Options List -->
                        <div id="prodi-options-list" class="hidden absolute left-0 right-0 top-full mt-1.5 bg-white border border-slate-200 rounded-2xl shadow-xl max-h-56 overflow-y-auto z-50 divide-y divide-slate-100">
                            @foreach($studyPrograms as $prodi)
                                <div class="px-4 py-2.5 text-xs md:text-sm text-slate-700 hover:bg-red-50 hover:text-[#DA2D3B] cursor-pointer transition-colors prodi-option"
                                     data-value="{{ $prodi }}">
                                    {{ $prodi }}
                                </div>
                            @endforeach
                            <div id="prodi-no-results" class="hidden px-4 py-3 text-xs text-slate-400 text-center">
                                Program studi tidak ditemukan
                            </div>
                        </div>
                    </div>
                    @error('study_program')
                        <p class="text-[11px] text-red-600 font-medium mt-1.5 flex items-center gap-1">
                            <span>•</span> {{ $message }}
                        </p>
                    @enderror
                </div>

                <!-- 4. Layanan (8 Kategori) -->
                <div>
                    <label for="service_category" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Kebutuhan Layanan <span class="text-[#DA2D3B]">*</span>
                    </label>
                    <div class="relative">
                        <select name="service_category"
                                id="service_category"
                                class="w-full px-4 py-3 rounded-xl border {{ $errors->has('service_category') ? 'border-red-400 bg-red-50/20' : 'border-slate-200 bg-slate-50/50' }} text-slate-800 text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#DA2D3B]/20 focus:border-[#DA2D3B] transition-all appearance-none cursor-pointer pr-10"
                                required>
                            <option value="" disabled {{ old('service_category') ? '' : 'selected' }}>-- Pilih Kategori Layanan --</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat }}" {{ old('service_category') == $cat ? 'selected' : '' }}>
                                    {{ $cat }}
                                </option>
                            @endforeach
                        </select>
                        <div class="absolute right-3.5 top-3.5 text-slate-400 pointer-events-none">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                    </div>
                    @error('service_category')
                        <p class="text-[11px] text-red-600 font-medium mt-1.5 flex items-center gap-1">
                            <span>•</span> {{ $message }}
                        </p>
                    @enderror
                </div>

                <!-- 5. Catatan (Opsional) -->
                <div>
                    <div class="flex justify-between items-center mb-1.5">
                        <label for="notes" class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                            Catatan <span class="text-slate-400 font-normal text-[11px] lowercase">(opsional)</span>
                        </label>
                        <span id="char-counter" class="text-[10px] text-slate-400 font-mono">0/255</span>
                    </div>
                    <textarea name="notes"
                              id="notes"
                              rows="3"
                              maxlength="255"
                              placeholder="Tuliskan keterangan singkat terkait berkas atau keperluan Anda..."
                              class="w-full px-4 py-2.5 rounded-xl border {{ $errors->has('notes') ? 'border-red-400 bg-red-50/20' : 'border-slate-200 bg-slate-50/50' }} text-slate-800 text-xs md:text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#DA2D3B]/20 focus:border-[#DA2D3B] transition-all resize-none">{{ old('notes') }}</textarea>
                    @error('notes')
                        <p class="text-[11px] text-red-600 font-medium mt-1.5 flex items-center gap-1">
                            <span>•</span> {{ $message }}
                        </p>
                    @enderror
                </div>

                <!-- 6. Tombol Submit -->
                <div class="pt-2">
                    <button type="submit"
                            id="submit-btn"
                            class="w-full py-4 px-6 rounded-2xl bg-[#DA2D3B] hover:bg-[#c12531] text-white font-bold text-sm md:text-base tracking-wide transition-all duration-300 shadow-lg shadow-red-200/50 active:scale-[0.98] flex items-center justify-center gap-2 cursor-pointer">
                        <span id="btn-text">Ambil Nomor Antrian</span>
                        <svg id="btn-spinner" class="hidden w-5 h-5 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                    </button>
                    <p class="text-[10px] text-slate-400 text-center mt-3">
                        Dengan menekan tombol di atas, Anda akan mendapatkan 1 nomor tiket antrian aktif.
                    </p>
                </div>
            </form>
        </div>
    </div>

    <!-- Script Searchable Dropdown & Double Submit Prevention -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // --- Auto-fill Mahasiswa via NIM Lookup ---
            const nimInput = document.getElementById('nim');
            const nameInput = document.getElementById('name');
            const nimLoader = document.getElementById('nim-loader');
            const nimStatusBadge = document.getElementById('nim-status-badge');
            const nimHint = document.getElementById('nim-hint');
            let debounceTimer = null;
            let lastQueriedNim = '';

            function setBadge(type, message) {
                if (!nimStatusBadge) return;
                nimStatusBadge.classList.remove('hidden', 'text-emerald-600', 'text-amber-600', 'text-red-500');
                if (type === 'found') {
                    nimStatusBadge.classList.add('text-emerald-600');
                    nimStatusBadge.innerHTML = `<span class="inline-flex items-center gap-1"> ${message}</span>`;
                } else if (type === 'not_found') {
                    nimStatusBadge.classList.add('text-amber-600');
                    nimStatusBadge.innerHTML = `<span class="inline-flex items-center gap-1"> ${message}</span>`;
                } else if (type === 'error') {
                    nimStatusBadge.classList.add('text-red-500');
                    nimStatusBadge.innerHTML = `<span class="inline-flex items-center gap-1"> ${message}</span>`;
                }
            }

            function clearBadge() {
                if (nimStatusBadge) {
                    nimStatusBadge.classList.add('hidden');
                    nimStatusBadge.innerHTML = '';
                }
            }

            async function performNimLookup(nimVal) {
                if (nimVal.length < 5) {
                    clearBadge();
                    return;
                }

                if (nimVal === lastQueriedNim) return;
                lastQueriedNim = nimVal;

                if (nimLoader) nimLoader.classList.remove('hidden');

                try {
                    const response = await fetch(`{{ route('queue.lookup-student') }}?nim=${encodeURIComponent(nimVal)}`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    if (response.ok) {
                        const data = await response.json();
                        if (data.found) {
                            if (nameInput) {
                                nameInput.value = data.name;
                                nameInput.classList.add('bg-emerald-50/40', 'border-emerald-300');
                                setTimeout(() => nameInput.classList.remove('bg-emerald-50/40', 'border-emerald-300'), 1800);
                            }

                            if (data.study_program) {
                                if (hiddenInput) hiddenInput.value = data.study_program;
                                if (searchInput) {
                                    searchInput.value = data.study_program;
                                    searchInput.classList.add('bg-emerald-50/40', 'border-emerald-300');
                                    setTimeout(() => searchInput.classList.remove('bg-emerald-50/40', 'border-emerald-300'), 1800);
                                }
                            }

                            setBadge('found', 'Mahasiswa Terdaftar');
                            if (nimHint) nimHint.textContent = 'Data mahasiswa berhasil dimuat otomatis.';
                        }
                    } else if (response.status === 404) {
                        setBadge('not_fo    und', 'NIM belum ada di master (isi manual)');
                        if (nimHint) nimHint.textContent = 'NIM tidak ditemukan. Silakan ketik nama & prodi manual.';
                    } else {
                        clearBadge();
                    }
                } catch (err) {
                    console.error('Error lookup student:', err);
                    clearBadge();
                } finally {
                    if (nimLoader) nimLoader.classList.add('hidden');
                }
            }

            if (nimInput) {
                nimInput.addEventListener('input', function () {
                    const cleanVal = this.value.replace(/[^0-9]/g, '');
                    if (this.value !== cleanVal) {
                        this.value = cleanVal;
                    }

                    clearTimeout(debounceTimer);
                    if (cleanVal.length >= 8) {
                        debounceTimer = setTimeout(() => performNimLookup(cleanVal), 400);
                    } else {
                        clearBadge();
                        if (nimHint) nimHint.textContent = 'Masukkan NIM untuk mengisi nama & prodi otomatis';
                    }
                });

                nimInput.addEventListener('blur', function () {
                    const cleanVal = this.value.trim();
                    if (cleanVal.length >= 8) {
                        performNimLookup(cleanVal);
                    }
                });
            }

            // --- Searchable Prodi Dropdown ---
            const container = document.getElementById('prodi-dropdown-container');
            const searchInput = document.getElementById('study_program_search');
            const hiddenInput = document.getElementById('study_program');
            const optionsList = document.getElementById('prodi-options-list');
            const options = document.querySelectorAll('.prodi-option');
            const noResults = document.getElementById('prodi-no-results');
            const chevron = document.getElementById('prodi-chevron');

            function openDropdown() {
                optionsList.classList.remove('hidden');
                chevron.classList.add('rotate-180');
            }

            function closeDropdown() {
                optionsList.classList.add('hidden');
                chevron.classList.remove('rotate-180');
            }

            searchInput.addEventListener('focus', openDropdown);
            searchInput.addEventListener('click', openDropdown);

            searchInput.addEventListener('input', function () {
                openDropdown();
                const query = this.value.toLowerCase().trim();
                let hasMatch = false;

                options.forEach(opt => {
                    const text = opt.innerText.toLowerCase();
                    if (text.includes(query)) {
                        opt.classList.remove('hidden');
                        hasMatch = true;
                    } else {
                        opt.classList.add('hidden');
                    }
                });

                if (hasMatch) {
                    noResults.classList.add('hidden');
                } else {
                    noResults.classList.remove('hidden');
                }

                // If cleared, reset hidden input
                if (query === '') {
                    hiddenInput.value = '';
                }
            });

            options.forEach(opt => {
                opt.addEventListener('click', function () {
                    const val = this.getAttribute('data-value');
                    hiddenInput.value = val;
                    searchInput.value = val;
                    closeDropdown();
                });
            });

            // Close when clicked outside
            document.addEventListener('click', function (e) {
                if (!container.contains(e.target)) {
                    closeDropdown();
                    // Validate if typed text matches an option
                    const curVal = searchInput.value.trim();
                    let match = false;
                    options.forEach(opt => {
                        if (opt.getAttribute('data-value').toLowerCase() === curVal.toLowerCase()) {
                            hiddenInput.value = opt.getAttribute('data-value');
                            searchInput.value = opt.getAttribute('data-value');
                            match = true;
                        }
                    });
                    if (!match && !hiddenInput.value) {
                        searchInput.value = '';
                    }
                }
            });

            // --- Notes Character Counter ---
            const notesTextarea = document.getElementById('notes');
            const charCounter = document.getElementById('char-counter');

            function updateCounter() {
                if (notesTextarea && charCounter) {
                    charCounter.innerText = `${notesTextarea.value.length}/255`;
                }
            }

            if (notesTextarea) {
                notesTextarea.addEventListener('input', updateCounter);
                updateCounter();
            }

            // --- Prevent Double Submit ---
            const form = document.getElementById('queue-form');
            const submitBtn = document.getElementById('submit-btn');
            const btnText = document.getElementById('btn-text');
            const btnSpinner = document.getElementById('btn-spinner');

            form.addEventListener('submit', function () {
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-75', 'cursor-not-allowed');
                btnText.innerText = 'Memproses Tiket...';
                btnSpinner.classList.remove('hidden');
            });
        });
    </script>
</body>
</html>
