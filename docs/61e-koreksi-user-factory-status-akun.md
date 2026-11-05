# 61e — Factory akun aktif untuk test yang memakai actingAs

## Hasil penelusuran

Test `email verification screen can be rendered` membuat `$user = User::factory()->unverified()->create()` lalu langsung `actingAs($user)`. Factory lama hanya memberi name, email, email_verified_at, password dan remember_token. Ia tidak memberi status/auth_source. Database memiliki default `status=active` dan `auth_source=manual`, tetapi nilai default database tidak otomatis hadir pada objek hasil create yang sama. Pada source Eloquent yang dipakai, performInsert hanya memuat ulang kolom dalam daftar refreshes; User tidak mengatur daftar tersebut.

Middleware `ApplyActiveRole` memeriksa `$user->status !== 'active'`, melakukan logout dan redirect ke login bila tidak aktif. Maka objek factory tanpa attribute status akan ditolak sebelum controller verifikasi email menerima request. Ini menjelaskan jalur redirect pada fixture tersebut; output pengguna baru menyertakan baris assertStatus, jadi bila setelah patch masih gagal, kirim juga pesan status/exception aktual (misalnya expected200/received302).

Pemeriksaan objek model tanpa koneksi DB membuktikan attribute status/auth_source yang tidak diisi bernilai null; setelah diisi eksplisit menjadi active/manual. Perbaikan di sini menyamakan default factory dengan default database agar objek yang dipakai actingAs konsisten. Tidak ada pengubahan middleware atau pelonggaran hak akses; factory juga tidak memberikan role/permission. Akun inactive/SSO/force-password-change tetap bisa dibuat melalui override pada masing-masing test. API override mengikuti [dokumentasi factory Laravel](https://laravel.com/docs/13.x/eloquent-factories#instantiating-models).

## 1. Ganti satu file yang sudah ada

Di folder asli `/home/xdranel/Code/Project/php-laravel/dadu`, ganti seluruh `database/factories/UserFactory.php` dengan berikut. Tidak perlu generator, migration, seed/reset, atau mengganti model User.

### `database/factories/UserFactory.php`

```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'status' => 'active',
            'auth_source' => 'manual',
            'must_change_password' => false,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
```

Tiga attribute baru:

- `status=active`: fixture boleh melewati pemeriksaan akun aktif.
- `auth_source=manual`: sama dengan default schema untuk insert tanpa override.
- `must_change_password=false`: sama dengan default schema, agar fixture akun normal eksplisit.

`unverified()` tetap hanya mengosongkan email_verified_at. Jangan menghapus state itu atau mengubah status assertion200. Contoh override test negatif yang tetap didukung: `User::factory()->create(['status' => 'inactive'])`; untuk SSO gunakan `['auth_source' => 'sso']`. Factory tidak menetapkan username agar test yang memerlukan username tertentu tetap dapat mengisinya sendiri.

## 2. Jalankan dua file Auth dahulu

Pertahankan koreksi SSO61d yang sudah diterapkan.

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/Auth/EmailVerificationTest.php tests/Feature/Auth/AuthenticationTest.php
```

Jika masih gagal, kirim blok yang memuat **Expected response status... / pesan exception**, nama test, dan potongan kode. Potongan baris assertion saja tidak memuat respons sebenarnya.

## 3. Periksa dampak factory bersama

Karena factory dipakai banyak test, jalankan kembali test Sidang TA, kemudian full suite berhenti pada kegagalan berikutnya:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTa*Test.php tests/Unit/SidangTaAdmissionPolicyTest.php tests/Unit/SidangTaMeetRequirementsTest.php
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test --stop-on-failure
```

Belum perlu mengubah test modul lain sebelum hasil berikutnya diketahui. Setelah tidak ada lagi kegagalan pertama, jalankan full suite tanpa stop-on-failure untuk mencatat total final. Jangan menganggap semua sisa kegagalan pasti berasal dari factory; beberapa modul mempunyai fixture role/kontrak yang perlu diperiksa terpisah bila masih gagal.

## Salin setelah seluruh test/manual selesai

Tambahkan `database/factories/UserFactory.php` ke daftar salin akhir. Dengan tambahan AuthenticationTest61d, paket yang akan disalin menjadi48 file (46 checkpoint61 +1 AuthenticationTest +1 UserFactory), tanpa menghitung tujuh file retire. Jangan salin saat diagnosis belum selesai.

## Verifikasi panduan

Source di dua folder di-hash sebelum dan sesudah penulisan setiap dokumen; tidak ada source yang ditulis asisten. PHP syntax lint file usulan lulus. Pemeriksaan model dilakukan tanpa bootstrap aplikasi dan tanpa DB. Unit test database tetap perlu dijalankan pengguna memakai perintah di atas.
