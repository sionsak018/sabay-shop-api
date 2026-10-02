<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Province;
use App\Support\MasterDataCache;
use Illuminate\Http\Request;

class ProvinceController extends Controller
{
    public function index(Request $request)
    {
        $query = Province::query();

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('code', 'like', '%' . $request->search . '%');
        }

        if ($request->has('page')) {
            $perPage = $request->input('per_page', 20);
            return response()->json($query->paginate($perPage));
        }

        if ($request->filled('search')) {
            return response()->json($query->get());
        }

        return response()->json(MasterDataCache::remember('provinces.all', fn () => $query->get()->toArray()));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:provinces',
        ]);
        $province = Province::create($validated);
        MasterDataCache::flush();
        return response()->json($province, 201);
    }

    public function update(Request $request, $id)
    {
        $province = Province::findOrFail($id);
        $province->update($request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:provinces,code,'.$province->id,
        ]));
        MasterDataCache::flush();
        return response()->json($province);
    }

    public function destroy($id)
    {
        Province::findOrFail($id)->delete();
        MasterDataCache::flush();
        return response()->json(['message' => 'Deleted']);
    }
}
