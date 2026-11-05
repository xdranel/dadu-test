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
        $this->call([
            RoleSeeder::class,
            RolePermissionSeeder::class,
            UnitSeeder::class,
            SyncSettingSeeder::class,
            UserSeeder::class,
            SidangTaTesterSeeder::class,
            QueueTicketSeeder::class,
        ]);
    }
}
