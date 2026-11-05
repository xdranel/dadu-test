<x-guest-layout>
    <div class="login-wrapper">

        <!-- LEFT: BRANDING PANEL -->
        <div class="login-brand">
            <div class="brand-content">
                <svg class="w-20 h-20 text-white mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                </svg>
                <h2 class="brand-title">
                    Lupa Password?
                </h2>
                <p class="brand-subtitle">
                    Kendala Reset Password? Hubungi Hotline Akademik
                </p>
            </div>
        </div>

        <!-- RIGHT: FORM PANEL -->
        <div class="login-form-panel">
            <div class="login-form-container">

                <!-- Header -->
                <div class="form-header">
                    <h1>Reset Password</h1>
                    <p>Masukkan username Anda untuk melanjutkan</p>
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
                        <span>{{ $errors->first() }}</span>
                    </div>
                @endif

                <!-- Form -->
                <form method="POST" action="{{ route('forgot-password.verify') }}" id="forgot-password-form">
                    @csrf

                    <!-- Username / NIP -->
                    <div class="form-group">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-wrapper">
                            <input
                                type="text"
                                name="username"
                                id="username"
                                class="form-input"
                                placeholder="username"
                                value="{{ old('username') }}"
                                required
                                autofocus
                            >
                            <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn-login" id="btn-continue">
                        Lanjutkan
                    </button>
                </form>

                <!-- Back to Login -->
                <div style="text-align: center; margin-top: 1.5rem;">
                    <a href="{{ route('login') }}" style="font-size: 0.8125rem; color: #6b7280; text-decoration: none;">
                        Kembali ke Login
                    </a>
                </div>

                <!-- Footer -->
                <div class="login-footer">
                    <p>&copy; {{ date('Y') }} Dadu 1.0 - Telkom University Surabaya</p>
                </div>
            </div>
        </div>

    </div>
</x-guest-layout>
