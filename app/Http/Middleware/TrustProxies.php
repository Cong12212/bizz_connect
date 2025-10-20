<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    // Tin các proxy phía trước (Render đứng trước Nginx/PHP-FPM)
    protected $proxies = '*';

    // Tôn trọng headers X-Forwarded-* để nhận biết HTTPS
    protected $headers = Request::HEADER_X_FORWARDED_ALL;
    // (Hoặc dùng phép OR như bạn đang có cũng được)
}
