<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPostingTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(Category $category): array
    {
        return [
            'title' => 'Console posting guard',
            'description' => 'A product submitted through the public flow.',
            'price' => 100,
            'category_id' => $category->id,
            'condition' => 'used',
            'location' => 'Phnom Penh',
        ];
    }

    public function test_admin_cannot_post_through_public_endpoint(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Cars', 'slug' => 'cars']);

        Sanctum::actingAs($admin);

        $this->postJson('/api/products', $this->validPayload($category))
            ->assertStatus(403);

        $this->assertDatabaseCount('products', 0);
    }

    public function test_user_with_a_role_cannot_post_through_public_endpoint(): void
    {
        $manager = User::factory()->create(['role' => 'user']);
        $role = Role::create(['name' => 'manager', 'display_name' => 'Manager']);
        $manager->roles()->attach($role->id);

        $category = Category::create(['name' => 'Cars', 'slug' => 'cars']);

        Sanctum::actingAs($manager);

        $this->postJson('/api/products', $this->validPayload($category))
            ->assertStatus(403);

        $this->assertDatabaseCount('products', 0);
    }

    public function test_regular_user_can_still_post_through_public_endpoint(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $category = Category::create(['name' => 'Cars', 'slug' => 'cars']);

        Sanctum::actingAs($user->refresh());

        $this->postJson('/api/products', $this->validPayload($category))
            ->assertStatus(201)
            ->assertJsonPath('seller_id', $user->id);

        $this->assertDatabaseHas('products', [
            'seller_id' => $user->id,
            'title' => 'Console posting guard',
        ]);
    }
}
