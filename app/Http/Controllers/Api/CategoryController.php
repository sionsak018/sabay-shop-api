<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Services\CloudinaryService;

class CategoryController extends Controller
{
    protected $cloudinaryService;

    public function __construct(CloudinaryService $cloudinaryService)
    {
        $this->cloudinaryService = $cloudinaryService;
    }

    public function index(Request $request)
    {
        $query = Category::query();

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('slug', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('parent_id')) {
            $query->where('parent_id', $request->parent_id);
        }

        $categories = $query->get();
        return response()->json($categories);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories',
            'slug' => 'required|string|max:255|unique:categories',
            'parent_id' => 'nullable|exists:categories,id',
            'image' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $url = $this->cloudinaryService->upload($request->file('image'), 'sabay-shop/categories');
            if ($url) {
                $validated['image_url'] = $url;
            }
        }

        $category = Category::create($validated);
        return response()->json($category, 201);
    }

    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories')->ignore($category->id)
            ],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories')->ignore($category->id)
            ],
            'parent_id' => 'nullable|exists:categories,id',
            'image' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('image')) {
            if ($category->image_url) {
                $this->cloudinaryService->delete($category->image_url);
            }
            $url = $this->cloudinaryService->upload($request->file('image'), 'sabay-shop/categories');
            if ($url) {
                $category->image_url = $url;
            }
        } elseif ($request->boolean('remove_image')) {
            if ($category->image_url) {
                $this->cloudinaryService->delete($category->image_url);
            }
            $category->image_url = null;
        }

        // Remove 'image' from validated data before updating database
        unset($validated['image']);

        $category->fill($validated);
        $category->save();

        return response()->json($category);
    }

    public function destroy($id)
    {
        $category = Category::with('children')->findOrFail($id);

        if ($this->hasAssociatedProducts($category)) {
            return response()->json([
                'message' => 'Cannot delete category because it or its subcategories have products associated with them. Please delete or reassign the products first.'
            ], 422);
        }

        $category->delete();
        return response()->json(['message' => 'Category deleted']);
    }

    /**
     * Check if a category or any of its children have products.
     *
     * @param  \App\Models\Category  $category
     * @return bool
     */
    private function hasAssociatedProducts($category)
    {
        if ($category->products()->exists()) {
            return true;
        }

        foreach ($category->children as $child) {
            if ($this->hasAssociatedProducts($child)) {
                return true;
            }
        }

        return false;
    }
}
