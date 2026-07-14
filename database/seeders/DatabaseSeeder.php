<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\Category\Database\Seeders\CategoryDatabaseSeeder;
use Modules\City\Database\Seeders\CityDatabaseSeeder;
use Modules\Country\Database\Seeders\CountryDatabaseSeeder;
use Modules\Level\Database\Seeders\LevelDatabaseSeeder;
use Modules\PaymentMethod\Database\Seeders\PaymentMethodDatabaseSeeder;
use Modules\Role\Database\Seeders\RoleDatabaseSeeder;
use Modules\Status\Database\Seeders\StatusDatabaseSeeder;
use Modules\Unit\Database\Seeders\UnitDatabaseSeeder;
use Modules\User\Database\Seeders\UserDatabaseSeeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleDatabaseSeeder::class,
            LevelDatabaseSeeder::class,
            PaymentMethodDatabaseSeeder::class,
            StatusDatabaseSeeder::class,
            CountryDatabaseSeeder::class,
            CityDatabaseSeeder::class,
            UserDatabaseSeeder::class,
            UnitDatabaseSeeder::class,
            CategoryDatabaseSeeder::class,
        ]);
    }
}
