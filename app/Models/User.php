<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'username',
        'name',
        'email',
        'password',
        'status',
        'employee_id',
        'phone',
        'photo',
        'auth_source',
        'sso_role',
        'must_change_password',
        'unit_id',
        'position',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
        ];
    }

    /**
     * Cek apakah akun ini adalah akun manual (bukan SSO).
     */
    public function isManualAccount(): bool
    {
        return $this->auth_source === 'manual';
    }

    /**
     * Cek apakah akun ini adalah akun SSO.
     */
    public function isSsoAccount(): bool
    {
        return $this->auth_source === 'sso';
    }

    /**
     * Profil user
     */
    public function toProfileArray(): array
    {
        $roleName = $this->getRoleNames()->first();

        return [
            'id'       => $this->id,
            'fullname' => $this->name,
            'username' => $this->username,
            'email'    => $this->email,
            'phone'    => $this->phone,
            'photo'    => $this->photo,
            'nim'      => $this->employee_id,
            'role_id'  => self::ROLE_ID_MAP[$roleName] ?? null,
            'unit_id'  => $this->unit_id,
        ];
    }

    // 101 → mahasiswa  (Pengguna: pengaju layanan / mahasiswa)
    // 102 → Petugas    (Front Desk / operator loket SSO auto-assign)
    // 103 → Pelaksana  (Staf unit kerja teknis)
    // 104 → Pemimpin   (Kepala unit / pimpinan struktural)
    // 105 → Pengamat   (Rektorat / monitoring read-only)
    // 106 → Akademik   (Superadmin / full access)
    // ---  → admin     (Administrator operasional, di bawah Akademik)

    public const ROLE_ID_MAP = [
        'mahasiswa' => '101',
        'Petugas'   => '102',
        'Pelaksana' => '103',
        'Pemimpin'  => '104',
        'Pengamat'  => '105',
        'Akademik'  => '106',
        'admin'     => '106', 
    ];

    public const ROLE_LABEL_MAP = [
        'mahasiswa' => 'Mahasiswa',
        'Petugas'   => 'Petugas',
        'Pelaksana' => 'Pelaksana',
        'Pemimpin'  => 'Pimpinan',
        'Pengamat'  => 'Pengamat',
        'Akademik'  => 'Akademik',
        'admin'     => 'Admin',
    ];

    /**
     * Urutan prioritas role (tertinggi → terendah).
     */
    public const ROLE_PRIORITY = ['Akademik', 'admin', 'Pelaksana', 'Pemimpin', 'Pengamat', 'Petugas', 'mahasiswa'];

    /**
     * Cek apakah user ini adalah Superadmin (role Akademik).
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('Akademik');
    }

    /**
     * Semua role yang DIMILIKI user (query langsung ke DB), terlepas dari
     * role aktif yang sedang dipakai pada sesi ini.
     *
     * @return array<int, string>
     */
    public function assignedRoleNames(): array
    {
        $names = $this->roles()->pluck('name')->all();

        usort($names, function ($a, $b) {
            $pa = array_search($a, self::ROLE_PRIORITY, true);
            $pb = array_search($b, self::ROLE_PRIORITY, true);
            return ($pa === false ? 99 : $pa) <=> ($pb === false ? 99 : $pb);
        });

        return $names;
    }

    public function canGrantRole(string $roleName): bool
    {
        if ($this->hasRole('Akademik')) {
            return true;
        }

        if ($roleName === 'Akademik') {
            return false;
        }

        $role = \Spatie\Permission\Models\Role::where('name', $roleName)->first();
        if (! $role) {
            return false;
        }

        foreach ($role->permissions->pluck('name') as $permission) {
            if (! $this->hasPermissionTo($permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Role aktif default: role dengan prioritas tertinggi.
     */
    public function defaultActiveRole(): ?string
    {
        return $this->assignedRoleNames()[0] ?? null;
    }

    /**
     * Daftar role yang hanya bisa di-assign secara manual (bukan via SSO auto-assign).
     */
    public static function manualRoles(): array
    {
        return ['Akademik', 'admin', 'Pelaksana', 'Pemimpin', 'Pengamat'];
    }

    /**
     * Kembalikan Role ID numerik SSC dari role Spatie user saat ini.
     * Mengembalikan ID dari role dengan prioritas tertinggi jika memiliki lebih dari 1 role.
     */
    public function roleId(): ?string
    {
        $priority = self::ROLE_PRIORITY;
        foreach ($priority as $roleName) {
            if ($this->hasRole($roleName)) {
                return self::ROLE_ID_MAP[$roleName] ?? null;
            }
        }
        $roleName = $this->getRoleNames()->first();
        return self::ROLE_ID_MAP[$roleName] ?? null;
    }

    /**
     * Cek apakah role user sesuai dengan salah satu Role ID SSC yang diberikan.
     * Memeriksa seluruh role yang dimiliki user.
     */
    public function hasRoleId(string ...$roleIds): bool
    {
        foreach ($this->getRoleNames() as $roleName) {
            $id = self::ROLE_ID_MAP[$roleName] ?? null;
            if ($id !== null && in_array($id, $roleIds, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Kembalikan label deskriptif SSC dari role user saat ini.
     */
    public function roleLabel(): string
    {
        $roleName = $this->getRoleNames()->first();
        return self::ROLE_LABEL_MAP[$roleName] ?? ucfirst($roleName ?? 'Pengguna');
    }

    public function activeQueueCounter()
    {
        return $this->hasOne(QueueCounter::class, 'active_user_id');
    }

    /**
     * Unit kerja penugasan pegawai (untuk role Pelaksana / Pemimpin).
     */
    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
}
