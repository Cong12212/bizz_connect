<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Database\QueryException; // <-- THÊM

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Alias middleware cho API
        $middleware->alias([
            'verified' => EnsureEmailIsVerified::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        // Ép mọi lỗi dưới /api/* trả về JSON (tránh 302/HTML)
        $exceptions->shouldRenderJsonWhen(
            fn ($request, $e) => $request->is('api/*')
        );

        // Tuỳ biến thông báo lỗi DB (ví dụ thiếu bảng Sanctum)
        $exceptions->render(function (QueryException $e, $request) {
            if ($request->is('api/*')) {
                $msg = str_contains($e->getMessage(), 'personal_access_tokens')
                    ? 'Thiếu bảng Sanctum. Chạy: php artisan migrate'
                    : 'Lỗi cơ sở dữ liệu.';
                return response()->json(['message' => $msg], 500);
            }
        });
    })
    ->create();
