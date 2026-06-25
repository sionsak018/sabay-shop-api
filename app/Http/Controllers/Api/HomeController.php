<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\Slider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class HomeController extends Controller
{
    public function index()
    {
        // We use Cache to make it load in milliseconds after the first hit
        return Cache::remember('home_page_data', 600, function () {
            return response()->json([
                'sliders' => Slider::where('is_active', true)->orderBy('sort_order')->get(),
                'categories' => Category::all(),
                'recent_products' => Product::with([
                    'seller:id,name,avatar',
                    'category:id,name',
                    'images' => function($q) {
                        $q->select('id', 'product_id', 'image_url')->orderBy('sort_order', 'asc')->limit(1);
                    },
                    'province:id,name'
                ])
                ->where('status', 'active')
                ->latest()
                ->limit(20)
                ->get()
                ->makeHidden(['description']) // Remove heavy text
            ]);
        });
    }
}
