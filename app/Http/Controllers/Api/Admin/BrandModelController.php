<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\BrandModel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BrandModelController extends Controller
{
    public function index()
    {
        return response()->json(BrandModel::with('brand')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'brand_id' => 'required|exists:brands,id',
            'name' => 'required|string|max:255',
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('brand_models')->where(fn ($q) => $q->where('brand_id', $request->brand_id))
            ],
        ]);

        $model = BrandModel::create($validated);
        return response()->json($model, 201);
    }

    public function update(Request $request, $id)
    {
        $model = BrandModel::findOrFail($id);
        $validated = $request->validate([
            'brand_id' => 'required|exists:brands,id',
            'name' => 'required|string|max:255',
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('brand_models')->where(fn ($q) => $q->where('brand_id', $request->brand_id))->ignore($model->id)
            ],
        ]);

        $model->update($validated);
        return response()->json($model);
    }

    public function destroy($id)
    {
        BrandModel::findOrFail($id)->delete();
        return response()->json(['message' => 'Model deleted']);
    }
}
