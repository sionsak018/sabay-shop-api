<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Province;
use App\Models\Brand;
use App\Models\BrandModel;
use App\Models\BodyType;
use App\Models\Attribute;
use App\Models\ProductAttributeValue;
use Faker\Factory as Faker;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

class ProductSeeder extends Seeder
{
    public function run()
    {
        // Temporarily disable foreign key checks to allow deletion
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        Product::query()->delete();
        ProductImage::query()->delete();
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

        $leafCategories = Category::doesntHave('children')->get();
        if ($leafCategories->isEmpty()) {
            $leafCategories = Category::all();
        }

        $provinces = Province::all();
        $faker = Faker::create();

        // Prepare product images directory
        Storage::disk('public')->makeDirectory('products');

        // Get available images from public/images
        $sourceDir = public_path('images');
        $availableImages = [];
        if (File::exists($sourceDir)) {
            $files = File::files($sourceDir);
            foreach ($files as $file) {
                if (in_array(strtolower($file->getExtension()), ['jpg', 'jpeg', 'png', 'webp', 'avif'])) {
                    $availableImages[] = $file->getFilename();
                }
            }
        }

        $this->command->info('Seeding products...');

        // Seed 50 products for faster seeding while testing, or stick to 100+
        for ($i = 1; $i <= 60; $i++) {
            $category = $leafCategories->random();
            $province = $provinces->isNotEmpty() ? $provinces->random() : null;

            // Pick brand matching category or its parent
            $brand = Brand::where('category_id', $category->id)
                ->orWhere('category_id', $category->parent_id)
                ->inRandomOrder()->first();

            $brandModel = $brand ? BrandModel::where('brand_id', $brand->id)->inRandomOrder()->first() : null;

            // Check if it's a vehicle (either the category itself or its parent is 'vehicles')
            $isVehicle = ($category->slug === 'vehicles' || $category->parent?->slug === 'vehicles');
            $bodyType = $isVehicle ? BodyType::inRandomOrder()->first() : null;

            $price = $faker->randomFloat(2, 5, 2000);
            $discountPrice = null;

            // 30% chance of having a discount
            if (rand(1, 100) <= 30) {
                // Discount is usually 10% to 40% off the original price
                $discountPercent = rand(10, 40);
                $discountPrice = $price * (1 - ($discountPercent / 100));
            }

            $product = Product::create([
                'seller_id'   => $seller->id,
                'category_id' => $category->id,
                'brand_id'    => $brand?->id,
                'brand_model_id' => $brandModel?->id,
                'body_type_id' => $bodyType?->id,
                'province_id' => $province?->id,
                'title'       => $faker->sentence(3),
                'description' => $faker->paragraph(3),
                'price'       => $price,
                'discount_price' => $discountPrice,
                'condition'   => $faker->randomElement(['new', 'used']),
                'location'    => $province?->name ?? $faker->city,
                'status'      => 'active',
                'poster_name' => $seller->name,
                'poster_phones' => $seller->phone,
            ]);

            // Add dynamic attribute values based on category and its parent
            $attributes = $category->attributes->concat($category->parent?->attributes ?? collect());

            foreach ($attributes as $attr) {
                $option = $attr->options()->inRandomOrder()->first();
                if ($option) {
                    ProductAttributeValue::create([
                        'product_id' => $product->id,
                        'attribute_id' => $attr->id,
                        'value' => $option->value
                    ]);
                }
            }

            // Add 1-3 random images
            if (!empty($availableImages)) {
                $numImages = rand(1, 3);
                $selectedImages = array_rand(array_flip($availableImages), min($numImages, count($availableImages)));
                if (!is_array($selectedImages)) $selectedImages = [$selectedImages];

                foreach ($selectedImages as $index => $imageName) {
                    $sourcePath = $sourceDir . '/' . $imageName;
                    $extension = File::extension($sourcePath);
                    $filename = $product->id . '_' . $index . '_' . time() . '.' . $extension;
                    $destinationPath = 'products/' . $filename;

                    Storage::disk('public')->put($destinationPath, File::get($sourcePath));

                    ProductImage::create([
                        'product_id' => $product->id,
                        'image_url'  => $destinationPath,
                        'sort_order' => $index
                    ]);
                }
            }
        }

        $this->command->info('Seeded ' . Product::count() . ' products with images successfully!');
    }
}
