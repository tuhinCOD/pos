<?php

namespace Modules\Unit\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Unit\Models\Unit;

class UnitDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Unit::truncate();

        $units = [
            'Piece (pcs)',
            'Dozen (doz)',
            'Pair',
            'Gram (g)',
            'Kilogram (kg)',
            'Ton',
            'Milliliter (ml)',
            'Liter (L)',
            'Meter (m)',
            'Centimeter (cm)',
            'Feet (ft)',
        ];

        foreach ($units as $name) {
            Unit::create([
                'name' => $name,
                'user_id' => 1,
            ]);
        }
    }
}
