<x-app-layout>
    <x-slot name="header">Tambah Akun Manual</x-slot>

    <div class="max-w-2xl mx-auto">
        {{-- Back Button --}}
        <div class="mb-4">
            <a href="{{ route('manual-users.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-slate-800 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Kembali ke Daftar Akun Manual
            </a>
        </div>

        <div class="campus-card p-6">
            <div class="mb-6 border-b border-slate-100 pb-4">
                <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Formulir Akun Baru</h3>
                <p class="text-xs text-slate-400 mt-1">Akun manual login langsung ke database lokal (tidak melalui SSO kampus).</p>
            </div>

            <form method="POST" action="{{ route('manual-users.store') }}" class="space-y-5">
                @csrf

                {{-- Nama --}}
                <div>
                    <label for="name" class="block text-sm font-medium text-slate-700 mb-1">Nama Lengkap <span class="text-rose-500">*</span></label>
                    <input type="text" name="name" id="name" value="{{ old('name') }}" required
                           class="campus-input w-full @error('name') border-rose-400 @enderror" placeholder="Masukkan nama lengkap">
                    @error('name') <p class="text-xs text-rose-500 mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Email --}}
                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700 mb-1">Email <span class="text-rose-500">*</span></label>
                    <input type="email" name="email" id="email" value="{{ old('email') }}" required
                           class="campus-input w-full @error('email') border-rose-400 @enderror" placeholder="contoh@email.com">
                    @error('email') <p class="text-xs text-rose-500 mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Username --}}
                <div>
                    <label for="username" class="block text-sm font-medium text-slate-700 mb-1">Username <span class="text-slate-400 text-xs">(opsional)</span></label>
                    <input type="text" name="username" id="username" value="{{ old('username') }}"
                           class="campus-input w-full @error('username') border-rose-400 @enderror" placeholder="username_login">
                    @error('username') <p class="text-xs text-rose-500 mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Password --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="password" class="block text-sm font-medium text-slate-700 mb-1">Password <span class="text-rose-500">*</span></label>
                        <input type="password" name="password" id="password" required
                               class="campus-input w-full @error('password') border-rose-400 @enderror" placeholder="Minimal 8 karakter">
                        @error('password') <p class="text-xs text-rose-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-slate-700 mb-1">Konfirmasi Password <span class="text-rose-500">*</span></label>
                        <input type="password" name="password_confirmation" id="password_confirmation" required
                               class="campus-input w-full" placeholder="Ketik ulang password">
                    </div>
                </div>

                {{-- Role --}}
                <div>
                    <label for="role" class="block text-sm font-medium text-slate-700 mb-1">Role <span class="text-rose-500">*</span></label>
                    <select name="role" id="role" required class="campus-select w-full @error('role') border-rose-400 @enderror">
                        <option value="">— Pilih Role —</option>
                        @foreach($roles as $role)
                            <option value="{{ $role }}" {{ old('role') === $role ? 'selected' : '' }}>{{ $role }}</option>
                        @endforeach
                    </select>
                    @error('role') <p class="text-xs text-rose-500 mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Submit --}}
                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                    <a href="{{ route('manual-users.index') }}" class="btn btn-secondary text-xs">Batal</a>
                    <button type="submit" class="btn btn-primary text-xs">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Simpan Akun
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
