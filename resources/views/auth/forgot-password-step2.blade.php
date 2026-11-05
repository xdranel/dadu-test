<x-guest-layout>
    <div class="login-wrapper">

        <!-- LEFT: BRANDING PANEL -->
        <div class="login-brand">
            <div class="brand-content">
                <svg class="w-20 h-20 text-white mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
                <h2 class="brand-title">
                    Buat Password Baru
                </h2>
                <p class="brand-subtitle">
                    Masukkan password baru untuk akun Anda
                </p>
            </div>
        </div>

        <!-- RIGHT: FORM PANEL -->
        <div class="login-form-panel">
            <div class="login-form-container">

                <!-- Header -->
                <div class="form-header">
                    <h1>Reset Password</h1>
                    <p>Password baru harus memenuhi persyaratan keamanan</p>
                </div>

                <!-- Error Alert -->
                @if (session('error'))
                    <div class="alert alert-error" id="alert-error">
                        <svg class="alert-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>{{ session('error') }}</span>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-error" id="alert-validation-error">
                        <svg class="alert-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div>
                            @foreach ($errors->all() as $error)
                                <p style="margin: 0;">{{ $error }}</p>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Info Box -->
                <div style="background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 12px; padding: 1rem; margin-bottom: 1.5rem;">
                    <div style="display: flex; align-items: start; gap: 0.625rem;">
                        <svg style="width: 18px; height: 18px; color: #0284c7; flex-shrink: 0; margin-top: 1px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div style="flex: 1;">
                            <p style="font-size: 0.75rem; color: #0c4a6e; line-height: 1.5; margin: 0;">
                                Setelah password direset, Anda akan otomatis logout dari semua perangkat lain untuk keamanan akun Anda.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Form -->
                <form method="POST" action="{{ route('forgot-password.reset') }}" id="reset-password-form">
                    @csrf

                    <!-- Password Baru -->
                    <div class="form-group">
                        <label for="password" class="form-label">Password Baru</label>
                        <div class="input-wrapper">
                            <input
                                type="password"
                                name="password"
                                id="password"
                                class="form-input"
                                placeholder="Masukkan password baru"
                                required
                                autofocus
                                style="padding-right: 2.75rem;"
                            >
                            <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                            <button type="button" class="password-toggle" onclick="togglePassword('password')" aria-label="Toggle password visibility">
                                <svg id="eye-open-password" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                <svg id="eye-closed-password" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:none;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L6.11 6.11m3.768 3.768L6.11 6.11m0 0L3 3m3.11 3.11l4.768 4.768M21 21l-3.11-3.11m0 0l-4.768-4.768"/>
                                </svg>
                            </button>
                        </div>
                        <p style="font-size: 0.75rem; color: #6b7280; margin-top: 0.5rem;">
                            Minimal 8 karakter, mengandung huruf dan angka
                        </p>
                    </div>

                    <!-- Konfirmasi Password Baru -->
                    <div class="form-group">
                        <label for="password_confirmation" class="form-label">Konfirmasi Password Baru</label>
                        <div class="input-wrapper">
                            <input
                                type="password"
                                name="password_confirmation"
                                id="password_confirmation"
                                class="form-input"
                                placeholder="Masukkan ulang password baru"
                                required
                                style="padding-right: 2.75rem;"
                            >
                            <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <button type="button" class="password-toggle" onclick="togglePassword('password_confirmation')" aria-label="Toggle password confirmation visibility">
                                <svg id="eye-open-password_confirmation" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                <svg id="eye-closed-password_confirmation" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:none;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L6.11 6.11m3.768 3.768L6.11 6.11m0 0L3 3m3.11 3.11l4.768 4.768M21 21l-3.11-3.11m0 0l-4.768-4.768"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn-login" id="btn-reset">
                        Reset Password
                    </button>
                </form>

                <!-- Back to Login -->
                <div style="text-align: center; margin-top: 1.5rem;">
                    <a href="{{ route('login') }}" style="font-size: 0.8125rem; color: #6b7280; text-decoration: none;">
                        ← Kembali ke Login
                    </a>
                </div>

                <!-- Footer -->
                <div class="login-footer">
                    <p>&copy; {{ date('Y') }} Dadu 1.0 - Telkom University Surabaya</p>
                </div>
            </div>
        </div>

    </div>

    <!-- Toggle Password Script -->
    <script>
        function togglePassword(fieldId) {
            const passwordInput = document.getElementById(fieldId);
            const eyeOpen = document.getElementById('eye-open-' + fieldId);
            const eyeClosed = document.getElementById('eye-closed-' + fieldId);

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeOpen.style.display = 'none';
                eyeClosed.style.display = 'block';
            } else {
                passwordInput.type = 'password';
                eyeOpen.style.display = 'block';
                eyeClosed.style.display = 'none';
            }
        }
    </script>
</x-guest-layout>
