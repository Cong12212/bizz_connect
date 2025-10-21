<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(base_path())
    ->withMiddleware(function (Middleware $middleware) {
        // Bật CORS toàn cục
        $middleware->append(\Fruitcake\Cors\HandleCors::class);
    })
    ->withRouting(
        web: base_path('routes/web.php'),
        api: base_path('routes/api.php'),
        commands: base_path('routes/console.php'),
        health: '/up',
    )
    ->create();
