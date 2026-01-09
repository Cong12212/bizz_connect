<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\ContactTagController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\BusinessCardController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\AiGuideController;

/**
 * AUTH (public)
 */
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login',    [AuthController::class, 'login']);
    Route::post('magic/exchange', [AuthController::class, 'magicExchange']);

    Route::post('password/request', [AuthController::class, 'passwordRequest']);
    Route::post('password/resend',  [AuthController::class, 'passwordResend']);
    Route::post('password/verify',  [AuthController::class, 'passwordVerify']);
});

Route::get('email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware(['signed'])
    ->name('verification.verify');

Route::middleware('auth:sanctum')->get('email/verified', fn(Request $r) => [
    'verified' => (bool) $r->user()->hasVerifiedEmail(),
]);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('auth/me',      [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::patch('auth/me', [AuthController::class, 'updateMe']);

    Route::post('email/verification-notification', [AuthController::class, 'resendVerification']);
});

// ✅ AI GUIDE ROUTES - PUBLIC (NO AUTH REQUIRED)
// Đặt NGOÀI middleware auth
Route::prefix('guides')->group(function () {
    Route::post('/ask', [AiGuideController::class, 'ask']);
    Route::post('/ask-stream', [AiGuideController::class, 'askStream']);

    // Knowledge base endpoints
    Route::get('/categories', [AiGuideController::class, 'getCategories']);
    Route::get('/category/{category}', [AiGuideController::class, 'getByCategory']);
    Route::get('/knowledge/{key}', [AiGuideController::class, 'getKnowledge']);
    Route::get('/popular', [AiGuideController::class, 'getPopular']);
    Route::get('/search', [AiGuideController::class, 'search']);
});

// ✅ Public business card routes (no auth required)
Route::get('business-card/public/{slug}', [BusinessCardController::class, 'showPublic']);

// ✅ Public location routes (no auth required)
Route::get('countries', [LocationController::class, 'countries']);
Route::get('countries/{code}/states', [LocationController::class, 'states']);
Route::get('states/{code}/cities', [LocationController::class, 'cities']);

/**
 * MAIN API — requires LOGIN + VERIFIED
 */
Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::prefix('contacts')->group(function () {
        Route::get('export', [ContactController::class, 'export']);
        Route::get('export-template', [ContactController::class, 'exportTemplate']);
        Route::post('import', [ContactController::class, 'import']);
        Route::post('bulk-delete', [ContactController::class, 'bulkDelete']);
    });

    // CRUD
    Route::get('/contacts', [ContactController::class, 'index']);
    Route::post('/contacts', [ContactController::class, 'store']);

    Route::get('/contacts/{contact}', [ContactController::class, 'show'])->whereNumber('contact');
    Route::put('/contacts/{contact}', [ContactController::class, 'update'])->whereNumber('contact');
    Route::delete('/contacts/{contact}', [ContactController::class, 'destroy'])->whereNumber('contact');

    Route::post('/contacts/{contact}/tags', [ContactController::class, 'attachTags'])->whereNumber('contact');
    Route::delete('/contacts/{contact}/tags/{tag}', [ContactController::class, 'detachTag'])
        ->whereNumber('contact')->whereNumber('tag');

    Route::get('/tags', [TagController::class, 'index']);
    Route::post('/tags', [TagController::class, 'store']);
    Route::put('/tags/{tag}', [TagController::class, 'update'])->whereNumber('tag');
    Route::delete('/tags/{tag}', [TagController::class, 'destroy'])->whereNumber('tag');

    Route::get('/reminders', [ReminderController::class, 'index']);
    Route::post('/reminders', [ReminderController::class, 'store']);
    Route::get('/reminders/{reminder}', [ReminderController::class, 'show'])->whereNumber('reminder');
    Route::patch('/reminders/{reminder}', [ReminderController::class, 'update'])->whereNumber('reminder');
    Route::delete('/reminders/{reminder}', [ReminderController::class, 'destroy'])->whereNumber('reminder');

    Route::post('/reminders/{reminder}/done', [ReminderController::class, 'markDone'])->whereNumber('reminder');
    Route::post('/reminders/{reminder}/contacts', [ReminderController::class, 'attachContacts'])->whereNumber('reminder');
    Route::delete('/reminders/{reminder}/contacts/{contact}', [ReminderController::class, 'detachContact'])
        ->whereNumber('reminder')->whereNumber('contact');

    Route::post('/reminders/bulk-status', [ReminderController::class, 'bulkStatus']);
    Route::post('/reminders/bulk-delete', [ReminderController::class, 'bulkDelete']);
    Route::get('/reminders/pivot', [ReminderController::class, 'pivotIndex']);

    Route::get('/contacts/{contact}/reminders', [ReminderController::class, 'byContact'])->whereNumber('contact');

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->whereNumber('notification');
    Route::post('/notifications/{notification}/done', [NotificationController::class, 'markDone'])->whereNumber('notification');
    Route::post('/notifications/bulk-read', [NotificationController::class, 'bulkRead']);
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->whereNumber('notification');

    // Company routes
    Route::apiResource('companies', CompanyController::class);
    Route::apiResource('business-cards', BusinessCardController::class);

    Route::get('company', [CompanyController::class, 'show']);
    Route::post('company', [CompanyController::class, 'store']);
    Route::delete('company', [CompanyController::class, 'destroy']);

    Route::get('business-card', [BusinessCardController::class, 'show']);
    Route::post('business-card', [BusinessCardController::class, 'store']);
    Route::delete('business-card', [BusinessCardController::class, 'destroy']);
    Route::post('business-card/connect/{slug}', [BusinessCardController::class, 'connect']);
});

// ✅ Handle OPTIONS preflight
Route::options('{any}', function () {
    return response('', 200)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
})->where('any', '.*');
