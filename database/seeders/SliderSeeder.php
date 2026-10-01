<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Slider;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

class SliderSeeder extends Seeder
{
    public function run()
    {
        $sliders = [
            [
                'title' => 'Upgrade Your Tech',
                'image' => 'USB-C Hub Adapter.jpg',
                'link' => '/category/electronics'
            ],
            [
                'title' => 'New Car Arrivals',
                'image' => 'game.jpg', // Placeholder for car banner
                'link' => '/category/vehicles'
            ],
            [
                'title' => 'Comfort for your Home',
                'image' => 'chair.png',
                'link' => '/category/home-garden'
            ],
        ];

        Storage::disk('public')->makeDirectory('sliders');

        foreach ($sliders as $index => $sData) {
            $imageUrl = null;
            $sourcePath = public_path('images/' . $sData['image']);

            if (File::exists($sourcePath)) {
                $filename = 'slider_' . $index . '.' . File::extension($sourcePath);
                $destinationPath = 'sliders/' . $filename;

                Storage::disk('public')->put($destinationPath, File::get($sourcePath));
                $imageUrl = $destinationPath;
            }

            Slider::create([
                'title' => $sData['title'],
                'image_url' => $imageUrl ?? 'https://placehold.co/1200x400?text=' . urlencode($sData['title']),
                'link_url' => $sData['link'],
                'sort_order' => $index,
                'is_active' => true
            ]);
        }
    }
}
