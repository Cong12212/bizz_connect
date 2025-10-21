<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/_test-mail', function () {
    try {
        Mail::raw('Hello from BizConnect on Render!', function ($m) {
            $m->to('congglpro2547@gmail.com')   // thay email thử nhận
              ->subject('SMTP smoke test');
        });
        return response('OK - sent', 200);
    } catch (\Throwable $e) {
        Log::error('MAIL ERROR: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
        return response('ERROR: '.$e->getMessage(), 500);
    }
});
