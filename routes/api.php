<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContactController;

/**
 * AUTH (public)
 */
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class,'register']);
    Route::post('login',    [AuthController::class,'login']);
    Route::post('magic/exchange', [AuthController::class,'magicExchange']);

    Route::post('password/request', [AuthController::class, 'passwordRequest']);
    Route::post('password/resend',  [AuthController::class, 'passwordResend']);
    Route::post('password/verify',  [AuthController::class, 'passwordVerify']);
});

Route::get('email/verify/{id}/{hash}', [AuthController::class,'verifyEmail'])
    ->middleware(['signed'])
    ->name('verification.verify');

Route::middleware('auth:sanctum')->get('email/verified', fn (Request $r) => [
    'verified' => (bool) $r->user()->hasVerifiedEmail(),
]);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('auth/me',      [AuthController::class,'me']);
    Route::post('auth/logout', [AuthController::class,'logout']);
});

/**
 * API CHÍNH — yêu cầu ĐĂNG NHẬP + ĐÃ VERIFY
 */
Route::middleware(['auth:sanctum','verified'])->group(function () {
    // Export/Import đặt TRƯỚC các route {contact}
    Route::match(['GET','POST'], '/contacts/export', [ContactController::class, 'export']);
    Route::post('/contacts/import', [ContactController::class, 'import']);
    // Nếu muốn public template thì đưa ra ngoài group; nếu muốn bảo vệ thì để ở đây.
    Route::get('/contacts/export-template', [ContactController::class, 'exportTemplate']);

    // CRUD
    Route::get('/contacts', [ContactController::class, 'index']);
    Route::post('/contacts', [ContactController::class, 'store']);

    Route::get('/contacts/{contact}', [ContactController::class, 'show'])->whereNumber('contact');
    Route::put('/contacts/{contact}', [ContactController::class, 'update'])->whereNumber('contact');
    Route::delete('/contacts/{contact}', [ContactController::class, 'destroy'])->whereNumber('contact');

    Route::post('/contacts/{contact}/tags', [ContactController::class, 'attachTags'])->whereNumber('contact');
    Route::delete('/contacts/{contact}/tags/{tag}', [ContactController::class, 'detachTag'])
        ->whereNumber('contact')->whereNumber('tag');
});

// 👉 BỎ dòng dưới (đang bị trùng) nếu bạn để template trong group ở trên.
// Route::get('contacts/export-template', [ContactController::class, 'exportTemplate']);
