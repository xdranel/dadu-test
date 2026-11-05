<x-guest-layout>
    <div class="login-wrapper">

        <!-- LEFT: BRANDING PANEL -->
        <div class="login-brand">
            <div class="brand-content">
                <p class="brand-greeting">Selamat Datang di</p>
                <h2 class="brand-title">
                    DADU 1.0
                </h2>
                <p class="brand-subtitle">
                    Dashboard akademik terpadu
                </p>
            </div>
        </div>

        <!-- RIGHT: LOGIN FORM -->
        <div class="login-form-panel">
            <div class="login-form-container">

                <!-- Header -->
                <div class="form-header">
                    <h1>LOG IN</h1>
                    <p>Single Account, Single Sign On login</p>
                </div>

                <!-- Error Alerts -->
                @if (session('error'))
                    <div class="alert alert-error" id="alert-session-error">
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

                <!-- Login Form -->
                <form method="POST" action="{{ route('sso.api.login') }}" id="login-form">
                    @csrf

                    <!-- Username / Email -->
                    <div class="form-group">
                        <div class="input-wrapper">
                            <input
                                type="text"
                                name="username"
                                id="username"
                                class="form-input"
                                placeholder="Username SSO"
                                value="{{ old('username') }}"
                                required
                                autocomplete="username"
                                autofocus
                            >
                            <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </div>
                    </div>

                    <!-- Password -->
                    <div class="form-group">
                        <div class="input-wrapper">
                            <input
                                type="password"
                                name="password"
                                id="password"
                                class="form-input"
                                placeholder="Password"
                                required
                                autocomplete="current-password"
                                style="padding-right: 2.75rem;"
                            >
                            <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                            <button type="button" class="password-toggle" onclick="togglePassword()" id="password-toggle-btn" aria-label="Toggle password visibility">
                                <svg id="eye-open" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                <svg id="eye-closed" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:none;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L6.11 6.11m3.768 3.768L6.11 6.11m0 0L3 3m3.11 3.11l4.768 4.768M21 21l-3.11-3.11m0 0l-4.768-4.768"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Remember Me -->
                    <div class="remember-row">
                        <input type="checkbox" name="remember" id="remember" class="remember-checkbox">
                        <label for="remember" class="remember-label">Ingat saya di perangkat ini</label>
                    </div>

                    <!-- Lupa Password Link
                    <div style="text-align: right; margin-bottom: 1rem;">
                        <a href="{{ route('forgot-password.step1') }}" style="font-size: 0.8125rem; color: #DA2D3B; text-decoration: none; font-weight: 500; transition: color 0.2s;" onmouseover="this.style.color='#c41e2a'" onmouseout="this.style.color='#DA2D3B'">
                            Lupa Password?
                        </a>
                    </div> -->

                    <!-- Google reCAPTCHA -->
                    @if (config('services.recaptcha.enabled'))
                        <div class="recaptcha-wrapper">
                            <div class="g-recaptcha" data-sitekey="{{ config('services.recaptcha.site_key') }}" id="recaptcha-widget"></div>
                        </div>
                    @endif

                    <!-- Submit -->
                    <button type="submit" class="btn-login" id="btn-login">
                        Masuk
                    </button>
                </form>

                <!-- Footer -->
                <div class="login-footer">
                    <p>&copy;{{ date('Y') }} Dadu 1.0 - Telkom University Surabaya</p>
                </div>
            </div>
        </div>
    </div>

    <!-- reCAPTCHA Script -->
    @if (config('services.recaptcha.enabled'))
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    @endif

    <!-- Toggle Password Script -->
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const eyeOpen = document.getElementById('eye-open');
            const eyeClosed = document.getElementById('eye-closed');

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