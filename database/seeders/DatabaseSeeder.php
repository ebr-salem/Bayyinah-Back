<?php

namespace Database\Seeders;

use App\Models\GlobalSetting;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => env('SUPER_ADMIN_EMAIL', 'superadmin@ertiqaa.com')],
            [
                'name' => env('SUPER_ADMIN_NAME', 'Super Admin'),
                'password' => env('SUPER_ADMIN_PASSWORD', 'superadmin'),
                'role' => User::ROLE_SUPER_ADMIN,
                'is_active' => true,
            ]
        );

        GlobalSetting::query()->updateOrCreate(
            ['setting_key' => GlobalSetting::AI_MONTHLY_LIMIT_KEY],
            ['setting_value' => env('AI_MONTHLY_LIMIT', '100')]
        );
    }
}
