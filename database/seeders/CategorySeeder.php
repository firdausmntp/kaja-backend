<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'id' => 1,
                'name' => 'Makanan Ringan'
            ],
            [
                'id' => 2,
                'name' => 'Makanan Berat'
            ],
            [
                'id' => 3,
                'name' => 'Minuman'
            ],
            [
                'id' => 4,
                'name' => 'Dessert'
            ],
            [
                'id' => 5,
                'name' => 'Jajanan Tradisional'
            ],
            [
                'id' => 6,
                'name' => 'Fast Food'
            ],
            [
                'id' => 10,
                'name' => 'Buah-buahan'
            ]
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }
    }
}
