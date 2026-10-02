<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductImage;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductListingTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(array $overrides = []): Product
    {
        $seller = User::factory()->create();
        $category = Category::create(['name' => 'Cars', 'slug' => 'cars']);
        $province = Province::create(['name' => 'Phnom Penh', 'code' => 'PP']);

        $product = Product::create(array_merge([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'province_id' => $province->id,
            'title' => 'Test product',
            'description' => 'A description',
            'price' => 100,
            'condition' => 'used',
            'location' => 'Phnom Penh',
            'status' => 'active',
        ], $overrides));

        ProductImage::create([
            'product_id' => $product->id,
            'image_url' => 'https://res.cloudinary.com/demo/image/upload/v1/x.jpg',
            'sort_order' => 0,
        ]);

        return $product;
    }

    private function queryCount(callable $fn): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $fn();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_listing_omits_heavy_relations_and_seller_pii(): void
    {
        $this->makeProduct();

        $response = $this->getJson('/api/products')->assertOk();
        $item = $response->json('data.0');

        $this->assertArrayNotHasKey('category', $item);
        $this->assertArrayNotHasKey('commune', $item);
        $this->assertArrayNotHasKey('attribute_values', $item);

        $this->assertArrayHasKey('seller', $item);
        $this->assertArrayHasKey('images', $item);
        $this->assertArrayHasKey('province', $item);

        $seller = $item['seller'];
        $this->assertArrayHasKey('id', $seller);
        $this->assertArrayHasKey('name', $seller);
        $this->assertArrayNotHasKey('email', $seller);
        $this->assertArrayNotHasKey('phone', $seller);
    }

    public function test_detail_includes_relations_and_hides_seller_pii(): void
    {
        $product = $this->makeProduct();

        $category = $product->category;
        $attribute = Attribute::create(['name' => 'Color', 'type' => 'select']);
        $category->attributes()->attach($attribute->id);
        ProductAttributeValue::create([
            'product_id' => $product->id,
            'attribute_id' => $attribute->id,
            'value' => 'Red',
        ]);

        $response = $this->getJson("/api/products/{$product->id}")->assertOk();

        $this->assertArrayHasKey('category', $response->json());
        $this->assertArrayHasKey('attribute_values', $response->json());
        $this->assertArrayNotHasKey('email', $response->json('seller'));
        $this->assertArrayNotHasKey('phone', $response->json('seller'));
    }

    public function test_default_listing_is_cached_between_requests(): void
    {
        $this->makeProduct();

        $first = $this->getJson('/api/products')->assertOk();
        $second = $this->getJson('/api/products')->assertOk();

        $this->assertEquals($first->json(), $second->json(), 'cached body must match');

        $missQueries = $this->queryCount(fn () => $this->getJson('/api/products')->assertOk());
        $this->assertSame(0, $missQueries, 'cached default listing should not hit the DB');
    }

    public function test_flushing_version_invalidates_default_listing(): void
    {
        $this->makeProduct();

        $first = $this->getJson('/api/products')->assertOk();
        $this->assertSame(0, $this->queryCount(fn () => $this->getJson('/api/products')->assertOk()), 'should be warm');

        Product::flushListingsCache();

        $after = $this->queryCount(fn () => $this->getJson('/api/products')->assertOk());
        $this->assertGreaterThan(0, $after, 'invalidated listing should query the DB again');

        $rebuild = $this->getJson('/api/products')->assertOk();
        $this->assertEquals($first->json(), $rebuild->json());
    }

    public function test_filtered_and_attribute_listings_bypass_cache(): void
    {
        $product = $this->makeProduct();
        $attribute = Attribute::create(['name' => 'Color', 'type' => 'select']);
        ProductAttributeValue::create([
            'product_id' => $product->id,
            'attribute_id' => $attribute->id,
            'value' => 'Red',
        ]);

        $catQueries = $this->queryCount(
            fn () => $this->getJson('/api/products?category_id=' . $product->category_id)->assertOk()
        );
        $this->assertGreaterThan(0, $catQueries);

        $this->getJson('/api/products?attr_' . $attribute->id . '=Red')
            ->assertOk()
            ->assertJsonPath('data.0.id', $product->id);
    }
}
