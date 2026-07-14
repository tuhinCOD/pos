<?php

namespace Modules\Category\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Category\Models\Category;

class CategoryDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Groceries',
                'children' => [
                    ['name' => 'Rice'],
                    ['name' => 'Flour'],
                    ['name' => 'Oil'],
                    ['name' => 'Spices'],
                    ['name' => 'Salt & Sugar'],
                    ['name' => 'Lentils (Dal)'],
                ]
            ],
            [
                'name' => 'Beverages',
                'children' => [
                    ['name' => 'Soft Drinks'],
                    ['name' => 'Juice'],
                    ['name' => 'Tea & Coffee'],
                    ['name' => 'Mineral Water'],
                ]
            ],
            [
                'name' => 'Snacks',
                'children' => [
                    ['name' => 'Chips'],
                    ['name' => 'Biscuits'],
                    ['name' => 'Chocolate'],
                    ['name' => 'Noodles'],
                ]
            ],
            [
                'name' => 'Dairy',
                'children' => [
                    ['name' => 'Milk'],
                    ['name' => 'Butter'],
                    ['name' => 'Cheese'],
                    ['name' => 'Yogurt'],
                ]
            ],
            [
                'name' => 'Meat & Fish',
                'children' => [
                    ['name' => 'Chicken'],
                    ['name' => 'Beef'],
                    ['name' => 'Fish'],
                ]
            ],
            [
                'name' => 'Fruits & Vegetables',
                'children' => [
                    ['name' => 'Fresh Fruits'],
                    ['name' => 'Vegetables'],
                ]
            ],
            [
                'name' => 'Personal Care',
                'children' => [
                    ['name' => 'Soap'],
                    ['name' => 'Shampoo'],
                    ['name' => 'Toothpaste'],
                    ['name' => 'Skincare'],
                ]
            ],
            [
                'name' => 'Household',
                'children' => [
                    ['name' => 'Detergent'],
                    ['name' => 'Cleaning Supplies'],
                    ['name' => 'Tissue & Paper'],
                ]
            ],
        ];

        foreach ($categories as $category) {
            $parent = Category::create([
                'name' => $category['name'],
                'parent_id' => null,
                'user_id' => 1
            ]);

            foreach ($category['children'] as $child) {
                Category::create([
                    'name' => $child['name'],
                    'parent_id' => $parent->id,
                    'user_id' => 1
                ]);
            }
        }
    }
}
