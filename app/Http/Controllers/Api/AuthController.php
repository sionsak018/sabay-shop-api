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

class AuthController extends Controller
{
public function login(LoginRequest $request)
{
    // The "email" field doubles as the login identifier: phone or email.
    $login = $request->email;
    $digits = preg_replace('/\D/', '', $login);

    $user = User::where('email', $login)
        ->orWhere('phone', $login)
        ->orWhere('phone', $digits)
        ->first();

    if (!$user || empty($user->password) || !Hash::check($request->password, $user->password)) {
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
        // Only sent by password recovery: the Google identity that comes back
        // must be the account the customer actually looked up.
        'login' => ['sometimes', 'nullable', 'string', 'max:255'],
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

    $expected = trim((string) $request->input('login', ''));

    if ($expected !== '') {
        return $this->recoverGoogleAccount($expected, $googleId, $email);
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
        // Repair the email-prefix fallback name from an earlier Google sign-up
        // without clobbering a name the user has chosen.
        if (!empty($payload['name'])
            && (!$user->name || $user->name === Str::before($user->email, '@'))) {
            $user->name = $payload['name'];
        }
        $user->save();
    } else {
        $user = User::create([
            'name' => $payload['name'] ?? Str::before($email, '@'),
            'email' => $email,
            'google_id' => $googleId,
            // Google customers are passwordless: Google is their only way in.
            'password' => null,
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
 * Password recovery for a Google account.
 *
 * Google's account chooser lets the customer pick *any* of their accounts, so
 * the credential is only accepted when its verified email matches the account
 * they asked to recover. Recovery also never creates accounts: it can only
 * return the session of an account that already exists.
 */
protected function recoverGoogleAccount(string $expected, string $googleId, string $email)
{
    $digits = preg_replace('/\D/', '', $expected);

    $user = User::where('email', $expected)->orWhere('phone', $expected)->first();

    if (!$user && $digits !== '') {
        $user = User::where('phone', $digits)->first();
    }

    if (!$user) {
        return response()->json(['message' => "No account found for {$expected}."], 404);
    }

    if (strcasecmp((string) $email, (string) $user->email) !== 0) {
        return response()->json([
            'message' => "You are signed in as {$email}, which is not {$expected}. Please choose the matching Google account.",
        ], 422);
    }

    if (empty($user->google_id)) {
        $user->google_id = $googleId;
        $user->save();
    }

    return response()->json([
        'user' => $user->load('roles.permissions'),
        'token' => $user->createToken('auth_token')->plainTextToken,
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

        // tokeninfo verifies the signature but frequently omits the profile
        // claims (name, picture) even when the profile scope was granted. Those
        // claims are still inside the signed ID token, so decode its payload
        // and merge them in. tokeninfo values win on overlap.
        $claims = $this->decodeJwtPayload($credential);

        return array_merge($claims, $data);
    });
}

/**
 * Decode the payload of a JWT without verifying it. Only used after the token
 * has already been validated by Google's tokeninfo endpoint.
 */
protected function decodeJwtPayload(string $jwt): array
{
    $parts = explode('.', $jwt);

    if (count($parts) !== 3) {
        return [];
    }

    $payload = strtr($parts[1], '-_', '+/');
    $remainder = strlen($payload) % 4;

    if ($remainder) {
        $payload .= str_repeat('=', 4 - $remainder);
    }

    $decoded = base64_decode($payload, true);

    if ($decoded === false) {
        return [];
    }

    $claims = json_decode($decoded, true);

    return is_array($claims) ? $claims : [];
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

    // Google-only customers sign in with Google and never hold a password, so
    // the client uses this to hide the password forms entirely.
    $userData['has_password'] = !empty($user->password);
    $userData['auth_provider'] = !empty($user->google_id) && empty($user->password) ? 'google' : 'password';

    return response()->json($userData);
}
}
