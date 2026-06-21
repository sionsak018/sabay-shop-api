<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define permissions
        $permissions = [
            // User management
            ['name' => 'view_users', 'display_name' => 'View Users', 'group' => 'User Management'],
            ['name' => 'create_users', 'display_name' => 'Create Users', 'group' => 'User Management'],
            ['name' => 'edit_users', 'display_name' => 'Edit Users', 'group' => 'User Management'],
            ['name' => 'delete_users', 'display_name' => 'Delete Users', 'group' => 'User Management'],

            // Product management
            ['name' => 'view_products', 'display_name' => 'View Products', 'group' => 'Product Management'],
            ['name' => 'create_products', 'display_name' => 'Create Products', 'group' => 'Product Management'],
            ['name' => 'edit_products', 'display_name' => 'Edit Products', 'group' => 'Product Management'],
            ['name' => 'delete_products', 'display_name' => 'Delete Products', 'group' => 'Product Management'],

            // Category management
            ['name' => 'manage_categories', 'display_name' => 'Manage Categories', 'group' => 'Settings'],

            // Role management
            ['name' => 'manage_roles', 'display_name' => 'Manage Roles', 'group' => 'Settings'],
        ];

        foreach ($permissions as $p) {
            Permission::updateOrCreate(['name' => $p['name']], $p);
        }

        // Create Admin Role
        $adminRole = Role::updateOrCreate(['name' => 'admin'], [
            'display_name' => 'Administrator',
            'description' => 'Full access to the system',
        ]);

        // Sync all permissions to admin
        $adminRole->permissions()->sync(Permission::all());

        // Create Manager Role
        $managerRole = Role::updateOrCreate(['name' => 'manager'], [
            'display_name' => 'Manager',
            'description' => 'Can manage users and products',
        ]);

        // Assign some permissions to manager
        $managerRole->permissions()->sync(
            Permission::whereIn('name', [
                'view_users', 'view_products', 'create_products', 'edit_products'
            ])->get()
        );

        // Assign Admin role to existing admin users
        $admins = User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
            $admin->roles()->syncWithoutDetaching([$adminRole->id]);
        }
    }
}
