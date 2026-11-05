<x-app-layout>
    <x-slot name="header">
        Ubah Sandi
    </x-slot>

    <div class="max-w-2xl mx-auto">

        <!-- Form Card -->
        <div class="bg-white rounded-lg shadow-md border border-slate-200 p-8">
            <div class="mb-6">
                <h2 class="text-xl font-semibold text-slate-800 mb-2">Ganti Password</h2>
                <p class="text-sm text-slate-600">Password baru harus berbeda dengan password lama</p>
            </div>

            <!-- Session Warning Alert -->
            @if (session('warning'))
                <div class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded-lg mb-6 flex items-start gap-3">
                    <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <span class="text-sm">{{ session('warning') }}</span>
                </div>
            @endif

            <!-- Error Alerts -->
            @if ($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-6">
                    <div class="flex items-start gap-3">
                        <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div class="flex-1">
                            @foreach ($errors->all() as $error)
                                <p class="text-sm">{{ $error }}</p>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            <!-- Form -->
            <form method="POST" action="{{ route('password.force-change.update') }}" class="space-y-5">
                @csrf

                <!-- Password Lama -->
                <div>
                    <label for="current_password" class="block text-sm font-medium text-slate-700 mb-2">
                        Password Lama
                    </label>
                    <input 
                        type="password" 
                        id="current_password" 
                        name="current_password" 
                        class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 transition @error('current_password') border-red-500 @enderror" 
                        required 
                        autofocus
                    >
                    @error('current_password')
                        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Password Baru -->
                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700 mb-2">
                        Password Baru
                    </label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 transition @error('password') border-red-500 @enderror" 
                        required
                    >
                    <p class="mt-1.5 text-xs text-slate-500">Minimal 8 karakter, mengandung huruf dan angka</p>
                    @error('password')
                        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Konfirmasi Password Baru -->
                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-slate-700 mb-2">
                        Konfirmasi Password Baru
                    </label>
                    <input 
                        type="password" 
                        id="password_confirmation" 
                        name="password_confirmation" 
                        class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 transition @error('password_confirmation') border-red-500 @enderror" 
                        required
                    >
                    @error('password_confirmation')
                        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Submit Button -->
                <div class="pt-4">
                    <button type="submit" class="w-full bg-gradient-to-r from-red-600 to-red-700 hover:from-red-700 hover:to-red-800 text-white font-semibold py-3 px-6 rounded-lg transition duration-200 shadow-md hover:shadow-lg">
                        Ganti Password
                    </button>
                </div>
            </form>

            <!-- Logout Link -->
            <div class="text-center mt-6 pt-6 border-t border-slate-200">
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="text-sm text-slate-600 hover:text-slate-800 underline transition">
                        Keluar dari Akun
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
