<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        \App\Console\Commands\GenerateUpcomingNotifications::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        // Mỗi phút quét N phút tới (mặc định 10) để sinh notification upcoming
        $schedule->command('notifications:generate-upcoming --minutes=10')
            ->everyMinute()
            ->onOneServer()
            ->withoutOverlapping()
            ->runInBackground()
            ->timezone(config('app.timezone', 'UTC'));
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
