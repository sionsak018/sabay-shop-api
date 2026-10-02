<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Support\MasterDataCache;
use Illuminate\Http\Request;

class DistrictController extends Controller
{
    public function index(Request $request)
    {
        $query = District::with('province');
        if ($request->filled('province_id')) {
            $query->where('province_id', $request->province_id);
        }

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

        $province = $request->filled('province_id') ? (int) $request->province_id : 'all';

        return response()->json(
            MasterDataCache::remember("districts.province.{$province}", fn () => $query->get()->toArray())
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'province_id' => 'required|exists:provinces,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:districts',
        ]);
        $district = District::create($validated);
        MasterDataCache::flush();
        return response()->json($district, 201);
    }

    public function update(Request $request, $id)
    {
        $district = District::findOrFail($id);
        $district->update($request->validate([
            'province_id' => 'required|exists:provinces,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:districts,code,'.$district->id,
        ]));
        MasterDataCache::flush();
        return response()->json($district);
    }

    public function destroy($id)
    {
        District::findOrFail($id)->delete();
        MasterDataCache::flush();
        return response()->json(['message' => 'Deleted']);
    }
}
