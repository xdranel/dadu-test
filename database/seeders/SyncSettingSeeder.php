<?php

namespace Database\Seeders;

use App\Models\SyncSetting;
use Illuminate\Database\Seeder;

class SyncSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $modules = [
            ['module' => 'student',    'label' => 'Student Data',       'icon' => '🎓', 'urutan' => 1],
            ['module' => 'course',     'label' => 'Course Data',        'icon' => '📚', 'urutan' => 2],
            ['module' => 'krs',        'label' => 'Student KRS',        'icon' => '📋', 'urutan' => 3],
            ['module' => 'grade',      'label' => 'Grades & Transcript', 'icon' => '📊', 'urutan' => 4],
            ['module' => 'attendance', 'label' => 'Attendance',         'icon' => '✅', 'urutan' => 5],
            ['module' => 'activity',   'label' => 'Activity (TAK)',     'icon' => '🏆', 'urutan' => 6],
            ['module' => 'lecturer',   'label' => 'Lecturer Data',      'icon' => '👨‍🏫', 'urutan' => 7],
            ['module' => 'eprt',       'label' => 'EPRT',               'icon' => '📝', 'urutan' => 8],
        ];

        foreach ($modules as $module) {
            SyncSetting::updateOrCreate(
                ['module' => $module['module']],
                array_merge($module, ['endpoint_url' => null, 'is_active' => true])
            );
        }
    }
}
