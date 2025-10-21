<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
       
        if (! Schema::hasTable('subscriptions')) {
            Schema::create('subscriptions', function (Blueprint $t) {
                $t->id();
                $t->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
                $t->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();

                $t->string('plan', 100);
                $t->string('status', 50);

                $t->timestamp('current_period_start')->nullable();
                $t->timestamp('current_period_end')->nullable();

                $t->timestamps();

             
                $t->index(['plan', 'status'], 'subscriptions_plan_status_index');
                $t->index('current_period_end', 'subscriptions_current_period_end_index');
                $t->unique(['company_id','user_id','plan','status'], 'uniq_owner_plan_status');
            });

          
            DB::statement(<<<'SQL'
                ALTER TABLE `subscriptions`
                ADD CONSTRAINT chk_one_owner
                CHECK (
                    (company_id IS NOT NULL AND user_id IS NULL)
                    OR (company_id IS NULL AND user_id IS NOT NULL)
                )
            SQL);
            return;
        }

     
        Schema::table('subscriptions', function (Blueprint $t) {
            if (! Schema::hasColumn('subscriptions', 'current_period_end')) {
                $t->timestamp('current_period_end')->nullable();
            }

         
            $t->index(['plan','status'], 'subscriptions_plan_status_index');
            $t->index('current_period_end', 'subscriptions_current_period_end_index');
            $t->unique(['company_id','user_id','plan','status'], 'uniq_owner_plan_status');
        });

       
        try {
            DB::statement(<<<'SQL'
                ALTER TABLE `subscriptions`
                ADD CONSTRAINT chk_one_owner
                CHECK (
                    (company_id IS NOT NULL AND user_id IS NULL)
                    OR (company_id IS NULL AND user_id IS NOT NULL)
                )
            SQL);
        } catch (\Throwable $e) {
           
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('subscriptions')) {
            try { DB::statement('ALTER TABLE `subscriptions` DROP CONSTRAINT chk_one_owner'); } catch (\Throwable $e) {}

            Schema::table('subscriptions', function (Blueprint $t) {
               
                $t->dropUnique('uniq_owner_plan_status');
                $t->dropIndex('subscriptions_plan_status_index');
                $t->dropIndex('subscriptions_current_period_end_index');
            });

          
        }
    }
};
