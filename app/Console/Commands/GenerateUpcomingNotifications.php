<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Reminder;
use App\Models\UserNotification;
use Carbon\Carbon;

class GenerateUpcomingNotifications extends Command
{
    // Chuyển sang minutes + hỗ trợ --now để giả lập thời gian test
    protected $signature = 'notifications:generate-upcoming 
                            {--minutes=10 : Khoảng phút sắp tới để quét}
                            {--now= : ISO datetime để test (mặc định now())}';

    protected $description = 'Create reminder.upcoming notifications for reminders due within the next N minutes.';

    public function handle(): int
    {
        $leadMinutes = (int) $this->option('minutes') ?: 10;
        $now = $this->option('now') ? Carbon::parse($this->option('now')) : now();
        $to  = (clone $now)->addMinutes($leadMinutes);

        // Lọc reminder sắp đến hạn, còn pending
        $reminders = Reminder::query()
            ->whereNotNull('due_at')
            ->where('status', 'pending')
            ->whereBetween('due_at', [$now, $to])
            ->orderBy('due_at')
            ->get();

        $count = 0;

        foreach ($reminders as $r) {
            // Chống trùng: đã tạo upcoming cho reminder này với scheduled_at = due_at thì bỏ qua
            $exists = UserNotification::where('owner_user_id', $r->owner_user_id)
                ->where('type', 'reminder.upcoming')
                ->where('reminder_id', $r->id)
                ->where('scheduled_at', $r->due_at)
                ->exists();

            if ($exists) continue;

            $minsLeft = max(0, (int) ceil(($r->due_at->timestamp - $now->timestamp) / 60));

            UserNotification::log($r->owner_user_id, [
                'type'         => 'reminder.upcoming',
                'title'        => 'Reminder upcoming',
                'body'         => $minsLeft > 0 ? "{$minsLeft} minute(s) left" : 'Due now',
                'data'         => ['reminder_id' => $r->id, 'minutes_left' => $minsLeft],
                'reminder_id'  => $r->id,
                'scheduled_at' => $r->due_at,
            ]);

            $count++;
        }

        $this->info("Created {$count} upcoming notifications (lead={$leadMinutes}m).");
        return self::SUCCESS;
    }
}
