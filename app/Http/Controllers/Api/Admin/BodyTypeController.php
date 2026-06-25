<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\BodyType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Services\CloudinaryService;

class BodyTypeController extends Controller
{
    protected $cloudinaryService;

    public function __construct(CloudinaryService $cloudinaryService)
    {
        $this->cloudinaryService = $cloudinaryService;
    }

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
            $url = $this->cloudinaryService->upload($request->file('image'), 'sabay-shop/body_types');
            if ($url) {
                $validated['image_url'] = $url;
            }
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
                $this->cloudinaryService->delete($bodyType->image_url);
            }
            $url = $this->cloudinaryService->upload($request->file('image'), 'sabay-shop/body_types');
            if ($url) {
                $bodyType->image_url = $url;
            }
        } elseif ($request->boolean('remove_image')) {
            if ($bodyType->image_url) {
                $this->cloudinaryService->delete($bodyType->image_url);
            }
            $bodyType->image_url = null;
        }

        unset($validated['image']);
        $bodyType->fill($validated);
        $bodyType->save();

        return response()->json($bodyType);
    }

    public function destroy($id)
    {
        $bodyType = BodyType::findOrFail($id);
        $bodyType->delete();
        return response()->json(['message' => 'Body type deleted']);
    }
}
