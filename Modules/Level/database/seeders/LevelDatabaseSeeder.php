<?php

namespace Modules\Level\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Level\Models\Level;

class LevelDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $levels = [
            ['name' => 'purchase'],
            ['name' => 'sale'],
            ['name' => 'client return'],
            ['name' => 'supplier return'],
            ['name' => 'damage'],
            ['name' => 'repair'],
        ];

        foreach ($levels as $level) {
            Level::create([
                'name' => $level['name'],
            ]); 
        }
    }
}
