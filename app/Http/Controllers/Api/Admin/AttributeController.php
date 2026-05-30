<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use App\Models\AttributeOption;
use Illuminate\Http\Request;

class AttributeController extends Controller
{
    public function index()
    {
        return response()->json(Attribute::with('options')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:text,number,select',
            'options' => 'nullable|array', // Array of strings for select type
        ]);

        $attribute = Attribute::create($request->only(['name', 'type']));

        if ($request->type === 'select' && !empty($request->options)) {
            foreach ($request->options as $optionValue) {
                $attribute->options()->create(['value' => $optionValue]);
            }
        }

        return response()->json($attribute->load('options'), 201);
    }

    public function update(Request $request, $id)
    {
        $attribute = Attribute::findOrFail($id);
        $attribute->update($request->only(['name', 'type']));

        if ($request->type === 'select' && $request->has('options')) {
            $attribute->options()->delete();
            foreach ($request->options as $optionValue) {
                $attribute->options()->create(['value' => $optionValue]);
            }
        }

        return response()->json($attribute->load('options'));
    }

    public function destroy($id)
    {
        Attribute::findOrFail($id)->delete();
        return response()->json(['message' => 'Attribute deleted']);
    }
}
