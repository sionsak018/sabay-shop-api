<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Commune;
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

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'district_id' => 'required|exists:districts,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:communes',
        ]);
        $commune = Commune::create($validated);
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
        return response()->json($commune->load('district.province'));
    }

    public function destroy($id)
    {
        Commune::findOrFail($id)->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
