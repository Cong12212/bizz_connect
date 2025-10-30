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

/**
 * MAIN API — requires LOGIN + VERIFIED
 */
Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    // Export/Import placed BEFORE {contact} routes
    Route::match(['GET', 'POST'], '/contacts/export', [ContactController::class, 'export']);
    Route::post('/contacts/import', [ContactController::class, 'import']);
    // If you want public template, move outside group; if protected, keep here.
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

    Route::get('/tags', [TagController::class, 'index']);
    Route::post('/tags', [TagController::class, 'store']);
    Route::put('/tags/{tag}', [TagController::class, 'update'])->whereNumber('tag');
    Route::delete('/tags/{tag}', [TagController::class, 'destroy'])->whereNumber('tag');

    Route::get('/reminders', [ReminderController::class, 'index']);
    Route::post('/reminders', [ReminderController::class, 'store']);
    Route::get('/reminders/{reminder}', [ReminderController::class, 'show'])->whereNumber('reminder');
    Route::patch('/reminders/{reminder}', [ReminderController::class, 'update'])->whereNumber('reminder');
    Route::delete('/reminders/{reminder}', [ReminderController::class, 'destroy'])->whereNumber('reminder');

    // Add missing routes
    Route::post('/reminders/{reminder}/done', [ReminderController::class, 'markDone'])->whereNumber('reminder');
    Route::post('/reminders/{reminder}/contacts', [ReminderController::class, 'attachContacts'])->whereNumber('reminder');
    Route::delete('/reminders/{reminder}/contacts/{contact}', [ReminderController::class, 'detachContact'])->whereNumber('reminder')->whereNumber('contact');

    Route::post('/reminders/bulk-status', [ReminderController::class, 'bulkStatus']);
    Route::post('/reminders/bulk-delete', [ReminderController::class, 'bulkDelete']);
    Route::get('/reminders/pivot', [ReminderController::class, 'pivotIndex']);

    // Add route for reminders by contact
    Route::get('/contacts/{contact}/reminders', [ReminderController::class, 'byContact'])->whereNumber('contact');

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->whereNumber('notification');
    Route::post('/notifications/{notification}/done', [NotificationController::class, 'markDone'])->whereNumber('notification');
    Route::post('/notifications/bulk-read', [NotificationController::class, 'bulkRead']);
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->whereNumber('notification');

    // Company routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('companies', App\Http\Controllers\CompanyController::class);
        Route::apiResource('business-cards', App\Http\Controllers\BusinessCardController::class);
    });

    // Public business card routes (no auth required)
    Route::get('business-card/public/{slug}', [App\Http\Controllers\BusinessCardController::class, 'showPublic']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('company', [App\Http\Controllers\CompanyController::class, 'show']);
        Route::post('company', [App\Http\Controllers\CompanyController::class, 'store']);
        Route::delete('company', [App\Http\Controllers\CompanyController::class, 'destroy']);

        Route::get('business-card', [App\Http\Controllers\BusinessCardController::class, 'show']);
        Route::post('business-card', [App\Http\Controllers\BusinessCardController::class, 'store']);
        Route::delete('business-card', [App\Http\Controllers\BusinessCardController::class, 'destroy']);
        Route::post('business-card/connect/{slug}', [App\Http\Controllers\BusinessCardController::class, 'connect']);
    });

    // Public location routes (no auth required)
    Route::get('countries', [App\Http\Controllers\LocationController::class, 'countries']);
    Route::get('countries/{code}/states', [App\Http\Controllers\LocationController::class, 'states']);
    Route::get('states/{code}/cities', [App\Http\Controllers\LocationController::class, 'cities']);
});

// ✅ Handle OPTIONS preflight
Route::options('{any}', function () {
    return response('', 200)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
})->where('any', '.*');
