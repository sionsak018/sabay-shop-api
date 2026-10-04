<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
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

/**
 * Sign in (or sign up) a customer using a Google Identity Services ID token.
 *
 * The frontend Google button returns a signed JWT "credential"; we exchange it
 * with Google's tokeninfo endpoint (which verifies the signature and expiry)
 * and then match or create the local account by Google ID / email.
 */
public function google(Request $request)
{
    $request->validate([
        'credential' => ['required', 'string'],
    ]);

    $payload = $this->verifyGoogleToken($request->input('credential'));

    if (!$payload) {
        return response()->json(['message' => 'Invalid Google credential'], 401);
    }

    $googleId = $payload['sub'] ?? null;
    $email = $payload['email'] ?? null;

    if (!$googleId || !$email) {
        return response()->json(['message' => 'Google account is missing required profile data'], 422);
    }

    $user = User::where('google_id', $googleId)
        ->orWhere('email', $email)
        ->first();

    if ($user) {
        // Link the Google identity to an existing email/password account and
        // trust Google's verified email flag.
        if (!$user->google_id) {
            $user->google_id = $googleId;
        }
        if (!$user->email_verified_at) {
            $user->email_verified_at = now();
        }
        if (empty($user->avatar) && !empty($payload['picture'])) {
            $user->avatar = $payload['picture'];
        }
        $user->save();
    } else {
        $user = User::create([
            'name' => $payload['name'] ?? Str::before($email, '@'),
            'email' => $email,
            'google_id' => $googleId,
            'password' => Hash::make(Str::random(40)),
            'avatar' => $payload['picture'] ?? null,
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();
    }

    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'user' => $user->load('roles.permissions'),
        'token' => $token
    ]);
}

/**
 * Verify a Google ID token and return its claims, or null when invalid.
 */
protected function verifyGoogleToken(string $credential): ?array
{
    $clientId = config('services.google.client_id');

    if (!$clientId) {
        return null;
    }

    // Cache valid claims briefly so repeated logins with the same token do not
    // hit Google on every request. Null results are not cached.
    return Cache::remember('google_id_token:' . sha1($credential), now()->addMinutes(5), function () use ($credential, $clientId) {
        try {
            $response = Http::timeout(10)->acceptJson()->get(
                'https://oauth2.googleapis.com/tokeninfo',
                ['id_token' => $credential]
            );
        } catch (\Throwable $e) {
            \Log::warning('Google token verification failed: ' . $e->getMessage());
            return null;
        }

        if (!$response->ok()) {
            return null;
        }

        $data = $response->json();

        if (!is_array($data)) {
            return null;
        }

        if (($data['aud'] ?? null) !== $clientId) {
            return null;
        }

        if (!in_array($data['iss'] ?? null, ['accounts.google.com', 'https://accounts.google.com'], true)) {
            return null;
        }

        if (!filter_var($data['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        if (isset($data['exp']) && (int) $data['exp'] < time()) {
            return null;
        }

        return $data;
    });
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
