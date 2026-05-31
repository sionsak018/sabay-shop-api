<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    public function run()
    {
        $categories = [
            ['name' => 'Phones & Tablets', 'slug' => 'phones-tablets'],
            ['name' => 'Computers', 'slug' => 'computers'],
            ['name' => 'Electronics', 'slug' => 'electronics'],
            ['name' => 'Vehicles', 'slug' => 'vehicles'],
            ['name' => 'Real Estate', 'slug' => 'real-estate'],
            ['name' => 'Fashion & Beauty', 'slug' => 'fashion-beauty'],
            ['name' => 'Home & Garden', 'slug' => 'home-garden'],
            ['name' => 'Jobs', 'slug' => 'jobs'],
            ['name' => 'Services', 'slug' => 'services'],
        ];

        foreach ($categories as $cat) {
            Category::updateOrCreate(['slug' => $cat['slug']], $cat);
        }
    }
}
