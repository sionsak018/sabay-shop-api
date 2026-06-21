<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
{
    $user = User::create([
        'name' => $request->name,
        'email' => $request->email,
        'password' => Hash::make($request->password),
        'phone' => $request->phone,
    ]);

    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'user' => $user->load('roles.permissions'),
        'token' => $token
    ], 201);
}

public function login(LoginRequest $request)
{
    $user = User::where('email', $request->email)->first();

    if (!$user || !Hash::check($request->password, $user->password)) {
        return response()->json(['message' => 'Invalid credentials'], 401);
    }

    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'user' => $user->load('roles.permissions'),
        'token' => $token
    ]);
}

public function logout(Request $request)
{
    $request->user()->currentAccessToken()->delete();
    return response()->json(['message' => 'Logged out']);
}

public function profile(Request $request)
{
    $user = $request->user()->load(['province', 'district', 'commune', 'village', 'roles.permissions']);

    // Convert to array and add stats explicitly to ensure they are in the JSON
    $userData = $user->toArray();
    $userData['ads_count'] = $user->products()->where('status', 'active')->count();
    $userData['followers_count'] = $user->followers()->count();
    $userData['following_count'] = $user->following()->count();

    return response()->json($userData);
}
}
