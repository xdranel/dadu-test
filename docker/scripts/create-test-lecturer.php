<?php

// Explicit staging operation. Read the private account JSON from stdin; never seed on boot.
use App\Models\Lecturer;
use App\Models\User;
use App\Services\SidangTaProgramAccess;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

try {
    $options = getopt('', ['check', 'apply']);
    if (isset($options['check']) === isset($options['apply'])) {
        throw new RuntimeException('Pilih tepat satu: --check atau --apply; JSON akun privat melalui stdin.');
    }
    $account = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
    if (($account['email'] ?? '') !== 'dosen.ta@dosen.test'
        || ($account['username'] ?? '') !== 'dosen-ta-test'
        || !is_string($account['password'] ?? null) || strlen($account['password']) < 12
        || !is_string($account['study_program'] ?? null) || trim($account['study_program']) === '') {
        throw new RuntimeException('Identitas dummy atau konfigurasi password/prodi tidak valid.');
    }
    require getcwd().'/vendor/autoload.php';
    $app = require getcwd().'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    if (!$app->environment('staging')) throw new RuntimeException('Hanya untuk APP_ENV=staging; database lokal/production tidak disentuh.');
    $role = Role::where('name', 'Pelaksana')->where('guard_name', 'web')->sole();
    $required = ['sidang_ta_registration_read', SidangTaProgramAccess::PERMISSION];
    foreach ($required as $permission) Permission::findByName($permission, 'web');
    if (!$role->hasPermissionTo(SidangTaProgramAccess::PERMISSION)) {
        throw new RuntimeException('Aktifkan Aksi Khusus Lihat Pengajuan (Prodi Dosen) pada role Pelaksana dahulu.');
    }
    $unsafe = $role->permissions->filter(fn ($p) => str_starts_with($p->name, 'sidang_ta_registration_') && !in_array($p->name, $required, true));
    if ($unsafe->isNotEmpty()) throw new RuntimeException('Role Pelaksana masih memiliki aksi tulis/review Sidang TA; periksa role dahulu. Script tidak mengubah role global.');
    $result = DB::transaction(function () use ($account, $role, $required, $options) {
        $roleSnapshot = DB::table('role_has_permissions')->orderBy('role_id')->orderBy('permission_id')->get()->toJson();
        $users = User::where('email', $account['email'])->orWhere('username', $account['username'])->lockForUpdate()->get();
        $lecturers = Lecturer::where('email_domain', $account['email'])->orWhere('lecturer_code', 'DTEST115')->lockForUpdate()->get();
        if ($users->count() > 1 || $lecturers->count() > 1) throw new RuntimeException('Identitas berbenturan; tidak ada data yang diubah.');
        $user = $users->first();
        $lecturer = $lecturers->first();
        if ($user && ($user->email !== $account['email'] || $user->username !== $account['username']
            || $user->name !== 'Dosen Simulasi Sidang TA' || $user->auth_source !== 'manual'
            || $user->getRoleNames()->sort()->values()->all() !== ['Pelaksana']
            || !Hash::check($account['password'], $user->password))) {
            throw new RuntimeException('Akun sudah ada dengan identitas/role/password berbeda; tidak ditimpa.');
        }
        if ($lecturer && ($lecturer->email_domain !== $account['email'] || $lecturer->lecturer_code !== 'DTEST115'
            || $lecturer->name !== 'Dosen Simulasi Sidang TA' || $lecturer->study_program !== $account['study_program'])) {
            throw new RuntimeException('Data Dosen sudah ada dengan identitas/prodi berbeda; tidak ditimpa.');
        }
        if (isset($options['check'])) return ['mode' => 'check', 'user_exists' => (bool) $user, 'lecturer_exists' => (bool) $lecturer, 'role_global_unchanged' => true];
        $createdUser = !$user;
        $createdLecturer = !$lecturer;
        if (!$user) {
            $user = User::create(['name' => 'Dosen Simulasi Sidang TA', 'username' => $account['username'],
                'email' => $account['email'], 'password' => Hash::make($account['password']),
                'employee_id' => 'DTEST115', 'auth_source' => 'manual', 'status' => 'active', 'must_change_password' => false]);
            $user->assignRole($role);
        }
        if (!$lecturer) {
            $lecturer = Lecturer::create(['name' => 'Dosen Simulasi Sidang TA', 'lecturer_code' => 'DTEST115',
                'email_domain' => $account['email'], 'study_program' => $account['study_program'],
                'active_status' => 'Aktif', 'employment_status' => 'Tetap', 'is_external' => false]);
        }
        // Keep the special action inherited from Pelaksana so its role checkbox remains testable.
        if ($user->hasDirectPermission(SidangTaProgramAccess::PERMISSION)) {
            throw new RuntimeException('Aksi Khusus harus berasal dari role, bukan permission langsung akun.');
        }
        $user->givePermissionTo('sidang_ta_registration_read');
        $user = $user->fresh();
        if (!$user->can($required[0]) || !$user->can($required[1]) || $user->can('sidang_ta_registration_review')
            || $user->can('sidang_ta_registration_create') || $user->can('sidang_ta_registration_update')
            || app(SidangTaProgramAccess::class)->programFor($user) !== $account['study_program']) {
            throw new RuntimeException('Pemeriksaan akses baca/prodi gagal; transaksi dibatalkan.');
        }
        if ($roleSnapshot !== DB::table('role_has_permissions')->orderBy('role_id')->orderBy('permission_id')->get()->toJson()) {
            throw new RuntimeException('Permission role global berubah; transaksi dibatalkan.');
        }
        return ['mode' => 'apply', 'user_created' => $createdUser, 'lecturer_created' => $createdLecturer,
            'email' => $user->email, 'username' => $user->username, 'role' => 'Pelaksana',
            'study_program' => $lecturer->study_program, 'lecturer_code' => $lecturer->lecturer_code,
            'direct_permissions' => $user->getDirectPermissions()->pluck('name')->sort()->values()->all(),
            'can_review_ta' => false, 'role_global_unchanged' => true];
    });
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage().PHP_EOL);
    exit(1);
}
