<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\Category;
use App\Models\District;
use App\Models\Province;
use App\Models\User;
use App\Support\MasterDataCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MasterDataCacheTest extends TestCase
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

    public function test_provinces_list_is_cached(): void
    {
        Province::create(['name' => 'Phnom Penh', 'code' => 'PP']);

        $first = $this->getJson('/api/provinces')->assertOk();

        $this->assertSame(0, $this->queryCount(fn () => $this->getJson('/api/provinces')->assertOk()));
        $this->assertEquals($first->json(), $this->getJson('/api/provinces')->json());
    }

    public function test_districts_are_cached_per_province_filter(): void
    {
        $p1 = Province::create(['name' => 'P1', 'code' => 'P1']);
        $p2 = Province::create(['name' => 'P2', 'code' => 'P2']);
        District::create(['province_id' => $p1->id, 'name' => 'D1', 'code' => 'D1']);
        District::create(['province_id' => $p2->id, 'name' => 'D2', 'code' => 'D2']);

        $this->getJson("/api/districts?province_id={$p1->id}")->assertOk();

        $this->assertSame(
            0,
            $this->queryCount(fn () => $this->getJson("/api/districts?province_id={$p1->id}")->assertOk())
        );

        $this->assertGreaterThan(
            0,
            $this->queryCount(fn () => $this->getJson("/api/districts?province_id={$p2->id}")->assertOk()),
            'a different province must use a different cache key'
        );
    }

    public function test_search_bypasses_the_cache(): void
    {
        Province::create(['name' => 'Phnom Penh', 'code' => 'PP']);

        $this->getJson('/api/provinces?search=Phnom')->assertOk();

        $this->assertGreaterThan(
            0,
            $this->queryCount(fn () => $this->getJson('/api/provinces?search=Phnom')->assertOk())
        );
    }

    public function test_flush_invalidates_master_data(): void
    {
        Province::create(['name' => 'Phnom Penh', 'code' => 'PP']);
        $this->getJson('/api/provinces')->assertOk();
        $this->assertSame(0, $this->queryCount(fn () => $this->getJson('/api/provinces')->assertOk()));

        MasterDataCache::flush();

        $this->assertGreaterThan(0, $this->queryCount(fn () => $this->getJson('/api/provinces')->assertOk()));
    }

    public function test_admin_mutation_invalidates_the_cache(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        Province::create(['name' => 'Old', 'code' => 'OLD']);
        $this->getJson('/api/provinces')->assertOk();
        $this->assertSame(0, $this->queryCount(fn () => $this->getJson('/api/provinces')->assertOk()));

        $this->postJson('/api/admin/provinces', ['name' => 'New', 'code' => 'NEW'])->assertCreated();

        $this->assertGreaterThan(0, $this->queryCount(fn () => $this->getJson('/api/provinces')->assertOk()));
        $this->getJson('/api/provinces')->assertJsonFragment(['name' => 'New']);
    }

    public function test_category_attributes_are_cached(): void
    {
        $category = Category::create(['name' => 'Cars', 'slug' => 'cars']);
        $attribute = Attribute::create(['name' => 'Color', 'type' => 'select']);
        $category->attributes()->attach($attribute->id);

        $this->getJson("/api/category-attributes/{$category->id}")->assertOk();

        $this->assertSame(
            0,
            $this->queryCount(fn () => $this->getJson("/api/category-attributes/{$category->id}")->assertOk())
        );
    }
}
