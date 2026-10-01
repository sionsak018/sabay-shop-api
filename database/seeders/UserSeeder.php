<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run()
    {
        // 1. Create Admin
        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'phone' => '012345678',
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );

        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole) {
            $admin->roles()->syncWithoutDetaching([$adminRole->id]);
        }

        // 2. Create Sellers
        $sellers = [
            ['name' => 'Phnom Penh Store', 'email' => 'pp-store@example.com'],
            ['name' => 'Electronics Hub', 'email' => 'electronics@example.com'],
            ['name' => 'Car Dealer Pro', 'email' => 'cars@example.com'],
            ['name' => 'Fashion Boutique', 'email' => 'fashion@example.com'],
        ];

        foreach ($sellers as $sData) {
            User::updateOrCreate(
                ['email' => $sData['email']],
                [
                    'name' => $sData['name'],
                    'password' => Hash::make('password123'),
                    'phone' => '0' . rand(10000000, 99999999),
                    'role' => 'user', // Basic role, but acts as seller
                    'email_verified_at' => now(),
                ]
            );
        }

        // 3. Create regular customers
        User::factory()->count(10)->create();
    }
}
