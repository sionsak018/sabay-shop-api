<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class HomeController extends Controller
{
    /**
     * Public marketplace totals for the storefront trust/hero sections.
     */
    public function stats()
    {
        $stats = Cache::remember('stats.public', now()->addMinutes(5), function () {
            return [
                'total_products' => Product::where('status', 'active')->count(),
                'total_users' => User::count(),
                'total_categories' => Category::count(),
            ];
        });

        return response()->json($stats);
    }
}
