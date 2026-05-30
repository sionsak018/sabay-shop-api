<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Attribute;
use Illuminate\Http\Request;

class CategoryAttributeController extends Controller
{
    public function index($categoryId)
    {
        $category = Category::with('attributes.options')->findOrFail($categoryId);
        return response()->json($category->attributes);
    }

    public function sync(Request $request, $categoryId)
    {
        $category = Category::findOrFail($categoryId);

        // fields: [{id: 1, is_required: true, sort_order: 1}, ...]
        $syncData = [];
        foreach ($request->fields as $field) {
            $syncData[$field['id']] = [
                'is_required' => $field['is_required'] ?? false,
                'sort_order' => $field['sort_order'] ?? 0
            ];
        }

        $category->attributes()->sync($syncData);

        return response()->json(['message' => 'Mapping updated']);
    }
}
