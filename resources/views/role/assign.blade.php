<x-app-layout>
    <x-slot name="header">Penugasan Hak Akses (Pre-Assignment Pegawai)</x-slot>

    <div class="max-w-4xl mx-auto space-y-6">

        {{-- Breadcrumb / Back button --}}
        <div class="flex items-center justify-between">
            <a href="{{ route('role.index') }}" class="btn btn-secondary text-xs flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Kembali
            </a>
        </div>

        {{-- Flash Alerts --}}
        @if(session('success'))
            <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-xl flex items-start gap-3">
                <svg class="w-5 h-5 text-emerald-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div>
                    <h5 class="text-xs font-bold text-emerald-800">Berhasil!</h5>
                    <p class="text-xs text-emerald-700 mt-0.5">{{ session('success') }}</p>
                </div>
            </div>
        @endif

        @if(session('error'))
            <div class="p-4 bg-rose-50 border border-rose-200 rounded-xl flex items-start gap-3">
                <svg class="w-5 h-5 text-rose-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div>
                    <h5 class="text-xs font-bold text-rose-800">Gagal</h5>
                    <p class="text-xs text-rose-700 mt-0.5">{{ session('error') }}</p>
                </div>
            </div>
        @endif

        {{-- Form Assignment --}}
        <div class="campus-card p-6 bg-white shadow-sm border border-slate-200 rounded-2xl">
            <h4 class="text-sm font-bold text-slate-800 mb-5 flex items-center gap-2 pb-3 border-b border-slate-100">
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                </svg>
                Form Pre-Assignment Pegawai
            </h4>

            <form action="{{ route('user-role.store') }}" method="POST" class="space-y-5">
                @csrf

                {{-- 1. Input Username SSO Pegawai --}}
                <div class="form-group">
                    <label for="username" class="form-label block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Username SSO / NIP Pegawai <span class="text-rose-500">*</span>
                    </label>
                    <input type="text"
                           name="username"
                           id="username"
                           value="{{ old('username') }}"
                           class="form-control campus-input w-full text-sm @error('username') border-rose-400 @enderror"
                           placeholder="Username"
                           required
                           autofocus>
                    <p class="text-[11px] text-slate-500 mt-1">
                        Masukan username SSO pegawai tanpa <code>@telkomuniversity.ac.id</code>
                    </p>
                    @error('username')
                        <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- 2. Pilih Role (Bisa memilih lebih dari 1 role, misal Mahasiswa + Petugas) --}}
                <div class="form-group">
                    <label class="form-label block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                        Pilih Role <span class="text-rose-500">*</span>
                    </label>
                    <p class="text-[11px] text-slate-500 mb-3">
                        Pilih satu atau beberapa role untuk akun ini. Jika mahasiswa ditugaskan sebagai Petugas (102), Anda dapat mencentang Mahasiswa dan Petugas sekaligus.
                    </p>

                    @php
                        $oldRoleIds = old('role_ids', old('role_id') ? [(string)old('role_id')] : []);
                        if (!is_array($oldRoleIds)) $oldRoleIds = [$oldRoleIds];
                    @endphp

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        @foreach($roles as $role)
                            @php
                                $roleKey = $role->name ?? $role->role;
                                $meta = $roleMetadata[$roleKey] ?? null;
                                $displayLabel = ($meta ? "{$meta['label']} [{$meta['id']}]" : $roleKey);
                                $isChecked = in_array((string)$role->id, $oldRoleIds, true)
                                          || in_array($roleKey, $oldRoleIds, true);
                            @endphp
                            <label class="flex items-start gap-3 p-3 rounded-xl border border-slate-200 hover:border-slate-300 hover:bg-slate-50/50 cursor-pointer transition-colors duration-150">
                                <input type="checkbox"
                                       name="role_ids[]"
                                       value="{{ $roleKey }}"
                                       data-name="{{ strtolower($roleKey) }}"
                                       data-id="{{ $role->id }}"
                                       {{ $isChecked ? 'checked' : '' }}
                                       onchange="toggleUnitField()"
                                       class="role-checkbox mt-0.5 rounded text-[#DA2D3B] focus:ring-[#DA2D3B]/20 border-slate-300">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="text-xs font-bold text-slate-800">{{ $meta['label'] ?? $roleKey }}</span>
                                        @if(isset($meta['id']) && $meta['id'] !== '---')
                                            <span class="px-1.5 py-0.2 rounded text-[10px] font-mono font-semibold bg-slate-100 text-slate-600">
                                                {{ $meta['id'] }}
                                            </span>
                                        @endif
                                    </div>
                                    <p class="text-[11px] text-slate-400 mt-0.5 truncate">{{ $roleKey }}</p>
                                </div>
                            </label>
                        @endforeach
                    </div>

                    @error('role_ids')
                        <p class="text-xs text-rose-500 mt-1.5">{{ $message }}</p>
                    @enderror
                    @error('role_id')
                        <p class="text-xs text-rose-500 mt-1.5">{{ $message }}</p>
                    @enderror
                </div>

                {{-- 3. Wadah Unit Kerja & Jabatan (Muncul dinamis jika Pelaksana / Pemimpin) --}}
                <div id="unit_section" style="display: none;" class="p-4 bg-slate-50 rounded-xl border border-slate-200 space-y-4">
                    <div class="flex items-center gap-2 pb-2 border-b border-slate-200">
                        <svg class="w-4 h-4 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                        <h6 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Penugasan Unit Kerja & Jabatan</h6>
                    </div>

                    <div class="form-group">
                        <label for="unit_id" class="form-label block text-xs font-semibold text-slate-700 mb-1">
                            Pilih Unit Kerja <span class="text-rose-500">*</span>
                        </label>
                        <select name="unit_id" id="unit_id" class="form-control campus-select w-full text-sm @error('unit_id') border-rose-400 @enderror">
                            <option value="">-- Pilih Unit Kerja --</option>
                            @foreach($units as $unit)
                                <option value="{{ $unit->id }}" {{ old('unit_id') == $unit->id ? 'selected' : '' }}>
                                    {{ $unit->nama_unit ?? $unit->name }} @if($unit->code) ({{ $unit->code }}) @endif
                                </option>
                            @endforeach
                        </select>
                        @error('unit_id')
                            <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="position" class="form-label block text-xs font-semibold text-slate-700 mb-1">
                            Jabatan / Posisi <span class="text-rose-500">*</span>
                        </label>
                        <input type="text"
                               name="position"
                               id="position"
                               value="{{ old('position') }}"
                               class="form-control campus-input w-full text-sm @error('position') border-rose-400 @enderror"
                               placeholder="Staf Pelaksana Layanan / Ketua Program Studi">
                        @error('position')
                            <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Submit Action --}}
                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                    <a href="{{ route('role.index') }}" class="btn btn-secondary text-xs">Batal</a>
                    <button type="submit" class="btn btn-primary text-xs flex items-center gap-1.5 px-4 py-2">
                        Simpan Hak Akses
                    </button>
                </div>
            </form>
        </div>

    </div>

    {{-- Script Dynamic Unit Field Toggle --}}
    <script>
        function toggleUnitField() {
            const checkedBoxes = document.querySelectorAll('.role-checkbox:checked');
            const unitSection = document.getElementById('unit_section');
            const unitInput = document.getElementById('unit_id');
            const positionInput = document.getElementById('position');

            let isUnitRequired = false;
            checkedBoxes.forEach(function(box) {
                const name = (box.getAttribute('data-name') || '').toLowerCase();
                const id = box.getAttribute('data-id');
                if (name.includes('pelaksana') || name.includes('pemimpin') || id === '103' || id === '104') {
                    isUnitRequired = true;
                }
            });

            if (isUnitRequired) {
                unitSection.style.display = 'block';
                unitInput.setAttribute('required', 'required');
                positionInput.setAttribute('required', 'required');
            } else {
                unitSection.style.display = 'none';
                unitInput.removeAttribute('required');
                positionInput.removeAttribute('required');
                unitInput.value = '';
                positionInput.value = '';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            toggleUnitField();
        });
    </script>
</x-app-layout>
