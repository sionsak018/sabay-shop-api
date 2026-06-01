<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use App\Models\AttributeOption;
use Illuminate\Http\Request;
use App\Services\CloudinaryService;

class AttributeController extends Controller
{
    protected $cloudinary;

    public function __construct(CloudinaryService $cloudinary)
    {
        $this->cloudinary = $cloudinary;
    }

    public function index()
    {
        return response()->json(Attribute::with('options')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:text,number,select',
            'options' => 'nullable|array',
        ]);

        $attribute = Attribute::create($request->only(['name', 'type']));

        if ($request->type === 'select' && !empty($request->options)) {
            foreach ($request->options as $index => $optionData) {
                // If it's a string (old format or simple text)
                if (is_string($optionData)) {
                    $attribute->options()->create(['value' => $optionData]);
                    continue;
                }

                $value = $optionData['value'] ?? '';
                $imageUrl = null;

                // Handle image upload from FormData
                if ($request->hasFile("option_images.$index")) {
                    $imageUrl = $this->cloudinary->upload($request->file("option_images.$index"), 'sabay-shop/attributes');
                }

                $attribute->options()->create([
                    'value' => $value,
                    'image_url' => $imageUrl
                ]);
            }
        }

        return response()->json($attribute->load('options'), 201);
    }

    public function update(Request $request, $id)
    {
        $attribute = Attribute::findOrFail($id);
        $attribute->update($request->only(['name', 'type']));

        if ($request->type === 'select' && $request->has('options')) {
            // Collect existing options to decide which to keep/update or delete
            // For simplicity in this specialized flow, we'll re-sync
            $attribute->options()->delete();

            foreach ($request->options as $index => $optionData) {
                if (is_string($optionData)) {
                    $attribute->options()->create(['value' => $optionData]);
                    continue;
                }

                $value = $optionData['value'] ?? '';
                $imageUrl = $optionData['image_url'] ?? null;

                // If a new file is uploaded for this index
                if ($request->hasFile("option_images.$index")) {
                    $imageUrl = $this->cloudinary->upload($request->file("option_images.$index"), 'sabay-shop/attributes');
                }

                $attribute->options()->create([
                    'value' => $value,
                    'image_url' => $imageUrl
                ]);
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
