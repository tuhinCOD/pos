<?php

namespace Modules\PaymentMethod\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\PaymentMethod\Models\PaymentMethod;

class PaymentMethodDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $pms = [
            ['name' => 'cash'],
            ['name' => 'bkash'],
            ['name' => 'card'],
            ['name' => 'credit card'],
            ['name' => 'Debit card'],
            ['name' => 'master card']
        ];

        foreach ($pms as $pm) {
            PaymentMethod::create($pm);
        }
    }
}
