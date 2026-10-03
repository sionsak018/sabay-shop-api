<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryHierarchyTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => 'admin'])->save();

        return $user;
    }

    public function test_can_create_main_category(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/categories', [
            'name' => 'Vehicles',
            'slug' => 'vehicles',
        ])->assertCreated();
    }

    public function test_can_create_subcategory_under_main_category(): void
    {
        $main = Category::create(['name' => 'Vehicles', 'slug' => 'vehicles']);
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/categories', [
            'name' => 'Cars',
            'slug' => 'cars',
            'parent_id' => $main->id,
        ])->assertCreated()->assertJsonPath('parent_id', $main->id);
    }

    public function test_cannot_create_subcategory_under_subcategory(): void
    {
        $main = Category::create(['name' => 'Vehicles', 'slug' => 'vehicles']);
        $sub = Category::create(['name' => 'Cars', 'slug' => 'cars', 'parent_id' => $main->id]);
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/categories', [
            'name' => 'Sedans',
            'slug' => 'sedans',
            'parent_id' => $sub->id,
        ])->assertStatus(422);
    }

    public function test_cannot_move_a_category_with_children_under_another_category(): void
    {
        $main = Category::create(['name' => 'Vehicles', 'slug' => 'vehicles']);
        Category::create(['name' => 'Cars', 'slug' => 'cars', 'parent_id' => $main->id]);
        $other = Category::create(['name' => 'Electronics', 'slug' => 'electronics']);
        Sanctum::actingAs($this->admin());

        $this->putJson("/api/admin/categories/{$main->id}", [
            'name' => 'Vehicles',
            'slug' => 'vehicles',
            'parent_id' => $other->id,
        ])->assertStatus(422);
    }

    public function test_cannot_set_a_category_as_its_own_parent(): void
    {
        $main = Category::create(['name' => 'Vehicles', 'slug' => 'vehicles']);
        Sanctum::actingAs($this->admin());

        $this->putJson("/api/admin/categories/{$main->id}", [
            'name' => 'Vehicles',
            'slug' => 'vehicles',
            'parent_id' => $main->id,
        ])->assertStatus(422);
    }
}
