<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Services\SatuService;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = [
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];

        if (config('services.recaptcha.enabled')) {
            $rules['g-recaptcha-response'] = ['required', 'string'];
        }

        return $rules;
    }

    /**
     * Get validation messages.
     */
    public function messages(): array
    {
        return [
            'username.required' => 'Username/Email wajib diisi.',
            'password.required' => 'Password wajib diisi.',
            'g-recaptcha-response.required' => 'Mohon centang kotak verifikasi reCAPTCHA.',
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (config('services.recaptcha.enabled')) {
            $this->verifyRecaptcha();
        }

        $loginInput = $this->username;
        $isEmail = str_contains($loginInput, '@');

        if (config('app.env') === 'local' && !config('services.sso.use_in_local')) {
            $this->authenticateLocalUser($loginInput, $isEmail);
            return;
        }

        $manualUser = User::where('auth_source', 'manual')
            ->where(function ($query) use ($loginInput) {
                $query->where('username', $loginInput)
                      ->orWhere('email', $loginInput);
            })
            ->first();

        if ($manualUser) {
            $this->authenticateManualUser($manualUser, $loginInput, $isEmail);
            return;
        }
        $this->authenticateSsoUser();
    }

    /**
     * Autentikasi untuk environment LOCAL (hanya menggunakan database lokal tanpa SSO gateway)
     *
     * @param string $loginInput
     * @param bool $isEmail
     * @throws ValidationException
     */
    private function authenticateLocalUser(string $loginInput, bool $isEmail): void
    {
        $usernameWithoutDomain = explode('@', $loginInput)[0];

        $user = User::where(function ($query) use ($loginInput, $usernameWithoutDomain) {
            $query->where('username', $loginInput)
                  ->orWhere('username', $usernameWithoutDomain)
                  ->orWhere('email', $loginInput);
        })->first();

        if (!$user) {
            Log::info('Login lokal gagal - akun tidak ditemukan di database lokal', [
                'username' => $loginInput,
                'ip' => $this->ip(),
            ]);

            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'username' => 'Akun tidak ditemukan di database lokal. Pastikan user sudah terdaftar di seeder/database lokal.',
            ]);
        }

        if ($user->status !== 'active') {
            Log::warning('Login lokal ditolak - akun non-aktif', [
                'user_id' => $user->id,
                'username' => $user->username,
            ]);

            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'username' => 'Akun Anda sedang tidak aktif. Silakan hubungi administrator.',
            ]);
        }

        $credentials = [
            'username' => $user->username,
            'password' => $this->password,
        ];

        if (!Auth::attempt($credentials, $this->boolean('remember'))) {
            Log::info('Login lokal gagal - password salah', [
                'username' => $loginInput,
                'ip' => $this->ip(),
            ]);

            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'username' => 'Username atau password salah.',
            ]);
        }

        Log::info('Login lokal berhasil', [
            'user_id' => $user->id,
            'username' => $user->username,
            'role' => $user->getRoleNames()->first() ?? 'none',
            'ip' => $this->ip(),
        ]);

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Verifikasi reCAPTCHA dengan Google
     *
     * @throws ValidationException
     */
    private function verifyRecaptcha(): void
    {
        try {
            $googleResponse = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => config('services.recaptcha.secret_key'),
                'response' => $this->input('g-recaptcha-response'),
                'remoteip' => $this->ip(),
            ]);

            if (!$googleResponse->successful() || !$googleResponse->json('success')) {
                Log::warning('reCAPTCHA verification failed', [
                    'ip' => $this->ip(),
                    'username' => $this->username,
                ]);
                
                RateLimiter::hit($this->throttleKey());
                
                throw ValidationException::withMessages([
                    'g-recaptcha-response' => 'Verifikasi reCAPTCHA gagal, silakan coba lagi.',
                ]);
            }
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('reCAPTCHA service error', [
                'error' => $e->getMessage(),
                'ip' => $this->ip(),
            ]);
            
            RateLimiter::hit($this->throttleKey());
            
            throw ValidationException::withMessages([
                'g-recaptcha-response' => 'Layanan verifikasi tidak tersedia. Silakan coba lagi.',
            ]);
        }
    }

    /**
     * Autentikasi akun MANUAL (lokal database)
     *
     * @param User $manualUser
     * @param string $loginInput
     * @param bool $isEmail
     * @throws ValidationException
     */
    private function authenticateManualUser(User $manualUser, string $loginInput, bool $isEmail): void
    {
        if ($manualUser->status !== 'active') {
            Log::warning('Login ditolak - akun manual non-aktif', [
                'user_id' => $manualUser->id,
                'username' => $manualUser->username,
                'email' => $manualUser->email,
            ]);

            RateLimiter::hit($this->throttleKey());
            
            throw ValidationException::withMessages([
                'username' => 'Akun Anda sedang tidak aktif. Silakan hubungi administrator.',
            ]);
        }

        $credentials = $isEmail
            ? ['email' => $loginInput, 'password' => $this->password]
            : ['username' => $loginInput, 'password' => $this->password];
        if (!Auth::attempt($credentials, $this->boolean('remember'))) {
            Log::info('Login manual gagal - kredensial salah', [
                'username' => $loginInput,
                'ip' => $this->ip(),
            ]);

            RateLimiter::hit($this->throttleKey());
            
            throw ValidationException::withMessages([
                'username' => 'Username atau password salah.',
            ]);
        }

        // Autentikasi berhasil
        Log::info('Login manual berhasil', [
            'user_id' => $manualUser->id,
            'username' => $manualUser->username,
            'email' => $manualUser->email,
            'role' => $manualUser->getRoleNames()->first() ?? 'none',
            'ip' => $this->ip(),
        ]);

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Autentikasi akun SSO via Gateway Telkom University
     *
     * Endpoint:
     *   POST /issueauth  → mendapatkan Bearer Token
     *   GET  /issueprofile (Bearer token) → mendapatkan data profil
     *
     * Payload profil SSO: user, numberid, fullname, email, phone, studyprogram, faculty
     *
     * @throws ValidationException
     */
    private function authenticateSsoUser(): void
    {
        $data = [
            'username' => explode('@', $this->username)[0],
            'password' => $this->password,
        ];

        $satuService = new SatuService();
        $login = $satuService->login($data);

        if (!isset($login->token)) {
            Log::warning('Login SSO gagal - token tidak diterima', [
                'username' => $data['username'],
                'message'  => $login->message ?? 'no message',
                'ip'       => $this->ip(),
            ]);

            RateLimiter::hit($this->throttleKey());
            throw ValidationException::withMessages([
                'username' => $login->message ?? 'Username atau password SSO tidak valid.',
            ]);
        }

        $profile = $satuService->profile($login->token);

        if (empty($profile->user) && empty($profile->numberid)) {
            Log::error('Login SSO gagal - profil tidak valid', [
                'username' => $data['username'],
                'ip'       => $this->ip(),
            ]);

            RateLimiter::hit($this->throttleKey());
            throw ValidationException::withMessages([
                'username' => 'Gagal mengambil data profil dari SSO Telkom University.',
            ]);
        }

        $defaultSsoRole = (isset($profile->studyprogram) && !empty($profile->studyprogram))
            ? 'mahasiswa'
            : 'Petugas';

        $user = User::where('username', $profile->user)->first();

        if ($user) {
            if ($user->status !== 'active') {
                Log::warning('Login SSO ditolak - akun non-aktif', [
                    'user_id'  => $user->id,
                    'username' => $user->username,
                    'email'    => $user->email,
                ]);

                RateLimiter::hit($this->throttleKey());

                throw ValidationException::withMessages([
                    'username' => 'Akun Anda sedang tidak aktif. Silakan hubungi administrator.',
                ]);
            }

            $user->update([
                'name'                => $profile->fullname ?? $user->name,
                'email'               => $profile->email ?? $user->email,
                'phone'               => $profile->phone ?? $user->phone,
                'employee_id'         => $profile->numberid ?? $user->employee_id,
                'photo'               => $profile->photo ?? $user->photo,
                'password'            => Hash::make(Str::random(32)),
                'must_change_password'=> false,
            ]);

            if ($user->roles()->count() === 0) {
                $user->assignRole($defaultSsoRole);
                $user->update(['sso_role' => $defaultSsoRole]);
            }
        } else {
            $user = User::create([
                'username'            => $profile->user,
                'name'                => $profile->fullname ?? $profile->user,
                'email'               => $profile->email ?? ($profile->user . '@telkomuniversity.ac.id'),
                'phone'               => $profile->phone ?? null,
                'employee_id'         => $profile->numberid ?? null,
                'photo'               => $profile->photo ?? null,
                'password'            => Hash::make(Str::random(32)),
                'auth_source'         => 'sso',
                'sso_role'            => $defaultSsoRole,
                'status'              => 'active',
                'must_change_password'=> false,
            ]);

            $user->assignRole($defaultSsoRole);
        }

        $this->syncStudentFromProfile($profile);

        Log::info('Login SSO berhasil', [
            'user_id'    => $user->id,
            'username'   => $user->username,
            'email'      => $user->email,
            'roles'      => $user->getRoleNames()->toArray(),
            'unit_id'    => $user->unit_id,
            'ip'         => $this->ip(),
        ]);

        Auth::login($user, $this->boolean('remember'));
        RateLimiter::clear($this->throttleKey());
    }
    /**
     * Sinkronkan data mahasiswa (foto, prodi, fakultas) dari profil SSO ke tabel students.
     * Kegagalan sinkronisasi tidak boleh menggagalkan login.
     */
    private function syncStudentFromProfile(object $profile): void
    {
        if (empty($profile->studyprogram) || empty($profile->numberid)) {
            return;
        }

        try {
            $student = \App\Models\Student::firstOrNew(['student_number' => $profile->numberid]);
            $student->name = $profile->fullname ?? $student->name;
            $student->email = $profile->email ?? $student->email;
            $student->study_program = $profile->studyprogram;
            $student->faculty = $profile->faculty ?? $student->faculty;
            if (!$student->exists) {
                $student->status = 'Aktif';
            }
            $student->save();
        } catch (\Throwable $e) {
            Log::warning('Sinkronisasi data mahasiswa dari SSO gagal', [
                'numberid' => $profile->numberid,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (!RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'username' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        $username = Str::lower(trim($this->string('username')));
        
        // Normalize email if contains @
        if (str_contains($username, '@')) {
            $username = filter_var($username, FILTER_SANITIZE_EMAIL);
        }
        
        return hash('sha256', $username . '|' . $this->ip());
    }
}
