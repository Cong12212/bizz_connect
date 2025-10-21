<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Database\QueryException;
use Fruitcake\Cors\HandleCors; 

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

       
        $middleware->append(HandleCors::class); 

        // Alias middleware cho API
        $middleware->alias([
            'verified' => EnsureEmailIsVerified::class,
        ]);

        $middleware->alias([
            'plus' => \App\Http\Middleware\EnsurePlus::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        // Force all errors under /api/* to return JSON (avoid 302/HTML)
        $exceptions->shouldRenderJsonWhen(
            fn ($request, $e) => $request->is('api/*')
        );

        // Customize DB error messages (e.g. missing Sanctum table)
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
