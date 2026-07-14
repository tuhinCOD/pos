<?php

namespace Modules\City\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\City\Models\City;

class CityDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $cities = [
            [
                'name' => 'sylhet',
                'country_id' => '1'
            ],
            [
                'name' => 'dhaka',
                'country_id' => '1'
            ],
            [
                'name' => 'khulna',
                'country_id' => '1'
            ],
            [
                'name' => 'barisal',
                'country_id' => '1'
            ],
            [
                'name' => 'pabna',
                'country_id' => '1'
            ],
            [
                'name' => 'fani',
                'country_id' => '1'
            ],
            [
                'name' => 'cumilla',
                'country_id' => '1'
            ],
        ];

        foreach ($cities as $city) {
            City::create($city);
        }
    }
}
