<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;

class ValidateSidebarConfig extends Command
{
    protected $signature = 'sidebar:validate';

    protected $description = 'Validasi semua route & permission di config/sidebar.php benar-benar terdaftar';

    public function handle(): int
    {
        $config = config('sidebar', []);

        if (empty($config)) {
            $this->warn('config/sidebar.php kosong atau tidak ditemukan.');

            return self::SUCCESS;
        }

        $permissions = Permission::pluck('name')->all();
        $errors = [];
        $checked = 0;

        $check = function (array $item) use (&$errors, &$checked, $permissions): void {
            $checked++;
            $label = $item['label'] ?? '(tanpa label)';
            $route = $item['route'] ?? null;
            if ($route && ! Route::has($route)) {
                $errors[] = "Route tidak terdaftar: '{$route}' (item: {$label})";
            }
            $perm = $item['permission'] ?? null;
            if ($perm && ! in_array($perm, $permissions, true)) {
                $errors[] = "Permission tidak terdaftar: '{$perm}' (item: {$label})";
            }
            if (array_key_exists('permissions_any', $item)) {
                $any = $item['permissions_any'];
                if (! is_array($any) || $any === []) {
                    $errors[] = "permissions_any harus berupa array nonkosong (item: {$label})";
                } else {
                    foreach ($any as $name) {
                        if (! is_string($name) || trim($name) === '') {
                            $errors[] = "permissions_any harus berisi nama permission (item: {$label})";
                        } elseif (! in_array($name, $permissions, true)) {
                            $errors[] = "Permission tidak terdaftar: '{$name}' (item: {$label})";
                        }
                    }
                }
            }
        };

        // Role mahasiswa: flat array
        foreach ($config['mahasiswa'] ?? [] as $item) {
            $check($item);
        }

        // Admin standalone: flat array item mandiri
        foreach ($config['admin_standalone'] ?? [] as $item) {
            $check($item);
        }

        // Role admin: array grup -> items
        foreach ($config['admin'] ?? [] as $group) {
            foreach ($group['items'] ?? [] as $item) {
                $check($item);
            }
        }

        if (empty($errors)) {
            $this->info("Sidebar config valid. {$checked} item diperiksa, semua route & permission terdaftar.");

            return self::SUCCESS;
        }

        $this->error('Ditemukan '.count($errors)." masalah pada config/sidebar.php ({$checked} item diperiksa):");
        foreach ($errors as $err) {
            $this->line("  - {$err}");
        }

        return self::FAILURE;
    }
}
