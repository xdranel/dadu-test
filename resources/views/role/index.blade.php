<x-app-layout>
    <x-slot name="header">Manajemen Hak Akses (Role)</x-slot>

    <div class="max-w-7xl mx-auto space-y-6">
        {{-- Header Info --}}
        <div class="campus-card p-5 from-slate-800 to-slate-700 text-white rounded-xl">
                <div class="flex items-center gap-2 flex-shrink-0">
                    @can('role_assign_user')
                    <a href="{{ route('user-role.create') }}"
                       class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs rounded-xl shadow transition-colors flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                        </svg>
                        Tambah Akun
                    </a>
                    @endcan
                </div>
        </div>

        {{-- Roles Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
            @foreach($roles as $role)
                @php
                    $meta = $metadata[$role->name] ?? [
                        'id'          => '---',
                        'label'       => ucfirst($role->name),
                        'icon'        => 'shield',
                        'color'       => 'slate',
                        'sso_auto'    => false,
                        'description' => 'Peran pengguna sistem.',
                        'access'      => '',
                    ];

                    $colorMap = [
                        'emerald' => ['card' => 'border-emerald-200 bg-emerald-50/40', 'badge' => 'bg-emerald-100 text-emerald-800', 'id' => 'bg-emerald-500', 'btn' => 'text-emerald-700 border-emerald-300 hover:bg-emerald-100'],
                        'amber'   => ['card' => 'border-amber-200 bg-amber-50/40',   'badge' => 'bg-amber-100 text-amber-800',   'id' => 'bg-amber-500',   'btn' => 'text-amber-700 border-amber-300 hover:bg-amber-100'],
                        'blue'    => ['card' => 'border-blue-200 bg-blue-50/40',     'badge' => 'bg-blue-100 text-blue-800',     'id' => 'bg-blue-500',    'btn' => 'text-blue-700 border-blue-300 hover:bg-blue-100'],
                        'purple'  => ['card' => 'border-purple-200 bg-purple-50/40', 'badge' => 'bg-purple-100 text-purple-800', 'id' => 'bg-purple-500',  'btn' => 'text-purple-700 border-purple-300 hover:bg-purple-100'],
                        'indigo'  => ['card' => 'border-indigo-200 bg-indigo-50/40', 'badge' => 'bg-indigo-100 text-indigo-800', 'id' => 'bg-indigo-500',  'btn' => 'text-indigo-700 border-indigo-300 hover:bg-indigo-100'],
                        'sky'     => ['card' => 'border-sky-200 bg-sky-50/40',       'badge' => 'bg-sky-100 text-sky-800',       'id' => 'bg-sky-500',     'btn' => 'text-sky-700 border-sky-300 hover:bg-sky-100'],
                        'rose'    => ['card' => 'border-rose-200 bg-rose-50/40',     'badge' => 'bg-rose-100 text-rose-800',     'id' => 'bg-rose-500',    'btn' => 'text-rose-700 border-rose-300 hover:bg-rose-100'],
                        'slate'   => ['card' => 'border-slate-200 bg-slate-50/40',   'badge' => 'bg-slate-100 text-slate-700',   'id' => 'bg-slate-500',   'btn' => 'text-slate-700 border-slate-300 hover:bg-slate-100'],
                    ];
                    $c = $colorMap[$meta['color']] ?? $colorMap['slate'];
                @endphp

                <div class="border rounded-xl p-5 flex flex-col justify-between transition-shadow duration-200 hover:shadow-md {{ $c['card'] }}">
                    <div class="space-y-3">
                        {{-- Header: Role ID + Nama --}}
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-3">
                                {{-- Role ID Badge (Icon) --}}
                                <div class="w-10 h-10 rounded-lg {{ $c['id'] }} flex items-center justify-center flex-shrink-0">
                                    @switch($meta['icon'] ?? 'shield')
                                        @case('user')
                                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                            </svg>
                                            @break
                                        @case('badge')
                                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/>
                                            </svg>
                                            @break
                                        @case('cog')
                                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            </svg>
                                            @break
                                        @case('star')
                                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                                            </svg>
                                            @break
                                        @case('eye')
                                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                            @break
                                        @case('crown')
                                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2 4l3 12h14l3-12-6 7-4-7-4 7-6-7zm3 16h14"/>
                                            </svg>
                                            @break
                                        @case('shield')
                                        @default
                                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                            </svg>
                                    @endswitch
                                </div>
                                <div>
                                    <h3 class="text-sm font-bold text-slate-800">{{ $meta['label'] }}</h3>
                                    <span class="text-[11px] text-slate-500 font-mono">{{ $role->name }}</span>
                                </div>
                            </div>
                        </div>
                        {{-- Stats --}}
                        <div class="flex items-center gap-3 pt-1">
                            <span class="inline-flex items-center gap-1.5 text-[11px] font-medium text-slate-600">
                                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                {{ $role->permissions_count }} permissions aktif
                            </span>
                            <span class="text-slate-300">|</span>
                            <span class="inline-flex items-center gap-1.5 text-[11px] font-medium text-slate-600">
                                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                {{ $role->users_count }} pengguna
                            </span>
                        </div>
                    </div>

                    {{-- Footer Actions --}}
                    <div class="border-t border-slate-200/80 pt-4 mt-4 flex items-center gap-2">
                        @if($role->name === 'Akademik')
                            <button disabled class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-semibold text-slate-400 border border-slate-200 rounded-lg cursor-not-allowed opacity-70">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                                Bypass (Superadmin)
                            </button>
                        @else
                            <a href="{{ route('role.edit', $role->id) }}"
                               class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-semibold border rounded-lg transition-colors {{ $c['btn'] }}">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
                                </svg>
                                Edit Hak Akses
                            </a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

    </div>
</x-app-layout>
