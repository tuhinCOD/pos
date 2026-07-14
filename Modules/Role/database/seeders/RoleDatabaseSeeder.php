<?php

namespace Modules\Role\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Role\Models\Role;

class RoleDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            ['name' => 'super admin'],
            ['name' => 'admin'],
            ['name' => 'manager'],
            ['name' => 'cashier'],
            ['name' => 'warehouse staff'],
            ['name' => 'user'],
        ];

        foreach ($roles as $role) {
            Role::create($role);
        }
    }
}
