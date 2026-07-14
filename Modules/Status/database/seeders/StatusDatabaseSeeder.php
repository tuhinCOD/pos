<?php

namespace Modules\Status\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Status\Models\Status;

class StatusDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $statuses = [
            // [
            //     'name' => 'sale',
            //     'children' => [
            //         ['name' => 'completed'],
            //         ['name' => 'partial completed'],
            //         ['name' => 'returned'],
            //     ]
            // ],
            [
                'name' => 'temp',
                'children' => [
                    ['name' => 'pending'],
                    ['name' => 'completed'],
                    ['name' => 'partial completed'],
                    ['name' => 'cancelled'],
                ]
            ],
            [
                'name' => 'purchase',
                'children' => [
                    ['name' => 'pending'],
                    ['name' => 'ordered'],
                    ['name' => 'received'],
                    ['name' => 'partial received'],
                    ['name' => 'cancelled'],
                ]
            ],
            [
                'name' => 'order',
                'children' => [
                    ['name' => 'pending'],
                    ['name' => 'confirmed'],
                    ['name' => 'processing'],
                    ['name' => 'packed'],
                    ['name' => 'shipped'],
                    ['name' => 'delivered'],
                    ['name' => 'failed'],
                    ['name' => 'returned'],
                    ['name' => 'cancelled'],
                ]
            ],
            [
                'name' => 'payment',
                'children' => [
                    ['name' => 'pending'],
                    ['name' => 'paid'],
                    ['name' => 'failed'],
                ]
            ],
            [
                'name' => 'delivery',
                'children' => [
                    ['name' => 'pending'],
                    ['name' => 'picked_up'],
                    ['name' => 'in transit'],
                    ['name' => 'delivered'],
                    ['name' => 'failed'],
                    ['name' => 'returned'],
                ]
            ],
            [
                'name' => 'damage',
                'children' => [
                    ['name' => 'damaged'],
                    ['name' => 'repairing'],
                    ['name' => 'repaired'],
                    ['name' => 'scratched'],
                    ['name' => 'sellable discount'],
                    ['name' => 'sellable'],
                ]
            ],
            [
                'name' => 'repair',
                'children' => [
                    ['name' => 'pending'],
                    ['name' => 'in progress'],
                    ['name' => 'completed'],
                    ['name' => 'failed'],
                ]
            ],
            [
                'name' => 'user',
                'children' => [
                    ['name' => 'active'],
                    ['name' => 'inactive'],
                    ['name' => 'blocked'],
                    ['name' => 'e-commerce'],
                    ['name' => 'regular'],
                ]
            ],
            [
                'name' => 'product',
                'children' => [
                    ['name' => 'active'],
                    ['name' => 'inactive'],
                    ['name' => 'out of stock'],
                ]
            ],
            [
                'name' => 'barcode',
                'children' => [
                    ['name' => 'active'],
                    ['name' => 'inactive'],
                    ['name' => 'sold'],
                ]
            ],
            [
                'name' => 'client return',
                'children' => [
                    ['name' => 'pending'],
                    ['name' => 'completed'],
                    ['name' => 'cancelled'],
                ]
            ],
            [
                'name' => 'supplier return',
                'children' => [
                    ['name' => 'pending'],
                    ['name' => 'completed'],
                    ['name' => 'cancelled'],
                ]
            ],
            [
                'name' => 'stock transfer',
                'children' => [
                    ['name' => 'pending'],
                    ['name' => 'completed'],
                    ['name' => 'cancelled'],
                ]
            ],
        ];

        foreach ($statuses as $group) {
            $parent = Status::create([
                'name' => $group['name'],
                'parent_id' => null
            ]);

            foreach ($group['children'] as $child) {
                Status::create([
                    'name' => $child['name'],
                    'parent_id' => $parent->id
                ]);
            }
        }
    }
}
