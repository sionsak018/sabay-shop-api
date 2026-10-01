<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Brand;
use App\Models\BrandModel;
use App\Models\BodyType;
use App\Models\Category;
use Illuminate\Support\Str;

class SpecSeeder extends Seeder
{
    public function run()
    {
        // 1. Seed Body Types
        $bodyTypes = ['Sedan', 'SUV', 'Hatchback', 'Pickup', 'Van', 'Coupe', 'Convertible', 'Wagon'];
        foreach ($bodyTypes as $bt) {
            BodyType::updateOrCreate(
                ['slug' => Str::slug($bt)],
                ['name' => $bt]
            );
        }

        // 2. Seed Brands and Models
        $vehicles = Category::where('slug', 'vehicles')->first();
        $phones = Category::where('slug', 'phones-tablets')->first();
        $computers = Category::where('slug', 'computers')->first();

        $brands = [
            // Vehicles
            [
                'name' => 'Toyota',
                'category_id' => $vehicles?->id,
                'models' => ['Camry', 'Corolla', 'Highlander', 'Prius', 'Land Cruiser', 'RAV4']
            ],
            [
                'name' => 'Lexus',
                'category_id' => $vehicles?->id,
                'models' => ['RX300', 'RX330', 'RX350', 'LX570', 'GS300', 'NX200t']
            ],
            [
                'name' => 'Ford',
                'category_id' => $vehicles?->id,
                'models' => ['Ranger', 'Everest', 'F-150', 'Explorer', 'Mustang']
            ],
            // Phones
            [
                'name' => 'Apple',
                'category_id' => $phones?->id,
                'models' => ['iPhone 15 Pro', 'iPhone 14', 'iPhone 13', 'iPad Air', 'iPad Pro']
            ],
            [
                'name' => 'Samsung',
                'category_id' => $phones?->id,
                'models' => ['Galaxy S23', 'Galaxy S22', 'Galaxy Z Fold', 'Galaxy A54']
            ],
            // Computers
            [
                'name' => 'Dell',
                'category_id' => $computers?->id,
                'models' => ['XPS 13', 'XPS 15', 'Latitude', 'Inspiron', 'Alienware']
            ],
            [
                'name' => 'HP',
                'category_id' => $computers?->id,
                'models' => ['Spectre x360', 'Envy', 'Pavilion', 'EliteBook']
            ],
        ];

        foreach ($brands as $bData) {
            $brand = Brand::updateOrCreate(
                ['slug' => Str::slug($bData['name'])],
                [
                    'name' => $bData['name'],
                    'category_id' => $bData['category_id']
                ]
            );

            foreach ($bData['models'] as $mName) {
                BrandModel::updateOrCreate(
                    [
                        'brand_id' => $brand->id,
                        'slug' => Str::slug($mName)
                    ],
                    ['name' => $mName]
                );
            }
        }
    }
}
