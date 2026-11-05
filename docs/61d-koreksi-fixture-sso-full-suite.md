# 61d — Memperbaiki fixture gateway SSO pada full suite

Hasil rescan source: 46 file checkpoint61 sudah diterapkan di folder asli, tujuh file lama sudah dihapus. Keempat test SSO mahasiswa/penolakan login dalam AuthenticationTest belum menyiapkan fake gateway, sedangkan test dosen sudah. Jalur aktual `LoginRequest::authenticateSsoUser()` memanggil `SatuService` ke `/issueauth` dan `/issueprofile`; trait transport memakai Laravel HTTP client. Seed hanya membuat row database, tidak membuat respons HTTP. Konfigurasi `services.sso.mock` tidak dipakai jalur ini.

Karena itu test bernama mock student masih bisa memanggil gateway eksternal; pesan error tidak deterministik dan login dengan password dummy tidak dijamin berhasil. Ini temuan source yang menjelaskan ketergantungan eksternal pada empat test; output dua baris yang diberikan belum membuktikan alasan seluruh92 kegagalan. Jangan menganggap patch ini akan membuat seluruh92 test lulus.

Perbaikan hanya pada fixture test: fake reCAPTCHA/gateway, respons sukses berdasarkan username/password uji,401 untuk kredensial salah, profile mahasiswa, dan larangan request lain yang tidak di-fake. Tidak mengubah login produksi, factory global, role/middleware, atau `.env`. Sembilan test asli serta assertion autentikasi/error tetap dipertahankan. Ada assertion HTTP tambahan untuk membuktikan jalur SSO benar-benar dipakai dan kegagalan kredensial tidak mengambil profil. API mengikuti [Laravel HTTP client testing](https://laravel.com/docs/13.x/http-client#testing).

## 1. Ganti satu file test

Di folder asli, ganti seluruh `tests/Feature/Auth/AuthenticationTest.php` dengan isi di bawah. Tidak perlu membuat file/test baru atau menjalankan migration untuk perubahan fixture ini.

### `tests/Feature/Auth/AuthenticationTest.php`

```php
<?php
use App\Models\User;
use Illuminate\Support\Facades\Http;


use Illuminate\Http\Client\Request as HttpRequest;

beforeEach(function () {
    config(['services.recaptcha.enabled' => true]);
    Http::preventStrayRequests();
    $this->fakeStudentGateway = function () {
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response(['success' => true]),
            '*/issueauth' => function (HttpRequest $request) {
                if ($request['username'] === '1202200001' && $request['password'] === 'password') {
                    return Http::response(['token' => 'test-student-token']);
                }
                return Http::response(['message' => 'Username atau password salah.'], 401);
            },
            '*/issueprofile' => Http::response([
                'user' => '1202200001', 'fullname' => 'KRISNA MAHASISWA',
                'email' => 'krisna@mahasiswa.test', 'phone' => '081234567890',
                'numberid' => '1202200001', 'studyprogram' => 'Informatika',
            ]),
        ]);
    };
});

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('users can authenticate using the login screen', function () {
    Http::fake([
        'https://www.google.com/recaptcha/api/siteverify' => Http::response(['success' => true]),
    ]);

    $user = User::factory()->create(['status' => 'active']);

    $response = $this->post('/auth/login-proses', [
        'username' => $user->email,
        'password' => 'password',
        'g-recaptcha-response' => 'mock-token',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect('/dashboard');
});

test('users can not authenticate with invalid password', function () {
    Http::fake([
        'https://www.google.com/recaptcha/api/siteverify' => Http::response(['success' => true]),
    ]);

    $user = User::factory()->create();

    $this->post('/auth/login-proses', [
        'username' => $user->email,
        'password' => 'wrong-password',
        'g-recaptcha-response' => 'mock-token',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/login');
});

test('sso login works for mock student and syncs to database', function () {
    $this->seed();
    ($this->fakeStudentGateway)();

    $response = $this->post('/auth/login-proses', [
        'username' => '1202200001',
        'password' => 'password',
        'g-recaptcha-response' => 'mock-token',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect('/dashboard');

    $user = Auth::user();
    expect($user->username)->toBe('1202200001');
    expect($user->name)->toBe('KRISNA MAHASISWA');
    expect($user->employee_id)->toBe('1202200001');
    expect($user->hasRole('mahasiswa'))->toBeTrue();
    Http::assertSent(fn (HttpRequest $request) => str_ends_with($request->url(), '/issueauth')
        && $request['username'] === '1202200001' && $request['password'] === 'password');
    Http::assertSent(fn (HttpRequest $request) => str_ends_with($request->url(), '/issueprofile')
        && $request->hasHeader('Authorization', 'Bearer test-student-token'));

});

test('sso login works for mock dosen and syncs to database', function () {
    $this->seed();

    Http::fake([
        'https://www.google.com/recaptcha/api/siteverify' => Http::response(['success' => true]),
        '*/issueauth' => Http::response(['token' => 'dummy-token']),
        '*/issueprofile' => Http::response([
            'user'         => 'krisna_dosen',
            'fullname'     => 'KRISNA DOSEN',
            'email'        => 'krisna@dosen.test',
            'phone'        => '08123456789',
            'numberid'     => '987654321',
            'studyprogram' => null,
        ]),
    ]);

    $response = $this->post('/auth/login-proses', [
        'username' => 'krisna_dosen',
        'password' => 'password',
        'g-recaptcha-response' => 'mock-token',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect('/dashboard');

    $user = Auth::user();
    expect($user->username)->toBe('krisna_dosen');
    expect($user->name)->toBe('KRISNA DOSEN');
    expect($user->employee_id)->toBe('987654321');
    expect($user->hasRole('Petugas'))->toBeTrue();
});

test('sso login fails with wrong password and returns expected error message', function () {
    $this->seed();
    ($this->fakeStudentGateway)();

    $response = $this->post('/auth/login-proses', [
        'username' => '1202200001',
        'password' => 'wrong-password',
        'g-recaptcha-response' => 'mock-token',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors([
        'username' => 'Username atau password salah.',
    ]);
    Http::assertNotSent(fn (HttpRequest $request) => str_ends_with($request->url(), '/issueprofile'));
});

test('sso login fails with unknown username and returns expected error message', function () {
    $this->seed();
    ($this->fakeStudentGateway)();

    $response = $this->post('/auth/login-proses', [
        'username' => 'usertidakada',
        'password' => 'password',
        'g-recaptcha-response' => 'mock-token',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors([
        'username' => 'Username atau password salah.',
    ]);
    Http::assertNotSent(fn (HttpRequest $request) => str_ends_with($request->url(), '/issueprofile'));
});

test('sso login works when user inputs full campus email address', function () {
    $this->seed();
    ($this->fakeStudentGateway)();

    $response = $this->post('/auth/login-proses', [
        'username' => '1202200001@student.telkomuniversity.ac.id',
        'password' => 'password',
        'g-recaptcha-response' => 'mock-token',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect('/dashboard');

    $user = Auth::user();
    expect($user->username)->toBe('1202200001');
    expect($user->hasRole('mahasiswa'))->toBeTrue();
});
```

## 2. Jalankan fokus autentikasi

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/Auth/AuthenticationTest.php
```

Jika masih gagal, kirim nama test, **pesan assertion lengkap sebelum potongan kode**, dan baris yang ditunjuk. Nama test/baris saja tidak menunjukkan response/session error aktual.

## 3. Pisahkan hasil Sidang TA dan full suite

Pastikan hasil perintah khusus SidangTa dan ketiga Node test pada langkah6 diketahui. Jika kelompok itu lulus, berarti ada bukti regresi TA yang terpisah dari full suite. Jangan menyatakan semua test proyek lulus berdasarkan kelompok TA saja.

Sesudah AuthenticationTest lulus, jalankan full suite dengan berhenti pada kegagalan pertama supaya output lebih singkat:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test --stop-on-failure
```

Kirim satu blok FAILED pertama lengkap dari hasil itu untuk diagnosis modul berikutnya. Cache hasil PHPUnit memuat92 defect non-TA (Auth, Jadwal Ujian, Yudisium, Pengelolaan Password, Profil, Dokumen Publik, Antrean, Sinkronisasi), tetapi cache bersifat historis dan bukan laporan run terkini. Jangan mengubah assertion/role/permission modul lain hanya berdasarkan angka tersebut.

Tetap pada tahap6; reset data aplikasi dan salin ke dadu-test dilakukan setelah persoalan test yang relevan diselesaikan. Nanti tambahkan `tests/Feature/Auth/AuthenticationTest.php` ke daftar file salin46 checkpoint61 sehingga menjadi47 file. Jangan menyalin file ini sekarang saat pengujian masih berlangsung.

## Rescan sesudah penulisan panduan

Source kedua folder diverifikasi tidak berubah selama penulisan dokumen ini. PHP syntax lint pada file test usulan lulus, semua9 nama test asli tetap ada. Unit test database dan browser tidak dijalankan oleh asisten; hasilnya harus diperoleh dari perintah pengguna di atas.
