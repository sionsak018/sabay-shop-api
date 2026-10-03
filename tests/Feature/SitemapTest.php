<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_returns_xml_with_products_and_categories(): void
    {
        $seller = User::factory()->create(['role' => 'user']);
        $category = Category::create(['name' => 'Cars', 'slug' => 'cars']);

        $product = Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'title' => 'Sitemap test listing',
            'description' => 'A listing used by the sitemap test.',
            'price' => 100,
            'condition' => 'used',
            'location' => 'Phnom Penh',
            'status' => 'active',
        ]);

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        $body = $response->getContent();
        $this->assertStringContainsString('<urlset', $body);
        $this->assertStringContainsString('</loc>', $body);
        $this->assertStringContainsString('/products/'.$product->id, $body);
        $this->assertStringContainsString('?category_id='.$category->id, $body);

        $this->assertNotNull(simplexml_load_string($body));
    }

    public function test_inactive_products_are_excluded_from_sitemap(): void
    {
        $seller = User::factory()->create(['role' => 'user']);
        $category = Category::create(['name' => 'Phones', 'slug' => 'phones']);

        $inactive = Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'title' => 'Hidden listing',
            'description' => 'Should not appear in the sitemap.',
            'price' => 50,
            'condition' => 'used',
            'location' => 'Phnom Penh',
            'status' => 'inactive',
        ]);

        $body = $this->get('/sitemap.xml')->getContent();

        $this->assertStringNotContainsString('/products/'.$inactive->id, $body);
    }
}
