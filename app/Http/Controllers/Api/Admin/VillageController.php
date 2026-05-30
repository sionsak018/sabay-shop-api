<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Village;
use Illuminate\Http\Request;

class VillageController extends Controller
{
    public function index(Request $request)
    {
        $query = Village::query();

        if ($request->filled('commune_id')) {
            $query->where('commune_id', $request->commune_id);
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('code', 'like', '%' . $request->search . '%');
        }

        if ($request->has('page')) {
            $perPage = $request->input('per_page', 20);
            return response()->json($query->paginate($perPage));
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'commune_id' => 'required|exists:communes,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:villages,code'
        ]);

        $village = Village::create($validated);
        return response()->json($village, 201);
    }

    public function show($id)
    {
        return response()->json(Village::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $village = Village::findOrFail($id);
        $validated = $request->validate([
            'commune_id' => 'sometimes|exists:communes,id',
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|unique:villages,code,' . $id
        ]);

        $village->update($validated);
        return response()->json($village);
    }

    public function destroy($id)
    {
        Village::findOrFail($id)->delete();
        return response()->json(['message' => 'Village deleted']);
    }
}
