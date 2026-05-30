<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use App\Models\Category;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $user = auth('sanctum')->user();
        $query = Product::with(['seller', 'category', 'images', 'province', 'commune'])->where('status', 'active');

        if ($user) {
            $query->withExists(['favoritedBy as is_favorited' => function($q) use ($user) {
                $q->where('user_id', $user->id);
            }]);
        }

        if ($request->filled('keyword')) {
            $query->where(function($q) use ($request) {
                $q->where('title', 'like', '%'.$request->keyword.'%')
                  ->orWhere('description', 'like', '%'.$request->keyword.'%');
            });
        }
        if ($request->filled('category_id')) {
            $categoryIds = [$request->category_id];
            // Include subcategories if the selected category is a parent
            $subCategoryIds = Category::where('parent_id', $request->category_id)->pluck('id')->toArray();
            $categoryIds = array_merge($categoryIds, $subCategoryIds);

            $query->whereIn('category_id', $categoryIds);
        }
        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }
        if ($request->filled('province_id')) {
            $query->where('province_id', $request->province_id);
        }
        if ($request->filled('district_id')) {
            $query->where('district_id', $request->district_id);
        }
        if ($request->filled('location') && !$request->filled('province_id')) {
            $query->where('location', 'like', '%'.$request->location.'%');
        }

        // Sorting
        $sort = $request->get('sort', 'latest');
        switch ($sort) {
            case 'price_low':
                $query->orderBy('price', 'asc');
                break;
            case 'price_high':
                $query->orderBy('price', 'desc');
                break;
            case 'latest':
            default:
                $query->latest();
                break;
        }

        // Dynamic attribute filtering
        foreach ($request->all() as $key => $value) {
            if (str_starts_with($key, 'attr_') && $request->filled($key)) {
                $attributeId = substr($key, 5);
                $query->whereHas('attributeValues', function($q) use ($attributeId, $value) {
                    $q->where('attribute_id', $attributeId)->where('value', $value);
                });
            }
        }

        return response()->json($query->paginate(20));
    }

    public function checkLimit(Request $request)
    {
        $user = $request->user();
        $activeCount = $user->products()->where('status', 'active')->count();

        return response()->json([
            'active_count' => $activeCount,
            'post_limit' => $user->post_limit,
            'account_type' => $user->account_type,
            'remaining' => max(0, $user->post_limit - $activeCount),
            'limit_reached' => $activeCount >= $user->post_limit
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        // Count active products
        $activeCount = $user->products()->where('status', 'active')->count();

        if ($activeCount >= $user->post_limit) {
            return response()->json([
                'message' => 'You have reached your free ad limit (' . $user->post_limit . '). Please delete old ads or upgrade to a store account.',
                'limit_reached' => true,
                'limit' => $user->post_limit
            ], 403);
        }

        $validated = $request->validate([
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
            'poster_phones' => 'nullable|string', // JSON string from frontend
            'company_name' => 'nullable|string',
            'images' => 'array|max:10',
            'images.*' => 'image|mimes:jpg,jpeg,png|max:2048'
        ]);

        $product = $request->user()->products()->create($request->except(['images', 'attributes']));

        $attributesData = $request->input('attributes');
        if ($attributesData) {
            $attrs = is_string($attributesData) ? json_decode($attributesData, true) : $attributesData;
            if (is_array($attrs)) {
                $attributeModels = \App\Models\Attribute::whereIn('id', array_keys($attrs))->get();
                foreach ($attrs as $attrId => $value) {
                    if ($value !== null && $value !== '') {
                        $product->attributeValues()->create([
                            'attribute_id' => $attrId,
                            'value' => is_array($value) ? json_encode($value) : $value
                        ]);

                        // Auto-sync "Discount Price" attribute to the product's discount_price column
                        $attrModel = $attributeModels->find($attrId);
                        if ($attrModel) {
                            $name = strtolower(str_replace(' ', '', $attrModel->name));
                            if ($name === 'discountprice' || $name === 'discount') {
                                if (is_numeric($value) && $value > 0) {
                                    $product->discount_price = $value;
                                } else {
                                    $product->discount_price = null;
                                }
                            }
                        }
                    }
                }
                $product->save();
            }
        }

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $index => $image) {
                $path = $image->store('products', 'public');
                $product->images()->create([
                    'image_url' => $path,
                    'sort_order' => $index
                ]);
            }
        }

        return response()->json($product->load('images'), 201);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();
        $product = $user->products()->findOrFail($id);

        if ($request->status === 'active' && $product->status !== 'active') {
            $activeCount = $user->products()->where('status', 'active')->count();
            if ($activeCount >= $user->post_limit) {
                return response()->json([
                    'message' => 'Cannot activate ad. You have reached your free ad limit (' . $user->post_limit . ').',
                    'limit_reached' => true
                ], 403);
            }
        }

        $validated = $request->validate([
            'title' => 'sometimes|string',
            'description' => 'sometimes',
            'price' => 'sometimes|numeric|min:0',
            'category_id' => 'sometimes|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'brand_model_id' => 'nullable|exists:brand_models,id',
            'body_type_id' => 'nullable|exists:body_types,id',
            'province_id' => 'nullable|exists:provinces,id',
            'district_id' => 'nullable|exists:districts,id',
            'commune_id' => 'nullable|exists:communes,id',
            'village_id' => 'nullable|exists:villages,id',
            'condition' => 'sometimes|in:new,used',
            'location' => 'sometimes|string',
            'address' => 'nullable|string',
            'lat' => 'nullable|string',
            'lng' => 'nullable|string',
            'poster_name' => 'nullable|string',
            'poster_email' => 'nullable|email',
            'poster_phones' => 'nullable|string',
            'company_name' => 'nullable|string',
            'images' => 'array|max:10',
            'images.*' => 'image|mimes:jpg,jpeg,png|max:2048'
        ]);

        $product->update($request->except(['images', 'attributes']));

        $attributesData = $request->input('attributes');
        if ($attributesData) {
            $product->attributeValues()->delete();
            $product->discount_price = null; // Reset before re-syncing

            $attrs = is_string($attributesData) ? json_decode($attributesData, true) : $attributesData;
            if (is_array($attrs)) {
                $attributeModels = \App\Models\Attribute::whereIn('id', array_keys($attrs))->get();
                foreach ($attrs as $attrId => $value) {
                    if ($value !== null && $value !== '') {
                        $product->attributeValues()->create([
                            'attribute_id' => $attrId,
                            'value' => is_array($value) ? json_encode($value) : $value
                        ]);

                        // Auto-sync "Discount Price" attribute
                        $attrModel = $attributeModels->find($attrId);
                        if ($attrModel) {
                            $name = strtolower(str_replace(' ', '', $attrModel->name));
                            if ($name === 'discountprice' || $name === 'discount') {
                                if (is_numeric($value) && $value > 0) {
                                    $product->discount_price = $value;
                                } else {
                                    $product->discount_price = null;
                                }
                            }
                        }
                    }
                }
                $product->save();
            }
        }

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $index => $image) {
                $path = $image->store('products', 'public');
                $product->images()->create([
                    'image_url' => $path,
                    'sort_order' => $product->images()->count() + $index
                ]);
            }
        }

        return response()->json($product->load('images'));
    }

    public function show($id)
    {
        $user = auth('sanctum')->user();
        $query = Product::with(['seller', 'category.parent', 'images', 'brand', 'brandModel', 'bodyType', 'province', 'district', 'commune', 'village'])
                    ->where('id', $id)
                    ->where('status', 'active');

        if ($user) {
            $query->withExists(['favoritedBy as is_favorited' => function($q) use ($user) {
                $q->where('user_id', $user->id);
            }]);
        }

        $product = $query->firstOrFail();

        // Only load attribute values for attributes currently assigned to this product's category
        $assignedAttributeIds = $product->category->attributes()->pluck('attributes.id')->toArray();
        $product->load(['attributeValues' => function($q) use ($assignedAttributeIds) {
            $q->whereIn('attribute_id', $assignedAttributeIds)->with(['attribute.options']);
        }]);

        return response()->json($product);
    }

    public function userProducts(Request $request)
    {
        $user = $request->user();
        $products = $user->products()
            ->with(['category', 'images', 'province', 'commune'])
            ->withExists(['favoritedBy as is_favorited' => function($q) use ($user) {
                $q->where('user_id', $user->id);
            }])
            ->latest()
            ->get();

        return response()->json($products);
    }

    public function destroy(Request $request, $id)
    {
        $product = $request->user()->products()->findOrFail($id);
        $product->delete();
        return response()->json(['message' => 'Product deleted successfully']);
    }
}
