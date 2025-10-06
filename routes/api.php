<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\ContactTagController;
use App\Http\Controllers\ReminderController;

/**
 * AUTH (public)
 */
Route::prefix('auth')->group(function () {
  Route::post('register', [AuthController::class,'register']);
  Route::post('login',    [AuthController::class,'login']);

  // Magic login: đổi code -> token (chỉ dùng nếu verify + login bằng ?login=1)
  Route::post('magic/exchange', [AuthController::class,'magicExchange']);

  Route::post('password/request', [AuthController::class, 'passwordRequest']);
  Route::post('password/resend',  [AuthController::class, 'passwordResend']);
  Route::post('password/verify',  [AuthController::class, 'passwordVerify']);
});

/**
 * EMAIL VERIFICATION
 * - Stateless: chỉ cần chữ ký 'signed', không yêu cầu đang đăng nhập.
 * - Có thể thêm ?login=1 để verify + tạo magic code (FE đổi code lấy token).
 */
Route::get('email/verify/{id}/{hash}', [AuthController::class,'verifyEmail'])
  ->middleware(['signed'])
  ->name('verification.verify');


// Kiểm tra đã verify chưa (cần đăng nhập)
Route::middleware('auth:sanctum')->get('email/verified', function (Request $r) {
  return ['verified' => (bool) $r->user()->hasVerifiedEmail()];
});

// Gửi lại email verify (cần đăng nhập, throttle)
Route::middleware(['auth:sanctum','throttle:6,1'])
  ->post('email/verification-notification', [AuthController::class, 'resendVerification']);

/**
 * API CHÍNH — yêu cầu ĐĂNG NHẬP + ĐÃ VERIFY
 */
Route::middleware(['auth:sanctum','verified'])->group(function () {
  // Contacts CRUD
  Route::apiResource('contacts', ContactController::class);

  // Tags + attach/detach
  Route::apiResource('tags', TagController::class)->only(['index','store','update','destroy']);
  Route::post('contacts/{contact}/tags', [ContactTagController::class,'attach']);
  Route::delete('contacts/{contact}/tags/{tag}', [ContactTagController::class,'detach']);

  // Reminders
  Route::apiResource('reminders', ReminderController::class)->only(['index','store','update','destroy']);
  Route::post('reminders/{reminder}/done', [ReminderController::class,'markDone']);
});

/**
 * Tuỳ chọn: cho phép lấy profile / logout không cần verified
 */
Route::middleware('auth:sanctum')->group(function () {
  Route::get('auth/me',      [AuthController::class,'me']);
  Route::post('auth/logout', [AuthController::class,'logout']);
});
