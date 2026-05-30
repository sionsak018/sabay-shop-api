<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use Faker\Factory as Faker;

class ProductSeeder extends Seeder
{
    public function run()
    {
        // Temporarily disable foreign key checks to allow deletion
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        Product::query()->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // Ensure seller exists
        $seller = User::where('email', 'seller@example.com')->first();
        if (!$seller) {
            $seller = User::create([
                'name' => 'Demo Seller',
                'email' => 'seller@example.com',
                'password' => Hash::make('password123'),
                'phone' => '012345678',
                'role' => 'user',
            ]);
        }

        // Ensure category exists
        $category = Category::where('name', 'Electronics')->first();
        if (!$category) {
            $category = Category::create([
                'name' => 'Electronics',
                'slug' => 'electronics',
            ]);
        }

        $faker = Faker::create();

        // Seed exactly 100 products
        for ($i = 1; $i <= 150; $i++) {
            Product::create([
                'seller_id'   => $seller->id,
                'category_id' => $category->id,
                'title'       => $faker->sentence(3),
                'description' => $faker->paragraph(2),
                'price'       => $faker->randomFloat(2, 10, 500),
                'condition'   => $faker->randomElement(['new', 'used']),
                'location'    => $faker->city,
                'status'      => 'active',
            ]);
        }

        // Optional: one specific test product
        Product::create([
            'seller_id'   => $seller->id,
            'category_id' => $category->id,
            'title'       => 'Test Product - iPhone 15',
            'description' => 'Brand new iPhone 15 with warranty.',
            'price'       => 999.99,
            'condition'   => 'new',
            'location'    => 'Phnom Penh',
            'status'      => 'active',
        ]);

        $this->command->info('Seeded 100 products successfully!');
    }
}
