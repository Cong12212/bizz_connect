<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Cache;
use Illuminate\Auth\Events\Verified;
use App\Notifications\PasswordResetCode;
use Illuminate\Support\Str;
use App\Models\User;

class AuthController extends Controller
{
    // Register: create user + send verification email
    public function register(Request $r)
    {
        $data = $r->validate([
            'name'     => 'required|string|max:100',
            'email'    => 'required|email|unique:users',
            'password' => 'required|string|min:6',
        ]);

        $u = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        try { $u->sendEmailVerificationNotification(); }
        catch (\Throwable $e) { \Log::warning('Send verify email failed: '.$e->getMessage()); }

        return response()->json([
            'token'    => $u->createToken('api')->plainTextToken,
            'message'  => 'Registered. Verification email sent (check inbox or logs).',
            'verified' => (bool) $u->hasVerifiedEmail(),
        ], 201);
    }

    // Login
   public function login(Request $r)
{
    $r->validate(['email' => 'required|email', 'password' => 'required']);
    $u = User::where('email', $r->email)->first();

    if (!$u || !Hash::check($r->password, $u->password)) {
        return response()->json(['message' => 'Invalid credentials'], 401);
    }

    return response()->json([
        'token'    => $u->createToken('api')->plainTextToken,
        'verified' => (bool) $u->hasVerifiedEmail(),
        'user'     => $u, 
    ]);
}


    public function me(Request $r)      { return $r->user(); }
    public function logout(Request $r)  { $r->user()->currentAccessToken()?->delete(); return ['ok'=>true]; }

    // Resend verification email
    public function resendVerification(Request $r)
    {
        if ($r->user()->hasVerifiedEmail()) {
            return response()->json(['message' => 'Already verified']);
        }
        $r->user()->sendEmailVerificationNotification();
        return response()->json(['message' => 'Verification link sent']);
    }

    /**
     * VERIFY EMAIL - STATELESS (no login required)
     * Support ?login=1 for "verify + login":
     *   - If login=1 and you want to return to FE with one-time code (secure): return redirect with #code=...
     *   - (Optional) If you want to return token JSON directly, set $issueTokenDirect = true;
     */
public function verifyEmail(Request $request, $id, $hash)
{
    if (! \Illuminate\Support\Facades\URL::hasValidSignature($request)) {
        return response()->json(['message' => 'Invalid or expired link'], 400);
    }

    $user = \App\Models\User::findOrFail($id);
    if (! hash_equals(sha1($user->getEmailForVerification()), (string) $hash)) {
        return response()->json(['message' => 'Invalid verification hash'], 400);
    }

    if (! $user->hasVerifiedEmail()) {
        $user->markEmailAsVerified();
        event(new \Illuminate\Auth\Events\Verified($user));
    }

    // ➜ Redirect to FE success page (with email if you want to display)
    $fe = rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/');
    return redirect()->away($fe . '/verify-success?email=' . urlencode($user->email));
}



    // Exchange magic code -> token (one-time use)
    public function magicExchange(Request $r)
    {
        $data = $r->validate(['code' => 'required|string']);
        $cacheKey = 'magic:'.$data['code'];

        $userId = Cache::pull($cacheKey); // get and delete -> one-time use only
        if (! $userId) {
            return response()->json(['message' => 'Invalid or expired code'], 400);
        }

        $user  = User::findOrFail($userId);
        // (Optional) set TTL for token: now()->addMinutes(60)
        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'token'    => $token,
            'verified' => (bool) $user->hasVerifiedEmail(),
        ]);
    }

    public function passwordRequest(Request $r)
{
    $data = $r->validate([
        'email'        => 'required|email',
        'new_password' => 'required|string|min:6|max:100',
    ]);

    $user = User::where('email', $data['email'])->first();
    // Prevent email enumeration: still return OK message
    if (!$user) return response()->json(['message' => 'If the email exists, a code has been sent'], 200);

    $code = (string) random_int(100000, 999999); // 6 digits
    $ttl  = 10; // minutes

    $cacheKey = 'pwreset:'.$user->id;
    Cache::put($cacheKey, [
        'code' => $code,
        'hash' => Hash::make($data['new_password']), // store HASH of new password
    ], now()->addMinutes($ttl));

    try {
        $user->notify(new PasswordResetCode($code, $ttl));
    } catch (\Throwable $e) {
        \Log::warning('Send reset code failed: '.$e->getMessage());
    }

    return response()->json(['message' => 'Verification code sent if the email exists'], 200);
}

/**
 * (Optional) B1b: resend old code / new code
 * body: { email }
 */
public function passwordResend(Request $r)
{
    $data = $r->validate(['email' => 'required|email']);
    $user = User::where('email', $data['email'])->first();
    if (!$user) return response()->json(['message' => 'Verification code re-sent if the email exists'], 200);

    $cacheKey = 'pwreset:'.$user->id;
    $payload = Cache::get($cacheKey);
    if (!$payload) return response()->json(['message' => 'No pending reset. Please start again.'], 400);

    // Generate new code for security
    $code = (string) random_int(100000, 999999);
    $payload['code'] = $code;
    Cache::put($cacheKey, $payload, now()->addMinutes(10));

    try { $user->notify(new PasswordResetCode($code, 10)); } catch (\Throwable $e) {}
    return response()->json(['message' => 'Verification code re-sent'], 200);
}

/**
 * B2: Verify code & change password (PUBLIC, throttle)
 * body: { email, code }
 */
public function passwordVerify(Request $r)
{
    $data = $r->validate([
        'email' => 'required|email',
        'code'  => 'required|digits:6',
    ]);

    $user = User::where('email', $data['email'])->firstOrFail();

    $cacheKey = 'pwreset:'.$user->id;
    $payload  = Cache::get($cacheKey);

    if (!$payload) {
        return response()->json(['message' => 'Code invalid or expired'], 400);
    }

    if ((string)$payload['code'] !== (string)$data['code']) {
        return response()->json(['message' => 'Invalid code'], 400);
    }

    // Update password: use stored HASH
    $user->password = $payload['hash'];
    $user->save();

    // Invalidate code + (recommended) revoke old tokens
    Cache::forget($cacheKey);
    try { $user->tokens()->delete(); } catch (\Throwable $e) {}

    return response()->json(['message' => 'Password has been reset. Please log in with your new password.'], 200);
}

public function updateMe(Request $r) {
  $u = $r->user();
  $data = $r->validate([
    'name' => 'sometimes|string|max:100',
    'email'=> 'sometimes|email|unique:users,email,'.$u->id,
    'password' => 'sometimes|string|min:6',
  ]);
  if (isset($data['password'])) $data['password'] = \Hash::make($data['password']);
  $u->fill($data)->save();
  return $u;
}
}