<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileShowCacheTest extends TestCase
{
    use RefreshDatabase;

    private function queryCount(callable $fn): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $fn();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    private function makeSellerWithProduct(): array
    {
        $seller = User::factory()->create();
        $category = Category::create(['name' => 'Cars', 'slug' => 'cars']);

        $product = Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'title' => 'Seller product',
            'description' => 'A description',
            'price' => 100,
            'condition' => 'used',
            'location' => 'Phnom Penh',
            'status' => 'active',
        ]);

        ProductImage::create([
            'product_id' => $product->id,
            'image_url' => 'https://res.cloudinary.com/demo/image/upload/v1/x.jpg',
            'sort_order' => 0,
        ]);

        return [$seller, $product];
    }

    public function test_profile_show_is_cached_for_guests(): void
    {
        [$seller] = $this->makeSellerWithProduct();

        $first = $this->getJson("/api/profile/{$seller->id}")->assertOk();

        $this->assertSame(
            0,
            $this->queryCount(fn () => $this->getJson("/api/profile/{$seller->id}")->assertOk())
        );
        $this->assertEquals($first->json(), $this->getJson("/api/profile/{$seller->id}")->json());
    }

    public function test_profile_show_hides_pii_and_layers_viewer_flags(): void
    {
        [$seller, $product] = $this->makeSellerWithProduct();
        $viewer = User::factory()->create();
        $viewer->favorites()->attach($product->id);
        Sanctum::actingAs($viewer);

        $response = $this->getJson("/api/profile/{$seller->id}")->assertOk();

        $this->assertArrayNotHasKey('email', $response->json('user'));
        $this->assertArrayNotHasKey('phone', $response->json('user'));
        $this->assertTrue($response->json('products.0.is_favorited'));
    }

    public function test_guest_products_are_not_favorited(): void
    {
        [$seller] = $this->makeSellerWithProduct();

        $response = $this->getJson("/api/profile/{$seller->id}")->assertOk();

        $this->assertFalse($response->json('products.0.is_favorited'));
    }
}
