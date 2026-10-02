<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\BrandModel;
use App\Support\MasterDataCache;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BrandModelController extends Controller
{
    public function index()
    {
        return response()->json(
            MasterDataCache::remember('brand_models.all', fn () => BrandModel::with('brand')->get()->toArray())
        );
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
        MasterDataCache::flush();
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
        MasterDataCache::flush();
        return response()->json($model);
    }

    public function destroy($id)
    {
        BrandModel::findOrFail($id)->delete();
        MasterDataCache::flush();
        return response()->json(['message' => 'Model deleted']);
    }
}
