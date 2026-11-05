<x-app-layout>
    <x-slot name="header">Edit Role: {{ $roleMeta['label'] ?? ucfirst($role->name) }} ({{ $roleMeta['id'] ?? '---' }})</x-slot>

    <div class="max-w-5xl mx-auto space-y-5">

        {{-- Back Button --}}
        <div>
            <a href="{{ route('role.index') }}"
               class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-slate-800 transition-colors">
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

        {{-- Tab Navigation --}}
        <div x-data="{ activeTab: (window.location.hash === '#tab-users' || new URLSearchParams(window.location.search).has('search') || new URLSearchParams(window.location.search).has('page')) ? 'users' : 'permissions' }">

            <div class="flex gap-1 border-b border-slate-200 mb-5">
                <button @click="activeTab = 'permissions'"
                        :class="activeTab === 'permissions'
                            ? 'border-b-2 border-[#DA2D3B] text-[#DA2D3B] font-semibold'
                            : 'text-slate-500 hover:text-slate-700'"
                        class="px-4 py-2.5 text-sm transition-colors flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                    Hak Akses
                </button>
                <button @click="activeTab = 'users'; window.location.hash = 'tab-users'"
                        :class="activeTab === 'users'
                            ? 'border-b-2 border-[#DA2D3B] text-[#DA2D3B] font-semibold'
                            : 'text-slate-500 hover:text-slate-700'"
                        class="px-4 py-2.5 text-sm transition-colors flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Kelola Pengguna
                    @if($isMahasiswa && $search === '')
                        <span class="inline-flex items-center justify-center px-1.5 h-5 rounded-full bg-slate-100 text-slate-500 text-[10px] font-medium" title="Gunakan pencarian untuk menampilkan data">
                            Cari
                        </span>
                    @else
                        <span class="inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full bg-slate-100 text-slate-600 text-[10px] font-bold">
                            {{ $roleUsers->total() }}
                        </span>
                    @endif
                </button>
            </div>

            {{-- PERMISSION MATRIX --}}
            <div x-show="activeTab === 'permissions'" x-transition>
                <form action="{{ route('role.update', $role->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="campus-card p-6 bg-white overflow-hidden">
                        <div class="mb-5 border-b border-slate-100 pb-4">
                            <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider">
                                Hak Akses Modul
                            </h3>
                            <p class="text-xs text-slate-400 mt-1">
                                Centang kotak pada baris modul dan kolom aksi untuk menetapkan izin akses.
                            </p>
                        </div>

                        {{-- Matrix Table (modul & kolom sama untuk SEMUA role; sumber: config/permission_modules.php) --}}
                        <div class="overflow-x-auto border border-slate-200 rounded-xl">
                            <table class="min-w-full divide-y divide-slate-200">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider w-2/5">
                                            Nama Modul
                                        </th>
                                        @foreach($actions as $actionKey => $actionLabel)
                                            <th scope="col" class="px-6 py-3.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider" title="{{ ucfirst($actionKey) }}">
                                                {{ $actionLabel }}
                                            </th>
                                        @endforeach
                                        <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                            Aksi Khusus
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-slate-100">
                                    @foreach($groups as $groupName)
                                        <tr class="bg-slate-50/70">
                                            <td colspan="{{ count($actions) + 2 }}" class="px-6 py-2 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                                                {{ $groupName }}
                                                @if($groupName === 'IT Panel')
                                                @endif
                                            </td>
                                        </tr>
                                        @foreach($modules as $moduleKey => $module)
                                            @continue($module['group'] !== $groupName)
                                            <tr class="hover:bg-slate-50 transition-colors duration-150">
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-slate-700">
                                                    {{ $module['label'] }}
                                                </td>
                                                @foreach($actions as $actionKey => $actionLabel)
                                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                                        @if(isset($module['actions'][$actionKey]))
                                                            @php
                                                                $permissionName = $module['actions'][$actionKey]['permission'];
                                                                $canManage = in_array($permissionName, $manageable, true);
                                                            @endphp
                                                            <label class="inline-flex items-center justify-center p-1.5 {{ $canManage ? 'cursor-pointer' : 'cursor-not-allowed opacity-60' }}"
                                                                   @unless($canManage) title="Di luar kewenangan Anda" @endunless>
                                                                <input type="checkbox" name="permissions[]" value="{{ $permissionName }}"
                                                                       {{ in_array($permissionName, $rolePermissions, true) ? 'checked' : '' }}
                                                                       {{ $canManage ? '' : 'disabled' }}
                                                                       class="w-4 h-4 text-[#DA2D3B] border-slate-300 rounded focus:ring-[#DA2D3B]/20 transition duration-150">
                                                            </label>
                                                        @else
                                                            <span class="text-xs text-slate-300 font-medium" title="Tidak relevan untuk modul ini">-</span>
                                                        @endif
                                                    </td>
                                                @endforeach
                                                <td class="px-6 py-4 text-xs text-slate-600">
                                                    @php $customActions = array_filter($module['actions'], fn ($a) => $a['custom']); @endphp
                                                    @forelse($customActions as $custom)
                                                        @php $canManage = in_array($custom['permission'], $manageable, true); @endphp
                                                        <label class="inline-flex items-center gap-1.5 mr-3 whitespace-nowrap {{ $canManage ? 'cursor-pointer' : 'cursor-not-allowed opacity-60' }}"
                                                               @unless($canManage) title="Di luar kewenangan Anda" @endunless>
                                                            <input type="checkbox" name="permissions[]" value="{{ $custom['permission'] }}"
                                                                   {{ in_array($custom['permission'], $rolePermissions, true) ? 'checked' : '' }}
                                                                   {{ $canManage ? '' : 'disabled' }}
                                                                   class="w-4 h-4 text-[#DA2D3B] border-slate-300 rounded focus:ring-[#DA2D3B]/20 transition duration-150">
                                                            <span>{{ $custom['label'] }}</span>
                                                        </label>
                                                    @empty
                                                        <span class="text-xs text-slate-300 font-medium">-</span>
                                                    @endforelse
                                                </td>
                                            </tr>
                                        @endforeach
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{-- Action Buttons --}}
                        <div class="flex items-center justify-end gap-3 mt-6 pt-4 border-t border-slate-100">
                            <a href="{{ route('role.index') }}" class="btn btn-secondary text-xs">Batal</a>
                            @can('role_update')
                            <button type="submit" class="btn btn-primary flex items-center gap-1.5 text-xs">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Simpan Hak Akses
                            </button>
                            @endcan
                        </div>
                    </div>
                </form>
            </div>

            {{-- TAB 2: KELOLA PENGGUNA --}}
            <div x-show="activeTab === 'users'" x-transition id="tab-users" class="space-y-5">

                {{-- Form Tambah Pengguna ke Role --}}
                @can('role_assign_user')
                <div class="campus-card p-5 bg-white border border-slate-200 rounded-xl">
                    <h4 class="text-sm font-bold text-slate-800 mb-2 flex items-center gap-2">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                        </svg>
                        Tugaskan Pengguna ke Role {{ $roleMeta['label'] ?? $role->name }}
                    </h4>
                    <p class="text-xs text-slate-500 mb-4">
                        Masukkan username akun SSO / NIM mahasiswa / NIP pegawai untuk memberikan role ini. Mahasiswa yang ditugaskan role Petugas akan memiliki multi-role dan dapat beralih peran kapan saja melalui sidebar.
                    </p>
                    <form action="{{ route('role.assign-user', $role->id) }}" method="POST" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3 items-end">
                        @csrf
                        <div class="md:col-span-{{ in_array($role->name, ['Pelaksana', 'Pemimpin']) ? '1' : '3' }}">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Username SSO / NIM</label>
                            <input type="text" name="username" required placeholder="misal: 103072400079 atau username"
                                   class="form-control campus-input w-full text-xs">
                        </div>
                        @if(in_array($role->name, ['Pelaksana', 'Pemimpin']))
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Unit Kerja</label>
                                <select name="unit_id" class="form-control campus-select w-full text-xs">
                                    <option value="">-- Pilih Unit Kerja --</option>
                                    @foreach($units as $u)
                                        <option value="{{ $u->id }}" {{ old('unit_id') == $u->id ? 'selected' : '' }}>
                                            {{ $u->name }} @if($u->code) ({{ $u->code }}) @endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Jabatan / Posisi</label>
                                <input type="text" name="position" placeholder="Posisi di unit" class="form-control campus-input w-full text-xs">
                            </div>
                        @endif
                        <div>
                            <button type="submit" class="btn btn-primary text-xs flex items-center justify-center gap-1.5 w-full py-2">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                Tambahkan
                            </button>
                        </div>
                    </form>
                </div>

                @endcan

                {{-- Current Role Users Table --}}
                <div class="campus-card p-5 bg-white border border-slate-200 rounded-xl space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <h4 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            Pengguna dengan Role
                        </h4>
                        <span class="text-xs font-normal text-slate-500">
                            @if($isMahasiswa && $search === '')
                                Pencarian diperlukan
                            @else
                                {{ $roleUsers->total() }} pengguna
                            @endif
                        </span>
                    </div>

                    {{-- Form Pencarian Server-Side --}}
                    <form method="GET" action="{{ route('role.edit', $role->id) }}#tab-users" class="flex flex-col sm:flex-row gap-2">
                        <div class="relative flex-1">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                            </div>
                            <input type="text"
                                   name="search"
                                   maxlength="100"
                                   value="{{ $search }}"
                                   placeholder="Cari berdasarkan username SSO / NIM, nama, atau email..."
                                   class="form-control campus-input w-full pl-9 pr-3 py-2 text-xs">
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="submit" class="btn btn-primary text-xs flex items-center justify-center gap-1.5 py-2 px-4 whitespace-nowrap">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                                Cari
                            </button>
                            @if($search !== '')
                                <a href="{{ route('role.edit', $role->id) }}#tab-users"
                                   class="btn btn-secondary text-xs flex items-center justify-center gap-1 py-2 px-3 whitespace-nowrap text-slate-600 hover:text-slate-800">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                    Reset
                                </a>
                            @endif
                        </div>
                    </form>

                    @if($isMahasiswa && $search === '')
                        <div class="text-center py-10 px-4 border border-dashed border-slate-200 rounded-xl bg-slate-50/50">
                            <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-slate-100 flex items-center justify-center text-slate-400">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                            </div>
                            <h5 class="text-xs font-bold text-slate-700 mb-1">Cari Mahasiswa Terlebih Dahulu</h5>
                        </div>
                    @elseif($search !== '' && $roleUsers->isEmpty())
                        <div class="text-center py-10 px-4 border border-dashed border-slate-200 rounded-xl bg-slate-50/50">
                            <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-rose-50 flex items-center justify-center text-rose-400">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <h5 class="text-xs font-bold text-slate-700 mb-1">Pengguna Tidak Ditemukan</h5>
                            <p class="text-xs text-slate-500 max-w-md mx-auto mb-3">
                                Tidak ditemukan pengguna dengan role <span class="font-semibold">{{ $roleMeta['label'] ?? $role->name }}</span> yang cocok dengan kata kunci &ldquo;<span class="font-semibold text-slate-700">{{ $search }}</span>&rdquo;.
                            </p>
                            <a href="{{ route('role.edit', $role->id) }}#tab-users" class="btn btn-secondary text-xs inline-flex items-center gap-1.5 py-1.5 px-3">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                </svg>
                                Reset Pencarian
                            </a>
                        </div>
                    @elseif($roleUsers->isEmpty())
                        <div class="text-center py-10 text-slate-400">
                            <svg class="w-10 h-10 mx-auto mb-2 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <p class="text-sm">Belum ada pengguna yang memiliki role ini.</p>
                        </div>
                    @else
                        <div class="overflow-x-auto border border-slate-200 rounded-xl">
                            <table class="min-w-full divide-y divide-slate-200">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Nama</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Username</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Unit & Jabatan</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Sumber Akun</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-slate-100">
                                    @foreach($roleUsers as $u)
                                        <tr class="hover:bg-slate-50 transition-colors">
                                            <td class="px-4 py-3">
                                                <div class="flex items-center gap-2.5">
                                                    <div class="w-8 h-8 rounded-full bg-[#DA2D3B] flex items-center justify-center flex-shrink-0">
                                                        <span class="text-white text-xs font-bold">{{ strtoupper(substr($u->name, 0, 1)) }}</span>
                                                    </div>
                                                    <div>
                                                        <p class="text-sm font-semibold text-slate-800">{{ $u->name }}</p>
                                                        <p class="text-xs text-slate-400">{{ $u->email }}</p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-sm text-slate-600 font-mono">
                                                {{ $u->username ?? '-' }}
                                            </td>
                                            <td class="px-4 py-3 text-xs text-slate-600">
                                                @if($u->unit || $u->position)
                                                    <div class="font-medium text-slate-800">
                                                        {{ $u->unit->name ?? '-' }}
                                                        @if(!empty($u->unit?->code))
                                                            <span class="text-[11px] text-slate-400 font-mono font-normal">({{ $u->unit->code }})</span>
                                                        @endif
                                                    </div>
                                                    <div class="text-[11px] text-slate-500">{{ $u->position ?? '-' }}</div>
                                                @else
                                                    <span class="text-slate-400 italic">-</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="flex items-center gap-1.5 flex-wrap">
                                                    @if($u->auth_source === 'sso')
                                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold text-slate-600 bg-slate-100">
                                                            SSO
                                                        </span>
                                                    @else
                                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold text-slate-600 bg-slate-100">
                                                            Manual
                                                        </span>
                                                    @endif
                                                    @if($u->status === 'inactive')
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold text-rose-700 bg-rose-50 border border-rose-200">
                                                            Nonaktif
                                                        </span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                @if($u->id !== auth()->id() && auth()->user()->can('role_revoke_user'))
                                                    <form action="{{ route('role.revoke-user', $role->id) }}" method="POST"
                                                          onsubmit="return confirm('{{ $u->status === 'inactive' ? 'Aktifkan kembali' : 'Nonaktifkan' }} akun {{ addslashes($u->name) }}?')">
                                                        @csrf
                                                        <input type="hidden" name="user_id" value="{{ $u->id }}">
                                                        @if($search !== '')
                                                            <input type="hidden" name="search" value="{{ $search }}">
                                                        @endif
                                                        @if($u->status === 'inactive')
                                                            <button type="submit"
                                                                    class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-semibold text-emerald-600 border border-emerald-200 rounded-lg hover:bg-emerald-50 transition-colors">
                                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                                </svg>
                                                                Aktifkan
                                                            </button>
                                                        @else
                                                            <button type="submit"
                                                                    class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-semibold text-red-600 border border-red-200 rounded-lg hover:bg-red-50 transition-colors">
                                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                                </svg>
                                                                Nonaktifkan
                                                            </button>
                                                        @endif
                                                    </form>
                                                @else
                                                    <span class="text-xs text-slate-300 italic">{{ $u->id === auth()->id() ? 'Anda sendiri' : '-' }}</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{-- Pagination --}}
                        @if($roleUsers->hasPages())
                            <div class="mt-4">
                                {{ $roleUsers->fragment('tab-users')->links() }}
                            </div>
                        @endif
                    @endif
                </div>

            </div>{{-- end tab users --}}

        </div>{{-- end x-data tabs --}}

    </div>
</x-app-layout>
