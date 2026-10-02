<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Commune;
use App\Support\MasterDataCache;
use Illuminate\Http\Request;

class CommuneController extends Controller
{
    public function index(Request $request)
    {
        $query = Commune::with('district.province');
        if ($request->filled('district_id')) {
            $query->where('district_id', $request->district_id);
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

        $district = $request->filled('district_id') ? (int) $request->district_id : 'all';

        return response()->json(
            MasterDataCache::remember("communes.district.{$district}", fn () => $query->get()->toArray())
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'district_id' => 'required|exists:districts,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:communes',
        ]);
        $commune = Commune::create($validated);
        MasterDataCache::flush();
        return response()->json($commune->load('district.province'), 201);
    }

    public function update(Request $request, $id)
    {
        $commune = Commune::findOrFail($id);
        $commune->update($request->validate([
            'district_id' => 'required|exists:districts,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:communes,code,'.$commune->id,
        ]));
        MasterDataCache::flush();
        return response()->json($commune->load('district.province'));
    }

    public function destroy($id)
    {
        Commune::findOrFail($id)->delete();
        MasterDataCache::flush();
        return response()->json(['message' => 'Deleted']);
    }
}
