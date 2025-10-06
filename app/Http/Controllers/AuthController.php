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
    // Đăng ký: tạo user + gửi mail verify
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

    // Đăng nhập
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

    // Gửi lại email verify
    public function resendVerification(Request $r)
    {
        if ($r->user()->hasVerifiedEmail()) {
            return response()->json(['message' => 'Already verified']);
        }
        $r->user()->sendEmailVerificationNotification();
        return response()->json(['message' => 'Verification link sent']);
    }

    /**
     * VERIFY EMAIL - STATELESS (không yêu cầu đang đăng nhập)
     * Hỗ trợ thêm ?login=1 để "verify + login":
     *   - Nếu login=1 và bạn muốn về FE với mã 1 lần (an toàn): trả về redirect kèm #code=...
     *   - (Tuỳ chọn) Nếu muốn trả token JSON trực tiếp, đổi $issueTokenDirect = true;
     */
    // app/Http/Controllers/AuthController.php
// app/Http/Controllers/AuthController.php
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

    // ➜ về FE trang báo thành công (kèm email nếu muốn hiển thị)
    $fe = rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/');
    return redirect()->away($fe . '/verify-success?email=' . urlencode($user->email));
}



    // Đổi magic code -> token (dùng 1 lần)
    public function magicExchange(Request $r)
    {
        $data = $r->validate(['code' => 'required|string']);
        $cacheKey = 'magic:'.$data['code'];

        $userId = Cache::pull($cacheKey); // lấy và xoá -> chỉ dùng 1 lần
        if (! $userId) {
            return response()->json(['message' => 'Invalid or expired code'], 400);
        }

        $user  = User::findOrFail($userId);
        // (Tuỳ chọn) đặt TTL cho token: now()->addMinutes(60)
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
    // Tránh lộ thông tin tồn tại email: vẫn trả về message OK
    if (!$user) return response()->json(['message' => 'If the email exists, a code has been sent'], 200);

    $code = (string) random_int(100000, 999999); // 6 số
    $ttl  = 10; // phút

    $cacheKey = 'pwreset:'.$user->id;
    Cache::put($cacheKey, [
        'code' => $code,
        'hash' => Hash::make($data['new_password']), // lưu HASH của mật khẩu mới
    ], now()->addMinutes($ttl));

    try {
        $user->notify(new PasswordResetCode($code, $ttl));
    } catch (\Throwable $e) {
        \Log::warning('Send reset code failed: '.$e->getMessage());
    }

    return response()->json(['message' => 'Verification code sent if the email exists'], 200);
}

/**
 * (tuỳ chọn) B1b: gửi lại mã cũ / mã mới
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

    // Phát mã mới để an toàn
    $code = (string) random_int(100000, 999999);
    $payload['code'] = $code;
    Cache::put($cacheKey, $payload, now()->addMinutes(10));

    try { $user->notify(new PasswordResetCode($code, 10)); } catch (\Throwable $e) {}
    return response()->json(['message' => 'Verification code re-sent'], 200);
}

/**
 * B2: Xác minh mã & đổi mật khẩu (PUBLIC, throttle)
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

    // cập nhật mật khẩu: dùng HASH đã lưu
    $user->password = $payload['hash'];
    $user->save();

    // Huỷ mã + (khuyến nghị) revoke token cũ
    Cache::forget($cacheKey);
    try { $user->tokens()->delete(); } catch (\Throwable $e) {}

    return response()->json(['message' => 'Password has been reset. Please log in with your new password.'], 200);
}
}
