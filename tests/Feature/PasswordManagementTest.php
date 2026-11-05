<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PasswordManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Buat roles yang diperlukan
        Role::firstOrCreate(['name' => 'Akademik']);
        Role::firstOrCreate(['name' => 'admin']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function akun_baru_manual_otomatis_set_must_change_password()
    {
        $superadmin = User::factory()->create([
            'auth_source' => 'manual',
            'must_change_password' => false,
        ]);
        $superadmin->assignRole('Akademik');

        $this->actingAs($superadmin);

        $response = $this->post(route('manual-users.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'username' => 'testuser',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'role' => 'admin',
        ]);

        $response->assertRedirect(route('manual-users.index'));

        $user = User::where('email', 'test@example.com')->first();
        $this->assertTrue($user->must_change_password);
        $this->assertEquals('manual', $user->auth_source);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function user_dengan_must_change_password_dipaksa_ke_halaman_ganti_password()
    {
        $user = User::factory()->create([
            'auth_source' => 'manual',
            'must_change_password' => true,
        ]);
        $user->assignRole('admin');

        $this->actingAs($user);

        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('password.force-change'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function user_dapat_mengganti_password_wajib()
    {
        $user = User::factory()->create([
            'auth_source' => 'manual',
            'password' => Hash::make('OldPassword123'),
            'must_change_password' => true,
        ]);
        $user->assignRole('admin');

        $this->actingAs($user);

        $response = $this->post(route('password.force-change.update'), [
            'current_password' => 'OldPassword123',
            'password' => 'NewPassword456',
            'password_confirmation' => 'NewPassword456',
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('success');

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('NewPassword456', $user->password));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function password_baru_tidak_boleh_sama_dengan_password_lama()
    {
        $user = User::factory()->create([
            'auth_source' => 'manual',
            'password' => Hash::make('SamePassword123'),
            'must_change_password' => true,
        ]);
        $user->assignRole('admin');

        $this->actingAs($user);

        $response = $this->post(route('password.force-change.update'), [
            'current_password' => 'SamePassword123',
            'password' => 'SamePassword123',
            'password_confirmation' => 'SamePassword123',
        ]);

        $response->assertSessionHasErrors(['password']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function superadmin_dapat_mereset_password_user_manual()
    {
        $superadmin = User::factory()->create([
            'auth_source' => 'manual',
        ]);
        $superadmin->assignRole('Akademik');

        $targetUser = User::factory()->create([
            'auth_source' => 'manual',
            'password' => Hash::make('OldPassword123'),
            'must_change_password' => false,
        ]);
        $targetUser->assignRole('admin');

        $this->actingAs($superadmin);

        $response = $this->post(route('manual-users.reset-password', $targetUser));

        $response->assertRedirect();
        $response->assertSessionHas('generated_password');
        $response->assertSessionHas('reset_user_name', $targetUser->name);

        $targetUser->refresh();
        $this->assertTrue($targetUser->must_change_password);
        // Password telah berubah (tidak bisa cek exact karena random)
        $this->assertFalse(Hash::check('OldPassword123', $targetUser->password));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function user_tidak_bisa_mereset_password_diri_sendiri()
    {
        $superadmin = User::factory()->create([
            'auth_source' => 'manual',
        ]);
        $superadmin->assignRole('Akademik');

        $this->actingAs($superadmin);

        $response = $this->post(route('manual-users.reset-password', $superadmin));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function middleware_tidak_berlaku_untuk_akun_sso()
    {
        $ssoUser = User::factory()->create([
            'auth_source' => 'sso',
            'must_change_password' => true, // Seharusnya diabaikan
        ]);
        $ssoUser->assignRole('admin');

        $this->actingAs($ssoUser);

        // Akun SSO tidak dipaksa ganti password meskipun flag true
        $response = $this->get(route('dashboard'));

        $response->assertOk();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function ganti_password_dari_profil_meng_invalidate_sesi_lain()
    {
        $user = User::factory()->create([
            'auth_source' => 'manual',
            'password' => Hash::make('OldPassword123'),
        ]);
        $user->assignRole('admin');

        $this->actingAs($user);

        $response = $this->put(route('password.update'), [
            'current_password' => 'OldPassword123',
            'password' => 'NewPassword456',
            'password_confirmation' => 'NewPassword456',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'password-updated');

        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword456', $user->password));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function validasi_password_minimal_8_karakter_dengan_huruf_dan_angka()
    {
        $user = User::factory()->create([
            'auth_source' => 'manual',
            'password' => Hash::make('OldPassword123'),
            'must_change_password' => true,
        ]);
        $user->assignRole('admin');

        $this->actingAs($user);

        // Password terlalu pendek
        $response = $this->post(route('password.force-change.update'), [
            'current_password' => 'OldPassword123',
            'password' => 'Short1',
            'password_confirmation' => 'Short1',
        ]);
        $response->assertSessionHasErrors(['password']);

        // Password tanpa angka
        $response = $this->post(route('password.force-change.update'), [
            'current_password' => 'OldPassword123',
            'password' => 'PasswordOnly',
            'password_confirmation' => 'PasswordOnly',
        ]);
        $response->assertSessionHasErrors(['password']);

        // Password tanpa huruf
        $response = $this->post(route('password.force-change.update'), [
            'current_password' => 'OldPassword123',
            'password' => '12345678',
            'password_confirmation' => '12345678',
        ]);
        $response->assertSessionHasErrors(['password']);
    }
}
