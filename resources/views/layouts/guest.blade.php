<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Dadu') }} - Login</title>
        <link rel="icon" href="{{ asset('assets/daduLogo.svg') }}" type="image/svg+xml">
        <meta name="description" content="Login portal Dadu 1.0 - Dashboard Akademik Terpadu">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

            body {
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
                -webkit-font-smoothing: antialiased;
                -moz-osx-font-smoothing: grayscale;
                min-height: 100vh;
                min-height: 100dvh;
                overflow-x: hidden;
                overflow-y: auto;
                background: #ffffff;
            }

            /* ─── Full-screen container ─── */
            .login-wrapper {
                display: flex;
                min-height: 100vh;
                min-height: 100dvh;
                width: 100%;
            }

            /* ─── Left branding panel ─── */
            .login-brand {
                flex: 1;
                background: url('/assets/Thumbnail.jpg') center center / cover no-repeat;
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: flex-start;
                padding: 5rem;
                position: relative;
            }

            .brand-content {
                position: relative;
                z-index: 2;
                text-align: left;
                max-width: 500px;
            }

            .brand-greeting {
                font-size: 1.5rem;
                color: #ffffff;
                margin-bottom: 0.5rem;
                font-weight: 400;
            }

            .brand-title {
                font-size: 3rem;
                font-weight: 700;
                color: #ffffff;
                letter-spacing: -0.02em;
                line-height: 1.2;
                margin-bottom: 0.5rem;
            }

            .brand-subtitle {
                font-size: 1.2rem;
                color: #ffffff;
                font-weight: 400;
                line-height: 1.6;
            }

            /* ─── Right form panel ─── */
            .login-form-panel {
                width: 520px;
                max-width: 520px;
                flex-shrink: 0;
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                padding: 3rem;
                background: #ffffff;
                position: relative;
                overflow-y: auto;
            }

            .login-form-container {
                width: 100%;
                max-width: 380px;
                margin: auto 0;
            }

            .form-header {
                margin-bottom: 2rem;
            }

            .form-header-badge {
                display: inline-flex;
                align-items: center;
                gap: 0.375rem;
                padding: 0.375rem 0.875rem;
                border-radius: 999px;
                background: rgba(218,45,59,0.08);
                color: #DA2D3B;
                font-size: 0.75rem;
                font-weight: 600;
                margin-bottom: 1.25rem;
                letter-spacing: 0.02em;
            }

            .form-header h1 {
                font-size: 1.75rem;
                font-weight: 700;
                color: #111827;
                letter-spacing: -0.025em;
                margin-bottom: 0.5rem;
            }

            .form-header p {
                font-size: 0.875rem;
                color: #6b7280;
                line-height: 1.5;
            }

            /* ─── Alert ─── */
            .alert {
                padding: 0.875rem 1rem;
                border-radius: 12px;
                font-size: 0.8125rem;
                margin-bottom: 1.5rem;
                display: flex;
                align-items: flex-start;
                gap: 0.625rem;
                line-height: 1.5;
                animation: shake 0.4s ease-in-out;
            }

            @keyframes shake {
                0%, 100% { transform: translateX(0); }
                25% { transform: translateX(-6px); }
                75% { transform: translateX(6px); }
            }

            .alert-error {
                background: #fef2f2;
                color: #991b1b;
                border: 1px solid #fecaca;
            }

            .alert-icon {
                width: 18px;
                height: 18px;
                flex-shrink: 0;
                margin-top: 1px;
            }

            /* ─── Form inputs ─── */
            .form-group {
                margin-bottom: 1.25rem;
                position: relative;
            }

            .form-label {
                display: block;
                font-size: 0.8125rem;
                font-weight: 600;
                color: #374151;
                margin-bottom: 0.5rem;
            }

            .input-wrapper {
                position: relative;
            }

            .input-icon {
                position: absolute;
                left: 14px;
                top: 50%;
                transform: translateY(-50%);
                width: 18px;
                height: 18px;
                color: #9ca3af;
                pointer-events: none;
                transition: color 0.2s;
            }

            .form-input {
                width: 100%;
                padding: 0.75rem 0.875rem 0.75rem 2.75rem;
                border: 1.5px solid #e5e7eb;
                border-radius: 12px;
                font-size: 0.875rem;
                font-family: 'Inter', sans-serif;
                color: #111827;
                background: #f9fafb;
                transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
                outline: none;
            }

            .form-input::placeholder {
                color: #9ca3af;
            }

            .form-input:hover {
                border-color: #d1d5db;
                background: #ffffff;
            }

            .form-input:focus {
                border-color: #DA2D3B;
                background: #ffffff;
                box-shadow: 0 0 0 3px rgba(218,45,59,0.1);
            }

            .form-input:focus ~ .input-icon,
            .form-input:focus + .input-icon {
                color: #DA2D3B;
            }

            /* Password toggle */
            .password-toggle {
                position: absolute;
                right: 14px;
                top: 50%;
                transform: translateY(-50%);
                background: none;
                border: none;
                cursor: pointer;
                color: #9ca3af;
                padding: 2px;
                transition: color 0.2s;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .password-toggle:hover {
                color: #4b5563;
            }

            .password-toggle svg {
                width: 18px;
                height: 18px;
            }

            /* ─── reCAPTCHA ─── */
            .recaptcha-wrapper {
                margin-bottom: 1.5rem;
                display: flex;
                justify-content: center;
            }

            .recaptcha-wrapper > div {
                transform: scale(0.92);
                transform-origin: center;
            }

            /* ─── Remember me ─── */
            .remember-row {
                display: flex;
                align-items: center;
                margin-bottom: 1.5rem;
            }

            .remember-checkbox {
                width: 16px;
                height: 16px;
                border: 1.5px solid #d1d5db;
                border-radius: 5px;
                appearance: none;
                -webkit-appearance: none;
                cursor: pointer;
                transition: all 0.15s;
                position: relative;
                flex-shrink: 0;
            }

            .remember-checkbox:checked {
                background: #DA2D3B;
                border-color: #DA2D3B;
            }

            .remember-checkbox:checked::after {
                content: '';
                position: absolute;
                top: 2px;
                left: 5px;
                width: 4px;
                height: 8px;
                border: solid #fff;
                border-width: 0 2px 2px 0;
                transform: rotate(45deg);
            }

            .remember-label {
                margin-left: 0.5rem;
                font-size: 0.8125rem;
                color: #6b7280;
                cursor: pointer;
                user-select: none;
            }

            /* ─── Submit button ─── */
            .btn-login {
                width: 100%;
                padding: 0.875rem 1.5rem;
                border: none;
                border-radius: 12px;
                font-family: 'Inter', sans-serif;
                font-size: 0.9375rem;
                font-weight: 600;
                color: #ffffff;
                background: linear-gradient(135deg, #DA2D3B 0%, #c41e2a 100%);
                cursor: pointer;
                transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
                position: relative;
                overflow: hidden;
                letter-spacing: 0.01em;
            }

            .btn-login::before {
                content: '';
                position: absolute;
                top: 0;
                left: -100%;
                width: 100%;
                height: 100%;
                background: linear-gradient(90deg, transparent, rgba(255,255,255,0.15), transparent);
                transition: left 0.5s ease;
            }

            .btn-login:hover {
                transform: translateY(-1px);
                box-shadow: 0 8px 25px rgba(218,45,59,0.35);
            }

            .btn-login:hover::before {
                left: 100%;
            }

            .btn-login:active {
                transform: translateY(0);
                box-shadow: 0 4px 15px rgba(218,45,59,0.25);
            }

            /* ─── Footer ─── */
            .login-footer {
                margin-top: 2.5rem;
                text-align: center;
            }

            .login-footer p {
                font-size: 0.75rem;
                color: #9ca3af;
            }

            /* ─── Responsive Media Queries ─── */

            /* Tablet Landscape / Medium screens (769px - 1024px) */
            @media (max-width: 1024px) and (min-width: 769px) {
                .login-brand {
                    padding: 3rem 2.5rem;
                }

                .brand-greeting {
                    font-size: 1.25rem;
                }

                .brand-title {
                    font-size: 2.25rem;
                }

                .brand-subtitle {
                    font-size: 1rem;
                }

                .login-form-panel {
                    width: 440px;
                    max-width: 50%;
                    padding: 2.5rem 2rem;
                }

                .login-form-container {
                    max-width: 350px;
                }
            }

            /* Tablet Portrait & Mobile (< 768px) */
            @media (max-width: 768px) {
                .login-wrapper {
                    flex-direction: column;
                    min-height: 100vh;
                    min-height: 100dvh;
                }

                .login-brand {
                    flex: 0 0 auto;
                    width: 100%;
                    padding: 2.5rem 1.5rem;
                    min-height: 180px;
                    align-items: center;
                    justify-content: center;
                    text-align: center;
                }

                .brand-content {
                    text-align: center;
                    max-width: 100%;
                }

                .brand-greeting {
                    font-size: 1rem;
                    margin-bottom: 0.25rem;
                }

                .brand-title {
                    font-size: 1.875rem;
                    margin-bottom: 0.25rem;
                }

                .brand-subtitle {
                    font-size: 0.875rem;
                }

                .login-form-panel {
                    width: 100%;
                    max-width: 100%;
                    flex: 1 0 auto;
                    padding: 2.25rem 1.5rem 2rem;
                    justify-content: flex-start;
                }

                .login-form-container {
                    max-width: 400px;
                    margin: 0 auto;
                }

                .form-header {
                    margin-bottom: 1.5rem;
                    text-align: center;
                }

                .form-header h1 {
                    font-size: 1.5rem;
                }

                .form-header p {
                    font-size: 0.8125rem;
                }

                .form-input {
                    font-size: 1rem; /* Prevent iOS Safari auto-zoom on input focus */
                }

                .login-footer {
                    margin-top: 2rem;
                }
            }

            /* Extra Small Mobile (< 480px) */
            @media (max-width: 480px) {
                .login-brand {
                    padding: 2rem 1.25rem 1.75rem;
                    min-height: 150px;
                }

                .brand-greeting {
                    font-size: 0.875rem;
                }

                .brand-title {
                    font-size: 1.625rem;
                }

                .brand-subtitle {
                    font-size: 0.8125rem;
                }

                .login-form-panel {
                    padding: 1.75rem 1.25rem 1.5rem;
                }

                .form-header {
                    margin-bottom: 1.25rem;
                }

                .form-header h1 {
                    font-size: 1.375rem;
                }

                .form-group {
                    margin-bottom: 1rem;
                }

                .remember-row {
                    margin-bottom: 1.25rem;
                }

                .recaptcha-wrapper {
                    margin-bottom: 1.25rem;
                    max-width: 100%;
                    overflow: hidden;
                }

                .recaptcha-wrapper > div {
                    transform: scale(0.85);
                    transform-origin: center center;
                }

                .login-footer {
                    margin-top: 1.5rem;
                }
            }

            /* Very Small Screens (< 360px) */
            @media (max-width: 360px) {
                .login-form-panel {
                    padding: 1.5rem 1rem 1.25rem;
                }

                .recaptcha-wrapper > div {
                    transform: scale(0.78);
                }
            }

            /* Mobile Landscape height handling */
            @media (max-height: 600px) and (orientation: landscape) and (max-width: 900px) {
                .login-wrapper {
                    flex-direction: row;
                }

                .login-brand {
                    padding: 1.5rem;
                    min-height: 100vh;
                    min-height: 100dvh;
                }

                .brand-title {
                    font-size: 1.5rem;
                }

                .brand-greeting {
                    font-size: 0.875rem;
                }

                .brand-subtitle {
                    font-size: 0.75rem;
                }

                .login-form-panel {
                    padding: 1.5rem 1.25rem;
                    width: 50%;
                    max-width: 50%;
                    overflow-y: auto;
                }

                .form-header {
                    margin-bottom: 1rem;
                }

                .form-group {
                    margin-bottom: 0.75rem;
                }

                .login-footer {
                    margin-top: 1rem;
                }
            }
        </style>
    </head>
    <body>
        <x-toast-notifications />
        {{ $slot }}
    </body>
</html>
