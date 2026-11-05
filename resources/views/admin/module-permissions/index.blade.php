<x-app-layout>
    <x-slot name="header">Pengaturan Role & Permission</x-slot>

    @if(session('success'))
    <div class="mb-5 flex items-start gap-3 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl">
        <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <p class="text-xs sm:text-sm font-semibold">{{ session('success') }}</p>
    </div>
    @endif

    @if(session('error'))
    <div class="mb-5 flex items-start gap-3 bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-xl">
        <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <p class="text-xs sm:text-sm font-semibold">{{ session('error') }}</p>
    </div>
    @endif

    <div class="campus-card">
        <div class="px-6 py-4 border-b border-slate-200">
            <h3 class="text-lg font-bold text-slate-800">Konfigurasi Permission per Module</h3>
            <p class="text-xs text-slate-600 mt-1">
                Atur hak akses (Create, Read, Update, Delete) untuk setiap role pada module tertentu.
                Perubahan akan langsung berlaku setelah disimpan.
            </p>
        </div>

        @foreach($modules as $moduleKey => $moduleLabel)
        <div class="px-6 py-5 border-b border-slate-100 last:border-b-0">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h4 class="text-base font-bold text-slate-800">{{ $moduleLabel }}</h4>
                    <p class="text-xs text-slate-500 mt-0.5">Module: <code class="text-xs bg-slate-100 px-2 py-0.5 rounded">{{ $moduleKey }}</code></p>
                </div>
                <form method="POST" action="{{ route('admin.module-permissions.reset', $moduleKey) }}" onsubmit="return confirm('Reset permission ke default?')">
                    @csrf
                    @method('POST')
                    <button type="submit" class="text-xs px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg transition">
                        Reset ke Default
                    </button>
                </form>
            </div>

            <form method="POST" action="{{ route('admin.module-permissions.update', $moduleKey) }}">
                @csrf
                @method('PUT')

                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200">
                                <th class="px-4 py-3 text-left font-semibold text-slate-700">Role</th>
                                <th class="px-4 py-3 text-center font-semibold text-slate-700">Create</th>
                                <th class="px-4 py-3 text-center font-semibold text-slate-700">Read</th>
                                <th class="px-4 py-3 text-center font-semibold text-slate-700">Update</th>
                                <th class="px-4 py-3 text-center font-semibold text-slate-700">Delete</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($roles as $role)
                            @php
                                $permission = $permissions[$moduleKey]->firstWhere('role_name', $role);
                            @endphp
                            <tr class="border-b border-slate-100 hover:bg-slate-50">
                                <td class="px-4 py-3 font-medium text-slate-800 capitalize">
                                    {{ str_replace('_', ' ', $role) }}
                                    <input type="hidden" name="permissions[{{ $loop->index }}][role]" value="{{ $role }}">
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <input type="checkbox" name="permissions[{{ $loop->index }}][can_create]" value="1" 
                                           {{ $permission && $permission->can_create ? 'checked' : '' }}
                                           class="w-4 h-4 text-[#DA2D3B] focus:ring-[#DA2D3B] rounded">
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <input type="checkbox" name="permissions[{{ $loop->index }}][can_read]" value="1" 
                                           {{ $permission && $permission->can_read ? 'checked' : '' }}
                                           class="w-4 h-4 text-[#DA2D3B] focus:ring-[#DA2D3B] rounded">
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <input type="checkbox" name="permissions[{{ $loop->index }}][can_update]" value="1" 
                                           {{ $permission && $permission->can_update ? 'checked' : '' }}
                                           class="w-4 h-4 text-[#DA2D3B] focus:ring-[#DA2D3B] rounded">
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <input type="checkbox" name="permissions[{{ $loop->index }}][can_delete]" value="1" 
                                           {{ $permission && $permission->can_delete ? 'checked' : '' }}
                                           class="w-4 h-4 text-[#DA2D3B] focus:ring-[#DA2D3B] rounded">
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex justify-end">
                    <button type="submit" class="btn btn-primary text-xs">
                        Simpan Perubahan
                    </button>
                </div>
            </form>

            <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                <p class="text-xs text-blue-800 font-medium mb-1">Catatan:</p>
                <ul class="text-xs text-blue-700 space-y-0.5 list-disc list-inside">
                    <li><strong>Mahasiswa:</strong> Constraint tambahan: hanya bisa Read/Update data miliknya sendiri, Update hanya jika status 'submitted' atau 'needs_review'</li>
                    <li><strong>Pelaksana (Staf SSC) &amp; Akademik:</strong> Akses ke semua data tanpa batasan ownership</li>
                </ul>
            </div>
        </div>
        @endforeach
    </div>
</x-app-layout>
