<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

class CategorySeeder extends Seeder
{
    public function run()
    {
        $categories = [
            [
                'name' => 'Phones & Tablets',
                'slug' => 'phones-tablets',
                'image' => 'True Wireless Earbuds.jpg',
                'children' => [
                    ['name' => 'Smart Phones', 'slug' => 'smart-phones'],
                    ['name' => 'Tablets', 'slug' => 'tablets'],
                    ['name' => 'Smart Watches', 'slug' => 'smart-watches'],
                    ['name' => 'Accessories', 'slug' => 'phone-accessories'],
                ]
            ],
            [
                'name' => 'Computers',
                'slug' => 'computers',
                'image' => 'mouse.jpg',
                'children' => [
                    ['name' => 'Laptops', 'slug' => 'laptops'],
                    ['name' => 'Desktops', 'slug' => 'desktops'],
                    ['name' => 'Monitors', 'slug' => 'monitors'],
                    ['name' => 'Computer Parts', 'slug' => 'computer-parts'],
                ]
            ],
            [
                'name' => 'Electronics',
                'slug' => 'electronics',
                'image' => 'webcam.jpg',
                'children' => [
                    ['name' => 'TVs & Displays', 'slug' => 'tvs-displays'],
                    ['name' => 'Cameras', 'slug' => 'cameras'],
                    ['name' => 'Audio & Speakers', 'slug' => 'audio-speakers'],
                    ['name' => 'Home Appliances', 'slug' => 'home-appliances'],
                ]
            ],
            [
                'name' => 'Vehicles',
                'slug' => 'vehicles',
                'image' => 'game.jpg',
                'children' => [
                    ['name' => 'Cars', 'slug' => 'cars'],
                    ['name' => 'Motorcycles', 'slug' => 'motorcycles'],
                    ['name' => 'Trucks', 'slug' => 'trucks'],
                    ['name' => 'Bicycles', 'slug' => 'bicycles'],
                    ['name' => 'Parts & Accessories', 'slug' => 'vehicle-parts'],
                ]
            ],
            [
                'name' => 'Real Estate',
                'slug' => 'real-estate',
                'image' => 'Monitor Stand Riser.jpg',
                'children' => [
                    ['name' => 'Houses for Sale', 'slug' => 'houses-sale'],
                    ['name' => 'Condos for Sale', 'slug' => 'condos-sale'],
                    ['name' => 'Land for Sale', 'slug' => 'land-sale'],
                    ['name' => 'Commercial for Rent', 'slug' => 'commercial-rent'],
                ]
            ],
            [
                'name' => 'Fashion & Beauty',
                'slug' => 'fashion-beauty',
                'image' => 'bag.png',
                'children' => [
                    ['name' => "Men's Clothing", 'slug' => 'mens-clothing'],
                    ['name' => "Women's Clothing", 'slug' => 'womens-clothing'],
                    ['name' => 'Watches', 'slug' => 'fashion-watches'],
                    ['name' => 'Jewelry', 'slug' => 'jewelry'],
                ]
            ],
            [
                'name' => 'Home & Garden',
                'slug' => 'home-garden',
                'image' => 'chair.png',
                'children' => [
                    ['name' => 'Furniture', 'slug' => 'furniture'],
                    ['name' => 'Kitchenware', 'slug' => 'kitchenware'],
                    ['name' => 'Decor', 'slug' => 'home-decor'],
                    ['name' => 'Gardening', 'slug' => 'gardening'],
                ]
            ],
            [
                'name' => 'Jobs',
                'slug' => 'jobs',
                'image' => 'key.png',
                'children' => [
                    ['name' => 'IT & Software', 'slug' => 'it-jobs'],
                    ['name' => 'Accounting', 'slug' => 'accounting-jobs'],
                    ['name' => 'Sales & Marketing', 'slug' => 'sales-jobs'],
                    ['name' => 'Hospitality', 'slug' => 'hospitality-jobs'],
                ]
            ],
            [
                'name' => 'Services',
                'slug' => 'services',
                'image' => 'Cable Management Box.jpg',
                'children' => [
                    ['name' => 'Repair & Maintenance', 'slug' => 'repair-services'],
                    ['name' => 'Education & Tutoring', 'slug' => 'education-services'],
                    ['name' => 'Wedding & Events', 'slug' => 'event-services'],
                    ['name' => 'Legal & Financial', 'slug' => 'legal-services'],
                ]
            ],
        ];

        // Create directory if not exists
        Storage::disk('public')->makeDirectory('categories');

        foreach ($categories as $cat) {
            $imageUrl = null;
            if (isset($cat['image'])) {
                $sourcePath = public_path('images/' . $cat['image']);

                if (File::exists($sourcePath)) {
                    $filename = $cat['slug'] . '.' . File::extension($sourcePath);
                    $destinationPath = 'categories/' . $filename;

                    // Copy file to storage
                    Storage::disk('public')->put($destinationPath, File::get($sourcePath));
                    $imageUrl = $destinationPath;
                }
            }

            $parent = Category::updateOrCreate(
                ['slug' => $cat['slug']],
                [
                    'name' => $cat['name'],
                    'image_url' => $imageUrl,
                    'parent_id' => null
                ]
            );

            if (isset($cat['children'])) {
                foreach ($cat['children'] as $child) {
                    $childImageUrl = null;
                    // If subcategory has an 'image' defined, use it. Otherwise, use parent image.
                    $childSourceImage = isset($child['image']) ? $child['image'] : $cat['image'];
                    $childSourcePath = public_path('images/' . $childSourceImage);

                    if (File::exists($childSourcePath)) {
                        $childFilename = $child['slug'] . '.' . File::extension($childSourcePath);
                        $childDestinationPath = 'categories/' . $childFilename;
                        Storage::disk('public')->put($childDestinationPath, File::get($childSourcePath));
                        $childImageUrl = $childDestinationPath;
                    }

                    Category::updateOrCreate(
                        ['slug' => $child['slug']],
                        [
                            'name' => $child['name'],
                            'parent_id' => $parent->id,
                            'image_url' => $childImageUrl
                        ]
                    );
                }
            }
        }
    }
}
