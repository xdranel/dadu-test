<?php

namespace App\Support;

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Akses terpusat ke config/permission_modules.php.
 */
class PermissionRegistry
{
    public static function standardActions(): array
    {
        return config('permission_modules.standard_actions', []);
    }

    /**
     * Modul ternormalisasi: tiap aksi memiliki 'permission' dan 'label'.
     *
     * @return array<string, array>
     */
    public static function modules(): array
    {
        $standard = self::standardActions();
        $result = [];

        foreach (config('permission_modules.modules', []) as $key => $module) {
            $actions = [];
            foreach ($module['actions'] ?? [] as $action => $meta) {
                $actions[$action] = [
                    'permission' => $meta['permission'] ?? "{$key}_{$action}",
                    'label'      => $meta['label'] ?? ($standard[$action] ?? ucfirst($action)),
                    'custom'     => ! isset($standard[$action]),
                ];
            }
            $result[$key] = [
                'label'     => $module['label'],
                'group'     => $module['group'] ?? 'Lainnya',
                'sensitive' => (bool) ($module['sensitive'] ?? false),
                'actions'   => $actions,
            ];
        }

        return $result;
    }

    /** Semua nama permission yang boleh dikelola lewat halaman Edit Role. */
    public static function allNames(): array
    {
        $names = [];
        foreach (self::modules() as $module) {
            foreach ($module['actions'] as $a) {
                $names[] = $a['permission'];
            }
        }

        return array_values(array_unique($names));
    }

    /** Permission sensitif (IT PANEL). */
    public static function sensitiveNames(): array
    {
        $names = [];
        foreach (self::modules() as $module) {
            if ($module['sensitive']) {
                foreach ($module['actions'] as $a) {
                    $names[] = $a['permission'];
                }
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * Permission dari registry yang boleh DIUBAH (diberi/dicabut) oleh $actor.
     * - Akademik (superadmin): semuanya.
     * - Lainnya: hanya yang tidak sensitif DAN dimiliki sendiri (tidak boleh memberi
     *   hak yang lebih tinggi dari kewenangannya).
     */
    public static function manageableBy(User $actor): array
    {
        $all = self::allNames();

        if ($actor->hasRole('Akademik')) {
            return $all;
        }

        $sensitive = self::sensitiveNames();

        return array_values(array_filter($all, fn ($p) => ! in_array($p, $sensitive, true) && $actor->hasPermissionTo($p)));
    }

    /**
     * Pastikan seluruh permission registry ada di DB (idempotent, tidak menduplikasi
     * dan tidak mengubah assignment ke role mana pun).
     */
    public static function ensureExist(string $guard = 'web'): void
    {
        $existing = Permission::where('guard_name', $guard)->pluck('name')->all();
        $missing = array_diff(self::allNames(), $existing);

        if ($missing) {
            foreach ($missing as $name) {
                Permission::firstOrCreate(['name' => $name, 'guard_name' => $guard]);
            }
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
}
