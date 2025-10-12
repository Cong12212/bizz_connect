<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillCompanyPlansToSubscriptions extends Command
{
    protected $signature = 'subs:backfill-company-plans';
    protected $description = 'Create company-level subscriptions from companies.plan (non-free values).';

    public function handle(): int
    {
        $count = 0;
        DB::transaction(function() use (&$count) {
            Company::whereNotNull('plan')->where('plan','!=','free')
                ->orderBy('id')->chunk(500, function($companies) use (&$count) {
                    foreach ($companies as $c) {
                        $exists = Subscription::where('company_id', $c->id)
                            ->active()
                            ->exists();
                        if ($exists) continue;

                        Subscription::create([
                            'company_id' => $c->id,
                            'plan'       => $c->plan, // map nếu bạn đổi tên gói
                            'status'     => 'active',
                            'current_period_start' => now(),
                            'current_period_end'   => now()->addMonth(),
                        ]);
                        $count++;
                    }
                });
        });

        $this->info("Backfilled {$count} company subscriptions.");
        return self::SUCCESS;
    }
}
