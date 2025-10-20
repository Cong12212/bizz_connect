<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;        // ← THÊM DÒNG NÀY
use Illuminate\Support\Carbon;
use Carbon\CarbonImmutable;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $tz = config('app.timezone', 'UTC');

        // Force https khi chạy production trên Render
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        // Chuẩn hoá serialize ngày giờ
        Carbon::serializeUsing(fn (Carbon $c) => $c->copy()->setTimezone($tz)->toIso8601String());
        CarbonImmutable::serializeUsing(fn (CarbonImmutable $c) => $c->setTimezone($tz)->toIso8601String());
    }
}
