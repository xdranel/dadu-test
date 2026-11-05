<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model untuk menyimpan konfigurasi permission per module yang bisa diatur oleh super admin.
 * Table ini khusus untuk module-level permission yang configurable via UI.
 */
class ModuleRolePermission extends Model
{
    protected $fillable = [
        'module_name',
        'role_name',
        'can_create',
        'can_read',
        'can_update',
        'can_delete',
        'additional_rules',
    ];

    protected $casts = [
        'can_create' => 'boolean',
        'can_read' => 'boolean',
        'can_update' => 'boolean',
        'can_delete' => 'boolean',
        'additional_rules' => 'array',
    ];

    /**
     * Cek apakah role memiliki akses untuk action tertentu pada module.
     */
    public static function hasAccess(string $moduleName, string $roleName, string $action): bool
    {
        $permission = self::where('module_name', $moduleName)
            ->where('role_name', $roleName)
            ->first();

        if (!$permission) {
            return false;
        }

        return match ($action) {
            'create' => $permission->can_create,
            'read' => $permission->can_read,
            'update' => $permission->can_update,
            'delete' => $permission->can_delete,
            default => false,
        };
    }

    /**
     * Get all permissions untuk module tertentu.
     */
    public static function getModulePermissions(string $moduleName): array
    {
        return self::where('module_name', $moduleName)
            ->get()
            ->keyBy('role_name')
            ->toArray();
    }

    /**
     * Set permission untuk module dan role.
     */
    public static function setPermission(
        string $moduleName,
        string $roleName,
        bool $canCreate,
        bool $canRead,
        bool $canUpdate,
        bool $canDelete,
        ?array $additionalRules = null
    ): self {
        return self::updateOrCreate(
            [
                'module_name' => $moduleName,
                'role_name' => $roleName,
            ],
            [
                'can_create' => $canCreate,
                'can_read' => $canRead,
                'can_update' => $canUpdate,
                'can_delete' => $canDelete,
                'additional_rules' => $additionalRules,
            ]
        );
    }
}
