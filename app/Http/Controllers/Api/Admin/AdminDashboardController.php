<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Product;
use App\Models\Order;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function index()
    {
        return response()->json([
            'stats' => [
                'total_users' => User::count(),
                'total_products' => Product::count(),
                'total_orders' => Order::count(),
                'revenue' => Order::where('status', 'paid')->sum('total_amount'),
            ],
            'recent_products' => Product::with('seller', 'category')->latest()->take(5)->get(),
            'recent_users' => User::latest()->take(5)->get(),
        ]);
    }
}
