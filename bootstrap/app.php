<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Database\QueryException;
use Illuminate\Http\Middleware\HandleCors;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // ✅ Thêm CORS middleware vào API
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        // Alias middleware cho API của bạn
        $middleware->alias([
            'verified' => EnsureEmailIsVerified::class,
            'plus'     => \App\Http\Middleware\EnsurePlus::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn ($request, $e) => $request->is('api/*'));
        $exceptions->render(function (QueryException $e, $request) {
            if ($request->is('api/*')) {
                $msg = str_contains($e->getMessage(), 'personal_access_tokens')
                    ? 'Missing Sanctum table. Run: php artisan migrate'
                    : 'Database error.';
                return response()->json(['message' => $msg], 500);
            }
        });
    })
    ->create();
