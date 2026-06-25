<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Services\CloudinaryService;

class BrandController extends Controller
{
    protected $cloudinaryService;

    public function __construct(CloudinaryService $cloudinaryService)
    {
        $this->cloudinaryService = $cloudinaryService;
    }

    public function index()
    {
        return \Illuminate\Support\Facades\Cache::remember('brands_all', 86400, function() {
            return Brand::with('category')->get();
        });
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:brands',
            'category_id' => 'nullable|exists:categories,id',
            'image' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $url = $this->cloudinaryService->upload($request->file('image'), 'sabay-shop/brands');
            if ($url) {
                $validated['image_url'] = $url;
            }
        }

        $brand = Brand::create($validated);
        return response()->json($brand, 201);
    }

    public function update(Request $request, $id)
    {
        $brand = Brand::findOrFail($id);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => ['required', 'string', 'max:255', Rule::unique('brands')->ignore($brand->id)],
            'category_id' => 'nullable|exists:categories,id',
            'image' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('image')) {
            if ($brand->image_url) {
                $this->cloudinaryService->delete($brand->image_url);
            }
            $url = $this->cloudinaryService->upload($request->file('image'), 'sabay-shop/brands');
            if ($url) {
                $brand->image_url = $url;
            }
        } elseif ($request->boolean('remove_image')) {
            if ($brand->image_url) {
                $this->cloudinaryService->delete($brand->image_url);
            }
            $brand->image_url = null;
        }

        unset($validated['image']);
        $brand->fill($validated);
        $brand->save();

        return response()->json($brand);
    }

    public function destroy($id)
    {
        $brand = Brand::findOrFail($id);
        $brand->delete();
        return response()->json(['message' => 'Brand deleted']);
    }
}
