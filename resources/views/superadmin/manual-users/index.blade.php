<x-app-layout>
    <x-slot name="header">Kelola Akun Manual</x-slot>

    <div class="max-w-6xl mx-auto">

        {{-- Flash Messages --}}
        @if(session('success'))
            <div class="mb-4 p-3 bg-emerald-50 border border-emerald-200 rounded-lg text-sm text-emerald-700">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="mb-4 p-3 bg-rose-50 border border-rose-200 rounded-lg text-sm text-rose-700">
                {{ session('error') }}
            </div>
        @endif

        {{-- Modal Alert Password Baru (Flash sekali pakai) --}}
        @if(session('generated_password'))
            <div class="mb-4 p-4 bg-amber-50 border-2 border-amber-300 rounded-lg" x-data="{ show: true }" x-show="show">
                <div class="flex items-start gap-3">
                    <div class="flex-1">
                        <h4 class="font-bold text-amber-800">Password Berhasil Direset!</h4>
                        <p class="text-sm text-amber-700 mt-1">Password baru untuk <strong>{{ session('reset_user_name') }}</strong>:</p>
                        <div class="mt-2 p-3 bg-white border border-amber-200 rounded font-mono text-lg text-amber-900 select-all break-all">
                            {{ session('generated_password') }}
                        </div>
                        <p class="text-xs text-amber-600 mt-2 leading-relaxed">
                            <strong>PENTING:</strong> Password ini hanya ditampilkan SEKALI dan tidak akan tersimpan di sistem. User akan diminta mengganti password saat login pertama kali.
                        </p>
                    </div>
                    <button @click="show = false" class="text-amber-500 hover:text-amber-700">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>
        @endif

        {{-- Header Row --}}
        <div class="flex items-center justify-between mb-4">
            <form method="GET" action="{{ route('manual-users.index') }}" class="flex items-center gap-2">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari username..."
                       class="campus-input text-sm w-64" />
                <button type="submit" class="btn btn-secondary text-xs">Cari</button>
            </form>
            <a href="{{ route('manual-users.create') }}" class="btn btn-primary flex items-center gap-1.5 text-xs">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                </svg>
                Tambah Akun Manual
            </a>
        </div>

        {{-- Users Table --}}
        <div class="campus-card overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200">
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Nama</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Email</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Username</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Role</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-slate-600 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-slate-600 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($users as $user)
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-4 py-3 text-sm font-medium text-slate-800">{{ $user->name }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $user->email }}</td>
                            <td class="px-4 py-3 text-sm text-slate-500">{{ $user->username ?? '-' }}</td>
                            <td class="px-4 py-3">
                                @php $roleName = $user->getRoleNames()->first() ?? '-'; @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold
                                    {{ $roleName === 'admin' ? 'bg-blue-50 text-blue-700 border border-blue-100' :
                                       (in_array($roleName, ['Pelaksana', 'Staf SSC']) ? 'bg-amber-50 text-amber-700 border border-amber-100' :
                                       (in_array($roleName, ['Pengamat', 'Direktorat']) ? 'bg-indigo-50 text-indigo-700 border border-indigo-100' :
                                       (in_array($roleName, ['Pemimpin', 'Kaprodi']) ? 'bg-purple-50 text-purple-700 border border-purple-100' :
                                       'bg-slate-50 text-slate-600 border border-slate-200'))) }}">
                                    {{ $roleName }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold
                                    {{ $user->status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-600' }}">
                                    {{ ucfirst($user->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <a href="{{ route('manual-users.edit', $user) }}" class="text-sky-600 hover:text-sky-800 transition-colors" title="Edit">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </a>
                                    @if($user->id !== auth()->id())
                                        @can('user_reset_password')
                                            <button type="button"
                                                    @click="$dispatch('open-reset-password-modal', { url: '{{ route('manual-users.reset-password', $user) }}', name: '{{ addslashes($user->name) }}' })"
                                                    class="text-amber-500 hover:text-amber-700 transition-colors" 
                                                    title="Reset Password">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                                                </svg>
                                            </button>
                                        @endcan
                                        <form method="POST" action="{{ route('manual-users.destroy', $user) }}" class="inline"
                                              onsubmit="return confirm('Nonaktifkan akun {{ $user->name }}?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-rose-500 hover:text-rose-700 transition-colors" title="Nonaktifkan">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                                </svg>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-sm text-slate-400">
                                Belum ada akun manual yang terdaftar.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            {{-- Pagination --}}
            @if($users->hasPages())
                <div class="px-4 py-3 border-t border-slate-100">
                    {{ $users->links() }}
                </div>
            @endif
        </div>
    </div>

    <!-- Modal Konfirmasi Reset Password -->
    <div x-data="{ openReset: false, resetUrl: '', userName: '' }"
         x-show="openReset"
         @open-reset-password-modal.window="openReset = true; resetUrl = $event.detail.url; userName = $event.detail.name;"
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm"
         style="display: none;"
         x-transition>
        <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-sm mx-4 border border-slate-200"
             @click.away="openReset = false">
            <div class="text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-amber-100 text-amber-600 mb-4">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-slate-800 mb-2">Konfirmasi Reset Password</h3>
                <p class="text-sm text-slate-500 mb-6 font-normal">
                    Apakah Anda yakin ingin mereset password untuk <span class="font-semibold text-slate-800" x-text="userName"></span>?
                    <br><br>
                    Password baru akan di-generate otomatis dan ditampilkan <strong>sekali</strong>. Pengguna wajib menggantinya saat login berikutnya.
                </p>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" @click="openReset = false"
                        class="btn btn-secondary w-full">Batal</button>
                <form :action="resetUrl" method="POST" class="w-full">
                    @csrf
                    <button type="submit" class="btn btn-primary bg-amber-600 hover:bg-amber-700 w-full text-white">Reset Password</button>
                </form>
            </div>
        </div>
    </div>

</x-app-layout>
