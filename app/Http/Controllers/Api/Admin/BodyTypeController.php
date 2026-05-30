<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\BodyType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

class BodyTypeController extends Controller
{
    public function index()
    {
        return response()->json(BodyType::all());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:body_types',
            'image' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('body_types', 'public');
            $validated['image_url'] = $path;
        }

        $bodyType = BodyType::create($validated);
        return response()->json($bodyType, 201);
    }

    public function update(Request $request, $id)
    {
        $bodyType = BodyType::findOrFail($id);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => ['required', 'string', 'max:255', Rule::unique('body_types')->ignore($bodyType->id)],
            'image' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('image')) {
            if ($bodyType->image_url) {
                Storage::disk('public')->delete($bodyType->image_url);
            }
            $path = $request->file('image')->store('body_types', 'public');
            $validated['image_url'] = $path;
        }

        $bodyType->update($validated);
        return response()->json($bodyType);
    }

    public function destroy($id)
    {
        $bodyType = BodyType::findOrFail($id);
        if ($bodyType->image_url) {
            Storage::disk('public')->delete($bodyType->image_url);
        }
        $bodyType->delete();
        return response()->json(['message' => 'Body type deleted']);
    }
}
