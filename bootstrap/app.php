<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Database\QueryException;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

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
        // ✅ Force render JSON cho API
        $exceptions->shouldRenderJsonWhen(function ($request, $e) {
            return $request->is('api/*') ||
                $request->expectsJson() ||
                $request->wantsJson();
        });

        // ✅ Xử lý ValidationException
        $exceptions->render(function (ValidationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        // ✅ Xử lý QueryException
        $exceptions->render(function (QueryException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $msg = str_contains($e->getMessage(), 'personal_access_tokens')
                    ? 'Missing Sanctum table. Run: php artisan migrate'
                    : 'Database error.';
                return response()->json(['message' => $msg], 500);
            }
        });

        // ✅ Xử lý HttpException (có getStatusCode)
        $exceptions->render(function (HttpException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage() ?: 'Server Error',
                    'error' => class_basename($e),
                ], $e->getStatusCode());
            }
        });

        // ✅ Xử lý tất cả exceptions khác (KHÔNG có getStatusCode)
        $exceptions->render(function (\Throwable $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                // ✅ FIX: Kiểm tra xem exception có method getStatusCode không
                $statusCode = ($e instanceof HttpException) ? $e->getStatusCode() : 500;

                return response()->json([
                    'message' => $e->getMessage() ?: 'Server Error',
                    'error' => class_basename($e),
                ], $statusCode);
            }
        });
    })
    ->create();
