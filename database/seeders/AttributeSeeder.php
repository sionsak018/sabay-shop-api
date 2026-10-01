<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Category;
use Illuminate\Support\Facades\DB;

class AttributeSeeder extends Seeder
{
    public function run()
    {
        $phones = Category::where('slug', 'phones-tablets')->first();
        $vehicles = Category::where('slug', 'vehicles')->first();
        $electronics = Category::where('slug', 'electronics')->first();

        // 1. Color Attribute (Global)
        $color = Attribute::create(['name' => 'Color', 'type' => 'select']);
        $colors = ['White', 'Black', 'Silver', 'Gold', 'Blue', 'Red', 'Green'];
        foreach ($colors as $c) {
            AttributeOption::create(['attribute_id' => $color->id, 'value' => $c]);
        }

        // 2. Storage Attribute (Phones/Computers)
        $storage = Attribute::create(['name' => 'Storage Capacity', 'type' => 'select']);
        $capacities = ['64GB', '128GB', '256GB', '512GB', '1TB'];
        foreach ($capacities as $cap) {
            AttributeOption::create(['attribute_id' => $storage->id, 'value' => $cap]);
        }

        // 3. Fuel Type (Vehicles)
        $fuel = Attribute::create(['name' => 'Fuel Type', 'type' => 'select']);
        $fuels = ['Gasoline', 'Diesel', 'Hybrid', 'Electric'];
        foreach ($fuels as $f) {
            AttributeOption::create(['attribute_id' => $fuel->id, 'value' => $f]);
        }

        // 4. Transmission (Vehicles)
        $transmission = Attribute::create(['name' => 'Transmission', 'type' => 'select']);
        $transTypes = ['Automatic', 'Manual'];
        foreach ($transTypes as $t) {
            AttributeOption::create(['attribute_id' => $transmission->id, 'value' => $t]);
        }

        // Link to Categories
        if ($phones) {
            $phones->attributes()->attach([
                $color->id => ['is_required' => false, 'sort_order' => 1],
                $storage->id => ['is_required' => true, 'sort_order' => 2],
            ]);
        }

        if ($vehicles) {
            $vehicles->attributes()->attach([
                $color->id => ['is_required' => false, 'sort_order' => 1],
                $fuel->id => ['is_required' => true, 'sort_order' => 2],
                $transmission->id => ['is_required' => true, 'sort_order' => 3],
            ]);
        }

        if ($electronics) {
            $electronics->attributes()->attach([
                $color->id => ['is_required' => false, 'sort_order' => 1],
            ]);
        }
    }
}
