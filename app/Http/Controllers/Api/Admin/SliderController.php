<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Slider;
use Illuminate\Http\Request;
use App\Services\CloudinaryService;

class SliderController extends Controller
{
    protected $cloudinaryService;

    public function __construct(CloudinaryService $cloudinaryService)
    {
        $this->cloudinaryService = $cloudinaryService;
    }

    public function index()
    {
        return response()->json(Slider::orderBy('sort_order')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'image' => 'required|image|max:5120', // Max 5MB
            'link_url' => 'nullable|string',
            'sort_order' => 'nullable|integer',
        ]);

        if ($request->hasFile('image')) {
            $url = $this->cloudinaryService->upload($request->file('image'), 'sabay-shop/sliders');
            if ($url) {
                $validated['image_url'] = $url;
            }
        }

        $slider = Slider::create($validated);
        return response()->json($slider, 201);
    }

    public function update(Request $request, $id)
    {
        $slider = Slider::findOrFail($id);

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'image' => 'nullable|image|max:5120',
            'link_url' => 'nullable|string',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean'
        ]);

        if ($request->hasFile('image')) {
            if ($slider->image_url) {
                $this->cloudinaryService->delete($slider->image_url);
            }
            $url = $this->cloudinaryService->upload($request->file('image'), 'sabay-shop/sliders');
            if ($url) {
                $validated['image_url'] = $url;
            }
        } elseif ($request->boolean('remove_image')) {
            if ($slider->image_url) {
                $this->cloudinaryService->delete($slider->image_url);
            }
            $slider->image_url = null;
            $slider->save();
        }

        $slider->update($validated);
        return response()->json($slider);
    }

    public function destroy($id)
    {
        $slider = Slider::findOrFail($id);
        $slider->delete();
        return response()->json(['message' => 'Slider deleted']);
    }

    /**
     * Public method to get active sliders
     */
    public function getActive()
    {
        return response()->json(Slider::where('is_active', true)->orderBy('sort_order')->get());
    }
}
