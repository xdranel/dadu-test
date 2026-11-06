<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Fail before creating seed data if staging secrets/roster are incomplete.
        app(DemoAccountSeeder::class)->plannedAccounts();
        $this->call([
            RoleSeeder::class,
            RolePermissionSeeder::class,
            UnitSeeder::class,
            SyncSettingSeeder::class,
            DemoAccountSeeder::class,
            QueueTicketSeeder::class,
        ]);
    }
}
