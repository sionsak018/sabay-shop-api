<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use App\Models\Product;
use App\Services\CloudinaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AdminProductController extends Controller
{
    protected CloudinaryService $cloudinaryService;

    public function __construct(CloudinaryService $cloudinaryService)
    {
        $this->cloudinaryService = $cloudinaryService;
    }

    public function index(Request $request)
    {
        $query = Product::with(['seller', 'category']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('title', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        return response()->json($query->latest()->paginate(20));
    }

    /**
     * Create a product from the admin console. Unlike the public store
     * endpoint this is not bound by the user's post_limit and defaults the
     * owner (seller) to the authenticated admin/manager.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string',
            'description' => 'required',
            'price' => 'required|numeric|min:0',
            'category_id' => 'required|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'brand_model_id' => 'nullable|exists:brand_models,id',
            'body_type_id' => 'nullable|exists:body_types,id',
            'province_id' => 'nullable|exists:provinces,id',
            'district_id' => 'nullable|exists:districts,id',
            'commune_id' => 'nullable|exists:communes,id',
            'village_id' => 'nullable|exists:villages,id',
            'condition' => 'required|in:new,used',
            'location' => 'required|string',
            'address' => 'nullable|string',
            'lat' => 'nullable|string',
            'lng' => 'nullable|string',
            'poster_name' => 'nullable|string',
            'poster_email' => 'nullable|email',
            'poster_phones' => 'nullable|string',
            'company_name' => 'nullable|string',
            'discount_price' => 'nullable|numeric|min:0',
            'seller_id' => 'nullable|exists:users,id',
        ]);

        $sellerId = $request->input('seller_id') ?: $request->user()->id;

        $product = Product::create(array_merge(
            $request->except(['images', 'attributes', 'seller_id']),
            ['seller_id' => $sellerId]
        ));

        $attributesData = $request->input('attributes');
        if ($attributesData) {
            $attrs = is_string($attributesData) ? json_decode($attributesData, true) : $attributesData;
            if (is_array($attrs)) {
                $attributeModels = Attribute::whereIn('id', array_keys($attrs))->get();
                foreach ($attrs as $attrId => $value) {
                    if ($value === null || $value === '') {
                        continue;
                    }

                    $product->attributeValues()->create([
                        'attribute_id' => (int) $attrId,
                        'value' => is_array($value) ? json_encode($value) : (string) $value,
                    ]);

                    $attrModel = $attributeModels->find($attrId);
                    if ($attrModel) {
                        $name = strtolower(str_replace(' ', '', $attrModel->name));
                        if (($name === 'discountprice' || $name === 'discount') && !$request->has('discount_price') && is_numeric($value) && $value > 0) {
                            $product->discount_price = $value;
                        }
                    }
                }
                $product->save();
            }
        }

        $imageFiles = $request->file('images');
        if ($imageFiles) {
            $files = is_array($imageFiles) ? $imageFiles : [$imageFiles];
            foreach ($files as $index => $image) {
                try {
                    $url = $this->cloudinaryService->upload($image, 'sabay-shop/products');
                    if ($url) {
                        $product->images()->create([
                            'image_url' => $url,
                            'sort_order' => $index,
                        ]);
                    }
                } catch (\Exception $e) {
                    \Log::error('Admin product image upload failed: ' . $e->getMessage());
                }
            }
        }

        Cache::forget("product.{$product->id}");
        Cache::forget("profile.show.{$product->seller_id}");
        Product::flushListingsCache();

        return response()->json($product->load('images'), 201);
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        $product->update($request->all());
        Cache::forget("product.{$product->id}");
        Cache::forget("profile.show.{$product->seller_id}");
        Product::flushListingsCache();
        return response()->json($product);
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $product->delete();
        Cache::forget("product.{$product->id}");
        Cache::forget("profile.show.{$product->seller_id}");
        Product::flushListingsCache();
        return response()->json(['message' => 'Product deleted by admin']);
    }
}
