<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

class ConfigController extends Controller
{
    public function index()
    {
        return response()->json([
            'site_name' => 'Sabay Shop',
            'contact_email' => 'support@sabayshop.com',
            'maintenance_mode' => false,
        ]);
    }
}
