<?php

// Explicit staging bootstrap. Never called by an image entrypoint.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (!$app->environment('staging')) {
    fwrite(STDERR, "This setup is restricted to APP_ENV=staging.\n");
    exit(1);
}
try {
    $accounts = app(Database\Seeders\DemoAccountSeeder::class)->plannedAccounts();
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage()."\n");
    exit(1);
}
if (in_array('--check', $argv, true)) {
    echo 'Preflight OK: '.count($accounts)." akun staging; tidak mengubah database atau mencetak password.\n";
    exit(0);
}
foreach ([
    Database\Seeders\RoleSeeder::class,
    Database\Seeders\RolePermissionSeeder::class,
    Database\Seeders\UnitSeeder::class,
    Database\Seeders\SyncSettingSeeder::class,
    Database\Seeders\DemoAccountSeeder::class,
] as $seeder) {
    $exit = Artisan::call('db:seed', ['--class' => $seeder, '--force' => true]);
    echo Artisan::output();
    if ($exit !== 0) exit($exit);
}
echo "Two fixed staging accounts retain DADU_TEST_PASSWORD; eleven blackbox accounts use the requested test password.\n";
echo "This script does not import academic JSON or create an open Sidang TA period.\n";
