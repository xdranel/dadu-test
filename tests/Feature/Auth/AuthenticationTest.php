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
