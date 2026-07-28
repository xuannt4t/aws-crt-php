<?php

namespace Database\Seeders;

use App\Models\OrganizationUnit;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $rootUnit = OrganizationUnit::firstOrCreate(
            ['code' => 'ROOT'],
            ['name' => 'DORMIDA WORK', 'is_active' => true]
        );

        User::firstOrCreate(
            ['email' => config('dormida.admin_email')],
            [
                'organization_unit_id' => $rootUnit->id,
                'name' => 'System Admin',
                'password' => bcrypt(config('dormida.admin_password')),
                'is_system_admin' => true,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}
