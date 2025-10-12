<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('subscriptions', function (Blueprint $t) {
            $t->index(['plan', 'status']);
            $t->index(['current_period_end']);
            $t->unique(['company_id','user_id','plan','status'], 'uniq_owner_plan_status');
        });

        // Enforce exactly-one owner (company XOR user)
        DB::statement(<<<SQL
            ALTER TABLE subscriptions
            ADD CONSTRAINT chk_one_owner
            CHECK (
                (company_id IS NOT NULL AND user_id IS NULL)
                OR (company_id IS NULL AND user_id IS NOT NULL)
            )
        SQL);
    }

    public function down(): void {
        try { DB::statement('ALTER TABLE subscriptions DROP CONSTRAINT chk_one_owner'); } catch (\Throwable $e) {}
        Schema::table('subscriptions', function (Blueprint $t) {
            $t->dropUnique('uniq_owner_plan_status');
            $t->dropIndex(['plan','status']);
            $t->dropIndex(['current_period_end']);
        });
    }
};
