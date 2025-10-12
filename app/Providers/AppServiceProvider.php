<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Carbon;
use Carbon\CarbonImmutable;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $tz = config('app.timezone', 'UTC');

        // Ép toàn bộ date trong JSON về TZ của app, không còn "Z"
        Carbon::serializeUsing(function (Carbon $c) use ($tz) {
            return $c->copy()->setTimezone($tz)->toIso8601String(); // 2025-10-12T21:17:00+07:00
        });

        CarbonImmutable::serializeUsing(function (CarbonImmutable $c) use ($tz) {
            return $c->setTimezone($tz)->toIso8601String();
        });
    }
}
