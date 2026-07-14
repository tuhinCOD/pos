<?php

namespace Modules\User\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\User\Models\User;

class UserDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'name' => 'tuhin',
            'email' => 'tuhin@gmail.com',
            'contact' => '01977326150',
            'password' => Hash::make('123456'),
            'address' => 'charadighirpar',
            'city_id' => 1,
            'role_id' => 1,
        ]);
        User::create([
            'name' => 'sajid',
            'email' => 'sajid@gmail.com',
            'contact' => '01977326151',
            'password' => Hash::make('123456'),
            'address' => 'charadighirpar',
            'city_id' => 1,
            'role_id' => 2,
        ]);
    }
}
